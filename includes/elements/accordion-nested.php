<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Accordion_Nested extends Element {
	public $category = 'general';
	public $name     = 'accordion-nested';
	public $icon     = 'ti-layout-accordion-merged';
	public $scripts  = [ 'bricksAccordion' ];
	public $nestable = true;
	private $faqpage_schema;

	public function get_label() {
		return esc_html__( 'Accordion', 'bricks' ) . ' (' . esc_html__( 'Nestable', 'bricks' ) . ')';
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
		// Array of nestable element.children (@since 1.5)
		$this->controls['_children'] = [
			'type'          => 'repeater',
			'titleProperty' => 'label',
			'items'         => 'children', // NOTE: Undocumented
			'description'   => esc_html__( 'Set "ID" on items above to open via anchor link.', 'bricks' ) . ' ' . esc_html__( 'No spaces. No pound (#) sign.', 'bricks' ),
		];

		$this->controls['expandFirstItem'] = [
			'deprecated' => '1.11.2', // Use 'expandItem' instead
			'label'      => esc_html__( 'Expand first item', 'bricks' ),
			'type'       => 'checkbox',
		];

		// Expand item on page load (@since 1.12)
		$this->controls['expandItem'] = [
			'label'       => esc_html__( 'Expand item indexes', 'bricks' ),
			'type'        => 'text',
			'description' => esc_html__( 'Indexes of the items to expand on page load, separated by comma, start at 0.', 'bricks' ),
			'inline'      => true,
			'placeholder' => '',
			'required'    => [ 'expandFirstItem', '!=', true ],
		];

		$this->controls['independentToggle'] = [
			'label'       => esc_html__( 'Independent toggle', 'bricks' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Enable to open & close an item without toggling other items.', 'bricks' ),
		];

		$this->controls['transition'] = [
			'label'       => esc_html__( 'Transition', 'bricks' ) . ' (ms)',
			'type'        => 'number',
			'placeholder' => 200,
		];

		$this->controls['faqSchema'] = [
			'label'       => esc_html__( 'FAQ schema', 'bricks' ),
			'type'        => 'checkbox',
			'description' => '<a href="https://developers.google.com/search/docs/appearance/structured-data/faqpage" target="_blank">' . esc_html__( 'Generate FAQPage structured data (JSON-LD).', 'bricks' ) . '</a>',
		];

		// TITLE

		$this->controls['titleHeight'] = [
			'group'   => 'title',
			'label'   => esc_html__( 'Min. height', 'bricks' ),
			'type'    => 'number',
			'units'   => true,
			'css'     => [
				[
					'property' => 'min-height',
					'selector' => '.accordion-title-wrapper',
				],
			],
			'default' => '50px',
		];

		$this->controls['titleMargin'] = [
			'group' => 'title',
			'label' => esc_html__( 'Margin', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'margin',
					'selector' => '.accordion-title-wrapper',
				],
			],
		];

		$this->controls['titlePadding'] = [
			'group' => 'title',
			'label' => esc_html__( 'Padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'padding',
					'selector' => '.accordion-title-wrapper',
				],
			],
		];

		$this->controls['titleBackgroundColor'] = [
			'group' => 'title',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.accordion-title-wrapper',
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
					'selector' => '.accordion-title-wrapper',
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
					'selector' => '.accordion-title-wrapper',
				],
				[
					'property' => 'font',
					'selector' => '.accordion-title-wrapper .brxe-heading',
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
			'group' => 'title',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.brx-open .accordion-title-wrapper',
				],
			],
		];

		$this->controls['titleActiveBorder'] = [
			'group' => 'title',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.brx-open .accordion-title-wrapper',
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
					'selector' => '.brx-open .accordion-title-wrapper',
				],
				[
					'property' => 'font',
					'selector' => '.brx-open .accordion-title-wrapper .brxe-heading',
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
					'selector' => '.accordion-content-wrapper',
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
					'selector' => '.accordion-content-wrapper',
				],
			],
			'default' => [
				'top'    => 15,
				'right'  => 0,
				'bottom' => 15,
				'left'   => 0,
			],
		];

		$this->controls['contentBackgroundColor'] = [
			'group' => 'content',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.accordion-content-wrapper',
				],
			],
		];

		$this->controls['contentBorder'] = [
			'group' => 'content',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.accordion-content-wrapper',
				],
			],
		];

		$this->controls['contentTypography'] = [
			'group' => 'content',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.accordion-content-wrapper',
				],
			],
		];
	}

	public function get_nestable_item() {
		/**
		 * NOTE: Required classes for element styling & script:
		 *
		 * .accordion-title-wrapper
		 * .accordion-content-wrapper
		 */

		return [
			'name'     => 'block',
			'label'    => esc_html__( 'Item', 'bricks' ),
			'children' => [
				[
					'name'     => 'block',
					'label'    => esc_html__( 'Title', 'bricks' ),
					'settings' => [
						'_alignItems'     => 'center',
						'_direction'      => 'row',
						'_justifyContent' => 'space-between',

						// NOTE: Undocumented (@since 1.5 to apply hard-coded hidden settings)
						'_hidden'         => [
							'_cssClasses' => 'accordion-title-wrapper',
						],
					],

					'children' => [
						[
							'name'     => 'heading',
							'settings' => [
								'text' => esc_html__( 'Accordion', 'bricks' ) . ' {item_index}',
								'tag'  => 'h3',
							],
						],
						[
							'name'     => 'icon',
							'settings' => [
								'icon'            => [
									'icon'    => 'ion-ios-arrow-forward',
									'library' => 'ionicons',
								],
								'iconSize'        => '1em',
								'isAccordionIcon' => true, // @since 2.0
							],
						],
					],
				],

				[
					'name'     => 'block',
					'label'    => esc_html__( 'Content', 'bricks' ),
					'settings' => [
						'_hidden' => [
							'_cssClasses' => 'accordion-content-wrapper',
						],
					],
					'children' => [
						[
							'name'     => 'text',
							'settings' => [
								'text' => 'Lorem ipsum dolor ist amte, consectetuer adipiscing eilt. Aenean commodo ligula egget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quak felis, ultricies nec, pellentesque eu, pretium quid, sem.',
							],
						],
					],
				],
			],
		];
	}

	public function get_nestable_children() {
		$children = [];

		for ( $i = 0; $i < 2; $i++ ) {
			$item = $this->get_nestable_item();

			// Replace {item_index} with $index
			$item       = wp_json_encode( $item );
			$item       = str_replace( '{item_index}', $i + 1, $item );
			$item       = json_decode( $item, true );
			$children[] = $item;
		}

		return $children;
	}

	public function render() {
		$settings = $this->settings;

		// FAQ Schema initialization if it's enabled in settings
		if ( isset( $settings['faqSchema'] ) ) {
			$this->faqpage_schema = [
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => [],
			];
		}

		// data-script-args: Expand first item & Independent toggle
		$data_script_args = [];

		foreach ( [ 'expandFirstItem', 'independentToggle' ] as $setting_key ) {
			if ( isset( $settings[ $setting_key ] ) ) {
				$data_script_args[] = $setting_key;
			}
		}

		// Expand item on page load (@since 1.12)
		if ( isset( $settings['expandItem'] ) ) {
			$this->set_attribute( '_root', 'data-expand-item', $settings['expandItem'] );
		}

		if ( count( $data_script_args ) ) {
			$this->set_attribute( '_root', 'data-script-args', join( ',', $data_script_args ) );
		}

		// data-transition: Transition duration in ms
		if ( isset( $settings['transition'] ) ) {
			$this->set_attribute( '_root', 'data-transition', $settings['transition'] );
		}

		$output = "<div {$this->render_attributes( '_root' )}>";

		// Render children elements (= individual items)
		$accordion_content = Frontend::render_children( $this, 'div' );

		// Render children elements (= individual items)
		$output .= $accordion_content;

		$output .= '</div>';

		// Enhance accessibility using DOMDocument
		echo $this->enhance_accessibility( $output );

		// FAQ Schema
		if ( ! empty( $this->faqpage_schema ) ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $this->faqpage_schema ) . '</script>';
		}
	}

	/**
	 * Enhances the accessibility of the accordion content by adding ARIA attributes.
	 * Generate FAQPage structured data if enabled in settings.
	 *
	 * This method parses the given HTML content using DOMDocument and adds
	 * necessary ARIA attributes to make the accordion more accessible. It focuses on:
	 * - Adding role, aria-expanded, and aria-controls to accordion headers
	 * - Adding role, aria-labelledby, and id to accordion content sections
	 * - Ensuring unique ids for accordion titles and content sections
	 *
	 * The method only adds attributes if they don't already exist, preserving any
	 * manually set attributes.
	 *
	 * @param string $html_content The HTML content of the accordion to be enhanced.
	 * @return string The enhanced HTML content with added accessibility attributes.
	 *
	 * @since 1.11
	 */
	private function enhance_accessibility( $html_content ) {
		$dom = new \DOMDocument();

		// Suppress warnings during HTML loading
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );

		$items = $xpath->query( "//*[contains(@class, 'brxe-accordion-nested')]/*[contains(@class, 'brxe-block')]" );

		foreach ( $items as $index => $item ) {
			$title_wrapper   = $xpath->query( ".//*[contains(@class, 'accordion-title-wrapper')]", $item )->item( 0 );
			$content_wrapper = $xpath->query( ".//*[contains(@class, 'accordion-content-wrapper')]", $item )->item( 0 );

			if ( $title_wrapper && $content_wrapper ) {
				// Set attributes for the title wrapper
				$this->set_attribute_if_not_exists( $title_wrapper, 'role', 'button' );
				$this->set_attribute_if_not_exists( $title_wrapper, 'aria-expanded', 'false' );
				$this->set_attribute_if_not_exists( $title_wrapper, 'tabindex', '0' );

				// Set content wrapper ID if it doesn't exist
				$content_id = $content_wrapper->getAttribute( 'id' ) ?? 'accordion-content-' . $index;

				$this->set_attribute_if_not_exists( $content_wrapper, 'id', $content_id );

				// Set aria-controls on title wrapper
				$this->set_attribute_if_not_exists( $title_wrapper, 'aria-controls', $content_id );

				// Set attributes for the content wrapper
				$this->set_attribute_if_not_exists( $content_wrapper, 'role', 'region' );

				// Find any heading element within the title wrapper
				$heading = $xpath->query( './/h1|.//h2|.//h3|.//h4|.//h5|.//h6', $title_wrapper )->item( 0 );
				if ( $heading ) {
					$heading_id = $heading->getAttribute( 'id' ) ?? 'accordion-title-' . $index;

					$this->set_attribute_if_not_exists( $heading, 'id', $heading_id );
					$this->set_attribute_if_not_exists( $content_wrapper, 'aria-labelledby', $heading_id );
				}

				// STEP: Generate FAQ schema if it's enabled in settings
				if ( isset( $this->faqpage_schema ) && $heading && $content_wrapper ) {
					$title_text   = $heading->textContent; // @phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$content_text = $content_wrapper->textContent; // @phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

					// Add the question-answer pair to the FAQ schema if both exist
					if ( $title_text && $content_text ) {
						$this->faqpage_schema['mainEntity'][] = [
							'@type'          => 'Question',
							'name'           => $title_text,
							'acceptedAnswer' => [
								'@type' => 'Answer',
								'text'  => $content_text,
							],
						];
					}
				}
			}
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
