<?php
namespace Bricks\Integrations\Form\Actions;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Redirect extends Base {
	/**
	 * Redirect action
	 *
	 * @since 1.0
	 */
	public function run( $form ) {
		$redirect_to = false;

		$form_settings = $form->get_settings();

		if ( isset( $form_settings['redirect'] ) ) {
			$redirect_to = $form->render_data( $form_settings['redirect'] );
		}

		// Redirect to admin area
		if ( isset( $form_settings['redirectAdminUrl'] ) ) {
			// Single site
			$redirect_to = isset( $form_settings['redirect'] ) ? admin_url( $form_settings['redirect'] ) : admin_url();

			// Multisite
			if ( is_multisite() && is_user_logged_in() ) {
				// Use get_current_user_id() instead of $login_response (#86c5v5qjg)
				$user_id       = get_current_user_id();
				$redirect_path = isset( $form_settings['redirect'] ) ? $form_settings['redirect'] : '';

				// @see https://developer.wordpress.org/reference/functions/get_dashboard_url/
				$redirect_to = get_dashboard_url( $user_id, $redirect_path );
			}
		}

		if ( $redirect_to ) {
			$form->set_result(
				[
					'action'          => $this->name,
					'type'            => 'redirect',
					'redirectTo'      => $redirect_to,
					'redirectTimeout' => isset( $form_settings['redirectTimeout'] ) ? intval( $form_settings['redirectTimeout'] ) : 0
				]
			);
		}
	}
}
