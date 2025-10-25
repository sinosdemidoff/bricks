<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit;

class Query_API {
	private $element_id = null;
	private $post_id    = 0;
	private $settings   = [];
	private $response   = null;
	private $last_error = null;

	private $cache_enabled  = false;
	private $cache_duration = 300; // Default 5 minutes
	private $cache_key      = null;
	private $current_page   = 1; // Default to page 1

	public function __construct( $settings = [], $elment_id = null, $post_id = 0 ) {
		$this->element_id = $elment_id;
		$this->post_id    = $post_id;
		$this->set_settings( $settings );

		// Cache settings
		$this->cache_duration = intval( $this->settings['cache_time'] ?? 300 );
		$this->cache_enabled  = $this->cache_duration > 0;
	}

	/**
	 * Set API settings and parse dynamic data
	 */
	public function set_settings( $settings = [] ) {
		if ( ! is_array( $settings ) || empty( $settings ) ) {
			$this->settings = [];
			return;
		}

		// Keys to exclude from dynamic data parsing
		$excluded_keys = [ 'api_name', 'response_path' ];

		// Recursively process the settings array
		$this->settings = $this->process_dynamic_settings( $settings, $excluded_keys );
	}

	private function process_dynamic_settings( $data, $excluded_keys = [], $current_key = '' ) {
		// If it's an array, process each element recursively
		if ( is_array( $data ) ) {
			$processed = [];

			foreach ( $data as $key => $value ) {
				$processed[ $key ] = $this->process_dynamic_settings(
					$value,
					$excluded_keys,
					$key
				);
			}

			return $processed;
		}

		// If it's a string and not in excluded keys, render dynamic data
		if ( is_string( $data ) && ! in_array( $current_key, $excluded_keys, true ) ) {
			if ( bricks_is_builder_call() ) {
				// Unslash the data when builder call (#86c5nfwwh)
				$data = wp_unslash( $data );
			}
			return bricks_render_dynamic_data( $data, $this->post_id );
		}

		// Return unchanged for other data types or excluded keys
		return $data;
	}

	/**
	 * Make the API request
	 */
	public function request() {
		$endpoint = $this->get_endpoint();

		if ( ! $endpoint ) {
			return [
				'error'      => esc_html__( 'API endpoint is not set or invalid.', 'bricks' ),
				'status'     => 400,
				'headers'    => [],
				'last_fetch' => time(),
			];
		}

		// Check cache first if enabled
		if ( $this->cache_enabled ) {
			$cached_response = $this->get_cache();
			if ( $cached_response !== false ) {
				$this->response = $cached_response;
				return $this->response;
			}
		}

		$args     = $this->build_request_args();
		$endpoint = $this->build_endpoint_with_params( $endpoint );

		// Maybe there was an error building auth or pagination
		if ( $this->last_error ) {
			return [
				'error'      => $this->last_error,
				'status'     => 400,
				'headers'    => [],
				'last_fetch' => time(),
			];
		}

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'error'      => $response->get_error_message(),
				'status'     => 500,
				'headers'    => [],
				'last_fetch' => time(),
			];
		}

		$this->response = $this->process_response( $response );

		// Cache setup
		if ( $this->cache_enabled ) {
			$this->set_cache( $this->response );
		}

		return $this->response;
	}

	/**
	 * Get the full API response (ignores response_path)
	 */
	public function get_full_response() {
		return $this->response['full_response'] ?? null;
	}

	/**
	 * Get the extracted data explicitly
	 */
	public function get_extracted_data() {
		return $this->response['extracted_data'] ?? null;
	}

	/**
	 * Get the response path that was used
	 */
	public function get_response_path() {
		return $this->response['response_path'] ?? '';
	}

	/**
	 * Get total pages based on pagination settings
	 */
	public function get_total_pages() {
		$total_pages = 1;

		// Check if pagination is enabled
		if ( ! isset( $this->settings['pagination_enabled'] ) || ! $this->settings['pagination_enabled'] ) {
			return $total_pages; // No pagination, return 1 page
		}

		$total_path = $this->settings['pagination_total_extract'] ?? '';

		if ( ! empty( $total_path ) ) {
			// Retrieve the total from pagination_total_extract settings.
			// Eg. 'data.total' to extract total items from {data: {total: 100}}
			$parts    = explode( '.', $total_path );
			$location = $parts[0] ?? '';
			$data     = null;

			if ( $location === 'body' ) { // body.maxPages
				// Extract from full response data
				$data = $this->get_full_response();
			} elseif ( $location === 'header' ) { // header.maxPages
				// Extract from response headers
				$data = $this->get_headers();
			}

			// Try to extract the total count from $parts[1] onwards
			if ( $data && count( $parts ) > 1 ) {
				$current = $data;

				// Traverse the path, maybe it's nested
				foreach ( array_slice( $parts, 1 ) as $key ) {
					if ( is_array( $current ) ) {
						// Handle array

						if ( isset( $current[ $key ] ) ) {
							$current = $current[ $key ];
						}

						elseif ( isset( $current[ strtolower( $key ) ] ) ) {
							// Handle case-insensitive keys
							$current = $current[ strtolower( $key ) ];
						}

						else {
							// If we can't find the key, return 1 page
							return 1;
						}

					} elseif ( is_object( $current ) ) {
						// Handle object
						if ( isset( $current->$key ) ) {
							$current = $current->$key;
						}

						elseif ( isset( $current->{ strtolower( $key ) } ) ) {
							$current = $current->{ strtolower( $key ) };
						}

						// Object is special, maybe can access like an array
						elseif ( isset( $current[ $key ] ) ) {
							$current = $current[ $key ];
						}

						else {
							// If we can't find the key, return 1 page
							return 1;
						}
					} else {
						// If we can't find the key, return 1 page
						return 1;
					}
				}

				// If we found a numeric value, set it as total pages
				if ( is_numeric( $current ) ) {
					// Handle different pagination methods
					$pagination_method = $this->settings['pagination_method'] ?? 'page';

					if ( $pagination_method === 'offset' ) {
						// For offset pagination, calculate pages based on items per page
						$items_per_page = $this->get_items_per_page();
						$total_items    = max( 0, (int) $current );

						if ( $items_per_page > 0 ) {
							$total_pages = max( 1, (int) ceil( $total_items / $items_per_page ) );
						} else {
							// If we can't determine items per page, log error and return 1
							Helpers::maybe_log(
								sprintf(
									esc_html__( 'Unable to calculate total pages for offset pagination. Items per page not found for element "%s".', 'bricks' ),
									$this->element_id
								)
							);
							$total_pages = 1; // Default to 1 page
						}
					} else {
						// For page number pagination method, the total already be pages
						$total_pages = max( 1, (int) $current );
					}
				} else {
					// If the value is not numeric, log an error
					Helpers::maybe_log(
						sprintf(
							esc_html__( 'Invalid total pages value "%1$s" extracted from path "%2$s". Expected a numeric value.', 'bricks' ),
							$current,
							$total_path
						)
					);
					$total_pages = 1; // Default to 1 page
				}
			}
		}

		return apply_filters( 'bricks/query_api/total_pages', $total_pages, $this->element_id, $this );
	}

	/**
	 * Get items per page for offset pagination
	 *
	 * @return int Items per page, or 0 if not found
	 */
	private function get_items_per_page() {
		// Check if pagination is enabled
		if ( ! isset( $this->settings['pagination_enabled'] ) || ! $this->settings['pagination_enabled'] ) {
			return 0; // No pagination enabled
		}
			$pagination_method = $this->settings['pagination_method'] ?? 'page';

		if ( $pagination_method !== 'offset' ) {
			return 0;
		}

		$offset_key          = isset( $this->settings['pagination_page_offset_key'] ) ? trim( $this->settings['pagination_page_offset_key'] ) : '';
		$offset_key_location = $this->settings['pagination_offset_location'] ?? 'query';

		if ( empty( $offset_key ) ) {
			return 0;
		}

		$items_per_page = 0;

		switch ( $offset_key_location ) {
			case 'query':
				$params = $this->get_params_settings();
				// Find the array where matching key exists
				foreach ( $params as $key => $value ) {
					if ( strtolower( $key ) === strtolower( $offset_key ) ) {
						$items_per_page = absint( $value );
						break;
					}
				}
				break;

			case 'header':
				$headers = $this->get_headers_settings();
				// Find the array where matching key exists
				foreach ( $headers as $key => $value ) {
					if ( strtolower( $key ) === strtolower( $offset_key ) ) {
						$items_per_page = absint( $value );
						break;
					}
				}
				break;
		}

		return $items_per_page;
	}

	public function set_pagination( $page ) {
		if ( ! isset( $this->settings['pagination_enabled'] ) || ! $this->settings['pagination_enabled'] ) {
			return $this; // No pagination enabled
		}

		// Set the page number for pagination
		$this->current_page = absint( $page );

		return $this;
	}

	private function build_pagination_args( $location = 'query' ) {
		$pagination_args = [];

		if ( ! isset( $this->settings['pagination_enabled'] ) || ! $this->settings['pagination_enabled'] ) {
			return $pagination_args; // No pagination enabled
		}

		$pagination_location = $this->settings['pagination_param_location'] ?? 'query';
		$pagination_key      = isset( $this->settings['pagination_page_param'] ) ? trim( $this->settings['pagination_page_param'] ) : '';
		$pagination_method   = $this->settings['pagination_method'] ?? 'page';

		// Early return if location doesn't match
		if ( $location !== $pagination_location ) {
			return $pagination_args;
		}

		// Early return if no pagination key is set
		if ( empty( $pagination_key ) ) {
			return $pagination_args;
		}

		if ( $pagination_method === 'offset' ) {
			// If using offset, get the offset key we need to retrieve
			$offset_key = isset( $this->settings['pagination_page_offset_key'] ) ? trim( $this->settings['pagination_page_offset_key'] ) : '';

			if ( ! empty( $offset_key ) ) {
				$items_per_page = $this->get_items_per_page();

				if ( $items_per_page > 0 ) {
					// Calculate the offset based on current page and items per page
					$offset = ( $this->current_page - 1 ) * $items_per_page;

					// Add to pagination args
					if ( ! empty( $pagination_key ) ) {
						$pagination_args[ sanitize_text_field( $pagination_key ) ] = $offset;
					}
				} else {
					$this->last_error = esc_html__( 'Unable to determine items per page for offset pagination. Check offset key settings.', 'bricks' );
				}
			}
		} else {
			// Default pagination method is 'page'
			if ( ! empty( $pagination_key ) ) {
				$pagination_args[ sanitize_text_field( $pagination_key ) ] = $this->current_page;
			}
		}

		return $pagination_args;
	}

	/**
	 * Check if API call was successful
	 */
	public function is_success() {
		return isset( $this->response['extracted_data'] ) && ! isset( $this->response['error'] );
	}

	/**
	 * Get response headers
	 */
	public function get_headers() {
		return $this->response['headers'] ?? [];
	}

	/**
	 * Get full response array
	 */
	public function get_response() {
		return $this->response;
	}

	/**
	 * Static method for quick requests (currently use by the builder in ajax.php)
	 */
	public static function make_request( $settings = [], $element_id = null, $post_id = 0, $clear_element_cache = false ) {
		$api = new self( $settings, $element_id, $post_id );
		if ( $clear_element_cache ) {
			$api->clear_element_cache();
		}
		return $api->request();
	}

	// Private methods for building the request
	private function get_endpoint() {
		return isset( $this->settings['api_url'] ) && ! empty( $this->settings['api_url'] )
			? trim( $this->settings['api_url'] )
			: '';
	}

	private function get_method() {
		return isset( $this->settings['api_method'] ) && ! empty( $this->settings['api_method'] )
			? strtoupper( trim( $this->settings['api_method'] ) )
			: 'GET';
	}

	private function build_request_args() {
		$method  = $this->get_method();
		$headers = $this->build_headers();

		$args = [
			'method'    => $method,
			'headers'   => $headers,
			'sslverify' => false, // Default false
			'timeout'   => 30, // Default timeout
		];

		// Add body for POST/PUT requests
		if ( in_array( $method, [ 'POST' ] ) ) {
			$body = $this->build_body();
			if ( $body ) {
				$args['body'] = $body;
			}
		}

		return apply_filters(
			'bricks/query_api/request_args',
			$args,
			$this->element_id,
			$this
		);
	}


	private function build_headers() {
		$default_headers = [
			'Content-Type' => 'application/json',
			'User-Agent'   => 'BricksBuilder/' . BRICKS_VERSION
		];

		$custom_headers     = $this->get_headers_settings();
		$auth_headers       = $this->build_auth_args( 'header' );
		$pagination_headers = $this->build_pagination_args( 'header' );

		$headers = array_merge( $default_headers, $custom_headers, $auth_headers, $pagination_headers );

		return apply_filters(
			'bricks/query_api/headers',
			$headers,
			$this->element_id,
			$this
		);
	}

	/**
	 * Retrieve headers from settings
	 */
	private function get_headers_settings() {
		$processed = [];

		$headers = $this->settings['api_headers'] ?? [];

		if ( ! is_array( $headers ) ) {
			return $processed;
		}

		foreach ( $headers as $header ) {
			if ( is_array( $header ) && isset( $header['key'], $header['value'] ) ) {
				$key   = sanitize_text_field( trim( $header['key'] ) );
				$value = sanitize_text_field( trim( $header['value'] ) );

				if ( ! empty( $key ) && ! empty( $value ) ) {
					$processed[ $key ] = $value;
				}
			}
		}

		return $processed;
	}

	/**
	 * Build authentication arguments based on settings
	 *
	 * @param string $location Where the auth should be applied (header or query)
	 * @return array Auth arguments
	 */
	private function build_auth_args( $location = 'header' ) {
		$auth_args     = [];
		$auth_type     = $this->settings['api_auth_type'] ?? 'none';
		$auth_location = $this->settings['api_auth_api_location'] ?? 'header';

		switch ( $auth_type ) {
			case 'apiKey':
				$api_key_name = isset( $this->settings['api_auth_api_key_name'] ) ? trim( $this->settings['api_auth_api_key_name'] ) : '';
				$use_constant = isset( $this->settings['api_auth_api_key_use_constant'] ) && $this->settings['api_auth_api_key_use_constant'];

				if ( $use_constant ) {
					$constant_key  = 'BRX_QUERY_API_KEY_' . strtoupper( $this->element_id );
					$api_key_value = defined( $constant_key ) ? constant( $constant_key ) : '';

					if ( ! empty( $api_key_name ) && ! empty( $api_key_value ) ) {
						$auth_args[ sanitize_text_field( $api_key_name ) ] = sanitize_text_field( $api_key_value );
					}
				}

				else {
					$api_key_value = isset( $this->settings['api_auth_api_key_value'] ) ? trim( $this->settings['api_auth_api_key_value'] ) : '';

					if ( ! empty( $api_key_name ) && ! empty( $api_key_value ) ) {
						$auth_args[ sanitize_text_field( $api_key_name ) ] = sanitize_text_field( $api_key_value );
					}
				}
				break;

			case 'bearer':
				$use_constant = isset( $this->settings['api_auth_bearer_use_constant'] ) && $this->settings['api_auth_bearer_use_constant'];

				if ( $use_constant ) {
					$constant_key = 'BRX_QUERY_BEARER_TOKEN_' . strtoupper( $this->element_id );
					$token        = defined( $constant_key ) ? constant( $constant_key ) : '';

					if ( ! empty( $token ) ) {
						$auth_args['Authorization'] = 'Bearer ' . sanitize_text_field( $token );
					}
				}

				else {
					$token = isset( $this->settings['api_auth_bearer_token'] ) ? trim( $this->settings['api_auth_bearer_token'] ) : '';
					if ( ! empty( $token ) ) {
						$auth_args['Authorization'] = 'Bearer ' . sanitize_text_field( $token );
					}
				}

				break;

			case 'basic':
				$use_constant = isset( $this->settings['api_auth_basic_use_constant'] ) && $this->settings['api_auth_basic_use_constant'];

				if ( $use_constant ) {
					$username_constant = 'BRX_QUERY_BASIC_AUTH_USERNAME_' . strtoupper( $this->element_id );
					$password_constant = 'BRX_QUERY_BASIC_AUTH_PASSWORD_' . strtoupper( $this->element_id );

					$username = defined( $username_constant ) ? constant( $username_constant ) : '';
					$password = defined( $password_constant ) ? constant( $password_constant ) : '';

					if ( ! empty( $username ) && ! empty( $password ) ) {
						$auth_args['Authorization'] = 'Basic ' . base64_encode(
							sanitize_text_field( $username ) . ':' . sanitize_text_field( $password )
						);
					}
				}

				else {
					$username = isset( $this->settings['api_auth_basic_username'] ) ? trim( $this->settings['api_auth_basic_username'] ) : '';
					$password = isset( $this->settings['api_auth_basic_password'] ) ? trim( $this->settings['api_auth_basic_password'] ) : '';

					if ( ! empty( $username ) && ! empty( $password ) ) {
						$auth_args['Authorization'] = 'Basic ' . base64_encode(
							sanitize_text_field( $username ) . ':' . sanitize_text_field( $password )
						);
					}
				}
				break;
		}

		// Only return auth args if they match the requested location
		return $location === $auth_location
			? $auth_args
			: [];

	}

	private function build_endpoint_with_params( $endpoint ) {
		$query_params = $this->build_query_params();
		$auth_params  = $this->build_auth_args( 'query' );

		// Merge auth params into query params
		$query_params = array_merge( $query_params, $auth_params );

		if ( empty( $query_params ) ) {
			return $endpoint;
		}

		$parsed_url = wp_parse_url( $endpoint );

		// Get existing query parameters
		$existing_params = [];
		if ( isset( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $existing_params );
		}

		// Merge with new parameters
		$all_params = array_merge( $existing_params, $query_params );

		// Build new query string
		$query_string = http_build_query( $all_params, '', '&', PHP_QUERY_RFC3986 );

		// Reconstruct URL
		$base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];

		if ( isset( $parsed_url['port'] ) ) {
			$base_url .= ':' . $parsed_url['port'];
		}

		if ( isset( $parsed_url['path'] ) ) {
			$base_url .= $parsed_url['path'];
		}

		return $base_url . ( $query_string ? '?' . $query_string : '' );
	}

	private function build_query_params() {
		// Get query parameters from settings
		$params = $this->get_params_settings();

		// Process pagination parameters if pagination is enabled
		$pagination_params = $this->build_pagination_args( 'query' );
		if ( ! empty( $pagination_params ) && is_array( $pagination_params ) ) {
			$params = array_merge( $params, $pagination_params );
		}

		return $params;
	}

	private function get_params_settings() {
		$processed = [];

		$api_params = $this->settings['api_params'] ?? [];

		if ( ! is_array( $api_params ) ) {
			return $processed;
		}

		foreach ( $api_params as $param ) {
			if ( is_array( $param ) && isset( $param['key'], $param['value'] ) ) {
				$key   = sanitize_text_field( trim( $param['key'] ) );
				$value = sanitize_text_field( trim( $param['value'] ) );

				if ( ! empty( $key ) && ! empty( $value ) ) {
					$processed[ $key ] = $value;
				}
			}
		}

		return $processed;
	}

	private function process_param_value( $value ) {
		// Handle arrays
		if ( is_array( $value ) ) {
			// Sanitize and trim
			$value = array_map(
				function( $item ) {
					return sanitize_text_field( trim( $item ) );
				},
				$value
			);

			// Convert array to comma-separated string
			$value = implode( ',', $value );
		}

		// Handle strings
		if ( is_string( $value ) ) {
			$value = sanitize_text_field( trim( $value ) );
		}

		return $value;
	}

	private function build_body() {
		$method = $this->get_method();

		if ( ! in_array( $method, [ 'POST' ] ) ) {
			return null;
		}

		$body_data = null;

		// Get body type from settings
		$body_type  = $this->settings['api_body_type'] ?? 'none';
		$pagination = $this->build_pagination_args( 'body' );

		// Handle different body types
		switch ( $body_type ) {
			case 'json':
				$raw_body = $this->settings['api_body_json'] ?? '';
				if ( ! empty( $raw_body ) ) {
					$body_data = $raw_body;
				}

				// If pagination is enabled, add it to the body
				if ( ! empty( $pagination ) ) {
					// Decode JSON to add pagination
					$body_data = json_decode( $body_data, true );
					if ( is_array( $body_data ) ) {
						$body_data = array_merge( $body_data, $pagination );
					} else {
						// If body_data is not an array, just append pagination
						$body_data = json_encode( array_merge( [ 'pagination' => $pagination ], $body_data ) );
					}

					// Re-encode to JSON
					$body_data = wp_json_encode( $body_data );
				}

				break;

			case 'formData':
			case 'xwwwformurlencoded':
				// Use api_body_params repeater
				$body_params = $this->settings['api_body_params'] ?? [];

				// Maybe add pagination to body
				if ( ! empty( $pagination ) && is_array( $body_params ) ) {
					$body_params = array_merge( $body_params, $pagination );
				}

				if ( is_array( $body_params ) ) {
					foreach ( $body_params as $param ) {
						if ( isset( $param['key'], $param['value'] ) && ! empty( $param['key'] ) ) {
							$key               = sanitize_text_field( $param['key'] );
							$value             = $this->process_param_value( $param['value'] );
							$body_data[ $key ] = $value;
						}
					}
				}

				if ( ! empty( $body_data ) ) {
					// Both formData and xwwwformurlencoded should use http_build_query
					$body_data = http_build_query( $body_data );
				}
				break;
		}

		return apply_filters(
			'bricks/query_api/body',
			$body_data,
			$this->element_id,
			$this
		);
	}

	private function process_response( $response ) {
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		// Check for HTTP errors
		if ( $status_code >= 400 ) {
			return [
				'error'      => sprintf( esc_html__( 'HTTP %1$d: %2$s' ), $status_code, wp_remote_retrieve_response_message( $response ) ),
				'status'     => $status_code,
				'headers'    => $headers,
				'last_fetch' => time(),
			];
		}

		if ( empty( $body ) ) {
			return [
				'error'      => esc_html__( 'Empty response from the API.', 'bricks' ),
				'status'     => $status_code,
				'headers'    => $headers,
				'last_fetch' => time(),
			];
		}

		// Try to decode JSON
		$full_data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [
				'error'      => esc_html__( 'JSON decode error: ' ) . json_last_error_msg(),
				'status'     => $status_code,
				'headers'    => $headers,
				'raw_body'   => $body,
				'last_fetch' => time(),
			];
		}

		// Handle response path (extract nested data)
		$extracted_data = $full_data; // Default to full data
		$response_path  = $this->settings['response_path'] ?? '';

		if ( ! empty( $response_path ) && is_array( $full_data ) ) {
			$extracted_data = $this->extract_data_by_path( $full_data, $response_path );
		}

		return [
			'full_response'  => $full_data,          // Full API response
			'extracted_data' => $extracted_data,     // Extracted data (explicit)
			'response_path'  => $response_path,      // The path used for extraction
			'status'         => $status_code,
			'headers'        => $headers,
			'last_fetch'     => time(),
		];
	}

	/**
	 * Extract data from the response based on a dot notation path
	 *
	 * @param array  $data The full response data
	 * @param string $path The dot notation path to extract data from
	 * @return mixed The extracted data or original data if path not found
	 */
	private function extract_data_by_path( $data, $path ) {
		if ( empty( $path ) ) {
			return $data;
		}

		$keys    = explode( '.', $path );
		$current = $data;

		foreach ( $keys as $key ) {
			if ( is_array( $current ) && isset( $current[ $key ] ) ) {
				$current = $current[ $key ];
			} else {
				// Path not found, return original data
				return $data;
			}
		}

		return $current;
	}

	/**
	 * Generate unique cache key based on all request parameters
	 */
	private function generate_cache_key() {
		$endpoint = $this->get_endpoint();
		$method   = $this->get_method();
		$headers  = $this->build_headers();
		$params   = $this->build_query_params();
		$body     = $this->build_body();

		$params_hash = md5(
			wp_json_encode(
				[
					'endpoint' => $endpoint,
					'method'   => $method,
					'headers'  => $headers,
					'params'   => $params,
					'body'     => $body,
				]
			)
		);

		// Create a unique hash based on all request components
		$cache_key = "bricks_query_api_{$this->element_id}_{$params_hash}";

		return $cache_key;
	}

	/**
	 * Get cached response
	 */
	private function get_cache() {
		if ( ! $this->cache_enabled ) {
			return false;
		}

		if ( $this->cache_key === null ) {
			$this->cache_key = $this->generate_cache_key();
		}

		return get_transient( $this->cache_key );
	}


	/**
	 * Store response in cache
	 */
	private function set_cache( $data ) {
		if ( ! $this->cache_enabled ) {
			return;
		}

		if ( $this->cache_key === null ) {
			$this->cache_key = $this->generate_cache_key();
		}

		set_transient( $this->cache_key, $data, $this->cache_duration );
	}

	/**
	 * Clear cache for this request
	 */
	public function clear_cache() {
		if ( $this->cache_key === null ) {
			$this->cache_key = $this->generate_cache_key();
		}

		delete_transient( $this->cache_key );
	}

	 /**
	  * Clear all cache for this element
	  */
	public function clear_element_cache() {
		if ( ! $this->element_id ) {
				return;
		}

			global $wpdb;

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options}
						WHERE option_name LIKE %s
						OR option_name LIKE %s",
					"_transient_bricks_query_api_{$this->element_id}_%",
					"_transient_timeout_bricks_query_api_{$this->element_id}_%"
				)
			);
	}

	/**
	 * Check if response is cached
	 */
	public function is_cached() {
		return $this->get_cache() !== false;
	}

	/**
	 * Clear all Query API caches
	 */
	public static function clear_all_caches() {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_bricks_query_api_%'
			 OR option_name LIKE '_transient_timeout_bricks_query_api_%'"
		);
	}
}
