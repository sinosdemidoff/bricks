<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Api {

	const API_NAMESPACE             = 'bricks/v1';
	public static $query_element_id = null; // Holds the current query element ID during a REST API request (@since 2.1)

	/**
	 * WordPress REST API help docs:
	 *
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
	 * https://developer.wordpress.org/rest-api/extending-the-rest-api/modifying-responses/
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'rest_api_init_custom_endpoints' ] );
	}

	/**
	 * Custom REST API endpoints
	 */
	public function rest_api_init_custom_endpoints() {
		// Server-side render (SSR) for builder elements via window.fetch API requests
		register_rest_route(
			self::API_NAMESPACE,
			'render_element',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'render_element' ],
				'permission_callback' => [ $this, 'render_element_permissions_check' ],
			]
		);

		// Get all templates data (templates, authors, bundles, tags etc.)
		register_rest_route(
			self::API_NAMESPACE,
			'/get-templates-data/',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_templates_data' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/get-templates/',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_templates' ],
				'permission_callback' => '__return_true',
			]
		);

		// Get individual template by ID
		register_rest_route(
			self::API_NAMESPACE,
			'/get-templates/(?P<args>[a-zA-Z0-9-=&]+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_templates' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'args' => [
						'required' => true
					],
				],
			]
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/get-template-authors/',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_template_authors' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/get-template-bundles/',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_template_bundles' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/get-template-tags/',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_template_tags' ],
				'permission_callback' => '__return_true',
			]
		);

		/**
		 * Query loop: Infinite scroll
		 *
		 * @since 1.5
		 */
		register_rest_route(
			self::API_NAMESPACE,
			'load_query_page',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'render_query_page' ],
				'permission_callback' => [ $this, 'render_query_page_permissions_check' ],
			]
		);

		/**
		 * Ajax Popup
		 *
		 * @since 1.9.4
		 */
		register_rest_route(
			self::API_NAMESPACE,
			'load_popup_content',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'render_popup_content' ],
				'permission_callback' => [ $this, 'render_popup_content_permissions_check' ],
			]
		);

		/**
		 * Query loop: Query result
		 *
		 * For load more, AJAX pagination, sort, filter, live search.
		 *
		 * @since 1.9.6
		 */
		register_rest_route(
			self::API_NAMESPACE,
			'query_result',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'render_query_result' ],
				'permission_callback' => [ $this, 'render_query_result_permissions_check' ],
			]
		);

		/**
		 * Get global classes categorized by their usage across the site
		 *
		 * @since 1.12
		 */
		register_rest_route(
			self::API_NAMESPACE,
			'/get-global-classes-site-usage',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_global_classes_site_usage' ],
				'permission_callback' => [ $this, 'get_global_classes_site_usage_permissions_check' ],
			]
		);
	}

	/**
	 * Return element HTML retrieved via Fetch API
	 *
	 * @since 1.5
	 */
	public static function render_element( $request ) {
		$data             = $request->get_json_params();
		$post_id          = $data['postId'] ?? false;
		$element          = $data['element'] ?? [];
		$element_name     = $element['name'] ?? '';
		$element_settings = $element['settings'] ?? '';

		if ( $post_id ) {
			Database::set_page_data( $post_id );

			// Set context in API endpoint (@since 1.12.2)
			global $wp_query;
			global $post;
			$post = get_post( $post_id );
			setup_postdata( $post );

			/**
			 * Set necessary global variables so we can use get_queried_object(), get_the_ID() etc.
			 */
			if ( $post && ! is_wp_error( $post ) ) {
				$wp_query->queried_object    = $post;
				$wp_query->queried_object_id = $post->ID;
				$wp_query->is_singular       = true;
				$wp_query->post_type         = $post->post_type;

				if ( is_page( $post->ID ) ) {
					$wp_query->is_page = true;
				} else {
					$wp_query->is_single = true;
				}
			}
		}

		// Include WooCommerce frontend classes and hooks to enable the WooCommerce element preview inside the builder (since 1.5)
		if ( Woocommerce::$is_active ) {
			WC()->frontend_includes();

			Woocommerce_Helpers::maybe_load_cart();
		}

		// Get rendered element HTML
		$html = Ajax::render_element( $data );

		// Prepare response
		$response = [ 'html' => $html ];

		// Template element (send template elements to run template element scripts on the canvas)
		if ( $element_name === 'template' ) {
			$template_id = $element_settings['template'] ?? false;
			if ( $template_id ) {
				$additional_data = Element_Template::get_builder_call_additional_data( $template_id );
				$response        = array_merge( $response, $additional_data );
			}
		}

		return [ 'data' => $response ];
	}

	/**
	 * Element render permission check
	 *
	 * @since 1.5
	 */
	public function render_element_permissions_check( $request ) {
		$data = $request->get_json_params();

		if ( empty( $data['postId'] ) || empty( $data['element'] ) || empty( $data['nonce'] ) ) {
			return new \WP_Error( 'bricks_api_missing', __( 'Missing parameters' ), [ 'status' => 400 ] );
		}

		// Return: Current user can not access builder
		if ( ! Capabilities::current_user_can_use_builder() ) {
			return new \WP_Error( 'rest_current_user_can_not_use_builder', __( 'Permission error' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Return all templates data in one call (templates, authors, bundles, tags, theme style)
	 *
	 * @param  array $data
	 * @return array
	 *
	 * @since 1.0
	 */
	public function get_templates_data( $data ) {
		$templates_args = $data['args'] ?? [];

		// STEP: Get templates metadata or all data
		$templates = $this->get_templates( $templates_args );

		// STEP: Check for template error
		if ( isset( $templates['error'] ) ) {
			return $templates;
		}

		$theme_styles     = get_option( BRICKS_DB_THEME_STYLES, false );
		$global_classes   = get_option( BRICKS_DB_GLOBAL_CLASSES, [] );
		$global_variables = get_option( BRICKS_DB_GLOBAL_VARIABLES, [] );
		$color_palette    = get_option( BRICKS_DB_COLOR_PALETTE, [] ); // @since 1.12

		// STEP: Add all active theme styles to template data to import when inserting a template
		foreach ( $templates as $index => $template ) {
			/**
			 * Provide only most-specific theme style for template import
			 *
			 * @since 2.0: Provide all active theme styles for template import (regardless if 'themeStylesLoadingMethod' Bricks setting is enabled on the site)
			 */
			$theme_style_ids = Theme_Styles::set_active_style( $template['id'], true );

			if ( is_array( $theme_style_ids ) && count( $theme_style_ids ) ) {
				foreach ( $theme_style_ids as $theme_style_id ) {
					// Get theme style by ID
					$theme_style = $theme_styles[ $theme_style_id ] ?? false;

					// Skip if theme style not found
					if ( ! $theme_style ) {
						continue;
					}

					// Remove theme style conditions
					unset( $theme_style['settings']['conditions'] );

					if ( ! isset( $templates[ $index ]['themeStyles'] ) ) {
						$templates[ $index ]['themeStyles'] = [];
					}

					/**
					 * NOTE: @pre 2.0 we passed a single 'themeStyle'
					 *
					 * @since 2.0 we pass an array of all active 'themeStyles'
					 */
					$theme_style['id']                    = $theme_style_id;
					$templates[ $index ]['themeStyles'][] = $theme_style;
				}
			}

			/**
			 * Loop over all template elements to add 'global_classes' data to remote template data
			 *
			 * To import global classes when importing remote template locally.
			 *
			 * @since 1.5
			 */
			if ( count( $global_classes ) ) {
				$template_classes  = [];
				$template_elements = [];

				if ( ! empty( $template['content'] ) && is_array( $template['content'] ) ) {
					$template_elements = $template['content'];
				} elseif ( ! empty( $template['header'] ) && is_array( $template['header'] ) ) {
					$template_elements = $template['header'];
				} elseif ( ! empty( $template['footer'] ) && is_array( $template['footer'] ) ) {
					$template_elements = $template['footer'];
				}

				foreach ( $template_elements as $element ) {
					if ( ! empty( $element['settings']['_cssGlobalClasses'] ) ) {
						$template_classes = array_unique( array_merge( $template_classes, $element['settings']['_cssGlobalClasses'] ) );
					}
				}

				if ( count( $template_classes ) ) {
					$templates[ $index ]['global_classes'] = [];

					foreach ( $template_classes as $template_class ) {
						foreach ( $global_classes as $global_class ) {
							if ( $global_class['id'] === $template_class ) {
								// Add category metadata to individual class before adding to template (@since 1.12.2)
								$global_class                            = Helpers::add_category_metadata_to_classes( [ $global_class ] )[0];
								$templates[ $index ]['global_classes'][] = $global_class;
							}
						}
					}
				}
			}
		}

		// Return all templates data
		$templates_data = [
			'timestamp'       => current_time( 'timestamp' ),
			'date'            => current_time( get_option( 'date_format' ) . ' (' . get_option( 'time_format' ) . ')' ),
			'templates'       => $templates,
			'authors'         => Templates::get_template_authors(),
			'bundles'         => Templates::get_template_bundles(),
			'tags'            => Templates::get_template_tags(),
			'globalVariables' => $global_variables, // @since 1.9.8
			'colorPalette'    => $color_palette, // To allowing importing all color palettes found in the inserted template (@since 1.12)
			'get'             => $_GET, // Pass URL params to perform additional checks (e.g. 'password' as license key, etc.)
		];

		$templates_data = apply_filters( 'bricks/api/get_templates_data', $templates_data );

		// Remove 'get' data (to avoid storing it in database)
		unset( $templates_data['get'] );

		return $templates_data;
	}

	/**
	 * Return templates array OR specific template by array index
	 *
	 * @since 1.0
	 *
	 * @param  array $data
	 *
	 * @return array
	 */
	public function get_templates( $data ) {
		$parameters         = $_GET;
		$templates_response = Templates::can_get_templates( $parameters );

		// Check for templates error (no site/password etc. provided)
		if ( isset( $templates_response['error'] ) ) {
			return $templates_response;
		}

		$templates_args = $data['args'] ?? [];

		// Add remote_request flag (@since 1.12.2)
		$templates_args['remote_request'] = true;

		// Merge $parameters with $templates_response args
		$templates_args = array_merge( $templates_args, $templates_response );

		$templates = Templates::get_templates( $templates_args );

		return $templates;
	}

	/**
	 * Get API endpoint
	 *
	 * Use /api to get Bricks Community Templates
	 * Default: Use /wp-json (= default WP REST API prefix)
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $base_url Base URL.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function get_endpoint( $endpoint = 'get-templates', $base_url = BRICKS_REMOTE_URL ) {
		$api_prefix = $base_url === BRICKS_REMOTE_URL ? 'api' : rest_get_url_prefix();

		return trailingslashit( $base_url ) . trailingslashit( $api_prefix ) . trailingslashit( self::API_NAMESPACE ) . $endpoint;
	}

	/**
	 * Get the Bricks REST API url
	 *
	 * @since 1.5
	 *
	 * @return string
	 */
	public static function get_rest_api_url() {
		return trailingslashit( get_rest_url( null, '/' . self::API_NAMESPACE ) );
	}

	/**
	 * Check if current endpoint is Bricks API endpoint
	 *
	 * @param string $endpoint E.g. 'render_element' or 'load_query_page' for our infinite scroll.
	 *
	 * @since 1.8.1
	 *
	 * @return bool
	 */
	public static function is_current_endpoint( $endpoint ) {
		if ( ! $endpoint ) {
			return false;
		}

		return self::is_bricks_rest_request( $endpoint );
	}

	/**
	 * Check if current request is a Bricks REST API request
	 *
	 * Works reliably during init hook before REST API is fully initialized.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */
	public static function is_bricks_rest_request( $endpoint = '' ) {
		// Build the namespace pattern
		$namespace_pattern = '/' . self::API_NAMESPACE . '/';
		$endpoint_pattern  = $endpoint ? $namespace_pattern . $endpoint : $namespace_pattern;

		// Method 1: Check if REST_REQUEST constant is defined and check for Bricks namespace
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			global $wp;
			$current_rest_route = isset( $wp->query_vars['rest_route'] ) ? $wp->query_vars['rest_route'] : '';

			if ( $current_rest_route ) {
				if ( $endpoint ) {
					return $current_rest_route === $endpoint_pattern;
				} else {
					return strpos( $current_rest_route, $namespace_pattern ) === 0;
				}
			}
		}

		// Method 2: Check REQUEST_URI for Bricks namespace (works during init hook)
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = $_SERVER['REQUEST_URI'];
			$rest_prefix = rest_get_url_prefix(); // Usually 'wp-json'

			// Check for pretty permalinks pattern
			$full_pattern = '/' . $rest_prefix . $endpoint_pattern;
			if ( strpos( $request_uri, $full_pattern ) !== false ) {
				return true;
			}

			// Check for non-pretty permalinks pattern: ?rest_route=
			if ( isset( $_SERVER['QUERY_STRING'] ) && $_SERVER['QUERY_STRING'] ) {
				parse_str( $_SERVER['QUERY_STRING'], $query_params );
				if ( isset( $query_params['rest_route'] ) ) {
					$rest_route = $query_params['rest_route'];
					if ( $endpoint ) {
						return $rest_route === $endpoint_pattern;
					} else {
						return strpos( $rest_route, $namespace_pattern ) === 0;
					}
				}
			}
		}

		// Method 3: Check global $wp for Bricks REST route (fallback)
		global $wp;
		if ( isset( $wp->query_vars['rest_route'] ) ) {
			$current_rest_route = $wp->query_vars['rest_route'];
			if ( $endpoint ) {
				return $current_rest_route === $endpoint_pattern;
			} else {
				return strpos( $current_rest_route, $namespace_pattern ) === 0;
			}
		}

		return false;
	}

	/**
	 * Get template authors
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_template_authors() {
		return Templates::get_template_authors();
	}

	/**
	 * Get template bundles
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_template_bundles() {
		return Templates::get_template_bundles();
	}

	/**
	 * Get template tags
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_template_tags() {
		return Templates::get_template_tags();
	}

	/**
	 * Get news feed
	 *
	 * NOTE: Not in use.
	 *
	 * @return array
	 */
	public static function get_feed() {
		$remote_base_url = BRICKS_REMOTE_URL;
		$feed_url        = trailingslashit( $remote_base_url ) . trailingslashit( rest_get_url_prefix() ) . trailingslashit( self::API_NAMESPACE ) . trailingslashit( 'feed' );

		$response = Helpers::remote_get( $feed_url );

		if ( is_wp_error( $response ) ) {
			return [];
		} else {
			return json_decode( wp_remote_retrieve_body( $response ), true );
		}
	}

	/**
	 * Query loop: Infinite scroll permissions callback
	 *
	 * @since 1.5
	 */
	public function render_query_page_permissions_check( $request ) {
		$data = $request->get_json_params();

		if ( empty( $data['queryElementId'] ) || empty( $data['nonce'] ) || empty( $data['page'] ) ) {
			return new \WP_Error( 'bricks_api_missing', __( 'Missing parameters' ), [ 'status' => 400 ] );
		}

		$result = wp_verify_nonce( $data['nonce'], 'bricks-nonce' );

		if ( $result === false ) {
			return new \WP_Error( 'rest_cookie_invalid_nonce', __( 'Bricks cookie check failed' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Query loop: Infinite scroll callback
	 * Or AJAX pagination (@since 1.12.2)
	 *
	 * @since 1.5
	 */
	public function render_query_page( $request ) {
		$request_data = $request->get_json_params();

		$query_element_id = $ori_query_element_id = $request_data['queryElementId'];
		$post_id          = $request_data['postId'];
		$page             = $request_data['page'];
		$query_vars       = json_decode( $request_data['queryVars'], true );
		$language         = isset( $request_data['lang'] ) ? sanitize_key( $request_data['lang'] ) : false;
		$pagination_id    = isset( $request_data['paginationId'] ) ? sanitize_key( $request_data['paginationId'] ) : false;
		$base_url         = $request_data['baseUrl'] ?? '';
		$main_query_id    = isset( $request_data['mainQueryId'] ) ? sanitize_text_field( $request_data['mainQueryId'] ) : false;

		// Set current language (@since 1.9.9)
		if ( $language ) {
			Database::$page_data['language'] = $language;
		}

		// Set main query ID (@since 2.0)
		if ( $main_query_id ) {
			Database::$main_query_id = $main_query_id;
		}

		// Set post_id for use in prepare_query_vars_from_settings
		Database::$page_data['preview_or_post_id'] = $post_id;

		// Allow addtional actions for custom code. WPML (@since 1.12.2)
		do_action( 'bricks/render_query_page/start', $request_data );

		/**
		 * Handle Query ID with dash
		 * This query is located inside a component instance (not root)
		 * hedzyv-flzcwg, hedzyv-hdcwtt
		 * - hedzyv is the query element ID that holds the structure
		 * - flzwg, hdcwtt is the element ID outside the component (unique), holds the actual properties
		 *
		 * @since 1.12.2
		 */
		$data = [];
		if ( strpos( $query_element_id, '-' ) !== false ) {
			// The query is located in a component instance
			$part              = explode( '-', $query_element_id );
			$query_instance_id = '';
			$element_id        = '';

			if ( count( $part ) === 2 ) {
				// The query element actual ID is the first part
				if ( ! empty( $part[0] ) ) {
					$query_instance_id = (string) $part[0];
				}

				// Element Instance ID is the second part
				if ( ! empty( $part[1] ) ) {
					$element_id = (string) $part[1];
				}
			}

			if ( empty( $query_instance_id ) || empty( $element_id ) ) {
				return rest_ensure_response(
					[
						'html'   => '',
						'styles' => '',
						'error'  => 'Query element in component not found',
					]
				);
			}

			// Get the element data (data for flzwg), this will contains the cid (physical element in bricks data)
			$element_data = Helpers::get_element_data( $post_id, $element_id );

			if ( empty( $element_data['element'] ) ) {
				return rest_ensure_response(
					[
						'html'   => '',
						'styles' => '',
						'error'  => 'Element not found: ' . $element_id,
					]
				);
			}

			// Ensure element has cid
			if ( empty( $element_data['element']['cid'] ) ) {
				return rest_ensure_response(
					[
						'html'   => '',
						'styles' => '',
						'error'  => 'Element is not a proper component: ' . $element_id,
					]
				);
			}

			// STEP: Get the component instance data (data for hedzyv) filled with properties
			$component_data_elements = Helpers::get_component_instance( $element_data['element'], 'elements' );

			// STEP: Add parentComponent and instanceId for each element
			foreach ( $component_data_elements as $key => $component_data_element ) {
				$component_data_elements[ $key ]['parentComponent'] = $element_data['element']['cid'];
				$component_data_elements[ $key ]['instanceId']      = $element_id;
				$component_data_elements[ $key ]['ajaxLocalId']     = $element_id . '-' . $component_data_element['id']; // component children become local element when running generate_css_from_elements (#86c4957mc)
			}

			// Find the query element via query_instance_id from the component data
			$query_element = array_values(
				array_filter(
					$component_data_elements,
					function( $element ) use ( $query_instance_id ) {
						return (string) $element['id'] === $query_instance_id;
					}
				)
			);

			// Get the first element if it exists
			$query_element = ! empty( $query_element ) ? $query_element[0] : null;

			if ( empty( $query_element ) ) {
				return rest_ensure_response(
					[
						'html'   => '',
						'styles' => '',
						'error'  => 'Query element not found: ' . $query_instance_id,
					]
				);
			}

			// Now build the data
			$data = [
				'element'   => $query_element,
				'elements'  => $component_data_elements,
				'source_id' => 'component',
			];

			// Set query element id
			$query_element_id = $query_instance_id;

		} else {
			// Normal query element ID
			$data = Helpers::get_element_data( $post_id, $query_element_id );
		}

		if ( empty( $data['elements'] ) ) {
			return rest_ensure_response(
				[
					'html'   => '',
					'styles' => '',
					'error'  => 'Template data not found',
				]
			);
		}

		// STEP: Build the flat list index
		$indexed_elements = [];

		foreach ( $data['elements'] as $element ) {
			$indexed_elements[ $element['id'] ] = $element;
		}

		if ( ! array_key_exists( $query_element_id, $indexed_elements ) ) {
			return rest_ensure_response(
				[
					'html'   => '',
					'styles' => '',
					'error'  => 'Element not found',
				]
			);
		}

		// Set current query element ID in the Api request, use in base.php and fore render the element (@since 2.1)
		self::$query_element_id = $query_element_id;

		// STEP: Set the query element pagination
		$query_element = $indexed_elements[ $query_element_id ];

		// STEP: Replace query element with component data (@since 1.12.2)
		if ( isset( $query_element['cid'] ) ) {
			// Replace query element with component data
			$query_element = self::replace_query_element_with_component_data( $query_element, $query_element_id );

			// Update indexed elements
			foreach ( $query_element['elements'] as $element ) {
				$indexed_elements[ $element['id'] ] = $element;
			}
		}

		// STEP: Get global query settings (@since 2.1)
		if ( isset( $query_element['settings']['query'] ) ) {
			$query_element['settings']['query'] = Helpers::maybe_get_global_query_settings( $query_element['settings']['query'] ?? [] );
		}

		/**
		 * STEP: Use hook to merge query_vars from the request instead of '_merge_vars' (@pre 1.9.5)
		 *
		 * Reason: _merge_vars not in use
		 * - not reliable as it is using wp_parse_args(), only merge if the key is not set
		 * - logic only occurs in post query, term and user not supported
		 *
		 * @since 1.9.5
		 */
		$object_type = $query_element['settings']['query']['objectType'] ?? 'post';

		if ( in_array( $object_type, [ 'term', 'user' ] ) ) {
			// Don't use request's offset, Term and User query offset should be calculated Query::inside prepare_query_vars_from_settings()
			unset( $query_vars['offset'] );
		}

		// NOTE: $ori_query_element_id could be a query element ID with dash (query inside a component instance). This type of query is supported in AJAX pagination only. Not Query filters. Must use this ID in hooks or we will get the wrong query.

		/**
		 * Set the page number
		 *
		 * Needed for term query to calculate pagination correctly.
		 *
		 * @since 1.12.2
		 */
		add_filter(
			'bricks/query/prepare_query_vars_from_settings',
			function( $settings, $element_id ) use ( $page, $ori_query_element_id, $object_type ) {
				if ( $element_id !== $ori_query_element_id || ( $object_type !== 'term' && $object_type !== 'api' ) ) {
					return $settings;
				}

				$settings['query']['paged'] = $page;

				return $settings;
			},
			999,
			2
		);

		add_filter(
			"bricks/{$object_type}s/query_vars",
			function( $vars, $settings, $element_id ) use ( $ori_query_element_id, $query_vars, $object_type, $page ) {
				if ( $element_id !== $ori_query_element_id ) {
					return $vars;
				}

				// STEP: Restore original query vars from frontend for dynamic parsed data (#86c4mgz6q; @since 2.0.1)
				$vars = Query::restore_original_query_vars_from_frontend( $query_vars, $vars, $object_type );

				// Set the page number which comes from the request
				$query_vars['paged'] = $page;

				// Merge the query vars
				$merged_query_vars = Query::merge_query_vars( $vars, $query_vars );

				return $merged_query_vars;
			},
			10,
			3
		);

		// Remove the parent
		if ( ! empty( $query_element['parent'] ) ) {
			$query_element['parent']       = 0;
			$query_element['_noRootClass'] = 1;
		}

		// STEP: Get the query loop elements (main and children)
		$loop_elements = [ $query_element ];

		$children = $query_element['children'];

		while ( ! empty( $children ) ) {
			$child_id = array_shift( $children );

			if ( array_key_exists( $child_id, $indexed_elements ) ) {
				$loop_elements[] = $indexed_elements[ $child_id ];

				if ( ! empty( $indexed_elements[ $child_id ]['children'] ) ) {
					$children = array_merge( $children, $indexed_elements[ $child_id ]['children'] );
				}
			}
		}

		// Set Theme Styles (for correct preview of query loop nodes)
		Theme_Styles::load_set_styles( $post_id );

		// STEP: Generate the styles again to catch dynamic data changes (eg. background-image)
		$scroll_query_page_id = "scroll_{$query_element_id}_{$page}";

		Assets::generate_css_from_elements( $loop_elements, $scroll_query_page_id );

		$inline_css = ! empty( Assets::$inline_css[ $scroll_query_page_id ] ) ? Assets::$inline_css[ $scroll_query_page_id ] : '';

		// STEP: Render the element after styles are generated as data-query-loop-index might be inserted through hook in Assets class (@since 1.7.2)
		$html = Frontend::render_data( $loop_elements );

		// Add popup HTML plus styles (@since 1.7.1)
		$popups = Popups::$looping_popup_html;

		// STEP: Add dynamic data styles after render_data() to catch dynamic data changes (eg. background-image) (@since 1.8.2)
		$inline_css .= Assets::$inline_css_dynamic_data;

		$styles = ! empty( $inline_css ) ? "\n<style>/* INFINITE SCROLL CSS */\n{$inline_css}</style>\n" : '';

		// STEP: Set the base URL for pagination or the pagination links will be using API endpoint
		if ( ! empty( $base_url ) ) {
			add_filter(
				'bricks/paginate_links_args',
				function( $args ) use ( $base_url ) {
					$args['base'] = $base_url . '%_%';
					return $args;
				}
			);
		}

		$pagination = false;
		if ( $pagination_id ) {
			$element_data = Helpers::get_element_data( $post_id, $pagination_id );
			if ( ! empty( $element_data['element'] ) ) {
				$pagination_element = $element_data['element'] ?? false;
				$pagination         = Frontend::render_element( $pagination_element );
			} else {
				// Maybe the pagination element is inside a component
				$pagination_component = array_filter(
					$data['elements'],
					function( $element ) use ( $pagination_id ) {
						return (string) $element['id'] === (string) $pagination_id;
					}
				);

				$pagination_component = reset( $pagination_component );

				if ( ! empty( $pagination_component ) ) {
					$pagination = Frontend::render_element( $pagination_component );
				}
			}
		}

		// STEP: Query data, use original query ID
		$query_data = Helpers::get_query_object_from_history_or_init( $ori_query_element_id, $post_id );

		// Remove unnecessary properties
		unset( $query_data->settings );
		unset( $query_data->query_result );
		unset( $query_data->loop_index );
		unset( $query_data->loop_object );
		unset( $query_data->is_looping );
		unset( $query_data->fake_result );

		if ( isset( $query_data->query_vars['queryEditor'] ) ) {
			unset( $query_data->query_vars['queryEditor'] );
		}

		if ( isset( $query_data->query_vars['signature'] ) ) {
			unset( $query_data->query_vars['signature'] );
		}

		return rest_ensure_response(
			[
				'html'          => $html,
				'styles'        => $styles,
				'popups'        => $popups,
				'pagination'    => $pagination,
				'updated_query' => $query_data,
			]
		);
	}

	/**
	 * AJAX popup callback
	 *
	 * @since 1.9.4
	 */
	public function render_popup_content( $request ) {
		$request_data = $request->get_json_params();

		$post_id            = $request_data['postId'] ?? false;
		$popup_id           = $request_data['popupId'] ?? false;
		$popup_loop_id      = $request_data['popupLoopId'] ?? false;
		$popup_context_id   = $request_data['popupContextId'] ?? false;
		$popup_context_type = $request_data['popupContextType'] ?? false;
		$query_element_id   = $request_data['queryElementId'] ?? false;
		$language           = isset( $request_data['lang'] ) ? sanitize_key( $request_data['lang'] ) : false;
		$main_query_id      = isset( $request_data['mainQueryId'] ) ? sanitize_text_field( $request_data['mainQueryId'] ) : false;

		// Set current language (@since 2.0)
		if ( $language ) {
			Database::$page_data['language'] = $language;
		}

		// Set main query ID (@since 2.0)
		if ( $main_query_id ) {
			Database::$main_query_id = $main_query_id;
		}

		// Allow addtional actions for custom code (@since 2.0)
		do_action( 'bricks/render_popup_content/start', $request_data );

		// Get Popup template settings and add classes to the popup content (@since 1.10.2)
		$popup_settings    = Helpers::get_template_settings( $popup_id );
		$is_woo_quick_view = isset( $popup_settings['popupIsWoo'] ) && Woocommerce::is_woocommerce_active();

		// Handle WooCommerce Quick View in case no popupContextId has been defined (@since 1.10.2)
		if ( $is_woo_quick_view && ! $popup_context_id && $popup_loop_id ) {
			// Retrieve the context from popupLoopId
			$popup_loop_id_parts = explode( ':', $popup_loop_id );
			if ( count( $popup_loop_id_parts ) === 4 ) {
				$popup_context_id = $popup_loop_id_parts[3];
				$popup_loop_id    = false;
				$query_element_id = false;
			}
		}

		// Set context in AJAX popup (post, term, user), $post_id might be zero on pages like 404, search, etc.
		if ( $post_id || $is_woo_quick_view || $popup_context_id ) {
			global $wp_query;
			global $post;
			$post = get_post( $post_id );
			setup_postdata( $post );

			/**
			 * Set necessary global variables so we can use get_queried_object(), get_the_ID() etc.
			 */
			switch ( $popup_context_type ) {
				case 'post':
					if ( $popup_context_id ) {
						// Override the global post
						$post = get_post( $popup_context_id );
						setup_postdata( $post );
					}

					if ( ! $post || is_wp_error( $post ) ) {
						break;
					}

					$wp_query->queried_object    = $post;
					$wp_query->queried_object_id = $post->ID;
					$wp_query->is_singular       = true;
					$wp_query->post_type         = $post->post_type;

					// Set is_single / is_page, otherwise comments_template wouldn't work (@since 1.10.2)
					if ( is_page( $post->ID ) ) {
						$wp_query->is_page = true;
					} else {
						$wp_query->is_single = true;
					}
					break;

				case 'term':
					$term = get_term( $popup_context_id ? $popup_context_id : $post_id );
					if ( ! $term || is_wp_error( $term ) ) {
						break;
					}

					$wp_query->queried_object    = $term;
					$wp_query->queried_object_id = $term->term_id;
					$wp_query->is_tax            = true;
					break;

				case 'user':
					$user = get_user_by( 'id', $popup_context_id ? $popup_context_id : $post_id );
					if ( ! $user || is_wp_error( $user ) ) {
						break;
					}

					$wp_query->queried_object    = $user;
					$wp_query->queried_object_id = $user->ID;
					$wp_query->is_author         = true;
					break;
			}
		}

		/**
		 * Default: Current context (query element ID)
		 * Re-run the query loop
		 * Inaccurate, might be empty if inside a nested loop or repeater
		 */
		if ( $query_element_id ) {
			// Set page_data via filter
			add_filter(
				'bricks/builder/data_post_id',
				function( $id ) use ( $post_id ) {
					return $post_id;
				}
			);

			// Preview ID or post ID is very important in popup as it's a template, so we need to set separately
			Database::$page_data['preview_or_post_id'] = $post_id;

			$data = [];

			/**
			 * Handle Query ID with dash
			 * This query is located inside a component instance (not root)
			 * hedzyv-flzcwg, hedzyv-hdcwtt
			 * - hedzyv is the query element ID that holds the structure
			 * - flzwg, hdcwtt is the element ID outside the component (unique), holds the actual properties
			 *
			 * @since 1.12.2
			 */
			if ( strpos( $query_element_id, '-' ) !== false ) {
				// The query is located in a component instance
				$part              = explode( '-', $query_element_id );
				$query_instance_id = '';
				$element_id        = '';

				if ( count( $part ) === 2 ) {
					// The query element actual ID is the first part
					if ( ! empty( $part[0] ) ) {
						$query_instance_id = (string) $part[0];
					}

					// Element Instance ID is the second part
					if ( ! empty( $part[1] ) ) {
						$element_id = (string) $part[1];
					}
				}

				if ( empty( $query_instance_id ) || empty( $element_id ) ) {
					return rest_ensure_response(
						[
							'html'   => '',
							'styles' => '',
							'error'  => 'Query element in component not found',
						]
					);
				}

				// Get the element data (data for flzwg), this will contains the cid (physical element in bricks data)
				$element_data = Helpers::get_element_data( $post_id, $element_id );

				if ( empty( $element_data['element'] ) ) {
					return rest_ensure_response(
						[
							'html'   => '',
							'styles' => '',
							'error'  => 'Element not found: ' . $element_id,
						]
					);
				}

				// Ensure element has cid
				if ( empty( $element_data['element']['cid'] ) ) {
					return rest_ensure_response(
						[
							'html'   => '',
							'styles' => '',
							'error'  => 'Element is not a proper component: ' . $element_id,
						]
					);
				}

				// STEP: Get the component instance data (data for hedzyv) filled with properties
				$component_data_elements = Helpers::get_component_instance( $element_data['element'], 'elements' );

				// STEP: Add parentComponent and instanceId for each element
				foreach ( $component_data_elements as $key => $component_data_element ) {
					$component_data_elements[ $key ]['parentComponent'] = $element_data['element']['cid'];
					$component_data_elements[ $key ]['instanceId']      = $element_id;
					$component_data_elements[ $key ]['ajaxLocalId']     = $element_id . '-' . $component_data_element['id']; // component children become local element when running generate_css_from_elements (#86c4957mc)
				}

				// Find the query element via query_instance_id from the component data
				$query_element = array_values(
					array_filter(
						$component_data_elements,
						function( $element ) use ( $query_instance_id ) {
							return (string) $element['id'] === $query_instance_id;
						}
					)
				);

				// Get the first element if it exists
				$query_element = ! empty( $query_element ) ? $query_element[0] : null;

				if ( empty( $query_element ) ) {
					return rest_ensure_response(
						[
							'html'   => '',
							'styles' => '',
							'error'  => 'Query element not found: ' . $query_instance_id,
						]
					);
				}

				// Now build the data
				$data = [
					'element'   => $query_element,
					'elements'  => $component_data_elements,
					'source_id' => 'component',
				];

				// Set query element id
				$query_element_id = $query_instance_id;

			} else {
				// Normal query element ID
				// This popup inside a loop
				$data = Helpers::get_element_data( $post_id, $query_element_id );
			}

			if ( empty( $data['elements'] ) ) {
				return rest_ensure_response(
					[
						'html'   => '',
						'styles' => '',
						'popups' => [],
						'error'  => esc_html__( 'Popup data not found', 'bricks' ),
					]
				);
			}

			// STEP: Build the flat list index
			$indexed_elements = [];

			foreach ( $data['elements'] as $element ) {
				$indexed_elements[ $element['id'] ] = $element;
			}

			if ( ! array_key_exists( $query_element_id, $indexed_elements ) ) {
				return rest_ensure_response(
					[
						'html'   => '',
						'styles' => '',
						'popups' => [],
						'error'  => esc_html__( 'Element not found', 'bricks' ),
					]
				);
			}

			// STEP: Set the query element pagination
			$query_element = $indexed_elements[ $query_element_id ];

			// To solve looping popup without context issue (@since 1.12.2)
			if ( isset( $query_element['cid'] ) ) {
				// Replace query element with component data
				$query_element = self::replace_query_element_with_component_data( $query_element, $query_element_id );

				// Update indexed elements
				foreach ( $query_element['elements'] as $element ) {
					$indexed_elements[ $element['id'] ] = $element;
				}

				// Must unset or the final query_vars is not using post_id (unknown reason)
				unset( $query_element['cid'] );
			}

			// Get the target object ID from popupId string, separated by ':'
			if ( $popup_loop_id ) {
				$popup_id_parts = explode( ':', $popup_loop_id );

				// $popup_id_parts at least 4 parts (@since 1.12)
				if ( count( $popup_id_parts ) >= 4 ) {
					$query_object_type = $popup_id_parts[2];
					$query_object_id   = $popup_id_parts[3];
					$actual_query_id   = $popup_id_parts[0];
					$new_popup_loop_id = $popup_loop_id;

					switch ( $query_object_type ) {
						case 'post':
							$query_element['settings']['query']['p'] = $query_object_id;
							$new_popup_loop_id                       = "{$actual_query_id}:0:{$query_object_type}:{$query_object_id}";
							break;
						case 'term':
							$query_element['settings']['query']['include'] = $query_object_id;
							$new_popup_loop_id                             = "{$actual_query_id}:0:{$query_object_type}:{$query_object_id}";
							break;
						case 'user':
							$query_element['settings']['query']['include'] = $query_object_id;
							$new_popup_loop_id                             = "{$actual_query_id}:0:{$query_object_type}:{$query_object_id}";
							break;
						default:
						case 'unknown':
							// Unable to detect query object type, this is inside repeater... query all ?
							// $query_element['settings']['query']['post_per_page'] = -1;
							// Return error and indicate not supported
							return rest_ensure_response(
								[
									'html'   => '',
									'styles' => '',
									'popups' => [],
									'error'  => esc_html__( 'Query object type not supported', 'bricks' ),
								]
							);

							break;
					}
				}
			}

			// Remove the parent
			if ( ! empty( $query_element['parent'] ) ) {
				$query_element['parent']       = 0;
				$query_element['_noRootClass'] = 1;
			}

			// STEP: Get the query loop elements (main and children)
			$loop_elements = [ $query_element ];

			$children = $query_element['children'];

			while ( ! empty( $children ) ) {
				$child_id = array_shift( $children );

				if ( array_key_exists( $child_id, $indexed_elements ) ) {
					$loop_elements[] = $indexed_elements[ $child_id ];

					if ( ! empty( $indexed_elements[ $child_id ]['children'] ) ) {
						$children = array_merge( $children, $indexed_elements[ $child_id ]['children'] );
					}
				}
			}

			// Set Theme Styles (for correct preview of query loop nodes)
			Theme_Styles::load_set_styles( $post_id );

			// STEP: Generate the styles again to catch dynamic data changes (eg. background-image)
			$looping_popup_id = "popup_{$query_element_id}_{$post_id}";

			Assets::generate_css_from_elements( $loop_elements, $looping_popup_id );

			$inline_css = ! empty( Assets::$inline_css[ $looping_popup_id ] ) ? Assets::$inline_css[ $looping_popup_id ] : '';

			Frontend::render_data( $loop_elements, 'popup' );

			$popups = Popups::$ajax_popup_contents;

			// Use $new_popup_loop_id to get popup content
			$popup_content = $popups[ $new_popup_loop_id ][ $popup_id ]['html'] ?? '';

			// STEP: Add dynamic data styles after render_data() to catch dynamic data changes (eg. background-image)
			$inline_css .= Assets::$inline_css_dynamic_data;

			$styles = ! empty( $inline_css ) ? "\n<style>/*AJAX POPUP CSS */\n{$inline_css}</style>\n" : '';
		}

		/**
		 * Use user defined context (popupContextId)
		 * More reliable than query_element_id way
		 */
		else {
			// Set page_data via filter
			add_filter(
				'bricks/builder/data_post_id',
				function( $id ) use ( $post_id, $popup_context_id ) {
					// Use popup_context_id if not false
					return $popup_context_id ? $popup_context_id : $post_id;
				}
			);

			// Preview or post id is very important in popup as it's a template, so we need to set separately
			Database::$page_data['preview_or_post_id'] = $popup_context_id ? $popup_context_id : $post_id;

			// This logic causing dynamic css not generated correctly (@since 1.12.2)
			// if ( $poup_is_looping ) {
			// Simulate Query::is_looping() as we skipped the query loop
			// add_filter( 'bricks/query/force_is_looping', '__return_true' );

			// Simulate Query::get_loop_index() as we skipped the query loop
			// add_filter(
			// 'bricks/query/force_loop_index',
			// function( $index ) {
			// return 0;
			// }
			// );
			// }

			// Get popup via popup ID
			$elements = Database::get_data( $popup_id );

			if ( empty( $elements ) ) {
				return rest_ensure_response(
					[
						'html'   => '',
						'styles' => '',
						'popups' => [],
						'error'  => esc_html__( 'Popup data not found', 'bricks' ),
					]
				);
			}

			// Set active templates
			Database::set_active_templates( $post_id );

			// Set Theme Styles (for correct preview of query loop nodes)
			Theme_Styles::load_set_styles( $post_id );

			// STEP: Generate the styles again to catch dynamic data changes (eg. background-image)
			$popup_page_id = "popup_{$post_id}";

			Assets::generate_css_from_elements( $elements, $popup_page_id );

			$inline_css = Assets::$inline_css[ $popup_page_id ] ?? '';

			$popup_content = Frontend::render_data( $elements, 'popup' );

			$inline_css .= Assets::$inline_css_dynamic_data;

			$styles = ! empty( $inline_css ) ? "\n<style>/* AJAX POPUP CSS */\n{$inline_css}</style>\n" : '';
		}

		$looping_popup_html = [];

		if ( ! empty( Popups::$looping_ajax_popup_ids ) ) {
			/**
			 * In certain scenario, some popup templates inserted inside a query loop which is inside another AJAX popup template
			 * Generate each looping AJAX popup html holder, we could use this to add into the DOM if it's not there yet
			 */
			foreach ( Popups::$looping_ajax_popup_ids as $looping_popup_id ) {
				$html                                    = Popups::generate_popup_html( $looping_popup_id );
				$looping_popup_html[ $looping_popup_id ] = $html;
			}
		}

		$response = [
			'html'   => $popup_content,
			'styles' => $styles,
			'popups' => $looping_popup_html,
		];

		// Add Woo quick view classes so JS can insert into popup content node (@since 1.10.2)
		if ( $is_woo_quick_view ) {
			global $product;
			$response['contentClasses'] = (array) wc_get_product_class( '', $product );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Ajax Popup permissions callback
	 *
	 * @since 1.9.4
	 */
	public function render_popup_content_permissions_check( $request ) {
		$data = $request->get_json_params();

		if ( empty( $data['popupId'] ) || empty( $data['nonce'] ) ) {
			return new \WP_Error( 'bricks_api_missing', __( 'Missing parameters' ), [ 'status' => 400 ] );
		}

		$result = wp_verify_nonce( $data['nonce'], 'bricks-nonce' );

		if ( $result === false ) {
			return new \WP_Error( 'rest_cookie_invalid_nonce', __( 'Bricks cookie check failed' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Similar like render_query_page() but for AJAX query result
	 *
	 * For load more, AJAX pagination, infinite scroll, sort, filter, live search.
	 *
	 * @since 1.9.6
	 */
	public function render_query_result( $request ) {
		$request_data = $request->get_json_params();

		$query_element_id    = $request_data['queryElementId'];
		$post_id             = $request_data['postId'];
		$filters             = $request_data['filters'] ?? [];
		$selected_filters    = $request_data['selectedFilters'] ?? [];
		$active_filters_tags = $request_data['afTags'] ?? [];
		$page_filters        = $request_data['pageFilters'] ?? [];
		$base_url            = $request_data['baseUrl'] ?? '';
		$language            = isset( $request_data['lang'] ) ? sanitize_key( $request_data['lang'] ) : false;
		$infinite_page       = isset( $request_data['infinitePage'] ) ? sanitize_text_field( $request_data['infinitePage'] ) : 1;
		$original_query_vars = isset( $request_data['originalQueryVars'] ) ? json_decode( $request_data['originalQueryVars'], true ) : [];
		$main_query_id       = isset( $request_data['mainQueryId'] ) ? sanitize_text_field( $request_data['mainQueryId'] ) : false;

		// Set current language (@since 1.9.9)
		if ( $language ) {
			Database::$page_data['language'] = $language;
		}

		// Set main query ID (@since 2.0)
		if ( $main_query_id ) {
			Database::$main_query_id = $main_query_id;
		}

		// Set post_id for use in prepare_query_vars_from_settings
		Database::$page_data['preview_or_post_id'] = $post_id;

		// Allow addtional actions for custom code. WPML (@since 1.12.2)
		do_action( 'bricks/render_query_result/start', $request_data );

		$data = Helpers::get_element_data( $post_id, $query_element_id );

		if ( empty( $data['elements'] ) ) {
			return rest_ensure_response(
				[
					'html'   => '',
					'styles' => '',
					'error'  => 'Template data not found',
				]
			);
		}

		// STEP: Build the flat list index
		$indexed_elements = [];

		foreach ( $data['elements'] as $element ) {
			$indexed_elements[ $element['id'] ] = $element;
		}

		if ( ! array_key_exists( $query_element_id, $indexed_elements ) ) {
			return rest_ensure_response(
				[
					'html'   => '',
					'styles' => '',
					'error'  => 'Element not found',
				]
			);
		}

		// Set current query element ID in the Api request, use in base.php and fore render the element (@since 2.1)
		self::$query_element_id = $query_element_id;

		// STEP: Set the query element pagination
		$query_element = $indexed_elements[ $query_element_id ];

		// STEP: Replace query element with component data (@since 1.12.2)
		if ( isset( $query_element['cid'] ) ) {
			// Replace query element with component data
			$query_element = self::replace_query_element_with_component_data( $query_element, $query_element_id );

			// Update indexed elements
			foreach ( $query_element['elements'] as $element ) {
				$indexed_elements[ $element['id'] ] = $element;
			}
		}

		$query_object_type = isset( $query_element['settings']['query']['objectType'] ) ? sanitize_text_field( $query_element['settings']['query']['objectType'] ) : 'post';

		// Return error: Not a post, term or user query
		if ( ! in_array( $query_object_type, [ 'post', 'term', 'user' ] ) ) {
			return rest_ensure_response(
				[
					'html'   => '',
					'styles' => '',
					'error'  => 'Query object type not supported',
				]
			);
		}

		// STEP: Set page filters
		Query_Filters::set_page_filters( $page_filters );

		// STEP: Set active filters
		Query_Filters::set_active_filters( $selected_filters, $post_id, $query_element_id );

		// STEP: Set flag for query_vars (@since 1.12)
		Query_Filters::set_generating_type( $query_object_type );

		// STEP: generate query vars from active filters
		$filter_query_vars = Query_Filters::generate_query_vars_from_active_filters( $query_element_id );

		// STEP: Check if query merge is disabled
		$disable_query_merge = $query_element['settings']['query']['disable_query_merge'] ?? false;

		// STEP: Use infinite page number
		if ( $infinite_page > 1 ) {
			$filter_query_vars['paged'] = $infinite_page;
		}

		// Set the paged & number - This is needed for term query to calculate pagination correctly (@since 1.12)
		if ( $query_object_type === 'term' ) {
			if (
				( isset( $filter_query_vars['paged'] ) && $filter_query_vars['paged'] > 1 ) ||
				( isset( $filter_query_vars['number'] ) && $filter_query_vars['number'] > 0 )
			) {
				add_filter(
					'bricks/query/prepare_query_vars_from_settings',
					function( $settings, $element_id ) use ( $filter_query_vars, $query_element_id, $query_object_type ) {
						if ( $element_id !== $query_element_id || $query_object_type !== 'term' ) {
							return $settings;
						}

						// Set paged value
						if ( isset( $filter_query_vars['paged'] ) ) {
							$settings['query']['paged'] = $filter_query_vars['paged'];
						}

						// Set number value
						if ( isset( $filter_query_vars['number'] ) ) {
							// Backup the user original number value
							if ( isset( $settings['query']['number'] ) ) {
								$settings['query']['brx_user_number'] = $settings['query']['number'];
							}
							// Set the new number value
							$settings['query']['number'] = $filter_query_vars['number'];
						}

						return $settings;
					},
					10,
					2
				);
			}
		}

		// STEP: Merge the query vars via filter, so we can override WooCommerce query vars, queryEditor query vars, etc.
		add_filter(
			"bricks/{$query_object_type}s/query_vars",
			function( $vars, $settings, $element_id ) use ( $filter_query_vars, $query_element_id, $disable_query_merge, $original_query_vars, $query_object_type ) {
				if ( $element_id !== $query_element_id ) {
					return $vars;
				}

				// STEP: Restore original query vars from the frontend (dynamic data parsed) (@since 1.12)
				$vars = Query::restore_original_query_vars_from_frontend( $original_query_vars, $vars, $query_object_type );

				// STEP: Original query vars should include page filters (if it's not disabled) (@since 1.11)
				if ( ! $disable_query_merge && Query_Filters::should_apply_page_filters( $vars ) ) {
					$vars = Query::merge_query_vars( $vars, Query_Filters::generate_query_vars_from_page_filters() );
				}

				// STEP: Save the query vars before merge only once (@since 1.11.1)
				if ( ! isset( Query_Filters::$query_vars_before_merge[ $query_element_id ] ) ) {
					Query_Filters::$query_vars_before_merge[ $query_element_id ] = $vars;

					// For term and user query, must save the user original number value or it will be overwritten by url parameter value after page reload
					if ( in_array( $query_object_type, [ 'term', 'user' ], true ) && isset( $vars['brx_user_number'] ) ) {
						Query_Filters::$query_vars_before_merge[ $query_element_id ]['number'] = $vars['brx_user_number'];

						// Cleanup
						unset( $vars['brx_user_number'] );
						unset( Query_Filters::$query_vars_before_merge[ $query_element_id ]['brx_user_number'] );
					}
				}

				// STEP: Merge the query vars from filters
				$final = Query::merge_query_vars( $vars, $filter_query_vars );

				return $final;
			},
			999, // As long as using Query Filters, the filter's query var should be the last (@since 1.11.1)
			3
		);

		// STEP: Reset flasg (@since 1.12)
		Query_Filters::reset_generating_type();

		// Remove the parent
		if ( ! empty( $query_element['parent'] ) ) {
			$query_element['parent']       = 0;
			$query_element['_noRootClass'] = 1;
		}

		// STEP: Get the query loop elements (main and children)
		$loop_elements = [ $query_element ];

		$children = $query_element['children'];

		while ( ! empty( $children ) ) {
			$child_id = array_shift( $children );

			if ( array_key_exists( $child_id, $indexed_elements ) ) {
				$loop_elements[] = $indexed_elements[ $child_id ];

				if ( ! empty( $indexed_elements[ $child_id ]['children'] ) ) {
					$children = array_merge( $children, $indexed_elements[ $child_id ]['children'] );
				}
			}
		}

		// Set Theme Styles (for correct preview of query loop nodes)
		Theme_Styles::load_set_styles( $post_id );

		// STEP: Generate the styles again to catch dynamic data changes (eg. background-image)
		$query_identifier = "ajax_query_{$query_element_id}";

		Assets::generate_css_from_elements( $loop_elements, $query_identifier );

		$inline_css = ! empty( Assets::$inline_css[ $query_identifier ] ) ? Assets::$inline_css[ $query_identifier ] : '';

		// STEP: Render the element after styles are generated as data-query-loop-index might be inserted through hook in Assets class
		$html = Frontend::render_data( $loop_elements );

		// Add popup HTML plus styles
		$popups = Popups::$looping_popup_html;

		// STEP: Add dynamic data styles after render_data() to catch dynamic data changes (eg. background-image)
		$inline_css .= Assets::$inline_css_dynamic_data;

		$styles = ! empty( $inline_css ) ? "\n<style>/* AJAX QUERY RESULT CSS */\n{$inline_css}</style>\n" : '';

		// STEP: Set the base URL for pagination or the pagination links will be using API endpoint
		if ( ! empty( $base_url ) ) {
			add_filter(
				'bricks/paginate_links_args',
				function( $args ) use ( $base_url ) {
					$args['base'] = $base_url . '%_%';
					return $args;
				}
			);
		}

		// STEP: Get updated filters HTML
		$updated_filters = Query_Filters::get_updated_filters( $filters, $post_id );

		// STEP: Query data
		$query_data = Helpers::get_query_object_from_history_or_init( $query_element_id, $post_id );

		// Remove unnecessary properties
		unset( $query_data->settings );
		unset( $query_data->query_result );
		unset( $query_data->loop_index );
		unset( $query_data->loop_object );
		unset( $query_data->is_looping );
		unset( $query_data->fake_result );

		if ( isset( $query_data->query_vars['queryEditor'] ) ) {
			unset( $query_data->query_vars['queryEditor'] );
		}

		if ( isset( $query_data->query_vars['signature'] ) ) {
			unset( $query_data->query_vars['signature'] );
		}

		// Get the active filters count via Dynamic Data (@since 2.0)
		$parsed_af_tags = [];
		if ( is_array( $active_filters_tags ) && ! empty( $active_filters_tags ) ) {
			foreach ( $active_filters_tags as $tag ) {
				if ( ! is_string( $tag ) ) {
					continue;
				}

				$tag = sanitize_text_field( trim( $tag ) );

				// Only parse dynamic data tags starting with 'active_filters_count'
				if ( strpos( $tag, 'active_filters_count' ) !== 0 ) {
					continue;
				}

				$parsed_af_tags[ $tag ] = Integrations\Dynamic_Data\Providers::render_tag( "{$tag}", $post_id );
			}
		}

		return rest_ensure_response(
			[
				'html'            => $html,
				'styles'          => $styles,
				'popups'          => $popups,
				'updated_filters' => $updated_filters,
				'updated_query'   => $query_data,
				'parsed_af_tags'  => $parsed_af_tags,
				// 'page_filters'    => Query_Filters::$page_filters,
				// 'filter_object_ids' => Query_Filters::$filter_object_ids,
				// 'active_filters'  => Query_Filters::$active_filters,
			]
		);
	}

	/**
	 * Query loop: Query result permissions callback
	 *
	 * @since 1.9.6
	 */
	public function render_query_result_permissions_check( $request ) {
		$data = $request->get_json_params();

		if ( empty( $data['queryElementId'] ) || empty( $data['nonce'] ) ) {
			return new \WP_Error( 'bricks_api_missing', __( 'Missing parameters' ), [ 'status' => 400 ] );
		}

		$result = wp_verify_nonce( $data['nonce'], 'bricks-nonce' );

		if ( $result === false ) {
			return new \WP_Error( 'rest_cookie_invalid_nonce', __( 'Bricks cookie check failed' ), [ 'status' => 403 ] );
		}

		return true;
	}

	/**
	 * Get global classes categorized by their usage across the site
	 *
	 * @since 1.12
	 */
	public function get_global_classes_site_usage() {
		return rest_ensure_response(
			[
				'data' => Helpers::scan_global_classes_site_usage()
			]
		);
	}

	/**
	 * Permission check for get_global_classes_site_usage endpoint
	 */
	public function get_global_classes_site_usage_permissions_check( $request ) {
		$nonce = $request->get_header( 'X-Bricks-Nonce' );

		$result = wp_verify_nonce( $nonce, 'bricks-nonce-builder' );

		if ( $result === false ) {
			return new \WP_Error( 'rest_cookie_invalid_nonce', __( 'Bricks cookie check failed' ), [ 'status' => 403 ] );
		}

		// Return: Current user does not have full access
		if ( ! Builder_Permissions::user_has_permission( 'access_class_manager' ) ) {
			return new \WP_Error( 'rest_current_user_does_not_have_full_access', __( 'Current user does not have access to get global classes site usage' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public static function replace_query_element_with_component_data( $query_element, $query_element_id ) {
		$component_settings = Helpers::get_component_instance( $query_element, 'settings' );

		// Update settings
		$query_element['settings'] = $component_settings ?? $query_element['settings'];

		$component_chidren = Helpers::get_component_instance( $query_element, 'children' );

		// Update children
		$query_element['children'] = $component_chidren ?? $query_element['children'];

		$component_elements = Helpers::get_component_instance( $query_element, 'elements' );

		// Update elements
		$query_element['elements'] = $component_elements ?? $query_element['elements'];

		// Replace all cid with query_element_id
		$query_element_elements_string = json_encode( $query_element['elements'] );

		$query_element_elements_string = str_replace( $query_element['cid'], $query_element_id, $query_element_elements_string );

		$query_element['elements'] = json_decode( $query_element_elements_string, true );

		return $query_element;
	}
}
