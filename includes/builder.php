<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Builder {
	public static $dynamic_data    = []; // key: DD tag; value: DD tag value (@since 1.7.1)
	public static $html_attributes = []; // key: header, main, footer, element ID; value: array with element attributes (@since 1.10)
	public static $elements_html   = []; // (@since 2.0)
	public static $preview_texts   = []; // (@since 2.0)
	public static $looping_html    = []; // (@since 2.0)
	public static $templates_data  = []; // (@since 2.0)
	public static $query_api_cache = []; // Cached data for query API results (@since 2.1)

	public function __construct() {
		// Builder: Add login form to page
		if ( bricks_is_builder_main() ) {
			add_action( 'wp_print_footer_scripts', 'wp_auth_check_html', 5 );
		}

		// Remove admin bar styles and disable admin bar in builder
		if ( BRICKS_DEBUG === false || bricks_is_builder_iframe() ) {
			add_action( 'wp_print_styles', [ $this, 'remove_admin_bar_inline_styles' ] );
			add_filter( 'show_admin_bar', [ $this, 'show_admin_bar' ] );
		}

		add_action( 'init', [ $this, 'set_language_direction' ] );

		/**
		 * @since 1.11: Changed from 'locale' to 'determine_locale' to avoid conflicts with other plugins (i.e. WPML)
		 */
		add_filter( 'determine_locale', [ $this, 'maybe_set_locale' ], 99999, 1 ); // Hook in after TranslatePress

		add_action( 'send_headers', [ $this, 'dont_cache_headers' ] );

		add_action( 'wp_footer', [ $this, 'element_x_templates' ] );

		add_action( 'bricks_before_site_wrapper', [ $this, 'before_site_wrapper' ] );
		add_action( 'bricks_after_site_wrapper', [ $this, 'after_site_wrapper' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		// add_action( 'wp_enqueue_scripts', [ $this, 'static_area_styles' ] );

		add_filter( 'tiny_mce_before_init', [ $this, 'tiny_mce_before_init' ] );

		add_action( 'template_redirect', [ $this, 'template_redirect' ] );

		// In the builder force our own template to avoid conflicts with other builders
		add_filter( 'template_include', [ $this, 'template_include' ], 1001 );

		// Skip loading Cloudflare Rocket Loader (@since 2.0)
		if ( self::cloudflare_rocket_loader_disabled() ) {
			add_action( 'template_redirect', [ $this, 'cloudflare_rocket_loader_modify_script_tags' ], 0 );
		}
	}

	/**
	 * Remove 'admin-bar' inline styles
	 *
	 * Necessary for WordPress 6.4+ as html {margin-top: 32px !important} causes gap in builder.
	 *
	 * @since 1.9.3
	 */
	public function remove_admin_bar_inline_styles() {
		// Remove 'admin-bar' inline style
		if ( wp_style_is( 'admin-bar', 'enqueued' ) ) {
			wp_style_add_data( 'admin-bar', 'after', '' );
		}
	}

	/**
	 * Don't cache headers or browser history buffer in builder
	 *
	 * To fix browser back button issue.
	 *
	 * https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching
	 * https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
	 *
	 * "at present any pages using Cache-Control: no-store will not be eligible for bfcache."
	 * - https://web.dev/bfcache/#minimize-use-of-cache-control-no-store
	 *
	 * @since 1.6.2
	 */
	public function dont_cache_headers() {
		header_remove( 'Cache-Control' );

		header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' ); // HTTP 1.1
		header( 'Pragma: no-cache' ); // HTTP 1.0
		header( 'Expires: 0' ); // HTTP 1.0 proxies
	}

	/**
	 * Remove admin bar and CSS
	 *
	 * @since 1.0
	 */
	public function show_admin_bar() {
		remove_action( 'wp_head', '_admin_bar_bump_cb' );

		return false;
	}

	/**
	 * Set a different language locale in builder if user has specified a different admin language
	 *
	 * @since 1.1.2
	 */
	public function maybe_set_locale( $locale ) {
		// Check for builder language
		$builder_locale = Database::get_setting( 'builderLocale', false );

		if ( $builder_locale && $builder_locale !== 'site-default' ) {
			return $builder_locale;
		}

		// Check for specific WP dashboard user language
		$user = wp_get_current_user();

		$locale = ! empty( $user->locale ) ? $user->locale : $locale;

		return $locale;
	}

	/**
	 * Set language direction in builder (panels)
	 *
	 * Apply only to main window (toolbar & panels). Canvas should use frontend direction.
	 *
	 * @since 1.5
	 */
	public function set_language_direction() {
		// Return: Window is not main builder window
		if ( ! bricks_is_builder_main() ) {
			return;
		}

		$direction = Database::get_setting( 'builderLanguageDirection', false );

		if ( ! $direction ) {
			$builder_locale = Database::get_setting( 'builderLocale', false );

			// If builderLocale is set to "site-default", get the site's default locale
			if ( $builder_locale == 'site-default' ) {
				$builder_locale = get_locale();
			}

			// Determine if the locale is a RTL or LTR language
			// NOTE: Best not to hardcode RTL languages if possible!
			$rtl_languages = [ 'ar', 'he', 'fa', 'ur', 'yi', 'ps', 'dv', 'ckb', 'sd', 'ug' ];

			// Apply filter to allow RTL languages to be added
			$rtl_languages = apply_filters( 'bricks/rtl_languages', $rtl_languages );

			$language_code = substr( $builder_locale, 0, 2 );
			$direction     = in_array( $language_code, $rtl_languages ) ? 'rtl' : 'ltr';
		}

		global $wp_locale, $wp_styles;

		$wp_locale->text_direction = $direction;

		if ( ! is_a( $wp_styles, 'WP_Styles' ) ) {
			$wp_styles = new \WP_Styles();
		}

		$wp_styles->text_direction = $direction;
	}

	/**
	 * Canvas: Add element x-template render scripts to wp_footer
	 */
	public function element_x_templates() {
		if ( ! bricks_is_builder_iframe() ) {
			return;
		}

		foreach ( Elements::$elements as $element ) {
			echo $element['class']::render_builder();
		}
	}

	/**
	 * Before site wrapper (opening tag to render builder)
	 *
	 * @since 1.0
	 */
	public function before_site_wrapper() {
		if ( bricks_is_builder_main() ) {
			echo '<div class="brx-body main">';
		} elseif ( bricks_is_builder_iframe() ) {
			echo '<div class="brx-body iframe">';
		}
	}

	/**
	 * After site wrapper (closing tag to render builder)
	 *
	 * @since 1.0
	 */
	public function after_site_wrapper() {
		if ( bricks_is_builder() ) {
			echo '</div>'; // END .brx-body
		}
	}

	/**
	 * Enqueue styles and scripts
	 *
	 * @since 1.0
	 */
	public function enqueue_scripts() {
		// Access MediaElementsJS (element: Audio) and to get global 'wp' object to open media library (control type 'image', 'audio' etc.)
		wp_enqueue_media();

		// Order matters for CSS flexbox (enqueue builder styles before frontend styles)
		if ( bricks_is_builder() ) {
			wp_enqueue_style( 'bricks-builder', BRICKS_URL_ASSETS . 'css/builder.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/builder.min.css' ) );

			if ( is_rtl() ) {
				wp_enqueue_style( 'bricks-builder-rtl', BRICKS_URL_ASSETS . 'css/builder-rtl.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/builder-rtl.min.css' ) );
			}

			// PopupDocs.vue: Load prettify
			wp_enqueue_script( 'bricks-prettify' );
			wp_enqueue_style( 'bricks-prettify' );

			// Builder isotope (PopupUnsplash.vue)
			wp_enqueue_script( 'bricks-isotope' );

			// Datepicker (form & countdown)
			wp_enqueue_script( 'bricks-flatpickr' );
			wp_enqueue_style( 'bricks-flatpickr' );

			add_filter( 'mce_buttons_2', [ $this, 'add_editor_buttons' ] );
		}

		if ( bricks_is_builder_main() ) {
			// Manually enqueue dashicons for 'wp_enqueue_media' as 'get_wp_editor' prevents dashicons enqueue
			wp_enqueue_style( 'bricks-dashicons', includes_url( '/css/dashicons.min.css' ), [], null );

			wp_enqueue_script( 'bricks-builder', BRICKS_URL_ASSETS . 'js/main.min.js', [ 'bricks-scripts', 'jquery' ], filemtime( BRICKS_PATH_ASSETS . 'js/main.min.js' ), true );

			// Builder Mode "Custom": Add CSS variables as inline CSS
			$builder_mode   = Database::get_setting( 'builderMode', 'dark' );
			$builder_ui_css = Database::get_setting( 'builderModeCss', '' );

			if ( $builder_mode === 'custom' && ! empty( $builder_ui_css ) ) {
				wp_add_inline_style( 'bricks-tooltips', $builder_ui_css );
			}
		}

		// Load Adobe fonts file
		// NOTE: Enqueue in main window for Font manager (@since 2.0)
		$adobe_fonts_project_id = ! empty( Database::get_setting( 'adobeFontsProjectId' ) ) ? Database::get_setting( 'adobeFontsProjectId' ) : false;

		if ( $adobe_fonts_project_id ) {
			wp_enqueue_style( "adobe-fonts-project-id-$adobe_fonts_project_id", "https://use.typekit.net/$adobe_fonts_project_id.css" );
		}

		if ( bricks_is_builder_iframe() ) {
			// Enqueue Dashicons for ACF icon picker (@since 2.0)
			wp_enqueue_style( 'bricks-dashicons', includes_url( '/css/dashicons.min.css' ), [], null );

			wp_enqueue_script( 'bricks-countdown' );
			wp_enqueue_script( 'bricks-counter' );
			wp_enqueue_script( 'bricks-flatpickr' );
			wp_enqueue_script( 'bricks-google-maps' );
			wp_enqueue_script( 'bricks-piechart' );
			wp_enqueue_script( 'bricks-swiper' );
			wp_enqueue_script( 'bricks-typed' );
			wp_enqueue_script( 'bricks-tocbot' );

			// Form element richtext field TinyMCE 8 (@since 2.1)
			wp_enqueue_script( 'bricks-tinymce8-builder' );

			wp_enqueue_script( 'bricks-builder', BRICKS_URL_ASSETS . 'js/iframe.min.js', [ 'bricks-scripts', 'jquery' ], filemtime( BRICKS_PATH_ASSETS . 'js/iframe.min.js' ), true );
		}

		$post_id        = get_the_ID();
		$featured_image = false;

		/**
		 * Get control options to ensure filter 'bricks/setup/control_options' ran
		 *
		 * Eaxmples: 'queryTypes', custom user control options, etc.
		 *
		 * @since 1.5.5
		 */
		$control_options = Setup::get_control_options();

		// NOTE: Set post ID to posts page
		if ( is_home() ) {
			$post_id = get_option( 'page_for_posts' );
		}

		// NOTE: Undocumented
		$post_id = apply_filters( 'bricks/builder/data_post_id', $post_id );

		if ( has_post_thumbnail( $post_id ) ) {
			$featured_image = [
				'id'  => get_post_thumbnail_id(),
				'url' => get_the_post_thumbnail_url( $post_id ),
			];

			$image_sizes = array_keys( $control_options['imageSizes'] );

			foreach ( $image_sizes as $image_size ) {
				$featured_image[ $image_size ] = get_the_post_thumbnail_url( $post_id, $image_size );
			}
		}

		wp_localize_script(
			'bricks-builder',
			'bricksData',
			[
				'loadData'                          => self::builder_data( $post_id ), // Initial data to bootstrap builder iframe
				'dynamicWrapper'                    => apply_filters( 'bricks/builder/dynamic_wrapper', [] ),

				// Bricks settings
				'customBreakpoints'                 => Database::get_setting( 'customBreakpoints', false ),
				'disableClassManager'               => Database::get_setting( 'disableClassManager', false ),
				'disableVariablesManager'           => Database::get_setting( 'disableVariablesManager', false ),
				'disableClassChaining'              => Database::get_setting( 'disableClassChaining', false ),
				'defaultTemplatesDisabled'          => Database::get_setting( 'defaultTemplatesDisabled' ),
				'generateTemplateScreenshots'       => Database::get_setting( 'generateTemplateScreenshots' ),
				'disableGlobalClasses'              => Database::get_setting( 'builderDisableGlobalClassesInterface', false ),
				'builderShowCssPropertyName'        => false, // Database::get_setting( 'builderShowCssPropertyName', false ),
				'disablePanelAutoExpand'            => Database::get_setting( 'builderDisablePanelAutoExpand', false ),
				'disableElementSpacing'             => Database::get_setting( 'disableElementSpacing', false ),
				'canvasScrollIntoView'              => Database::get_setting( 'canvasScrollIntoView', false ),
				'structureAutoSync'                 => Database::get_setting( 'structureAutoSync', false ),
				'structureDuplicateElement'         => Database::get_setting( 'structureDuplicateElement', false ),
				'structureDeleteElement'            => Database::get_setting( 'structureDeleteElement', false ),
				'structureCollapsed'                => Database::get_setting( 'structureCollapsed', false ),
				'builderElementBreadcrumbs'         => Database::get_setting( 'builderElementBreadcrumbs', false ),
				'builderDisableRestApi'             => Database::get_setting( 'builderDisableRestApi', false ),
				'builderResponsiveControlIndicator' => Database::get_setting( 'builderResponsiveControlIndicator', 'any' ),
				'builderControlGroupVisibility'     => Database::get_setting( 'builderControlGroupVisibility', 'open' ),
				'builderFontFamilyControl'          => Database::get_setting( 'builderFontFamilyControl', 'all' ),
				'builderWrapElement'                => Database::get_setting( 'builderWrapElement', 'block' ),
				'builderInsertElement'              => Database::get_setting( 'builderInsertElement', 'block' ),
				'builderInsertLayout'               => Database::get_setting( 'builderInsertLayout', 'block' ),
				'enableDynamicDataPreview'          => Database::get_setting( 'enableDynamicDataPreview', false ),
				'enableQueryFilters'                => Database::get_setting( 'enableQueryFilters', false ),
				'enableQueryFiltersIntegration'     => Database::get_setting( 'enableQueryFiltersIntegration', false ),
				'builderQueryMaxResults'            => Database::get_setting( 'builderQueryMaxResults', false ),
				'builderDynamicDropdownKey'         => Database::get_setting( 'builderDynamicDropdownKey', false ),
				'builderDynamicDropdownNoLabel'     => Database::get_setting( 'builderDynamicDropdownNoLabel', false ),
				'builderDynamicDropdownExpand'      => Database::get_setting( 'builderDynamicDropdownExpand', false ),
				'builderGlobalClassesSync'          => Database::get_setting( 'builderGlobalClassesSync', false ),
				'builderVariablePickerHideValue'    => Database::get_setting( 'builderVariablePickerHideValue', false ),
				'builderCodeVim'                    => Database::get_setting( 'builderCodeVim', false ),
				'builderCloudflareRocketLoader'     => self::cloudflare_rocket_loader_disabled(),
				'builderCheckForCorruptData'        => Database::get_setting( 'builderCheckForCorruptData', false ),
				'bricksComponentsInBlockEditor'     => Database::get_setting( 'bricksComponentsInBlockEditor', false ),
				'autosave'                          => [
					'disabled' => Database::get_setting( 'builderAutosaveDisabled', false ),
					'interval' => Database::get_setting( 'builderAutosaveInterval', 60 ),
				],
				'toolbarLogoLink'                   => Database::get_setting( 'builderToolbarLogoLink', 'current' ),
				'toolbarLogoLinkCustom'             => Database::get_setting( 'builderToolbarLogoLinkCustom', '' ),
				'toolbarLogoLinkNewTab'             => Database::get_setting( 'builderToolbarLogoLinkNewTab', '' ),
				'mode'                              => Database::get_setting( 'builderMode', 'dark' ),
				'featuredImage'                     => $featured_image,
				'panelWidth'                        => get_option( BRICKS_DB_PANEL_WIDTH, 300 ),
				'structureWidth'                    => get_option( BRICKS_DB_STRUCTURE_WIDTH, 300 ),
				'scaleOff'                          => get_user_meta( get_current_user_id(), BRICKS_DB_BUILDER_SCALE_OFF, true ),
				'widthLocked'                       => get_user_meta( get_current_user_id(), BRICKS_DB_BUILDER_WIDTH_LOCKED, true ),

				'allowedHtmlTags'                   => Helpers::get_allowed_html_tags(),
				'validPseudoClasses'                => Helpers::get_valid_pseudo_classes(),
				'validPseudoElements'               => Helpers::get_valid_pseudo_elements(),
				'wp'                                => self::get_wordpress_data(),
				'academy'                           => [
					'home'              => 'https://academy.bricksbuilder.io/',
					'article'           => 'https://academy.bricksbuilder.io/article/',
					'components'        => 'https://academy.bricksbuilder.io/article/components/',
					'layout'            => 'https://academy.bricksbuilder.io/article/layout/',
					'headerTemplate'    => 'https://academy.bricksbuilder.io/article/create-template/',
					'footerTemplate'    => 'https://academy.bricksbuilder.io/article/create-template/',
					'createElement'     => 'https://academy.bricksbuilder.io/article/create-your-own-elements/',
					'globalElement'     => 'https://academy.bricksbuilder.io/article/global-elements/',
					'keyboardShortcuts' => 'https://academy.bricksbuilder.io/article/keyboard-shortcuts/',
					'pseudoClasses'     => 'https://academy.bricksbuilder.io/article/pseudo-classes/',
					'conditions'        => 'https://academy.bricksbuilder.io/article/element-conditions/',
					'interactions'      => 'https://academy.bricksbuilder.io/article/interactions/',
					'popups'            => 'https://academy.bricksbuilder.io/article/popup-builder/',
					'capabilities'      => 'https://academy.bricksbuilder.io/article/builder-access/',
					'fluidTypography'   => 'https://academy.bricksbuilder.io/article/fluid-typography/'
				],

				'version'                           => BRICKS_VERSION,
				'debug'                             => isset( $_GET['debug'] ) ? sanitize_text_field( $_GET['debug'] ) : false,
				'message'                           => isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : false,
				'breakpoints'                       => Breakpoints::$breakpoints,
				'builderPreviewParam'               => BRICKS_BUILDER_IFRAME_PARAM,
				'maxUploadSize'                     => wp_max_upload_size(),

				'dynamicTags'                       => Integrations\Dynamic_Data\Providers::get_dynamic_tags_list(),
				'dynamicTagsQueryLoop'              => Integrations\Dynamic_Data\Providers::get_query_supported_tags_list(),

				// URL to edit header/content/footer templates
				'editHeaderUrl'                     => ! empty( Database::$active_templates['header'] ) ? Helpers::get_builder_edit_link( Database::$active_templates['header'] ) : '',
				'editContentUrl'                    => ! empty( Database::$active_templates['content'] ) ? Helpers::get_builder_edit_link( Database::$active_templates['content'] ) : '',
				'editFooterUrl'                     => ! empty( Database::$active_templates['footer'] ) ? Helpers::get_builder_edit_link( Database::$active_templates['footer'] ) : '',

				'locale'                            => get_locale(),
				'i18n'                              => self::i18n(),
				'nonce'                             => wp_create_nonce( 'bricks-nonce-builder' ),
				'ajaxUrl'                           => admin_url( 'admin-ajax.php' ),
				'restApiUrl'                        => Api::get_rest_api_url(),
				'homeUrl'                           => home_url( '/' ),
				'adminUrl'                          => admin_url(),
				'loginUrl'                          => wp_login_url(),
				'themeUrl'                          => BRICKS_URL,
				'assetsUrl'                         => BRICKS_URL_ASSETS,
				'editPostUrl'                       => get_edit_post_link( $post_id ),
				'previewUrl'                        => add_query_arg( 'bricks_preview', time(), get_the_permalink( $post_id ) ),
				'siteName'                          => get_bloginfo( 'name' ),
				'siteUrl'                           => get_site_url(),
				'settingsUrl'                       => Helpers::settings_url(),
				'elementsManagerUrl'                => Helpers::elements_manager_url(),

				'defaultImageSize'                  => 'large',
				'author'                            => get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) ),
				'canManageOptions'                  => current_user_can( 'manage_options' ),
				'isTemplate'                        => get_post_type() === BRICKS_DB_TEMPLATE_SLUG,
				'isRtl'                             => is_rtl(),
				'postId'                            => $post_id,
				'postStatus'                        => get_post_status( $post_id ),
				'postType'                          => get_post_type( $post_id ),
				'postTypeUrl'                       => admin_url( 'edit.php?post_type=' ) . get_post_type( $post_id ),
				'postTypesRegistered'               => Helpers::get_registered_post_types(),
				'postTypesSupported'                => Helpers::get_supported_post_types(),
				'postsPerPage'                      => get_option( 'posts_per_page' ),
				'elements'                          => Elements::$elements,
				'elementsCatFirst'                  => $this->get_first_elements_category( $post_id ),
				'wpEditor'                          => $this->get_wp_editor(),
				'recaptchaIds'                      => [],
				'saveMessages'                      => $this->save_messages(),
				'builderParam'                      => BRICKS_BUILDER_PARAM,

				'animatedTypingInstances'           => [], // Necessary to destroy and then reinit TypedJS instances
				'videoInstances'                    => [], // Necessary to destroy and then reinit Plyr instances
				'splideInstances'                   => [], // Necessary to destroy and then reinit SplideJS instances
				'tocbotInstances'                   => [], // Necessary to destroy and then reinit Tocbot instances
				'swiperInstances'                   => [], // Necessary to destroy and then reinit SwiperJS instances
				'isotopeInstances'                  => [], // Necessary to destroy and then reinit Isotope instances
				'filterInstances'                   => [], // Necessary to destroy and then reinit query filter instances
				'googleMapInstances'                => [], // Necessary to destroy and then reinit Google Maps instances
				'activeFiltersCountInstances'       => [], // Necessary to destroy and then reinit query filter instances

				'icons'                             => self::get_icon_font_classes(),

				'controls'                          => [
					'themeStyles'  => Theme_Styles::get_controls_data(),
					'settings'     => Settings::get_controls_data(),
					'conditions'   => Conditions::get_controls_data(),
					'interactions' => Interactions::get_controls_data(),
				],

				'controlOptions'                    => $control_options, // Static data

				'themeStyles'                       => Theme_Styles::$styles,

				'remoteTemplateSettings'            => Templates::get_remote_template_settings(),

				'template'                          => [
					'orderBy' => $control_options['templatesOrderBy'],
					'preview' => self::get_template_preview_data( $post_id ),

					'authors' => Templates::get_template_authors(),
					'bundles' => Templates::get_template_bundles(),
					'tags'    => Templates::get_template_tags(),

					'types'   => $control_options['templateTypes'],
				],

				'mailchimpLists'                    => Integrations\Form\Actions\Mailchimp::get_list_options(),
				'wooCommerceActive'                 => Woocommerce::$is_active,
				'googleFontsDisabled'               => Helpers::google_fonts_disabled(),
				'fonts'                             => self::get_fonts(),
				'templateManagerThumbnailHeight'    => Database::get_setting( 'templateManagerThumbnailHeight' ),
				'themeStylesLoadingMethod'          => Database::get_setting( 'themeStylesLoadingMethod', 'specific' ), // @since 2.0
				'codeMirrorConfig'                  => apply_filters( 'bricks/builder/codemirror_config', [] ), // @since 1.11.1
				'builderGlobalClassesImport'        => Database::get_setting( 'builderGlobalClassesImport' ),
				'placeholderImage'                  => [
					'img'     => self::get_template_placeholder_image(),
					'svg'     => self::get_template_placeholder_image( true ),
					'svgPath' => self::get_template_placeholder_image( true, 'path' ),
				], // @since 1.12.2
				'pasteAndImportImage'               => Database::get_setting( 'importImageOnPaste', false ), // @since 1.12.2
				'adobeFontsProjectId'               => Database::get_setting( 'adobeFontsProjectId' ),
				'bricksGoogleMarkerScript'          => BRICKS_URL_ASSETS . 'js/libs/bricks-google-marker.min.js?v=' . BRICKS_VERSION, // @since 2.0
				'infoboxScript'                     => BRICKS_URL_ASSETS . 'js/libs/infobox.min.js?v=' . BRICKS_VERSION, // @since 2.0
				'markerClustererScript'             => BRICKS_URL_ASSETS . 'js/libs/markerclusterer.min.js?v=' . BRICKS_VERSION, // @since 2.0
			]
		);

		/**
		 * Deregister wp-polyfill.min.js as it is causing performance issue for Firefox browser (in WordPress 6.4+)
		 *
		 * @since 1.9.5
		 */
		if ( ! Database::get_setting( 'builderWpPolyfill', false ) && wp_script_is( 'wp-polyfill', 'registered' ) ) {
			wp_deregister_script( 'wp-polyfill' );
			wp_register_script( 'wp-polyfill', false );
		}
	}

	/**
	 * Enqueue inline styles for static areas
	 *
	 * NOTE: Not in use (handled in StaticArea.vue line198). Keep for future reference.
	 *
	 * @since 1.8.2 (#862jzhynp)
	 */
	public function static_area_styles() {
		return;

		// Return: Is main window (static area styles only needed on iframe canvas)
		if ( ! bricks_is_builder_iframe() ) {
			return;
		}

		$static_areas  = [];
		$template_type = Templates::get_template_type();

		// Header template: Static areas are 'content' & 'footer'
		if ( $template_type === 'header' ) {
			$static_areas = [ 'content', 'footer' ];
		} elseif ( $template_type === 'content' ) {
			$static_areas = [ 'header', 'footer' ];
		} elseif ( $template_type === 'footer' ) {
			$static_areas = [ 'header', 'content' ];
		}

		foreach ( $static_areas as $static_area ) {
			$preview_id         = ! empty( Database::$active_templates[ $static_area ] ) ? Database::$active_templates[ $static_area ] : 0;
			$static_area_handle = "bricks-static-area-{$static_area}-{$preview_id}";

			if ( $preview_id ) {
				// Generate & use only inline styles for this static area
				Assets::generate_inline_css( $preview_id );
				$styles = ! empty( Assets::$inline_css[ $static_area ] ) ? Assets::$inline_css[ $static_area ] : '';

				// Dynamic background image inside query loop (@see assets.php (l1365))
				if ( Database::get_setting( 'cssLoading' ) === 'file' ) {
					$styles .= Assets::$inline_css_dynamic_data;
				}

				if ( ! $styles ) {
					continue;
				}

				wp_register_style( $static_area_handle, false );
				wp_enqueue_style( $static_area_handle );
				wp_add_inline_style( $static_area_handle, $styles );
			}
		}
	}

	/**
	 * Get WordPress data for use in builder x-template (to reduce AJAX calls)
	 *
	 * @return array
	 *
	 * @since 1.0
	 */
	public static function get_wordpress_data() {
		return [
			'post' => [
				'title'         => Helpers::get_the_title( get_the_ID(), false ),
				'title_context' => Helpers::get_the_title( get_the_ID(), true ),
			],
		];
	}

	/**
	 * Get all fonts
	 *
	 * - Adobe fonts (@since 1.7.1)
	 * - Custom fonts
	 * - Google fonts
	 * - Standard fonts
	 *
	 * @since 1.2.1
	 *
	 * @return array
	 */
	public static function get_fonts() {
		$fonts = [];

		// Build font dropdown 'options' for ControlTypography.vue
		$options = [];

		// STEP: Adobe fonts
		$adobe_fonts = Database::$adobe_fonts;

		if ( is_array( $adobe_fonts ) && count( $adobe_fonts ) ) {
			$options['adobeFontsGroupTitle'] = 'Adobe fonts';

			foreach ( $adobe_fonts as $adobe_font ) {
				$adobe_font_family_name      = $adobe_font['name'] ?? '';
				$adobe_font_family_slug      = $adobe_font['slug'] ?? '';
				$adobe_font_family_css_names = $adobe_font['css_names'] ?? [];

				if ( ! $adobe_font_family_name ) {
					continue;
				}

				/**
				 * Segmented fonts: For legacy Adobe Fonts kits, a font may have multiple CSS names (is always an array, though) (@since 1.9.4)
				 *
				 * Example: Azo Sans (where 'slug' is 'azo-sans', but css_names[0] is 'azo-sans-web', the latter which we need).
				 *
				 * https://fonts.adobe.com/docs/api/css_names
				 */
				if ( is_array( $adobe_font_family_css_names ) && count( $adobe_font_family_css_names ) ) {
						// Concatenate CSS names
						$adobe_font_family_key = implode( ', ', $adobe_font_family_css_names );

						$options[ $adobe_font_family_key ] = $adobe_font_family_name;
				}

				// Fallack to font slug
				else {
					$options[ $adobe_font_family_slug ] = $adobe_font_family_name;
				}
			}

			if ( count( $adobe_fonts ) ) {
				$fonts['adobe'] = $adobe_fonts;
			}
		}

		// STEP: Custom fonts
		$custom_fonts = Custom_Fonts::get_custom_fonts();

		if ( $custom_fonts ) {
			$options['customFontsGroupTitle'] = esc_html__( 'Custom Fonts', 'bricks' );

			foreach ( $custom_fonts as $custom_font_id => $custom_font ) {
				$options[ $custom_font_id ] = $custom_font['family'];
			}

			$fonts['custom'] = $custom_fonts;
		}

		// STEP: Google fonts (if not disabled via filter OR settings)
		if ( ! Helpers::google_fonts_disabled() ) {
			$google_fonts = self::get_google_fonts();

			$options['googleFontsGroupTitle'] = 'Google fonts';

			foreach ( $google_fonts as $google_font ) {
				$options[ $google_font['family'] ] = $google_font['family'];
			}

			$fonts['google'] = $google_fonts;
		}

		// STEP: Standard fonts
		$standard_fonts = self::get_standard_fonts();

		$options['standardFontsGroupTitle'] = esc_html__( 'Standard fonts', 'bricks' );

		foreach ( $standard_fonts as $standard_font ) {
			$options[ $standard_font ] = $standard_font;
		}

		$fonts['standard'] = $standard_fonts;

		$fonts['options'] = $options;

		return $fonts;
	}

	/**
	 * Get standard (web safe) fonts
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_standard_fonts() {
		$standard_fonts = [
			'Arial',
			'Helvetica',
			'Helvetica Neue',
			'Times New Roman',
			'Times',
			'Georgia',
			'Courier New',
		];

		return apply_filters( 'bricks/builder/standard_fonts', $standard_fonts );
	}

	/**
	 * Get Google fonts
	 *
	 * Return fonts array with 'family' & 'variants' (to update font-weight for each font in builder)
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_google_fonts() {
		/**
		 * STEP: Generate Google fonts JSON file from API response
		 *
		 * We only need the 'family' & 'variants' properties from the Google fonts API response.
		 *
		 * DEV_ONLY: Set $google_fonts_generate below to true to generate the JSON file!
		 * NOTE First get the Google fonts JSON file from the API response (https://www.googleapis.com/webfonts/v1/webfonts) and save it to the src/assets/fonts/ folder.
		 *
		 * @since 1.7.1
		 */
		$google_fonts_generate = false;

		if ( $google_fonts_generate ) {
			$google_fonts = file_get_contents(
				BRICKS_URL . 'src/assets/fonts/google-fonts.json',
				false,
				stream_context_create(
					[
						'ssl' => [
							'verify_peer'      => false,
							'verify_peer_name' => false,
						],
					]
				)
			);

			$google_fonts           = json_decode( $google_fonts, true );
			$google_fonts           = $google_fonts['items'] ?? [];
			$google_fonts_processed = [];

			foreach ( $google_fonts as $google_font ) {
				$family   = ! empty( $google_font['family'] ) ? $google_font['family'] : false;
				$variants = ! empty( $google_font['variants'] ) ? wp_json_encode( $google_font['variants'] ) : false;

				$variants = str_replace( 'regular', '400', $variants );

				if ( ! $family ) {
					continue;
				}

				$google_fonts_processed[] = [
					'family'   => $family,
					'variants' => json_decode( $variants, true ),
				];
			}

			// Encode into minified JSON format
			$json = wp_json_encode( $google_fonts_processed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

			// Save the modified JSON data back to the file
			file_put_contents( BRICKS_PATH . 'src/assets/fonts/google-fonts.min.json', $json );

			return [];
		}

		// STEP: Get contents of the Google fonts JSON file
		$google_fonts = Helpers::file_get_contents( BRICKS_PATH_ASSETS . 'fonts/google-fonts.min.json' );

		// Return: Empty file OR not found
		if ( ! $google_fonts ) {
			return [];
		}

		// Decode the JSON data into a PHP object
		$google_fonts = json_decode( $google_fonts, true );

		return is_array( $google_fonts ) ? $google_fonts : [];
	}

	/**
	 * Template placeholder image (if importImages set to false)
	 *
	 * @since 1.0
	 */
	public static function get_template_placeholder_image( $is_svg = false, $format = 'url' ) {
		$image = $is_svg ? 'placeholder-svg.svg' : 'placeholder-image-800x600.jpg';

		$default_image = $format === 'path' ? BRICKS_PATH . 'assets/images/' . $image : get_template_directory_uri() . '/assets/images/' . $image;

		// @see https://article.bricksbuilder.io/article/filter-placeholder_image/ (@since 2.0)
		return apply_filters( 'bricks/placeholder_image', $default_image, $is_svg, $format );
	}

	/**
	 * Template preview data
	 *
	 * @since 1.0
	 */
	public static function get_template_preview_data( $post_id ) {
		$preview_data = [];

		// Placeholder HTML
		$placeholder = '<section class="brxe-container brxe-alert" style="cursor: pointer">';

		$placeholder .= Helpers::get_element_placeholder(
			[
				'icon-class' => 'ti-layout',
				'text'       => esc_html__( 'Click to set preview content.', 'bricks' ),
			]
		);

		$placeholder .= '</section>';

		$preview_data['placeholder'] = $placeholder;

		// Only add the preview post id if there is a preview
		if ( Helpers::is_bricks_template( $post_id ) ) {
			$preview_post_id = Helpers::get_template_setting( 'templatePreviewPostId', $post_id );

			if ( $preview_post_id ) {
				$post_id = intval( $preview_post_id );
			}

			$preview_data['postId'] = $post_id;
		}

		return $preview_data;
	}

	/**
	 * Post thumbnail data (for use in _background control)
	 *
	 * @since 1.0
	 */
	public function get_post_thumbnail() {
		return [
			'filename' => basename( get_attached_file( get_post_thumbnail_id( get_the_ID() ) ) ),
			'full'     => get_the_post_thumbnail_url( get_the_ID(), 'full' ),
			'id'       => get_post_thumbnail_id( get_the_ID() ),
			'size'     => BRICKS_DEFAULT_IMAGE_SIZE,
			'url'      => get_the_post_thumbnail_url( get_the_ID(), BRICKS_DEFAULT_IMAGE_SIZE ),
		];
	}

	/**
	 * Custom TinyMCE settings for builder
	 *
	 * @since 1.0
	 */
	public function tiny_mce_before_init( $in ) {
		// Remove certain TinyMCE plugins in builder
		$plugins = explode( ',', $in['plugins'] );
		$key     = array_search( 'fullscreen', $plugins );

		if ( isset( $plugins[ $key ] ) ) {
			unset( $plugins[ $key ] );
		}

		$in['plugins'] = join( ',', $plugins );

		return $in;
	}

	/**
	 * WordPress editor
	 *
	 * Without tag button, "Add media" button (use respective elements instead)
	 *
	 * @since 1.0
	 */
	public function get_wp_editor() {
		ob_start();

		$mce_buttons = add_filter(
			'mce_buttons',
			function( $buttons ) {
				// NOTE: Show all editor controls @since 1.3.6 ("Basic Text" element)
				// Remove formatselect button (paragraph/heading/preformatted etc.)
				// $buttons_to_remove = [ 'formatselect' ];

				// foreach ( $buttons as $index => $button_name ) {
				// if ( in_array( $button_name, $buttons_to_remove ) ) {
				// unset( $buttons[ $index ] );
				// }
				// }

				// Add dynamic tag picker dropdown button to tinyMCE editor
				$buttons[] = 'tagPickerButton';

				return $buttons;
			}
		);

		$content   = '%%BRICKS_EDITOR_CONTENT_PLACEHOLDER%%';
		$editor_id = 'brickswpeditor'; // No dashes, see https://codex.wordpress.org/Function_Reference/wp_editor
		$settings  = [
			'editor_class' => 'bricks-wp-editor',
			// 'media_buttons' => false, // Use image element instead
			'quicktags'    => [
				'buttons' => 'sup',
			// 'buttons' => 'strong,em,ul,ol,li,link,close', // No spaces
			],
		];

		wp_editor( $content, $editor_id, $settings );

		return ob_get_clean();
	}

	/**
	 * Add 'superscript' & 'subscript' button to TinyMCE in builder
	 *
	 * @since 1.4
	 */
	public function add_editor_buttons( $buttons ) {
		if ( ! in_array( 'superscript', $buttons ) ) {
			$buttons[] = 'superscript';
		}

		if ( ! in_array( 'subscript', $buttons ) ) {
			$buttons[] = 'subscript';
		}

		return $buttons;
	}

	/**
	 * Builder strings
	 *
	 * @since 1.0
	 */
	public static function i18n() {
		$i18n = I18n::get_all_i18n();

		return apply_filters( 'bricks/builder/i18n', $i18n );
	}

	/**
	 * Custom save messages
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function save_messages() {
		$messages = [
			esc_html__( 'All right', 'bricks' ),
			esc_html__( 'Amazing', 'bricks' ),
			esc_html__( 'Aye', 'bricks' ),
			esc_html__( 'Beautiful', 'bricks' ),
			esc_html__( 'Brilliant', 'bricks' ),
			esc_html__( 'Champ', 'bricks' ),
			esc_html__( 'Cool', 'bricks' ),
			esc_html__( 'Congrats', 'bricks' ),
			esc_html__( 'Done', 'bricks' ),
			esc_html__( 'Excellent', 'bricks' ),
			esc_html__( 'Exceptional', 'bricks' ),
			esc_html__( 'Exquisite', 'bricks' ),
			esc_html__( 'Enjoy', 'bricks' ),
			esc_html__( 'Fantastic', 'bricks' ),
			esc_html__( 'Fine', 'bricks' ),
			esc_html__( 'Good', 'bricks' ),
			esc_html__( 'Grand', 'bricks' ),
			esc_html__( 'Impressive', 'bricks' ),
			esc_html__( 'Incredible', 'bricks' ),
			esc_html__( 'Magnificent', 'bricks' ),
			esc_html__( 'Marvelous', 'bricks' ),
			esc_html__( 'Neat', 'bricks' ),
			esc_html__( 'Nice job', 'bricks' ),
			esc_html__( 'Okay', 'bricks' ),
			esc_html__( 'Outstanding', 'bricks' ),
			esc_html__( 'Remarkable', 'bricks' ),
			esc_html__( 'Saved', 'bricks' ),
			esc_html__( 'Skillful', 'bricks' ),
			esc_html__( 'Stunning', 'bricks' ),
			esc_html__( 'Superb', 'bricks' ),
			esc_html__( 'Sure thing', 'bricks' ),
			esc_html__( 'Sweet', 'bricks' ),
			esc_html__( 'Top', 'bricks' ),
			esc_html__( 'Very well', 'bricks' ),
			esc_html__( 'Woohoo', 'bricks' ),
			esc_html__( 'Wonderful', 'bricks' ),
			esc_html__( 'Yeah', 'bricks' ),
			esc_html__( 'Yep', 'bricks' ),
			esc_html__( 'Yes', 'bricks' ),
		];

		$messages = apply_filters( 'bricks/builder/save_messages', $messages );

		return $messages;
	}

	/**
	 * Get icon font classes
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_icon_font_classes() {
		return [
			'close'         => 'ion-md-close',
			'undo'          => 'ion-ios-undo',
			'redo'          => 'ion-ios-redo',

			'arrowRight'    => 'ion-ios-arrow-forward',
			'arrowDown'     => 'ion-ios-arrow-down',
			'arrowLeft'     => 'ion-ios-arrow-back',
			'arrowUp'       => 'ion-ios-arrow-up',

			'preview'       => 'ion-ios-eye',
			'settings'      => 'ion-md-settings',
			'structure'     => 'ion-ios-albums',

			'publish'       => 'ion-ios-power',
			'templates'     => 'ion-ios-folder-open',
			'page'          => 'ion-md-document',

			'desktop'       => 'ion-md-desktop',
			'mobile'        => 'ion-md-phone-portrait',
			'globe'         => 'ion-md-globe',
			'documentation' => 'ion-ios-help-buoy',
			'panelMaximize' => 'ion-ios-qr-scanner',
			'panelMinimize' => 'ion-ios-qr-scanner',

			'add'           => 'ion-md-add',
			'addTi'         => 'ti-plus',
			'remove'        => 'ion-md-remove',
			'edit'          => 'ion-md-create',
			'clone'         => 'ion-ios-copy',
			'move'          => 'ion-md-move',
			'save'          => 'ion-md-save',
			'check'         => 'ion-md-checkmark',
			'trash'         => 'ion-md-trash',
			'trashTi'       => 'ti-trash',
			'newTab'        => 'ti-new-window',

			'brush'         => 'ion-md-brush',
			'image'         => 'ion-ios-image',
			'video'         => 'ion-md-videocam',
			'cssFilter'     => 'ion-md-color-filter',

			'faceSad'       => 'ti-face-sad',
			'heart'         => 'ion-md-heart',
			'refresh'       => 'ti-reload',
			'help'          => 'ti-help-alt',
			'helpIon'       => 'ion-md-help-circle',
			'hover'         => 'ti-hand-point-up',
			'more'          => 'ti-more-alt',
			'notifications' => 'ti-bell',
			'revisions'     => 'ion-md-time',
			'link'          => 'ion-ios-link',
			'docs'          => 'ti-agenda',
			'email'         => 'ion-ios-mail',

			'search'        => 'ti-search',
			'wordpress'     => 'ti-wordpress',

			'import'        => 'ti-import',
			'export'        => 'ti-export',
			'download'      => 'ti-download',
			'zoomIn'        => 'ti-zoom-in',
		];
	}

	/**
	 * Based on post_type or template type select the first elements category to show up on builder.
	 */
	public function get_first_elements_category( $post_id = 0 ) {
		$post_type = get_post_type( $post_id );

		// NOTE: Undocumented
		$category = apply_filters( 'bricks/builder/first_element_category', false, $post_id, $post_type );

		if ( $category ) {
			return $category;
		}

		if ( 'page' !== $post_type ) {
			return 'single';
		}

		return '';
	}

	/**
	 * Default color palette (https://www.materialui.co/colors)
	 *
	 * Only used if no custom colorPalette is saved in db.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function default_color_palette() {
		$colors = [
			// Grey
			[ 'hex' => '#f5f5f5' ],
			[ 'hex' => '#e0e0e0' ],
			[ 'hex' => '#9e9e9e' ],
			[ 'hex' => '#616161' ],
			[ 'hex' => '#424242' ],
			[ 'hex' => '#212121' ],

			// A200
			[ 'hex' => '#ffeb3b' ],
			[ 'hex' => '#ffc107' ],
			[ 'hex' => '#ff9800' ],
			[ 'hex' => '#ff5722' ],
			[ 'hex' => '#f44336' ],
			[ 'hex' => '#9c27b0' ],

			[ 'hex' => '#2196f3' ],
			[ 'hex' => '#03a9f4' ],
			[ 'hex' => '#81D4FA' ],
			[ 'hex' => '#4caf50' ],
			[ 'hex' => '#8bc34a' ],
			[ 'hex' => '#cddc39' ],
		];

		$colors = apply_filters( 'bricks/builder/color_palette', $colors );

		foreach ( $colors as $key => $color ) {
			$colors[ $key ]['id'] = Helpers::generate_random_id( false );
			// translators: %s: Color #
			$colors[ $key ]['name'] = sprintf( esc_html__( 'Color #%s', 'bricks' ), $key + 1 );
		}

		$palettes[] = [
			'id'     => Helpers::generate_random_id( false ),
			'name'   => esc_html__( 'Default', 'bricks' ),
			'colors' => $colors,
		];

		return $palettes;
	}

	/**
	 * Check permissions for a certain user to access the Bricks builder
	 *
	 * @since 1.0
	 */
	public function template_redirect() {
		// Redirect non-logged-in visitors to home page
		if ( ! is_user_logged_in() ) {
			wp_redirect( home_url() );
			die;
		}

		// STEP: Return if license in not valid
		$license_is_valid = License::license_is_valid();

		if ( ! $license_is_valid ) {
			wp_redirect( admin_url( 'admin.php?page=bricks-license' ) );
		}

		// STEP: Return if current user can not edit this post
		$post_id = is_single() ? get_the_ID() : 0;
		if ( ! Capabilities::current_user_can_use_builder( $post_id ) ) {
			// Redirect users without builder capabilities back to WordPress admin area
			wp_redirect( admin_url( '/?action=edit&bricks_notice=error_role_manager' ) );
			die();
		}

		// NOTE: Don't check for template
		if ( is_home() || ( function_exists( 'is_shop' ) && is_shop() ) ) {
			return;
		}

		// STEP: Return if post type is not supported for editing with Bricks
		$current_post_type = get_post_type();

		$supported_post_types = Database::get_setting( 'postTypes', [] );

		// Bricks templates always have builder support
		if ( $current_post_type === BRICKS_DB_TEMPLATE_SLUG ) {
			$supported_post_types[] = BRICKS_DB_TEMPLATE_SLUG;
		}

		// NOTE: Undocumented
		$supported_post_types = apply_filters( 'bricks/builder/supported_post_types', $supported_post_types, $current_post_type );

		if ( ! in_array( $current_post_type, $supported_post_types ) ) {
			wp_redirect( admin_url( "/edit.php?post_type={$current_post_type}&bricks_notice=error_post_type" ) );
		}
	}

	/**
	 * Get page data for builder
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function builder_data( $post_id ) {
		$global_data              = Database::$global_data;
		$page_data                = Database::$page_data;
		$theme_styles             = Theme_Styles::$styles;
		$theme_style_active_ids   = array_keys( Theme_Styles::$settings_by_id );
		$theme_style_active_id    = end( $theme_style_active_ids ); // Get most specific theme style (@since 2.0)
		$template_settings        = Helpers::get_template_settings( $post_id );
		$template_preview_post_id = Helpers::get_template_setting( 'templatePreviewPostId', $post_id );

		$load_data = [
			'breakpoints'          => Breakpoints::$breakpoints,
			'permissions'          => Builder_Permissions::get_current_user_permissions(), // @since 2.0
			'breakpointActive'     => Breakpoints::$base_key,
			'themeStyles'          => $theme_styles,
			'themeStyleActiveIds'  => $theme_style_active_ids,
			'themeStyleActiveId'   => $theme_style_active_id,
			'pinnedElements'       => get_option( BRICKS_DB_PINNED_ELEMENTS, [] ),
			'elementManager'       => Elements::$manager,
			'codeExecutionEnabled' => Helpers::code_execution_enabled(),
			'currentUserId'        => get_current_user_id(),
			'queryMaxResults'      => self::get_query_max_results(),
			'userCan'              => [
				'executeCode'  => Capabilities::current_user_can_execute_code(),
				'uploadSvg'    => Capabilities::current_user_can_upload_svg(),
				'editPosts'    => current_user_can( 'edit_posts' ), // User can create/edit posts (@since 2.0)
				'publishPosts' => current_user_can( 'publish_posts' ),
				'publishPages' => current_user_can( 'publish_pages' ),
			],
		];

		// Components
		if ( ! empty( $global_data['components'] ) ) {
			// STEP: Upgrade components to use latest data structure and add to load_data
			$load_data['components'] = Components::upgrade_components( $global_data['components'], false );
		}

		// Add color palettes to load_data
		if ( ! empty( $global_data['colorPalette'] ) && is_array( $global_data['colorPalette'] ) ) {
			$load_data['colorPalette'] = $global_data['colorPalette'];
		} else {
			$load_data['colorPalette'] = self::default_color_palette();
		}

		// Add global queries to load_data (@since 2.1)
		if ( ! empty( $global_data['globalQueries'] ) && is_array( $global_data['globalQueries'] ) ) {
			$load_data['globalQueries'] = $global_data['globalQueries'];
		}

		// Add global queries categories to load_data (@since 2.1)
		if ( ! empty( $global_data['globalQueriesCategories'] ) && is_array( $global_data['globalQueriesCategories'] ) ) {
			$load_data['globalQueriesCategories'] = $global_data['globalQueriesCategories'];
		}

		// Add font favorites to load_data
		if ( ! empty( $global_data['fontFavorites'] ) ) {
			$load_data['fontFavorites'] = $global_data['fontFavorites'];
		}

		// Add global variables (@since 1.9.8)
		if ( ! empty( $global_data['globalVariables'] ) ) {
			$load_data['globalVariables'] = $global_data['globalVariables'];
		}

		// Add icon sets (@since 2.0)
		if ( ! empty( $global_data['iconSets'] ) ) {
			$load_data['iconSets'] = $global_data['iconSets'];
		}

		if ( ! empty( $global_data['customIcons'] ) ) {
			$load_data['customIcons'] = $global_data['customIcons'];
		}

		if ( ! empty( $global_data['disabledIconSets'] ) ) {
			$load_data['disabledIconSets'] = $global_data['disabledIconSets'];
		}

		// Add global variables categories (@since 1.9.8)
		if ( ! empty( $global_data['globalVariablesCategories'] ) ) {
			$load_data['globalVariablesCategories'] = $global_data['globalVariablesCategories'];
		}

		// Add global classes
		if ( ! empty( $global_data['globalClasses'] ) ) {
			$load_data['globalClasses'] = $global_data['globalClasses'];
		}

		// Add global classes trash (@since 1.11)
		if ( ! empty( $global_data['globalClassesTrash'] ) ) {
			$load_data['globalClassesTrash'] = $global_data['globalClassesTrash'];

			// Set username for global classes trash view
			foreach ( $load_data['globalClassesTrash'] as $key => $class ) {
				$trashed_by_user_id = ! empty( $class['user_id'] ) ?? false;

				if ( $trashed_by_user_id && $trashed_by_user_id != get_current_user_id() ) {
					$user = get_user_by( 'id', $class['user_id'] );

					if ( $user ) {
						$load_data['globalClassesTrash'][ $key ]['deletedBy'] = $user->display_name;
					}
				}
			}
		}

		// Add global classes categories
		if ( ! empty( $global_data['globalClassesCategories'] ) ) {
			$load_data['globalClassesCategories'] = $global_data['globalClassesCategories'];
		}

		// Add global classes locked
		if ( ! empty( $global_data['globalClassesLocked'] ) ) {
			$load_data['globalClassesLocked'] = $global_data['globalClassesLocked'];
		}

		// Add global classes timestamp (@since 1.9.8)
		if ( ! empty( $global_data['globalClassesTimestamp'] ) ) {
			$load_data['globalClassesTimestamp'] = $global_data['globalClassesTimestamp'];
		}

		// Add global classes user (@since 1.9.8)
		if ( ! empty( $global_data['globalClassesUser'] ) ) {
			$load_data['globalClassesUser'] = $global_data['globalClassesUser'];
		}

		// Add pseudo classes
		if ( ! empty( $global_data['pseudoClasses'] ) ) {
			$load_data['pseudoClasses'] = $global_data['pseudoClasses'];
		} else {
			$load_data['pseudoClasses'] = [
				':hover',
				':active',
				':focus',
			];
		}

		// Add elements & global settings
		if ( ! empty( $global_data['elements'] ) ) {
			$load_data['globalElements'] = $global_data['elements'];
		}

		if ( ! empty( $global_data['settings'] ) ) {
			$load_data['globalSettings'] = $global_data['settings'];
		}

		// Add page data to load_data
		if ( ! empty( $page_data['header'] ) ) {
			$load_data['header'] = $page_data['header'];
		} else {
			// Check for header template
			$template_header_id = Database::$active_templates['header'];
			$header_template    = $template_header_id ? Database::get_data( $template_header_id, 'header' ) : Database::get_data( $post_id, 'header' );

			if ( ! empty( $header_template ) ) {
				$load_data['header'] = $header_template;

				// Sticky header, header postition etc.
				$load_data['templateHeaderSettings'] = Helpers::get_template_settings( $template_header_id );
			}
		}

		// Content
		$template_content_id = Database::$active_templates['content'];

		if ( count( $page_data['content'] ) ) {
			$load_data['content'] = $page_data['content'];
		}

		// If content still not populated, check if populated content was set to preview it
		if ( empty( $load_data['content'] ) && $template_preview_post_id ) {
			// Template preview
			$content              = get_post_meta( $template_preview_post_id, BRICKS_DB_PAGE_CONTENT, true );
			$load_data['content'] = empty( $content ) ? [] : $content;
		}

		// Last resort for getting content: WP blocks
		if ( empty( $load_data['content'] ) && Database::get_setting( 'wp_to_bricks' ) ) {
			$template_preview_post_id = $template_preview_post_id ? $template_preview_post_id : $post_id;

			// Convert Gutenberg blocks to Bricks element
			$converter           = new Blocks();
			$content_from_blocks = $converter->convert_blocks_to_bricks( $template_preview_post_id );

			if ( is_array( $content_from_blocks ) ) {
				$load_data['content'] = $content_from_blocks;

				// NOTE: Development-only
				$post   = get_post( $template_content_id );
				$blocks = parse_blocks( $post->post_content );

				$load_data['blocks'] = $blocks;
			}
		}

		// Add template preview logic for single templates (@since 1.12)
		if ( $template_content_id && $template_content_id != $post_id ) {
			// Load template content for static area
			$template_content = Database::get_data( $template_content_id, 'content' );

			if ( ! empty( $template_content ) ) {
				$load_data['staticContent'] = $template_content;

				// Add template page settings to generate CSS for static content (@since 1.12)
				$template_page_settings = get_post_meta( $template_content_id, BRICKS_DB_PAGE_SETTINGS, true );

				if ( ! empty( $template_page_settings ) ) {
					$load_data['outerPostContentTemplatePageSettings'] = $template_page_settings;
				}
			}
		}

		// Footer
		if ( ! empty( $page_data['footer'] ) ) {
			$load_data['footer'] = $page_data['footer'];
		} else {
			$template_footer_id = Database::$active_templates['footer'];

			// Check for footer template
			$footer_template = $template_footer_id ? Database::get_data( $template_footer_id, 'footer' ) : [];

			if ( ! empty( $footer_template ) ) {
				$load_data['footer'] = $footer_template;
			}
		}

		if ( ! empty( $page_data['settings'] ) ) {
			$load_data['pageSettings'] = $page_data['settings'];
		}

		// Template type
		$template_type = Templates::get_template_type( $post_id );

		// @since 1.7.1 - Default template type is 'content' (so listenHistory in builder can work properly)
		$load_data['templateType'] = ! empty( $template_type ) ? $template_type : 'content';

		// Template settings
		if ( $template_settings ) {
			$load_data['templateSettings'] = $template_settings;
		}

		// Parse elements to replace dynamic data (needed for background image)
		$template_preview_post_id = $template_preview_post_id ? $template_preview_post_id : $post_id;

		if ( $template_type !== 'header' && ! empty( $load_data['header'] ) && is_array( $load_data['header'] ) ) {
			$load_data['header'] = self::render_dynamic_data_on_elements( $load_data['header'], $template_preview_post_id );
		}

		if ( ! empty( $load_data['content'] ) && is_array( $load_data['content'] ) ) {
			$load_data['content'] = self::render_dynamic_data_on_elements( $load_data['content'], $template_preview_post_id );
		}

		if ( $template_type !== 'footer' && ! empty( $load_data['footer'] ) && is_array( $load_data['footer'] ) ) {
			$load_data['footer'] = self::render_dynamic_data_on_elements( $load_data['footer'], $template_preview_post_id );
		}

		/**
		 * Generate element HTML strings in PHP for fast initial render
		 *
		 * Individual element HTML AJAX calls in builder are too slow.
		 * Only load for dynamic area, but not static areas.
		 */
		$load_data['elementsHtml'] = [];

		// Remove setting in builder to get 'elementsHtml' with element ID for all PHP elements (@since 1.7)
		unset( Database::$global_settings['elementAttsAsNeeded'] );

		// New rendering mode: Collect HTML strings for all elements start (@since 2.0)
		add_filter( 'bricks/frontend/render_element', [ __CLASS__, 'collect_elements_html' ], 10, 2 );
		add_filter( 'bricks/frontend/render_loop', [ __CLASS__, 'collect_looping_html' ], 10, 3 );
		add_action( 'bricks/query/query_api_response', [ __CLASS__, 'collect_query_api_results' ], 10, 2 );

		// Header
		if ( $template_type === 'header' && isset( $load_data['header'] ) && is_array( $load_data['header'] ) ) {
			Frontend::render_data( $load_data['header'], 'header' );
		}

		// Content
		if ( ! in_array( $template_type, [ 'header', 'footer' ] ) && isset( $load_data['content'] ) && is_array( $load_data['content'] ) ) {
			Frontend::render_data( $load_data['content'], 'content' );
		}

		// Footer
		if ( $template_type === 'footer' && isset( $load_data['footer'] ) && is_array( $load_data['footer'] ) ) {
			Frontend::render_data( $load_data['footer'], 'footer' );
		}

		remove_filter( 'bricks/frontend/render_loop', [ __CLASS__, 'collect_looping_html' ], 10, 3 );
		// Collect HTML strings for all elements end (@since 2.0)
		remove_filter( 'bricks/frontend/render_element', [ __CLASS__, 'collect_elements_html' ], 10, 2 );
		remove_action( 'bricks/query/query_api_response', [ __CLASS__, 'collect_query_api_results' ], 10, 2 );

		// Set collected HTML strings for all elements for fast initial render, reduce API calls (@since 2.0)
		$load_data['elementsHtml']  = self::$elements_html;
		$load_data['previewTexts']  = self::$preview_texts;
		$load_data['loopingHtml']   = self::$looping_html;
		$load_data['templatesData'] = self::$templates_data;
		$load_data['queryApiCache'] = self::$query_api_cache;

		/**
		 * STEP: Pre-populate dynamic data to minimize AJAX requests on builder load
		 *
		 * Only if Bricks builder setting 'enableDynamicDataPreview' is enabled, we pre-populate.
		 *
		 * @see render_dynamic_data_on_elements
		 *
		 * @since 1.7.1
		 */
		if ( Database::get_setting( 'enableDynamicDataPreview', false ) && is_array( self::$dynamic_data ) && count( self::$dynamic_data ) ) {
			$load_data['dynamicData'] = self::$dynamic_data;
		}

		/**
		 * STEP: Code signatures validation for builder unsignedCodeIds
		 *
		 * @since 2.0
		 */
		$load_data['invalidCodeSignatures'] = self::get_invalid_code_signatures( $load_data );

		/**
		 * STEP: Add custom attributes to builder
		 *
		 * Keys: 'header', 'main', 'footer', or individual Bricks element ID
		 *
		 * @since 1.10
		 */
		$load_data['htmlAttributes'] = self::$html_attributes;

		$load_data['htmlAttributes']['header']  = apply_filters( 'bricks/header/attributes', [] );
		$load_data['htmlAttributes']['content'] = apply_filters( 'bricks/content/attributes', [] );
		$load_data['htmlAttributes']['footer']  = apply_filters( 'bricks/footer/attributes', [] );

		return $load_data;
	}

	/**
	 * Bricks 2.0 render mode
	 *
	 * Collect HTML string of every single element for initial fast builder render
	 *
	 * Use Frontend::render_data() instead of Ajax::render_element so all attributes can be collected successfully as well without execute apply_filters bricks/element/render_attributes
	 *
	 * @since 2.0 (#86c2z8bmd)
	 */
	public static function collect_elements_html( $html, $instance ) {
		$element_name = $instance->element['name'] ?? '';
		$settings     = $instance->element['settings'] ?? [];

		/**
		 * Skip: Nav menu
		 *
		 * As inside '.brx-dropdown-content' the nav-menu wrapper, etc. is not needed
		 *
		 * @since 1.11
		 */
		if ( $element_name === 'nav-menu' ) {
			return $html;
		}

		// Skip: Code element to prevent critical errors with code execution enabled on builder load
		if ( $element_name === 'code' ) {
			return $html;
		}

		/**
		 * Template element
		 *
		 * Skip to render template inline CSS in builder (TODO: Improve performance)
		 *
		 * @since 2.0: Do not skip if this is a render component call
		 */
		if ( $element_name === 'template' && empty( $_POST['isRenderComponent'] ) ) {
			return $html;
		}

		/**
		 * Skip: Shortcode element with bricks_template to render nested accordions, tabs, slider in builder
		 *
		 * Necessary as we skip nestable elements below (line 2335)
		 *
		 * @since 1.11
		 */
		if ( $element_name === 'shortcode' ) {
			$is_bricks_shortcode = ! empty( $settings['shortcode'] ) && strpos( $settings['shortcode'], 'bricks_template' ) !== false;

			if ( $is_bricks_shortcode ) {
				return $html;
			}
		}

		// Do not skip nestable elements or all inner elements wouldn't have HTML and cause extra render_elements calls in the builder
		// Skip nestable elements
		// if ( $instance->nestable ) {
		// return $html;
		// }

		// Handle component: Use unique ID that already considered elements inside component (@since 2.0)
		$element_id = $instance->uid ?? $instance->id;

		if ( ! empty( $_POST['isRenderComponent'] ) ) {
			// is RenderComponent call - always use the same instance ID so nested component's PHP element can get the correct HTML
			$root_instance_id = sanitize_key( $_POST['isRenderComponent'] );
			$element_id       = $instance->id . '-' . $root_instance_id;
		}

		if ( ! isset( self::$elements_html[ $element_id ] ) ) {
			// Manually run bricks_render_dynamic_data as bricks/frontend/render_data not yet triggered (#86c3reqnt)
			self::$elements_html[ $element_id ] = bricks_render_dynamic_data( $html );
		}

		// Pre-populate dynamic data for all elements (Only if not looping #86c4pmh96)
		if ( Database::get_setting( 'enableDynamicDataPreview', false ) && ! empty( $settings ) && ! Query::is_any_looping() ) {
			$settings_string = wp_json_encode( $settings );

			// Get all dynamic data tags inside element settings
			preg_match_all( '/\{([^{}"]+)\}/', $settings_string, $matches );
			$dynamic_data_tags = $matches[1];

			foreach ( $dynamic_data_tags as $dynamic_data_tag ) {
				$dynamic_data_value = \Bricks\Integrations\Dynamic_Data\Providers::render_tag( $dynamic_data_tag, $instance->post_id );

				if ( $dynamic_data_value ) {
					self::$dynamic_data[ "{$dynamic_data_tag}" ] = $dynamic_data_value;
				}
			}
		}

		// Generate preview text for 1st looping element, exclude nestable element to avoid unnecessary HTML generation, previewTexts are used by contentEditable elements
		if ( Query::is_any_looping() && ! $instance->nestable ) {
			if ( ! isset( self::$preview_texts[ $element_id ] ) ) {
				// Manually run bricks_render_dynamic_data as bricks/frontend/render_data not yet triggered (#86c3reqnt)
				// Do not wp_strip_all_tags so first loop node can render complete HTML (#86c5hj7au; @since 2.1)
				self::$preview_texts[ $element_id ] = bricks_render_dynamic_data( $html );
			}
		}

		return $html;
	}

	/**
	 * Bricks 2.0 builder render mode
	 *
	 * Collect query loop first node HTML string if the query located inside a component.
	 *
	 * @since 2.0
	 */
	public static function collect_looping_html( $html, $element_data, $instance ) {
		$element_id = $instance->uid ?? $instance->id;

		if ( ! empty( $_POST['isRenderComponent'] ) ) {
			// is RenderComponent call - always use the same instance ID so nested component's PHP element can get the correct HTML
			$root_instance_id = sanitize_key( $_POST['isRenderComponent'] );
			$element_id       = $instance->id . '-' . $root_instance_id;
		}

		if ( ! isset( self::$looping_html[ $element_id ] ) ) {
			self::$looping_html[ $element_id ] = $html;
		}

		return $html;
	}

	/**
	 * Collect query results for API calls
	 *
	 * Used in PopupQueryApi.vue
	 *
	 * @since 2.1
	 */
	public static function collect_query_api_results( $results, $element_id ) {
		// Collect query results for external API calls
		if ( isset( self::$query_api_cache[ $element_id ] ) ) {
			return;
		}

		// Do not cache if it's error
		if ( isset( $results['error'] ) && ! empty( $results['error'] ) ) {
			return;
		}
		// Add results to cache
		self::$query_api_cache[ $element_id ] = $results;
	}

	/**
	 * Screens all elements and try to convert dynamic data to enhance builder experience
	 *
	 * @param array $elements
	 * @param int   $post_id
	 */
	public static function render_dynamic_data_on_elements( $elements, $post_id ) {
		if ( strpos( wp_json_encode( $elements ), 'useDynamicData' ) === false ) {
			return $elements;
		}

		foreach ( $elements as $index => $element ) {
			$elements[ $index ]['settings'] = self::render_dynamic_data_on_settings( $element['settings'], $post_id );
		}

		return $elements;
	}

	/**
	 * On the settings array, if _background exists and is set to image, get the image URL
	 * Needed when setting element background image
	 *
	 * @param array $settings
	 * @param int   $post_id
	 */
	public static function render_dynamic_data_on_settings( $settings, $post_id ) {
		// Return: Do not render dynamic data for elements inside a loop
		if ( isset( $settings['hasLoop'] ) ) {
			return $settings;
		}

		$background_image_dd_tag = ! empty( $settings['_background']['image']['useDynamicData'] ) ? $settings['_background']['image']['useDynamicData'] : false;

		if ( ! $background_image_dd_tag ) {
			return $settings;
		}

		$size     = ! empty( $settings['_background']['image']['size'] ) ? $settings['_background']['image']['size'] : BRICKS_DEFAULT_IMAGE_SIZE;
		$images   = Integrations\Dynamic_Data\Providers::render_tag( $background_image_dd_tag, $post_id, 'image', [ 'size' => $size ] );
		$image_id = ! empty( $images[0] ) ? $images[0] : false;

		if ( ! $image_id ) {
			unset( $settings['_background']['image']['id'], $settings['_background']['image']['url'] );

			return $settings;
		}

		if ( is_numeric( $image_id ) ) {
			$settings['_background']['image']['id']   = $image_id;
			$settings['_background']['image']['size'] = $size;
			$settings['_background']['image']['url']  = wp_get_attachment_image_url( $image_id, $size );
		} else {
			$settings['_background']['image']['url'] = $image_id;
		}

		return $settings;
	}


	/**
	 * Builder: Force Bricks template to avoid conflicts with other builders (Elementor PRO, etc.)
	 */
	public function template_include( $template ) {
		if ( bricks_is_builder() ) {
			$template = BRICKS_PATH . 'template-parts/builder.php';
		}

		return $template;
	}

	/**
	 * Helper function to check if a AJAX or REST API call comes from inside the builder
	 *
	 * NOTE: Use bricks_is_builder_call() to check if AJAX/REST API call inside the builder
	 *
	 * @since 1.5.5
	 *
	 * @return boolean
	 */
	public static function is_builder_call() {
		/**
		 * STEP: Builder AJAX call: Check data for 'bricks-is-builder'
		 */
		if ( bricks_is_ajax_call() && check_ajax_referer( 'bricks-nonce-builder', 'nonce', false ) ) {
			$action     = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
			$is_builder = isset( $_REQUEST['bricks-is-builder'] );

			if ( $is_builder ) {
				return true;
			}
		}

		/**
		 * STEP: REST API call
		 *
		 * Is default builder render.
		 */
		if ( bricks_is_rest_call() ) {
			return ! empty( $_SERVER['HTTP_X_BRICKS_IS_BUILDER'] );
		}

		/**
		 * STEP: Builder frontend preview (window opened via builder toolbar preview icon)
		 *
		 * Check needed as referrer check below is the builder.
		 *
		 * @since 1.6.2
		 */
		if ( isset( $_GET['bricks_preview'] ) ) {
			return false;
		}

		// STEP: Check query string of referer URL (@since 1.5.5)
		$referer          = ! empty( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : wp_get_referer();
		$url_parsed       = $referer ? wp_parse_url( $referer ) : '';
		$url_query_string = isset( $url_parsed['query'] ) ? $url_parsed['query'] : '';

		if ( $url_query_string && strpos( $url_query_string, 'bricks=run' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Return the maximum number of query loop results to display in the builder
	 *
	 * @since 1.11
	 */
	public static function get_query_max_results() {
		return Database::get_setting( 'builderQueryMaxResults', false );
	}

	/**
	 * Get query max results info
	 *
	 * @since 1.11
	 */
	public static function get_query_max_results_info() {
		return sprintf( esc_html__( 'Query loop results in the builder are limited to %1$s.', 'bricks' ), self::get_query_max_results() ) . ' <a href="' . admin_url( 'admin.php?page=bricks-settings#tab-builder' ) . '" target="_blank">[' . esc_html__( 'Edit', 'bricks' ) . ']</a>';
	}

	/**
	 * Check if user enabled through Bricks > Settings > Builder builderCloudflareRocketLoader
	 *
	 * - Ensure that the request is coming from Cloudflare.
	 * - TODO: Will be set as default in a future version of Bricks. (#86c2rdm5a)
	 *
	 * @since 2.0
	 */
	public static function cloudflare_rocket_loader_disabled() {
		return ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && Database::get_setting( 'builderCloudflareRocketLoader', false );
	}

	/**
	 * Add data-cfasync="false" to all <script> tags to avoid Cloudflare Rocket Loader
	 *
	 * @since 2.0
	 */
	public function cloudflare_rocket_loader_modify_script_tags() {
		ob_start(
			function ( $html ) {
				// Use preg_replace to add data-cfasync="false" to all <script> tags
				$html = preg_replace_callback(
					'/<script\b([^>]*)>/i',
					function ( $matches ) {
						// Check if the script tag already has data-cfasync="false"
						if ( isset( $matches[1] ) && $matches[1] !== '' && strpos( $matches[1], 'data-cfasync' ) === false ) {
							// Add data-cfasync="false" to the script tag
							return '<script data-cfasync="false" ' . $matches[1] . '>';
						}

						// Return the original ta
						return $matches[0];
					},
					$html
				);

				return $html;
			}
		);
	}

	/**
	 * Returns an array of invalid code signatures elements IDs
	 *
	 * @param array $data (elements data)
	 * @since 2.0
	 */
	public static function get_invalid_code_signatures( $data ) {
		$invalid_code_signatures = [];

		// Check from 'header', 'content', 'footer'
		$elements = array_merge(
			$data['header'] ?? [],
			$data['content'] ?? [],
			$data['footer'] ?? []
		);

		// Elements from components
		foreach ( Database::$global_data['components'] as $component ) {
			if ( ! empty( $component['elements'] ) && is_array( $component['elements'] ) ) {
				$elements = array_merge( $elements, $component['elements'] );
			}
		}

		foreach ( $elements as $element ) {
			$element_settings = $element['settings'] ?? [];
			$element_name     = $element['name'] ?? '';

			$global_settings = Helpers::get_global_element( $element, 'settings' );

			if ( $global_settings ) {
				$element_settings = $global_settings;
			}

			// Check: Component root
			$component_instance_settings = ! empty( $element['cid'] ) ? Helpers::get_component_instance( $element, 'settings' ) : false;

			if ( $component_instance_settings ) {
				$element_settings = $component_instance_settings;
			}

			if ( empty( $element_settings ) ) {
				continue;
			}

			// STEP: Code element
			if ( $element_name === 'code' ) {
				$element['execute_code'] = isset( $element_settings['executeCode'] );

				// Execute code
				if ( $element['execute_code'] ) {
					$element_settings_code = isset( $element_settings['code'] ) ? $element_settings['code'] : '';

					// Skip if no code or empty code
					if ( empty( $element_settings_code ) ) {
						continue;
					}

					$valid = false;

					if ( ! empty( $element_settings['signature'] ) ) {
						$valid = Helpers::verify_code_signature( $element_settings['signature'], $element_settings_code );
					}

					if ( ! $valid ) {
						$invalid_code_signatures[] = $element['id'];
					}
				}

				continue;
			}

			// STEP: SVG element
			if ( $element_name === 'svg' ) {
				$element['execute_code'] = isset( $element_settings['code'] ) && ! empty( $element_settings['code'] );

				if ( $element['execute_code'] ) {
					$element_settings_code = isset( $element_settings['code'] ) ? $element_settings['code'] : '';

					// Skip if no code or empty code
					if ( empty( $element_settings_code ) ) {
						continue;
					}

					$valid = false;

					if ( ! empty( $element_settings['signature'] ) ) {
						$valid = Helpers::verify_code_signature( $element_settings['signature'], $element_settings_code );
					}

					if ( ! $valid ) {
						$invalid_code_signatures[] = $element['id'];
					}
				}

				continue;
			}

			// STEP: Query editor element
			if ( isset( $element_settings['query']['queryEditor'] ) ) {
				$element['execute_code'] = isset( $element_settings['query']['useQueryEditor'] );

				if ( $element['execute_code'] ) {
					$element_settings_code = isset( $element_settings['query']['queryEditor'] ) ? $element_settings['query']['queryEditor'] : '';

					// Skip if no code or empty code
					if ( empty( $element_settings_code ) ) {
						continue;
					}

					$valid = false;

					if ( ! empty( $element_settings['query']['signature'] ) ) {
						$valid = Helpers::verify_code_signature( $element_settings['query']['signature'], $element_settings_code );
					}

					if ( ! $valid ) {
						$invalid_code_signatures[] = $element['id'];
					}
				}

				continue;
			}

		}

		return $invalid_code_signatures;
	}
}
