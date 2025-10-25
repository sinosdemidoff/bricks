<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Elements {
	public static $elements = [];
	public static $manager  = []; // = Element Manager (@since 2.0)
	public static $native   = []; // Use in Element Manager (@since 2.0)

	public function __construct() {
		// Init elements on init hook (to get custom registered taxonomies, etc.)
		add_action( 'init', [ $this, 'init_elements' ] );

		// Load elements on 'wp' hook (to get post_id, etc.)
		add_action( 'wp', [ $this, 'load_elements' ] );
	}

	public static function init_elements() {
		// Load abstract element base class
		require_once BRICKS_PATH . 'includes/elements/base.php';

		/**
		 * Load custom render element base class
		 *
		 * Handle setting/resetting $bricks_query to render in-loop data correctly.
		 *
		 * Used for Carousel, Posts, Related Posts, Woo Products.
		 *
		 * @since 1.10.2
		 */
		require_once BRICKS_PATH . 'includes/elements/custom-render-element.php';

		$element_names = [
			// Layout (nestable elements)
			'container',
			'section', // @since 1.5
			'block', // @since 1.5
			'div', // @since 1.5

			// Basic
			'heading',
			'text-basic', // @since 1.3.6
			'text',
			'text-link', // @since 1.8
			'button',
			'icon',
			'image',
			'video',

			// General
			'nav-nested', // @since 1.8
			'dropdown', // @since 1.8
			'offcanvas', // @since 1.8
			'toggle', // @since 1.8

			'divider',
			'icon-box',
			'social-icons',
			'list',
			'accordion',
			'accordion-nested', // @since 1.5
			'tabs',
			'tabs-nested', // @since 1.5
			'form',
			'map',
			'map-leaflet', // @since 2.1
			'map-connector', // @since 2.0
			'alert',
			'animated-typing',
			'countdown',
			'counter',
			'pricing-tables',
			'progress-bar',
			'pie-chart',
			'team-members',
			'testimonials',
			'html',
			'code',
			'template',
			'logo',
			'facebook-page',
			'breadcrumbs', // @since 1.8.1
			'rating', // @since 1.11
			'back-to-top', // @since 1.11

			// Media
			'image-gallery',
			'audio',
			'carousel',
			'slider',
			'slider-nested', // @since 1.5
			'svg',
			'instagram-feed',

			// Query
			'pagination',
			'query-results-summary', // @since 1.12.2

			// WordPress
			'wordpress',
			'posts',
			'nav-menu',
			'sidebar',
			'search',
			'shortcode',

			// Single
			'post-title',
			'post-excerpt',
			'post-meta',
			'post-content',
			'post-sharing',
			'related-posts',
			'post-author',
			'post-comments',
			'post-taxonomy',
			'post-navigation',

			'post-reading-time',
			'post-reading-progress-bar',
			'post-toc',
		];

		// Load filter element base class (@since 1.9.6)
		if ( Helpers::enabled_query_filters() ) {
			require_once BRICKS_PATH . 'includes/elements/filter-base.php';
			$input_elements = [
				'filter-checkbox',
				'filter-datepicker',
				'filter-radio',
				'filter-range',
				'filter-search',
				'filter-select',
				'filter-submit',
				'filter-active-filters',
			];

			$element_names = array_merge( $element_names, $input_elements );
		}

		// Add element names to self::$native element names array (@since 2.0)
		self::$native = array_merge( self::$native, $element_names );

		$element_names = apply_filters( 'bricks/builder/elements', $element_names );

		/**
		 * Get element manager data
		 *
		 * Remove element if 'status' is 'disabled'
		 *
		 * @since 2.0
		 */
		self::$manager = self::manager();

		foreach ( $element_names as $element_name ) {
			// Skip if element is disabled and we aren't on the Bricks > Elements page (@since 2.0)
			if ( isset( self::$manager[ $element_name ]['status'] ) && self::$manager[ $element_name ]['status'] === 'disabled' ) {
				$page_name = $_GET['page'] ?? '';
				if ( $page_name !== 'bricks-elements' ) {
					continue;
				}
			}

			$file = BRICKS_PATH . "includes/elements/$element_name.php";

			// Construct element class name from element name to avoid having to get all declared classes
			$class_name = str_replace( '-', '_', $element_name );
			$class_name = ucwords( $class_name, '_' );
			$class_name = "Bricks\\Element_$class_name";

			// Register all elements in builder & frontend
			self::register_element( $file, $element_name, $class_name );
		}
	}

	/**
	 * Get mandatory elements
	 *
	 * @since 2.0.2
	 */
	public static function mandatory_elements() {
		return [
			'container',
			'posts',
			'post-comments',
		];
	}

	/**
	 * Get element manager data
	 *
	 * @since 2.0.2
	 */
	public static function manager() {
		$manager = get_option( BRICKS_DB_ELEMENT_MANAGER, [] );

		// Rectify element status in case user disabled it in previous version (@since 2.0.2)
		$mandatory_elements = self::mandatory_elements();
		foreach ( $mandatory_elements as $element_name ) {
			if ( isset( $manager[ $element_name ]['status'] ) && $manager[ $element_name ]['status'] !== 'active' ) {
				// Set mandatory element status to 'active'
				$manager[ $element_name ]['status'] = 'active';
			}
		}

		return $manager;
	}

	/**
	 * Register element (built-in and custom elements via child theme)
	 *
	 * Element 'name' and 'class' only to load element on frontend when requested.
	 */
	public static function register_element( $file, $element_name = '', $element_class_name = '' ) {
		if ( ! is_readable( $file ) ) {
			return;
		}

		require_once $file;

		// Get element class (= last declared class) if not defined (e.g. custom elements)
		if ( empty( $element_class_name ) || ! class_exists( $element_class_name ) ) {
			$get_declared_classes = get_declared_classes();
			$element_class_name   = end( $get_declared_classes );
		}

		$element_label = '';

		// Init element to get element name
		if ( empty( $element_name ) ) {
			$element_instance = new $element_class_name();
			$element_name     = $element_instance->name;
			$element_label    = $element_instance->get_label();
		}

		// Store elements
		self::$elements[ $element_name ] = [
			'class' => $element_class_name,
			'name'  => $element_name,
			'label' => $element_label,
		];
	}

	/**
	 * Load elements on 'wp' hook to get post_id for controls, etc.
	 */
	public static function load_elements() {
		foreach ( self::$elements as $element_name => $element ) {
			self::load_element( $element_name );
		}
	}

	public static function load_element( $element_name ) {
		// Skip if element doesn't exists
		if ( ! isset( self::$elements[ $element_name ] ) ) {
			return;
		}

		$element_class_name = self::$elements[ $element_name ]['class'];

		// Initialize element class
		$element_instance = new $element_class_name();

		// Set controls
		$element_instance->load();

		$controls = $element_instance->controls;

		// Control 'tab' not defined: Set to 'content' (@since 1.5)
		foreach ( $controls as $index => $control ) {
			if ( empty( $controls[ $index ]['tab'] ) ) {
				$controls[ $index ]['tab'] = 'content';
			}
		}

		$control_groups = $element_instance->control_groups;

		// Control group 'tab' not defined: Set to 'content' (@since 1.5)
		foreach ( $control_groups as $index => $control ) {
			if ( empty( $control_groups[ $index ]['tab'] ) ) {
				$control_groups[ $index ]['tab'] = 'content';
			}
		}

		self::$elements[ $element_instance->name ] = [
			'class'            => $element_class_name,
			'name'             => $element_instance->name,
			'icon'             => $element_instance->icon,
			'category'         => $element_instance->category,
			'label'            => $element_instance->label,
			'keywords'         => $element_instance->keywords,
			'tag'              => $element_instance->tag,
			'controls'         => $controls,
			'controlGroups'    => $control_groups,
			'scripts'          => $element_instance->scripts,
			'block'            => $element_instance->block ? $element_instance->block : null,
			'draggable'        => $element_instance->draggable,
			'deprecated'       => $element_instance->deprecated,
			'panelCondition'   => $element_instance->panel_condition,

			// @since 1.5 (= Nestable element)
			'nestable'         => $element_instance->nestable,
			'nestableItem'     => $element_instance->nestable_item,
			'nestableChildren' => $element_instance->nestable_children,

			// @since 1.11.1 (Masonry layout element)
			'supportMasonry'   => $element_instance->support_masonry,
		];

		/**
		 * Rendered HTML output for nestable non-layout elements (slider, accordion, tabs, etc.)
		 *
		 * To use inside BricksNestable.vue on mount()
		 *
		 * @since 1.5
		 */

		// Use specific Vue component to render element on canvas (@since 1.5)
		if ( $element_instance->vue_component ) {
			self::$elements[ $element_instance->name ]['component'] = $element_instance->vue_component;
		}

		// To distinguish non-layout nestables (slider-nested, etc.) in Vue render (@since 1.5)
		if ( ! $element_instance->is_layout_element() ) {
			self::$elements[ $element_instance->name ]['nestableHtml'] = $element_instance->nestable_html;
		}

		// Nestable element (@since 1.5)
		if ( $element_instance->nestable ) {
			// Always run certain scripts
			self::$elements[ $element_instance->name ]['scripts'][] = 'bricksBackgroundVideoInit';
		}

		// Provide 'attributes' data in builder
		if ( count( $element_instance->attributes ) ) {
			self::$elements[ $element_instance->name ]['attributes'] = $element_instance->attributes;
		}

		// Enqueue elements scripts in the builder iframe
		if ( bricks_is_builder_iframe() ) {
			$element_instance->enqueue_scripts();
		}
	}

	/**
	 * Get specific element
	 *
	 * @param array  $element Array containing all element data. Use to retrieve element name.
	 * @param string $element_property String to retrieve specific element data. Such as 'controls' for CSS string generation.
	 */
	public static function get_element( $element, $element_property = '' ) {
		$element_name = $element['name'];

		// Check if element is loaded by checking for 'controls' property, which is only set after element is loaded
		$element_loaded = isset( self::$elements[ $element_name ]['controls'] ) ? true : false;

		if ( ! $element_loaded ) {
			self::load_element( $element_name );
		}

		// Check if element exists
		if ( ! isset( self::$elements[ $element_name ] ) ) {
			return [];
		}

		return $element_property ? self::$elements[ $element_name ][ $element_property ] : self::$elements[ $element_name ];
	}
}
