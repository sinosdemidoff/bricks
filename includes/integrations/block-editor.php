<?php
namespace Bricks\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Block_Editor {
	public function __construct() {
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_shortcode( 'bricks_component', [ $this, 'render_component_shortcode' ] );
	}

	/**
	 * Register all component blocks
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$this->register_bricks_components_as_blocks();
	}

	/**
	 * Register all components as blocks, if enabled for block editor
	 */
	public function register_bricks_components_as_blocks() {
		if ( ! \Bricks\Database::get_setting( 'bricksComponentsInBlockEditor' ) ) {
			return;
		}

		$components = get_option( BRICKS_DB_COMPONENTS, [] );

		foreach ( $components as $component ) {
			if ( empty( $component['id'] ) || empty( $component['elements'] ) ) {
				continue;
			}

			// NOTE: Register ALL components as blocks (not just enabled ones)
			// The JavaScript side will handle showing placeholders for disabled components
			$this->register_component_block( $component );
		}
	}

	/**
	 * Register a single component block
	 *
	 * @param array $component Component data.
	 * @return void
	 */
	public function register_component_block( $component ) {
		// Skip if no data
		if ( ! $component || empty( $component['elements'] ) ) {
			return;
		}

		// Use component ID directly for block name (matches JavaScript registration)
		$block_name = 'bricks-components/' . $component['id'];

		// Get component name from the first element or use ID
		$component_name = '';
		if ( isset( $component['elements'][0]['label'] ) ) {
			$component_name = $component['elements'][0]['label'];
		} else {
			$component_name = sprintf(
				/* translators: %s: Component ID */
				__( 'Component %s', 'bricks' ),
				$component['id']
			);
		}

		$attributes = [
			'componentId' => [
				'type'    => 'string',
				'default' => $component['id'],
			],
			'properties'  => [
				'type'    => 'object',
				'default' => [],
			],
			'blockId'     => [
				'type'    => 'string',
				'default' => '',
			],
		];

		// Register block type
		register_block_type(
			$block_name,
			[
				'attributes'      => $attributes,
				'render_callback' => [ $this, 'render_component_block' ],
			]
		);
	}

	/**
	 * Render component block
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	public function render_component_block( $attributes ) {
		try {
			$component_id = $attributes['componentId'] ?? '';

			if ( ! $component_id ) {
				return '';
			}

			// Check if component is enabled for block editor before rendering
			$components = get_option( BRICKS_DB_COMPONENTS, [] );
			$component  = null;

			foreach ( $components as $comp ) {
				if ( isset( $comp['id'] ) && $comp['id'] === $component_id ) {
					$component = $comp;
					break;
				}
			}

			// Return empty string if component not found or not enabled for block editor
			if ( ! $component ) {
				return '';
			}

			// Check if component is enabled for block editor
			if ( \Bricks\Database::get_setting( 'bricksComponentsInBlockEditor' ) === 'manual' && empty( $component['blockEditor'] ) ) {
				return '';
			}

			// Render component directly with attributes
			return $this->render_component_shortcode( $attributes );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Render component shortcode: [bricks_component id="component_id"]
	 *
	 * Simplified version that leverages Bricks' native component system
	 */
	public function render_component_shortcode( $attributes = [] ) {
		try {
			// Handle both direct calls (from blocks) and shortcode calls
			$component_id = ! empty( $attributes['componentId'] ) ? sanitize_text_field( $attributes['componentId'] ) :
							( ! empty( $attributes['id'] ) ? sanitize_text_field( $attributes['id'] ) : false );

			if ( ! $component_id ) {
				return '';
			}

			// Check if component exists
			$component = \Bricks\Helpers::get_component_by_cid( $component_id );
			if ( ! $component ) {
				return '';
			}

			// Check if component is enabled for block editor
			if ( \Bricks\Database::get_setting( 'bricksComponentsInBlockEditor' ) === 'manual' && empty( $component['blockEditor'] ) ) {
				return '';
			}

			// Get properties from attributes (handle both new and legacy formats)
			$properties = [];
			if ( isset( $attributes['properties'] ) && is_array( $attributes['properties'] ) ) {
				// New format: single properties object
				$properties = $attributes['properties'];
			}

			// Get block ID for unique element ID
			$block_id = ! empty( $attributes['blockId'] ) ? sanitize_text_field( $attributes['blockId'] ) : '';

			// Get the main element from the component
			$main_element = null;
			foreach ( $component['elements'] as $element ) {
				if ( $element['id'] === $component_id ) {
					$main_element = $element;
					break;
				}
			}

			if ( ! $main_element ) {
				return '';
			}

			// Create component element using the main element's name and structure
			// Use blockId for consistent element ID, fallback to component ID if no blockId
			$element_id = $block_id ? $component_id . '-' . $block_id : $component_id;

			$component_element = [
				'id'         => $element_id,
				'name'       => $main_element['name'], // Use the actual element name (e.g., 'post - title')
				'cid'        => $component_id,
				'properties' => $properties,
			];

			// Generate CSS for this component instance
			\Bricks\Assets::generate_css_from_elements( [ $component_element ], "component_$component_id" );

			// Enqueue icon fonts and other setting-specific scripts for this component instance
			\Bricks\Assets::enqueue_setting_specific_scripts( [ $component_element ] );

			// Ensure theme styles are loaded for Gutenberg context
			if ( $this->is_gutenberg_render() && empty( \Bricks\Theme_Styles::$settings_by_id ) ) {
				\Bricks\Theme_Styles::load_set_styles();
			}

			// Handle CSS output based on context
			$html = '';
			if ( bricks_is_builder() || bricks_is_builder_call() || $this->is_gutenberg_render() ) {
				// For builder/Gutenberg: Add inline CSS for immediate preview
				$component_css = \Bricks\Assets::$inline_css[ "component_$component_id" ] ?? '';

				// Add global styles for editor contexts
				$global_css         = '';
				$global_classes_css = \Bricks\Assets::generate_global_classes();
				if ( $global_classes_css ) {
					$global_css .= "\n/* Global Classes */\n" . $global_classes_css;
				}

				$global_variables = \Bricks\Assets::get_global_variables();
				if ( $global_variables ) {
					$variables_css = \Bricks\Assets::format_variables_as_css( $global_variables );
					if ( $variables_css ) {
						$global_css .= "\n/* Global Variables */\n" . $variables_css;
					}
				}

				$global_colors = \Bricks\Assets::generate_inline_css_color_vars( \Bricks\Database::$global_data['colorPalette'] );
				if ( $global_colors ) {
					$global_css .= "\n/* Global Colors */\n" . $global_colors;
				}

				// Add theme styles that apply to current page
				$theme_style_css = '';
				if ( ! empty( \Bricks\Theme_Styles::$settings_by_id ) ) {
					foreach ( \Bricks\Theme_Styles::$settings_by_id as $style_id => $settings ) {
						$theme_style_css .= \Bricks\Assets::generate_inline_css_theme_style( $settings );
					}
				}
				if ( $theme_style_css ) {
					$global_css .= "\n/* Theme Styles */\n" . $theme_style_css;
				}

				// Add webfonts
				$webfont_links = '';
				if ( $component_css ) {
					if ( $this->is_gutenberg_render() ) {
						$webfont_links = \Bricks\Assets::load_webfonts( $component_css, true );
					} else {
						\Bricks\Assets::load_webfonts( $component_css );
					}
				}

				// Disable links in editor
				$editor_css = "\n/* Disable links in editor */\n.brxe-{$component_id} a { pointer-events: none; }\n";

				// Scope CSS to Gutenberg editor canvas
				if ( $this->is_gutenberg_render() ) {
					$all_css = $global_css . $component_css . $editor_css;
					if ( $all_css ) {
						$scoped_css = self::scope_css_for_gutenberg( $all_css );
						$html      .= "{$webfont_links}<style id=\"bricks-inline-css-component-{$component_id}\">{$scoped_css}</style>";
					} else {
						$html .= $webfont_links;
					}
				} else {
					$html .= "{$webfont_links}<style id=\"bricks-inline-css-component-{$component_id}\">{$global_css}{$component_css}{$editor_css}</style>";
				}
			} else {
				// For frontend: Add CSS to Bricks' normal CSS handling system
				$component_css = \Bricks\Assets::$inline_css[ "component_$component_id" ] ?? '';
				if ( $component_css ) {
					// Add to dynamic CSS for frontend output
					\Bricks\Assets::$inline_css_dynamic_data .= $component_css;

					// Load webfonts for frontend
					\Bricks\Assets::load_webfonts( $component_css );
				}
			}

			// Prevent infinite loops
			static $rendered_components = [];
			if ( in_array( $component_id, $rendered_components, true ) ) {
				return '';
			}

			$rendered_components[] = $component_id;

			// Let Bricks handle everything - this is the key simplification!
			// But first, ensure we have post context for post-related elements
			global $post;
			$original_post = $post;

			// If no post context in Gutenberg, try to get the current editing post
			if ( ! $post && $this->is_gutenberg_render() ) {
				$post_id = get_the_ID();
				if ( ! $post_id && isset( $_GET['post'] ) ) {
					$post_id = intval( $_GET['post'] );
				}
				if ( ! $post_id && isset( $_POST['post_id'] ) ) {
					$post_id = intval( $_POST['post_id'] );
				}

				if ( $post_id ) {
					$post = get_post( $post_id );
					setup_postdata( $post );
				}
			}

			$html .= \Bricks\Frontend::render_element( $component_element );

			// Restore original post context
			if ( $original_post ) {
				$post = $original_post;
				setup_postdata( $post );
			} elseif ( ! $original_post && $post ) {
				wp_reset_postdata();
			}

			array_pop( $rendered_components );

			return $html;

		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Scope CSS for Gutenberg editor while preserving root-level declarations
	 *
	 * Separates CSS that must stay at root level (:root, @font-face, @keyframes)
	 * from CSS that should be scoped to .is-root-container
	 *
	 * @param string $css CSS to scope.
	 * @return string Scoped CSS.
	 */
	public static function scope_css_for_gutenberg( $css ) {
		if ( empty( $css ) ) {
			return $css;
		}

		$root_level_css = '';
		$scoped_css     = '';

		// STEP: Extract :root blocks (CSS variables)
		if ( preg_match_all( '/:root\s*\{[^}]*\}/s', $css, $root_matches ) ) {
			foreach ( $root_matches[0] as $root_block ) {
				$root_level_css .= $root_block . "\n";
				$css             = str_replace( $root_block, '', $css );
			}
		}

		// STEP: Extract @font-face blocks
		if ( preg_match_all( '/@font-face\s*\{[^}]*\}/s', $css, $font_matches ) ) {
			foreach ( $font_matches[0] as $font_block ) {
				$root_level_css .= $font_block . "\n";
				$css             = str_replace( $font_block, '', $css );
			}
		}

		// STEP: Extract @keyframes blocks
		if ( preg_match_all( '/@(?:-webkit-)?keyframes\s+[^{]+\{(?:[^{}]*\{[^}]*\})*[^}]*\}/s', $css, $keyframe_matches ) ) {
			foreach ( $keyframe_matches[0] as $keyframe_block ) {
				$root_level_css .= $keyframe_block . "\n";
				$css             = str_replace( $keyframe_block, '', $css );
			}
		}

		// STEP: Wrap remaining CSS in .is-root-container
		$css = trim( $css );
		if ( $css ) {
			$scoped_css = ".is-root-container {\n" . $css . "\n}\n";
		}

		// STEP: Combine root-level first, then scoped
		return $root_level_css . $scoped_css;
	}

	/**
	 * Check if we're in a Gutenberg ServerSideRender context
	 */
	private function is_gutenberg_render() {
		// Check if we're in a REST API call for block rendering
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// Check for AJAX request from Gutenberg block renderer
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( isset( $_POST['action'] ) && $_POST['action'] === 'gutenberg_render_block' ) {
				return true;
			}
		}

		// Check if we're in admin and not in Bricks builder
		if ( is_admin() && ! bricks_is_builder() && ! bricks_is_builder_call() ) {
			return true;
		}

		// Check for specific Gutenberg query parameters
		if ( isset( $_GET['context'] ) && sanitize_text_field( wp_unslash( $_GET['context'] ) ) === 'edit' ) {
			return true;
		}

		// Check if current screen is Gutenberg editor
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
				return true;
			}
		}

		// Check for block editor specific headers or request attributes
		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) && is_admin() ) {
			return true;
		}

		return false;
	}

	/**
	 * Get select options from the first connected element with a select control
	 *
	 * @param array $property    The component property array.
	 * @param array $elements    The component elements array.
	 * @return array|null The select options array or null if not found.
	 */
	public function get_select_options_from_connected_elements( $property, $elements ) {
		// Only process select properties that have connections
		if ( $property['type'] !== 'select' || empty( $property['connections'] ) || ! is_array( $property['connections'] ) ) {
			return null;
		}

		// Check each connected element
		foreach ( $property['connections'] as $element_id => $connection_paths ) {
			// Find the element in the elements array
			$element = $this->find_element_by_id( $elements, $element_id );
			if ( ! $element ) {
				continue;
			}

			// Get the element's controls to find select controls
			$element_controls = \Bricks\Elements::get_element( $element, 'controls' );
			if ( empty( $element_controls ) ) {
				continue;
			}

			// Check each connection path to find select controls
			foreach ( $connection_paths as $path ) {
				// For simple paths (most common case)
				if ( strpos( $path, '.' ) === false ) {
					if ( isset( $element_controls[ $path ] ) ) {
						$control = $element_controls[ $path ];
						if ( isset( $control['type'] ) && $control['type'] === 'select' && ! empty( $control['options'] ) ) {
							return $control['options'];
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Find element by ID in elements array (recursive)
	 *
	 * @param array  $elements   The elements array to search.
	 * @param string $element_id The element ID to find.
	 * @return array|null The element array or null if not found.
	 */
	private function find_element_by_id( $elements, $element_id ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['id'] ) && $element['id'] === $element_id ) {
				return $element;
			}

			// Search recursively in children
			if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
				$found = $this->find_element_by_id( $element['children'], $element_id );
				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

}
