<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Frontend {
	public static $area                    = 'content'; // header/content/footer
	public static $template_ids_to_enqueue = []; // IDs of templates to enqueue early (@since 1.12)

	/**
	 * Elements requested for rendering
	 *
	 * key: ID
	 * value: element data
	 */
	public static $elements = [];

	/**
	 * Live search results selectors
	 *
	 * key: live search ID
	 * value: live search results CSS selector
	 *
	 * @since 1.9.6
	 */
	public static $live_search_wrapper_selectors = [];

	public function __construct() {
		add_action( 'wp_head', [ $this, 'add_seo_meta_tags' ], 1 );

		add_filter( 'document_title_parts', [ $this, 'set_seo_document_title' ] );

		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'set_image_attributes' ], 10, 3 );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_inline_css' ], 11 );
		add_action( 'wp_footer', [ $this, 'enqueue_footer_inline_css' ] );

		add_action( 'bricks_after_site_wrapper', [ $this, 'one_page_navigation_wrapper' ] );

		// Load custom header body script (for analytics) only on the frontend
		add_action( 'wp_head', [ $this, 'add_header_scripts' ] );
		add_action( 'wp_head', [ $this, 'add_open_graph_meta_tags' ], 99999 );
		add_action( 'bricks_body', [ $this, 'add_body_header_scripts' ] );

		// Change the priority to 21 to load the custom scripts after the default Bricks scripts in the footer (@since 1.5)
		// @see core: add_action( 'wp_footer', 'wp_print_footer_scripts', 20 );
		add_action( 'wp_footer', [ $this, 'add_body_footer_scripts' ], 21 );

		add_action( 'template_redirect', [ $this, 'template_redirect' ] );

		// User activation: Update the user meta when the user is activated
		if ( Database::get_setting( 'userActivationEnabled' ) ) {
				add_action( 'template_redirect', [ $this, 'activate_user_account' ] );
		}

		add_action( 'bricks_body', [ $this, 'add_skip_link' ] );

		add_action( 'bricks_body', [ $this, 'remove_wp_hooks' ] );

		add_action( 'render_header', [ $this, 'render_header' ] );
		add_action( 'render_footer', [ $this, 'render_footer' ] );

		add_filter( 'wp_nav_menu_objects', [ $this, 'adjust_menu_item_classes' ], 10, 2 );
	}

	/**
	 * Add header scripts
	 *
	 * Do not add template JS (we only want to provide content)
	 *
	 * @since 1.0
	 */
	public function add_header_scripts() {
		$header_scripts = '';

		// Global settings scripts
		if ( ! empty( Database::$global_settings['customScriptsHeader'] ) ) {
			$header_scripts .= stripslashes_deep( Database::$global_settings['customScriptsHeader'] ) . PHP_EOL;
		}

		// Page settings header scripts (@since 1.4)
		$header_scripts .= Assets::get_page_settings_scripts( 'customScriptsHeader' );

		echo $header_scripts;
	}

	/**
	 * Page settings: Add meta description, keywords and robots
	 */
	public function add_seo_meta_tags() {
		// NOTE: Undocumented
		$disable_seo = apply_filters( 'bricks/frontend/disable_seo', ! empty( Database::$global_settings['disableSeo'] ) );

		if ( $disable_seo ) {
			return;
		}

		$template_id = Database::$active_templates['content'];

		$template_settings = get_post_meta( $template_id, BRICKS_DB_PAGE_SETTINGS, true );

		$post_id = is_home() ? get_option( 'page_for_posts' ) : get_the_ID();

		if ( $template_id !== $post_id ) {
			$page_settings = get_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS, true );
		}

		$seo_tags = [
			'metaDescription' => 'description',
			'metaKeywords'    => 'keywords',
			'metaRobots'      => 'robots',
		];

		foreach ( $seo_tags as $meta_key => $name ) {
			// Page settings preceeds Template settings
			$meta_value = ! empty( $page_settings[ $meta_key ] ) ? $page_settings[ $meta_key ] : ( ! empty( $template_settings[ $meta_key ] ) ? $template_settings[ $meta_key ] : false );

			if ( empty( $meta_value ) ) {
				continue;
			}

			if ( $meta_key == 'metaRobots' ) {
				$meta_value = join( ', ', $meta_value );
			} else {
				$meta_value = bricks_render_dynamic_data( $meta_value, $post_id );
			}

			echo '<meta name="' . $name . '" content="' . esc_attr( $meta_value ) . '">';
		}
	}

	/**
	 * Page settings: Set document title
	 *
	 * @param array $title
	 *
	 * @see https://developer.wordpress.org/reference/hooks/document_title_parts/
	 *
	 * @since 1.6.1
	 */
	public function set_seo_document_title( $title ) {
		// NOTE: Undocumented
		$disable_seo = apply_filters( 'bricks/frontend/disable_seo', ! empty( Database::$global_settings['disableSeo'] ) );

		if ( $disable_seo ) {
			return $title;
		}

		$template_id = Database::$active_templates['content'];

		$template_settings = get_post_meta( $template_id, BRICKS_DB_PAGE_SETTINGS, true );

		$post_id = is_home() ? get_option( 'page_for_posts' ) : get_the_ID();

		if ( $template_id !== $post_id ) {
			$page_settings = get_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS, true );
		}

		// Page settings preceeds Template settings
		$meta_value = ! empty( $page_settings['documentTitle'] ) ? $page_settings['documentTitle'] : ( ! empty( $template_settings['documentTitle'] ) ? $template_settings['documentTitle'] : false );

		if ( empty( $meta_value ) ) {
			return $title;
		}

		$meta_value = bricks_render_dynamic_data( $meta_value, $post_id );

		if ( $meta_value ) {
			$title['title'] = $meta_value;
		}

		return $title;
	}

	/**
	 * Add Facebook Open Graph Meta Data
	 *
	 * https://ogp.me
	 *
	 * @since 1.0
	 */
	public function add_open_graph_meta_tags() {
		// Return: Don't add Open Graph tag when maintenance mode is enabled (@since 1.10)
		if ( \Bricks\Maintenance::get_mode() ) {
			return;
		}

		// NOTE: Undocumented
		$disable_og = apply_filters( 'bricks/frontend/disable_opengraph', ! empty( Database::$global_settings['disableOpenGraph'] ) );

		if ( $disable_og ) {
			return;
		}

		// STEP: Calculate OG settings
		$template_id = Database::$active_templates['content'];

		$template_settings = get_post_meta( $template_id, BRICKS_DB_PAGE_SETTINGS, true );

		$post_id = is_home() ? get_option( 'page_for_posts' ) : get_the_ID();
		$post_id = empty( $post_id ) ? null : $post_id; // Fix PHP notice on Error page

		if ( $template_id !== $post_id && ! empty( $post_id ) ) {
			$page_settings = get_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS, true );
		}

		$settings = [];

		$og_tags = [
			'sharingTitle',
			'sharingDescription',
			'sharingImage',
		];

		foreach ( $og_tags as $meta_key ) {
			// Page settings preceeds Template settings
			$settings[ $meta_key ] = ! empty( $page_settings[ $meta_key ] ) ? $page_settings[ $meta_key ] : ( ! empty( $template_settings[ $meta_key ] ) ? $template_settings[ $meta_key ] : false );
		}

		// STEP: Render tags
		$open_graph_meta_tags = [];

		$facebook_app_id = Database::$global_settings['facebookAppId'] ?? false;

		if ( $facebook_app_id ) {
			$open_graph_meta_tags[] = '<meta property="fb:app_id" content="' . $facebook_app_id . '" />';
		}

		/**
		 * Get current page URL
		 *
		 * Has to work for all pages, including custom post types, archives, search, etc.
		 *
		 * @since 1.11
		 */
		global $wp;
		$og_url = home_url( add_query_arg( [], $wp->request ? trailingslashit( $wp->request ) : '' ) );

		$open_graph_meta_tags[] = '<meta property="og:url" content="' . $og_url . '" />';

		// Site Name
		$open_graph_meta_tags[] = '<meta property="og:site_name" content="' . get_bloginfo( 'name' ) . '" />';

		// Title
		if ( ! empty( $settings['sharingTitle'] ) ) {
			$sharing_title = bricks_render_dynamic_data( $settings['sharingTitle'], $post_id );
		} else {
			$sharing_title = get_the_title( $post_id );
		}

		$open_graph_meta_tags[] = '<meta property="og:title" content="' . esc_attr( trim( $sharing_title ) ) . '" />';

		// Description
		if ( ! empty( $settings['sharingDescription'] ) ) {
			$sharing_description = bricks_render_dynamic_data( $settings['sharingDescription'], $post_id );
		} else {
			$sharing_description = $post_id ? get_the_excerpt( $post_id ) : '';
		}

		if ( $sharing_description ) {
			$open_graph_meta_tags[] = '<meta property="og:description" content="' . esc_attr( trim( $sharing_description ) ) . '" />';
		}

		// Image
		$sharing_image     = ! empty( $settings['sharingImage'] ) ? $settings['sharingImage'] : false;
		$sharing_image_url = ! empty( $sharing_image['url'] ) ? $sharing_image['url'] : false;

		if ( $sharing_image ) {
			if ( ! empty( $sharing_image['useDynamicData'] ) ) {
				$images = Integrations\Dynamic_Data\Providers::render_tag( $sharing_image['useDynamicData'], $post_id, 'image' );

				if ( ! empty( $images[0] ) ) {
					$size              = ! empty( $sharing_image['size'] ) ? $sharing_image['size'] : BRICKS_DEFAULT_IMAGE_SIZE;
					$sharing_image_url = is_numeric( $images[0] ) ? wp_get_attachment_image_url( $images[0], $size ) : $images[0];
				}
			} else {
				$sharing_image_url = $sharing_image['url'];
			}
		} elseif ( has_post_thumbnail() ) {
			$sharing_image_url = get_the_post_thumbnail_url( get_the_ID(), 'large' );
		}

		if ( $sharing_image_url ) {
			$open_graph_meta_tags[] = '<meta property="og:image" content="' . esc_url( $sharing_image_url ) . '" />';
		}

		// Type
		if ( is_home() ) {
			$sharing_type = 'blog';
		} elseif ( get_post_type() === 'post' ) {
			$sharing_type = 'article';
		} else {
			$sharing_type = 'website';
		}

		$open_graph_meta_tags[] = '<meta property="og:type" content="' . $sharing_type . '" />';

		echo "\n" . join( "\n", $open_graph_meta_tags ) . "\n";
	}

	/**
	 * Add body header scripts
	 *
	 * NOTE: Do not add template JS (we only want to provide content)
	 *
	 * @since 1.0
	 */
	public function add_body_header_scripts() {
		$body_header_scripts = '';

		// Global settings scripts
		if ( isset( Database::$global_settings['customScriptsBodyHeader'] ) && ! empty( Database::$global_settings['customScriptsBodyHeader'] ) ) {
			$body_header_scripts .= stripslashes_deep( Database::$global_settings['customScriptsBodyHeader'] ) . PHP_EOL;
		}

		// Page settings scripts (@since 1.4)
		$body_header_scripts .= Assets::get_page_settings_scripts( 'customScriptsBodyHeader' );

		echo $body_header_scripts;
	}

	/**
	 * Add body footer scripts
	 *
	 * NOTE: Do not add template JS (only provide content)
	 *
	 * @since 1.0
	 */
	public function add_body_footer_scripts() {
		$body_footer_scripts = '';

		// Global settings scripts
		if ( isset( Database::$global_settings['customScriptsBodyFooter'] ) && ! empty( Database::$global_settings['customScriptsBodyFooter'] ) ) {
			$body_footer_scripts .= stripslashes_deep( Database::$global_settings['customScriptsBodyFooter'] ) . PHP_EOL;
		}

		// Page settings scripts (@since 1.4)
		$body_footer_scripts .= Assets::get_page_settings_scripts( 'customScriptsBodyFooter' );

		echo $body_footer_scripts;
	}

	/**
	 * Enqueue styles and scripts
	 */
	public function enqueue_scripts() {
		if ( is_admin_bar_showing() && current_user_can( 'manage_options' ) ) {
			// Load admin.min.css to add styles to the quick edit links
			wp_enqueue_style( 'bricks-admin', BRICKS_URL_ASSETS . 'css/admin.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/admin.min.css' ) );
		}

		// No Bricks content: Load default post content styles (post header & content)
		$bricks_data = Helpers::get_bricks_data( get_the_ID(), 'content' );
		if ( ! $bricks_data ) {
			if ( is_search() || get_the_content() || is_singular( 'post' ) ) {
				wp_enqueue_style( 'bricks-default-content', BRICKS_URL_ASSETS . 'css/frontend/content-default.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/frontend/content-default.min.css' ) );
			}
		}

		// Remove .mejs from attachment page
		if ( is_attachment() ) {
			wp_deregister_script( 'wp-mediaelement' );
			wp_deregister_style( 'wp-mediaelement' );
		}

		global $wp;

		$base_url = home_url( $wp->request );

		// Check if the URL contains a paging path (/page/X)
		if ( preg_match( '/\/page\/\d+\/?$/', $base_url, $matches ) ) {
			$paging_path = $matches[0];
			$base_url    = str_replace( $paging_path, '', $base_url );
		}

		$base_url = trailingslashit( $base_url );

		$current_language = '';
		$wpml_url_format  = '';
		$multilang_plugin = '';

		// Check if WPML is active and get the current language code (@since 1.9.9)
		if ( \Bricks\Integrations\Wpml\Wpml::is_wpml_active() ) {
			$current_language = \Bricks\Integrations\Wpml\Wpml::get_current_language();
			$wpml_url_format  = \Bricks\Integrations\Wpml\Wpml::get_url_format();
			$multilang_plugin = 'wpml';
		}

		// Check if Polylang is active and get the current language code (@since 1.9.9)
		elseif ( \Bricks\Integrations\Polylang\Polylang::$is_active ) {
			$current_language = \Bricks\Integrations\Polylang\Polylang::get_current_language();
			$multilang_plugin = 'polylang';
		}

		wp_localize_script(
			'bricks-scripts',
			'bricksData',
			[
				'debug'                       => isset( $_GET['debug'] ),
				'locale'                      => get_locale(),
				'ajaxUrl'                     => admin_url( 'admin-ajax.php' ),
				'restApiUrl'                  => Api::get_rest_api_url(),
				'nonce'                       => wp_create_nonce( 'bricks-nonce' ),
				'formNonce'                   => wp_create_nonce( 'bricks-nonce-form' ),
				'wpRestNonce'                 => wp_create_nonce( 'wp_rest' ),
				'postId'                      => Database::$page_data['preview_or_post_id'] ?? get_the_ID(),
				'recaptchaIds'                => [],
				'animatedTypingInstances'     => [], // To destroy and then re-init TypedJS instances
				'videoInstances'              => [], // To destroy and then re-init Plyr instances
				'splideInstances'             => [], // Necessary to destroy and then reinit SplideJS instances
				'tocbotInstances'             => [], // Necessary to destroy and then reinit Tocbot instances
				'swiperInstances'             => [], // To destroy and then re-init SwiperJS instances
				'queryLoopInstances'          => [], // To hold the query data for infinite scroll + load more
				'interactions'                => [], // Holds all the interactions
				'filterInstances'             => [], // Holds all the filter instances (@since 1.9.6)
				'isotopeInstances'            => [], // Holds all the isotope instances (@since 1.9.6)
				'activeFiltersCountInstances' => [], // Holds all the active filters count instances (@since 2.0)
				'googleMapInstances'          => [], // Holds all the Google Map instances (@since 2.0)
				'facebookAppId'               => isset( Database::$global_settings['facebookAppId'] ) ? Database::$global_settings['facebookAppId'] : false,
				'headerPosition'              => Database::$header_position,
				'offsetLazyLoad'              => ! empty( Database::$global_settings['offsetLazyLoad'] ) ? Database::$global_settings['offsetLazyLoad'] : 300,
				'baseUrl'                     => $base_url, // @since 1.9.6
				'useQueryFilter'              => Helpers::enabled_query_filters(), // @since 1.9.6
				'pageFilters'                 => Query_Filters::$page_filters, // @since 1.9.6
				'facebookAppId'               => Database::$global_settings['facebookAppId'] ?? false,
				'offsetLazyLoad'              => Database::$global_settings['offsetLazyLoad'] ?? 300,
				'headerPosition'              => Database::$header_position,
				'language'                    => $current_language,
				'wpmlUrlFormat'               => $wpml_url_format ?? '',
				'multilangPlugin'             => $multilang_plugin,
				'i18n'                        => I18n::get_frontend_i18n(),
				'selectedFilters'             => Query_Filters::$selected_filters, // @since 1.11
				'filterNiceNames'             => [], // @since 1.11
				'bricksGoogleMarkerScript'    => BRICKS_URL_ASSETS . 'js/libs/bricks-google-marker.min.js?v=' . BRICKS_VERSION, // @since 2.0
				'infoboxScript'               => BRICKS_URL_ASSETS . 'js/libs/infobox.min.js?v=' . BRICKS_VERSION, // @since 2.0
				'markerClustererScript'       => BRICKS_URL_ASSETS . 'js/libs/markerclusterer.min.js?v=' . BRICKS_VERSION, // @since 2.0
				'mainQueryId'                 => Database::$main_query_id, // @since 2.0
			]
		);
	}

	/**
	 * Enqueue inline CSS
	 *
	 * @since 1.8.2 using wp_footer instead of wp_enqueue_scripts to get all dynamic data styles & global classes
	 */
	public function enqueue_inline_css() {
		// Dummy style to load after woocommerce.min.css or woocommerce-layer.min.css
		wp_register_style( 'bricks-frontend-inline', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'bricks-frontend-inline' );

		// Bricks settings: AJAX Hide View Cart Button (@since 1.9)
		if ( WooCommerce::is_woocommerce_active() && Database::get_setting( 'woocommerceAjaxHideViewCart' ) ) {
			wp_add_inline_style( 'bricks-frontend-inline', '.added_to_cart.wc-forward {display: none}' );
		}

		$inline_css = '';

		// CSS loading method: External files
		// TODO NEXT: Generate external CSS file for global classes (#861mcc28z)
		if ( Database::get_setting( 'cssLoading' ) === 'file' ) {
			// Global classes need to be loaded inline
			$inline_css = Assets::$inline_css['global_classes'];
		}

		// CSS loading method: Inline styles (= default)
		else {
			$inline_css = Assets::generate_inline_css();
		}

		// Bricks settings: Smooth scroll CSS
		if ( Database::get_setting( 'smoothScroll' ) ) {
			$inline_css = "html {scroll-behavior: smooth}\n" . $inline_css;
		}

		if ( $inline_css ) {
			// Minify inline CSS (@since 1.9.9)
			$inline_css = Assets::minify_css( $inline_css );
			wp_add_inline_style( 'bricks-frontend-inline', $inline_css );
		}

		// Clear global classes inline CSS to avoid adding duplicate classes in enqueue_footer_inline_css function below
		Assets::$inline_css['global_classes'] = '';
	}

	/**
	 * Enqueue inline CSS in wp_footer: Global classes (Template element) & dynamic data
	 *
	 * @since 1.8.2
	 */
	public function enqueue_footer_inline_css() {
		/**
		 * Add global classes in wp_footer
		 *
		 * Clear in enqueue_inline_css function above to avoid adding duplicate classes.
		 *
		 * Generated in Template element and therefore not available in enqueue_inline_css function above.
		 *
		 * @since 1.8.2
		 */

		// Performance improvement (@since 1.9.8)
		Assets::generate_global_classes();

		$global_classes = Assets::$inline_css['global_classes'];

		if ( $global_classes ) {
			wp_register_style( 'bricks-global-classes-inline', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_style( 'bricks-global-classes-inline' );
			wp_add_inline_style( 'bricks-global-classes-inline', $global_classes );
		}

		// Get dynamic data CSS (for AJAX pagination only @since 1.8.2)
		$inline_css_dynamic_data = Assets::$inline_css_dynamic_data;

		if ( $inline_css_dynamic_data ) {
			// Replace for AJAX pagination (see frontend.js #bricks-dynamic-data)
			wp_register_style( 'bricks-dynamic-data', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_style( 'bricks-dynamic-data' );
			wp_add_inline_style( 'bricks-dynamic-data', $inline_css_dynamic_data );
		}
	}

	/**
	 * Get element content wrapper
	 */
	public static function get_content_wrapper( $settings, $fields, $post ) {
		$output = '';

		foreach ( $fields as $index => $field ) {
			$post_id = $post->ID;

			if ( ! empty( $field['dynamicData'] ) ) {
				$content = bricks_render_dynamic_data( $field['dynamicData'], $post_id );

				$content = do_shortcode( $content );

				if ( $content == '' ) {
					continue;
				}

				$tag = 'div';

				if ( ! empty( $field['tag'] ) ) {
					$tag = Helpers::sanitize_html_tag( $field['tag'], 'div' );
				}

				$field_id = isset( $field['id'] ) ? $field['id'] : $index;

				/**
				 * Posts element: $settings['imagLink'] set: add aria-hidden="true" & tabindex="-1" to <a> tag in $content to prevent screen readers for redundatn links
				 *
				 * @since 1.11
				 */
				if ( isset( $settings['imageLink'] ) && strpos( $content, '<a' ) === 0 && strpos( $content, get_the_permalink( $post_id ) ) !== -1 ) {
					$content = preg_replace( '/<a(.*?)>/', '<a$1 aria-hidden="true" tabindex="-1">', $content );
				}

				$output .= "<{$tag} class=\"dynamic\" data-field-id=\"{$field_id}\">{$content}</{$tag}>";
			}
		}

		return $output;
	}

	/**
	 * Render element recursively
	 *
	 * @param array $element
	 */
	public static function render_element( $element ) {
		$element_name = $element['name'] ?? '';

		if ( ! $element_name ) {
			return;
		}

		/**
		 * STEP: Get component 'children' and instance 'settings'
		 *
		 * @since 1.12
		 */
		$component_id       = $element['cid'] ?? false;
		$component_instance = $component_id ? Helpers::get_component_instance( $element ) : false;
		$component_elements = $component_instance['elements'] ?? [];

		// Add component children to Frontend::$elements to render them
		foreach ( $component_elements as $component_element ) {
			// Set 'parentComponent' on component child to get use .brxe-{} class name on component child
			$component_element['parentComponent'] = $component_id;
			$component_element['instanceId']      = $element['instanceId'] ?? $element['id']; // Use grandparent instanceId if available (nested component) (#86c51y7xy; @since 2.1)

			self::$elements[ $component_element['id'] ] = $component_element;

			// Popupate element with component instance settings and children
			if ( $element['cid'] === $component_element['id'] ) {
				// Get component instance settings
				if ( ! empty( $component_element['settings'] ) ) {
					$element['settings'] = $component_element['settings'];
				}

				// Get children from component
				if ( ! empty( $component_element['children'] ) ) {
					$element['children'] = $component_element['children'];
				}
			}
		}

		/**
		 * STEP: Get global element settings
		 *
		 * Skip if AJAX call is coming from builder via 'global_settings_checked'.
		 */
		$global_settings = ! isset( $element['global_settings_checked'] ) ? Helpers::get_global_element( $element, 'settings' ) : false;

		if ( is_array( $global_settings ) ) {
			$element['settings'] = $global_settings;
		}

		// Prevent endless loop (@since 2.0; #86c3qwrm6)
		if ( isset( $element['settings']['hasLoop'] ) && $element['settings']['hasLoop'] && isset( $element['looped'] ) && $element['looped'] ) {
			// This element already looped, the current 'hasLoop' might be coming from component properties
			unset( $element['settings']['hasLoop'] );
		}

		// Init element class (e.g.: new Bricks\Element_Alert( $element ))
		$element_class_name = Elements::$elements[ $element_name ]['class'] ?? $element_name;

		if ( class_exists( $element_class_name ) ) {
			// Assign most-specific theme settings to element
			$theme_style_ids = array_keys( Theme_Styles::$settings_by_id );
			$theme_style_id  = end( $theme_style_ids );

			if ( $theme_style_id ) {
				$element['themeStyleSettings'] = Theme_Styles::$settings_by_id[ $theme_style_id ];
			}

			$element_instance = new $element_class_name( $element );
			$element_instance->load();

			// Enqueue element styles/scripts & render element
			ob_start();
			$element_instance->init();
			$element_html = ob_get_clean();

			// @see https://academy.bricksbuilder.io/article/filter-bricks-frontend-render_element/ (@since 2.0)
			return apply_filters( 'bricks/frontend/render_element', $element_html, $element_instance );
		}

		// Element doesn't exist: Show message to user with builder access
		if ( Capabilities::current_user_can_use_builder() ) {
			$no_element_text = esc_html__( 'PHP class does not exist', 'bricks' );

			// Check: Element is disabled via element manager (@since 2.0)
			if ( isset( Elements::$manager[ $element_class_name ]['status'] ) && Elements::$manager[ $element_class_name ]['status'] === 'disabled' ) {
				$no_element_text = esc_html__( 'Element has been disabled globally.', 'bricks' ) . ' (<a href="' . admin_url( 'admin.php?page=bricks-elements' ) . '" target="_blank">Bricks > ' . esc_html__( 'Elements', 'bricks' ) . '</a>)';
			}

			return sprintf( '<div class="bricks-element-placeholder no-php-class">' . $element_class_name . ': %s</div>', $no_element_text );
		}
	}

	/**
	 * Render element 'children' (= nestable element)
	 *
	 * @param array  $element_instance Instance of the element.
	 * @param string $tag Tag name.
	 * @param array  $extra_attributes Extra attributes.
	 *
	 * @since 1.5
	 */
	public static function render_children( $element_instance = null, $tag = 'div', $extra_attributes = [] ) {
		$element = $element_instance->element;

		// Get componentInstance (builder) OR from database (frontend)
		$component_instance = $element['componentInstance'] ?? Helpers::get_component_instance( $element );

		// FRONTEND: Return children HTML
		$children = ! empty( $element['children'] ) && is_array( $element['children'] ) ? $element['children'] : [];
		$output   = '';

		// Get component 'children' (ids) and add components to Frontend::$elements array (@since 1.12)
		$component_children = ! empty( $component_instance['children'] ) && is_array( $component_instance['children'] ) ? $component_instance['children'] : [];
		if ( $component_children ) {
			$children           = $component_children;
			$component_elements = $component_instance['elements'] ?? [];
			foreach ( $component_elements as $component_child ) {
				// Set 'parentComponent' on component child to get use .brxe-{} class name on component child (see: set_root_attributes)
				$component_child['parentComponent']       = $component_instance['id'];
				self::$elements[ $component_child['id'] ] = $component_child;
			}
		}

		foreach ( $children as $child_id ) {
			$child = self::$elements[ $child_id ] ?? false;

			if ( $child ) {
				// Add the extra attributes to child '_attributes'
				if ( is_array( $extra_attributes ) && ! empty( $extra_attributes ) ) {
					foreach ( $extra_attributes as $attr_name => $attr_value ) {
						$child['settings']['_attributes'][] = [
							'name'  => $attr_name,
							'value' => $attr_value,
						];
					}
				}

				$output .= self::render_element( $child ); // Recursive
			}
		}

		/**
		 * BUILDER: Replace children placeholder node with Vue components (in BricksElementPHP.vue)
		 *
		 * If not static builder area && not frontend && not a loop ghost node (loop index: 1, 2, 3, etc.)
		 *
		 * @since 1.7.1
		 */
		if ( ! isset( $element['staticArea'] ) && ! $element_instance->is_frontend && ! Query::get_loop_index() ) {
			return '<div class="brx-nestable-children-placeholder"></div>';
		}

		return $output;
	}

	/**
	 * Return rendered elements (header/content/footer)
	 *
	 * @param array  $elements Array of Bricks elements.
	 * @param string $area     header/content/footer.
	 *
	 * @since 1.2
	 */
	public static function render_data( $elements = [], $area = 'content' ) {
		if ( ! is_array( $elements ) ) {
			return;
		}

		if ( ! count( $elements ) ) {
			return;
		}

		// NOTE: Undocumented. Useful to remove plugin actions/filters (@since 1.5.4)
		do_action( 'bricks/frontend/before_render_data', $elements, $area );

		self::$elements = [];
		self::$area     = $area;

		// Prepare flat list of elements for recursive calls
		foreach ( $elements as $element ) {
			if ( isset( $element['id'] ) ) {
				self::$elements[ $element['id'] ] = $element;

				/**
				 * Store live search results selectors
				 *
				 * To set element root data attribute 'data-brx-ls-wrapper' to hide live search wrapper on page load (@see container.php:902)
				 *
				 * @since 1.9.6
				 */
				if (
					Helpers::enabled_query_filters() &&
					! empty( $element['settings']['hasLoop'] ) &&
					! empty( $element['settings']['query']['is_live_search'] ) &&
					! empty( $element['settings']['query']['is_live_search_wrapper_selector'] )
				) {
					self::$live_search_wrapper_selectors[ $element['id'] ] = $element['settings']['query']['is_live_search_wrapper_selector'];
				}
			}
		}

		// Generate elements HTML
		$content = '';

		foreach ( $elements as $element ) {
			/**
			 * Skip element: Component with this 'cid' doesn't exist in database
			 *
			 * @since 1.12
			 */
			if ( ! empty( $element['cid'] ) && ! Helpers::get_component_by_cid( $element['cid'] ) ) {
				continue;
			}

			if ( ! empty( $element['parent'] ) ) {
				continue;
			}

			$content .= self::render_element( $element );
		}

		// NOTE: Undocumented. Useful to re-add plugin actions/filters (@since 1.5.4)
		do_action( 'bricks/frontend/after_render_data', $elements, $area );

		// Check: Are we looping a template element
		$looping_query_id = Query::is_any_looping();
		$loop_object_type = Query::get_loop_object_type( $looping_query_id );

		$post_id = $loop_object_type === 'post' ? get_the_ID() : Database::$page_data['preview_or_post_id'];

		$post = get_post( $post_id );

		// Filter Bricks content (incl. parsing of dynamic data)
		// https://academy.bricksbuilder.io/article/filter-bricks-frontend-render_data/
		$content = apply_filters( 'bricks/frontend/render_data', $content, $post, $area );

		self::$elements = [];

		return $content;
	}

	/**
	 * One Page Navigation Wrapper
	 */
	public function one_page_navigation_wrapper() {
		if ( isset( Database::$page_settings['onePageNavigation'] ) ) {
			echo '<ul id="bricks-one-page-navigation"></ul>';
		}
	}

	/**
	 * Lazy load via img data attribute
	 *
	 * https://developer.wordpress.org/reference/hooks/wp_get_attachment_image_attributes/
	 *
	 * @param array        $attr Image attributes.
	 * @param object       $attachment WP_POST object of image.
	 * @param string|array $size Requested image size.
	 *
	 * @return array
	 */
	public function set_image_attributes( $attr, $attachment, $size ) {
		// Disable lazy load for AJAX (builder & frontend) or REST API calls (builder) to ensure assets are always rendered properly
		// REST_REQUEST constant discussion: https://github.com/WP-API/WP-API/issues/926
		if ( bricks_is_ajax_call() || bricks_is_rest_call() ) {
			return $attr;
		}

		// Return: WC api endpoint might use image for email content (@since 2.0)
		if ( WooCommerce::is_woocommerce_active() && WooCommerce::is_wc_api_endpoint() ) {
			return $attr;
		}

		// Disable lazy load inside TranslatePress iframe (@since 1.6)
		if ( function_exists( 'trp_is_translation_editor' ) && trp_is_translation_editor( 'preview' ) ) {
			return $attr;
		}

		// Disable images lazy loading in the Product Gallery
		if ( isset( $attr['_brx_disable_lazy_loading'] ) ) {
			unset( $attr['_brx_disable_lazy_loading'] );

			return $attr;
		}

		// Check: Lazy load disabled
		if ( isset( Database::$global_settings['disableLazyLoad'] ) || isset( Database::$page_settings['disableLazyLoad'] ) ) {
			return $attr;
		}

		// Return: To disable lazy loading for all images with attribute loading="eager" (@since 1.6)
		if ( isset( $attr['loading'] ) && $attr['loading'] === 'eager' ) {
			return $attr;
		}

		$attr['class']    = $attr['class'] . ' bricks-lazy-hidden';
		$attr['data-src'] = $attr['src'];

		// Lazy load placeholder: URL-encoded SVG with image dimensions
		$attr['data-type'] = gettype( $size );

		if ( gettype( $size ) === 'string' ) {
			$image_src    = wp_get_attachment_image_src( $attachment->ID, $size );
			$image_width  = $image_src[1];
			$image_height = $image_src[2];
		} else {
			$image_width  = $size[0];
			$image_height = $size[1];
		}

		// Set SVG placeholder to preserve image aspect ratio to prevent browser content reflow when lazy loading the image
		// Encode spaces and use singlequotes instead of double quotes to avoid W3 "space" validator error (@since 1.5.1)
		$attr['src'] = "data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%20$image_width%20$image_height'%3E%3C/svg%3E";

		// Add data-sizes attribute for lazy load to avoid "sizes" W3 validator error (@since 1.5.1)
		if ( isset( $attr['sizes'] ) ) {
			$attr['data-sizes'] = $attr['sizes'];
			$attr['sizes']      = '';
			unset( $attr['sizes'] );
		}

		if ( isset( $attr['srcset'] ) ) {
			$attr['data-srcset'] = $attr['srcset'];
			$attr['srcset']      = '';
			unset( $attr['srcset'] );
		}

		return $attr;
	}

	/**
	 * Template frontend view: Permanently redirect users without Bricks editing permission to homepage
	 *
	 * Exclude template pages in search engine results.
	 *
	 * Overwrite via 'publicTemplates' setting
	 *
	 * @since 1.9.4: Exclude redirect if maintenance mode activated (to prevent endless redirect)
	 */
	public function template_redirect() {
		if (
			is_singular( BRICKS_DB_TEMPLATE_SLUG ) &&
			! Capabilities::current_user_can_use_builder() &&
			! isset( Database::$global_settings['publicTemplates'] ) &&
			! Maintenance::get_mode() ) {
			wp_safe_redirect( site_url(), 301 );
			die;
		}
	}

	public function add_skip_link() {
		if ( Database::get_setting( 'disableSkipLinks', false ) ) {
			return;
		}

		$template_footer_id = Database::$active_templates['footer'];
		?>
		<a class="skip-link" href="#brx-content"><?php esc_html_e( 'Skip to main content', 'bricks' ); ?></a>

		<?php if ( ! empty( $template_footer_id ) && ! Database::is_template_disabled( 'footer' ) ) { ?>
			<a class="skip-link" href="#brx-footer"><?php esc_html_e( 'Skip to footer', 'bricks' ); ?></a>
			<?php
		}
	}

	/**
	 * Remove WP hooks on frontend
	 *
	 * @since 1.5.5
	 */
	public function remove_wp_hooks() {
		if ( is_attachment() && ! empty( Database::$active_templates['content'] ) ) {
			// Post type 'attachment' template: This filter prepends/adds the attachment to all Bricks elements that use the_content (@since 1.5.5)
			remove_filter( 'the_content', 'prepend_attachment' );
		}
	}

	/**
	 * Render header
	 *
	 * Bricks data exists & header is not disabled on this page.
	 *
	 * @since 1.3.2
	 */
	public function render_header() {
		$header_data = Database::get_template_data( 'header' );

		// Return: No header data exists
		if ( ! is_array( $header_data ) ) {
			return;
		}

		$settings = Helpers::get_template_settings( Database::$active_templates['header'] );
		$classes  = [];

		// Sticky header (top, not left or right)
		if ( ! isset( $settings['headerPosition'] ) && isset( $settings['headerSticky'] ) ) {
			$classes[] = 'brx-sticky';

			if ( isset( $settings['headerStickyOnScroll'] ) ) {
				$classes[] = 'on-scroll';
			}
		}

		$attributes = [
			'id' => 'brx-header',
		];

		if ( count( $classes ) ) {
			$attributes['class'] = $classes;
		}

		if ( ! empty( $settings['headerStickySlideUpAfter'] ) ) {
			$attributes['data-slide-up-after'] = intval( $settings['headerStickySlideUpAfter'] );
		}

		// https://academy.bricksbuilder.io/article/filter-bricks-header-attributes/ (@since 1.5)
		$attributes = apply_filters( 'bricks/header/attributes', $attributes );

		$attributes = Helpers::stringify_html_attributes( $attributes );

		$header_html = "<header {$attributes}>" . self::render_data( $header_data, 'header' ) . '</header>';

		// NOTE: Undocumented
		echo apply_filters( 'bricks/render_header', $header_html );
	}

	/**
	 * Render Bricks content + surrounding 'main' tag
	 *
	 * For pages rendered with Bricks
	 *
	 * To allow customizing the 'main' tag attributes
	 *
	 * @since 1.5
	 */
	public static function render_content( $bricks_data = [], $attributes = [], $html_after_begin = '', $html_before_end = '', $tag = 'main' ) {
		// Merge custom attributes with default attributes ('id')
		if ( is_array( $attributes ) ) {
			$attributes = array_merge( [ 'id' => 'brx-content' ], $attributes );
		}

		// Return: Popup template preview
		if ( Templates::get_template_type() === 'popup' ) {
			return;
		}

		// https://academy.bricksbuilder.io/article/filter-bricks-content-attributes/ (@since 1.5)
		$attributes = apply_filters( 'bricks/content/attributes', $attributes );

		$attributes = Helpers::stringify_html_attributes( $attributes );

		// https://academy.bricksbuilder.io/article/filter-bricks-content-tag/ (@since 1.11.1)
		$content_tag = apply_filters( 'bricks/content/tag', $tag );

		// Sanitize custom HTML tag
		$tag = Helpers::sanitize_html_tag( $content_tag, $tag );

		if ( $tag ) {
			echo "<{$tag} {$attributes}>";
		}

		// https://academy.bricksbuilder.io/article/filter-bricks-content-html_after_begin/
		$html_after_begin = apply_filters( 'bricks/content/html_after_begin', $html_after_begin, $bricks_data, $attributes, $tag );

		if ( $html_after_begin ) {
			echo $html_after_begin;
		}

		if ( is_array( $bricks_data ) && count( $bricks_data ) ) {
			echo self::render_data( $bricks_data );
		}

		// https://academy.bricksbuilder.io/article/filter-bricks-content-html_before_end/
		$html_before_end = apply_filters( 'bricks/content/html_before_end', $html_before_end, $bricks_data, $attributes, $tag );

		if ( $html_before_end ) {
			echo $html_before_end;
		}

		if ( $tag ) {
			echo "</{$tag}>";
		}
	}

	/**
	 * Render footer
	 *
	 * To follow already available 'render_header' function syntax
	 *
	 * @since 1.5
	 */
	public function render_footer() {
		$footer_data = Database::get_template_data( 'footer' );

		if ( ! is_array( $footer_data ) ) {
			return;
		}

		// https://academy.bricksbuilder.io/article/filter-bricks-footer-attributes/ (@since 1.5)
		$attributes = apply_filters(
			'bricks/footer/attributes',
			[
				'id' => 'brx-footer',
			]
		);

		$attributes = Helpers::stringify_html_attributes( $attributes );

		$footer_html = "<footer {$attributes}>" . self::render_data( $footer_data, 'footer' ) . '</footer>';

		// NOTE: Undocumented
		echo apply_filters( 'bricks/render_footer', $footer_html );
	}

	/**
	 * Remove current menu item classes from anchor links
	 *
	 * @since 1.11
	 */
	public function adjust_menu_item_classes( $items, $args ) {
		$ancestor_ids = [];

		// Function to get ancestor IDs
		$get_ancestor = function( $item ) {
			$looped_ids = [];
			$parent_ids = [];
			$current_id = absint( $item->db_id );

			while ( ! in_array( $current_id, $looped_ids ) ) {
				$looped_ids[] = $current_id;
				$parent_id    = get_post_meta( $current_id, '_menu_item_menu_item_parent', true );

				// skip if no parent
				if ( empty( $parent_id ) ) {
					break;
				}

				$current_id   = $parent_id;
				$parent_ids[] = absint( $parent_id );
			}

			return $parent_ids;
		};

		// Detect anchor links with current-menu-item class
		foreach ( $items as $item ) {
			// If the item link contains an anchor and contains current-menu-item class or current-menu-ancestor class
			if ( strpos( $item->url, '#' ) !== false && in_array( 'current-menu-item', $item->classes ) ) {
				// Remove the current-menu-item class
				$item->classes = array_diff( $item->classes, [ 'current-menu-item' ] );
				// Add the ancestor IDs to the array
				$ancestor_ids = array_merge( $ancestor_ids, $get_ancestor( $item ) );
			}
		}

		// Loop through the items again to remove the current-menu-ancestor class from the parent items
		if ( ! empty( $ancestor_ids ) ) {
			foreach ( $items as $item ) {
				// If the item is a parent of an anchor link with current-menu-item class
				if ( in_array( absint( $item->db_id ), $ancestor_ids ) ) {
					// Remove the current-menu-ancestor, current-menu-parent classes
					$item->classes = array_diff( $item->classes, [ 'current-menu-ancestor', 'current-menu-parent' ] );
				}
			}
		}

		return $items;
	}

		/**
		 * If user lands on an activation page, check if there is a valid activation key,
		 * and if so, activate the user account.
		 *
		 * @since 2.1
		 */

	public function activate_user_account() {

		$page_success = Database::get_setting( 'userActivationLinkSuccessPage', null );
		$page_failure = Database::get_setting( 'userActivationLinkFailurePage', null );

		// If no activation pages are set, return
		if ( ! $page_success || ! $page_failure ) {
			return;
		}

		// Check if it's an activation page
		if ( ! is_page( $page_success ) ) {
			return;
		}

		/**
		 * If no activation key or user ID is set, just return and process the page normally
		 *
		 * Example: User lands on the activation page without an activation link.
		 *
		 * @since 2.1.3
		 */
		if ( ! isset( $_GET['activation_key'] ) || ! isset( $_GET['user_id'] ) ) {
			return;
		}

		// Get user ID and activation key
		$user_id        = intval( $_GET['user_id'] );
		$activation_key = sanitize_text_field( $_GET['activation_key'] );

		// Check: If user ID or activation key is empty, redirect to failure page
		if ( empty( $user_id ) || empty( $activation_key ) ) {
			wp_safe_redirect( get_permalink( $page_failure ) );
			exit;
		}

		// If user is already activated, skip activation
		if ( get_user_meta( $user_id, 'bricks_user_activation_status', true ) === 'active' ) {
			return;
		}

		// Check if the activation key is valid
		$activation_key_valid = get_user_meta( $user_id, 'bricks_user_activation_key', true ) === $activation_key;

		// Activate user account
		if ( $activation_key_valid ) {
			delete_user_meta( $user_id, 'bricks_user_activation_key' );
			update_user_meta( $user_id, 'bricks_user_activation_status', 'active' );

			// Auto login user, if enabled
			if ( Database::get_setting( 'userActivationAutoLogin', false ) ) {
				wp_set_current_user( $user_id );
				wp_set_auth_cookie( $user_id, false, is_ssl() );
			}

		}

		// Else: redirect to activation error page
		else {
			wp_safe_redirect( get_permalink( $page_failure ) );
			exit;
		}
	}
}
