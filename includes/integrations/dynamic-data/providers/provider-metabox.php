<?php
namespace Bricks\Integrations\Dynamic_Data\Providers;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Provider_Metabox extends Base {
	public static function load_me() {
		add_filter( 'mbv_data', [ __CLASS__, 'mb_views_post_data' ], 10, 2 );

		return class_exists( 'RWMB_Loader' );
	}

	public function register_tags() {
		$fields = self::get_fields();

		foreach ( $fields as $field ) {
			$this->register_tag( $field );
		}

		// Register relationships to the query loop
		$this->register_relationships();
	}

	public function register_tag( $field = [], $parent_field = [], $parent_dd_tag = '' ) {
		$contexts = self::get_fields_by_context();

		$type = $field['type'];

		if ( ! isset( $contexts[ $type ] ) ) {
			return;
		}

		// STEP: Field key
		$key = 'mb_';

		if ( ! empty( $parent_field ) ) {
			$key .= preg_replace( '/[\s\:]/', '', $parent_field['_brx_group'] ) . '_' . $parent_field['id'] . '_';
		}

		// If field has parent, no need to add the group again
		$key .= isset( $field['_brx_group'] ) && empty( $parent_field ) ? preg_replace( '/[\s\:]/', '', $field['_brx_group'] ) . '_' . $field['id'] : $field['id'];

		foreach ( $contexts[ $type ] as $context ) {
			$name = self::CONTEXT_TEXT === $context || self::CONTEXT_LOOP === $context ? $key : $key . '_' . $context;

			// STEP: Field label
			$label = ! empty( $parent_field['name'] ) ? $field['name'] . ' (' . $parent_field['name'] . ')' : $field['name'];

			if ( $context === self::CONTEXT_LOOP ) {
				$label = 'MB ' . ucfirst( $type ) . ': ' . $label;
			}

			// Enhance the label for relationship fields for text context (Not for loop context) (@since 1.11.1)
			if ( isset( $field['relationship'] ) && $field['relationship'] ) {
				$label = 'MB Relationship: ' . $field['id'];
			}

			// Avoid empty label in the builder {mb_user_remember} (created by Meta Box)
			if ( $label === '' ) {
				$label = $name;
			}

			$tag = [
				'name'     => '{' . $name . '}',
				'label'    => $label,
				'group'    => $field['_brx_group_label'],
				'field'    => $field,
				'provider' => $this->name,
			];

			if ( ! empty( $parent_field ) ) {
				// Add the parent field attributes to the child tag
				$tag['parent'] = [
					'id'     => $parent_field['id'],
					'dd_tag' => $parent_dd_tag, // Add the parent dd tag for Query Filters integration (@since 1.11.1)
				];
			}

			// Register tags in the loop
			if ( $context === self::CONTEXT_LOOP ) {
				$this->loop_tags[ $name ] = $tag;

				if ( ! empty( $field['fields'] ) ) {
					foreach ( $field['fields'] as $sub_field ) {

						$sub_field['_brx_object_type'] = $field['_brx_object_type'];

						$sub_field['_brx_group'] = $field['_brx_group'];

						$sub_field['_brx_group_label'] = $field['_brx_group_label'];

						$this->register_tag( $sub_field, $field, $tag['name'] ); // Recursive
					}
				}
			}

			// Register regular tags
			elseif ( $context === self::CONTEXT_TEXT || empty( $parent_field ) ) {
				// For legacy purposes we keep different tags for all the contexts, only when fields belong to posts
				if ( $field['_brx_object_type'] != 'post' && $context != self::CONTEXT_TEXT ) {
					continue;
				}

				$this->tags[ $name ] = $tag;

				if ( self::CONTEXT_TEXT !== $context ) {
					$this->tags[ $name ]['deprecated'] = 1;
				}
			}
		}
	}

	public static function get_fields() {
		if ( ! function_exists( 'rwmb_get_registry' ) ) {
			return [];
		}

		$field_registry = rwmb_get_registry( 'field' );

		$mb_fields = [];

		foreach ( [ 'post', 'term', 'user', 'setting' ] as $type ) {
			$fields = $field_registry->get_by_object_type( $type );

			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $group => $group_fields ) {
				if ( $type == 'post' ) {
					$post_type_obj = get_post_type_object( $group );
					$group_label   = $post_type_obj ? $post_type_obj->labels->name : $group;
				} else {
					$group_label = ucfirst( $group );
				}

				foreach ( $group_fields as $field ) {
					if ( ! isset( $field['type'] ) ) {
						continue;
					}

					$field['_brx_object_type'] = $type; // 'post','term', 'user', 'setting'

					// Page settings object id could have spaces and colons
					$field['_brx_group'] = $group;

					$field['_brx_group_label'] = isset( $group_label ) ? 'Meta Box (' . $group_label . ')' : 'Meta Box';

					$mb_fields[] = $field;
				}
			}
		}

		return $mb_fields;
	}

	public function register_relationships() {
		if ( ! class_exists( 'MB_Relationships_API' ) ) {
			return;
		}

		$relations = \MB_Relationships_API::get_all_relationships_settings();

		if ( empty( $relations ) ) {
			return;
		}

		foreach ( $relations as $relation_key => $relation ) {
			$label = ! empty( $relation['menu_title'] ) ? $relation['menu_title'] : ucfirst( str_replace( '-', ' ', $relation_key ) );

			$relation['_brx_object_type'] = 'relationship';

			$tag_key = 'mb_' . $relation_key;

			$tag = [
				'name'     => '{' . $tag_key . '}',
				'label'    => "MB Relationship: {$label}",
				'group'    => 'Meta Box',
				'field'    => $relation,
				'provider' => $this->name,
			];

			$this->loop_tags[ $tag_key ] = $tag;
		}
	}

	public function get_tag_value( $tag, $post, $args, $context ) {
		$post_id    = isset( $post->ID ) ? $post->ID : '';
		$tag_object = $this->tags[ $tag ];
		$field      = $this->tags[ $tag ]['field'];

		// STEP: Check for filter args
		$filters = $this->get_filters_from_args( $args );

		// STEP: Get the value
		$value = $this->get_raw_value( $tag, $post_id );

		// @since 1.8 - New array_value filter. Once used, we don't want to process the field type logic
		if ( isset( $filters['array_value'] ) && is_array( $value ) ) {
			// Force context to text
			$context = 'text';
			$value   = $this->return_array_value( $value, $filters );
		}

		// Process field type logic
		else {

			switch ( $field['type'] ) {
				case 'file_input':
					// Support :value filter to return IDs only
					if ( ! isset( $filters['value'] ) ) {
						$filters['object_type'] = 'media';
						$filters['link']        = true;
					}

					$value = empty( $field['clone'] ) ? [ $value ] : $value;

					$value = array_map( 'attachment_url_to_postid', $value );
					$value = array_filter( $value );
					break;

				case 'file':
				case 'file_upload':
				case 'file_advanced':
				case 'video':
					// Support :value filter to return IDs only
					if ( ! isset( $filters['value'] ) ) {
						$filters['object_type'] = 'media';
						$filters['link']        = true;
					}

					$value = ! empty( $value ) ? array_values( $value ) : [];

					$value = isset( $value[0]['ID'] ) ? wp_list_pluck( $value, 'ID' ) : $value;
					break;

				// @since 2.0
				case 'icon':
					$value                    = self::get_icon( $field, $value );
					$filters['skip_sanitize'] = true;
					break;

				case 'image':
				case 'image_advanced':
				case 'image_upload':
				case 'single_image':
					// Support :value filter to return IDs only
					if ( ! isset( $filters['value'] ) ) {
						$filters['object_type'] = 'media';
					}

					// Empty field value should return empty array to avoid default post title in text context. @see $this->format_value_for_context()
					$value = empty( $value ) ? [] : $value;

					// Single image returns a single array
					$value = isset( $value['ID'] ) || ! is_array( $value ) ? [ $value ] : $value;

					$value = ! empty( $value ) ? array_values( $value ) : [];

					$value = isset( $value[0]['ID'] ) ? wp_list_pluck( $value, 'ID' ) : $value;
					break;

				case 'taxonomy_advanced':
				case 'taxonomy':
					// Support :value filter to return IDs only
					if ( ! isset( $filters['value'] ) ) {
						$filters['object_type'] = 'term';
						$filters['taxonomy']    = $field['taxonomy'][0] ?? '';

						// NOTE: Undocumented
						$show_as_link = apply_filters( 'bricks/metabox/taxonomy/show_as_link', true, $value, $field );

						if ( $show_as_link ) {
							$filters['link'] = true;
						}
					}

					$value = is_a( $value, 'WP_Term' ) || ! is_array( $value ) ? [ $value ] : $value;

					// Must check if $value is empty or not, maybe the $value is an empty array (@since 1.12)
					$value = ! empty( $value ) && is_a( $value[0], 'WP_Term' ) ? wp_list_pluck( $value, 'term_id' ) : $value;
					break;

				case 'radio':
				case 'select':
				case 'checkbox_list':
				case 'select_advanced':
				case 'autocomplete':
				case 'button_group': // (@since 1.12)
					// STEP: Return raw value for element conditions
					if ( isset( $filters['value'] ) ) {
						return is_array( $value ) ? implode( ', ', $value ) : $value;
					}

					$value = empty( $field['clone'] ) || ! is_array( $value ) ? [ $value ] : $value;

					foreach ( $value as $key => $item ) {
						$item          = (array) $item;
						$item          = array_intersect_key( $field['options'], array_fill_keys( $item, '' ) );
						$value[ $key ] = implode( ', ', $item );
					}

					break;

				// @since 1.6.2
				case 'image_select':
					// STEP: Return raw value for element conditions
					if ( isset( $filters['value'] ) ) {
						return is_array( $value ) ? implode( ', ', $value ) : $value;
					}

					// STEP: Set default value
					$value = empty( $value ) ? [] : $value;
					$value = ! is_array( $value ) ? [ $value ] : $value;

					$attachment_ids = [];

					foreach ( $value as $index => $option_key ) {
						$url = isset( $field['options'][ $option_key ] ) ? $field['options'][ $option_key ] : $option_key;
						// Try to get the image ID from the image URL (it might be URL from other site)
						$attachment_id = attachment_url_to_postid( $url );

						if ( $attachment_id ) {
							$attachment_ids[] = $attachment_id;
						}

						$image = [
							'ID'  => $attachment_id,
							'url' => $url,
							'key' => $option_key,
						];

						$value[ $index ] = $image;
					}

					// Verify if the total number of attachment IDs is the same as the total number of values
					if ( count( $attachment_ids ) === count( $value ) ) {
						// All images are from the current site, treat this dd field as a normal image field
						// NOTE: image field can use on image element and gallery element and all filters like a normal image field
						$filters['object_type'] = 'media';
						$value                  = $attachment_ids;
					} else {
						// Some images are from other sites, treat this dd field as a normal text field and return the image URL
						// NOTE: image url can use on image element, but not on image gallery element
						foreach ( $value as $index => $image ) {
							$value[ $index ] = $image['url'];
						}

						// Note: If the field is allowed multiple, implode the values with comma but cannot use on image element anymore
						$value = ! empty( $field['multiple'] ) ? implode( ', ', $value ) : $value;
					}

					break;

				case 'checkbox':
					// STEP: Return raw value for element conditions (@since 1.5.7)
					if ( isset( $filters['value'] ) ) {
						return is_array( $value ) ? implode( ', ', $value ) : $value;
					}

					$value = (array) $value; // Supports clone option

					foreach ( $value as $key => $item ) {
						$original_value = $item;
						$item           = $original_value ? esc_html__( 'Yes', 'bricks' ) : esc_html__( 'No', 'bricks' );

						/**
						 * NOTE: Undocumented
						 */
						$value[ $key ] = apply_filters( 'bricks/metabox/checkbox_value', $item, $original_value, $field, $post );
					}
					break;

				case 'fieldset_text':
					$value = empty( $field['clone'] ) ? [ $value ] : $value;

					foreach ( $value as $key => $row ) {
						$output = [];

						if ( isset( $field['options'] ) ) {
							foreach ( $field['options'] as $option_key => $label ) {
								$output[] = esc_html( $label ) . ': ' . esc_html( $row[ $option_key ] );
							}
						} else {
							$output = implode( ', ', array_values( $row ) );
						}

						$value[ $key ] = is_array( $output ) ? implode( ', ', $output ) : $output;
					}

					break;

				case 'date':
				case 'time':
				case 'datetime':
					// NOTE: Rework the logic to support dynamic date filters @since 1.9

					// Make sure the $value is not empty
					if ( ! empty( $value ) ) {
						// STEP: Force $value to be an array
						$value = empty( $field['clone'] ) ? [ $value ] : $value;

						// STEP: Get the date format so that we can use it to create a DateTime object
						// Default date time format in metabox
						$date_format = 'Y-m-d';
						$time_format = 'H:i';

						switch ( $field['type'] ) {
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

						$use_timestamp      = ! empty( $field['timestamp'] );
						$is_group_sub_field = isset( $tag_object['parent']['id'] );

						// NOTE: Overwrite the format if not using timestamp and save_format is set (Metabox not follow save_format if it's a group subfield)
						if ( ! $use_timestamp && ! $is_group_sub_field && ! empty( $field['save_format'] ) ) {
							$format = $field['save_format'];
						}

						$utc_value = [];
						// STEP: Try convert the $value to DateTime object in UTC and save it to $utc_value
						foreach ( $value as $key => $row ) {
							// If this is a group sub-field and saved as timestamp, the $row is an array, pick the timestamp value
							$date_value = $use_timestamp && is_array( $row ) && isset( $row['timestamp'] ) ? $row['timestamp'] : $row;

							$date_value = $use_timestamp ? date_i18n( $format, $date_value ) : $date_value;

							// Replace original $value with $date_value as well for backward compatibility (in case the createFromFormat() failed)
							$value[ $key ] = $date_value;

							$date = \DateTime::createFromFormat( $format, $date_value );
							// Skip if the conversion failed
							if ( ! $date instanceof \DateTime ) {
								continue;
							}

							// Store converted DateTime in UTC
							$utc_value[ $key ] = $date->format( 'U' );
						}

						/**
						 * STEP: Set the object_type and meta_key so format_value_for_context() can handle it
						 *
						 * Only execute this if $utc_value is not empty and $utc_value will be used in the next step.
						 */
						if ( ! empty( $utc_value ) ) {
							$filters['meta_key']    = ! empty( $filters['meta_key'] ) ? $filters['meta_key'] : $format;
							$filters['object_type'] = $field['type'] == 'date' ? 'date' : 'datetime';
							$value                  = $utc_value;
						}
					}
					break;

				case 'map':
					/**
					 * NOTE: Undocumented
					 */
					$show_as_map = apply_filters( 'bricks/metabox/show_as_map', false, $field, $post );

					if ( $show_as_map ) {
						$value = rwmb_meta( $field['id'], null, $post_id );
					} else {
						$value = empty( $field['clone'] ) ? [ $value ] : $value;

						foreach ( $value as $key => $row ) {
							$output = [];

							foreach ( [ 'latitude', 'longitude' ] as $coordinate ) {
								if ( ! empty( $row[ $coordinate ] ) ) {
									$output[] = sprintf( '<span class="metabox-map-%s">%s</span>', $coordinate, $row[ $coordinate ] );
								}
							}

							$value[ $key ] = implode( ', ', $output );
						}
					}
					break;

				case 'oembed':
					if ( $context === 'text' ) {
						$filters['separator']     = '';
						$filters['skip_sanitize'] = true;

						$value = empty( $field['clone'] ) ? [ $value ] : $value;

						foreach ( $value as $key => $row ) {
							$value[ $key ] = wp_oembed_get( esc_url( $row ) );
						}
					}
					break;

				case 'text_list':
					$value = empty( $field['clone'] ) ? [ $value ] : $value;

					foreach ( $value as $key => $row ) {
						$value[ $key ] = esc_html( implode( ', ', array_values( (array) $row ) ) );
					}
					break;

				case 'wysiwyg':
					$filters['separator'] = ' ';

					$value = empty( $field['clone'] ) ? [ $value ] : $value;

					foreach ( $value as $key => $item ) {
						$value[ $key ] = \Bricks\Helpers::parse_editor_content( $item );
					}
					break;

				case 'post':
					// Support :value filter to return the post ID (@since 1.8)
					// Note: separator is <br> by default if multiple checked, didn't change in 1.8
					if ( ! isset( $filters['value'] ) ) {
						$filters['object_type'] = 'post';
						$filters['link']        = true;
					}

					$value = ! empty( $value ) ? $value : [];
					break;

				case 'user':
					// Support :value filter to return the IDs only
					if ( ! isset( $filters['value'] ) ) {
						$filters['object_type'] = 'user';
						$filters['link']        = true;
					}

					break;
			}

		}

		// STEP: Apply context (text, link, image, media)
		$value = $this->format_value_for_context( $value, $tag, $post_id, $filters, $context );

		return $value;
	}

	public function get_raw_value( $tag, $post_id ) {
		$tag_object = $this->tags[ $tag ];
		$field      = $tag_object['field'];

		// STEP: Check if in a Repeater loop, use is_any_looping (@since 1.10)
		$any_loop_id = \Bricks\Query::is_any_looping();
		if ( $any_loop_id ) {
			$query_type = \Bricks\Query::get_query_object_type( $any_loop_id );

			// Check if this loop belongs to this provider
			if ( array_key_exists( $query_type, $this->loop_tags ) ) {

				$parent_tag = $this->loop_tags[ $query_type ];

				// Check if the field is a sub-field of this loop field
				if (
					isset( $parent_tag['field']['id'] ) &&
					isset( $tag_object['parent']['id'] ) &&
					$parent_tag['field']['id'] == $tag_object['parent']['id']
				) {

					$query_loop_object = \Bricks\Query::get_loop_object( $any_loop_id );

					// Sub-field not found in the loop object (array)
					if ( ! is_array( $query_loop_object ) || ! array_key_exists( $field['id'], $query_loop_object ) ) {
						return '';
					}

					return $query_loop_object[ $field['id'] ];
				}
			}
		}

		if ( in_array( $field['_brx_object_type'], [ 'term', 'user', 'setting' ] ) ) {
			$get_args = [ 'object_type' => $field['_brx_object_type'] ];
		} else {
			$get_args = null;
		}

		// STEP: is a Group sub-field (not in query loop builder)
		if ( isset( $tag_object['parent']['id'] ) ) {
			$parent_field_value = rwmb_get_value( $tag_object['parent']['id'], $get_args, $this->get_object_id( $field, $post_id ) );

			// If field is clonable, get the first row
			$parent_field_value = isset( $parent_field_value[0] ) ? $parent_field_value[0] : $parent_field_value;

			return isset( $parent_field_value[ $field['id'] ] ) ? $parent_field_value[ $field['id'] ] : '';
		}

		// STEP: Is a regular field
		return rwmb_get_value( $field['id'], $get_args, $this->get_object_id( $field, $post_id ) );
	}

	/**
	 * Calculate the object ID to be used when fetching the field value
	 *
	 * @param array $field
	 * @param int   $post_id
	 */
	public function get_object_id( $field, $post_id ) {
		$object_type = $field['_brx_object_type'];

		// Field belongs to a settings page
		if ( $object_type == 'setting' ) {
			return $field['_brx_group'];
		}

		// If any looping (@since 1.10)
		$any_loop_id = \Bricks\Query::is_any_looping();
		if ( $any_loop_id ) {
			$loop_type = \Bricks\Query::get_loop_object_type( $any_loop_id );
			$object_id = \Bricks\Query::get_loop_object_id( $any_loop_id );

			// loop type is the same as the field object type (term, user, post)
			if ( $loop_type == $object_type ) {
				return $object_id;
			}
		}

		$queried_object = \Bricks\Helpers::get_queried_object( $post_id );

		if ( $object_type == 'term' && is_a( $queried_object, 'WP_Term' ) ) {
			return isset( $queried_object->term_id ) ? $queried_object->term_id : 0;
		}

		if ( $object_type == 'user' ) {
			if ( is_a( $queried_object, 'WP_User' ) && isset( $queried_object->ID ) ) {
				return $queried_object->ID;
			}

			return get_current_user_id();
		}

		return $post_id;
	}

	/**
	 * Set the loop query if exists
	 *
	 * @param array $results
	 * @param Query $query
	 * @return array
	 */
	public function set_loop_query( $results, $query ) {
		if ( ! array_key_exists( $query->object_type, $this->loop_tags ) ) {
			return $results;
		}

		$field = $this->loop_tags[ $query->object_type ]['field'];

		// Get the $post_id or the template preview ID (default)
		$post_id = \Bricks\Database::$page_data['preview_or_post_id'];

		$looping_query_id = \Bricks\Query::is_any_looping();

		if ( $looping_query_id ) {
			$loop_query_object_type = \Bricks\Query::get_query_object_type( $looping_query_id );
			$loop_object_type       = \Bricks\Query::get_loop_object_type( $looping_query_id );

			// Maybe it is a nested relationship or nested group
			if ( array_key_exists( $loop_query_object_type, $this->loop_tags ) ) {
				$loop_object = \Bricks\Query::get_loop_object( $looping_query_id );

				if ( is_array( $loop_object ) && array_key_exists( $field['id'], $loop_object ) ) {
					// Non-cloneable nested group field: $loop_object[ $field['id'] ] is considered as a single result (@since 1.6.2)
					if ( ! $field['clone'] ) {
						return [ $loop_object[ $field['id'] ] ];
					}

					return $loop_object[ $field['id'] ];
				}

				// The loop object is a post (from a relationship field)
				elseif ( is_a( $loop_object, 'WP_Post' ) ) {
					$post_id      = $loop_object->ID;
					$mb_object_id = $post_id;
				}
			}

			/**
			 * Check: Is it a post loop?
			 *
			 * @since 1.7: use $loop_object_type instead of $loop_query_object_type so that it works with user custom queries via PHP filters
			 */
			elseif ( $loop_object_type === 'post' ) {
				$post_id      = get_the_ID();
				$mb_object_id = $post_id;
			}
		}

		if ( ! isset( $mb_object_id ) ) {
			$mb_object_id = $this->get_object_id( $field, $post_id );
		}

		// Relationship
		if ( $field['_brx_object_type'] == 'relationship' ) {
			$queried_object = \Bricks\Helpers::get_queried_object( $post_id );

			/**
			 * Currently in a loop which is not a post, term or user loop (Maybe Group Loop)
			 * Use the current queried object to set the "from" or "to" argument
			 *
			 * @since 1.11.1
			 */
			if ( \Bricks\Query::is_any_looping() && ! is_a( $queried_object, 'WP_Post' ) && ! is_a( $queried_object, 'WP_Term' ) && ! is_a( $queried_object, 'WP_User' ) ) {
				$queried_object = get_queried_object();
			}

			// Function to set the relationship arguments
			$set_relationship_args = function( $current_object ) use ( $field ) {
				$api_args = [
					'id' => $field['id'],
					// 'from' or 'to' to be set
				];

				foreach ( [
					'post' => 'WP_Post',
					'term' => 'WP_Term',
					'user' => 'WP_User'
				] as $object_type => $object_class ) {

					foreach ( [ 'from', 'to' ] as $direction ) {
						// Queried object type is the same as the field direction object type
						if ( is_a( $current_object, $object_class ) && $field[ $direction ]['object_type'] == $object_type ) {

							if ( $object_type == 'post' && in_array( $current_object->post_type, $field[ $direction ]['meta_box']['post_types'] ) ) {
								$api_args[ $direction ] = $current_object->ID;
							} elseif ( $object_type == 'term' && in_array( $current_object->taxonomy, $field[ $direction ]['meta_box']['taxonomies'] ) ) {
								$api_args[ $direction ] = $current_object->term_id;
							} elseif ( $object_type == 'user' ) {
								$api_args[ $direction ] = $current_object->ID;
							}

						}

						if ( isset( $api_args[ $direction ] ) ) {
							break( 2 );
						}
					}
				}

				return $api_args;
			};

			// STEP: Calculate the "from" or "to" argument according to the context and the field object type
			$api_args = $set_relationship_args( $queried_object );

			/**
			 * In Builder, the queried_object could be wrong or retrieve incorrectly when located in nested query, especially in different context loops
			 * Helpers::get_queried_object will use the get_post() if bricks_is_ajax()
			 *
			 * @since 1.12.2
			 */
			if ( count( $api_args ) != 2 && \Bricks\Helpers::is_bricks_preview() && $looping_query_id ) {
				$api_args = $set_relationship_args( \Bricks\Query::get_loop_object( $looping_query_id ) );
			}

			// STEP: Query
			$results = count( $api_args ) == 2 ? \MB_Relationships_API::get_connected( $api_args ) : [];
		}

		// Or, regular field
		else {
			if ( in_array( $field['_brx_object_type'], [ 'term', 'user', 'setting' ] ) ) {
				$get_args = [ 'object_type' => $field['_brx_object_type'] ];
			} else {
				$get_args = null;
			}

			$results = rwmb_meta( $field['id'], $get_args, $mb_object_id );
		}

		if ( empty( $results ) ) {
			return [];
		}

		// Check if the first array key is numeric (@since 1.5.3)
		if ( is_array( $results ) ) {
			reset( $results );
			$first_key = key( $results );
		}

		return isset( $first_key ) && is_numeric( $first_key ) ? $results : [ $results ];
	}

	/**
	 * Manipulate the loop object
	 *
	 * @param array  $loop_object
	 * @param string $loop_key
	 * @param Query  $query
	 * @return array
	 */
	public function set_loop_object( $loop_object, $loop_key, $query ) {
		if ( ! array_key_exists( $query->object_type, $this->loop_tags ) ) {
			return $loop_object;
		}

		$field = $this->loop_tags[ $query->object_type ]['field'];

		// Set the global $post, if looping through posts (Maybe we should restrict to the relationships: $field['_brx_object_type'] == 'relationship')
		if ( is_a( $loop_object, 'WP_Post' ) ) {
			global $post;
			$post = get_post( $loop_object );
			setup_postdata( $post );
		}

		return $loop_object;
	}

	/**
	 * Get all fields supported and their contexts
	 *
	 * @return array
	 */
	private static function get_fields_by_context() {
		$fields = [
			// Basic
			'text'              => [ self::CONTEXT_TEXT ],
			'textarea'          => [ self::CONTEXT_TEXT ],
			'checkbox'          => [ self::CONTEXT_TEXT ],
			'checkbox_list'     => [ self::CONTEXT_TEXT ],
			'email'             => [ self::CONTEXT_TEXT, self::CONTEXT_LINK ],
			'number'            => [ self::CONTEXT_TEXT ],
			'password'          => [ self::CONTEXT_TEXT ],
			'range'             => [ self::CONTEXT_TEXT ],
			'select_advanced'   => [ self::CONTEXT_TEXT ],
			'icon'              => [ self::CONTEXT_TEXT ],
			'radio'             => [ self::CONTEXT_TEXT ],
			'select'            => [ self::CONTEXT_TEXT ],
			'image_select'      => [ self::CONTEXT_TEXT ], // @since 1.6.2
			'switch'            => [ self::CONTEXT_TEXT ], // @since 1.5.5
			'url'               => [ self::CONTEXT_TEXT, self::CONTEXT_LINK ],

			// Advanced
			'autocomplete'      => [ self::CONTEXT_TEXT ],
			'fieldset_text'     => [ self::CONTEXT_TEXT ],
			'date'              => [ self::CONTEXT_TEXT ],
			'time'              => [ self::CONTEXT_TEXT ],
			'datetime'          => [ self::CONTEXT_TEXT ],
			'slider'            => [ self::CONTEXT_TEXT ],
			'color'             => [ self::CONTEXT_TEXT ],
			'map'               => [ self::CONTEXT_TEXT ],
			'oembed'            => [ self::CONTEXT_TEXT, self::CONTEXT_LINK, self::CONTEXT_VIDEO, self::CONTEXT_MEDIA ],
			'text_list'         => [ self::CONTEXT_TEXT ],
			'wysiwyg'           => [ self::CONTEXT_TEXT ],
			'button_group'      => [ self::CONTEXT_TEXT ], // (@since 1.12)

			// WordPress
			'post'              => [ self::CONTEXT_TEXT, self::CONTEXT_LINK ],
			'taxonomy_advanced' => [ self::CONTEXT_TEXT, self::CONTEXT_LINK ],
			'taxonomy'          => [ self::CONTEXT_TEXT, self::CONTEXT_LINK ],
			'user'              => [ self::CONTEXT_TEXT ],

			// Upload
			'file'              => [ self::CONTEXT_TEXT, self::CONTEXT_LINK, self::CONTEXT_IMAGE, self::CONTEXT_VIDEO, self::CONTEXT_MEDIA ],
			'file_input'        => [ self::CONTEXT_TEXT, self::CONTEXT_LINK, self::CONTEXT_IMAGE, self::CONTEXT_VIDEO, self::CONTEXT_MEDIA ],
			'file_advanced'     => [ self::CONTEXT_TEXT, self::CONTEXT_LINK, self::CONTEXT_IMAGE, self::CONTEXT_VIDEO, self::CONTEXT_MEDIA ],
			'file_upload'       => [ self::CONTEXT_TEXT, self::CONTEXT_LINK, self::CONTEXT_IMAGE, self::CONTEXT_VIDEO, self::CONTEXT_MEDIA ],
			'image_advanced'    => [ self::CONTEXT_TEXT, self::CONTEXT_IMAGE ],
			'image_upload'      => [ self::CONTEXT_TEXT, self::CONTEXT_IMAGE ],
			'image'             => [ self::CONTEXT_TEXT, self::CONTEXT_IMAGE ],
			'single_image'      => [ self::CONTEXT_TEXT, self::CONTEXT_IMAGE ],
			'video'             => [ self::CONTEXT_TEXT, self::CONTEXT_VIDEO ],

			'group'             => [ self::CONTEXT_LOOP ],
		];

		return $fields;
	}

	/**
	 * Inside Query Loop: Change $post value so it could be used inside of the MB view
	 *
	 * @since 1.5.3
	 */
	public static function mb_views_post_data( $data, $twig ) {
		if ( ! \Bricks\Query::is_looping() || \Bricks\Query::get_loop_object_type() !== 'post' ) {
			return $data;
		}

		// Get the iteration $post object
		$loop_object = \Bricks\Query::get_loop_object();

		// Init of the MB Views logic to prepare the post data
		// @see meta-box-aio/vendor/meta-box/mb-views/src/Renderer.php: get_post_data()
		$meta_box_renderer = new \MBViews\Renderer\MetaBox();
		$post_object       = new \MBViews\Renderer\Post( $meta_box_renderer );
		$post_object->set_post( $loop_object );

		// Replace the value in the mb views data array
		$data['post'] = $post_object;

		return $data;
	}

	/**
	 * Retrieve all registered tags which are supported in WP_Query post__in parameter
	 *
	 * @since 1.12
	 */
	public function get_query_supported_tags() {
		// NOTE: There is no field type named 'relationship' in Meta Box
		// Meta Box will create 'post' field type for the related post type.
		$field_types = [
			'post',
			'image_advanced',
			'image',
			'image_upload',
			'single_image',
			'relationship',
		];

		$supported_tags = [];

		foreach ( $this->tags as $tag ) {
			if ( ! isset( $tag['field'] ) ) {
				continue;
			}

			if ( isset( $tag['deprecated'] ) ) {
				continue;
			}

			$field           = $tag['field'] ?? [];
			$field_type      = $field['type'] ?? '';
			$is_relationship = isset( $field['relationship'] ) && $field['relationship'] === true;

			if ( $is_relationship ) {
				$field_type = 'relationship';
			}

			if ( in_array( $field_type, $field_types, true ) ) {
				$supported_tags[] = [
					'name'     => $tag['name'],
					'type'     => $field_type,
					'label'    => $tag['label'],
					'params'   => [
						'post__in',
					],
					'provider' => $tag['provider'],
				];
			}
		}

		return $supported_tags;
	}

	/**
	 * Retrieve icon by value, from icon field type
	 *
	 * @since 2.0
	 */
	public static function get_icon( $field, $value ) {
		// Get all available icons
		$icons = self::get_available_icons( $field, $value );

		// Loop over icon options, and select the one that matches the value
		foreach ( $icons as $icon ) {
			if ( $icon['name'] === $value ) {

				// If "icon" field is set, we need to enqueue the icon CSS
				if ( isset( $icon['icon'] ) ) {

					// If "icon_css" is a string, directly enqueue the CSS file
					if ( is_string( $field['icon_css'] ) ) {
						$unique_handle = 'bricks-icon-' . md5( $field['icon_css'] ); // Generate unique handle
						wp_enqueue_style( $unique_handle, $field['icon_css'], [], BRICKS_VERSION );

						// If "icon_css" is not a string, but a callable, call it
					} elseif ( is_callable( $field['icon_css'] ) ) {
						$field['icon_css']();
					}

					// Return the icon eg. <i class="fa fa-icon-name"></i>
					return $icon['icon'];
				}

				// If "svg" field is set, directly return the SVG
				elseif ( isset( $icon['svg'] ) ) {
					return $icon['svg'];
				}
			}
		}

		return null;
	}

	/**
	 * Retrieve all icons used for icon field type
	 *
	 * @return array of icons
	 *      - Option 1: ['name' => 'icon-name', 'icon' => '<i class="fa fa-icon-name"></i>']
	 *      - Option 2: ['name' => 'icon-name', 'svg' => '<svg>...</svg>']
	 *
	 * @since 2.0
	 */
	public static function get_available_icons( $field, $value ) {
		// We will store a list of icons here
		$icons = [];

		// STEP: Parse icons from file (SVG)
		if ( ! empty( $field['icon_dir'] ) ) {
			$directory = $field['icon_dir'];

			// If directory does not exists, return empty array
			if ( ! is_dir( $directory ) ) {
				return [];
			}

			// Get file where $value is file name (.svg)
			$file = trailingslashit( $directory ) . $value . '.svg';
			if ( file_exists( $file ) ) {
				$icons[] = [
					'name' => $value,
					'svg'  => file_get_contents( $file ),
				];
			}
		}

		// STEP: Parse icon as CSS
		elseif ( ! empty( $field['icon_css'] ) ) {

			// Just directly return the value as icon
			$icons[] = [
				'name' => $value,
				'icon' => sprintf( '<i class="%s"></i>', $value ),
			];
		}

		// STEP: Parse icons from file (JSON)
		elseif ( ! empty( $field['icon_file'] ) ) {
			$file     = $field['icon_file'];
			$icon_set = $field['icon_set'];

			// If file does not exists, return empty array
			if ( ! file_exists( $file ) ) {
				return [];
			}

			// Get file content and decode it
			$data = json_decode( file_get_contents( $file ), true );

			// If json decode failed, return empty array
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return [];
			}

			// Loop over all parsed icons and add them to the list
			foreach ( $data as $key => $icon ) {

				// Default: Font Awesome (Free & Pro)
				if ( $icon_set === 'font-awesome-free' || $icon_set === 'font-awesome-pro' ) {

					// To be compatible with FA Pro, we need to loop over all styles,
					// because FA Pro can have more styles than FA Free (only one).
					foreach ( $icon['styles'] as $style ) {
						$icons[] = [
							'name' => "fa-{$style} fa-{$key}",
							'svg'  => $icon['svg'][ $style ]['raw'],
						];
					}
				}

				 // JSON file that contains SVG icons
				elseif ( is_string( $key ) ) {

					  // If it's array - custom label (icon:svg_json) like (icon:{'svg':'<svg>...</svg>', label:'Custom Label'})
					if ( is_array( $icon ) ) {
						$svg = $icon['svg'] ?? null;
					}
					  // If it's string - default label (icon:svg)
					else {
						$svg = str_contains( $icon, '<svg' ) ? $icon : null;
					}

					  // Only add icon if it has a SVG
					if ( isset( $svg ) && ! is_null( $svg ) ) {
						$icons[] = [
							'name' => $key,
							'svg'  => $svg,
						];
					}
				}

					// If nothing is found, return empty array
				else {
					return [];
				}
			}
		}

			return $icons;
	}

}
