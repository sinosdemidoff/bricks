<?php
namespace Bricks\Integrations\Wpml;

use Bricks\Elements;
use Bricks\Database;
use Bricks\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Wpml {
	public $wpml_identifier                        = 'Bricks';
	public static $is_active                       = false;
	private static $is_processing_wpml_translation = false; // @since 1.11

	public function __construct() {
		self::$is_active = self::is_wpml_active();

		if ( ! self::$is_active ) {
			return;
		}

		add_action( 'init', [ $this, 'init_elements' ] );

		// WPML (@since 1.7)
		if ( function_exists( 'icl_object_id' ) ) {
			add_filter( 'bricks/database/bricks_get_all_templates_by_type_args', [ $this, 'wpml_get_posts_args' ] );
		}

		// @since 1.7.1 - Prefix cache key with get_locale() to ensure correct templates are loaded for different languages (@see #862jdhqgr)
		add_filter( 'bricks/database/get_all_templates_cache_key', [ $this, 'get_all_templates_cache_key' ] );

		add_filter( 'wpml_page_builder_support_required', [ $this, 'wpml_page_builder_support_required' ], 10, 1 );
		add_action( 'wpml_page_builder_register_strings', [ $this, 'wpml_page_builder_register_strings' ], 10, 2 );
		add_action( 'wpml_pro_translation_completed', [ $this, 'handle_translation_completed_no_strings' ], 10, 3 );

		/**
		 * Using a closure to ensure this function is only triggered from the 'wpml_page_builder_string_translated' hook
		 *
		 * Necessary because the function sets $is_processing_wpml_translation to true, which should only happen in the context of WPML string translation.
		 *
		 * @since 1.11
		 */
		add_action(
			'wpml_page_builder_string_translated',
			function( $package_kind, $translated_post_id, $original_post, $string_translations, $lang ) {
				$this->wpml_page_builder_string_translated( $package_kind, $translated_post_id, $original_post, $string_translations, $lang );
			},
			10,
			5
		);

		// Addressing all page builder "Corner cases"
		// https://git.onthegosystems.com/glue-plugins/wpml/wpml-page-builders/-/wikis/Integrating-a-page-builder-with-WPML#corner-cases
		add_filter( 'wpml_pb_is_editing_translation_with_native_editor', [ $this, 'wpml_pb_is_editing_translation_with_native_editor' ], 10, 2 );
		add_filter( 'wpml_pb_is_page_builder_page', [ $this, 'wpml_pb_is_page_builder_page' ], 10, 2 );

		// Hide WPML language switcher for specific Bricks admin pages
		add_action( 'admin_head', [ $this, 'hide_wpml_language_switcher_for_bricks' ] );

		// WPML Media Translation
		add_filter( 'wp_get_attachment_image_src', [ $this, 'translate_attachment_image_src' ], 10, 3 );

		add_filter( 'bricks/builder/post_title', [ $this, 'add_langugage_to_post_title' ], 10, 2 );

		// Add language parameter to query args (@since 1.9.9)
		add_filter( 'bricks/posts/query_vars', [ $this, 'add_language_query_var' ], 100, 3 );

		// Add language code to populate correct export template link (@since 1.10)
		add_filter( 'bricks/export_template_args', [ $this, 'add_export_template_arg' ], 10, 2 );

		// Filter builder edit link (@since 1.10)
		add_filter( 'bricks/get_builder_edit_link', [ $this, 'filter_builder_edit_link' ], 10, 2 );

		// Apply filter to each term name (@since 1.11)
		add_filter( 'bricks/builder/term_name', [ $this, 'add_language_to_term_name' ], 10, 3 );

		// Reassign filter element IDs for translated posts (fix DB AJAX) (@since 1.12.2)
		add_filter( 'bricks/fix_filter_element_db', [ $this, 'fix_filter_element_db' ], 10, 3 );

		// Add language code to filter element data (@since 1.12.2)
		add_filter( 'bricks/query_filters/element_data', [ $this, 'set_filter_element_language' ], 10, 3 );

		// Switch language for Bricks job execution (@since 1.12.2)
		add_action( 'bricks_execute_filter_index_job', [ $this, 'bricks_execute_filter_index_job' ], 10 );

		// Enable WPML hooks in Bricks frontend endpoints (@since 1.12.2)
		add_action( 'bricks/render_query_result/start', [ $this, 'wpml_get_term_adjust_id' ] );
		add_action( 'bricks/render_query_page/start', [ $this, 'wpml_get_term_adjust_id' ] );
		add_action( 'bricks/render_popup_content/start', [ $this, 'wpml_get_term_adjust_id' ] );

		/**
		 * Component translation support using WPML String Packages
		 *
		 * @since 2.1
		 */
		// Declare string package kind for components
		add_filter( 'wpml_active_string_package_kinds', [ $this, 'declare_component_string_package_kind' ] );

		// Register component strings when components are saved
		add_action( 'update_option_' . BRICKS_DB_COMPONENTS, [ $this, 'register_components_string_packages' ], 10, 2 );
	}

	/**
	 * Handle WPML translation completion ONLY when there are no strings to translate
	 * This is a fallback for when wpml_page_builder_register_strings is not triggered
	 *
	 * @param int    $new_post_id     ID of the translated post.
	 * @param array  $fields          Translated fields.
	 * @param string $original_post   Original post data.
	 *
	 * @since 1.12
	 */
	public function handle_translation_completed_no_strings( $new_post_id, $fields, $original_post ) {
		// Skip if 'wpml_page_builder_string_translated' already handled duplication
		if ( did_action( 'wpml_page_builder_string_translated' ) ) {
			return;
		}

		$original_post_id = $original_post->original_doc_id ?? false;

		if ( ! $original_post_id ) {
			return;
		}

		// Skip if not processing a Bricks post
		if ( ! $this->wpml_pb_is_page_builder_page( false, get_post( $original_post_id ) ) ) {
			return;
		}

		// Meta keys to copy from original post to translation
		$meta_keys = [
			BRICKS_DB_PAGE_CONTENT,
			BRICKS_DB_PAGE_HEADER,
			BRICKS_DB_PAGE_FOOTER,
			BRICKS_DB_TEMPLATE_TYPE,
			BRICKS_DB_TEMPLATE_SETTINGS,
			BRICKS_DB_PAGE_SETTINGS,
		];

		// Copy each meta key using WPML's helper function
		foreach ( $meta_keys as $meta_key ) {
			$meta_value = get_post_meta( $original_post_id, $meta_key, true );
			if ( $meta_value ) {
				if ( in_array( $meta_key, [ BRICKS_DB_PAGE_CONTENT, BRICKS_DB_PAGE_HEADER, BRICKS_DB_PAGE_FOOTER ], true ) ) {
					$filter_elements = \Bricks\Query_Filters::filter_controls_elements();

					// Ensure each Filter element Bricks ID is unique
					$meta_value = \Bricks\Helpers::generate_new_element_ids( $meta_value, $filter_elements );
				}
				update_post_meta( $new_post_id, $meta_key, $meta_value );
			}
		}

		// Clear unique inline CSS if using file-based CSS loading
		if ( Database::get_setting( 'cssLoading' ) === 'file' ) {
			\Bricks\Assets::$unique_inline_css = [];
		}
	}

	/**
	 * Check if WPML is currently processing a translation.
	 *
	 * @return bool True if WPML is processing a translation, false otherwise.
	 * @since 1.11
	 */
	public static function is_processing_wpml_translation() {
		return self::$is_processing_wpml_translation;
	}

	/**
	 * Reset the WPML translation processing flag.
	 *
	 * This method should be called after the translation process is complete and the flag is no longer needed.
	 *
	 * @since 1.11
	 */
	public static function end_processing_wpml_translation() {
		self::$is_processing_wpml_translation = false;
	}

	/**
	 * Add language query var
	 *
	 * @see https://wpml.org/documentation/support/debugging-theme-compatibility/#issue-custom-non-standard-wordpress-ajax-requests-always-return-the-default-language-content
	 * @since 1.9.9
	 */
	public function add_language_query_var( $query_vars, $settings, $element_id ) {
		if ( ! empty( Database::$page_data['language'] ) ) {
			$current_lang = sanitize_key( Database::$page_data['language'] );
			do_action( 'wpml_switch_language', $current_lang );
		}

		return $query_vars;
	}

	/**
	 * Add language code to export template args
	 *
	 * @since 1.10
	 */
	public function add_export_template_arg( $args, $post_id ) {
		$post_language = self::get_post_language_code( $post_id );

		if ( ! empty( $post_language ) ) {
			$args['lang'] = $post_language;
		}

		return $args;
	}

	/**
	 * Hide the WPML language switcher on specified Bricks admin pages.
	 */
	public function hide_wpml_language_switcher_for_bricks() {
		global $pagenow;

		$bricks_admin_pages_to_hide_language_switcher = [ 'bricks-settings' ];

		if ( $pagenow == 'admin.php' && isset( $_GET['page'] ) && in_array( $_GET['page'], $bricks_admin_pages_to_hide_language_switcher ) ) {
			echo '<style>
				#wp-admin-bar-WPML_ALS {
					display: none !important;
				}
			</style>';
		}
	}

	/**
	 * Check if WPML plugin is active
	 *
	 * @return boolean
	 */
	public static function is_wpml_active() {
		return class_exists( 'SitePress' );
	}

	/**
	 * Init WPML elements
	 */
	public function init_elements() {
		$wpml_elements = [ 'wpml-language-switcher' ];

		foreach ( $wpml_elements as $element_name ) {
			$wpml_element_file = BRICKS_PATH . "includes/integrations/wpml/elements/$element_name.php";

			// Get the class name from the element name
			$class_name = str_replace( '-', '_', $element_name );
			$class_name = ucwords( $class_name, '_' );
			$class_name = "Bricks\\$class_name";

			if ( is_readable( $wpml_element_file ) ) {
				Elements::register_element( $wpml_element_file, $element_name, $class_name );
			}
		}
	}

	/**
	 * WPML: Add 'suppress_filters' => false query arg to get templates of currently viewed language
	 *
	 * @param array $query_args
	 * @return array
	 *
	 * @since 1.7
	 */
	public function wpml_get_posts_args( $query_args ) {
		if ( ! isset( $query_args['suppress_filters'] ) ) {
			$query_args['suppress_filters'] = false;
		}

		return $query_args;
	}

	/**
	 * WMPL: Register 'Bricks' identifier for WPML
	 *
	 * https://git.onthegosystems.com/glue-plugins/wpml/wpml-page-builders/-/wikis/Integrating-a-page-builder-with-WPML#declaring-support-for-a-page-builder
	 *
	 * @since 1.8
	 */
	public function wpml_page_builder_support_required( $plugins ) {
		$plugins[] = $this->wpml_identifier; // = 'Bricks'

		return $plugins;
	}

	/**
	 * WPML: Register text strings of Bricks elements for translation in WPML
	 *
	 * @param \WP_Post|stdClass $post
	 * @param array             $package_data
	 *
	 * @since 1.8
	 */
	public function wpml_page_builder_register_strings( $post, $package_data ) {
		// Return: Package is not for 'Bricks'
		if ( $package_data['kind'] !== $this->wpml_identifier ) {
			return;
		}

		$template_type = get_post_meta( $post->ID, BRICKS_DB_TEMPLATE_TYPE, true );

		switch ( $template_type ) {
			case 'header':
				$bricks_elements = Database::get_data( $post->ID, 'header' );
				break;
			case 'footer':
				$bricks_elements = Database::get_data( $post->ID, 'footer' );
				break;
			default:
				$bricks_elements = Database::get_data( $post->ID, 'content' );
				break;
		}

		if ( empty( $bricks_elements ) || ! is_array( $bricks_elements ) ) {
			return;
		}

		/**
		 * Start the string package registration
		 * NOTE: Wrapping string registration with 'wpml_start_string_package_registration' and
		 * 'wpml_delete_unused_package_strings' actions ensures WPML can track and clean up unused strings when content is updated.
		 * See: https://wpml.org/documentation/support/string-package-translation/#updating-strings-and-removing-unused-ones
		 *
		 * @since 1.11
		 */
		do_action( 'wpml_start_string_package_registration', $package_data );

		// Build the elements tree
		$elements_tree = \Bricks\Helpers::build_elements_tree( $bricks_elements );

		// Traverse the tree and process each element
		$this->traverse_elements_tree( $elements_tree, $post );

		// End the string package registration and remove unused strings (@since 1.11)
		do_action( 'wpml_delete_unused_package_strings', $package_data );
	}

	/**
	 * Traverse the tree and process each element in a depth-first manner.
	 *
	 * @param array                   $elements
	 * @param \WP_Post|stdClass|array $post_or_package Post object for regular elements, package array for components.
	 *
	 * @since 1.10.2
	 */
	private function traverse_elements_tree( $elements, $post_or_package ) {
		if ( ! is_array( $elements ) ) {
			\Bricks\Helpers::maybe_log( 'Bricks: Invalid elements provided to traverse_elements_tree' );
			return;
		}

		foreach ( $elements as $element ) {
			if ( ! isset( $element['id'] ) ) {
				\Bricks\Helpers::maybe_log( 'Bricks: Invalid element encountered during traversal: missing ID' );
				continue;
			}

			// Process the current element
			$this->process_element( $element, $post_or_package );

			// Recursively process children
			if ( ! empty( $element['children'] ) && is_array( $element['children'] ) ) {
				$this->traverse_elements_tree( $element['children'], $post_or_package );
			}
		}
	}

	private function process_element( $element, $post_or_package ) {
		$element_name     = ! empty( $element['name'] ) ? $element['name'] : false;
		$element_settings = ! empty( $element['settings'] ) ? $element['settings'] : false;
		$element_config   = Elements::get_element( [ 'name' => $element_name ] );
		$element_controls = ! empty( $element_config['controls'] ) ? $element_config['controls'] : false;
		$element_label    = ! empty( $element_config['label'] ) ? $element_config['label'] : $element_name;

		// Handle component properties (@since 2.1)
		if ( isset( $element['cid'] ) && isset( $element['properties'] ) && is_array( $element['properties'] ) ) {
			$this->process_component_properties( $element, $post_or_package );
		}

		if ( ! $element_settings || ! $element_name || ! is_array( $element_controls ) ) {
			return;
		}

		$translatable_control_types = [ 'text', 'textarea', 'editor', 'repeater', 'link' ];

		// Loop over element controls to get translatable settings
		foreach ( $element_controls as $key => $control ) {
			$this->process_control( $key, $control, $element_settings, $element, $element_label, $translatable_control_types, $post_or_package );
		}
	}

	/**
	 * Process component properties for translation
	 *
	 * @param array                   $element The element containing component properties.
	 * @param \WP_Post|stdClass|array $post_or_package The post object or package data.
	 *
	 * @since 2.1
	 */
	private function process_component_properties( $element, $post_or_package ) {
		if ( ! isset( $element['id'] ) || ! isset( $element['cid'] ) || ! isset( $element['properties'] ) ) {
			return;
		}

		$element_id    = $element['id'];
		$component_id  = $element['cid'];
		$properties    = $element['properties'];
		$element_label = "Component Instance (CID: $component_id)";

		foreach ( $properties as $property_key => $property_value ) {
			// Handle direct text/HTML strings (text & textarea properties)
			if ( is_string( $property_value ) && ! empty( $property_value ) ) {
				$string_id = "{$element_id}_prop_{$property_key}";
				$this->register_wpml_string( $property_value, $string_id, $element_label, $post_or_package );
			}

			// Handle objects with URLs (link property)
			elseif ( is_array( $property_value ) ) {
				// Handle link-type properties
				if ( isset( $property_value['url'] ) && is_string( $property_value['url'] ) && ! empty( $property_value['url'] ) ) {
					$string_id = "{$element_id}_prop_{$property_key}_url";
					$this->register_wpml_string( $property_value['url'], $string_id, $element_label, $post_or_package );
				}

				// Handle image galleries or image properties
				if ( isset( $property_value['images'] ) && is_array( $property_value['images'] ) ) {
					foreach ( $property_value['images'] as $index => $image ) {
						if ( isset( $image['url'] ) && is_string( $image['url'] ) && ! empty( $image['url'] ) ) {
							$string_id = "{$element_id}_prop_{$property_key}_image_{$index}_url";
							$this->register_wpml_string( $image['url'], $string_id, $element_label, $post_or_package );
						}
					}
				}
			}
		}
	}

	private function process_control( $key, $control, $element_settings, $element, $element_label, $translatable_control_types, $post_or_package ) {
		$control_type = ! empty( $control['type'] ) ? $control['type'] : false;

		if ( ! in_array( $control_type, $translatable_control_types ) ) {
			return;
		}

		// Exclude certain controls from translation according to their key (@since 1.9.2)
		$exclude_control_from_translation = [ 'customTag', '_gridTemplateColumns', '_gridTemplateRows', '_cssId', 'targetSelector' ];

		if ( in_array( $key, $exclude_control_from_translation ) ) {
			return;
		}

		$string_value = ! empty( $element_settings[ $key ] ) ? $element_settings[ $key ] : '';

		if ( $control_type == 'repeater' && isset( $control['fields'] ) ) {
			$this->process_repeater_control( $key, $control, $element_settings, $element, $element_label, $translatable_control_types, $post_or_package );
			return;
		}

		// If control type is link, specifically process the URL
		if ( $control_type === 'link' && isset( $string_value['url'] ) ) {
			$string_value = $string_value['url'];
		}

		if ( ! is_string( $string_value ) || empty( $string_value ) ) {
			return;
		}

		$string_id = "{$element['id']}_$key"; // Set WPML string ID to "$element_id-$setting_key"
		$this->register_wpml_string( $string_value, $string_id, $element_label, $post_or_package, $control_type );
	}

	private function process_repeater_control( $key, $control, $element_settings, $element, $element_label, $translatable_control_types, $post_or_package ) {
		$repeater_items = ! empty( $element_settings[ $key ] ) ? $element_settings[ $key ] : [];

		if ( is_array( $repeater_items ) ) {
			foreach ( $repeater_items as $repeater_index => $repeater_item ) {
				if ( is_array( $repeater_item ) ) {
					foreach ( $repeater_item as $repeater_key => $repeater_value ) {
						// Get the type of this field, check if it's one of the accepted types
						$repeater_field_type = isset( $control['fields'][ $repeater_key ]['type'] ) ? $control['fields'][ $repeater_key ]['type'] : false;
						if ( ! in_array( $repeater_field_type, $translatable_control_types ) ) {
							continue;
						}

						$string_value = ! empty( $repeater_value ) ? $repeater_value : '';

						// If control type is link, get the URL
						if ( $repeater_field_type === 'link' && isset( $string_value['url'] ) ) {
							$string_value = $string_value['url'];
						}

						if ( ! is_string( $string_value ) || empty( $string_value ) ) {
							continue;
						}

						$string_id = "{$element['id']}_{$key}_{$repeater_index}_{$repeater_key}";

						$this->register_wpml_string( $string_value, $string_id, $element_label, $post_or_package );
					}
				}
			}
		}
	}

	/**
	 * Helper function to register a string for translation in WPML
	 */
	private function register_wpml_string( $string_value, $string_id, $element_label, $post_or_package, $control_type = null ) {
		if ( ! $string_value ) {
			return;
		}

		$string_title = "Bricks ($element_label)"; // Title of the string used in the translation

		// Determine the string type based on control type
		if ( $control_type == 'textarea' ) {
			$string_type = 'TEXTAREA';
		} else {
			$string_type = 'LINE'; // 'LINE', 'TEXTAREA', 'VISUAL'
		}

		// Handle both post objects and package arrays
		if ( is_array( $post_or_package ) ) {
			// This is a component package
			$package_data = $post_or_package;
		} else {
			// This is a regular post object
			$package_data = [
				'kind'    => $this->wpml_identifier,
				'name'    => $post_or_package->ID,
				'post_id' => $post_or_package->ID,
				'title'   => "Bricks (ID {$post_or_package->ID})",
			];
		}

		do_action( 'wpml_register_string', $string_value, $string_id, $package_data, $string_title, $string_type );
	}

	/**
	 * WPML: Translated strings are applied to the translated post.
	 *
	 * https://git.onthegosystems.com/glue-plugins/wpml/wpml-page-builders/-/wikis/Integrating-a-page-builder-with-WPML#applying-the-string-translations-in-post-translation
	 *
	 * @param string            $package_kind
	 * @param int               $translated_post_id
	 * @param \WP_Post|stdClass $original_post
	 * @param array             $string_translations
	 * @param string            $lang
	 *
	 * @since 1.8 NOTE: This is a modified version of the original function
	 */
	private function wpml_page_builder_string_translated( $package_kind, $translated_post_id, $original_post, $string_translations, $lang ) {
		// Return: Package is not for 'Bricks'
		if ( $package_kind !== $this->wpml_identifier ) {
			return;
		}

		/**
		 * Indicate that the current request is processing a WPML translation
		 * This flag is necessary because WPML's REST API does not provide user context,
		 * which causes issues with our capability checks when updating Bricks postmeta.
		 * Setting this flag to `true` allows us to bypass those checks safely within this request.
		 *
		 * @since 1.11
		 */
		self::$is_processing_wpml_translation = true;

		$original_post_id = $original_post->ID;

		/**
		 * Steps:
		 *
		 * 1. Get Bricks data from original post
		 * 2. Update template type
		 * 3. Update Bricks data with the translated strings
		 * 4. Update template settings if this is a template
		 * 5. Save to the translated post
		 */

		$area          = 'content';
		$template_type = get_post_meta( $original_post_id, BRICKS_DB_TEMPLATE_TYPE, true );

		// Update the BRICKS_DB_TEMPLATE_TYPE of the translated post with the value from the original post
		update_post_meta( $translated_post_id, BRICKS_DB_TEMPLATE_TYPE, $template_type );

		if ( $template_type === 'header' || $template_type === 'footer' ) {
			$area = $template_type;
		}

		$bricks_elements = Database::get_data( $original_post_id, $area );

		if ( ! is_array( $bricks_elements ) ) {
			return;
		}

		// Loop over translations for this post
		foreach ( $string_translations as $string_id => $translation ) {
			// Split the string ID to extract various details (like element ID, setting key, repeater index, etc.)
			$string_parts = explode( '_', $string_id );

			$element_id  = isset( $string_parts[0] ) ? $string_parts[0] : false;
			$setting_key = isset( $string_parts[1] ) ? $string_parts[1] : false;

			// If it's a link, update the URL
			if ( $setting_key === 'link' ) {
				foreach ( $bricks_elements as $index => $element ) {
					if ( $element['id'] === $element_id && isset( $translation[ $lang ]['value'] ) ) {
						$bricks_elements[ $index ]['settings'][ $setting_key ]['url'] = $translation[ $lang ]['value'];
					}
				}
				continue;
			}

			if ( count( $string_parts ) > 3 && isset( $string_parts[2] ) && isset( $string_parts[3] ) ) {
				// Split the string ID to extract various details (like element ID, setting key, repeater index, etc.)
				$string_parts = explode( '_', $string_id );

				// Assign values to $element_id and $setting_key if the corresponding parts are set, else assign false
				$element_id  = $string_parts[0] ?? false;
				$setting_key = $string_parts[1] ?? false;

				// If there are more than 3 parts in the string ID, it indicates this string belongs to a repeater field
				if ( count( $string_parts ) > 3 && isset( $string_parts[2] ) && isset( $string_parts[3] ) ) {
					$repeater_index = $string_parts[2];  // The repeater item index
					$repeater_key   = $string_parts[3];  // The repeater item key

					// Loop through elements to update the repeater field value with the translation
					foreach ( $bricks_elements as $index => $element ) {
						if ( $element['id'] === $element_id && isset( $translation[ $lang ]['value'] ) ) {
							// Define the path for readability
							$path = &$bricks_elements[ $index ]['settings'][ $setting_key ][ $repeater_index ][ $repeater_key ];

							// If $repeater_key is 'link', update the 'url', else update the repeater's specific field with its translated value
							if ( $repeater_key === 'link' && isset( $path['url'] ) ) {
								$path['url'] = $translation[ $lang ]['value'];
							} elseif ( isset( $path ) ) {
								$path = $translation[ $lang ]['value'];
							}
						}
					}
					continue;  // Skip further processing and jump to the next iteration
				}
			}

			if ( ! $element_id || ! $setting_key ) {
				continue;
			}

			// Check if this is a component property translation
			if ( $setting_key === 'prop' && count( $string_parts ) >= 3 ) {
				$property_key = $string_parts[2];

				// Handle property URL
				if ( count( $string_parts ) >= 4 && $string_parts[3] === 'url' ) {
					foreach ( $bricks_elements as $index => $element ) {
						if ( $element['id'] === $element_id && isset( $element['properties'][ $property_key ] ) && isset( $translation[ $lang ]['value'] ) ) {
							$bricks_elements[ $index ]['properties'][ $property_key ]['url'] = $translation[ $lang ]['value'];
						}
					}
				}

				// Handle simple property value (text & textarea properties)
				else {
					foreach ( $bricks_elements as $index => $element ) {
						if ( $element['id'] === $element_id && isset( $element['properties'] ) && isset( $translation[ $lang ]['value'] ) ) {
							$bricks_elements[ $index ]['properties'][ $property_key ] = $translation[ $lang ]['value'];
						}
					}
				}

				continue;
			}

			// Loop over element and replace their text
			foreach ( $bricks_elements as $index => $element ) {
				// STEP: Check if this is a 'Template' element and replace the template ID with the translated template ID if it exists (@since 1.9.4)
				if ( $element['name'] ?? null === 'template' ) {
					// Fetch the original template ID from the element settings
					$original_template_id = $element['settings']['template'] ?? null;

					if ( $original_template_id ) {
						// Fetch the translated ID of the linked 'bricks_template' post
						$translated_template_id = apply_filters( 'wpml_object_id', $original_template_id, BRICKS_DB_TEMPLATE_SLUG, true, $lang );

						// Check if the translated ID is valid; if not, retain the original ID
						if ( $translated_template_id ) {
							// Replace the original ID with the translated ID
							$bricks_elements[ $index ]['settings']['template'] = $translated_template_id;
						}
					}
				}

				// STEP: Translate popup template IDs in element interactions (@since 1.11)
				if ( isset( $element['settings']['_interactions'] ) && is_array( $element['settings']['_interactions'] ) ) {
					foreach ( $element['settings']['_interactions'] as $interaction_index => $interaction ) {
						if (
							isset( $interaction['action'] ) &&
							isset( $interaction['target'] ) &&
							isset( $interaction['templateId'] ) &&
							$interaction['action'] === 'show' &&
							$interaction['target'] === 'popup' &&
							is_numeric( $interaction['templateId'] )
						) {
							$original_popup_id   = intval( $interaction['templateId'] );
							$translated_popup_id = apply_filters( 'wpml_object_id', $original_popup_id, BRICKS_DB_TEMPLATE_SLUG, true, $lang );

							if ( $translated_popup_id ) {
								$bricks_elements[ $index ]['settings']['_interactions'][ $interaction_index ]['templateId'] = $translated_popup_id;
							}
						}
					}
				}

				// STEP: Replace the text of the element with the translated text
				if ( $element['id'] === $element_id && isset( $translation[ $lang ]['value'] ) ) {
					$bricks_elements[ $index ]['settings'][ $setting_key ] = $translation[ $lang ]['value'];
				}
			}
		}

		// Save the original post data which now contains the translations
		$meta_key = Database::get_bricks_data_key( $area );

		if ( in_array( $meta_key, [ BRICKS_DB_PAGE_CONTENT, BRICKS_DB_PAGE_HEADER, BRICKS_DB_PAGE_FOOTER ], true ) ) {
			/**
			 * To avoid all IDs regenerate for every translation sync (especially query ids), only regenerate the IDs of the filter elements to solve index issues.
			 * This is not same as Polylang
			 * Not recommended as not unique element IDs might issue might happen (#862je0kmd)
			 *
			 * @since 1.12.2
			 */
			$filter_elements = \Bricks\Query_Filters::filter_controls_elements();

			// Ensure each Filter element Bricks ID is unique (@since 1.12.2)
			$bricks_elements = Helpers::generate_new_element_ids( $bricks_elements, $filter_elements );
		}

		update_post_meta( $translated_post_id, $meta_key, $bricks_elements );

		// Update template settings if this is a template
		if ( get_post_type( $translated_post_id ) === BRICKS_DB_TEMPLATE_SLUG ) {
			// Get the template settings from the original post
			$original_template_settings = Helpers::get_template_settings( $original_post->ID );

			// Set the original template settings on the translated post
			Helpers::set_template_settings( $translated_post_id, $original_template_settings );
		}

		/**
		 * STEP: Clear unique_inline_css T
		 *
		 * To regenerate CSS file for secondary languages without triggering return on line 2356 in assets.php
		 */
		if ( Database::get_setting( 'cssLoading' ) == 'file' ) {
			\Bricks\Assets::$unique_inline_css = [];
		}
	}

	/**
	 * Translation edited with Bricks (POST 'bricks-is-builder' set)
	 *
	 * Skip translating this post save.
	 *
	 * https://git.onthegosystems.com/glue-plugins/wpml/wpml-page-builders/-/wikis/Integrating-a-page-builder-with-WPML#1-the-translation-is-edited-with-the-page-builder-editor-instead-of-a-wpml-translation-editor
	 *
	 * @param bool $is_translation_with_native_editor
	 * @param int  $translated_post_id
	 *
	 * @since 1.8
	 */
	public function wpml_pb_is_editing_translation_with_native_editor( $is_translation_with_native_editor, $translated_post_id ) {
		if ( ! $is_translation_with_native_editor && isset( $_POST['bricks-is-builder'] ) ) {
			$post_id = ! empty( $_POST['postId'] ) ? intval( $_POST['postId'] ) : false;

			return $translated_post_id === $post_id;
		}

		return $is_translation_with_native_editor;
	}

	/**
	 * Check if post is built & rendered with Bricks
	 *
	 * https://git.onthegosystems.com/glue-plugins/wpml/wpml-page-builders/-/wikis/Integrating-a-page-builder-with-WPML#2-the-original-page-or-post-is-not-built-with-the-page-builder
	 *
	 * @param bool              $is_pb_post
	 * @param \WP_Post|stdClass $post
	 *
	 * @since 1.8
	 */
	public function wpml_pb_is_page_builder_page( $is_pb_post, $post ) {
		if ( ! $is_pb_post ) {
			$post_id       = $post->ID;
			$area          = 'content';
			$template_type = get_post_meta( $post_id, BRICKS_DB_TEMPLATE_TYPE, true );

			if ( $template_type === 'header' || $template_type === 'footer' ) {
				$area = $template_type;
			}

			$meta_key    = Database::get_bricks_data_key( $area );
			$bricks_data = get_post_meta( $post_id, $meta_key, true );

			// Post has Bricks data && is rendered with Bricks
			$editor_mode                    = get_post_meta( $post_id, BRICKS_DB_EDITOR_MODE, true );
			$built_and_rendered_with_bricks = $bricks_data && $editor_mode === 'bricks';

			return $built_and_rendered_with_bricks;
		}

		return $is_pb_post;
	}

	/**
	 * Modify the wp_get_attachment_image_src output to return the translated image src.
	 *
	 * @param array        $image          The array containing the image src and dimensions.
	 * @param int          $attachment_id  The attachment ID.
	 * @param string|array $size           Image size.
	 *
	 * @return array
	 */
	public function translate_attachment_image_src( $image, $attachment_id, $size ) {
		$translated_id = $this->get_translated_attachment_id( $attachment_id );

		// If the translated ID is different than the original, get the src for the translated image.
		if ( $translated_id !== $attachment_id ) {
			$image = wp_get_attachment_image_src( $translated_id, $size );
		}

		return $image;
	}

	/**
	 * Translate the attachment ID to the current language's version.
	 *
	 * @param int $attachment_id
	 *
	 * @return int
	 */
	public function get_translated_attachment_id( $attachment_id ) {
		return apply_filters( 'wpml_object_id', $attachment_id, 'attachment', true );
	}

	/**
	 * Add language code to post title
	 *
	 * @param string $title   The original title of the page.
	 * @param int    $page_id The ID of the page.
	 * @return string The modified title with the language suffix.
	 */
	public function add_langugage_to_post_title( $title, $page_id ) {
		if ( isset( $_GET['addLanguageToPostTitle'] ) ) {
			$language_code = self::get_post_language_code( $page_id );
			$language_code = ! empty( $language_code ) ? strtoupper( $language_code ) : '';

			if ( $language_code ) {
				return "[$language_code] $title";
			}
		}

		// Return the original title if conditions are not met
		return $title;
	}

	/**
	 * Add language code to term name
	 *
	 * @param string $name    The original name of the term.
	 * @param int    $term_id The ID of the term.
	 * @param string $taxonomy The taxonomy of the term.
	 * @return string The modified name with the language suffix.
	 *
	 * @since 1.11
	 */
	public function add_language_to_term_name( $name, $term_id, $taxonomy ) {
		\Bricks\Ajax::verify_nonce( 'bricks-nonce-builder' );

		if ( ! isset( $_GET['addLanguageToTermName'] ) || ! filter_var( $_GET['addLanguageToTermName'], FILTER_VALIDATE_BOOLEAN ) ) {
			return $name;
		}

		$term_id = absint( $term_id );

		if ( $term_id === 0 || ! term_exists( $term_id, $taxonomy ) ) {
			return $name;
		}

		if ( ! function_exists( 'apply_filters' ) || ! has_filter( 'wpml_element_language_details' ) ) {
			return $name;
		}

		$language_details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $term_id,
				'element_type' => $taxonomy,
			]
		);

		if ( ! is_object( $language_details ) || ! isset( $language_details->language_code ) ) {
			return $name;
		}

		$language_code = strtoupper( sanitize_key( $language_details->language_code ) );

		if ( ! empty( $language_code ) ) {
			return '[' . $language_code . '] ' . $name;
		}

		return $name;
	}

	/**
	 * Get the language code of a post
	 *
	 * @since 1.10
	 */
	public static function get_post_language_code( $post_id ) {
		$language_info = apply_filters( 'wpml_post_language_details', null, $post_id );

		return ! empty( $language_info['language_code'] ) ? $language_info['language_code'] : '';
	}

	/**
	 * Get the current language code in WPML
	 *
	 * @return string|null The current language code or null if not set.
	 *
	 * @since 1.9.9
	 */
	public static function get_current_language() {
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			return \ICL_LANGUAGE_CODE; // phpcs:ignore
		}

		return null;
	}

	/**
	 * Get the URL format for WPML
	 *
	 * @since 1.9.9
	 */
	public static function get_url_format() {
		global $sitepress;

		if ( ! $sitepress || ! method_exists( $sitepress, 'get_setting' ) ) {
			return null;
		}

		return $sitepress->get_setting( 'language_negotiation_type' );
	}

	/**
	 * Filter the builder edit link to include the language code
	 *
	 * @param string $url The original builder edit URL.
	 * @param int    $post_id The post ID.
	 * @return string The filtered URL.
	 *
	 * @since 1.10
	 */
	public function filter_builder_edit_link( $url, $post_id ) {
		if ( empty( $url ) || empty( $post_id ) || ! is_numeric( $post_id ) ) {
			return $url;
		}

		if ( ! get_post( $post_id ) ) {
			return $url;
		}

		$post_language_details = apply_filters( 'wpml_post_language_details', null, $post_id );

		// Verify we got a valid array/object response and it has the required language_code
		if ( ! empty( $post_language_details ) &&
			is_array( $post_language_details ) &&
			isset( $post_language_details['language_code'] ) &&
			! empty( $post_language_details['language_code'] )
		) {

			// Sanitize the language code
			$lang_code = sanitize_key( $post_language_details['language_code'] );

			$url = apply_filters( 'wpml_permalink', $url, $lang_code );
		}

		return $url;
	}

	/**
	 * Prefix cache key with get_locale() to ensure correct templates are loaded for different languages
	 *
	 * @since 1.7.1
	 */
	public function get_all_templates_cache_key( $cache_key ) {
		return get_locale() . "_$cache_key";
	}

	/**
	 * Reassign new IDs for filter elements when fixing the filter element DB
	 *
	 * @since 1.12.2
	 */
	public function fix_filter_element_db( $handled, $post_id, $template_type ) {
		$language_code = self::get_post_language_code( $post_id );

		if ( ! $language_code ) {
			return $handled;
		}

		$default_language = apply_filters( 'wpml_default_language', null );

		// If default language, skip
		if ( $language_code === $default_language ) {
			return $handled;
		}

		// We need to reassign new IDs for filter elements
		$filter_elements = \Bricks\Query_Filters::filter_controls_elements();

		// Ensure each Filter element Bricks ID is unique
		$bricks_elements = Database::get_data( $post_id, $template_type );

		$bricks_elements = \Bricks\Helpers::generate_new_element_ids( $bricks_elements, $filter_elements );

		// Update the post meta with the new elements, will auto update custom element DB and reindex
		update_post_meta( $post_id, Database::get_bricks_data_key( $template_type ), $bricks_elements );

		// Return true to indicate the filter element DB has been handled
		return true;
	}

	/**
	 * Insert language code into the element settings
	 *
	 * @since 1.12.2
	 */
	public function set_filter_element_language( $data, $element, $post_id ) {
		// Get the language code of the post
		$language_code = self::get_post_language_code( $post_id );

		if ( empty( $language_code ) ) {
			return $data;
		}

		// Insert the language code into the element settings
		$data['language'] = $language_code;

		return $data;
	}

	/**
	 * Switch language based on query filter index job
	 * Otherwise, the values of the index records is following the current language set by WPML plugin
	 *
	 * @since 1.12.2
	 */
	public function bricks_execute_filter_index_job( $job ) {
		$language_code = $job['language'] ?? false;

		if ( ! empty( $language_code ) ) {
			do_action( 'wpml_switch_language', $language_code );
		}
	}

	/**
	 * Run WPML hooks to auto-adjust term IDs in Bricks frontend endpoints
	 *
	 * Adjust queried categories and tags ids according to the language
	 *
	 * @since 1.12.2
	 */
	public function wpml_get_term_adjust_id( $request_data ) {
		global $sitepress;

		if ( ! $sitepress || ! method_exists( $sitepress, 'get_setting' ) ) {
			return;
		}

		// @see sitepress.class.php set_term_filters_and_hooks()
		if ( $sitepress->get_setting( 'auto_adjust_ids' ) ) {
			add_filter( 'get_term', [ $sitepress, 'get_term_adjust_id' ], 1, 1 );
		}
	}

	/**
	 * Register component strings for translation when components are saved
	 *
	 * @param mixed $old_value The old option value.
	 * @param mixed $value     The new option value.
	 *
	 * @since 2.1
	 */
	public function register_components_string_packages( $old_value, $value ) {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return;
		}

		// Register each component as its own package with unique name
		foreach ( $value as $component ) {
			if ( ! isset( $component['id'] ) ) {
				continue;
			}

			$component_id   = $component['id'];
			$component_name = $component['name'] ?? "Component $component_id";

			// Create unique package for this component
			$package = [
				'kind'      => 'Bricks components',
				'kind_slug' => 'bricks-components',
				'name'      => $component_id,
				'title'     => $component_name,
			];

			// Start string package registration for this component
			do_action( 'wpml_start_string_package_registration', $package );

			// Process component elements if they exist
			if ( isset( $component['elements'] ) && is_array( $component['elements'] ) ) {
				// Build the elements tree and traverse it
				$elements_tree = \Bricks\Helpers::build_elements_tree( $component['elements'] );
				$this->traverse_elements_tree( $elements_tree, $package );
			}

			// Process component properties defaults if they exist
			if ( isset( $component['properties'] ) && is_array( $component['properties'] ) ) {
				$this->process_component_properties_defaults( $component['properties'], $package );
			}

			// End string package registration and cleanup unused strings
			do_action( 'wpml_delete_unused_package_strings', $package );
		}
	}

	/**
	 * Process component properties default values for translation
	 *
	 * @param array $properties The component properties array.
	 * @param array $package    The string package data.
	 *
	 * @since 2.1
	 */
	private function process_component_properties_defaults( $properties, $package ) {
		foreach ( $properties as $property ) {
			if ( ! isset( $property['id'] ) || ! isset( $property['type'] ) ) {
				continue;
			}

			$property_id    = $property['id'];
			$property_type  = $property['type'];
			$property_label = $property['label'] ?? "Property $property_id";
			$default_value  = $property['default'] ?? null;

			if ( ! $default_value ) {
				continue;
			}

			switch ( $property_type ) {
				case 'text':
					if ( is_string( $default_value ) && ! empty( $default_value ) ) {
						$string_name = "property_{$property_id}_default";
						do_action( 'wpml_register_string', $default_value, $string_name, $package, $property_label, 'LINE' );
					}
					break;

				case 'editor':
					if ( is_string( $default_value ) && ! empty( $default_value ) ) {
						$string_name = "property_{$property_id}_default";
						do_action( 'wpml_register_string', $default_value, $string_name, $package, $property_label, 'TEXTAREA' );
					}
					break;

				case 'image':
					if ( is_array( $default_value ) && isset( $default_value['url'] ) && ! empty( $default_value['url'] ) ) {
						$string_name = "property_{$property_id}_default_url";
						do_action( 'wpml_register_string', $default_value['url'], $string_name, $package, $property_label, 'LINE' );
					}
					break;

				case 'image-gallery':
					if ( is_array( $default_value ) && isset( $default_value['images'] ) && is_array( $default_value['images'] ) ) {
						foreach ( $default_value['images'] as $index => $image ) {
							if ( isset( $image['url'] ) && ! empty( $image['url'] ) ) {
								$string_name = "property_{$property_id}_default_image_{$index}_url";
								do_action( 'wpml_register_string', $image['url'], $string_name, $package, $property_label, 'LINE' );
							}
						}
					}
					break;

				case 'link':
					if ( is_array( $default_value ) && isset( $default_value['type'] ) && $default_value['type'] === 'external' && isset( $default_value['url'] ) && ! empty( $default_value['url'] ) ) {
						$string_name = "property_{$property_id}_default_url";
						do_action( 'wpml_register_string', $default_value['url'], $string_name, $package, $property_label, 'LINE' );
					}
					break;

				case 'select':
					if ( isset( $property['options'] ) && is_array( $property['options'] ) ) {
						foreach ( $property['options'] as $option_index => $option ) {
							if ( isset( $option['label'] ) && ! empty( $option['label'] ) ) {
								$string_name = "property_{$property_id}_option_{$option_index}_label";
								do_action( 'wpml_register_string', $option['label'], $string_name, $package, $property_label, 'LINE' );
							}
							if ( isset( $option['value'] ) && ! empty( $option['value'] ) ) {
								$string_name = "property_{$property_id}_option_{$option_index}_value";
								do_action( 'wpml_register_string', $option['value'], $string_name, $package, $property_label, 'LINE' );
							}
						}
					}
					break;
			}
		}
	}

	/**
	 * Declare string package kind for components
	 *
	 * @param array $active_string_package_kinds
	 * @return array
	 *
	 * @since 2.1
	 */
	public function declare_component_string_package_kind( $active_string_package_kinds ) {
		$active_string_package_kinds['bricks-components'] = [
			'title'  => 'Bricks Components',
			'slug'   => 'bricks-components',
			'plural' => 'Bricks Components',
		];

		return $active_string_package_kinds;
	}

	/**
	 * Translate a component on-the-fly using WPML string translations
	 *
	 * @param array $component The component to translate.
	 * @return array The component with translated strings.
	 *
	 * @since 2.1
	 */
	public static function get_translated_component( $component ) {
		$current_language = self::get_current_language();
		$default_language = apply_filters( 'wpml_default_language', null );

		// Return original if current language is default or not set
		if ( ! $current_language || $current_language === $default_language ) {
			return $component;
		}

		// Each component has its own package with unique name
		if ( ! isset( $component['id'] ) ) {
			return $component;
		}

		$component_id   = $component['id'];
		$component_name = $component['name'] ?? "Component $component_id";

		// Create the package data for translation lookups
		$package = [
			'kind'      => 'Bricks components',
			'kind_slug' => 'bricks-components',
			'name'      => $component_id,
			'title'     => $component_name,
		];

		$translated_component = $component;

		// Translate component elements if they exist
		if ( isset( $component['elements'] ) && is_array( $component['elements'] ) ) {
			$translated_component['elements'] = self::get_translated_elements( $component['elements'], $package );
		}

		// Translate component properties defaults if they exist
		if ( isset( $component['properties'] ) && is_array( $component['properties'] ) ) {
			$translated_component['properties'] = self::get_translated_component_properties( $component['properties'], $package );
		}

		return $translated_component;
	}

	/**
	 * Translate component elements on-the-fly
	 *
	 * @param array $elements The elements to translate.
	 * @param array $package  The WPML package data.
	 * @return array The translated elements.
	 *
	 * @since 2.1
	 */
	private static function get_translated_elements( $elements, $package ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}

		foreach ( $elements as &$element ) {
			if ( ! isset( $element['id'] ) ) {
				continue;
			}

			// Translate element settings
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				foreach ( $element['settings'] as $setting_key => $setting_value ) {
					if ( is_string( $setting_value ) && ! empty( $setting_value ) ) {
						$string_id        = "{$element['id']}_{$setting_key}";
						$translated_value = apply_filters( 'wpml_translate_string', $setting_value, $string_id, $package );

						if ( $translated_value !== $setting_value ) {
							$element['settings'][ $setting_key ] = $translated_value;
						}
					}
					// Handle link settings (URL translation)
					elseif ( is_array( $setting_value ) && isset( $setting_value['url'] ) && ! empty( $setting_value['url'] ) ) {
						$string_id      = "{$element['id']}_{$setting_key}_url";
						$translated_url = apply_filters( 'wpml_translate_string', $setting_value['url'], $string_id, $package );

						if ( $translated_url !== $setting_value['url'] ) {
							$element['settings'][ $setting_key ]['url'] = $translated_url;
						}
					}
					// Handle repeater settings
					elseif ( is_array( $setting_value ) ) {
						foreach ( $setting_value as $repeater_index => $repeater_item ) {
							if ( is_array( $repeater_item ) ) {
								foreach ( $repeater_item as $repeater_key => $repeater_value ) {
									if ( is_string( $repeater_value ) && ! empty( $repeater_value ) ) {
										$string_id        = "{$element['id']}_{$setting_key}_{$repeater_index}_{$repeater_key}";
										$translated_value = apply_filters( 'wpml_translate_string', $repeater_value, $string_id, $package );
										if ( $translated_value !== $repeater_value ) {
											$element['settings'][ $setting_key ][ $repeater_index ][ $repeater_key ] = $translated_value;
										}
									}
									// Handle link in repeater
									elseif ( is_array( $repeater_value ) && isset( $repeater_value['url'] ) && ! empty( $repeater_value['url'] ) ) {
										$string_id      = "{$element['id']}_{$setting_key}_{$repeater_index}_{$repeater_key}_url";
										$translated_url = apply_filters( 'wpml_translate_string', $repeater_value['url'], $string_id, $package );
										if ( $translated_url !== $repeater_value['url'] ) {
											$element['settings'][ $setting_key ][ $repeater_index ][ $repeater_key ]['url'] = $translated_url;
										}
									}
								}
							}
						}
					}
				}
			}

			// Handle component properties (for component instances)
			if ( isset( $element['properties'] ) && is_array( $element['properties'] ) ) {
				foreach ( $element['properties'] as $property_key => $property_value ) {
					if ( is_string( $property_value ) && ! empty( $property_value ) ) {
						$string_id        = "{$element['id']}_prop_{$property_key}";
						$translated_value = apply_filters( 'wpml_translate_string', $property_value, $string_id, $package );
						if ( $translated_value !== $property_value ) {
							$element['properties'][ $property_key ] = $translated_value;
						}
					}
					// Handle link-type properties
					elseif ( is_array( $property_value ) && isset( $property_value['url'] ) && ! empty( $property_value['url'] ) ) {
						$string_id      = "{$element['id']}_prop_{$property_key}_url";
						$translated_url = apply_filters( 'wpml_translate_string', $property_value['url'], $string_id, $package );
						if ( $translated_url !== $property_value['url'] ) {
							$element['properties'][ $property_key ]['url'] = $translated_url;
						}
					}
				}
			}

			// Recursively translate children
			if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
				$element['children'] = self::get_translated_elements( $element['children'], $package );
			}
		}

		return $elements;
	}

	/**
	 * Translate component properties defaults on-the-fly
	 *
	 * @param array $properties The properties to translate.
	 * @param array $package    The WPML package data.
	 * @return array The translated properties.
	 *
	 * @since 2.1
	 */
	private static function get_translated_component_properties( $properties, $package ) {
		if ( ! is_array( $properties ) ) {
			return $properties;
		}

		foreach ( $properties as &$property ) {
			if ( ! isset( $property['id'] ) || ! isset( $property['type'] ) ) {
				continue;
			}

			$property_id   = $property['id'];
			$property_type = $property['type'];
			$default_value = $property['default'] ?? null;

			if ( ! $default_value ) {
				continue;
			}

			switch ( $property_type ) {
				case 'text':
				case 'editor':
					if ( is_string( $default_value ) && ! empty( $default_value ) ) {
						$string_id        = "property_{$property_id}_default";
						$translated_value = apply_filters( 'wpml_translate_string', $default_value, $string_id, $package );
						if ( $translated_value !== $default_value ) {
							$property['default'] = $translated_value;
						}
					}
					break;

				case 'image':
					if ( is_array( $default_value ) && isset( $default_value['url'] ) && ! empty( $default_value['url'] ) ) {
						$string_id      = "property_{$property_id}_default_url";
						$translated_url = apply_filters( 'wpml_translate_string', $default_value['url'], $string_id, $package );
						if ( $translated_url !== $default_value['url'] ) {
							$property['default']['url'] = $translated_url;
						}
					}
					break;

				case 'link':
					if ( is_array( $default_value ) && isset( $default_value['url'] ) && ! empty( $default_value['url'] ) ) {
						$string_id      = "property_{$property_id}_default_url";
						$translated_url = apply_filters( 'wpml_translate_string', $default_value['url'], $string_id, $package );
						if ( $translated_url !== $default_value['url'] ) {
							$property['default']['url'] = $translated_url;
						}
					}
					break;

				case 'select':
					if ( isset( $property['options'] ) && is_array( $property['options'] ) ) {
						foreach ( $property['options'] as $option_index => $option ) {
							if ( isset( $option['label'] ) && ! empty( $option['label'] ) ) {
								$string_id        = "property_{$property_id}_option_{$option_index}_label";
								$translated_label = apply_filters( 'wpml_translate_string', $option['label'], $string_id, $package );
								if ( $translated_label !== $option['label'] ) {
									$property['options'][ $option_index ]['label'] = $translated_label;
								}
							}
							if ( isset( $option['value'] ) && ! empty( $option['value'] ) ) {
								$string_id        = "property_{$property_id}_option_{$option_index}_value";
								$translated_value = apply_filters( 'wpml_translate_string', $option['value'], $string_id, $package );
								if ( $translated_value !== $option['value'] ) {
									$property['options'][ $option_index ]['value'] = $translated_value;
								}
							}
						}
					}
					break;
			}
		}

		return $properties;
	}
}
