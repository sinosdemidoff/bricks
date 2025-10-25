<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Responsible for handling the custom redirection logic for authentication-related pages.
 *
 * Login page
 * Registration page
 * Lost password page
 * Reset password page
 *
 * @since 1.9.2
 */
class Auth_Redirects {
	public function __construct() {
		add_action( 'wp_loaded', [ $this, 'handle_auth_redirects' ] );
		add_action( 'wp_login', [ $this, 'clear_bypass_auth_cookie' ] );
		add_filter( 'retrieve_password_message', [ $this, 'modify_reset_password_email' ], 10, 4 );
		add_filter( 'logout_redirect', [ $this, 'handle_logout_redirect' ], 10, 3 ); // @since 1.12.2

		// If user activation is enabled (@since 2.1)
		if ( Database::get_setting( 'userActivationEnabled' ) ) {

			// Check user activation status on login
			add_filter( 'authenticate', [ $this, 'check_user_activation_status' ], 999, 3 );

			// Send activation email on user registration
			add_action( 'user_register', [ $this, 'on_user_registration' ], 10, 1 );
		}
	}

	/**
	 * Main function to handle authentication redirects
	 *
	 * Depending on the current URL and the action parameter, decides which page to redirect to.
	 */
	public function handle_auth_redirects() {
		// Is a WooCommerce auth page: Let WooCommerce handle its own auth pages (@since 1.11)
		if ( $this->is_woocommerce_auth_page() ) {
			return;
		}

		/**
		 * STEP: Set the bypass cookie (expires in 5 minutes)
		 *
		 * If the 'use_default_wp' URL parameter is set and the Global setting 'brx_use_wp_login' is not disabled.
		 *
		 * @since 1.9.4
		 */
		if ( isset( $_GET['brx_use_wp_login'] ) && ! Database::get_setting( 'disable_brx_use_wp_login' ) ) {
			setcookie(
				'brx_use_wp_login',
				'1',
				[
					'expires'  => time() + 5 * 60, // Expires in 5 minutes
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Strict',
				]
			);
		}

		// STEP: Check if the bypass cookie is set, and if so, bypass redirects (@since 1.9.4)
		if ( isset( $_COOKIE['brx_use_wp_login'] ) && $_COOKIE['brx_use_wp_login'] === '1' ) {
			return;
		}

		$request_uri      = esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' );
		$current_url_path = wp_parse_url( home_url( $request_uri ), PHP_URL_PATH );

		$wp_login_url_path         = wp_parse_url( wp_login_url(), PHP_URL_PATH );
		$wp_registration_url_path  = wp_parse_url( wp_registration_url(), PHP_URL_PATH );
		$wp_lost_password_url_path = wp_parse_url( wp_lostpassword_url(), PHP_URL_PATH );

		// Get the home path
		$home_path = wp_parse_url( home_url(), PHP_URL_PATH );

		// Fallback to '/' if home path is empty to prevent preg_quote error (e.g. URL ends with port number)
		if ( ! $home_path ) {
			$home_path = '/';
		}

		// Remove home path from request URI
		$current_url_path = preg_replace( '/^' . preg_quote( $home_path, '/' ) . '/', '', $request_uri );

		// Remove query string if present
		$current_url_path = strtok( $current_url_path, '?' );

		// Normalize paths by trimming trailing slashes (@since 1.12.2)
		$current_url_path          = rtrim( $current_url_path, '/' );
		$wp_login_url_path         = rtrim( $wp_login_url_path, '/' );
		$wp_registration_url_path  = rtrim( $wp_registration_url_path, '/' );
		$wp_lost_password_url_path = rtrim( $wp_lost_password_url_path, '/' );

		// Also remove home path from WordPress URLs
		$wp_login_url_path         = preg_replace( '/^' . preg_quote( $home_path, '/' ) . '/', '', $wp_login_url_path );
		$wp_registration_url_path  = preg_replace( '/^' . preg_quote( $home_path, '/' ) . '/', '', $wp_registration_url_path );
		$wp_lost_password_url_path = preg_replace( '/^' . preg_quote( $home_path, '/' ) . '/', '', $wp_lost_password_url_path );

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : null;

		// STEP: Filter to allow custom logic for redirects
		$custom_redirect_url = apply_filters( 'bricks/auth/custom_redirect_url', null, $current_url_path );

		if ( ! is_null( $custom_redirect_url ) ) {
			wp_safe_redirect( $custom_redirect_url );
			exit;
		}

		// STEP: Check WordPress authentication URL (@since 1.11)
		$wp_auth_url_behavior = Database::get_setting( 'wp_auth_url_behavior', 'default' );

		// Login page & actions
		if ( strpos( $current_url_path, $wp_login_url_path ) === 0 ) {
			switch ( $action ) {
				case null:
				case 'login':
					$this->handle_login_redirect( $wp_auth_url_behavior );
					break;

				case 'lostpassword':
					$this->handle_lost_password_redirect( $wp_auth_url_behavior );
					break;

				case 'register':
					$this->handle_registration_redirect( $wp_auth_url_behavior );
					break;

				case 'rp': // Reset password
					$this->handle_reset_password_redirect( $wp_auth_url_behavior );
					break;

				case 'logout':
					$this->handle_logout_behavior( $wp_auth_url_behavior );
					break;

				default:
					// Handle unrecognized actions (@since 1.12)
					$this->handle_custom_behavior( $wp_auth_url_behavior );
					break;
			}
		}

		// Registration page fallback
		elseif ( $current_url_path === $wp_registration_url_path ) {
			$this->handle_registration_redirect( $wp_auth_url_behavior );
		}

		// Lost password page fallback
		elseif ( $current_url_path === $wp_lost_password_url_path ) {
			$this->handle_lost_password_redirect( $wp_auth_url_behavior );
		}
	}

	/**
	 * Handle custom behavior for WordPress auth URLs.
	 *
	 * @since 1.11
	 *
	 * @param string $behavior The selected behavior: 404/home/custom.
	 */
	private function handle_custom_behavior( $behavior ) {
		switch ( $behavior ) {
			case '404':
				global $wp_query;
				$wp_query->set_404();
				\Bricks\Database::set_active_templates();
				status_header( 404 );
				get_template_part( 404 );
				exit;
			case 'home':
				wp_safe_redirect( home_url() );
				exit;
			case 'custom':
				$redirect_page_id = Database::get_setting( 'wp_auth_url_redirect_page' );
				if ( $redirect_page_id && $this->is_custom_page_valid( $redirect_page_id ) ) {
					wp_safe_redirect( get_permalink( $redirect_page_id ) );
					exit;
				}
				// Fallback to home if no valid page is set
				wp_safe_redirect( home_url() );
				exit;
		}
	}

	/**
	 * Handle logout specific behavior
	 *
	 * @since 1.12.2
	 *
	 * @param string $behavior The selected behavior: 404/home/custom.
	 */
	private function handle_logout_behavior( $behavior ) {
		// Let WordPress handle the actual logout process (otherwise the user might not be logged out due to wp_auth_url_redirect_page)
		// Our logout_redirect filter will handle the redirect for logged-in users
		if ( is_user_logged_in() ) {
			return;
		}

		// For non-logged-in users, follow the custom behavior
		$this->handle_custom_behavior( $behavior );
	}

	/**
	 * Handle login page redirect
	 *
	 * @since 1.11
	 *
	 * @param string $behavior The selected behavior.
	 */
	private function handle_login_redirect( $behavior ) {
		$custom_login_page = Database::get_setting( 'login_page' );
		if ( $custom_login_page && $behavior !== 'default' ) {
			$this->handle_custom_behavior( $behavior );
		} else {
			$this->redirect_to_custom_login_page();
		}
	}

	/**
	 * Handle registration page redirect
	 *
	 * @since 1.11
	 *
	 * @param string $behavior The selected behavior.
	 */
	private function handle_registration_redirect( $behavior ) {
		$custom_registration_page = Database::get_setting( 'registration_page' );
		if ( $custom_registration_page && $behavior !== 'default' ) {
			$this->handle_custom_behavior( $behavior );
		} else {
			$this->redirect_to_custom_registration_page();
		}
	}

	/**
	 * Handle lost password page redirect
	 *
	 * @since 1.11
	 *
	 * @param string $behavior The selected behavior.
	 */
	private function handle_lost_password_redirect( $behavior ) {
		$custom_lost_password_page = Database::get_setting( 'lost_password_page' );
		if ( $custom_lost_password_page && $behavior !== 'default' ) {
			$this->handle_custom_behavior( $behavior );
		} else {
			$this->redirect_to_custom_lost_password_page();
		}
	}

	/**
	 * Handle reset password page redirect
	 *
	 * @since 1.11
	 *
	 * @param string $behavior The selected behavior.
	 */
	private function handle_reset_password_redirect( $behavior ) {
		$custom_reset_password_page = Database::get_setting( 'reset_password_page' );
		if ( $custom_reset_password_page && $behavior !== 'default' ) {
			$this->handle_custom_behavior( $behavior );
		} else {
			$this->redirect_to_custom_reset_password_page();
		}
	}

	/**
	 * Clears the bypass cookie when the user logs in.
	 */
	public function clear_bypass_auth_cookie() {
		if ( isset( $_COOKIE['brx_use_wp_login'] ) ) {
			   // Ensure the path and domain match where the cookie was set
			setcookie(
				'brx_use_wp_login',
				'',
				[
					'expires'  => time() - 3600,
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Strict'
				]
			);

			unset( $_COOKIE['brx_use_wp_login'] );
		}
	}

	/**
	 * Redirects to the custom login page if it's set and valid.
	 */
	private function redirect_to_custom_login_page() {
		$selected_login_page_id = Database::get_setting( 'login_page' );

		 // Filter for the login page redirect
		$selected_login_page_id = apply_filters( 'bricks/auth/custom_login_redirect', $selected_login_page_id );

		$this->redirect_if_valid_page( $selected_login_page_id );
	}

	/**
	 * Redirects to the custom lost password page if it's set and valid.
	 */
	private function redirect_to_custom_lost_password_page() {
		$selected_lost_password_page_id = Database::get_setting( 'lost_password_page' );

		// Filter for the lost password page redirect
		$selected_lost_password_page_id = apply_filters( 'bricks/auth/custom_lost_password_redirect', $selected_lost_password_page_id );

		$this->redirect_if_valid_page( $selected_lost_password_page_id );
	}

	/**
	 * Redirects to the custom registration page if it's set and valid.
	 */
	private function redirect_to_custom_registration_page() {
		$selected_registration_page_id = Database::get_setting( 'registration_page' );

		// Filter for the registration page redirect
		$selected_registration_page_id = apply_filters( 'bricks/auth/custom_registration_redirect', $selected_registration_page_id );

		$this->redirect_if_valid_page( $selected_registration_page_id );
	}

	/**
	 * Redirects to the custom reset password page if it's set and valid.
	 */
	private function redirect_to_custom_reset_password_page() {
		$selected_reset_password_page_id = Database::get_setting( 'reset_password_page' );

		// Filter for the reset password page redirect
		$selected_reset_password_page_id = apply_filters( 'bricks/auth/custom_reset_password_redirect', $selected_reset_password_page_id );

		$this->redirect_if_valid_page( $selected_reset_password_page_id );
	}

	/**
	 * Helper function to redirect to the provided page if it's valid.
	 * If the page is not valid, redirects to a default URL if provided.
	 *
	 * @param int $selected_page_id The ID of the page to redirect to.
	 */
	private function redirect_if_valid_page( $selected_page_id ) {
		if ( $this->is_custom_page_valid( $selected_page_id ) ) {
			$custom_url = get_permalink( $selected_page_id );

			// Preserve query parameters
			if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
				$parameters = $_GET;
				if ( is_array( $parameters ) ) {
					// Sanitize all parameters
					foreach ( $parameters as $key => $value ) {
						$sanitized_value = Helpers::sanitize_value( $value );
						// Encode the value to ensure it's URL-safe (@since 1.12.3)
						$custom_url = add_query_arg( $key, rawurlencode( $sanitized_value ), $custom_url );
					}
				}
			}

			if ( $custom_url ) {
				wp_safe_redirect( $custom_url );
				exit;
			}
		}
	}

	/**
	 * Checks if the custom page is valid.
	 *
	 * @param int $page_id
	 *
	 * @return bool
	 */
	private function is_custom_page_valid( $page_id ) {
		return $page_id && get_post_status( $page_id ) === 'publish';
	}

	/**
	 * Modifies the password reset email to use the custom reset password page URL.
	 *
	 * This modification only occurs if:
	 * 1. A custom reset password page is set
	 * 2. The WordPress auth URL behavior is not set to default
	 *
	 * @since 1.11
	 *
	 * @param string $message    The current email message.
	 * @param string $key        The password reset key.
	 * @param string $user_login The username for the user.
	 * @param object $user_data  WP_User object.
	 *
	 * @return string The modified email message.
	 */
	public function modify_reset_password_email( $message, $key, $user_login, $user_data ) {
		$custom_reset_page_id = Database::get_setting( 'reset_password_page' );

		if ( $custom_reset_page_id &&
			$this->is_custom_page_valid( $custom_reset_page_id ) ) {
			// Replace the network URL with the current site URL in the message (@since 1.12.2)
			$network_url      = network_site_url( 'wp-login.php' );
			$custom_reset_url = get_permalink( $custom_reset_page_id );

			// Replace the login URL while preserving query parameters
			$message = str_replace( $network_url, $custom_reset_url, $message );
		}

		return $message;
	}

	/**
	 * Check if the current page is a WooCommerce auth page
	 *
	 * @since 1.11
	 * @return bool
	 */
	private function is_woocommerce_auth_page() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		$current_url = esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' );

		// Check for WooCommerce-specific endpoints in the URL
		$woo_endpoints = [
			wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ),
			add_query_arg( [ 'show-reset-form' => 'true' ], wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ),
			wc_get_endpoint_url( 'customer-logout', '', wc_get_page_permalink( 'myaccount' ) ),
		];

		foreach ( $woo_endpoints as $endpoint ) {
			$endpoint_path = wp_parse_url( $endpoint, PHP_URL_PATH );
			if ( $endpoint_path && strpos( $current_url, $endpoint_path ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle redirect after successful logout
	 *
	 * @since 1.12.2
	 *
	 * @param string           $redirect_to           The redirect destination URL.
	 * @param string           $requested_redirect_to The requested redirect destination URL.
	 * @param WP_User|WP_Error $user                  The logged out user.
	 * @return string
	 */
	public function handle_logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
		// Check if WordPress is trying to redirect to the login page
		$wp_login_url = wp_login_url();
		if ( strpos( $redirect_to, $wp_login_url ) === 0 ) {
			// If we have a custom login page, use that instead (to get around wp_auth_url_behavior)
			$custom_login_page = Database::get_setting( 'login_page' );
			if ( $custom_login_page && $this->is_custom_page_valid( $custom_login_page ) ) {
				return get_permalink( $custom_login_page ) . '?logged_out=true';
			}
		}

		return $redirect_to;
	}


	/**
	 * Check user activation status (filter)
	 *
	 * @since 2.1
	 */
	public function check_user_activation_status( $user, $username, $password ) {

		// If there are errors, retun them
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// Check if user activation is enabled
		if ( ! Database::get_setting( 'userActivationEnabled' ) ) {
			return $user;
		}

		// Check  if the user is valid
		// If the user is not valid, return it
		if ( ! $user || ! is_a( $user, 'WP_User' ) || ! $user->exists() ) {
			return $user;
		}

		$user_status = get_user_meta( $user->ID, 'bricks_user_activation_status', true );

		// To allow users to log in, the user status can be 'active' or empty (empty means the user was there before the activation was added)
		if ( $user_status === 'active' || empty( $user_status ) ) {
			return $user;
		}

		// User is not activated: Show login error message
		$error_message = '<strong>' . __( 'Error', 'bricks' ) . ':</strong> ' . __( 'Your account is inactive', 'bricks' ) . '</strong>';

		// Set the error
		$user = new \WP_Error( 'user_activation_error', $error_message );

		return $user;
	}

	/**
	 * Send activation email on user registration
	 *
	 * @since 2.1
	 */
	public function on_user_registration( $user_id ) {

		Helpers::set_activation_meta( $user_id );
		\Bricks\Helpers::send_user_activation_email( $user_id, 'activation' );
	}

}
