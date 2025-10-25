<?php
namespace Bricks\Integrations\Query_Filters;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WooCommerce {
	private static $instance = null;
	private $query_filters;
	public static $cache = [];

	public function __construct() {
		if ( ! \Bricks\Helpers::enabled_query_filters() ) {
			return;
		}

		$this->query_filters = \Bricks\Query_Filters::get_instance();

		// Add more WooCommerce filter controls
		add_filter( 'bricks/filter_element/controls', [ $this, 'add_filter_controls' ], 10, 2 );

		// Filter element counts and data source
		add_filter( 'bricks/filter_element/data_source_wcField', [ $this, 'set_wc_data_source' ], 10, 2 );
		add_filter( 'bricks/filter_element/filtered_source', [ $this, 'modify_wc_rating_filtered_source' ], 10, 2 );
		add_filter( 'bricks/filter_element/count_source_wcField', [ $this, 'modify_wc_rating_count_source' ], 10, 2 );

		// Indexer
		add_filter( 'bricks/query_filters_indexer/validate_job_settings', [ $this, 'validate_wc_job_settings' ], 10, 3 );
		add_filter( 'bricks/query_filters/index_args', [ $this, 'wc_job_index_args' ], 10, 4 );
		add_filter( 'bricks/query_filters_indexer/post/wcField', [ $this, 'wc_product_rows' ], 10, 4 );
		add_filter( 'bricks/query_filters/index_post/wcField', [ $this, 'wc_index_product_on_save' ], 10, 3 );

		// Query vars
		add_filter( 'bricks/query_filters/filter_query_vars', [ $this, 'wc_filter_query_vars' ], 10, 4 );
		add_filter( 'bricks/query_filters/sort_query_vars', [ $this, 'wc_sort_query_vars' ], 10, 4 );
	}

	/**
	 * Singleton - Get the instance of this class
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new WooCommerce();
		}

		return self::$instance;
	}

	/**
	 * Add WooCommerce filter controls
	 */
	public function add_filter_controls( $controls, $element ) {
		$element_name = $element->name ?? '';

		if ( ! in_array( $element_name, [ 'filter-checkbox', 'filter-radio', 'filter-range', 'filter-select' ], true ) ) {
			return $controls;
		}

		// Add more sort optionSource
		$controls['sortOptions']['fields']['optionSource']['options']['wcPrice']  = '(WooCommerce) ' . esc_html__( 'Price', 'bricks' );
		$controls['sortOptions']['fields']['optionSource']['options']['wcRating'] = '(WooCommerce) ' . esc_html__( 'Rating', 'bricks' );

		// Add filterSource option for WooCommerce
		$controls['filterSource']['options']['wcField'] = 'WooCommerce';

		$wc_field_options = [
			'wcOnSale'      => esc_html__( 'On sale', 'bricks' ),
			'wcInStock'     => esc_html__( 'In stock', 'bricks' ),
			'wcFeatured'    => esc_html__( 'Featured products', 'bricks' ),
			'wcProductType' => esc_html__( 'Product type', 'bricks' ),
		];

		// Select, Radio support wcRating
		if ( in_array( $element_name, [ 'filter-radio', 'filter-select' ], true ) ) {
			$wc_field_options['wcRating'] = esc_html__( 'Rating', 'bricks' );
		}

		// Range only support wcPrice
		if ( $element_name === 'filter-range' ) {
			$wc_field_options = [
				'wcPrice' => esc_html__( 'Price', 'bricks' ),
			];
		}

		$woo_controls = [
			'wcField'           => [
				'type'        => 'select',
				'label'       => esc_html__( 'Filter by', 'bricks' ),
				'inline'      => true,
				'options'     => $wc_field_options,
				'placeholder' => esc_html__( 'Select', 'bricks' ),
				'required'    => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'wcField' ],
				],
			],
			'wcHideNotOnSale'   => [
				'type'     => 'checkbox',
				'label'    => esc_html__( 'Hide', 'bricks' ) . ' "' . esc_html__( 'Not on sale', 'bricks' ) . '" ' . esc_html__( 'Option', 'bricks' ),
				'required' => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'wcField' ],
					[ 'wcField', '=', 'wcOnSale' ],
				],
			],
			'wcHideOutOfStock'  => [
				'type'     => 'checkbox',
				'label'    => esc_html__( 'Hide', 'bricks' ) . ' "' . esc_html__( 'Out of stock', 'bricks' ) . '" ' . esc_html__( 'Option', 'bricks' ),
				'required' => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'wcField' ],
					[ 'wcField', '=', 'wcInStock' ],
				],
			],
			'wcHideNotFeatured' => [
				'type'     => 'checkbox',
				'label'    => esc_html__( 'Hide', 'bricks' ) . ' "' . esc_html__( 'Not featured', 'bricks' ) . '" ' . esc_html__( 'Option', 'bricks' ),
				'required' => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'wcField' ],
					[ 'wcField', '=', 'wcFeatured' ],
				],
			],
		];

		// Add wcField right after filterSource
		$query_key_index = absint( array_search( 'filterSource', array_keys( $controls ) ) );

		$controls = array_merge(
			array_slice( $controls, 0, $query_key_index + 1, true ),
			$woo_controls,
			array_slice( $controls, $query_key_index + 1, null, true )
		);

		return $controls;
	}

	/**
	 * Modify WooCommerce rating filtered source
	 * - To ensure always return 5 options and match the fixed ratings format
	 */
	public function modify_wc_rating_filtered_source( $filtered_source, $element ) {
		$element_name = $element->name ?? '';

		// Radio and Select support wcRating
		if ( ! in_array( $element_name, [ 'filter-radio', 'filter-select' ], true ) ) {
			return $filtered_source;
		}

		$settings  = $element->settings ?? [];
		$filter_by = $settings['wcField'] ?? false;

		if ( $filter_by !== 'wcRating' ) {
			return $filtered_source;
		}

		// Fixed rating values from 1 to 5
		$fixed_ratings = range( 1, 5 );

		$modified_filtered_source = [];
		$original_filtered_source = $filtered_source;

		foreach ( $fixed_ratings as $rating ) {
			$rating_count = 0;

			// Get the count where the rating is higher or equal to the current rating
			foreach ( $original_filtered_source as $choices ) {
				$meta_value = $choices['filter_value'] ?? 0;

				if ( $meta_value >= $rating ) {
					$rating_count += $choices['count'];
				}
			}

			$modified_filtered_source[] = [
				'filter_value'         => $rating,
				'filter_value_display' => sprintf( 'Rated %s out of 5', $rating ),
				'filter_value_id'      => 0,
				'filter_value_parennt' => 0,
				'count'                => $rating_count,
			];
		}

		return $modified_filtered_source;
	}

	/**
	 * Set WooCommerce data source
	 */
	public function set_wc_data_source( $data_source, $element ) {
		$settings             = $element->settings;
		$filter_by            = $settings['wcField'] ?? false;
		$label_mapping        = $settings['labelMapping'] ?? 'value';
		$custom_label_mapping = $settings['customLabelMapping'] ?? false;

		if ( ! $filter_by ) {
			return;
		}

		$data_source = [];

		switch ( $filter_by ) {
			case 'wcOnSale':
			case 'wcFeatured':
			case 'wcInStock':
				// Use choices source
				$choices_source = $element->choices_source ?? [];

				// Set a placeholder option if this is a select input
				if ( $element->filter_type === 'select' ) {
					$data_source[] = [
						'value'          => '',
						'text'           => sprintf( '%s %s', esc_html__( 'Select', 'bricks' ), esc_html__( 'Option', 'bricks' ) ),
						'class'          => 'placeholder',
						'is_placeholder' => true,
					];
				}

				// Add an empty option for radio input
				if ( $element->filter_type === 'radio' ) {
					$data_source[] = [
						'value'    => '',
						'text'     => esc_html__( 'All', 'bricks' ),
						'class'    => 'brx-input-radio-option-empty',
						'is_all'   => true,
						'parent'   => 0,
						'children' => [],
					];
				}

				if ( ! empty( $choices_source ) ) {

					if ( $filter_by === 'wcOnSale' ) {
						// Disable not on sale
						if ( isset( $settings['wcHideNotOnSale'] ) ) {
							$choices_source = array_filter(
								$choices_source,
								function( $choice ) {
									return $choice['filter_value'] === 'on_sale';
								}
							);
						}
					}

					if ( $filter_by === 'wcInStock' ) {
						// Disable out of stock
						if ( isset( $settings['wcHideOutOfStock'] ) ) {
							$choices_source = array_filter(
								$choices_source,
								function( $choice ) {
									return $choice['filter_value'] === 'in_stock';
								}
							);
						}
					}

					if ( $filter_by === 'wcFeatured' ) {
						// Disable not featured
						if ( isset( $settings['wcHideNotFeatured'] ) ) {
							$choices_source = array_filter(
								$choices_source,
								function( $choice ) {
									return $choice['filter_value'] === 'featured';
								}
							);
						}
					}

					// wcOnSale: Arrange the choices source by value DESC (on_sale, not_on_sale)
					if ( in_array( $filter_by, [ 'wcOnSale' ], true ) ) {
						usort(
							$choices_source,
							function( $a, $b ) {
								return $a['filter_value'] === 'on_sale' ? -1 : 1;
							}
						);
					}

					foreach ( $choices_source as $choices ) {
						$value = $choices['filter_value'] ?? '';
						$label = $choices['filter_value_display'] ?? 'No label';

						// Maybe use custom label mapping
						if ( $label_mapping === 'custom' && ! empty( $custom_label_mapping ) ) {
							// Find the label from custom label mapping array, use optionLabel if optionMetaValue is match with $meta_value
							foreach ( $custom_label_mapping as $mapping ) {
								$custom_label = $mapping['optionLabel'] ?? '';

								// Not allow empty custom label
								if ( $custom_label === '' ) {
									continue;
								}

								// optionMetaValue can be string 0, or empty string
								$find_meta_value = isset( $mapping['optionMetaValue'] ) ? $mapping['optionMetaValue'] : '';

								// For custom_field, only replace if $meta_value is match
								if ( $find_meta_value === $value ) {
									$label = $custom_label;
									break;
								}
							}
						}

						$data_source[] = [
							'value'  => $value,
							'text'   => $label,
							'count'  => $choices['count'],
							'parent' => 0,
							'is_all' => false,
						];
					}
				}

				break;

			case 'wcProductType':
				// Use choices source
				$choices_source = $element->choices_source ?? [];

				// Set a placeholder option if this is a select input
				if ( $element->filter_type === 'select' ) {
					$data_source[] = [
						'value'          => '',
						'text'           => sprintf( '%s %s', esc_html__( 'Select', 'bricks' ), esc_html__( 'Option', 'bricks' ) ),
						'class'          => 'placeholder',
						'is_placeholder' => true,
					];
				}

				// Add an empty option for radio input
				if ( $element->filter_type === 'radio' ) {
					$data_source[] = [
						'value'    => '',
						'text'     => esc_html__( 'All', 'bricks' ),
						'class'    => 'brx-input-radio-option-empty',
						'is_all'   => true,
						'parent'   => 0,
						'children' => [],
					];
				}

				if ( ! empty( $choices_source ) ) {
					foreach ( $choices_source as $choices ) {
						$value = $choices['filter_value'] ?? '';
						$label = $choices['filter_value_display'] ?? 'No label';

						// Maybe use custom label mapping
						if ( $label_mapping === 'custom' && ! empty( $custom_label_mapping ) ) {
							// Find the label from custom label mapping array, use optionLabel if optionMetaValue is match with $meta_value
							foreach ( $custom_label_mapping as $mapping ) {
								$custom_label = $mapping['optionLabel'] ?? '';

								// Not allow empty custom label
								if ( $custom_label === '' ) {
									continue;
								}

								// optionMetaValue can be string 0, or empty string
								$find_meta_value = isset( $mapping['optionMetaValue'] ) ? $mapping['optionMetaValue'] : '';

								// For custom_field, only replace if $meta_value is match
								if ( $find_meta_value === $value ) {
									$label = $custom_label;
									break;
								}
							}
						}

						$data_source[] = [
							'value'  => $value,
							'text'   => $label,
							'count'  => $choices['count'],
							'parent' => 0,
							'is_all' => false,
						];
					}
				}

				break;

			case 'wcRating':
				// Use choices source
				$choices_source = $element->choices_source ?? [];

				// Set a placeholder option if this is a select input
				if ( $element->filter_type === 'select' ) {
					$data_source[] = [
						'value'          => '',
						'text'           => sprintf( '%s %s', esc_html__( 'Select', 'bricks' ), esc_html__( 'Option', 'bricks' ) ),
						'class'          => 'placeholder',
						'is_placeholder' => true,
					];
				}

				// Add an empty option for radio input
				if ( $element->filter_type === 'radio' ) {
					$data_source[] = [
						'value'    => '',
						'text'     => esc_html__( 'All', 'bricks' ),
						'class'    => 'brx-input-radio-option-empty',
						'is_all'   => true,
						'parent'   => 0,
						'children' => [],
					];
				}

				if ( ! empty( $choices_source ) ) {
					// Fixed rating values from 1 to 5 and DESC
					$fixed_ratings = array_reverse( range( 1, 5 ) );

					foreach ( $fixed_ratings as $rating ) {
						$rating_count = 0;

						// Get the count where the rating is higher or equal to the current rating
						foreach ( $choices_source as $choices ) {
							$meta_value = $choices['filter_value'] ?? 0;

							if ( $meta_value >= $rating ) {
								$rating_count += $choices['count'];
							}
						}

						$data_source[] = [
							'value'  => $rating,
							'text'   => sprintf( 'Rated %s out of 5', $rating ),
							'count'  => $rating_count,
							'parent' => 0,
							'is_all' => false,
						];
					}
				}

				break;
		}

		return $data_source;
	}

	/**
	 * Modify WooCommerce count source for wcRating
	 */
	public function modify_wc_rating_count_source( $count_source, $element ) {
		$element_name = $element->name ?? '';

		// Radio and Select support wcRating
		if ( ! in_array( $element_name, [ 'filter-radio', 'filter-select' ], true ) ) {
			return $count_source;
		}

		$settings  = $element->settings ?? [];
		$filter_by = $settings['wcField'] ?? false;

		if ( $filter_by !== 'wcRating' ) {
			return $count_source;
		}

		// Modify count source to match the fixed ratings format
		$modified_count_source = [];

		$fixed_ratings = array_reverse( range( 1, 5 ) );

		foreach ( $fixed_ratings as $rating ) {
			$rating_count = 0;

			// Get the count where the rating is higher or equal to the current rating
			foreach ( $count_source as $counted ) {
				$meta_value = $counted['filter_value'] ?? 0;

				if ( $meta_value >= $rating ) {
					$rating_count += $counted['count'];
				}
			}

			$modified_count_source[] = [
				'filter_value'         => $rating,
				'filter_value_display' => sprintf( 'Rated %s out of 5', $rating ),
				'filter_value_id'      => 0,
				'filter_value_parent'  => 0,
				'count'                => $rating_count,
			];
		}

		return $modified_count_source;
	}

	/**
	 * Validate WooCommerce index job settings
	 */
	public function validate_wc_job_settings( $validate, $filter_source, $filter_settings ) {
		if ( $filter_source !== 'wcField' ) {
			return $validate;
		}

		$field_type = $filter_settings['wcField'] ?? false;

		// Ensure field type is set
		return (bool) $field_type ? true : false;
	}

	/**
	 * Arguments for WooCommerce index job
	 */
	public function wc_job_index_args( $args, $filter_source, $filter_setting, $query_type ) {
		if ( $filter_source !== 'wcField' || $query_type !== 'wp_query' ) {
			return $args;
		}

		$args = [
			'post_type'        => 'product',
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'cache_results'    => false,
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'lang'             => '',
		];

		return $args;
	}

	/**
	 * Retrieve the total rows to be indexed for WooCommerce products when adding a new job
	 */
	public function get_wc_job_total_rows( $rows, $filter_settings, $element_data ) {
		$filter_source = $filter_settings['filterSource'] ?? false;

		if ( $filter_source !== 'wcField' ) {
			return $rows;
		}

		$args = [
			'post_type'        => 'product',
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'cache_results'    => false,
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'lang'             => '',
		];

		// Get the total rows for WooCommerce

		// Get total rows
		$query      = new \WP_Query( $args );
		$total_rows = count( $query->posts );

		// Release memory
		unset( $query );

		// Return the total rows
		return $total_rows;
	}

	/**
	 * Generate index rows for WooCommerce products
	 * - Triggered by the indexer
	 */
	public function wc_product_rows( $rows, $post, $filter_id, $filter_settings ) {
		$wc_field = $filter_settings['wcField'] ?? false;

		if ( ! $wc_field ) {
			return $rows;
		}

		$post_id = is_a( $post, 'WP_Post' ) ? $post->ID : $post;

		$rows_to_insert = self::generate_wc_field_index_rows( $post_id, $wc_field );

		if ( empty( $rows_to_insert ) ) {
			return [];
		}

		// Build $rows_to_insert, insert filter_id
		$rows_to_insert = array_map(
			function( $row ) use ( $filter_id ) {
				$row['filter_id'] = $filter_id;
				return $row;
			},
			$rows_to_insert
		);

		return $rows_to_insert;
	}

	/**
	 * Generate index rows for WooCommerce products
	 * - Triggered on post save
	 */
	public function wc_index_product_on_save( $rows, $post_id, $filter_elements ) {
		$wc_fields = [];

		$rows_to_insert = [];

		// $filter_elements is an array that already grouped by filter_id
		// Loop through them to check if wcField is set, can generate index rows at one go to improve performance
		foreach ( $filter_elements as $element ) {
			$filter_settings = $element['settings'];
			$wc_field        = $filter_settings['wcField'] ?? false;

			if ( ! $wc_field ) {
				continue;
			}

			if ( isset( $wc_fields[ $wc_field ] ) ) {
				$wc_fields[ $wc_field ][] = $element['filter_id'];
			} else {
				$wc_fields[ $wc_field ] = [ $element['filter_id'] ];
			}
		}

		if ( ! empty( $wc_fields ) ) {
			// Generate rows for each wc_field
			foreach ( $wc_fields as $wc_field => $filter_ids ) {

				$rows_for_this_wc_field = self::generate_wc_field_index_rows( $post_id, $wc_field );

				// Build $rows_to_insert
				if ( ! empty( $rows_for_this_wc_field ) && ! empty( $filter_ids ) ) {
					// Add filter_id to each row, row is the standard template, do not overwrite it.
					foreach ( $filter_ids as $filter_id ) {
						$rows_to_insert = array_merge(
							$rows_to_insert,
							array_map(
								function( $row ) use ( $filter_id ) {
									$row['filter_id'] = $filter_id;

									return $row;
								},
								$rows_for_this_wc_field
							)
						);
					}
				}
			}
		}

		// Return the rows to be inserted
		return $rows_to_insert;
	}

	public static function generate_wc_field_index_rows( $post_id, $wc_field ) {
		$rows = [];

		// Get the product object
		$product = wc_get_product( $post_id );

		if ( ! is_a( $product, 'WC_Product' ) ) {
			return $rows;
		}

		switch ( $wc_field ) {
			case 'wcOnSale':
				$value = $product->is_on_sale() ? 'on_sale' : 'not_on_sale';
				$label = $value === 'on_sale' ? esc_html__( 'On sale', 'bricks' ) : esc_html__( 'Not on sale', 'bricks' );

				// Check if the post is on sale
				$rows[] = [
					'filter_id'            => '',
					'object_id'            => $post_id,
					'object_type'          => 'post',
					'filter_value'         => $value,
					'filter_value_display' => $label,
					'filter_value_id'      => 0,
					'filter_value_parent'  => 0,
				];

				break;

			case 'wcInStock':
				/**
				 * WooCommerce logic:
				 * Backorder | In stock | Track inventory + allow backorders = in_stock
				 * Out of stock | Track inventory + 0 stock + disallow backorders = out_of_stock
				 */
				$value = $product->is_in_stock() ? 'in_stock' : 'out_of_stock';
				$label = $value === 'in_stock' ? esc_html__( 'In stock', 'bricks' ) : esc_html__( 'Out of stock', 'bricks' );

				$rows[] = [
					'filter_id'            => '',
					'object_id'            => $post_id,
					'object_type'          => 'post',
					'filter_value'         => $value,
					'filter_value_display' => $label,
					'filter_value_id'      => 0,
					'filter_value_parent'  => 0,
				];

				break;

			case 'wcRating':
				$value = (float) $product->get_average_rating() ?? 0;
				$label = $value ? sprintf( esc_html__( 'Rated %s out of 5', 'bricks' ), $value ) : esc_html__( 'No rating', 'bricks' );

				$rows[] = [
					'filter_id'            => '',
					'object_id'            => $post_id,
					'object_type'          => 'post',
					'filter_value'         => $value,
					'filter_value_display' => $label,
					'filter_value_id'      => 0,
					'filter_value_parent'  => 0,
				];
				break;

			case 'wcProductType':
				$product_types = wc_get_product_types();
				$value         = $product->get_type() ?? 'simple';
				$label         = $product_types[ $value ] ?? ucfirst( $value );

				$rows[] = [
					'filter_id'            => '',
					'object_id'            => $post_id,
					'object_type'          => 'post',
					'filter_value'         => $value,
					'filter_value_display' => $label,
					'filter_value_id'      => 0,
					'filter_value_parent'  => 0,
				];
				break;

			case 'wcFeatured':
				$value = $product->is_featured() ? 'featured' : 'not_featured';
				$label = $value === 'featured' ? esc_html__( 'Featured', 'bricks' ) : esc_html__( 'Not featured', 'bricks' );

				$rows[] = [
					'filter_id'            => '',
					'object_id'            => $post_id,
					'object_type'          => 'post',
					'filter_value'         => $value,
					'filter_value_display' => $label,
					'filter_value_id'      => 0,
					'filter_value_parent'  => 0,
				];

				break;

			case 'wcPrice':
				// Handle variable and grouped product (@since 2.0)
				if ( $product->is_type( 'grouped' ) || $product->is_type( 'variable' ) ) {
					// Get the price of each child product
					$children_ids = $product->get_children();

					foreach ( $children_ids as $child_id ) {
						$child_product = wc_get_product( $child_id );
						if ( ! is_a( $child_product, 'WC_Product' ) ) {
							continue;
						}

						$value = (float) $child_product->get_price() ?? 0;
						$label = $value;

						$rows[] = [
							'filter_id'            => '',
							'object_id'            => $post_id,
							'object_type'          => 'post',
							'filter_value'         => $value,
							'filter_value_display' => $label,
							'filter_value_id'      => 0,
							'filter_value_parent'  => 0,
						];
					}
				}

				// Normal product. (Might encounter problem if it's a custom product type)
				else {
					$value = (float) $product->get_price() ?? 0;
					$label = $value;

					$rows[] = [
						'filter_id'            => '',
						'object_id'            => $post_id,
						'object_type'          => 'post',
						'filter_value'         => $value,
						'filter_value_display' => $label,
						'filter_value_id'      => 0,
						'filter_value_parent'  => 0,
					];
				}

				break;
		}

		return $rows;
	}

	/**
	 * Generate query vars for WooCommerce sort filters
	 */
	public function wc_sort_query_vars( $query_vars, $filter, $query_id, $active_filter_index ) {
		$settings     = $filter['settings'];
		$filter_value = $filter['value'];
		$sort_options = ! empty( $settings['sortOptions'] ) ? $settings['sortOptions'] : false;

		if ( ! $sort_options ) {
			return $query_vars;
		}

		$selected_option = $this->query_filters::get_selected_sort_option( $filter_value, $sort_options );

		if ( ! $selected_option ) {
			return $query_vars;
		}

		$key   = $selected_option['key'];
		$order = $selected_option['order'];

		if ( ! $key || ! $order || ! in_array( $key, [ 'wcPrice', 'wcRating' ], true ) ) {
			return $query_vars;
		}

		$meta_key = $key === 'wcPrice' ? '_price' : '_wc_average_rating';

		$sort_query = [
			'meta_key'  => $meta_key,
			'orderby'   => [
				'meta_value_num' => $order,
			],
			'meta_type' => 'NUMERIC',
		];

		// $sort_query should override the existing query_vars
		foreach ( $sort_query as $key => $value ) {
			$query_vars[ $key ] = $value;
		}

		// Update $active_filters with the selected option, will be used in other area
		if ( isset( $this->query_filters::$active_filters[ $query_id ][ $active_filter_index ] ) ) {
			$this->query_filters::$active_filters[ $query_id ][ $active_filter_index ]['sort_option_info'] = $selected_option;
			$this->query_filters::$active_filters[ $query_id ][ $active_filter_index ]['query_vars']       = $sort_query;
			$this->query_filters::$active_filters[ $query_id ][ $active_filter_index ]['query_type']       = 'sort';
		}

		return $query_vars;
	}

	/**
	 * Generate query vars for WooCommerce filters
	 */
	public function wc_filter_query_vars( $query_vars, $filter, $query_id, $active_filter_index ) {
		$instance_name = $filter['instance_name'];
		$settings      = $filter['settings'];
		$filter_source = $settings['filterSource'] ?? false;

		if ( $filter_source !== 'wcField' ) {
			return $query_vars;
		}

		switch ( $instance_name ) {
			case 'filter-select':
			case 'filter-radio':
			case 'filter-checkbox':
				$query_vars = $this->build_wc_field_query_vars( $query_vars, $filter, $query_id, $active_filter_index );
				break;

			case 'filter-range':
				$query_vars = $this->build_wc_range_query_vars( $query_vars, $filter, $query_id, $active_filter_index );
				break;
		}

		return $query_vars;
	}

	private function build_wc_range_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings     = $filter['settings'];
		$filter_value = $filter['value'];
		$filter_by    = $settings['wcField'] ?? false;
		$query_type   = '';

		if ( ! $filter_by ) {
			return $query_vars;
		}

		switch ( $filter_by ) {
			case 'wcPrice':
				// Ensure values are integers
				$filter_value = array_map( 'floatval', $filter_value );

				// Ensure smallest value is first
				sort( $filter_value );

				$product_ids = $this->get_wc_price_ids( $filter['filter_id'], $filter_value );

				// No IDs found from the index table, force set to 0
				if ( empty( $product_ids ) ) {
					$product_ids = [ 0 ];
				}

				// Check if post__in is already set
				if ( isset( $query_vars['post__in'] ) ) {
					$intersect_ids = array_intersect( $query_vars['post__in'], $product_ids );

					$query_vars['post__in'] = empty( $intersect_ids ) ? [ 0 ] : $intersect_ids;
				} else {
					$query_vars['post__in'] = $product_ids;
				}

				$query_type = 'wp_query';

				// Update $active_filters
				if ( isset( $this->query_filters::$active_filters[ $query_id ][ $filter_index ] ) ) {
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = [ 'post__in' => $product_ids ];
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_type'] = $query_type;
				}
				break;
		}

		return $query_vars;
	}

	private function build_wc_field_query_vars( $query_vars, $filter, $query_id, $filter_index ) {
		$settings     = $filter['settings'];
		$filter_value = $filter['value'];
		$filter_by    = $settings['wcField'] ?? false;
		$query_type   = '';

		if ( ! $filter_by ) {
			return $query_vars;
		}

		switch ( $filter_by ) {
			case 'wcOnSale':
				// Maybe is checkbox, so the value is an array
				if ( is_array( $filter_value ) ) {
					// Check if the value is valid
					$filter_value = array_intersect( $filter_value, [ 'on_sale', 'not_on_sale' ] );

					// Skip: empty or not 1 value (checkbox on_sale and not_on_sale means all)
					if ( empty( $filter_value ) || count( $filter_value ) !== 1 ) {
						break;
					}

					// Get the first value
					$filter_value = reset( $filter_value );
				}

				$on_sales_ids = $this->get_wc_on_sale_ids( $filter['filter_id'] );

				// No IDs found from the index table, force set to 0
				if ( empty( $on_sales_ids ) ) {
					$on_sales_ids = [ 0 ];
				}

				// Use post__in or post__not_in based on the value
				$param = $filter_value === 'on_sale' ? 'post__in' : 'post__not_in';

				// Check if post__in is already set
				if ( isset( $query_vars[ $param ] ) ) {
					$intersect_ids = array_intersect( $query_vars[ $param ], $on_sales_ids );

					$query_vars[ $param ] = empty( $intersect_ids ) ? [ 0 ] : $intersect_ids;
				} else {
					$query_vars[ $param ] = $on_sales_ids;
				}

				$query_type = 'wp_query';

				// Update $active_filters
				if ( isset( $this->query_filters::$active_filters[ $query_id ][ $filter_index ] ) ) {
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = [ $param => $on_sales_ids ];
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_type'] = $query_type;
				}
				break;

			case 'wcInStock':
				// Maybe is checkbox, so the value is an array
				if ( is_array( $filter_value ) ) {
					// Check if the value is valid
					$filter_value = array_intersect( $filter_value, [ 'in_stock', 'out_of_stock' ] );

					// Skip: empty or not 1 value (checkbox in_stock and out_of_stock means all)
					if ( empty( $filter_value ) || count( $filter_value ) !== 1 ) {
						break;
					}

					// Get the first value
					$filter_value = reset( $filter_value );
				}

				// Use out_of_stock_ids as it should be lesser than in_stock_ids (performance)
				$out_of_stock_ids = $this->get_wc_out_of_stock_ids( $filter['filter_id'] );

				// No IDs found from the index table, force set to 0
				if ( empty( $out_of_stock_ids ) ) {
					$out_of_stock_ids = [ 0 ];
				}

				// Use post__in or post__not_in based on the value
				$param = $filter_value === 'out_of_stock' ? 'post__in' : 'post__not_in';

				// Check if post__in is already set
				if ( isset( $query_vars[ $param ] ) ) {
					$intersect_ids = array_intersect( $query_vars[ $param ], $out_of_stock_ids );

					$query_vars[ $param ] = empty( $intersect_ids ) ? [ 0 ] : $intersect_ids;
				} else {
					$query_vars[ $param ] = $out_of_stock_ids;
				}

				$query_type = 'wp_query';

				// Update $active_filters
				if ( isset( $this->query_filters::$active_filters[ $query_id ][ $filter_index ] ) ) {
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = [ $param => $out_of_stock_ids ];
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_type'] = $query_type;
				}
				break;

			case 'wcFeatured':
					$featured_ids = [];
					// Maybe is checkbox, so the value is an array
				if ( is_array( $filter_value ) ) {
					// Check if the value is valid
					$filter_value = array_intersect( $filter_value, [ 'featured', 'not_featured' ] );

					// Skip: empty or not 1 value (checkbox featured and not_featured means all)
					if ( empty( $filter_value ) || count( $filter_value ) !== 1 ) {
						break;
					}

					// Get the first value
					$filter_value = reset( $filter_value );
				}

					$featured_ids = $this->get_wc_featured_ids( $filter['filter_id'] );

					// No IDs found from the index table, force set to 0
				if ( empty( $featured_ids ) ) {
					$featured_ids = [ 0 ];
				}

					// Use post__in or post__not_in based on the value
					$param = $filter_value === 'featured' ? 'post__in' : 'post__not_in';

					// Check if post__in is already set
				if ( isset( $query_vars[ $param ] ) ) {
					$intersect_ids = array_intersect( $query_vars[ $param ], $featured_ids );

					$query_vars[ $param ] = empty( $intersect_ids ) ? [ 0 ] : $intersect_ids;
				} else {
					$query_vars[ $param ] = $featured_ids;
				}

					$query_type = 'wp_query';

					// Update $active_filters
				if ( isset( $this->query_filters::$active_filters[ $query_id ][ $filter_index ] ) ) {
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = [ $param => $featured_ids ];
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_type'] = $query_type;
				}

				break;

			case 'wcProductType':
				$type_ids = [];

				// Maybe is checkbox, so the value is an array
				if ( is_array( $filter_value ) ) {
					// Get all IDs based on the selected product type
					foreach ( $filter_value as $type ) {
						$type_ids = array_merge( $type_ids, $this->get_wc_product_ids_by_type( $type, $filter['filter_id'] ) );
					}
				} else {
					$type_ids = $this->get_wc_product_ids_by_type( $filter_value, $filter['filter_id'] );
				}

				// No IDs found from the index table, force set to 0
				if ( empty( $type_ids ) ) {
					$type_ids = [ 0 ];
				}

				// Check if post__in is already set
				if ( isset( $query_vars['post__in'] ) ) {
					$intersect_ids = array_intersect( $query_vars['post__in'], $type_ids );

					$query_vars['post__in'] = empty( $intersect_ids ) ? [ 0 ] : $intersect_ids;
				} else {
					$query_vars['post__in'] = $type_ids;
				}

				$query_type = 'wp_query';

				// Update $active_filters
				if ( isset( $this->query_filters::$active_filters[ $query_id ][ $filter_index ] ) ) {
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = [ 'post__in' => $type_ids ];
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_type'] = $query_type;
				}

				break;

			case 'wcRating':
				// Not support checkbox, only radio and select
				if ( is_array( $filter_value ) ) {
					$filter_value = (int) reset( $filter_value );
				}

				$rating_ids = $this->get_wc_product_ids_by_rating( $filter_value, $filter['filter_id'] );

				// No IDs found from the index table, force set to 0
				if ( empty( $rating_ids ) ) {
					$rating_ids = [ 0 ];
				}

				// Check if post__not_in is already set
				if ( isset( $query_vars['post__in'] ) ) {
					$intersect_ids = array_intersect( $query_vars['post__in'], $rating_ids );

					$query_vars['post__in'] = empty( $intersect_ids ) ? [ 0 ] : $intersect_ids;
				} else {
					$query_vars['post__in'] = $rating_ids;
				}

				$query_type = 'wp_query';

				// Update $active_filters
				if ( isset( $this->query_filters::$active_filters[ $query_id ][ $filter_index ] ) ) {
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_vars'] = [ 'post__in' => $rating_ids ];
					$this->query_filters::$active_filters[ $query_id ][ $filter_index ]['query_type'] = $query_type;
				}

				break;
		}

		return $query_vars;
	}

	/**
	 * Get all on sale product ids from index table
	 */
	private function get_wc_on_sale_ids( $filter_id ) {
		$cache_key = 'wc_on_sale_ids';

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		// Retrieve all on sale product ids from index table
		$index_table_name = $this->query_filters::get_table_name();

		global $wpdb;

		$on_sale_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT object_id FROM {$index_table_name} WHERE filter_id = %s AND filter_value = 'on_sale'",
				$filter_id
			)
		);

		self::$cache[ $cache_key ] = $on_sale_ids;

		return $on_sale_ids;
	}

	/**
	 * Get all out of stock product ids from index table
	 */
	private function get_wc_out_of_stock_ids( $filter_id ) {
		$cache_key = 'wc_out_of_stock_ids';

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		// Retrieve all in stock product ids from index table
		$index_table_name = $this->query_filters::get_table_name();

		global $wpdb;

		$in_stock_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT object_id FROM {$index_table_name} WHERE filter_id = %s AND filter_value = 'out_of_stock'",
				$filter_id
			)
		);

		self::$cache[ $cache_key ] = $in_stock_ids;

		return $in_stock_ids;
	}

	/**
	 * Get all product ids by type from index table
	 */
	private function get_wc_product_ids_by_type( $value, $filter_id ) {
		$cache_key = 'wc_product_ids_by_type_' . $value;

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		// Retrieve all product ids by type from index table
		$index_table_name = $this->query_filters::get_table_name();

		global $wpdb;

		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT object_id FROM {$index_table_name} WHERE filter_id = %d AND filter_value = %s",
				$filter_id,
				$value
			)
		);

		self::$cache[ $cache_key ] = $product_ids;

		return $product_ids;
	}

	/**
	 * Get all featured product ids from index table
	 */
	private function get_wc_featured_ids( $filter_id ) {
		$cache_key = 'wc_featured_ids';

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		// Retrieve all featured product ids from index table
		$index_table_name = $this->query_filters::get_table_name();

		global $wpdb;

		$featured_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT object_id FROM {$index_table_name} WHERE filter_id = %s AND filter_value = 'featured'",
				$filter_id
			)
		);

		self::$cache[ $cache_key ] = $featured_ids;

		return $featured_ids;
	}

	/**
	 * Get all product ids by rating from index table
	 */
	private function get_wc_product_ids_by_rating( $value, $filter_id ) {
		$cache_key = 'wc_product_ids_by_rating_' . $value;

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		// Retrieve all product ids by rating from index table
		$index_table_name = $this->query_filters::get_table_name();

		global $wpdb;

		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT object_id FROM {$index_table_name} WHERE filter_id = %s AND filter_value >= %d",
				$filter_id,
				$value
			)
		);

		self::$cache[ $cache_key ] = $product_ids;

		return $product_ids;
	}

	/**
	 * Get all product ids by price range from index table
	 */
	private function get_wc_price_ids( $filter_id, $filter_value ) {
		// Ensure filter_value is an array
		if ( ! is_array( $filter_value ) && ! count( $filter_value ) === 2 ) {
			return [];
		}

		$cache_key = 'wc_price_ids_' . implode( '_', $filter_value );

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		// Retrieve all product ids by price from index table
		$index_table_name = $this->query_filters::get_table_name();

		global $wpdb;

		// Support float values
		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT object_id FROM {$index_table_name} WHERE filter_id = %s AND filter_value BETWEEN %f AND %f",
				$filter_id,
				$filter_value[0],
				$filter_value[1]
			)
		);

		self::$cache[ $cache_key ] = $product_ids;

		return $product_ids;
	}
}
