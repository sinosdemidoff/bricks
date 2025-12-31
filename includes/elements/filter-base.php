<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Filter_Element extends Element {
	public $category                   = 'filter';
	public $input_name                 = '';
	public $filter_type                = '';
	public $filtered_source            = [];
	public $choices_source             = [];
	public $data_source                = [];
	public $populated_options          = [];
	public $page_filter_value          = [];
	public $query_settings             = [];
	public $target_query_results_count = 0;

	public function enqueue_scripts() {
		wp_enqueue_script( 'bricks-filters' );
	}

	public function get_keywords() {
		return [ 'input', 'form', 'field', 'filter' ];
	}

	public function set_controls_after() {
		if ( $this->name !== 'filter-active-filters' ) {
			$this->control_groups['filter-active'] = [
				'title' => esc_html__( 'Active filter', 'bricks' ),
				'tab'   => 'content',
			];
		}
	}

	/**
	 * Retrieve the standard controls for filter inputs for frontend
	 */
	public function get_common_filter_settings() {
		if ( ! Helpers::enabled_query_filters() ) {
			return [];
		}

		return [
			'filterId'            => $this->id,
			'targetQueryId'       => $this->settings['filterQueryId'],
			'filterAction'        => $this->settings['filterAction'] ?? 'filter', // 'filter' or 'sort
			'filterType'          => $this->filter_type,
			'filterMethod'        => $this->settings['filterMethod'] ?? 'ajax',
			'filterApplyOn'       => $this->settings['filterApplyOn'] ?? 'change',
			'filterInputDebounce' => $this->settings['filterInputDebounce'] ?? 500,
			'filterNiceName'      => $this->settings['filterNiceName'] ?? '',
		];
	}

	/**
	 * Determine whether this input is a filter input
	 * Will be overriden by each input if needed
	 *
	 * @return boolean
	 */
	public function is_filter_input() {
		return ! empty( $this->settings['filterQueryId'] );
	}

	/**
	 * Check if this filter has indexing job in progress
	 *
	 * @since 1.10
	 */
	public function is_indexing() {
		$indexer    = Query_Filters_Indexer::get_instance();
		$active_job = $indexer->get_active_job_for_element( $this->id );

		return ! empty( $active_job );
	}

	public function prepare_sources() {
		// Get target query id
		$query_id = $this->settings['filterQueryId'];

		/**
		 * Get target query results count to execute query at least once and saved in history.
		 * Posts element needs this if filter element is targeting it.
		 *
		 * @since 1.9.8
		 * @since 1.11: In-builder: Make sure the count is never 0, so the populated_options will be populated.
		 */
		$this->target_query_results_count = bricks_is_builder() ? 1 : Integrations\Dynamic_Data\Providers::render_tag( "{query_results_count:$query_id}", $this->post_id );

		/**
		 * Get the settings from the query history
		 *
		 * If any plugin disabled query history, we will not get the settings, but performance is better.
		 * Otherwise, we need to use Helpers::get_element_data()
		 *
		 * @since 1.11
		 */
		$this->query_settings = Helpers::get_query_object_from_history_or_init( $query_id, $this->post_id )->settings['query'] ?? []; // Init query object if not found in history (Maybe currently rendering in builder via render_element API) (@since 2.0.2)

		// Get filtered data from index
		$this->filtered_source = apply_filters( 'bricks/filter_element/filtered_source', Query_Filters::get_filtered_data_from_index( $this->id, Query_Filters::get_filter_object_ids( $query_id ) ), $this );

		// Get choices data from index - for custom field filter
		$this->choices_source = Query_Filters::get_filtered_data_from_index( $this->id, Query_Filters::get_filter_object_ids( $query_id, 'original' ) );
	}

	public function set_data_source() {
		$settings      = $this->settings;
		$filter_action = $settings['filterAction'] ?? 'filter';
		$filter_source = $settings['filterSource'] ?? false;

		if ( $filter_action !== 'filter' || ! $filter_source ) {
			return;
		}

		$data_source = [];

		switch ( $filter_source ) {
			case 'taxonomy':
				$data_source = $this->set_data_source_from_taxonomy();
				break;
			case 'wpField':
				$data_source = $this->set_data_source_from_wp_field();
				break;
			case 'customField':
				$data_source = $this->set_data_source_from_custom_field();
				break;
			default:
				// Undocumented (WooCommerce)
				$data_source = apply_filters( 'bricks/filter_element/data_source_' . $filter_source, [], $this );
				break;
		}

		$this->data_source = $data_source;
	}

	public function set_data_source_from_taxonomy() {
		$settings = $this->settings;
		$taxonomy = $settings['filterTaxonomy'] ?? false;

		if ( ! $taxonomy ) {
			return [];
		}

		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		];

		if ( isset( $settings['hierarchical'] ) ) {
			$args['hierarchical'] = true;
		} else {
			$args['hierarchical'] = false;
		}

		$term_include = $settings['filterTermInclude'] ?? [];
		$term_exclude = $settings['filterTermExclude'] ?? [];

		// Term include
		if ( ! empty( $term_include ) ) {
			$term_include_ids = Query::convert_terms_to_ids( $term_include );
			if ( ! empty( $term_include_ids ) ) {
				$args['include'] = $term_include_ids;

				// Included the children of each include_ids
				if ( isset( $settings['hierarchical'] ) ) {
					$include_children = [];
					foreach ( $term_include_ids as $term_id ) {
						$children = get_term_children( $term_id, $taxonomy );
						if ( ! empty( $children ) ) {
							$include_children = array_merge( $include_children, $children );
						}
					}

					if ( ! empty( $include_children ) ) {
						$args['include'] = array_merge( $args['include'], $include_children );
					}
				}
			}
		}

		// Term exclude
		if ( ! empty( $term_exclude ) ) {
			$term_exclude_ids = Query::convert_terms_to_ids( $term_exclude );
			if ( ! empty( $term_exclude_ids ) ) {
				$args['exclude'] = $term_exclude_ids;

				// Excluded the children of each exclude_ids if hierarchical
				if ( isset( $settings['hierarchical'] ) ) {
					$exclude_children = [];
					foreach ( $term_exclude_ids as $term_id ) {
						$children = get_term_children( $term_id, $taxonomy );
						if ( ! empty( $children ) ) {
							$exclude_children = array_merge( $exclude_children, $children );
						}
					}

					if ( ! empty( $exclude_children ) ) {
						$args['exclude'] = array_merge( $args['exclude'], $exclude_children );
					}
				}
			}
		}

		// Term order
		if ( isset( $settings['filterTaxonomyOrder'] ) ) {
			$args['order'] = sanitize_text_field( $settings['filterTaxonomyOrder'] );
		}

		// Term order by
		if ( isset( $settings['filterTaxonomyOrderBy'] ) ) {
			$args['orderby'] = sanitize_text_field( $settings['filterTaxonomyOrderBy'] );

			// Set order 'meta_key' If orderby is 'meta_value' or 'meta_value_num' (@since 1.12.2)
			if ( in_array( $args['orderby'], [ 'meta_value', 'meta_value_num' ], true ) ) {
				$args['meta_key'] = isset( $settings['filterTaxonomyOrderMetaKey'] ) ? sanitize_text_field( $settings['filterTaxonomyOrderMetaKey'] ) : '';
			}
		}

		// Top level only
		if ( isset( $settings['filterTermTopLevel'] ) ) {
			$args['parent'] = 0;
		}

		// Undocumented
		$args = apply_filters( 'bricks/filter/taxonomy_args', $args, $this );

		// Get terms and never hide empty, we will handle it later when populating options
		$terms = get_terms( $args );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$data_source = [];

			// Set default placeholder
			$taxonomy_obj   = get_taxonomy( $taxonomy );
			$taxonomy_label = $taxonomy_obj->labels->all_items;

			// Add an empty option
			if ( $this->name === 'filter-radio' ) {
				$data_source[] = [
					'value'    => '',
					'value_id' => '',
					'text'     => $taxonomy_label,
					'class'    => 'brx-input-radio-option-empty',
					'is_all'   => true,
					'parent'   => 0,
					'children' => [],
				];
			}

			if ( $this->name === 'filter-select' ) {
				$data_source[] = [
					'value'          => '',
					'valued_id'      => '',
					'text'           => $taxonomy_label,
					'class'          => 'placeholder',
					'is_placeholder' => true,
					'parent'         => 0,
					'children'       => [],
				];
			}

			$choices_source = $this->choices_source ?? [];

			foreach ( $terms as $term ) {
				// We need to use the count from choices source
				$count = 0;

				if ( ! empty( $choices_source ) ) {
					foreach ( $choices_source as $choice ) {
						if ( self::is_option_value_matched( $choice['filter_value'], $term->slug ) ) {
							$count = $choice['count'];
							break;
						}
					}
				}

				$data_source[] = [
					'value'    => $term->slug,
					'value_id' => $term->term_id,
					'text'     => $term->name,
					'count'    => $count,
					'parent'   => $term->parent,
					'children' => [],
				];
			}

			return $data_source;
		}

		return [];
	}

	/**
	 * Similar to set_data_source_from_custom_field, but separate for easier maintenance in the future
	 */
	public function set_data_source_from_wp_field() {
		$settings             = $this->settings;
		$field_type           = $settings['sourceFieldType'] ?? 'post';
		$label_mapping        = $settings['labelMapping'] ?? 'value';
		$custom_label_mapping = $settings['customLabelMapping'] ?? false;
		$selected_field       = false;
		$data_source          = [];

		switch ( $field_type ) {
			case 'post':
			case 'user':
			case 'term':
				// Use choices source
				$choices_source = $this->choices_source ?? [];

				if ( $field_type === 'post' ) {
					$selected_field = $settings['wpPostField'] ?? false;

					if ( ! $selected_field ) {
						return;
					}

					$selected_field_label = $this->controls['wpPostField']['options'][ $selected_field ] ?? esc_html__( 'Option', 'bricks' );
				}

				if ( $field_type === 'user' ) {
					$selected_field = $settings['wpUserField'] ?? false;

					if ( ! $selected_field ) {
						return;
					}

					if ( $selected_field === 'user_role' ) {
						// If target filter has set role__in, should only include selected user roles. Otherwise, irrelevant user roles will be displayed as option if 1 user has multiple roles (#86c44rghg @since 2.0)
						$query_role_in = $this->query_settings['role__in'] ?? [];

						if ( ! empty( $query_role_in ) ) {
							$choices_source = array_filter(
								$choices_source,
								function( $choice ) use ( $query_role_in ) {
									return in_array( $choice['filter_value'], $query_role_in, true );
								}
							);
						}
					}

					$selected_field_label = $this->controls['wpUserField']['options'][ $selected_field ] ?? esc_html__( 'Option', 'bricks' );
				}

				if ( $field_type === 'term' ) {
					$selected_field = $settings['wpTermField'] ?? false;

					if ( ! $selected_field ) {
						return;
					}

					$selected_field_label = $this->controls['wpTermField']['options'][ $selected_field ] ?? esc_html__( 'Option', 'bricks' );
				}

				// Set a placeholder option if this is a select input
				if ( $this->filter_type === 'select' ) {
					$data_source[] = [
						'value'          => '',
						'text'           => sprintf( '%s %s', esc_html__( 'Select', 'bricks' ), $selected_field_label ),
						'class'          => 'placeholder',
						'is_placeholder' => true,
					];
				}

				// Add an empty option for radio input
				if ( $this->filter_type === 'radio' ) {
					$data_source[] = [
						'value'    => '',
						'text'     => sprintf( esc_html__( 'All %s', 'bricks' ), $selected_field_label ),
						'class'    => 'brx-input-radio-option-empty',
						'is_all'   => true,
						'parent'   => 0,
						'children' => [],
					];
				}

				if ( ! empty( $choices_source ) ) {
					foreach ( $choices_source as $choices ) {
						// meta_value can be string 0, or empty string
						$field_value = isset( $choices['filter_value'] ) ? $choices['filter_value'] : '';
						$label       = isset( $choices['filter_value_display'] ) ? $choices['filter_value_display'] : 'No label';

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

								// For wp_field, the value might be the same as the label
								if ( $find_meta_value === $field_value || $find_meta_value === $label ) {
									$label = $custom_label;
									break;
								}
							}
						}

						$data_source[] = [
							'value'          => $field_value,
							'text'           => $label,
							'count'          => $choices['count'],
							'parent'         => 0,
							'wp_field'       => $field_type,
							'selected_field' => $selected_field,
						];
					}
				}

				break;
		}

		// Set data source
		return $data_source;
	}

	public function set_data_source_from_custom_field() {
		$settings             = $this->settings;
		$source_field_type    = $settings['sourceFieldType'] ?? 'post';
		$custom_field_key     = $settings['customFieldKey'] ?? false;
		$label_mapping        = $settings['labelMapping'] ?? 'value';
		$custom_label_mapping = $settings['customLabelMapping'] ?? false;

		if ( ! $source_field_type || ! $custom_field_key ) {
			return [];
		}

		$data_source = [];

		// @since 1.11.1
		do_action( 'bricks/filter_element/before_set_data_source_from_custom_field', $this );

		switch ( $source_field_type ) {
			case 'post':
			case 'term':
			case 'user':
				// Use choices source
				$choices_source = $this->choices_source ?? [];

				// Set a placeholder option if this is a select input
				if ( $this->filter_type === 'select' ) {
					$data_source[] = [
						'value'          => '',
						'text'           => esc_html__( 'Select option', 'bricks' ),
						'class'          => 'placeholder',
						'is_placeholder' => true,
					];
				}

				// Add an empty option for radio input
				if ( $this->filter_type === 'radio' ) {
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
						$meta_key = $custom_field_key;
						$is_all   = false;

						// meta_value can be string 0, or empty string
						$meta_value = isset( $choices['filter_value'] ) ? $choices['filter_value'] : '';
						$label      = isset( $choices['filter_value_display'] ) ? $choices['filter_value_display'] : 'No label';

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
								if ( $find_meta_value === $meta_value ) {
									$label = $custom_label;
									break;
								}
							}
						}

						if ( ! $meta_key ) {
							continue;
						}

						$data_source[] = [
							'value'  => $meta_value,
							'text'   => $label,
							'count'  => $choices['count'],
							'parent' => 0,
							'is_all' => $is_all,
						];
					}
				}

				break;
		}

		return $data_source;
	}

	/**
	 * Set options with count
	 * DO NOT use this method if no count is needed as it will generate more queries.
	 *
	 * Used in: filter-checkbox, filter-radio, filter-select
	 */
	public function set_options_with_count() {
		$settings      = $this->settings;
		$hide_empty    = isset( $settings['filterHideEmpty'] );
		$hierarchical  = isset( $settings['filterHierarchical'] );
		$filter_source = $settings['filterSource'] ?? false;
		$query_id      = $settings['filterQueryId'] ?? false;
		$combine_logic = $settings['filterMultiLogic'] ?? 'OR';
		$url_param     = $settings['filterNiceName'] ?? "brx_{$this->id}";

		if ( ! $query_id ) {
			return;
		}

		// Now we have data source and filtered source, we can populate options
		$options             = [];
		$filtered_source     = $this->filtered_source;
		$data_source         = $this->data_source;
		$query_results_count = $this->target_query_results_count;

		$this_active_filter   = false;
		$count_source         = [];
		$other_active_filters = [];
		$active_filters       = Query_Filters::$active_filters[ $query_id ] ?? [];
		$page_filters         = Query_filters::$page_filters ?? [];
		$disable_query_merge  = $this->query_settings['disable_query_merge'] ?? false;

		// STEP: Hierarchical display logic
		if ( $hierarchical && $filter_source === 'taxonomy' ) {
			$cloned_data_source = $data_source;
			$sorted_source      = [];
			self::sort_terms_hierarchically( $cloned_data_source, $sorted_source );

			$flattened_source = [];
			self::flatten_terms_hierarchically( $sorted_source, $flattened_source );

			// TODO: Update children_ids on every depth recursively update_children_ids
			$data_source = $flattened_source;
		}

		// STEP: Get count source for each option
		if ( ! empty( $active_filters ) || ! empty( $page_filters ) ) {
			// Get all active filters that will affect the count
			$filters_affecting_count = array_filter(
				$active_filters,
				function( $filter ) {
					return isset( $filter['query_type'] ) && $filter['query_type'] !== 'sort' && $filter['query_type'] !== 'pagination' && $filter['query_type'] !== 'per_page';
				}
			);

			// Assign this_active_filter and other_active_filters from filters_affecting_count
			foreach ( $filters_affecting_count as $filter ) {
				if ( $filter['filter_id'] === $this->id ) {
					$this_active_filter = $filter;

					// Include this filter's query vars in count source if combine logic is AND for checkbox (@since 1.11.1)
					if ( $combine_logic === 'AND' && $this->name === 'filter-checkbox' ) {
						$other_active_filters[] = $filter;
					}
				}
				else {
					// If the filter has same url_param, should not generate additional count source or wrong count will be displayed (@since 1.12)
					if ( (string) $filter['url_param'] !== (string) $url_param ) {
						$other_active_filters[] = $filter;
					}
				}
			}

			// Get all the query_vars from other active filters
			$count_query_vars = [];
			foreach ( $other_active_filters as $filter ) {
				$filter_query_type = $filter['query_type'] ?? 'default';
				switch ( $filter_query_type ) {
					case 'wp_query':
						$count_query_vars = Query::merge_query_vars( $count_query_vars, $filter['query_vars'] );
						break;

					case 'meta_query':
						$count_query_vars = Query::merge_query_vars(
							$count_query_vars,
							[
								'meta_query' => [ $filter['query_vars'] ],
							],
							true
						); // Third parameter is true to merge meta_query correctly if not AJAX call (@since 1.11.1)

						break;

					case 'tax_query':
						$count_query_vars = Query::merge_query_vars(
							$count_query_vars,
							[
								'tax_query' => [ $filter['query_vars'] ],
							]
						);

						break;

					case 'default':
						// Do nothing
						break;
				}
			}

			// Get query_vars from page filters if disable_query_merge is false and page filters should be applied
			if ( ! $disable_query_merge && Query_Filters::should_apply_page_filters( $count_query_vars ) ) {
				$count_query_vars     = Query::merge_query_vars( $count_query_vars, Query_Filters::generate_query_vars_from_page_filters() );
				$other_active_filters = array_merge( $other_active_filters, $page_filters );
			}

			// Get the count source
			if ( count( $count_query_vars ) > 0 ) {
				$count_source = Query_Filters::get_filtered_data_from_index( $this->id, Query_Filters::get_filter_object_ids( $query_id, 'original', $count_query_vars ) );

				// Undocumented (WooCommerce)
				$count_source = apply_filters( 'bricks/filter_element/count_source_' . $filter_source, $count_source, $this );
			}
		}

		// STEP: Populate options
		if ( is_array( $data_source ) && ! empty( $data_source ) ) {
			foreach ( $data_source as $source ) {
				$option = [
					'value'          => $source['value'] ?? '',
					'text'           => $source['text'] ?? '',
					'class'          => $source['class'] ?? '',
					'is_all'         => $source['is_all'] ?? false,
					'is_placeholder' => $source['is_placeholder'] ?? false,
					'count'          => $source['count'] ?? 0,
					'depth'          => $source['depth'] ?? 0,
					'children_ids'   => $source['children_ids'] ?? [],
				];

				// Get count from filtered data
				if ( ! $option['is_all'] && ! $option['is_placeholder'] ) {
					// Default use count from data source
					$count = $option['count'];

					/**
					 * Decide whether use count from filtered_source or count_source
					 *
					 * filtered_source: count from the filtered data
					 * count_source: count from the filtered data where query_vars are affected by other active filters
					 *
					 * @since 1.11
					 */
					$check_count_array = [];

					// This filter is active and there are other active filters, use count source
					if ( $this_active_filter !== false && count( $other_active_filters ) > 0 ) {
						if ( empty( $count_source ) ) {
							// No count source, set count to 0
							$count = 0;
						} else {
							$check_count_array        = $count_source;
							$not_found_option_as_zero = true;
						}
					}

					// This filter is not active and there are other active filters, use filtered source
					elseif ( count( $other_active_filters ) > 0 ) {
						if ( empty( $filtered_source ) ) {
							// No filtered source, set count to 0
							$count = 0;
						} else {
							$check_count_array        = $filtered_source;
							$not_found_option_as_zero = true;
						}
					}

					// No other active filters
					else {
						if ( $this->name === 'filter-checkbox' ) {
							// Checkbox: Always use filtered source
							$check_count_array = $filtered_source;
							// If checkbox combine logic is AND, set not_found_option_as_zero to true
							$not_found_option_as_zero = $combine_logic === 'AND';
						} else {
							// Other filters: Use filtered source if this filter is not active
							if ( $this_active_filter === false ) {
								$check_count_array = $filtered_source;
								// Don't set not_found_option_as_zero or other options will be disabled
								$not_found_option_as_zero = false;
							}
							// Reach here, this filter is active, use current count, don't set $check_count_array
						}
					}

					// Find the count from the check_count_array
					if ( ! empty( $check_count_array ) ) {
						$found = false;
						foreach ( $check_count_array as $counted ) {
							// Loop through the source and check if the value is current option value
							if ( self::is_option_value_matched( $counted['filter_value'], $option['value'] ) ) {
								$count = $counted['count'];
								$found = true;
								break;
							}
						}

						if ( ! $found && $not_found_option_as_zero ) {
							// This option is not found in the count array, set count to 0
							$count = 0;
						}
					}

					// Update option count
					$option['count'] = $count;
				}

				// Farget query results count is 0: set count to 0, if this filter is not active
				if ( $query_results_count == 0 && $this_active_filter === false ) {
					$option['count'] = 0;
				}

				// Disable the option if count is 0
				if ( $option['count'] === 0 && ! $option['is_all'] && ! $option['is_placeholder'] ) {
					$option['disabled'] = true;
					$option['class']   .= ' brx-option-disabled';

					if ( $hide_empty ) {
						// skip to next option to avoid safari and empty <li> style issues (#86bxj43yg)
						continue;
					}
				}

				// Use custom 'filterLabelAll' text for all option (radio), and placeholder option (select)
				if ( ( $option['is_all'] || $option['is_placeholder'] ) && isset( $settings['filterLabelAll'] ) ) {
					$option['text'] = $settings['filterLabelAll'];
				}

				// Maybe hierarchy
				if ( isset( $option['depth'] ) ) {
					// Add depth-n class
					$option['class'] .= ' depth-' . $option['depth'];

					// Add dash prefix to the text (except for radio input which is using button display mode)
					$indent = ! isset( $settings['displayMode'] ) || $settings['displayMode'] !== 'button';

					if ( $indent && $option['depth'] != 0 ) {
						// Custom indentation: Don't repeat
						if ( isset( $settings['filterChildIndentation'] ) ) {
							$option['text'] = esc_attr( $settings['filterChildIndentation'] ) . $option['text'];
						}
						// Default indentation: Repeat dash (one dash for each depth level)
						else {
							$option['text'] = str_repeat( '&mdash;', $option['depth'] ) . ' ' . $option['text'];
						}
					}
				}

				$option['class'] = trim( $option['class'] );

				$options[] = $option;
			}
		}

		$this->populated_options = $options;
	}

	/**
	 * Get populated options
	 * Only used for filter-select, filter-radio, filter-checkbox
	 *
	 * @since 2.0.2
	 */
	public function get_populated_options() {
		$populated_options = $this->populated_options ?? [];

		$settings       = $this->settings;
		$filter_action  = $settings['filterAction'] ?? 'filter';
		$filter_source  = $settings['filterSource'] ?? false;
		$option_orderby = $settings['populatedOptionsOrderBy'] ?? 'value';
		$option_order   = $settings['populatedOptionsOrder'] ?? 'asc';

		// Modify the populated options order if needed
		if ( $filter_action === 'filter' && $filter_source !== 'taxonomy' && in_array( $this->name, [ 'filter-select', 'filter-radio', 'filter-checkbox' ], true ) && ! empty( $populated_options ) ) {

			// STEP: Always place is_all and is_placeholder options at the top
			$top_options   = [];
			$other_options = [];

			foreach ( $populated_options as $option ) {
				if ( ! empty( $option['is_all'] ) || ! empty( $option['is_placeholder'] ) ) {
					$top_options[] = $option;
				} else {
					$other_options[] = $option;
				}
			}

			// STEP: Sort other_options based on the orderby and order settings
			if ( $option_orderby === 'value' ) {
				usort(
					$other_options,
					function( $a, $b ) use ( $option_order ) {
						return self::smart_compare( $a['value'], $b['value'], $option_order );
					}
				);
			} elseif ( $option_orderby === 'label' ) {
				usort(
					$other_options,
					function( $a, $b ) use ( $option_order ) {
						return self::smart_compare( $a['text'], $b['text'], $option_order );
					}
				);
			}

			// STEP: Merge top options and other options
			$populated_options = array_merge( $top_options, $other_options );

		}

		// Return the populated options @see https://academy.bricksbuilder.io/article/filter-bricks-filter_element-populated_options/ (@since 2.0.2)
		return apply_filters( 'bricks/filter_element/populated_options', $populated_options, $this );
	}

	/**
	 * For filter-select, filter-radio, filter-checkbox
	 *
	 * @since 1.11
	 */
	public function get_option_text_with_count( $option ) {
		$settings       = $this->settings;
		$text           = esc_html( trim( $option['text'] ) );
		$count          = $option['count'] ?? 0;
		$is_all         = $option['is_all'] ?? false;
		$is_placeholder = $option['is_placeholder'] ?? false;
		$filter_action  = $settings['filterAction'] ?? 'filter';

		$hide_count = isset( $settings['filterHideCount'] );
		$no_bracket = isset( $settings['filterCountNoBracket'] );

		// Return text only
		if ( $hide_count || $is_all || $is_placeholder || $filter_action === 'sort' || $filter_action === 'per_page' ) {
			return $text;
		}

		$count = $no_bracket ? $count : "($count)";

		if ( in_array( $this->name, [ 'filter-radio', 'filter-checkbox' ], true ) ) {
			// Wrap the count with span for filter-radio and filter-checkbox
			$count = '<span class="brx-option-count">' . $count . '</span>';
		} else {
			// For filter-select, add a space before the count (not controlled by CSS) (@since 1.12.3)
			$count = ' ' . $count;
		}

		return $text . $count;
	}

	/**
	 * For filter-select, filter-radio
	 */
	public function setup_sort_options() {
		if ( ! in_array( $this->name, [ 'filter-select', 'filter-radio' ], true ) ) {
			return;
		}

		$settings = $this->settings;

		$sort_options = ! empty( $settings['sortOptions'] ) ? $settings['sortOptions'] : false;

		if ( ! $sort_options ) {
			return;
		}

		$options = [];

		if ( $this->name === 'filter-select' ) {
			// Add placeholder option
			$options[] = [
				'value'          => '',
				'text'           => esc_html__( 'Select sort', 'bricks' ),
				'class'          => 'placeholder',
				'is_placeholder' => true,
			];
		}

		foreach ( $sort_options as $option ) {
			$sort_source = $option['optionSource'] ?? false;
			$label       = $option['optionLabel'] ?? false;

			if ( ! $sort_source || ! $label ) {
				continue;
			}

			// If the source contains |, means it is a term or user, just remove the prefix (@since 1.12)
			$sort_source = str_replace( [ 'term|', 'user|' ], '', $sort_source );

			$order = $option['optionOrder'] ?? 'ASC';
			$value = $sort_source . '_' . $order;

			if ( in_array( $sort_source, [ 'meta_value','meta_value_num' ], true ) ) {
				// Ensure optionMetaKey is not empty
				if ( empty( $option['optionMetaKey'] ) ) {
					continue;
				}

				$value = $option['optionMetaKey'] . '_' . $order;
			}

			$options[] = [
				'value' => $value,
				'text'  => $label,
				'class' => '',
			];
		}

		$this->populated_options = $options;
	}

	/**
	 * For filter-select, filter-radio
	 * Note: Not retrieving the per_page options from the query history for now
	 *
	 * @since 1.12.2
	 */
	public function setup_per_page_options() {
		if ( ! in_array( $this->name, [ 'filter-select', 'filter-radio' ], true ) ) {
			return;
		}

		$settings = $this->settings;

		$options = [];

		if ( $this->name === 'filter-select' ) {
			// Add placeholder option
			$options[] = [
				'value'          => '',
				'text'           => esc_html__( 'Results per page', 'bricks' ),
				'class'          => 'placeholder',
				'is_placeholder' => true,
			];
		}

		// Get per page options array via settings
		$per_page_array = self::get_per_page_options_array( $settings );

		foreach ( $per_page_array as $per_page ) {
			$options[] = [
				'value' => $per_page,
				'text'  => $per_page,
				'class' => '',
			];
		}

		$this->populated_options = $options;
	}

	public static function get_per_page_options_array( $settings = [] ) {
		$per_page_string = $settings['perPageOptions'] ?? '10, 20, 50, 100';

		// STEP: Convert string to array
		$per_page_array = explode( ',', (string) $per_page_string );
		$per_page_array = array_map( 'trim', $per_page_array );
		// STEP: Ensure no empty value, and all unique values
		$per_page_array = array_unique( array_filter( $per_page_array ) );

		return $per_page_array;
	}

	/**
	 * Sort the terms hierarchically
	 */
	public static function sort_terms_hierarchically( &$data_source, &$new_source, $parentId = 0 ) {
		foreach ( $data_source as $i => $data ) {
			if ( isset( $data['is_placeholder'] ) ) {
				$new_source['placeholder'] = $data;
				unset( $data_source[ $i ] );
				continue;
			}

			if ( $data['parent'] == $parentId && isset( $data['value_id'] ) ) {
				$new_source[ $data['value_id'] ] = $data;
				unset( $data_source[ $i ] );
				continue;
			}
		}

		foreach ( $new_source as $parent_id => &$top_cat ) {
			$top_cat['children'] = [];
			self::sort_terms_hierarchically( $data_source, $top_cat['children'], $parent_id );
		}
	}

	/**
	 * Now we need to flatten the arrays.
	 * If no children, just push to $flattern and set depth to 0
	 * If has children, push the childrens to $flattern and set depth to its parent depth + 1 (recursively).
	 * The children must be placed under its parent
	 * Then save all nested children's value_id to children_ids key of its parent (recursively)
	 */
	public static function flatten_terms_hierarchically( &$source, &$flattern, $parentId = 0, $depth = 0 ) {
		foreach ( $source as $i => $data ) {
			if ( $data['parent'] == $parentId ) {
				$data['depth'] = $depth;
				$flattern[]    = $data;
				unset( $source[ $i ] );

				if ( ! empty( $data['children'] ) ) {
					// Save all children ids to children_ids key of its parent
					$children_ids                                       = array_values( array_column( $data['children'], 'value_id' ) );
					$flattern[ count( $flattern ) - 1 ]['children_ids'] = $children_ids;

					self::flatten_terms_hierarchically( $data['children'], $flattern, $data['value_id'], $depth + 1 );
				}
			}
		}

		// Unset children key
		foreach ( $flattern as $i => $term ) {
			unset( $flattern[ $i ]['children'] );
		}
	}

	/**
	 * Some of the flattened terms may have children_ids
	 * But we need to merge the children_ids to its parent recursively
	 * Not in Beta
	 */
	public static function update_children_ids( &$flattened_terms, &$updated_data_source ) {
		foreach ( $flattened_terms as $i => $term ) {
			$updated_data_source[ $i ] = $term;

			if ( ! empty( $term['children_ids'] ) && $term['depth'] > 0 ) {
				// Find the parent & merge the children_ids (recursively)
				foreach ( $updated_data_source as $j => $parent ) {
					if ( self::is_option_value_matched( $parent['value_id'], $term['parent'] ) ) {
						$updated_data_source[ $j ]['children_ids'] = array_merge( $updated_data_source[ $j ]['children_ids'], $term['children_ids'] );
						break;
					}
				}
			}
		}
	}

	/**
	 * Return query filter controls
	 *
	 * If element support query filters.
	 *
	 * Only common controls are returned.
	 * Each element might add or remove controls.
	 *
	 * @since 1.9.6
	 */
	public function get_filter_controls() {
		if ( ! in_array( $this->name, Query_Filters::filter_controls_elements(), true ) ) {
			return [];
		}

		$controls = [];

		$controls['filterQueryId'] = [
			'type'             => 'query-list',
			'label'            => esc_html__( 'Target query', 'bricks' ),
			'placeholder'      => esc_html__( 'Select', 'bricks' ),
			'excludeMainQuery' => true, // (@since 1.12.2)
			'desc'             => esc_html__( 'Select the query this filter should target.', 'bricks' ) . ' ' . esc_html__( 'Only post queries are supported in this version.', 'bricks' ),
		];

		$controls['filterQueryIdInfo'] = [
			'type'     => 'info',
			'content'  => esc_html__( 'Target query has not been set. Without connecting a filter to a query, the filter has no effect.', 'bricks' ),
			'required' => [ 'filterQueryId', '=', '' ],
		];

		// NOTE: Not in use yet
		// $controls['filterMethod'] = [
		// 'type'    => 'select',
		// 'label'   => esc_html__( 'Filter method', 'bricks' ),
		// 'options' => [
		// 'ajax'     => 'AJAX',
		// 'refresh'  => esc_html__( 'Refresh', 'bricks' ),
		// ],
		// ];

		// @since 1.11
		$controls['filterNiceName'] = [
			'type'           => 'text',
			'label'          => esc_html__( 'URL parameter', 'bricks' ),
			'required'       => [ 'filterQueryId', '!=', '' ],
			'hasDynamicData' => false,
			'inline'         => true,
			'placeholder'    => 'eg. _color',
			'description'    => esc_html__( 'Define a unique, more readable URL parameter name for this filter.', 'bricks' ),
		];

		$controls['filterNiceNameInfo'] = [
			'type'     => 'info',
			'required' => [
				[ 'filterQueryId', '!=', '' ],
				[ 'filterNiceName', '!=', '' ]
			],
			'content'  => esc_html__( 'Use a prefix to avoid conflicts with plugins or WordPress reserved parameters.', 'bricks' ),
		];

		$controls['filterApplyOn'] = [
			'type'        => 'select',
			'label'       => esc_html__( 'Apply on', 'bricks' ),
			'options'     => [
				'change' => esc_html__( 'Input', 'bricks' ),
				'click'  => esc_html__( 'Submit', 'bricks' ),
			],
			'inline'      => true,
			'placeholder' => esc_html__( 'Input', 'bricks' ),
			'required'    => [ 'filterQueryId', '!=', '' ],
		];

		// Select & radio input: Add filter & sort as filterActions option
		if ( in_array( $this->name, [ 'filter-select', 'filter-radio' ], true ) ) {
			$controls['filterAction'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Action', 'bricks' ),
				'options'     => [
					'filter'   => esc_html__( 'Filter', 'bricks' ),
					'sort'     => esc_html__( 'Sort', 'bricks' ),
					'per_page' => esc_html__( 'Results per page', 'bricks' ), // (@since 1.12.2)
				],
				'inline'      => true,
				'placeholder' => esc_html__( 'Filter', 'bricks' ),
				'required'    => [ 'filterQueryId', '!=', '' ],
			];
		}

		// Filter options for input-select, input-radio, input-checkbox, input-datepicker
		if ( in_array( $this->name, [ 'filter-checkbox', 'filter-datepicker', 'filter-radio', 'filter-range', 'filter-select' ], true ) ) {
			$controls['filterSource'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Source', 'bricks' ),
				'inline'      => true,
				'options'     => [
					'taxonomy'    => esc_html__( 'Taxonomy', 'bricks' ),
					'wpField'     => esc_html__( 'WordPress field', 'bricks' ),
					'customField' => esc_html__( 'Custom field', 'bricks' ),
				],
				'placeholder' => esc_html__( 'Select', 'bricks' ),
				'required'    => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
				],
			];

			// source field type so we can show the correct field options
			$controls['sourceFieldType'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Field type', 'bricks' ),
				'inline'      => true,
				'options'     => [
					'post' => esc_html__( 'Post', 'bricks' ),
					'term' => esc_html__( 'Term', 'bricks' ),
					'user' => esc_html__( 'User', 'bricks' ),
				],
				'placeholder' => esc_html__( 'Post', 'bricks' ),
				'required'    => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', [ 'wpField', 'customField' ] ],
				],
			];

			// source:post wpPostField - post date, post type, post status, post author, post modified date
			$controls['wpPostField'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Field', 'bricks' ),
				'inline'      => true,
				'options'     => [
					'post_id'     => esc_html__( 'Post title', 'bricks' ) . ' (ID)', // Change to title which makes more sense (@since 1.12.2)
					'post_type'   => esc_html__( 'Post type', 'bricks' ),
					'post_status' => esc_html__( 'Post status', 'bricks' ),
					'post_author' => esc_html__( 'Post author', 'bricks' ),
				],
				'placeholder' => esc_html__( 'Select', 'bricks' ),
				'required'    => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'wpField' ],
					[ 'sourceFieldType', '=', [ '', 'post' ] ],
				],
			];

			// source:user wpUserField - user role, user display name, user nicename, user email, user url, user registered date
			$controls['wpUserField'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Field', 'bricks' ),
				'inline'      => true,
				'options'     => [
					'user_id'   => esc_html__( 'User name', 'bricks' ) . ' (ID)', // (@since 2.0)
					'user_role' => esc_html__( 'User role', 'bricks' ),
				],
				'placeholder' => esc_html__( 'Select', 'bricks' ),
				'required'    => [
					[ 'filterSource', '=', 'wpField' ],
					[ 'sourceFieldType', '=', 'user' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
				]
			];

			// source:term wpTermField - term name (@since 2.0)
			$controls['wpTermField'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Field', 'bricks' ),
				'inline'      => true,
				'options'     => [
					'term_id' => esc_html__( 'Term name', 'bricks' ) . ' (ID)', // (@since 2.0)
				],
				'placeholder' => esc_html__( 'Select', 'bricks' ),
				'required'    => [
					[ 'filterSource', '=', 'wpField' ],
					[ 'sourceFieldType', '=', 'term' ],
					[ 'filterAction', '!=', 'sort' ],
				]
			];

			$controls['filterTaxonomy'] = [
				'type'          => 'select',
				'label'         => esc_html__( 'Taxonomy', 'bricks' ),
				'inline'        => true,
				'options'       => Setup::$control_options['taxonomies'],
				'placeholder'   => esc_html__( 'Select', 'bricks' ),
				'required'      => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'taxonomy' ],
				],
				'clearOnChange' => [
					'filterTermInclude',
					'filterTermExclude',
				],
			];

			$controls['filterTaxonomyOrderBy'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Order by', 'bricks' ),
				'inline'      => true,
				'options'     => Setup::$control_options['termsOrderBy'],
				'placeholder' => esc_html__( 'Name', 'bricks' ),
				'required'    => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'taxonomy' ],
				],
			];

			$controls['filterTaxonomyOrderMetaKey'] = [
				'type'           => 'text',
				'label'          => esc_html__( 'Order meta key', 'bricks' ),
				'inline'         => true,
				'hasDynamicData' => false,
				'placeholder'    => esc_html__( 'Meta key', 'bricks' ),
				'required'       => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '!=', 'sort' ],
					[ 'filterSource', '=', 'taxonomy' ],
					[ 'filterTaxonomyOrderBy', '=', [ 'meta_value', 'meta_value_num' ] ],
				],
			];

			// @since 1.11
			$controls['filterTaxonomyOrder'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Order', 'bricks' ),
				'inline'      => true,
				'options'     => Setup::get_control_options( 'queryOrder' ),
				'placeholder' => esc_html__( 'ASC', 'bricks' ),
				'required'    => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'taxonomy' ],
				],
			];

			// Include terms
			$controls['filterTermInclude'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Terms', 'bricks' ) . ': ' . esc_html__( 'Include', 'bricks' ),
				'optionsAjax' => [
					'action'    => 'bricks_get_terms_options',
					'postTypes' => [ 'any' ],
					'taxonomy'  => '{{filterTaxonomy|array}}', // @since 1.11
				],
				'multiple'    => true,
				'searchable'  => true,
				'inline'      => true,
				'required'    => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'taxonomy' ],
					[ 'filterTaxonomy', '!=', '' ],
				],
				'placeholder' => esc_html__( 'None', 'bricks' ),
			];

			// Exclude terms
			$controls['filterTermExclude'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Terms', 'bricks' ) . ': ' . esc_html__( 'Exclude', 'bricks' ),
				'optionsAjax' => [
					'action'    => 'bricks_get_terms_options',
					'postTypes' => [ 'any' ],
					'taxonomy'  => '{{filterTaxonomy|array}}', // @since 1.11
				],
				'multiple'    => true,
				'searchable'  => true,
				'inline'      => true,
				'required'    => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'taxonomy' ],
					[ 'filterTaxonomy', '!=', '' ],
				],
				'placeholder' => esc_html__( 'None', 'bricks' ),
			];

			// Top level terms only
			$controls['filterTermTopLevel'] = [
				'type'     => 'checkbox',
				'label'    => esc_html__( 'Top level terms only', 'bricks' ),
				'required' => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'taxonomy' ],
					[ 'filterTaxonomy', '!=', '' ],
				],
			];

			$controls['filterHideCount'] = [
				'type'     => 'checkbox',
				'label'    => esc_html__( 'Hide count', 'bricks' ),
				'required' => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '!=', '' ],
				],
			];

			$controls['filterHideEmpty'] = [
				'type'     => 'checkbox',
				'label'    => esc_html__( 'Hide empty', 'bricks' ),
				'required' => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '!=', '' ],
				],
			];

			// @since 1.11
			if ( in_array( $this->name, [ 'filter-checkbox', 'filter-radio' ], true ) ) {
				$controls['filterCountNoBracket'] = [
					'type'     => 'checkbox',
					'label'    => esc_html__( 'Hide count bracket', 'bricks' ),
					'info'     => sprintf( esc_html( 'Style count via %s', 'bricks' ), '.brx-option-count' ),
					'required' => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '!=', '' ],
					],
				];
			}

			$controls['filterHierarchical'] = [
				'type'     => 'checkbox',
				'label'    => esc_html__( 'Hierarchical', 'bricks' ),
				'required' => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'taxonomy' ],
				],
			];

			// Auto check children option for checkbox
			if ( $this->name === 'filter-checkbox' ) {
				$controls['filterAutoCheckChildren'] = [
					'type'     => 'checkbox',
					'label'    => esc_html__( 'Auto toggle child terms', 'bricks' ),
					'required' => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '=', 'taxonomy' ],
						[ 'filterHierarchical', '=', true ],
					],
				];
			}

			// Indendation for checkbox, radio, select taxonomy
			if ( in_array( $this->name, [ 'filter-checkbox', 'filter-radio', 'filter-select' ], true ) ) {
				$controls['filterChildIndentation'] = [
					'type'        => 'text',
					'inline'      => true,
					'dd'          => false,
					'label'       => esc_html__( 'Indent', 'bricks' ) . ': ' . esc_html__( 'Prefix', 'bricks' ),
					'placeholder' => '—',
					'required'    => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '=', 'taxonomy' ],
						[ 'filterHierarchical', '=', true ],
						[ 'displayMode', '!=', 'button' ],
					],
				];
			}

			// Indentation gap for checkbox, radio, taxonomy
			if ( in_array( $this->name, [ 'filter-checkbox', 'filter-radio' ], true ) ) {
				$controls['filterChildIndentationGap'] = [
					'type'     => 'number',
					'units'    => true,
					'dd'       => false,
					'label'    => esc_html__( 'Indent', 'bricks' ) . ': ' . esc_html__( 'Gap', 'bricks' ),
					'required' => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '=', 'taxonomy' ],
						[ 'filterHierarchical', '=', true ],
						[ 'displayMode', '!=', 'button' ],
					],
					'css'      => [
						[
							'selector' => '[class*="depth-"]:not([class*="depth-0"])',
							'property' => 'margin-inline-start',
						],
					],
				];
			}

			// Custom fields integration (@since 1.11.1)
			if ( Helpers::enabled_query_filters_integration() ) {
				$controls['fieldProvider'] = [
					'type'        => 'select',
					'label'       => esc_html__( 'Provider', 'bricks' ),
					'inline'      => true,
					'options'     => Integrations\Query_Filters\Fields::get_active_provider_list(),
					'required'    => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '=', 'customField' ],
					],
					'placeholder' => 'WordPress',
				];

				$controls['fieldProviderCustomKeyInfo'] = [
					'type'     => 'info',
					'content'  => esc_html__( 'Use the dynamic picker in the "Meta key" control below to select the desired custom field. The dynamic data tag is used to retrieves the field settings, it is not parsed.', 'bricks' ),
					'required' => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '=', 'customField' ],
						[ 'fieldProvider', '!=', [ '', 'none' ] ],
					],
				];
			}

			$controls['customFieldKey'] = [
				'type'           => 'text',
				'label'          => esc_html__( 'Meta key', 'bricks' ),
				'inline'         => true,
				'hasDynamicData' => false,
				'required'       => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'customField' ],
				],
			];

			// Change customFieldKey dynamicData Condition (@since 1.11.1)
			if ( Helpers::enabled_query_filters_integration() ) {
				unset( $controls['customFieldKey']['hasDynamicData'] );
				$controls['customFieldKey']['dynamicDataConditions'] = [
					[ 'fieldProvider', '!=', [ '', 'none' ] ],
				];
			}

			$controls['fieldCompareOperator'] = [
				'type'           => 'select',
				'label'          => esc_html__( 'Compare', 'bricks' ),
				'options'        => Setup::get_control_options( 'queryCompare' ),
				'inline'         => true,
				'hasDynamicData' => false,
				'placeholder'    => 'IN',
				'required'       => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', 'customField' ],
				],
			];

			// Multiselect logic for checkbox (@since 1.11.1)
			if ( $this->name === 'filter-checkbox' ) {
				$controls['filterMultiLogic'] = [
					'type'        => 'select',
					'label'       => esc_html__( 'Multiple options', 'bricks' ),
					'options'     => [
						'OR'  => 'OR',
						'AND' => 'AND',
					],
					'inline'      => true,
					'placeholder' => 'OR',
					'required'    => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
					],
				];
			}

			// Options (@since 2.0.2)
			$controls['optionSep'] = [
				'label'    => esc_html__( 'Options', 'bricks' ),
				'type'     => 'separator',
				'required' => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '!=', '' ],
				],
			];

			// Radio Hide "All" option
			if ( $this->name === 'filter-radio' ) {
				$controls['filterHideAllOption'] = [
					'type'     => 'checkbox',
					'label'    => esc_html__( 'Hide "All" option', 'bricks' ),
					'required' => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '!=', '' ],
					],
				];
			}

			// Radio & select filter label for first item (All)
			if ( in_array( $this->name, [ 'filter-radio', 'filter-select' ], true ) ) {
				$controls['filterLabelAll'] = [
					'type'     => 'text',
					'inline'   => true,
					'dd'       => false,
					'label'    => esc_html__( 'Label', 'bricks' ) . ': ' . esc_html__( 'All', 'bricks' ),
					'required' => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '!=', '' ],
						[ 'filterHideAllOption', '!=', true ], // Hide All option for radio
					],
				];
			}

			$controls['labelMapping'] = [
				'type'        => 'select',
				'label'       => esc_html__( 'Label', 'bricks' ),
				'inline'      => true,
				'options'     => [
					'value'  => esc_html__( 'Value', 'bricks' ),
					'custom' => esc_html__( 'Custom', 'bricks' ),
				],
				'placeholder' => esc_html__( 'Value', 'bricks' ),
				'required'    => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', [ 'customField', 'wpField', 'wcField' ] ],
				],
			];

			$controls['customLabelMapping'] = [
				'type'          => 'repeater',
				'label'         => esc_html__( 'Label', 'bricks' ) . ': ' . esc_html__( 'Custom', 'bricks' ),
				'desc'          => esc_html__( 'Set custom labels for specific values.', 'bricks' ),
				'titleProperty' => 'optionLabel',
				'fields'        => [
					'optionMetaValue' => [
						'type'           => 'text',
						'label'          => esc_html__( 'Find', 'bricks' ) . ' (' . esc_html__( 'Value', 'bricks' ) . ')',
						'inline'         => true,
						'hasDynamicData' => false,
					],

					'optionLabel'     => [
						'type'           => 'text',
						'label'          => esc_html__( 'Label as', 'bricks' ),
						'inline'         => true,
						'hasDynamicData' => false,
					],
				],
				'required'      => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
					[ 'filterSource', '=', [ 'customField', 'wpField', 'wcField' ] ],
					[ 'labelMapping', '=', 'custom' ],
				],
			];

			// Populated options order by & order, exclude taxonomy source (@since 2.0.2)
			if ( in_array( $this->name, [ 'filter-radio', 'filter-select','filter-checkbox' ], true ) ) {
				$controls['populatedOptionsOrderBy'] = [
					'type'        => 'select',
					'label'       => esc_html__( 'Order by', 'bricks' ),
					'inline'      => true,
					'options'     => [
						'value' => esc_html__( 'Value', 'bricks' ),
						'label' => esc_html__( 'Label', 'bricks' ),
					],
					'placeholder' => esc_html__( 'Value', 'bricks' ),
					'required'    => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '=', [ 'customField', 'wpField', 'wcField' ] ],
					],
				];

				$controls['populatedOptionsOrder'] = [
					'type'        => 'select',
					'label'       => esc_html__( 'Order', 'bricks' ),
					'inline'      => true,
					'options'     => [
						'asc'  => esc_html__( 'Ascending', 'bricks' ),
						'desc' => esc_html__( 'Descending', 'bricks' ),
					],
					'placeholder' => esc_html__( 'Ascending', 'bricks' ),
					'required'    => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '=', [ 'customField', 'wpField', 'wcField' ] ],
					],
				];

				$controls['populatedOptionsInfo'] = [
					'type'     => 'info',
					'content'  => esc_html__( 'Filter', 'bricks' ) . ': ' . Helpers::article_link( 'filter-bricks-filter_element-populated_options', '<code>bricks/filter_element/populated_options</code>' ),
					'required' => [
						[ 'filterQueryId', '!=', '' ],
						[ 'filterAction', '=', [ '', 'filter' ] ],
						[ 'filterSource', '=', [ 'customField', 'wpField', 'wcField' ] ],
					],
				];
			}

			// NOTE: Necessary to save & reload builder to generate filter index and populate options correctly
			$controls['filterApply'] = [
				'type'     => 'apply',
				'reload'   => true,
				'label'    => esc_html__( 'Update filter index', 'bricks' ),
				'desc'     => esc_html__( 'Click to apply the latest filter settings. This ensures all filter options are up-to-date.', 'bricks' ),
				'required' => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', [ '', 'filter' ] ],
				],
			];
		}

		// Sorting controls
		if ( in_array( $this->name, [ 'filter-select', 'filter-radio' ], true ) ) {
			// queryOrderBy, termsOrderBy, usersOrderBy
			$post_sort_options  = Setup::get_control_options( 'queryOrderBy' );
			$term_order_options = Setup::get_control_options( 'termsOrderBy' );
			$user_order_options = Setup::get_control_options( 'usersOrderBy' );

			$sort_options = [];
			foreach ( $post_sort_options as $key => $value ) {
				$key                  = $key;
				$value                = '(' . esc_html__( 'Post', 'bricks' ) . ') ' . $value;
				$sort_options[ $key ] = $value;
			}

			foreach ( $term_order_options as $key => $value ) {
				$key                  = 'term|' . $key;
				$value                = '(' . esc_html__( 'Term', 'bricks' ) . ') ' . $value;
				$sort_options[ $key ] = $value;
			}

			foreach ( $user_order_options as $key => $value ) {
				$key                  = 'user|' . $key;
				$value                = '(' . esc_html__( 'User', 'bricks' ) . ') ' . $value;
				$sort_options[ $key ] = $value;
			}

			// Each of the options add . (Post) / .term (Term) / .user (User) prefix
			$controls['sortOptions'] = [
				'type'          => 'repeater',
				'label'         => esc_html__( 'Sort options', 'bricks' ),
				'titleProperty' => 'optionLabel',
				'fields'        => [
					'optionLabel'   => [
						'type'           => 'text',
						'label'          => esc_html__( 'Label', 'bricks' ),
						'hasDynamicData' => false,
					],
					'optionSource'  => [
						'type'        => 'select',
						'label'       => esc_html__( 'Source', 'bricks' ),
						'options'     => $sort_options,
						'placeholder' => esc_html__( 'Select', 'bricks' ),
						'searchable'  => true,
					],
					'optionMetaKey' => [
						'type'           => 'text',
						'label'          => esc_html__( 'Meta Key', 'bricks' ),
						'hasDynamicData' => false,
						'required'       => [
							[ 'optionSource', '=', [ 'meta_value', 'term|meta_value', 'user|meta_value', 'meta_value_num', 'term|meta_value_num', 'user|meta_value_num' ] ],
						],
					],
					'optionOrder'   => [
						'type'        => 'select',
						'label'       => esc_html__( 'Order', 'bricks' ),
						'options'     => [
							'ASC'  => esc_html__( 'Ascending', 'bricks' ),
							'DESC' => esc_html__( 'Descending', 'bricks' ),
						],
						'placeholder' => esc_html__( 'Ascending', 'bricks' ),
					]
				],
				'required'      => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', 'sort' ],
				],
			];

			// per_page options (@since 1.12.2)
			$controls['perPageOptions'] = [
				'type'           => 'text',
				'label'          => esc_html__( 'Options', 'bricks' ) . ': ' . esc_html__( 'Results per page', 'bricks' ),
				'hasDynamicData' => false,
				'placeholder'    => '10, 20, 50, 100',
				'required'       => [
					[ 'filterQueryId', '!=', '' ],
					[ 'filterAction', '=', 'per_page' ],
				],
				'description'    => esc_html__( 'Comma-separated list of results per page options.', 'bricks' ),
			];
		}

		// Button Mode (filter-radio, filter-checkbox)
		if ( in_array( $this->name, [ 'filter-radio', 'filter-checkbox' ], true ) ) {
			// MODE: Radio / Button
			$controls['modeSep'] = [
				'label' => esc_html__( 'Mode', 'bricks' ),
				'type'  => 'separator',
			];

			$controls['displayMode'] = [
				'label'       => esc_html__( 'Mode', 'bricks' ),
				'type'        => 'select',
				'inline'      => true,
				'options'     => [
					'default' => $this->name === 'filter-radio' ? esc_html__( 'Radio', 'bricks' ) : esc_html__( 'Checkbox', 'bricks' ),
					'button'  => esc_html__( 'Button', 'bricks' ),
				],
				'placeholder' => $this->name === 'filter-radio' ? esc_html__( 'Radio', 'bricks' ) : esc_html__( 'Checkbox', 'bricks' ),
			];

			// BUTTON
			$controls['buttonSep'] = [
				'label'    => esc_html__( 'Button', 'bricks' ),
				'type'     => 'separator',
				'required' => [ 'displayMode', '=', 'button' ],
			];

			$controls['buttonSize'] = [
				'label'       => esc_html__( 'Size', 'bricks' ),
				'type'        => 'select',
				'options'     => $this->control_options['buttonSizes'],
				'inline'      => true,
				'placeholder' => esc_html__( 'Default', 'bricks' ),
				'required'    => [ 'displayMode', '=', 'button' ],
			];

			$controls['buttonStyle'] = [
				'label'       => esc_html__( 'Style', 'bricks' ),
				'type'        => 'select',
				'options'     => $this->control_options['styles'],
				'inline'      => true,
				'placeholder' => esc_html__( 'None', 'bricks' ),
				'required'    => [ 'displayMode', '=', 'button' ],
			];

			$controls['buttonCircle'] = [
				'label'    => esc_html__( 'Circle', 'bricks' ),
				'type'     => 'checkbox',
				'required' => [ 'displayMode', '=', 'button' ],
			];

			$controls['buttonOutline'] = [
				'label'    => esc_html__( 'Outline', 'bricks' ),
				'type'     => 'checkbox',
				'required' => [
					[ 'displayMode', '=', 'button' ],
					[ 'buttonStyle', '!=', '' ],
				],
			];

			// Style none: Show background color, border, typography controls
			$controls['buttonBackgroundColor'] = [
				'label'    => esc_html__( 'Background color', 'bricks' ),
				'type'     => 'color',
				'css'      => [
					[
						'property' => 'background-color',
						'selector' => '&[data-mode="button"] .bricks-button',
					],
				],
				'required' => [ 'displayMode', '=', 'button' ],
			];

			$controls['buttonBorder'] = [
				'label'    => esc_html__( 'Border', 'bricks' ),
				'type'     => 'border',
				'css'      => [
					[
						'property' => 'border-color',
						'selector' => '&[data-mode="button"] .bricks-button',
					],
				],
				'required' => [ 'displayMode', '=', 'button' ],
			];

			$controls['buttonTypography'] = [
				'label'    => esc_html__( 'Typography', 'bricks' ),
				'type'     => 'typography',
				'css'      => [
					[
						'property' => 'font',
						'selector' => '&[data-mode="button"] .bricks-button',
					],
				],
				'required' => [ 'displayMode', '=', 'button' ],
			];

			// Active button
			$controls['buttonActiveSep'] = [
				'label'    => esc_html__( 'Button', 'bricks' ) . ' (' . esc_html__( 'Active', 'bricks' ) . ')',
				'type'     => 'separator',
				'required' => [ 'displayMode', '=', 'button' ],
			];

			$controls['buttonActiveBackgroundColor'] = [
				'label'    => esc_html__( 'Background color', 'bricks' ),
				'type'     => 'color',
				'css'      => [
					[
						'property' => 'background-color',
						'selector' => '&[data-mode="button"] .bricks-button.brx-option-active',
					],
				],
				'required' => [ 'displayMode', '=', 'button' ],
			];

			$controls['buttonActiveBorder'] = [
				'label'    => esc_html__( 'Border', 'bricks' ),
				'type'     => 'border',
				'css'      => [
					[
						'property' => 'border-color',
						'selector' => '&[data-mode="button"] .bricks-button.brx-option-active',
					],
				],
				'required' => [ 'displayMode', '=', 'button' ],
			];

			$controls['buttonActiveTypography'] = [
				'label'    => esc_html__( 'Typography', 'bricks' ),
				'type'     => 'typography',
				'css'      => [
					[
						'property' => 'font',
						'selector' => '&[data-mode="button"] .bricks-button.brx-option-active',
					],
				],
				'required' => [ 'displayMode', '=', 'button' ],
			];
		}

		/**
		 * Active filter prefix, suffix or title attribute
		 *
		 * @since 1.11
		 */
		if ( $this->name !== 'filter-active-filters' ) {
			$controls['filterActivePrefix'] = [
				'group'  => 'filter-active',
				'type'   => 'text',
				'label'  => esc_html__( 'Prefix', 'bricks' ),
				'inline' => true,
			];

			$controls['filterActiveSuffix'] = [
				'group'  => 'filter-active',
				'type'   => 'text',
				'label'  => esc_html__( 'Suffix', 'bricks' ),
				'inline' => true,
			];

			$controls['filterActiveTitle'] = [
				'group'  => 'filter-active',
				'type'   => 'text',
				'label'  => esc_html__( 'Title', 'bricks' ),
				'inline' => true,
			];
		}

		return apply_filters( 'bricks/filter_element/controls', $controls, $this );
	}

	/**
	 * Compares the both values in string format
	 *
	 * @since 1.12
	 */
	public static function is_option_value_matched( $option, $value ) {
		// Ensure both values are of the same type
		if ( is_array( $option ) ) {
			$option = array_map( 'strval', $option );
		} else {
			$option = strval( $option );
		}

		// Ensure both values are of the same type
		if ( is_array( $value ) ) {
			$value = array_map( 'strval', $value );
		} else {
			$value = strval( $value );
		}

		if ( is_array( $value ) ) {
			return in_array( $option, $value, true );
		}

		return $option === $value;
	}

	public static function get_range_formatted_value( $value, $settings ) {
		$mode           = isset( $settings['labelMode'] ) ? $settings['labelMode'] : 'range';
		$separator      = $settings['labelThousandSeparator'] ?? false;
		$decimal_places = isset( $settings['decimalPlaces'] ) ? (int) $settings['decimalPlaces'] : 0;
		$thousands      = ! empty( $settings['labelThousandSeparator'] ) ? $settings['labelThousandSeparator'] : '';
		$separator      = ! empty( $settings['labelSeparatorText'] ) ? bricks_render_dynamic_data( $settings['labelSeparatorText'] ) : ',';

		// Add thousands separator (Only for range)
		if ( $thousands && $mode === 'range' ) {
			$fomatted_value = number_format( $value, $decimal_places, '.', $separator );
		} else {
			$fomatted_value = number_format( $value, $decimal_places, '.', '' );
		}

		return $fomatted_value;
	}

	/**
	 * Smart comparison function that handles both numeric and alphanumeric sorting
	 *
	 * @param string $a First value to compare
	 * @param string $b Second value to compare
	 * @param string $order Sort order ('asc' or 'desc')
	 * @return int -1, 0, or 1 for sorting
	 */
	public static function smart_compare( $a, $b, $order = 'asc' ) {
		// Handle empty values
		if ( empty( $a ) && empty( $b ) ) {
			return 0;
		}
		if ( empty( $a ) ) {
			return $order === 'asc' ? -1 : 1;
		}
		if ( empty( $b ) ) {
			return $order === 'asc' ? 1 : -1;
		}

		// Check if both values are numeric
		$a_is_numeric = is_numeric( $a );
		$b_is_numeric = is_numeric( $b );

		// Both are numeric: compare as numbers
		if ( $a_is_numeric && $b_is_numeric ) {
			$result = (float) $a <=> (float) $b;
		}
		// Mixed types: numeric values come first in ascending order
		elseif ( $a_is_numeric && ! $b_is_numeric ) {
			$result = $order === 'asc' ? -1 : 1;
		}
		elseif ( ! $a_is_numeric && $b_is_numeric ) {
			$result = $order === 'asc' ? 1 : -1;
		}
		// Both are non-numeric: use natural string comparison
		else {
			$result = strnatcasecmp( $a, $b );
		}

		// Reverse result for descending order
		return $order === 'desc' ? -$result : $result;
	}
}
