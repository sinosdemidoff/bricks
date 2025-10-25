<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Filter_Active_Filters extends Filter_Element {
	public $name        = 'filter-active-filters';
	public $icon        = 'ti-filter';
	public $filter_type = 'active-filters';

	public function get_label() {
		return esc_html__( 'Filter', 'bricks' ) . ' - ' . esc_html__( 'Active Filters', 'bricks' );
	}

	public function set_controls() {
		// SORT / FILTER
		$filter_controls = $this->get_filter_controls();

		if ( ! empty( $filter_controls ) ) {
			unset( $filter_controls['filterApplyOn'] );
			unset( $filter_controls['filterNiceName'] );
			$this->controls = array_merge( $this->controls, $filter_controls );
		}

		$this->controls['excludeIds'] = [
			'type'           => 'text',
			'label'          => esc_html__( 'Exclude filter IDs', 'bricks' ),
			'description'    => esc_html__( 'Enter Bricks IDs, separated by comma, of filter elements to exclude.', 'bricks' ),
			'placeholder'    => 'q1w2e3,mn9456',
			'required'       => [ 'filterQueryId', '!=', '' ],
			'hasDynamicData' => false,
		];

		// BUTTON
		$this->controls['buttonSep'] = [
			'label' => esc_html__( 'Button', 'bricks' ),
			'type'  => 'separator',
		];

		$this->controls['buttonPadding'] = [
			'label' => esc_html__( 'Padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'padding',
					'selector' => '.bricks-button',
				],
			],
		];

		$this->controls['buttonGap'] = [
			'label' => esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => '',
				],
			],
		];

		$this->controls['buttonSize'] = [
			'label'       => esc_html__( 'Size', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['buttonSizes'],
			'inline'      => true,
			'placeholder' => esc_html__( 'Default', 'bricks' ),
		];

		$this->controls['buttonStyle'] = [
			'label'       => esc_html__( 'Style', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['styles'],
			'inline'      => true,
			'placeholder' => esc_html__( 'None', 'bricks' ),
		];

		$this->controls['buttonCircle'] = [
			'label' => esc_html__( 'Circle', 'bricks' ),
			'type'  => 'checkbox',
		];

		$this->controls['buttonOutline'] = [
			'label' => esc_html__( 'Outline', 'bricks' ),
			'type'  => 'checkbox',
		];

		$this->controls['buttonBackgroundColor'] = [
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.bricks-button',
				],
			],
		];

		$this->controls['buttonBorder'] = [
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border-color',
					'selector' => '.bricks-button',
				],
			],
		];

		$this->controls['buttonTypography'] = [
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.bricks-button',
				],
			],
		];

		// ICON
		$this->controls['iconSeparator'] = [
			'label' => esc_html__( 'Icon', 'bricks' ),
			'type'  => 'separator',
		];

		$this->controls['icon'] = [
			'label' => esc_html__( 'Icon', 'bricks' ),
			'type'  => 'icon',
		];

		$this->controls['iconColor'] = [
			'label'    => esc_html__( 'Color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'color',
					'selector' => '.bricks-button i',
				],
				[
					'property' => 'fill',
					'selector' => '.bricks-button svg path',
				],
			],
			'required' => [ 'icon.icon', '!=', '' ],
		];

		$this->controls['iconSize'] = [
			'label'    => esc_html__( 'Size', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'property' => 'font-size',
					'selector' => '.bricks-button .icon',
				],
			],
			'required' => [ 'icon.icon', '!=', '' ],
		];

		$this->controls['iconGap'] = [
			'label'    => esc_html__( 'Gap', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'property' => 'gap',
					'selector' => '.bricks-button',
				],
			],
			'required' => [ 'icon', '!=', '' ],
		];

		$this->controls['iconPosition'] = [
			'label'       => esc_html__( 'Position', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['iconPosition'],
			'inline'      => true,
			'placeholder' => esc_html__( 'Right', 'bricks' ),
			'required'    => [ 'icon', '!=', '' ],
		];
	}

	private function set_as_filter() {
		$settings = $this->settings;

		// Insert filter settings as data-brx-filter attribute
		$filter_settings = $this->get_common_filter_settings();
		$this->set_attribute( '_root', 'data-brx-filter', wp_json_encode( $filter_settings ) );
	}

	public function render() {
		$settings = $this->settings;

		if ( $this->is_filter_input() ) {
			$this->set_as_filter();
		}

		$target_query_id = $settings['filterQueryId'] ?? false;

		// Return: No target query ID selected
		if ( ! $target_query_id ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No target query selected.', 'bricks' ),
				]
			);
		}

		$exclude_ids = $settings['excludeIds'] ?? false;

		if ( $exclude_ids ) {
			$exclude_ids = array_map(
				function( $id ) {
					// remove whitespace
					$id = trim( $id );
					// remove brxe- prefix if exists
					$id = str_replace( 'brxe-', '', $id );
					// remove hash if exists
					return str_replace( '#', '', $id );
				},
				explode( ',', $exclude_ids )
			);
		}

		$active_filters = Query_Filters::$active_filters[ $target_query_id ] ?? [];

		$this->set_attribute( '_remove', 'class', 'bricks-button' );

		$items = [];

		// In builder preview, populate a fake item for testing
		if (
			bricks_is_builder_main() ||
			bricks_is_builder_iframe() ||
			bricks_is_builder_call() ||
			isset( $_GET['bricks_preview'] )
		) {
			$items = [
				[
					'filter_id' => 'fake-filter-id',
					'value'     => 'fake-value',
					'label'     => esc_html__( 'Active filter', 'bricks' ),
				],
				[
					'filter_id' => 'fake-filter-id-2',
					'value'     => 'fake-value-2',
					'label'     => esc_html__( 'Active filter', 'bricks' ) . ' (2)',
				],
			];
		}

		// Actual frontend, generate items for active filters
		elseif ( ! empty( $active_filters ) ) {
			$generated_urls = [];
			foreach ( $active_filters as $filter_info ) {
				$value         = $filter_info['value'];
				$filter_id     = $filter_info['filter_id'];
				$instance_name = $filter_info['instance_name'];
				$url_param     = $filter_info['url_param'];

				// Skip excluded filter IDs
				if ( $exclude_ids && in_array( $filter_id, $exclude_ids ) ) {
					continue;
				}

				// Skip pagination filter (no need to show it in active filters)
				if ( $instance_name === 'pagination' ) {
					continue;
				}

				// Skip if the filter for this url param is already generated (@since 1.12)
				if ( $url_param && in_array( $url_param, $generated_urls ) ) {
					continue;
				}

				$choices = Query_Filters::get_filtered_data_from_index( $filter_id, Query_Filters::get_filter_object_ids( $target_query_id, 'original' ) );

				if ( is_array( $value ) && $instance_name === 'filter-checkbox' ) {
					// Checkbox filter shows 1 button for each selected value
					foreach ( $value as $val ) {
						$item = $this->get_item( $filter_id, $val, $choices, $filter_info );
						if ( is_array( $item ) ) {
							$items[] = $item;
						}
					}
				} else {
					// Other filter types show 1 button for each filter
					$item = $this->get_item( $filter_id, $value, $choices, $filter_info );
					if ( is_array( $item ) ) {
						$items[] = $item;
					}
				}

				// Add url param to generated urls
				$generated_urls[] = $url_param;
			}
		}

		// Button classes
		$button_classes = [ 'bricks-button' ];

		if ( isset( $settings['buttonSize'] ) ) {
			$button_classes[] = $settings['buttonSize'];
		}

		if ( isset( $settings['buttonOutline'] ) ) {
			$button_classes[] = 'outline';
		}

		if ( isset( $settings['buttonStyle'] ) ) {
			if ( isset( $settings['buttonOutline'] ) ) {
				$button_classes[] = "bricks-color-{$settings['buttonStyle']}";
			} else {
				$button_classes[] = "bricks-background-{$settings['buttonStyle']}";
			}
		}

		if ( isset( $settings['buttonCircle'] ) ) {
			$button_classes[] = 'circle';
		}

		// Icon
		$icon          = ! empty( $settings['icon'] ) ? self::render_icon( $settings['icon'], [ 'icon' ] ) : false;
		$icon_position = ! empty( $settings['iconPosition'] ) ? $settings['iconPosition'] : 'right';

		echo "<ul {$this->render_attributes('_root')}>";

		foreach ( $items as $k => $item ) {
			$filter_id  = esc_attr( $item['filter_id'] );
			$value      = esc_attr( $item['value'] );
			$label      = esc_attr( $item['label'] );
			$title      = isset( $item['title'] ) ? esc_attr( $item['title'] ) : '';
			$unique_key = $filter_id . '-' . $k;
			$url_param  = isset( $item['url_param'] ) ? sanitize_key( $item['url_param'] ) : '';

			$this->set_attribute( "item_button_$unique_key", 'aria-label', esc_html__( 'Clear filter', 'bricks' ) );
			$this->set_attribute( "item_button_$unique_key", 'class', $button_classes );
			$this->set_attribute( "item_button_$unique_key", 'data-filter-id', $filter_id );
			$this->set_attribute( "item_button_$unique_key", 'data-filter-value', $value );
			$this->set_attribute( "item_button_$unique_key", 'data-filter-url-param', $url_param );

			if ( $title ) {
				$this->set_attribute( "item_button_$unique_key", 'title', $item['title'] );
			}

			$button_inner = $label;

			if ( $icon ) {
				$button_inner = $icon_position === 'left' ? $icon . $button_inner : $button_inner . $icon;
			}

			echo "<li {$this->render_attributes( 'item_'. $unique_key )}>";

			echo "<button {$this->render_attributes( 'item_button_'. $unique_key )}>{$button_inner}</button>";

			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Generate items for active filters
	 *
	 * @return array
	 */
	private function get_item( $filter_id, $value, $choices, $filter_info ) {
		$settings      = $filter_info['settings'];
		$instance_name = $filter_info['instance_name'];
		$url_param     = $filter_info['url_param'];
		$filter_action = $settings['filterAction'] ?? 'filter';
		$label         = '';
		$title         = '';

		if ( $filter_action === 'filter' ) {
			// Handle range filter - Use labelMin and labelMax
			if ( in_array( $instance_name, [ 'filter-range' ] ) ) {
				$min_label       = $settings['labelMin'] ?? '';
				$max_label       = $settings['labelMax'] ?? '';
				$label_direction = $settings['labelDirection'] ?? false; // @since 1.12.2

				if ( is_array( $value ) ) {
					$min_label_value = self::get_range_formatted_value( $value[0], $settings );
					$max_label_value = self::get_range_formatted_value( $value[1], $settings );

					// Set label direction based on the filter setting (@since 1.12.2)
					if ( $label_direction === 'row-reverse' ) {
						$label = "{$min_label_value} {$min_label} - {$max_label_value} {$max_label}";
					} else {
						$label = "{$min_label} {$min_label_value} - {$max_label} {$max_label_value}";
					}

					$value = $value[0]; // Change the value to min value only - no array value
				} else {
					// Thousand separator
					$label_value = self::get_range_formatted_value( $value, $settings );

					// Set label direction based on the filter setting (@since 1.12.2)
					$label = $label_direction === 'row-reverse' ? "{$label_value} {$min_label}" : "{$min_label} {$label_value}";
				}
			}

			// Handle datepicker filter
			elseif ( in_array( $instance_name, [ 'filter-datepicker' ] ) ) {
				// Set default label, cannot retrieve from db, must be escaped as it's user input (@since 2.0)
				$label       = esc_attr( $value );
				$placeholder = ! empty( $settings['placeholder'] ) ? $this->render_dynamic_data( $settings['placeholder'] ) : '';
				if ( ! empty( $placeholder ) ) {
					$label = "{$placeholder} {$value}";
				}
			}

			// Handle other filter types
			else {

				// Try to use filter_value_display from index table as default label
				$data_matched_value = array_filter(
					$choices,
					function( $choice ) use ( $value ) {
						// DB value must use rawurldecode first when comparing with user input value (@since 1.12)
						return self::is_option_value_matched( esc_attr( rawurldecode( $choice['filter_value'] ) ), esc_attr( $value ) );
					}
				);

				// Get the first data matched value
				$data_matched_value = array_shift( $data_matched_value );
				// Set default label, use value from db, otherwise must be escaped as it's user input
				$label = $data_matched_value['filter_value_display'] ?? esc_attr( $value );
				// Always use value from db so frontend JS can deselect the correct option when clicking on the button (@since 1.12)
				$value = $data_matched_value['filter_value'] ?? $value;

				// Handle other filter types with filterSource (Search filter has no filterSource)
				if ( ( ! empty( $settings['filterSource'] ) ) ) {
					switch ( $settings['filterSource'] ) {
						case 'taxonomy':
							// Use default filter_value_display as label
							break;

						case 'wpField':
						case 'customField':
						case 'wcField':
							$label_mapping        = $settings['labelMapping'] ?? 'value';
							$custom_label_mapping = $settings['customLabelMapping'] ?? [];

							// Use custom label mapping if set
							if ( $label_mapping === 'custom' && ! empty( $custom_label_mapping ) ) {
								// Find the label from the custom_label_mapping array
								$selected_label_mapping = array_filter(
									$custom_label_mapping,
									function( $mapping ) use ( $value ) {
										return self::is_option_value_matched( $mapping['optionMetaValue'], $value );
									}
								);

								$selected_label_mapping = array_shift( $selected_label_mapping );

								$label = $selected_label_mapping['optionLabel'] ?? $value;
							}

							break;
					}
				}
			}
		}

		// Sort
		elseif ( $filter_action === 'sort' ) {
			// Only filter-select and filter-radio has sort options
			if ( ! in_array( $instance_name, [ 'filter-select', 'filter-radio' ], true ) ) {
				return false;
			}

			// sort_option_info is the option of the selected value defined in the builder. This info populated and saved in Query_Filters::$active_filters when executing Query_Filters::generate_query_vars_from_active_filters() function
			$sort_info = ! empty( $filter_info['sort_option_info'] ) ? $filter_info['sort_option_info'] : false;

			// Ensure sort options and sort info are available
			if ( $sort_info ) {
				$label = $sort_info['optionLabel'] ?? $value;
			}
		}

		// Per page
		else {
			// Only filter-select and filter-radio has sort options
			if ( ! in_array( $instance_name, [ 'filter-select', 'filter-radio' ], true ) ) {
				return false;
			}

			// No per page options, the value is not matched with any per page options
			if ( empty( $filter_info['per_page_options'] ) || ! in_array( $value, $filter_info['per_page_options'] ) ) {
				return false;
			}

			$label = (int) $value;
			$value = (int) $value;
		}

		// Add active filter prefix, suffix or title attribute
		if ( isset( $settings['filterActivePrefix'] ) ) {
			$label = esc_attr( $this->render_dynamic_data( $settings['filterActivePrefix'] ) ) . $label;
		}

		if ( isset( $settings['filterActiveSuffix'] ) ) {
			$label = $label . esc_attr( $this->render_dynamic_data( $settings['filterActiveSuffix'] ) );
		}

		if ( isset( $settings['filterActiveTitle'] ) ) {
			$title = esc_attr( $this->render_dynamic_data( $settings['filterActiveTitle'] ) );
		}

		return [
			'filter_id' => $filter_id,
			'value'     => $value,
			'label'     => $label,
			'title'     => $title,
			'url_param' => $url_param,
		];
	}
}
