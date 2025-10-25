<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Tabs_Nested extends Element {
	public $category = 'general';
	public $name     = 'tabs-nested';
	public $icon     = 'ti-layout-tab';
	public $scripts  = [ 'bricksTabs' ];
	public $nestable = true;

	public function get_label() {
		return esc_html__( 'Tabs', 'bricks' ) . ' (' . esc_html__( 'Nestable', 'bricks' ) . ')';
	}

	public function get_keywords() {
		return [ 'nestable' ];
	}

	public function set_control_groups() {
		$this->control_groups['title'] = [
			'title' => esc_html__( 'Title', 'bricks' ),
		];

		$this->control_groups['content'] = [
			'title' => esc_html__( 'Content', 'bricks' ),
		];
	}

	public function set_controls() {
		$this->controls['direction'] = [
			'label'       => esc_html__( 'Direction', 'bricks' ),
			'tooltip'     => [
				'content'  => 'flex-direction',
				'position' => 'top-left',
			],
			'type'        => 'direction',
			'css'         => [
				[
					'property' => 'flex-direction',
				],
			],
			'inline'      => true,
			'rerender'    => true,
			'description' => esc_html__( 'Set "ID" on tab menu "Div" to open a tab via anchor link.', 'bricks' ) . ' ' . esc_html__( 'No spaces. No pound (#) sign.', 'bricks' ),
		];

		$this->controls['openTabOn'] = [
			'label'       => esc_html__( 'Open tab on', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'click'      => esc_html__( 'Click', 'bricks' ),
				'mouseenter' => esc_html__( 'Hover', 'bricks' ),
			],
			'inline'      => true,
			'placeholder' => 'Click',
		];

		// Expand item on page load (@since 1.12)
		$this->controls['openTab'] = [
			'label'       => esc_html__( 'Open tab index', 'bricks' ),
			'type'        => 'text',
			'description' => esc_html__( 'Index of the item to expand on page load, start at 0.', 'bricks' ),
			'inline'      => true,
			'placeholder' => '0',
		];

			// TITLE

			$this->controls['titleWidth'] = [
				'group'       => 'title',
				'label'       => esc_html__( 'Width', 'bricks' ),
				'type'        => 'number',
				'units'       => true,
				'css'         => [
					[
						'selector' => '> .tab-menu .tab-title',
						'property' => 'width',
					],
				],
				'placeholder' => 'auto',
			];

			$this->controls['titleMargin'] = [
				'group' => 'title',
				'label' => esc_html__( 'Margin', 'bricks' ),
				'type'  => 'spacing',
				'css'   => [
					[
						'property' => 'margin',
						'selector' => '> .tab-menu .tab-title',
					],
				],
			];

			$this->controls['titlePadding'] = [
				'group'   => 'title',
				'label'   => esc_html__( 'Padding', 'bricks' ),
				'type'    => 'spacing',
				'css'     => [
					[
						'property' => 'padding',
						'selector' => '> .tab-menu .tab-title',
					],
				],
				'default' => [
					'top'    => 20,
					'right'  => 20,
					'bottom' => 20,
					'left'   => 20,
				],
			];

			$this->controls['titleBackgroundColor'] = [
				'group' => 'title',
				'label' => esc_html__( 'Background', 'bricks' ),
				'type'  => 'color',
				'css'   => [
					[
						'property' => 'background-color',
						'selector' => '> .tab-menu .tab-title',
					],
				],
			];

			$this->controls['titleBorder'] = [
				'group' => 'title',
				'label' => esc_html__( 'Border', 'bricks' ),
				'type'  => 'border',
				'css'   => [
					[
						'property' => 'border',
						'selector' => '> .tab-menu .tab-title',
					],
				],
			];

			$this->controls['titleTypography'] = [
				'group' => 'title',
				'label' => esc_html__( 'Typography', 'bricks' ),
				'type'  => 'typography',
				'css'   => [
					[
						'property' => 'font',
						'selector' => '> .tab-menu .tab-title',
					],
				],
			];

			// ACTIVE TITLE

			$this->controls['titleActiveSeparator'] = [
				'group'      => 'title',
				'label'      => esc_html__( 'Active', 'bricks' ),
				'type'       => 'separator',
				'fullAccess' => true,
			];

			$this->controls['titleActiveBackgroundColor'] = [
				'group'   => 'title',
				'label'   => esc_html__( 'Background color', 'bricks' ),
				'type'    => 'color',
				'css'     => [
					[
						'property' => 'background-color',
						'selector' => '> .tab-menu .tab-title.brx-open',
					],
				],
				'default' => [
					'hex' => '#dddedf',
				],
			];

			$this->controls['titleActiveBorder'] = [
				'group' => 'title',
				'label' => esc_html__( 'Border', 'bricks' ),
				'type'  => 'border',
				'css'   => [
					[
						'property' => 'border',
						'selector' => '> .tab-menu .tab-title.brx-open',
					],
				],
			];

			$this->controls['titleActiveTypography'] = [
				'group' => 'title',
				'label' => esc_html__( 'Typography', 'bricks' ),
				'type'  => 'typography',
				'css'   => [
					[
						'property' => 'font',
						'selector' => '> .tab-menu .tab-title.brx-open',
					],
				],
			];

			// CONTENT

			$this->controls['contentMargin'] = [
				'group' => 'content',
				'label' => esc_html__( 'Margin', 'bricks' ),
				'type'  => 'spacing',
				'css'   => [
					[
						'property' => 'margin',
						'selector' => '> .tab-content',
					],
				],
			];

			$this->controls['contentPadding'] = [
				'group'   => 'content',
				'label'   => esc_html__( 'Padding', 'bricks' ),
				'type'    => 'spacing',
				'css'     => [
					[
						'property' => 'padding',
						'selector' => '> .tab-content',
					],
				],
				'default' => [
					'top'    => 20,
					'right'  => 20,
					'bottom' => 20,
					'left'   => 20,
				],
			];

			$this->controls['contentColor'] = [
				'group' => 'content',
				'label' => esc_html__( 'Text color', 'bricks' ),
				'type'  => 'color',
				'css'   => [
					[
						'property' => 'color',
						'selector' => '> .tab-content',
					],
				],
			];

			$this->controls['contentBackgroundColor'] = [
				'group' => 'content',
				'label' => esc_html__( 'Background color', 'bricks' ),
				'type'  => 'color',
				'css'   => [
					[
						'property' => 'background-color',
						'selector' => '> .tab-content',
					],
				],
			];

			$this->controls['contentBorder'] = [
				'group'   => 'content',
				'label'   => esc_html__( 'Border', 'bricks' ),
				'type'    => 'border',
				'css'     => [
					[
						'property' => 'border',
						'selector' => '> .tab-content',
					],
				],
				'default' => [
					'width' => [
						'top'    => 1,
						'right'  => 1,
						'bottom' => 1,
						'left'   => 1,
					],
					'style' => 'solid',
				],
			];
	}

	/**
	 * Get child elements
	 *
	 * @return array Array of child elements.
	 *
	 * @since 1.5
	 */
	public function get_nestable_children() {
		/**
		 * NOTE: Required classes for element styling & script:
		 *
		 * .tab-menu
		 * .tab-title
		 * .tab-content
		 * .tab-pane
		 */
		return [
			// Title
			[
				'name'     => 'block',
				'label'    => esc_html__( 'Tab menu', 'bricks' ),
				'settings' => [
					'_direction' => 'row',
					'_hidden'    => [
						'_cssClasses' => 'tab-menu',
					],
				],
				'children' => [
					[
						'name'     => 'div',
						'label'    => esc_html__( 'Title', 'bricks' ),
						'settings' => [
							'_hidden' => [
								'_cssClasses' => 'tab-title',
							],
						],
						'children' => [
							[
								'name'     => 'text-basic',
								'settings' => [
									'text' => esc_html__( 'Title', 'bricks' ) . ' 1',
								],
							],
						],
					],

					[
						'name'     => 'div',
						'label'    => esc_html__( 'Title', 'bricks' ),
						'settings' => [
							'_hidden' => [
								'_cssClasses' => 'tab-title',
							],
						],
						'children' => [
							[
								'name'     => 'text-basic',
								'settings' => [
									'text' => esc_html__( 'Title', 'bricks' ) . ' 2',
								],
							],
						],
					],
				],
			],

			// Content
			[
				'name'     => 'block',
				'label'    => esc_html__( 'Tab content', 'bricks' ),
				'settings' => [
					'_hidden' => [
						'_cssClasses' => 'tab-content',
					],
				],
				'children' => [
					[
						'name'     => 'block',
						'label'    => esc_html__( 'Pane', 'bricks' ),
						'settings' => [
							'_hidden' => [
								'_cssClasses' => 'tab-pane',
							],
						],
						'children' => [
							[
								'name'     => 'text',
								'settings' => [
									'text' => esc_html__( 'Content goes here ..', 'bricks' ) . ' (1)',
								],
							],
						],
					],

					[
						'name'     => 'block',
						'label'    => esc_html__( 'Pane', 'bricks' ),
						'settings' => [
							'_hidden' => [
								'_cssClasses' => 'tab-pane',
							],
						],
						'children' => [
							[
								'name'     => 'text',
								'settings' => [
									'text' => esc_html__( 'Content goes here ..', 'bricks' ) . ' (2)',
								],
							],
						],
					],
				],
			],
		];
	}

	public function render() {
		$settings = $this->settings;

		// Open tab on hover (@since 1.10.2)
		if ( ! empty( $settings['openTabOn'] ) ) {
			$this->set_attribute( '_root', 'data-open-on', $settings['openTabOn'] );
		}

		// Expand item on page load (@since 1.12)
		$this->set_attribute( '_root', 'data-open-tab', ! isset( $settings['openTab'] ) ? 0 : $settings['openTab'] );

		$output = "<div {$this->render_attributes( '_root' )}>";

		// Render children elements (= individual items)
		$output .= Frontend::render_children( $this );

		$output .= '</div>';

		// Enhance accessibility using DOMDocument
		echo $this->enhance_accessibility( $output );
	}

	/**
	 * Enhances the accessibility of the nested tabs content by adding ARIA attributes.
	 *
	 * @since 1.11
	 *
	 * @param string $html_content The HTML content of the nested tabs to be enhanced.
	 * @return string The enhanced HTML content with added accessibility attributes.
	 */
	private function enhance_accessibility( $html_content ) {
		$dom = new \DOMDocument();
		// Suppress warnings during HTML loading
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );

		// Process tab titles
		$tab_titles = $xpath->query( "//*[contains(@class, 'tab-title')]" );
		foreach ( $tab_titles as $index => $title ) {
			$this->set_attribute_if_not_exists( $title, 'role', 'tab' );
			if ( $index === 0 ) {
				$this->set_attribute_if_not_exists( $title, 'aria-selected', 'true' );
				$this->set_attribute_if_not_exists( $title, 'tabindex', '0' );
			} else {
				$this->set_attribute_if_not_exists( $title, 'aria-selected', 'false' );
				$this->set_attribute_if_not_exists( $title, 'tabindex', '-1' );
			}

			$title_id = $title->getAttribute( 'id' );
			if ( ! $title_id ) {
				$title_id = "brx-tab-title-{$this->id}-$index";
				$this->set_attribute_if_not_exists( $title, 'id', $title_id );
			}

			// Find the corresponding tab pane
			$pane = $xpath->query( "//*[contains(@class, 'tab-pane')]" )->item( $index );
			if ( $pane ) {
				$pane_id = $pane->getAttribute( 'id' );
				if ( ! $pane_id ) {
					$pane_id = "brx-tab-pane-{$this->id}-$index";
					$this->set_attribute_if_not_exists( $pane, 'id', $pane_id );
				}
				$this->set_attribute_if_not_exists( $title, 'aria-controls', $pane_id );
			}
		}

		// Process tab panes
		$tab_panes = $xpath->query( "//*[contains(@class, 'tab-pane')]" );
		foreach ( $tab_panes as $index => $pane ) {
			$this->set_attribute_if_not_exists( $pane, 'role', 'tabpanel' );

			$pane_id = $pane->getAttribute( 'id' );
			if ( ! $pane_id ) {
				$pane_id = "brx-tab-pane-{$this->id}-$index";
				$this->set_attribute_if_not_exists( $pane, 'id', $pane_id );
			}

			$title = $tab_titles->item( $index );
			if ( $title ) {
				$title_id = $title->getAttribute( 'id' );
				$this->set_attribute_if_not_exists( $pane, 'aria-labelledby', $title_id );
			}

			$this->set_attribute_if_not_exists( $pane, 'tabindex', '0' );
		}

		$tab_menu = $xpath->query( "//*[contains(@class, 'tab-menu')]" )->item( 0 );
		if ( $tab_menu ) {
			$this->set_attribute_if_not_exists( $tab_menu, 'role', 'tablist' );
		}

		return $dom->saveHTML();
	}

	/**
	 * Sets an attribute on a DOM element if it doesn't already exist.
	 *
	 * @since 1.11
	 *
	 * @param \DOMElement $element The DOM element to set the attribute on.
	 * @param string      $attribute The name of the attribute to set.
	 * @param string      $value The value to set for the attribute.
	 */
	private function set_attribute_if_not_exists( $element, $attribute, $value ) {
		if ( ! $element->hasAttribute( $attribute ) ) {
			$element->setAttribute( $attribute, $value );
		}
	}
}
