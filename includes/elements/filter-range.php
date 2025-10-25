<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Filter_Range extends Filter_Element {
	public $name         = 'filter-range';
	public $icon         = 'ti-arrows-horizontal';
	public $filter_type  = 'range';
	private $min_value   = null;
	private $max_value   = null;
	private $current_min = null;
	private $current_max = null;

	public function get_label() {
		return esc_html__( 'Filter', 'bricks' ) . ' - ' . esc_html__( 'Range', 'bricks' );
	}
	public function set_control_groups() {
		$this->control_groups['label'] = [
			'title' => esc_html__( 'Label', 'bricks' ),
		];

		$this->control_groups['input'] = [
			'title'    => esc_html__( 'Input', 'bricks' ),
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->control_groups['slider'] = [
			'title'    => esc_html__( 'Slider', 'bricks' ),
			'required' => [ 'displayMode', '!=', 'input' ],
		];
	}

	public function set_controls() {
		// SORT / FILTER
		$filter_controls = $this->get_filter_controls();

		if ( ! empty( $filter_controls ) ) {
			// Support customField only
			unset( $filter_controls['filterSource']['options']['taxonomy'] );
			unset( $filter_controls['filterSource']['options']['wpField'] );
			unset( $filter_controls['filterTaxonomy'] );
			unset( $filter_controls['filterHierarchical'] );
			unset( $filter_controls['filterTaxonomyHideEmpty'] );
			unset( $filter_controls['filterHideCount'] );
			unset( $filter_controls['filterHideEmpty'] );
			unset( $filter_controls['labelMapping'] );
			unset( $filter_controls['customLabelMapping'] );
			unset( $filter_controls['fieldCompareOperator'] );

			$this->controls = array_merge( $this->controls, $filter_controls );
		}

		// MODE
		$this->controls['modeSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Mode', 'bricks' ),
		];

		$this->controls['disableAutoMinMax'] = [
			'type'        => 'checkbox',
			'label'       => esc_html__( 'Disable auto min/max value', 'bricks' ),
			'description' => esc_html__( 'By default, the min/max values are dynamically set based on each filter query loop results. Disable this feature so the min/max values are only set initially.', 'bricks' ),
		];

		$this->controls['displayMode'] = [
			'label'       => esc_html__( 'Mode', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'range' => esc_html__( 'Slider', 'bricks' ),
				'input' => esc_html__( 'Input', 'bricks' ),
			],
			'placeholder' => esc_html__( 'Slider', 'bricks' ),
		];

		$this->controls['step'] = [
			'label'       => esc_html__( 'Step', 'bricks' ),
			'type'        => 'number',
			'placeholder' => '1',
			// 'required' => [ 'displayMode', '=', 'input' ], // NOTE: Why limit step to input mode only?
		];

		$this->controls['decimalPlaces'] = [
			'label'       => esc_html__( 'Decimal places', 'bricks' ),
			'type'        => 'number',
			'placeholder' => '0',
			'inline'      => true,
		];

		// Auto-set via JS: toLocaleString()
		$this->controls['labelThousandSeparator'] = [
			// 'group'    => 'label',
			'label'    => esc_html__( 'Thousand separator', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'displayMode','!=','input' ],
		];

		$this->controls['labelSeparatorText'] = [
			// 'group'       => 'label',
			'label'       => esc_html__( 'Separator', 'bricks' ),
			'type'        => 'text',
			'inline'      => true,
			'placeholder' => ',',
			'required'    => [
				[ 'displayMode', '!=', 'input' ],
				[ 'labelThousandSeparator', '=', true ],
			],
		];

		// LABEL
		$this->controls['labelMin'] = [
			'group'  => 'label',
			'label'  => esc_html__( 'Min', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
		];

		$this->controls['labelMax'] = [
			'group'  => 'label',
			'label'  => esc_html__( 'Max', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
		];

		$this->controls['labelDirection'] = [
			'group'   => 'label',
			'label'   => esc_html__( 'Direction', 'bricks' ),
			'inline'  => true,
			'tooltip' => [
				'content'  => 'flex-direction',
				'position' => 'top-left',
			],
			'type'    => 'direction',
			'css'     => [
				[
					'property' => 'flex-direction',
					'selector' => '.min-max-wrap > *, .value-wrap > *',
				],
			],
		];

		$this->controls['labelGap'] = [
			'group' => 'label',
			'label' => esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => '.value-wrap > span',
				],
				[
					'property' => 'gap',
					'selector' => '.min-max-wrap > div',
				],
			],
		];

		$this->controls['labelTypography'] = [
			'group' => 'label',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.label',
				],
			],
		];

		// INPUT
		$this->controls['placeholderMin'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Placeholder', 'bricks' ) . ' (' . esc_html__( 'Min', 'bricks' ) . ')',
			'type'     => 'text',
			'inline'   => true,
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['placeholderMax'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Placeholder', 'bricks' ) . ' (' . esc_html__( 'Max', 'bricks' ) . ')',
			'type'     => 'text',
			'inline'   => true,
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['inputBackgroundColor'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Background color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'background-color',
					'selector' => '.min-max-wrap input',
				],
			],
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['inputBorder'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Border', 'bricks' ),
			'type'     => 'border',
			'css'      => [
				[
					'property' => 'border',
					'selector' => '.min-max-wrap input',
				],
			],
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['inputTypography'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.min-max-wrap input',
				],
			],
			'required' => [ 'displayMode', '=', 'input' ],
		];

		$this->controls['inputWidth'] = [
			'group'    => 'input',
			'label'    => esc_html__( 'Width', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'property' => 'width',
					'selector' => '.min-max-wrap input',
				],
			],
			'required' => [ 'displayMode', '=', 'input' ],
		];

		// SLIDER (@since 1.11)
		$this->controls['sliderSpacing'] = [
			'group'       => 'slider',
			'label'       => esc_html__( 'Spacing', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'padding-top',
					'selector' => '.double-slider-wrap',
				],
				[
					'property' => 'margin-top',
					'selector' => '.double-slider-wrap .value-wrap',
				],
			],
			'placeholder' => '14px',
			'required'    => [ 'displayMode', '!=', 'input' ],
		];

		// Bar
		$this->controls['sliderBarHeight'] = [
			'group'       => 'slider',
			'label'       => esc_html__( 'Bar', 'bricks' ) . ':' . ' ' . esc_html__( 'Height', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'border-width',
					'selector' => '.double-slider-wrap .slider-wrap .slider-base',
				],
				[
					'property' => 'border-width',
					'selector' => '.double-slider-wrap .slider-wrap .slider-track',
				],
			],
			'placeholder' => '2px',
			'required'    => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderBarColor'] = [
			'group'    => 'slider',
			'label'    => esc_html__( 'Bar', 'bricks' ) . ':' . ' ' . esc_html__( 'Color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap .slider-wrap .slider-base',
				],
			],
			'required' => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderBarColorActive'] = [
			'group'    => 'slider',
			'label'    => esc_html__( 'Bar', 'bricks' ) . ':' . ' ' . esc_html__( 'Color', 'bricks' ) . ' (' . esc_html__( 'Active', 'bricks' ) . ')',
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap .slider-wrap .slider-track',
				],
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
			],
			'required' => [ 'displayMode', '!=', 'input' ],
		];

		// Thumb
		$this->controls['sliderThumbSize'] = [
			'group'       => 'slider',
			'label'       => esc_html__( 'Thumb', 'bricks' ) . ': ' . esc_html__( 'Size', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'width',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'width',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
				[
					'property' => 'height',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'height',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
				[
					'property' => 'border-radius',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'border-radius',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
			],
			'placeholder' => '14px',
			'required'    => [ 'displayMode', '!=', 'input' ],
		];

		// @since 2.1
		$this->controls['sliderThumbBackgroundColor'] = [
			'group'    => 'slider',
			'label'    => esc_html__( 'Thumb', 'bricks' ) . ': ' . esc_html__( 'Background color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'background-color',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'background-color',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
			],
			'required' => [ 'displayMode', '!=', 'input' ],
		];

		// @since 2.1
		$this->controls['sliderThumbBorderFull'] = [
			'group'    => 'slider',
			'label'    => esc_html__( 'Thumb', 'bricks' ) . ': ' . esc_html__( 'Border', 'bricks' ),
			'type'     => 'border',
			'css'      => [
				[
					'property' => 'border',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'border',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
			],
			'required' => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderThumbBorder'] = [
			'deprecated'  => '2.1',
			'group'       => 'slider',
			'label'       => esc_html__( 'Thumb', 'bricks' ) . ': ' . esc_html__( 'Border width', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'border-width',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'border-width',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
			],
			'placeholder' => '2px',
			'required'    => [ 'displayMode', '!=', 'input' ],
		];

		$this->controls['sliderThumbColor'] = [
			'deprecated' => '2.1',
			'group'      => 'slider',
			'label'      => esc_html__( 'Thumb', 'bricks' ) . ': ' . esc_html__( 'Border color', 'bricks' ),
			'type'       => 'color',
			'css'        => [
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
				[
					'property' => 'border-color',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
			],
			'required'   => [ 'displayMode', '!=', 'input' ],
		];

		// @since 2.1
		$this->controls['sliderThumbBoxShadow'] = [
			'group'    => 'slider',
			'label'    => esc_html__( 'Thumb', 'bricks' ) . ': ' . esc_html__( 'Box shadow', 'bricks' ),
			'type'     => 'box-shadow',
			'css'      => [
				[
					'property' => 'box-shadow',
					'selector' => '.double-slider-wrap input[type="range"]::-moz-range-thumb',
				],
				[
					'property' => 'box-shadow',
					'selector' => '.double-slider-wrap input[type="range"]::-webkit-slider-thumb',
				],
			],
			'required' => [ 'displayMode', '!=', 'input' ],
		];
	}

	private function set_as_filter() {
		$settings = $this->settings;

		// Check required filter settings
		if ( empty( $settings['filterQueryId'] ) || empty( $settings['filterSource'] ) ) {
			return;
		}

		$this->prepare_sources();

		$auto_min_max         = ! empty( $settings['disableAutoMinMax'] ) ? false : true;
		$query_id             = $settings['filterQueryId'] ?? false;
		$active_filters       = Query_Filters::$active_filters[ $query_id ] ?? [];
		$this_active_filter   = false;
		$other_active_filters = [];
		$source_for_min_max   = '';
		$filtered_source      = $this->filtered_source ?? [];
		$choices_source       = $this->choices_source ?? [];
		$count_source         = [];

		// Auto min/max logic (@since 1.12)
		if ( $auto_min_max ) {
			// Similar logic with set_options_with_count(), additional queries generated (@since 1.12)
			if ( ! empty( $active_filters ) ) {
				// Get all active filters that will affect the count
				$filters_affecting_count = array_filter(
					$active_filters,
					function( $filter ) {
						return isset( $filter['query_type'] ) && $filter['query_type'] !== 'sort' && $filter['query_type'] !== 'pagination';
					}
				);

				// Assign this_active_filter and other_active_filters from filters_affecting_count
				foreach ( $filters_affecting_count as $filter ) {
					if ( $filter['filter_id'] === $this->id ) {
						$this_active_filter = $filter;
					}
					else {
						$other_active_filters[] = $filter;
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

				$disable_query_merge = $this->query_settings['disable_query_merge'] ?? false;
				$page_filters        = Query_filters::$page_filters ?? [];
				// Get query_vars from page filters if disable_query_merge is false and page filters should be applied
				if ( ! $disable_query_merge && Query_Filters::should_apply_page_filters( $count_query_vars ) ) {
					$count_query_vars     = Query::merge_query_vars( $count_query_vars, Query_Filters::generate_query_vars_from_page_filters() );
					$other_active_filters = array_merge( $other_active_filters, $page_filters );
				}

				// Get the count source
				if ( count( $count_query_vars ) > 0 ) {
					$count_source = Query_Filters::get_filtered_data_from_index( $this->id, Query_Filters::get_filter_object_ids( $query_id, 'original', $count_query_vars ) );
				}
			}

			// This filter is active and there are other active filters, use filtered_source
			if ( $this_active_filter !== false && count( $other_active_filters ) > 0 ) {
				$source_for_min_max = 'count_source';
			}

			// This filter is not active and there are other active filters, use filtered source
			elseif ( count( $other_active_filters ) > 0 ) {
				$source_for_min_max = 'filtered_source';
			}

			// No other active filters
			else {
				$source_for_min_max = 'choices_source';
			}

		}

		// Legacy logic - before 1.12
		else {
			$source_for_min_max = 'choices_source';
		}

		// Get min/max value from the correct source
		if ( ! empty( $$source_for_min_max ) ) {
			foreach ( $$source_for_min_max as $source ) {
				$choice_value = $source['filter_value'] ?? false;

				// Value could be zero, so we need to check if it's false
				if ( $choice_value === false ) {
					continue;
				}

				// Force to convert to float
				$choice_value = (float) $choice_value;

				// Set min/max value, set as Integer, we only support Integer
				if ( $this->min_value === null || $choice_value < $this->min_value ) {
					// If the value is 1.9, it will be converted to 1
					$choice_value = floor( $choice_value );
					// Convert to integer - Set min value
					$this->min_value = (float) $choice_value;
				}

				if ( $this->max_value === null || $choice_value > $this->max_value ) {
					// If the value is 1.9, it will be converted to 2
					$choice_value = ceil( $choice_value );
					// Convert to integer - Set max value
					$this->max_value = (float) $choice_value;
				}
			}
		}

		$ori_min_value = null;
		$ori_max_value = null;
		// Always get original min/max value from choices_source for frontend reset logic (@since 1.12)
		if ( ! empty( $choices_source ) ) {
			foreach ( $choices_source as $source ) {
				$choice_value = $source['filter_value'] ?? false;

				// Value could be zero, so we need to check if it's false
				if ( $choice_value === false ) {
					continue;
				}

				// Force to convert to float
				$choice_value = (float) $choice_value;

				// Set min/max value, set as Integer, we only support Integer
				if ( $ori_min_value === null || $choice_value < $ori_min_value ) {
					// If the value is 1.9, it will be converted to 1
					$choice_value = floor( $choice_value );
					// Convert to integer - Set min value
					$ori_min_value = (float) $choice_value;
				}

				if ( $ori_max_value === null || $choice_value > $ori_max_value ) {
					// If the value is 1.9, it will be converted to 2
					$choice_value = ceil( $choice_value );
					// Convert to integer - Set max value
					$ori_max_value = (float) $choice_value;
				}
			}
		}

		// Insert filter settings as data-brx-filter attribute
		$filter_settings                 = $this->get_common_filter_settings();
		$filter_settings['filterSource'] = $settings['filterSource'];

		// min, max, step values
		$filter_settings['min']           = $ori_min_value ?? 0; // For frontend Reset logic
		$filter_settings['max']           = $ori_max_value ?? 100; // For frontend Reset logic
		$filter_settings['step']          = isset( $settings['step'] ) ? (float) $settings['step'] : 1;
		$filter_settings['decimalPlaces'] = isset( $settings['decimalPlaces'] ) ? (int) $settings['decimalPlaces'] : 0;

		// thousand separator
		$display_mode = $settings['displayMode'] ?? 'range';
		if ( $display_mode === 'range' ) {
			$filter_settings['thousands'] = ! empty( $settings['labelThousandSeparator'] ) ? $settings['labelThousandSeparator'] : '';
			$filter_settings['separator'] = ! empty( $settings['labelSeparatorText'] ) ? $this->render_dynamic_data( $settings['labelSeparatorText'] ) : '';
		}

		$this->set_attribute( '_root', 'data-brx-filter', wp_json_encode( $filter_settings ) );
	}

	public function render() {
		$settings = $this->settings;

		if ( $this->is_filter_input() ) {
			$this->set_as_filter();

			// Return: Indexing in progress (@since 1.10)
			if ( $this->is_indexing() ) {
				return $this->render_element_placeholder(
					[
						'title' => esc_html__( 'Indexing in progress.', 'bricks' ),
					]
				);
			}
		}

		// Return: No filter source selected
		if ( empty( $settings['filterSource'] ) ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No filter source selected.', 'bricks' ),
				]
			);
		}

		$this->min_value = $this->min_value ?? 0;
		$this->max_value = $this->max_value ?? 100;

		// Avoid division by zero (@since 1.11)
		if ( $this->min_value === $this->max_value ) {
			$this->max_value += 1;
		}

		$this->current_min = $this->min_value ?? 0;
		$this->current_max = $this->max_value ?? 100;

		// In filter AJAX call, filterValue is the current filter value
		if ( isset( $settings['filterValue'] ) && is_array( $settings['filterValue'] ) ) {
			// The expected value is an array, first element is min, second element is max
			$current_value = $settings['filterValue'];

			if ( isset( $current_value[0] ) && ! empty( $current_value[0] ) ) {
				$this->current_min = $current_value[0];
			}

			if ( isset( $current_value[1] ) && ! empty( $current_value[1] ) ) {
				$this->current_max = $current_value[1];
			}
		}

		// Ensure current_min is not less than min_value
		if ( $this->current_min < $this->min_value ) {
			$this->current_min = $this->min_value;
		}

		// Ensure current_max is not greater than max_value
		if ( $this->current_max > $this->max_value ) {
			$this->current_max = $this->max_value;
		}

		echo "<div {$this->render_attributes('_root')}>";

		// Range slider UI
		$this->maybe_render_range_slider();

		// Input fields UI - must be rendered
		$this->render_input_fields();

		echo '</div>'; // end root
	}

	private function maybe_render_range_slider() {
		$settings     = $this->settings;
		$display_mode = $settings['displayMode'] ?? 'range';
		$label_min    = ! empty( $settings['labelMin'] ) ? $this->render_dynamic_data( $settings['labelMin'] ) : '';
		$label_max    = ! empty( $settings['labelMax'] ) ? $this->render_dynamic_data( $settings['labelMax'] ) : '';
		$step         = isset( $settings['step'] ) ? (float) $settings['step'] : 1;

		if ( $display_mode !== 'range' ) {
			return;
		}

		// Adjust slider-wrap width and left/right position (@since 1.11)
		$min_value = $this->current_min ?? 0;
		$max_value = $this->current_max ?? 100;

		$min_percent = ( $min_value - $this->min_value ) / ( $this->max_value - $this->min_value ) * 100;
		$max_percent = ( $max_value - $this->min_value ) / ( $this->max_value - $this->min_value ) * 100;
		$width       = $max_percent - $min_percent;

		// Tweak for firefox, otherwise there might be a small line visible
		if ( $width === 100 ) {
			$width = 99;
		}

		// @since 1.11.1: If it's RTL, we need to offset from left
		$style = 'width:' . $width . '%; ' . ( is_rtl() ? 'right:' : 'left:' ) . $min_percent . '%;';

		// Hide the track if the width is less than 2%. Otherwise, there might be a small line visible
		if ( $width <= 2 ) {
			$style .= ' visibility: hidden;';
		}

		echo '<div class="double-slider-wrap">';

		// Slider wrap, slider-base, slider-track (@since 1.11)
		echo '<div class="slider-wrap">';
		echo '<div class="slider-base"></div>';
		echo '<div class="slider-track" style="' . $style . '"></div>';

		// Generate unique IDs for labels (@since 1.12.2)
		$min_label_id = "label-min-{$this->id}";
		$max_label_id = "label-max-{$this->id}";

		$this->set_attribute( 'min-range', 'type', 'range' );
		$this->set_attribute( 'min-range', 'class', 'min' );
		$this->set_attribute( 'min-range', 'name', "form-field-min-{$this->id}" );
		$this->set_attribute( 'min-range', 'min', $this->min_value ?? 0 );
		$this->set_attribute( 'min-range', 'max', $this->max_value ?? 100 );
		$this->set_attribute( 'min-range', 'value', $this->current_min );
		$this->set_attribute( 'min-range', 'step', $step );
		$this->set_attribute( 'min-range', 'tabindex', '0' ); // Safari needs this or focusin event won't fire (@since 1.11)

		if ( ! empty( $label_min ) ) {
			$this->set_attribute( 'min-range', 'aria-labelledby', $min_label_id );
		} else {
			// If no label is set, use aria-label (@since 1.12.2)
			$this->set_attribute( 'min-range', 'aria-label', esc_html__( 'Minimum value', 'bricks' ) );
		}

		echo "<input {$this->render_attributes( 'min-range' )}>";

		$this->set_attribute( 'max-range', 'type', 'range' );
		$this->set_attribute( 'max-range', 'class', 'max' );
		$this->set_attribute( 'max-range', 'name', "form-field-max-{$this->id}" );
		$this->set_attribute( 'max-range', 'min', $this->min_value ?? 0 );
		$this->set_attribute( 'max-range', 'max', $this->max_value ?? 100 );
		$this->set_attribute( 'max-range', 'value', $this->current_max );
		$this->set_attribute( 'max-range', 'step', $step );
		$this->set_attribute( 'max-range', 'tabindex', '0' ); // Safari needs this or focusin event won't fire (@since 1.11)

		if ( ! empty( $label_max ) ) {
			$this->set_attribute( 'max-range', 'aria-labelledby', $max_label_id );
		} else {
			// If no label is set, use aria-label (@since 1.12.2)
			$this->set_attribute( 'max-range', 'aria-label', esc_html__( 'Maximum value', 'bricks' ) );
		}

		echo "<input {$this->render_attributes( 'max-range' )}>";

		echo '</div>';

		// Hardcode HTML
		echo '<div class="value-wrap">';

		$min_value = self::get_range_formatted_value( $this->current_min, $settings );
		$max_value = self::get_range_formatted_value( $this->current_max, $settings );

		$value_wrapper_html  = '<span class="lower">';
		$value_wrapper_html .= ! empty( $label_min ) ? '<span id="' . esc_attr( $min_label_id ) . '" class="label">' . $label_min . '</span>' : '';
		$value_wrapper_html .= '<span class="value">' . $min_value . '</span>';
		$value_wrapper_html .= '</span>';

		$value_wrapper_html .= '<span class="upper">';
		$value_wrapper_html .= ! empty( $label_max ) ? '<span id="' . esc_attr( $max_label_id ) . '" class="label">' . $label_max . '</span>' : '';
		$value_wrapper_html .= '<span class="value">' . $max_value . '</span>';
		$value_wrapper_html .= '</span>';

		echo $value_wrapper_html;

		echo '</div>';

		echo '</div>';
	}

	private function render_input_fields() {
		$settings        = $this->settings;
		$display_mode    = $settings['displayMode'] ?? 'range';
		$label_min       = ! empty( $settings['labelMin'] ) ? $this->render_dynamic_data( $settings['labelMin'] ) : '';
		$label_max       = ! empty( $settings['labelMax'] ) ? $this->render_dynamic_data( $settings['labelMax'] ) : '';
		$placeholder_min = ! empty( $settings['placeholderMin'] ) ? $this->render_dynamic_data( $settings['placeholderMin'] ) : esc_html__( 'Min', 'bricks' );
		$placeholder_max = ! empty( $settings['placeholderMax'] ) ? $this->render_dynamic_data( $settings['placeholderMax'] ) : esc_html__( 'Max', 'bricks' );
		$step            = isset( $settings['step'] ) ? (float) $settings['step'] : 1;

		$this->set_attribute( 'min-max-wrap', 'class', 'min-max-wrap' );

		if ( $display_mode === 'range' ) {
			// Hide input fields if range slider is used
			$this->set_attribute( 'min-max-wrap', 'style', 'display: none;' );
		}

		echo "<div {$this->render_attributes( 'min-max-wrap' )}>";

		// Min. value
		echo '<div class="min-wrap">';

		if ( ! empty( $label_min ) ) {
			echo '<label for="form-field-min-' . esc_attr( $this->id ) . '" class="label">' . $label_min . '</label>';
		} else {
			// If no label is set, use aria-label (@since 1.12.2)
			$this->set_attribute( 'min-input', 'aria-label', esc_html__( 'Minimum value', 'bricks' ) );
		}

		$this->set_attribute( 'min-input', 'type', 'number' );
		$this->set_attribute( 'min-input', 'class', 'min' );
		$this->set_attribute( 'min-input', 'name', "form-field-min-{$this->id}" );
		$this->set_attribute( 'min-input', 'id', "form-field-min-{$this->id}" ); // @since 1.12.2
		$this->set_attribute( 'min-input', 'min', $this->min_value ?? 0 );
		$this->set_attribute( 'min-input', 'max', $this->max_value ?? 100 );
		$this->set_attribute( 'min-input', 'step', $step );
		$this->set_attribute( 'min-input', 'placeholder', $placeholder_min );
		$this->set_attribute( 'min-input', 'value', $this->current_min );
		echo "<input {$this->render_attributes( 'min-input' )}>";

		echo '</div>';

		// Max. value
		echo '<div class="max-wrap">';

		if ( ! empty( $label_max ) ) {
			echo '<label for="form-field-max-' . esc_attr( $this->id ) . '" class="label">' . $label_max . '</label>';
		} else {
			// If no label is set, use aria-label (@since 1.12.2)
			$this->set_attribute( 'max-input', 'aria-label', esc_html__( 'Maximum value', 'bricks' ) );
		}

		$this->set_attribute( 'max-input', 'type', 'number' );
		$this->set_attribute( 'max-input', 'class', 'max' );
		$this->set_attribute( 'max-input', 'name', "form-field-max-{$this->id}" );
		$this->set_attribute( 'max-input', 'id', "form-field-max-{$this->id}" ); // @since 1.12.2
		$this->set_attribute( 'max-input', 'min', $this->min_value ?? 0 );
		$this->set_attribute( 'max-input', 'max', $this->max_value ?? 100 );
		$this->set_attribute( 'max-input', 'step', $step );
		$this->set_attribute( 'max-input', 'placeholder', $placeholder_max );
		$this->set_attribute( 'max-input', 'value', $this->current_max );
		echo "<input {$this->render_attributes( 'max-input' )}>";

		echo '</div>';

		echo '</div>';
	}
}
