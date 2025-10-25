<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Filter_DatePicker extends Filter_Element {
	public $name        = 'filter-datepicker';
	public $icon        = 'ti-calendar';
	public $filter_type = 'datepicker';
	public $min_date    = null; // timestamp
	public $max_date    = null; // timestamp

	public function get_label() {
		return esc_html__( 'Filter', 'bricks' ) . ' - ' . esc_html__( 'Datepicker', 'bricks' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'bricks-filters' );
		wp_enqueue_script( 'bricks-flatpickr' );
		wp_enqueue_style( 'bricks-flatpickr' );

		// Load datepicker localisation
		$l10n = $this->settings['l10n'] ?? '';
		if ( $l10n ) {
			// Hosted locally (@since 2.0)
			wp_enqueue_script( 'bricks-flatpickr-l10n', BRICKS_URL_ASSETS . "js/libs/flatpickr-l10n/$l10n.min.js", [ 'bricks-flatpickr' ], null );
		}
	}

	public function set_control_groups() {
		$this->control_groups['input'] = [
			'title' => esc_html__( 'Input', 'bricks' ),
		];
	}

	public function set_controls() {
		// SORT / FILTER
		$filter_controls = $this->get_filter_controls();

		if ( ! empty( $filter_controls ) ) {
			if ( ! empty( $filter_controls['wpPostField'] ) ) {
				// Rebuild options: Post date and post modified date only (@since 1.11)
				$filter_controls['wpPostField']['options'] = [
					'post_date'     => esc_html__( 'Post date', 'bricks' ),
					'post_modified' => esc_html__( 'Post modified date', 'bricks' ),
				];
			}

			if ( ! empty( $filter_controls['wpUserField'] ) ) {
				// Add user_registered date (@since 1.12)
				$filter_controls['wpUserField']['options']['user_registered'] = esc_html__( 'User registered date', 'bricks' );
			}

			unset( $filter_controls['filterSource']['options']['taxonomy'] );
			unset( $filter_controls['filterTaxonomy'] );
			unset( $filter_controls['filterHierarchical'] );
			unset( $filter_controls['filterTaxonomyHideEmpty'] );
			unset( $filter_controls['filterHideCount'] );
			unset( $filter_controls['filterHideEmpty'] );
			unset( $filter_controls['labelMapping'] );
			unset( $filter_controls['customLabelMapping'] );

			$filter_controls['enableTime'] = [
				'label'    => esc_html__( 'Enable time', 'bricks' ),
				'type'     => 'checkbox',
				'required' => [
					[ 'filterSource', '!=', '' ],
				]
			];

			$filter_controls['isDateRange'] = [
				'label'    => esc_html__( 'Date range', 'bricks' ),
				'type'     => 'checkbox',
				'required' => [
					[ 'filterSource', '!=', '' ],
				]
			];

			/**
			 * Date format
			 *
			 * Different custom field has different way to save value
			 *
			 * @since 1.9.8
			 */
			$filter_controls['dateFormat'] = [
				'label'          => esc_html__( 'Date format', 'bricks' ),
				'type'           => 'text',
				'inline'         => true,
				'hasDynamicData' => false,
				'placeholder'    => get_option( 'date_format' ),
				'description'    => esc_html__( 'Must match with the format saved in database.', 'bricks' ) . ' ' . esc_html__( 'ACF Date picker, for example, uses Ymd.', 'bricks' ),
				'required'       => [
					[ 'filterSource', '!=', '' ],
					[ 'filter_source', '=', 'wpField' ],
				]
			];

			if ( Helpers::enabled_query_filters_integration() ) {
				// Hide dateFormat when using fieldProvider format (@since 1.11.1)
				$filter_controls['dateFormat']['required'][] = [ 'fieldProvider', '=', [ 'none', '' ] ];
			}

			$filter_controls['useMinMax'] = [
				'label'       => esc_html__( 'Min/max date', 'bricks' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Use min/max date from index table.', 'bricks' ),
				'required'    => [
					[ 'filterSource', '!=', '' ],
				]
			];

			$filter_controls['fieldCompareOperator']['required'] = [
				[ 'filterSource', '!=', '' ],
				[ 'isDateRange', '=', '' ],
			];

			$filter_controls['fieldCompareOperator']['options'] = [
				'is'     => '==',
				'before' => '<',
				'after'  => '>',
			];

			$filter_controls['fieldCompareOperator']['placeholder'] = '==';

			$this->controls = array_merge( $this->controls, $filter_controls );
		}

		// INPUT
		$this->controls['placeholder'] = [
			'group'       => 'input',
			'label'       => esc_html__( 'Placeholder', 'bricks' ),
			'type'        => 'text',
			'inline'      => true,
			'placeholder' => esc_html__( 'Date', 'bricks' ),
		];

		$this->controls['placeholderTypography'] = [
			'group' => 'input',
			'label' => esc_html__( 'Placeholder typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '&::placeholder',
				],
			],
		];

		$this->controls['l10n'] = [
			'group'          => 'input',
			'label'          => esc_html__( 'Language', 'bricks' ),
			'type'           => 'text',
			'inline'         => true,
			'hasDynamicData' => false,
			'description'    => '<a href="https://github.com/flatpickr/flatpickr/tree/master/src/l10n" target="_blank">' . esc_html__( 'Language codes', 'bricks' ) . '</a> (de, es, fr, etc.)',
		];
	}

	public function is_filter_input() {
		return ! empty( $this->settings['filterQueryId'] ) && ! empty( $this->settings['filterSource'] );
	}

	/**
	 * Setup filter
	 * - Prepare sources
	 * - Get min/max date
	 * - Set data-brx-filter attribute
	 */
	private function set_as_filter() {
		$settings = $this->settings;

		// Check required filter settings
		if ( empty( $settings['filterQueryId'] ) || empty( $settings['filterSource'] ) ) {
			return;
		}

		$this->prepare_sources();

		/**
		 * Get min/max date from $this->choices_source
		 * not get from $this->filtered_choices because it will be awkward if selected date is not in the choices.
		 */
		if ( ! empty( $this->choices_source ) && isset( $settings['useMinMax'] ) ) {
			// date string format:YYYY-MM-DD HH:MM:SS, in filter_value key
			// Loop through choices_source to get min/max date
			foreach ( $this->choices_source as $choice ) {
				$choice_date = $choice['filter_value'] ?? false;

				if ( ! $choice_date ) {
					continue;
				}

				// Convert to timestamp
				$choice_date = strtotime( $choice_date );

				if ( ! $choice_date ) {
					continue;
				}

				// Set min/max date
				if ( ! $this->min_date || $choice_date < $this->min_date ) {
					$this->min_date = $choice_date;
				}

				if ( ! $this->max_date || $choice_date > $this->max_date ) {
					$this->max_date = $choice_date;
				}
			}
		}

		$field_type = $settings['sourceFieldType'] ?? 'post';
		$field_key  = false;

		// Build $field_info to be used by the JS filter in frontend
		if ( $settings['filterSource'] === 'wpField' ) {
			switch ( $field_type ) {
				case 'post':
					$field_key = $settings['wpPostField'] ?? false;
					break;

				case 'user':
					$field_key = $settings['wpUserField'] ?? false;
					break;

				// case 'term':
				// $field_key = $settings['wpTermField'] ?? false;
				// break;
			}

			if ( ! $field_key ) {
				return $this->render_element_placeholder(
					[
						'title' => esc_html__( 'Required', 'bricks' ) . ': ' . esc_html__( 'Field', 'bricks' )
					]
				);
			}
		}

		elseif ( $settings['filterSource'] === 'customField' ) {
			$meta_key = $settings['customFieldKey'] ?? false;

			if ( ! $meta_key ) {
				return $this->render_element_placeholder(
					[
						'title' => esc_html__( 'Required', 'bricks' ) . ': ' . esc_html__( 'Meta key', 'bricks' )
					]
				);
			}
		}

		// Insert filter settings as data-brx-filter attribute
		$filter_settings                 = $this->get_common_filter_settings();
		$filter_settings['filterSource'] = $settings['filterSource'];

		$this->set_attribute( '_root', 'data-brx-filter', wp_json_encode( $filter_settings ) );
	}

	public function render() {
		$settings         = $this->settings;
		$placeholder      = ! empty( $settings['placeholder'] ) ? $this->render_dynamic_data( $settings['placeholder'] ) : esc_html__( 'Date', 'bricks' );
		$this->input_name = $settings['name'] ?? "form-field-{$this->id}";
		$query_id         = $settings['filterQueryId'] ?? false;

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

		// Datepicker options
		$time_24h = get_option( 'time_format' );
		$time_24h = strpos( $time_24h, 'H' ) !== false || strpos( $time_24h, 'G' ) !== false;

		$date_format = $this->get_date_format();

		$mode = isset( $settings['isDateRange'] ) ? 'range' : 'single';

		$datepicker_options = [
			'enableTime' => isset( $settings['enableTime'] ),
			'minTime'    => '',
			'maxTime'    => '',
			'altInput'   => true,
			'altFormat'  => $date_format,
			'dateFormat' => $date_format,
			'time_24hr'  => $time_24h,
			'mode'       => $mode, // single, range
		];

		if ( isset( $settings['useMinMax'] ) ) {
			if ( $this->min_date ) {
				// convert to date string following date format
				$min_date                      = date( $date_format, $this->min_date );
				$datepicker_options['minDate'] = $min_date;
			}

			if ( $this->max_date ) {
				// convert to date string following date format
				$max_date = date( $date_format, $this->max_date );
				// if time is enabled, remove the time part or user unable to select the time freely
				if ( isset( $settings['enableTime'] ) ) {
					$max_date = explode( ' ', $max_date )[0];
				}
				$datepicker_options['maxDate'] = $max_date;
			}
		}

		// Localization
		if ( ! empty( $settings['l10n'] ) ) {
			$datepicker_options['locale'] = $settings['l10n'];
		}

		// In filter AJAX call, filterValue is the current filter value, previously use 'value' attribute, now use flatpickr defaultDate
		if ( isset( $settings['filterValue'] ) && $query_id ) {
			// Get active filter for this element
			$active_filter = Query_Filters::get_active_filter_by_element_id( $this->id, $query_id );

			if ( $active_filter && is_array( $active_filter ) && isset( $active_filter['parsed_dates'] ) && ! empty( $active_filter['parsed_dates'] ) ) {
				$default_date = [];
				foreach ( $active_filter['parsed_dates'] as $parsed_date ) {
					// Use the object if it's a valid DateTime object
					$date_object = $parsed_date['object'] ?? false;
					if ( $date_object && is_a( $date_object, 'DateTime' ) ) {
						$default_date[] = $date_object->format( $date_format );
					}
				}
				if ( ! empty( $default_date ) ) {
					$datepicker_options['defaultDate'] = $default_date;
				}
			}
		}

		// Undocumented filter
		$datepicker_options = apply_filters( 'bricks/filter-element/datepicker_options', $datepicker_options, $this );

		$this->set_attribute( '_root', 'data-bricks-datepicker-options', wp_json_encode( $datepicker_options ) );
		$this->set_attribute( '_root', 'name', $this->input_name );
		$this->set_attribute( '_root', 'placeholder', $placeholder );
		$this->set_attribute( '_root', 'type', 'text' );
		$this->set_attribute( '_root', 'autocomplete', 'off' );
		$this->set_attribute( '_root', 'aria-label', $placeholder );

		echo "<input {$this->render_attributes('_root')}>";
	}

	private function get_date_format() {
		$settings = $this->settings;

		// User defined date format (@since 1.9.8)
		$date_format   = $settings['dateFormat'] ?? get_option( 'date_format' );
		$provider      = $settings['fieldProvider'] ?? 'none';
		$filter_source = $settings['filterSource'] ?? false;

		// Date format for WP field (@since 1.12)
		if ( $filter_source === 'wpField' ) {
			$field_type = $settings['sourceFieldType'] ?? 'post';
			switch ( $field_type ) {
				case 'post':
				case 'user':
					$date_format = 'Y-m-d';
					break;
			}
		}

		if ( isset( $settings['enableTime'] ) ) {
			$date_format .= ' H:i';
		}

		// @since 1.11.1
		return apply_filters( 'bricks/filter_element/datepicker_date_format', $date_format, $provider, $this );
	}
}
