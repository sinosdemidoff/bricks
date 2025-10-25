<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builder Permissions
 *
 * Manages the available permissions for builder access control
 */
class Builder_Permissions {
	const DEFAULT_CAPABILITIES = [
		'bricks_full_access'  => 'Full access',
		'bricks_edit_content' => 'Edit content',
		'bricks_no_access'    => 'No access',
	];

	private static $sections = null;

	/**
	 * Get all permission sections with their corresponding permissions
	 */
	public static function get_sections( $load_elements = false ) {
		// Load elements on Bricks > Settings page to get element 'label'
		if ( $load_elements ) {
			Elements::load_elements();
		}

		// Return sections if available
		if ( ! empty( self::$sections['edit_elements']['permissions'] ) ) {
			return self::$sections;
		}

		$permissions = [
			'access_builder'  => [
				'label'       => esc_html__( 'Post types', 'bricks' ),
				'description' => esc_html__( 'Select which post types can be edited using Bricks.', 'bricks' ),
				'permissions' => self::get_post_type_permissions(),
			],
			'general'         => [
				'label'       => esc_html__( 'General', 'bricks' ),
				'permissions' => [
					'access_breakpoints_manager' => esc_html__( 'Access breakpoints manager', 'bricks' ),
					'access_page_settings'       => esc_html__( 'Access page settings', 'bricks' ),
					'access_template_settings'   => esc_html__( 'Access template settings', 'bricks' ),
					'access_revisions'           => esc_html__( 'Access revisions', 'bricks' ),
					'delete_revisions'           => esc_html__( 'Delete revisions', 'bricks' ),
					'access_font_manager'        => esc_html__( 'Access font manager', 'bricks' ),
					'access_icon_manager'        => esc_html__( 'Access icon manager', 'bricks' ),
				]
			],
			'templates'       => [
				'label'       => esc_html__( 'Templates', 'bricks' ),
				'permissions' => [
					'create_templates'        => esc_html__( 'Create templates', 'bricks' ),
					'edit_templates'          => esc_html__( 'Edit templates', 'bricks' ),
					'delete_templates'        => esc_html__( 'Delete templates', 'bricks' ),
					'insert_templates'        => esc_html__( 'Insert templates', 'bricks' ),
					'access_remote_templates' => esc_html__( 'Access remote templates', 'bricks' ),
					'import_export_templates' => esc_html__( 'Import/export templates', 'bricks' ),
				]
			],
			'global_styles'   => [
				'label'       => esc_html__( 'Global styles & settings', 'bricks' ),
				'permissions' => [
					'access_theme_styles'              => esc_html__( 'Access theme styles', 'bricks' ),
					'edit_color_palettes'              => esc_html__( 'Access color palettes', 'bricks' ),
					'access_variable_manager'          => esc_html__( 'Access variable manager', 'bricks' ),
					'access_query_manager'             => esc_html__( 'Access query manager', 'bricks' ),
					'access_class_manager'             => esc_html__( 'Access class manager', 'bricks' ),
					'create_global_classes'            => esc_html__( 'Create global classes', 'bricks' ),
					'edit_global_classes'              => esc_html__( 'Edit global classes', 'bricks' ),
					'delete_global_classes'            => esc_html__( 'Delete global classes', 'bricks' ),
					'assign_unassign_global_classes'   => esc_html__( 'Assign/unassign global classes', 'bricks' ),
					'lock_unlock_global_classes'       => esc_html__( 'Lock/unlock global classes', 'bricks' ),
					'copy_paste_global_classes_styles' => esc_html__( 'Copy/paste global class styles', 'bricks' ),
					'access_pseudo_selectors'          => esc_html__( 'Access pseudos & selectors', 'bricks' ),
				]
			],
			'components'      => [
				'label'       => esc_html__( 'Components', 'bricks' ),
				'permissions' => [
					'insert_components'        => esc_html__( 'Insert components', 'bricks' ),
					'set_component_props'      => esc_html__( 'Edit properties', 'bricks' ) . ' (' . esc_html__( 'Instance', 'bricks' ) . ')',
					'edit_components'          => esc_html__( 'Edit components', 'bricks' ),
					'create_components'        => esc_html__( 'Create components', 'bricks' ),
					'delete_components'        => esc_html__( 'Delete components', 'bricks' ),
					'import_export_components' => esc_html__( 'Import/export components', 'bricks' ),
				]
			],
			'element_editing' => [
				'label'       => esc_html__( 'Element editing & styling', 'bricks' ),
				'permissions' => [
					'access_element_content'          => esc_html__( 'Access content (HTML) settings', 'bricks' ),
					'access_element_styles'           => esc_html__( 'Access style (CSS) settings', 'bricks' ),
					'access_query_loop_builder'       => esc_html__( 'Access query loop builder', 'bricks' ),
					'access_element_hide'             => esc_html__( 'Access element hide', 'bricks' ),
					'access_element_conditions'       => esc_html__( 'Access element conditions', 'bricks' ),
					'access_element_interactions'     => esc_html__( 'Access element interactions', 'bricks' ),
					'duplicate_elements'              => esc_html__( 'Duplicate elements', 'bricks' ),
					'delete_elements'                 => esc_html__( 'Delete elements', 'bricks' ),
					'move_elements'                   => esc_html__( 'Move elements', 'bricks' ),
					'copy_paste_elements'             => esc_html__( 'Copy/paste elements', 'bricks' ),
					'copy_paste_element_styles'       => esc_html__( 'Copy/paste element styles', 'bricks' ),
					'copy_paste_element_conditions'   => esc_html__( 'Copy/paste element conditions', 'bricks' ),
					'copy_paste_element_interactions' => esc_html__( 'Copy/paste element interactions', 'bricks' ),
					'copy_paste_element_attributes'   => esc_html__( 'Copy/paste element attributes', 'bricks' ),
					'pin_unpin_elements'              => esc_html__( 'Pin/unpin elements', 'bricks' ),
				]
			],
			'edit_elements'   => [
				'label'       => esc_html__( 'Edit elements', 'bricks' ),
				'description' =>
				// translators: %1$s and %2$s are permission names
				sprintf(
					esc_html__( 'Requires enabling of "%1$s" and/or "%2$s" permission.', 'bricks' ),
					esc_html__( 'Access content (HTML) settings', 'bricks' ),
					esc_html__( 'Access style (CSS) settings', 'bricks' )
				),
				'permissions' => self::get_edit_element_permissions(),
			],
			'add_elements'    => [
				'label'       => esc_html__( 'Add elements', 'bricks' ),
				'permissions' => self::get_element_permissions(),
			],
		];

		// Add manage global elements permission (if global elements exist)
		if ( ! empty( get_option( BRICKS_DB_GLOBAL_ELEMENTS, [] ) ) ) {
			$permissions['general']['permissions']['manage_global_elements'] = esc_html__( 'Manage global elements', 'bricks' );
		}

		// Store the result for future use
		self::$sections = $permissions;

		return $permissions;
	}

	/**
	 * Get post type permissions
	 */
	private static function get_post_type_permissions() {
		$permissions = [];

		$post_types = Helpers::get_supported_post_types();

		foreach ( $post_types as $post_type => $post_type_label ) {
			$permissions[ "access_builder_$post_type" ] = $post_type_label;
		}

		return $permissions;
	}

	/**
	 * Get element permissions based on registered elements
	 */
	private static function get_element_permissions() {
		$permissions = [];

		// Get all registered elements
		$all_elements = Elements::$elements;

		// Add permission for each element
		foreach ( $all_elements as $element ) {
			$permissions[ "add_element_{$element['name']}" ] = ! empty( $element['label'] ) ? $element['label'] : $element['name'];
		}

		return $permissions;
	}

	/**
	 * Get edit element permissions based on registered elements
	 */
	private static function get_edit_element_permissions() {
		$permissions = [];

		// Get all registered elements
		$all_elements = Elements::$elements;

		// Add permission for each element
		foreach ( $all_elements as $element ) {
			$permissions[ "edit_element_{$element['name']}" ] = ! empty( $element['label'] ) ? $element['label'] : $element['name'];
		}

		return $permissions;
	}

	/**
	 * Get all available permissions as a flat array
	 */
	public static function get_all_permissions() {
		// If the user is not logged in, return empty array
		if ( ! is_user_logged_in() ) {
			return [];
		}

		$permissions = [];
		foreach ( self::get_sections() as $section ) {
			$permissions = array_merge( $permissions, $section['permissions'] );
		}
		return $permissions;
	}

	/**
	 * Get default permissions for built-in capabilities
	 *
	 * @param string $capability The capability to get permissions for.
	 * @return array Array of permissions for the capability.
	 */
	public static function get_default_capability_permissions( $capability ) {
		switch ( $capability ) {
			case Capabilities::FULL_ACCESS:
				// Full access gets all available permissions
				return [
					'label'       => self::DEFAULT_CAPABILITIES[ $capability ],
					'description' => '',
					'permissions' => array_keys( self::get_all_permissions() )
				];

			case Capabilities::EDIT_CONTENT:
				// Edit content gets limited permissions
				$edit_elements_permissions = self::get_edit_element_permissions(); // Can edit all elements
				return [
					'label'       => self::DEFAULT_CAPABILITIES[ $capability ],
					'description' => '',
					'permissions' => array_merge(
						[
							'access_revisions',
							'set_component_props',
							'access_element_content',
						],
						array_keys( $edit_elements_permissions ),
						array_keys( self::get_post_type_permissions() ),
					)
				];

			case Capabilities::NO_ACCESS:
				// No access gets no permissions
				return [
					'label'       => self::DEFAULT_CAPABILITIES[ $capability ],
					'description' => '',
					'permissions' => []
				];

			default:
				return [
					'label'       => $capability,
					'description' => '',
					'permissions' => []
				];
		}
	}

	/**
	 * Check if a user has a specific permission
	 *
	 * @param string $permission The permission to check.
	 * @param int    $user_id Optional user ID. Defaults to current user.
	 * @return bool True if user has permission, false otherwise.
	 */
	public static function user_has_permission( $permission, $user_id = null ) {
		if ( ! $user_id ) {
			$user    = wp_get_current_user();
			$user_id = $user->ID;
		}

		if ( ! $user_id ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return false;
		}

		// Check if user has any capability that grants this permission
		$capabilities = get_option( BRICKS_DB_CAPABILITIES_PERMISSIONS, [] );

		if ( ! empty( $capabilities ) ) {
			foreach ( $capabilities as $cap_id => $cap_data ) {
				if ( isset( $cap_data['permissions'] ) && in_array( $permission, $cap_data['permissions'] ) && $user->has_cap( $cap_id ) ) {
					return true;
				}
			}
		}

		// Check default capabilities
		foreach ( [ Capabilities::FULL_ACCESS, Capabilities::EDIT_CONTENT ] as $default_cap ) {
			if ( $user->has_cap( $default_cap ) && in_array( $permission, self::get_default_capability_permissions( $default_cap )['permissions'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete a custom capability and its permissions
	 *
	 * @param string $capability_id The capability to delete.
	 * @return bool True if successful, false otherwise.
	 */
	public static function delete_capability( $capability_id ) {
		// Don't allow deleting default capabilities
		if ( in_array( $capability_id, [ Capabilities::FULL_ACCESS, Capabilities::EDIT_CONTENT, Capabilities::NO_ACCESS ] ) ) {
			return false;
		}

		$capabilities = get_option( BRICKS_DB_CAPABILITIES_PERMISSIONS, [] );

		if ( isset( $capabilities[ $capability_id ] ) ) {
			unset( $capabilities[ $capability_id ] );

			// Remove capability from all roles
			$roles = wp_roles()->get_names();
			foreach ( $roles as $role_key => $role_name ) {
				wp_roles()->remove_cap( $role_key, $capability_id );
			}

			return update_option( BRICKS_DB_CAPABILITIES_PERMISSIONS, $capabilities );
		}

		return false;
	}

	/**
	 * Save custom capabilities from settings form
	 *
	 * @param array $capabilities Array of capabilities with their permissions.
	 * @return bool True if successful, false otherwise.
	 */
	public static function save_custom_capabilities( $capabilities ) {
		if ( ! is_array( $capabilities ) ) {
			return false;
		}

		// Get existing capabilities
		$existing_capabilities = get_option( BRICKS_DB_CAPABILITIES_PERMISSIONS, [] );

		// Create a new array to store updated capabilities
		$updated_capabilities = [];

		// Keep track of capability IDs to detect removed ones
		$capability_ids = [];

		// Process each capability from the form
		foreach ( $capabilities as $capability ) {
			if ( empty( $capability['id'] ) || empty( $capability['label'] ) || ! isset( $capability['permissions'] ) || ! is_array( $capability['permissions'] ) ) {
				continue;
			}

			$id               = sanitize_key( $capability['id'] );
			$capability_ids[] = $id;

			// Don't allow overriding default capabilities
			if ( in_array( $id, [ Capabilities::FULL_ACCESS, Capabilities::EDIT_CONTENT, Capabilities::NO_ACCESS ] ) ) {
				continue;
			}

			// Store the permissions for this capability
			$updated_capabilities[ $id ] = [
				'label'       => sanitize_text_field( $capability['label'] ),
				'description' => isset( $capability['description'] ) ? sanitize_textarea_field( $capability['description'] ) : '',
				'permissions' => array_unique( array_map( 'sanitize_text_field', $capability['permissions'] ) )
			];

			// Ensure the capability exists in WordPress
			self::ensure_capability_exists( $id );
		}

		// Find capabilities that were removed
		foreach ( $existing_capabilities as $id => $data ) {
			// Skip default capabilities
			if ( in_array( $id, [ Capabilities::FULL_ACCESS, Capabilities::EDIT_CONTENT, Capabilities::NO_ACCESS ] ) ) {
				continue;
			}

			// If a capability is no longer in the list, remove it
			if ( ! in_array( $id, $capability_ids ) ) {
				self::remove_capability( $id );
			}
		}

		// Preserve default capabilities in the stored options
		foreach ( self::DEFAULT_CAPABILITIES as $cap_id => $cap_label ) {
			if ( isset( $existing_capabilities[ $cap_id ] ) ) {
				$updated_capabilities[ $cap_id ] = $existing_capabilities[ $cap_id ];
			}
		}

		// Save the updated capabilities
		return update_option( BRICKS_DB_CAPABILITIES_PERMISSIONS, $updated_capabilities );
	}

	/**
	 * Ensure a capability exists in WordPress
	 *
	 * @param string $capability_id The capability to create.
	 * @return void
	 */
	private static function ensure_capability_exists( $capability_id ) {
		// Get administrator role
		$role = get_role( 'administrator' );

		// Add capability to administrator role if it doesn't exist
		if ( $role && ! $role->has_cap( $capability_id ) ) {
			$role->add_cap( $capability_id, true );
		}
	}

	/**
	 * Remove a capability from WordPress
	 *
	 * @param string $capability_id The capability to remove.
	 * @return void
	 */
	private static function remove_capability( $capability_id ) {
		// Get all roles
		$roles = wp_roles();

		// Remove capability from all roles
		foreach ( $roles->role_objects as $role ) {
			if ( $role->has_cap( $capability_id ) ) {
				$role->remove_cap( $capability_id );
			}
		}
	}

	/**
	 * Get all permissions the current user has
	 *
	 * @return array Array of all permissions the current user has.
	 */
	public static function get_current_user_permissions() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return [];
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return [];
		}

		// Check user capabilities
		$user_caps = array_keys( $user->caps );
		$role_caps = array_keys( $user->allcaps );

		// STEP: First check user-specific capabilities
		if ( in_array( Capabilities::FULL_ACCESS, $user_caps ) ) {
			return self::get_default_capability_permissions( Capabilities::FULL_ACCESS )['permissions'];
		} elseif ( in_array( Capabilities::EDIT_CONTENT, $user_caps ) ) {
			return self::get_default_capability_permissions( Capabilities::EDIT_CONTENT )['permissions'];
		} elseif ( in_array( Capabilities::NO_ACCESS, $user_caps ) ) {
			return self::get_default_capability_permissions( Capabilities::NO_ACCESS )['permissions'];
		}

		// STEP: Check custom capabilities at user level
		$capabilities = get_option( BRICKS_DB_CAPABILITIES_PERMISSIONS, [] );
		foreach ( $capabilities as $cap_name => $cap_data ) {
			if ( in_array( $cap_name, $user_caps ) ) {
				return $cap_data['permissions'];
			}
		}

		// STEP: If no user-specific capabilities found, check role capabilities
		if ( in_array( Capabilities::FULL_ACCESS, $role_caps ) ) {
			return self::get_default_capability_permissions( Capabilities::FULL_ACCESS )['permissions'];
		} elseif ( in_array( Capabilities::EDIT_CONTENT, $role_caps ) ) {
			return self::get_default_capability_permissions( Capabilities::EDIT_CONTENT )['permissions'];
		} elseif ( in_array( Capabilities::NO_ACCESS, $role_caps ) ) {
			return self::get_default_capability_permissions( Capabilities::NO_ACCESS )['permissions'];
		}

		// STEP: Check custom capabilities at role level
		foreach ( $capabilities as $cap_name => $cap_data ) {
			if ( in_array( $cap_name, $role_caps ) ) {
				return $cap_data['permissions'];
			}
		}

		// STEP: Default to full access for administrators if no specific capability is set (@since 2.0)
		if ( current_user_can( 'administrator' ) ) {
			return self::get_default_capability_permissions( Capabilities::FULL_ACCESS )['permissions'];
		}

		// No capabilities found
		return [];
	}

	/**
	 * Check if user has access to any element
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool True if user has access to at least one element, false otherwise.
	 */
	public static function user_can_add_any_element( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		// Get all user permissions
		$user_permissions = self::get_current_user_permissions();

		// Check if any permission starts with 'add_element_'
		foreach ( $user_permissions as $permission ) {
			if ( strpos( $permission, 'add_element_' ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user can modify the number of elements through any means
	 * (adding elements, inserting components/templates, copying/pasting, etc.)
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool True if user can modify element count, false otherwise.
	 */
	public static function user_can_modify_element_count( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		// Check all ways to add/remove elements
		return self::user_can_add_any_element( $user_id ) ||
			self::user_has_permission( 'insert_components' ) ||
			self::user_has_permission( 'insert_templates' ) ||
			self::user_has_permission( 'copy_paste_elements' ) ||
			self::user_has_permission( 'duplicate_elements' ) ||
			self::user_has_permission( 'delete_elements' ) ||
			self::user_has_permission( 'access_revisions' );
	}

	/**
	 * Get all capabilities that have a specific permission
	 *
	 * @param string $permission The permission to check for.
	 * @return array Array of capability names that have this permission.
	 */
	public static function get_capabilities_by_permission( $permission ) {
		$capabilities = [];

		// Check default capabilities first
		foreach ( [ Capabilities::FULL_ACCESS, Capabilities::EDIT_CONTENT, Capabilities::NO_ACCESS ] as $default_cap ) {
			if ( in_array( $permission, self::get_default_capability_permissions( $default_cap )['permissions'] ) ) {
				$capabilities[] = $default_cap;
			}
		}

		// Check custom capabilities
		$custom_capabilities = get_option( BRICKS_DB_CAPABILITIES_PERMISSIONS, [] );

		if ( ! empty( $custom_capabilities ) ) {
			foreach ( $custom_capabilities as $cap_name => $cap_data ) {
				if ( ! empty( $cap_data['permissions'] ) && in_array( $permission, $cap_data['permissions'] ) ) {
					$capabilities[] = $cap_name;
				}
			}
		}

		return array_unique( $capabilities );
	}
}
