<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Components {
	public function __construct() {
		add_action( 'wp_ajax_bricks_upgrade_components', [ $this, 'upgrade_components' ] );
		add_action( 'wp_ajax_bricks_get_component_instances', [ $this, 'get_component_instances' ] );
	}

	/**
	 * Upgrade components to latest data structure
	 *
	 * @since 1.12
	 */
	public static function upgrade_components( $components, $is_import = true ) {
		// Component in-builder (PanelElements.vue)
		if ( bricks_is_ajax_call() ) {
			Ajax::verify_request( 'bricks-nonce-builder' );
			$components = $_POST['components'] ?? [];
		}

		foreach ( $components as $index => $component ) {
			/**
			 * STEP: Convert 1.12-beta components to 1.12 data structure
			 *
			 * Move root component element (incl. name, settings, children, label) from component object to elements array.
			 */
			if ( isset( $component['name'] ) ) {
				$component_root_element = [
					'id'       => $component['id'],
					'name'     => $component['name'],
					'settings' => $component['settings'] ?? [],
					'children' => $component['children'] ?? [],
					'parent'   => 0,
				];

				// Move component label to root element
				if ( ! empty( $component['label'] ) ) {
					$component_root_element['label'] = $component['label'];
				}

				// Add root element as first item to elements array
				if ( isset( $components[ $index ]['elements'] ) && is_array( $components[ $index ]['elements'] ) ) {
					array_unshift( $components[ $index ]['elements'], $component_root_element );
				} else {
					$components[ $index ]['elements'] = [ $component_root_element ];
				}

				// Remove root level properties
				unset( $components[ $index ]['name'] );
				unset( $components[ $index ]['settings'] );
				unset( $components[ $index ]['children'] );
			}

			// Remove backslashes from component settings if this is import action (i.e. Code element; @since 2.0)
			$components[ $index ] = $is_import ? stripslashes_deep( $components[ $index ] ) : $components[ $index ];
		}

		// Return: Components in-builder import (PanelElements.vue)
		if ( bricks_is_ajax_call() ) {
			wp_send_json_success(
				[
					'newComponents' => $components,
				]
			);
		}

		// Return upgraded components
		return $components;
	}

	public function get_component_instances() {
		Ajax::verify_request( 'bricks-nonce-builder' );

		// Get component IDS from real-time builder data to account for deleted components, etc.
		$component_ids   = $_POST['componentIds'] ?? [];
		$current_post_id = $_POST['postId'] ?? 0;

		// Loop over all Bricks-enabled post types to get elements with 'cid' key
		$instances = [];

		// Get IDs of all Bricks posts & templates
		$bricks_post_ids = Helpers::get_all_bricks_post_ids();
		$template_ids    = Templates::get_all_template_ids();
		$post_ids        = array_merge( $bricks_post_ids, $template_ids );

		foreach ( $post_ids as $post_id ) {
			// Skip the current post (get instances in builder from dynamicElements
			if ( $post_id == $current_post_id ) {
				continue;
			}

			$type = 'content';

			// Get template type (header, footer, etc.) for templates
			if ( get_post_type( $post_id ) === BRICKS_DB_TEMPLATE_SLUG ) {
				$type = Templates::get_template_type( $post_id );
			}

			$bricks_data = Database::get_data( $post_id, $type );

			// Stringify the data to search for all 'cid' appearances
			$bricks_data_json = json_encode( $bricks_data );

			// Find all 'cid' keys in the data
			preg_match_all( '/"cid":"(.*?)"/', $bricks_data_json, $matches );

			// Loop over all matches and add them to the $instances array
			foreach ( $matches[1] as $cid ) {
				// Skip if the component ID is not in the list of component IDs
				if ( ! in_array( $cid, $component_ids ) ) {
					continue;
				}

				if ( empty( $instances[ $cid ][ $post_id ] ) ) {
					$post_title       = get_the_title( $post_id );
					$post_type        = get_post_type( $post_id );
					$post_type_object = get_post_type_object( $post_type );

					$instances[ $cid ][ $post_id ] = [
						'count'      => 1,
						'post_title' => $post_title,
						'post_type'  => $post_type_object->labels->singular_name ?? $post_type,
						'permalink'  => Helpers::get_builder_edit_link( $post_id ),
					];
				} else {
					$instances[ $cid ][ $post_id ]['count']++;
				}
			}

		}

		wp_send_json_success( $instances );
	}
}
