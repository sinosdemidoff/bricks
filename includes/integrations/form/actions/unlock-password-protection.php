<?php
namespace Bricks\Integrations\Form\Actions;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Unlock_Password_Protection extends Base {
	/**
	 * Unlock password protected content
	 *
	 * @since 1.11.1
	 */
	public function run( $form ) {
		$form_settings   = $form->get_settings();
		$form_fields     = $form->get_fields();
		$form_element_id = isset( $_POST['formId'] ) ? sanitize_text_field( $_POST['formId'] ) : '';

		$template_id = isset( $form_fields['brx_pp_temp_id'] ) ? intval( $form_fields['brx_pp_temp_id'] ) : 0;

		// STEP: Verify that the template ID is set
		if ( ! $template_id ) {
			$this->set_error_result( $form );
			return;
		}

		// Get password field value - either from specified field or first password field found
		$password_field = false;

		if ( isset( $form_settings['passwordProtectionPassword'] ) ) {
			// Use specified password field
			$password_field = $form_fields[ "form-field-{$form_settings['passwordProtectionPassword']}" ] ?? false;
		} else {
			// Find first password field in form
			$element_data = \Bricks\Helpers::get_element_data( $template_id, $form_element_id );

			if ( $element_data && isset( $element_data['element']['settings']['fields'] ) ) {
				foreach ( $element_data['element']['settings']['fields'] as $field ) {
					if ( isset( $field['type'] ) && $field['type'] === 'password' && isset( $field['id'] ) ) {
						$password_field = $form_fields[ "form-field-{$field['id']}" ] ?? false;
						if ( $password_field ) {
							break;
						}
					}
				}
			}
		}

		// STEP: Verify that the password field is set
		if ( ! $password_field ) {
			$this->set_error_result( $form );
			return;
		}

		// STEP: Verify that the form exists in the template
		if ( ! \Bricks\Password_Protection::verify_form_in_template( $form_element_id, $template_id ) ) {
			$this->set_error_result( $form );
			return;
		}

		// STEP: Validate the password against the template's password
		if ( ! \Bricks\Password_Protection::validate_password( $template_id, $password_field ) ) {
			$this->set_error_result( $form );
			return;
		}

		// STEP: Password is valid, set the cookie
		\Bricks\Password_Protection::set_password_cookie( $template_id, $password_field );

		$this->handle_success( $form, $form_settings );
	}

	private function set_error_result( $form ) {
		// Get custom 'errorMessage' from password form field or use default error message
		$form_settings = $form->get_settings();
		$error_message = $form_settings['passwordProtectionErrorMessage'] ?? esc_html__( 'Incorrect password.', 'bricks' );

		$form->set_result(
			[
				'action'  => $this->name,
				'type'    => 'error',
				'message' => esc_html( $error_message ),
			]
		);
	}

	private function handle_success( $form, $form_settings ) {
		$redirect_to = '';

		// Check for the 'redirect_to' URL parameter if 'redirect' action is not set
		if ( ! in_array( 'redirect', $form_settings['actions'], true ) ||
			( ! isset( $form_settings['redirect'] ) && ! isset( $form_settings['redirectAdminUrl'] ) )
		) {
			$redirect_to = esc_url_raw( $form->get_fields()['form-field-redirect_to'] ?? '' );
		}

		// Validate and redirect if 'redirect_to' is present
		if ( $redirect_to && wp_http_validate_url( $redirect_to ) ) {
			$form->set_result(
				[
					'action'     => $this->name,
					'type'       => 'redirect',
					'redirectTo' => $redirect_to,
				]
			);
		} else {
			$form->set_result(
				[
					'action'      => $this->name,
					'type'        => 'success',
					'message'     => $form_settings['unlockSuccessMessage'] ?? esc_html__( 'Password accepted. You can now access the protected content.', 'bricks' ),
					'refreshPage' => true,
				]
			);
		}
	}
}
