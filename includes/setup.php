<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Setup {
	public static $control_options = [];

	/**
	 * Set Google Maps API key stored in Bricks settings for ACF
	 *
	 * Avoids having to add this ACF action manually into child theme.
	 *
	 * https://www.advancedcustomfields.com/blog/google-maps-api-settings/
	 */
	public function acf_set_google_maps_api_key( $api ) {
		if ( is_array( $api ) ) {
			$api['key'] = Database::$global_settings['apiKeyGoogleMaps'];
		}

		return $api;
	}

	public function __construct() {
		add_action( 'bricks_body', [ $this, 'body_tag' ], 1 );
		add_filter( 'body_class', [ $this, 'body_class' ] );

		// Save Google Maps API key stored in Bricks as ACF setting
		if ( class_exists( 'ACF' ) && isset( Database::$global_settings['apiKeyGoogleMaps'] ) ) {
			add_filter( 'acf/fields/google_map/api', [ $this, 'acf_set_google_maps_api_key' ] );
		}

		add_filter( 'pre_get_document_title', [ $this, 'pre_get_document_title' ], 10, 1 );

		add_action( 'after_switch_theme', [ $this, 'after_switch_theme' ], 10, 2 );
		add_action( 'switch_theme', [ $this, 'switch_theme' ] );
		add_action( 'after_setup_theme', [ $this, 'after_setup_theme' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'widgets_init', [ $this, 'widgets_init' ] );

		add_filter( 'shortcode_atts_gallery', [ $this, 'shortcode_atts_gallery' ], 10, 3 );

		add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 99 );
		add_action( 'edit_form_top', [ $this, 'edit_form_top' ] );

		add_filter( 'nav_menu_css_class', [ $this, 'nav_menu_css_class' ], 10, 4 );
		add_filter( 'script_loader_tag', [ $this, 'custom_script_attributes' ], 10, 3 );

		$this->init_performance();

		/**
		 * Run on 'init' (again @since 1.5.5)
		 *
		 * Priority 99: Ensures custom taxonomies, etc. are all already registered.
		 *
		 * @see #3p0u7xb
		 */
		add_action( 'init', [ $this, 'init_control_options' ], 99 );

		// Tweak the query results in the builder (@since 1.11)
		if ( Builder::get_query_max_results() ) {
			add_filter( 'bricks/query/result', [ $this, 'builder_query_max_results' ], 10, 2 );
		}
	}

	/**
	 * Body classes
	 *
	 * @since 1.0
	 */
	public function body_class( $classes ) {
		// Is frontend
		if ( bricks_is_frontend() ) {
			$classes[] = 'brx-body';
			$classes[] = 'bricks-is-frontend';

			// https://developer.wordpress.org/block-editor/developers/themes/theme-support/#responsive-embedded-content
			$classes[] = 'wp-embed-responsive';
		}

		// Site layout: boxed, wide (default)
		if ( ! bricks_is_builder_main() && ! empty( Database::$page_settings['siteLayout'] ) ) {
			$classes[] = 'brx-' . Database::$page_settings['siteLayout'];
		} elseif ( ! bricks_is_builder_main() && Theme_Styles::get_setting_by_key( 'general', 'siteLayout' ) ) {
			$classes[] = 'brx-' . Theme_Styles::get_setting_by_key( 'general', 'siteLayout' );
		}

		// Header position: Left, right
		$header_settings = Helpers::get_template_settings( Database::$active_templates['header'] );

		if ( ! bricks_is_builder_main() && ! empty( $header_settings['headerPosition'] ) ) {
			// If header is not disabled via page setting 'headerDisabled'
			if ( ! Database::is_template_disabled( 'header' ) ) {
				$classes[] = "brx-header-{$header_settings['headerPosition']}";
			}
		}

		// Page classes <body> (@since 1.7.2)
		if ( ! bricks_is_builder_main() && ! empty( Database::$page_settings['bodyClasses'] ) ) {
			$page_classes = explode( ' ', bricks_render_dynamic_data( Database::$page_settings['bodyClasses'] ) );
			$classes      = array_merge( $classes, $page_classes );
		}

		return $classes;
	}

	/**
	 * Opening body tag
	 *
	 * @since 1.5
	 */
	public function body_tag() {
		$body_attributes = [];

		// Get body classes
		$body_attributes['class'] = get_body_class();

		if ( bricks_is_builder() ) {
			$body_attributes['data-builder-mode'] = Database::get_setting( 'builderMode', 'dark' );

			$body_attributes['data-builder-window'] = bricks_is_builder_main() ? 'main' : 'iframe';
		}

		// NOTE: Undocumented
		$body_attributes = apply_filters( 'bricks/body/attributes', $body_attributes );

		$body_attributes_string = '';

		foreach ( $body_attributes as $key => $value ) {
			// Stringify array (e.g. 'class')
			if ( is_array( $value ) ) {
				$value = join( ' ', $value );
			}

			$body_attributes_string .= "{$key}=\"{$value}\"";
		}

		echo "<body {$body_attributes_string}>";

		wp_body_open(); // @since 2.0
	}

	public function init_control_options() {
		self::$control_options = self::get_control_options();
	}

	/**
	 * Custom document title
	 *
	 * @since 1.0
	 */
	public function pre_get_document_title( $title ) {
		if ( get_post_type( get_the_ID() ) === BRICKS_DB_TEMPLATE_SLUG && ! Maintenance::use_custom_template() ) {
			return get_the_title() . ' (' . esc_html__( 'Template', 'bricks' ) . ')';
		}

		if ( bricks_is_builder() ) {
			$title  = is_home() ? get_the_title( get_option( 'page_for_posts' ) ) : get_the_title();
			$title .= ' (' . esc_html__( 'Builder', 'bricks' ) . ')';
		}

		return $title;
	}

	/**
	 * Performance enhancement Bricks settings
	 *
	 * @since 1.0
	 */
	public function init_performance() {
		// Apply performance settings only in builder and frontend
		if ( is_admin() ) {
			return;
		}

		add_action( 'init', [ $this, 'init' ] );
		add_action( 'wp_print_styles', [ $this, 'deregister_styles' ], 100 );
		add_action( 'wp_default_scripts', [ $this, 'wp_default_scripts' ] );
	}

	public function init() {
		// Disable: Emojis (CSS & JS)
		if ( isset( Database::$global_settings['disableEmojis'] ) ) {
			$this->disable_emojis();
		}

		// Disable: Embed
		if ( isset( Database::$global_settings['disableEmbed'] ) ) {
			wp_deregister_script( 'wp-embed' );
		}
	}

	public function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter( 'tiny_mce_plugins', [ $this, 'disable_emojis_tinymce' ] );

		// Remove DNS Prefetch
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	public function disable_emojis_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, [ 'wpemoji' ] );
		} else {
			return [];
		}
	}

	/**
	 * Frontend only: Remove Gutenberg blocks stylesheet file
	 */
	public function deregister_styles() {
		$post_id   = get_the_ID();
		$post_type = get_post_type( $post_id );

		if ( bricks_is_builder() ) {
			return;
		}

		if ( $post_type === BRICKS_DB_TEMPLATE_SLUG ) {
			return;
		}

		// Return: Page is rendered with Bricks
		$is_rendered_with_bricks = Helpers::render_with_bricks( $post_id );

		if ( $is_rendered_with_bricks ) {
			wp_dequeue_style( 'wp-block-library' );

			// After WP 5.9
			wp_dequeue_style( 'global-styles' );
		}
	}

	public function wp_default_scripts( $scripts ) {
		// Return: Not frontend (e.g. wp-admin, builder)
		if ( ! bricks_is_frontend() ) {
			return $scripts;
		}

		// Disable jQuery migrate
		if ( isset( Database::$global_settings['disableJqueryMigrate'] ) ) {
			if ( ! empty( $scripts->registered['jquery'] ) ) {
				$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, [ 'jquery-migrate' ] );
			}
		}
	}

	/**
	 * First things first
	 *
	 * @since 1.0
	 */
	public function after_setup_theme() {
		/**
		 * Make theme available for translation
		 *
		 * @since 1.11.1.1: Temporary fix for WP 6.7 translation loading issue
		 * @see https://core.trac.wordpress.org/ticket/62337
		 */
		if ( version_compare( get_bloginfo( 'version' ), '6.7', '==' ) ) {
			add_action(
				'init',
				function() {
					global $l10n, $wp_textdomain_registry;
					$domain = 'bricks';
					$locale = get_locale();
					$wp_textdomain_registry->set( $domain, $locale, get_template_directory() . '/languages' );
					if ( isset( $l10n[ $domain ] ) ) {
						unset( $l10n[ $domain ] );
					}
					load_theme_textdomain( $domain, get_template_directory() . '/languages' );
				}
			);
		}

		// Normal translation loading for all other versions
		else {
			load_theme_textdomain( 'bricks', get_template_directory() . '/languages' );
		}

		// Add RSS feed links to <head> for posts and comments
		add_theme_support( 'automatic-feed-links' );

		// Let WordPress manage the document <title>
		add_theme_support( 'title-tag' );

		/**
		 * Switch default core markup for search form, comment form, comments, gallery and caption to output valid HTML5
		 *
		 * Removed 'comment-form' as it adds non-removable 'novalidate' attribute to the form (@since 1.8)
		 */
		add_theme_support( 'html5', [ 'search-form', 'comment-list', 'gallery', 'caption', 'script', 'style' ] );

		// Add Menu Support
		add_theme_support( 'menus' );

		// Enable support for post thumbnails and declare custom sizes
		add_theme_support( 'post-thumbnails' );

		// Enable opt-in Block Editor (Gutenberg) settings (@since 1.12.2)
		add_theme_support( 'custom-spacing' );
		add_theme_support( 'border' );

		// Enable custom page excerpt
		add_post_type_support( 'page', 'excerpt' );

		// Theme-specific image sizes
		if ( isset( Database::$global_settings['customImageSizes'] ) ) {
			add_image_size( 'bricks_large_16x9', 1200, 675, true );
			add_image_size( 'bricks_large', 1200, 9999 );
			add_image_size( 'bricks_large_square', 1200, 1200, true );
			add_image_size( 'bricks_medium', 600, 9999 );
			add_image_size( 'bricks_medium_square', 600, 600, true );
		}

		// Add support for wide and full width alignment (https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-support/#wide-alignment)
		add_theme_support( 'align-wide' );
	}

	/**
	 * On theme activation
	 *
	 * @param string   $old_name Old theme name.
	 * @param WP_Theme $old_theme Instance of the old theme.
	 * @since 1.0
	 */
	public function after_switch_theme( $old_name, $old_theme ) {
		// Redirect to Bricks - Getting Started admin page after theme activation
		if ( isset( $_GET['activated'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bricks' ) );
			exit;
		}
	}

	/**
	 * On theme deactivation
	 *
	 * Delete license data transient (hack to manually flush license data before transient expires)
	 *
	 * TODO: Add redirect after theme deactivation to collect feedback via 'https://codex.wordpress.org/Plugin_API/Action_Reference/switch_theme'
	 *
	 * @since 1.0
	 */
	public function switch_theme() {
		delete_transient( 'bricks_license_status' );
	}

	/**
	 * Register styles and scripts to enqueue in builder and frontend respectively
	 *
	 * @since 1.0
	 */
	public function enqueue_scripts() {
		/**
		 * Styles
		 */

		// Load frontend CSS files OR inline styles (default: inline style)
		if ( Database::get_setting( 'cssLoading' ) !== 'file' || bricks_is_builder() ) {
			if ( ! Database::get_setting( 'disableBricksCascadeLayer' ) ) { // @since 2.0
				wp_enqueue_style( 'bricks-frontend', BRICKS_URL_ASSETS . 'css/frontend-layer.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/frontend-layer.min.css' ) );
			} else {
				wp_enqueue_style( 'bricks-frontend', BRICKS_URL_ASSETS . 'css/frontend.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/frontend.min.css' ) );
			}
		} else {
			if ( ! Database::get_setting( 'disableBricksCascadeLayer' ) ) { // @since 2.0
				wp_enqueue_style( 'bricks-frontend', BRICKS_URL_ASSETS . 'css/frontend-light-layer.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/frontend-light-layer.min.css' ) );
			} else {
				wp_enqueue_style( 'bricks-frontend', BRICKS_URL_ASSETS . 'css/frontend-light.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/frontend-light.min.css' ) );
			}
		}

		/**
		 * Overwrite custom styles for Gutenberg blocks
		 *
		 * For Bricks elements: Image, Image gallery
		 *
		 * @since 2.1
		 */
		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_add_inline_style( 'wp-block-library', ':where(figure) { margin: 0; }' );
			},
			20
		);

		/**
		 * Scripts
		 */

		// Contains common JS libraries & Bricks-specific frontend.js init scripts
		wp_enqueue_script( 'bricks-scripts', BRICKS_URL_ASSETS . 'js/bricks.min.js', [], filemtime( BRICKS_PATH_ASSETS . 'js/bricks.min.js' ), true );

		// Register Query Filters JS file, but only enqueue when needed (@since 1.12)
		if ( Helpers::enabled_query_filters() ) {
			wp_register_script( 'bricks-filters', BRICKS_URL_ASSETS . 'js/filters.min.js', [ 'bricks-scripts' ], filemtime( BRICKS_PATH_ASSETS . 'js/filters.min.js' ), true );
		}

		// Element Form (setting: enableRecaptcha)
		$recaptcha_api_key  = Database::$global_settings['apiKeyGoogleRecaptcha'] ?? false;
		$recaptcha_language = Database::$global_settings['recaptchaLanguage'] ?? false;

		if ( ! bricks_is_builder() && $recaptcha_api_key ) {
			$recaptcha_script_url = "https://www.google.com/recaptcha/api.js?render=$recaptcha_api_key";

			if ( $recaptcha_language ) {
				$recaptcha_script_url .= "&hl=$recaptcha_language";
			}

			wp_register_script( 'bricks-google-recaptcha', $recaptcha_script_url, null, true );
		}

		// Element Form (setting: enableHCaptcha)
		$hcaptcha_api_key = Database::$global_settings['apiKeyHCaptcha'] ?? false;

		if ( ! bricks_is_builder() && $hcaptcha_api_key ) {
			wp_register_script( 'bricks-hcaptcha', 'https://hcaptcha.com/1/api.js', null, true );
		}

		// Form element (setting: enableTurnstile)
		$turnstile_api_key = Database::$global_settings['apiKeyTurnstile'] ?? false;

		if ( ! bricks_is_builder() && $turnstile_api_key ) {
			wp_register_script( 'bricks-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', null, true );
		}

		// Map element: Leaflet (@since 2.1)
		wp_register_script( 'bricks-leaflet', BRICKS_URL_ASSETS . 'js/libs/leaflet.js', [], '1.9.4', true );

		// STEP: Register scripts
		wp_register_script( 'bricks-flatpickr', BRICKS_URL_ASSETS . 'js/libs/flatpickr.min.js', [ 'bricks-scripts' ], '4.5.2', true );
		wp_register_script( 'bricks-isotope', BRICKS_URL_ASSETS . 'js/libs/isotope.min.js', [ 'bricks-scripts' ], '3.0.4', true );

		/**
		 * NOTE: Renamed JS class 'PhotoSwipe' JS class to 'PhotoSwipe5' in photoswipe.umd.min.js to avoid conflicts with WooCommerce PhotoSwipe 4 version
		 */
		wp_register_script( 'bricks-photoswipe', BRICKS_URL_ASSETS . 'js/libs/photoswipe.umd.min.js', [ 'bricks-scripts' ], '5.4.4', true );
		wp_register_script( 'bricks-photoswipe-lightbox', BRICKS_URL_ASSETS . 'js/libs/photoswipe-lightbox.umd.min.js', [ 'bricks-scripts' ], '5.4.4', true );
		wp_register_script( 'bricks-photoswipe-caption', BRICKS_URL_ASSETS . 'js/libs/photoswipe-caption.umd.min.js', [ 'bricks-scripts' ], '1.2.7', true );

		wp_register_script( 'bricks-piechart', BRICKS_URL_ASSETS . 'js/libs/easypiechart.min.js', [ 'bricks-scripts' ], '2.1.7', true );
		wp_register_script( 'bricks-prettify', BRICKS_URL_ASSETS . 'js/libs/prettify.min.js', [ 'bricks-scripts' ], false, true );
		wp_register_script( 'bricks-swiper', BRICKS_URL_ASSETS . 'js/libs/swiper.min.js', [ 'bricks-scripts' ], '8.4.4', true ); // @pre 1.5 (for flat swiper element)
		wp_register_script( 'bricks-splide', BRICKS_URL_ASSETS . 'js/libs/splide.min.js', [ 'bricks-scripts' ], '4.1.4', true ); // @since 1.5 (for nestable elements)
		wp_register_script( 'bricks-typed', BRICKS_URL_ASSETS . 'js/libs/typed.min.js', [ 'bricks-scripts' ], '2.0.9', true );
		wp_register_script( 'bricks-tocbot', BRICKS_URL_ASSETS . 'js/libs/tocbot.min.js', [ 'bricks-scripts' ], '4.21.0', true ); // @since 1.8.5

		wp_register_script( 'bricks-tinymce8', BRICKS_URL_ASSETS . 'js/libs/tinymce/tinymce.min.js', [ 'bricks-scripts' ], '8.0.2', true ); // @since 2.1
		wp_register_script( 'bricks-tinymce8-builder', BRICKS_URL_ASSETS . 'js/libs/tinymce/tinymce8.bundle.min.js', [ 'bricks-scripts' ], '8.0.2', true ); // @since 2.1

		// STEP: Register styles
		if ( ! Database::get_setting( 'disableBricksCascadeLayer' ) ) { // @since 2.0
			wp_register_style( 'bricks-splide', BRICKS_URL_ASSETS . 'css/libs/splide-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/splide-layer.min.css' ) );
			wp_register_style( 'bricks-animate', BRICKS_URL_ASSETS . 'css/libs/animate-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/animate-layer.min.css' ) );
			wp_register_style( 'bricks-flatpickr', BRICKS_URL_ASSETS . 'css/libs/flatpickr-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/flatpickr-layer.min.css' ) );
			wp_register_style( 'bricks-isotope', BRICKS_URL_ASSETS . 'css/libs/isotope-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/isotope-layer.min.css' ) );
			// NOTE: Conflicts with WooCommerce lightbox if using photoswipe-layer.min.css (@since 2.1)
			// wp_register_style( 'bricks-photoswipe', BRICKS_URL_ASSETS . 'css/libs/photoswipe-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/photoswipe-layer.min.css' ) );
			wp_register_style( 'bricks-photoswipe', BRICKS_URL_ASSETS . 'css/libs/photoswipe.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/photoswipe.min.css' ) );
			wp_register_style( 'bricks-prettify', BRICKS_URL_ASSETS . 'css/libs/prettify-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/prettify-layer.min.css' ) );
			wp_register_style( 'bricks-swiper', BRICKS_URL_ASSETS . 'css/libs/swiper-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/swiper-layer.min.css' ) );
			wp_register_style( 'bricks-tooltips', BRICKS_URL_ASSETS . 'css/libs/tooltips-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/tooltips-layer.min.css' ) );
			wp_register_style( 'bricks-ajax-loader', BRICKS_URL_ASSETS . 'css/libs/loading-animation-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/loading-animation-layer.min.css' ) );
		} else {
			wp_register_style( 'bricks-splide', BRICKS_URL_ASSETS . 'css/libs/splide.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/splide.min.css' ) );
			wp_register_style( 'bricks-animate', BRICKS_URL_ASSETS . 'css/libs/animate.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/animate.min.css' ) );
			wp_register_style( 'bricks-flatpickr', BRICKS_URL_ASSETS . 'css/libs/flatpickr.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/flatpickr.min.css' ) );
			wp_register_style( 'bricks-isotope', BRICKS_URL_ASSETS . 'css/libs/isotope.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/isotope.min.css' ) );
			wp_register_style( 'bricks-photoswipe', BRICKS_URL_ASSETS . 'css/libs/photoswipe.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/photoswipe.min.css' ) );
			wp_register_style( 'bricks-prettify', BRICKS_URL_ASSETS . 'css/libs/prettify.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/prettify.min.css' ) );
			wp_register_style( 'bricks-swiper', BRICKS_URL_ASSETS . 'css/libs/swiper.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/swiper.min.css' ) );
			wp_register_style( 'bricks-tooltips', BRICKS_URL_ASSETS . 'css/libs/tooltips.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/tooltips.min.css' ) );
			wp_register_style( 'bricks-ajax-loader', BRICKS_URL_ASSETS . 'css/libs/loading-animation.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/loading-animation.min.css' ) );
		}

		// Map element: Leaflet (@since 2.1)
		wp_register_style( 'bricks-leaflet', BRICKS_URL_ASSETS . 'css/libs/leaflet/leaflet.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/leaflet/leaflet.css' ) );

		// Icon fonts
		if ( ! Database::get_setting( 'disableBricksCascadeLayer' ) ) { // @since 2.0
			wp_register_style( 'bricks-font-awesome-6', BRICKS_URL_ASSETS . 'css/libs/font-awesome-6-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/font-awesome-6-layer.min.css' ) );
			wp_register_style( 'bricks-font-awesome-6-brands', BRICKS_URL_ASSETS . 'css/libs/font-awesome-6-brands-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/font-awesome-6-brands-layer.min.css' ) );
			wp_register_style( 'bricks-ionicons', BRICKS_URL_ASSETS . 'css/libs/ionicons-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/ionicons-layer.min.css' ) );
			wp_register_style( 'bricks-themify-icons', BRICKS_URL_ASSETS . 'css/libs/themify-icons-layer.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/themify-icons-layer.min.css' ) );
		} else {
			wp_register_style( 'bricks-font-awesome-6', BRICKS_URL_ASSETS . 'css/libs/font-awesome-6.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/font-awesome-6.min.css' ) );
			wp_register_style( 'bricks-font-awesome-6-brands', BRICKS_URL_ASSETS . 'css/libs/font-awesome-6-brands.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/font-awesome-6-brands.min.css' ) );
			wp_register_style( 'bricks-ionicons', BRICKS_URL_ASSETS . 'css/libs/ionicons.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/ionicons.min.css' ) );
			wp_register_style( 'bricks-themify-icons', BRICKS_URL_ASSETS . 'css/libs/themify-icons.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/themify-icons.min.css' ) );
		}

		if ( is_404() ) {
			wp_enqueue_style( 'bricks-404', BRICKS_URL_ASSETS . 'css/frontend/404.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/frontend/404.min.css' ) );
		}

		wp_register_style( 'bricks-one-page-navigation', BRICKS_URL_ASSETS . 'css/frontend/one-page-navigation.min.css', [ 'bricks-frontend' ], filemtime( BRICKS_PATH_ASSETS . 'css/frontend/one-page-navigation.min.css' ) );

		if ( bricks_is_builder_iframe() || isset( Database::$page_settings['onePageNavigation'] ) ) {
			wp_enqueue_style( 'bricks-one-page-navigation' );
		}

		if ( bricks_is_builder_main() ) {
			// Stylelint bundle (@since 1.10)
			wp_enqueue_script( 'stylelint', BRICKS_URL_ASSETS . 'js/libs/stylelint-bundle.min.js', [ 'bricks-scripts' ], filemtime( BRICKS_PATH_ASSETS . 'js/libs/stylelint-bundle.min.js' ), true );
		}
	}

	/**
	 * Gallery shortcode default size
	 *
	 * @since 1.0
	 */
	public function shortcode_atts_gallery( $output, $pairs, $atts ) {
		// Check if custom image size exist, if so set to gallery default
		if ( in_array( 'bricks_medium_square', get_intermediate_image_sizes() ) ) {
			$output['size'] = 'bricks_medium_square';
		}

		return $output;
	}

	/**
	 * Sidebars
	 *
	 * @since 1.0
	 */
	public static function widgets_init() {
		// Default sidebar
		register_sidebar(
			[
				'name'          => esc_html__( 'Sidebar', 'bricks' ),
				'id'            => 'sidebar',
				'before_widget' => '<li class="bricks-widget-wrapper">',
				'after_widget'  => '</li>',
				'before_title'  => '<h4 class="bricks-widget-title">',
				'after_title'   => '</h4>',
			]
		);

		// Custom sidebars
		$bricks_sidebars = get_option( BRICKS_DB_SIDEBARS, [] );

		foreach ( $bricks_sidebars as $sidebar ) {
			register_sidebar(
				[
					'name'          => $sidebar['name'],
					'id'            => $sidebar['id'],
					'description'   => $sidebar['description'],
					'before_widget' => '<li class="bricks-widget-wrapper">',
					'after_widget'  => '</li>',
					'before_title'  => '<h4 class="bricks-widget-title">',
					'after_title'   => '</h4>',
				]
			);
		}
	}

	/**
	 * WP admin bar: Add menu bar item "Edit with Bricks"
	 *
	 * @since 1.0
	 */
	public function admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ) {
		if ( bricks_is_builder() ) {
			return;
		}

		// Show "Edit with Bricks" in admin area only on post/page edit screen
		if ( is_admin() && get_current_screen()->base !== 'post' ) {
			return;
		}

		$post_id = get_the_ID();

		if ( ! Capabilities::current_user_can_use_builder( $post_id ) ) {
			return;
		}

		if ( is_home() ) {
			$post_id = get_option( 'page_for_posts' );
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$post_id = wc_get_page_id( 'shop' );
		}

		if ( ! Helpers::is_post_type_supported( $post_id ) ) {
			if ( ! empty( Database::$active_templates['content'] ) ) {
				$post_id = Database::$active_templates['content'];
			} else {
				return;
			}
		}

		$wp_admin_bar->add_menu(
			[
				'id'    => 'edit_with_bricks',
				/* translators: %s: "Bricks" (theme name) */
				'title' => sprintf( esc_html__( 'Edit with %s', 'bricks' ), 'Bricks' ),
				'href'  => $post_id ? Helpers::get_builder_edit_link( $post_id ) : '#',
			]
		);

		$parts = [
			'header'  => esc_html__( 'Edit header', 'bricks' ) . ': ',
			'content' => esc_html__( 'Edit content', 'bricks' ) . ': ',
			'footer'  => esc_html__( 'Edit footer', 'bricks' ) . ': ',
		];

		foreach ( $parts as $part => $label ) {
			if ( empty( Database::$active_templates[ $part ] ) || $post_id == Database::$active_templates[ $part ] ) {
				continue;
			}

			$part_post_id = Database::$active_templates[ $part ];

			$wp_admin_bar->add_menu(
				[
					'parent' => 'edit_with_bricks',
					'id'     => 'edit_with_bricks_' . $part,
					'title'  => sprintf( '<span>' . $label . '</span><span>%s</span>', get_the_title( $part_post_id ) ),
					'href'   => Helpers::get_builder_edit_link( $part_post_id ),
				]
			);
		}

		// WooCommerce (@since 1.11.1)
		if ( WooCommerce::is_woocommerce_active() ) {
			$woo_templates    = WooCommerce::get_woo_templates();
			$active_templates = WooCommerce::get_active_templates_for_current_page(); // @since 1.12

			// Add WooCommerce templates to admin bar
			foreach ( $active_templates as $template => $template_id ) {
				if ( ! $template_id ) {
					continue;
				}

				$menu_title = $woo_templates[ $template ] ?? 'Unknown';

				$wp_admin_bar->add_menu(
					[
						'parent' => 'edit_with_bricks',
						'id'     => 'edit_with_bricks_' . $template,
						'title'  => esc_html__( 'Edit', 'bricks' ) . ": $menu_title (WooCommerce)",
						'href'   => Helpers::get_builder_edit_link( $template_id ),
						'meta'   => [
							'class' => 'brx-woocommerce',
						],
					]
				);
			}
		}

		$wp_admin_bar->add_menu(
			[
				'parent' => 'edit_with_bricks',
				'id'     => 'bricks_settings',
				'title'  => esc_html__( 'Go to', 'bricks' ) . ': Bricks > ' . esc_html__( 'Settings', 'bricks' ),
				'href'   => admin_url( 'admin.php?page=bricks-settings' ),
			]
		);

		$wp_admin_bar->add_menu(
			[
				'parent' => 'edit_with_bricks',
				'id'     => 'bricks_templates',
				'title'  => esc_html__( 'Go to', 'bricks' ) . ': Bricks > ' . esc_html__( 'Templates', 'bricks' ),
				'href'   => admin_url( 'edit.php?post_type=' . BRICKS_DB_TEMPLATE_SLUG ),
			]
		);

		if ( Database::get_setting( 'deleteBricksData', false ) ) {
			$wp_admin_bar->add_menu(
				[
					'id'    => 'delete_bricks_data',
					'title' => esc_html__( 'Delete Bricks data', 'bricks' ),
					'href'  => Helpers::delete_bricks_data_by_post_id( $post_id ),
					'meta'  => [
						// translators: %s: Post type name
						'onclick' => 'return confirm("' . ( sprintf( esc_html__( 'Are you sure you want to delete the Bricks-generated data for this %s?', 'bricks' ), get_post_type() ) ) . '")',
					],
				]
			);
		}

		// STEP: Editor mode

		// Return: Editing Bricks template
		if ( get_post_type( $post_id ) === BRICKS_DB_TEMPLATE_SLUG ) {
			return;
		}

		$edit_post_link = get_edit_post_link( $post_id );

		// Return: Not an editable post
		if ( ! $edit_post_link ) {
			return;
		}

		$editor_mode = Helpers::get_editor_mode( $post_id );

		if ( ! empty( $_GET['editor_mode'] ) ) {
			$editor_mode = sanitize_text_field( $_GET['editor_mode'] );
		}

		// Bricks mode selected, but no Bricks data exists: Show "Render with WordPress" (@since 1.12)
		Database::set_active_templates();

		if (
			$editor_mode === 'bricks' &&
			! Helpers::get_bricks_data( $post_id, 'content' )
			&& ( Database::$active_templates['content'] == 0 || Database::$active_templates['content'] == $post_id )
		) {
			$editor_mode = 'wordpress';
		}

		$rendered_with_bricks    = sprintf( esc_html__( 'Rendered with %s', 'bricks' ), 'Bricks' ); // translators: %s: "Bricks" (theme name)
		$rendered_with_wordpress = sprintf( esc_html__( 'Rendered with %s', 'bricks' ), 'WordPress' ); // translators: %s: "WordPress"

		$wp_admin_bar->add_menu(
			[
				'id'    => 'editor_mode',
				'title' => $editor_mode === 'wordpress' ? $rendered_with_wordpress : $rendered_with_bricks,
			]
		);

		$render_with_bricks    = sprintf( esc_html__( 'Render with %s', 'bricks' ), 'Bricks' ); // translators: %s: "Bricks" (theme name)
		$render_with_wordpress = sprintf( esc_html__( 'Render with %s', 'bricks' ), 'WordPress' ); // translators: %s: "WordPress"

		if ( $editor_mode === 'wordpress' ) {
			$wp_admin_bar->add_menu(
				[
					'parent' => 'editor_mode',
					'id'     => 'editor_mode_bricks',
					'title'  => $render_with_bricks,
					'href'   => wp_nonce_url( add_query_arg( 'editor_mode', 'bricks', $edit_post_link ), '_bricks_editor_mode_nonce', '_bricksmode' )
				]
			);
		} else {
			$wp_admin_bar->add_menu(
				[
					'parent' => 'editor_mode',
					'id'     => 'editor_mode_wordpress',
					'title'  => $render_with_wordpress,
					'href'   => wp_nonce_url( add_query_arg( 'editor_mode', 'wordpress', $edit_post_link ), '_bricks_editor_mode_nonce', '_bricksmode' )
				]
			);
		}
	}

	/**
	 * Post type display: Add "Edit with Bricks" link for post type without "editor" support
	 *
	 * @since 1.10.2
	 */
	public function edit_form_top() {
		$post_id = get_the_ID();

		// Show "Edit with Bricks" in admin area only on post/page edit screen
		if ( is_admin() && get_current_screen()->base !== 'post' ) {
			return;
		}

		if ( ! Capabilities::current_user_can_use_builder( $post_id ) ) {
			return;
		}

		if ( ! Helpers::is_post_type_supported( $post_id ) ) {
			return;
		}

		// Return: Editing Bricks template
		if ( get_post_type( $post_id ) === BRICKS_DB_TEMPLATE_SLUG ) {
			return;
		}

		$edit_post_link = get_edit_post_link( $post_id );

		// Return: Not an editable post
		if ( ! $edit_post_link ) {
			return;
		}

		$edit_post_link = wp_nonce_url( $edit_post_link, '_bricks_editor_mode_nonce', '_bricksmode' );

		$edit_with_bricks = sprintf(
			'<a href="%s" class="button button-primary">%s</a>',
			$post_id ? Helpers::get_builder_edit_link( $post_id ) : '#',
			// translators: %s: "Bricks" (theme name)
			sprintf( esc_html__( 'Edit with %s', 'bricks' ), 'Bricks' )
		);

		echo '<div class="bricks-classic-editor-wrapper">' . $edit_with_bricks . '</div>';
	}

	/**
	 * Nav menu classes
	 *
	 * @since 1.0
	 */
	public function nav_menu_css_class( $classes, $item, $args, $depth ) {
		$classes[] = 'bricks-menu-item';

		return $classes;
	}

	/**
	 * Custom script attributes (async and defer)
	 *
	 * https://www.growingwiththeweb.com/2014/02/async-vs-defer-attributes.html
	 *
	 * @since 1.0
	 */
	public function custom_script_attributes( $tag, $handle, $src ) {
		// Add async and defer atts to: Google Maps & Google reCAPTCHA script tag
		// NOTE: Don't load Google Maps JS async when loading 'bricks-google-maps-infobox'

		if ( $handle === 'bricks-google-recaptcha' ) {
			$tag = str_replace( 'src=', 'async="async" defer="defer" src=', $tag );
		}

		// Defer loading of PhotoSwipe
		elseif ( $handle === 'bricks-photoswipe' ) {
			$tag = str_replace( 'src=', 'defer="defer" src=', $tag );
		}

		return $tag;
	}

	/**
	 * Return map styles from https://snazzymaps.com/explore for Map element
	 *
	 * @param string $style Style name (@since 1.9.3).
	 *
	 * @since 1.0
	 */
	public static function get_map_styles( $style = '' ) {
		$map_styles = [
			'ultraLightWithLabels' => [
				'label' => 'Ultra light with labels',
				'style' => '[ { "featureType": "water", "elementType": "geometry", "stylers": [ { "color": "#e9e9e9" }, { "lightness": 17 } ] }, { "featureType": "landscape", "elementType": "geometry", "stylers": [ { "color": "#f5f5f5" }, { "lightness": 20 } ] }, { "featureType": "road.highway", "elementType": "geometry.fill", "stylers": [ { "color": "#ffffff" }, { "lightness": 17 } ] }, { "featureType": "road.highway", "elementType": "geometry.stroke", "stylers": [ { "color": "#ffffff" }, { "lightness": 29 }, { "weight": 0.2 } ] }, { "featureType": "road.arterial", "elementType": "geometry", "stylers": [ { "color": "#ffffff" }, { "lightness": 18 } ] }, { "featureType": "road.local", "elementType": "geometry", "stylers": [ { "color": "#ffffff" }, { "lightness": 16 } ] }, { "featureType": "poi", "elementType": "geometry", "stylers": [ { "color": "#f5f5f5" }, { "lightness": 21 } ] }, { "featureType": "poi.park", "elementType": "geometry", "stylers": [ { "color": "#dedede" }, { "lightness": 21 } ] }, { "elementType": "labels.text.stroke", "stylers": [ { "visibility": "on" }, { "color": "#ffffff" }, { "lightness": 16 } ] }, { "elementType": "labels.text.fill", "stylers": [ { "saturation": 36 }, { "color": "#333333" }, { "lightness": 40 } ] }, { "elementType": "labels.icon", "stylers": [ { "visibility": "off" } ] }, { "featureType": "transit", "elementType": "geometry", "stylers": [ { "color": "#f2f2f2" }, { "lightness": 19 } ] }, { "featureType": "administrative", "elementType": "geometry.fill", "stylers": [ { "color": "#fefefe" }, { "lightness": 20 } ] }, { "featureType": "administrative", "elementType": "geometry.stroke", "stylers": [ { "color": "#fefefe" }, { "lightness": 17 }, { "weight": 1.2 } ] } ]',
			],
			'blueWater'            => [
				'label' => 'Blue water',
				'style' => '[ { "featureType": "administrative", "elementType": "labels.text.fill", "stylers": [ { "color": "#444444" } ] }, { "featureType": "landscape", "elementType": "all", "stylers": [ { "color": "#f2f2f2" } ] }, { "featureType": "poi", "elementType": "all", "stylers": [ { "visibility": "off" } ] }, { "featureType": "road", "elementType": "all", "stylers": [ { "saturation": -100 }, { "lightness": 45 } ] }, { "featureType": "road.highway", "elementType": "all", "stylers": [ { "visibility": "simplified" } ] }, { "featureType": "road.arterial", "elementType": "labels.icon", "stylers": [ { "visibility": "off" } ] }, { "featureType": "transit", "elementType": "all", "stylers": [ { "visibility": "off" } ] }, { "featureType": "water", "elementType": "all", "stylers": [ { "color": "#46bcec" }, { "visibility": "on" } ] } ]',
			],
			'lightDream'           => [
				'label' => 'Light dream',
				'style' => '[ { "featureType": "landscape", "stylers": [ { "hue": "#FFBB00" }, { "saturation": 43.400000000000006 }, { "lightness": 37.599999999999994 }, { "gamma": 1 } ] }, { "featureType": "road.highway", "stylers": [ { "hue": "#FFC200" }, { "saturation": -61.8 }, { "lightness": 45.599999999999994 }, { "gamma": 1 } ] }, { "featureType": "road.arterial", "stylers": [ { "hue": "#FF0300" }, { "saturation": -100 }, { "lightness": 51.19999999999999 }, { "gamma": 1 } ] }, { "featureType": "road.local", "stylers": [ { "hue": "#FF0300" }, { "saturation": -100 }, { "lightness": 52 }, { "gamma": 1 } ] }, { "featureType": "water", "stylers": [ { "hue": "#0078FF" }, { "saturation": -13.200000000000003 }, { "lightness": 2.4000000000000057 }, { "gamma": 1 } ] }, { "featureType": "poi", "stylers": [ { "hue": "#00FF6A" }, { "saturation": -1.0989010989011234 }, { "lightness": 11.200000000000017 }, { "gamma": 1 } ] } ]',
			],
			'blueEssence'          => [
				'label' => 'Blue essence',
				'style' => '[ { "featureType": "landscape.natural", "elementType": "geometry.fill", "stylers": [ { "visibility": "on" }, { "color": "#e0efef" } ] }, { "featureType": "poi", "elementType": "geometry.fill", "stylers": [ { "visibility": "on" }, { "hue": "#1900ff" }, { "color": "#c0e8e8" } ] }, { "featureType": "road", "elementType": "geometry", "stylers": [ { "lightness": 100 }, { "visibility": "simplified" } ] }, { "featureType": "road", "elementType": "labels", "stylers": [ { "visibility": "off" } ] }, { "featureType": "transit.line", "elementType": "geometry", "stylers": [ { "visibility": "on" }, { "lightness": 700 } ] }, { "featureType": "water", "elementType": "all", "stylers": [ { "color": "#7dcdcd" } ] } ]',
			],
			'appleMapsesque'       => [
				'label' => 'Apple maps-esque',
				'style' => '[ { "featureType": "landscape.man_made", "elementType": "geometry", "stylers": [ { "color": "#f7f1df" } ] }, { "featureType": "landscape.natural", "elementType": "geometry", "stylers": [ { "color": "#d0e3b4" } ] }, { "featureType": "landscape.natural.terrain", "elementType": "geometry", "stylers": [ { "visibility": "off" } ] }, { "featureType": "poi", "elementType": "labels", "stylers": [ { "visibility": "off" } ] }, { "featureType": "poi.business", "elementType": "all", "stylers": [ { "visibility": "off" } ] }, { "featureType": "poi.medical", "elementType": "geometry", "stylers": [ { "color": "#fbd3da" } ] }, { "featureType": "poi.park", "elementType": "geometry", "stylers": [ { "color": "#bde6ab" } ] }, { "featureType": "road", "elementType": "geometry.stroke", "stylers": [ { "visibility": "off" } ] }, { "featureType": "road", "elementType": "labels", "stylers": [ { "visibility": "off" } ] }, { "featureType": "road.highway", "elementType": "geometry.fill", "stylers": [ { "color": "#ffe15f" } ] }, { "featureType": "road.highway", "elementType": "geometry.stroke", "stylers": [ { "color": "#efd151" } ] }, { "featureType": "road.arterial", "elementType": "geometry.fill", "stylers": [ { "color": "#ffffff" } ] }, { "featureType": "road.local", "elementType": "geometry.fill", "stylers": [ { "color": "black" } ] }, { "featureType": "transit.station.airport", "elementType": "geometry.fill", "stylers": [ { "color": "#cfb2db" } ] }, { "featureType": "water", "elementType": "geometry", "stylers": [ { "color": "#a2daf2" } ] } ]',
			],
			'paleDawn'             => [
				'label' => 'Pale dawn',
				'style' => '[ { "featureType": "administrative", "elementType": "all", "stylers": [ { "visibility": "on" }, { "lightness": 33 } ] }, { "featureType": "landscape", "elementType": "all", "stylers": [ { "color": "#f2e5d4" } ] }, { "featureType": "poi.park", "elementType": "geometry", "stylers": [ { "color": "#c5dac6" } ] }, { "featureType": "poi.park", "elementType": "labels", "stylers": [ { "visibility": "on" }, { "lightness": 20 } ] }, { "featureType": "road", "elementType": "all", "stylers": [ { "lightness": 20 } ] }, { "featureType": "road.highway", "elementType": "geometry", "stylers": [ { "color": "#c5c6c6" } ] }, { "featureType": "road.arterial", "elementType": "geometry", "stylers": [ { "color": "#e4d7c6" } ] }, { "featureType": "road.local", "elementType": "geometry", "stylers": [ { "color": "#fbfaf7" } ] }, { "featureType": "water", "elementType": "all", "stylers": [ { "visibility": "on" }, { "color": "#acbcc9" } ] } ]',
			],
			'neutralBlue'          => [
				'label' => 'Neutral blue',
				'style' => '[ { "featureType": "water", "elementType": "geometry", "stylers": [ { "color": "#193341" } ] }, { "featureType": "landscape", "elementType": "geometry", "stylers": [ { "color": "#2c5a71" } ] }, { "featureType": "road", "elementType": "geometry", "stylers": [ { "color": "#29768a" }, { "lightness": -37 } ] }, { "featureType": "poi", "elementType": "geometry", "stylers": [ { "color": "#406d80" } ] }, { "featureType": "transit", "elementType": "geometry", "stylers": [ { "color": "#406d80" } ] }, { "elementType": "labels.text.stroke", "stylers": [ { "visibility": "on" }, { "color": "#3e606f" }, { "weight": 2 }, { "gamma": 0.84 } ] }, { "elementType": "labels.text.fill", "stylers": [ { "color": "#ffffff" } ] }, { "featureType": "administrative", "elementType": "geometry", "stylers": [ { "weight": 0.6 }, { "color": "#1a3541" } ] }, { "elementType": "labels.icon", "stylers": [ { "visibility": "off" } ] }, { "featureType": "poi.park", "elementType": "geometry", "stylers": [ { "color": "#2c5a71" } ] } ]',
			],
			'avocadoWorld'         => [
				'label' => 'Avocado world',
				'style' => '[ { "featureType": "water", "elementType": "geometry", "stylers": [ { "visibility": "on" }, { "color": "#aee2e0" } ] }, { "featureType": "landscape", "elementType": "geometry.fill", "stylers": [ { "color": "#abce83" } ] }, { "featureType": "poi", "elementType": "geometry.fill", "stylers": [ { "color": "#769E72" } ] }, { "featureType": "poi", "elementType": "labels.text.fill", "stylers": [ { "color": "#7B8758" } ] }, { "featureType": "poi", "elementType": "labels.text.stroke", "stylers": [ { "color": "#EBF4A4" } ] }, { "featureType": "poi.park", "elementType": "geometry", "stylers": [ { "visibility": "simplified" }, { "color": "#8dab68" } ] }, { "featureType": "road", "elementType": "geometry.fill", "stylers": [ { "visibility": "simplified" } ] }, { "featureType": "road", "elementType": "labels.text.fill", "stylers": [ { "color": "#5B5B3F" } ] }, { "featureType": "road", "elementType": "labels.text.stroke", "stylers": [ { "color": "#ABCE83" } ] }, { "featureType": "road", "elementType": "labels.icon", "stylers": [ { "visibility": "off" } ] }, { "featureType": "road.local", "elementType": "geometry", "stylers": [ { "color": "#A4C67D" } ] }, { "featureType": "road.arterial", "elementType": "geometry", "stylers": [ { "color": "#9BBF72" } ] }, { "featureType": "road.highway", "elementType": "geometry", "stylers": [ { "color": "#EBF4A4" } ] }, { "featureType": "transit", "stylers": [ { "visibility": "off" } ] }, { "featureType": "administrative", "elementType": "geometry.stroke", "stylers": [ { "visibility": "on" }, { "color": "#87ae79" } ] }, { "featureType": "administrative", "elementType": "geometry.fill", "stylers": [ { "color": "#7f2200" }, { "visibility": "off" } ] }, { "featureType": "administrative", "elementType": "labels.text.stroke", "stylers": [ { "color": "#ffffff" }, { "visibility": "on" }, { "weight": 4.1 } ] }, { "featureType": "administrative", "elementType": "labels.text.fill", "stylers": [ { "color": "#495421" } ] }, { "featureType": "administrative.neighborhood", "elementType": "labels", "stylers": [ { "visibility": "off" } ] } ]',
			],
			'gowalla'              => [
				'label' => 'Gowalla',
				'style' => '[ { "featureType": "administrative.land_parcel", "elementType": "all", "stylers": [ { "visibility": "off" } ] }, { "featureType": "landscape.man_made", "elementType": "all", "stylers": [ { "visibility": "off" } ] }, { "featureType": "poi", "elementType": "labels", "stylers": [ { "visibility": "off" } ] }, { "featureType": "road", "elementType": "labels", "stylers": [ { "visibility": "simplified" }, { "lightness": 20 } ] }, { "featureType": "road.highway", "elementType": "geometry", "stylers": [ { "hue": "#f49935" } ] }, { "featureType": "road.highway", "elementType": "labels", "stylers": [ { "visibility": "simplified" } ] }, { "featureType": "road.arterial", "elementType": "geometry", "stylers": [ { "hue": "#fad959" } ] }, { "featureType": "road.arterial", "elementType": "labels", "stylers": [ { "visibility": "off" } ] }, { "featureType": "road.local", "elementType": "geometry", "stylers": [ { "visibility": "simplified" } ] }, { "featureType": "road.local", "elementType": "labels", "stylers": [ { "visibility": "simplified" } ] }, { "featureType": "transit", "elementType": "all", "stylers": [ { "visibility": "off" } ] }, { "featureType": "water", "elementType": "all", "stylers": [ { "hue": "#a1cdfc" }, { "saturation": 30 }, { "lightness": 49 } ] } ]',
			]
		];

		$map_styles = apply_filters( 'bricks/builder/map_styles', $map_styles );

		if ( $style && ! empty( $map_styles[ $style ]['style'] ) ) {
			return $map_styles[ $style ]['style'];
		}

		return $map_styles;
	}

	/**
	 * Get default color (same as SCSS color vars)
	 *
	 * For consistent element 'default' color setting
	 *
	 * @since 1.0
	 */
	public static function get_default_color( $color_name ) {
		$default_colors = [
			'primary'          => '#03a9f4',
			'secondary'        => '#81d4fa',
			'heading'          => '#212121',
			'body'             => '#616161',
			'muted'            => '#9e9e9e',
			'info'             => '#309dd4',
			'success'          => '#4cb944',
			'warning'          => '#ffc12b',
			'danger'           => '#f1404b',
			'border'           => '#dddedf',
			'background-light' => '#f0f1f2',
			'background-dark'  => '#263238',
		];

		if ( isset( $default_colors[ $color_name ] ) ) {
			return $default_colors[ $color_name ];
		} else {
			throw new \Exception( "Default color $color_name does not exist." );
		}
	}

	/**
	 * Control options
	 *
	 * @param string $key Single option key to return specific option.
	 *
	 * @since 1.0
	 */
	public static function get_control_options( $key = '' ) {
		if ( empty( self::$control_options ) ) {
			$control_options['backgroundPosition'] = [
				'top left'      => esc_html__( 'Top left', 'bricks' ),
				'top center'    => esc_html__( 'Top center', 'bricks' ),
				'top right'     => esc_html__( 'Top right', 'bricks' ),

				'center left'   => esc_html__( 'Center left', 'bricks' ),
				'center center' => esc_html__( 'Center center', 'bricks' ),
				'center right'  => esc_html__( 'Center right', 'bricks' ),

				'bottom left'   => esc_html__( 'Bottom left', 'bricks' ),
				'bottom center' => esc_html__( 'Bottom center', 'bricks' ),
				'bottom right'  => esc_html__( 'Bottom right', 'bricks' ),

				'custom'        => esc_html__( 'Custom', 'bricks' ),
			];

			$control_options['backgroundRepeat'] = [
				'no-repeat' => esc_html__( 'No repeat', 'bricks' ),
				'repeat-x'  => esc_html__( 'Repeat-x', 'bricks' ),
				'repeat-y'  => esc_html__( 'Repeat-y', 'bricks' ),
				'repeat'    => esc_html__( 'Repeat', 'bricks' ),
			];

			$control_options['backgroundSize'] = [
				'auto'    => esc_html__( 'Auto', 'bricks' ),
				'cover'   => esc_html__( 'Cover', 'bricks' ),
				'contain' => esc_html__( 'Contain', 'bricks' ),
				'custom'  => esc_html__( 'Custom', 'bricks' ),
			];

			$control_options['backgroundAttachment'] = [
				'scroll' => esc_html__( 'Scroll', 'bricks' ),
				'fixed'  => esc_html__( 'Fixed', 'bricks' ),
			];

			// Video poster YouTube size options (@since 1.11)
			$control_options['backgroundVideoPosterYouTubeSize'] = [
				'hqdefault'     => esc_html__( 'High', 'bricks' ),
				'mqdefault'     => esc_html__( 'Medium', 'bricks' ),
				'sddefault'     => esc_html__( 'Standard', 'bricks' ),
				'maxresdefault' => esc_html__( 'Max resolution', 'bricks' ),
			];

			// Used for mix-blend-mode & background-blend-mode (@since 1.9)
			$control_options['blendMode'] = [
				'normal'      => 'normal',
				'multiply'    => 'multiply',
				'screen'      => 'screen',
				'overlay'     => 'overlay',
				'darken'      => 'darken',
				'lighten'     => 'lighten',
				'color-dodge' => 'color-dodge',
				'color-burn'  => 'color-burn',
				'hard-light'  => 'hard-light',
				'soft-light'  => 'soft-light',
				'difference'  => 'difference',
				'exclusion'   => 'exclusion',
				'hue'         => 'hue',
				'saturation'  => 'saturation',
				'color'       => 'color',
				'luminosity'  => 'luminosity',
			];

			$control_options['buttonSizes'] = [
				'sm' => esc_html__( 'Small', 'bricks' ),
				'md' => esc_html__( 'Medium', 'bricks' ),
				'lg' => esc_html__( 'Large', 'bricks' ),
				'xl' => esc_html__( 'Extra large', 'bricks' ),
			];

			$control_options['styles'] = [
				'primary'   => esc_html__( 'Primary', 'bricks' ),
				'secondary' => esc_html__( 'Secondary', 'bricks' ),
				'light'     => esc_html__( 'Light', 'bricks' ),
				'dark'      => esc_html__( 'Dark', 'bricks' ),
				'muted'     => esc_html__( 'Muted', 'bricks' ),
				'info'      => esc_html__( 'Info', 'bricks' ),
				'success'   => esc_html__( 'Success', 'bricks' ),
				'warning'   => esc_html__( 'Warning', 'bricks' ),
				'danger'    => esc_html__( 'Danger', 'bricks' ),
			];

			$control_options['borderStyle'] = [
				'none'   => esc_html__( 'None', 'bricks' ),
				'hidden' => esc_html__( 'Hidden', 'bricks' ),
				'solid'  => esc_html__( 'Solid', 'bricks' ),
				'dotted' => esc_html__( 'Dotted', 'bricks' ),
				'dashed' => esc_html__( 'Dashed', 'bricks' ),
				'double' => esc_html__( 'Double', 'bricks' ),
				'groove' => esc_html__( 'Groove', 'bricks' ),
				'ridge'  => esc_html__( 'Ridge', 'bricks' ),
				'inset'  => esc_html__( 'Inset', 'bricks' ),
				'outset' => esc_html__( 'Outset', 'bricks' ),
			];

			// Identical syntax as Google Fonts variants
			$control_options['fontWeight'] = [
				'100' => '100',
				'200' => '200',
				'300' => '300',
				'400' => '400',
				'500' => '500',
				'600' => '600',
				'700' => '700',
				'800' => '800',
				'900' => '900',
			];

			$control_options['fontStyle'] = [
				'normal'  => esc_html__( 'Normal', 'bricks' ),
				'italic'  => esc_html__( 'Italic', 'bricks' ),
				'oblique' => esc_html__( 'Oblique', 'bricks' ),
			];

			$control_options['iconPosition'] = [
				'left'  => esc_html__( 'Left', 'bricks' ),
				'right' => esc_html__( 'Right', 'bricks' ),
			];

			$control_options['objectFit'] = [
				'contain'    => esc_html__( 'Contain', 'bricks' ),
				'cover'      => esc_html__( 'Cover', 'bricks' ),
				'fill'       => esc_html__( 'Fill', 'bricks' ),
				'scale-down' => esc_html__( 'Scale down', 'bricks' ),
				'none'       => esc_html__( 'None', 'bricks' ),
			];

			$control_options['position'] = [
				'static'   => 'static',
				'relative' => 'relative',
				'absolute' => 'absolute',
				'fixed'    => 'fixed',
				'sticky'   => 'sticky',
			];

			$control_options['queryTypes'] = [
				'post' => esc_html__( 'Posts', 'bricks' ),
				'term' => esc_html__( 'Terms', 'bricks' ),
				'user' => esc_html__( 'Users', 'bricks' ),
				'api'  => 'API', // (@since 2.1)
			];

			$control_options['queryOrder'] = [
				'asc'  => esc_html__( 'Ascending', 'bricks' ),
				'desc' => esc_html__( 'Descending', 'bricks' ),
			];

			$control_options['queryOrderBy'] = [
				'none'           => esc_html( 'None', 'bricks' ),
				'ID'             => esc_html( 'ID', 'bricks' ),
				'author'         => esc_html( 'Author', 'bricks' ),
				'title'          => esc_html( 'Title', 'bricks' ),
				'name'           => esc_html( 'Slug', 'bricks' ), // Post name (@since 1.11.1)
				'type'           => esc_html( 'Post type', 'bricks' ), // (@since 1.11.1)
				'date'           => esc_html( 'Published date', 'bricks' ),
				'modified'       => esc_html( 'Modified date', 'bricks' ),
				'rand'           => esc_html( 'Random', 'bricks' ),
				'comment_count'  => esc_html( 'Comment count', 'bricks' ),
				'relevance'      => esc_html( 'Relevance', 'bricks' ),
				'menu_order'     => esc_html( 'Menu order', 'bricks' ),
				'parent'         => esc_html( 'Parent', 'bricks' ),
				'meta_value'     => esc_html( 'Meta value', 'bricks' ),
				'meta_value_num' => esc_html( 'Meta numeric value', 'bricks' ),
				'post__in'       => esc_html( 'Post include order', 'bricks' ),
				'_default'       => esc_html( 'Default', 'bricks' ), // Use WP default order (@since 1.12)
			];

			$control_options['termsOrderBy'] = [
				'none'           => esc_html( 'None', 'bricks' ),
				'term_id'        => esc_html( 'ID', 'bricks' ),
				'name'           => esc_html( 'Name', 'bricks' ),
				// 'term_order'         => esc_html( 'Term order', 'bricks' ),
				'parent'         => esc_html( 'Parent', 'bricks' ),
				'meta_value'     => esc_html( 'Meta value', 'bricks' ), // (@since 1.11.1)
				'meta_value_num' => esc_html( 'Meta numeric value', 'bricks' ), // (@since 1.11.1)
				'count'          => esc_html( 'Count', 'bricks' ),
				'include'        => esc_html( 'Include list', 'bricks' ),
				'_default'       => esc_html( 'Default', 'bricks' ), // Use WP default order (@since 1.12)
			];

			$control_options['usersOrderBy'] = [
				'none'           => esc_html( 'None', 'bricks' ),
				'ID'             => esc_html( 'ID', 'bricks' ),
				'display_name'   => esc_html( 'Name', 'bricks' ),
				'name'           => esc_html( 'Username', 'bricks' ),
				'nicename'       => esc_html( 'Nicename', 'bricks' ),
				'login'          => esc_html( 'Login', 'bricks' ),
				'email'          => esc_html( 'Email', 'bricks' ),
				// 'url'        => esc_html( 'Website', 'bricks' ),
				'registered'     => esc_html( 'Registered date', 'bricks' ),
				'post_count'     => esc_html( 'Post count', 'bricks' ),
				'include'        => esc_html( 'Include list', 'bricks' ),
				'meta_value'     => esc_html( 'Meta value', 'bricks' ),
				'meta_value_num' => esc_html( 'Meta numeric value', 'bricks' ),
				// 'post__in' => esc_html( 'Post include order', 'bricks' ),
				'_default'       => esc_html( 'Default', 'bricks' ), // Use WP default order (@since 1.12)
			];

			$control_options['queryCompare'] = [
				'='           => esc_html( 'Equal', 'bricks' ),
				'!='          => esc_html( 'Not equal', 'bricks' ),
				'>'           => esc_html( 'Greater than', 'bricks' ),
				'>='          => esc_html( 'Greater than or equal', 'bricks' ),
				'<'           => esc_html( 'Lesser', 'bricks' ),
				'<='          => esc_html( 'Lesser or equal', 'bricks' ),
				'LIKE'        => 'LIKE',
				'NOT LIKE'    => 'NOT LIKE',
				'IN'          => 'IN',
				'NOT IN'      => 'NOT IN',
				'BETWEEN'     => 'BETWEEN',
				'NOT BETWEEN' => 'NOT BETWEEN',
				'EXISTS'      => 'EXISTS',
				'NOT EXISTS'  => 'NOT EXISTS',
			];

			$control_options['queryOperator'] = [
				'IN'         => 'IN',
				'NOT IN'     => 'NOT IN',
				'AND'        => 'AND',
				'EXISTS'     => 'EXISTS',
				'NOT EXISTS' => 'NOT EXISTS',
			];

			$control_options['queryValueType'] = [
				'NUMERIC'  => 'NUMERIC',
				'BINARY'   => 'CHAR',
				'DATE'     => 'DATE',
				'DATETIME' => 'DATETIME',
				'DECIMAL'  => 'DECIMAL',
				'SIGNED'   => 'SIGNED',
				'TIME'     => 'TIME',
				'UNSIGNED' => 'UNSIGNED'
			];

			$control_options['templatesOrderBy'] = [
				'author'   => esc_html( 'Author', 'bricks' ),
				'title'    => esc_html( 'Title', 'bricks' ),
				'date'     => esc_html( 'Published date', 'bricks' ),
				'modified' => esc_html( 'Modified date', 'bricks' ),
				'rand'     => esc_html( 'Random', 'bricks' ),
			];

			$control_options['templateTypes'] = [
				'header'  => esc_html__( 'Header', 'bricks' ),
				'footer'  => esc_html__( 'Footer', 'bricks' ),
				'content' => esc_html__( 'Single', 'bricks' ),
				'section' => esc_html__( 'Section', 'bricks' ),
				'popup'   => esc_html__( 'Popup', 'bricks' ),
				'archive' => esc_html__( 'Archive', 'bricks' ),
				'search'  => esc_html__( 'Search results', 'bricks' ),
				'error'   => esc_html__( 'Error page', 'bricks' ),
			];

			if ( Database::get_setting( 'passwordProtectionEnabled', false ) ) {
				$control_options['templateTypes']['password_protection'] = esc_html__( 'Password protection', 'bricks' );
			}

			$control_options['animationTypes'] = [
				'bounce'             => 'bounce',
				'flash'              => 'flash',
				'pulse'              => 'pulse',
				'rubberBand'         => 'rubberBand',
				'shakeX'             => 'shakeX',
				'shakeY'             => 'shakeY',
				'headShake'          => 'headShake',
				'swing'              => 'swing',
				'tada'               => 'tada',
				'wobble'             => 'wobble',
				'jello'              => 'jello',
				'heartBeat'          => 'heartBeat',

				'backInDown'         => 'backInDown',
				'backInLeft'         => 'backInLeft',
				'backInRight'        => 'backInRight',
				'backInUp'           => 'backInUp',

				'backOutDown'        => 'backOutDown',
				'backOutLeft'        => 'backOutLeft',
				'backOutRight'       => 'backOutRight',
				'backOutUp'          => 'backOutUp',

				'bounceIn'           => 'bounceIn',
				'bounceInDown'       => 'bounceInDown',
				'bounceInLeft'       => 'bounceInLeft',
				'bounceInRight'      => 'bounceInRight',
				'bounceInUp'         => 'bounceInUp',

				'bounceOut'          => 'bounceOut',
				'bounceOutDown'      => 'bounceOutDown',
				'bounceOutLeft'      => 'bounceOutLeft',
				'bounceOutRight'     => 'bounceOutRight',
				'bounceOutUp'        => 'bounceOutUp',

				'fadeIn'             => 'fadeIn',
				'fadeInDown'         => 'fadeInDown',
				'fadeInDownBig'      => 'fadeInDownBig',
				'fadeInLeft'         => 'fadeInLeft',
				'fadeInLeftBig'      => 'fadeInLeftBig',
				'fadeInRight'        => 'fadeInRight',
				'fadeInRightBig'     => 'fadeInRightBig',
				'fadeInUp'           => 'fadeInUp',
				'fadeInUpBig'        => 'fadeInUpBig',
				'fadeInTopLeft'      => 'fadeInTopLeft',
				'fadeInTopRight'     => 'fadeInTopRight',
				'fadeInBottomLeft'   => 'fadeInBottomLeft',
				'fadeInBottomRight'  => 'fadeInBottomRight',

				'fadeOut'            => 'fadeOut',
				'fadeOutDown'        => 'fadeOutDown',
				'fadeOutDownBig'     => 'fadeOutDownBig',
				'fadeOutLeft'        => 'fadeOutLeft',
				'fadeOutLeftBig'     => 'fadeOutLeftBig',
				'fadeOutRight'       => 'fadeOutRight',
				'fadeOutRightBig'    => 'fadeOutRightBig',
				'fadeOutUp'          => 'fadeOutUp',
				'fadeOutUpBig'       => 'fadeOutUpBig',
				'fadeOutTopLeft'     => 'fadeOutTopLeft',
				'fadeOutTopRight'    => 'fadeOutTopRight',
				'fadeOutBottomRight' => 'fadeOutBottomRight',
				'fadeOutBottomLeft'  => 'fadeOutBottomLeft',

				'flip'               => 'flip',
				'flipInX'            => 'flipInX',
				'flipInY'            => 'flipInY',
				'flipOutX'           => 'flipOutX',
				'flipOutY'           => 'flipOutY',

				// 'lightSpeedIn'       => 'lightSpeedIn', // deprecated on Bricks 1.5
				// 'lightSpeedOut'      => 'lightSpeedOut', // deprecated on Bricks 1.5
				'lightSpeedInRight'  => 'lightSpeedInRight',
				'lightSpeedInLeft'   => 'lightSpeedInLeft',
				'lightSpeedOutRight' => 'lightSpeedOutRight',
				'lightSpeedOutLeft'  => 'lightSpeedOutLeft',

				'rotateIn'           => 'rotateIn',
				'rotateInDownLeft'   => 'rotateInDownLeft',
				'rotateInDownRight'  => 'rotateInDownRight',
				'rotateInUpLeft'     => 'rotateInUpLeft',
				'rotateInUpRight'    => 'rotateInUpRight',

				'rotateOut'          => 'rotateOut',
				'rotateOutDownLeft'  => 'rotateOutDownLeft',
				'rotateOutDownRight' => 'rotateOutDownRight',
				'rotateOutUpLeft'    => 'rotateOutUpLeft',
				'rotateOutUpRight'   => 'rotateOutUpRight',

				'hinge'              => 'hinge',
				'jackInTheBox'       => 'jackInTheBox',
				'rollIn'             => 'rollIn',
				'rollOut'            => 'rollOut',

				'zoomIn'             => 'zoomIn',
				'zoomInDown'         => 'zoomInDown',
				'zoomInLeft'         => 'zoomInLeft',
				'zoomInRight'        => 'zoomInRight',
				'zoomInUp'           => 'zoomInUp',

				'zoomOut'            => 'zoomOut',
				'zoomOutDown'        => 'zoomOutDown',
				'zoomOutLeft'        => 'zoomOutLeft',
				'zoomOutRight'       => 'zoomOutRight',
				'zoomOutUp'          => 'zoomOutUp',

				'slideInUp'          => 'slideInUp',
				'slideInDown'        => 'slideInDown',
				'slideInLeft'        => 'slideInLeft',
				'slideInRight'       => 'slideInRight',

				'slideOutUp'         => 'slideOutUp',
				'slideOutDown'       => 'slideOutDown',
				'slideOutLeft'       => 'slideOutLeft',
				'slideOutRight'      => 'slideOutRight',
			];

			$control_options['lightboxAnimationTypes'] = [
				'none' => esc_html__( 'None', 'bricks' ),
				'fade' => esc_html__( 'Fade', 'bricks' ),
				'zoom' => esc_html__( 'Zoom', 'bricks' ),
			];

			// AJAX loader animations (@since 1.9)
			$control_options['ajaxLoaderAnimations'] = [
				'default'   => esc_html__( 'Default', 'bricks' ),
				'ellipsis'  => esc_html__( 'Ellipsis', 'bricks' ),
				'ring'      => esc_html__( 'Ring', 'bricks' ),
				'dual-ring' => esc_html__( 'Dual ring', 'bricks' ),
				'facebook'  => 'Facebook',
				'roller'    => esc_html__( 'Roller', 'bricks' ),
				'ripple'    => esc_html__( 'Ripple', 'bricks' ),
				'spinner'   => esc_html__( 'Spinner', 'bricks' ),
			];

			// text-wrap control options (@since 1.11.1)
			$control_options['textWrap'] = [
				'wrap'    => 'wrap',
				'nowrap'  => 'nowrap',
				'balance' => 'balance',
				'pretty'  => 'pretty',
				'stable'  => 'stable',
			];

			// white-space control options (@since 1.11.1)
			$control_options['whiteSpace'] = [
				'normal'       => 'normal',
				'nowrap'       => 'nowrap',
				'pre'          => 'pre',
				'pre-wrap'     => 'pre-wrap',
				'pre-line'     => 'pre-line',
				'break-spaces' => 'break-spaces',
			];

			// PERFORMANCE: Run WP query to populate control options only in builder
			$control_options['imageSizes'] = bricks_is_builder() ? self::get_image_sizes_options() : [];

			$control_options['taxonomies'] = bricks_is_builder() ? self::get_taxonomies_options() : [];

			$control_options['userRoles'] = bricks_is_builder() ? wp_roles()->get_names() : [];

			// 'allSectionTemplates' is used in query control (@since 1.9.6)
			$control_options['allSectionTemplates'] = bricks_is_builder() ? Templates::get_templates_list( [ 'section' ], get_the_ID() ) : [];
		} else {
			$control_options = self::$control_options;
		}

		// @see: https://academy.bricksbuilder.io/article/filter-bricks-setup-control_options/
		$control_options = apply_filters( 'bricks/setup/control_options', $control_options );

		return $key ? $control_options[ $key ] : $control_options;
	}

	/**
	 * Return a list of taxonomies
	 */
	public static function get_taxonomies_options() {
		$all_taxonomies = get_taxonomies( [], 'object' );

		$taxonomies_options = [];

		foreach ( $all_taxonomies as $taxonomy => $tax ) {
			// Skip unnecessary default taxonomies
			if (
				$taxonomy === 'nav_menu' ||
				$taxonomy === 'link_category' ||
				$taxonomy === 'post_format' ||
				$taxonomy === 'wp_theme' ||
				// $taxonomy === BRICKS_DB_TEMPLATE_TAX_TAG || // Don't exclude, might be needed in query filters (@since 1.12)
				// $taxonomy === BRICKS_DB_TEMPLATE_TAX_BUNDLE || // Don't exclude, might be needed in query filters (@since 1.12)
				empty( $tax->label ) // Some taxonomies have no label (e.g. Polylang internal taxonomies)
				) {
				continue;
			}

			// Show all post types the taxonomy is registered to (#86c47ukrw; @since 2.x)
			if ( isset( $tax->object_type ) && is_array( $tax->object_type ) && count( $tax->object_type ) > 0 ) {
				$post_types = array_map( 'ucwords', $tax->object_type );
				$post_type  = ' (' . implode( '/', $post_types ) . ')';
			} else {
				$post_type = '';
			}

			$taxonomies_options[ $taxonomy ] = $tax->label . $post_type;
		}

		return $taxonomies_options;
	}

	/**
	 * Get image size options for control select options
	 *
	 * @since 1.0
	 */
	public static function get_image_sizes() {
		// Get all registered image sizes (default + custom) with width/height/crop
		$image_sizes = [];

		// Populate default image sizes
		foreach ( get_intermediate_image_sizes() as $image_size ) {
			$image_sizes[ $image_size ] = [
				'width'  => (int) get_option( $image_size . '_size_w' ),
				'height' => (int) get_option( $image_size . '_size_h' ),
				'crop'   => (bool) get_option( $image_size . '_crop' ),
			];
		}

		// Manually add size 'full'
		$image_sizes['full'] = [
			'width'  => 0,
			'height' => 0,
			'crop'   => false,
		];

		// Merge additional image sizes set via 'add_image_size' with default image sizes
		global $_wp_additional_image_sizes;

		if ( $_wp_additional_image_sizes ) {
			$image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
		}

		// https://academy.bricksbuilder.io/article/filter-bricks-builder-image_size_options/ (@since 1.9.4)
		return apply_filters( 'bricks/builder/image_size_options', $image_sizes );
	}

	/**
	 * Get image size options for control select options
	 *
	 * @since 1.0
	 */
	public static function get_image_sizes_options() {
		$image_sizes = self::get_image_sizes();

		$image_sizes_keys = array_keys( $image_sizes );

		$image_options = [];

		foreach ( $image_sizes_keys as $image_size ) {
			// New WordPress 5.3 image sizes (see bottom of wp-incluces/media.php)
			if ( $image_size === '1536x1536' ) {
				$image_size_label = '2x medium large';
			} elseif ( $image_size === '2048x2048' ) {
				$image_size_label = '2x large';
			} else {
				$image_size_label = ucwords( str_replace( '_', ' ', $image_size ) );
			}

			if ( $image_size !== 'full' ) {
				$image_size_label .= ' (' . $image_sizes[ $image_size ]['width'] . 'x' . $image_sizes[ $image_size ]['height'] . ')';
			}

			$image_options[ $image_size ] = $image_size_label;
		}

		/**
		 * Support WP core hook to set custom image size label
		 *
		 * https://developer.wordpress.org/reference/hooks/image_size_names_choose/
		 *
		 * @since 1.9.9
		 */
		$image_options = apply_filters( 'image_size_names_choose', $image_options );

		return $image_options;
	}

	/**
	 * Limit the max. number of query loop results (builder-only)
	 *
	 * @since 1.11
	 */
	public function builder_query_max_results( $result, $query_instance ) {
		// Return: Not in the builder
		// NOTE: add && ! isset( $_GET['bricks_preview'] ) if want to apply to template preview too
		if (
			! bricks_is_builder_main() &&
			! bricks_is_builder_iframe() &&
			! bricks_is_builder_call()
		) {
			return $result;
		}

		$builder_query_max_results = Builder::get_query_max_results();

		// Return: builderQueryMaxResults
		if ( ! $builder_query_max_results ) {
			return $result;
		}

		$object_type = $query_instance->object_type;

		switch ( $object_type ) {
			case 'post':
				// Remove from posts property
				if ( $result->have_posts() ) {
					$posts_array = $result->posts;

					if ( is_array( $posts_array ) ) {
						// Only keep the maximum number of items specified
						$result->posts = array_slice( $posts_array, 0, $builder_query_max_results );

						// Optionally, reset the post data after slicing
						$result->post_count = count( $result->posts );

						// To avoid get_posts() which will retrieve all posts again
						if ( isset( $result->query_vars['posts_per_page'] ) ) {
							$result->query_vars['posts_per_page'] = $builder_query_max_results;
						}
					}
				}

				break;
			case 'term':
				// Remove from terms result
				if ( is_array( $result ) ) {
					$terms_array = $result;
					// Only keep the maximum number of items specified
					$result = array_slice( $terms_array, 0, $builder_query_max_results );
				}
				break;
			case 'user':
				// Remove from users results
				if ( is_array( $result ) ) {
					$users_array = $result;
					// Only keep the maximum number of items specified
					$result = array_slice( $users_array, 0, $builder_query_max_results );
				}
				break;

			default:
				// Maybe is Bricks providers loop
				if (
					strpos( $object_type, 'acf_' ) === 0 ||
					strpos( $object_type, 'mb_' ) === 0 ||
					strpos( $object_type, 'je_' ) === 0 ||
					strpos( $object_type, 'cmb2_' ) === 0 ||
					strpos( $object_type, 'pods_' ) === 0 ||
					strpos( $object_type, 'ts_' ) === 0 ||
					$object_type === 'wooCart'
				) {
					// Remove from results array
					if ( is_array( $result ) ) {
						$acf_array = $result;
						// Only keep the maximum number of items specified
						$result = array_slice( $acf_array, 0, $builder_query_max_results );
					}
				} else {
					// Undocumented
					$result = apply_filters( 'bricks_query_max_items_in_builder', $result, $query_instance, $builder_query_max_results );
				}

				break;
		}

		return $result;
	}
}
