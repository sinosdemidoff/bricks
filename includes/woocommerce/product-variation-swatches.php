<?php
namespace Bricks\Woocommerce;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Product_Variation_Swatches {
	public function __construct() {
		// Only initialize if variation swatches setting is enabled
		if ( ! \Bricks\Database::get_setting( 'woocommerceUseVariationSwatches' ) ) {
			return;
		}

		// Add fields to attribute add/edit forms
		add_action( 'woocommerce_after_edit_attribute_fields', [ $this, 'add_attribute_swatch_type_settings' ] );
		add_action( 'woocommerce_attribute_updated', [ $this, 'save_attribute_swatch_type' ], 10, 3 );

		// Register hooks for all product attribute taxonomies
		add_action( 'init', [ $this, 'register_attribute_term_hooks' ], 100 );

		// Save the term fields
		add_action( 'created_term', [ $this, 'save_attribute_term_fields' ], 10, 3 );
		add_action( 'edit_term', [ $this, 'save_attribute_term_fields' ], 10, 3 );

		// Enqueue WordPress media scripts on attribute term pages
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_media_scripts' ] );

		// Add swatch type column to attribute taxonomy pages
		add_action( 'init', [ $this, 'register_swatch_type_column' ], 100 );
	}

	/**
	 * Enqueue WordPress media scripts on attribute term pages and attribute settings page
	 */
	public function enqueue_media_scripts() {
		// Check if user has required capability
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Check if we're on product attribute pages
		$is_attribute_page = (
			// Attribute settings page
			$screen->id === 'product_page_product_attributes' ||
			// Attribute term list/edit pages
			( in_array( $screen->base, [ 'edit-tags', 'term' ], true ) &&
			taxonomy_is_product_attribute( $screen->taxonomy ) )
		);

		if ( $is_attribute_page ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Add swatch type settings to product attribute configuration
	 *
	 * @since 2.0
	 */
	public function add_attribute_swatch_type_settings() {
		$attribute_id = 0;

		// Get attribute ID when editing existing attribute
		if ( isset( $_GET['edit'] ) ) {
			$attribute_id = absint( $_GET['edit'] );

			// Verify this is a valid attribute
			$attribute = wc_get_attribute( $attribute_id );
			if ( ! $attribute ) {
				return;
			}
		}

		// Get current swatch type for this attribute
		$swatch_type = $attribute_id ? get_term_meta( $attribute_id, 'bricks_swatch_type', true ) : '';

		// Get default values for each type
		$default_color       = get_term_meta( $attribute_id, 'bricks_swatch_default_color', true );
		$default_label       = get_term_meta( $attribute_id, 'bricks_swatch_default_label', true );
		$default_image       = get_term_meta( $attribute_id, 'bricks_swatch_default_image', true );
		$use_variation_image = get_term_meta( $attribute_id, 'bricks_swatch_use_variation_image', true );

		// Add nonce field
		wp_nonce_field( 'bricks_update_attribute_swatch_type', 'bricks_attribute_swatch_nonce' );

		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="swatch_type"><?php esc_html_e( 'Swatch type', 'bricks' ); ?></label>
			</th>
			<td>
				<select name="swatch_type" id="swatch_type">
					<option value=""><?php esc_html_e( 'None', 'bricks' ); ?></option>
					<option value="color" <?php selected( $swatch_type, 'color' ); ?>><?php esc_html_e( 'Color', 'bricks' ); ?></option>
					<option value="label" <?php selected( $swatch_type, 'label' ); ?>><?php esc_html_e( 'Label', 'bricks' ); ?></option>
					<option value="image" <?php selected( $swatch_type, 'image' ); ?>><?php esc_html_e( 'Image', 'bricks' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Choose how to display product variations.', 'bricks' ); ?></p>
			</td>
		</tr>

		<tr class="form-field bricks-swatch-fallback bricks-swatch-fallback-color" style="display: none;">
			<th scope="row" valign="top">
				<label for="swatch_default_color"><?php esc_html_e( 'Fallback color', 'bricks' ); ?></label>
			</th>
			<td>
				<div class="bricks-color-swatch-wrapper">
					<?php if ( empty( $default_color ) || $default_color === 'none' ) : ?>
						<div style="display: inline-block">
							<button type="button" class="button show-color-picker" data-input-id="swatch_default_color">
								<?php esc_html_e( 'Select color', 'bricks' ); ?>
							</button>
							<input type="hidden" name="swatch_default_color" id="swatch_default_color" value="none">
						</div>
					<?php else : ?>
						<input
							type="color"
							id="swatch_default_color"
							name="swatch_default_color"
							value="<?php echo esc_attr( $default_color ); ?>"
						/>
						<button type="button" class="button bricks-remove-color" data-input="swatch_default_color">
							<?php esc_html_e( 'Remove', 'bricks' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Default color for terms without a specific color', 'bricks' ); ?></p>
			</td>
		</tr>

		<tr class="form-field bricks-swatch-fallback bricks-swatch-fallback-label" style="display: none;">
			<th scope="row" valign="top">
				<label for="swatch_default_label"><?php esc_html_e( 'Fallback label', 'bricks' ); ?></label>
			</th>
			<td>
				<input
					type="text"
					id="swatch_default_label"
					name="swatch_default_label"
					value="<?php echo esc_attr( $default_label ); ?>"
				>
				<p class="description"><?php esc_html_e( 'Fallback label for terms without a specific label', 'bricks' ); ?></p>
			</td>
		</tr>

		<tr class="form-field bricks-swatch-fallback bricks-swatch-fallback-image" style="display: none;">
			<th scope="row" valign="top">
				<label for="swatch_default_image"><?php esc_html_e( 'Fallback image', 'bricks' ); ?></label>
			</th>
			<td>
				<?php
				$default_image_url = $default_image ? wp_get_attachment_image_url( $default_image, 'thumbnail' ) : '';
				if ( $default_image_url ) :
					?>
					<img src="<?php echo esc_url( $default_image_url ); ?>" class="swatch-image-preview" style="max-width: 150px; display: block; margin-bottom: 8px;">
				<?php endif; ?>

				<input
					type="hidden"
					id="swatch_default_image"
					name="swatch_default_image"
					value="<?php echo esc_attr( $default_image ); ?>"
				>

				<button type="button" class="button bricks_swatch_image_upload">
					<?php esc_html_e( 'Upload/Add image', 'bricks' ); ?>
				</button>

				<button type="button" class="button bricks_swatch_image_remove" <?php echo $default_image ? '' : 'style="display:none"'; ?>>
					<?php esc_html_e( 'Remove image', 'bricks' ); ?>
				</button>

				<p style="margin-top:8px;">
					<label for="swatch_fallback_variation_image">
						<input type="checkbox" id="swatch_fallback_variation_image" name="swatch_fallback_variation_image" value="1" <?php checked( $use_variation_image, '1' ); ?> />
						<?php esc_html_e( 'Use product variation image', 'bricks' ); ?>
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'If enabled Bricks will try to use the image of the matching product variation when no term-specific image swatch is set.', 'bricks' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save swatch type setting for product attribute
	 *
	 * @since 2.0
	 */
	public function save_attribute_swatch_type( $attribute_id, $attribute, $old_attribute ) {
		if ( ! isset( $_POST['bricks_attribute_swatch_nonce'] ) || ! wp_verify_nonce( $_POST['bricks_attribute_swatch_nonce'], 'bricks_update_attribute_swatch_type' ) ) {
			return;
		}

		if ( isset( $_POST['swatch_type'] ) ) {
			$swatch_type = sanitize_text_field( wp_unslash( $_POST['swatch_type'] ) );
			$valid_types = [ '', 'color', 'label', 'image' ];

			if ( in_array( $swatch_type, $valid_types, true ) ) {
				update_term_meta( $attribute_id, 'bricks_swatch_type', $swatch_type );

				// Save default values based on type
				switch ( $swatch_type ) {
					case 'color':
						if ( isset( $_POST['swatch_default_color'] ) ) {
							$color = sanitize_text_field( wp_unslash( $_POST['swatch_default_color'] ) );
							// Save 'none' if empty or 'none', otherwise validate hex color
							if ( $color === 'none' || empty( $color ) ) {
								update_term_meta( $attribute_id, 'bricks_swatch_default_color', 'none' );
							} elseif ( sanitize_hex_color( $color ) ) {
								update_term_meta( $attribute_id, 'bricks_swatch_default_color', $color );
							}
						}
						break;

					case 'label':
						if ( isset( $_POST['swatch_default_label'] ) ) {
							$label = sanitize_text_field( wp_unslash( $_POST['swatch_default_label'] ) );
							update_term_meta( $attribute_id, 'bricks_swatch_default_label', $label );
						}
						break;

					case 'image':
						if ( isset( $_POST['swatch_default_image'] ) && current_user_can( 'upload_files' ) ) {
							$image = sanitize_text_field( wp_unslash( $_POST['swatch_default_image'] ) );
							update_term_meta( $attribute_id, 'bricks_swatch_default_image', $image );
						}

						// Save "Use product variation image" fallback option
						if ( isset( $_POST['swatch_fallback_variation_image'] ) ) {
							update_term_meta( $attribute_id, 'bricks_swatch_use_variation_image', '1' );
						} else {
							delete_term_meta( $attribute_id, 'bricks_swatch_use_variation_image' );
						}

						break;
				}
			}
		}
	}

	/**
	 * Add swatch fields when adding a new attribute term
	 *
	 * @since 2.0
	 */
	public function add_attribute_term_fields( $term = false ) {
		$term_id  = is_object( $term ) ? $term->term_id : 0;
		$taxonomy = is_object( $term ) ? $term->taxonomy : ( isset( $_GET['taxonomy'] ) ? $_GET['taxonomy'] : '' );

		// Get attribute swatch type
		$attribute_id = wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) );
		$swatch_type  = get_term_meta( $attribute_id, 'bricks_swatch_type', true );

		// Add nonce field
		wp_nonce_field( 'bricks_save_attribute_term_fields', 'bricks_attribute_term_nonce' );

		switch ( $swatch_type ) {
			case 'color':
				$color_value = $term_id ? get_term_meta( $term_id, 'bricks_swatch_color_value', true ) : '';
				?>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="bricks_swatch_color_value"><?php esc_html_e( 'Color', 'bricks' ); ?></label>
					</th>
					<td>
						<div class="bricks-color-swatch-wrapper">
							<input
								type="color"
								id="bricks_swatch_color_value"
								name="swatch_color_value"
								value="<?php echo esc_attr( $color_value ); ?>"
							/>
							<button type="button" class="button bricks-remove-color" data-input="bricks_swatch_color_value">
								<?php esc_html_e( 'Remove', 'bricks' ); ?>
							</button>
						</div>
						<p class="description"><?php esc_html_e( 'Choose a color for this attribute value.', 'bricks' ); ?></p>
					</td>
				</tr>
				<?php
				break;

			case 'label':
				$label_value = $term_id ? get_term_meta( $term_id, 'bricks_swatch_label_value', true ) : '';
				?>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="bricks_swatch_label_value"><?php esc_html_e( 'Label', 'bricks' ); ?></label>
					</th>
					<td>
						<input type="text" id="bricks_swatch_label_value" name="swatch_label_value" value="<?php echo esc_attr( $label_value ); ?>" />
						<p class="description"><?php esc_html_e( 'Enter a custom label for this attribute value (optional).', 'bricks' ); ?></p>
					</td>
				</tr>
				<?php
				break;

			case 'image':
				if ( ! current_user_can( 'upload_files' ) ) {
					return;
				}

				$image_id  = $term_id ? get_term_meta( $term_id, 'bricks_swatch_image_value', true ) : '';
				$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
				?>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="bricks_swatch_image_value"><?php esc_html_e( 'Image', 'bricks' ); ?></label>
					</th>
					<td>
						<?php if ( $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" class="swatch-image-preview" style="max-width: 150px; display: block; margin-bottom: 8px;">
						<?php endif; ?>

						<input
							type="hidden"
							id="bricks_swatch_image_value"
							name="swatch_image_value"
							value="<?php echo esc_attr( $image_id ); ?>"
						>

						<button type="button" class="button bricks_swatch_image_upload">
							<?php esc_html_e( 'Upload/Add image', 'bricks' ); ?>
						</button>

						<button type="button" class="button bricks_swatch_image_remove" <?php echo $image_id ? '' : 'style="display:none"'; ?>>
							<?php esc_html_e( 'Remove image', 'bricks' ); ?>
						</button>

						<p class="description">
							<?php esc_html_e( 'Choose an image for this attribute value.', 'bricks' ); ?>
						</p>
					</td>
				</tr>
				<?php
				break;
		}
	}

	/**
	 * Add swatch fields when editing an attribute term
	 *
	 * @since 2.0
	 */
	public function edit_attribute_term_fields( $term, $taxonomy ) {
		// Return if not a product attribute
		if ( ! taxonomy_is_product_attribute( $taxonomy ) ) {
			return;
		}

		// Get attribute ID from taxonomy
		$attribute_id = wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) );
		$swatch_type  = get_term_meta( $attribute_id, 'bricks_swatch_type', true );

		// Add nonce field
		wp_nonce_field( 'bricks_save_attribute_term_fields', 'bricks_attribute_term_nonce' );

		switch ( $swatch_type ) {
			case 'label':
				$label_value = get_term_meta( $term->term_id, 'bricks_swatch_label_value', true );
				?>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="bricks_swatch_label_value"><?php esc_html_e( 'Label', 'bricks' ); ?></label>
					</th>
					<td>
						<input type="text" id="bricks_swatch_label_value" name="swatch_label_value" value="<?php echo esc_attr( $label_value ); ?>" />
						<p class="description"><?php esc_html_e( 'Enter a custom label for this attribute value (optional).', 'bricks' ); ?></p>
					</td>
				</tr>
				<?php
				break;

			case 'color':
				$color_value = get_term_meta( $term->term_id, 'bricks_swatch_color_value', true );
				?>
				<tr class="form-field">
					<th scope="row"><label for="swatch_color_value"><?php esc_html_e( 'Color', 'bricks' ); ?></label></th>
					<td>
						<div class="bricks-color-swatch-wrapper">
							<?php if ( empty( $color_value ) || $color_value === 'none' ) : ?>
								<div style="display: inline-block">
									<button type="button" class="button show-color-picker" data-input-id="swatch_color_value">
										<?php esc_html_e( 'Select Color', 'bricks' ); ?>
									</button>
									<input type="hidden" name="swatch_color_value" id="swatch_color_value" value="none">
								</div>
							<?php else : ?>
								<input type="color" name="swatch_color_value" id="swatch_color_value" value="<?php echo esc_attr( $color_value ); ?>">
								<button type="button" class="button bricks-remove-color" data-input="swatch_color_value">
									<?php esc_html_e( 'Remove', 'bricks' ); ?>
								</button>
							<?php endif; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Choose a color for this attribute term', 'bricks' ); ?></p>
					</td>
				</tr>
				<?php
				break;

			case 'image':
				if ( ! current_user_can( 'upload_files' ) ) {
					return;
				}

				$image_id  = get_term_meta( $term->term_id, 'bricks_swatch_image_value', true );
				$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
				?>
				<tr class="form-field">
					<th scope="row"><label for="swatch_image_value"><?php esc_html_e( 'Image', 'bricks' ); ?></label></th>
					<td>
						<?php if ( $image_url ) : ?>
							<img src="<?php echo esc_url( $image_url ); ?>" class="swatch-image-preview" style="max-width: 150px; display: block; margin-bottom: 8px;">
						<?php endif; ?>

						<input
							type="hidden"
							name="swatch_image_value"
							id="swatch_image_value"
							value="<?php echo esc_attr( $image_id ); ?>"
						>

						<button type="button" class="button bricks_swatch_image_upload">
							<?php esc_html_e( 'Upload/Add image', 'bricks' ); ?>
						</button>

						<button type="button" class="button bricks_swatch_image_remove" <?php echo $image_id ? '' : 'style="display:none"'; ?>>
							<?php esc_html_e( 'Remove image', 'bricks' ); ?>
						</button>

						<p class="description"><?php esc_html_e( 'Choose an image for this attribute term', 'bricks' ); ?></p>
					</td>
				</tr>
				<?php
				break;
		}
	}

	/**
	 * Save the term fields
	 *
	 * @since 2.0
	 */
	public function save_attribute_term_fields( $term_id, $tt_id = '', $taxonomy = '' ) {
		if ( ! isset( $_POST['bricks_attribute_term_nonce'] ) || ! wp_verify_nonce( $_POST['bricks_attribute_term_nonce'], 'bricks_save_attribute_term_fields' ) ) {
			return;
		}

		// Get attribute swatch type
		$attribute_id = wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) );
		$swatch_type  = get_term_meta( $attribute_id, 'bricks_swatch_type', true );

		switch ( $swatch_type ) {
			case 'label':
				if ( isset( $_POST['swatch_label_value'] ) ) {
					$label  = sanitize_text_field( wp_unslash( $_POST['swatch_label_value'] ) );
					$result = update_term_meta( $term_id, 'bricks_swatch_label_value', $label );
				}
				break;

			case 'color':
				if ( isset( $_POST['swatch_color_value'] ) ) {
					$color = sanitize_text_field( wp_unslash( $_POST['swatch_color_value'] ) );
					// Save 'none' if empty or 'none', otherwise validate hex color
					if ( $color === 'none' || empty( $color ) ) {
						update_term_meta( $term_id, 'bricks_swatch_color_value', 'none' );
					} elseif ( sanitize_hex_color( $color ) ) {
						update_term_meta( $term_id, 'bricks_swatch_color_value', $color );
					}
				}
				break;

			case 'image':
				if ( isset( $_POST['swatch_image_value'] ) && current_user_can( 'upload_files' ) ) {
					$image_value = sanitize_text_field( wp_unslash( $_POST['swatch_image_value'] ) );
					update_term_meta( $term_id, 'bricks_swatch_image_value', $image_value );
				}
				break;
		}
	}

	/**
	 * Register hooks for all product attribute taxonomies
	 *
	 * @since 2.0
	 */
	public function register_attribute_term_hooks() {
		// Get all product attribute taxonomies
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $tax ) {
				$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );

				// Add form fields
				add_action( "{$taxonomy}_add_form_fields", [ $this, 'add_attribute_term_fields' ] );
				add_action( "{$taxonomy}_edit_form_fields", [ $this, 'edit_attribute_term_fields' ], 10, 2 );
			}
		}
	}

	/**
	 * Register swatch type column for all product attribute taxonomies
	 */
	public function register_swatch_type_column() {
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $tax ) {
				$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );

				// Get the swatch type for this taxonomy
				$attribute_id = wc_attribute_taxonomy_id_by_name( $tax->attribute_name );
				$swatch_type  = get_term_meta( $attribute_id, 'bricks_swatch_type', true );

				// Only add column if swatch type is defined
				if ( ! empty( $swatch_type ) ) {
					// Add the column
					add_filter( "manage_edit-{$taxonomy}_columns", [ $this, 'add_swatch_type_column' ] );

					// Populate the column
					add_filter( "manage_{$taxonomy}_custom_column", [ $this, 'populate_swatch_type_column' ], 10, 3 );
				}
			}
		}
	}

	/**
	 * Add swatch type column to the taxonomy table
	 */
	public function add_swatch_type_column( $columns ) {
		$new_columns = [];
		$taxonomy    = isset( $_GET['taxonomy'] ) ? sanitize_text_field( $_GET['taxonomy'] ) : '';

		if ( ! $taxonomy ) {
			return $columns;
		}

		// Get attribute ID and swatch type
		$attribute_id = wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) );
		$swatch_type  = get_term_meta( $attribute_id, 'bricks_swatch_type', true );

		// Get column title based on swatch type
		$column_title = '';
		switch ( $swatch_type ) {
			case 'color':
				$column_title = esc_html__( 'Color', 'bricks' );
				break;
			case 'label':
				$column_title = esc_html__( 'Label', 'bricks' );
				break;
			case 'image':
				$column_title = esc_html__( 'Image', 'bricks' );
				break;
		}

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			// Add our column after the Name column
			if ( $key === 'name' && ! empty( $column_title ) ) {
				$new_columns['swatch_type'] = $column_title;
			}
		}

		return $new_columns;
	}

	/**
	 * Populate the swatch type column
	 */
	public function populate_swatch_type_column( $content, $column_name, $term_id ) {
		if ( $column_name !== 'swatch_type' ) {
			return $content;
		}

		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( $_GET['taxonomy'] ) : '';
		if ( ! $taxonomy ) {
			return '&mdash;';
		}

		// Get attribute ID and swatch type
		$attribute_id = wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) );
		$swatch_type  = get_term_meta( $attribute_id, 'bricks_swatch_type', true );

		switch ( $swatch_type ) {
			case 'color':
				$color = get_term_meta( $term_id, 'bricks_swatch_color_value', true );
				if ( empty( $color ) || $color === 'none' ) {
					return '&mdash;';
				}
				return sprintf( '<span class="bricks-swatch-preview bricks-swatch-color-preview" style="background-color:%1$s;" title="%1$s"></span>', esc_attr( $color ) );

			case 'image':
				$image_id = get_term_meta( $term_id, 'bricks_swatch_image_value', true );
				if ( empty( $image_id ) ) {
					return '&mdash;';
				}
				$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
				return sprintf( '<img src="%s" alt="" class="bricks-swatch-preview bricks-swatch-image-preview">', esc_url( $image_url ) );

			case 'label':
				$label = get_term_meta( $term_id, 'bricks_swatch_label_value', true );
				return ! empty( $label ) ? esc_html( $label ) : '&mdash;';

			default:
				return '&mdash;';
		}
	}
}
