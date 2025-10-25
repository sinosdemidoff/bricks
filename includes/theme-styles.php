<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Theme_Styles {
	public static $styles          = [];
	public static $settings_by_id  = []; // key: Theme style ID, value: Theme style settings array (@since 2.0)
	public static $control_options = [];
	public static $control_groups  = [];
	public static $controls        = [];

	public function __construct() {
		add_action( 'wp', [ $this, 'set_controls' ] );
		add_action( 'wp', [ $this, 'load_set_styles' ] );

		add_action( 'wp_ajax_bricks_create_styles', [ $this, 'create_styles' ] );
		add_action( 'wp_ajax_bricks_delete_style', [ $this, 'delete_style' ] );
	}

	public static function set_controls() {
		self::$control_options = Setup::$control_options;
		self::$control_groups  = self::get_control_groups();
		self::$controls        = self::get_controls();
	}

	public static function load_set_styles( $post_id = 0 ) {
		self::load_styles();
		self::set_active_style( $post_id );
	}

	/**
	 * Load theme styles
	 */
	public static function load_styles() {
		// Load 'Styles' abstract base class
		require_once BRICKS_PATH . 'includes/theme-styles/base.php';

		// // NOTE: Undocumented
		self::$styles = apply_filters( 'bricks/theme_styles', get_option( BRICKS_DB_THEME_STYLES, [] ) );
	}

	/**
	 * Get control groups
	 */
	public static function get_control_groups() {
		$control_groups = [];

		// CONDITIONS

		$control_groups['conditions'] = [
			'title' => esc_html__( 'Conditions', 'bricks' ),
		];

		// CODE (@since 2.0)
		$control_groups['css'] = [
			'title' => esc_html__( 'Stylesheet', 'bricks' ),
			'badge' => Helpers::render_badge( '2.0' ),
		];

		// GROUPS

		$control_groups['general'] = [
			'title' => esc_html__( 'General', 'bricks' ),
		];

		$control_groups['colors'] = [
			'title' => esc_html__( 'Colors', 'bricks' ),
		];

		$control_groups['links'] = [
			'title' => esc_html__( 'Links', 'bricks' ),
		];

		$control_groups['contextualSpacing'] = [
			'title' => esc_html__( 'Contextual spacing', 'bricks' ),
			'badge' => Helpers::render_badge( '2.0' ),
		];

		$control_groups['content'] = [
			'title' => esc_html__( 'Content', 'bricks' ) . ' (' . esc_html__( 'Margin', 'bricks' ) . ')',
		];

		$control_groups['typography'] = [
			'title' => esc_html__( 'Typography', 'bricks' ),
		];

		$control_groups['popup'] = [
			'title' => esc_html__( 'Popup', 'bricks' ),
		];

		$control_groups['elements'] = [
			'title'    => esc_html__( 'Elements', 'bricks' ),
			'isParent' => true, // @since 2.0
		];

		// ELEMENT STYLES
		$element_control_groups = [
			'section',
			'container',
			'block',
			'div',
			'accordion',
			'alert',
			'button',
			'carousel',
			'code',
			'counter',
			'divider',
			'form',
			'heading',
			'icon-box',
			'social-icons',
			'image',
			'image-gallery',
			'list',
			'nav-menu',
			'post-content',
			'post-meta',
			'post-navigation',
			'related-posts',
			'post-taxonomy',
			'post-title',
			'pricing-tables',
			'progress-bar',
			'search',
			'sidebar',
			'slider',
			'svg',
			'tabs',
			'team-members',
			'testimonials',
			'text',
			'video',
			'wordpress',
		];

		foreach ( $element_control_groups as $element_name ) {
			$element = Elements::$elements[ $element_name ] ?? false;

			// Element is registered: Load it in theme styles panel
			if ( $element ) {
				$element_label = ! empty( $element['label'] ) ? $element['label'] : str_replace( '-', ' ', $element_name );

				$control_groups[ $element_name ] = [
					'title'  => $element_label,
					'parent' => 'elements', // @since 2.0
				];
			}
		}

		$control_groups = apply_filters( 'bricks/theme_styles/control_groups', $control_groups );

		return $control_groups;
	}

	/**
	 * Get all theme style controls
	 */
	public static function get_controls() {
		$theme_styles_controls = [];

		foreach ( glob( BRICKS_PATH . 'includes/theme-styles/controls/*.php' ) as $file ) {
			if ( ! is_readable( $file ) ) {
				continue;
			}

			$file_name = basename( $file, '.php' );

			// Is theme style for an element (starts with 'element-') (@since 1.5.1)
			if ( strpos( $file_name, 'element-' ) === 0 ) {
				$element_name = str_replace( 'element-', '', $file_name );

				// Element not registered: Skip loading it in theme styles panel
				if ( ! isset( Elements::$elements[ $element_name ] ) ) {
					continue;
				}
			}

			$element          = require_once $file;
			$element_name     = isset( $element['name'] ) ? $element['name'] : '';
			$element_controls = isset( $element['controls'] ) ? $element['controls'] : '';

			if ( ! $element_name || ! is_array( $element_controls ) ) {
				continue;
			}

			foreach ( $element_controls as $control_key => $control ) {
				$control['group'] = $element_name;

				// Check for control property 'cssSelector': Prefix CSS 'selector' with element CSS class (e.g.: '.brxe-alert')
				$css_selector = isset( $element['cssSelector'] ) ? $element['cssSelector'] : '';

				if ( isset( $control['css'] ) && is_array( $control['css'] ) ) {
					foreach ( $control['css'] as $index => $value ) {
						// Append custom selector
						$custom_selector = isset( $control['css'][ $index ]['selector'] ) ? $control['css'][ $index ]['selector'] : '';

						if ( $custom_selector ) {
							// @since 1.4: Remove leading '&' (attach to element root)
							if ( strpos( $custom_selector, '&' ) !== false ) {
								$custom_selector = str_replace( '&', '', $custom_selector );

								$control['css'][ $index ]['selector'] = "{$css_selector}{$custom_selector}";
							} else {
								$control['css'][ $index ]['selector'] = "$css_selector $custom_selector";
							}
						} else {
							$control['css'][ $index ]['selector'] = $css_selector;
						}
					}
				}

				$theme_styles_controls[ $element_name ][ $control_key ] = $control;
			}
		}

		return apply_filters( 'bricks/theme_styles/controls', $theme_styles_controls );
	}

	/**
	 * Get controls data
	 */
	public static function get_controls_data() {
		return [
			'controlGroups' => self::$control_groups,
			'controls'      => self::$controls,
		];
	}

	/**
	 * Create new styles (create new one or import styles from file)
	 */
	public function create_styles() {
		Ajax::verify_nonce( 'bricks-nonce-builder' );

		if ( ! Builder_Permissions::user_has_permission( 'access_theme_styles' ) ) {
			wp_send_json_error( 'verify_request: Sorry, you are not allowed to perform this action.' );
		}

		if ( ! isset( $_POST['styles'] ) ) {
			wp_send_json_success();
		}

		$custom_styles = get_option( BRICKS_DB_THEME_STYLES, [] );
		$new_styles    = stripslashes_deep( $_POST['styles'] );

		if ( empty( $new_styles ) || ! is_array( $new_styles ) ) {
			wp_send_json_success();
		}

		foreach ( $new_styles as $style ) {
			if ( array_key_exists( $style['id'], $custom_styles ) ) {
				continue;
			}

			if ( isset( $style['oldId'] ) && array_key_exists( $style['oldId'], $custom_styles ) ) {
				$custom_styles[ $style['id'] ] = $custom_styles[ $style['oldId'] ];
				unset( $custom_styles[ $style['oldId'] ] );
				continue;
			}

			$custom_styles[ $style['id'] ] = [
				'label'    => $style['label'],
				'settings' => $style['settings'],
			];
		}

		update_option( BRICKS_DB_THEME_STYLES, $custom_styles );

		wp_send_json_success();
	}

	/**
	 * Delete custom style from db (by style ID)
	 */
	public function delete_style() {
		Ajax::verify_nonce( 'bricks-nonce-builder' );

		if ( ! Builder_Permissions::user_has_permission( 'access_theme_styles' ) ) {
			wp_send_json_error( 'verify_request: Sorry, you are not allowed to perform this action.' );
		}

		$custom_styles = get_option( BRICKS_DB_THEME_STYLES, [] );
		$style_id      = $_POST['styleId'] ?? '';

		// Remove reset from custom styles
		if ( $style_id && array_key_exists( $style_id, $custom_styles ) ) {
			unset( $custom_styles[ $style_id ] );
		}

		// Save custom style in db option table
		update_option( BRICKS_DB_THEME_STYLES, $custom_styles );

		wp_send_json_success();
	}

	/**
	 * Get active theme style according to theme style conditions
	 *
	 * @param int     $post_id Template ID.
	 * @param boolean $return_id Set to true to return active theme style ID for this template (needed on template import).
	 */
	public static function set_active_style( $post_id = 0, $return_id = false ) {
		$styles = get_option( BRICKS_DB_THEME_STYLES, [] );

		if ( empty( $post_id ) || is_object( $post_id ) ) {
			$post_id = is_home() ? get_option( 'page_for_posts' ) : get_the_ID();
		}

		$post_type = get_post_type( $post_id );

		$preview_type = ''; // Only applicable to templates

		// Check if Bricks template has preview content
		if ( $post_id && $post_type === BRICKS_DB_TEMPLATE_SLUG && ! $return_id ) {
			$preview_type = Helpers::get_template_setting( 'templatePreviewType', $post_id );
			$preview_id   = Helpers::get_template_setting( 'templatePreviewPostId', $post_id );

			if ( ! empty( $preview_id ) ) {
				$post_id   = $preview_id;
				$post_type = get_post_type( $preview_id );
			}
		}

		// Collect the theme style IDs with score 0.low XX.high [score => style id]
		$matching_theme_style_ids_by_score = [];

		// Check if any style condition is met (if so, return style ID to apply it to post)
		// 2 - Entire website (condition = any)
		// 7 - Post Type
		// 8 - Terms, specific archives, children of specific Post ID
		// 9 - Front page
		// 10 - Specific Post ID (best match)
		foreach ( $styles as $style_id => $style ) {
			$conditions = $style['settings']['conditions']['conditions'] ?? false;

			// Skip styles without conditions
			if ( ! is_array( $conditions ) ) {
				continue;
			}

			$new_matching_theme_style_ids_by_score = Database::screen_conditions( [], $style_id, $conditions, $post_id, $preview_type );

			if ( $new_matching_theme_style_ids_by_score ) {
				foreach ( $new_matching_theme_style_ids_by_score as $score => $matching_style_id ) {
					if ( empty( $matching_theme_style_ids_by_score[ $score ] ) ) {
						$matching_theme_style_ids_by_score[ $score ] = [];
					}

					// Add style ID with score
					$matching_theme_style_ids_by_score[ $score ][] = $matching_style_id;
				}
			}
		}

		// Sort styles by score (highest first)
		ksort( $matching_theme_style_ids_by_score, SORT_NUMERIC );

		$matching_theme_style_ids = [];

		// Collect all matching theme styles by score
		foreach ( $matching_theme_style_ids_by_score as $score => $style_ids ) {
			$matching_theme_style_ids = array_merge( $matching_theme_style_ids, $style_ids );
		}

		// If no styles match conditions, return empty array
		if ( empty( $matching_theme_style_ids ) ) {
			return;
		}

		// Default: Get most specific style (highest score)
		if ( ! Database::get_setting( 'themeStylesLoadingMethod' ) ) {
			$matching_theme_style_ids = [ end( $matching_theme_style_ids ) ];
		}

		/**
		 * Collect all theme styles that match conditions and sort them by score.
		 *
		 * Enable via Bricks > Settings > Miscellaneous > Theme styles: Collect all styles that match conditions
		 *
		 * @since 2.0
		 */
		foreach ( $matching_theme_style_ids as $score => $style_id ) {
			self::$settings_by_id[ $style_id ] = $styles[ $style_id ]['settings'] ?? [];
		}

		if ( $return_id ) {
			return array_keys( self::$settings_by_id );
		}
	}

	/**
	 * Get active theme style settings
	 *
	 * Start search from the most specific style (= last) and return the first match.
	 *
	 * @param string $group_key Theme style group key.
	 * @param int    $setting_key Theme style setting key.
	 * @return mixed|null Returns the value of the setting if found, otherwise null.
	 * @example Theme_Styles::get_setting_by_key( 'popup', 'popupBreakpointMode' );
	 *
	 * @since 2.0
	 */
	public static function get_setting_by_key( $group_key = '', $setting_key = '' ) {
		foreach ( array_reverse( self::$settings_by_id ) as $style_id => $settings ) {
			if ( $group_key && $setting_key ) {
				return isset( $settings[ $group_key ][ $setting_key ] ) ? $settings[ $group_key ][ $setting_key ] : null;
			}

			elseif ( $group_key ) {
				return isset( $settings[ $group_key ] ) ? $settings[ $group_key ] : null;
			}
		}
	}
}
