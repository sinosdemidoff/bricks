<?php
namespace Bricks\Integrations\Query_Filters;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Field_Metabox {
	protected $name          = 'Meta Box';
	protected $provider_key  = 'metabox';
	public static $is_active = false;
	private $metabox_dd_tags = [];

	public function __construct() {
		if ( ! class_exists( 'RWMB_Loader' ) ) {
			return;
		}

		self::$is_active = true;
		// After provider tags are registered, before query-filters set active_filters_query_vars (query-filters.php)
		// Use bricks/dynamic_data/tags_registered hook (#86c3xg01h; @since 2.0)
		add_action( 'bricks/dynamic_data/tags_registered', [ $this, 'init' ] );

		add_action( 'bricks/query_filters/index_post/before', [ $this, 'maybe_register_dd_provider' ], 10 );
		add_action( 'bricks/query_filters/index_user/before', [ $this, 'maybe_register_dd_provider' ], 10 );

		add_filter( 'bricks/query_filters/index_args', [ $this, 'index_args' ], 10, 3 );

		add_filter( 'bricks/query_filters/index_post/meta_exists', [ $this, 'index_post_meta_exists' ], 10, 4 );
		add_filter( 'bricks/query_filters/index_user/meta_exists', [ $this, 'index_user_meta_exists' ], 10, 4 );

		add_filter( 'bricks/query_filters/custom_field_index_rows', [ $this, 'custom_field_index_rows' ], 10, 5 );

		add_action( 'bricks/filter_element/before_set_data_source_from_custom_field', [ $this, 'modify_custom_field_choices' ] );

		add_filter( 'bricks/query_filters/custom_field_meta_query', [ $this, 'custom_field_meta_query' ], 10, 4 );

		add_filter( 'bricks/query_filters/range_custom_field_meta_query', [ $this, 'range_custom_field_meta_query' ], 10, 4 );

		add_filter( 'bricks/query_filters/datepicker_custom_field_meta_query', [ $this, 'datepicker_custom_field_meta_query' ], 10, 4 );

		add_filter( 'bricks/filter_element/datepicker_date_format', [ $this, 'datepicker_date_format' ], 10, 3 );
	}

	/**
	 * Retrieve all registered tags from Meta Box provider
	 */
	public function init() {
		$metabox_provider = \Bricks\Integrations\Dynamic_Data\Providers::get_registered_provider( $this->provider_key );
		if ( $metabox_provider ) {
			$this->metabox_dd_tags = $metabox_provider->get_tags();
		}
	}

	/**
	 * Get the name of the provider
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Check if the provider is active
	 */
	public static function is_active() {
		return self::$is_active;
	}

	/**
	 * Manually register the provider if it's not registered (due to is_admin() check in providers.php)
	 */
	public function maybe_register_dd_provider( $object_id ) {
		// Check if provider is registered, it might not be registered due to is_admin() check
		$metabox_provider = \Bricks\Integrations\Dynamic_Data\Providers::get_registered_provider( $this->provider_key );
		if ( is_null( $metabox_provider ) && empty( $this->metabox_dd_tags ) ) {
			$classname = 'Bricks\Integrations\Dynamic_Data\Providers\Provider_' . ucfirst( $this->provider_key );

			if ( ! class_exists( $classname ) ) {
				return;
			}

			// Try manually init the provider
			if ( $classname::load_me() ) {
				$metabox_provider      = new $classname( $this->provider_key );
				$this->metabox_dd_tags = $metabox_provider->get_tags();
			}
		}
	}

	/**
	 * Modify the actual meta key for custom fields
	 * When user hit on Regenerate Index button
	 * Otherwise the post with the actual meta key will not be indexed
	 *
	 * @return array
	 */
	public function index_args( $args, $filter_source, $filter_settings ) {
		$provider = $filter_settings['fieldProvider'] ?? 'none';

		if ( $provider !== $this->provider_key ) {
			return $args;
		}

		// Modify the actual meta key for custom fields
		if ( $filter_source === 'customField' ) {
			$meta_key = $filter_settings['customFieldKey'] ?? false;
			if ( ! $meta_key ) {
				return $args;
			}

			// Get the real meta key
			$meta_key = $this->get_meta_key_by_dd_tag( $meta_key );

			// TODO: For 'taxonomy' field type, no meta key will be saved. Need to handle this case, currently all taxonomy fields will be skipped
			$args['meta_query'] = [
				[
					'key'     => $meta_key,
					'compare' => 'EXISTS'
				],
			];
		}

		return $args;
	}

	/**
	 * Decide whether to index the post based on the meta key
	 * Index the post if the meta key exists
	 *
	 * @return bool
	 */
	public function index_post_meta_exists( $index, $post_id, $meta_key, $provider ) {
		if ( $provider !== $this->provider_key ) {
			return $index;
		}

		// Get the real meta key
		$meta_key = $this->get_meta_key_by_dd_tag( $meta_key );

		// Check if the meta key exists
		return metadata_exists( 'post', $post_id, $meta_key );
	}

	/**
	 * Decide whether to index the user based on the meta key
	 * Index the user if the meta key exists
	 *
	 * @return bool
	 */
	public function index_user_meta_exists( $index, $user_id, $meta_key, $provider ) {
		if ( $provider !== $this->provider_key ) {
			return $index;
		}

		// Get the real meta key
		$meta_key = $this->get_meta_key_by_dd_tag( $meta_key );

		// Check if the meta key exists
		return metadata_exists( 'user', $user_id, $meta_key );
	}

	/**
	 * Modify the index value based on the field type
	 * Generate index rows for a given custom field
	 *
	 * @return array
	 */
	public function custom_field_index_rows( $rows, $object_id, $meta_key, $provider, $object_type ) {
		if ( $provider !== $this->provider_key ) {
			return $rows;
		}

		// Get the real meta key
		$dd_tag         = $meta_key;
		$meta_key       = $this->get_meta_key_by_dd_tag( $meta_key );
		$field_info     = $this->get_field_settings_from_dd_provider( $dd_tag );
		$is_group_field = isset( $field_info['parent']['id'] );

		$field_settings              = $field_info['field'] ?? [];
		$field_settings['brx_label'] = [];

		$metabox_arg = '';

		if ( in_array( $object_type, [ 'term', 'user' ], true ) ) {
			$metabox_arg = [
				'object_type' => $object_type,
			];
		}

		// Use the actual meta key to get the value
		$mb_value = rwmb_get_value( $meta_key, $metabox_arg, $object_id );

		if ( empty( $field_settings ) ) {
			return $rows;
		}

		// Handle fields in a group
		if ( $is_group_field && is_array( $mb_value ) ) {
			// Try to find the value from $mb_value based on the field ID, field ID will be the key
			$field_id  = $field_settings['id'];
			$new_value = [];
			foreach ( $mb_value as $group ) {
				if ( isset( $group[ $field_id ] ) ) {
					$new_value[] = $group[ $field_id ];
				}
			}

			// If no value found, try to get the value directly by using the field ID as the key
			if ( empty( $new_value ) && isset( $mb_value[ $field_id ] ) ) {
				$new_value = $mb_value[ $field_id ];
			}

			$mb_value = $new_value;
		}

		$mb_field_type = $field_settings['type'] ?? 'text';

		switch ( $mb_field_type ) {
			case 'radio':
			case 'select':
			case 'checkbox_list':
			case 'select_advanced':
			case 'autocomplete':
				break;

			case 'post':
			case 'taxonomy':
			case 'taxonomy_advanced':
			case 'user':
				// Generate label for post, taxonomy, user
				$mb_value = is_array( $mb_value ) ? $mb_value : [ $mb_value ];

				// Generate label for post, taxonomy, user
				foreach ( $mb_value as $key => $value ) {
					$label = '';
					switch ( $mb_field_type ) {
						case 'post':
							if ( is_a( $value, 'WP_Post' ) ) {
								$post  = $value;
								$value = $post->ID;
								$label = $post->post_title;
							}

							else {
								$post  = get_post( $value );
								$label = is_a( $post, 'WP_Post' ) ? $post->post_title : '';
							}

							break;

						case 'taxonomy':
						case 'taxonomy_advanced':
							if ( is_a( $value, 'WP_Term' ) ) {
								$term  = $value;
								$value = $term->term_id;
								$label = $term->name;
							}

							else {
								$term  = get_term( $value );
								$label = ! is_wp_error( $term ) && is_a( $term, 'WP_Term' ) ? $term->name : '';
							}

							break;

						case 'user':
							if ( is_a( $value, 'WP_User' ) ) {
								$user  = $value;
								$value = $user->ID;
								$label = $user->display_name;
							}

							else {
								$user  = get_user_by( 'ID', $value );
								$label = is_a( $user, 'WP_User' ) ? $user->display_name : '';
							}

							break;
					}

					// Update $mb_value with the actual value (ID)
					$mb_value[ $key ]                      = $value;
					$field_settings['brx_label'][ $value ] = $label;
				}

				break;

			case 'date':
			case 'datetime':
			case 'time':
				if ( ! empty( $mb_value ) ) {

					// STEP: Force $mb_value to be an array
					$mb_value = empty( $field_settings['clone'] ) ? [ $mb_value ] : $mb_value;

					$use_timestamp = ! empty( $field_settings['timestamp'] );
					// Default date time format in metabox
					$date_format = 'Y-m-d';
					$time_format = 'H:i';

					switch ( $mb_field_type ) {
						case 'date':
							$format = $date_format;
							break;
						case 'datetime':
							$format = $date_format . ' ' . $time_format;
							break;
						case 'time':
							$format = $time_format;
							break;
					}

					// NOTE: Overwrite the format if not using timestamp and save_format is set (Metabox not follow save_format if it's a group subfield)
					if ( ! $use_timestamp && ! $is_group_field && ! empty( $field_settings['save_format'] ) ) {
						$format = $field_settings['save_format'];
					}

					$db_value  = [];
					$db_format = $mb_field_type == 'date' ? 'Y-m-d' : 'Y-m-d H:i';
					// STEP: Try convert the $value to DateTime object in UTC and save it to $db_value
					foreach ( $mb_value as $key => $val ) {
						// If this is a group sub-field and saved as timestamp, the $row is an array, pick the timestamp value
						$date_value = $use_timestamp && is_array( $val ) && isset( $val['timestamp'] ) ? $val['timestamp'] : $val;

						$date_value = $use_timestamp ? date_i18n( $format, $date_value ) : $date_value;

						// Replace original $mb_value with $date_value as well for backward compatibility (in case the createFromFormat() failed)
						$mb_value[ $key ] = $date_value;

						$date = \DateTime::createFromFormat( $format, $date_value );

						// Skip if the conversion failed
						if ( ! $date instanceof \DateTime ) {
							continue;
						}

						// Store converted date in DB format
						$db_value[ $key ] = $date->format( $db_format );
					}

					// Use the converted value
					$mb_value = ! empty( $db_value ) ? $db_value : $mb_value;
				}
				break;
		}

		// Retrieve label function
		$get_label = function( $value, $field_settings ) {
			$label = $value;

			if ( ! is_array( $value ) ) {
				// Use label if available
				if ( isset( $field_settings['options'][ $value ] ) ) {
					$label = $field_settings['options'][ $value ];
				}

				// Use custom label if available
				if ( isset( $field_settings['brx_label'] ) && isset( $field_settings['brx_label'][ $value ] ) ) {
					$label = $field_settings['brx_label'][ $value ];
				}
			}

			return $label;
		};

		$final_values = is_array( $mb_value ) ? $mb_value : [ $mb_value ];

		// Generate rows
		foreach ( $final_values as $value ) {
			$rows[] = [
				'filter_id'            => '',
				'object_id'            => $object_id,
				'object_type'          => $object_type,
				'filter_value'         => $value,
				'filter_value_display' => $get_label( $value, $field_settings ),
				'filter_value_id'      => 0,
				'filter_value_parent'  => 0,
			];
		}

		return $rows;
	}

	/**
	 * Modify the custom field choices following the Metabox field choices
	 *
	 * Direct update element->choices_source
	 */
	public function modify_custom_field_choices( $element ) {
		$settings         = $element->settings;
		$custom_field_key = $settings['customFieldKey'] ?? false;
		$provider         = $settings['fieldProvider'] ?? 'none';

		if ( ! $custom_field_key || $provider !== $this->provider_key ) {
			return;
		}

		$field_settings = $this->get_field_settings_from_dd_provider( $custom_field_key, 'field' );
		$mb_choices     = $field_settings['options'] ?? [];
		$mb_field_type  = $field_settings['type'] ?? 'text';

		// Taxonomy field can have choices from the terms, build the choices from the terms
		if ( in_array( $mb_field_type, [ 'taxonomy', 'taxonomy_advanced' ], true ) ) {
			$taxonomy = $field_settings['taxonomy'] ?? '';
			if ( ! $taxonomy ) {
				return;
			}

			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				]
			);

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$mb_choices = [];
				foreach ( $terms as $term ) {
					$mb_choices[ $term->term_id ] = $term->name;
				}
			}
		}

		// Return if no choices
		if ( empty( $mb_choices ) ) {
			return;
		}

		// Modify the choices source
		$temp_choices = [];
		$ori_choices  = $element->choices_source;
		foreach ( $mb_choices as $mb_value => $mb_label ) {
			$matched_choice = array_filter(
				$ori_choices,
				function( $choice ) use ( $mb_value ) {
					return isset( $choice['filter_value'] ) && \Bricks\Filter_Element::is_option_value_matched( $choice['filter_value'], $mb_value );
				}
			);

			$matched_choice = array_values( $matched_choice );

			$temp_choices[] = [
				'filter_value'         => $mb_value,
				'filter_value_display' => $mb_label,
				'filter_value_id'      => 0,
				'filter_value_parent'  => 0,
				'count'                => ! empty( $matched_choice ) ? $matched_choice[0]['count'] : 0,
			];
		}

		// Overwrite the choices source
		$element->choices_source = $temp_choices;

	}

	public function custom_field_meta_query( $meta_query, $filter, $provider, $query_id ) {
		if ( $provider !== $this->provider_key ) {
			return $meta_query;
		}

		$settings         = $filter['settings'];
		$filter_value     = $filter['value'];
		$fiield_type      = $settings['sourceFieldType'] ?? 'post';
		$custom_field_key = $settings['customFieldKey'] ?? false;
		$combine_logic    = $settings['filterMultiLogic'] ?? 'OR';

		$instance_name = $filter['instance_name'];

		if ( isset( $settings['fieldCompareOperator'] ) ) {
			$compare_operator = $settings['fieldCompareOperator'];
		} else {
			// Default compare operator for filter-select and filter-radio is =, for others is IN
			$compare_operator = in_array( $instance_name, [ 'filter-select', 'filter-radio' ], true ) ? '=' : 'IN';
		}

		// Use the actual meta key
		$dd_tag           = $custom_field_key;
		$custom_field_key = $this->get_meta_key_by_dd_tag( $dd_tag );
		$field_info       = $this->get_field_settings_from_dd_provider( $dd_tag );
		$field_settings   = $field_info['field'] ?? [];
		$mb_field_type    = $field_settings['type'] ?? 'text';
		$is_timestamp     = false;

		// Rebuild meta query
		$meta_query = [];

		$is_multiple    = isset( $field_info['parent']['id'] ); // Group field saved in serialized format
		$sprintf_format = '"%s"'; // For serialized value

		// Certain field types are multiple
		switch ( $mb_field_type ) {
			case 'taxonomy':
			case 'taxonomy_advanced':
				$is_multiple = isset( $field_settings['multiple'] ) && $field_settings['multiple'];

				if ( $is_multiple ) {
					$sprintf_format = '%s'; // Taxonomy field value as comma separated string only
				}
				break;

			case 'date':
			case 'datetime':
				$is_timestamp = ! empty( $field_settings['timestamp'] );
				if ( $is_timestamp ) {
					// Value saved as timestamp, convert to timestamp (@since 1.12)
					$filter_value = strtotime( $filter_value );
				}
				break;
		}

		if ( ! $is_multiple ) {

			if ( $combine_logic === 'AND' && is_array( $filter_value ) && $instance_name === 'filter-checkbox' ) {
				// In Metabox, multiple values saved as multiple rows with the same meta key
				foreach ( $filter_value as $value ) {
					$meta_query[] = [
						'key'     => $custom_field_key,
						'value'   => $value,
						'compare' => $compare_operator,
					];

					// Handle timestamp value (@since 1.12)
					if ( $is_timestamp ) {
						$meta_query['type'] = 'NUMERIC';
					}
				}

				// Add relation
				$meta_query['relation'] = $combine_logic;
			}

			// Normal case
			else {
				$meta_query = [
					'key'     => $custom_field_key,
					'value'   => $filter_value,
					'compare' => $compare_operator,
				];

				// Handle timestamp value (@since 1.12)
				if ( $is_timestamp ) {
					$meta_query['type'] = 'NUMERIC';
				}
			}
		}

		else {
			// Multiple values and value in serialized format
			if ( in_array( $instance_name, [ 'filter-select', 'filter-radio' ], true ) ) {
				// Radio or select filter, $filter_value is a string
				$meta_query = [
					'key'     => $custom_field_key,
					'value'   => sprintf( $sprintf_format, $filter_value ),
					'compare' => 'LIKE',
				];

			} else {

				foreach ( $filter_value as $value ) {
					$meta_query[] = [
						'key'     => $custom_field_key,
						'value'   => sprintf( $sprintf_format, $value ),
						'compare' => 'LIKE',
					];
				}

				// Add relation
				$meta_query['relation'] = $combine_logic;
			}
		}

		return $meta_query;
	}

	/**
	 * Modify the meta query for filter range element
	 *
	 * @return array
	 */
	public function range_custom_field_meta_query( $meta_query, $filter, $provider, $query_id ) {
		if ( $provider !== $this->provider_key ) {
			return $meta_query;
		}

		$settings         = $filter['settings'];
		$custom_field_key = $settings['customFieldKey'] ?? false;

		// Use the actual meta key
		$actual_meta_key = $this->get_meta_key_by_dd_tag( $custom_field_key );

		// Replace the meta_key with the actual meta key
		$meta_query['key'] = $actual_meta_key;

		return $meta_query;
	}

	/**
	 * Modify the meta query for Filter - datepicker element
	 *
	 * @return array
	 */
	public function datepicker_custom_field_meta_query( $meta_query, $filter, $provider, $query_id ) {
		if ( $provider !== $this->provider_key ) {
			return $meta_query;
		}

		$settings         = $filter['settings'];
		$custom_field_key = $settings['customFieldKey'] ?? false;
		$mode             = isset( $settings['isDateRange'] ) ? 'range' : 'single';

		// Use the actual meta key
		$actual_meta_key = $this->get_meta_key_by_dd_tag( $custom_field_key );

		$field_info     = $this->get_field_settings_from_dd_provider( $custom_field_key );
		$field_settings = $field_info['field'] ?? [];
		$field_type     = $field_settings['type'] ?? 'text';
		$is_timestamp   = false;

		switch ( $field_type ) {
			case 'date':
			case 'time':
			case 'datetime':
				$is_timestamp = ! empty( $field_settings['timestamp'] );
				break;
		}

		// Replace the meta_key with the actual meta key
		if ( $mode === 'single' ) {
			$meta_query['key'] = $actual_meta_key;

			// Handle timestamp value (@since 1.12)
			if ( $is_timestamp ) {
				$meta_query['value'] = strtotime( $meta_query['value'] );
				$meta_query['type']  = 'NUMERIC';
			}
		} else {
			foreach ( $meta_query as $key => $query ) {
				$meta_query[ $key ]['key'] = $actual_meta_key;

				// Handle timestamp value (@since 1.12)
				if ( $is_timestamp ) {
					$meta_query[ $key ]['value'] = strtotime( $query['value'] );
					$meta_query[ $key ]['type']  = 'NUMERIC';
				}
			}
		}

		return $meta_query;
	}

	/**
	 * Auto detect the date format for Filter - Datepicker following Meta Box field settings
	 */
	public function datepicker_date_format( $date_format, $provider, $element ) {
		if ( $provider !== $this->provider_key ) {
			return $date_format;
		}
		$settings         = $element->settings;
		$custom_field_key = $settings['customFieldKey'] ?? false;
		$enable_time      = isset( $settings['enableTime'] );

		// Use the actual meta key
		$dd_tag             = $custom_field_key;
		$field_info         = $this->get_field_settings_from_dd_provider( $dd_tag );
		$is_group_sub_field = isset( $field_info['parent']['id'] );
		$field_settings     = $field_info['field'] ?? [];
		$mb_field_type      = $field_settings['type'] ?? 'text';
		$use_timestamp      = ! empty( $field_settings['timestamp'] );

		// Default date time format in metabox
		$date_format = 'Y-m-d';
		$time_format = 'H:i';

		switch ( $mb_field_type ) {
			case 'date':
				$format = $date_format;
				break;
			case 'datetime':
				$format = $enable_time ? "{$date_format} {$time_format}" : $date_format;
				break;
			case 'time':
				$format = $time_format;
				break;
		}

		// NOTE: Overwrite the format if not using timestamp and save_format is set (Metabox not follow save_format if it's a group subfield)
		if ( ! $use_timestamp && ! $is_group_sub_field && ! empty( $field_settings['save_format'] ) ) {
			$format = $field_settings['save_format'];
		}

		return $format;
	}

	/**
	 * Get field settings from the Metabox provider (Dynamic Data)
	 * By doing this, we no need to call rwmb_get_field_settings() as it requires the actual post ID as object ID parameter
	 *
	 * @param string $tag The dynamic data tag
	 * @param string $key The key to retrieve from the field settings
	 */
	public function get_field_settings_from_dd_provider( $tag, $key = '' ) {
		if ( empty( $this->metabox_dd_tags ) ) {
			return false;
		}

		$dd_key = str_replace( [ '{','}' ], '', $tag );

		$dd_info = $this->metabox_dd_tags[ $dd_key ] ?? false;

		if ( ! $dd_info ) {
			return false;
		}

		// Return all settings or specific key
		if ( empty( $key ) ) {
			return $dd_info;
		}

		return $dd_info[ $key ] ?? false;
	}

	/**
	 * Get the actual meta key from the DD tag
	 *
	 * @param string $tag The dynamic data tag
	 */
	public function get_meta_key_by_dd_tag( $tag ) {
		if ( empty( $this->metabox_dd_tags ) ) {
			return $tag;
		}

		$field_info = $this->get_field_settings_from_dd_provider( $tag );

		if ( ! $field_info || ! isset( $field_info['field']['id'] ) ) {
			return $tag;
		}

		// Use the field ID as the meta key
		$actual_meta_key = $field_info['field']['id'];

		// Recursively get the parent field ID
		while ( isset( $field_info['parent']['id'] ) ) {
			// Use the parent field ID as the meta key
			$actual_meta_key = $field_info['parent']['id'];

			// Get the parent field info by the parent DD tag
			$field_info = $this->get_field_settings_from_dd_provider( $field_info['parent']['dd_tag'] );

			// Break if no parent field info or no field ID
			if ( ! $field_info || ! isset( $field_info['field']['id'] ) ) {
				break;
			}

			// Parent field is found, update the meta key
			$actual_meta_key = $field_info['field']['id'] ?? $actual_meta_key;
		}

		return $actual_meta_key;
	}

}
