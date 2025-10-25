<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Query_Filters_Indexer {
	const INDEXER_OPTION_KEY = 'bricks_indexer_running';
	/**
	 * The one and only Query_Filters_Indexer instance
	 *
	 * @var Query_Filters_Indexer
	 */
	private static $instance = null;
	private $query_filters;

	public function __construct() {
		$this->query_filters = Query_Filters::get_instance();

		if ( Helpers::enabled_query_filters() ) {
			// Register hooks
			$this->register_hooks();
		}
	}

	/**
	 * Singleton - Get the instance of this class
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new Query_Filters_Indexer();
		}

		return self::$instance;
	}

	// Register hooks
	public function register_hooks() {
		// A new cron interval every 5 minutes
		add_filter( 'cron_schedules', [ $this, 'add_cron_interval' ] );

		// Schedule the cron job
		add_action( 'init', [ $this, 'schedule_cron_job' ] );

		// Index query filters every 5 minutes
		add_action( 'bricks_indexer', [ $this, 'continue_index_jobs' ] );

		// Add a new job for an element
		add_action( 'wp_ajax_bricks_background_index_job', [ $this, 'background_index_job' ] );
	}

	/**
	 * Add a new cron interval every 5 minutes
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['brx_every_five_minutes'] = [
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'bricks' )
		];

		return $schedules;
	}

	/**
	 * Schedule the cron job
	 *
	 * @since 2.1
	 */
	public function schedule_cron_job() {
		if ( ! wp_next_scheduled( 'bricks_indexer' ) ) {
			wp_schedule_event( time(), 'brx_every_five_minutes', 'bricks_indexer' );
		}
	}

	/**
	 * Check if the indexer is running: To avoid multiple indexer and incorrect indexing
	 */
	public function indexer_is_running() {
		$flag     = (bool) get_option( self::INDEXER_OPTION_KEY, false );
		$next_job = $this->get_next_job();

		// If the indexer is running but no job is found, set the indexer status to false (maybe orphan job in previous run)
		if ( $flag && ! $next_job ) {
			$this->update_indexer_status( false );
			return false;
		}

		// Check if the flag is correctly set
		if ( $flag && $next_job ) {
			$job_updated_at = strtotime( $next_job['job_updated_at'] );
			$now            = time();
			$diff           = $now - $job_updated_at;
			$is_orphan_job  = isset( $next_job['filter_id'] ) ? false : true;

			// If the job is not updated for more than 30 minutes, it's abnormal. Orphan job is also considered as abnormal. Set the indexer status to false
			if ( $diff > 1800 || $is_orphan_job ) {
				$this->update_indexer_status( false );
				return false;
			}
		}

		return $flag;
	}

	/**
	 * Update the indexer status
	 */
	private function update_indexer_status( $running = false ) {
		update_option( self::INDEXER_OPTION_KEY, (bool) $running, false );
	}

	/**
	 * Retrieve jobs from the database, and continue indexing them
	 * Should be run every 5 minutes, might be triggered manually via do_action( 'bricks_indexer' )
	 * Will not do anything if the indexer is already running to avoid multiple indexer and incorrect indexing
	 */
	public function continue_index_jobs() {
		// Remove orphan jobs (@since 1.10.2)
		$this->remove_orphan_jobs();

		if ( $this->indexer_is_running() ) {
			// Indexer is running, do nothing
			return;
		}

		// Set the indexer status to running
		$this->update_indexer_status( true );

		while ( $job = $this->get_next_job() ) {
			// Check if server resource limits are reached
			if ( self::resource_limit_reached() ) {
				break;
			}

			// Index the job
			$this->execute_job( $job );
		} // End while

		// Set the indexer status to false, to be triggered again
		$this->update_indexer_status( false );
	}

	/**
	 * Trigger bricks_indexer action
	 * Should be called via wp_remote_post
	 */
	public function background_index_job() {
		Ajax::verify_nonce( 'bricks-nonce-indexer' );
		do_action( 'bricks_indexer' );
		wp_die();
	}

	/**
	 * Execute a job
	 *
	 * @param array $job
	 */
	private function execute_job( $job ) {
		// Get the latest element settings
		$filter_settings = $job['settings'] ?? false;
		$job_settings    = $job['job_details'] ?? false;
		$filter_id       = $job['filter_id'] ?? false;
		$filter_status   = $job['status'] ?? false;

		// If the filter status is not active, or required data is missing, remove the job
		if ( ! $filter_status || ! $filter_id || ! $filter_settings || ! $job_settings ) {
			// Remove the job
			$this->remove_job( $job );
			// Remove indexed records for this element
			$this->query_filters->remove_index_rows(
				[
					'filter_id' => $filter_id,
				]
			);
			return;
		}

		// If the settings not same, remove the job and add a new job
		if ( $filter_settings !== $job_settings ) {
			// Something not right, need to remove the job, remove indexed records for this element and add a new job
			$this->remove_job( $job );
			// Remove indexed records for this element
			$this->query_filters->remove_index_rows(
				[
					'filter_id' => $filter_id,
				]
			);
			// Add a new job
			$this->add_job( $job, true );
			return;
		}

		$filter_settings = json_decode( $filter_settings, true );
		$filter_source   = $filter_settings['filterSource'] ?? false;

		// If the filter source is not valid, remove the job
		if ( ! $filter_source ) {
			// Invalid filter source, remove the job
			$this->remove_job( $job );
			// Remove indexed records for this element
			$this->query_filters->remove_index_rows(
				[
					'filter_id' => $filter_id,
				]
			);
			return;
		}

		// Validate job settings
		if ( ! self::validate_job_settings( $filter_source, $filter_settings ) ) {
			// Invalid job settings, remove the job
			$this->remove_job( $job );
			// Remove indexed records for this element
			$this->query_filters->remove_index_rows(
				[
					'filter_id' => $filter_id,
				]
			);
			return;
		}

		// 'customField' source can choose term or user
		$field_type = $filter_settings['sourceFieldType'] ?? 'post';

		// Default query type
		$query_type = 'wp_query';

		// Determine the query type based on filter_source and field_type
		if ( $filter_source === 'customField' || $filter_source === 'wpField' ) {
			if ( $field_type === 'term' ) {
				$query_type = 'wp_term_query';
			} elseif ( $field_type === 'user' ) {
				$query_type = 'wp_user_query';
			}
		}

		// Ready to execute the job, for WPML to switch the language (@since 1.12.2)
		do_action( 'bricks_execute_filter_index_job', $job );

		$total          = $job['total'] ?? 0;
		$processing_row = $job['processed'] ?? 0;

		// Get index args
		$args = self::get_index_args( $filter_source, $filter_settings, $query_type );

		if ( $query_type === 'wp_query' ) {

			// STEP: Start the index process, each time index 100 posts and update the job
			while ( $processing_row < $total ) {
				if ( self::resource_limit_reached() ) {
					// Resource limits reached, stop indexing, update the processing row and exit
					$this->update_job( $job, [ 'processed' => $processing_row ] );
					break;
				}

				// Get 100 posts
				$args['posts_per_page'] = 100;
				$args['offset']         = $processing_row;
				if ( $filter_source === 'wpField' ) {
					// We need the whole post object
					unset( $args['fields'] );
				}
				$query = new \WP_Query( $args );
				$posts = $query->posts;

				/**
				 * No posts found for current processing row
				 *
				 * Maybe some posts are deleted while indexing.
				 *
				 * @since 1.11
				 */
				if ( empty( $posts ) ) {
					// STEP: Check the total posts again for current args without offset
					$args['posts_per_page'] = -1;
					$args['offset']         = 0;
					$query                  = new \WP_Query( $args );
					$new_total              = count( $query->posts );

					// Release memory
					unset( $query );

					if ( $processing_row >= $new_total ) {
						// Processing row is more than or equal to the new total, consider the job as completed
						$total = $new_total;
					} else {
						// Something not right, remove the job
						$this->remove_job( $job );
						// Remove indexed records for this element
						$this->query_filters->remove_index_rows(
							[
								'filter_id' => $filter_id,
							]
						);
						// Add a new job
						$this->add_job( $job, true );
					}

					// Exit while loop
					break;
				}

				// Index the posts
				foreach ( $posts as $post ) {
					if ( self::resource_limit_reached() ) {
						// Resource limits reached, stop indexing, update the processing row and exit
						$this->update_job( $job, [ 'processed' => $processing_row ] );
						break;
					}

					// Index the post
					$this->index_post_by_job( $post, $job );

					$processing_row++;
				}

				// Update the job
				$this->update_job( $job, [ 'processed' => $processing_row ] );

				// Release memory
				unset( $query );
				unset( $posts );
			}

		}

		elseif ( $query_type === 'wp_term_query' ) {

			// STEP: Start the index process, each time index 100 terms and update the job
			while ( $processing_row < $total ) {
				if ( self::resource_limit_reached() ) {
					// Resource limits reached, stop indexing, update the processing row and exit
					$this->update_job( $job, [ 'processed' => $processing_row ] );
					break;
				}

				// Get 100 terms
				$args['number'] = 100;
				$args['offset'] = $processing_row;
				if ( $filter_source === 'wpField' ) {
					// We need the whole term object
					unset( $args['fields'] );
				}

				$terms = new \WP_Term_Query( $args );
				$terms = $terms->get_terms();

				/**
				 * No terms found for current processing row
				 *
				 * Maybe some terms are deleted while indexing.
				 */
				if ( empty( $terms ) ) {
					// STEP: Check the total terms again for current args without offset
					$args['number'] = 0;
					$args['offset'] = 0;
					$terms          = new \WP_Term_Query( $args );
					$terms          = $terms->get_terms();
					$new_total      = count( $terms );

					// Release memory
					unset( $terms );

					if ( $processing_row >= $new_total ) {
						// Processing row is more than or equal to the new total, consider the job as completed
						$total = $new_total;
					} else {
						// Something not right, remove the job
						$this->remove_job( $job );
						// Remove indexed records for this element
						$this->query_filters->remove_index_rows(
							[
								'filter_id' => $filter_id,
							]
						);
						// Add a new job
						$this->add_job( $job, true );
					}
				}

				// Index the terms
				foreach ( $terms as $term ) {
					if ( self::resource_limit_reached() ) {
						// Resource limits reached, stop indexing, update the processing row and exit
						$this->update_job( $job, [ 'processed' => $processing_row ] );
						break;
					}

					// Index the term
					$this->index_term_by_job( $term, $job );

					$processing_row++;
				}

				// Release memory
				unset( $terms );
			}
		}

		elseif ( $query_type === 'wp_user_query' ) {

			// STEP: Start the index process, each time index 100 users and update the job
			while ( $processing_row < $total ) {
				if ( self::resource_limit_reached() ) {
					// Resource limits reached, stop indexing, update the processing row and exit
					$this->update_job( $job, [ 'processed' => $processing_row ] );
					break;
				}

				// Get 100 users
				$args['number'] = 100;
				$args['offset'] = $processing_row;
				if ( $filter_source === 'wpField' ) {
					// We need the whole user object
					unset( $args['fields'] );
				}
				$users = new \WP_User_Query( $args );
				$users = $users->get_results();

				/**
				 * No users found for current processing row
				 *
				 * Maybe some users are deleted while indexing.
				 */
				if ( empty( $users ) ) {
					// STEP: Check the total users again for current args without offset
					$args['number'] = -1;
					$args['offset'] = 0;
					$users          = new \WP_User_Query( $args );
					$users          = $users->get_results();
					$new_total      = count( $users );

					// Release memory
					unset( $users );

					if ( $processing_row >= $new_total ) {
						// Processing row is more than or equal to the new total, consider the job as completed
						$total = $new_total;
					} else {
						// Something not right, remove the job
						$this->remove_job( $job );
						// Remove indexed records for this element
						$this->query_filters->remove_index_rows(
							[
								'filter_id' => $filter_id,
							]
						);
						// Add a new job
						$this->add_job( $job, true );
					}
				}

				// Index the users
				foreach ( $users as $user ) {
					if ( self::resource_limit_reached() ) {
						// Resource limits reached, stop indexing, update the processing row and exit
						$this->update_job( $job, [ 'processed' => $processing_row ] );
						break;
					}

					// Index the user
					$this->index_user_by_job( $user, $job );

					$processing_row++;
				}

				// Release memory
				unset( $users );
			}
		}

		// STEP: Job completed
		if ( $processing_row >= $total ) {
			$this->complete_job( $job );
		}
	}

	/**
	 * Validate job settings
	 */
	private static function validate_job_settings( $filter_source, $filter_settings ) {
		switch ( $filter_source ) {
			case 'wpField':
				$field_type = $filter_settings['sourceFieldType'] ?? 'post';

				if ( ! $field_type ) {
					return false;
				}

				$selected_field = false;
				switch ( $field_type ) {
					case 'post':
						$selected_field = $filter_settings['wpPostField'] ?? false;
						break;

					case 'user':
						$selected_field = $filter_settings['wpUserField'] ?? false;
						break;

					case 'term':
						$selected_field = $filter_settings['wpTermField'] ?? false;
						break;
				}

				if ( ! $selected_field ) {
					return false;
				}

				break;

			case 'customField':
				$meta_key = $filter_settings['customFieldKey'] ?? false;

				if ( ! $meta_key ) {
					return false;
				}

				break;

			case 'taxonomy':
				$filter_taxonomy = $filter_settings['filterTaxonomy'] ?? false;

				if ( ! $filter_taxonomy ) {
					return false;
				}

				break;

			default:
			case 'unknown':
				// Undocumented (WooCommerce)
				$validate = (bool) apply_filters( 'bricks/query_filters_indexer/validate_job_settings', false, $filter_source, $filter_settings );
				return $validate;

				break;
		}

		return true;
	}

	/**
	 * Get index args for the job
	 */
	private static function get_index_args( $filter_source = '', $filter_settings = [], $query_type = 'wp_query' ) {
		$args = [];

		if ( $query_type === 'wp_query' ) {
			// NOTE: 'exclude_from_search' => false not in use as we might miss some post types which are excluded from search
			$post_types = array_diff(
				get_post_types(),
				[ 'revision', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request' ], // Not necessary to index these post types as nobody will use filters on these (@since 1.12.2)
			);

			$args = [
				'post_type'        => $post_types,
				'post_status'      => 'any', // cannot use 'publish' as we might miss some posts
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'cache_results'    => false,
				'no_found_rows'    => true,
				'suppress_filters' => true, // Avoid filters (@since 1.9.10)
				'lang'             => '', // Polylang (@since 1.9.10)
			];

			switch ( $filter_source ) {
				case 'wpField':
					$field_type = $filter_settings['sourceFieldType'] ?? 'post';

					if ( ! $field_type ) {
						return;
					}

					$selected_field = false;
					switch ( $field_type ) {
						case 'post':
							$selected_field = $filter_settings['wpPostField'] ?? false;
							break;

						case 'user':
							// not implemented
							break;

						case 'term':
							// not implemented
							break;
					}

					if ( ! $selected_field ) {
						return;
					}

					// No need to add any query for wpField, all posts will be indexed

					break;

				case 'customField':
					$meta_key = $filter_settings['customFieldKey'] ?? false;

					if ( ! $meta_key ) {
						return;
					}

					// Add meta query
					$args['meta_query'] = [
						[
							'key'     => $meta_key,
							'compare' => 'EXISTS'
						],
					];

					break;

				case 'taxonomy':
					$filter_taxonomy = $filter_settings['filterTaxonomy'] ?? false;

					if ( ! $filter_taxonomy ) {
						return;
					}

					// Add taxonomy query
					$args['tax_query'] = [
						[
							'taxonomy' => $filter_taxonomy,
							'operator' => 'EXISTS'
						],
					];
					break;
			}

		}

		elseif ( $query_type === 'wp_term_query' ) {
			$args = [
				'hide_empty'    => false,
				'number'        => 0,
				'offset'        => 0,
				'fields'        => 'ids',
				'orderby'       => 'id',
				'cache_results' => false,
				'lang'          => '',
			];
		}

		elseif ( $query_type === 'wp_user_query' ) {
			$args = [
				'number'  => -1,
				'offset'  => 0,
				'fields'  => 'ID',
				'orderby' => 'ID',
			];
		}

		// NOTE: Undocumented - Not ready for third-party plugins (@since 1.11.1)
		return apply_filters( 'bricks/query_filters/index_args', $args, $filter_source, $filter_settings, $query_type );
	}

	/**
	 * Generate index rows for a post based on a job (a filter element)
	 * $post: post id || post object (wpField source only)
	 */
	private function index_post_by_job( $post, $job ) {
		$filter_settings = $job['settings'] ?? false;
		$job_settings    = $job['job_details'] ?? false;
		$filter_id       = $job['filter_id'] ?? false;

		if ( ! $filter_id || ! $filter_settings || ! $job_settings ) {
			return;
		}

		$filter_settings = json_decode( $filter_settings, true );
		$filter_source   = $filter_settings['filterSource'] ?? false;

		if ( ! $filter_source ) {
			return;
		}

		$rows_to_insert = [];

		switch ( $filter_source ) {
			case 'wpField':
				$field_type = $filter_settings['sourceFieldType'] ?? 'post';

				if ( ! $field_type ) {
					return;
				}

				$selected_field = false;
				switch ( $field_type ) {
					case 'post':
						$selected_field = $filter_settings['wpPostField'] ?? false;
						break;

					case 'user':
						// not implemented
						break;

					case 'term':
						// not implemented
						break;
				}

				if ( ! $selected_field ) {
					return;
				}

				$post_rows = $this->query_filters::generate_post_field_index_rows( $post, $selected_field );

				// Build $rows_to_insert, insert filter_id
				if ( ! empty( $post_rows ) ) {
					$rows_to_insert = array_merge(
						$rows_to_insert,
						array_map(
							function( $row ) use ( $filter_id ) {
								$row['filter_id'] = $filter_id;
								return $row;
							},
							$post_rows
						)
					);
				}

				break;

			case 'customField':
				$meta_key = $filter_settings['customFieldKey'] ?? false;

				if ( ! $meta_key ) {
					return;
				}

				$provider = $filter_settings['fieldProvider'] ?? 'none';

				// Centralize the function (@since 1.11.1)
				$meta_rows = $this->query_filters::generate_custom_field_index_rows( $post, $meta_key, $provider, 'post' );

				// Build $rows_to_insert, insert filter_id
				if ( ! empty( $meta_rows ) ) {
					$rows_to_insert = array_merge(
						$rows_to_insert,
						array_map(
							function( $row ) use ( $filter_id ) {
								$row['filter_id'] = $filter_id;
								return $row;
							},
							$meta_rows
						)
					);
				}

				break;

			case 'taxonomy':
				$taxonomy = $filter_settings['filterTaxonomy'] ?? false;

				if ( ! $taxonomy ) {
					return;
				}

				$tax_rows = $this->generate_taxonomy_index_row( $post, $taxonomy );

				// Build $rows_to_insert, insert filter_id
				if ( ! empty( $tax_rows ) ) {
					$rows_to_insert = array_merge(
						$rows_to_insert,
						array_map(
							function( $row ) use ( $filter_id ) {
								$row['filter_id'] = $filter_id;
								return $row;
							},
							$tax_rows
						)
					);
				}
				break;

			default:
			case 'unknown':
				// Undocumented (WooCommerce)
				$rows_to_insert = apply_filters( 'bricks/query_filters_indexer/post/' . $filter_source, [], $post, $filter_id, $filter_settings );
				break;
		}

		if ( empty( $rows_to_insert ) ) {
			return;
		}

		// Insert rows
		$this->query_filters::insert_index_rows( $rows_to_insert );
	}

	/**
	 * Generate index rows for a term based on a job (a filter element)
	 * $term: term object
	 */
	private function index_term_by_job( $term, $job ) {
		$filter_settings = $job['settings'] ?? false;
		$job_settings    = $job['job_details'] ?? false;
		$filter_id       = $job['filter_id'] ?? false;

		if ( ! $filter_id || ! $filter_settings || ! $job_settings ) {
			return;
		}

		$filter_settings = json_decode( $filter_settings, true );
		$filter_source   = $filter_settings['filterSource'] ?? false;

		if ( ! $filter_source ) {
			return;
		}

		$rows_to_insert = [];

		switch ( $filter_source ) {
			case 'wpField':
				// Support filter Term Query by term ID (@since 2.0)
				$field_type = $filter_settings['sourceFieldType'] ?? 'post';

				if ( ! $field_type || $field_type !== 'term' ) {
					return;
				}

				$selected_field = $filter_settings['wpTermField'] ?? false;

				if ( ! $selected_field ) {
					return;
				}

				$term_rows = $this->query_filters::generate_term_field_index_rows( $term, $selected_field );

				// Build $rows_to_insert, insert filter_id
				if ( ! empty( $term_rows ) ) {
					$rows_to_insert = array_merge(
						$rows_to_insert,
						array_map(
							function( $row ) use ( $filter_id ) {
								$row['filter_id'] = $filter_id;
								return $row;
							},
							$term_rows
						)
					);
				}

				break;

			case 'customField':
				$meta_key = $filter_settings['customFieldKey'] ?? false;

				if ( ! $meta_key ) {
					return;
				}

				$provider = $filter_settings['fieldProvider'] ?? 'none';

				$meta_rows = $this->query_filters::generate_custom_field_index_rows( $term, $meta_key, $provider, 'term' );

				// Build $rows_to_insert, insert filter_id
				if ( ! empty( $meta_rows ) ) {
					$rows_to_insert = array_merge(
						$rows_to_insert,
						array_map(
							function( $row ) use ( $filter_id ) {
								$row['filter_id'] = $filter_id;
								return $row;
							},
							$meta_rows
						)
					);
				}

				break;

			default:
			case 'taxonomy':
				break;

		}

		if ( empty( $rows_to_insert ) ) {
			return;
		}

		// Insert rows
		$this->query_filters::insert_index_rows( $rows_to_insert );
	}

	/**
	 * Generate index rows for a user based on a job (a filter element)
	 * $user: user id || user object (wpField source only)
	 */
	private function index_user_by_job( $user, $job ) {
		$filter_settings = $job['settings'] ?? false;
		$job_settings    = $job['job_details'] ?? false;
		$filter_id       = $job['filter_id'] ?? false;

		if ( ! $filter_id || ! $filter_settings || ! $job_settings ) {
			return;
		}

		$filter_settings = json_decode( $filter_settings, true );
		$filter_source   = $filter_settings['filterSource'] ?? false;

		if ( ! $filter_source ) {
			return;
		}

		$rows_to_insert = [];

		switch ( $filter_source ) {
			case 'wpField':
				$field_type = $filter_settings['sourceFieldType'] ?? 'post';

				if ( ! $field_type || $field_type !== 'user' ) {
					return;
				}

				$selected_field = $filter_settings['wpUserField'] ?? false;

				if ( ! $selected_field ) {
					return;
				}

				$user_rows = $this->query_filters::generate_user_field_index_rows( $user, $selected_field );

				// Build $rows_to_insert, insert filter_id
				if ( ! empty( $user_rows ) ) {
					$rows_to_insert = array_merge(
						$rows_to_insert,
						array_map(
							function( $row ) use ( $filter_id ) {
								$row['filter_id'] = $filter_id;
								return $row;
							},
							$user_rows
						)
					);
				}

				break;

			case 'customField':
				$meta_key = $filter_settings['customFieldKey'] ?? false;

				if ( ! $meta_key ) {
					return;
				}

				$provider = $filter_settings['fieldProvider'] ?? 'none';

				$meta_rows = $this->query_filters::generate_custom_field_index_rows( $user, $meta_key, $provider, 'user' );

				// Build $rows_to_insert, insert filter_id
				if ( ! empty( $meta_rows ) ) {
					$rows_to_insert = array_merge(
						$rows_to_insert,
						array_map(
							function( $row ) use ( $filter_id ) {
								$row['filter_id'] = $filter_id;
								return $row;
							},
							$meta_rows
						)
					);
				}
				break;
		}

		if ( empty( $rows_to_insert ) ) {
			return;
		}

		// Insert rows
		$this->query_filters::insert_index_rows( $rows_to_insert );
	}

	/**
	 * Generate taxonomy index rows
	 */
	private function generate_taxonomy_index_row( $post_id, $taxonomy ) {
		$rows = [];

		$terms = get_the_terms( $post_id, $taxonomy );
		// If no terms, skip
		if ( ! $terms || is_wp_error( $terms ) ) {
			return $rows;
		}

		foreach ( $terms as $term ) {
			// Populate rows
			$rows[] = [
				'filter_id'            => '',
				'object_id'            => $post_id,
				'object_type'          => 'post',
				'filter_value'         => $term->slug,
				'filter_value_display' => $term->name,
				'filter_value_id'      => $term->term_id,
				'filter_value_parent'  => $term->parent ?? 0,
			];
		}

		return $rows;
	}

	/**
	 * Add index job for an element
	 * Condition:
	 * - If active job exists, do nothing
	 */
	public function add_job( $element, $remove_active_jobs = false ) {
		$element_id = $element['filter_id'] ?? false;
		$db_row_id  = $element['id'] ?? false;

		if ( ! $element_id || ! $db_row_id ) {
			return;
		}

		// Get active job for this element
		$active_job = $this->get_active_job_for_element( $element_id );

		if ( $active_job && $remove_active_jobs ) {
			// Remove active job for this element if requested
			$this->remove_job( $active_job );
		} elseif ( $active_job ) {
			// exit if active job exists and removal is not requested
			return;
		}

		$filter_settings = json_decode( $element['settings'], true ) ?? false;
		$filter_source   = $filter_settings['filterSource'] ?? false;

		if ( ! $filter_settings || ! $filter_source ) {
			return;
		}

		// Validate job settings
		if ( ! self::validate_job_settings( $filter_source, $filter_settings ) ) {
			return;
		}

		// 'customField' source can choose term or user
		$field_type = $filter_settings['sourceFieldType'] ?? 'post';

		// Default query type
		$query_type = 'wp_query';

		// Determine the query type based on filter_source and field_type
		if ( $filter_source === 'customField' || $filter_source === 'wpField' ) {
			if ( $field_type === 'term' ) {
				$query_type = 'wp_term_query';
			} elseif ( $field_type === 'user' ) {
				$query_type = 'wp_user_query';
			}
		}

		// Get index args
		$args       = self::get_index_args( $filter_source, $filter_settings, $query_type );
		$total_rows = 0;

		// Get total rows
		if ( ! empty( $args ) ) {
			if ( $query_type === 'wp_query' ) {
				$query      = new \WP_Query( $args );
				$total_rows = count( $query->posts );
				// Release memory
				unset( $query );
			}

			elseif ( $query_type === 'wp_term_query' ) {
				$term_query = new \WP_Term_Query( $args );
				$terms      = $term_query->get_terms();
				$total_rows = count( $terms );

				// Release memory
				unset( $term_query );
				unset( $terms );
			}

			elseif ( $query_type === 'wp_user_query' ) {
				$user_query = new \WP_User_Query( $args );
				$total_rows = $user_query->get_total();

				// Release memory
				unset( $user_query );
			}
		}

		if ( $total_rows === 0 ) {
			return;
		}

		// Create a new job
		$job_data = [
			'filter_row_id' => $db_row_id, // Use the db row id
			'job_details'   => $element['settings'], // Store the settings without json_decode
			'total'         => $total_rows,
			'processed'     => 0,
		];

		$this->create_index_job( $job_data );
	}

	/**
	 * Create index job in index_job table
	 */
	private function create_index_job( $job_data ) {
		global $wpdb;

		$table_name = $this->query_filters::get_table_name( 'index_job' );

		$wpdb->insert( $table_name, $job_data );
	}


	/**
	 * Get next job from index_job table, left join with element table
	 */
	private function get_next_job() {
		global $wpdb;

		$job_table     = $this->query_filters::get_table_name( 'index_job' );
		$element_table = $this->query_filters::get_table_name( 'element' );

		$job = $wpdb->get_row( "SELECT * FROM {$job_table} LEFT JOIN {$element_table} ON {$job_table}.filter_row_id = {$element_table}.id ORDER BY {$job_table}.job_created_at ASC LIMIT 1", ARRAY_A );

		// Convert total, processed to integer
		if ( $job ) {
			$job['total']     = (int) $job['total'];
			$job['processed'] = (int) $job['processed'];
		}
		return $job;
	}

	/**
	 * Get active job for an element
	 */
	public function get_active_job_for_element( $filter_id ) {
		// Use sql to get the job rows, left join index_job.filter_row_id with element.id where element.filter_id = $filter_id
		global $wpdb;

		$job_table     = $this->query_filters::get_table_name( 'index_job' );
		$element_table = $this->query_filters::get_table_name( 'element' );

		$query = "SELECT * FROM {$job_table} LEFT JOIN {$element_table} ON {$job_table}.filter_row_id = {$element_table}.id WHERE {$element_table}.filter_id = %s";

		$job = $wpdb->get_row( $wpdb->prepare( $query, $filter_id ), ARRAY_A );

		return $job;
	}

	/**
	 * Update job
	 */
	private function update_job( $job, $data ) {
		global $wpdb;

		$id = $job['job_id'] ?? false;

		if ( ! $id ) {
			return;
		}

		$job_table = $this->query_filters::get_table_name( 'index_job' );

		$wpdb->update( $job_table, $data, [ 'job_id' => $id ] );
	}

	/**
	 * Complete job
	 */
	private function complete_job( $job ) {
		$id = $job['job_id'] ?? false;
		if ( ! $id ) {
			return;
		}

		// TODO: Maybe update a flag on the actual filter row to indicate that the indexing is completed, currently just remove the job
		$this->remove_job( $job );
	}

	/**
	 * Remove job
	 */
	public function remove_job( $job ) {
		global $wpdb;

		$id = $job['job_id'] ?? false;

		if ( ! $id ) {
			return;
		}

		$job_table = $this->query_filters::get_table_name( 'index_job' );

		$wpdb->delete( $job_table, [ 'job_id' => $id ] );
	}

	/**
	 * Remove all jobs
	 *
	 * @since 1.11
	 */
	public function remove_all_jobs() {
		global $wpdb;

		$job_table = $this->query_filters::get_table_name( 'index_job' );

		$wpdb->query( "TRUNCATE TABLE {$job_table}" );
	}

	/**
	 * Remove orphan jobs that are still in the database
	 *
	 * @since 1.10.2
	 */
	private function remove_orphan_jobs() {
		global $wpdb;

		$job_table     = $this->query_filters::get_table_name( 'index_job' );
		$element_table = $this->query_filters::get_table_name( 'element' );

		// Directly delete orphaned jobs as we cannot determine the filter_id anymore
		$query = "
			DELETE {$job_table}
			FROM {$job_table}
			LEFT JOIN {$element_table}
			ON {$job_table}.filter_row_id = {$element_table}.id
			WHERE {$element_table}.id IS NULL
		";

		$wpdb->query( $query );
	}

	/**
	 * Get all jobs
	 */
	public function get_jobs() {
		global $wpdb;

		$job_table     = $this->query_filters::get_table_name( 'index_job' );
		$element_table = $this->query_filters::get_table_name( 'element' );

		$query = "SELECT * FROM {$job_table} LEFT JOIN {$element_table} ON {$job_table}.filter_row_id = {$element_table}.id";

		$jobs = $wpdb->get_results( $query, ARRAY_A );

		return $jobs;
	}

	/**
	 * Get the progress text for the indexing process
	 * - Use in the admin settings page
	 */
	public function get_overall_progress() {
		$text       = esc_html__( 'All indexing jobs completed.', 'bricks' );
		$all_jobs   = $this->get_jobs();
		$total_jobs = count( $all_jobs );

		if ( $total_jobs > 0 ) {
			// current job is the first job
			$current_job          = $all_jobs[0];
			$current_job_progress = round( ( $current_job['processed'] / $current_job['total'] ) * 100, 2 );

			// Show Total jobs, current job, progress
			$text  = esc_html__( 'Total jobs', 'bricks' ) . ': ' . $total_jobs . '<br>';
			$text .= esc_html__( 'Current job', 'bricks' ) . ': #' . $current_job['filter_id'] . '<br>';
			$text .= esc_html__( 'Progress', 'bricks' ) . ': ' . $current_job_progress . '%';
		}

		return $text;
	}


	/**
	 * Check if server resource limits are reached
	 * Default: 85% memory usage, and 20s time usage
	 * - Majority of servers have 30s time limit, save 10s for other processes
	 */
	public static function resource_limit_reached() {
		$default_time_limit      = 20;
		$memory_limit_percentage = 85;

		// Memory usage check
		$memory_limit = ini_get( 'memory_limit' );
		$memory_usage = memory_get_usage( true );

		// if not set or if set to '0' or '-1' (unlimited)
		if ( ! $memory_limit || $memory_limit == 0 || $memory_limit == -1 ) {
			$memory_limit = '512M';
		}

		$memory_limit = wp_convert_hr_to_bytes( $memory_limit );

		// Calculate current memory usage percentage
		$current_memory_percentage = ( $memory_usage / $memory_limit ) * 100;

		// Time usage check
		$time_limit = ini_get( 'max_execution_time' );
		$time_usage = microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'];

		// Use default time limit if not set or if set to '0' or '-1' (unlimited)
		if ( ! $time_limit || $time_limit == 0 || $time_limit == -1 ) {
			$time_limit = $default_time_limit;
		}

		return ( $current_memory_percentage >= $memory_limit_percentage || $time_usage >= $time_limit );
	}

	/**
	 * Dispatch a background job (unblocking) to reindex query filters
	 */
	public static function trigger_background_job() {
		if ( ! Helpers::enabled_query_filters() ) {
			return;
		}

		$url = add_query_arg(
			[
				'action' => 'bricks_background_index_job',
				'nonce'  => wp_create_nonce( 'bricks-nonce-indexer' ) // Verify nonce in background_index_job
			],
			admin_url( 'admin-ajax.php' )
		);

		$args = [
			'sslverify' => false,
			'body'      => '',
			'timeout'   => 0.01,
			'blocking'  => false,
			'cookies'   => $_COOKIE,
		];

		Helpers::remote_post( $url, $args );
	}
}
