<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Filter_Checkbox extends Filter_Element {
	public $name        = 'filter-checkbox';
	public $icon        = 'ti-check-box';
	public $filter_type = 'checkbox';

	public function get_label() {
		return esc_html__( 'Filter', 'bricks' ) . ' - ' . esc_html__( 'Checkbox', 'bricks' );
	}

	public function set_controls() {
		$filter_controls = $this->get_filter_controls();

		if ( ! empty( $filter_controls ) ) {
			// fieldCompareOperator only support 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'
			$filter_controls['fieldCompareOperator']['options'] = [
				'IN'          => 'IN',
				'NOT IN'      => 'NOT IN',
				'BETWEEN'     => 'BETWEEN',
				'NOT BETWEEN' => 'NOT BETWEEN',
			];

			$this->controls = array_merge( $this->controls, $filter_controls );
		}
	}

	/**
	 * Populate options from user input and set to $this->populated_options
	 *
	 * Not in Beta
	 */
	private function populate_user_options() {
		$options      = [];
		$user_options = ! empty( $this->settings['options'] ) ? Helpers::parse_textarea_options( $this->settings['options'] ) : false;

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

	public function is_filter_input() {
		return ! empty( $this->settings['filterQueryId'] ) && ! empty( $this->settings['filterSource'] );
	}

	/**
	 * Setup filter
	 */
	private function set_as_filter() {
		$settings = $this->settings;

		// Return: Required filter settings not set
		if ( empty( $settings['filterQueryId'] ) || empty( $settings['filterSource'] ) ) {
			return;
		}

		$this->prepare_sources();
		$this->set_data_source();
		$this->set_options_with_count();

		// Insert filter settings as data-brx-filter attribute
		$filter_settings                 = $this->get_common_filter_settings();
		$filter_settings['filterSource'] = $settings['filterSource'];
		$filter_settings['hierarchy']    = $settings['filterHierarchical'] ?? false;
		$filter_settings['autoCheck']    = $settings['filterAutoCheckChildren'] ?? false;

		$this->set_attribute( '_root', 'data-brx-filter', wp_json_encode( $filter_settings ) );
	}

	public function render() {
		$settings         = $this->settings;
		$this->input_name = $settings['name'] ?? "form-field-{$this->id}";
		$hierarchy        = $settings['filterHierarchical'] ?? false;

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

		// Set current value
		$current_value = isset( $settings['value'] ) && is_array( $settings['value'] ) ? $settings['value'] : [];

		// In filter AJAX call, filterValue is the current filter value
		if ( isset( $settings['filterValue'] ) ) {
			$current_value = $settings['filterValue'];
		}

		// Escape attributes or it can't match with the options (@since 1.12)
		if ( is_array( $current_value ) ) {
			$current_value = array_map( 'esc_attr', $current_value );
		} else {
			$current_value = esc_attr( $current_value );
		}

		// Button mode (@since 1.11)
		$display_mode = $settings['displayMode'] ?? 'default';

		if ( $display_mode === 'button' ) {
			$this->set_attribute( '_root', 'data-mode', 'button' );
		}

		echo "<ul {$this->render_attributes('_root')}>";

		foreach ( $this->get_populated_options() as $index => $option ) {
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

			$option_text     = $this->get_option_text_with_count( $option );
			$option_value    = esc_attr( $option['value'] );
			$option_class    = esc_attr( $option['class'] );
			$option_selected = in_array( rawurldecode( $option_value ), $current_value );
			$option_disabled = isset( $option['disabled'] ) && ! $option_selected;

			$li_key    = 'li_' . $index;
			$label_key = 'label_' . $index;
			$input_key = 'input_' . $index;
			$span_key  = 'span_' . $index;

			$this->set_attribute( $input_key, 'type', 'checkbox' );
			$this->set_attribute( $input_key, 'name', $this->input_name );
			$this->set_attribute( $input_key, 'value', $option_value );

			$this->set_attribute( $span_key, 'class', 'brx-option-text' );
			$this->set_attribute( $label_key, 'class', $option_class );

			if ( $hierarchy ) {
				$depth = $option['depth'] ?? 0;
				$this->set_attribute( $li_key, 'data-depth', $depth );
			}

			if ( $option_selected ) {
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
