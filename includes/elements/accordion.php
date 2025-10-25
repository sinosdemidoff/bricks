<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Accordion extends Element {
	public $category     = 'general';
	public $name         = 'accordion';
	public $icon         = 'ti-layout-accordion-merged';
	public $scripts      = [ 'bricksAccordion' ];
	public $css_selector = '.accordion-item';
	public $loop_index   = 0;
	private $faqpage_schema;

	public function get_label() {
		return esc_html__( 'Accordion', 'bricks' );
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
		$this->controls['accordions'] = [
			'placeholder' => esc_html__( 'Accordion', 'bricks' ),
			'type'        => 'repeater',
			'checkLoop'   => true,
			'description' => esc_html__( 'Set "ID" on items above to open via anchor link.', 'bricks' ) . ' ' . esc_html__( 'No spaces. No pound (#) sign.', 'bricks' ),
			'fields'      => [
				'title'    => [
					'label' => esc_html__( 'Title', 'bricks' ),
					'type'  => 'text',
				],
				'anchorId' => [
					'label' => esc_html__( 'ID', 'bricks' ),
					'type'  => 'text',
				],
				'subtitle' => [
					'label' => esc_html__( 'Subtitle', 'bricks' ),
					'type'  => 'text',
				],
				'content'  => [
					'label' => esc_html__( 'Content', 'bricks' ),
					'type'  => 'editor',
				],
			],
			'default'     => [
				[
					'title'    => esc_html__( 'Item', 'bricks' ),
					'subtitle' => esc_html__( 'I am a so called subtitle.', 'bricks' ),
					'content'  => esc_html__( 'Content goes here ..', 'bricks' ),
				],
				[
					'title'    => esc_html__( 'Item', 'bricks' ) . ' 2',
					'subtitle' => esc_html__( 'I am a so called subtitle.', 'bricks' ),
					'content'  => esc_html__( 'Content goes here ..', 'bricks' ),
				],
			],
		];

		$this->controls = array_replace_recursive( $this->controls, $this->get_loop_builder_controls() );

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

		$this->controls['titleTag'] = [
			'group'       => 'title',
			'label'       => esc_html__( 'HTML tag', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'div' => 'div',
				'h1'  => 'h1',
				'h2'  => 'h2',
				'h3'  => 'h3',
				'h4'  => 'h4',
				'h5'  => 'h5',
				'h6'  => 'h6',
			],
			'inline'      => true,
			'placeholder' => 'h5',
			'default'     => 'h3',
		];

		$this->controls['icon'] = [
			'group'   => 'title',
			'label'   => esc_html__( 'Icon', 'bricks' ),
			'type'    => 'icon',
			'default' => [
				'icon'    => 'ion-ios-arrow-forward',
				'library' => 'ionicons',
			],
		];

		$this->controls['iconTypography'] = [
			'group'    => 'title',
			'label'    => esc_html__( 'Icon typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.accordion-title{pseudo} .icon', // NOTE: Undocumented
				],
			],
			'required' => [ 'icon.icon', '!=', '' ],
		];

		$this->controls['iconExpanded'] = [
			'group'   => 'title',
			'label'   => esc_html__( 'Icon expanded', 'bricks' ),
			'type'    => 'icon',
			'default' => [
				'icon'    => 'ion-ios-arrow-down',
				'library' => 'ionicons',
			],
		];

		$this->controls['iconExpandedTypography'] = [
			'group'    => 'title',
			'label'    => esc_html__( 'Icon expanded typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.accordion-title{pseudo} .icon.expanded',
				],
			],
			'exclude'  => [
				'font-family',
				'font-weight',
				'font-style',
				'text-align',
				'text-decoration',
				'text-transform',
				'line-height',
				'letter-spacing',
			],
			'required' => [ 'iconExpanded.icon', '!=', '' ],
		];

		$this->controls['iconPosition'] = [
			'group'       => 'title',
			'label'       => esc_html__( 'Icon position', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['iconPosition'],
			'inline'      => true,
			'placeholder' => esc_html__( 'Right', 'bricks' ),
			'required'    => [ 'icon', '!=', '' ],
		];

		$this->controls['iconRotate'] = [
			'group'       => 'title',
			'label'       => esc_html__( 'Icon rotate in °', 'bricks' ),
			'type'        => 'number',
			'unit'        => 'deg',
			'css'         => [
				[
					'property' => 'transform:rotate',
					'selector' => '.brx-open .title + .icon',
				],
			],
			'small'       => false,
			'description' => esc_html__( 'Icon rotation for expanded accordion.', 'bricks' ),
			'required'    => [ 'icon', '!=', '' ],
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

		$this->controls['titleTypography'] = [
			'group' => 'title',
			'label' => esc_html__( 'Title typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.accordion-title{pseudo} .title',
				],
			],
		];

		$this->controls['subtitleTypography'] = [
			'group' => 'title',
			'label' => esc_html__( 'Subtitle typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.accordion-subtitle',
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

		$this->controls['titleActiveBoxShadow'] = [
			'group' => 'title',
			'label' => esc_html__( 'Box shadow', 'bricks' ),
			'type'  => 'box-shadow',
			'css'   => [
				[
					'property' => 'box-shadow',
					'selector' => '.accordion-title-wrapper',
				],
			],
		];

		$this->controls['titleActiveTypography'] = [
			'group' => 'title',
			'label' => esc_html__( 'Active typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.brx-open .title',
				],
			],
		];

		$this->controls['titleActiveBackgroundColor'] = [
			'group' => 'title',
			'label' => esc_html__( 'Active background', 'bricks' ),
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
			'label' => esc_html__( 'Active border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.brx-open .accordion-title-wrapper',
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
			'group' => 'content',
			'label' => esc_html__( 'Padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'padding',
					'selector' => '.accordion-content-wrapper',
				],
			],
		];

		$this->controls['contentTypography'] = [
			'group' => 'content',
			'label' => esc_html__( 'Content typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.accordion-content-wrapper',
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
	}

	public function render() {
		$settings     = $this->settings;
		$theme_styles = $this->theme_styles;

		// FAQ Schema
		if ( isset( $settings['faqSchema'] ) ) {
			$this->faqpage_schema = [
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => []
			];
		}

		// Icon
		$icon = false;

		if ( ! empty( $settings['icon'] ) ) {
			$icon = self::render_icon( $settings['icon'], [ 'icon' ] );
		} elseif ( ! empty( $theme_styles['accordionIcon'] ) ) {
			$icon = self::render_icon( $theme_styles['accordionIcon'], [ 'icon' ] );
		}

		// Icon expanded
		$icon_expanded = false;

		if ( ! empty( $settings['iconExpanded'] ) ) {
			$icon_expanded = self::render_icon( $settings['iconExpanded'], [ 'icon', 'expanded' ] );
		} elseif ( ! empty( $theme_styles['accordionIconExpanded'] ) ) {
			$icon_expanded = self::render_icon( $theme_styles['accordionIconExpanded'], [ 'icon', 'expanded' ] );
		}

		$title_classes = [ 'accordion-title' ];

		if ( $icon && ! empty( $settings['iconPosition'] ) ) {
			$title_classes[] = "icon-{$settings['iconPosition']}";
		}

		$this->set_attribute( 'accordion-title', 'class', $title_classes );

		// STEP: Render Accordionss
		$accordions = ! empty( $settings['accordions'] ) ? $settings['accordions'] : false;

		if ( ! $accordions ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No accordion item added.', 'bricks' ),
				]
			);
		}

		$title_tag = ! empty( $settings['titleTag'] ) ? Helpers::sanitize_html_tag( $settings['titleTag'], 'h5' ) : 'h5';

		// Expand first item, Independent toggle
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

		$this->set_attribute( '_root', 'role', 'presentation' );

		$output = "<ul {$this->render_attributes( '_root' )}>";

		// Query Loop
		if ( isset( $settings['hasLoop'] ) ) {
			$query = new Query(
				[
					'id'       => $this->id,
					'settings' => $settings,
				]
			);

			$accordion = $accordions[0];

			$output .= $query->render( [ $this, 'render_repeater_item' ], compact( 'accordion', 'title_tag', 'icon', 'icon_expanded' ) );

			// Destroy query to explicitly remove it from the global store
			$query->destroy();
			unset( $query );
		} else {
			foreach ( $accordions as $index => $accordion ) {
				$output .= self::render_repeater_item( $accordion, $title_tag, $icon, $icon_expanded );
			}
		}

		$output .= '</ul>';

		echo $output;

		// FAQ Schema
		if ( ! empty( $this->faqpage_schema ) ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $this->faqpage_schema ) . '</script>';
		}
	}

	public function render_repeater_item( $accordion, $title_tag, $icon, $icon_expanded ) {
		$index    = $this->loop_index;
		$settings = $this->settings;
		$output   = '';

		// Set 'id' to open & scroll to specific tab (@since 1.8.6)
		if ( ! empty( $accordion['anchorId'] ) ) {
			$this->set_attribute( "accordion-item-$index", 'id', $accordion['anchorId'] );
		}

		$this->set_attribute( "accordion-item-$index", 'class', [ 'accordion-item' ] );

		// Set unique id for each item for ARIA roles and properties
		$accordion_id    = "accordion-{$this->id}-$index";
		$accordion_title = '';

		$output .= "<li {$this->render_attributes( "accordion-item-$index" )}>";

		if ( ! empty( $accordion['title'] ) || ! empty( $accordion['subtitle'] ) ) {
			$this->set_attribute( "accordion-title-wrapper-$index", 'class', [ 'accordion-title-wrapper' ] );

			// Set ARIA attributes
			$this->set_attribute( "accordion-title-wrapper-$index", 'aria-controls', "panel-{$accordion_id}" );
			$this->set_attribute( "accordion-title-wrapper-$index", 'aria-expanded', 'false' );
			// Add id to the button for aria-labelledby
			$this->set_attribute( "accordion-title-wrapper-$index", 'id', $accordion_id );
			$this->set_attribute( "accordion-title-wrapper-$index", 'role', 'button' );
			$this->set_attribute( "accordion-title-wrapper-$index", 'tabindex', '0' );

			$output .= "<div {$this->render_attributes("accordion-title-wrapper-$index")}>";

			if ( ! empty( $accordion['title'] ) ) {
				$accordion_title = $this->render_dynamic_data( $accordion['title'] );
				$output         .= "<div {$this->render_attributes( 'accordion-title' )}>";

				$this->set_attribute( "accordion-title-$index", 'class', [ 'title' ] );

				$output .= "<$title_tag {$this->render_attributes( "accordion-title-$index" )}>" . $accordion_title . "</$title_tag>";

				if ( $icon_expanded ) {
					$output .= $icon_expanded;
				}

				if ( $icon ) {
					$output .= $icon;
				}

				$output .= '</div>';
			}

			if ( ! empty( $accordion['subtitle'] ) ) {
				$this->set_attribute( "accordion-subtitle-$index", 'class', [ 'accordion-subtitle' ] );

				$output .= "<div {$this->render_attributes( "accordion-subtitle-$index" )}>" . $this->render_dynamic_data( $accordion['subtitle'] ) . '</div>';
			}

			$output .= '</div>';
		}

		$content = ! empty( $accordion['content'] ) ? $accordion['content'] : false;

		if ( $content ) {
			$this->set_attribute( "accordion-content-$index", 'class', [ 'accordion-content-wrapper' ] );

			// Add role and aria-labelledby for content
			$this->set_attribute( "accordion-content-$index", 'role', 'region' );
			$this->set_attribute( "accordion-content-$index", 'aria-labelledby', $accordion_id );
			$this->set_attribute( "accordion-content-$index", 'id', 'panel-' . $accordion_id );

			$content = $this->render_dynamic_data( $content );

			$content = Helpers::parse_editor_content( $content );

			$output .= "<div {$this->render_attributes( "accordion-content-$index" )}>$content</div>";
		}

		if ( isset( $settings['faqSchema'] ) ) {
			$faq_schema = [
				'@type'          => 'Question',
				'name'           => $accordion_title,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => strip_tags( $content )
				],
			];

			$this->faqpage_schema['mainEntity'][] = $faq_schema;
		}

		$output .= '</li>';

		$this->loop_index++;

		return $output;
	}
}
