<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Nav_Nested extends Element {
	public $category = 'general';
	public $name     = 'nav-nested';
	public $icon     = 'ti-menu';
	public $tag      = 'nav';
	public $scripts  = [ 'bricksNavNested', 'bricksSubmenuListeners', 'bricksSubmenuPosition' ];
	public $nestable = true;

	public function get_label() {
		return esc_html__( 'Nav', 'bricks' ) . ' (' . esc_html__( 'Nestable', 'bricks' ) . ')';
	}

	public function get_keywords() {
		return [ 'menu', 'nestable' ];
	}

	public function set_control_groups() {
		$this->control_groups['item'] = [
			'title' => esc_html__( 'Top level', 'bricks' ) . ' (' . esc_html__( 'Item', 'bricks' ) . ')',
		];

		$this->control_groups['dropdown'] = [
			'title' => esc_html__( 'Dropdown', 'bricks' ),
		];

		$this->control_groups['mobile-menu'] = [
			'title' => esc_html__( 'Mobile menu', 'bricks' ),
		];
	}

	public function set_controls() {
		// Apply transitions to menu items (@since 1.8.2)
		$controls['_cssTransition']['css'] = [
			[
				'property' => 'transition',
				'selector' => '.menu-item',
			],
			[
				'property' => 'transition',
				'selector' => '.menu-item a',
			],
			[
				'property' => 'transition',
				'selector' => '.brx-submenu-toggle > *',
			],
			[
				'property' => 'transition',
				'selector' => '.brxe-dropdown',
			],
			[
				'property' => 'transition',
				'selector' => '.brx-dropdown-content a',
			],
		];

		$controls['tag'] = [
			'label'          => esc_html__( 'HTML tag', 'bricks' ),
			'type'           => 'text',
			'lowercase'      => true,
			'inline'         => true,
			'fullAccess'     => true,
			'hasDynamicData' => false,
			'validate'       => $this->get_in_builder_html_tag_validation_rules(),
			'placeholder'    => 'nav',
		];

		$controls['ariaLabel'] = [
			'label'          => 'aria-label',
			'type'           => 'text',
			'inline'         => true,
			'hasDynamicData' => false,
			'placeholder'    => esc_html__( 'Menu', 'bricks' ),
		];

		// TOP LEVEL (ITEM)

		$controls['gap'] = [
			'group' => 'item',
			'label' => esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => '.brx-nav-nested-items',
				],
			],
		];

		$controls['itemJustifyContent'] = [
			'deprecated'  => true, // @since 1.10
			'group'       => 'item',
			'label'       => esc_html__( 'Justify content', 'bricks' ),
			'description' => esc_html__( 'Set "Align items" under "Mobile menu" instead.', 'bricks' ),
			'type'        => 'justify-content',
			'direction'   => 'row',
			'exclude'     => [
				'space',
			],
			'inline'      => true,
		];

		$controls['itemPadding'] = [
			'group' => 'item',
			'label' => esc_html__( 'Padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'padding',
					'selector' => '.brx-nav-nested-items > li > a',
				],
				// @since 2.0: Target text link only (without the link)
				[
					'property' => 'padding',
					'selector' => '.brx-nav-nested-items > li > .brxe-text-link:not(a)',
				],
				// @since 2.0: Target icon only (without the link wrapper)
				[
					'property' => 'padding',
					'selector' => '.brx-nav-nested-items > li > .brxe-icon',
				],
				// @since 2.0: Target svg only (without the link wrapper)
				[
					'property' => 'padding',
					'selector' => '.brx-nav-nested-items > li{pseudo}:has( > .brxe-svg)',
				],
				[
					'property' => 'padding',
					'selector' => '.brx-nav-nested-items > li > .brx-submenu-toggle > *',
				],
				// Close mobile menu toggle
				[
					'property' => 'padding',
					'selector' => '&.brx-open .brx-nav-nested-items > li > button.brx-toggle-div',
				],
			],
		];

		$controls['itemBackgroundColor'] = [
			'group' => 'item',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.brx-nav-nested-items > li{pseudo} > a',
				],
				// @since 2.0: Target text link only (without the link)
				[
					'property' => 'background-color',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-text-link:not(a)',
				],
				// @since 2.0: Target icon only (without the link wrapper)
				[
					'property' => 'background-color',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-icon',
				],
				// @since 2.0: Target svg only (without the link wrapper)
				[
					'property' => 'background-color',
					'selector' => '.brx-nav-nested-items > li{pseudo}:has( > .brxe-svg)',
				],
				[
					'property' => 'background-color',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brx-submenu-toggle',
				],
			],
		];

		$controls['itemBorder'] = [
			'group' => 'item',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.brx-nav-nested-items > li{pseudo} > a',
				],
				// @since 2.0: Target text link only (without the link)
				[
					'property' => 'border',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-text-link:not(a)',
				],
				// @since 2.0: Target icon only (without the link wrapper)
				[
					'property' => 'border',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-icon',
				],
				// @since 2.0: Target svg only (without the link wrapper)
				[
					'property' => 'border',
					'selector' => '.brx-nav-nested-items > li{pseudo}:has( > .brxe-svg)',
				],
				[
					'property' => 'border',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brx-submenu-toggle',
				],
			],
		];

		$controls['itemTypography'] = [
			'group' => 'item',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.brx-nav-nested-items > li{pseudo} > a',
				],
				// @since 2.0: Target text link only (without the link)
				[
					'property' => 'font',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-text-link:not(a)',
				],
				// @since 2.0: Target icon only (without the link wrapper)
				[
					'property' => 'font',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-icon',
				],
				// @since 2.0: Target svg only (without the link wrapper)
				[
					'property' => 'font',
					'selector' => '.brx-nav-nested-items > li{pseudo}:has( > .brxe-svg)',
				],
				[
					'property' => 'font',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brx-submenu-toggle > *',
				],
			],
		];

		$controls['itemTransform'] = [
			'group' => 'item',
			'label' => esc_html__( 'Transform', 'bricks' ),
			'type'  => 'transform',
			'css'   => [
				[
					'property' => 'transform',
					'selector' => '.brx-nav-nested-items > li{pseudo} > a',
				],
				// @since 2.0: Target text link only (without the link)
				[
					'property' => 'transform',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-text-link:not(a)',
				],
				// @since 2.0: Target icon only (without the link wrapper)
				[
					'property' => 'transform',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-icon',
				],
				// @since 2.0: Target svg only (without the link wrapper)
				[
					'property' => 'transform',
					'selector' => '.brx-nav-nested-items > li{pseudo}:has( > .brxe-svg)',
				],
				[
					'property' => 'transform',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brx-submenu-toggle',
				],
			],
		];

		$controls['itemTransition'] = [
			'group'          => 'item',
			'label'          => esc_html__( 'Transition', 'bricks' ),
			'type'           => 'text',
			'hasDynamicData' => false,
			'inline'         => true,
			'css'            => [
				[
					'property' => 'transition',
					'selector' => '.brx-nav-nested-items > li',
				],
				[
					'property' => 'transition',
					'selector' => '.brx-nav-nested-items > li{pseudo} > a',
				],
				// @since 2.0: Target text link only (without the link)
				[
					'property' => 'transition',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-text-link:not(a)',
				],
				// @since 2.0: Target icon only (without the link wrapper)
				[
					'property' => 'transition',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brxe-icon',
				],
				// @since 2.0: Target svg only (without the link wrapper)
				[
					'property' => 'transition',
					'selector' => '.brx-nav-nested-items > li{pseudo}:has( > .brxe-svg)',
				],
				[
					'property' => 'transition',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brx-submenu-toggle',
				],
				[
					'property' => 'transition',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brx-submenu-toggle > *',
				],
			],
		];

		// ACTIVE LINK (CURRENT PAGE)

		$controls['itemActiveSep'] = [
			'group' => 'item',
			'label' => esc_html__( 'Active', 'bricks' ),
			'type'  => 'separator',
		];

		$controls['itemBackgroundColorActive'] = [
			'group' => 'item',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.brx-nav-nested-items > li{pseudo} > [aria-current="page"]',
				],
				[
					'property' => 'background-color',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brx-submenu-toggle.aria-current',
				],
			],
		];

		$controls['itemBorderActive'] = [
			'group' => 'item',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.brx-nav-nested-items > li{pseudo} > [aria-current="page"]',
				],
				[
					'property' => 'border',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brx-submenu-toggle.aria-current',
				],
			],
		];

		$controls['itemTypographyActive'] = [
			'group' => 'item',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.brx-nav-nested-items > li{pseudo} > [aria-current="page"]',
				],
				[
					'property' => 'font',
					'selector' => '.brx-nav-nested-items > li{pseudo} > .brx-submenu-toggle.aria-current > *',
				],
			],
		];

		// DROPDOWN - TOP LEVEL (@since 2.0)
		$controls['topItemDropdownSep'] = [
			'group'       => 'item',
			'type'        => 'separator',
			'label'       => esc_html__( 'Dropdown', 'bricks' ),
			'description' => esc_html__( 'Only applies to top level dropdown elements.', 'bricks' ),

		];

		// @since 2.0
		$controls['topLevelDropdownTextPadding'] = [
			'group' => 'item',
			'label' => esc_html__( 'Text padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'padding',
					'selector' => '.brx-nav-nested-items > li > .brx-submenu-toggle > span',
				],
			],
		];

		// @since 2.0
		$controls['topLevelDropdownIconPadding'] = [
			'group' => 'item',
			'label' => esc_html__( 'Icon padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'padding',
					'selector' => '.brx-nav-nested-items > li > .brx-submenu-toggle button',
				],
			],
		];

		// @since 2.0
		$controls['topLevelGap'] = [
			'group' => 'item',
			'label' => esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'large' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => '.brx-nav-nested-items > li > .brx-submenu-toggle',
				],
			],
		];

		// DROPDOWN

		// DROPDOWN - TEXT (@since 2.0)
		$controls['dropdownTextSep'] = [
			'group'       => 'dropdown',
			'type'        => 'separator',
			'label'       => esc_html__( 'Text', 'bricks' ),
			'description' => esc_html__( 'Control dropdown element text.', 'bricks' ),

		];

		// @since 2.0
		$controls['dropdownTextPadding'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Text padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				// Apply to all dropdowns (@since 2.0)
				[
					'property' => 'padding',
					'selector' => '.brx-submenu-toggle span',
				],
			],
		];

		// DROPDOWN - ICON

		$controls['iconSep'] = [
			'group'       => 'dropdown',
			'type'        => 'separator',
			'label'       => esc_html__( 'Icon', 'bricks' ),
			'description' => esc_html__( 'Edit dropdown to set icon individually.', 'bricks' ),
		];

		$controls['iconPadding'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Icon padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'padding',
					'selector' => '.brx-submenu-toggle button',
				],
			],
		];

		$controls['iconGap'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => '.brx-submenu-toggle',
				],
			],
		];

		$controls['iconPosition'] = [
			'group'       => 'dropdown',
			'label'       => esc_html__( 'Icon position', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['iconPosition'],
			'inline'      => true,
			'placeholder' => esc_html__( 'Right', 'bricks' ),
			'css'         => [
				[
					'selector' => '.brx-submenu-toggle',
					'property' => 'flex-direction',
					'value'    => 'row-reverse',
					'required' => 'left',
				],
			],
		];

		$controls['iconSize'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Icon size', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'font-size',
					'selector' => '.brx-submenu-toggle button',
				],
			],
		];

		$controls['iconColor'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Icon color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'color',
					'selector' => '.brx-submenu-toggle button',
				],
			],
		];

		$controls['iconTransform'] = [
			'group'  => 'dropdown',
			'type'   => 'transform',
			'label'  => esc_html__( 'Icon transform', 'bricks' ),
			'inline' => true,
			'small'  => true,
			'css'    => [
				[
					'property' => 'transform',
					'selector' => '.brx-submenu-toggle button > *', // Target icon only (@since 2.0)
				],
			],
		];

		$controls['iconTransformOpen'] = [
			'group'  => 'dropdown',
			'type'   => 'transform',
			'label'  => esc_html__( 'Icon transform', 'bricks' ) . ' (' . esc_html__( 'Open', 'bricks' ) . ')',
			'inline' => true,
			'small'  => true,
			'css'    => [
				[
					'property' => 'transform',
					'selector' => '.brx-submenu-toggle button[aria-expanded="true"] > *',  // Target icon only (@since 2.0)
				],
			],
		];

		$controls['iconTransition'] = [
			'group'          => 'dropdown',
			'label'          => esc_html__( 'Icon transition', 'bricks' ),
			'type'           => 'text',
			'hasDynamicData' => false,
			'inline'         => true,
			'css'            => [
				[
					'property'  => 'transition',
					'selector'  => '.brx-submenu-toggle button[aria-expanded] > *',  // Target icon only (@since 2.0)
					'important' => true, // To precede sticker header transition template setting (@since 1.11.1)
				],
			],
		];

		// DROPDOWN - CONTENT

		$controls['dropdownContentSep'] = [
			'group'       => 'dropdown',
			'type'        => 'separator',
			'label'       => esc_html__( 'Content', 'bricks' ),
			'description' => esc_html__( 'Sub menu, mega menu, or multilevel area.', 'bricks' ),
		];

		$controls['dropdownContentWidth'] = [
			'group'    => 'dropdown',
			'label'    => esc_html__( 'Min. width', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'property' => 'min-width',
					'selector' => '.brx-dropdown-content',
				],
			],
			'rerender' => true,
		];

		$controls['dropdownBackgroundColor'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.brx-dropdown-content',
				],
			],
		];

		$controls['dropdownBorder'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.brx-dropdown-content',
				],
			],
		];

		$controls['dropdownBoxShadow'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Box shadow', 'bricks' ),
			'type'  => 'box-shadow',
			'css'   => [
				[
					'property' => 'box-shadow',
					'selector' => '.brx-dropdown-content',
				],
			],
		];

		$controls['dropdownTypography'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.brx-dropdown-content',
				],
			],
		];

		$controls['dropdownTransform'] = [
			'group'  => 'dropdown',
			'type'   => 'transform',
			'label'  => esc_html__( 'Transform', 'bricks' ),
			'inline' => true,
			'small'  => true,
			'css'    => [
				[
					'property' => 'transform',
					'selector' => '.brx-nav-nested-items > .brxe-dropdown > .brx-dropdown-content',
				],
			],
		];

		$controls['dropdownTransformOpen'] = [
			'group'  => 'dropdown',
			'type'   => 'transform',
			'label'  => esc_html__( 'Transform', 'bricks' ) . ' (' . esc_html__( 'Open', 'bricks' ) . ')',
			'inline' => true,
			'small'  => true,
			'css'    => [
				[
					'property' => 'transform',
					'selector' => '.brx-nav-nested-items > .brxe-dropdown.open > .brx-dropdown-content',
				],
			],
		];

		$controls['dropdownTransition'] = [
			'group'          => 'dropdown',
			'label'          => esc_html__( 'Transition', 'bricks' ),
			'type'           => 'text',
			'hasDynamicData' => false,
			'inline'         => true,
			'css'            => [
				[
					'property' => 'transition',
					'selector' => '.brx-dropdown-content',
				],
			],
		];

		$controls['dropdownZindex'] = [
			'group'       => 'dropdown',
			'label'       => esc_html__( 'Z-index', 'bricks' ),
			'type'        => 'number',
			'css'         => [
				[
					'property' => 'z-index',
					'selector' => '.brxe-dropdown',
				],
			],
			'placeholder' => 1001,
		];

		// DROPDOWN - ITEM

		$controls['dropdownItemSep'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Item', 'bricks' ),
			'type'  => 'separator',
		];

		$controls['dropdownPadding'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'padding',
					'selector' => '.brx-dropdown-content > li > a',
				],
				[
					'property' => 'padding',
					'selector' => '.brx-dropdown-content :where(.brx-submenu-toggle > *)', // Added :where, so we can override it with selectors above (@since 2.0)
				],
			],
		];

		$controls['dropdownItemBackground'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Background', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.brx-dropdown-content > li',
				],
				[
					'property' => 'background-color',
					'selector' => '.brx-dropdown-content .brx-submenu-toggle',
				],
			],
		];

		$controls['dropdownItemBorder'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.brx-dropdown-content > li:not(.brxe-dropdown)',
				],
				[
					'property' => 'border',
					'selector' => '.brx-dropdown-content .brx-submenu-toggle',
				],
			],
		];

		$controls['dropdownItemTypography'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.brx-dropdown-content > li > a',
				],
				[
					'property' => 'font',
					'selector' => '.brx-dropdown-content .brx-submenu-toggle > *',
				],
			],
		];

		$controls['dropdownItemTransition'] = [
			'group'          => 'dropdown',
			'label'          => esc_html__( 'Transition', 'bricks' ),
			'type'           => 'text',
			'hasDynamicData' => false,
			'inline'         => true,
			'css'            => [
				[
					'property' => 'transition',
					'selector' => '.brx-dropdown-content > li',
				],
				[
					'property' => 'transition',
					'selector' => '.brx-dropdown-content > li > a',
				],
				[
					'property' => 'transition',
					'selector' => '.brx-dropdown-content .brx-submenu-toggle',
				],
				[
					'property' => 'transition',
					'selector' => '&.brx-has-megamenu .brx-dropdown-content > *',
				],
			],
		];

		// DROPDOWN: MULTILEVEL

		$controls['multiLevelSep'] = [
			'group'       => 'dropdown',
			'label'       => esc_html__( 'Multilevel', 'bricks' ),
			'type'        => 'separator',
			'description' => esc_html__( 'Show only active dropdown. Toggle on click. Inner dropdowns inherit multilevel.', 'bricks' ),
		];

		$controls['multiLevel'] = [
			'group' => 'dropdown',
			'label' => esc_html__( 'Enable', 'bricks' ),
			'type'  => 'checkbox',
		];

		$controls['multiLevelBackText'] = [
			'group'    => 'dropdown',
			'label'    => esc_html__( 'Back', 'bricks' ) . ': ' . esc_html__( 'Text', 'bricks' ),
			'type'     => 'text',
			'inline'   => true,
			'required' => [ 'multiLevel', '=', true ],
		];

		$controls['multiLevelBackTypography'] = [
			'group'    => 'dropdown',
			'label'    => esc_html__( 'Back', 'bricks' ) . ': ' . esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'inline'   => true,
			'required' => [ 'multiLevel', '=', true ],
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.brx-multilevel-back',
				],
			],
		];

		$controls['multiLevelBackBackground'] = [
			'group'    => 'dropdown',
			'label'    => esc_html__( 'Back', 'bricks' ) . ': ' . esc_html__( 'Background', 'bricks' ),
			'type'     => 'color',
			'inline'   => true,
			'required' => [ 'multiLevel', '=', true ],
			'css'      => [
				[
					'property' => 'background-color',
					'selector' => '.brx-multilevel-back',
				],
			],
		];

		// MOBILE MENU

		$controls['mobileMenuSep'] = [
			'group'       => 'mobile-menu',
			'type'        => 'separator',
			'description' => esc_html__( 'Insert "Toggle" element after "Nav items" to show/hide your mobile menu.', 'bricks' ),
		];

		/**
		 * NOTE: Undocumented '_addedClasses' controlKey
		 *
		 * Stored in builder state only. Not saved as setting.
		 *
		 * Processed in ControlCheckbox.vue to add additional 'class' in builder while editing.
		 *
		 * @since 1.8
		 */
		$controls['_addedClasses'] = [
			'group' => 'mobile-menu',
			'label' => esc_html__( 'Keep open while styling', 'bricks' ),
			'type'  => 'checkbox',
			'class' => 'brx-open',
		];

		// Show mobile menu toggle on breakpoint
		$breakpoints        = Breakpoints::$breakpoints;
		$breakpoint_options = [];

		foreach ( $breakpoints as $index => $breakpoint ) {
			$breakpoint_options[ $breakpoint['key'] ] = $breakpoint['label'];
		}

		$breakpoint_options['always'] = esc_html__( 'Always', 'bricks' );
		$breakpoint_options['never']  = esc_html__( 'Never', 'bricks' );

		$controls['mobileMenu'] = [
			'group'       => 'mobile-menu',
			'label'       => Breakpoints::$is_mobile_first ? esc_html__( 'Hide at breakpoint', 'bricks' ) : esc_html__( 'Show at breakpoint', 'bricks' ),
			'type'        => 'select',
			'options'     => $breakpoint_options,
			'rerender'    => true,
			'placeholder' => esc_html__( 'Mobile landscape', 'bricks' ),
		];

		$controls['mobileMenuWidth'] = [
			'group' => 'mobile-menu',
			'label' => esc_html__( 'Width', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'width',
					'selector' => '&.brx-open .brx-nav-nested-items',
				],
			],
		];

		$controls['mobileMenuHeight'] = [
			'group' => 'mobile-menu',
			'label' => esc_html__( 'Height', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'height',
					'selector' => '&.brx-open .brx-nav-nested-items',
				],
			],
		];

		// @since 2.0: Adds padding control for dropdown content (only for mobile)
		$controls['mobileMenuContentPadding'] = [
			'group'       => 'mobile-menu',
			'label'       => esc_html__( 'Padding', 'bricks' ),
			'type'        => 'spacing',
			'css'         => [
				[
					'property' => 'padding',
					'selector' => '&.brx-open .brx-dropdown-content > *',
				],
			],
			'description' => esc_html__( 'Used to creat visual hiarchy between nested dropdowns.', 'bricks' ),
		];

		$controls['mobileMenuAlignItems'] = [
			'group' => 'mobile-menu',
			'label' => esc_html__( 'Align items', 'bricks' ),
			'type'  => 'align-items',
			'css'   => [
				[
					'property' => 'align-items',
					'selector' => '&.brx-open .brx-nav-nested-items',
				],
			],
		];

		$controls['mobileMenuJustifyContent'] = [
			'group' => 'mobile-menu',
			'label' => esc_html__( 'Justify content', 'bricks' ),
			'type'  => 'justify-content',
			'css'   => [
				[
					'property' => 'justify-content',
					'selector' => '&.brx-open .brx-nav-nested-items',
				],
			],
		];

		$controls['mobileMenuPosition'] = [
			'group' => 'mobile-menu',
			'label' => esc_html__( 'Position', 'bricks' ),
			'type'  => 'dimensions',
			'css'   => [
				[
					'selector' => '&.brx-open .brx-nav-nested-items',
				],
				[
					'selector'  => '&.brx-open .brx-nav-nested-items',
					'property'  => 'width',
					'value'     => 'auto',
					'skipIfSet' => 'mobileMenuWidth', // NOTE: Undocumented (@since 1.10)
				],
			],
		];

		$controls['mobileMenuBackgroundColor'] = [
			'group' => 'mobile-menu',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '&.brx-open .brx-nav-nested-items',
				],
			],
		];

		// MOBILE MENU: ITEM

		$controls['mobileMenuItemSep'] = [
			'group' => 'mobile-menu',
			'type'  => 'separator',
			'label' => esc_html__( 'Item', 'bricks' ),
		];

		$controls['mobileMenuItemAlignItems'] = [
			'group'   => 'mobile-menu',
			'label'   => esc_html__( 'Align items', 'bricks' ),
			'type'    => 'align-items',
			'exclude' => [
				'stretch',
			],
			'inline'  => true,
			'css'     => [
				[
					'property' => 'justify-content',
					'selector' => '&.brx-open .brx-submenu-toggle',
				],
				[
					'property' => 'justify-content',
					'selector' => '&.brx-open .brx-nav-nested-items > li',
				],

				[
					'property' => 'justify-content',
					'selector' => '&.brx-open .brx-submenu-toggle',
				],
				[
					'property' => 'justify-content',
					'selector' => '&.brx-open .brx-nav-nested-items > li',
				],

				[
					'property' => 'justify-content',
					'selector' => '&.brx-open .brx-submenu-toggle',
				],
				[
					'property' => 'justify-content',
					'selector' => '&.brx-open .brx-nav-nested-items > li',
				],

				// Necessary to apply justify-content (@since 1.10)
				[
					'property' => 'display',
					'selector' => '&.brx-open li.menu-item',
					'value'    => 'flex',
				],
			],
		];

		/**
		 * Loop over all Nav nestable $controls css selectors add wrap them in :where() to lower CSS specificity
		 *
		 * NOTE: Not in use yyet due to conflict with 'dropdownPadding' control aboove!
		 *
		 * @since 2.1
		 */
		// foreach ( $controls as $key => $control ) {
		// if ( ! empty( $control['css'] ) && is_array( $control['css'] ) ) {
		// foreach ( $control['css'] as $index => $css ) {
		// if ( ! empty( $css['selector'] ) ) {
		// Selector starts with &: Wrap after &
		// if ( str_starts_with( $css['selector'], '&' ) ) {
		// $controls[ $key ]['css'][ $index ]['selector'] = ':where(' . substr( $css['selector'], 1 ) . ')';
		// } else {
		// $controls[ $key ]['css'][ $index ]['selector'] = ":where({$css['selector']})";
		// }
		// }
		// }
		// }
		// }

		// Merge $controls into $this->controls
		$this->controls = array_merge( $this->controls, $controls );
	}

	public function get_nestable_children() {
		$children = [];

		// Text link 1
		$children[] = [
			'name'     => 'text-link',
			'label'    => 'Nav link',
			'settings' => [
				'text' => 'Home',
				'link' => [
					'type' => 'external',
					'url'  => '#',
				],
			],
		];

		// Text link 2
		$children[] = [
			'name'     => 'text-link',
			'label'    => 'Nav link',
			'settings' => [
				'text' => 'About',
				'link' => [
					'type' => 'external',
					'url'  => '#',
				],
			],
		];

		$dropdown_element = Elements::get_element( [ 'name' => 'dropdown' ] );

		$dropdown_children = ! empty( $dropdown_element['nestableChildren'] ) ? $dropdown_element['nestableChildren'] : [];

		$children[] = [
			'name'     => 'dropdown',
			'label'    => esc_html__( 'Dropdown', 'bricks' ),
			'children' => $dropdown_children,
			'settings' => [
				'text' => 'Dropdown',
			],
		];

		// Toggle (close mobile menu)
		$children[] = [
			'name'     => 'toggle',
			'label'    => esc_html__( 'Toggle', 'bricks' ) . ' (' . esc_html__( 'Close', 'bricks' ) . ': ' . esc_html__( 'Mobile', 'bricks' ) . ')',
			'settings' => [
				'_hidden' => [
					'_cssClasses' => 'brx-toggle-div',
				],
			],
		];

		return [
			// Nav items
			[
				'name'      => 'block',
				'label'     => esc_html__( 'Nav items', 'bricks' ),
				'children'  => $children,
				'deletable' => false, // Prevent deleting this element directly. NOTE: Undocumented (@since 1.8)
				'cloneable' => false, // Prevent cloning this element directly.  NOTE: Undocumented (@since 1.8)
				'settings'  => [
					'tag'     => 'ul',
					'_hidden' => [
						'_cssClasses' => 'brx-nav-nested-items',
					],
				],
			],

			// Toggle (open mobile menu)
			[
				'name'     => 'toggle',
				'label'    => esc_html__( 'Toggle', 'bricks' ) . ' (' . esc_html__( 'Open', 'bricks' ) . ': ' . esc_html__( 'Mobile', 'bricks' ) . ')',
				'settings' => [],
			],
		];
	}

	public function render() {
		$settings = $this->settings;

		$this->set_attribute( '_root', 'aria-label', ! empty( $settings['ariaLabel'] ) ? esc_attr( $settings['ariaLabel'] ) : esc_html__( 'Menu', 'bricks' ) );

		// Nav button: Show at breakpoint
		$show_nav_button_at = ! empty( $settings['mobileMenu'] ) ? $settings['mobileMenu'] : 'mobile_landscape';

		// Is mobile-first: Swap always <> never
		if ( Breakpoints::$is_mobile_first ) {
			if ( $show_nav_button_at === 'always' ) {
				$show_nav_button_at = 'never';
			} elseif ( $show_nav_button_at === 'never' ) {
				$show_nav_button_at = 'always';
			}
		}

		$this->set_attribute( '_root', 'data-toggle', $show_nav_button_at );

		// Multi level
		if ( isset( $settings['multiLevel'] ) ) {
			$this->set_attribute( '_root', 'class', 'multilevel' );
			$this->set_attribute( '_root', 'data-back-text', ! empty( $settings['multiLevelBackText'] ) ? esc_attr( $settings['multiLevelBackText'] ) : esc_html__( 'Back', 'bricks' ) );
		}

		// If we have mobile menu set to "justify center", we need to fix the alignment #86c0rw3ex (@since 2.0)
		if ( isset( $settings['mobileMenuJustifyContent'] ) && $settings['mobileMenuJustifyContent'] === 'center' ) {
			$this->set_attribute( '_root', 'class', 'brx-mobile-center' );
		}

		$output = "<{$this->tag} {$this->render_attributes( '_root' )}>";

		$output .= Frontend::render_children( $this );

		if ( $show_nav_button_at !== 'never' ) {
			// Builder: Add nav menu & mobile menu visibility via inline style
			if ( bricks_is_builder() || bricks_is_builder_call() ) {
				$breakpoint          = Breakpoints::get_breakpoint_by( 'key', $show_nav_button_at );
				$nav_menu_inline_css = $this->generate_mobile_menu_inline_css( $settings, $breakpoint );

				$output .= "<style>$nav_menu_inline_css</style>";
			}
		}

		$output .= "</{$this->tag}>";

		echo $output;
	}
}
