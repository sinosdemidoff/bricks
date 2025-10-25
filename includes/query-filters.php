<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Query_Filters {
	const INDEX_TABLE_NAME     = 'bricks_filters_index';
	const ELEMENT_TABLE_NAME   = 'bricks_filters_element';
	const INDEX_JOB_TABLE_NAME = 'bricks_filters_index_job'; // @since 1.10
	const DB_CHECK_TRANSIENT   = 'bricks_filters_db_check'; // @since 1.10
	const OPTION_SUFFIX        = '_db_version'; // @since 1.10
	const ELEMENT_DB_VERSION   = '1.1'; // code version @since 1.10
	const INDEX_DB_VERSION     = '1.0'; // code version @since 1.10
	const INDEX_JOB_DB_VERSION = '1.0'; // code version @since 1.10

	private static $instance = null;
	private static $index_table_name;
	private static $element_table_name;
	private static $index_job_table_name; // @since 1.10

	public static $filter_object_ids = [];
	/**
	 * Structure for $active_filters
	 * key: query_id
	 * value: array of filter_info
	 * filter_info: array of filter_id, query_id, settings, value, instance_name (sort_option_info, query_vars, query_type will be added after running generate_query_vars_from_active_filters)
	 *
	 * @since 1.11
	 */
	public static $active_filters          = [];
	public static $page_filters            = [];
	public static $selected_filters        = []; // @since 1.11
	public static $query_vars_before_merge = [];
	public static $is_saving_post          = false;
	private static $generating_object_type = 'post'; // @since 1.12

	public function __construct() {
		global $wpdb;

		self::$index_table_name     = $wpdb->prefix . self::INDEX_TABLE_NAME;
		self::$element_table_name   = $wpdb->prefix . self::ELEMENT_TABLE_NAME;
		self::$index_job_table_name = $wpdb->prefix . self::INDEX_JOB_TABLE_NAME; // @since 1.10

		if ( Helpers::enabled_query_filters() ) {
			add_action( 'wp', [ $this, 'set_page_filters_from_wp_query' ], 100 );

			// After dynamic tags are set (providers.php), After filed-integration init, before main query pre_get_posts. Must follow the order
			// Use bricks/dynamic_data/tags_registered hook (#86c3xg01h; @since 2.0)
			add_action( 'bricks/dynamic_data/tags_registered', [ $this, 'set_active_filters_from_url' ], 10022 );
			add_action( 'bricks/dynamic_data/tags_registered', [ $this, 'add_active_filters_query_vars' ], 10023 );
			add_action( 'bricks/dynamic_data/tags_registered', [ $this, 'set_selected_filters_from_active_filters' ], 10024 );

			// Check required tables (@since 1.10)
			add_action( 'admin_init', [ $this, 'tables_check' ] );

			/**
			 * Capture filter elements and index if needed
			 * Use update_post_metadata to capture filter elements when duplicate content.
			 * Priority 11 after the hook check in ajax.php
			 *
			 * @since 1.9.8
			 */
			add_action( 'update_post_metadata', [ $this, 'qf_update_post_metadata' ], 11, 5 );

			// Polylang use add_metadata to when copying post, use this to capture filter elements (@since 1.12.2)
			add_action( 'added_post_meta', [ $this, 'qf_added_post_meta' ], 10, 4 );

			// Hooks to listen so we can add new index record. Use largest priority
			add_action( 'save_post', [ $this, 'save_post' ], PHP_INT_MAX - 10, 2 );
			add_action( 'delete_post', [ $this, 'delete_post' ] );
			add_filter( 'wp_insert_post_parent', [ $this, 'wp_insert_post_parent' ], 10, 4 );
			add_action( 'set_object_terms', [ $this, 'set_object_terms' ], PHP_INT_MAX - 10, 6 );
			add_action( 'updated_post_meta', [ $this, 'handle_post_meta_update' ], PHP_INT_MAX - 10, 4 ); // (#86c4uh71y @since 2.0.2)
			add_action( 'deleted_post_meta', [ $this, 'handle_post_meta_update' ], PHP_INT_MAX - 10, 4 ); // (#86c4uh71y @since 2.0.2)
			add_action( 'edit_attachment', [ $this, 'edit_attachment' ] ); // (#86c4uh71y @since 2.0.2)

			// Term
			add_action( 'edited_term', [ $this, 'edited_term' ], PHP_INT_MAX - 10, 3 );
			add_action( 'delete_term', [ $this, 'delete_term' ], 10, 4 );

			// User
			add_action( 'profile_update', [ $this, 'user_updated' ], PHP_INT_MAX - 10, 2 );
			add_action( 'user_register', [ $this, 'user_updated' ], PHP_INT_MAX - 10, 1 );
			add_action( 'delete_user', [ $this, 'delete_user' ] );

			// Element conditions all true for filter elements in filter API endpoints (@since 1.9.8)
			add_filter( 'bricks/element/render', [ $this, 'filter_element_render' ], 10, 2 );
		}
	}

	/**
	 * Singleton - Get the instance of this class
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new Query_Filters();
		}

		return self::$instance;
	}

	/**
	 * Get the database key for the given table name
	 * - To be used in options table
	 * - Example: bricks_filters_index_db_version
	 *
	 * @since 1.10
	 */
	private static function get_option_key( $table_type = 'index' ) {
		if ( $table_type === 'element' ) {
			$option_key = self::ELEMENT_TABLE_NAME;
		} elseif ( $table_type === 'index_job' ) {
			$option_key = self::INDEX_JOB_TABLE_NAME;
		} else {
			$option_key = self::INDEX_TABLE_NAME;
		}

		return $option_key . self::OPTION_SUFFIX;
	}

	public static function get_table_name( $table_name = 'index' ) {
		if ( $table_name === 'element' ) {
			return self::$element_table_name;
		}

		if ( $table_name === 'index_job' ) {
			return self::$index_job_table_name;
		}

		return self::$index_table_name;
	}

	public static function check_managed_db_access() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create custom database table for storing filter index
	 */
	public function maybe_create_tables() {
		if ( ! self::check_managed_db_access() ) {
			return;
		}

		$this->create_index_table();
		$this->create_element_table();
		$this->create_index_job_table();
	}

	private function create_index_job_table() {
		global $wpdb;

		$index_job_table_name = self::get_table_name( 'index_job' );

		// Return: Table already exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $index_job_table_name ) ) === $index_job_table_name ) {
			return;
		}

		// Create table
		$charset_collate = $wpdb->get_charset_collate();

		/**
		 * Table columns:
		 * filter_row_id: Reference to the filter element row ID
		 * job_details: The details of the job
		 * total: The total rows to be processed
		 * processed: The total rows processed
		 * job_created_at: The time the job was created
		 * job_updated_at: The time the job was updated
		 *
		 * Indexes:
		 * filter_row_id_idx (filter_row_id)
		 */
		$sql = "CREATE TABLE {$index_job_table_name} (
			job_id BIGINT(20) NOT NULL AUTO_INCREMENT,
			filter_row_id BIGINT(20) UNSIGNED,
			job_details LONGTEXT,
			total BIGINT(20) UNSIGNED default '0',
			processed BIGINT(20) UNSIGNED default '0',
			job_created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			job_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (job_id),
			KEY filter_row_id_idx (filter_row_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}

	private function create_index_table() {
		global $wpdb;

		$index_table_name = self::get_table_name();

		// Return: Table already exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $index_table_name ) ) === $index_table_name ) {
			return;
		}

		// Create table
		$charset_collate = $wpdb->get_charset_collate();

		/**
		 * Table columns:
		 * filter_id: The unique 6-character filter element ID
		 * object_id: The ID of the post/page
		 * object_type: The type of object (post, page, etc.)
		 * filter_value: The value of the filter
		 * filter_value_display: The value of the filter (displayed)
		 * filter_value_id: The ID of the filter value (if applicable)
		 * filter_value_parent: The parent ID of the filter value (if applicable)
		 *
		 * Indexes:
		 * filter_id_idx (filter_id)
		 * object_id_idx (object_id)
		 * filter_id_object_id_idx (filter_id, object_id)
		 */
		$sql = "CREATE TABLE {$index_table_name} (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			filter_id CHAR(6) NOT NULL,
			object_id BIGINT(20) UNSIGNED,
			object_type VARCHAR(50),
			filter_value VARCHAR(255),
			filter_value_display VARCHAR(255),
			filter_value_id BIGINT(20) UNSIGNED default '0',
			filter_value_parent BIGINT(20) UNSIGNED default '0',
			PRIMARY KEY  (id),
			KEY filter_id_idx (filter_id),
			KEY object_id_idx (object_id),
			KEY filter_id_object_id_idx (filter_id, object_id)
    ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}

	private function create_element_table() {
		global $wpdb;

		$element_table_name = self::get_table_name( 'element' );

		// Return: Table already exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $element_table_name ) ) === $element_table_name ) {
			return;
		}

		// Create table
		$charset_collate = $wpdb->get_charset_collate();

		/**
		 * This table is used to store all filter elements created across the site
		 *
		 * When a post update or save, we will loop through all filter elements and update the index table
		 * Table columns:
		 * filter_id: The unique 6-character filter element ID
		 * filter_action: The action of the filter element (filter, sort)
		 * status: The status of the filter element (0, 1)
		 * indexable: Whether this filter element is indexable (0, 1)
		 * settings: The settings of the filter element
		 * post_id: The ID of this filter element located in
		 * nice_name: The nice name of the filter element (@since 1.11)
		 * language: The language of the filter element (@since 1.11)
		 * filter_type: The type of the filter element (@since 1.11)
		 *
		 * Indexes:
		 * filter_id_idx (filter_id)
		 * filter_action_idx (filter_action)
		 * status_idx (status)
		 * indexable_idx (indexable)
		 * post_id_idx (post_id)
		 */

		$sql = "CREATE TABLE {$element_table_name} (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			filter_id CHAR(6) NOT NULL,
			filter_action VARCHAR(50),
			status INT UNSIGNED default '0',
			indexable INT UNSIGNED default '0',
			settings LONGTEXT,
			post_id BIGINT(20) UNSIGNED,
			nice_name VARCHAR(100) default '',
			language VARCHAR(50) default '',
			filter_type VARCHAR(50) default '',
			PRIMARY KEY  (id),
			KEY filter_id_idx (filter_id),
			KEY filter_action_idx (filter_action),
			KEY status_idx (status),
			KEY indexable_idx (indexable),
			KEY post_id_idx (post_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}

	/**
	 * Handle table structure update
	 *
	 * @since 1.10
	 */
	private function maybe_update_table_structure() {
		if ( ! self::check_managed_db_access() ) {
			return;
		}

		$tables = [
			'element'   => [
				'code_version' => self::ELEMENT_DB_VERSION,
				'update_fn'    => [
					'1.0' => 'update_filter_element_db_v_1_0',
					'1.1' => 'update_filter_element_db_v_1_1', // @since 1.11
				],
			],
			'index'     => [
				'code_version' => self::INDEX_DB_VERSION,
				'update_fn'    => [
					'1.0' => 'update_filter_index_db_v_1_0',
				],
			],
			'index_job' => [
				'code_version' => self::INDEX_JOB_DB_VERSION,
				'update_fn'    => [
					'1.0' => 'update_filter_index_job_db_v_1_0',
				],
			]
		];

		foreach ( $tables as $table_type => $table_info ) {
			// Get element db version, default is 0.1
			$table_db_version = get_option( self::get_option_key( $table_type ), '0.1' );

			// Exit if db version is higher or equal to code version
			if ( version_compare( $table_db_version, $table_info['code_version'], '>=' ) ) {
				continue;
			}

			/**
			 * Loop through all update functions
			 * Ensure any website instance can update every version althought it's a small performance hit
			 */
			foreach ( $table_info['update_fn'] as $version => $function_name ) {
				if ( version_compare( $table_db_version, $version, '<' ) && method_exists( $this, $function_name ) ) {
					$this->$function_name();
				}
			}

		}

	}

	/**
	 * Check if all database tables are updated
	 * Used in admin settings page
	 *
	 * @since 1.10
	 */
	public static function all_db_updated() {
		$element_db_version = get_option( self::get_option_key( 'element' ), '0.1' );
		if ( version_compare( $element_db_version, self::ELEMENT_DB_VERSION, '<' ) ) {
			return false;
		}

		$index_db_version = get_option( self::get_option_key( 'index' ), '0.1' );
		if ( version_compare( $index_db_version, self::INDEX_DB_VERSION, '<' ) ) {
			return false;
		}

		$index_job_db_version = get_option( self::get_option_key( 'index_job' ), '0.1' );
		if ( version_compare( $index_job_db_version, self::INDEX_JOB_DB_VERSION, '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * v1.0 element db version update
	 * - Update post_id to BIGINT(20) UNSIGNED
	 *
	 * @since 1.10
	 */
	private function update_filter_element_db_v_1_0() {
		$fn_version         = '1.0';
		$db_key             = self::get_option_key( 'element' );
		$current_db_version = get_option( $db_key, '0.1' );

		// STEP: If current db version is higher or equal to 1.0, return
		if ( version_compare( $current_db_version, $fn_version, '>=' ) ) {
			return;
		}

		global $wpdb;
		$table_name = self::get_table_name( 'element' );

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return;
		}

		// STEP: Alter element table
		// post_id to BIGINT(20) UNSIGNED, remain index as is
		$sql = "ALTER TABLE {$table_name} MODIFY post_id BIGINT(20) UNSIGNED, DROP INDEX post_id_idx, ADD INDEX post_id_idx (post_id)";

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Update element db version, no auto-load
		update_option( $db_key, $fn_version, false );
	}

	/**
	 * v1.1 element db version update
	 * - Update new columns: nice_name, language, filter_type
	 *
	 * @since 1.11
	 */
	private function update_filter_element_db_v_1_1() {
		$fn_version         = '1.1';
		$db_key             = self::get_option_key( 'element' );
		$current_db_version = get_option( $db_key, '0.1' );

		// STEP: If current db version is higher or equal to 1.1, return
		if ( version_compare( $current_db_version, $fn_version, '>=' ) ) {
			return;
		}

		// STEP: Update new columns
		global $wpdb;

		$table_name = self::get_table_name( 'element' );

		// v1.1 new columns
		$column_names = [
			'nice_name'   => 'VARCHAR(100) default \'\'',
			'language'    => 'VARCHAR(50) default \'\'',
			'filter_type' => 'VARCHAR(50) default \'\'',
		];

		$columns = $wpdb->get_col( "DESC $table_name" );

		// Add missing columns
		foreach ( $column_names as $column_name => $column_type ) {
			if ( ! in_array( $column_name, $columns, true ) ) {
				$wpdb->query( "ALTER TABLE $table_name ADD COLUMN $column_name $column_type" );
			}
		}

		// STEP: Update filter_type column (element instance name)
		// Get all elements from element table
		$all_db_elements = $this->get_elements_from_element_table( [], false );

		// Loop through all elements from element table
		foreach ( $all_db_elements as $db_element ) {
			$element_id = $db_element['filter_id'] ?? false;
			$post_id    = $db_element['post_id'] ?? 0;
			$row_id     = $db_element['id'] ?? 0;

			if ( ! $element_id || ! $row_id ) {
				continue;
			}

			/**
			 * Get element data from Helpers::get_element_data()
			 * Note: Some elements are not found from get_element_data() due to many reasons, user needs to trigger the save again. (the post/template is draft, post_id is not set, etc.)
			 */
			$element_data   = Helpers::get_element_data( $post_id, $element_id );
			$target_element = $element_data['element'] ?? false;

			if ( ! $target_element ) {
				continue;
			}

			// Get element name
			$element_name = $target_element['name'] ?? false;

			// Populate element data (Use the same data structure as in the element table)
			$db_element['filter_type'] = $element_name;

			// Update element table
			$this->update_element(
				$db_element,
				[
					'filter_id' => $element_id,
					'id'        => $row_id
				]
			);
		}

		// Update element db version, no auto-load
		update_option( $db_key, $fn_version, false );
	}

	/**
	 * v1.0 index db version update
	 * - Update object_id, filter_value_id, filter_value_parent to BIGINT(20) UNSIGNED
	 *
	 * @since 1.10
	 */
	private function update_filter_index_db_v_1_0() {
		$fn_version         = '1.0';
		$db_key             = self::get_option_key( 'index' );
		$current_db_version = get_option( $db_key, '0.1' );

		// STEP: If current db version is higher or equal to 1.0, return
		if ( version_compare( $current_db_version, $fn_version, '>=' ) ) {
			return;
		}

		global $wpdb;
		$table_name = self::get_table_name();

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return;
		}

		// STEP: Alter index table
		// object_id, filter_value_id, filter_value_parent to BIGINT(20) UNSIGNED, remain index as is
		$sql = "ALTER TABLE {$table_name} MODIFY object_id BIGINT(20) UNSIGNED, MODIFY filter_value_id BIGINT(20) UNSIGNED, MODIFY filter_value_parent BIGINT(20) UNSIGNED, DROP INDEX object_id_idx, DROP INDEX filter_id_object_id_idx, ADD INDEX object_id_idx (object_id), ADD INDEX filter_id_object_id_idx (filter_id, object_id)";

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Update index db version, no auto-load
		update_option( $db_key, $fn_version, false );
	}

	/**
	 * v1.0 index job db version update
	 * - Update total, processed to BIGINT(20) UNSIGNED
	 *
	 * @since 1.10
	 */
	private function update_filter_index_job_db_v_1_0() {
		$fn_version         = '1.0';
		$db_key             = self::get_option_key( 'index_job' );
		$current_db_version = get_option( $db_key, '0.1' );

		// STEP: If current db version is higher or equal to 1.0, return
		if ( version_compare( $current_db_version, $fn_version, '>=' ) ) {
			return;
		}

		global $wpdb;
		$table_name = self::get_table_name( 'index_job' );

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return;
		}

		// STEP: Alter index job table
		// total, processed to BIGINT(20) UNSIGNED, remain index as is
		$sql = "ALTER TABLE {$table_name} MODIFY total BIGINT(20) UNSIGNED, MODIFY processed BIGINT(20) UNSIGNED";

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Update index job db version, no auto-load
		update_option( $db_key, $fn_version, false );
	}

	/**
	 * Check if the required tables exist
	 *
	 * @since 1.10
	 */
	public function tables_check() {
		// Check: DB last checked time
		$db_check = get_transient( self::DB_CHECK_TRANSIENT );
		$ttl      = 28800; // = 8 hours

		// Check: DB tables need to be checked
		if ( ! $db_check || ( time() - $db_check ) > $ttl ) {
			$this->maybe_create_tables();
			$this->maybe_update_table_structure(); // @since 1.10
			set_transient( self::DB_CHECK_TRANSIENT, time(), $ttl );
		}
	}

	/**
	 * Rebuild the filter element DB
	 * - Get all posts with filter elements
	 * - Loop through all posts and update the element table
	 * - Might be slow on large websites
	 * - Allow multilanguage logic to handle the meta_value separately (avoid duplicated element ID)
	 *
	 * @since 1.12.2
	 */
	public function fix_filter_element_db() {
		// truncate filter element DB
		global $wpdb;
		$element_table = self::get_table_name( 'element' );
		$index_table   = self::get_table_name();

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $element_table ) ) !== $element_table ) {
			return false;
		}

		// Check if index table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $index_table ) ) !== $index_table ) {
			return false;
		}

		// Clear all elements
		$wpdb->query( "TRUNCATE TABLE $element_table" );

		// Clear all index
		$wpdb->query( "TRUNCATE TABLE $index_table" );

		// STEP: Retrieve all posts with filter elements from the entire website
		$meta_query = [
			'relation' => 'OR',
		];

		$filter_instance = [
			'filter-checkbox'       => 's:15:"filter-checkbox"',
			'filter-datepicker'     => 's:16:"filter-datepicker"',
			'filter-radio'          => 's:12:"filter-radio"',
			'filter-range'          => 's:12:"filter-range"',
			'filter-search'         => 's:13:"filter-search"',
			'filter-select'         => 's:13:"filter-select"',
			'filter-submit'         => 's:13:"filter-submit"',
			'filter-active-filters' => 's:21:"filter-active-filters"',
		];

		$merge_query_function = function( $filter, $key ) {
			return [
				[
					'key'     => BRICKS_DB_PAGE_HEADER,
					'value'   => $key,
					'compare' => 'LIKE',
				],
				[
					'key'     => BRICKS_DB_PAGE_CONTENT,
					'value'   => $key,
					'compare' => 'LIKE',
				],
				[
					'key'     => BRICKS_DB_PAGE_FOOTER,
					'value'   => $key,
					'compare' => 'LIKE',
				],
			];
		};

		foreach ( $filter_instance as $type => $key ) {
			$meta_query = array_merge( $meta_query, $merge_query_function( $type, $key ) );
		}

		$post_types = array_diff(
			get_post_types(),
			[ 'revision', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request' ], // Not necessary to index these post types as nobody will use filters on these (@since 1.12.2)
		);

		$args = [
			'post_type'              => $post_types,
			'post_status'            => [ 'publish', 'draft', 'pending', 'future', 'private' ],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'cache_results'          => false,
			'update_post_term_cache' => false,
			'no_found_rows'          => true,
			'suppress_filters'       => true, // WPML (also to prevent any posts_where filters from modifying the query)
			'lang'                   => '', // Polylang
			'meta_query'             => $meta_query,
		];

		$posts_with_filters = new \WP_Query( $args );

		$post_ids = $posts_with_filters->posts ?? [];

		foreach ( $post_ids as $post_id ) {
			$template_type = 'content';

			// Set the template type (header, footer, content)
			if ( get_post_type( $post_id ) === 'bricks_template' ) {
				$template_type = Templates::get_template_type( $post_id );
			}

			// Allow Polylang / WPML handle separately
			$handled = apply_filters( 'bricks/fix_filter_element_db', false, $post_id, $template_type );

			if ( $handled ) {
				continue;
			}

			// Get bricks data
			$bricks_data = Database::get_data( $post_id, $template_type );

			$this->maybe_update_element( $post_id, $bricks_data );
		}

		return true;
	}

	/**
	 * Return array of element names that have filter settings.
	 *
	 * Pagination is one of them but it's filter setting handled in /includes/elements/pagination.php set_ajax_attributes()
	 */
	public static function filter_controls_elements() {
		return [
			'filter-checkbox',
			'filter-datepicker',
			'filter-radio',
			'filter-range',
			'filter-search',
			'filter-select',
			'filter-submit',
			'filter-active-filters',
		];
	}

	/**
	 * Dynamic update elements names
	 * - These elements will be updated dynamically when the filter AJAX is called
	 */
	public static function dynamic_update_elements() {
		return [
			'filter-checkbox',
			'filter-datepicker',
			'filter-radio',
			'filter-range',
			'filter-search',
			'filter-select',
			'pagination', // @since 1.9.8
			'filter-active-filters', // @since 1.11
			'filter-submit', // @since 1.11
		];
	}

	/**
	 * Indexable elements names
	 * - These elements will be indexed in the index table
	 */
	public static function indexable_elements() {
		return [
			'filter-checkbox',
			'filter-datepicker',
			'filter-radio',
			'filter-select',
			'filter-range',
		];
	}

	/**
	 * Force render filter elements in filter API endpoint.
	 *
	 * Otherwise, filter elements will not be re-rendered in filter API endpoint as element condition fails.
	 *
	 * @since 1.9.8
	 */
	public function filter_element_render( $render, $element_instance ) {
		$element_name = is_object( $element_instance ) ? $element_instance->name : false;

		if ( ! $element_name ) {
			$element_name = $element_instance['name'] ?? false;
		}

		// Check: Is this a dynamic update element
		if ( ! in_array( $element_name, self::dynamic_update_elements(), true ) ) {
			return $render;
		}

		// Return true for dynamic update elements (if this is filter API endpoint)
		if ( Api::is_current_endpoint( 'query_result' ) ) {
			return true;
		}

		return $render;
	}

	/**
	 * Set page filters manually on wp hook:
	 * Example: In archive page, taxonomy page, etc.
	 */
	public function set_page_filters_from_wp_query() {
		$page_filters = [];

		// Check if this is taxonomy page
		if ( is_tax() || is_category() || is_tag() || is_post_type_archive() ) {
			// What is current taxonomy?
			$queried_object = get_queried_object();

			$taxonomy = $queried_object->taxonomy ?? false;

			if ( ! $taxonomy ) {
				return;
			}

			// Set current page filters so each filter element can disabled as needed
			$page_filters[ $taxonomy ] = $queried_object->slug;
		}

		self::set_page_filters( $page_filters );
	}

	/**
	 * Set active filters via URL parameters
	 *
	 * NOTE: This feature is only available if the element table is updated to 1.1
	 *
	 * @since 1.11
	 */
	public function set_active_filters_from_url() {
		// Check current element db version before using this feature
		$current_db_version = get_option( self::get_option_key( 'element' ), '0.1' );

		// If current db version is lower than 1.1, return
		if ( version_compare( $current_db_version, '1.1', '<' ) ) {
			return;
		}

		/**
		 * Retrieve GET parameters starts with 'brx_' and save as $brx_filters, otherwise save as $unknown_filters
		 *
		 * Use wp_unslash for the values or it will be slashed.
		 *
		 * @since 1.12
		 */
		$brx_filters     = [];
		$unknown_filters = [];

		foreach ( $_GET as $key => $value ) {
			if ( strpos( $key, 'brx_' ) === 0 ) {
				$filter_id                 = str_replace( 'brx_', '', $key );
				$brx_filters[ $filter_id ] = [
					'value'        => wp_unslash( $value ),
					'element_data' => false,
					'url_param'    => $key,
				];
			} else {
				$unknown_filters[ $key ] = wp_unslash( $value );
			}
		}

		// Retrive all filter elements from the element table where nice_name is not empty
		$elements_with_nice_name = $this->get_elements_from_element_table(
			[
				'status'    => 1,
				'nice_name' => [
					'operator' => '!=',
					'value'    => '',
				]
			]
		);

		// Loop through $unknown_filters and check if it matches with any nice_name, add it to $brx_filters
		foreach ( $unknown_filters as $key => $value ) {

			$found_element = array_filter(
				$elements_with_nice_name,
				function( $element ) use ( $key ) {
					return $element['nice_name'] === $key;
				}
			);

			if ( empty( $found_element ) ) {
				continue;
			}

			// Maybe multiple elements with the same nice_name. Add all into $brx_filters
			foreach ( $found_element as $element_data ) {
				if ( ! isset( $element_data['filter_id'] ) ) {
					continue;
				}

				$brx_filters[ $element_data['filter_id'] ] = [
					'value'        => $value,
					'element_data' => $element_data,
					'url_param'    => $key
				];
			}
		}

		$active_filters = [];

		if ( ! empty( $brx_filters ) ) {
			// $brx_filters is an array
			foreach ( $brx_filters as $filter_id => $data ) {
				// Ensure the ID is a string as it could be 6 digit number (@since 1.12)
				$filter_id    = (string) $filter_id;
				$value        = $data['value'];
				$element_data = $data['element_data'];
				$url_param    = $data['url_param'];

				// Get filter element settings from DB if is empty (not found in $unknown_filters)
				if ( ! $element_data ) {
					$element_datas = $this->get_elements_from_element_table(
						[
							'filter_id' => $filter_id,
							'status'    => 1,
						]
					);

					// Only one element should be returned
					$element_data = reset( $element_datas );
				}

				if ( ! $element_data || ! is_array( $element_data ) || ! isset( $element_data['settings'] ) ) {
					continue;
				}

				$element_settings = json_decode( $element_data['settings'], true );
				$target_query_id  = $element_settings['filterQueryId'] ?? false;
				$filter_type      = $element_data['filter_type'] ?? '';

				if ( ! $target_query_id ) {
					continue;
				}

				$value = self::sanitize_filter_value( $value, $filter_type, $element_settings );

				$filter_info = [
					'filter_id'     => $filter_id,
					'query_id'      => $target_query_id,
					'settings'      => $element_settings,
					'value'         => $value,
					'instance_name' => $filter_type,
					'url_param'     => $url_param
				];

				if ( ! isset( $active_filters[ $target_query_id ] ) ) {
					$active_filters[ $target_query_id ] = [];
				}

				$existing_filter_ids = array_column( $active_filters[ $target_query_id ], 'filter_id' );

				// Add filter_info to active_filters if it does not exist, ensure unique filters
				if ( ! in_array( $filter_id, $existing_filter_ids, true ) ) {
					$active_filters[ $target_query_id ][] = $filter_info;
				}

				// Update filter element filterValue
				add_filter(
					'bricks/element/settings',
					function( $settings, $element_instance ) use ( $filter_id, $value ) {
						if ( $element_instance->id !== $filter_id ) {
							return $settings;
						}
						$settings['filterValue'] = $value;

						return $settings;
					},
					10,
					2
				);
			}
		}

		self::$active_filters = $active_filters;
	}

	/**
	 * Generate query vars from active filters (via URL parameters)
	 *
	 * @since 1.11
	 */
	public function add_active_filters_query_vars() {
		if ( empty( self::$active_filters ) ) {
			return;
		}

		$object_types = [ 'post','term','user' ];
		// Loop through all active filters
		foreach ( self::$active_filters as $query_id => $active_filters ) {
			// Ensure the ID is a string as it could be 6 digit number (@since 1.12)
			$query_id = (string) $query_id;

			foreach ( $object_types as $object_type ) {
				// STEP: Set flag for query_vars (@since 1.12)
				self::set_generating_type( $object_type );

				// STEP: generate query vars from active filters
				$filter_query_vars = self::generate_query_vars_from_active_filters( $query_id );

				// STEP: Set the paged & number - This is needed for term query (@since 1.12.2)
				if ( $object_type === 'term' ) {
					if (
						( isset( $filter_query_vars['paged'] ) && $filter_query_vars['paged'] > 1 ) ||
						( isset( $filter_query_vars['number'] ) && $filter_query_vars['number'] > 0 )
					) {
						add_filter(
							'bricks/query/prepare_query_vars_from_settings',
							function( $settings, $element_id ) use ( $filter_query_vars, $query_id, $object_type ) {
								if ( $element_id !== $query_id || $object_type !== 'term' ) {
									return $settings;
								}

								// Set paged value
								if ( isset( $filter_query_vars['paged'] ) ) {
									$settings['query']['paged'] = $filter_query_vars['paged'];
								}

								// Set number value
								if ( isset( $filter_query_vars['number'] ) ) {
									// Backup the user original number value
									if ( isset( $settings['query']['number'] ) ) {
										$settings['query']['brx_user_number'] = $settings['query']['number'];
									}
									// Set the new number value
									$settings['query']['number'] = $filter_query_vars['number'];
								}

								return $settings;
							},
							999,
							2
						);
					}
				}

				// STEP: Add filter_vars to query_vars
				add_filter(
					"bricks/{$object_type}s/query_vars",
					function( $vars, $settings, $element_id ) use ( $filter_query_vars, $query_id, $object_type ) {
						if ( $element_id !== $query_id ) {
							return $vars;
						}

						// STEP: Do not apply URL parameters filter (@since 2.0 )
						if ( isset( $settings['query']['disable_url_params'] ) && $settings['query']['disable_url_params'] ) {
							// Also remove the active filters for this query
							unset( Query_Filters::$active_filters[ $query_id ] );
							return $vars;
						}

						// STEP: save the query vars before merge only once (@since 1.11.1)
						if ( ! isset( Query_Filters::$query_vars_before_merge[ $query_id ] ) ) {
							Query_Filters::$query_vars_before_merge[ $query_id ] = $vars;

							// For term and user query, must save the user original number value or it will be overwritten by url parameter value after page reload
							if ( in_array( $object_type, [ 'term', 'user' ], true ) && isset( $vars['brx_user_number'] ) ) {
								Query_Filters::$query_vars_before_merge[ $query_id ]['number'] = $vars['brx_user_number'];

								// Cleanup
								unset( $vars['brx_user_number'] );
								unset( Query_Filters::$query_vars_before_merge[ $query_id ]['brx_user_number'] );
							}
						}

						// STEP: merge the query vars, indicate third parameter to force meta_query logic merge as well(@since 1.11.1)
						$merged = Query::merge_query_vars( $vars, $filter_query_vars, true );

						return $merged;
					},
					999, // As long as using Query Filters, the filter's query var should be the last (@since 1.11.1)
					3
				);

				// STEP: Reset flasg (@since 1.12)
				self::reset_generating_type();
			}

			// STEP: Do not suppress render content for the target query. Otherwise Live Search query results will not be displayed.
			add_filter(
				'bricks/query/supress_render_content',
				function( $supress, $query_instance ) use ( $query_id ) {
					$element_id = $query_instance->element_id ?? false;

					if ( ! $element_id ) {
						return $supress;
					}

					if ( $element_id === $query_id ) {
						return false;
					}

					return $supress;
				},
				10,
				3
			);
		}
	}

	/**
	 * Populate selected_filters from active filters
	 * - An array with query_id as key and active_filters' IDs as value
	 * - Will be used in the frontent
	 *
	 * @since 1.11
	 */
	public function set_selected_filters_from_active_filters() {
		if ( empty( self::$active_filters ) ) {
			return;
		}
		// Loop through all active filters
		foreach ( self::$active_filters as $query_id => $active_filters ) {
			$selected_filters                    = array_column( $active_filters, 'filter_id' );
			self::$selected_filters[ $query_id ] = $selected_filters;
		}
	}

	/**
	 * Hook into update_post_metadata, if filter element found, update the index table
	 */
	public function qf_update_post_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		// Exclude revisions
		if ( wp_is_post_revision( $object_id ) ) {
			return $check;
		}

		// Only listen to header, content, footer
		if ( ! in_array( $meta_key, [ BRICKS_DB_PAGE_HEADER, BRICKS_DB_PAGE_CONTENT, BRICKS_DB_PAGE_FOOTER ], true ) ) {
			return $check;
		}

		$this->maybe_update_element( $object_id, $meta_value );

		return $check;
	}

	/**
	 * If post meta updated (Not via save_post), update the index table
	 *
	 * @since 2.0.2
	 */
	public function handle_post_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Exclude revisions and save_post action
		if ( wp_is_post_revision( $object_id ) || self::$is_saving_post ) {
			return;
		}

		$exclude_keys = [
			'_encloseme',
			'_edit_lock',
			'_edit_last',
			'_wp_page_template',
			'_wp_trash_meta_status',
			'_wp_trash_meta_time',
			'_wp_desired_post_slug',
			BRICKS_DB_PAGE_HEADER, // Handled in qf_update_post_metadata
			BRICKS_DB_PAGE_CONTENT, // Handled in qf_update_post_metadata
			BRICKS_DB_PAGE_FOOTER, // Handled in qf_update_post_metadata
		];

		if ( ! in_array( $meta_key, $exclude_keys, true ) ) {
			$this->index_post( $object_id );
			return;
		}
	}

	/**
	 * ACF update attachment via AJAX action acf/fields/gallery/update_attachment
	 * It will trigger wp_insert_post but not save_post, so we need to handle it separately
	 * Need to set is_saving_post to false otherwise updated_post_meta will not be triggered
	 * Note: is_saving_post is used to prevent duplicate index job when saving post
	 *
	 * @since 2.0.2
	 */
	public function edit_attachment( $post_id ) {
		self::$is_saving_post = false;
	}

	/**
	 * Hook into added_post_meta, if filter element found, update the index table
	 *
	 * @since 1.12.2
	 */
	public function qf_added_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Exclude revisions
		if ( wp_is_post_revision( $object_id ) ) {
			return;
		}

		// Only listen to header, content, footer
		if ( ! in_array( $meta_key, [ BRICKS_DB_PAGE_HEADER, BRICKS_DB_PAGE_CONTENT, BRICKS_DB_PAGE_FOOTER ], true ) ) {
			return;
		}

		$this->maybe_update_element( $object_id, $meta_value );
	}

	/**
	 * Logic to update element table if filter element found
	 * Automatically update the index table
	 *
	 * @since 1.12.2
	 */
	private function maybe_update_element( $object_id, $meta_value ) {
		$filter_elements = [];
		// Get all filter elements from meta_value
		foreach ( $meta_value as $element ) {
			$element_id = $element['id'] ?? false;

			if ( ! $element_id ) {
				continue;
			}

			$element_name = $element['name'] ?? false;

			if ( ! in_array( $element_name, self::filter_controls_elements(), true ) ) {
				continue;
			}

			$filter_elements[ $element_id ] = $element;
		}

		if ( ! empty( $filter_elements ) ) {
			// Update element table
			$updated_data = $this->update_element_table( $filter_elements, $object_id );

			// Now we need to update the index table by using the updated_data
			$this->update_index_table( $updated_data );
		} else {

			/**
			 * No filter elements found, run delete_post logic to remove all elements from the element table + remove index job + remove index records
			 * Only run this logic if the current action is save_post, update_post_metadata, added_post_meta
			 *
			 * @since 2.0
			 */
			$post_actions = [ 'save_post', 'update_post_metadata', 'added_post_meta' ];

			if ( in_array( current_action(), $post_actions, true ) ) {
				$this->delete_post( $object_id );
			}
		}
	}

	/**
	 *  Decide whether create, update or delete elements in the element table
	 *  Return: array of new_elements, updated_elements, deleted_elements
	 *  Index table will use the return data to decide what to do
	 */
	private function update_element_table( $elements, $post_id ) {
		// Get all elements from element table where post_id = $post_id
		$all_db_elements = $this->get_elements_from_element_table(
			[
				'post_id' => $post_id,
			],
			false
		);

		// Just get the filter_id
		$all_db_elements_ids = array_column( $all_db_elements, 'filter_id' );

		$update_data = [
			'new_elements'     => [],
			'updated_elements' => [],
			'deleted_elements' => [],
		];

		// Loop through all elements from element table
		foreach ( $all_db_elements_ids as $key => $db_element_id ) {
			// If this element is not in the new elements, delete it
			if ( ! isset( $elements[ $db_element_id ] ) ) {
				$this->delete_element( [ 'filter_id' => $db_element_id ] );
				$update_data['deleted_elements'][] = $all_db_elements[ $key ];
			}
		}

		// Loop through all elements, create or update them into element table
		foreach ( $elements as $element ) {
			$element_id = $element['id'] ?? false;

			if ( ! $element_id ) {
				continue;
			}

			$filter_settings = $element['settings'] ?? [];
			$filter_action   = $filter_settings['filterAction'] ?? 'filter';
			$indexable       = in_array( $element['name'], self::indexable_elements(), true ) && 'filter' === $filter_action ? 1 : 0;
			$filter_type     = $element['name'] ?? '';
			$nice_name       = $filter_settings['filterNiceName'] ?? '';
			$filterQueryId   = $filter_settings['filterQueryId'] ?? false;

			$element_data = [
				'filter_id'     => $element_id,
				'filter_action' => $filter_action,
				'status'        => ! empty( $filterQueryId ) ? 1 : 0,
				'indexable'     => $indexable,
				'settings'      => wp_json_encode( $filter_settings ),
				'post_id'       => $post_id,
				'filter_type'   => $filter_type,
				'language'      => '',
				'nice_name'     => $nice_name,
			];

			// Allow modifying element data before saving to the element table (@since 1.12.2)
			$element_data = apply_filters( 'bricks/query_filters/element_data', $element_data, $element, $post_id );

			// If this element is not in the db elements, create it
			if ( ! in_array( $element_id, $all_db_elements_ids, true ) ) {
				$inserted_id = $this->create_element( $element_data );
				if ( $inserted_id > 0 ) {
					// Add id to $element_data so we can use it in update_index_table
					$element_data['id']            = $inserted_id;
					$update_data['new_elements'][] = $element_data;
				}
			} else {
				// If this element is in the db elements, update it
				$updated_id = $this->update_element( $element_data );
				// Only add to updated_elements if updated_id is not false
				if ( $updated_id > 0 ) {
					// Add id to $element_data so we can use it in update_index_table
					$element_data['id']                = $updated_id;
					$update_data['updated_elements'][] = $element_data;
				}

			}
		}

		return $update_data;
	}

	/**
	 * Check & update element table structure. (@since 1.11) - Should move to a separate button to trigger the update.
	 * Remove index DB table and recreate it. (Ensure index table structure is up-to-date)
	 * Retrieve all indexable elements from element table.
	 * Index based on the element settings.
	 */
	public function reindex() {
		if ( ! self::check_managed_db_access() ) {
			return [ 'error' => 'Access denied (current user can\'t manage_options)' ];
		}

		global $wpdb;

		$table_name = self::get_table_name();

		// Always drop index table and recreate it
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Ensure all tables created - @since 1.10
		$this->maybe_create_tables();

		// Ensure all tables updated - @since 1.10
		$this->maybe_update_table_structure();

		// Exit if index table does not exist
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return [ 'error' => "Table {$table_name} does not exist" ];
		}

		// Get all indexable elements from element table
		$indexable_elements = $this->get_elements_from_element_table(
			[
				'indexable' => 1,
				'status'    => 1,
			]
		);

		$element_data = [
			'new_elements'     => [],
			'deleted_elements' => [],
			'updated_elements' => $indexable_elements,
		];

		$this->update_index_table( $element_data );

		return true;
	}

	/**
	 * Update index table based on the updated_data
	 * updated_data holds new_elements, updated_elements, deleted_elements
	 * 1. Remove all rows related to the deleted_elements
	 * 2. Generate index for all new_elements and updated_elements
	 */
	private function update_index_table( $updated_data ) {
		// STEP: Handle deleted elements
		foreach ( $updated_data['deleted_elements'] as $deleted_element ) {
			$id = $deleted_element['filter_id'] ?? false;
			if ( ! $id ) {
				continue;
			}

			// Remove rows related to this filter_id
			self::remove_index_rows( [ 'filter_id' => $id ] );
		}

		// STEP: Handle updated elements
		foreach ( $updated_data['updated_elements'] as $updated_element ) {
			$id = $updated_element['filter_id'] ?? false;
			if ( ! $id ) {
				continue;
			}

			// Remove rows related to this filter_id
			self::remove_index_rows( [ 'filter_id' => $id ] );
		}

		// STEP: Handle new elements & updated elements (we can retrieve from database again but we already have the data)
		$elements = array_merge( $updated_data['new_elements'], $updated_data['updated_elements'] );

		// Only get elements that are indexable, status is 1, and filter_action is filter
		$indexable_elements = array_filter(
			$elements,
			function ( $element ) {
				$indexable     = $element['indexable'] ?? false;
				$status        = $element['status'] ?? false;
				$filter_action = $element['filter_action'] ?? false;

				if ( ! $indexable || ! $status || $filter_action !== 'filter' ) {
					return false;
				}

				return true;
			}
		);

		/**
		 * Exit if no indexable elements found
		 *
		 * Improve performance or trigger_background_job post request will always emit.
		 *
		 * @since 1.10.2
		 */
		if ( empty( $indexable_elements ) ) {
			return;
		}

		$indexer = Query_Filters_Indexer::get_instance();

		// STEP: Send each element to the job queue (@since 1.10)
		foreach ( $indexable_elements as $indexable_element ) {
			// filter_settings is json string
			$filter_settings = json_decode( $indexable_element['settings'], true );
			$filter_db_id    = $indexable_element['id'] ?? false;
			$filter_id       = $indexable_element['filter_id'] ?? false;
			$filter_source   = $filter_settings['filterSource'] ?? false;

			// Ensure all required data is available
			if ( ! $filter_source || ! $filter_db_id || ! $filter_id ) {
				continue;
			}

			$indexer->add_job( $indexable_element );
		}

		// STEP: Trigger the job once
		$indexer::trigger_background_job();
	}


	/**
	 * Get all elements from element table where post_id = $post_id
	 *
	 * @param array $args
	 * @param bool  $publish_only (Default: return elements that the posts are published, use false if want to remove this condition) (@since 2.0) (#86c2d1zav)
	 */
	private function get_elements_from_element_table( $args = [], $publish_only = true ) {
		global $wpdb;

		$table_name  = self::get_table_name( 'element' );
		$posts_table = $wpdb->posts;

		// Initialize an empty array to store placeholders and values
		$placeholders = [];
		$values       = [];
		$where_clause = '';

		// Loop through all args and build where clause
		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) ) {
				$placeholders[] = "{$key} {$value['operator']} %s";
				$values[]       = $value['value'];
			} else {
				$placeholders[] = "{$key} = %s";
				$values[]       = $value;
			}
		}

		if ( $publish_only ) {
			// Add condition to ensure the post is published
			$placeholders[] = "{$posts_table}.post_status = %s";
			$values[]       = 'publish';
		}

		// If we have placeholders, build where clause
		if ( ! empty( $placeholders ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $placeholders );
		}

		// Build the query with a LEFT JOIN to the posts table
		$query = "
			SELECT {$table_name}.*
			FROM {$table_name}
			LEFT JOIN {$posts_table} ON {$table_name}.post_id = {$posts_table}.ID
			{$where_clause}
		";

		// Use prepare to avoid SQL injection if we have values
		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$all_elements = $wpdb->get_results( $query, ARRAY_A );

		return $all_elements ?? [];
	}

	/**
	 * Delete element from element table
	 */
	private function delete_element( $args = [] ) {
		if ( empty( $args ) ) {
			return;
		}

		global $wpdb;

		$table_name = self::get_table_name( 'element' );

		// Check if array_keys is filter_id or post_id
		$column = array_keys( $args )[0];

		if ( ! in_array( $column, [ 'filter_id', 'post_id' ], true ) ) {
			return;
		}

		$wpdb->delete(
			$table_name,
			$args
		);
	}

	/**
	 * Create element in element table
	 */
	private function create_element( $element_data ) {
		global $wpdb;

		$table_name = self::get_table_name( 'element' );

		$element_id = $element_data['filter_id'] ?? false;

		if ( ! $element_id ) {
			return false;
		}

		// Insert element into element table
		$wpdb->insert( $table_name, $element_data );

		// Return inserted ID
		return $wpdb->insert_id;
	}

	/**
	 * Update element in element table
	 *
	 * Only update if the new data is different from the current data
	 *
	 * @return int The ID of the updated element or false if no update needed
	 */
	private function update_element( $element_data, $where_clause = [] ) {
		global $wpdb;

		$table_name = self::get_table_name( 'element' );

		$element_id = $element_data['filter_id'] ?? false;

		if ( ! $element_id ) {
			return false;
		}

		// Get the element data from the element table
		$db_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE filter_id = %s",
				$element_id
			),
			ARRAY_A
		);

		// Return: No element found
		if ( ! $db_data ) {
			return false;
		}

		// Do not use element_data['post_id'], always use db_data['post_id'] (@since 1.12.2)
		if ( isset( $db_data['post_id'] ) ) {
			$element_data['post_id'] = $db_data['post_id'];
		}

		$needs_update = false;

		// Check if the new data is different from the current data
		foreach ( $element_data as $key => $value ) {
			if ( isset( $db_data[ $key ] ) && (string) $db_data[ $key ] !== (string) $value ) {
				$needs_update = true;
				break;
			}
		}

		// Return: No update needed
		if ( ! $needs_update ) {
			return false;
		}

		if ( empty( $where_clause ) ) {
			$where_clause = [
				'filter_id' => $element_id,
			];
		}

		// Update element in element table
		$wpdb->update(
			$table_name,
			$element_data,
			$where_clause
		);

		// Return updated data
		return $db_data['id'];
	}

	/**
	 * Generate index records for a given taxonomy
	 */
	public static function generate_taxonomy_index_rows( $all_posts_ids, $taxonomy ) {
		$rows = [];
		// Loop through all posts
		foreach ( $all_posts_ids as $post_id ) {
			$terms = get_the_terms( $post_id, $taxonomy );

			// If no terms, skip
			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}

			// Loop through all terms
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
		}

		return $rows;

	}

	/**
	 * Remove rows from database
	 */
	public static function remove_index_rows( $args = [] ) {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( empty( $args ) ) {
			return;
		}

		// Remove rows
		$wpdb->delete( $table_name, $args );
	}

	/**
	 * Insert rows into database
	 */
	public static function insert_index_rows( $rows ) {
		global $wpdb;

		$table_name = self::get_table_name();

		$insert_values = [];

		foreach ( $rows as $row ) {
			$insert_values[] = $wpdb->prepare(
				'( %s, %d, %s, %s, %s, %d, %d )',
				$row['filter_id'],
				$row['object_id'],
				$row['object_type'],
				$row['filter_value'],
				$row['filter_value_display'],
				$row['filter_value_id'],
				$row['filter_value_parent']
			);
		}

		if ( ! empty( $insert_values ) ) {
			$insert_query = "INSERT INTO {$table_name}
			( filter_id, object_id, object_type, filter_value, filter_value_display, filter_value_id, filter_value_parent )
			VALUES " . implode( ', ', $insert_values );

			$wpdb->query( $insert_query );
		}

	}

	/**
	 * Generate index records for a given custom field
	 */
	public static function generate_custom_field_index_rows( $object_id, $meta_key, $provider = 'none', $object_type = 'post' ) {
		$rows = [];

		if ( is_a( $object_id, 'WP_Post' ) ) {
			$object_id = $object_id->ID;
		}

		elseif ( is_a( $object_id, 'WP_Term' ) ) {
			$object_id = $object_id->term_id;
		}

		elseif ( is_a( $object_id, 'WP_User' ) ) {
			$object_id = $object_id->ID;
		}

		if ( $provider === 'none' ) {

			switch ( $object_type ) {
				case 'post':
					$meta_value = get_post_meta( $object_id, $meta_key, true );
					break;

				case 'term':
					$meta_value = get_term_meta( $object_id, $meta_key, true );
					break;

				case 'user':
					$meta_value = get_user_meta( $object_id, $meta_key, true );
					break;
			}

			if ( ! $meta_value ) {
				return $rows;
			}

			$rows[] = [
				'filter_id'            => '',
				'object_id'            => $object_id,
				'object_type'          => $object_type,
				'filter_value'         => $meta_value,
				'filter_value_display' => $meta_value,
				'filter_value_id'      => 0,
				'filter_value_parent'  => 0,
			];

		} else {
			// For other providers to generate index rows, not using the default get_post_meta to reduce unnecessary queries
			// NOTE: Undocumented - Not ready for third-party plugins (@since 1.11.1)
			$rows = apply_filters( 'bricks/query_filters/custom_field_index_rows', $rows, $object_id, $meta_key, $provider, $object_type );
		}

		return $rows;
	}

	/**
	 * Generate index records for a given post field.
	 *
	 * @param array  $posts Array of post objects
	 * @param string $post_field The post field to be used
	 */
	public static function generate_post_field_index_rows( $post, $post_field ) {

		$rows = [];

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return $rows;
		}

		// Change field name if needed so we can get it from post object
		$post_field = $post_field === 'post_id' ? 'ID' : $post_field;

		// Populate rows
		$value         = $post->$post_field ?? false;
		$display_value = $value ?? 'None';

		// If post field is post_author, get the author name
		if ( $post_field === 'post_author' ) {
			$author        = get_user_by( 'id', $value );
			$display_value = $author->display_name ?? 'None';
		}

		// If post field is ID, get the post title as display value (@since 1.12.2)
		if ( $post_field === 'ID' ) {
			$display_value = $post->post_title ?? $display_value;
		}

		$rows[] = [
			'filter_id'            => '',
			'object_id'            => $post->ID,
			'object_type'          => 'post',
			'filter_value'         => $value,
			'filter_value_display' => $display_value,
			'filter_value_id'      => 0,
			'filter_value_parent'  => 0,
		];

		return $rows;
	}

	public static function generate_user_field_index_rows( $user, $user_field ) {
		$rows = [];

		if ( ! is_a( $user, 'WP_User' ) ) {
			return $rows;
		}

		$user_id = $user->ID;

		// Change field name if needed so we can get it from user object
		$user_field = $user_field === 'user_id' ? 'ID' : $user_field;

		$value         = $user->$user_field ?? false;
		$display_value = $value ?? 'None';

		if ( $user_field === 'user_role' ) {
			$roles = $user->roles;
			// One user can have multiple roles, we will index all roles
			global $wp_roles;

			foreach ( $roles as $role ) {
				$display_value = $wp_roles->roles[ $role ]['name'] ?? $role;
				$rows[]        = [
					'filter_id'            => '',
					'object_id'            => $user_id,
					'object_type'          => 'user',
					'filter_value'         => $role,
					'filter_value_display' => $display_value,
					'filter_value_id'      => 0,
					'filter_value_parent'  => 0,
				];
			}
		} else {

			// Support filter User Query by user ID (@since 2.0)
			if ( $user_field === 'ID' ) {
				// Change display value: Follow provider-wp.php (name logic)
				if ( ! empty( $user->first_name ) && ! empty( $user->last_name ) ) {
					$display_value = trim( $user->first_name . ' ' . $user->last_name );
				} else {
					$display_value = trim( $user->display_name );
				}
			}

			$rows[] = [
				'filter_id'            => '',
				'object_id'            => $user_id,
				'object_type'          => 'user',
				'filter_value'         => $value,
				'filter_value_display' => $display_value,
				'filter_value_id'      => 0,
				'filter_value_parent'  => 0,
			];
		}

		return $rows;
	}

	public static function generate_term_field_index_rows( $term, $term_field ) {
		$rows = [];

		if ( ! is_a( $term, 'WP_Term' ) ) {
			return $rows;
		}

		$value         = $term->$term_field ?? false;
		$display_value = $term->name ?? 'None';

		$rows[] = [
			'filter_id'            => '',
			'object_id'            => $term->term_id,
			'object_type'          => 'term',
			'filter_value'         => $value,
			'filter_value_display' => $display_value,
			'filter_value_id'      => 0,
			'filter_value_parent'  => 0,
		];

		return $rows;
	}

	/**
	 * Set page filters
	 *
	 * @since 1.11
	 */
	public static function set_page_filters( $page_filters ) {
		self::$page_filters = $page_filters;
	}

	/**
	 * Set active filters
	 *
	 * @since 1.11
	 */
	public static function set_active_filters( $filters = [], $post_id = 0, $query_id = '' ) {
		if ( empty( $filters ) || empty( $query_id ) ) {
			return;
		}

		$active_filters = [];

		foreach ( $filters as $filter_id => $value ) {
			// Ensure the ID is a string as it could be 6 digit number (@since 1.12)
			$filter_id      = (string) $filter_id;
			$element_data   = Helpers::get_element_data( $post_id, $filter_id );
			$filter_element = $element_data['element'] ?? false;

			// Check if $filter_element exists
			if ( ! $filter_element || empty( $filter_element ) ) {
				continue;
			}

			$filter_settings = $filter_element['settings'] ?? [];
			$target_query_id = $filter_settings['filterQueryId'] ?? false;
			$filter_type     = $filter_element['name'] ?? '';

			// Pagination element does not have filterQueryId, only queryId
			if ( ! $target_query_id && $filter_type === 'pagination' ) {
				$target_query_id = $filter_settings['queryId'] ?? 'main';

				if ( $target_query_id === 'main' && Database::$main_query_id !== '' ) {
					$target_query_id = Database::$main_query_id;
				}
			}

			// Ensure target_query_id is set and matches the current query_id
			if ( ! $target_query_id || $target_query_id !== $query_id ) {
				continue;
			}

			$value = self::sanitize_filter_value( $value, $filter_type, $filter_settings );

			$filter_info = [
				'filter_id'     => $filter_id,
				'query_id'      => $target_query_id,
				'settings'      => $filter_settings,
				'value'         => $value,
				'instance_name' => $filter_type,
				'url_param'     => $filter_settings['filterNiceName'] ?? "{brx_{$filter_id}}",
			];

			if ( ! isset( $active_filters[ $target_query_id ] ) ) {
				$active_filters[ $target_query_id ] = [];
			}

			$existing_filter_ids = array_column( $active_filters[ $target_query_id ], 'filter_id' );

			// Add filter_info to active_filters if it does not exist, ensure unique filters
			if ( ! in_array( $filter_id, $existing_filter_ids, true ) ) {
				$active_filters[ $target_query_id ][] = $filter_info;
			}

			// Use hook to set filterValue, the target filter element will be updated
			add_filter(
				'bricks/element/settings',
				function( $settings, $element_instance ) use ( $filter_id, $value ) {
					if ( $element_instance->id !== $filter_id ) {
						return $settings;
					}
					$settings['filterValue'] = $value;

					return $settings;
				},
				10,
				2
			);
		}

		// Set active filters
		self::$active_filters = $active_filters;
	}

	/**
	 * Convert it to the correct format based on the filter type & sanitize value
	 *
	 * @since 1.11
	 */
	public static function sanitize_filter_value( $filter_value, $filter_type, $filter_settings ) {
		// Validate filter value
		switch ( $filter_type ) {
			case 'filter-checkbox':
				// Ensure filter_value is an array
				if ( ! is_array( $filter_value ) ) {
					$filter_value = [ $filter_value ];
				}
				break;

			case 'filter-range':
				// Must be an array with 2 values, ensure it is an array
				if ( is_array( $filter_value ) ) {
					if ( count( $filter_value ) < 2 ) {
						// If only one value, duplicate it
						$filter_value = [ $filter_value[0], $filter_value[0] ];
					} elseif ( count( $filter_value ) > 2 ) {
						// If more than 2 values, slice it
						$filter_value = array_slice( $filter_value, 0, 2 );
					}
				} else {
					// If not an array, convert it to an array with same values (min and max same)
					$filter_value = [ $filter_value, $filter_value ];
				}
				break;

			case 'filter-datepicker':
				$mode = isset( $filter_settings['isDateRange'] ) ? 'range' : 'single';

				// Force convert to array
				if ( ! is_array( $filter_value ) ) {
					$filter_value = explode( ',', $filter_value );
				}

				// Ensure array has 2 values
				if ( count( $filter_value ) < 2 ) {
					// If only one value, duplicate it
					$filter_value = [ $filter_value[0], $filter_value[0] ];
				} elseif ( count( $filter_value ) > 2 ) {
					// If more than 2 values, slice it
					$filter_value = array_slice( $filter_value, 0, 2 );
				}

				if ( $mode === 'single' ) {
					// If single mode, only use the first value
					$filter_value = [ $filter_value[0] ];
				}

				// Now convert back to string with comma separator
				$filter_value = implode( ',', $filter_value );
				break;

			default:
				// Ensure filter_value is a string
				if ( is_array( $filter_value ) ) {
					$filter_value = reset( $filter_value );
				}
				$filter_value = (string) $filter_value;
				break;
		}

		// Sanitize filter value (use rawurldecode to preserve + sign @since 1.12.2)
		if ( is_array( $filter_value ) ) {
			$filter_value = array_map( 'rawurldecode', $filter_value );
			$filter_value = array_map( 'sanitize_text_field', $filter_value );
		} else {
			$filter_value = rawurldecode( $filter_value );
			$filter_value = sanitize_text_field( $filter_value );
		}

		return $filter_value;
	}

	/**
	 * Get active filters by element ID
	 *
	 * @since 1.11
	 */
	public static function get_active_filter_by_element_id( $element_id = '', $query_id = '' ) {
		if ( empty( $element_id ) || empty( $query_id ) ) {
			return false;
		}

		$active_filters = self::$active_filters[ $query_id ] ?? [];

		if ( empty( $active_filters ) ) {
			return false;
		}

		$active_filter = array_filter(
			$active_filters,
			function( $filter ) use ( $element_id ) {
				return $filter['filter_id'] === $element_id;
			}
		);

		return reset( $active_filter ) ?: false;
	}

	/**
	 * Generate query vars from active filters
	 * active filters - filters that are set by the user
	 *
	 * @since 1.11
	 */
	public static function generate_query_vars_from_active_filters( $query_id = '' ) {
		if ( empty( $query_id ) ) {
			return [];
		}

		$query_vars = [];

		$active_filters = self::$active_filters[ $query_id ] ?? [];

		if ( empty( $active_filters ) ) {
			return $query_vars;
		}

		// STEP: Build query args by using active filters
		foreach ( $active_filters as $index => $filter ) {
			$instance_name = $filter['instance_name'];
			$filter_id     = $filter['filter_id'];
			$filter_value  = $filter['value'];
			$settings      = $filter['settings'];
			$filter_source = $settings['filterSource'] ?? false;
			$filter_action = $settings['filterAction'] ?? 'filter';

			// Sort
			if ( $filter_action === 'sort' ) {
				// Only for filter-select and filter-radio
				if ( ! in_array( $instance_name, [ 'filter-select', 'filter-radio' ], true ) ) {
					continue;
				}

				// Build sort query vars
				$query_vars = self::build_sort_query_vars( $query_vars, $filter, $query_id, $index );

				// Undocumented (WooCommerce)
				$query_vars = apply_filters(
					'bricks/query_filters/sort_query_vars',
					$query_vars,
					$filter,
					$query_id,
					$index
				);
				continue;
			}

			// PerPage
			if ( $filter_action === 'per_page' ) {
				// Only for filter-select and filter-radio
				if ( ! in_array( $instance_name, [ 'filter-select', 'filter-radio' ], true ) ) {
					continue;
				}

				// Build perPage query vars
				$query_vars = self::build_per_page_query_vars( $query_vars, $filter, $query_id, $index );
				continue;
			}

			// Filter
			if ( $filter_action === 'filter' ) {
				switch ( $instance_name ) {
					case 'filter-search':
						$query_vars = self::build_search_query_vars( $query_vars, $filter, $query_id, $index );
						break;

					case 'filter-select':
					case 'filter-radio':
					case 'filter-checkbox':
						switch ( $filter_source ) {
							case 'taxonomy':
								$query_vars = self::build_taxonomy_query_vars( $query_vars, $filter, $query_id, $index );
								break;

							case 'customField':
								$query_vars = self::build_custom_field_query_vars( $query_vars, $filter, $query_id, $index );
								break;

							case 'wpField':
								$query_vars = self::build_wp_field_query_vars( $query_vars, $filter, $query_id, $index );
								break;
						}

						break;

					case 'filter-range':
						$query_vars = self::build_filter_range_query_vars( $query_vars, $filter, $query_id, $index );
						break;

					case 'filter-datepicker':
						$query_vars = self::build_datepicker_query_vars( $query_vars, $filter, $query_id, $index );
						break;

					case 'pagination':
						$query_vars = self::build_pagination_query_vars( $query_vars, $filter, $query_id, $index );
						break;
				}

				// Undocumented (WooCommerce)
				$query_vars = apply_filters(
					'bricks/query_filters/filter_query_vars',
					$query_vars,
					$filter,
					$query_id,
					$index
				);

			}
		}

		return $query_vars;
	}

	/**
	 * Use page_filters to generate tax_query
	 * We need this in REST endpoint as we unable to identify which taxonomy is used in the page
	 *
	 * @since 1.11
	 */
	public static function generate_query_vars_from_page_filters() {
		$page_filters = self::$page_filters ?? [];

		if ( empty( $page_filters ) ) {
			return [];
		}

		$query_vars = [];

		foreach ( $page_filters as $taxonomy => $slug ) {
			$tax_query = [
				'taxonomy'     => $taxonomy,
				'field'        => 'slug',
				'terms'        => $slug,
				'brx_no_merge' => true, // Ensure this tax_query executed as 'AND' (@since 1.12)
			];

			// Check if tax_query is already set
			if ( isset( $query_vars['tax_query'] ) ) {
				$query_vars['tax_query'][] = $tax_query;
			} else {
				$query_vars['tax_query'] = [ $tax_query ];
			}
		}

		return $query_vars;
	}

	/**
	 * Identify if the page_filters should be applied in the query_vars
	 * Special handling for tax_query
	 *
	 * @since 1.11
	 */
	public static function should_apply_page_filters( $query_vars ) {
		$page_filters = self::$page_filters ?? [];

		if ( empty( $page_filters ) ) {
			return false;
		}

		// If no tax_query, directly merge the page_filters
		if ( empty( $query_vars['tax_query'] ) || ! is_array( $query_vars['tax_query'] ) ) {
			return true;
		}

		// Check if the page_filters taxonomy already exists in the query_vars
		$page_filters_tax = array_keys( $page_filters )[0] ?? false;
		if ( ! $page_filters_tax ) {
			return false;
		}

		$tax_query = $query_vars['tax_query'];
		$tax_found = false;

		foreach ( $tax_query as $tax ) {
			// Skip non-array items or arrays without 'taxonomy' key (@since 2.1)
			if ( ! is_array( $tax ) || ! isset( $tax['taxonomy'] ) ) {
				continue;
			}

			if ( $tax['taxonomy'] == $page_filters_tax ) {
				$tax_found = true;
				break;
			}
		}

		// Return if the page_filters taxonomy already exists in the query_vars
		if ( $tax_found ) {
			return false;
		}

		return true;

	}

	/**
	 * Populate query vars for sorting type filter
	 *
	 * @since 1.11
	 */
	private static function build_sort_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings     = $filter['settings'];
		$filter_value = $filter['value'];
		$sort_options = ! empty( $settings['sortOptions'] ) ? $settings['sortOptions'] : false;

		if ( ! $sort_options ) {
			return $query_vars;
		}

		$selected_option = self::get_selected_sort_option( $filter_value, $sort_options );

		if ( ! $selected_option ) {
			return $query_vars;
		}

		$key         = $selected_option['key'];
		$order       = $selected_option['order'];
		$sort_source = $selected_option['optionSource'] ?? false;

		if ( ! $sort_source ) {
			return $query_vars;
		}

		// Remove the prefix if it is term or user
		$sort_source = str_replace( [ 'term|', 'user|' ], '', $sort_source );

		// Check if the source is meta_value or meta_value_num
		$is_custom_field = in_array( $sort_source, [ 'meta_value','meta_value_num' ], true ) && ! empty( $selected_option['optionMetaKey'] );
		// Check if the source is meta_value_num
		$is_numeric = $sort_source === 'meta_value_num' ? true : false;
		$sort_query = [];

		if ( $is_custom_field ) {
			$sort_query['meta_key'] = $key;
		}

		if ( $is_numeric ) {
			$sort_query['orderby']['meta_value_num'] = $order;
			$sort_query['meta_type']                 = 'NUMERIC';
		} else {
			$sort_query['orderby'][ $key ] = $order;
		}

		// WP_Term_Query only accepts string for orderby, rebuild the sort_query
		if ( self::$generating_object_type === 'term' ) {
			$sort_query['orderby'] = $key;
			$sort_query['order']   = $order;
		}

		// $sort_query should override the existing query_vars
		foreach ( $sort_query as $key => $value ) {
			$query_vars[ $key ] = $value;
		}

		// Update $active_filters with the selected option, will be used in other area
		if ( isset( self::$active_filters[ $query_id ][ $filter_index ] ) ) {
			self::$active_filters[ $query_id ][ $filter_index ]['sort_option_info'] = $selected_option;
			self::$active_filters[ $query_id ][ $filter_index ]['query_vars']       = $sort_query;
			self::$active_filters[ $query_id ][ $filter_index ]['query_type']       = 'sort';
		}

		return $query_vars;
	}

	public static function get_selected_sort_option( $filter_value, $sort_options ) {

		// The value is combination of the source value and the order value. Example: ID_ASC, selling_price_DESC
		$sort_value = explode( '_', $filter_value );
		if ( count( $sort_value ) < 2 ) {
			// Something wrong with the value
			return false;
		} elseif ( count( $sort_value ) > 2 ) {
			$order = array_pop( $sort_value );
			$key   = implode( '_', $sort_value );
		} else {
			$key   = $sort_value[0];
			$order = $sort_value[1];
		}

		if ( ! $key || ! $order ) {
			return false;
		}

		// Find the selected option
		$selected_option = array_filter(
			$sort_options,
			function( $option ) use ( $key, $order ) {
				$db_source = $option['optionSource'] ?? '';

				// If the source contains |, means it is a term or user, just remove the prefix (@since 1.12)
				$db_source = str_replace( [ 'term|', 'user|' ], '', $db_source );

				$db_order = $option['optionOrder'] ?? 'ASC';

				$custom_key = isset( $option['optionMetaKey'] ) && in_array( $db_source, [ 'meta_value', 'meta_value_num' ], true ) ? $option['optionMetaKey'] : false;

				// Check if the selected option matches the key and order
				return ( $db_source === $key || $custom_key === $key ) && $db_order === $order;
			}
		);

		$selected_option = array_shift( $selected_option );

		if ( ! $selected_option ) {
			return false;
		}

		$selected_option['key']   = $key;
		$selected_option['order'] = $order;

		return $selected_option;
	}

	/**
	 * Populate query vars for perPage type filter
	 *
	 * @since 1.12.2
	 */
	private static function build_per_page_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings     = $filter['settings'];
		$filter_value = (int) $filter['value'];
		// Get per page options array via settings
		$per_page_array = \Bricks\Filter_Element::get_per_page_options_array( $settings );

		if ( ! in_array( $filter_value, $per_page_array ) ) {
			return $query_vars;
		}

		$query_object_type = self::get_generating_type();
		$per_page_query    = [];

		switch ( $query_object_type ) {
			case 'post':
				$per_page_query               = [ 'posts_per_page' => $filter_value ];
				$query_vars['posts_per_page'] = $filter_value;
				break;

			case 'term':
				$per_page_query       = [ 'number' => $filter_value ];
				$query_vars['number'] = $filter_value;
				break;

			case 'user':
				$per_page_query       = [ 'number' => $filter_value ];
				$query_vars['number'] = $filter_value;
				break;

			default:
				break;
		}

		// Update $active_filters with the selected option, will be used in other area
		if ( isset( self::$active_filters[ $query_id ][ $filter_index ] ) ) {
			self::$active_filters[ $query_id ][ $filter_index ]['query_vars']       = $per_page_query;
			self::$active_filters[ $query_id ][ $filter_index ]['query_type']       = 'per_page';
			self::$active_filters[ $query_id ][ $filter_index ]['per_page_options'] = $per_page_array;
		}

		return $query_vars;
	}

	/**
	 * Populate query vars for filter type filter
	 * source: taxonomy
	 *
	 * @since 1.11
	 */
	private static function build_taxonomy_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings      = $filter['settings'];
		$filter_value  = $filter['value'];
		$filter_source = $settings['filterSource'] ?? false;
		$taxonomy      = $settings['filterTaxonomy'] ?? false;
		$combine_logic = $settings['filterMultiLogic'] ?? 'OR';
		$instance_name = $filter['instance_name'];

		if ( ! $filter_source || ! $taxonomy ) {
			return $query_vars;
		}

		if ( $combine_logic === 'AND' && $instance_name === 'filter-checkbox' && is_array( $filter_value ) ) {
			// Generate taxonomy to fulfill AND (@since 1.11.1)
			foreach ( $filter_value as $value ) {
				$tax_query[] = [
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $value,
				];
			}

			$tax_query['relation'] = 'AND';
		} else {
			$tax_query = [
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $filter_value,
			];
		}

		// Check if tax_query is already set
		if ( isset( $query_vars['tax_query'] ) ) {
			$query_vars['tax_query'][] = $tax_query;
		} else {
			$query_vars['tax_query'] = [ $tax_query ];
		}

		// Update $active_filters with the selected option, will be used in other area
		if ( isset( self::$active_filters[ $query_id ][ $filter_index ] ) ) {
			self::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = $tax_query;
			self::$active_filters[ $query_id ][ $filter_index ]['query_type'] = 'tax_query';
		}

		return $query_vars;
	}

	/**
	 * Populate query vars for filter type filter
	 * source: wpField
	 *
	 * @since 1.11
	 */
	private static function build_wp_field_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings      = $filter['settings'];
		$filter_value  = $filter['value'];
		$field_type    = $settings['sourceFieldType'] ?? 'post';
		$source_field  = false;
		$instance_name = $filter['instance_name'];

		switch ( $field_type ) {
			case 'post':
				$source_field = $settings['wpPostField'] ?? false;

				if ( ! $source_field ) {
					return $query_vars;
				}

				break;

			case 'user':
				$source_field = $settings['wpUserField'] ?? false;

				if ( ! $source_field ) {
					return $query_vars;
				}
				break;

			case 'term':
				$source_field = $settings['wpTermField'] ?? false;

				if ( ! $source_field ) {
					return $query_vars;
				}
				break;

		}

		// Check if source_field is set
		if ( ! $source_field ) {
			return $query_vars;
		}

		switch ( $source_field ) {
			// POST
			case 'post_date':
				$key = 'date';
				break;

			case 'post_modified':
				$key = 'modified';
				break;

			case 'post_author':
				$key = $instance_name === 'filter-checkbox' ? 'author__in' : 'author'; // For checkbox, use author__in (@since 2.0)
				break;

			case 'post_id':
				$key = 'p';
				break;

			// USER
			case 'user_registered':
				$key = 'registered';

				break;

			case 'user_role':
				$key = 'role__in';
				break;

			case 'user_id':
				$key = 'include'; // For user, use include to filter by user ID
				break;

			// TERM
			case 'term_id':
				$key = 'include'; // For term, use include to filter by term ID
				break;

			default:
				$key = $source_field;
				break;
		}

		$wp_field_query = [
			$key => $filter_value,
		];

		// Update $active_filters
		if ( isset( self::$active_filters[ $query_id ][ $filter_index ] ) ) {
			self::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = $wp_field_query;
			self::$active_filters[ $query_id ][ $filter_index ]['query_type'] = 'wp_query';
		}

		// $wp_field_query should override the existing query_vars
		foreach ( $wp_field_query as $key => $value ) {
			$query_vars[ $key ] = $value;
		}

		return $query_vars;
	}

	/**
	 * Populate query vars for filter type filter
	 * source: customField
	 *
	 * @since 1.11
	 */
	private static function build_custom_field_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings         = $filter['settings'];
		$filter_value     = $filter['value'];
		$field_type       = $settings['sourceFieldType'] ?? 'post';
		$custom_field_key = $settings['customFieldKey'] ?? false;
		$provider         = $settings['fieldProvider'] ?? 'none';
		$combine_logic    = $settings['filterMultiLogic'] ?? 'OR';

		if ( ! $field_type || ! $custom_field_key ) {
			return $query_vars;
		}

		// Old Logic (meta_query) (Initially wanted to use 'post__in' but encounter many bugs when cobining with multiple filters)
		$instance_name = $filter['instance_name'];

		if ( isset( $settings['fieldCompareOperator'] ) ) {
			$compare_operator = $settings['fieldCompareOperator'];
		} else {
			// Default compare operator for filter-select and filter-radio is =, for others is IN
			$compare_operator = in_array( $instance_name, [ 'filter-select', 'filter-radio' ], true ) ? '=' : 'IN';
		}

		if ( $combine_logic === 'AND' && $instance_name === 'filter-checkbox' && is_array( $filter_value ) ) {
			// Generate meta_query to fulfill AND (@since 1.11.1)
			foreach ( $filter_value as $value ) {
				$meta_query[] = [
					'key'     => $custom_field_key,
					'value'   => $value,
					'compare' => $compare_operator,
				];
			}

			// Add relation
			$meta_query['relation'] = $combine_logic;
		}

		// Normal Logic
		else {
			$meta_query = [
				'key'     => $custom_field_key,
				'value'   => $filter_value,
				'compare' => $compare_operator,
			];
		}

		// NOTE: Undocumented - Not ready for third-party plugins (@since 1.11.1)
		$meta_query = apply_filters( 'bricks/query_filters/custom_field_meta_query', $meta_query, $filter, $provider, $query_id );

		// Check if meta_query is already set
		if ( ! empty( $meta_query ) ) {
			if ( isset( $query_vars['meta_query'] ) ) {
				$query_vars['meta_query'][] = $meta_query;
			} else {
				$query_vars['meta_query'] = [ $meta_query ];
			}

			// Update $active_filters
			if ( isset( self::$active_filters[ $query_id ][ $filter_index ] ) ) {
				self::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = $meta_query;
				self::$active_filters[ $query_id ][ $filter_index ]['query_type'] = 'meta_query';
			}
		}

		return $query_vars;
	}

	/**
	 * Populate query vars for pagination filter
	 *
	 * @since 1.11
	 */
	private static function build_pagination_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings     = $filter['settings'];
		$filter_value = $filter['value'];

		// Set the paged query var
		$query_vars['paged'] = (int) $filter_value;

		// Update $active_filters with the selected option, will be used in other area
		if ( isset( self::$active_filters[ $query_id ][ $filter_index ] ) ) {
			self::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = [ 'paged' => (int) $filter_value ];
			self::$active_filters[ $query_id ][ $filter_index ]['query_type'] = 'pagination';
		}

		return $query_vars;
	}

	/**
	 * Populate query vars for search filter
	 *
	 * @since 1.11
	 */
	private static function build_search_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings          = $filter['settings'];
		$filter_value      = $filter['value'];
		$query_object_type = self::get_generating_type();

		$search_query = [];

		switch ( $query_object_type ) {
			case 'post':
				// Hardcoded search key until filter-search element supports custom key
				$search_query                = [
					's' => $filter_value,
				];
				$query_vars['s']             = $filter_value;
				$query_vars['brx_is_search'] = true;
				break;

			// Support term query (@since 1.12)
			case 'term':
				$search_query                = [
					'search' => $filter_value,
				];
				$query_vars['search']        = $filter_value;
				$query_vars['brx_is_search'] = true;
				break;

			// Support user query (@since 1.12)
			case 'user':
				$search_query                = [
					'search' => '*' . $filter_value . '*',
				];
				$query_vars['search']        = '*' . $filter_value . '*';
				$query_vars['brx_is_search'] = true;
				break;

			default:
				break;
		}

		// Update $active_filters with the selected option, will be used in other area
		if ( isset( self::$active_filters[ $query_id ][ $filter_index ] ) ) {
			self::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = $search_query; // [ 's' => $filter_value ];
			self::$active_filters[ $query_id ][ $filter_index ]['query_type'] = 'wp_query';
		}

		return $query_vars;
	}

	/**
	 * Populate query vars for range filter
	 *
	 * @since 1.11
	 */
	private static function build_filter_range_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings     = $filter['settings'];
		$filter_value = $filter['value'];
		$field_type   = $settings['sourceFieldType'] ?? 'post';
		$field_source = $settings['filterSource'] ?? 'customField';
		$range_key    = $settings['customFieldKey'] ?? false;
		$provider     = $settings['fieldProvider'] ?? 'none';

		if ( ! $range_key || $field_source !== 'customField' || ! is_array( $filter_value ) || count( $filter_value ) !== 2 ) {
			return $query_vars;
		}

		// Ensure values are float
		$filter_value = array_map( 'floatval', $filter_value );

		// Ensure smallest value is first
		sort( $filter_value );

		$range_query = [
			'key'     => $range_key,
			'value'   => [ $filter_value[0], $filter_value[1] ], // Min and Max
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		];

		// NOTE: Undocumented - Not ready for third-party plugins (@since 1.11.1)
		$range_query = apply_filters( 'bricks/query_filters/range_custom_field_meta_query', $range_query, $filter, $provider, $query_id );

		if ( ! empty( $range_query ) ) {
			// Check if meta_query is already set
			if ( isset( $query_vars['meta_query'] ) ) {
				$query_vars['meta_query'][] = $range_query;
			} else {
				$query_vars['meta_query'] = [ $range_query ];
			}

			// Update $active_filters
			if ( isset( self::$active_filters[ $query_id ][ $filter_index ] ) ) {
				self::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = $range_query;
				self::$active_filters[ $query_id ][ $filter_index ]['query_type'] = 'meta_query';
			}
		}

		return $query_vars;
	}

	/**
	 * Populate query vars for datepicker
	 *
	 * @since 1.11
	 */
	private static function build_datepicker_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings     = $filter['settings'];
		$filter_value = $filter['value'];
		$field_type   = $settings['sourceFieldType'] ?? 'post';
		$field_source = $settings['filterSource'] ?? 'wpField';
		$custom_key   = $settings['customFieldKey'] ?? false;
		$mode         = isset( $settings['isDateRange'] ) ? 'range' : 'single';
		$operator     = $settings['fieldCompareOperator'] ?? 'is';
		$enable_time  = isset( $settings['enableTime'] );
		$provider     = $settings['fieldProvider'] ?? 'none';
		$query        = [];

		/**
		 * STEP: Parse date
		 * range mode, 2024-05-01 to 2024-05-15
		 * single mode, 2024-05-01
		 * time enabled, 2024-05-01 09:00 to 2024-05-15 13:30 or 2024-05-01 09:00
		 */
		$parsed_dates = self::parse_datepicker_date( $filter_value );

		// Ebsure $parsed_dates count is match with the mode
		if ( $mode === 'range' && count( $parsed_dates ) !== 2 ) {
			return $query_vars;
		} elseif ( $mode === 'single' && count( $parsed_dates ) !== 1 ) {
			return $query_vars;
		}

		// handle wpField
		if ( $field_source === 'wpField' ) {
			$field_key   = $settings['wpPostField'] ?? 'post_date';
			$date_values = [];
			// Get necessary value from parsed_dates
			foreach ( $parsed_dates as $key => $date ) {
				// We only need year, month, day, hour, minute
				$needed_keys = [ 'year', 'month', 'day' ];
				if ( $enable_time ) {
					$needed_keys = array_merge( $needed_keys, [ 'hour', 'minute' ] );
				}

				$date_values[ $key ] = array_intersect_key( $date, array_flip( $needed_keys ) );
			}

			// Build Date Query
			// 'single' mode
			if ( $mode === 'single' && isset( $date_values['start'] ) ) {
				if ( $operator === 'is' ) {
					$query = $date_values['start'];
				} else {
					$query = [
						$operator => $date_values['start']
					];
				}
			}

			// 'range' mode
			elseif ( $mode === 'range' && isset( $date_values['start'], $date_values['end'] ) ) {
				$query = [
					'after'     => $date_values['start'],
					'before'    => $date_values['end'],
					'inclusive' => true // Include selected dates (instead of between)
				];
			}

			// If $field_key is not post_date, use column parameter
			if ( $field_key !== 'post_date' ) {
				$query['column'] = $field_key;
			}

			// Check if date_query is already set
			if ( isset( $query_vars['date_query'] ) ) {
				$query_vars['date_query'][] = $query;
			} else {
				$query_vars['date_query'] = [ $query ];
			}

		}

		// handle customField
		elseif ( $field_source === 'customField' ) {
			$date_values = [];
			// Get necessary value from parsed_dates
			foreach ( $parsed_dates as $key => $date ) {
				// We only need string (Y-m-d H:i)
				if ( isset( $date['string'] ) ) {
					$date_string = $date['string'];
					if ( ! $enable_time ) {
						$date_string = explode( ' ', $date_string )[0];
					}
					$date_values[ $key ] = $date_string;
				}
			}

			// Build Meta Query
			// 'single' mode
			if ( $mode === 'single' && isset( $date_values['start'] ) ) {
				$query = [
					'key'     => $custom_key,
					'value'   => $date_values['start'],
					'compare' => '=',
					'type'    => $enable_time ? 'DATETIME' : 'DATE',
				];

				if ( $operator !== 'is' ) {
					$query['compare'] = $operator === 'before' ? '<=' : '>=';
				}
			}

			// 'range' mode
			elseif ( $mode === 'range' && isset( $date_values['start'], $date_values['end'] ) ) {
				foreach ( $parsed_dates as $key => $date ) {
					$query[] = [
						'key'     => $custom_key,
						'value'   => $date_values[ $key ],
						'compare' => $key === 'start' ? '>=' : '<=',
						'type'    => $enable_time ? 'DATETIME' : 'DATE',
					];
				}
			}

			// NOTE: Undocumented - Not ready for third-party plugins (@since 1.11.1)
			$query = apply_filters( 'bricks/query_filters/datepicker_custom_field_meta_query', $query, $filter, $provider, $query_id );

			// Check if meta_query is already set
			if ( isset( $query_vars['meta_query'] ) ) {
				$query_vars['meta_query'][] = $query;
			} else {
				$query_vars['meta_query'] = [ $query ];
			}
		}

		// Update $active_filters
		if ( isset( self::$active_filters[ $query_id ][ $filter_index ] ) ) {
			self::$active_filters[ $query_id ][ $filter_index ]['query_vars']   = $query;
			self::$active_filters[ $query_id ][ $filter_index ]['parsed_dates'] = $parsed_dates;

			if ( $field_source === 'wpField' ) {
				self::$active_filters[ $query_id ][ $filter_index ]['query_type'] = 'date_query';
			} elseif ( $field_source === 'customField' ) {
				self::$active_filters[ $query_id ][ $filter_index ]['query_type'] = 'meta_query';
			}
		}

		return $query_vars;
	}

	/**
	 * Parse datepicker date string into formatted array
	 *
	 * @since 1.11
	 */
	private static function parse_datepicker_date( $date_string ) {
		$result = [];

		// Define a pattern to match and capture the date and time components separated by a comma with no spaces
		$pattern = '/^(\d{4}-\d{2}-\d{2})(?: (\d{2}:\d{2}))?(?:,(\d{4}-\d{2}-\d{2})(?: (\d{2}:\d{2}))?)?$/';

		if ( preg_match( $pattern, $date_string, $matches ) ) {
			// Parse start date and time
			$start_date = $matches[1];
			$start_time = $matches[2] ?? null;

			$start_date_time = new \DateTime( $start_date . ( $start_time ? " $start_time" : '' ) );
			$result['start'] = [
				'year'   => $start_date_time->format( 'Y' ),
				'month'  => ltrim( $start_date_time->format( 'm' ), '0' ), // Remove leading zero
				'day'    => ltrim( $start_date_time->format( 'd' ), '0' ), // Remove leading zero
				'hour'   => $start_time ? $start_date_time->format( 'H' ) : null,
				'minute' => $start_time ? $start_date_time->format( 'i' ) : null,
				'string' => $start_date_time->format( 'Y-m-d H:i' ),
				'object' => $start_date_time,
			];

			// Parse end date and time if present
			if ( isset( $matches[3] ) ) {
				$end_date = $matches[3];
				$end_time = $matches[4] ?? null;

				$end_date_time = new \DateTime( $end_date . ( $end_time ? " $end_time" : '' ) );
				$result['end'] = [
					'year'   => $end_date_time->format( 'Y' ),
					'month'  => ltrim( $end_date_time->format( 'm' ), '0' ), // Remove leading zero
					'day'    => ltrim( $end_date_time->format( 'd' ), '0' ), // Remove leading zero
					'hour'   => $end_time ? $end_date_time->format( 'H' ) : null,
					'minute' => $end_time ? $end_date_time->format( 'i' ) : null,
					'string' => $end_date_time->format( 'Y-m-d H:i' ),
					'object' => $end_date_time,
				];
			}
		} elseif ( preg_match( '/^(\d{4}-\d{2}-\d{2})(?: (\d{2}:\d{2}))?$/', $date_string, $matches ) ) {
			// Single date or datetime without range
			$start_date = $matches[1];
			$start_time = $matches[2] ?? null;

			$start_date_time = new \DateTime( $start_date . ( $start_time ? " $start_time" : '' ) );
			$result['start'] = [
				'year'   => $start_date_time->format( 'Y' ),
				'month'  => ltrim( $start_date_time->format( 'm' ), '0' ), // Remove leading zero
				'day'    => ltrim( $start_date_time->format( 'd' ), '0' ), // Remove leading zero
				'hour'   => $start_time ? $start_date_time->format( 'H' ) : null,
				'minute' => $start_time ? $start_date_time->format( 'i' ) : null,
				'string' => $start_date_time->format( 'Y-m-d H:i' ),
				'object' => $start_date_time,
			];
		} else {
			$result = [];
		}

		return $result;
	}


	/**
	 * Updated filters to be used in frontend after each filter ajax request
	 */
	public static function get_updated_filters( $filters = [], $post_id = 0 ) {
		$updated_filters = [];

		// Loop through all filter_ids and gather elements that need to be updated
		$valid_elements = [];

		foreach ( $filters as $filter_id ) {
			$element_data   = Helpers::get_element_data( $post_id, $filter_id );
			$filter_element = $element_data['element'] ?? false;

			// Check if $filter_element exists
			if ( ! $filter_element || empty( $filter_element ) ) {
				continue;
			}

			if ( ! in_array( $filter_element['name'], self::dynamic_update_elements(), true ) ) {
				continue;
			}

			$filter_settings = $filter_element['settings'] ?? [];
			$filter_action   = $filter_settings['filterAction'] ?? 'filter';

			// Skip: filter_action is not set to filter
			// if ( $filter_action !== 'filter' ) {
			// continue;
			// }

			// Valid elements will regenerate new HTML
			$valid_elements[ $filter_id ] = $filter_element;
		}

		// Loop through all valid elements and generate index
		foreach ( $valid_elements as $filter_id => $element ) {
			$updated_filters[ $filter_id ] = Frontend::render_element( $element );
		}

		return $updated_filters;
	}

	/**
	 * Get filtered data from index table
	 */
	public static function get_filtered_data_from_index( $filter_id = '', $object_ids = [] ) {
		if ( empty( $filter_id ) ) {
			return [];
		}

		// Improve performance by using cache (not object cache) (@since 1.12)
		// Static cache for the function results
		static $cache = [];

		// Generate a unique cache key based on input parameters
		$cache_key = md5( $filter_id . wp_json_encode( $object_ids ) );

		// Check if the cache exists
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		global $wpdb;

		$table_name = self::get_table_name();

		$where_clause = '';
		$params       = [ $filter_id ];

		// If object_ids is set, add to where clause
		if ( ! empty( $object_ids ) ) {
			$placeholders = array_fill( 0, count( $object_ids ), '%d' );
			$placeholders = implode( ',', $placeholders );
			$where_clause = "AND object_id IN ({$placeholders})";
			$params       = array_merge( $params, $object_ids );
		}

		$sql = "SELECT filter_value, filter_value_display, filter_value_id, filter_value_parent, COUNT(DISTINCT object_id) AS count
		FROM {$table_name}
		WHERE filter_id = %s {$where_clause}
		GROUP BY filter_value, filter_value_display, filter_value_id, filter_value_parent";

		// Get all filter values for this filter_id
		$filter_values = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				$params
			),
			ARRAY_A
		);

		// Cache the result
		$cache[ $cache_key ] = $filter_values;

		return $filter_values ?? [];
	}

	/**
	 * Get all possible object ids from a query
	 * To be used in get_filtered_data()
	 * Each query_id will only be queried once
	 *
	 * @param string $query_id
	 * @return array $all_object_ids
	 */
	public static function get_filter_object_ids( $query_id = '', $source = 'history', $additonal_query_vars = [] ) {
		if ( empty( $query_id ) ) {
			return [];
		}

		$cache_key = $query_id . '_' . $source;

		if ( isset( $additonal_query_vars ) && is_array( $additonal_query_vars ) ) {
			$cache_key .= '_' . md5( json_encode( $additonal_query_vars ) );
		}
		// Check if query_id is inside self::$filter_object_ids, if yes, return the object_ids
		if ( isset( self::$filter_object_ids[ $cache_key ] ) ) {
			return self::$filter_object_ids[ $cache_key ];
		}

		$query_data = Query::get_query_by_element_id( $query_id );

		// Return empty array if query_data is empty
		if ( ! $query_data ) {
			return [];
		}

		$query_vars = $query_data->query_vars ?? [];

		if ( $source === 'original' && isset( self::$query_vars_before_merge[ $query_id ] ) ) {
			$query_vars = self::$query_vars_before_merge[ $query_id ];
		}

		$query_type = $query_data->object_type ?? 'post';

		// Support post, term, user
		if ( ! in_array( $query_type, [ 'post', 'term', 'user' ] ) ) {
			return [];
		}

		$all_object_ids = [];

		switch ( $query_type ) {
			case 'post':
				// Use the query_vars and get all possible post ids
				$all_posts_args = array_merge(
					$query_vars,
					[
						'paged'                  => 1,
						'posts_per_page'         => -1,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						'cache_results'          => false,
						'no_found_rows'          => true,
						'nopaging'               => true,
						'fields'                 => 'ids',
					]
				);

				if ( ! empty( $additonal_query_vars ) ) {
					$all_posts_args = Query::merge_query_vars( $all_posts_args, $additonal_query_vars );
				}

				$all_posts = new \WP_Query( $all_posts_args );

				$all_object_ids = $all_posts->posts;

				break;

			case 'term':
				// Use the query_vars and get all possible term ids
				$all_terms_args = array_merge(
					$query_vars,
					[
						'hide_empty'    => false,
						'fields'        => 'ids',
						'number'        => 0,
						'offset'        => 0,
						'orderby'       => 'id',
						'cache_results' => false,
					]
				);

				if ( ! empty( $additonal_query_vars ) ) {
					$all_terms_args = Query::merge_query_vars( $all_terms_args, $additonal_query_vars );
				}

				$all_terms = new \WP_Term_Query( $all_terms_args );

				$all_object_ids = $all_terms->get_terms();

				break;

			case 'user':
				// Use the query_vars and get all possible user ids
				$all_users_args = array_merge(
					$query_vars,
					[
						'number'  => -1,
						'offset'  => 0,
						'fields'  => 'ID',
						'orderby' => 'ID',
					]
				);

				if ( ! empty( $additonal_query_vars ) ) {
					$all_users_args = Query::merge_query_vars( $all_users_args, $additonal_query_vars );
				}

				$all_users = new \WP_User_Query( $all_users_args );

				$all_object_ids = $all_users->get_results();

				break;
		}

		// Undocumented: For Event Calendar Pro (#86c1193hv)
		$all_object_ids = apply_filters( 'bricks/query_filters/get_filter_object_ids', $all_object_ids, $query_id, $query_type, $source );

		/**
		 * Consider user offset in query loop settings
		 *
		 * @since 1.11
		 */
		switch ( $query_type ) {
			case 'post':
			case 'user':
				if ( isset( $query_vars['offset'] ) && $query_vars['offset'] > 0 ) {
					$all_object_ids = array_slice( $all_object_ids, $query_vars['offset'] );
				}

				break;

			case 'term':
				if ( isset( $query_vars['original_offset'] ) && $query_vars['original_offset'] > 0 ) {
					$all_object_ids = array_slice( $all_object_ids, $query_vars['original_offset'] );
				}
				break;
		}

		// Store the object_ids in self::$filter_object_ids
		self::$filter_object_ids[ $cache_key ] = $all_object_ids;

		return $all_object_ids;
	}

	/**
	 * Generate index when a post is saved
	 */
	public function save_post( $post_id, $post ) {

		// Revision
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// auto-draft
		if ( $post->post_status === 'auto-draft' ) {
			return;
		}

		$this->index_post( $post_id );
		self::$is_saving_post = false;
	}

	/**
	 * Remove index when a post is deleted
	 * - Also used in maybe_update_element() when no more elements are found in the post (@since 2.0)
	 */
	public function delete_post( $post_id ) {
		// Remove rows related to this post_id
		self::remove_index_rows(
			[
				'object_id'   => $post_id,
				'object_type' => 'post',
			]
		);

		/**
		 * Maybe this post contains filter elements
		 *
		 * Must remove filter elements from element table, and related rows from index table.
		 *
		 * @since 1.9.8
		 */
		// STEP: Get all filter elements from this post_id
		$all_db_elements = $this->get_elements_from_element_table( [ 'post_id' => $post_id ], false );

		// Just get the filter_id
		$all_db_elements_ids = array_column( $all_db_elements, 'filter_id' );

		$indexer = Query_Filters_Indexer::get_instance();

		// STEP: Remove rows related to these filter_ids
		foreach ( $all_db_elements_ids as $filter_id ) {
			// Remove all index jobs related to this filter_id (@since 1.10.2)
			$job = $indexer->get_active_job_for_element( $filter_id );
			if ( $job ) {
				$indexer->remove_job( $job );
			}
			self::remove_index_rows( [ 'filter_id' => $filter_id ] );
		}

		// Remove elements related to this post_id
		$this->delete_element( [ 'post_id' => $post_id ] );
	}

	/**
	 * Set is_saving_post to true when a post is assigned to a parent to avoid reindexing
	 * Triggered when using wp_insert_post()
	 */
	public function wp_insert_post_parent( $post_parent, $post_id, $new_postarr, $postarr ) {
		// Set is_saving_post to true
		self::$is_saving_post = true;

		return $post_parent;
	}

	/**
	 * Generate index when a post is assigened to a term
	 * Triggered when using wp_set_post_terms() or wp_set_object_terms()
	 */
	public function set_object_terms( $object_id ) {
		if ( self::$is_saving_post ) {
			return;
		}

		$this->index_post( $object_id );
	}

	/**
	 * Core function to index a post based on all active indexable filter elements
	 *
	 * @param int $post_id
	 */
	public function index_post( $post_id ) {
		if ( empty( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		// Get all indexable and active filter elements from element table
		$indexable_elements = $this->get_elements_from_element_table(
			[
				'indexable' => 1,
				'status'    => 1
			]
		);

		if ( empty( $indexable_elements ) ) {
			return;
		}

		// NOTE: Undocumented - Not ready for third-party plugins - Trigger action before indexing post (@since 1.11.1)
		do_action( 'bricks/query_filters/index_post/before', $post_id );

		// Loop through all indexable elements and group them up by filter_source
		$grouped_elements = [];

		foreach ( $indexable_elements as $element ) {
			// filter_settings is json string
			$filter_settings = json_decode( $element['settings'], true );
			$filter_source   = $filter_settings['filterSource'] ?? false;

			if ( ! $filter_source ) {
				continue;
			}

			// Update filter_settings properly
			$element['settings'] = $filter_settings;

			if ( $filter_source === 'taxonomy' ) {
				$filter_taxonomy = $filter_settings['filterTaxonomy'] ?? false;
				if ( ! $filter_taxonomy ) {
					continue;
				}
				$key                        = $filter_source . '|' . $filter_taxonomy;
				$grouped_elements[ $key ][] = $element;
			} else {
				$grouped_elements[ $filter_source ][] = $element;
			}

		}

		// Loop through all grouped elements and generate index
		foreach ( $grouped_elements as $source => $elements ) {
			$rows_to_insert = [];

			// If contains |, it means taxonomy (@since 2.0)
			$group_type = strpos( $source, '|' ) !== false ? 'taxonomy' : $source;

			// Build $rows
			switch ( $group_type ) {
				case 'wpField':
					$post_fields = [];
					foreach ( $elements as $element ) {
						// check what is the selected field
						$filter_settings = $element['settings'];
						$field_type      = $filter_settings['sourceFieldType'] ?? 'post';

						if ( ! $field_type || $field_type !== 'post' ) {
							continue;
						}

						$selected_field = $filter_settings['wpPostField'] ?? false;

						if ( ! $selected_field ) {
							continue;
						}

						if ( isset( $post_fields[ $selected_field ] ) ) {
							$post_fields[ $selected_field ][] = $element['filter_id'];
						} else {
							$post_fields[ $selected_field ] = [ $element['filter_id'] ];
						}
					}

					if ( ! empty( $post_fields ) ) {
						// Generate rows for each post_field
						foreach ( $post_fields as $post_field => $filter_ids ) {

							$rows_for_this_post_field = self::generate_post_field_index_rows( $post, $post_field );

							// Build $rows_to_insert
							if ( ! empty( $rows_for_this_post_field ) && ! empty( $filter_ids ) ) {
								// Add filter_id to each row, row is the standard template, do not overwrite it.
								foreach ( $filter_ids as $filter_id ) {
									$rows_to_insert = array_merge(
										$rows_to_insert,
										array_map(
											function( $row ) use ( $filter_id ) {
												$row['filter_id'] = $filter_id;

												return $row;
											},
											$rows_for_this_post_field
										)
									);
								}
							}

							// Remove rows related to this filter_id and post_id
							foreach ( $filter_ids as $filter_id ) {
								self::remove_index_rows(
									[
										'filter_id' => $filter_id,
										'object_id' => $post_id,
									]
								);
							}
						}

					}

					break;

				case 'customField':
					$meta_keys = [];

					// STEP: Gather all meta keys from each element settings
					foreach ( $elements as $element ) {
						// filter_settings is json string
						$filter_settings = $element['settings'];
						$meta_key        = $filter_settings['customFieldKey'] ?? false;
						$source_type     = $filter_settings['sourceFieldType'] ?? 'post';
						$provider        = $filter_settings['fieldProvider'] ?? 'none';

						if ( ! $meta_key || $source_type !== 'post' ) {
							continue;
						}

						// Logic to detect if the meta_key is exits on this post
						// STEP: Check if this meta_key exists on $post_id
						if ( $provider === 'none' ) {
							if ( ! metadata_exists( 'post', $post_id, $meta_key ) ) {
								continue;
							}
						} else {
							// NOTE: Undocumented - Not ready for third-party plugins (@since 1.11.1)
							if ( ! apply_filters( 'bricks/query_filters/index_post/meta_exists', false, $post_id, $meta_key, $provider ) ) {
								continue;
							}
						}

						$identifier = $meta_key . '||' . $provider;

						// Add filter_id to existing meta_key, so we can add filter_id for each row later
						if ( isset( $meta_keys[ $identifier ] ) ) {
							$meta_keys[ $identifier ][] = $element['filter_id'];
						} else {
							$meta_keys[ $identifier ] = [ $element['filter_id'] ];
						}
					}

					if ( empty( $meta_keys ) ) {
						continue 2;
					}

					// Generate rows for each meta_key
					foreach ( $meta_keys as $identifier => $filter_ids ) {
						// explode the identifier
						$keys     = explode( '||', $identifier );
						$meta_key = $keys[0] ?? false;
						$provider = $keys[1] ?? 'none';

						if ( ! $meta_key ) {
							continue;
						}

						// Generate rows for this meta_key
						$rows_for_this_meta_key = self::generate_custom_field_index_rows( $post_id, $meta_key, $provider, 'post' );

						// Build $rows_to_insert
						if ( ! empty( $rows_for_this_meta_key ) && ! empty( $filter_ids ) ) {
							// Add filter_id to each row, row is the standard template, do not overwrite it. insert rows_to_insert instead after foreach loop
							foreach ( $filter_ids as $filter_id ) {
								$rows_to_insert = array_merge(
									$rows_to_insert,
									array_map(
										function( $row ) use ( $filter_id ) {
											$row['filter_id'] = $filter_id;

											return $row;
										},
										$rows_for_this_meta_key
									)
								);
							}
						}

						// Remove rows related to this filter_id and post_id
						foreach ( $filter_ids as $filter_id ) {
							self::remove_index_rows(
								[
									'filter_id' => $filter_id,
									'object_id' => $post_id,
								]
							);
						}

					}

					break;

				case 'taxonomy':
					// explode the key
					$keys            = explode( '|', $source );
					$filter_source   = $keys[0] ?? false;
					$filter_taxonomy = $keys[1] ?? false;

					if ( ! $filter_source || ! $filter_taxonomy ) {
						continue 2;
					}

					$rows_for_this_taxonomy = self::generate_taxonomy_index_rows( [ $post_id ], $filter_taxonomy );

					// Add filter_id to each row, filter_ids are inside $elements
					$filter_ids = array_column( $elements, 'filter_id' );

					// Build $rows_to_insert
					if ( ! empty( $rows_for_this_taxonomy ) && ! empty( $filter_ids ) ) {
						foreach ( $filter_ids as $filter_id ) {
							// Add filter_id to each row, row is the standard template, do not overwrite it. insert rows_to_insert instead after foreach loop
							$rows_to_insert = array_merge(
								$rows_to_insert,
								array_map(
									function( $row ) use ( $filter_id ) {
										$row['filter_id'] = $filter_id;

										return $row;
									},
									$rows_for_this_taxonomy
								)
							);
						}
					}

					// Remove rows related to this filter_id and post_id
					foreach ( $filter_ids as $filter_id ) {
						self::remove_index_rows(
							[
								'filter_id' => $filter_id,
								'object_id' => $post_id,
							]
						);
					}

					break;

				default:
				case 'unknown':
					$rows_to_insert = apply_filters(
						'bricks/query_filters/index_post/' . $source,
						[],
						$post_id,
						$elements
					);

					if ( ! empty( $rows_to_insert ) ) {
						// Ensure filter_id is set for each row, otherwise empty the entire array for safety
						$error = false;
						foreach ( $rows_to_insert as $key => $row ) {
							if ( ! isset( $row['filter_id'] ) ) {
								$error = true;
								break;
							}
						}

						if ( $error ) {
							$rows_to_insert = [];
						} else {
							$filter_ids = array_unique( array_column( $elements, 'filter_id' ) );

							// Remove rows related to this filter_id and post_id
							foreach ( $filter_ids as $filter_id ) {
								self::remove_index_rows(
									[
										'filter_id' => $filter_id,
										'object_id' => $post_id,
									]
								);
							}
						}

					}
					break;
			}

			// Insert rows into database
			if ( ! empty( $rows_to_insert ) ) {
				self::insert_index_rows( $rows_to_insert );
			}
		}

	}

	/**
	 * Update indexed records when a term is amended (slug, name)
	 */
	public function edited_term( $term_id, $tt_id, $taxonomy ) {

		// Get all indexable and active filter elements from element table
		$indexable_elements = $this->get_elements_from_element_table(
			[
				'indexable' => 1,
				'status'    => 1
			]
		);

		if ( empty( $indexable_elements ) ) {
			return;
		}

		// Only get filter elements that use taxonomy as filter source and filter taxonomy is the same as $taxonomy
		$taxonomy_elements = array_filter(
			$indexable_elements,
			function( $element ) use ( $taxonomy ) {
				$filter_settings = json_decode( $element['settings'], true );
				$filter_source   = $filter_settings['filterSource'] ?? false;
				$filter_taxonomy = $filter_settings['filterTaxonomy'] ?? false;

				if ( $filter_source !== 'taxonomy' || $filter_taxonomy !== $taxonomy ) {
					return false;
				}

				return true;
			}
		);

		if ( empty( $taxonomy_elements ) ) {
			return;
		}

		global $wpdb;
		$table_name    = self::get_table_name();
		$placeholders  = array_fill( 0, count( $taxonomy_elements ), '%s' );
		$placeholders  = implode( ',', $placeholders );
		$term          = get_term( $term_id, $taxonomy );
		$value         = $term->slug;
		$display_value = $term->name;
		$filter_ids    = array_column( $taxonomy_elements, 'filter_id' );

		// Update index table
		$query = "UPDATE {$table_name}
		SET filter_value = %s, filter_value_display = %s
		WHERE filter_id IN ($placeholders) AND filter_value_id = %d";

		$wpdb->query(
			$wpdb->prepare(
				$query,
				array_merge( [ $value, $display_value ], $filter_ids, [ $term_id ] )
			)
		);
	}

	/**
	 * Update indexed records when a term is deleted
	 */
	public function delete_term( $term_id, $tt_id, $taxonomy, $deleted_term ) {
		// Remove index rows related to this term_id (@since 1.12)
		self::remove_index_rows(
			[
				'object_id'   => $term_id,
				'object_type' => 'term',
			]
		);

		// Get all indexable and active filter elements from element table
		$indexable_elements = $this->get_elements_from_element_table(
			[
				'indexable' => 1,
				'status'    => 1
			]
		);

		if ( empty( $indexable_elements ) ) {
			return;
		}

		// Only get filter elements that use taxonomy as filter source and filter taxonomy is the same as $taxonomy
		$taxonomy_elements = array_filter(
			$indexable_elements,
			function( $element ) use ( $taxonomy ) {
				$filter_settings = json_decode( $element['settings'], true );
				$filter_source   = $filter_settings['filterSource'] ?? false;
				$filter_taxonomy = $filter_settings['filterTaxonomy'] ?? false;

				if ( $filter_source !== 'taxonomy' || $filter_taxonomy !== $taxonomy ) {
					return false;
				}

				return true;
			}
		);

		if ( empty( $taxonomy_elements ) ) {
			return;
		}

		global $wpdb;
		$table_name   = self::get_table_name();
		$filter_ids   = array_column( $taxonomy_elements, 'filter_id' );
		$placeholders = array_fill( 0, count( $taxonomy_elements ), '%s' );
		$placeholders = implode( ',', $placeholders );

		// Remove rows related to this term_id
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE filter_id IN ({$placeholders}) AND filter_value_id = %d",
				array_merge( $filter_ids, [ $term_id ] )
			)
		);
	}

	public function user_updated( $user_id ) {
		$this->index_user( $user_id );
	}

	/**
	 * Remove index when a user is deleted
	 */
	public function delete_user( $user_id ) {
		// Remove rows related to this user_id
		self::remove_index_rows(
			[
				'object_id'   => $user_id,
				'object_type' => 'user',
			]
		);
	}

	/**
	 * Core function to index a user based on all active indexable filter elements
	 *
	 * @since 1.12
	 * @param int $user_id
	 */
	public function index_user( $user_id ) {
		// Get all indexable and active filter elements from element table
		$indexable_elements = $this->get_elements_from_element_table(
			[
				'indexable' => 1,
				'status'    => 1
			]
		);

		if ( empty( $indexable_elements ) ) {
			return;
		}

		// Only get filter elements that related to user field
		$user_elements = array_filter(
			$indexable_elements,
			function ( $element ) {
				$filter_settings = json_decode( $element['settings'], true );
				$filter_source   = $filter_settings['filterSource'] ?? false;
				$field_type      = $filter_settings['sourceFieldType'] ?? false;

				$is_user_element = false;

				if (
					$filter_source === 'customField' && $field_type === 'user' ||
					$filter_source === 'wpField' && $field_type === 'user'
				) {
					$is_user_element = true;
				}

				return $is_user_element;
			}
		);

		if ( empty( $user_elements ) ) {
			return;
		}

		// NOTE: Undocumented - Not ready for third-party plugins - Trigger action before indexing post (@since 1.11.1)
		do_action( 'bricks/query_filters/index_user/before', $user_id );

		// Loop through all user elements and group them up by filter_source
		$grouped_elements = [];
		$user             = get_userdata( $user_id );

		foreach ( $user_elements as $element ) {
			// filter_settings is json string
			$filter_settings = json_decode( $element['settings'], true );
			$filter_source   = $filter_settings['filterSource'] ?? false;

			if ( ! $filter_source ) {
				continue;
			}

			// Update filter_settings properly
			$element['settings'] = $filter_settings;

			$grouped_elements[ $filter_source ][] = $element;
		}

		// Loop through all grouped elements and generate index
		foreach ( $grouped_elements as $source => $elements ) {
			$rows_to_insert = [];

			// Build $rows
			switch ( $source ) {
				case 'wpField':
					$user_fields = [];
					foreach ( $elements as $element ) {
						// check what is the selected field
						$filter_settings = $element['settings'];
						$field_type      = $filter_settings['sourceFieldType'] ?? 'post';

						if ( $field_type !== 'user' ) {
							continue;
						}

						$selected_field = $filter_settings['wpUserField'] ?? false;

						if ( ! $selected_field ) {
							continue;
						}

						if ( isset( $user_fields[ $selected_field ] ) ) {
							$user_fields[ $selected_field ][] = $element['filter_id'];
						} else {
							$user_fields[ $selected_field ] = [ $element['filter_id'] ];
						}
					}

					if ( ! empty( $user_fields ) ) {
						// Generate rows for each user_field
						foreach ( $user_fields as $user_field => $filter_ids ) {

							$rows_for_this_user_field = self::generate_user_field_index_rows( $user, $user_field );

							// Build $rows_to_insert
							if ( ! empty( $rows_for_this_user_field ) && ! empty( $filter_ids ) ) {
								// Add filter_id to each row, row is the standard template, do not overwrite it.
								foreach ( $filter_ids as $filter_id ) {
									$rows_to_insert = array_merge(
										$rows_to_insert,
										array_map(
											function( $row ) use ( $filter_id ) {
												$row['filter_id'] = $filter_id;

												return $row;
											},
											$rows_for_this_user_field
										)
									);
								}
							}

							// Remove rows related to this filter_id and user_id
							foreach ( $filter_ids as $filter_id ) {
								self::remove_index_rows(
									[
										'filter_id' => $filter_id,
										'object_id' => $user_id,
									]
								);
							}
						}

					}

					break;

				case 'customField':
					$meta_keys = [];

					// STEP: Gather all meta keys from each element settings
					foreach ( $elements as $element ) {
						// filter_settings is json string
						$filter_settings = $element['settings'];
						$meta_key        = $filter_settings['customFieldKey'] ?? false;
						$source_type     = $filter_settings['sourceFieldType'] ?? 'user';
						$provider        = $filter_settings['fieldProvider'] ?? 'none';

						if ( ! $meta_key || $source_type !== 'user' ) {
							continue;
						}

						// Logic to detect if the meta_key is exits on this user
						// STEP: Check if this meta_key exists on $user_id
						if ( $provider === 'none' ) {
							if ( ! metadata_exists( 'user', $user_id, $meta_key )
							) {
								continue;
							}

						} else {
							// NOTE: Undocumented - Not ready for third-party plugins (@since 1.12)
							if ( ! apply_filters( 'bricks/query_filters/index_user/meta_exists', false, $user_id, $meta_key, $provider ) ) {
								continue;
							}
						}

						$identifier = $meta_key . '||' . $provider;

						// Add filter_id to existing meta_key, so we can add filter_id for each row later
						if ( isset( $meta_keys[ $identifier ] ) ) {
							$meta_keys[ $identifier ][] = $element['filter_id'];
						} else {
							$meta_keys[ $identifier ] = [ $element['filter_id'] ];
						}

					}

					if ( empty( $meta_keys ) ) {
						continue 2;
					}

					// Generate rows for each meta_key
					foreach ( $meta_keys as $identifier => $filter_ids ) {
						// explode the identifier
						$keys     = explode( '||', $identifier );
						$meta_key = $keys[0] ?? false;
						$provider = $keys[1] ?? 'none';

						if ( ! $meta_key ) {
							continue;
						}

						// Generate rows for this meta_key
						$rows_for_this_meta_key = self::generate_custom_field_index_rows( $user_id, $meta_key, $provider, 'user' );

						// Build $rows_to_insert
						if ( ! empty( $rows_for_this_meta_key ) && ! empty( $filter_ids ) ) {
							// Add filter_id to each row, row is the standard template, do not overwrite it. insert rows_to_insert instead after foreach loop
							foreach ( $filter_ids as $filter_id ) {
								$rows_to_insert = array_merge(
									$rows_to_insert,
									array_map(
										function( $row ) use ( $filter_id ) {
											$row['filter_id'] = $filter_id;

											return $row;
										},
										$rows_for_this_meta_key
									)
								);
							}
						}

						// Remove rows related to this filter_id and user_id
						foreach ( $filter_ids as $filter_id ) {
							self::remove_index_rows(
								[
									'filter_id' => $filter_id,
									'object_id' => $user_id,
								]
							);
						}

					}

					break;

			}

			// Insert rows into database
			if ( ! empty( $rows_to_insert ) ) {
				self::insert_index_rows( $rows_to_insert );
			}
		}

	}

	public static function set_generating_type( $object_type ) {
		self::$generating_object_type = $object_type;
	}

	public static function reset_generating_type() {
		self::$generating_object_type = 'post';
	}

	public static function get_generating_type() {
		return self::$generating_object_type;
	}

	/**
	 * Return true if detected corrupted data for query filters
	 *
	 * @since 1.12.2
	 */
	public static function has_corrupted_db() {
		global $wpdb;

		// Check if any duplicated rows exist in the filter element table. (filter_id)
		$element_table = self::get_table_name( 'element' );
		$element_query = "SELECT filter_id, COUNT(filter_id) as count FROM {$element_table} GROUP BY filter_id HAVING count > 1";

		$element_duplicates = $wpdb->get_results( $element_query, ARRAY_A );

		return ! empty( $element_duplicates );
	}

	/**
	 * Get all active filters count for a query_id
	 *
	 * Exclude: pagination, empty instance_name, exclude_filters.
	 *
	 * @since 2.0
	 */
	public static function get_active_filters_count( $query_id = '', $additional_params = [] ) {
		if ( empty( $query_id ) || empty( self::$active_filters ) ) {
			return 0;
		}

		if ( ! isset( self::$active_filters[ $query_id ] ) ) {
			return 0;
		}

		$active_filters       = self::$active_filters[ $query_id ];
		$url_params           = []; // Hold the nicename that collected from the filter settings, avoid duplicate
		$clean_active_filters = [];
		$exclude_filters      = $additional_params['exclude_filters'] ?? [];

		foreach ( $active_filters as $filter_info ) {
			$filter_id       = $filter_info['filter_id'] ?? false;
			$instance_name   = $filter_info['instance_name'] ?? '';
			$filter_settings = $filter_info['settings'] ?? [];
			$url_param       = $filter_settings['filterNiceName'] ?? '';

			// Skip if no filter_id
			if ( ! $filter_id ) {
				continue;
			}

			// Skip if instance_name is empty or pagination
			if ( empty( $instance_name ) || $instance_name === 'pagination' ) {
				continue;
			}

			// Skip if filter_id is in exclude_filters
			if ( in_array( $filter_id, $exclude_filters ) ) {
				continue;
			}

			// Skip if url_param is empty or already exists in $url_params
			if ( ! empty( $url_param ) && in_array( $url_param, $url_params ) ) {
				continue;
			}

			$url_params[]           = $url_param; // Flag this url_param as already exists
			$clean_active_filters[] = $filter_info;
		}

		return count( $clean_active_filters );
	}
}
