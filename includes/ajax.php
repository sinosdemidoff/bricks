<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Ajax {
	public function __construct() {
		// In builder
		add_action( 'wp_ajax_bricks_command_palette_get_posts', [ $this, 'command_palette_get_posts' ] );
		add_action( 'wp_ajax_bricks_generate_code_signature', [ $this, 'generate_code_signature' ] );

		add_filter( 'sanitize_post_meta_' . BRICKS_DB_PAGE_CONTENT, [ $this, 'sanitize_bricks_postmeta' ], 10, 3 );
		add_filter( 'sanitize_post_meta_' . BRICKS_DB_PAGE_HEADER, [ $this, 'sanitize_bricks_postmeta' ], 10, 3 );
		add_filter( 'sanitize_post_meta_' . BRICKS_DB_PAGE_FOOTER, [ $this, 'sanitize_bricks_postmeta' ], 10, 3 );
		add_filter( 'sanitize_post_meta_' . BRICKS_DB_PAGE_SETTINGS, [ $this, 'sanitize_bricks_postmeta_page_settings' ], 10, 3 );
		add_action( 'update_post_metadata', [ $this, 'update_bricks_postmeta' ], 10, 5 );

		add_action( 'wp_ajax_bricks_import_images', [ $this, 'import_images' ] );
		add_action( 'wp_ajax_bricks_download_image', [ $this, 'download_image' ] );
		add_action( 'wp_ajax_bricks_get_image_metadata', [ $this, 'get_image_metadata' ] );
		add_action( 'wp_ajax_bricks_get_image_from_custom_field', [ $this, 'get_image_from_custom_field' ] );

		add_action( 'wp_ajax_bricks_get_dynamic_data_preview_content', [ $this, 'get_dynamic_data_preview_content' ] );

		add_action( 'wp_ajax_bricks_get_posts', [ $this, 'get_posts' ] );
		add_action( 'wp_ajax_bricks_get_terms_options', [ $this, 'get_terms_options' ] );
		add_action( 'wp_ajax_bricks_get_users', [ $this, 'get_users' ] );

		add_action( 'wp_ajax_bricks_render_data', [ $this, 'render_data' ] );

		add_action( 'wp_ajax_bricks_publish_post', [ $this, 'publish_post' ] );
		add_action( 'wp_ajax_bricks_save_post', [ $this, 'save_post' ] );
		add_action( 'wp_ajax_bricks_save_template_screenshot', [ $this, 'save_template_screenshot' ] );
		add_action( 'wp_ajax_bricks_create_autosave', [ $this, 'create_autosave' ] );
		add_action( 'wp_ajax_bricks_get_builder_url', [ $this, 'get_builder_url' ] );

		add_action( 'wp_ajax_bricks_save_color_palette', [ $this, 'save_color_palette' ] );
		add_action( 'wp_ajax_bricks_save_panel_width', [ $this, 'save_panel_width' ] );
		add_action( 'wp_ajax_bricks_save_builder_scale_off', [ $this, 'save_builder_scale_off' ] );
		add_action( 'wp_ajax_bricks_save_builder_width_locked', [ $this, 'save_builder_width_locked' ] );

		add_action( 'wp_ajax_bricks_render_element', [ $this, 'render_element' ] );

		add_action( 'wp_ajax_bricks_get_pages', [ $this, 'get_pages' ] );
		add_action( 'wp_ajax_bricks_create_new_page', [ $this, 'create_new_page' ] );
		add_action( 'wp_ajax_bricks_duplicate_post_page', [ $this, 'duplicate_content' ] );

		add_action( 'wp_ajax_bricks_get_my_templates_data', [ $this, 'get_my_templates_data' ] );

		add_action( 'wp_ajax_bricks_get_remote_templates_data', [ $this, 'get_remote_templates_data' ] );

		add_action( 'wp_ajax_bricks_get_current_user_id', [ $this, 'get_current_user_id' ] );

		add_action( 'wp_ajax_bricks_query_loop_delete_random_seed_transient', [ $this, 'query_loop_delete_random_seed_transient' ] );

		// In Gutenberg
		add_action( 'wp_ajax_bricks_get_html_from_content', [ $this, 'get_html_from_content' ] );

		// Get template elements by template ID
		add_action( 'wp_ajax_bricks_get_template_elements_by_id', [ $this, 'get_template_elements_by_id' ] );

		// Get custom shape divider SVG from URL (@since 1.8.6)
		add_action( 'wp_ajax_bricks_get_custom_shape_divider', [ $this, 'get_custom_shape_divider' ] );

		// Frontend: Regenerate form nonce (@since 1.9.6)
		add_action( 'wp_ajax_bricks_regenerate_form_nonce', [ $this, 'regenerate_form_nonce' ] );
		add_action( 'wp_ajax_nopriv_bricks_regenerate_form_nonce', [ $this, 'regenerate_form_nonce' ] );

		// Frontend: Regenerate query nonce (@since 1.11)
		add_action( 'wp_ajax_bricks_regenerate_query_nonce', [ $this, 'regenerate_query_nonce' ] );
		add_action( 'wp_ajax_nopriv_bricks_regenerate_query_nonce', [ $this, 'regenerate_query_nonce' ] );

		// Restore global class (@since 1.11)
		add_action( 'wp_ajax_bricks_restore_global_class', [ $this, 'restore_global_class' ] );

		// Add new action for deleting global classes permanently (@since 1.11)
		add_action( 'wp_ajax_bricks_delete_global_classes_permanently', [ $this, 'delete_global_classes_permanently' ] );

		// Clean up orphaned elements across site (@since 2.0)
		add_action( 'wp_ajax_bricks_cleanup_orphaned_elements', [ $this, 'cleanup_orphaned_elements' ] );

		// Scan for orphaned elements across site (@since 2.0)
		add_action( 'wp_ajax_bricks_scan_orphaned_elements', [ $this, 'scan_orphaned_elements' ] );

		// Query API (@since 2.1)
		add_action( 'wp_ajax_bricks_query_api', [ $this, 'query_api' ] );
	}

	/**
	 * Command palette: Get posts for the PopupCommandPalette.vue
	 *
	 * @since 2.0
	 */
	public function command_palette_get_posts() {
		self::verify_request( 'bricks-nonce-builder' );

		// Get all posts with direct SQL query
		global $wpdb;

		// Get selected post type
		$post_type = ! empty( $_POST['postType'] ) ? sanitize_text_field( $_POST['postType'] ) : 'any';

		// Get all public post types if no post type is selected
		if ( $post_type === 'any' ) {
			$post_types = array_keys( get_post_types( [ 'public' => true ] ) );
			$post_types = array_map( 'sanitize_text_field', $post_types );
		} else {
			$post_types = [ $post_type ];
		}

		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// Get all post IDs in a single query
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type IN ($placeholders)
				AND post_status IN ('publish', 'draft', 'pending', 'private')
				ORDER BY post_modified DESC",
				$post_types
			)
		);

		// Process all posts
		$posts          = [];
		$post_edit_link = admin_url() . 'post.php?post=POST_ID&action=edit';

		foreach ( $post_ids as $post_id ) {
			$posts[] = [
				'id'        => $post_id,
				'title'     => get_the_title( $post_id ),
				'postType'  => get_post_type( $post_id ),
				'slug'      => get_post_field( 'post_name', $post_id ),
				'permalink' => get_permalink( $post_id ),
				'status'    => get_post_status( $post_id ),
				'editUrl'   => Helpers::get_builder_edit_link( $post_id ),
				'editUrlWp' => str_replace( 'POST_ID', $post_id, $post_edit_link ),
			];
		}

		wp_send_json_success( $posts );
	}

	/**
	 * Check if current endpoint is Bricks AJAX endpoint
	 *
	 * @param string $action E.g. 'get_template_elements_by_id' or 'form_submit'.
	 * @param string $action E.g. 'get_template_elements_by_id' or 'form_submit'.
	 *
	 * @since 1.11
	 *
	 * @return boolean
	 * @return boolean
	 */
	public static function is_current_endpoint( $action ) {
		if ( ! bricks_is_ajax_call() || ! isset( $_POST['action'] ) ) {
			return false;
		}

		return sanitize_text_field( $_POST['action'] ) === 'bricks_' . $action;
	}

	/**
	 * Builder: Generate code signature
	 *
	 * @since 1.9.7
	 */
	public function generate_code_signature() {
		self::verify_request( 'bricks-nonce-builder' );

		// Check if code signatures are locked (@since 1.11.1)
		if ( defined( 'BRICKS_LOCK_CODE_SIGNATURES' ) && BRICKS_LOCK_CODE_SIGNATURES ) {
			wp_send_json_error( esc_html__( 'Code signatures are locked.', 'bricks' ) );
		}

		if (
			! empty( $_POST['element'] ) &&
			Helpers::code_execution_enabled() &&
			Capabilities::current_user_can_execute_code()
		) {
			$element  = self::decode( $_POST['element'], false );
			$elements = Admin::process_elements_for_signature( [ $element ] );

			wp_send_json_success( [ 'element' => $elements[0] ] );
		}

		wp_send_json_error( esc_html__( 'Not allowed', 'bricks' ) );
	}

	/**
	 * Decode stringified JSON data
	 *
	 * @since 1.0
	 */
	public static function decode( $data, $run_wp_slash = true ) {
		$data = stripslashes( $data );
		$data = json_decode( $data, true );
		$data = $run_wp_slash ? wp_slash( $data ) : $data; // Make sure we keep the good slashes on update_post_meta

		return $data;
	}

	/**
	 * Form element: Regenerate nonce
	 *
	 * @since 1.9.6
	 */
	public function regenerate_form_nonce() {
		echo wp_create_nonce( 'bricks-nonce-form' );
		wp_die();
	}

	/**
	 *
	 * Query Sort/Filter: Regenerate nonce
	 *
	 * @since 1.11
	 */
	public function regenerate_query_nonce() {
		wp_send_json_success(
			[
				'bricks_nonce' => wp_create_nonce( 'bricks-nonce' ),
				'rest_nonce'   => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	/**
	 * Verify nonce (AJAX call)
	 *
	 * wp-admin: 'bricks-nonce-admin'
	 * builder:  'bricks-nonce-builder'
	 * frontend: 'bricks-nonce' (= default)
	 *
	 * @return void
	 */
	public static function verify_nonce( $nonce = 'bricks-nonce' ) {
		if ( ! check_ajax_referer( $nonce, 'nonce', false ) ) {
			wp_send_json_error( "verify_nonce: \"$nonce\" is invalid." );
		}
	}

	/**
	 * Verify request: nonce and user access
	 *
	 * Check for builder in order to not trigger on wp_auth_check
	 *
	 * @since 1.0
	 */
	public static function verify_request( $nonce = 'bricks-nonce' ) {
		self::verify_nonce( $nonce );

		// Verify user access (get_the_ID() returns 0 in AJAX call)
		$post_id = isset( $_POST['postId'] ) ? intval( $_POST['postId'] ) : get_the_ID();

		if ( ! Capabilities::current_user_can_use_builder( $post_id ) ) {
			wp_send_json_error( 'verify_request: User can not use builder (' . get_current_user_id() . ')' );
		}
	}

	/**
	 * Save color palette
	 *
	 * @since 1.0
	 */
	public function save_color_palette() {
		self::verify_request( 'bricks-nonce-builder' );

		if ( isset( $_POST['colorPalette'] ) ) {
			$color_palette_updated = update_option( BRICKS_DB_COLOR_PALETTE, stripslashes_deep( $_POST['colorPalette'] ) );
			wp_send_json_success( $color_palette_updated );
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'New color could not be saved.', 'bricks' ) ] );
		}
	}

	/**
	 * Save panel width
	 *
	 * @since 1.0
	 */
	public function save_panel_width() {
		self::verify_request( 'bricks-nonce-builder' );

		// STEP: Update panel width
		$panel_width = isset( $_POST['panelWidth'] ) ? intval( $_POST['panelWidth'] ) : 0;

		if ( $panel_width >= 100 ) {
			$panel_width_updated = update_option( BRICKS_DB_PANEL_WIDTH, $panel_width );
			wp_send_json_success(
				[
					'panel_width_updated' => $panel_width_updated,
					'panel_width'         => $panel_width,
				]
			);
		}

		// STEP: Update structure width
		$structure_width = isset( $_POST['structureWidth'] ) ? intval( $_POST['structureWidth'] ) : 0;

		if ( $structure_width >= 100 ) {
			$structure_width_updated = update_option( BRICKS_DB_STRUCTURE_WIDTH, $structure_width );
			wp_send_json_success(
				[
					'structure_width_updated' => $structure_width_updated,
					'structure_width'         => $structure_width,
				]
			);
		}

		// Return error
		wp_send_json_error(
			[
				'message'     => esc_html__( 'Panel width could not be saved.', 'bricks' ),
				'panel_width' => $panel_width || $structure_width,
			]
		);
	}

	/**
	 * Save builder state 'off' (enabled by default)
	 *
	 * @since 1.3.2
	 */
	public function save_builder_scale_off() {
		self::verify_request( 'bricks-nonce-builder' );

		$scale_off = isset( $_POST['off'] ) ? $_POST['off'] == 'true' : false;
		$user_id   = get_current_user_id();

		if ( $scale_off ) {
			update_user_meta( $user_id, BRICKS_DB_BUILDER_SCALE_OFF, true );
		} else {
			delete_user_meta( $user_id, BRICKS_DB_BUILDER_SCALE_OFF );
		}

		wp_send_json_success(
			[
				'scale_off' => $scale_off,
				'user_id'   => $user_id,
			]
		);
	}

	/**
	 * Save builder width locked state (disabled by default)
	 *
	 * Only apply for bas breakpoint. Allows users on smaller screen not having to set a custom width on every page load.
	 *
	 * @since 1.3.2
	 */
	public function save_builder_width_locked() {
		self::verify_request( 'bricks-nonce-builder' );

		$preview_width = isset( $_POST['width'] ) ? intval( $_POST['width'] ) : false;
		$user_id       = get_current_user_id();

		if ( $preview_width ) {
			update_user_meta( $user_id, BRICKS_DB_BUILDER_WIDTH_LOCKED, $preview_width );
		} else {
			delete_user_meta( $user_id, BRICKS_DB_BUILDER_WIDTH_LOCKED );
		}

		wp_send_json_success(
			[
				'preview_width' => $preview_width,
				'user_id'       => $user_id,
			]
		);
	}

	/**
	 * Get pages
	 *
	 * @since 1.0
	 */
	public function get_pages() {
		self::verify_request( 'bricks-nonce-builder' );

		$query_args = [
			'posts_per_page'   => -1,
			'post_status'      => 'any',
			'post_type'        => ! empty( $_POST['postType'] ) ? sanitize_text_field( $_POST['postType'] ) : 'page',
			'fields'           => 'ids',
			'suppress_filters' => true, // WPML (also prevents any posts_where filters from modifying the query)
			'lang'             => '', // Polylang
		];

		// NOTE: Undocumented
		$query_args = apply_filters( 'bricks/ajax/get_pages_args', $query_args );

		$page_ids = get_posts( $query_args );

		$pages = [];

		foreach ( $page_ids as $page_id ) {
			$page_title = wp_kses_post( get_the_title( $page_id ) );

			// NOTE: Undocumented
			$page_title = apply_filters( 'bricks/builder/post_title', $page_title, $page_id );

			$page_data = [
				'id'      => $page_id,
				'title'   => $page_title,
				'status'  => get_post_status( $page_id ),
				'slug'    => get_post_field( 'post_name', $page_id ),
				'editUrl' => Helpers::get_builder_edit_link( $page_id ),
			];

			if ( has_post_thumbnail( $page_id ) ) {
				$image_size         = ! empty( $_POST['imageSize'] ) ? sanitize_text_field( $_POST['imageSize'] ) : 'large';
				$page_data['image'] = get_the_post_thumbnail_url( $page_id, $image_size );
			}

			$pages[] = $page_data;
		}

		wp_send_json_success( $pages );
	}

	/**
	 * Create new page
	 *
	 * @since 1.0
	 * @since 2.0: Command palette support
	 */
	public function create_new_page() {
		self::verify_request( 'bricks-nonce-builder' );

		if ( ! current_user_can( Admin::EDITING_CAP ) ) {
			wp_send_json_error( esc_html__( 'Not allowed', 'bricks' ) );
		}

		$new_page_id = wp_insert_post(
			[
				'post_title' => ! empty( $_POST['title'] ) ? esc_html( $_POST['title'] ) : esc_html__( '(no title)', 'bricks' ),
				'post_type'  => ! empty( $_POST['postType'] ) ? esc_html( $_POST['postType'] ) : 'page',
			]
		);

		$new_post       = get_post( $new_page_id );
		$post_edit_link = admin_url() . 'post.php?post=POST_ID&action=edit';

		$new_post = [
			'id'        => $new_post->ID,
			'title'     => wp_kses_post( $new_post->post_title ),
			'slug'      => $new_post->post_name,
			'postType'  => $new_post->post_type,
			'status'    => $new_post->post_status,
			'editUrl'   => Helpers::get_builder_edit_link( $new_post->ID ),
			'editUrlWp' => str_replace( 'POST_ID', $new_post->ID, $post_edit_link ),
			'permalink' => get_permalink( $new_post->ID ),
		];

		wp_send_json_success( $new_post );
	}

	/**
	 * Duplicate page or post in the builder (Bricks or WordPress)
	 *
	 * @since 1.9.8
	 * @since 2.0: Command palette support
	 */
	public function duplicate_content() {
		self::verify_request( 'bricks-nonce-builder' );

		$post_id = ! empty( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;

		// Return: User can not edit this post
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		$new_post_id = Admin::duplicate_content( $post_id );

		if ( ! $new_post_id ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Post could not be duplicated', 'bricks' ) ] );
		}

		$new_post       = get_post( $new_post_id );
		$post_edit_link = admin_url() . 'post.php?post=POST_ID&action=edit';

		$new_post = [
			'id'        => $new_post->ID,
			'title'     => wp_kses_post( $new_post->post_title ),
			'slug'      => $new_post->post_name,
			'postType'  => $new_post->post_type,
			'status'    => $new_post->post_status,
			'editUrl'   => Helpers::get_builder_edit_link( $new_post->ID ),
			'editUrlWp' => str_replace( 'POST_ID', $new_post->ID, $post_edit_link ),
			'permalink' => get_permalink( $new_post->ID ),
		];

		wp_send_json_success( $new_post );
	}

	/**
	 * Render element HTML from settings
	 *
	 * AJAX call / REST API call: In-builder (getHTML for PHP-rendered elements)
	 *
	 * @since 1.0
	 */
	public static function render_element( $data ) {
		$is_ajax = bricks_is_ajax_call();

		if ( $is_ajax && isset( $_POST ) ) {
			$data = $_POST;
		}

		$loop_element = $data['loopElement'] ?? false;
		$element      = $data['element'] ?? false;
		$element_name = $element['name'] ?? false;
		$parent_loops = $data['parentLoops'] ?? [];

		// AJAX call
		if ( isset( $_POST['element'] ) ) {
			self::verify_request( 'bricks-nonce-builder' );

			$element = stripslashes_deep( $element );
		}

		// No additional processing needed for REST API calls or builder.php
		// REST API permissions are already checked in API->render_element_permissions_check()

		/**
		 * Builder: Init Query to get the builder preview for the first loop item (e.g.: "Product Category Image" DD)
		 *
		 * @since 1.4
		 */
		if ( ! empty( $loop_element ) ) {
			// If it's an element inside a component, should use the component data (@since 1.12)
			$cid       = $loop_element['cid'] ?? false;
			$component = $element['componentInstance'] ?? false;

			if ( $cid && isset( $component['id'] ) && $component['id'] === $cid ) {
				$loop_element = $component;
			}

			// Run parent queries (@since 1.12.2)
			if ( ! empty( $parent_loops ) ) {
				foreach ( $parent_loops as $parent_loop ) {
					// Run each parent query
					self::simulate_bricks_query( $parent_loop );
				}
			}

			// Run the current query
			self::simulate_bricks_query( $loop_element );
		}

		// Init element class (i.e. new Bricks\Element_Alert( $element ))
		$element_class_name = isset( Elements::$elements[ $element_name ]['class'] ) ? Elements::$elements[ $element_name ]['class'] : false;

		if ( class_exists( $element_class_name ) ) {
			$element['is_frontend'] = false;

			$element_instance = new $element_class_name( $element );
			$element_instance->load();

			// Init element: enqueue styles/scripts, render element
			ob_start();
			$element_instance->init();
			$response = ob_get_clean();
		}

		// Element doesn't exist
		else {
			// translators: %s: Element name
			$no_element_text = sprintf( esc_html__( 'Element "%s" doesn\'t exist.', 'bricks' ), $element_name );

			// Check: Element is disabled via element manager (@since 2.0)
			if ( isset( Elements::$manager[ $element_name ]['status'] ) && Elements::$manager[ $element_name ]['status'] === 'disabled' ) {
				$no_element_text = esc_html__( 'Element has been disabled globally.', 'bricks' ) . ' (<a href="' . admin_url( 'admin.php?page=bricks-elements' ) . '" target="_blank">Bricks > ' . esc_html__( 'Elements', 'bricks' ) . '</a>)';
			}

			$no_element_text = "$element_name: $no_element_text";

			$response = '<div class="bricks-element-placeholder no-php-class">' . $no_element_text . '</div>';
		}

		if ( $is_ajax ) {
			// Template element: Add additional builder data (CSS & list of elements to run scripts (@since 1.5))
			if ( $element_name === 'template' ) {
				$template_id = ! empty( $element['settings']['template'] ) ? $element ['settings']['template'] : false;

				if ( $template_id ) {
					$additional_data = Element_Template::get_builder_call_additional_data( $template_id );

					$response = array_merge( [ 'html' => $response ], $additional_data );
				}
			}

			// Subsequent element render via AJAX call
			wp_send_json_success( $response );
		}

		// Initial element render via PHP or REST API
		else {
			return $response;
		}
	}

	/**
	 * Generate the HTML based on the builder content data (post Id or content)
	 *
	 * Used to feed Rank Math SEO & Yoast content analysis (@since 1.11)
	 *
	 * NOTE: This method doesn't generate any styles!
	 */
	public function get_html_from_content() {
		if ( bricks_is_builder() ) {
			$nonce = 'bricks-nonce-builder';
		} elseif ( is_admin() ) {
			$nonce = 'bricks-nonce-admin';
		} else {
			$nonce = 'bricks-nonce';
		}

		self::verify_request( $nonce );

		$post_id = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : false;

		// Set active templates for the current post
		Database::set_active_templates( $post_id );

		// Get the content data
		$data = Helpers::get_bricks_data( $post_id, 'content' );

		$html = ! empty( $data ) ? Frontend::render_data( $data ) : '';

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Get template elements by template ID
	 *
	 * To generate global classes CSS in builder.
	 *
	 * @since 1.8.2
	 */
	public function get_template_elements_by_id() {
		self::verify_request( 'bricks-nonce-builder' );

		$template_ids            = ! empty( $_POST['templateIds'] ) ? self::decode( $_POST['templateIds'] ) : [];
		$template_elements_by_id = [];

		if ( is_array( $template_ids ) ) {
			foreach ( $template_ids as $template_id ) {
				$template_elements_by_id[ $template_id ] = get_post_meta( $template_id, BRICKS_DB_PAGE_CONTENT, true );
			}
		}

		wp_send_json_success( $template_elements_by_id );
	}

	/**
	 * Query control: Get posts
	 *
	 * @since 1.0
	 */
	public function get_posts() {
		if ( ! check_ajax_referer( 'bricks-nonce-builder', 'nonce', false ) ) {
			if ( ! check_ajax_referer( 'bricks-nonce-admin', 'nonce', false ) ) {
				wp_send_json_error( 'Invalid nonce' );
			}
		}

		$post_type = 'any';

		// Get specific post type
		if ( ! empty( $_GET['postType'] ) ) {
			$post_type = array_map( 'sanitize_text_field', (array) $_GET['postType'] );
		}

		// Get all public post types
		else {
			$post_type = get_post_types( [ 'public' => true ] );
			$post_type = array_keys( $post_type );
		}

		// Exclude specific post types if requested
		if ( ! empty( $_GET['excludePostTypes'] ) ) {
			$exclude_post_types = array_map( 'sanitize_text_field', (array) $_GET['excludePostTypes'] );

			if ( is_array( $post_type ) ) {
				$post_type = array_diff( $post_type, $exclude_post_types );
			}
		}

		// Set query args
		$query_args = [ 'post_type' => $post_type ];

		// Necessary to retrieve more than 2 posts initially
		if ( $post_type !== 'any' ) {
			$query_args['orderby'] = 'date';
		}

		if ( ! empty( $_GET['search'] ) ) {
			$query_args['s']       = stripslashes_deep( sanitize_text_field( $_GET['search'] ) );
			$query_args['orderby'] = 'relevance'; // (#86c0zr03p)
		}

		// @since 2.0
		if ( ! empty( $_GET['postStatus'] ) ) {
			$query_args['post_status'] = sanitize_text_field( $_GET['postStatus'] );
		}

		$posts = Helpers::get_posts_by_post_id( $query_args );

		foreach ( $posts as $post_id => $post_title ) {
			// NOTE: Undocumented
			$posts[ $post_id ] = apply_filters( 'bricks/builder/post_title', $post_title, $post_id );
		}

		// If AJAX request contains "include" parameter, make sure some post_ids are included in the response
		if ( ! empty( $_GET['include'] ) ) {
			$include_post_ids = (array) $_GET['include'];
			$include_post_ids = array_map( 'intval', $include_post_ids );

			foreach ( $include_post_ids as $post_id ) {
				if ( ! array_key_exists( $post_id, $posts ) ) {
					$posts[ $post_id ] = get_the_title( $post_id );
				}
			}
		}

		wp_send_json_success( $posts );
	}

	/**
	 * Get users
	 *
	 * @since 1.2.2
	 *
	 * @return void
	 */
	public function get_users() {
		self::verify_request( 'bricks-nonce-builder' );

		$args = [
			'count_total' => false,
			'number'      => 50,
		];

		$search_term = ! empty( $_GET['search'] ) ? stripslashes_deep( sanitize_text_field( $_GET['search'] ) ) : '';
		if ( $search_term ) {
			$args['search'] = $search_term;
		}

		// Query users
		$users = Helpers::get_users_options( $args, true );

		if ( ! empty( $_GET['include'] ) ) {
			$include_user_ids = (array) $_GET['include'];
			$include_user_ids = array_map( 'intval', $include_user_ids );

			foreach ( $include_user_ids as $user_id ) {
				if ( ! array_key_exists( $user_id, $users ) ) {
					$user = get_userdata( $user_id );
					if ( $user ) {
						$users[ $user_id ] = $user->display_name;
					}
				}
			}
		}

		wp_send_json_success( $users );
	}

	/**
	 * Get terms
	 *
	 * @since 1.0
	 */
	public function get_terms_options() {
		if ( ! check_ajax_referer( 'bricks-nonce-builder', 'nonce', false ) ) {
			if ( ! check_ajax_referer( 'bricks-nonce-admin', 'nonce', false ) ) {
				wp_send_json_error( 'Invalid nonce' );
			}
		}

		$post_types  = ! empty( $_GET['postTypes'] ) ? array_map( 'sanitize_text_field', $_GET['postTypes'] ) : null;
		$taxonomy    = ! empty( $_GET['taxonomy'] ) ? array_map( 'sanitize_text_field', $_GET['taxonomy'] ) : null;
		$search      = ! empty( $_GET['search'] ) ? stripslashes_deep( sanitize_text_field( $_GET['search'] ) ) : ''; // Improve performance (@since 1.12)
		$include_all = ! empty( $_GET['includeAll'] ) ? true : null;
		$terms       = [];

		if ( ! empty( $post_types ) ) {
			foreach ( (array) $post_types as $post_type ) {
				$type_terms = Helpers::get_terms_options( $taxonomy, $post_type, $include_all, $search );

				if ( ! empty( $type_terms ) ) {
					$terms = array_merge( $terms, $type_terms );
				}
			}
		} elseif ( ! empty( $taxonomy ) ) {
			$terms = Helpers::get_terms_options( $taxonomy, null, $include_all, $search );
		}

		// If AJAX request contains "include" parameter, make sure some term_ids are included in the response (@since 1.12)
		if ( ! empty( $_GET['include'] ) ) {
			$include_terms = (array) $_GET['include'];

			foreach ( $include_terms as $term_string ) {
				if ( ! array_key_exists( $term_string, $terms ) ) {
					$term_parts = explode( '::', $term_string );
					$taxonomy   = sanitize_key( $term_parts[0] );
					$term_id    = sanitize_key( $term_parts[1] );

					if ( $term_id !== 0 && taxonomy_exists( $taxonomy ) ) {
						$term_name      = '';
						$taxonomy_label = Helpers::generate_taxonomy_label( $taxonomy );

						if ( $term_id === 'all' ) {
							$term_name = esc_html__( 'All terms', 'bricks' );
						} else {
							$term = get_term( $term_id, $taxonomy );
							if ( $term && ! is_wp_error( $term ) ) {
								$term_name = $term->name;
							}
						}

						$terms[ $term_string ] = "{$term_name} ({$taxonomy_label})";
					}
				}
			}
		}

		// Apply filter to each term name (@since 1.11)
		foreach ( $terms as $key => $value ) {
			$term_parts = explode( '::', $key );

			if ( count( $term_parts ) === 2 ) {
				$taxonomy = sanitize_key( $term_parts[0] );
				$term_id  = sanitize_key( $term_parts[1] );

				if ( $term_id !== 0 && $term_id !== 'all' && taxonomy_exists( $taxonomy ) ) {
					// NOTE: Undocumented (@since 1.11)
					$filtered_name = apply_filters( 'bricks/builder/term_name', $value, $term_id, $taxonomy );
					$terms[ $key ] = sanitize_text_field( $filtered_name );
				}
			}
		}

		wp_send_json_success( $terms );
	}

	/**
	 * Render Bricks data for static header/content/footer and query loop preview HTML in builder
	 *
	 * @since 1.0
	 */
	public static function render_data() {
		self::verify_request( 'bricks-nonce-builder' );

		if ( empty( $_POST['elements'] ) ) {
			return;
		}

		$area     = ! empty( $_POST['area'] ) ? sanitize_text_field( $_POST['area'] ) : 'content';
		$post_id  = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
		$elements = self::decode( $_POST['elements'], false );
		$elements = array_map( 'Bricks\Helpers::set_is_frontend_to_false', $elements );

		// Use global classes data from builder not from database
		if ( ! empty( $_POST['globalClasses'] ) ) {
			Database::$global_data['globalClasses'] = self::decode( $_POST['globalClasses'], false );
		}

		// Use global queries data from builder not from database (@since 2.1)
		if ( ! empty( $_POST['globalQueries'] ) ) {
			Database::$global_data['globalQueries'] = self::decode( $_POST['globalQueries'], false );
		}

		/**
		 * Use real-time, possibly unsaved components data from builder to generate in-builder styles correctly
		 *
		 * Needed as components data in builder is unsaved and not yet in database.
		 *
		 * @since 1.12
		 */
		$components = isset( $_POST['components'] ) ? self::decode( $_POST['components'], false ) : false;
		if ( $components ) {
			Database::$global_data['components'] = $components;
		}

		// Set Theme Styles (for correct preview of query loop nodes)
		Theme_Styles::load_set_styles( $post_id );

		// Use global elements data from builder (@since 1.7.1)
		$global_elements = ! empty( $_POST['globalElements'] ) ? $_POST['globalElements'] : [];

		if ( is_array( $global_elements ) && count( $global_elements ) ) {
			foreach ( $elements as $index => $element ) {
				$global_element_id = $element['global'] ?? false;

				if ( $global_element_id ) {
					foreach ( $global_elements as $global_element ) {
						if ( ! empty( $global_element['global'] ) && $global_element['global'] == $global_element_id ) {
							$elements[ $index ]['settings'] = $global_element['settings'];

							// To skip getting element setting from db in Frontend::render_data() > render_element() later on
							$elements[ $index ]['global_settings_checked'] = true;
						}
					}
				}
			}
		}

		// Generate query loop styles for dynamic data
		$loop_name = "loop_{$post_id}";

		// Use loop element ID as loop_name if possible
		if ( isset( $elements[0]['id'] ) ) {
			$loop_name = "loop_{$elements[0]['id']}";
		}

		// Run parent queries (@since 1.12.2)
		$parent_loops = ! empty( $_POST['parentLoops'] ) ? self::decode( $_POST['parentLoops'], false ) : [];
		if ( ! empty( $parent_loops ) ) {
			foreach ( $parent_loops as $parent_loop ) {
				// Run each parent query
				self::simulate_bricks_query( $parent_loop );
			}
		}

		$filtered_elements = $elements;

		// If post-content with source="bricks" is in elements, remove it
		if ( ! Helpers::is_bricks_template( $post_id ) ) {
			$filtered_elements = array_filter(
				$elements,
				function( $element ) {
					return $element['name'] !== 'post-content' || $element['settings']['dataSource'] !== 'bricks';
				}
			);
		}

		// Generate Assets before Frontend render to add 'data-query-loop-index' attribute successfully in builder
		Assets::generate_css_from_elements( $filtered_elements, $loop_name );

		$inline_css = Assets::$inline_css[ $loop_name ] ?? '';

		// Collect HTML strings for all elements start (@since 2.0)
		add_filter( 'bricks/frontend/render_element', [ 'Bricks\Builder', 'collect_elements_html' ], 10, 2 );
		add_filter( 'bricks/frontend/render_loop', [ 'Bricks\Builder', 'collect_looping_html' ], 10, 3 );

		$html = Frontend::render_data( $elements, $area );

		// Collect HTML strings for all elements end (@since 2.0)
		remove_filter( 'bricks/frontend/render_loop', [ 'Bricks\Builder', 'collect_looping_html' ], 10, 3 );
		remove_filter( 'bricks/frontend/render_element', [ 'Bricks\Builder', 'collect_elements_html' ], 10, 2 );

		$inline_css .= Assets::$inline_css_dynamic_data;

		/**
		 * Add missing global classes in builder preview if template element loop
		 *
		 * @since 1.8.2 If not static area (global classes are already added in dynamic area)
		 */
		if ( ! isset( $_POST['staticArea'] ) ) {
			$inline_css .= Assets::generate_global_classes();
		}

		$styles = ! empty( $inline_css ) ? "\n<style id=\"bricks-$loop_name\">/* {$loop_name} CSS */\n{$inline_css}</style>\n" : '';

		$data = [
			'html'   => $html,
			'styles' => isset( $_POST['staticArea'] ) ? $inline_css : $styles, // StaticArea.vue: inline CSS only, otherwise full styles with <style> tag (@since 1.12)
		];

		// For builder elements to reduce render_elements API calls (@since 2.0)
		$data['elementsHtml']  = Builder::$elements_html;
		$data['previewTexts']  = Builder::$preview_texts;
		$data['loopingHtml']   = Builder::$looping_html;
		$data['templatesData'] = Builder::$templates_data;

		// Run query to get query results count in builder (@since 1.9.1)
		$element = ! empty( $_POST['element'] ) ? self::decode( $_POST['element'], false ) : false;

		if ( $element ) {
			$query               = new Query( $element );
			$query_results_count = $query->count;

			$data[ "query_results_count:{$element['id']}" ] = $query_results_count;
		}

		wp_send_json_success( $data );
	}

	/**
	 * Don't check for chnage when creating revision as all that changed is the postmeta
	 *
	 * @since 1.7
	 */
	public function dont_check_for_revision_changes() {
		return false;
	}

	/**
	 * Save post
	 *
	 * @since 1.0
	 */
	public function save_post() {
		self::verify_request( 'bricks-nonce-builder' );

		$post_id = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;

		// Return: No post ID set
		if ( ! $post_id ) {
			wp_send_json_error( 'Error: No postId provided!' );
		}

		$post = get_post( $post_id );

		// Update post at the very end
		$the_post = false;

		/**
		 * Save revision in database
		 */
		$revision_id = 0;

		/**
		 * Bricks elements data changed (header, content, footer, components)
		 *
		 * If not, don't save post & don't create revision
		 */
		$bricks_data_changed = isset( $_POST['header'] ) || isset( $_POST['content'] ) || isset( $_POST['footer'] ) || isset( $_POST['components'] );

		// Page settings changed: Re-generate external CSS file
		if ( ! $bricks_data_changed ) {
			$bricks_data_changed = isset( $_POST['pageSettings'] );
		}

		/**
		 * Save components in database
		 *
		 * @since 1.12
		 * @since 2.1: decode param 2 true to add back backslashes via wp_slash
		 * @since 2.1: decode param 2 false as wp_slash not needed for options
		 */
		$components = isset( $_POST['components'] ) ? self::decode( $_POST['components'], false ) : false;

		if ( is_array( $components ) ) {
			update_option( BRICKS_DB_COMPONENTS, $components );
		}

		/*
		 * Collect notifications & conflicts (users modified the same global class at the same time)
		 *
		 * Global data in database differs from builder data
		 *
		 * Data: globalClasses
		 *
		 * @since 1.9.8
		 */
		$notifications       = [];
		$conflicting_classes = [];

		/**
		 * Create revision if data contains 'header', 'footer', or 'content'
		 *
		 * To avoid create false empty revision.
		 *
		 * @since 1.7.1 (if check added)
		 */
		if ( $bricks_data_changed ) {
			// Disabled WordPress content diff check
			add_filter( 'wp_save_post_revision_check_for_changes', [ $this, 'dont_check_for_revision_changes' ] );

			$revision_id = wp_save_post_revision( $post );

			// Delete autosave (@since 1.7)
			if ( $revision_id ) {
				$autosave = wp_get_post_autosave( $post_id );

				if ( $autosave ) {
					wp_delete_post_revision( $autosave );
				}
			}

			remove_filter( 'wp_save_post_revision_check_for_changes', [ $this, 'dont_check_for_revision_changes' ] );
		}

		/**
		 * Save color palettes
		 *
		 * @since 1.4
		 */
		if ( isset( $_POST['colorPalette'] ) && Builder_Permissions::user_has_permission( 'edit_color_palettes' ) ) {
			$color_palette = self::decode( $_POST['colorPalette'], false );

			if ( is_array( $color_palette ) && count( $color_palette ) ) {
				update_option( BRICKS_DB_COLOR_PALETTE, $color_palette );
			} else {
				delete_option( BRICKS_DB_COLOR_PALETTE );
			}
		}

		/**
		 * Save global query loops
		 *
		 * Required capability: access_query_manager
		 *
		 * @since 2.1
		 */
		if ( isset( $_POST['globalQueries'] ) && Builder_Permissions::user_has_permission( 'access_query_manager' ) ) {
			$global_queries = self::decode( $_POST['globalQueries'], false );

			if ( is_array( $global_queries ) && count( $global_queries ) ) {
				update_option( BRICKS_DB_GLOBAL_QUERIES, $global_queries );
			} else {
				delete_option( BRICKS_DB_GLOBAL_QUERIES );
			}
		}

		/**
		 * Save globalQueriesCategories
		 *
		 * @since 2.1
		 */
		if ( isset( $_POST['globalQueriesCategories'] ) && Builder_Permissions::user_has_permission( 'access_query_manager' ) ) {
			$global_queries_categories = self::decode( $_POST['globalQueriesCategories'], false );

			if ( is_array( $global_queries_categories ) && count( $global_queries_categories ) ) {
				update_option( BRICKS_DB_GLOBAL_QUERIES_CATEGORIES, $global_queries_categories );
			} else {
				delete_option( BRICKS_DB_GLOBAL_QUERIES_CATEGORIES );
			}
		}

		/**
		 * Save icon sets
		 *
		 * @since 2.0
		 */
		if ( isset( $_POST['iconSets'] ) ) {
			$icon_sets = self::decode( $_POST['iconSets'], false );

			if ( is_array( $icon_sets ) && count( $icon_sets ) ) {
				update_option( BRICKS_DB_ICON_SETS, $icon_sets );
			} else {
				delete_option( BRICKS_DB_ICON_SETS );
			}
		}

		/**
		 * Save custom icons
		 *
		 * @since 2.0
		 */
		if ( isset( $_POST['customIcons'] ) ) {
			$custom_icons = self::decode( $_POST['customIcons'], false );

			if ( is_array( $custom_icons ) && count( $custom_icons ) ) {
				update_option( BRICKS_DB_CUSTOM_ICONS, $custom_icons );
			} else {
				delete_option( BRICKS_DB_CUSTOM_ICONS );
			}
		}

		/**
		 * Save disabled icon sets
		 *
		 * @since 2.0
		 */
		if ( isset( $_POST['disabledIconSets'] ) ) {
			$disabled_icon_sets = self::decode( $_POST['disabledIconSets'], false );

			if ( is_array( $disabled_icon_sets ) && count( $disabled_icon_sets ) ) {
				update_option( BRICKS_DB_DISABLED_ICON_SETS, $disabled_icon_sets );
			} else {
				delete_option( BRICKS_DB_DISABLED_ICON_SETS );
			}
		}

		/**
		 * Save font favorites
		 *
		 * @since 2.0
		 */
		if ( isset( $_POST['fontFavorites'] ) ) {
			$font_favorites = self::decode( $_POST['fontFavorites'], false );

			if ( is_array( $font_favorites ) && count( $font_favorites ) ) {
				update_option( BRICKS_DB_FONT_FAVORITES, $font_favorites );
			} else {
				delete_option( BRICKS_DB_FONT_FAVORITES );
			}
		}

		/**
		 * Save global classes
		 *
		 * @since 1.4
		 * @since 1.9.9: Added global classes conflict check and notifications (if enabled in Bricks settings)
		 */
		$global_changes      = isset( $_POST['globalChanges'] ) ? self::decode( $_POST['globalChanges'], false ) : [];
		$global_ids_added    = $global_changes['added'] ?? [];
		$global_ids_deleted  = $global_changes['deleted'] ?? [];
		$global_ids_modified = $global_changes['modified'] ?? [];
		$global_ids_trashed  = $global_changes['trashed'] ?? []; // Track trashed classes (@since 1.11)

		if ( isset( $_POST['globalClasses'] ) && ( Builder_Permissions::user_has_permission( 'edit_global_classes' ) || Builder_Permissions::user_has_permission( 'create_global_classes' ) ) ) {
			$global_classes = self::decode( $_POST['globalClasses'], false );

			// Remove in savePost if conflict has been discarded
			$global_classes_timestamp = $_POST['globalClassesTimestamp'] ?? 0;

			// Check: Global classes conflict check enabled
			$global_classes_snyc = Database::get_setting( 'builderGlobalClassesSync', false );

			$global_classes_db           = $global_classes_snyc ? get_option( BRICKS_DB_GLOBAL_CLASSES, [] ) : [];
			$global_classes_db_timestamp = $global_classes_snyc ? get_option( BRICKS_DB_GLOBAL_CLASSES_TIMESTAMP, 0 ) : 0;
			$global_classes_db_user_id   = $global_classes_snyc ? get_option( BRICKS_DB_GLOBAL_CLASSES_USER, 0 ) : 0;
			$global_classes_trash        = get_option( BRICKS_DB_GLOBAL_CLASSES_TRASH, [] );

			/**
			 * STEP: Classes with newer timestamp found in database
			 *
			 * The same or a different user has modified global classes in the meantime.
			 *
			 * Collect new classes, deleted classes, and modified classes to show in builder to accept or discard.
			 *
			 * @since 1.9.9
			 */
			if (
				$global_classes_snyc &&
				$global_classes_timestamp && $global_classes_db_timestamp &&
				intval( $global_classes_db_timestamp ) > intval( $global_classes_timestamp )
			) {
				$global_classes_db_user = get_user_by( 'ID', $global_classes_db_user_id );
				$author                 = $global_classes_db_user->display_name ?? esc_html__( 'Another user', 'bricks' );
				$avatar                 = get_avatar( $global_classes_db_user_id, 40 );

				// Collect all added, deleted and modified classes from database
				$classes_added    = [];
				$classes_deleted  = [];
				$classes_modified = [];
				$classes_trashed  = [];

				// STEP: Loop through classes in database to find newly added and modified classes
				foreach ( $global_classes_db as $global_class_db ) {
					$found    = false;
					$modified = false;

					foreach ( $global_classes as $global_class ) {
						// Class found (i.e. not newly added)
						if ( $global_class['id'] === $global_class_db['id'] ) {
							$found                   = true;
							$compare_global_class    = $global_class;
							$compare_global_class_db = $global_class_db;

							// Remove 'modified' timestamp and 'user' (ID) to compare
							unset( $compare_global_class['modified'], $compare_global_class_db['modified'] );
							unset( $compare_global_class['user'], $compare_global_class_db['user'] );

							// Class modified (name, category, settings, etc.)
							if ( wp_json_encode( $compare_global_class ) !== wp_json_encode( $compare_global_class_db ) ) {
								// Database 'modified' timestamp newer than builder timestamp
								if ( ! empty( $global_class_db['modified'] ) && intval( $global_class_db['modified'] ) > intval( $global_classes_timestamp ) ) {
									$modified = true;
								}
							}
							break;
						}
					}

					// Class added in database, but not in builder, and not deleted or trashed in builder
					if (
						! $found &&
						! in_array( $global_class_db['id'], $global_ids_added ) &&
						! in_array( $global_class_db['id'], $global_ids_deleted ) &&
						! in_array( $global_class_db['id'], $global_ids_trashed )
					) {
						$classes_added[] = $global_class_db;
					}

					// Class modified and not deleted or trashed in builder
					if ( $found && $modified && ! in_array( $global_class_db['id'], $global_ids_deleted ) && ! in_array( $global_class_db['id'], $global_ids_trashed ) ) {
						$classes_modified[] = $global_class_db;

						// CONFLICT: Class modified in database and builder
						if ( in_array( $global_class_db['id'], $global_ids_modified ) ) {
							$conflicting_classes[] = $global_class_db;
						}
					}
				}

				// Check for trashed classes
				foreach ( $global_ids_trashed as $trashed_id ) {
					$trashed_class = array_filter(
						$global_classes_db,
						function( $class ) use ( $trashed_id ) {
							return $class['id'] === $trashed_id;
						}
					);

					if ( ! empty( $trashed_class ) ) {
						$classes_trashed[] = reset( $trashed_class );
					}
				}

				// Handle modified class in database, incoming class moved the same class to trash
				foreach ( $classes_trashed as $trashed_class ) {
					if ( in_array( $trashed_class['id'], $global_ids_modified ) ) {
						// Keep the modifications by User A, but move to trash
						$global_classes_trash[] = $trashed_class;
						$notifications[]        = [
							'key'    => 'globalClassesTrashed',
							'action' => 'trashed',
							'data'   => [ $trashed_class ],
							// translators: %s: Class name
							'desc'   => sprintf( esc_html__( 'Class "%s" has been moved to trash but your modifications are kept.', 'bricks' ), $trashed_class['name'] ),
						];
					}
				}

				// Handle modified class in database, incoming class permanently deletes the same class
				foreach ( $global_ids_deleted as $deleted_id ) {
					if ( in_array( $deleted_id, $global_ids_modified ) || in_array( $deleted_id, $global_ids_added ) ) {
						$conflicting_class = array_filter(
							$global_classes,
							function( $class ) use ( $deleted_id ) {
								return $class['id'] === $deleted_id;
							}
						);

						if ( ! empty( $conflicting_class ) ) {
							$conflicting_class     = reset( $conflicting_class );
							$conflicting_classes[] = $conflicting_class;
							$notifications[]       = [
								'key'    => 'globalClassesConflict',
								'action' => 'conflict',
								'data'   => [ $conflicting_class ],
								// translators: %s: Class name
								'desc'   => sprintf( esc_html__( 'Conflict: Class "%s" has been permanently deleted by another user.', 'bricks' ), $conflicting_class['name'] ),
							];
						}
					}
				}

				// Add notifications for modified, added, and deleted classes
				if ( count( $classes_modified ) ) {
					$notifications[] = [
						'data'      => $classes_modified,
						'key'       => 'globalClasses',
						'action'    => 'modified',
						'author'    => $author,
						'avatar'    => $avatar,
						'timestamp' => $global_classes_db_timestamp,
						'desc'      => esc_html__( 'Modified', 'bricks' ) . ': ' . esc_html__( 'Classes', 'bricks' ) . ' (' . count( $classes_modified ) . ')',
					];

					// Add conflict: Classes modified in database and builder
					if ( count( $conflicting_classes ) ) {
						$notifications[] = [
							'conflict' => '<strong>' . esc_html( 'Conflict', 'bricks' ) . '</strong>: ' . esc_html__( 'The following classes have been modified by the user below since your last save. Accept or discard those changes to continue your save.', 'bricks' ),
							'data'     => $conflicting_classes,
						];
					}
				}

				// Add notification: Classes added
				if ( count( $classes_added ) ) {
					$notifications[] = [
						'data'      => $classes_added,
						'key'       => 'globalClasses',
						'action'    => 'added',
						'author'    => $author,
						'avatar'    => $avatar,
						'timestamp' => $global_classes_db_timestamp,
						'desc'      => esc_html__( 'Added', 'bricks' ) . ': ' . esc_html__( 'Classes', 'bricks' ) . ' (' . count( $classes_added ) . ')',
					];
				}

				foreach ( $global_classes as $global_class ) {
					$found = false;

					// STEP: Loop through classes in builder to find deleted database classes
					foreach ( $global_classes_db as $global_class_db ) {
						if ( $global_class['id'] === $global_class_db['id'] ) {
							$found = true;
							break;
						}
					}

					// Class deleted: Not found in builder, and not added in builder either
					if ( ! $found && ! in_array( $global_class['id'], $global_ids_added ) ) {
						$classes_deleted[] = $global_class;
					}
				}

				// Add notification: Classes deleted
				if ( count( $classes_deleted ) ) {
					$notifications[] = [
						'data'      => $classes_deleted,
						'key'       => 'globalClasses',
						'action'    => 'deleted',
						'author'    => $author,
						'avatar'    => $avatar,
						'timestamp' => $global_classes_db_timestamp,
						'desc'      => esc_html__( 'Deleted', 'bricks' ) . ': ' . esc_html__( 'Classes', 'bricks' ) . ' (' . count( $classes_deleted ) . ')',
					];
				}
			}

			// STEP: Return notifications without save if conflicts found
			if ( count( $notifications ) && count( $conflicting_classes ) || ! empty( $conflicting_trash_classes ) ) {
				wp_send_json_error(
					[
						'notifications'   => $notifications,
						'globalClassesDb' => $global_classes_db,
					]
				);
			}

			/**
			 * STEP: Handle global classes trash synchronization
			 *
			 * globalClassesTrash: Array of trashed global classes (always sent from builder, even if unchanged)
			 *
			 * @since 1.11
			 */
			$incoming_trash = isset( $_POST['globalClassesTrash'] ) ? self::decode( $_POST['globalClassesTrash'], false ) : [];
			$existing_trash = get_option( BRICKS_DB_GLOBAL_CLASSES_TRASH, [] );

			// Sanity check: Ensure $incoming_trash and $existing_trash are arrays
			$incoming_trash = is_array( $incoming_trash ) ? $incoming_trash : [];
			$existing_trash = is_array( $existing_trash ) ? $existing_trash : [];

			// Remove restored classes from incoming trash
			$incoming_trash = array_filter(
				$incoming_trash,
				function( $trash_item ) use ( $global_classes ) {
					return ! in_array( $trash_item['id'], array_column( $global_classes, 'id' ) );
				}
			);

			// If there's incoming trash, proceed with synchronization
			if ( ! empty( $incoming_trash ) ) {
				// Merge incoming and existing trash, prioritizing the most recent deletedAt
				$merged_trash = array_merge( $existing_trash, $incoming_trash );
				$unique_trash = [];
				foreach ( $merged_trash as $trash_item ) {
					if ( ! isset( $trash_item['id'] ) || ! isset( $trash_item['deletedAt'] ) ) {
						continue; // Skip invalid items
					}
					$existing_index = array_search( $trash_item['id'], array_column( $unique_trash, 'id' ) );
					if ( $existing_index === false ) {
						$unique_trash[] = $trash_item;
					} else {
						if ( $trash_item['deletedAt'] > $unique_trash[ $existing_index ]['deletedAt'] ) {
							$unique_trash[ $existing_index ] = $trash_item;
						}
					}
				}

				// Remove items from trash if they exist in global_classes
				$final_trash = array_values( // Ensure we have a sequential array (@since 1.12)
					array_filter(
						$unique_trash,
						function( $trash_item ) use ( $global_classes ) {
							return ! in_array( $trash_item['id'], array_column( $global_classes, 'id' ) );
						}
					)
				);

				// Save updated trash
				if ( empty( $final_trash ) ) {
					delete_option( BRICKS_DB_GLOBAL_CLASSES_TRASH );
				} else {
					update_option( BRICKS_DB_GLOBAL_CLASSES_TRASH, $final_trash );
				}

				// Check for conflicts between final trash and active classes
				$conflicting_trash_classes = array_intersect(
					array_column( $final_trash, 'id' ),
					array_column( $global_classes, 'id' )
				);

				if ( ! empty( $conflicting_trash_classes ) ) {
					$notifications[] = [
						'key'    => 'globalClassesTrashConflict',
						'action' => 'conflict',
						'data'   => $conflicting_trash_classes,
						'desc'   => esc_html__( 'Conflict: Some classes exist in both trash and active classes', 'bricks' ),
					];
				}

				// Return notifications if trash conflicts found
				if ( count( $notifications ) ) {
					wp_send_json_error(
						[
							'notifications'      => $notifications,
							'globalClassesDb'    => $global_classes_db,
							'globalClassesTrash' => $existing_trash,
						]
					);
				}
			} else {
				// No incoming trash, but we need to check if previously trashed classes have been restored
				$final_trash = array_filter(
					$existing_trash,
					function( $trash_item ) use ( $global_classes ) {
						return ! in_array( $trash_item['id'], array_column( $global_classes, 'id' ), true );
					}
				);
			}

			// Always update the trash option, even if it's unchanged
			update_option( BRICKS_DB_GLOBAL_CLASSES_TRASH, $final_trash );

			// STEP: Add 'modified' timestamp and 'user' (ID) to modified global class (to compare on save)
			if ( count( $global_ids_modified ) ) {
				$current_timestamp = time();
				$current_user_id   = get_current_user_id();

				foreach ( $global_classes as $global_class_index => $global_class ) {
					if ( in_array( $global_class['id'], $global_ids_modified ) ) {
						$global_classes[ $global_class_index ]['modified'] = $current_timestamp;
						$global_classes[ $global_class_index ]['user_id']  = $current_user_id;
					}
				}
			}

			$global_classes_response = Helpers::save_global_classes_in_db( $global_classes );

			if ( isset( $global_classes_response['timestamp'] ) ) {
				$_POST['globalClassesTimestamp'] = $global_classes_response['timestamp'];
			}

			if ( isset( $global_classes_response['user_id'] ) ) {
				$_POST['globalClassesUser'] = get_userdata( $global_classes_response['user_id'] )->display_name ?? '';
			}

			// STEP: Return notifications
			if ( count( $notifications ) ) {
				wp_send_json_error(
					[
						'notifications'        => $notifications,
						'globalClassesDb'      => $global_classes_db,
						'globalClassesTrashDb' => $existing_trash,
					]
				);
			}
		}

		/**
		 * Save global classes locked
		 *
		 * @since 1.4
		 */
		if ( isset( $_POST['globalClassesLocked'] ) && Builder_Permissions::user_has_permission( 'edit_global_classes' ) ) {
			$global_classes_locked = self::decode( $_POST['globalClassesLocked'], false );

			if ( is_array( $global_classes_locked ) && count( $global_classes_locked ) ) {
				update_option( BRICKS_DB_GLOBAL_CLASSES_LOCKED, $global_classes_locked, false );
			} else {
				delete_option( BRICKS_DB_GLOBAL_CLASSES_LOCKED );
			}
		}

		/**
		 * Save global classes categories
		 *
		 * @since 1.9.4
		 */
		if ( isset( $_POST['globalClassesCategories'] ) && Builder_Permissions::user_has_permission( 'edit_global_classes' ) ) {
			$global_classes_categories = self::decode( $_POST['globalClassesCategories'], false );

			if ( is_array( $global_classes_categories ) && count( $global_classes_categories ) ) {
				update_option( BRICKS_DB_GLOBAL_CLASSES_CATEGORIES, $global_classes_categories, false );
			} else {
				delete_option( BRICKS_DB_GLOBAL_CLASSES_CATEGORIES );
			}
		}

		/**
		 * Save global variables
		 *
		 * @since 1.9.8
		 */
		if ( isset( $_POST['globalVariables'] ) && Builder_Permissions::user_has_permission( 'access_variable_manager' ) ) {
			$global_variables = self::decode( $_POST['globalVariables'], false );

			// Process renamed variables if any
			if ( isset( $_POST['globalVariablesRenamed'] ) && ! empty( $_POST['globalVariablesRenamed'] ) ) {
				$renamed_variable_ids = self::decode( $_POST['globalVariablesRenamed'], false );

				if ( is_array( $renamed_variable_ids ) && ! empty( $renamed_variable_ids ) ) {
					// Get existing variables from database to compare
					$existing_variables = get_option( BRICKS_DB_GLOBAL_VARIABLES, [] );

					// Create lookup arrays for existing and new variables by ID
					$existing_variables_by_id = [];
					$new_variables_by_id      = [];

					// Index existing variables by ID
					if ( is_array( $existing_variables ) ) {
						foreach ( $existing_variables as $variable ) {
							if ( isset( $variable['id'] ) ) {
								$existing_variables_by_id[ $variable['id'] ] = $variable;
							}
						}
					}

					// Index new variables by ID
					if ( is_array( $global_variables ) ) {
						foreach ( $global_variables as $variable ) {
							if ( isset( $variable['id'] ) ) {
								$new_variables_by_id[ $variable['id'] ] = $variable;
							}
						}
					}

					// Process each renamed variable
					foreach ( $renamed_variable_ids as $variable_id ) {
						// Skip if this is a new variable (not in database)
						if ( ! isset( $existing_variables_by_id[ $variable_id ] ) || ! isset( $new_variables_by_id[ $variable_id ] ) ) {
							continue;
						}

						$old_name = $existing_variables_by_id[ $variable_id ]['name'];
						$new_name = $new_variables_by_id[ $variable_id ]['name'];

						// Skip if name hasn't actually changed
						if ( $old_name === $new_name ) {
							continue;
						}

						// Update all references to this variable
						Helpers::update_global_variable_references( $old_name, $new_name );
					}
				}
			}

			Helpers::save_global_variables_in_db( $global_variables );
		}

		/**
		 * Save global variables categories
		 *
		 * @since 1.9.8
		 */
		if ( isset( $_POST['globalVariablesCategories'] ) && Builder_Permissions::user_has_permission( 'access_variable_manager' ) ) {
			$global_variables_categories = self::decode( $_POST['globalVariablesCategories'], false );

			if ( is_array( $global_variables_categories ) && count( $global_variables_categories ) ) {
				update_option( BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES, $global_variables_categories, false );
			} else {
				delete_option( BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES );
			}
		}

		/**
		 * Save global elements
		 *
		 * @since 1.4
		 */
		if ( isset( $_POST['globalElements'] ) ) {
			if ( isset( $_POST['globalElements'] ) && Builder_Permissions::user_has_permission( 'manage_global_elements' ) ) {
				$global_elements = self::decode( $_POST['globalElements'], false );

				if ( is_array( $global_elements ) && count( $global_elements ) ) {
					$global_elements = Helpers::security_check_elements_before_save( $global_elements, null, 'global' );
					update_option( BRICKS_DB_GLOBAL_ELEMENTS, $global_elements );
				} else {
					delete_option( BRICKS_DB_GLOBAL_ELEMENTS );
				}
			}
		}

		/**
		 * Save pinned elements
		 *
		 * @since 1.4
		 */

		if ( isset( $_POST['pinnedElements'] ) && Builder_Permissions::user_has_permission( 'pin_unpin_elements' ) ) {
			$pinned_elements = self::decode( $_POST['pinnedElements'], false );

			if ( is_array( $pinned_elements ) && count( $pinned_elements ) ) {
				update_option( BRICKS_DB_PINNED_ELEMENTS, $pinned_elements );
			} else {
				delete_option( BRICKS_DB_PINNED_ELEMENTS );
			}
		}

		/**
		 * Save pseudo-classes
		 *
		 * @since 1.4
		 */
		if ( isset( $_POST['pseudoClasses'] ) && Builder_Permissions::user_has_permission( 'access_pseudo_selectors' ) ) {
			$global_pseudo_classes = self::decode( $_POST['pseudoClasses'] );

			if ( is_array( $global_pseudo_classes ) && count( $global_pseudo_classes ) ) {
				update_option( BRICKS_DB_PSEUDO_CLASSES, $global_pseudo_classes );
			} else {
				delete_option( BRICKS_DB_PSEUDO_CLASSES );
			}
		}

		/**
		 * Save theme styles
		 *
		 * @since 1.4
		 */
		if ( isset( $_POST['themeStyles'] ) && Builder_Permissions::user_has_permission( 'access_theme_styles' ) ) {
			$theme_styles = self::decode( $_POST['themeStyles'], false );

			foreach ( $theme_styles as $theme_style_id => $theme_style ) {
				// Remove empty settings 'group'
				if ( isset( $theme_style['settings'] ) ) {
					foreach ( $theme_style['settings'] as $group_key => $group_settings ) {
						if ( ! $group_settings || ( is_array( $group_settings ) && ! count( $group_settings ) ) ) {
							unset( $theme_styles[ $theme_style_id ]['settings'][ $group_key ] );
						}
					}
				}
			}

			if ( is_array( $theme_styles ) && count( $theme_styles ) ) {
				update_option( BRICKS_DB_THEME_STYLES, $theme_styles );
			} else {
				delete_option( BRICKS_DB_THEME_STYLES );
			}
		}

		/**
		 * Save page data (post meta table)
		 */
		$header  = isset( $_POST['header'] ) ? self::decode( $_POST['header'] ) : [];
		$content = isset( $_POST['content'] ) ? self::decode( $_POST['content'] ) : [];
		$footer  = isset( $_POST['footer'] ) ? self::decode( $_POST['footer'] ) : [];

		/**
		 * Save page setting
		 *
		 * @since 1.4
		 */
		if ( isset( $_POST['pageSettings'] ) && Builder_Permissions::user_has_permission( 'access_page_settings' ) ) {
			$page_settings = self::decode( $_POST['pageSettings'] );

			if ( is_array( $page_settings ) && count( $page_settings ) ) {
				if ( ! empty( $page_settings['postName'] ) || ! empty( $page_settings['postTitle'] ) ) {
					$the_post['ID'] = $post_id;
				}

				// Update post name (slug)
				if ( ! empty( $page_settings['postName'] ) ) {
					$the_post['post_name'] = trim( $page_settings['postName'] );

					unset( $page_settings['postName'] );
				}

				// Update post title
				if ( ! empty( $page_settings['postTitle'] ) ) {
					$the_post['post_title'] = trim( $page_settings['postTitle'] );

					unset( $page_settings['postTitle'] );
				}

				update_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS, $page_settings );
			} else {
				delete_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS );
			}
		}

		/**
		 * Bricks template
		 *
		 * @since 1.4
		 */
		$template_type = ! empty( $_POST['templateType'] ) ? sanitize_text_field( $_POST['templateType'] ) : false;

		if ( $template_type ) {
			update_post_meta( $post_id, BRICKS_DB_TEMPLATE_TYPE, $template_type );

			switch ( $template_type ) {
				// Header template
				case 'header':
					if ( isset( $_POST['header'] ) ) {
						$header = Helpers::security_check_elements_before_save( $header, $post_id, 'header' );

						if ( is_array( $header ) && count( $header ) ) {
							// Save revision in post meta ('update_post_meta' can't process post type 'revision'
							if ( $revision_id ) {
								update_metadata( 'post', $revision_id, BRICKS_DB_PAGE_HEADER, $header );
							}

							update_post_meta( $post_id, BRICKS_DB_PAGE_HEADER, $header );
						} else {
							delete_post_meta( $post_id, BRICKS_DB_PAGE_HEADER );
						}
					}
					break;

				// Footer template
				case 'footer':
					if ( isset( $_POST['footer'] ) ) {
						$footer = Helpers::security_check_elements_before_save( $footer, $post_id, 'footer' );

						if ( is_array( $footer ) && count( $footer ) ) {
							// Save revision in post meta ('update_post_meta' can't process post type 'revision'
							if ( $revision_id ) {
								update_metadata( 'post', $revision_id, BRICKS_DB_PAGE_FOOTER, $footer );
							}

							update_post_meta( $post_id, BRICKS_DB_PAGE_FOOTER, $footer );
						} else {
							delete_post_meta( $post_id, BRICKS_DB_PAGE_FOOTER );
						}
					}
					break;

				// Any other template type
				default:
					if ( isset( $_POST['content'] ) ) {
						$content = Helpers::security_check_elements_before_save( $content, $post_id, 'content' );

						if ( is_array( $content ) && count( $content ) ) {
							// Save revision in post meta ('update_post_meta' can't process post type 'revision')
							if ( $revision_id ) {
								update_metadata( 'post', $revision_id, BRICKS_DB_PAGE_CONTENT, $content );
							}

							update_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, $content );
						} else {
							delete_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT );
						}
					}
			}
		}

		/**
		 * Template settings
		 *
		 * @since 1.4
		 */
		if ( isset( $_POST['templateSettings'] ) && Builder_Permissions::user_has_permission( 'access_template_settings' ) ) {
			$template_settings = self::decode( $_POST['templateSettings'], false );

			if ( is_array( $template_settings ) && count( $template_settings ) ) {
				// User saved template settings: Delete auto content notification
				unset( $template_settings['templatePreviewAutoContent'] );

				Helpers::set_template_settings( $post_id, $template_settings );
			} else {
				Helpers::delete_template_settings( $post_id );
			}
		}

		/**
		 * Content (not a Bricks template)
		 *
		 * @since 1.4
		 */
		if ( isset( $_POST['content'] ) && get_post_type( $post_id ) !== BRICKS_DB_TEMPLATE_SLUG ) {
			$content = Helpers::security_check_elements_before_save( $content, $post_id, 'content' );

			if ( is_array( $content ) && count( $content ) ) {
				// Update empty or existing Gutenberg post_content (preserve Classic Editor data)
				$existing_post_content = $post->post_content;

				if ( Database::get_setting( 'bricks_to_wp' ) && ( ! $existing_post_content || has_blocks( get_post( $post_id ) ) ) ) {
					$new_post_content = Blocks::serialize_bricks_to_blocks( $content, $post_id );

					if ( $new_post_content ) {
						$the_post = (
							[
								'ID'           => $post_id,
								'post_content' => $new_post_content,
							]
						);
					}
				}

				// Save revision in post meta ('update_post_meta' can't process post type 'revision')
				if ( $revision_id ) {
					update_metadata( 'post', $revision_id, BRICKS_DB_PAGE_CONTENT, $content );
				}

				// Save content in post meta
				update_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, $content );
			} else {
				delete_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT );
			}
		}

		// Always set editor mode to 'bricks' if the template type is header or footer, or if BRICKS_DB_PAGE_CONTENT is not empty (#86c48ct1y; @since 2.0)
		$editor_mode = ( in_array( $template_type, [ 'header', 'footer' ], true ) ||
				get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true ) )
				? 'bricks'
				: 'wordpress';

		update_post_meta( $post_id, BRICKS_DB_EDITOR_MODE, $editor_mode );

		/**
		 * STEP: Update post to (1) update post date & (2) re-generate CSS file via 'save_post' in files.php
		 *
		 * Check $wp_post_updated to ensure wp_update_post did not already ran above.
		 *
		 * @since 1.5.7
		 */
		if ( $bricks_data_changed ) {
			$post_id = $the_post ? wp_update_post( $the_post ) : wp_update_post( $post );
		}

		/**
		 * Save custom fonts
		 *
		 * @since 1.0
		 */
		if ( isset( $_POST['fonts'] ) && Builder_Permissions::user_has_permission( 'access_font_manager' ) ) {
			$custom_fonts = self::decode( $_POST['fonts'], false );
			$custom_fonts = $custom_fonts['custom'];
			$font_changes = isset( $_POST['fontChanges'] ) ? self::decode( $_POST['fontChanges'], false ) : [];

			// Get existing custom fonts from database for comparison
			$existing_custom_fonts = Custom_Fonts::get_custom_fonts();

			// Track actual post IDs for fonts (for variant processing later)
			$actual_post_ids = [];

			// Helper function to get font extension from URL or type
			$get_font_extension = function( $font_data ) {
				// First try to get from type
				if ( isset( $font_data['type'] ) && $font_data['type'] ) {
					$type_parts = explode( '/', $font_data['type'] );
					if ( count( $type_parts ) >= 2 ) {
						$extension = $type_parts[1];
						// Normalize common variations
						if ( $extension === 'truetype' ) {
							$extension = 'ttf';
						}
						return $extension;
					}
				}

				// Fallback to getting from URL
				if ( isset( $font_data['url'] ) && $font_data['url'] ) {
					$path_info = pathinfo( $font_data['url'] );
					if ( isset( $path_info['extension'] ) ) {
						return strtolower( $path_info['extension'] );
					}
				}

				// Default fallback
				return 'woff2';
			};

			// Process family level changes
			if ( ! empty( $font_changes['families'] ) ) {
				// Process deleted fonts - check if any existing fonts are missing from new data
				if ( $existing_custom_fonts ) {
					foreach ( $existing_custom_fonts as $font_id => $font_data ) {
						if ( ! isset( $custom_fonts[ $font_id ] ) || in_array( $font_id, $font_changes['families']['deleted'] ) ) {
							$post_id = intval( str_replace( 'custom_font_', '', $font_id ) );
							wp_delete_post( $post_id, true );
						}
					}
				}

				// Process added and modified fonts
				foreach ( $custom_fonts as $font_id => $font_data ) {
					$post_id     = intval( str_replace( 'custom_font_', '', $font_id ) );
					$is_new      = ! isset( $existing_custom_fonts[ $font_id ] );
					$is_modified = in_array( $font_id, $font_changes['families']['modified'] );

					if ( $is_new || $is_modified ) {
						// Prepare post data
						$post_data = [
							'post_title'  => $font_data['family'],
							'post_type'   => BRICKS_DB_CUSTOM_FONTS,
							'post_status' => 'publish',
						];

						if ( ! $is_new ) {
							$post_data['ID'] = $post_id;
							$result          = wp_update_post( $post_data );

							// For existing fonts, post_id stays the same
							$actual_post_ids[ $font_id ] = $post_id;
						} else {
							// Check if this is a draft post that already exists (created by create_draft_font)
							$existing_post = get_post( $post_id );
							if ( $existing_post && $existing_post->post_type === BRICKS_DB_CUSTOM_FONTS ) {
								// Update the existing draft post
								$post_data['ID']             = $post_id;
								$result                      = wp_update_post( $post_data );
								$actual_post_ids[ $font_id ] = $post_id;
							} else {
								// Create a completely new post
								$new_post_id                 = wp_insert_post( $post_data );
								$actual_post_ids[ $font_id ] = $new_post_id;
								$post_id                     = $new_post_id;
							}
						}

						// Update font faces if they exist
						if ( isset( $font_data['fontFaces'] ) ) {
							// Check if fontFaces is an array before updating
							if ( is_array( $font_data['fontFaces'] ) ) {
								// Convert frontend format (URL/type) to database format (attachment ID/extension)
								$converted_font_faces = [];
								foreach ( $font_data['fontFaces'] as $variant_key => $font_face ) {
									if ( isset( $font_face['url'] ) && $font_face['url'] ) {
										$font_extension = $get_font_extension( $font_face );
										$attachment_id  = attachment_url_to_postid( $font_face['url'] );

										if ( $attachment_id ) {
											$converted_font_faces[ $variant_key ] = [
												$font_extension => $attachment_id
											];
										}
									}
								}

								$meta_result = update_post_meta( $post_id, BRICKS_DB_CUSTOM_FONT_FACES, $converted_font_faces );
							}
						}
					} else {
						// For unchanged fonts, still track their post_id
						$actual_post_ids[ $font_id ] = $post_id;
					}
				}
			}

			// Process variant level changes
			if ( ! empty( $font_changes['variants'] ) ) {

				// Before processing, detect "replace" scenarios where same variant is both deleted and added
				// and move them to modified instead
				$deleted_variants = $font_changes['variants']['deleted'] ?? [];
				$added_variants   = $font_changes['variants']['added'] ?? [];

				foreach ( $deleted_variants as $font_id => $variants ) {
					if ( isset( $added_variants[ $font_id ] ) ) {
						// Find variants that are both deleted and added (replacements)
						$common_variants = array_intersect( $variants, $added_variants[ $font_id ] );

						if ( ! empty( $common_variants ) ) {

							// Move common variants from deleted/added to modified
							if ( ! isset( $font_changes['variants']['modified'] ) ) {
								$font_changes['variants']['modified'] = [];
							}
							if ( ! isset( $font_changes['variants']['modified'][ $font_id ] ) ) {
								$font_changes['variants']['modified'][ $font_id ] = [];
							}

							foreach ( $common_variants as $variant_key ) {
								// Add to modified
								if ( ! in_array( $variant_key, $font_changes['variants']['modified'][ $font_id ] ) ) {
									$font_changes['variants']['modified'][ $font_id ][] = $variant_key;
								}

								// Remove from deleted
								$delete_index = array_search( $variant_key, $font_changes['variants']['deleted'][ $font_id ] );
								if ( $delete_index !== false ) {
									unset( $font_changes['variants']['deleted'][ $font_id ][ $delete_index ] );
								}

								// Remove from added
								$add_index = array_search( $variant_key, $font_changes['variants']['added'][ $font_id ] );
								if ( $add_index !== false ) {
									unset( $font_changes['variants']['added'][ $font_id ][ $add_index ] );
								}
							}

							// Clean up empty arrays
							if ( empty( $font_changes['variants']['deleted'][ $font_id ] ) ) {
								unset( $font_changes['variants']['deleted'][ $font_id ] );
							}
							if ( empty( $font_changes['variants']['added'][ $font_id ] ) ) {
								unset( $font_changes['variants']['added'][ $font_id ] );
							}
						}
					}
				}

				foreach ( $font_changes['variants'] as $change_type => $fonts ) {
					if ( empty( $fonts ) ) {
						continue;
					}

					foreach ( $fonts as $font_id => $variants ) {
						if ( empty( $variants ) ) {
							continue;
						}

						// Use the actual post_id that was tracked during family processing
						$post_id = isset( $actual_post_ids[ $font_id ] ) ? $actual_post_ids[ $font_id ] : intval( str_replace( 'custom_font_', '', $font_id ) );

						// Get current font faces
						$current_font_faces = get_post_meta( $post_id, BRICKS_DB_CUSTOM_FONT_FACES, true );

						// If we have a structured array of font faces
						if ( is_array( $current_font_faces ) ) {
							// Process deleted variants
							if ( $change_type === 'deleted' ) {
								foreach ( $variants as $variant_key ) {
									// Extract weight and style from variant key (e.g., "400normal")
									preg_match( '/(\d+)(.*)/', $variant_key, $matches );
									if ( count( $matches ) >= 3 ) {
										$weight = $matches[1];
										$style  = $matches[2] ? $matches[2] : 'normal';

										// Construct the expected database key format
										$db_key = $weight;
										if ( $style !== 'normal' ) {
											$db_key .= $style;
										}

										// Unset using the determined database key
										if ( isset( $current_font_faces[ $db_key ] ) ) {
											unset( $current_font_faces[ $db_key ] );
										}

										// Also unset using the client-side key as a fallback for potential inconsistencies
										if ( $db_key !== $variant_key && isset( $current_font_faces[ $variant_key ] ) ) {
											unset( $current_font_faces[ $variant_key ] );
										}
									}
								}

								// Update the font faces
								$meta_result = update_post_meta( $post_id, BRICKS_DB_CUSTOM_FONT_FACES, $current_font_faces );
							}
							// Process added variants
							elseif ( $change_type === 'added' ) {
								// Get the updated font data from the client
								if ( isset( $custom_fonts[ $font_id ] ) && isset( $custom_fonts[ $font_id ]['fontFaces'] ) ) {
									$new_font_faces = $custom_fonts[ $font_id ]['fontFaces'];

									// If fontFaces is a string (CSS), convert it to an array
									if ( is_string( $new_font_faces ) ) {
										// Try to parse as JSON first
										$decoded = json_decode( $new_font_faces, true );
										if ( $decoded && is_array( $decoded ) ) {
											$new_font_faces = $decoded;
										} else {
											// If not JSON, it might be a CSS string - we'll keep the current structure
											$new_font_faces = $current_font_faces;
										}
									}

									// For each added variant, ensure it exists in the font faces
									foreach ( $variants as $variant_key ) {
										// Extract weight and style from variant key (e.g., "400normal")
										preg_match( '/(\d+)(.*)/', $variant_key, $matches );
										if ( count( $matches ) >= 3 ) {
											$weight = $matches[1];
											$style  = $matches[2] ? $matches[2] : 'normal';

											// Check if this variant exists in the new font faces
											if ( isset( $new_font_faces[ $variant_key ] ) ) {
												$font_extension = $get_font_extension( $new_font_faces[ $variant_key ] );

												// Find and remove the old variant with same URL
												foreach ( $current_font_faces as $old_key => $old_face ) {
													// Check all possible extensions, not just woff2
													foreach ( [ 'woff2', 'woff', 'ttf' ] as $ext ) {
														if ( isset( $old_face[ $ext ] ) ) {
															$old_url = wp_get_attachment_url( $old_face[ $ext ] );
															if ( $old_url === $new_font_faces[ $variant_key ]['url'] ) {
																unset( $current_font_faces[ $old_key ] );
																break 2; // Break out of both loops
															}
														}
													}
												}

												// Update this variant in the current font faces with attachment ID
												$current_font_faces[ $variant_key ] = [
													$font_extension => attachment_url_to_postid( $new_font_faces[ $variant_key ]['url'] )
												];
											}
										}
									}

									// Update the font faces
									$meta_result = update_post_meta( $post_id, BRICKS_DB_CUSTOM_FONT_FACES, $current_font_faces );
								}
							}
							// Process modified variants
							elseif ( $change_type === 'modified' ) {
								// Get the updated font data from the client
								if ( isset( $custom_fonts[ $font_id ] ) && isset( $custom_fonts[ $font_id ]['fontFaces'] ) ) {
									$new_font_faces = $custom_fonts[ $font_id ]['fontFaces'];

									// If fontFaces is a string (CSS), convert it to an array
									if ( is_string( $new_font_faces ) ) {
										// Try to parse as JSON first
										$decoded = json_decode( $new_font_faces, true );
										if ( $decoded ) {
											$new_font_faces = $decoded;
										}
									}

									// For each modified variant, update it in the font faces
									foreach ( $variants as $variant_key ) {
										// Extract weight and style from variant key (e.g., "400normal")
										preg_match( '/(\d+)(.*)/', $variant_key, $matches );
										if ( count( $matches ) >= 3 ) {
											$weight = $matches[1];
											$style  = $matches[2] ? $matches[2] : 'normal';
											// Generate database key format (e.g. '400' for normal, '700italic' for italic)
											$db_key = $weight;
											if ( $style !== 'normal' ) {
												$db_key .= $style;
											}

											// Check if this variant exists in the new font faces
											if ( isset( $new_font_faces[ $variant_key ] ) ) {
												// Find and remove the old variant with same attachment ID
												if ( isset( $new_font_faces[ $variant_key ]['url'] ) ) {
													$new_url           = $new_font_faces[ $variant_key ]['url'];
													$new_attachment_id = attachment_url_to_postid( $new_url );

													// Check if DB format key exists in current font faces
													if ( isset( $current_font_faces[ $db_key ] ) ) {

														// Get old attachment ID to compare - check all extensions
														$old_attachment_id = 0;
														foreach ( [ 'woff2', 'woff', 'ttf' ] as $ext ) {
															if ( isset( $current_font_faces[ $db_key ][ $ext ] ) ) {
																$old_attachment_id = $current_font_faces[ $db_key ][ $ext ];
																break;
															}
														}

														if ( $old_attachment_id != $new_attachment_id ) {
															unset( $current_font_faces[ $db_key ] );
														}
													}

													// Also check the client format key just in case
													if ( isset( $current_font_faces[ $variant_key ] ) ) {
														unset( $current_font_faces[ $variant_key ] );
													}

													// Look for any variants using the same file but with different key
													foreach ( $current_font_faces as $old_key => $old_face ) {
														// Check all possible extensions
														foreach ( [ 'woff2', 'woff', 'ttf' ] as $ext ) {
															if ( isset( $old_face[ $ext ] ) ) {
																if ( $old_face[ $ext ] == $new_attachment_id && $old_key != $db_key && $old_key != $variant_key ) {
																	unset( $current_font_faces[ $old_key ] );
																	break; // Break out of extension loop once found
																}
															}
														}
													}

													// Add/update the variant using the database format key
													$font_extension = $get_font_extension( $new_font_faces[ $variant_key ] );

													$current_font_faces[ $db_key ] = [
														$font_extension => $new_attachment_id
													];
												}
											}
										}
									}

									// Update the font faces
									$meta_result = update_post_meta( $post_id, BRICKS_DB_CUSTOM_FONT_FACES, $current_font_faces );
								}
							}
						}
						// If we have font faces from the client
						elseif ( isset( $custom_fonts[ $font_id ]['fontFaces'] ) ) {
							// Convert from URL/type format to extension/attachment_id format
							if ( is_array( $custom_fonts[ $font_id ]['fontFaces'] ) ) {
								$converted_font_faces = [];
								foreach ( $custom_fonts[ $font_id ]['fontFaces'] as $variant_key => $font_data ) {
									if ( isset( $font_data['url'] ) && $font_data['url'] ) {
										$font_extension                       = $get_font_extension( $font_data );
										$attachment_id                        = attachment_url_to_postid( $font_data['url'] );
										$converted_font_faces[ $variant_key ] = [
											$font_extension => $attachment_id
										];
									}
								}

								if ( ! empty( $converted_font_faces ) ) {
									$meta_result = update_post_meta( $post_id, BRICKS_DB_CUSTOM_FONT_FACES, $converted_font_faces );
								}
							}
						}
					}
				}
			}

			// Regenerate custom font face CSS rules after font changes
			// This ensures fonts are immediately available in frontend/builder
			if ( ! empty( $font_changes ) || ! empty( $custom_fonts ) ) {
				// Clear the static cache to force regeneration
				Custom_Fonts::$fonts           = false;
				Custom_Fonts::$font_face_rules = '';

				// Get all custom fonts (this regenerates the CSS rules)
				$fonts = Custom_Fonts::get_custom_fonts();

				// Update the cached CSS rules option
				if ( Custom_Fonts::$font_face_rules ) {
					update_option( BRICKS_DB_CUSTOM_FONT_FACE_RULES, Custom_Fonts::$font_face_rules );
				} else {
					delete_option( BRICKS_DB_CUSTOM_FONT_FACE_RULES );
				}
			}
		}

		/**
		 * STEP: Components changed: Regenerate external files CSS
		 * NOTE: Reconsider if this ends up causing builder save delay on large sites.
		 *
		 * @since 2.0
		 */
		if ( $bricks_data_changed &&
			is_array( $components ) &&
			! empty( $components ) &&
			Database::get_setting( 'cssLoading' ) === 'file'
		) {
			// Create a CRON job to regenerate CSS files in the background
			Assets_Files::schedule_css_file_regeneration();
		}

		wp_send_json_success( $_POST );
	}

	/**
	 * Save generated template screenshot
	 *
	 * @since 1.10
	 */
	public function save_template_screenshot() {
		// Verify nonce
		self::verify_request( 'bricks-nonce-builder' );

		// Check if the user can upload files
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'User does not have permission to upload files.' );
		}

		$post_id = ! empty( $_POST['screenshotPostId'] ) ? intval( $_POST['screenshotPostId'] ) : 0;

		// Return: No post ID set
		if ( ! $post_id ) {
			wp_send_json_error( 'Error: No screenshotPostId provided!' );
		}

		if ( ! Capabilities::current_user_can_use_builder( $post_id ) ) {
			wp_send_json_error( 'verify_request: User cannot use builder (' . get_current_user_id() . ')' );
		}

		if ( ! Database::get_setting( 'generateTemplateScreenshots' ) ) {
			wp_send_json_error( esc_html__( 'Template screenshot generation is disabled.', 'bricks' ) );
		}

		if ( ! isset( $_FILES['screenshot'] ) || empty( $_FILES['screenshot'] ) ) {
			wp_send_json_error( 'No screenshot file provided' );
		}

		$screenshot_file = $_FILES['screenshot'];

		// Check for upload errors
		if ( $screenshot_file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( 'File upload error: ' . $screenshot_file['error'] );
		}

		// Check file size (limit to 5MB for security purposes)
		$max_size = 5 * 1024 * 1024; // 5MB
		if ( $screenshot_file['size'] > $max_size ) {
			wp_send_json_error( 'File size exceeds the maximum limit.' );
		}

		// Check MIME type using multiple methods
		$finfo         = new \finfo( FILEINFO_MIME_TYPE );
		$mime_type     = $finfo->file( $screenshot_file['tmp_name'] );
		$allowed_types = [ 'image/webp', 'image/png' ];

		if ( ! in_array( $mime_type, $allowed_types, true ) ) {
			wp_send_json_error( 'Invalid file type. Expected WebP or PNG image.' );
		}

		// Double-check with exif_imagetype
		$image_type = exif_imagetype( $screenshot_file['tmp_name'] );
		if ( ! in_array( $image_type, [ IMAGETYPE_WEBP, IMAGETYPE_PNG ] ) ) {
			wp_send_json_error( 'Invalid image type detected.' );
		}

		// Get WordPress upload directory
		$wp_upload_dir = wp_upload_dir();
		$custom_dir    = $wp_upload_dir['basedir'] . '/' . BRICKS_TEMPLATE_SCREENSHOTS_DIR . '/';

		// Ensure the custom directory exists and is writable
		if ( ! file_exists( $custom_dir ) ) {
			if ( ! wp_mkdir_p( $custom_dir ) ) {
				wp_send_json_error( 'Failed to create upload directory.' );
			}
		}

		if ( ! is_writable( $custom_dir ) ) {
			wp_send_json_error( 'Upload directory is not writable.' );
		}

		// Get and then Deleted all existing files in the custom directory starting with 'template-screenshot-$post_id-'
		$existing_files = glob( $custom_dir . "template-screenshot-$post_id-*" );
		foreach ( $existing_files as $file ) {
			if ( file_exists( $file ) && ! unlink( $file ) ) {
				error_log( "Failed to delete old screenshot file: $file" );
			}
		}

		// Define the file path with timestamp to avoid caching issues
		$timestamp = time();
		$extension = ( $mime_type === 'image/webp' ) ? 'webp' : 'png';
		$filename  = "template-screenshot-$post_id-$timestamp.$extension";
		$filepath  = $custom_dir . $filename;

		// Move the uploaded file
		if ( ! move_uploaded_file( $screenshot_file['tmp_name'], $filepath ) ) {
			wp_send_json_error( 'Error saving screenshot' );
		}

		// Verify that the file was actually uploaded and is readable
		if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
			wp_send_json_error( 'Failed to save or read the uploaded file.' );
		}

		// Convert file path to URL
		$fileurl = str_replace( $wp_upload_dir['basedir'], $wp_upload_dir['baseurl'], $filepath );

		// Sanitize the URL before sending it back
		$fileurl = esc_url( $fileurl );

		wp_send_json_success( [ 'file_url' => $fileurl ] );
	}

	/**
	 * Sanitize Bricks postmeta
	 */
	public function sanitize_bricks_postmeta( $meta_value, $meta_key, $object_type ) {
		$post_id = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : get_the_ID();

		// Return: No post ID set
		if ( ! $post_id ) {
			return $meta_value;
		}

		// Return: Is in-builder
		if ( check_ajax_referer( 'bricks-nonce-builder', 'nonce', false ) ) {
			return $meta_value;
		}

		if ( $meta_key === BRICKS_DB_PAGE_CONTENT ) {
			$meta_value = Helpers::security_check_elements_before_save( $meta_value, $post_id, 'content' );
		} elseif ( $meta_key === BRICKS_DB_PAGE_HEADER ) {
			$meta_value = Helpers::security_check_elements_before_save( $meta_value, $post_id, 'header' );
		} elseif ( $meta_key === BRICKS_DB_PAGE_FOOTER ) {
			$meta_value = Helpers::security_check_elements_before_save( $meta_value, $post_id, 'footer' );
		}

		return $meta_value;
	}

	/**
	 * Sanitize Bricks postmeta page settings
	 *
	 * @since 1.9.9
	 */
	public function sanitize_bricks_postmeta_page_settings( $meta_value, $meta_key, $object_type ) {
		$post_id = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : get_the_ID();

		// Return: No post ID set
		if ( ! $post_id ) {
			return $meta_value;
		}

		if ( $meta_key === BRICKS_DB_PAGE_SETTINGS ) {
			// User has no 'unfiltered_html' cap: Remove page settings that could contain <script> tags
			if ( ! current_user_can( 'unfiltered_html' ) && is_array( $meta_value ) ) {
				// Remove script settings
				foreach ( $meta_value as $key => $value ) {
					if ( in_array( $key, [ 'customScriptsHeader', 'customScriptsBodyHeader', 'customScriptsBodyFooter' ] ) ) {
						unset( $meta_value[ $key ] );
					}
				}

				// Use existing script settings (added by user with 'unfiltered_html' cap)
				$existing_page_settings = get_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS, true );
				if ( is_array( $existing_page_settings ) ) {
					foreach ( $existing_page_settings as $key => $value ) {
						if ( in_array( $key, [ 'customScriptsHeader', 'customScriptsBodyHeader', 'customScriptsBodyFooter' ] ) ) {
							$meta_value[ $key ] = $existing_page_settings[ $key ];
						}
					}
				}
			}
		}

		return $meta_value;
	}

	/**
	 * Update postmeta: Prevent user without builder access from updating Bricks postmeta
	 */
	public function update_bricks_postmeta( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		// Return: Not Bricks postmeta
		$is_bricks_postmeta = in_array( $meta_key, [ BRICKS_DB_PAGE_CONTENT, BRICKS_DB_PAGE_HEADER, BRICKS_DB_PAGE_FOOTER ], true );
		if ( $is_bricks_postmeta ) {
			// User doesn't have builder access, but WPML is processing a translation, allow the update (@since 1.11)
			if ( ! Capabilities::current_user_can_use_builder( $object_id ) && \Bricks\Integrations\Wpml\Wpml::is_processing_wpml_translation() ) {
				// Reset the processing flag after our check
				\Bricks\Integrations\Wpml\Wpml::end_processing_wpml_translation();

				return $check;
			}

			// If the user does not have builder access and WPML is not processing a translation, block the update
			if ( ! Capabilities::current_user_can_use_builder( $object_id ) ) {
				return false;
			}
		}

		// STEP: Handle password protection template population
		if ( $meta_key === BRICKS_DB_TEMPLATE_TYPE && $meta_value === 'password_protection' && get_post_type( $object_id ) === BRICKS_DB_TEMPLATE_SLUG ) {
			// Check if template type was just set to password_protection
			$template_type = get_post_meta( $object_id, BRICKS_DB_TEMPLATE_TYPE, true );

			if ( $template_type === 'password_protection' && Capabilities::current_user_can_use_builder( $object_id ) ) {
				// Get current content
				$current_content = get_post_meta( $object_id, BRICKS_DB_PAGE_CONTENT, true );

				// If content is empty, populate the template
				if ( empty( $current_content ) ) {
					Password_Protection::populate_template( $object_id );
				}
			}
		}

		return $check;
	}

	/**
	 * Create autosave
	 *
	 * @since 1.0
	 */
	public static function create_autosave() {
		self::verify_request( 'bricks-nonce-builder' );

		$area     = ! empty( $_POST['area'] ) ? sanitize_text_field( $_POST['area'] ) : false;
		$post_id  = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
		$elements = ! empty( $_POST['elements'] ) ? self::decode( $_POST['elements'] ) : false;

		if ( ! $area || ! $post_id || ! $elements ) {
			return;
		}

		$post_type = get_post_type( $post_id );

		// 1/2: Create autosave
		$autosave_id = wp_create_post_autosave(
			[
				'post_ID'       => $post_id,
				'post_type'     => $post_type,
				'post_excerpt'  => '<!-- Built With Bricks -->', // Forces $autosave_is_different to 'true'
				'post_modified' => current_time( 'mysql' ),
			]
		);

		if ( is_wp_error( $autosave_id ) ) {
			wp_send_json_error( new \WP_Error( 'autosave_error', $autosave_id ) );
		}

		// 2/2: Save elements in db post meta with autosave post ID
		$elements = self::decode( $_POST['elements'] );

		$elements = Helpers::security_check_elements_before_save( $elements, $post_id, 'content' );

		if ( ! is_array( $elements ) ) {
			wp_send_json_error( new \WP_Error( 'element_error', 'No elements' ) );
		}

		switch ( $area ) {
			case 'header':
				update_metadata( 'post', $autosave_id, BRICKS_DB_PAGE_HEADER, $elements );
				break;

			case 'content':
				update_metadata( 'post', $autosave_id, BRICKS_DB_PAGE_CONTENT, $elements );
				break;

			case 'footer':
				update_metadata( 'post', $autosave_id, BRICKS_DB_PAGE_FOOTER, $elements );
				break;
		}

		// STEP: Generate post CSS file on autosave
		if ( Database::get_setting( 'cssLoading' ) === 'file' ) {
			Assets_Files::generate_post_css_file( $post_id, $area, $elements );
		}

		wp_send_json_success( [ 'autosave_id' => $autosave_id ] );
	}

	/**
	 * Get bulider URL
	 *
	 * To reload builder with newly saved postName/postTitle (page settigns)
	 *
	 * @since 1.0
	 */
	public function get_builder_url() {
		self::verify_request( 'bricks-nonce-builder' );

		wp_send_json_success( [ 'url' => Helpers::get_builder_edit_link( ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0 ) ] );
	}

	/**
	 * Publish post
	 *
	 * @since 1.0
	 */
	public function publish_post() {
		self::verify_request( 'bricks-nonce-builder' );

		// Return: Current user can not publish posts
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( 'Error: You do not have permission to publish posts' );
		}

		$post_id = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( 'Error: No postId provided.' );
		}

		$response = wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			]
		);

		wp_send_json_success( $response );
	}

	/**
	 * Get image metadata
	 *
	 * @since 1.0
	 */
	public function get_image_metadata() {
		self::verify_request( 'bricks-nonce-builder' );

		$image_id   = ! empty( $_POST['imageId'] ) ? intval( $_POST['imageId'] ) : 0;
		$image_size = ! empty( $_POST['imageSize'] ) ? sanitize_text_field( $_POST['imageSize'] ) : '';

		if ( ! $image_id ) {
			wp_send_json_error( 'Error: No imageId provided.' );
		}

		$get_attachment_metadata = wp_get_attachment_metadata( $image_id );

		// SVG returns empty metadata
		if ( ! $get_attachment_metadata ) {
			wp_send_json_success();
		}

		$response = [
			'filename' => isset( $get_attachment_metadata['original_image'] ) ? $get_attachment_metadata['original_image'] : '',
			'full'     => [
				'width'  => isset( $get_attachment_metadata['width'] ) ? $get_attachment_metadata['width'] : '',
				'height' => isset( $get_attachment_metadata['height'] ) ? $get_attachment_metadata['height'] : '',
			],
			'sizes'    => isset( $get_attachment_metadata['sizes'] ) ? $get_attachment_metadata['sizes'] : [],
			'src'      => wp_get_attachment_image_src( $image_id, $image_size ),
		];

		wp_send_json_success( $response );
	}

	/**
	 * Get Image Id from a custom field
	 *
	 * @since 1.0
	 */
	public function get_image_from_custom_field() {
		self::verify_request( 'bricks-nonce-builder' );

		$image_id = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
		$meta_key = ! empty( $_POST['metaKey'] ) ? sanitize_text_field( $_POST['metaKey'] ) : '';
		$size     = ! empty( $_POST['size'] ) ? sanitize_text_field( $_POST['size'] ) : BRICKS_DEFAULT_IMAGE_SIZE;

		if ( ! $image_id ) {
			wp_send_json_error( 'Error: No postId provided' );
		}

		if ( ! $meta_key ) {
			wp_send_json_error( 'Error: No postmeta key provided' );
		}

		// Get images from custom field
		$images = Integrations\Dynamic_Data\Providers::render_tag( $meta_key, $image_id, 'image', [ 'size' => $size ] );

		if ( empty( $images ) ) {
			wp_send_json_error( 'Error: Image not found' );
		}

		if ( is_numeric( $images[0] ) ) {
			$get_attachment_metadata = wp_get_attachment_metadata( $images[0] );

			if ( empty( $get_attachment_metadata ) ) {
				wp_send_json_error( 'Error: Image not found' );
			}

			$output = [
				'filename' => isset( $get_attachment_metadata['original_image'] ) ? $get_attachment_metadata['original_image'] : '',
				'id'       => $images[0],
				'size'     => $size,
				'url'      => wp_get_attachment_image_url( $images[0], $size ),
			];
		}

		// Might be a Gravatar image
		else {
			$output = [
				'url' => $images[0]
			];
		}

		wp_send_json_success( $output );
	}

	/**
	 * Download image to WordPress media libary (Unsplash)
	 *
	 * @since 1.0
	 */
	public function download_image() {
		self::verify_request( 'bricks-nonce-builder' );

		// http://www.codingduniya.com/2016/07/generate-featured-image-for-post-using.html
		$file_array = [];

		$tmp = download_url( ! empty( $_POST['download_url'] ) ? esc_url( $_POST['download_url'] ) : '' );

		$file_array['tmp_name'] = $tmp;

		// Manually add file extension as Unsplash download URL doesn't provide file extension
		$file_array['name'] = ! empty( $_POST['file_name'] ) ? $_POST['file_name'] . '.jpg' : '';

		// Check for download errors
		if ( is_wp_error( $tmp ) ) {
			wp_send_json_error( $tmp );
		}

		$id = media_handle_sideload( $file_array, 0 );

		// If error storing permanently, unlink
		if ( is_wp_error( $id ) ) {
			if ( isset( $file_array['tmp_name'] ) ) {
				@unlink( $file_array['tmp_name'] );
			}
		}

		wp_send_json_success( $id );
	}

	/**
	 * Import paste element images (cross-site)
	 *
	 * @since 1.12.2
	 */
	public function import_images() {
		self::verify_request( 'bricks-nonce-builder' );

		$target_images  = $_POST['images'] ?? [];
		$handled_images = [];

		foreach ( $target_images as $target_image ) {
			$element_id   = $target_image['elementId'] ?? false; // Will replace the new image settings to this element
			$element_name = $target_image['elementName'] ?? false;
			$setting_key  = $target_image['settingKey'] ?? false; // Will replace the new image settings to this setting key
			$settings     = $target_image['settings'] ?? []; // Copied image settings

			// STEP: Resue the logic in Templates::import_images()
			if ( count( $settings ) ) {
				// Handle image-gallery element
				if ( $element_name === 'image-gallery' ) {
					// The images are stored in the 'images' setting key
					$images = $settings['images'] ?? [];
					$size   = $settings['size'] ?? 'full';

					if ( ! count( $images ) ) {
						continue;
					}

					$new_images = [];

					foreach ( $images as $image ) {
						// import_image requires size to be set
						$image['size'] = $size;
						$new_image     = Templates::import_image( $image, true );

						if ( ! $new_image || ( is_array( $new_image ) && isset( $new_image['error'] ) ) ) {
							continue;
						}

						// Remove the 'size', 'filename' key (Not needed)
						unset( $new_image['size'] );
						unset( $new_image['filename'] );
						$new_images[] = $new_image;
					}

					$handled_images[] = [
						'elementId'   => $element_id,
						'elementName' => $element_name,
						'settingKey'  => $setting_key,
						'settings'    => [
							'images' => $new_images, // follow image-gallery format
							'size'   => $size, // follow image-gallery format
						]
					];
				}

				// Handle Image and SVG element (single image)
				else {
					$new_image = Templates::import_image( $settings, true );

					if ( ! $new_image || ( is_array( $new_image ) && isset( $new_image['error'] ) ) ) {
						continue;
					}

					$handled_images[] = [
						'elementId'   => $element_id,
						'elementName' => $element_name,
						'settingKey'  => $setting_key,
						'settings'    => $new_image,
					];
				}

			}
		}

		wp_send_json_success( $handled_images );
	}

	/**
	 * Parse content through dynamic data logic
	 *
	 * @since 1.5.1
	 */
	public function get_dynamic_data_preview_content() {
		self::verify_request( 'bricks-nonce-builder' );

		$post_id = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : 0;
		$content = ! empty( $_POST['content'] ) ? wp_kses_post( $_POST['content'] ) : '';
		$context = ! empty( $_POST['context'] ) ? sanitize_text_field( $_POST['context'] ) : 'text';

		if ( ! $post_id ) {
			wp_send_json_error( 'Error: No post ID' );
		}

		if ( ! $content ) {
			wp_send_json_error( 'Error: No content' );
		}

		// Use stripslashes to unescape img URLs, etc. (@since 1.7)
		if ( is_string( $content ) ) {
			$content = stripslashes( $content );
		}

		// STEP: Set up post data so WP core function like get_the_ID() work inside custom PHP functions called via DD 'echo:'
		global $post;

		$post = get_post( $post_id );

		setup_postdata( $post );

		// Get content from custom field
		if ( is_array( $content ) ) {
			// Array format used to parse colors in the builder (@since 1.5.1)
			foreach ( $content as $key => $data ) {
				$content[ $key ]['value'] = bricks_render_dynamic_data( $data['value'], $post_id, $context );
			}
		} else {
			// Preview composed links e.g. "https://my-domain.com/?user={wp_user_id}" (@since 1.5.4)
			if ( $context == 'link' && ( strpos( $content, '{' ) !== 0 || substr_count( $content, '}' ) > 1 ) ) {
				$context = 'text';
			}

			$content = bricks_render_dynamic_data( $content, $post_id, $context );
		}

		wp_reset_postdata();

		// If controlName is for video background, we handle it differently based on full url or video ID (@since 1.12.3)
		$control_name = ! empty( $_POST['controlName'] ) ? sanitize_text_field( $_POST['controlName'] ) : false;
		if ( $control_name === 'backgroundVideoUrl' ) {

			// Only escape URL if it's valid one. If we have a video ID, we don't escape it (so that we don't add http:// as prefix to video ID).
			if ( filter_var( trim( $content ), FILTER_VALIDATE_URL ) ) {
				$content = esc_url( $content );
			}
		}

		elseif ( 'link' === $context ) {
			$content = esc_url( $content );
		}

		// When output a code field, extract the content
		elseif ( is_string( $content ) && strpos( $content, '<pre' ) === 0 ) {
			preg_match( '#<\s*?code\b[^>]*>(.*?)</code\b[^>]*>#s', $content, $matches );
			$content = isset( $matches[1] ) ? $matches[1] : $content;

			// esc_html to escape code tags
			$content = esc_html( $content );
		}

		/**
		 * Run additional checks for non-basic text elements like removing extra <p> tags, etc.
		 *
		 * ContentEditable.js provides the element name.
		 *
		 * @since 1.7
		 */
		$element_name = ! empty( $_POST['elementName'] ) ? sanitize_text_field( $_POST['elementName'] ) : false;

		if ( $element_name && $element_name !== 'text-basic' ) {
			$content = Helpers::parse_editor_content( $content );
		}

		// NOTE: We are not escaping text content since it could contain formatting tags like <strong> (@since 1.5.1 - preview dynamic data)
		wp_send_json_success( [ 'content' => $content ] );
	}

	/**
	 * Get latest remote templates data in builder (PopupTemplates.vue)
	 *
	 * @since 1.0
	 */
	public function get_remote_templates_data() {
		self::verify_request( 'bricks-nonce-builder' );

		$remote_templates = Templates::get_remote_templates_data();

		wp_send_json_success( $remote_templates );
	}

	/**
	 * Builder: Get "My templates" from db
	 *
	 * @since 1.4
	 */
	public function get_my_templates_data() {
		self::verify_request( 'bricks-nonce-builder' );

		wp_send_json_success(
			Templates::get_templates(
				[
					'post_status'           => 'any',
					'lang'                  => '', // Get all templates in builder for Polylang (@since 1.9.5)
					'remove_code_signature' => true, // Don't remove signature from local templates (@since 1.9.7)
				]
			)
		);
	}

	/**
	 * Get current user
	 *
	 * Verify logged-in user when builder is loaded on the frontend.
	 *
	 * @since 1.5
	 */
	public function get_current_user_id() {
		self::verify_request( 'bricks-nonce-builder' );

		wp_send_json_success( [ 'user_id' => get_current_user_id() ] );
	}


	/**
	 * Delete bricks query loop random seed transient
	 *
	 * @since 1.7.1
	 */
	public function query_loop_delete_random_seed_transient() {
		self::verify_request( 'bricks-nonce-builder' );

		$element_id = ! empty( $_POST['elementId'] ) ? sanitize_text_field( $_POST['elementId'] ) : false;

		if ( ! $element_id ) {
			wp_send_json_error( 'Error: No element ID' );
		}

		// @see Bricks\Query->set_bricks_query_loop_random_order_seed()
		$transient_name = "bricks_query_loop_random_seed_$element_id";

		delete_transient( $transient_name );

		wp_send_json_success();
	}

	/**
	 * Get custom shape divider (SVG) from attachment ID
	 *
	 * Only allow to select SVG files from the media library for security reasons.
	 *
	 * @since 1.8.6
	 */
	public function get_custom_shape_divider() {
		self::verify_request( 'bricks-nonce-builder' );

		$svg_path = ! empty( $_POST['id'] ) ? get_attached_file( intval( $_POST['id'] ) ) : false;
		$svg      = $svg_path ? Helpers::file_get_contents( $svg_path ) : false;

		wp_send_json_success( $svg );
	}

	public function restore_global_class() {
		self::verify_request( 'bricks-nonce-builder' );

		if ( ! current_user_can( 'edit_posts' ) && ! Builder_Permissions::user_has_permission( 'access_class_manager' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$class_id = $_POST['class_id'] ?? null;

		if ( ! $class_id ) {
			wp_send_json_error( 'No class ID provided' );
		}

		$global_classes = get_option( BRICKS_DB_GLOBAL_CLASSES, [] );
		$trash          = get_option( BRICKS_DB_GLOBAL_CLASSES_TRASH, [] );

		foreach ( $trash as $index => $class ) {
			if ( $class['id'] === $class_id ) {
				// Check for name conflicts
				$name = $class['name'];
				foreach ( $global_classes as $existing_class ) {
					if ( $existing_class['name'] === $name ) {
						$name = $name . '-restored';
						break;
					}
				}
				$class['name'] = $name;

				// Remove from trash and add to global classes
				unset( $trash[ $index ] );
				$global_classes[] = $class;
				break;
			}
		}

		update_option( BRICKS_DB_GLOBAL_CLASSES, $global_classes );
		update_option( BRICKS_DB_GLOBAL_CLASSES_TRASH, array_values( $trash ) );

		wp_send_json_success();
	}

	/**
	 * Delete global classes permanently
	 *
	 * @since 1.11
	 */
	public function delete_global_classes_permanently() {
		// Verify nonce and user capabilities
		self::verify_request( 'bricks-nonce-builder' );

		if ( ! Builder_Permissions::user_has_permission( 'access_class_manager' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		// Get class IDs to delete
		$class_ids = $_POST['classIds'] ?? [];

		// If $class_ids is a string, try to decode it
		if ( is_string( $class_ids ) ) {
			$class_ids = self::decode( $class_ids, false );
		}

		if ( empty( $class_ids ) || ! is_array( $class_ids ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'No classes specified for deletion', 'bricks' ) ] );
		}

		// Get current trashed classes and active global classes
		$trashed_classes = get_option( BRICKS_DB_GLOBAL_CLASSES_TRASH, [] );
		$active_classes  = get_option( BRICKS_DB_GLOBAL_CLASSES, [] );

		// Initialize array to store deleted classes
		$deleted_classes = [];

		// Create associative arrays for faster lookup
		$trashed_classes_assoc = array_column( $trashed_classes, null, 'id' );
		$active_classes_assoc  = array_column( $active_classes, null, 'id' );

		foreach ( $class_ids as $class_id ) {
			if ( isset( $trashed_classes_assoc[ $class_id ] ) ) {
				// Store the deleted class info from trash
				$deleted_classes[] = $trashed_classes_assoc[ $class_id ];
				// Remove the class from the trashed classes array
				unset( $trashed_classes_assoc[ $class_id ] );
			} elseif ( isset( $active_classes_assoc[ $class_id ] ) ) {
				// Store the deleted class info from active classes
				$deleted_classes[] = $active_classes_assoc[ $class_id ];
				// Remove the class from the active classes array
				unset( $active_classes_assoc[ $class_id ] );
			}
		}

		// Convert associative arrays back to indexed arrays
		$trashed_classes = array_values( $trashed_classes_assoc );
		$active_classes  = array_values( $active_classes_assoc );

		// Update both options in the database
		$updated_trash  = update_option( BRICKS_DB_GLOBAL_CLASSES_TRASH, $trashed_classes );
		$updated_active = update_option( BRICKS_DB_GLOBAL_CLASSES, $active_classes );

		if ( $updated_trash || $updated_active ) {
			// Singular/plural message based on the number of classes deleted
			$deleted_count = count( $deleted_classes );
			// translators: %d: number of classes deleted
			$message = _n(
				'%d class permanently deleted',
				'%d classes permanently deleted',
				$deleted_count,
				'bricks'
			);
			$message = sprintf( $message, $deleted_count );

			wp_send_json_success(
				[
					'message'        => $message,
					'deletedClasses' => $deleted_classes
				]
			);
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to delete classes', 'bricks' ) ] );
		}
	}

	/**
	 * Used in the builder only
	 *
	 * - Force Query is_looping to true
	 * - Force Query loop_object
	 * - If the loop_object is WP_Post, setup the post data and save in global $post (Mimic the loop)
	 * - Caution: Never restore global $post in this function. Don't use this if unsure.
	 *
	 * @since 1.12.2
	 */
	public static function simulate_bricks_query( $loop_settings ) {
		$query = new Query( $loop_settings );

		if ( ! empty( $query->count ) ) {
			$query->is_looping = true;

			// NOTE: Use array_shift because not all the results are sequential arrays (e.g. JetEngine)
			$query->loop_object = $query->object_type == 'post' ? $query->query_result->posts[0] : array_shift( $query->query_result );

			// Set global $post for the loop object
			if ( is_a( $query->loop_object, 'WP_Post' ) ) {
				global $post;
				$post = $query->loop_object;
				setup_postdata( $post );
			}
		}
	}

	/**
	 * Clean up orphaned elements across site
	 *
	 * @since 2.0
	 */
	public function cleanup_orphaned_elements() {
		self::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		// Find orphaned elements across the site
		$orphaned_data = Helpers::find_orphaned_elements_across_site();

		if ( empty( $orphaned_data['orphaned_by_post_id'] ) ) {
			wp_send_json_success(
				[
					'message'       => esc_html__( 'No orphaned elements found.', 'bricks' ),
					'total_cleaned' => 0,
					'posts_cleaned' => 0,
				]
			);
		}

		// Clean up orphaned elements
		$cleanup_result = Helpers::cleanup_orphaned_elements_across_site( $orphaned_data );

		if ( $cleanup_result['success'] ) {
			$message = sprintf(
				esc_html__( 'Successfully removed %1$d orphaned elements across %2$d posts.', 'bricks' ),
				$cleanup_result['total_cleaned'],
				$cleanup_result['posts_cleaned']
			);

			wp_send_json_success(
				[
					'message'       => $message,
					'total_cleaned' => $cleanup_result['total_cleaned'],
					'posts_cleaned' => $cleanup_result['posts_cleaned'],
				]
			);
		} else {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to clean up orphaned elements.', 'bricks' ) ] );
		}
	}

	/**
	 * Scan for orphaned elements across site
	 *
	 * @since 2.0
	 */
	public function scan_orphaned_elements() {
		self::verify_nonce( 'bricks-nonce-admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Not allowed', 'bricks' ) ] );
		}

		// Scan for orphaned elements
		$scan_result = Helpers::find_orphaned_elements_across_site();

		// translators: %1$d: Number of orphaned elements found, %2$d: Number of posts scanned
		$message = sprintf(
			esc_html__( 'Scan complete: %1$d orphaned elements found across %2$d posts.', 'bricks' ),
			$scan_result['total_orphans'],
			$scan_result['total_posts']
		);

		wp_send_json_success(
			[
				'message'             => $message,
				'orphaned_by_post_id' => $scan_result['orphaned_by_post_id'],
				'total_orphans'       => $scan_result['total_orphans'],
				'total_posts'         => $scan_result['total_posts'],
			]
		);
	}

	/**
	 * Query API
	 *
	 * @since 2.1
	 */
	public function query_api() {
		self::verify_request( 'bricks-nonce-builder' );

		$settings   = $_POST['settings'] ?? [];
		$element_id = ! empty( $_POST['elementId'] ) ? sanitize_text_field( $_POST['elementId'] ) : false;
		$post_id    = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : get_the_ID();
		$response   = Query_API::make_request( $settings, $element_id, $post_id, true ); // Always clear cache

		if ( isset( $response['error'] ) ) {
			wp_send_json_error(
				[
					'message'    => $response['error'],
					'status'     => $response['status'] ?? 500,
					'headers'    => $response['headers'] ?? null,
					'last_fetch' => $response['last_fetch'] ?? time(),
				]
			);
		}

		// Return both full response and extracted data
		wp_send_json_success(
			[
				'full_response'  => $response['full_response'] ?? null,   // Full API response
				'extracted_data' => $response['extracted_data'] ?? null, // Extracted data
				'response_path'  => $response['response_path'] ?? null,   // Path used
				'status'         => $response['status'] ?? 200,
				'headers'        => $response['headers'] ?? null,
				'last_fetch'     => $response['last_fetch'] ?? time(),
			]
		);

	}
}
