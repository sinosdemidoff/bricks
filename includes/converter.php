<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class Converter {
	public function __construct() {
		add_action( 'wp_ajax_bricks_get_converter_items', [ $this, 'get_converter_items' ] );
		add_action( 'wp_ajax_bricks_run_converter', [ $this, 'run_converter' ] );

		add_action( 'wp_ajax_bricks_convert_global_elements', [ $this, 'convert_global_elements' ] );
	}

	/**
	 * Convert global elements to components
	 */
	public function convert_global_elements() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		$post_id          = $_POST['postId'] ?? null;
		$unlink_nestables = $_POST['unlinkNestables'] ?? false;
		$post_meta_key    = BRICKS_DB_PAGE_CONTENT;
		$components       = get_option( BRICKS_DB_COMPONENTS, [] );

		Elements::load_elements();

		// STEP: Convert all global elements of specific post to components
		if ( $post_id ) {
			// Get all Bricks elements of post
			$elements  = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );
			$post_type = get_post_type( $post_id );

			if ( ! $elements && $post_type === BRICKS_DB_TEMPLATE_SLUG ) {
				$elements = get_post_meta( $post_id, BRICKS_DB_PAGE_HEADER, true );

				if ( ! $elements ) {
					$elements      = get_post_meta( $post_id, BRICKS_DB_PAGE_FOOTER, true );
					$post_meta_key = BRICKS_DB_PAGE_FOOTER;
				} else {
					$post_meta_key = BRICKS_DB_PAGE_HEADER;
				}
			}

			// Loop over elements & convert global elements to components
			if ( is_array( $elements ) && count( $elements ) ) {
				foreach ( $elements as $index => $element ) {
					$global_element    = Helpers::get_global_element( $element );
					$global_element_id = $global_element['global'] ?? 0;
					$new_component_id  = Helpers::generate_random_id( false );

					if ( ! $global_element_id ) {
						continue;
					}

					$is_nestable = Elements::$elements[ $element['name'] ]['nestable'] ?? false;

					// Not a nestable element: Convert global element to component
					if ( $is_nestable != 1 ) {
						// Remove global element identifier
						unset( $elements[ $index ]['global'] );

						// STEP: Create new component from global element and add to components array, then save to DB
						$new_component = [
							'id'         => $new_component_id,
							'category'   => 'Converted Global Elements',
							'desc'       => '',
							'elements'   => [
								[
									'id'       => $new_component_id,
									'name'     => $global_element['name'],
									'parent'   => 0,
									'children' => [],
									'settings' => $global_element['settings'] ?? [],
								],
							],
							'properties' => [],
							'_created'   => time(),
							'_user_id'   => get_current_user_id(),
							'_version'   => BRICKS_VERSION,
							'_converted_from_global_element_id' => $global_element_id, // NOTE: Flag to indicate that this component was converted from a global element
						];

						if ( ! empty( $global_element['label'] ) ) {
							$new_component['elements'][0]['label'] = esc_html( $global_element['label'] );
						}

						// Add new component to components array, if no other component with same 'id' exists
						$component_ids = array_column( $components, '_converted_from_global_element_id' );
						if ( array_search( $global_element_id, $component_ids ) === false ) {
							$components[] = $new_component;

							// Save components in DB
							update_option( BRICKS_DB_COMPONENTS, $components );

							// Set component instance 'cid' to new component ID
							$elements[ $index ]['cid'] = $new_component_id;
						}

						// Component with global element ID already exists
						else {
							// Set component instance 'cid' to existing component ID
							foreach ( $components as $component ) {
								$global_element_id = $component['_converted_from_global_element_id'] ?? false;

								if ( $global_element_id && $global_element_id === $global_element_id ) {
									$elements[ $index ]['cid'] = $component['id'];
								}
							}
						}
					}

					// STEP: Unlink nestable elements and use global element settings
					if ( $is_nestable == 1 && $unlink_nestables == 'true' ) {
						// Use global element settings for individual element
						if ( isset( $global_element['settings'] ) ) {
							$elements[ $index ]['settings'] = $global_element['settings'];
						}

						// Remove global element identifier
						unset( $elements[ $index ]['global'] );
					}
				}

				// Update Bricks data post meta
				update_post_meta( $post_id, $post_meta_key, $elements );
			}
		}

		wp_send_json_success(
			[
				'post_id'          => $post_id,
				'message'          => $post_id ? get_the_title( $post_id ) . ': ' . esc_html__( 'Global elements converted to components.', 'bricks' ) : esc_html__( 'No post ID provided.', 'bricks' ),
				'components'       => $components,
				'unlink_nestables' => $unlink_nestables,
			]
		);
	}

	/**
	 * Get all items that need to run through converter
	 *
	 * - themeStyles
	 * - globalSettings
	 * - globalClasses
	 * - globalElements
	 * - template IDs (+ their page settings)
	 * - post IDs (+ their page settings)
	 *
	 * @since 1.4
	 */
	public function get_converter_items() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		$items_to_convert = [];

		$convert = $_POST['convert'] ?? [];

		// Convert theme styles
		if ( in_array( 'container', $convert ) ) {
			$items_to_convert[] = 'themeStyles';
		}

		// Convert element IDs & classes
		if ( in_array( 'elementClasses', $convert ) ) {
			$items_to_convert[] = 'globalSettings';
		}

		// Global classes (for any converter action)
		$items_to_convert[] = 'globalClasses';

		// Global elements (for any converter action)
		$items_to_convert[] = 'globalElements';

		// Get IDs of all Bricks templates
		$template_ids     = Templates::get_all_template_ids();
		$items_to_convert = array_merge( $items_to_convert, $template_ids );

		// Get IDs of all Bricks data posts
		$post_ids         = Helpers::get_all_bricks_post_ids();
		$items_to_convert = array_merge( $items_to_convert, $post_ids );

		wp_send_json_success(
			[
				'items'   => $items_to_convert,
				'convert' => $convert,
			]
		);
	}

	/**
	 * Run converter
	 *
	 * @since 1.4 Convert element IDs & class names for 1.4 ('bricks-element-' to 'brxe-')
	 */
	public function run_converter() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		$data    = $_POST['data'] ?? false;
		$convert = $_POST['convert'] ?? [];
		$updated = [];
		$label   = '';

		switch ( $data ) {
			case 'themeStyles':
				$theme_styles       = get_option( BRICKS_DB_THEME_STYLES, [] );
				$converter_response = self::convert( $theme_styles, 'themeStyles', $convert );

				if ( isset( $converter_response['count'] ) && $converter_response['count'] > 0 ) {
					$label            = esc_html__( 'Theme Styles', 'bricks' );
					$updated[ $data ] = update_option( BRICKS_DB_THEME_STYLES, $converter_response['data'] );
				}
				break;

			case 'globalSettings':
				$converter_response = self::convert( Database::$global_settings, 'globalSettings', $convert );

				if ( isset( $converter_response['count'] ) && $converter_response['count'] > 0 ) {
					$label            = esc_html__( 'Global settings', 'bricks' ) . ' (' . esc_html__( 'Custom CSS', 'bricks' ) . ')';
					$updated[ $data ] = update_option( BRICKS_DB_GLOBAL_SETTINGS, $converter_response['data'] );
				}
				break;

			case 'globalClasses':
				$global_classes     = get_option( BRICKS_DB_GLOBAL_CLASSES, [] );
				$converter_response = self::convert( $global_classes, 'globalClasses', $convert );

				if ( isset( $converter_response['count'] ) && $converter_response['count'] > 0 ) {
					$label            = esc_html__( 'Global classes', 'bricks' );
					$updated[ $data ] = Helpers::save_global_classes_in_db( $converter_response['data'] );
				}
				break;

			case 'globalElements':
				$global_elements    = get_option( BRICKS_DB_GLOBAL_ELEMENTS, [] );
				$converter_response = self::convert( $global_elements, 'globalElements', $convert );

				if ( isset( $converter_response['count'] ) && $converter_response['count'] > 0 ) {
					$label            = esc_html__( 'Global elements', 'bricks' );
					$updated[ $data ] = update_option( BRICKS_DB_GLOBAL_ELEMENTS, $converter_response['data'] );
				}
				break;

			/**
			 * Convert global elements to components
			 *
			 * NOTE: Global elements are deprecated since 2.0!
			 *
			 * @since 2.0
			 */
			case 'globalElementsToComponents':
				$global_elements              = get_option( BRICKS_DB_GLOBAL_ELEMENTS, [] );
				$components                   = get_option( BRICKS_DB_COMPONENTS, [] );
				$components_count             = count( $components );
				$component_ids                = array_column( $components, 'id' );
				$new_components_created       = 0;
				$components_already_converted = 0;

				foreach ( $global_elements as $global_element ) {
					$global_element_id = $global_element['global'] ?? $global_element['id'];
					$component         = [
						'id'         => $global_element_id,
						'category'   => 'Converted Global Elements',
						'desc'       => '',
						'elements'   => [
							[
								'id'       => $global_element_id,
								'name'     => $global_element['name'],
								'parent'   => 0,
								'children' => [],
								'settings' => $global_element['settings'] ?? [],
							],
						],
						'properties' => [],
						'_created'   => time(),
						'_user_id'   => get_current_user_id(),
						'_version'   => BRICKS_VERSION,
						'_converted' => 'global_element', // NOTE: Flag to indicate that this component was converted from a global element
					];

					if ( ! empty( $global_element['label'] ) ) {
						$component['elements'][0]['label'] = esc_html( $global_element['label'] );
					}

					// Add new component to components array, if no other component with same 'id' exists
					if ( array_search( $global_element_id, $component_ids ) === false ) {
						$components[] = $component;
						$new_components_created++;
					}

					// Global element already converted to component
					else {
						$components_already_converted++;
					}
				}

				// Update components in DB (if any new components were created)
				$updated[ $data ] = $components_count !== count( $components ) ? update_option( BRICKS_DB_COMPONENTS, $components ) : false; // true if successful

				// New components created
				if ( $new_components_created > 0 ) {
					// translators: %1$s: Number of components created, %2$s: Total number of global elements
					$label = sprintf(
						esc_html__( '%1$s out of %2$s global elements have been converted to components.', 'bricks' ),
						$new_components_created,
						count( $global_elements )
					);
				}

				// Global elements already converted to components
				if ( $components_already_converted > 0 ) {
					// translators: %1$s: Number of components already converted, %2$s: Total number of global elements
					$label = sprintf( esc_html__( '%1$s out of %2$s global elements components were already converted to components.', 'bricks' ), $components_already_converted, count( $global_elements ) );
				}
				break;

			// Individual post + any possible page settings (that has Bricks data OR is Bricks template)
			default:
				$post_id       = $data;
				$post_type     = get_post_type( $post_id );
				$elements      = false;
				$post_meta_key = false;

				// Get content type (header, content, footer) & elements
				if ( $post_type === BRICKS_DB_TEMPLATE_SLUG ) {
					$elements = get_post_meta( $post_id, BRICKS_DB_PAGE_HEADER, true );

					if ( $elements ) {
						$post_meta_key = BRICKS_DB_PAGE_HEADER;
					} else {
						$elements = get_post_meta( $post_id, BRICKS_DB_PAGE_FOOTER, true );

						if ( $elements ) {
							$post_meta_key = BRICKS_DB_PAGE_FOOTER;
						}
					}
				}

				// No 'header' or 'footer' data: Check for 'content' post meta
				if ( ! $elements ) {
					$elements = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );

					if ( $elements ) {
						$post_meta_key = BRICKS_DB_PAGE_CONTENT;
					}
				}

				if ( $elements && $post_meta_key ) {
					$converter_response = self::convert( $elements, $post_id, $convert );

					// Update post if change was made (check: count)
					if ( isset( $converter_response['count'] ) && $converter_response['count'] > 0 ) {
						$elements = is_array( $converter_response['data'] ) ? $converter_response['data'] : false;

						if ( $elements ) {
							// Update Bricks data post meta
							$updated[ $data ] = update_post_meta( $post_id, $post_meta_key, $elements );

							// Generate label to show in Bricks settings
							$post_type_object = get_post_type_object( $post_type );
							$post_type        = $post_type_object ? $post_type_object->labels->singular_name : $post_type;
							$label            = "$post_type: <a href='" . Helpers::get_builder_edit_link( $post_id ) . "' target='_blank'>" . get_the_title( $post_id ) . '</a>';
						}
					}

					// Convert: Page settings
					$page_settings = get_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS, true );

					if ( $page_settings ) {
						$converter_response = self::convert( $page_settings, 'pageSettings', $convert );

						if ( isset( $converter_response['count'] ) && $converter_response['count'] > 0 ) {
							$updated[ $data ] = update_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS, $converter_response['data'] );

							if ( $label ) {
								$label .= ' (+ ' . esc_html__( 'Page settings', 'bricks' ) . ')';
							} else {
								$post_type_object = get_post_type_object( $post_type );
								$post_type        = $post_type_object ? $post_type_object->labels->singular_name : $post_type;

								$label = "$post_type: " . get_the_title( $post_id ) . ' (' . esc_html__( 'Page settings', 'bricks' ) . ')';
							}
						}
					}
				}
		}

		wp_send_json_success(
			[
				'data'    => $data,
				'updated' => $updated,
				'label'   => $label,
			]
		);
	}

	/**
	 * Convert: elementClasses
	 *
	 * @param string $data Source string to apply search & replace for.
	 * @param string $source themeStyles, globalSettings, globalClasses, globalElements, pageSettings, $post_id.
	 * @param array  $convert elementClasses, container.
	 *
	 * @return string
	 *
	 * @since 1.4
	 */
	private function convert( $data, $source, $convert ) {
		if ( ! $data ) {
			return $data;
		}

		$count = 0;

		/**
		 * STEP: Convert global elements to components
		 *
		 * @since 2.0
		 */
		if ( in_array( 'globalElementsToComponents', $convert ) ) {
			$elements = $data;
			Elements::load_elements();

			if ( is_array( $elements ) && count( $elements ) ) {
				foreach ( $elements as $index => $element ) {
					$global_element_id = Helpers::get_global_element( $element, 'global' );

					// Is global element
					if ( $global_element_id ) {
						$global_element_settings = Helpers::get_global_element( $element, 'settings' );

						// Is nestable element: Convert to individual element instead of component by using global element settings
						$is_nestable = Elements::$elements[ $element['name'] ]['nestable'] ?? false;
						if ( $is_nestable ) {
							$elements[ $index ]['settings'] = $global_element_settings;
						}

						// Set component instance 'cid' to global element ID
						else {
							$elements[ $index ]['cid'] = $global_element_id;
						}

						// Remove global element identifier
						unset( $elements[ $index ]['global'] );

						$count++;
					}
				}
			}

			$data = $elements;
		}

		/**
		 * STEP: Convert entry animation ('_animation') to interaction
		 *
		 * '_animation' controls (Style > Layout > Misc > Animation) are deprecated since 1.6 too.
		 *
		 * @since 1.6
		 */
		if ( in_array( 'entryAnimationToInteraction', $convert ) ) {
			$elements = $data;

			foreach ( $elements as $index => $element ) {
				$settings  = ! empty( $element['settings'] ) ? $element['settings'] : [];
				$animation = ! empty( $settings['_animation'] ) ? $settings['_animation'] : false;

				// Skip: Element has no old entry animation
				if ( ! $animation ) {
					continue;
				}

				// Create entry animation under interactions & delete old '_animation' settings
				$new_animation = [
					'id'            => Helpers::generate_random_id( false ),
					'trigger'       => 'enterView',
					'action'        => 'startAnimation',
					'animationType' => $animation,
				];

				unset( $elements[ $index ]['settings']['_animation'] );

				if ( isset( $settings['_animationDuration'] ) ) {
					if ( $settings['_animationDuration'] === 'very-slow' ) {
						$new_animation['animationDuration'] = '2s';
					} elseif ( $settings['_animationDuration'] === 'slow' ) {
						$new_animation['animationDuration'] = '1.5s';
					} elseif ( $settings['_animationDuration'] === 'fast' ) {
						$new_animation['animationDuration'] = '0.5s';
					} elseif ( $settings['_animationDuration'] === 'very-fast' ) {
						$new_animation['animationDuration'] = '0.25s';
					}

					unset( $elements[ $index ]['settings']['_animationDuration'] );
				}

				if ( isset( $settings['_animationDurationCustom'] ) ) {
					$new_animation['animationDuration'] = $settings['_animationDurationCustom'];

					unset( $elements[ $index ]['settings']['_animationDurationCustom'] );
				}

				if ( isset( $settings['_animationDelay'] ) ) {
					$new_animation['animationDelay'] = $settings['_animationDelay'];

					unset( $elements[ $index ]['settings']['_animationDelay'] );
				}

				$new_animation['titleEditable'] = 'Entry animation';

				$interactions = ! empty( $settings['_interactions'] ) ? $settings['_interactions'] : [];

				$interactions[] = $new_animation;

				$elements[ $index ]['settings']['_interactions'] = $interactions;

				$count++;
			}

			$data = $elements;
		}

		/**
		 * STEP: Add position: relative as needed
		 *
		 * @since 1.5.1
		 */
		if ( in_array( 'addPositionRelative', $convert ) ) {
			$elements = $data;

			foreach ( $elements as $index => $element ) {
				$settings = ! empty( $element['settings'] ) ? $element['settings'] : [];

				foreach ( $settings as $key => $value ) {
					// STEP: Element has '_top', '_right', '_bottom', '_left' set, but no '_position'
					$directions = [ '_top', '_right', '_bottom', '_left', '_zIndex' ];

					foreach ( $directions as $direction ) {
						// Setting starts with direction key (to capture all breakpoint & pseudo-class settings, etc.)
						if ( strpos( $key, $direction ) === 0 ) {
							$position_key = str_replace( $direction, '_position', $key );

							// Position not set: Set to 'relative'
							if ( empty( $settings[ $position_key ] ) ) {
								$elements[ $index ]['settings'][ $position_key ] = 'relative';
								$count++;
							}
						}
					}

					// STEP: Element has 'position: absolute': Set 'position: relative' on parent element
					if ( strpos( $key, '_position' ) === 0 && $value === 'absolute' ) {
						$parent_id = ! empty( $element['parent'] ) ? $element['parent'] : false;

						if ( $parent_id ) {
							foreach ( $elements as $i => $el ) {
								if ( isset( $el['id'] ) && $el['id'] === $parent_id && ! isset( $el['settings']['_position'] ) ) {
									if ( ! isset( $el['settings'] ) ) {
										$elements[ $i ]['settings'] = [];
									}

									$elements[ $i ]['settings']['_position'] = 'relative';
									$count++;
								}
							}
						}
					}

					// STEP: Element has _gradient.applyTo === 'overlay' set
					if ( strpos( $key, '_gradient' ) === 0 && ! empty( $settings[ $key ]['applyTo'] ) && $settings[ $key ]['applyTo'] === 'overlay' ) {
						$child_ids = ! empty( $element['children'] ) ? $element['children'] : [];

						// Add position: relative to direct children of element with gradient
						foreach ( $elements as $i => $el ) {
							if ( ! empty( $el['id'] ) && in_array( $el['id'], $child_ids ) && ! isset( $el['settings']['_position'] ) ) {

								if ( ! isset( $el['settings'] ) ) {
									$elements[ $i ]['settings'] = [];
								}

								$elements[ $i ]['settings']['_position'] = 'relative';
								$count++;
							}
						}
					}
				}
			}

			$data = $elements;
		}

		/**
		 * STEP: Convert element IDs & class name 'bricks-element-' to 'brxe-'
		 *
		 * @since 1.4
		 */

		if ( in_array( 'elementClasses', $convert ) ) {
			// Check if data is array: JSON encode to string > convert > decode back to array
			$is_array = is_array( $data );

			if ( $is_array ) {
				$data = wp_json_encode( $data );
			}

			// Search for (key) & replace with (value)
			$search_replace = [
				'#bricks-element-'  => '#brxe-',
				'.bricks-element-'  => '.brxe-',
				'#bricks-header'    => '#brx-header',
				'#bricks-content'   => '#brx-content',
				'#bricks-footer'    => '#brx-footer',

				// All elements use brxe- class prefix (@since 1.5)
				'.bricks-container' => '.brxe-container',
				'.brx-container'    => '.brxe-container',
			];

			foreach ( $search_replace as $search => $replace ) {
				$data = str_replace( $search, $replace, $data, $number_of_replacements_made );

				$count += $number_of_replacements_made;
			}

			if ( $is_array ) {
				$data = json_decode( $data, true );
			}
		}

		/**
		 * STEP: Convert 'container' to 'section' & 'block' element & theme styles
		 *
		 * @since 1.5
		 */
		if ( in_array( 'container', $convert ) ) {
			/**
			 * - Stretched root 'container' to 'section' element
		   * - 'container' inside 'container' to 'block' element
			 */
			if ( is_array( $data ) && is_numeric( $source ) ) {
				$elements = $data;

				$response = self::convert_container_to_section_block_element( $elements );
				$data     = $response['elements'];
				$count    = $response['count'];
			}

			/**
			 * Theme styles
			 */
			elseif ( $source === 'themeStyles' ) {
				$theme_styles = $data;

				foreach ( $theme_styles as $style_id => $style ) {
					$settings           = ! empty( $style['settings'] ) ? $style['settings'] : [];
					$section_settings   = ! empty( $settings['section'] ) ? $settings['section'] : [];
					$container_settings = ! empty( $settings['container'] ) ? $settings['container'] : [];

					foreach ( $settings as $group => $group_settings ) {
						switch ( $group ) {
							case 'general':
								foreach ( $group_settings as $key => $value ) {
									// Root container margin to section margin
									if ( $key === 'sectionMargin' ) {
										$section_settings['margin'] = $value;
										$count++;

										unset( $theme_styles[ $style_id ]['settings'][ $group ][ $key ] );
									}

									// Root container padding to section padding
									elseif ( $key === 'sectionPadding' ) {
										$section_settings['padding'] = $value;
										$count++;

										unset( $theme_styles[ $style_id ]['settings'][ $group ][ $key ] );
									}

									// Root container max-width to container width
									elseif ( $key === 'containerMaxWidth' ) {
										$container_settings['width'] = $value;
										$count++;

										unset( $theme_styles[ $style_id ]['settings'][ $group ][ $key ] );
									}
								}
								break;
						}
					}

					if ( count( $section_settings ) ) {
						$theme_styles[ $style_id ]['settings']['section'] = $section_settings;
					}

					if ( count( $container_settings ) ) {
						$theme_styles[ $style_id ]['settings']['container'] = $container_settings;
					}
				}

				$data = $theme_styles;
			}
		}

		return [
			'count' => $count,
			'data'  => $data,
		];
	}

	public static function convert_container_to_section_block_element( $elements = [] ) {
		$converted_container_ids = [];
		$count                   = 0;

		foreach ( $elements as $index => $element ) {
			// Skip non-container elements
			if ( $element['name'] !== 'container' ) {
				continue;
			}

			$parent_id = $element['parent'];

			// STEP: Stretched or 100%/vw width root container to section
			if ( $parent_id == '0' ) {
				if (
					( ! empty( $element['settings']['_alignSelf'] ) && $element['settings']['_alignSelf'] === 'stretch' ) ||
					( ! empty( $element['settings']['_width'] ) && in_array( $element['settings']['_width'], [ '100%', '100vw' ] ) ) ||
					( ! empty( $element['settings']['_widthMin'] ) && in_array( $element['settings']['_widthMin'], [ '100%', '100vw' ] ) ) ||
					( ! empty( $element['settings']['_widthMax'] ) && in_array( $element['settings']['_widthMax'], [ '100%', '100vw' ] ) )
					) {
					$elements[ $index ]['name'] = 'section';

					if ( ! isset( $element['label'] ) || $element['label'] === 'Container' ) {
						unset( $elements[ $index ]['label'] );
					}

					$count++;
				}
			}

			// Child 'container' inside 'container' to 'block' element
			elseif ( $parent_id != '0' ) {
				$parent_index   = array_search( $parent_id, array_column( $elements, 'id' ) );
				$parent_element = ! empty( $elements[ $parent_index ] ) ? $elements[ $parent_index ] : false;
				$parent_name    = ! empty( $parent_element['name'] ) ? $parent_element['name'] : false;

				if (
					( $parent_name === 'container' ) || // Convert if parent is 'container'
					( $parent_name === 'block' && in_array( $parent_element['id'], $converted_container_ids ) ) // Convert if parent 'id' was converted from 'container' to 'block'
				) {
					$elements[ $index ]['name'] = 'block';

					if ( ! isset( $element['label'] ) || $element['label'] === 'Container' ) {
						unset( $elements[ $index ]['label'] );
					}

					if ( ! empty( $elements[ $index ]['id'] ) ) {
						$converted_container_ids[] = $elements[ $index ]['id'];
					}

					$count++;
				}
			}
		}

		return [
			'elements' => $elements,
			'count'    => $count,
		];
	}
}
