<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Filter_Radio extends Filter_Element {
	public $name        = 'filter-radio';
	public $icon        = 'ti-control-record';
	public $filter_type = 'radio';

	public function get_label() {
		return esc_html__( 'Filter', 'bricks' ) . ' - ' . esc_html__( 'Radio', 'bricks' );
	}

	public function set_controls() {
		// SORT / FILTER
		$filter_controls = $this->get_filter_controls();

		if ( ! empty( $filter_controls ) ) {
			// fieldCompareOperator placeholder and default value should be Equal
			$filter_controls['fieldCompareOperator']['placeholder'] = esc_html__( 'Equal', 'bricks' );

			// Remove "IN", "NOT IN", "BETWEEN", "NOT BETWEEN" from select options
			unset( $filter_controls['fieldCompareOperator']['options']['IN'] );
			unset( $filter_controls['fieldCompareOperator']['options']['NOT IN'] );
			unset( $filter_controls['fieldCompareOperator']['options']['BETWEEN'] );
			unset( $filter_controls['fieldCompareOperator']['options']['NOT BETWEEN'] );
			$this->controls = array_merge( $this->controls, $filter_controls );
		}
	}

	/**
	 * Populate options from user input and set to $this->populated_options
	 *
	 * NOTE: Not in 1.11-beta
	 */
	private function populate_user_options() {
		$settings = $this->settings;

		if ( ! isset( $settings['options'] ) ) {
			return;
		}

		$options      = [];
		$user_options = Helpers::parse_textarea_options( $settings['options'] );

		if ( ! empty( $user_options ) ) {
			foreach ( $user_options as $option ) {
				$options[] = [
					'value' => $option,
					'text'  => $option,
					'class' => '',
				];
			}
		}

		$this->populated_options = $options;
	}

	/**
	 * Setup filter
	 *
	 * If is a sort input
	 * - Set sorting options
	 *
	 * If is a per_page input
	 * - Set per_page options
	 *
	 * If is a filter input
	 * - Prepare sources
	 * - Set data_source
	 * - Set final options
	 *
	 * - Set attribute 'data-brx-filter'
	 */
	private function set_as_filter() {
		$settings = $this->settings;

		// Check required filter settings
		if ( empty( $settings['filterQueryId'] ) ) {
			return;
		}

		// Filter or Sort
		$filter_action = $settings['filterAction'] ?? 'filter';

		if ( $filter_action === 'filter' ) {
			// A filter input must have filterSource
			if ( empty( $settings['filterSource'] ) ) {
				return;
			}

			$this->prepare_sources();
			$this->set_data_source();
			$this->set_options_with_count();
		}

		elseif ( $filter_action === 'sort' ) {
			// User wish to use what options as sort options
			$this->setup_sort_options();
		}

		else {
			// User wish to use what options as per_page options
			$this->setup_per_page_options();
		}

		// Insert filter settings as data-brx-filter attribute
		$filter_settings                 = $this->get_common_filter_settings();
		$filter_settings['filterSource'] = $settings['filterSource'] ?? false;

		$this->set_attribute( '_root', 'data-brx-filter', wp_json_encode( $filter_settings ) );
	}

	public function render() {
		$settings      = $this->settings;
		$current_value = isset( $settings['value'] ) ? $settings['value'] : '';

		// Return: No filter source selected
		$filter_action = $this->settings['filterAction'] ?? 'filter';
		if ( $filter_action === 'filter' && empty( $settings['filterSource'] ) ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No filter source selected.', 'bricks' ),
				]
			);
		}

		// In filter AJAX call, filterValue is the current filter value
		if ( isset( $settings['filterValue'] ) ) {
			$current_value = $settings['filterValue'];
		}

		// Escape attributes or it can't match with the options (@since 1.12)
		$current_value = esc_attr( $current_value );

		$this->input_name = $settings['name'] ?? "form-field-{$this->id}";

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
		} else {
			// Not in Beta
			// $this->populate_user_options();
		}

		$display_mode    = $settings['displayMode'] ?? 'default';
		$hide_all_option = isset( $settings['filterHideAllOption'] );

		if ( $display_mode === 'button' ) {
			$this->set_attribute( '_root', 'data-mode', 'button' );
		}

		echo "<ul {$this->render_attributes('_root')}>";

		foreach ( $this->get_populated_options() as $index => $option ) {
			// Skip the "All" option is set (@since 1.11)
			if ( $hide_all_option && isset( $option['is_all'] ) && $option['is_all'] ) {
				continue;
			}

			/**
			 * Skip empty text options
			 *
			 * Each option must have a text. 0 is allowed, otherwise it will conflict with the "All" option / Placeholder option.
			 *
			 * @since 1.12
			 */
			if ( isset( $option['text'] ) && $option['text'] === '' ) {
				continue;
			}

			$option_value    = esc_attr( $option['value'] );
			$option_text     = $this->get_option_text_with_count( $option );
			$option_class    = esc_attr( $option['class'] );
			$option_checked  = self::is_option_value_matched( rawurldecode( $option_value ), $current_value );
			$option_disabled = isset( $option['disabled'] );

			$li_key    = 'li_' . $index;
			$label_key = 'label_' . $index;
			$input_key = 'input_' . $index;
			$span_key  = 'span_' . $index;

			$this->set_attribute( $input_key, 'type', 'radio' );
			$this->set_attribute( $input_key, 'name', $this->input_name );
			$this->set_attribute( $input_key, 'value', $option_value );

			$this->set_attribute( $span_key, 'class', 'brx-option-text' );
			$this->set_attribute( $label_key, 'class', $option_class );

			// Set brx-option-all class for the "All" option (Easier identify for Filter-empty interaction) (@since 2.0)
			if ( isset( $option['is_all'] ) && $option['is_all'] ) {
				$this->set_attribute( $li_key, 'class', 'brx-option-all' );
			}

			if ( $option_checked ) {
				// Set checked attribute
				$this->set_attribute( $input_key, 'checked', 'checked' );

				// Set .brx-option-active classes so user could style easily (avoid using .active as it's too general)
				$this->set_attribute( $li_key, 'class', 'brx-option-active' );
				$this->set_attribute( $label_key, 'class', 'brx-option-active' );
				$this->set_attribute( $span_key, 'class', 'brx-option-active' );
			}

			if ( $option_disabled ) {
				// Set disabled attribute
				$this->set_attribute( $input_key, 'disabled', 'disabled' );
			}

			// Mode: Button
			if ( $display_mode === 'button' ) {
				$this->set_attribute( $span_key, 'class', 'bricks-button' );
				$this->set_attribute( $span_key, 'tabindex', '0' ); // Make it focusable

				if ( isset( $settings['buttonSize'] ) ) {
					$this->set_attribute( $span_key, 'class', $settings['buttonSize'] );
				}

				if ( isset( $settings['buttonStyle'] ) ) {
					if ( isset( $settings['buttonOutline'] ) ) {
						$this->set_attribute( $span_key, 'class', 'outline bricks-color-' . $settings['buttonStyle'] );
					} else {
						$this->set_attribute( $span_key, 'class', 'bricks-background-' . $settings['buttonStyle'] );
					}
				}

				if ( isset( $settings['buttonCircle'] ) ) {
					$this->set_attribute( $span_key, 'class', 'circle' );
				}
			}

			echo "<li {$this->render_attributes( $li_key )}>";
			echo "<label {$this->render_attributes( $label_key )}>";
			echo "<input {$this->render_attributes( $input_key )}>";
			echo "<span {$this->render_attributes( $span_key )}>{$option_text}</span>";
			echo '</label>';
			echo '</li>';
		}

		echo '</ul>';
	}
}
