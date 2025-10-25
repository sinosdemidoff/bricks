<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Element and popup interactions
 *
 * @since 1.6
 */
class Interactions {
	public static $global_class_interactions = [];
	public static $control_options           = [];

	public function __construct() {
		// Add interaction attributes to root node
		add_filter( 'bricks/element/set_root_attributes', [ $this, 'add_data_attributes' ], 10, 2 );

		// Add popup interactions (template settings) to popup root
		add_filter( 'bricks/popup/attributes', [ $this, 'add_to_template_root' ], 10, 2 );

		add_action( 'init', [ $this, 'set_controls' ], 10 );

		self::get_global_class_interactions();
	}

	/**
	 * Get interaction controls
	 *
	 * @return array
	 *
	 * @since 1.6
	 */
	public static function get_controls_data() {
		return self::$control_options;
	}

	/**
	 * Set interaction controls once initially
	 *
	 * @since 1.6.2
	 *
	 * @return void
	 */
	public function set_controls() {
		$trigger_options = [
			'elementGroupTitle' => esc_html__( 'Element', 'bricks' ),
			'click'             => esc_html__( 'Click', 'bricks' ),
			'mouseover'         => esc_html__( 'Hover', 'bricks' ),
			'focus'             => esc_html__( 'Focus', 'bricks' ),
			'blur'              => esc_html__( 'Blur', 'bricks' ),
			'mouseenter'        => esc_html__( 'Mouse enter', 'bricks' ),
			'mouseleave'        => esc_html__( 'Mouse leave', 'bricks' ),
			'enterView'         => esc_html__( 'Enter viewport', 'bricks' ),
			'leaveView'         => esc_html__( 'Leave viewport', 'bricks' ),
			'animationEnd'      => esc_html__( 'Animation end', 'bricks' ),
			'ajaxStart'         => esc_html__( 'Query', 'bricks' ) . ' ' . esc_html__( 'AJAX loader', 'bricks' ) . ' (' . esc_html__( 'Start', 'bricks' ) . ')',
			'ajaxEnd'           => esc_html__( 'Query', 'bricks' ) . ' ' . esc_html__( 'AJAX loader', 'bricks' ) . ' (' . esc_html__( 'End', 'bricks' ) . ')',
			'formSubmit'        => esc_html__( 'Form', 'bricks' ) . ' ' . esc_html__( 'Submit', 'bricks' ),
			'formSuccess'       => esc_html__( 'Form', 'bricks' ) . ' ' . esc_html__( 'Success', 'bricks' ),
			'formError'         => esc_html__( 'Form', 'bricks' ) . ' ' . esc_html__( 'Error', 'bricks' ),
		];

		// Interactions for browser/window events
		$trigger_options['browserGroupTitle'] = esc_html__( 'Browser', 'bricks' ) . ' / ' . esc_html__( 'Window', 'bricks' );
		$trigger_options['scroll']            = esc_html__( 'Scroll', 'bricks' );
		$trigger_options['contentLoaded']     = esc_html__( 'Content loaded', 'bricks' );
		$trigger_options['mouseleaveWindow']  = esc_html__( 'Mouse leave window', 'bricks' );

		// Interactions for Query filter (@since 1.11)
		if ( Helpers::enabled_query_filters() ) {
			$trigger_options['filterGroupTitle']     = esc_html__( 'Query filters', 'bricks' );
			$trigger_options['filterOptionEmpty']    = esc_html__( 'Filter', 'bricks' ) . ': ' . esc_html__( 'Empty', 'bricks' );
			$trigger_options['filterOptionNotEmpty'] = esc_html__( 'Filter', 'bricks' ) . ': ' . esc_html__( 'Not empty', 'bricks' );
		}

		// Interactions for WooCommerce events (@since 2.0)
		if ( Woocommerce::$is_active ) {
			$trigger_options['wooGroupTitle']      = 'WooCommerce';
			$trigger_options['wooAddedToCart']     = esc_html__( 'Added to cart', 'bricks' );
			$trigger_options['wooAddingToCart']    = esc_html__( 'Adding to cart', 'bricks' );
			$trigger_options['wooRemovedFromCart'] = esc_html__( 'Removed from cart', 'bricks' );
			$trigger_options['wooUpdateCart']      = esc_html__( 'Cart updated', 'bricks' );
			$trigger_options['wooCouponApplied']   = esc_html__( 'Coupon applied', 'bricks' );
			$trigger_options['wooCouponRemoved']   = esc_html__( 'Coupon removed', 'bricks' );
		}

		// STEP: Add interaction controls (= repeater)
		self::$control_options = [
			'type'          => 'repeater',
			'titleProperty' => 'trigger',
			'titleEditable' => true,
			'placeholder'   => esc_html__( 'Interaction', 'bricks' ),
			'fields'        => [
				// Show Interaction ID for copy & paste to other interactions
				'id'                            => [
					'label'          => esc_html__( 'ID', 'bricks' ),
					'type'           => 'text',
					'clearable'      => false,
					'inline'         => true,
					'readonly'       => true,
					'hasDynamicData' => false,
					'copyable'       => true, // NOTE: Undocumented
				],

				'trigger'                       => [
					'label'       => esc_html__( 'Trigger', 'bricks' ),
					'type'        => 'select',
					'options'     => $trigger_options,
					'placeholder' => esc_html__( 'Select', 'bricks' ),
				],

				// @since 2.0
				'disablePreventDefault'         => [
					'label'    => esc_html__( 'Disable', 'bricks' ) . ': preventDefault',
					'type'     => 'checkbox',
					'required' => [ 'trigger', '=', 'click' ],
					'desc'     => esc_html__( 'Allow the element\'s normal click behavior (like following links) instead of blocking it.', 'bricks' ),
				],

				// @since 2.0
				'rootMargin'                    => [
					'label'       => 'rootMargin',
					'type'        => 'text',
					'placeholder' => '0px 0px 0px 0px',
					'required'    => [ 'trigger', '=', 'enterView' ],
					'desc'        =>
					// translators: %s is the value for root margin
					sprintf( esc_html__( 'To trigger the interaction when 50%% of the element is in the viewport, set the rootMargin to %s.', 'bricks' ), '<a href="https://developer.mozilla.org/en-US/docs/Web/API/IntersectionObserver/rootMargin" target="_blank"><code>0px 0px -50% 0px</code></a>' ),
				],

				'ajaxQueryId'                   => [
					'label'    => esc_html__( 'Query', 'bricks' ),
					'type'     => 'query-list',
					'required' => [ 'trigger', '=', [ 'ajaxStart', 'ajaxEnd' ] ],
				],

				'filterElementId'               => [
					'label'       => esc_html__( 'Filter', 'bricks' ) . ' (Bricks ID)',
					'type'        => 'text',
					'placeholder' => 'ah1yh' ,
					'required'    => [ 'trigger', '=', [ 'filterOptionEmpty', 'filterOptionNotEmpty' ] ],
					'description' => sprintf(
						'%s: %s, %s, %s, %s, %s, %s, %s',
						esc_html__( 'Supported filters', 'bricks' ),
						esc_html__( 'Active filters', 'bricks' ),
						esc_html__( 'Checkbox', 'bricks' ),
						esc_html__( 'Datepicker', 'bricks' ),
						esc_html__( 'Radio', 'bricks' ),
						esc_html__( 'Range', 'bricks' ),
						esc_html__( 'Search', 'bricks' ),
						esc_html__( 'Select', 'bricks' )
					),
				],

				'formId'                        => [
					'label'       => esc_html__( 'Form', 'bricks' ) . ' ' . esc_html__( 'ID', 'bricks' ),
					'type'        => 'text',
					'placeholder' => '#brxe-ah1yh' ,
					'required'    => [ 'trigger', '=', [ 'formSubmit', 'formSuccess', 'formError' ] ],
				],

				'delay'                         => [
					'label'       => esc_html__( 'Delay' ),
					'type'        => 'text',
					'placeholder' => '0s',
					'required'    => [ 'trigger', '=', [ 'contentLoaded' ] ],
				],

				'scrollOffset'                  => [
					'label'    => esc_html__( 'Scroll offset', 'bricks' ),
					'type'     => 'text',
					'required' => [ 'trigger', '=', 'scroll' ],
				],

				/**
				 * To hold the target interaction id for animationEnd trigger.
				 * Leave it empty and will use the previous interaction id where action is startAnimation
				 *
				 * @since 1.8.3
				 */
				'animationId'                   => [
					'label'       => esc_html__( 'Target interaction ID', 'bricks' ),
					'type'        => 'text',
					'placeholder' => esc_html__( 'Previous interaction ID', 'bricks' ),
					'info'        => esc_html__( 'Not allowed', 'bricks' ) . ': ' . esc_html__( 'Current interaction ID', 'bricks' ),
					'required'    => [ 'trigger', '=', 'animationEnd' ],
				],

				'action'                        => [
					'label'       => esc_html__( 'Action', 'bricks' ),
					'type'        => 'select',
					'options'     => [
						'show'            => esc_html__( 'Show element', 'bricks' ),
						'hide'            => esc_html__( 'Hide element', 'bricks' ),
						'setAttribute'    => esc_html__( 'Set attribute', 'bricks' ),
						'removeAttribute' => esc_html__( 'Remove attribute', 'bricks' ),
						'toggleAttribute' => esc_html__( 'Toggle attribute', 'bricks' ),
						'toggleOffCanvas' => esc_html__( 'Toggle offcanvas', 'bricks' ),
						'loadMore'        => esc_html__( 'Load more', 'bricks' ) . ' (' . esc_html__( 'Query loop', 'bricks' ) . ')',
						'startAnimation'  => esc_html__( 'Start animation', 'bricks' ),
						'scrollTo'        => esc_html__( 'Scroll to', 'bricks' ),
						'javascript'      => 'JavaScript ' . esc_html__( '(Function)', 'bricks' ),
						'openAddress'     => esc_html__( 'Open address' ) . ' (' . esc_html__( 'Map', 'bricks' ) . ')', // (@since 2.0)
						'closeAddress'    => esc_html__( 'Close address' ) . ' (' . esc_html__( 'Map', 'bricks' ) . ')', // (@since 2.0)
						'clearForm'       => esc_html__( 'Clear form', 'bricks' ), // (@since 2.0)
						'storageAdd'      => esc_html__( 'Browser storage', 'bricks' ) . ': ' . esc_html__( 'Add', 'bricks' ),
						'storageRemove'   => esc_html__( 'Browser storage', 'bricks' ) . ': ' . esc_html__( 'Remove', 'bricks' ),
						'storageCount'    => esc_html__( 'Browser storage', 'bricks' ) . ': ' . esc_html__( 'Count', 'bricks' ),
					],
					'placeholder' => esc_html__( 'Select', 'bricks' ),
				],

				'storageType'                   => [
					'label'       => esc_html__( 'Type', 'bricks' ),
					'type'        => 'select',
					'options'     => [
						'storageGroupTitle' => esc_html__( 'Browser storage', 'bricks' ),
						'windowStorage'     => esc_html__( 'Window storage', 'bricks' ),
						'sessionStorage'    => esc_html__( 'Session storage', 'bricks' ),
						'localStorage'      => esc_html__( 'Local storage', 'bricks' ),
					],
					'placeholder' => esc_html__( 'Select', 'bricks' ),
					'required'    => [ 'action', '=', [ 'storageAdd', 'storageRemove', 'storageCount' ] ],
				],

				'actionAttributeKey'            => [
					'label'    => esc_html__( 'Key', 'bricks' ),
					'type'     => 'text',
					'required' => [ 'action', '=', [ 'setAttribute', 'removeAttribute', 'toggleAttribute', 'storageAdd', 'storageRemove', 'storageCount' ] ],
				],

				'actionAttributeValue'          => [
					'label'    => esc_html__( 'Value', 'bricks' ),
					'type'     => 'text',
					'required' => [ 'action', '=', [ 'setAttribute', 'removeAttribute', 'toggleAttribute', 'storageAdd' ] ],
				],

				'loadMoreQuery'                 => [
					'label'    => esc_html__( 'Query', 'bricks' ),
					'type'     => 'query-list',
					'required' => [ 'action', '=', 'loadMore' ],
				],

				'animationType'                 => [
					'label'       => esc_html__( 'Animation', 'bricks' ),
					'type'        => 'select',
					'options'     => Setup::get_control_options( 'animationTypes' ),
					'searchable'  => true,
					'inline'      => true,
					'placeholder' => esc_html__( 'None', 'bricks' ),
					'required'    => [ 'action', '=', 'startAnimation' ],
				],

				'animationDuration'             => [
					'label'          => esc_html__( 'Animation duration', 'bricks' ),
					'type'           => 'text',
					'hasDynamicData' => false,
					'hasVariables'   => true,
					'placeholder'    => '1s',
					'required'       => [ 'action', '=', 'startAnimation' ],
				],

				'animationDelay'                => [
					'label'          => esc_html__( 'Animation delay', 'bricks' ),
					'type'           => 'text',
					'inline'         => true,
					'hasDynamicData' => false,
					'hasVariables'   => true,
					'placeholder'    => '0s',
					'required'       => [ 'action', '=', 'startAnimation' ],
				],

				// Target Form ID (@since 2.0)
				'targetFormSelector'            => [
					'label'       => esc_html__( 'Target form selector', 'bricks' ) ,
					'type'        => 'text',
					'placeholder' => 'form', // If no selector provided, it will clear all forms
					'required'    => [
						[ 'action', '=', [ 'clearForm' ] ],
						[ 'trigger', '!=', [ 'formSubmit', 'formSuccess', 'formError' ] ],
					],
				],

				'target'                        => [
					'label'       => esc_html__( 'Target', 'bricks' ),
					'type'        => 'select',
					'options'     => [
						'self'   => esc_html__( 'Self', 'bricks' ),
						'custom' => esc_html__( 'CSS selector', 'bricks' ),
						'popup'  => esc_html__( 'Popup', 'bricks' ),
					],
					'placeholder' => esc_html__( 'Self', 'bricks' ),
					'required'    => [ 'action', '!=', [ 'loadMore', 'storageAdd', 'storageRemove', 'storageCount', 'toggleOffCanvas', 'openAddress', 'closeAddress', 'clearForm' ] ],
				],

				'targetSelector'                => [
					'label'    => esc_html__( 'CSS selector', 'bricks' ),
					'type'     => 'text',
					'required' => [
						[ 'target', '=', 'custom' ],
						[ 'action', '!=', [ 'loadMore', 'storageAdd', 'storageRemove', 'storageCount', 'toggleOffCanvas', 'openAddress', 'closeAddress' ] ],
					],
				],

				'toggleOffCanvasInfo'           => [
					'type'     => 'info',
					'content'  => esc_html__( 'Do not set this interaction on a Toggle element.', 'bricks' ),
					'required' => [ 'action', '=', 'toggleOffCanvas' ],
				],

				'offCanvasSelector'             => [
					'label'       => esc_html__( 'Offcanvas', 'bricks' ) . ': ' . esc_html__( 'CSS selector', 'bricks' ),
					'type'        => 'text',
					'placeholder' => '#brxe-off123',
					'required'    => [ 'action', '=', 'toggleOffCanvas' ],
				],

				'scrollToOffset'                => [
					'label'    => esc_html__( 'Scroll to', 'bricks' ) . ': ' . esc_html__( 'Offset', 'bricks' ) . ' (px)',
					'type'     => 'number',
					'required' => [ 'action', '=', [ 'scrollTo' ] ],
				],

				'scrollToDelay'                 => [
					'label'    => esc_html__( 'Scroll to', 'bricks' ) . ': ' . esc_html__( 'Delay', 'bricks' ) . ' (ms)',
					'type'     => 'number',
					'required' => [ 'action', '=', [ 'scrollTo' ] ],
				],

				'templateId'                    => [
					'label'       => esc_html__( 'Popup', 'bricks' ),
					'type'        => 'select',
					'options'     => bricks_is_builder() ? Templates::get_templates_list( 'popup' ) : [],
					'searchable'  => true,
					'placeholder' => esc_html__( 'Select template', 'bricks' ),
					'required'    => [
						[ 'target', '=', 'popup' ],
						[ 'action', '!=', [ 'openAddress', 'closeAddress' ] ],
					],
				],

				'infoBoxId'                     => [
					'label'       => esc_html__( 'Info Box', 'bricks' ),
					'type'        => 'select',
					'options'     => bricks_is_builder() ? Templates::get_templates_list( 'infobox' ) : [],
					'searchable'  => true,
					'placeholder' => esc_html__( 'Select template', 'bricks' ),
					'required'    => [ 'action', '=', [ 'openAddress', 'closeAddress' ] ],
				],

				// @since 1.9.4
				'popupContextType'              => [
					'label'       => esc_html__( 'Context type', 'bricks' ),
					'type'        => 'select',
					'options'     => [
						'post' => esc_html__( 'Post', 'bricks' ),
						'term' => esc_html__( 'Term', 'bricks' ),
						'user' => esc_html__( 'User', 'bricks' ),
					],
					'description' => esc_html__( 'Set if your dynamic data in AJAX popup is rendered incorrectly.', 'bricks' ),
					'placeholder' => esc_html__( 'Post', 'bricks' ),
					'required'    => [
						[ 'action', '=', 'show' ],
						[ 'target', '=', 'popup' ],
					],
				],

				// @since 1.9.4
				'popupContextId'                => [
					'label'       => esc_html__( 'Context ID', 'bricks' ),
					'type'        => 'text',
					'description' => esc_html__( 'Set if your dynamic data in AJAX popup is rendered incorrectly.', 'bricks' ),
					'required'    => [
						[ 'action', '=', 'show' ],
						[ 'target', '=', 'popup' ],
					],
				],

				// @since 1.9.5
				'jsFunction'                    => [
					'label'       => esc_html__( 'Function name', 'bricks' ) . ' (JavaScript)',
					'type'        => 'text',
					'placeholder' => 'myFunction',
					'required'    => [ 'action', '=', 'javascript' ],
					'description' => esc_html__( 'JavaScript function name without parentheses or window object.', 'bricks' ) . ' ' . Helpers::article_link( 'interactions/#javascript', esc_html__( 'Learn more', 'bricks' ) ),
				],

				// @since 1.9.5
				'jsFunctionArgs'                => [
					'label'         => esc_html__( 'Arguments', 'bricks' ),
					'type'          => 'repeater',
					'titleProperty' => 'jsFunctionArg',
					'fields'        => [
						'jsFunctionArg' => [
							'label' => esc_html__( 'Argument', 'bricks' ),
							'type'  => 'text',
						],
					],
					'default'       => [
						[
							'jsFunctionArg' => '%brx%',
						],
					],
					'required'      => [ 'action', '=', 'javascript' ],
					'desc'          => sprintf(
						__( 'Use %s to pass data such as the source and target elements to your custom function.', 'bricks' ),
						'<strong>%brx%</strong>'
					),
				],

				'runOnce'                       => [
					'label'    => esc_html__( 'Run only once', 'bricks' ),
					'type'     => 'checkbox',
					'required' => [ 'trigger', '!=', [ 'contentLoaded' ] ],
				],

				'conditionsSep'                 => [
					'label'       => esc_html__( 'Conditions', 'bricks' ),
					'description' => esc_html__( 'Run this interaction if the following conditions are met.', 'bricks' ),
					'type'        => 'separator',
				],

				'interactionConditions'         => [
					'type'          => 'repeater',
					'placeholder'   => esc_html__( 'Condition', 'bricks' ),
					'titleProperty' => 'conditionType',
					'fields'        => [
						'conditionType'       => [
							'label'   => esc_html__( 'Type', 'bricks' ),
							'type'    => 'select',
							'options' => [
								'storageGroupTitle' => esc_html__( 'Browser storage', 'bricks' ),
								'windowStorage'     => esc_html__( 'Window storage', 'bricks' ),
								'sessionStorage'    => esc_html__( 'Session storage', 'bricks' ),
								'localStorage'      => esc_html__( 'Local storage', 'bricks' ),
							]
						],

						'storageKey'          => [
							'label'    => esc_html__( 'Browser storage', 'bricks' ) . ': ' . esc_html__( 'Key', 'bricks' ),
							'type'     => 'text',
							'required' => [ 'conditionType', '=', [ 'windowStorage', 'sessionStorage', 'localStorage' ] ],
						],

						'storageCompare'      => [
							'label'       => esc_html__( 'Browser storage', 'bricks' ) . ': ' . esc_html__( 'Compare', 'bricks' ),
							'type'        => 'select',
							'options'     => [
								'exists'    => esc_html__( 'Exists', 'bricks' ),
								'notExists' => esc_html__( 'Not exists', 'bricks' ),
								'=='        => '==',
								'!='        => '!=',
								'>='        => '>=',
								'<='        => '<=',
								'>'         => '>',
								'<'         => '<',
							],
							'placeholder' => esc_html__( 'Exists', 'bricks' ),
							'required'    => [ 'conditionType', '=', [ 'windowStorage', 'sessionStorage', 'localStorage' ] ],
						],

						'storageCompareValue' => [
							'label'    => esc_html__( 'Browser storage', 'bricks' ) . ': ' . esc_html__( 'Value', 'bricks' ),
							'type'     => 'text',
							'required' => [ 'storageCompare', '!=', [ '', 'exists', 'notExists' ] ],
						],
					]
				],

				'interactionConditionsRelation' => [
					'label'       => esc_html__( 'Relation', 'bricks' ),
					'type'        => 'select',
					'inline'      => true,
					'options'     => [
						'or'  => esc_html__( 'Or', 'bricks' ),
						'and' => esc_html__( 'And', 'bricks' ),
					],
					'placeholder' => esc_html__( 'And', 'bricks' )
				],
			],
		];
	}

	/**
	 * Get global classes with interaction settings (once initially) to merge with element setting interactions in add_data_attributes()
	 *
	 * @since 1.6
	 */
	public static function get_global_class_interactions() {
		if ( ! empty( self::$global_class_interactions ) ) {
			return;
		}

		$global_classes = Database::$global_data['globalClasses'];

		if ( empty( $global_classes ) ) {
			return;
		}

		foreach ( $global_classes as $global_class ) {
			$class_interactions = ! empty( $global_class['settings']['_interactions'] ) ? $global_class['settings']['_interactions'] : false;

			if ( $class_interactions ) {
				self::$global_class_interactions[ $global_class['id'] ] = $class_interactions;
			}
		}
	}

	/**
	 * Add element interactions via HTML data attributes to element root node
	 *
	 * Can originate from global class and/or element settings.
	 *
	 * @since 1.6
	 */
	public function add_data_attributes( $attributes, $element ) {
		$interactions = [];

		// STEP: Element class interactions
		$class_ids = ! empty( $element->settings['_cssGlobalClasses'] ) ? $element->settings['_cssGlobalClasses'] : false;

		if ( is_array( $class_ids ) ) {
			foreach ( $class_ids as $class_id ) {
				if ( ! empty( self::$global_class_interactions[ $class_id ] ) ) {
					$interactions = array_merge( self::$global_class_interactions[ $class_id ], $interactions );
				}
			}
		}

		// STEP: Element setting interactions
		if ( ! empty( $element->settings['_interactions'] ) ) {
			// Parse dynamic data
			$element_interactions = map_deep( $element->settings['_interactions'], [ 'Bricks\Integrations\Dynamic_Data\Providers', 'render_content' ] );

			$interactions = array_merge( $element_interactions, $interactions );
		}

		// STEP: Add interaction data attributes to element
		if ( count( $interactions ) ) {
			// @since 1.7 - Add attribute to element if it should be hidden initially
			$hidden_on_load = false;

			foreach ( $interactions as $index => $element_interactions ) {
				// Interaction data modification for toggleOffCanvas (@since 1.11)
				if ( isset( $element_interactions['action'] ) && $element_interactions['action'] === 'toggleOffCanvas' ) {
					// Not allowed to set toggleOffCanvas interaction on toggle element
					if ( $element->name === 'toggle' ) {
						unset( $interactions[ $index ] );
						continue;
					}

					$interactions[ $index ]['target'] = 'offcanvas';
				}

				// Interaction data modification for loadMore (@since 1.12.2)
				if ( isset( $element_interactions['action'] ) && $element_interactions['action'] === 'loadMore' ) {
					$load_more_query_id = isset( $element_interactions['loadMoreQuery'] ) ? $element_interactions['loadMoreQuery'] : 'main';

					// Retrieve main_query_id from Database class (@since 1.12.2)
					$main_query_id = (string) Database::$main_query_id;

					// Only replace the actual query id if main_query_id is set and loadMoreQuery is 'main'
					if ( $main_query_id !== '' && $load_more_query_id === 'main' ) {
						$interactions[ $index ]['loadMoreQuery'] = $main_query_id;
					}

					// Support Load More inside component
					if ( $load_more_query_id !== 'main' && isset( $element->element['instanceId'] ) && ! empty( $element->element['instanceId'] ) ) {
						$interactions[ $index ]['loadMoreQuery'] = $load_more_query_id . '-' . $element->element['instanceId'];
					}
				}

				// Early exit if action is not startAnimation
				if ( isset( $element_interactions['action'] ) && $element_interactions['action'] !== 'startAnimation' ) {
					continue;
				}

				$target = ! empty( $element_interactions['target'] ) ? $element_interactions['target'] : 'self';
				// Early exit if target is not self
				if ( $target != 'self' ) {
					continue;
				}

				$animation_type = $element_interactions['animationType'] ?? false;

				// Early exit if animation type is not set
				if ( ! $animation_type ) {
					continue;
				}

				// Only set hidden_on_load to true if animationType contains 'In' (case sensitive; e.g.: 'fadeInUp', 'slideInUp', etc.)
				if ( stripos( $animation_type, 'In' ) !== false ) {
					$hidden_on_load = true;

					// Exit foreach loop as long as one interaction needs to hide the element initially
					break;
				}
			}

			// Set attributes after modification in foreach loop logic above (@since 1.11)
			$attributes['data-interactions'] = htmlspecialchars( wp_json_encode( $interactions ) );

			$attributes['data-interaction-id'] = Helpers::generate_random_id( false );

			if ( $hidden_on_load ) {
				$attributes['data-interaction-hidden-on-load'] = true;
			}

			// Interaction has animation: Enqueue animate.csss
			if ( strpos( $attributes['data-interactions'], 'startAnimation' ) !== false ) {
				wp_enqueue_style( 'bricks-animate' );
			}

			// Interaction has toggleOffcanvas: Add attributes (@since 1.11)
			if ( strpos( $attributes['data-interactions'], 'toggleOffCanvas' ) !== false ) {
				// For frontend bricksOffcanvas JavaScript
				$attributes['data-brx-toggle-offcanvas'] = 'true';

				// Set the element focusable
				if ( ! isset( $attributes['tabindex'] ) ) {
					$attributes['tabindex'] = '0';
				}

				// Set aria attributes
				if ( ! isset( $attributes['aria-expanded'] ) ) {
					$attributes['aria-expanded'] = 'false';
				}

				// Set aria-label (Note: always Open, no option to change or user can set on the attribute itself)
				if ( ! isset( $attributes['aria-label'] ) ) {
					$attributes['aria-label'] = esc_html__( 'Open', 'bricks' );
				}
			}

			// Add interaction loop attributes for JavaScript logic (@since 1.7.1)
			$looping_query_id = Query::is_any_looping();

			if ( $looping_query_id ) {
				// Unique identifier for each element in query loop to avoid targeting the wrong elements (popups, etc.) (@since 1.8.4)
				$attributes['data-interaction-loop-id'] = Query::get_looping_unique_identifier( 'interaction' );
			}
		}

		return $attributes;
	}

	/**
	 * Add template (e.g. popup) interaction settings to template root node
	 *
	 * @since 1.6
	 */
	public function add_to_template_root( $attributes, $template_id ) {
		$template_settings = Helpers::get_template_settings( $template_id );

		if ( ! empty( $template_settings['template_interactions'] ) ) {
			// STEP: Parse dynamic data
			$interactions = map_deep( $template_settings['template_interactions'], [ 'Bricks\Integrations\Dynamic_Data\Providers', 'render_content' ] );

			// Ensure animation is enqueued even if the page has no data-interactions (@since 1.8)
			$json_interactions = wp_json_encode( $interactions );

			if ( strpos( $json_interactions, 'startAnimation' ) !== false ) {
				wp_enqueue_style( 'bricks-animate' );
			}

			$attributes['data-interactions'] = htmlspecialchars( $json_interactions );

			$attributes['data-interaction-id'] = Helpers::generate_random_id( false );
		}

		return $attributes;
	}
}
