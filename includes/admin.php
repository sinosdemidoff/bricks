<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Admin {
	const EDITING_CAP = 'edit_posts';

	public function __construct() {
		add_action( 'after_switch_theme', [ $this, 'set_default_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'enqueue_block_assets', [ $this, 'gutenberg_scripts' ] );

		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'admin_notices', [ $this, 'admin_notice_regenerate_css_files' ] );
		add_action( 'admin_notices', [ $this, 'admin_notice_code_signatures' ] );

		add_filter( 'display_post_states', [ $this, 'add_post_state' ], 10, 2 );

		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
		add_filter( 'image_size_names_choose', [ $this, 'image_size_names_choose' ] );

		add_action( 'admin_init', [ $this, 'save_editor_mode' ] );
		add_filter( 'admin_url', [ $this, 'admin_url' ] );

		add_action( 'wp_ajax_bricks_import_global_settings', [ $this, 'import_global_settings' ] );
		add_action( 'wp_ajax_bricks_export_global_settings', [ $this, 'export_global_settings' ] );
		add_action( 'wp_ajax_bricks_save_settings', [ $this, 'save_settings' ] );
		add_action( 'wp_ajax_bricks_reset_settings', [ $this, 'reset_settings' ] );
		add_action( 'wp_ajax_bricks_save_element_manager', [ $this, 'save_element_manager' ] );
		add_action( 'wp_ajax_bricks_get_element_usage_count', [ $this, 'get_element_usage_count' ] ); // Updated AJAX action

		add_action( 'edit_form_after_title', [ $this, 'builder_tab_html' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'row_actions' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );

		add_filter( 'manage_' . BRICKS_DB_TEMPLATE_SLUG . '_posts_columns', [ $this, 'bricks_template_posts_columns' ] );
		add_action( 'manage_' . BRICKS_DB_TEMPLATE_SLUG . '_posts_custom_column', [ $this, 'bricks_template_posts_custom_column' ], 10, 2 );

		// Additional filters for user activation (@since 2.1)
		if ( Database::get_setting( 'userActivationEnabled' ) ) {

			// Add new column to user list
			add_filter( 'manage_users_columns', [ $this, 'add_user_activation_status_column' ] );
			add_filter( 'manage_users_custom_column', [ $this, 'user_activation_status_column_content' ], 10, 3 );
		}

		// Export template
		add_filter( 'bulk_actions-edit-bricks_template', [ $this, 'bricks_template_bulk_action_export' ] );
		add_filter( 'handle_bulk_actions-edit-bricks_template', [ $this, 'bricks_template_handle_bulk_action_export' ], 10, 3 );

		// Import template
		add_action( 'admin_footer', [ $this, 'import_templates_form' ] );

		// Add template type meta box
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'meta_box_save_post' ] );

		// Filter by template type
		add_action( 'restrict_manage_posts', [ $this, 'template_type_filter_dropdown' ] );
		add_filter( 'parse_query', [ $this, 'template_type_filter_query' ] );

		// Dismissable HTTPS notice
		add_action( 'wp_ajax_bricks_dismiss_https_notice', [ $this, 'dismiss_https_notice' ] );

		// Drop form submissions table (@since 1.9.2)
		add_action( 'wp_ajax_bricks_form_submissions_drop_table', [ $this, 'form_submissions_drop_table' ] );

		// Reset form submissions table (@since 1.9.2)
		add_action( 'wp_ajax_bricks_form_submissions_reset_table', [ $this, 'form_submissions_reset_table' ] );

		// Delete form submissions of form ID (@since 1.9.2)
		add_action( 'wp_ajax_bricks_form_submissions_delete_form_id', [ $this, 'form_submissions_delete_form_id' ] );

		// Set custom screen options (@since 1.9.2)
		add_filter( 'set-screen-option', [ 'Bricks\Integrations\Form\Submission_Table', 'set_screen_option' ], 10, 3 );

		// Instagram access token
		add_action( 'wp_ajax_bricks_dismiss_instagram_access_token_notice', [ $this, 'dismiss_instagram_access_token_notice' ] );

		// Reindex query filters records (@since 1.9.6)
		add_action( 'wp_ajax_bricks_reindex_query_filters', [ $this, 'reindex_query_filters' ] );

		// Manually trigger index job instead of waiting for cron (@since 1.10)
		add_action( 'wp_ajax_bricks_run_index_job', [ $this, 'run_index_job' ] );

		// Force remove all index jobs (@since 1.11)
		add_action( 'wp_ajax_bricks_remove_all_index_jobs', [ $this, 'remove_all_index_jobs' ] );

		// Regenerate code signatures (@since 1.9.7)
		add_action( 'wp_ajax_bricks_regenerate_code_signatures', [ $this, 'regenerate_code_signatures' ] );

		// Bricks duplicate content action (@since 1.9.8)
		add_action( 'admin_action_bricks_duplicate_content', [ $this, 'bricks_duplicate_content' ] );

		// Delete templat screenshots (@since 1.10)
		add_action( 'wp_ajax_bricks_delete_template_screenshots', [ $this, 'delete_template_screenshots' ] );

		// System information test (@since 1.11)
		add_action( 'wp_ajax_bricks_system_info_wp_remote_post_test', [ $this, 'system_info_wp_remote_post_test' ] );

		// Fix filter element database (@since 1.12)
		add_action( 'wp_ajax_bricks_fix_filter_element_db', [ $this, 'bricks_fix_filter_element_db' ] );

		// User activation actions (@since 2.1)
		if ( Database::get_setting( 'userActivationEnabled' ) ) {
			add_action( 'admin_action_bricks_user_activation', [ $this, 'bricks_user_activation_action' ] );
		}
	}

	/**
	 * Add meta box: Template type
	 *
	 * @since 1.0
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'meta-box-template-type',
			esc_html__( 'Template type', 'bricks' ),
			[ $this, 'meta_box_template_type' ],
			BRICKS_DB_TEMPLATE_SLUG,
			'side',
			'high'
		);
	}

	/**
	 * Meta box: Template type render
	 *
	 * @since 1.0
	 */
	public function meta_box_template_type( $post ) {
		$template_type = get_post_meta( $post->ID, BRICKS_DB_TEMPLATE_TYPE, true );

		$template_types_options = Setup::$control_options['templateTypes'];
		?>
		<p><label for="bricks_template_type"><?php esc_html_e( 'Select template type', 'bricks' ); ?>:</label></p>
		<select name="bricks_template_type" id="bricks_template_type" style="width: 100%">
			<option value=""><?php esc_html_e( 'Select', 'bricks' ); ?></option>
		<?php
		foreach ( $template_types_options as $key => $value ) {
			echo '<option value=' . $key . ' ' . selected( $key, $template_type ) . '>' . $value . '</option>';
		}
		?>
		</select>
		<?php
	}

	/**
	 * Meta box: Save/delete template type
	 *
	 * @since 1.0
	 */
	public function meta_box_save_post( $post_id ) {
		$template_type = ! empty( $_POST['bricks_template_type'] ) ? sanitize_text_field( $_POST['bricks_template_type'] ) : false;

		if ( $template_type ) {
			// Get previous template type
			$previous_type = get_post_meta( $post_id, BRICKS_DB_TEMPLATE_TYPE, true );

			// Update new template type
			update_post_meta( $post_id, BRICKS_DB_TEMPLATE_TYPE, $template_type );

			// Convert template types into content area (header, content, footer)
			$previous_type = $previous_type ? Database::get_bricks_data_key( $previous_type ) : false;

			$new_type = Database::get_bricks_data_key( $template_type );

			// If content areas exist and are different, then migrate data
			if ( $previous_type && $new_type && $previous_type !== $new_type ) {
				// Get the data from the previous content area
				$previous_data = get_post_meta( $post_id, $previous_type, true );

				// wp_slash the postmeta value as update_post_meta removes backslashes via wp_unslash (@since 1.9.7)
				if ( is_array( $previous_data ) ) {
					$previous_data = wp_slash( $previous_data );
				}

				// Save data using the new content area
				$updated_template_type = update_post_meta( $post_id, $new_type, $previous_data );

				// Delete data from previous content area
				if ( $updated_template_type ) {
					delete_post_meta( $post_id, $previous_type );
				}
			}
		}
	}

	/**
	 * Render dashboard widget
	 *
	 * @since 1.0
	 */
	public function dashboard_widget() {
		// Get remote feed from Bricks blog
		$feed = Api::get_feed();

		if ( count( $feed ) ) {
			echo '<ul class="bricks-dashboard-feed-wrapper">';

			foreach ( $feed as $post ) {
				echo '<li>';
				echo '<a href="' . $post['permalink'] . '?utm_source=wp-admin&utm_medium=wp-dashboard-widget&utm_campaign=feed" target="_blank">' . $post['title'] . '</a>';
				echo '<p>' . $post['excerpt'] . '</p>';
				echo '</li>';
			}

			echo '</ul>';
		}
	}

	/**
	 * Post custom column
	 *
	 * @since 1.0
	 */
	public function posts_custom_column( $column, $post_id ) {
		if ( $column === 'template' ) {
			$post_template_id = 0;
			$post_template    = get_post( $post_id );

			if ( $post_template_id ) {
				echo '<a href="' . Helpers::get_builder_edit_link( $post_id ) . '" target="_blank">' . $post_template['title'] . '</a>';
			} else {
				echo '-';
			}
		}
	}

	/**
	 * Add bulk action "Export"
	 *
	 * @since 1.0
	 */
	public function bricks_template_bulk_action_export( $actions ) {
		$actions[ BRICKS_EXPORT_TEMPLATES ] = esc_html__( 'Export', 'bricks' );

		return $actions;
	}

	/**
	 * Handle bulk action "Export"
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $doaction     Action to run.
	 * @param array  $items        Items to run action on.
	 *
	 * @since 1.0
	 */
	public function bricks_template_handle_bulk_action_export( $redirect_url, $doaction, $items ) {
		if ( $doaction === BRICKS_EXPORT_TEMPLATES ) {
			$this->export_templates( $items );
		}

		return $redirect_url;
	}

	/**
	 * Export templates
	 *
	 * @param array $template_ids IDs of templates to export.
	 *
	 * @since 1.0
	 */
	public function export_templates( $template_ids ) {
		$files = [];

		$wp_upload_dir = wp_upload_dir();

		$temp_path = trailingslashit( $wp_upload_dir['basedir'] ) . BRICKS_TEMP_DIR;

		// Create temp path if it doesn't exist
		wp_mkdir_p( $temp_path );

		foreach ( $template_ids as $template_id ) {
			$file_data         = Templates::export_template( $template_id );
			$file_path         = trailingslashit( $temp_path ) . $file_data['name'];
			$file_put_contents = file_put_contents( $file_path, $file_data['content'] );

			$files[] = [
				'path' => $file_path,
				'name' => $file_data['name'],
			];
		}

		// Check if ZipArchive PHP extension exists
		if ( ! class_exists( '\ZipArchive' ) ) {
			return new \WP_Error( 'ziparchive_error', 'Error: ZipArchive PHP extension does not exist.' );
		}

		// Create ZIP file
		$zip_filename = 'templates-' . date( 'Y-m-d' ) . '.zip';
		$zip_path     = trailingslashit( $temp_path ) . $zip_filename;
		$zip_archive  = new \ZipArchive();
		$zip_archive->open( $zip_path, \ZipArchive::CREATE );

		foreach ( $files as $file ) {
			$zip_archive->addFile( $file['path'], $file['name'] );
		}

		$zip_archive->close();

		// Delete template JSON files
		foreach ( $files as $file ) {
			unlink( $file['path'] );
		}

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . $zip_filename );
		header( 'Cache-Control: must-revalidate' );
		header( 'Expires: 0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $zip_path ) );

		@ob_end_flush();

		@readfile( $zip_path );

		unlink( $zip_path );

		die;
	}

	/**
	 * Import templates form
	 *
	 * @since 1.0
	 */
	public function import_templates_form() {
		global $current_screen;

		if ( ! $current_screen ) {
			return;
		}

		// Show import templates form on "My Templates" admin page
		if ( $current_screen->id === 'edit-' . BRICKS_DB_TEMPLATE_SLUG ) {
			?>
		<div id="bricks-admin-import-wrapper">
			<a id="bricks-admin-import-action" class="page-title-action bricks-admin-import-toggle"><?php esc_html_e( 'Import', 'bricks' ); ?></a>
			<a id="bricks-admin-template-bundles" href="<?php echo admin_url( 'edit-tags.php?taxonomy=template_bundle&post_type=bricks_template' ); ?>" class="page-title-action"><?php esc_html_e( 'Bundles', 'bricks' ); ?></a>
			<a id="bricks-admin-template-tags" href="<?php echo admin_url( 'edit-tags.php?taxonomy=template_tag&post_type=bricks_template' ); ?>"class="page-title-action"><?php esc_html_e( 'Tags', 'bricks' ); ?></a>

			<div id="bricks-admin-import-form-wrapper">
				<p><?php esc_html_e( 'Select and import your template JSON/ZIP file from your computer.', 'bricks' ); ?></p>

				<form id="bricks-admin-import-form" method="post" enctype="multipart/form-data">
					<p><input type="file" name="files" id="bricks_import_files" accept=".json,application/json,.zip,application/octet-stream,application/zip,application/x-zip,application/x-zip-compressed" multiple required></p>

					<p><input type="checkbox" name="importImages" id="bricks_import_images" value="true"> <label for="bricks_import_images"><?php esc_html_e( 'Import images', 'bricks' ); ?></label></p>

					<input type="submit" class="button button-primary button-large" value="<?php echo esc_attr__( 'Import', 'bricks' ); ?>">
					<button class="button button-large bricks-admin-import-toggle"><?php esc_html_e( 'Cancel', 'bricks' ); ?></button>

					<input type="hidden" name="action" value="bricks_import_template">
				<?php wp_nonce_field( 'bricks-nonce-admin', 'nonce' ); ?>
				</form>

				<i class="close bricks-admin-import-toggle dashicons dashicons-no-alt"></i>

				<div class="import-progress"><span class="spinner is-active"></span></div>
			</div>
		</div>
			<?php
		}
	}

	/**
	 * Template type filter dropdown
	 *
	 * @since 1.9.3
	 */
	public function template_type_filter_dropdown() {
		global $typenow; // Get the current post type

		if ( $typenow == BRICKS_DB_TEMPLATE_SLUG ) {
			// Get template types
			$template_types = Setup::$control_options['templateTypes'];

			// Check if template type is selected in filter dropdown
			$selected = ! empty( $_GET['template_type'] ) ? sanitize_text_field( $_GET['template_type'] ) : '';

			echo '<select name="template_type" id="template_type" class="postform">';

			echo '<option value="">' . esc_html__( 'All template types', 'bricks' ) . '</option>';

			foreach ( $template_types as $key => $label ) {
				echo '<option value="' . $key . '"' . selected( $key, $selected ) . '>' . $label . '</option>';
			}

			echo '</select>';
		}
	}

	/**
	 * Template type filter query
	 *
	 * @since 1.9.3
	 */
	public function template_type_filter_query( $query ) {
		global $pagenow;

		$post_type     = ! empty( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : 'post';
		$template_type = ! empty( $_GET['template_type'] ) ? sanitize_text_field( $_GET['template_type'] ) : '';

		// Perform filter action only for Bricks template post type and main query (@since 2.0)
		if ( $query->is_main_query() && is_admin() && $template_type && $post_type === BRICKS_DB_TEMPLATE_SLUG && $pagenow == 'edit.php' ) {
			$query->query_vars['meta_key']   = BRICKS_DB_TEMPLATE_TYPE;
			$query->query_vars['meta_value'] = $template_type;
		}
	}

	/**
	 * Import global settings
	 *
	 * @since 1.0
	 */
	public function import_global_settings() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'verify_request: Sorry, you are not allowed to perform this action.' );
		}

		// Load WP_WP_Filesystem for temp file URL access
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Import single JSON file
		$files    = $_FILES['files']['tmp_name'] ?? [];
		$settings = [];
		$updated  = false;

		foreach ( $files as $file ) {
			$settings = json_decode( $wp_filesystem->get_contents( $file ), true );
		}

		if ( is_array( $settings ) && count( $settings ) ) {
			$updated = update_option( BRICKS_DB_GLOBAL_SETTINGS, $settings );
		}

		wp_send_json_success(
			[
				'settings' => $settings,
				'updated'  => $updated,
			]
		);
	}

	/**
	 * Generate and download JSON file with global settings
	 *
	 * @since 1.0
	 */
	public static function export_global_settings() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'verify_request: Sorry, you are not allowed to perform this action.' );
		}

		// Get latest settings
		$settings    = get_option( BRICKS_DB_GLOBAL_SETTINGS, [] );
		$export_json = wp_json_encode( $settings );

		header( 'Content-Description: File Transfer' );
		header( 'Content-type: application/txt' );
		header( 'Content-Disposition: attachment; filename="bricks-settings-' . date( 'Y-m-d' ) . '.json"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );

		echo $export_json;
		exit;
	}

	/**
	 * Save settings in WP dashboard on form 'save' submit
	 *
	 * @since 1.0
	 */
	public function save_settings() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		parse_str( $_POST['formData'] ?? [], $settings );

		$old_settings = Database::$global_settings;
		$new_settings = [];

		// Code execution is not enabled: Remove any execute capability from user role
		if ( ! isset( $settings['executeCodeEnabled'] ) ) {
			unset( $settings['executeCodeCapabilities'] );
		}

		foreach ( $settings as $key => $value ) {
			// Skip empty values
			if ( $value == '' ) {
				continue;
			}

			// Handle custom capabilities
			if ( $key === 'customCapabilities' ) {
				// Decode the JSON string from the hidden input
				$capabilities = json_decode( stripslashes( $value ), true );

				// Save the capabilities if valid
				if ( is_array( $capabilities ) ) {
					Builder_Permissions::save_custom_capabilities( $capabilities );
				}

				continue;
			}

			if ( $key === 'builderCapabilities' ) {
				Capabilities::save_builder_capabilities( $value );

				// Don't save selected capabilities in Global Settings, but as user role capabilities
				continue;
			}

			if ( $key === 'uploadSvgCapabilities' ) {
				Capabilities::save_capabilities( Capabilities::UPLOAD_SVG, $value );

				// Don't save selected capabilities in Global Settings, but as user role capabilities
				continue;
			}

			if ( $key === 'executeCodeCapabilities' ) {
				Capabilities::save_capabilities( Capabilities::EXECUTE_CODE, $value );

				// Don't save selected capabilities in Global Settings, but as user role capabilities
				continue;
			}

			// Maintenance mode
			if ( $key === 'bypassMaintenanceCapabilities' ) {
				Capabilities::save_capabilities( Capabilities::BYPASS_MAINTENANCE, $value );

				// Don't save selected capabilities in Global Settings, but as user role capabilities
				continue;
			}

			// Form submission access (@since 1.11)
			if ( $key === 'formSubmissionAccessCapabilities' ) {
				Capabilities::save_capabilities( Capabilities::FORM_SUBMISSION_ACCESS, $value );

				// Don't save selected capabilities in Global Settings, but as user role capabilities
				continue;
			}

			// STEP: Modify settings values based on key

			// builderQueryMaxResults (int), minimum 2 (@since 1.11)
			if ( $key === 'builderQueryMaxResults' && intval( $value ) < 2 ) {
				$value = '';
			}

			 // English (United States) uses an empty string for the value attribute
			if ( $key === 'builderLocale' && empty( $value ) ) {
				$value = 'en_US';
			}

			// Min. autosave interval: 15 seconds
			if ( $key === 'builderAutosaveInterval' && intval( $value ) < 15 ) {
				$value = 15;
			}

			// Unlimited remote template URLs
			elseif ( $key === 'remoteTemplates' ) {
				if ( is_array( $value ) ) {
					// Filter out any entries with an empty URL
					$value = array_filter(
						$value,
						function( $item ) {
							return ! empty( $item['url'] );
						}
					);
				} else {
					// $value is not an array: Set to empty array (for consistency)
					$value = [];
				}
			}

			// Email content settings
			elseif ( in_array( $key, [ 'userActivationLinkEmailContent' ] ) ) {
				// Do nothing?
			}

			// Textarea settings
			elseif ( in_array( $key, [ 'myTemplatesWhitelist', 'builderModeCss', ] ) ) {
				$value = sanitize_textarea_field( $value );
			}

			// Preserve backslashes in custom code via wp_slash
			elseif ( in_array( $key, [ 'customCss', 'customScriptsHeader', 'customScriptsBodyHeader', 'customScriptsBodyFooter' ] ) ) {
				// jQuery.serialize() adds the slash to single quote
				$value = str_replace( "\'", "'", $value );
				$value = wp_slash( $value );
			}

			else {
				// Sanitize Bricks settings values
				if ( is_array( $value ) ) {
					foreach ( $value as $k => $v ) {
						$value[ $k ] = Helpers::sanitize_value( $v );
					}
				} else {
					$value = Helpers::sanitize_value( $value );
				}
			}

			// STEP: Modify settings value according to the value
			if ( $value === 'on' ) {
				$value = true;
			}

			// Enciphered API keys: Use existing value (instead of the 'xxxxxxxx' placeholder value)
			if ( is_string( $value ) && strpos( $value, 'xxxxxxxx' ) !== false ) {
				$value = $old_settings[ $key ];
			}

			// STEP: Set new settings value
			$new_settings[ $key ] = $value;
		}

		if ( empty( $settings['uploadSvgCapabilities'] ) ) {
			Capabilities::save_capabilities( Capabilities::UPLOAD_SVG );
		}

		if ( empty( $settings['executeCodeCapabilities'] ) ) {
			Capabilities::save_capabilities( Capabilities::EXECUTE_CODE );
		}

		// Remove bypass maintenance mode capabilitie for all roles (@since 1.9.4)
		if ( empty( $settings['bypassMaintenanceCapabilities'] ) ) {
			Capabilities::save_capabilities( Capabilities::BYPASS_MAINTENANCE, [] );
		}

		// Remove form submission access capability for all roles (@since 1.11)
		if ( empty( $settings['formSubmissionAccessCapabilities'] ) ) {
			Capabilities::save_capabilities( Capabilities::FORM_SUBMISSION_ACCESS, [] );
		}

		update_option( BRICKS_DB_GLOBAL_SETTINGS, $new_settings );

		// Sync Mailchimp and Sendgrid lists (@since 1.0)
		$mailchimp_lists = \Bricks\Integrations\Form\Actions\Mailchimp::sync_lists();
		$sendgrid_lists  = \Bricks\Integrations\Form\Actions\Sendgrid::sync_lists();

		// Maybe create form submission table (@since 1.9.2)
		if ( isset( $settings['saveFormSubmissions'] ) ) {
			\Bricks\Integrations\Form\Submission_Database::maybe_create_table();
		}

		// Download remote templates from server and store as db option
		Templates::get_remote_templates_data();

		// Maybe create query filters table (@since 1.9.6)
		if ( isset( $settings['enableQueryFilters'] ) ) {
			\Bricks\Query_Filters::get_instance()->maybe_create_tables();
		}

		// STEP: Regenerate CSS files if 'disableBricksCascadeLayer' setting changed (@since 2.0)
		$cascade_layer_old      = isset( $old_settings['disableBricksCascadeLayer'] );
		$cascade_layer_new      = isset( $settings['disableBricksCascadeLayer'] );
		$css_loading_method_old = $old_settings['cssLoading'] ?? false;
		$css_loading_method_new = $settings['cssLoading'] ?? false;

		if (
			$css_loading_method_new === 'file' &&
			$cascade_layer_new !== $cascade_layer_old
		) {
			// NOTE: Run schedule_css_file_regeneration() code manually as no access to PHP class Assets_Files
			$timestamp = time() + 1;
			$hook      = 'bricks_regenerate_css_files';
			wp_schedule_single_event( $timestamp, $hook );
		}

		wp_send_json_success(
			[
				'new_settings'    => $new_settings,
				'mailchimp_lists' => $mailchimp_lists,
				'sendgrid_lists'  => $sendgrid_lists,
			]
		);
	}

	/**
	 * Reset settings in WP dashboard on form 'reset' submit
	 *
	 * @since 1.0
	 */
	public function reset_settings() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		delete_option( BRICKS_DB_GLOBAL_SETTINGS );
		delete_option( 'bricks_mailchimp_lists' );
		delete_option( 'bricks_sendgrid_lists' );

		self::set_default_settings();

		Capabilities::set_defaults();

		wp_send_json_success();
	}

	/**
	 * Save element manager
	 *
	 * @since 2.0
	 */
	public function save_element_manager() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		$elements = $_POST['elements'] ?? [];

		// Check: Reset element manager
		if ( $_POST['reset'] == 'true' ) {
			delete_option( BRICKS_DB_ELEMENT_MANAGER );

			wp_send_json_success( $elements );
		}

		foreach ( $elements as $name => $element ) {
			// No permission or all permissions: Remove permission
			if ( empty( $element['permission'] ) || in_array( 'all', $element['permission'] ) ) {
				unset( $element['permission'] );
			}

			// No status or active status: Remove status
			if ( empty( $element['status'] ) || $element['status'] === 'active' ) {
				unset( $element['status'] );
			}

			// Remove element if no status and no permission
			if ( empty( $element['status'] ) && empty( $element['permission'] ) ) {
				unset( $elements[ $name ] );
			} else {
				$elements[ $name ] = $element;
			}
		}

		foreach ( Elements::mandatory_elements() as $element_name ) {
			// Unset mandatory elements
			unset( $elements[ $element_name ] );
		}

		// STEP: Update or delete element manger in options table
		if ( count( $elements ) ) {
			update_option( BRICKS_DB_ELEMENT_MANAGER, $elements );
		} else {
			delete_option( BRICKS_DB_ELEMENT_MANAGER );
		}

		wp_send_json_success( $elements );
	}

	/**
	 * Get element usage count via AJAX for multiple elements
	 *
	 * @since 2.0
	 */
	public function get_element_usage_count() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		$element_names = isset( $_POST['elementNames'] ) ? $_POST['elementNames'] : [];

		// Ensure element names are an array and not more than 25 elements (admin.js BATCH_SIZE)
		if ( ! is_array( $element_names ) || count( $element_names ) > 25 ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid element names', 'bricks' ) ] );
		}

		// Get site-wide element usage count for multiple elements
		global $wpdb;

		$results = [];

		foreach ( $element_names as $element_name ) {
			// Sanitize element name
			if ( ! is_string( $element_name ) ) {
				continue; // Skip if element name is not a string
			}

			// Sanitize and prepare element name
			$element_name = sanitize_text_field( trim( $element_name ) );
			$length       = absint( strlen( $element_name ) );

			// Skip if element name is empty
			if ( $length < 1 ) {
				continue;
			}

			// Prepare the LIKE pattern for serialized data
			$like_pattern = '%' . $wpdb->esc_like( 's:4:"name";s:' . $length . ':"' . $wpdb->esc_like( $element_name ) . '";' ) . '%';

			// Prepare and execute the query to count rows
			$posts_with_element = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.meta_value
						FROM {$wpdb->postmeta} pm
						LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
						WHERE pm.meta_key IN (%s, %s, %s)
							AND pm.meta_value LIKE %s
							AND p.post_type NOT IN ('revision', 'nav_menu_item', 'attachment')
							AND p.post_status NOT IN ('auto-draft', 'trash' )",
					BRICKS_DB_PAGE_HEADER,
					BRICKS_DB_PAGE_CONTENT,
					BRICKS_DB_PAGE_FOOTER,
					$like_pattern
				)
			);

			$total_count = 0;

			// Use regex to find the serialized string for the element name to check how many times it appears
			foreach ( $posts_with_element as $row ) {
				$serialized_string = (string) $row->meta_value ?? '';
				// Count occurrences of the element name in the serialized string, s:4:"name";s:{length}:"{element_name}"
				$pattern = '/s:4:"name";s:' . $length . ':"' . preg_quote( $element_name, '/' ) . '";/';
				preg_match_all( $pattern, $serialized_string, $matches );
				$count        = count( $matches[0] );
				$total_count += $count;
			}

			$results[ $element_name ] = [
				'count' => $total_count,
			];
		}

		wp_send_json_success( [ 'results' => $results ] );
	}

	/**
	 * Template columns
	 *
	 * @since 1.0
	 */
	public function bricks_template_posts_columns( $columns ) {
		$columns = [
			'cb'                  => '<input type="checkbox" />',
			'title'               => esc_html__( 'Title', 'bricks' ),
			'template_type'       => esc_html__( 'Type', 'bricks' ),
			'template_conditions' => esc_html__( 'Conditions', 'bricks' ),
			'template_thumbnail'  => esc_html__( 'Thumbnail', 'bricks' ),
			'shortcode'           => esc_html__( 'Shortcode', 'bricks' ),
			'author'              => esc_html__( 'Author', 'bricks' ),
			'date'                => esc_html__( 'Date', 'bricks' ),
		];

		if ( ! Database::get_setting( 'templateScreenshotsAdminColumn' ) ) {
			unset( $columns['template_thumbnail'] );
		}

		return $columns;
	}

	/**
	 * Template custom column
	 *
	 * @since 1.0
	 */
	public function bricks_template_posts_custom_column( $column, $post_id ) {
		$template_type = get_post_meta( $post_id, BRICKS_DB_TEMPLATE_TYPE, true );

		/**
		 * STEP: Template screenshot
		 *
		 * Feature image OR generated template screenshot.
		 *
		 * @since 1.10
		 */
		if ( $column === 'template_thumbnail' ) {
			$thumbnail_width  = Database::get_setting( 'templateAdminColumnThumbnailWidth', 60 );
			$thumbnail_height = Database::get_setting( 'templateAdminColumnThumbnailHeight', 60 );

			$style = '';

			if ( $thumbnail_width && $thumbnail_height ) {
				$style = ' style="width: ' . esc_attr( $thumbnail_width ) . 'px; height: ' . esc_attr( $thumbnail_height ) . 'px; max-height: ' . esc_attr( $thumbnail_height ) . 'px;"';

				// Add inline style for the column header
				echo '<style>
					.column-template_thumbnail,
					.manage-column.column-template_thumbnail {
						width: ' . esc_attr( $thumbnail_width ) . 'px;
					}
				</style>';
			}

			// Get screenshot from thumbnail
			if ( has_post_thumbnail( $post_id ) ) {
				echo '<a href="' . get_the_permalink( $post_id ) . '" target="_blank"' . $style . '>' . get_the_post_thumbnail( $post_id, 'thumbnail' ) . '</a>';
			}

			// Get automatically-generated template screenshot from custom directory (@since 1.10)
			else {
				$wp_upload_dir = wp_upload_dir();
				$custom_dir    = $wp_upload_dir['basedir'] . '/' . BRICKS_TEMPLATE_SCREENSHOTS_DIR . '/';
				$custom_url    = $wp_upload_dir['baseurl'] . '/' . BRICKS_TEMPLATE_SCREENSHOTS_DIR . '/';

				// Get all files for this template ID
				$all_files   = glob( $custom_dir . "template-screenshot-$post_id-*" );
				$latest_file = null;
				$latest_time = 0;

				foreach ( $all_files as $file ) {
					// Check if the file is of a valid type
					$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
					if ( in_array( $extension, [ 'webp','png' ] ) ) {
						// Extract the timestamp from the filename
						if ( preg_match( '/-(\d+)\.' . $extension . '$/', $file, $matches ) ) {
							$file_time = intval( $matches[1] );
							if ( $file_time > $latest_time ) {
								$latest_time = $file_time;
								$latest_file = $file;
							}
						}
					}
				}

				if ( $latest_file ) {
					$filename = basename( $latest_file );
					$file_url = $custom_url . $filename;
					echo '<a href="' . get_the_permalink( $post_id ) . '" target="_blank"' . $style . '>' . "<img src=\"$file_url\" /></a>";
				} else {
					echo '-';
				}
			}

			return;
		}

		// Template conditions
		if ( $column === 'template_conditions' ) {
			$settings_template_controls = isset( Settings::$controls['template'] ) ? Settings::$controls['template']['controls'] : false;

			$template_settings   = Helpers::get_template_settings( $post_id );
			$template_conditions = isset( $template_settings['templateConditions'] ) && is_array( $template_settings['templateConditions'] ) ? $template_settings['templateConditions'] : [];

			// STEP: No template conditions found: Check for default template (by template type, must be published)
			if ( ! count( $template_conditions ) && ! Database::get_setting( 'defaultTemplatesDisabled', false ) && get_post_status( $post_id ) === 'publish' ) {
				// Check if template type in a default template type
				if ( in_array( $template_type, Database::$default_template_types ) ) {
					$default_condition = '';

					switch ( $template_type ) {
						case 'header':
						case 'footer':
							$default_condition = esc_html__( 'Entire website', 'bricks' );
							break;

						case 'archive':
							$default_condition = esc_html__( 'All archives', 'bricks' );
							break;

						case 'search':
							$default_condition = esc_html__( 'Search results', 'bricks' );
							break;

						case 'error':
							$default_condition = esc_html__( 'Error page', 'bricks' );
							break;

						// WooCommerce
						case 'wc_archive':
							$default_condition = esc_html__( 'Product archive', 'bricks' );
							break;

						case 'wc_product':
							$default_condition = esc_html__( 'Single product', 'bricks' );
							break;

						case 'wc_cart':
							$default_condition = esc_html__( 'Cart', 'bricks' );
							break;

						case 'wc_cart_empty':
							$default_condition = esc_html__( 'Empty cart', 'bricks' );
							break;

						case 'wc_form_checkout':
							$default_condition = esc_html__( 'Checkout', 'bricks' );
							break;

						case 'wc_form_pay':
							$default_condition = esc_html__( 'Pay', 'bricks' );
							break;

						case 'wc_thankyou':
							$default_condition = esc_html__( 'Thank you', 'bricks' );
							break;

						case 'wc_order_receipt':
							$default_condition = esc_html__( 'Order receipt', 'bricks' );
							break;

						// Woo Phase 3
						case 'wc_account_dashboard':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Dashboard', 'bricks' );
							break;

						case 'wc_account_orders':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Orders', 'bricks' );
							break;

						case 'wc_account_view_order':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'View order', 'bricks' );
							break;

						case 'wc_account_downloads':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Downloads', 'bricks' );
							break;

						case 'wc_account_addresses':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Addresses', 'bricks' );
							break;

						case 'wc_account_form_edit_address':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Edit address', 'bricks' );
							break;

						case 'wc_account_form_edit_account':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Edit account', 'bricks' );
							break;

						case 'wc_account_form_login':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Login', 'bricks' );
							break;

						case 'wc_account_form_lost_password':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Lost password', 'bricks' );
							break;

						case 'wc_account_form_lost_password_confirmation':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Lost password', 'bricks' ) . ' (' . esc_html__( 'Confirmation', 'bricks' ) . ')';
							break;

						case 'wc_account_reset_password':
							$default_condition = esc_html__( 'Account', 'bricks' ) . ' - ' . esc_html__( 'Reset password', 'bricks' );
							break;
					}

					if ( $default_condition ) {
						echo esc_html__( 'Default', 'bricks' ) . ': ' . $default_condition;
					}

					return;
				}
			}

			$conditions = [];

			if ( count( $template_conditions ) ) {
				foreach ( $template_conditions as $template_condition ) {
					$sub_conditions = [];
					$main_condition = '';
					$hooks          = [];

					if ( isset( $template_condition['main'] ) ) {
						if ( $template_condition['main'] === 'hook' ) {
							// Backwards compatibility
							$main_condition = esc_html__( 'Entire website', 'bricks' );
						} else {
							$main_condition = $settings_template_controls['templateConditions']['fields']['main']['options'][ $template_condition['main'] ];
						}

						switch ( $template_condition['main'] ) {
							case 'hook':
								break;

							case 'ids':
								if ( isset( $template_condition['ids'] ) && is_array( $template_condition['ids'] ) ) {
									foreach ( $template_condition['ids'] as $id ) {
										$sub_conditions[] = get_the_title( $id );
									}
								}
								break;

							case 'postType':
								if ( isset( $template_condition['postType'] ) && is_array( $template_condition['postType'] ) ) {
									foreach ( $template_condition['postType'] as $post_type ) {
										$post_type_object = get_post_type_object( $post_type );

										if ( $post_type_object ) {
											$sub_conditions[] = $post_type_object->labels->singular_name;
										} else {
											$sub_conditions[] = ucfirst( $post_type );
										}
									}
								}
								break;

							case 'archiveType':
								if ( isset( $template_condition['archiveType'] ) && is_array( $template_condition['archiveType'] ) ) {
									foreach ( $template_condition['archiveType'] as $archive_type ) {
										$sub_conditions[] = $settings_template_controls['templateConditions']['fields']['archiveType']['options'][ $archive_type ];
									}
								}
								break;

							case 'terms':
								if ( isset( $template_condition['terms'] ) && is_array( $template_condition['terms'] ) ) {
									foreach ( $template_condition['terms'] as $term_parts ) {
										$term_parts = explode( '::', $term_parts );
										$taxonomy   = $term_parts[0];
										$term_id    = $term_parts[1];

										$term = get_term_by( 'id', $term_id, $taxonomy );

										if ( gettype( $term ) === 'object' ) {
											$sub_conditions[] = $term->name;
										}
									}
								}
								break;
						}

						// Section templates: Has hook settings (@since 1.9.2)
						$hook_name     = $template_condition['hookName'] ?? false;
						$hook_priority = $template_condition['hookPriority'] ?? 10;

						if ( $hook_name ) {
							$hooks[] = $hook_name . ' (' . $hook_priority . ')';
						}
					} else {
						echo '-';
					}

					$main_condition = isset( $template_condition['exclude'] ) ? esc_html__( 'Exclude', 'bricks' ) . ': ' . $main_condition : $main_condition;

					if ( count( $sub_conditions ) ) {
						$conditions[] = $main_condition . ' (' . join( ', ', $sub_conditions ) . ')';
					} else {
						$conditions[] = $main_condition;
					}

					// Show hooks
					if ( count( $hooks ) ) {
						$conditions[] = '<ul>';

						foreach ( $hooks as $hook ) {
							$conditions[] = "<li><span>Hook</span>: <code>$hook</code></li>";
						}

						$conditions[] = '</ul>';
					}
				}
			} else {
				echo '-';
			}

			if ( count( $conditions ) ) {
				echo '<ul>';

				foreach ( $conditions as $condition ) {
					echo '<li>' . $condition . '</li>';
				}

				echo '</ul>';
			}
		}

		// Template type
		elseif ( $column === 'template_type' ) {
			$template_types = Setup::$control_options['templateTypes'];

			$output_template_type = array_key_exists( $template_type, $template_types ) ? $template_types[ $template_type ] : '-';

			echo $output_template_type;

			// Template bundle
			$template_bundles = get_the_terms( $post_id, BRICKS_DB_TEMPLATE_TAX_BUNDLE );

			if ( is_array( $template_bundles ) ) {
				$bundle_url = [];

				foreach ( $template_bundles as $bundle ) {
					$bundle_list_url = admin_url( 'edit.php?post_type=' . BRICKS_DB_TEMPLATE_SLUG . '&template_bundle=' . $bundle->slug );
					$bundle_edit_url = get_edit_tag_link( $bundle->term_id, BRICKS_DB_TEMPLATE_TAX_BUNDLE );

					$bundle_url[] = '<a href="' . esc_url( $bundle_list_url ) . '">' . $bundle->name . '</a> (<a href="' . $bundle_edit_url . '">' . esc_html( 'edit', 'bricks' ) . '</a>)';
				}

				echo '<br>' . esc_html__( 'Bundle', 'bricks' ) . ': ' . join( ', ', $bundle_url );
			}

			// Template tag
			$template_tags = get_the_terms( $post_id, BRICKS_DB_TEMPLATE_TAX_TAG );

			if ( is_array( $template_tags ) ) {
				$tag_url = [];

				foreach ( $template_tags as $tag ) {
					$tag_list_url = admin_url( 'edit.php?post_type=' . BRICKS_DB_TEMPLATE_SLUG . '&template_tag=' . $tag->slug );
					$tag_edit_url = get_edit_tag_link( $tag->term_id, BRICKS_DB_TEMPLATE_TAX_TAG );

					$tag_url[] = '<a href="' . esc_url( $tag_list_url ) . '">' . $tag->name . '</a> (<a href="' . $tag_edit_url . '">' . esc_html( 'edit', 'bricks' ) . '</a>)';
				}

				echo '<br>' . esc_html__( 'Tags', 'bricks' ) . ': ' . join( ', ', $tag_url );
			}
		}

		// Template shortcode
		elseif ( $column === 'shortcode' ) {
			$shortcode = "[bricks_template id=\"$post_id\"]";

			echo '<input type="text" size="' . strlen( $shortcode ) . '" class="bricks-copy-to-clipboard" readonly title="' . esc_attr( 'Copy to clipboard', 'bricks' ) . '" data-success="' . esc_attr( 'Copied to clipboard', 'bricks' ) . '" value="' . esc_attr( $shortcode ) . '">';
		}

		return $column;
	}

	/**
	 * Set default settings
	 *
	 * @since 1.0
	 */
	public static function set_default_settings() {
		add_option(
			BRICKS_DB_GLOBAL_SETTINGS,
			[
				'postTypes'              => [ 'page' ],
				'builderMode'            => 'dark',
				'builderToolbarLogoLink' => 'current',
			]
		);
	}

	public function gutenberg_scripts() {
		if ( Helpers::is_post_type_supported() && Capabilities::current_user_can_use_builder() ) {
			wp_enqueue_style( 'bricks-admin', BRICKS_URL_ASSETS . 'css/admin.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/admin.min.css' ) );

			wp_enqueue_script( 'bricks-gutenberg', BRICKS_URL_ASSETS . 'js/gutenberg.min.js', [ 'jquery' ], filemtime( BRICKS_PATH_ASSETS . 'js/gutenberg.min.js' ), true );
		}

		// Enqueue component-related scripts, if the components in the block editor are enabled (@since 2.1)
		if ( Database::get_setting( 'bricksComponentsInBlockEditor' ) ) {
			// Register a dummy handle and add scoped CSS inline
			wp_register_style( 'bricks-frontend-gutenberg', false );
			wp_enqueue_style( 'bricks-frontend-gutenberg' );

			// Scope frontend CSS to Gutenberg editor canvas
			// Preserve :root variables, @font-face, @keyframes at root level
			if ( Database::get_setting( 'cssLoading' ) !== 'file' ) {
				$frontend_css_file = BRICKS_PATH_ASSETS . 'css/frontend-layer.min.css';
			} else {
				$frontend_css_file = BRICKS_PATH_ASSETS . 'css/frontend.min.css';
			}

			if ( file_exists( $frontend_css_file ) ) {
				$frontend_css = file_get_contents( $frontend_css_file );
				if ( $frontend_css ) {
					$scoped_css = \Bricks\Integrations\Block_Editor::scope_css_for_gutenberg( $frontend_css );
					wp_add_inline_style( 'bricks-frontend-gutenberg', $scoped_css );
				}
			}

			wp_enqueue_script( 'bricks-scripts', BRICKS_URL_ASSETS . 'js/bricks.min.js', [], filemtime( BRICKS_PATH_ASSETS . 'js/bricks.min.js' ), true );

			// Enqueue icon fonts for Gutenberg editor
			wp_enqueue_style( 'bricks-font-awesome-6', BRICKS_URL_ASSETS . 'css/libs/font-awesome-6.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/libs/font-awesome-6.min.css' ) );
			wp_enqueue_style( 'bricks-font-awesome-6-brands', BRICKS_URL_ASSETS . 'css/libs/font-awesome-6-brands.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/libs/font-awesome-6-brands.min.css' ) );
			wp_enqueue_style( 'bricks-ionicons', BRICKS_URL_ASSETS . 'css/libs/ionicons.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/libs/ionicons.min.css' ) );
			wp_enqueue_style( 'bricks-themify-icons', BRICKS_URL_ASSETS . 'css/libs/themify-icons.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/libs/themify-icons.min.css' ) );

			wp_enqueue_script( 'bricks-gutenberg-components', BRICKS_URL_ASSETS . 'js/integrations/gutenberg-components.min.js', [ 'jquery' ], filemtime( BRICKS_PATH_ASSETS . 'js/integrations/gutenberg-components.min.js' ), true );

			// Enqueue icon font data for Gutenberg controls
			wp_enqueue_script( 'bricks-gutenberg-icon-fonts-bridge', BRICKS_URL_ASSETS . 'js/integrations/gutenberg/controls/icon-fonts-bridge.js', [ 'bricks-gutenberg-components' ], filemtime( BRICKS_PATH_ASSETS . 'js/integrations/gutenberg/controls/icon-fonts-bridge.js' ), true );
		}

		/**
		 * Check if the post/page is built with Bricks (must have Bricks data)
		 *
		 * @since 1.12.2
		 */
		$screen                 = get_current_screen();
		$post_id                = get_the_ID();
		$show_built_with_bricks = false;

		if ( $screen && $screen->base === 'post' && $post_id ) {
			$post = get_post( $post_id );
			// Check: No Bricks data
			if ( Helpers::get_bricks_data( $post_id, 'content', true ) ) {
				$show_built_with_bricks = true;
			}

			// Check: Gutenberg data > Don't show "Built with Bricks"
			if ( ! empty( $post->post_content ) && use_block_editor_for_post( $post ) ) {
				$show_built_with_bricks = false;
			}
		}

		$global_classes = get_option( BRICKS_DB_GLOBAL_CLASSES, [] );

		// Retrieve only the classes names & IDs
		$global_classes_names_ids = [];
		foreach ( $global_classes as $class ) {
			$global_classes_names_ids[] = [
				'name' => $class['name'],
				'id'   => $class['id'],
			];
		}

		// Get icon sets and custom icons for Gutenberg
		$icon_sets          = get_option( BRICKS_DB_ICON_SETS, [] );
		$custom_icons       = get_option( BRICKS_DB_CUSTOM_ICONS, [] );
		$disabled_icon_sets = Database::get_setting( 'disabledIconSets', [] );

		$components            = [];
		$enabled_component_ids = [];

		if ( Database::get_setting( 'bricksComponentsInBlockEditor' ) ) {
			$all_components = get_option( BRICKS_DB_COMPONENTS, [] );

			// Process components to inherit select options for properties without options
			$components = $this->process_components_for_gutenberg( $all_components );

			// Get list of enabled component IDs for JavaScript logic
			if ( \Bricks\Database::get_setting( 'bricksComponentsInBlockEditor' ) === 'all' ) {
				// All components are enabled
				foreach ( $all_components as $component ) {
					if ( ! empty( $component['id'] ) ) {
						$enabled_component_ids[] = $component['id'];
					}
				}
			} else {
				// Only manually enabled components (have blockEditor property set)
				foreach ( $all_components as $component ) {
					if ( ! empty( $component['id'] ) && ! empty( $component['blockEditor'] ) ) {
						$enabled_component_ids[] = $component['id'];
					}
				}
			}
		}

		$i18n = I18n::get_admin_i18n();

		// Merge builder i18n
		$i18n = array_merge( $i18n, I18n::get_builder_i18n() );

		$all_section_templates = Templates::get_templates_list( [ 'section' ], get_the_ID() );

		$gutenberg_data = [
			'globalClassesNamesIds' => $global_classes_names_ids,
			'iconSets'              => $icon_sets,
			'customIcons'           => $custom_icons,
			'disabledIconSets'      => $disabled_icon_sets,
			'postTypes'             => Helpers::get_registered_post_types(),
			'taxonomies'            => get_taxonomies( [ 'public' => true ], 'objects' ),
			'userRoles'             => wp_roles()->get_names(),
			'components'            => $components,
			'enabledComponentIds'   => $enabled_component_ids,
			'sectionTemplates'      => $all_section_templates,
		];

		// Always localize data for Gutenberg
		// Use wp-blocks which is always available in the block editor
		wp_localize_script(
			'wp-blocks',
			'bricksGutenbergData',
			$gutenberg_data
		);
	}

	/**
	 * Process components to inherit select options for Gutenberg
	 *
	 * @param array $components Array of components.
	 * @return array Processed components with inherited select options.
	 */
	private function process_components_for_gutenberg( $components ) {
		if ( ! class_exists( '\Bricks\Integrations\Block_Editor' ) ) {
			return $components;
		}

		$block_editor         = new \Bricks\Integrations\Block_Editor();
		$processed_components = [];

		foreach ( $components as $component ) {
			$processed_component = $component;

			// Process properties to inherit select options
			if ( isset( $component['properties'] ) && is_array( $component['properties'] ) ) {
				foreach ( $component['properties'] as $index => $property ) {
					// For select properties without options, try to inherit from connected elements
					if ( isset( $property['type'] ) && $property['type'] === 'select' && empty( $property['options'] ) ) {
						$inherited_options = $block_editor->get_select_options_from_connected_elements( $property, $component['elements'] );
						if ( $inherited_options ) {
							$processed_component['properties'][ $index ]['options'] = $inherited_options;
						}
					}
				}
			}

			$processed_components[] = $processed_component;
		}

		return $processed_components;
	}

	/**
	 * Admin scripts and styles
	 *
	 * @since 1.0
	 */
	public function admin_enqueue_scripts( $hook ) {
		wp_enqueue_style( 'bricks-admin', BRICKS_URL_ASSETS . 'css/admin.min.css', [], filemtime( BRICKS_PATH_ASSETS . 'css/admin.min.css' ) );

		if ( is_rtl() ) {
			wp_enqueue_style( 'bricks-admin-rtl', BRICKS_URL_ASSETS . 'css/admin-rtl.min.css', [ 'bricks-admin' ], filemtime( BRICKS_PATH_ASSETS . 'css/admin-rtl.min.css' ) );
		}

		// Is admin page="bricks-elements" (@since 2.0)
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'bricks-elements' ) {
			wp_enqueue_style( 'bricks-font-awesome-6', BRICKS_URL_ASSETS . 'css/libs/font-awesome-6.min.css', [ 'bricks-admin' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/font-awesome-6.min.css' ) );
			wp_enqueue_style( 'bricks-font-awesome-6-brands', BRICKS_URL_ASSETS . 'css/libs/font-awesome-6-brands.min.css', [ 'bricks-admin' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/font-awesome-6-brands.min.css' ) );
			wp_enqueue_style( 'bricks-ionicons', BRICKS_URL_ASSETS . 'css/libs/ionicons.min.css', [ 'bricks-admin' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/ionicons.min.css' ) );
			wp_enqueue_style( 'bricks-themify-icons', BRICKS_URL_ASSETS . 'css/libs/themify-icons.min.css', [ 'bricks-admin' ], filemtime( BRICKS_PATH_ASSETS . 'css/libs/themify-icons.min.css' ) );
		}

		wp_enqueue_script( 'bricks-admin', BRICKS_URL_ASSETS . 'js/admin.min.js', [ 'jquery' ], filemtime( BRICKS_PATH_ASSETS . 'js/admin.min.js' ), true );

		$screen  = get_current_screen();
		$post_id = get_the_ID();

		// Check if the post/page is rendered with Bricks (directly on the page, or through a Bricks template) (@since 1.12)
		$render_with_bricks = false;
		if ( $screen && $screen->base === 'post' && $post_id ) {
			$post = get_post( $post_id );
			// Check: No Gutenberg data > Render with Bricks
			$render_with_bricks = empty( $post->post_content );
			if ( $render_with_bricks ) {
				// Check: No Bricks data
				if ( ! Helpers::get_bricks_data( $post_id, 'content' ) ) {
					$render_with_bricks = false;

					// Set active templates to check if any template renders this post
					Database::set_active_templates();

					// Current page is rendered through Bricks template
					if ( Database::$active_templates['content'] != 0 && Database::$active_templates['content'] != $post_id ) {
						$render_with_bricks = true;
					}
				}
			}
		}

		/**
		 * STEP: Add script to modify Bricks theme data in the themes.php page
		 *
		 * @since 2.0.2
		 */
		if ( $hook === 'themes.php' ) {
			add_action(
				'admin_footer',
				function() {
					?>
			<script>
				jQuery(document).ready(function($) {
					// Modify Bricks theme data
					if (Array.isArray(wp.themes.data.themes)) {
						// Find the Bricks theme and modify its update string
						wp.themes.data.themes.forEach(theme => {
							if (theme.id === 'bricks') {
								// Get first URL in theme.update string
								const urlMatch = theme.update.match(/https?:\/\/[^\s]+/)
								if (urlMatch) {
									const url = urlMatch[0];
									// Add target="_blank" to the URL in theme.update string
									theme.update = theme.update.replace(url, url + '/" target="_blank" ')

									// Remove any URL parameters
									theme.update = theme.update.replace(/(\?|\&)[^"]+/, '')
								}

								// Remove 'thickbox' from theme.update string
								theme.update = theme.update.replace(/thickbox/g, '')
							}
						})
					}
				})
			</script>
					<?php
				}
			);
		}

		// Add filterByUnused parameter to the data if it exists
		$filter_by_unused = isset( $_GET['unused'] ) && $_GET['unused'] ? 'true' : 'false';

		wp_localize_script(
			'bricks-admin',
			'bricksData',
			[
				'title'                        => BRICKS_NAME,
				'ajaxUrl'                      => admin_url( 'admin-ajax.php' ),
				'builderParam'                 => BRICKS_BUILDER_PARAM,
				'postId'                       => get_the_ID(),
				'nonce'                        => wp_create_nonce( 'bricks-nonce-admin' ),
				'i18n'                         => I18n::get_all_i18n(),
				'renderWithBricks'             => $render_with_bricks,
				'builderAccessPermissions'     => Builder_Permissions::get_sections( true ), // @since 2.0
				'defaultCapabilities'          => Builder_Permissions::DEFAULT_CAPABILITIES, // @since 2.0
				'defaultCapabilityPermissions' => [
					Capabilities::FULL_ACCESS  => Builder_Permissions::get_default_capability_permissions( Capabilities::FULL_ACCESS ),
					Capabilities::EDIT_CONTENT => Builder_Permissions::get_default_capability_permissions( Capabilities::EDIT_CONTENT ),
					Capabilities::NO_ACCESS    => Builder_Permissions::get_default_capability_permissions( Capabilities::NO_ACCESS ),
				], // @since 2.0
				'filterByUnused'               => $filter_by_unused, // @since 2.0
			]
		);
	}

	/**
	 * Admin menu
	 *
	 * @since 1.0
	 */
	public function admin_menu() {
		$menu_icon = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB3aWR0aD0iMzVweCIgaGVpZ2h0PSI0NXB4IiB2aWV3Qm94PSIwIDAgMzUgNDUiIHZlcnNpb249IjEuMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayI+CiAgICA8IS0tIEdlbmVyYXRvcjogU2tldGNoIDU5LjEgKDg2MTQ0KSAtIGh0dHBzOi8vc2tldGNoLmNvbSAtLT4KICAgIDx0aXRsZT5iPC90aXRsZT4KICAgIDxkZXNjPkNyZWF0ZWQgd2l0aCBTa2V0Y2guPC9kZXNjPgogICAgPGcgaWQ9IkxvZ29zLC1GYXZpY29uIiBzdHJva2U9Im5vbmUiIHN0cm9rZS13aWR0aD0iMSIgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIj4KICAgICAgICA8ZyBpZD0iRmF2aWNvbi0oNjR4NjQpIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMTYuMDAwMDAwLCAtMTEuMDAwMDAwKSIgZmlsbD0iIzIxMjEyMSIgZmlsbC1ydWxlPSJub256ZXJvIj4KICAgICAgICAgICAgPHBhdGggZD0iTTI1LjE4NzUsMTEuMzQzNzUgTDI1LjkzNzUsMTEuODEyNSBMMjUuOTM3NSwyNC44NDM3NSBDMjguNTgzMzQ2NiwyMy4wOTM3NDEzIDMxLjUxMDQwMDYsMjIuMjE4NzUgMzQuNzE4NzUsMjIuMjE4NzUgQzM5LjM0Mzc3MzEsMjIuMjE4NzUgNDMuMTc3MDY4MSwyMy44MzMzMTcyIDQ2LjIxODc1LDI3LjA2MjUgQzQ5LjIxODc2NSwzMC4yOTE2ODI4IDUwLjcxODc1LDM0LjI3MDgwOTcgNTAuNzE4NzUsMzkgQzUwLjcxODc1LDQzLjc1MDAyMzcgNDkuMjA4MzQ4NCw0Ny43MjkxNTA2IDQ2LjE4NzUsNTAuOTM3NSBDNDMuMTQ1ODE4MSw1NC4xNjY2ODI4IDM5LjMyMjkzOTcsNTUuNzgxMjUgMzQuNzE4NzUsNTUuNzgxMjUgQzMwLjY5Nzg5NjYsNTUuNzgxMjUgMjcuMjYwNDMwOSw1NC4zNDM3NjQ0IDI0LjQwNjI1LDUxLjQ2ODc1IEwyNC40MDYyNSw1NSBMMTYuMDMxMjUsNTUgTDE2LjAzMTI1LDEyLjM3NSBMMjUuMTg3NSwxMS4zNDM3NSBaIE0zMy4xMjUsMzAuNjg3NSBDMzAuOTE2NjU1NiwzMC42ODc1IDI5LjA3MjkyNDEsMzEuNDM3NDkyNSAyNy41OTM3NSwzMi45Mzc1IEMyNi4xMTQ1NzU5LDM0LjQ3OTE3NDQgMjUuMzc1LDM2LjQ5OTk4NzUgMjUuMzc1LDM5IEMyNS4zNzUsNDEuNTAwMDEyNSAyNi4xMTQ1NzU5LDQzLjUxMDQwOTEgMjcuNTkzNzUsNDUuMDMxMjUgQzI5LjA1MjA5MDYsNDYuNTUyMDkwOSAzMC44OTU4MjIyLDQ3LjMxMjUgMzMuMTI1LDQ3LjMxMjUgQzM1LjQ3OTE3ODQsNDcuMzEyNSAzNy4zODU0MDk0LDQ2LjUyMDg0MTMgMzguODQzNzUsNDQuOTM3NSBDNDAuMjgxMjU3Miw0My4zNzQ5OTIyIDQxLDQxLjM5NTg0NTMgNDEsMzkgQzQxLDM2LjYwNDE1NDcgNDAuMjcwODQwNiwzNC42MTQ1OTEzIDM4LjgxMjUsMzMuMDMxMjUgQzM3LjM1NDE1OTQsMzEuNDY4NzQyMiAzNS40NTgzNDUsMzAuNjg3NSAzMy4xMjUsMzAuNjg3NSBaIiBpZD0iYiI+PC9wYXRoPgogICAgICAgIDwvZz4KICAgIDwvZz4KPC9zdmc+';

		/**
		 * Handle form submissions standalone menu first
		 *
		 * Only show standalone form submissions menu if:
		 * 1. Form submissions are enabled in global settings
		 * 2. User has form submission access capability
		 * 3. User has no other Bricks access
		 *
		 * This creates a limited menu just for viewing form submissions for users with restricted access
		 *
		 * @since 1.12
		 */
		if (
			isset( Database::$global_settings['saveFormSubmissions'] ) &&
			Capabilities::$form_submission_access &&
			! current_user_can( 'manage_options' )
		) {
			// Handle bulk actions
			Integrations\Form\Submission_Table::handle_custom_actions();

			// Add top-level menu page for form submissions
			$submissions_page = add_menu_page(
				'Bricks - ' . esc_html__( 'Form Submissions', 'bricks' ),
				'Bricks - ' . esc_html__( 'Form Submissions', 'bricks' ),
				Capabilities::FORM_SUBMISSION_ACCESS,
				'bricks-form-submissions',
				[ $this, 'admin_screen_form_submissions' ],
				$menu_icon,
				2
			);

			$this->setup_submissions_page( $submissions_page );
		}

		// Return: Current user has no access to Bricks admin settings
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			BRICKS_NAME,
			BRICKS_NAME,
			self::EDITING_CAP,
			'bricks',
			[ $this, 'admin_screen_getting_started' ],
			$menu_icon,
			// 'dashicons-editor-bold',
			// BRICKS_URL_ASSETS . 'images/bricks-favicon-b.svg',
			2
		);

		add_submenu_page(
			'bricks',
			esc_html__( 'Getting Started', 'bricks' ),
			esc_html__( 'Getting Started', 'bricks' ),
			self::EDITING_CAP,
			'bricks',
			[ $this, 'admin_screen_getting_started' ]
		);

		add_submenu_page(
			'bricks',
			esc_html__( 'Templates', 'bricks' ),
			esc_html__( 'Templates', 'bricks' ),
			self::EDITING_CAP,
			'edit.php?post_type=' . BRICKS_DB_TEMPLATE_SLUG
		);

		add_submenu_page(
			'bricks',
			esc_html__( 'Settings', 'bricks' ),
			esc_html__( 'Settings', 'bricks' ),
			self::EDITING_CAP,
			'bricks-settings',
			[ $this, 'admin_screen_settings' ]
		);

		add_submenu_page(
			'bricks',
			esc_html__( 'Elements', 'bricks' ),
			esc_html__( 'Elements', 'bricks' ),
			'manage_options',
			'bricks-elements',
			[ $this, 'admin_screen_elements' ]
		);

		add_submenu_page(
			'bricks',
			esc_html__( 'Custom Fonts', 'bricks' ),
			esc_html__( 'Custom Fonts', 'bricks' ),
			self::EDITING_CAP,
			'edit.php?post_type=' . BRICKS_DB_CUSTOM_FONTS
		);

		// Form submissions (@since 1.9.2)
		if ( isset( Database::$global_settings['saveFormSubmissions'] ) && Capabilities::$form_submission_access ) {
			// Handle bulk actions (failed to hook on handle-bulk_actions)
			Integrations\Form\Submission_Table::handle_custom_actions();

			$submissions_page = add_submenu_page(
				'bricks',
				esc_html__( 'Form Submissions', 'bricks' ),
				esc_html__( 'Form Submissions', 'bricks' ),
				Capabilities::FORM_SUBMISSION_ACCESS,
				'bricks-form-submissions',
				[ $this, 'admin_screen_form_submissions' ]
			);

			$this->setup_submissions_page( $submissions_page );
		}

		add_submenu_page(
			'bricks',
			esc_html__( 'Sidebars', 'bricks' ),
			esc_html__( 'Sidebars', 'bricks' ),
			self::EDITING_CAP,
			'bricks-sidebars',
			[ $this, 'admin_screen_sidebars' ]
		);

		add_submenu_page(
			'bricks',
			esc_html__( 'System Information', 'bricks' ),
			esc_html__( 'System Information', 'bricks' ),
			self::EDITING_CAP,
			'bricks-system-information',
			[ $this, 'admin_screen_system_information' ]
		);

		add_submenu_page(
			'bricks',
			esc_html__( 'License', 'bricks' ),
			esc_html__( 'License', 'bricks' ),
			self::EDITING_CAP,
			'bricks-license',
			[ $this, 'admin_screen_license' ]
		);
	}


	/**
	 * Setup form submissions page options and columns
	 *
	 * @param string $submissions_page The page hook.
	 */
	private function setup_submissions_page( $submissions_page ) {
		// Add screen options
		add_action( "load-$submissions_page", [ 'Bricks\Integrations\Form\Submission_Table', 'add_screen_options' ] );

		// Add columns if form_id is present and valid
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		if ( $form_id ) {
			add_filter( "manage_{$submissions_page}_columns", [ 'Bricks\Integrations\Form\Submission_Table', 'screen_columns' ] );
		}
	}

	public function admin_screen_getting_started() {
		require_once 'admin/admin-screen-getting-started.php';
	}

	public function admin_screen_settings() {
		require_once 'admin/admin-screen-settings.php';
	}

	public function admin_screen_elements() {
		require_once 'admin/admin-screen-elements.php';
	}

	public function admin_screen_sidebars() {
		require_once 'admin/admin-screen-sidebars.php';
	}

	public function admin_screen_system_information() {
		require_once 'admin/admin-screen-system-information.php';
	}

	public function admin_screen_license() {
		require_once 'admin/admin-screen-license.php';
	}

	/**
	 * Form submissions admin screen
	 *
	 * @since 1.9.2
	 */
	public function admin_screen_form_submissions() {
		require_once 'admin/admin-screen-form-submissions.php';
	}

	/**
	 * Admin notice: Show regenerate CSS files notification after Bricks theme update
	 *
	 * @since 1.3.7
	 */
	public static function admin_notice_regenerate_css_files() {
		// Show update & CSS files regeneration admin notice ONCE after theme update
		if ( get_option( BRICKS_CSS_FILES_ADMIN_NOTICE ) ) {
			$text  = '<p>' . esc_html__( 'You are now running the latest version', 'bricks' ) . ': ' . BRICKS_VERSION . ' 🥳</p>';
			$text .= '<p>' . esc_html__( 'Your CSS files were automatically generated in the background.', 'bricks' ) . '</p>';
			$text .= '<a class="button button-primary" href="' . admin_url( 'admin.php?page=bricks-settings#tab-performance' ) . '">' . esc_html__( 'Manually regenerate CSS files', 'bricks' ) . '</a>';
			$text .= '<a class="button" href="https://bricksbuilder.io/release/bricks-' . BRICKS_VERSION . '/" target="_blank" style="margin: 4px">' . esc_html__( 'View changelog', 'bricks' ) . '</a>';

			echo wp_kses_post( sprintf( '<div class="notice notice-info is-dismissible">%s</div>', wpautop( $text ) ) );

			// Remove admin notice option entry to not show it again
			delete_option( BRICKS_CSS_FILES_ADMIN_NOTICE );

			// Fallback: Regenerate CSS files now (@since 1.8.1)
			if ( Database::get_setting( 'cssLoading' ) === 'file' ) {
				Assets_Files::regenerate_css_files();

				// NOTE: Not in use. Requires WP cron & not needed here anymore as we already run the updated theme version code
				// Assets_Files::schedule_css_file_regeneration();
			}
		}
	}

	/**
	 * Admin notice: Show missing code signatures notification
	 *
	 * @since 1.9.7
	 */
	public static function admin_notice_code_signatures() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add option table entry to not show hide this message
		if ( isset( $_GET['code-sig-notice-off'] ) ) {
			update_option( BRICKS_CODE_SIGNATURES_ADMIN_NOTICE, true );
			return;
		}

		if ( get_option( BRICKS_CODE_SIGNATURES_ADMIN_NOTICE ) ) {
			return;
		}

		if ( get_option( BRICKS_CODE_SIGNATURES_LAST_GENERATED ) && \Bricks\Helpers::code_execution_enabled() ) {
			return;
		}

		$text = '';

		$text .= '<h3>BRICKS: BREAKING CHANGES 🚨</h3>';

		$text .= '<p><strong>1. ' . esc_html__( 'Code execution: Disabled by default', 'bricks' ) . ' 🔌</strong></p>';

		$text .= '<p>' . esc_html__( 'Code execution, if needed, must be explicitly enabled.', 'bricks' );
		$text .= ' (<a href="https://bricksbuilder.io/release/bricks-1-9-7/" target="_blank">' . esc_html__( 'Learn more', 'bricks' ) . '</a>)';
		$text .= '</p>';
		$text .= '<p>' . esc_html__( 'Enable code execution if your site uses Code elements, SVG elements (source: code), Query editors, or "echo" tags.', 'bricks' );

		$text .= '<p><strong>2. ' . esc_html__( 'New feature', 'bricks' ) . ': ' . esc_html__( 'Code signatures', 'bricks' ) . ' 🔑</strong></p>';
		$text .= '<p>' . esc_html__( 'All Code elements, SVG elements (source: code), and Query editor instances now require code signatures.', 'bricks' ) . '</p>';

		$text .= '<p>' . esc_html__( 'Please review your code and generate code signatures.', 'bricks' ) . '</p>';

		$text .= '<p><strong>3. ' . esc_html__( 'Echo tags: Allow functions via filter', 'bricks' ) . ' 👀</strong></p>';
		$text .= '<p>' . sprintf( esc_html__( 'Function names called through the "echo" tag must be whitelisted via the new %s filter.', 'bricks' ), '<code>bricks/code/echo_function_names</code>' );
		$text .= ' (' . Helpers::article_link( 'filter-bricks-code-echo_function_names', esc_html__( 'Learn more', 'bricks' ) ) . ')';
		$text .= '</p>';

		$text .= '<a class="button button-primary" href="' . admin_url( 'admin.php?page=bricks-settings#tab-custom-code' ) . '">' . esc_html__( 'Go to', 'bricks' ) . ': ' . esc_html__( 'Custom code', 'bricks' ) . '</a>';

		// Append URL param 'code-sig-notice-off' to current admin URL
		$url   = admin_url( 'admin.php?page=bricks-settings&code-sig-notice-off=1&time=' . time() . '#tab-custom-code' );
		$text .= '<a class="button" href="' . $url . '" style="margin: 4px">' . esc_html__( 'Dismiss', 'bricks' ) . '</a>';

		echo wp_kses_post( sprintf( '<div class="notice notice-info">%s</div>', wpautop( $text ) ) );
	}

	/**
	 * Admin notices
	 *
	 * @since 1.0
	 */
	public function admin_notices() {
		/**
		 * STEP: site URL is HTTP instead of HTTPS (and notice has not been dismiss before): Show admin notice
		 *
		 * @since 1.8.4
		 */
		if ( current_user_can( 'manage_options' ) ) {
			$site_url = get_option( 'siteurl' );

			if ( $site_url && strpos( $site_url, 'http://' ) !== false ) {
				if ( ! get_option( 'bricks_https_notice_dismissed', false ) ) {
					$text = 'Bricks: ' . esc_html__( 'Please update your WordPress URLs under Settings > General to use https:// instead of http:// for optimal performance & functionality. Valid SSL certificate required.', 'bricks' );

					echo self::admin_notice_html( 'warning', $text, true, 'brxe-https-notice' );
				}
			}
		}

		$bricks_notice = isset( $_GET['bricks_notice'] ) ? sanitize_text_field( $_GET['bricks_notice'] ) : '';

		if ( ! $bricks_notice ) {
			return;
		}

		$type = 'warning';
		$text = '';

		switch ( $bricks_notice ) {
			case 'settings_saved':
				// Bricks settings saved
				$text = esc_html__( 'Settings saved', 'bricks' ) . '.';
				$type = 'success';
				break;

			case 'settings_resetted':
				// Bricks settings resetted
				$text = esc_html__( 'Settings resetted', 'bricks' ) . '.';
				$type = 'success';
				break;

			case 'error_role_manager':
				// User role not allowed to use builder
				$user = wp_get_current_user();
				$role = isset( $user->roles[0] ) ? $user->roles[0] : '';
				// translators: %s: user role, %s: theme name
				$text = sprintf(
					esc_html__( 'Your user role "%1$s" is not allowed to edit this post type with %2$s. Please get in touch with the site admin to change it.', 'bricks' ),
					$role,
					'Bricks'
				);
				break;

			case 'error_post_type':
				// Post type is not enabled for Bricks
				$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : '';
				// translators: %s: post type, %s: theme name, %s: settings page
				$text = sprintf(
					esc_html__( '%1$s is not enabled for post type "%2$s". Go to "%3$s > %4$s" to enable this post type.', 'bricks' ),
					'Bricks',
					$post_type,
					'Bricks',
					esc_html__( 'Settings', 'bricks' )
				);
				break;

			case 'post_meta_deleted':
				// translators: %s: post title
				$text = sprintf( esc_html__( '%1$s data for "%2$s" deleted.', 'bricks' ), 'Bricks', get_the_title() );
				$type = 'success';
				break;
		}

		$html = sprintf( '<div class="notice notice-' . sanitize_html_class( $type, 'warning' ) . ' is-dismissible">%s</div>', wpautop( $text ) );

		echo self::admin_notice_html( $type, $text );
	}

	public static function admin_notice_html( $type, $text, $dismissible = true, $extra_classes = '' ) {
		$classes = [ 'notice', "notice-$type" ];

		if ( $dismissible ) {
			$classes[] = 'is-dismissible';
		}

		if ( $extra_classes ) {
			$classes[] = $extra_classes;
		}

		return wp_kses_post( sprintf( '<div class="' . implode( ' ', $classes ) . '">%s</div>', wpautop( $text ) ) );
	}

	/**
	 * Add custom post state: "Bricks"
	 *
	 * If post has last been saved with Bricks (check post meta value: '_bricks_editor_mode')
	 *
	 * @param array    $post_states Array of post states.
	 * @param \WP_Post $post        Current post object.
	 *
	 * @since 1.0
	 */
	public function add_post_state( $post_states, $post ) {
		if (
		! Helpers::is_post_type_supported() ||
		! Capabilities::current_user_can_use_builder( $post->ID ) ||
		Helpers::get_editor_mode( $post->ID ) === 'wordpress'
		) {
			return $post_states;
		}

		$post_states['bricks'] = BRICKS_NAME;

		$data_type   = 'content';
		$is_template = get_post_type( $post->ID ) === BRICKS_DB_TEMPLATE_SLUG;

		if ( $is_template ) {
			$template_type = Templates::get_template_type( $post->ID );

			if ( $template_type === 'header' ) {
				$data_type = 'header';
			} elseif ( $template_type === 'footer' ) {
				$data_type = 'footer';
			}
		}

		// Checks for new data structure
		$has_container_data = get_post_meta( $post->ID, "_bricks_page_{$data_type}_2", true );

		// No Bricks container data: Remove 'Bricks' label
		if ( ! $has_container_data && ! $is_template ) {
			unset( $post_states['bricks'] );
		}

		return $post_states;
	}

	/**
	 * Add editor body class 'active'
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		global $pagenow;

		if ( ! in_array( $pagenow, [ 'post.php', 'post-new.php' ] ) ) {
			return $classes;
		}

		$editor_mode = Helpers::get_editor_mode( get_the_ID() );

		if ( ! empty( $editor_mode ) ) {
			$classes .= ' ' . $editor_mode . '-editor-active';
		}

		return $classes;
	}

	/**
	 * Add custom image sizes to WordPress media library in admin area
	 *
	 * Also used to build dropdown of control 'images' for single image element.
	 *
	 * @since 1.0
	 */
	public function image_size_names_choose( $default_sizes ) {
		global $_wp_additional_image_sizes;
		$custom_image_sizes = [];

		foreach ( $_wp_additional_image_sizes as $key => $value ) {
			$key_array         = explode( '_', $key );
			$capitalized_array = [];

			foreach ( $key_array as $string ) {
				array_push( $capitalized_array, ucfirst( $string ) );
			}

			$custom_image_sizes[ $key ] = join( ' ', $capitalized_array );
		}

		return array_merge( $default_sizes, $custom_image_sizes );
	}

	/**
	 * Make sure 'editor_mode' URL param is not removed from admin URL
	 */
	public function admin_url( $link ) {
		if ( isset( $_REQUEST['editor_mode'] ) && ! empty( $_REQUEST['editor_mode'] ) ) {
			return add_query_arg(
				[
					'editor_mode' => $_REQUEST['editor_mode']
				],
				$link
			);
		}

		return $link;
	}

	/**
	 * Save Editor mode based on the admin bar links
	 *
	 * @see Setup->admin_bar_menu()
	 *
	 * @since 1.3.7
	 */
	public function save_editor_mode() {
		$action      = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		$bricks_mode = isset( $_GET['_bricksmode'] ) ? sanitize_text_field( $_GET['_bricksmode'] ) : '';
		$editor_mode = isset( $_GET['editor_mode'] ) ? sanitize_text_field( $_GET['editor_mode'] ) : '';
		$post_id     = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;

		if ( ! $action || ! $bricks_mode || ! $editor_mode || ! $post_id ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $bricks_mode, '_bricks_editor_mode_nonce' ) ) {
			return;
		}

		update_post_meta( $post_id, BRICKS_DB_EDITOR_MODE, $editor_mode );
	}

	/**
	 * Builder tab HTML (toggle via builder tab)
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function builder_tab_html() {
		// Return: Post type not supported for Bricks OR current user is not allowed to use the builder
		if ( ! Helpers::is_post_type_supported() || ! Capabilities::current_user_can_use_builder() ) {
			return;
		}

		$post_id = ! empty( $_GET['bricks_delete_post_meta'] ) ? intval( $_GET['bricks_delete_post_meta'] ) : 0;

		// Delete post meta: content and editor mode
		if ( $post_id && Capabilities::current_user_can_use_builder( $post_id ) && current_user_can( 'manage_options' ) ) {
			delete_post_meta( $post_id, BRICKS_DB_PAGE_HEADER );
			delete_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT );
			delete_post_meta( $post_id, BRICKS_DB_PAGE_FOOTER );
			delete_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS );
			delete_post_meta( $post_id, BRICKS_DB_EDITOR_MODE );
			delete_post_meta( $post_id, BRICKS_DB_TEMPLATE_TYPE );
		}

		// Get editor mode
		$editor_mode = Helpers::get_editor_mode( get_the_ID() );
		?>

		<div id="bricks-editor" class="bricks-editor postarea wp-editor-expand">
		<?php wp_nonce_field( 'editor_mode', '_bricks_editor_mode_nonce' ); ?>
			<input type="hidden" id="bricks-editor-mode" name="_bricks_editor_mode" value="<?php echo esc_attr( $editor_mode ); ?>" />

			<div class="wp-core-ui wp-editor-wrap bricks-active">

			<?php if ( get_post_type() !== BRICKS_DB_TEMPLATE_SLUG ) { ?>
				<div class="wp-editor-tools">
					<div class="wp-editor-tabs">
						<button type="button" id="content-tmce" class="wp-switch-editor switch-tmce"><?php esc_html_e( 'Visual', 'bricks' ); ?></button>
						<button type="button" id="content-html" class="wp-switch-editor switch-html"><?php esc_html_e( 'Text', 'bricks' ); ?></button>
						<button type="button" id="content-bricks" class="wp-switch-editor switch-bricks"><?php echo BRICKS_NAME; ?></button>
					</div>
				</div>
				<?php } ?>

				<div class="wp-editor-container">
					<p>
						<a href="<?php echo Helpers::get_builder_edit_link(); ?>" class="button button-primary button-hero">
						<?php
						// translators: %s: "Bricks" (theme name)
						echo sprintf( esc_html__( 'Edit with %s', 'bricks' ), 'Bricks' );
						?>
						</a>
					</p>

				<?php if ( Database::get_setting( 'deleteBricksData', false ) ) { ?>
						<?php // translators: %s: post type ?>
					<p><a href="<?php echo esc_url( Helpers::delete_bricks_data_by_post_id() ); ?>" class="bricks-delete-post-meta button" onclick="return confirm('<?php echo sprintf( esc_html__( 'Are you sure you want to delete the Bricks-generated data for this %s?', 'bricks' ), get_post_type() ); ?>')">
						<?php esc_html_e( 'Delete Bricks data', 'bricks' ); ?>
					</a></p>
					<?php } ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * "Edit with Bricks" link for post type 'page', 'post' and all other CPTs
	 *
	 * @since 1.0
	 */
	public function row_actions( $actions, $post ) {
		$post_id = $post->ID;

		// Add "Duplicate with Bricks" link to post row actions (@since 1.12)
		if ( self::use_duplicate_content( $post_id ) ) {
			$builder_mode             = Helpers::get_editor_mode( $post_id ) === 'bricks' ? ' (Bricks)' : ' (WordPress)';
			$actions['brx_duplicate'] = sprintf(
				'<a class="bricks-duplicate" href="%s">%s</a>',
				wp_nonce_url( admin_url( 'admin.php?action=bricks_duplicate_content&post_id=' . $post_id ), 'bricks-nonce-admin' ),
				esc_html__( 'Duplicate', 'bricks' ) . $builder_mode
			);
		}

		if ( Helpers::is_post_type_supported() && Capabilities::current_user_can_use_builder( $post_id ) ) {
			// Export template
			if ( get_post_type() === BRICKS_DB_TEMPLATE_SLUG ) {
				$export_template_url = admin_url( 'admin-ajax.php' );

				// Undocumented: For multi-language support (@since 1.10)
				$export_template_args = apply_filters(
					'bricks/export_template_args',
					[
						'action'     => 'bricks_export_template',
						'nonce'      => wp_create_nonce( 'bricks-nonce-admin' ),
						'templateId' => get_the_ID(),
					],
					$post_id
				);

				$export_template_url = add_query_arg(
					$export_template_args,
					$export_template_url
				);

				$actions['export_template'] = sprintf(
					'<a href="%s">%s</a>',
					$export_template_url,
					esc_html__( 'Export Template', 'bricks' )
				);
			}

			// Edit with Bricks
			$actions['edit_with_bricks'] = sprintf(
				'<a href="%s">%s</a>',
				Helpers::get_builder_edit_link( $post_id ),
				// translators: %s: "Bricks" (theme name)
				sprintf( esc_html__( 'Edit with %s', 'bricks' ), 'Bricks' )
			);
		}

		return $actions;
	}

	/**
	 * Dismiss HTTPS notice
	 *
	 * @since 1.8.4
	 */
	public function dismiss_https_notice() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		// Dismiss admin notice
		if ( current_user_can( 'manage_options' ) ) {
			update_option( 'bricks_https_notice_dismissed', BRICKS_VERSION );
		}

		wp_die();
	}

	/**
	 * Delete form submissions table
	 *
	 * @since 1.9.2
	 */
	public function form_submissions_drop_table() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) || ! Capabilities::current_user_can_form_submission_access() ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		// Reset bricks_form_submissions table
		$result = \Bricks\Integrations\Form\Submission_Database::drop_table();

		if ( $result ) {
			// Remove 'saveFormSubmissions' Bricks setting
			$global_settings = get_option( BRICKS_DB_GLOBAL_SETTINGS );
			unset( $global_settings['saveFormSubmissions'] );
			update_option( BRICKS_DB_GLOBAL_SETTINGS, $global_settings );

			wp_send_json_success( [ 'message' => esc_html__( 'Form submission table deleted successfully.', 'bricks' ) ] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Form submission table could not be deleted.', 'bricks' ) ] );
		}
	}

	/**
	 * Reset/clear all form submissions table entries (rows)
	 *
	 * @since 1.9.2
	 */
	public function form_submissions_reset_table() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) || ! Capabilities::current_user_can_form_submission_access() ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		// Reset bricks_form_submissions table
		$result = \Bricks\Integrations\Form\Submission_Database::reset_table();

		if ( $result ) {
			wp_send_json_success( [ 'message' => esc_html__( 'Form submissions table resetted successfully.', 'bricks' ) ] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Form submissions table could not be resetted.', 'bricks' ) ] );
		}
	}

	/**
	 * Delete form submissions of form ID
	 *
	 * @since 1.9.2
	 */
	public function form_submissions_delete_form_id() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) || ! Capabilities::current_user_can_form_submission_access() ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		// Remove all rows with form_id in bricks_form_submissions table
		$form_element_id = isset( $_POST['formId'] ) ? sanitize_text_field( $_POST['formId'] ) : '';
		$result          = \Bricks\Integrations\Form\Submission_Database::remove_form_id( $form_element_id );

		if ( $result ) {
			wp_send_json_success( [ 'message' => esc_html__( 'Form submissions deleted.', 'bricks' ) ] );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Form submissions could not be deleted.', 'bricks' ) ] );
		}
	}

	/**
	 * Show admin notice
	 *
	 * @param string $message Notice message
	 * @param string $type    success|error|warning|info
	 * @param string $class   Additional CSS class
	 *
	 * @since 1.9.1
	 */
	public static function show_admin_notice( $message, $type = 'success', $class = '' ) {
		add_action(
			'admin_notices',
			function() use ( $message, $type, $class ) {
				echo "<div class='notice notice-{$type} is-dismissible {$class}'><p>{$message}</p></div>";
			}
		);
	}

	/**
	 * Dismiss Instagram access token notice
	 *
	 * @since 1.9.1
	 */
	public function dismiss_instagram_access_token_notice() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		// Dismiss admin notice
		if ( current_user_can( 'manage_options' ) ) {
			update_option( 'bricks_instagram_access_token_notice_dismissed', true );
		}

		wp_die();
	}

	/**
	 * Reindex query filters
	 *
	 * @since 1.9.6
	 */
	public function reindex_query_filters() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		// Reindex query filters
		$result = Query_Filters::get_instance()->reindex();

		if ( $result && empty( $result['error'] ) ) {
			wp_send_json_success(
				[
					'message' => esc_html__( 'Query filters reindex job started.', 'bricks' ),
					'result'  => $result,
				],
			);
		} else {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Something went wrong.', 'bricks' ),
					'result'  => $result,
				]
			);
		}
	}

	/**
	 * Regenerate code signatures
	 *
	 * @since 1.9.7
	 */
	public function regenerate_code_signatures() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		// Check if code signatures are locked (@since 1.11.1)
		if ( defined( 'BRICKS_LOCK_CODE_SIGNATURES' ) && BRICKS_LOCK_CODE_SIGNATURES ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Code signatures are locked.', 'bricks' ) ] );
		}

		if ( ! current_user_can( 'manage_options' ) || ! Capabilities::current_user_can_execute_code() ) {
			wp_send_json_error( [ 'message' => 'Sorry, you are not allowed to perform this action (no code execution capability).' ] );
		}

		// Add option table entry with version & timestamp of last code signature generation
		update_option( BRICKS_CODE_SIGNATURES_LAST_GENERATED, BRICKS_VERSION );
		update_option( BRICKS_CODE_SIGNATURES_LAST_GENERATED_TIMESTAMP, time() );

		$success = self::crawl_and_update_code_signatures();

		if ( $success ) {
			// Regenerate CSS files if 'file' is set
			if ( Database::get_setting( 'cssLoading' ) === 'file' ) {
				Assets_Files::regenerate_css_files();
			}

			wp_send_json_success(
				[
					'message' => esc_html__( 'Code signatures regenerated successfully.', 'bricks' ),
					'result'  => $success,
				],
			);
		}

		wp_send_json_error(
			[
				'message' => esc_html__( 'Something went wrong.', 'bricks' ),
				'result'  => $success,
			]
		);
	}

	/**
	 * Return query args for code signature regeneration & code review results
	 *
	 * @see Code review && crawl_and_update_code_signatures below.
	 *
	 * @since 1.9.7
	 */
	public static function get_code_instances_query_args( $filter = false ) {
		$meta_query = [
			'relation' => 'OR',
		];

		$code_instances = [
			'code'        => 's:4:"code"',
			'svg'         => 's:3:"svg"',
			'queryEditor' => 's:11:"queryEditor";',
		];

		// Include 'echo' tag for code review results
		if ( in_array( $filter, [ 'echo', 'all' ] ) ) {
			$code_instances['echo'] = '{echo:';
		}

		// Merge query function
		$merge_query_function = function( $filter, $key ) {
			return [
				[
					'key'     => BRICKS_DB_PAGE_HEADER,
					'value'   => $key,
					'compare' => 'LIKE',
				],
				[
					'key'     => BRICKS_DB_PAGE_CONTENT,
					'value'   => $key,
					'compare' => 'LIKE',
				],
				[
					'key'     => BRICKS_DB_PAGE_FOOTER,
					'value'   => $key,
					'compare' => 'LIKE',
				],
			];
		};

		// Add only the selected filter type to the $meta_query
		if ( in_array( $filter, array_keys( $code_instances ) ) ) {
			$key        = $code_instances[ $filter ];
			$meta_query = array_merge( $meta_query, $merge_query_function( $filter, $key ) );
		}

		// Add all filter types to the $meta_query
		else {
			foreach ( $code_instances as $type => $key ) {
				$meta_query = array_merge( $meta_query, $merge_query_function( $type, $key ) );
			}
		}

		return [
			'post_type'              => get_post_types(),
			'post_status'            => [ 'publish', 'draft', 'pending', 'future', 'private' ],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'cache_results'          => false,
			'update_post_term_cache' => false,
			'no_found_rows'          => true,
			'suppress_filters'       => true, // WPML (also to prevent any posts_where filters from modifying the query)
			'lang'                   => '', // Polylang
			'meta_query'             => $meta_query,
		];
	}

	/**
	 * Update code signatures for all Bricks data & global elemnts
	 *
	 * @since 1.9.7
	 *
	 * @param bool $only_regenerate_if_missing If true, only regenerate the signature if it's missing.
	 */
	public static function crawl_and_update_code_signatures( $only_regenerate_if_missing = false ) {
		// STEP: Get post IDs of all posts (of any post type) that have Bricks data
		$post_ids = get_posts( self::get_code_instances_query_args() );
		$success  = true;

		// STEP: Get header/content/footer
		foreach ( $post_ids as $post_id ) {
			// Header data
			$postmeta = BRICKS_DB_PAGE_HEADER;
			$elements = get_post_meta( $post_id, $postmeta, true );

			// Content data
			if ( empty( $elements ) ) {
				$postmeta = BRICKS_DB_PAGE_CONTENT;
				$elements = get_post_meta( $post_id, $postmeta, true );
			}

			// Footer data
			if ( empty( $elements ) ) {
				$postmeta = BRICKS_DB_PAGE_FOOTER;
				$elements = get_post_meta( $post_id, $postmeta, true );
			}

			// Skip if no elements
			if ( empty( $elements ) ) {
				continue;
			}

			// wp_slash the postmeta values
			$elements           = wp_slash( $elements );
			$elements_processed = self::process_elements_for_signature( $elements, $only_regenerate_if_missing, true );

			// Update post meta
			if ( $elements !== $elements_processed ) {
				$success = update_post_meta( $post_id, $postmeta, $elements_processed );
			}
		}

		// STEP: Global elements (no need to wp_slash the options value)
		if ( $global_elements = get_option( BRICKS_DB_GLOBAL_ELEMENTS, [] ) ) {
			$updated_global_elements = self::process_elements_for_signature( $global_elements, $only_regenerate_if_missing, false );

			// Update global elements (options table)
			if ( $updated_global_elements !== $global_elements ) {
				$success = update_option( BRICKS_DB_GLOBAL_ELEMENTS, $updated_global_elements );
			}
		}

		// STEP: Elements in Component instances
		$components        = get_option( BRICKS_DB_COMPONENTS, [] );
		$component_updated = false;
		foreach ( $components as $index => $component ) {
			$component_elements = $component['elements'] ?? [];

			if ( ! empty( $component_elements ) ) {
				$updated_component_elements = self::process_elements_for_signature( $component_elements, $only_regenerate_if_missing, false );

				if ( $updated_component_elements !== $component_elements ) {
					$components[ $index ]['elements'] = $updated_component_elements;
					$component_updated                = true;
				}
			}
		}

		if ( ! empty( $components ) && $component_updated ) {
			$success = update_option( BRICKS_DB_COMPONENTS, $components );
		}

		return $success;
	}

	/**
	 * Process code and svg elements and queryEditors to add a code signature to element settings
	 *
	 * @since 1.9.7
	 *
	 * @param array $elements
	 * @param bool  $only_regenerate_if_missing If true, only regenerate the signature if it's missing.
	 */
	public static function process_elements_for_signature( $elements = [], $only_regenerate_if_missing = false, $strip_slashes = false ) {
		if ( is_array( $elements ) && Helpers::code_execution_enabled() && current_user_can( 'manage_options' ) && Capabilities::current_user_can_execute_code() ) {
			foreach ( $elements as $index => $element ) {
				$element_settings = $element['settings'] ?? [];
				$element_name     = $element['name'] ?? '';

				// Check: Component root (@since 2.0)
				$component_instance_settings = ! empty( $element['cid'] ) && isset( $element['properties'] ) ? Helpers::get_component_instance( $element, 'settings' ) : false;

				// Handle root component element settings (@since 2.0)
				if ( $component_instance_settings ) {
					// Handle 'queryEditor' property settings, No 'code' type property yet
					foreach ( $element['properties'] as $property_id => $property ) {
						if ( ! $only_regenerate_if_missing && ! empty( $property['queryEditor'] ) ) {
								$code = $strip_slashes ? stripslashes( $property['queryEditor'] ) : $property['queryEditor'];
								$elements[ $index ]['properties'][ $property_id ]['signature'] = wp_hash( $code );
								$elements[ $index ]['properties'][ $property_id ]['user_id']   = get_current_user_id();
								$elements[ $index ]['properties'][ $property_id ]['time']      = time();
						}
					}
				}

				else {
					// Handle 'code' setting in Code & SVG element
					if ( ! empty( $element_name ) && in_array( $element_name, [ 'code', 'svg' ] ) && ! empty( $element_settings['code'] ) ) {
						if ( ! $only_regenerate_if_missing || empty( $element_settings['signature'] ) ) {
							$code                                        = $strip_slashes ? stripslashes( $element_settings['code'] ) : $element_settings['code'];
							$elements[ $index ]['settings']['signature'] = wp_hash( $code );
							$elements[ $index ]['settings']['user_id']   = get_current_user_id();
							$elements[ $index ]['settings']['time']      = time();
						}
					}

					// Handle 'queryEditor' setting when query loop is enabled
					elseif ( ! empty( $element_settings['query']['queryEditor'] ) ) {
						if ( ! $only_regenerate_if_missing || empty( $element_settings['signature'] ) ) {
							$code = $strip_slashes ? stripslashes( $element_settings['query']['queryEditor'] ) : $element_settings['query']['queryEditor'];
							$elements[ $index ]['settings']['query']['signature'] = wp_hash( $code );
							$elements[ $index ]['settings']['query']['user_id']   = get_current_user_id();
							$elements[ $index ]['settings']['query']['time']      = time();
						}
					}
				}

			}
		}

		return $elements;
	}

	/**
	 * Duplicate page or post in WP admin (Bricks or WordPress)
	 *
	 * @since 1.9.8
	 */
	public function bricks_duplicate_content() {
		// Check nonce
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		// Get post
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		// Return: User can not edit this post
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		// Return: Wrong action
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';

		if ( $action !== 'bricks_duplicate_content' ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid action', 'bricks' ) ] );
		}

		// Duplicate post/page core function
		$new_post_id = self::duplicate_content( $post_id );

		if ( ! $new_post_id ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Post could not be duplicated', 'bricks' ) ] );
		}

		wp_safe_redirect( wp_get_referer() );
	}

	/**
	 * Duplicate page or post incl. taxnomy terms (Bricks or WordPress)
	 *
	 * Handles Bricks data ID duplication as well.
	 *
	 * @param int $post_id
	 * @return int|bool
	 * @since 1.9.8
	 */
	public static function duplicate_content( $post_id = 0 ) {
		$post = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return false;
		}

		// Return: Duplicate content is not allowed (@since 1.12)
		if ( ! self::use_duplicate_content( $post_id ) ) {
			return false;
		}

		// STEP: Insert new post
		$new_post_id = wp_insert_post(
			[
				'post_author'    => get_current_user_id(),
				'post_status'    => 'draft',
				'post_title'     => $post->post_title . ' (' . esc_html__( 'Copy', 'bricks' ) . ')',
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				// 'post_name'      => $post->post_name, // Don't copy the slug or original post will take the wrong info
				'post_parent'    => $post->post_parent,
				'post_password'  => $post->post_password,
				'post_type'      => $post->post_type,
				'to_ping'        => $post->to_ping,
				'ping_status'    => $post->ping_status,
				'comment_status' => $post->comment_status,
				'menu_order'     => $post->menu_order,
			]
		);

		// STEP: Set post taxonomy terms
		$taxonomies = get_object_taxonomies( $post->post_type );

		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );

			if ( ! empty( $post_terms ) ) {
				wp_set_object_terms( $new_post_id, $post_terms, $taxonomy );
			}
		}

		// STEP: Handle non-bricks post meta, do not copy _edit_lock or new cloned post will show as being edited
		global $wpdb;

		$meta_infos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM $wpdb->postmeta
				WHERE post_id = %d AND meta_key NOT IN (%s, %s, %s, %s, %s, %s)",
				$post_id,
				BRICKS_DB_PAGE_HEADER,
				BRICKS_DB_PAGE_CONTENT,
				BRICKS_DB_PAGE_FOOTER,
				BRICKS_DB_TEMPLATE_SETTINGS,
				'_edit_lock',
				'_wp_old_slug' // Do not copy the old slug or redirect will have problems (@since 1.10)
			)
		);

		if ( count( $meta_infos ) != 0 ) {
			$sql_query        = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) VALUES ";
			$sql_query_params = [];

			foreach ( $meta_infos as $meta_info ) {
				$meta_key           = $meta_info->meta_key;
				$meta_value         = $meta_info->meta_value;
				$sql_query         .= '(%d, %s, %s),';
				$sql_query_params[] = $new_post_id;
				$sql_query_params[] = $meta_key;
				$sql_query_params[] = $meta_value;
			}

			$sql_query = rtrim( $sql_query, ',' );
			$wpdb->query( $wpdb->prepare( $sql_query, $sql_query_params ) );
		}

		// STEP: Clone template settings without conditions (@since 1.10)
		$template_settings = get_post_meta( $post_id, BRICKS_DB_TEMPLATE_SETTINGS, true );
		if ( $template_settings ) {
			unset( $template_settings['templateConditions'] );
			update_post_meta( $new_post_id, BRICKS_DB_TEMPLATE_SETTINGS, $template_settings );
		}

		// STEP: Handle Bricks data
		$area = 'content';

		// Set the content type (header, footer, content, section, popup, etc)
		if ( $post->post_type === 'bricks_template' ) {
			$area = Templates::get_template_type( $post_id );
		}

		// Get the Bricks data
		$bricks_data = Database::get_data( $post_id, $area );

		// Add bricks data to new post if not empty
		if ( is_array( $bricks_data ) && ! empty( $bricks_data ) ) {
			$bricks_meta_key = Database::get_bricks_data_key( $area );

			// STEP: Generate new & unique IDs for Bricks elements
			$new_bricks_data = Helpers::generate_new_element_ids( $bricks_data );

			// STEP: wp_slash the new Bricks data before updating the postmeta
			$new_bricks_data = wp_slash( $new_bricks_data );

			// STEP: Update the Bricks data postmeta
			update_post_meta( $new_post_id, $bricks_meta_key, $new_bricks_data );
		}

		return $new_post_id;
	}

	/**
	 * Determine if duplicate content is allowed for a post.
	 *
	 * @param int $post_id
	 * @return bool
	 *
	 * @since 1.9.8
	 * @since 1.12 Refactored for duplicate content Bricks settings.
	 */
	public static function use_duplicate_content( $post_id ) {
		// Get setting
		$setting = Database::get_setting( 'duplicateContent', 'enable' );

		// Possible settings: enable, disable_all, disable_wp
		switch ( $setting ) {
			case 'disable_all':
				$use = false;
				break;
			case 'disable_wp':
				$use = Helpers::get_editor_mode( $post_id ) === 'bricks';
				break;
			default:
				// Shouldn't restricted to Bricks data posts only then this will force user use another plugin to clone non-Bricks posts
				$use = true;
				break;
		}

		// Ensure user can edit the post
		$use = $use && current_user_can( 'edit_post', $post_id );

		// @see https://academy.bricksbuilder.io/article/filter-bricks-use_duplicate_content/ (@since 1.12)
		$use = apply_filters( 'bricks/use_duplicate_content', $use, $post_id, $setting );

		return $use;
	}

	/**
	 * Delete template screenshots
	 *
	 * @since 1.10
	 */
	public function delete_template_screenshots() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		// Get WordPress upload directory
		$wp_upload_dir = wp_upload_dir();
		$custom_dir    = $wp_upload_dir['basedir'] . '/' . BRICKS_TEMPLATE_SCREENSHOTS_DIR . '/';

		if ( is_dir( $custom_dir ) ) {
			// Find all files (including hidden files)
			$existing_files = array_merge( glob( $custom_dir . '*' ), glob( $custom_dir . '.*' ) );

			// Delete all files
			foreach ( $existing_files as $file ) {
				if ( file_exists( $file ) ) {
					@unlink( $file );
				}
			}

			// Delete directory
			$directory_deleted = rmdir( $custom_dir );
		}

		wp_send_json_success( [ 'message' => esc_html__( 'Template screenshots deleted', 'bricks' ) ] );
	}

	/**
	 * Manually trigger index job
	 *
	 * @since 1.10
	 */
	public function run_index_job() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		if ( ! Helpers::enabled_query_filters() ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Query filters are disabled', 'bricks' ) ] );
		}

		// Send background job to reindex query filters
		$indexer = Query_Filters_Indexer::get_instance();
		if ( ! $indexer->indexer_is_running() ) {
			// Trigger background job
			$indexer::trigger_background_job();
		}

		$response = [
			'progress' => $indexer->get_overall_progress(),
			'pending'  => count( $indexer->get_jobs() ),
		];

		wp_send_json_success( $response );
	}

	/**
	 * Remove all index jobs
	 *
	 * @since 1.11
	 */
	public function remove_all_index_jobs() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		if ( ! Helpers::enabled_query_filters() ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Query filters are disabled', 'bricks' ) ] );
		}

		$indexer = Query_Filters_Indexer::get_instance();

		$indexer->remove_all_jobs();

		wp_send_json_success( [ 'message' => esc_html__( 'All index jobs removed', 'bricks' ) ] );
	}

	/**
	 * System information wp_remote_post test
	 *
	 * To debug query filter index issue.
	 *
	 * @since 1.11
	 */
	public function system_info_wp_remote_post_test() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		// Return ok
		wp_send_json_success( [ 'message' => 'ok' ] );

		wp_die();
	}

	/**
	 * Fix filter element database
	 *
	 * @since 1.12.2
	 */
	public function bricks_fix_filter_element_db() {
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		if ( ! Helpers::enabled_query_filters() ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Query filters are disabled', 'bricks' ) ] );
		}

		$fixed = Query_Filters::get_instance()->fix_filter_element_db();

		if ( $fixed ) {
			wp_send_json_success( [ 'message' => esc_html__( 'Corrupted filter element database has been fixed', 'bricks' ) ] );
		}

		wp_send_json_error( [ 'message' => esc_html__( 'Unable to fix corrupted filter element database', 'bricks' ) ] );
	}

	/**
	 * Return elements that need code review
	 *
	 * @since 2.0
	 */
	public static function code_review_items( $bricks_data, $code_review, &$code_signature_results ) {
		$code_review_elements = [];

		if ( is_array( $bricks_data ) && ! empty( $bricks_data ) ) {
			foreach ( $bricks_data as $element ) {
				$element_settings = $element['settings'] ?? [];
				$element_name     = $element['name'] ?? '';

				$global_settings = Helpers::get_global_element( $element, 'settings' );

				if ( $global_settings ) {
					$element['settings'] = $element_settings = $global_settings;
				}

				// Check: Component root (@since 2.0)
				$component_instance_settings = ! empty( $element['cid'] ) ? Helpers::get_component_instance( $element, 'settings' ) : false;

				if ( $component_instance_settings ) {
					$element['settings']     = $element_settings = $component_instance_settings;
					$element['is_component'] = true;
				}

				if ( empty( $element_settings ) ) {
					continue;
				}

				// STEP: Code element
				if ( $element_name === 'code' && array_key_exists( 'code', $element_settings ) && in_array( $code_review, [ 'code', 'all' ] ) ) {
					$element['execute_code'] = isset( $element_settings['executeCode'] );

					// Code signature
					if ( $element['execute_code'] ) {
						$element['signature'] = [
							'label' => esc_html__( 'No signature', 'bricks' ),
							'type'  => 'missing',
						];

						if ( ! empty( $element_settings['signature'] ) ) {
							// Valid signature
							$element_settings_code = isset( $element_settings['code'] ) ? $element_settings['code'] : '';
							if ( Helpers::verify_code_signature( $element_settings['signature'], $element_settings_code ) ) {
								$element['signature']['label'] = esc_html__( 'Valid signature', 'bricks' );
								$element['signature']['type']  = 'valid';
							}

							// Invalid signature
							else {
								$element['signature']['label'] = esc_html__( 'Invalid signature', 'bricks' );
								$element['signature']['type']  = 'invalid';
							}
						}

						// User who signed the code + timestamp
						$element['signature']['meta'] = '';
						if ( isset( $element['settings']['user_id'] ) ) {
							$user = get_userdata( $element['settings']['user_id'] );

							if ( $user ) {
								$element['signature']['meta'] = $user->display_name ?? $user->user_login;
							}
						}

						if ( isset( $element['settings']['time'] ) ) {
							// Timestamp to datetime
							$element['signature']['meta'] .= ' (' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $element['settings']['time'] ) . ')';
						}
					}

					$element['type']        = 'code';
					$code_review_elements[] = $element;
				}

				// STEP: Query editor element
				elseif ( isset( $element_settings['query']['queryEditor'] ) && in_array( $code_review, [ 'queryeditor', 'all' ] ) ) {
					$element['execute_code'] = isset( $element_settings['query']['useQueryEditor'] );

					// Code signature
					$element['signature'] = [
						'label' => esc_html__( 'No signature', 'bricks' ),
						'type'  => 'missing',
					];

					if ( ! empty( $element_settings['query']['signature'] ) ) {
						// Valid signature
						if ( Helpers::verify_code_signature( $element_settings['query']['signature'], $element_settings['query']['queryEditor'] ) ) {
							$element['signature']['label'] = esc_html__( 'Valid signature', 'bricks' );
							$element['signature']['type']  = 'valid';
						}

						// Invalid signature
						else {
							$element['signature']['label'] = esc_html__( 'Invalid signature', 'bricks' );
							$element['signature']['type']  = 'invalid';
						}
					}

					// User who signed the code + timestamp
					$element['signature']['meta'] = '';
					if ( isset( $element['settings']['query']['user_id'] ) ) {
						$user = get_userdata( $element['settings']['query']['user_id'] );

						if ( $user ) {
							$element['signature']['meta'] = $user->display_name ?? $user->user_login;
						}
					}

					if ( isset( $element['settings']['query']['time'] ) ) {
						// Timestamp to datetime
						$element['signature']['meta'] .= ' (' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $element['settings']['query']['time'] ) . ')';
					}

					$element['type']        = 'queryeditor';
					$code_review_elements[] = $element;
				}

				// STEP: SVG element
				elseif ( $element_name === 'svg' && array_key_exists( 'code', $element_settings ) && in_array( $code_review, [ 'svg', 'all' ] ) ) {
					$element['execute_code'] = true;

					// Code signature
					$element['signature'] = [
						'label' => esc_html__( 'No signature', 'bricks' ),
						'type'  => 'missing',
					];

					if ( ! empty( $element_settings['signature'] ) ) {
						// Valid signature
						if ( Helpers::verify_code_signature( $element_settings['signature'], $element_settings['code'] ) ) {
							$element['signature']['label'] = esc_html__( 'Valid signature', 'bricks' );
							$element['signature']['type']  = 'valid';
						}

						// Invalid signature
						else {
							$element['signature']['label'] = esc_html__( 'Invalid signature', 'bricks' );
							$element['signature']['type']  = 'invalid';
						}
					}

					// User who signed the code + timestamp
					$element['signature']['meta'] = '';
					if ( isset( $element['settings']['user_id'] ) ) {
						$user = get_userdata( $element['settings']['user_id'] );

						if ( $user ) {
							$element['signature']['meta'] = $user->display_name ?? $user->user_login;
						}
					}

					if ( isset( $element['settings']['time'] ) ) {
						// Timestamp to datetime
						$element['signature']['meta'] .= ' (' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $element['settings']['time'] ) . ')';
					}

					$element['type']        = 'code';
					$code_review_elements[] = $element;
				}

				// Add element code 'signature' results to $code_signature_results
				$signature_type = $element['signature']['type'] ?? '';
				if ( $signature_type ) {
					$code_signature_results[ $signature_type ] += 1;
					$code_signature_results['total']           += 1;
				}

				// STEP: Echo tag instances
				$settings_string = wp_json_encode( $element_settings );
				if ( strpos( $settings_string, '{echo:' ) !== false && in_array( $code_review, [ 'echo', 'all' ] ) ) {
					$element['execute_code'] = true;
					$element['type']         = 'echo';
					$code_review_elements[]  = $element;
				}
			}
		}

		return $code_review_elements;
	}

	/**
	 * Add custom column to the WordPress users list (used for account activation status)
	 *
	 * @since 2.1
	 */
	public function add_user_activation_status_column( $columns ) {
		$columns['activation_status'] = esc_html__( 'Activation status', 'bricks' ) . ' (Bricks)';

		return $columns;
	}

	/**
	 * Add content to the custom column in the WordPress users list (used for account activation status)
	 *
	 * @since 2.1
	 */
	public function user_activation_status_column_content( $content, $column_name, $user_id ) {
		if ( $column_name !== 'activation_status' ) {
			return $content;
		}

		$actions = [
			'mark_active'   => sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url( admin_url( 'admin.php?action=bricks_user_activation&type=activate&user_id=' . $user_id . '&status=active' ), 'bricks-nonce-admin' ),
				esc_html__( 'Mark as active', 'bricks' )
			),
			'mark_inactive' => sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url( admin_url( 'admin.php?action=bricks_user_activation&type=deactivate&user_id=' . $user_id . '&status=pending' ), 'bricks-nonce-admin' ),
				esc_html__( 'Mark as inactive', 'bricks' )
			),
			'resend'        => sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url( admin_url( 'admin.php?action=bricks_user_activation&type=resend_activation&user_id=' . $user_id ), 'bricks-nonce-admin' ),
				esc_html__( 'Resend activation email', 'bricks' )
			),
		];

		// User activation status: active, pending
		$activation_status = get_user_meta( $user_id, 'bricks_user_activation_status', true );

		// Activation status is empty: Old user account, active before activation feature was added
		// if ( empty( $activation_status ) ) {
		// $content  = '<span style="color: gray;">' . esc_html__( 'Old user', 'bricks' ) . '</span>';
		// $content .= '<div class="row-actions">';
		// $content .= $actions['mark_active'] . ' | ' . $actions['resend'];
		// $content .= '</div>';
		// }

		// Active user: No activation (user registered before user activation was required OR status is 'active'
		if ( ! $activation_status || $activation_status === 'active' ) {
			$content  = '<span style="color: green;">' . esc_html__( 'Active', 'bricks' ) . '</span>';
			$content .= '<div class="row-actions">';
			$content .= $actions['mark_inactive'];
			$content .= '</div>';
		}

		// Inactive user: Activation status is 'pending'
		else {
			$content  = '<span style="color: red;">' . esc_html__( 'Inactive', 'bricks' ) . '</span>';
			$content .= '<div class="row-actions">';
			$content .= $actions['mark_active'] . ' | ' . $actions['resend'];
			$content .= '</div>';
		}

		return $content;
	}

	/**
	 * User activation / deactivation / resend activation email actions
	 *
	 * @since 2.1
	 */
	public function bricks_user_activation_action() {
		// Check nonce
		Ajax::verify_nonce( 'bricks-nonce-admin' );

		// Get post
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$type    = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : false;

		if ( ! $user_id || ! $type ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid request', 'bricks' ) ] );
		}

		// If type is not one of (activate, deactivate, resend_activation), return error
		if ( ! in_array( $type, [ 'activate', 'deactivate', 'resend_activation' ] ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid request - wrong action type: ' . $type, 'bricks' ) ] );
		}

		switch ( $type ) {
			case 'activate':
				delete_user_meta( $user_id, 'bricks_user_activation_key' );
				update_user_meta( $user_id, 'bricks_user_activation_status', 'active' );
				break;

			case 'deactivate':
				delete_user_meta( $user_id, 'bricks_user_activation_key' );
				update_user_meta( $user_id, 'bricks_user_activation_status', 'pending' );
				break;

			case 'resend_activation':
				Helpers::set_activation_meta( $user_id );
				\Bricks\Helpers::send_user_activation_email( $user_id, 'resend-activation' );
				break;
		}

		wp_safe_redirect( wp_get_referer() );
	}
}
