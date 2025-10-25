<?php
namespace Bricks\Integrations\Form\Actions;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Webhook extends Base {
	/**
	 * Webhook action
	 *
	 * @since 2.0
	 */
	public function run( $form ) {
		$form_settings = $form->get_settings();
		$form_fields   = $form->get_fields();
		$form_id       = $form->get_id();
		$post_id       = $form->get_post_id();

		// No webhooks configured
		if ( empty( $form_settings['webhooks'] ) || ! is_array( $form_settings['webhooks'] ) ) {
			return;
		}

		$has_errors = false;

		// Process each webhook endpoint
		foreach ( $form_settings['webhooks'] as $webhook ) {
			// Render dynamic data for the webhook URL (@since 2.0)
			$webhook['url'] = ! empty( $webhook['url'] ) ? $form->render_data( $webhook['url'] ) : '';

			if ( empty( $webhook['url'] ) || ! wp_http_validate_url( $webhook['url'] ) ) {
				$error_message = esc_html__( 'Invalid webhook URL.', 'bricks' );
				\Bricks\Helpers::maybe_log( "Bricks form webhook error: $error_message (Post ID: $post_id; Form ID: $form_id )" );
				$has_errors = true;
				continue;
			}

			// Check rate limiting if enabled
			if ( ! empty( $form_settings['webhookRateLimit'] ) ) {
				$requests_limit = ! empty( $form_settings['webhookRateLimitRequests'] ) ? absint( $form_settings['webhookRateLimitRequests'] ) : 60;
				$transient_key  = 'bricks_webhook_' . md5( $form_id . '_' . $webhook['url'] );
				$current_count  = get_transient( $transient_key );

				if ( false === $current_count ) {
					set_transient( $transient_key, 1, HOUR_IN_SECONDS );
				} elseif ( $current_count >= $requests_limit ) {
					$error_message = esc_html__( 'Rate limit exceeded. Please try again later.', 'bricks' );
					\Bricks\Helpers::maybe_log( "Bricks form webhook error: $error_message (Post ID: $post_id; Form ID: $form_id; Webhook URL: {$webhook['url']})" );
					$has_errors = true;
					continue;
				} else {
					set_transient( $transient_key, $current_count + 1, HOUR_IN_SECONDS );
				}
			}

			// Prepare headers based on content type
			$content_type = $webhook['contentType'] ?? 'json';
			$headers      = [
				'Content-Type' => $content_type === 'json' ? 'application/json' : 'application/x-www-form-urlencoded',
			];

			// Add custom headers if provided
			if ( ! empty( $webhook['headers'] ) ) {
				try {
					$custom_headers = $webhook['headers'];
					$custom_headers = $form->render_data( $custom_headers ); // Render dynamic data (@since 2.1)

					$custom_headers = json_decode( $custom_headers, true );

					if ( is_array( $custom_headers ) ) {
						foreach ( $custom_headers as $header => $value ) {
							$header = sanitize_key( $header );
							$value  = wp_kses( $value, [] );

							if ( ! empty( $header ) ) {
								$headers[ $header ] = $value;
							}
						}
					}
				} catch ( \Exception $e ) {
					\Bricks\Helpers::maybe_log( 'Bricks webhook error: Invalid headers format - ' . $e->getMessage() );
				}
			}

			// Prepare the payload
			if ( ! empty( $webhook['dataTemplate'] ) ) {
				// Use custom data template
				$payload = $webhook['dataTemplate'];
				$payload = $form->render_data( $payload );

				// Ensure valid JSON if using JSON format
				if ( $content_type === 'json' ) {
					try {
						$payload = json_decode( $payload, true );
						if ( ! is_array( $payload ) ) {
							$error_message = esc_html__( 'Invalid webhook payload format.', 'bricks' );
							\Bricks\Helpers::maybe_log( 'Bricks webhook error: ' . $error_message );
							$has_errors = true;
							continue;
						}
					} catch ( \Exception $e ) {
						$error_message = esc_html__( 'Invalid webhook payload format.', 'bricks' );
						\Bricks\Helpers::maybe_log( 'Bricks webhook error: ' . $error_message );
						$has_errors = true;
						continue;
					}
				}
			} else {
				// Send all form fields
				$payload = $form_fields;
			}

			// Check payload size
			$default_size = 1024; // KB
			$max_size_kb  = ! empty( $form_settings['webhookMaxSize'] ) ? $form_settings['webhookMaxSize'] : $default_size;
			$max_size     = $max_size_kb * 1024; // Convert to bytes

			if ( $content_type === 'json' ) {
				$payload_size = strlen( wp_json_encode( $payload ) );
			} else {
				$payload_size = strlen( http_build_query( $payload ) );
			}

			if ( $payload_size > $max_size ) {
				$error_message = esc_html__( 'Webhook payload too large.', 'bricks' );
				\Bricks\Helpers::maybe_log( 'Bricks webhook error: ' . $error_message . ' (Size: ' . size_format( $payload_size ) . ', Limit: ' . size_format( $max_size ) . ')' );
				$has_errors = true;
				continue;
			}

			// Prepare the request body based on content type
			$body = $content_type === 'json' ? wp_json_encode( $payload ) : $payload;

			// Send the webhook request
			$response = \Bricks\Helpers::remote_post(
				$webhook['url'],
				[
					'headers'   => $headers,
					'body'      => $body,
					'timeout'   => apply_filters( 'bricks/webhook/timeout', 15 ),
					'blocking'  => true,
					'sslverify' => true,
				]
			);

				// Check for errors
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				\Bricks\Helpers::maybe_log( 'Bricks webhook error: ' . $error_message );
				$has_errors = true;
				continue;
			}

			// Check response code
			$response_code = wp_remote_retrieve_response_code( $response );
			if ( $response_code < 200 || $response_code >= 300 ) {
				$error_message = esc_html__( 'Webhook request failed with status code', 'bricks' ) . ": $response_code";
				\Bricks\Helpers::maybe_log( 'Bricks webhook error: ' . $error_message );
				$has_errors = true;
				continue;
			}
		}

		// STEP: Set final result
		if ( $has_errors && empty( $form_settings['webhookErrorIgnore'] ) ) {
			$error_message = ! empty( $form_settings['webhookErrorMessage'] ) ? bricks_render_dynamic_data( $form_settings['webhookErrorMessage'], $form->get_post_id() ) : esc_html__( 'One or more webhook requests failed.', 'bricks' );

			$form->set_result(
				[
					'action'  => $this->name,
					'type'    => 'error',
					'message' => $error_message,
				]
			);
		} else {
			$form->set_result(
				[
					'action' => $this->name,
					'type'   => 'success',
				]
			);
		}
	}
}
