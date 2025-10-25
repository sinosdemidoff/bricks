<?php
namespace Bricks\Integrations\Form\Actions;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Update_Post extends Base {
	/**
	 * Update an existing post
	 *
	 * @since 2.1
	 */
	public function run( $form ) {
		$form_settings = $form->get_settings();
		$form_fields   = $form->get_fields();
		$post_id       = $form_settings['updatePostId'] ?? '';

		// No specified post ID, try to get it from form field 'postId' first
		if ( ! $post_id ) {
			// Use form object method instead of direct $_POST access (already sanitized with absint in form_submit)
			$post_id = $form->get_post_id();

			// Get post ID from loopId if set (sanitize with absint)
			if ( ! empty( $form_fields['loopId'] ) ) {
				$post_id = absint( $form_fields['loopId'] );
			}
		}

		// Sanitize post_id (additional safety check)
		$post_id = absint( $post_id );

		// Return: Current user is not allowed to update this post
		if ( empty( $form_settings['updatePostDisableCapabilityCheck'] ) && ! current_user_can( 'edit_post', $post_id ) ) {
			$form->set_result(
				[
					'action'  => $this->name,
					'type'    => 'error',
					'message' => esc_html__( 'You do not have the required capability to perform this action.', 'bricks' ),
				]
			);

			return;
		}

		// Return: No post with $post_id found
		if ( ! get_post( $post_id ) ) {
			$form->set_result(
				[
					'action'  => $this->name,
					'type'    => 'error',
					'message' => "No post found with ID: $post_id",
				]
			);

			return;
		}

		$post_data = [ 'ID' => $post_id ];

		// STEP: Update fields
		if ( isset( $form_settings['updatePostTitle'] ) ) {
			$post_data['post_title'] = $form->get_field_value( $form_settings['updatePostTitle'] );

			// Sanitize post_title
			$post_data['post_title'] = sanitize_text_field( $post_data['post_title'] );
		}

		if ( isset( $form_settings['updatePostContent'] ) ) {
			$rendered_content = $form->get_field_value( $form_settings['updatePostContent'] );

			// Sanitize post_content
			$post_data['post_content'] = wp_kses_post( $rendered_content );
		}

		if ( isset( $form_settings['updatePostExcerpt'] ) ) {
			$post_data['post_excerpt'] = $form->get_field_value( $form_settings['updatePostExcerpt'] );

			// Sanitize post_excerpt
			$post_data['post_excerpt'] = wp_kses_post( $post_data['post_excerpt'] );
		}

		if ( isset( $form_settings['updatePostStatus'] ) ) {
			$post_data['post_status'] = $form_settings['updatePostStatus'];

			// Sanitize post_status
			$post_data['post_status'] = sanitize_text_field( $post_data['post_status'] );
		}

		// Update post (if there's data to update)
		if ( count( $post_data ) > 1 ) {
			$updated_post_id = wp_update_post( $post_data, true );

			if ( is_wp_error( $updated_post_id ) ) {
				// Error handling
				$form->set_result(
					[
						'action'  => $this->name,
						'type'    => 'error',
						'message' => $updated_post_id->get_error_message(),
					]
				);

				return;
			}
		}

		// Set/remove featured image
		if ( ! empty( $form_settings['updatePostFeaturedImage'] ) ) {
			$featured_image_id = $form->get_field_value( $form_settings['updatePostFeaturedImage'] );

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
		if ( ! empty( $form_settings['updatePostTaxonomies'] ) ) {
			foreach ( $form_settings['updatePostTaxonomies'] as $taxonomy ) {
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

		// Handle post meta fields update
		$this->update_meta_fields( $post_id, $form, $form_settings, $form_fields );

		// Trigger save_post once again after all meta updates to ensure all hooks are fired for query filters (#86c5gxrya;)
		if ( \Bricks\Helpers::enabled_query_filters() ) {
			do_action( 'save_post', $post_id, get_post( $post_id ), true );
		}

		// Success handling
		$form->set_result(
			[
				'action'  => $this->name,
				'type'    => 'success',
				'post_id' => $updated_post_id ?? $post_id,
			]
		);
	}

	/**
	 * Updates meta fields of a post based on form field mappings.
	 *
	 * Iterates over field mappings defined in the form settings and updates the meta fields
	 * of the specified post with the corresponding values from the form fields.
	 *
	 * @param int    $post_id The ID of the post to update.
	 * @param object $form The form object.
	 * @param array  $form_settings Form settings containing field mappings.
	 * @param array  $form_fields Array of form field values.
	 */
	private function update_meta_fields( $post_id, $form, $form_settings, $form_fields ) {
		if ( empty( $form_settings['updatePostMeta'] ) ) {
			return;
		}

		// Access uploaded files
		$uploaded_files = $form->get_uploaded_files();

		foreach ( $form_settings['updatePostMeta'] ?? [] as $mapping ) {
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

			if ( $is_file_upload && isset( $uploaded_files[ "form-field-$field_id" ] ) ) {
				// Assign file data
				$meta_value = $uploaded_files[ "form-field-$field_id" ];
				// Since it's a file upload, no sanitization is applied here
			} else {
				// Handle non-file upload fields
				$meta_value          = $form->get_field_value( $field_id );
				$sanitization_method = $mapping['sanitizationMethod'] ?? 'sanitize_text_field';
				$meta_value          = $this->sanitize_meta_value( $meta_value, $sanitization_method );
			}

			$meta_value = apply_filters( 'bricks/form/update_post/meta_value', $meta_value, $meta_key, $post_id, $form_fields );

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

				// TODO: checkbox_list saves every value as separate entry in the DB, so this doesn't work properly yet
				rwmb_set_meta( $post_id, $meta_box_field_key, $meta_value );
				continue;
			}

			// Fallback to update_post_meta if ACF function is not available
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
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
