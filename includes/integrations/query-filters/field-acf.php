<?php
namespace Bricks\Integrations\Query_Filters;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Field_Acf {
	protected $name                 = 'ACF';
	protected $provider_key         = 'acf';
	public static $is_active        = false;
	public static $actual_meta_keys = []; // Hold the real meta keys for ACF fields (improve performance)
	private $acf_dd_tags            = [];

	public function __construct() {
		if ( ! class_exists( 'ACF' ) ) {
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
	 * Retrieve all registered tags from ACF provider
	 */
	public function init() {
		$acf_provider = \Bricks\Integrations\Dynamic_Data\Providers::get_registered_provider( $this->provider_key );
		if ( $acf_provider ) {
			$this->acf_dd_tags = $acf_provider->get_tags();
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
		$acf_provider = \Bricks\Integrations\Dynamic_Data\Providers::get_registered_provider( $this->provider_key );
		if ( is_null( $acf_provider ) && empty( $this->acf_dd_tags ) ) {
			$classname = 'Bricks\Integrations\Dynamic_Data\Providers\Provider_' . ucfirst( $this->provider_key );

			if ( ! class_exists( $classname ) ) {
				return;
			}

			// Try manually init the provider
			if ( $classname::load_me() ) {
				$acf_provider      = new $classname( $this->provider_key );
				$this->acf_dd_tags = $acf_provider->get_tags();
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

			$actual_meta_key = $this->get_meta_key_by_dd_tag( $meta_key );

			$args['meta_query'] = [
				[
					'key'     => $actual_meta_key,
					'compare' => 'EXISTS'
				],
			];
		}

		return $args;
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

		// $meta_key is a dynamic tag
		$actual_meta_key = $this->get_meta_key_by_dd_tag( $meta_key );

		$get_field_object_id = $object_id;

		if ( in_array( $object_type, [ 'term', 'user' ], true ) ) {
			$get_field_object_id = $object_type . '_' . $object_id;
		}

		// Get field object via ACF function when generating index rows
		$acf_field = get_field_object( $actual_meta_key, $get_field_object_id );

		// Return if the field is not found
		if ( ! $acf_field ) {
			return $rows;
		}

		$acf_value              = $acf_field['value'] ?? false;
		$field_type             = $acf_field['type'] ?? 'text';
		$acf_field['brx_label'] = []; // Hold custom label
		$set_value_id           = false;

		switch ( $field_type ) {
			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				$return_format = $acf_field['return_format'] ?? 'value';
				$value         = empty( $acf_value ) ? [] : (array) $acf_value;

				// If return format is set to "Both (array)" return 'value' by default
				if ( $return_format === 'array' ) {
					if ( isset( $value['label'] ) ) {
						// For single choice field
						unset( $value['label'] );
					} else {
						// For multiple choice field
						$value = wp_list_pluck( $value, 'value' );
					}
				}

				$acf_value = $value;
				break;

			case 'true_false':
				// ACF true/false field returns 1 or 0 (@since 2.0)
				$acf_value                            = $acf_value ? 1 : 0;
				$acf_field['brx_label'][ $acf_value ] = $acf_value ? esc_html__( 'True', 'bricks' ) : esc_html__( 'False', 'bricks' );
				break;

			case 'relationship':
			case 'post_object':
				$return_format = $acf_field['return_format'] ?? false;
				$temp_value    = empty( $acf_value ) ? [] : (array) $acf_value;

				// Either object or id
				if ( $return_format === 'object' ) {
					// Retrieve the Post Title as label
					foreach ( $temp_value as $post ) {
						if ( is_a( $post, 'WP_Post' ) ) {
							$acf_field['brx_label'][ $post->ID ] = $post->post_title;
						}
					}
					// Retrieve the Post ID as value
					$temp_value = wp_list_pluck( $temp_value, 'ID' );
				} else {
					// Retrieve the Post Title as label
					foreach ( $temp_value as $post_id ) {
						$post = get_post( $post_id );
						if ( is_a( $post, 'WP_Post' ) ) {
							$acf_field['brx_label'][ $post_id ] = $post->post_title;
						}
					}
				}

				$acf_value    = $temp_value;
				$set_value_id = true;
				break;

			case 'taxonomy':
				$return_format = $acf_field['return_format'] ?? false;
				$temp_value    = empty( $acf_value ) ? [] : (array) $acf_value;

				// Either object or id
				if ( $return_format === 'object' ) {
					// Retrieve the Term Name as label
					foreach ( $temp_value as $term ) {
						if ( is_a( $term, 'WP_Term' ) ) {
							$acf_field['brx_label'][ $term->term_id ] = $term->name;
						}
					}
					// Retrieve the Term ID as value
					$temp_value = wp_list_pluck( $temp_value, 'term_id' );
				} else {
					// Retrieve the Term Name as label
					foreach ( $temp_value as $term_id ) {
						$term = get_term( $term_id );
						if ( ! is_wp_error( $term ) && is_a( $term, 'WP_Term' ) ) {
							$acf_field['brx_label'][ $term_id ] = $term->name;
						}
					}
				}

				$acf_value    = $temp_value;
				$set_value_id = true;

				break;

			case 'user':
				if ( ! empty( $acf_value ) ) { // Avoid PHP warning when using wp_list_pluck (@since 1.12.2)
					// ACF allows for single or multiple users
					$temp_value    = $acf_field['multiple'] ? $acf_value : [ $acf_value ];
					$return_format = $acf_field['return_format'] ?? false;
					$temp_value    = $return_format === 'id' ? $temp_value : wp_list_pluck( $temp_value, 'ID' );

					foreach ( $temp_value as $user_id ) {
						$user = get_user_by( 'ID', $user_id );
						if ( $user ) {
							$acf_field['brx_label'][ $user_id ] = $user->display_name ?? $user->nickname;
						}
					}

					$acf_value    = $temp_value;
					$set_value_id = true;
				}

				break;

			case 'date_picker':
			case 'date_time_picker':
				// case 'time_picker':
				if ( ! empty( $acf_value ) ) {
					$return_format = $acf_field['return_format'] ?? false;
					$format        = $field_type == 'date_picker' ? 'Y-m-d' : 'Y-m-d H:i:s';

					// Use the return format if available
					if ( ! empty( $return_format ) ) {
						$format = $return_format;
					}

					$date = \DateTime::createFromFormat( $format, $acf_value );

					if ( $date instanceof \DateTime ) {
						// Save the date in required format (query index)
						$db_format = $field_type == 'date_picker' ? 'Y-m-d' : 'Y-m-d H:i:s';
						$acf_value = $date->format( $db_format );
					}
				}

				break;
		}

		// Retrieve label function
		$get_label = function( $value, $field_settings ) {
			$label = $value;

			if ( ! is_array( $value ) ) {
				// Use label if available
				if ( isset( $field_settings['choices'][ $value ] ) ) {
					$label = $field_settings['choices'][ $value ];
				}

				// Use custom label if available
				if ( isset( $field_settings['brx_label'] ) && isset( $field_settings['brx_label'][ $value ] ) ) {
					$label = $field_settings['brx_label'][ $value ];
				}
			}

			return $label;
		};

		$final_values = is_array( $acf_value ) ? $acf_value : [ $acf_value ];

		// Generate rows
		foreach ( $final_values as $value ) {
			$rows[] = [
				'filter_id'            => '',
				'object_id'            => $object_id,
				'object_type'          => $object_type,
				'filter_value'         => $value,
				'filter_value_display' => $get_label( $value, $acf_field ),
				'filter_value_id'      => $set_value_id ? $value : 0,
				'filter_value_parent'  => 0,
			];
		}

		return $rows;
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

		// Get the actual meta key
		$actual_meta_key = $this->get_meta_key_by_dd_tag( $meta_key );

		// Check if the meta key exists
		return metadata_exists( 'post', $post_id, $actual_meta_key );
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

		// Get the actual meta key
		$actual_meta_key = $this->get_meta_key_by_dd_tag( $meta_key );

		// Check if the meta key exists
		return metadata_exists( 'user', $user_id, $actual_meta_key );
	}

	/**
	 * Modify the meta query for custom fields based on the field type
	 *
	 * @return array
	 */
	public function custom_field_meta_query( $meta_query, $filter, $provider, $query_id ) {
		if ( $provider !== $this->provider_key ) {
			return $meta_query;
		}

		$settings         = $filter['settings'];
		$filter_value     = $filter['value'];
		$field_type       = $settings['sourceFieldType'] ?? 'post';
		$custom_field_key = $settings['customFieldKey'] ?? false;
		$instance_name    = $filter['instance_name'];
		$combine_logic    = $settings['filterMultiLogic'] ?? 'OR';

		if ( isset( $settings['fieldCompareOperator'] ) ) {
			$compare_operator = $settings['fieldCompareOperator'];
		} else {
			// Default compare operator for filter-select and filter-radio is =, for others is IN
			$compare_operator = in_array( $instance_name, [ 'filter-select', 'filter-radio' ], true ) ? '=' : 'IN';
		}

		// Retrieve the actual meta key from dynamic tag to be used in the query
		$actual_meta_key = $this->get_meta_key_by_dd_tag( $custom_field_key );

		// Get the field settings
		$field_info     = $this->get_field_settings_from_dd_provider( $custom_field_key );
		$acf_field      = $field_info['field'] ?? [];
		$acf_field_type = $acf_field['type'] ?? 'text';

		// Rebuild meta query
		$meta_query = [];

		// Majority of the field type use multiple key to determine the field is multiple or not
		$is_multiple = $acf_field['multiple'] ?? false;

		// Certain field types are always multiple
		switch ( $acf_field_type ) {
			case 'checkbox':
			case 'relationship':
				$is_multiple = true;
				break;

			case 'taxonomy':
				$is_multiple = isset( $acf_field['field_type'] ) && ( $acf_field['field_type'] === 'multi_select' || $acf_field['field_type'] === 'checkbox' );
				break;
		}

		if ( ! $is_multiple ) {
			// Single value
			$meta_query = [
				'key'     => $actual_meta_key,
				'value'   => $filter_value,
				'compare' => $compare_operator,
			];
		}

		else {
			// Multiple values and value in serialized format
			if ( in_array( $instance_name, [ 'filter-select', 'filter-radio' ], true ) ) {
				// Radio or select filter, $filter_value is a string
				$meta_query = [
					'key'     => $actual_meta_key,
					'value'   => sprintf( '"%s"', $filter_value ),
					'compare' => 'LIKE',
				];

			} else {

				// Checkbox filter
				foreach ( $filter_value as $value ) {
					$meta_query[] = [
						'key'     => $actual_meta_key,
						'value'   => sprintf( '"%s"', $value ),
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

		// Replace the meta_key with the actual meta key
		if ( $mode === 'single' ) {
			$meta_query['key'] = $actual_meta_key;
		} else {
			foreach ( $meta_query as $key => $query ) {
				$meta_query[ $key ]['key'] = $actual_meta_key;
			}
		}

		return $meta_query;
	}

	/**
	 * Modify the custom field choices following the ACF field choices
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

		$field_info  = $this->get_field_settings_from_dd_provider( $custom_field_key );
		$acf_field   = $field_info['field'] ?? [];
		$acf_choices = $acf_field['choices'] ?? [];
		$field_type  = $acf_field['type'] ?? 'text';

		// Taxonomy field can have choices from the terms, build the choices from the terms
		if ( $field_type === 'taxonomy' ) {
			$taxonomy = $acf_field['taxonomy'] ?? false;
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
				$acf_choices = [];
				foreach ( $terms as $term ) {
					$acf_choices[ $term->term_id ] = $term->name;
				}
			}
		}

		// Return if no choices
		if ( empty( $acf_choices ) ) {
			return;
		}

		// Modify the choices source
		$temp_choices = [];
		$ori_choices  = $element->choices_source;
		foreach ( $acf_choices as $acf_value => $acf_label ) {
			$matched_choice = array_filter(
				$ori_choices,
				function( $choice ) use ( $acf_value ) {
					return isset( $choice['filter_value'] ) && \Bricks\Filter_Element::is_option_value_matched( $choice['filter_value'], $acf_value );
				}
			);

			$matched_choice = array_values( $matched_choice );

			$temp_choices[] = [
				'filter_value'         => $acf_value,
				'filter_value_display' => $acf_label,
				'filter_value_id'      => 0,
				'filter_value_parent'  => 0,
				'count'                => ! empty( $matched_choice ) ? $matched_choice[0]['count'] : 0,
			];
		}

		// Overwrite the choices source
		$element->choices_source = $temp_choices;

	}

	/**
	 * Auto detect the date format for Filter - Datepicker following ACF datepicker field return format
	 */
	public function datepicker_date_format( $date_format, $provider, $element ) {
		if ( $provider !== $this->provider_key ) {
			return $date_format;
		}
		$settings         = $element->settings;
		$custom_field_key = $settings['customFieldKey'] ?? false;
		$enable_time      = isset( $settings['enableTime'] );

		// Use the actual meta key
		$field_info    = $this->get_field_settings_from_dd_provider( $custom_field_key );
		$acf_field     = $field_info['field'] ?? [];
		$return_format = $acf_field['return_format'] ?? false;

		// Use the return format if available
		if ( $return_format ) {
			$date_format = $return_format;
		} else {
			// Use the default date format saved in the database
			$date_format = $enable_time ? 'Y-m-d H:i:s' : 'Y-m-d';
		}

		return $date_format;
	}

	/**
	 * Get the meta key saved in the database by the ACF key
	 * Convert field_123456789 to actual meta key considering parent fields
	 */
	private function get_meta_key_by_acf_key( $acf_key ) {
		// Check if the meta key is already saved in the static variable
		if ( isset( self::$actual_meta_keys[ $acf_key ] ) ) {
			return self::$actual_meta_keys[ $acf_key ];
		}

		$field = acf_maybe_get_field( $acf_key );

		if ( empty( $field ) || ! is_array( $field ) || ! isset( $field['name'] ) || ! isset( $field['parent'] ) ) {
			// Save the meta key in the static variable
			self::$actual_meta_keys[ $acf_key ] = $acf_key;
			return $acf_key;
		}

		$parents = [];

		// Get the final key
		while ( ! empty( $field['parent'] ) && ! in_array( $field['name'], $parents ) ) {
			$parents[] = $field['name'];
			$field     = acf_get_field( $field['parent'] );
		}

		$final_key = implode( '_', array_reverse( $parents ) );

		// Save the meta key in the static variable
		self::$actual_meta_keys[ $acf_key ] = $final_key;

		return $final_key;
	}

	/**
	 * Get the field settings from the dynamic data provider
	 *
	 * @param string $tag The dynamic data tag
	 * @param string $key The key to retrieve from the field settings (optional)
	 */
	public function get_field_settings_from_dd_provider( $tag, $key = '' ) {
		if ( empty( $this->acf_dd_tags ) ) {
			return false;
		}

		$dd_key = str_replace( [ '{','}' ], '', $tag );

		$dd_info = $this->acf_dd_tags[ $dd_key ] ?? false;

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
	 * Get the actual meta key from the dynamic data tag
	 *
	 * @param string $tag The dynamic data tag
	 */
	public function get_meta_key_by_dd_tag( $tag ) {
		if ( empty( $this->acf_dd_tags ) ) {
			return $tag;
		}

		$field_info = $this->get_field_settings_from_dd_provider( $tag );

		if ( ! $field_info || ! isset( $field_info['field']['key'] ) ) {
			return $tag;
		}

		return $this->get_meta_key_by_acf_key( $field_info['field']['key'] );
	}
}
