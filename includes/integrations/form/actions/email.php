<?php
namespace Bricks\Integrations\Form\Actions;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Email extends Base {
	/**
	 * Send email
	 *
	 * @since 1.0
	 */
	public function run( $form ) {
		$form_settings = $form->get_settings();
		$form_fields   = $form->get_fields();

		// Email To
		if ( isset( $form_settings['emailTo'] ) && $form_settings['emailTo'] === 'custom' && ! empty( $form_settings['emailToCustom'] ) ) {
			$recipients = $this->parse_emails( $form->render_data( $form_settings['emailToCustom'] ) );

			$recipients = explode( ',', $recipients );

			$recipients = array_map( 'trim', $recipients );
		}

		if ( empty( $recipients ) ) {
			$recipients = get_option( 'admin_email' );
		}

		// Email subject
		// translators: %s: Site name
		$subject = isset( $form_settings['emailSubject'] ) ? sanitize_text_field( $form->render_data( $form_settings['emailSubject'] ) ) : sprintf( esc_html__( '%s: New contact form message', 'bricks' ), get_bloginfo( 'name' ) );

		// Email content
		$line_break     = isset( $form_settings['htmlEmail'] ) ? '<br>' : "\n";
		$custom_message = ! empty( $form_settings['emailContent'] ) ? $form_settings['emailContent'] : '';
		$message        = wp_kses_post( $this->get_all_fields( $form_settings, $form ) );

		// Custom message
		if ( $custom_message ) {
			// Replace {{all_fields}} with all fields content (@since 1.9.2)
			$processed_message = str_replace( '{{all_fields}}', $message, $custom_message );

			// Render email content (replace {{form_field_id}} with submitted value)
			$processed_message = $form->render_data( $processed_message );

			// Add line breaks if user didn't add an HTML template
			if ( isset( $form_settings['htmlEmail'] ) && strpos( $processed_message, '<html' ) === false ) {
				$processed_message = nl2br( $processed_message );
			}
		}

		// Default message
		else {
			$processed_message = $message; // Ensure all fields message is still set if no custom message is used
		}

		/**
		 * Append default text if:
		 * 1. We have a $custom_message, but $processed_message is empty (ex: {{all_fields}}, but only field is "file". #86c3axazv)
		 * 2. We don't have a $custom_message
		 *
		 * @since 2.0
		 */
		if ( ( $custom_message && empty( $processed_message ) ) || empty( $custom_message ) ) {
			if ( isset( $_POST['referrer'] ) ) {
				$processed_message .= "{$line_break}{$line_break}" . esc_html__( 'Message sent from:', 'bricks' ) . ' ' . esc_url( $_POST['referrer'] );
			} else {
				// Fallback to page name
				$processed_message .= "{$line_break}{$line_break}" . esc_html__( 'Message sent from:', 'bricks' ) . ' ' . get_bloginfo( 'name' );
			}
		}

		$email = [
			'to'      => $recipients,
			'subject' => $subject,
			'message' => $processed_message,
		];

		// Email headers
		$headers = [];

		// Header: 'From'
		$from_email = ! empty( $form_settings['fromEmail'] ) ? sanitize_email( $form->render_data( $form_settings['fromEmail'] ) ) : false;

		if ( $from_email ) {
			$from_name = ! empty( $form_settings['fromName'] ) ? sanitize_text_field( $form->render_data( $form_settings['fromName'] ) ) : false;

			$headers[] = $from_name ? "From: $from_name <$from_email>" : "From: $from_email";
		}

		// Header: 'Content-Type'
		if ( isset( $form_settings['htmlEmail'] ) ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		// Header: 'Bcc'
		if ( isset( $form_settings['emailBcc'] ) ) {
			$headers[] = sprintf( 'Bcc: %s', $this->parse_emails( $form->render_data( $form_settings['emailBcc'] ) ) );
		}

		// Get first email address from submitted form data
		$first_valid_email = false;

		foreach ( $form_fields as $key => $value ) {
			if ( is_string( $value ) && is_email( $value ) ) {
				$first_valid_email = $value;
				break;
			}
		}

		// Header: 'Reply-To' email address
		$reply_to_email_address = ! empty( $form_settings['replyToEmail'] ) ? $this->parse_emails( $form->render_data( $form_settings['replyToEmail'] ) ) : '';

		if ( $reply_to_email_address ) {
			$headers[] = sprintf( 'Reply-To: %s', $reply_to_email_address );
		} elseif ( $first_valid_email ) {
			// Use first valid email address found in submitted form data as 'Reply-To' email address
			$headers[] = sprintf( 'Reply-To: %s', $first_valid_email );
		}

		// Add attachments if exist
		$attachments    = [];
		$uploaded_files = $form->get_uploaded_files() ?? [];

		if ( ! empty( $uploaded_files ) ) {
			foreach ( $uploaded_files as $input_name => $files ) {
				foreach ( $files as $file ) {
					$attachments[] = $file['file'];
				}
			}
		}

		// STEP: Send the email
		$email_sent = wp_mail( $email['to'], $email['subject'], $email['message'], $headers, $attachments );

		// STEP: Send confirmation email to submitted email address (@since 1.7.2)
		$confirmation_email_content = $form_settings['confirmationEmailContent'] ?? false;
		if ( $confirmation_email_content ) {
			$all_fields_message = wp_kses_post( $this->get_all_fields( $form_settings, $form ) );

			// Replace {{all_fields}} with all fields content.
			$confirmation_message = str_replace( '{{all_fields}}', $all_fields_message, $confirmation_email_content );

			// Render email content (replace {{form_field_id}} with submitted value)
			$confirmation_email_content = $form->render_data( $confirmation_message );

			// Add line breaks if HTML email is enabled and no HTML template exists (@since 1.11.1)
			if ( isset( $form_settings['confirmationEmailHTML'] ) && strpos( $confirmation_email_content, '<html' ) === false ) {
				$confirmation_email_content = nl2br( $confirmation_email_content );
			}
		}

		if ( $confirmation_email_content ) {
			$confirmation_email_to      = isset( $form_settings['confirmationEmailTo'] ) ? $this->parse_emails( $form->render_data( $form_settings['confirmationEmailTo'] ) ) : $first_valid_email;
			$confirmation_email_subject = isset( $form_settings['confirmationEmailSubject'] ) ? $form->render_data( $form_settings['confirmationEmailSubject'] ) : get_bloginfo( 'name' ) . ': ' . esc_html__( 'Thank you for your message', 'bricks' );

			// Header: 'From'
			$confirmation_from_name  = isset( $form_settings['confirmationFromName'] ) ? $form->render_data( $form_settings['confirmationFromName'] ) : get_bloginfo( 'name' );
			$confirmation_from_email = isset( $form_settings['confirmationFromEmail'] ) ? $form->render_data( $form_settings['confirmationFromEmail'] ) : get_option( 'admin_email' );

			$confirmation_email_headers = [ "From: $confirmation_from_name <$confirmation_from_email>" ];

			// Header: 'Reply-To' email address (@since 1.9.5)
			$confirmation_email_reply_to_email = ! empty( $form_settings['confirmationReplyToEmail'] ) ? $this->parse_emails( $form->render_data( $form_settings['confirmationReplyToEmail'] ) ) : '';

			if ( $confirmation_email_reply_to_email ) {
				$confirmation_email_headers[] = sprintf( 'Reply-To: %s', $confirmation_email_reply_to_email );
			}

			if ( isset( $form_settings['confirmationEmailHTML'] ) ) {
				$confirmation_email_headers[] = 'Content-Type: text/html; charset=UTF-8';
			}

			// Send confirmation email
			$confirmation_sent = wp_mail(
				$confirmation_email_to,
				$confirmation_email_subject,
				$confirmation_email_content,
				$confirmation_email_headers
			);
		}

		// Error
		if ( ! $email_sent ) {
			$form->set_result(
				[
					'action'  => $this->name,
					'type'    => 'error',
					'message' => ! empty( $form_settings['emailErrorMessage'] ) ? $form->render_data( $form_settings['emailErrorMessage'] ) : '',
					'content' => $message,
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

	public function get_all_fields( $form_settings, $form ) {
		$line_break = isset( $form_settings['htmlEmail'] ) ? '<br>' : "\n";
		$message    = '';

		foreach ( $form_settings['fields'] as $field ) {
			$field_label = $field['label'] ?? '';
			$field_id    = $field['id'] ?? '';
			$field_value = $form->get_field_value( $field_id );

			// Skip: Form field type 'file' and 'html'
			if ( $field['type'] === 'file' || $field['type'] === 'html' ) {
				continue;
			}

			// Skip: Fields that are defined as honeypot (@since 1.12.2)
			if ( isset( $field['isHoneypot'] ) ) {
				continue;
			}

			if ( $field_label ) {
				$message .= "$field_label: ";
			}

			$value = is_array( $field_value ) && ! empty( $field_value ) ? implode( ', ', $field_value ) : $field_value;

			// Remove HTML tags from value (useful for 'textarea' field type) and add line breaks
			$message .= wp_kses_post( $value ) . $line_break;
		}

		return $message;
	}

	/**
	 * Parse comma-separated emails (name + email address)
	 *
	 * Format: "Name <reply@domain.com>, Name2 <reply2@domain.com>"
	 *
	 * @since 1.10
	 */
	private function parse_emails( $email_string, $as_string = true ) {
		// Remove any unwanted characters, new lines, tabs, etc.
		$email_string = trim( $email_string );
		$email_string = preg_replace( '/\s+/', ' ', $email_string );

		// Split the email string by comma
		$emails = explode( ',', $email_string );

		$sanitized_emails = [];

		foreach ( $emails as $email ) {
			$email = trim( $email );

			$name = '';
			if ( preg_match( '/^(.+?)<(.+?)>$/', $email, $matches ) ) {
				// Case for: Firstname Lastname <user@email.com> or "My special Name" <user@email.com>
				$name  = trim( $matches[1] );
				$email = sanitize_email( trim( $matches[2] ) );
			} else {
				// Case for: user@email.com
				$email = sanitize_email( $email );
			}

			// Validate email
			if ( is_email( $email ) ) {
				$sanitized_emails[] = [
					'email' => $email,
					'name'  => $name,
				];
			}
		}

		if ( $as_string ) {
			$email_strings = array_map(
				function( $entry ) {
					return ! empty( $entry['name'] ) ? '"' . $entry['name'] . '" <' . $entry['email'] . '>' : $entry['email'];
				},
				$sanitized_emails
			);

			return implode( ', ', $email_strings );
		} else {
			return $sanitized_emails;
		}
	}
}
