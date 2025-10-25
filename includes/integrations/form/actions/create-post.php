<?php
namespace Bricks\Integrations\Form\Actions;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Create_Post extends Base {
	/**
	 * Create a new post
	 *
	 * @since 2.1
	 */
	public function run( $form ) {
		$form_settings = $form->get_settings();
		$form_fields   = $form->get_fields();
		$post_type     = $form_settings['createPostType'] ?? '';

		// Return: Current user is not allowed to create posts of this type
		if ( empty( $form_settings['createPostDisableCapabilityCheck'] ) ) {
			$post_type_object = get_post_type_object( $post_type );

			if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
				$form->set_result(
					[
						'action'  => $this->name,
						'type'    => 'error',
						'message' => esc_html__( 'You do not have the required capability to perform this action.', 'bricks' ),
					]
				);

				return;
			}
		}

		// Return: No form settings or fields
		if ( ! is_array( $form_settings ) || ! is_array( $form_fields ) ) {
			$form->set_result(
				[
					'action'  => $this->name,
					'type'    => 'error',
					'message' => 'Invalid form settings or fields.',
				]
			);

			return;
		}

		// Return: Invalid post type
		if ( ! post_type_exists( $post_type ) ) {
			$form->set_result(
				[
					'action'  => $this->name,
					'type'    => 'error',
					'message' => "Invalid post type: $post_type",
				]
			);

			return;
		}

		// Initialize post data with post_type
		$post_data = [ 'post_type' => $post_type ];

		// Conditional field assignments
		if ( ! empty( $form_settings['createPostTitle'] ) ) {
			$post_data['post_title'] = $form->get_field_value( $form_settings['createPostTitle'] );

			// Sanitize post_title
			$post_data['post_title'] = sanitize_text_field( $post_data['post_title'] );
		}

		if ( ! empty( $form_settings['createPostContent'] ) ) {
			$rendered_content = $form->get_field_value( $form_settings['createPostContent'] );

			// Sanitize post_content
			$post_data['post_content'] = wp_kses_post( $rendered_content );
		}

		if ( ! empty( $form_settings['createPostExcerpt'] ) ) {
			$post_data['post_excerpt'] = $form->get_field_value( $form_settings['createPostExcerpt'] );

			// Sanitize post_excerpt
			$post_data['post_excerpt'] = sanitize_text_field( $post_data['post_excerpt'] );
		}

		if ( ! empty( $form_settings['createPostStatus'] ) ) {
			$post_data['post_status'] = $form_settings['createPostStatus'];

			// Sanitize post_status
			$post_data['post_status'] = sanitize_text_field( $post_data['post_status'] );
		}

		// Default to the current logged-in user, otherwise default to the site's super admin
		$current_user_id = get_current_user_id();

		if ( $current_user_id ) {
			$post_data['post_author'] = $current_user_id;
		} else {
			// Default to the site's super admin
			$super_admins             = get_super_admins();
			$super_admin_id           = ! empty( $super_admins ) ? username_exists( $super_admins[0] ) : 1; // Assuming super admin exists or defaulting to user ID 1
			$post_data['post_author'] = $super_admin_id;
		}

		// Handle post meta fields
		$post_data['meta_input'] = $this->get_meta_input( $form, $form_settings, $form_fields );

		// Insert new post
		$post_id = wp_insert_post( $post_data );

		// Return: Error
		if ( is_wp_error( $post_id ) ) {
			$form->set_result(
				[
					'action'  => $this->name,
					'type'    => 'error',
					'message' => $post_id->get_error_message(),
				]
			);

			return;
		}

		// Set/remove featured image
		if ( ! empty( $form_settings['createPostFeaturedImage'] ) ) {
			$featured_image_id = $form->get_field_value( $form_settings['createPostFeaturedImage'] );

			if ( $featured_image_id ) {
				// Sanitize and validate featured image ID
				$featured_image_id = intval( $featured_image_id );

				// Verify the attachment exists and is actually an image
				if ( $featured_image_id && wp_attachment_is_image( $featured_image_id ) ) {
					set_post_thumbnail( $post_id, $featured_image_id );
				}
			} else {
				delete_post_thumbnail( $post_id );
			}
		}

		// Set taxonomy terms
		if ( ! empty( $form_settings['createPostTaxonomies'] ) ) {
			foreach ( $form_settings['createPostTaxonomies'] as $taxonomy ) {
				$terms = $form->get_field_value( $taxonomy['fieldId'], $form_fields );
				if ( ! empty( $terms ) ) {
					if ( is_string( $terms ) ) {
						$terms = explode( ',', $terms );
						$terms = array_map( 'trim', $terms );
					}

					$terms = array_map( 'intval', $terms );

					wp_set_post_terms( $post_id, $terms, $taxonomy['taxonomy'] );
				} else {
					wp_set_post_terms( $post_id, [], $taxonomy['taxonomy'] ); // Clear terms if none provided
				}
			}
		}

		/**
		 * STEP: Handle post meta fields (including ACF & Meta Box fields)
		 */
		$post_meta = $this->get_meta_input( $form, $form_settings, $form_fields );

		if ( is_array( $post_meta ) ) {
			foreach ( $post_meta as $meta_key => $meta_value ) {
				// Update ACF field using update_field
				$acf_field_key = \Bricks\Integrations\Form\Init::get_acf_field_key_from_meta_key( $meta_key, $post_id );
				if ( $acf_field_key && function_exists( 'update_field' ) ) {
					// Convert comma-separated image IDs to array for ACF gallery fields
					if ( is_string( $meta_value ) && strpos( $meta_value, ',' ) !== false ) {
						$meta_value = array_map( 'intval', explode( ',', $meta_value ) );
					}

					update_field( $acf_field_key, $meta_value, $post_id );
					continue;
				}

				// Update Meta Box field using rwmb_set_meta
				$meta_box_field_key = \Bricks\Integrations\Form\Init::get_meta_box_field_key_from_meta_key( $meta_key, $post_id );
				if ( $meta_box_field_key && function_exists( 'rwmb_set_meta' ) ) {
					// Convert comma-separated image IDs to array for Meta Box image_advanced fields
					if ( is_string( $meta_value ) && strpos( $meta_value, ',' ) !== false ) {
						$meta_value = array_map( 'intval', explode( ',', $meta_value ) );
					}

					rwmb_set_meta( $post_id, $meta_box_field_key, $meta_value );
					continue;
				}

				// Fallback to update_post_meta if ACF function is not available
				update_post_meta( $post_id, $meta_key, $meta_value );
			}

			// Trigger save_post once again after all meta updates to ensure all hooks are fired for query filters (#86c5gxrya;)
			if ( \Bricks\Helpers::enabled_query_filters() ) {
				do_action( 'save_post', $post_id, get_post( $post_id ), true );
			}
		}

		// Success handling
		$form->set_result(
			[
				'action'  => $this->name,
				'type'    => 'success',
				'post_id' => $post_id,
			]
		);
	}

	/**
	 * Prepares meta input data for creating a new post.
	 *
	 * @param object $form The form object.
	 * @param array  $form_settings Form settings containing field mappings.
	 * @param array  $form_fields Array of form field values.
	 * @return array Array of meta keys and values to be used as meta input.
	 */
	private function get_meta_input( $form, $form_settings, $form_fields ) {
		if ( empty( $form_settings['createPostMeta'] ) ) {
			return;
		}

		$meta_input     = [];
		$uploaded_files = $form->get_uploaded_files();

		foreach ( $form_settings['createPostMeta'] as $mapping ) {
			$meta_key = $mapping['metaKey'] ?? '';

			// Sanitize meta key to prevent injection
			$meta_key = sanitize_key( $meta_key );

			// Skip if meta key is empty after sanitization
			if ( empty( $meta_key ) ) {
				continue;
			}

			$field_id = $mapping['metaValue'] ?? '';

			$is_file_upload = false;
			if ( $field_id ) {
				// Loop through the form settings fields to find the type of the field
				foreach ( $form_settings['fields'] as $field_setting ) {
					if ( $field_setting['id'] === $field_id && $field_setting['type'] === 'file' ) {
						$is_file_upload = true;
						break;
					}
				}
			}

			if ( $is_file_upload ) {
				if ( isset( $uploaded_files[ "form-field-$field_id" ] ) ) {
					// Assign all file data
					$meta_value = $uploaded_files[ "form-field-$field_id" ];
				} else {
					$meta_value = []; // No file uploaded or field_id not found
				}
			} else {
				// Handle non-file upload fields
				$meta_value = $form->get_field_value( $field_id );
			}

			// Apply sanitization based on the determined method
			$sanitization_method = $mapping['sanitizationMethod'] ?? '';
			$meta_value          = $this->sanitize_meta_value( $meta_value, $sanitization_method );

			// Apply filtering
			$meta_value = apply_filters( 'bricks/form/create_post/meta_value', $meta_value, $meta_key, $form_settings, $form_fields );

			$meta_input[ $meta_key ] = $meta_value;
		}

		return $meta_input;
	}

	/**
	 * Sanitize meta value based on the specified method
	 *
	 * @param mixed  $value The value to sanitize.
	 * @param string $method The sanitization method.
	 * @return mixed The sanitized value.
	 */
	private function sanitize_meta_value( $value, $method ) {
		// If value is an array, sanitize each element
		if ( is_array( $value ) ) {
			return array_map(
				function( $single_value ) use ( $method ) {
					return $this->sanitize_meta_value( $single_value, $method );
				},
				$value
			);
		}

		switch ( $method ) {
			case 'intval':
				return intval( $value );
			case 'floatval':
				return floatval( $value );
			case 'sanitize_email':
				return sanitize_email( $value );
			case 'esc_url':
				return esc_url( $value );
			case 'wp_kses_post':
				return wp_kses_post( $value );
			default:
				return sanitize_text_field( $value );
		}
	}
}
