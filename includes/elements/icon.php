<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Icon extends Element {
	public $category = 'basic';
	public $name     = 'icon';
	public $icon     = 'ti-star';

	public function get_label() {
		return esc_html__( 'Icon', 'bricks' );
	}

	public function set_controls() {
		$this->controls['icon'] = [
			'label'   => esc_html__( 'Icon', 'bricks' ),
			'type'    => 'icon',
			'root'    => true, // To target 'svg' root
			'default' => [
				'library' => 'themify',
				'icon'    => 'ti-star',
			],
		];

		$this->controls['iconColor'] = [
			'label'    => esc_html__( 'Color', 'bricks' ),
			'type'     => 'color',
			'required' => [ 'icon.icon', '!=', '' ],
			'css'      => [
				[
					'property' => 'color',
				],
				[
					'property' => 'fill',
				],
			],
		];

		$this->controls['iconSize'] = [
			'label'    => esc_html__( 'Size', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'required' => [ 'icon.icon', '!=', '' ],
			'css'      => [
				[
					'property' => 'font-size',
				],
			],
		];

		$this->controls['link'] = [
			'label' => esc_html__( 'Link', 'bricks' ),
			'type'  => 'link',
		];

		/**
		 * Accordion nested: State (collapsed/expanded)
		 *
		 * @since 2.0
		 */
		$this->controls['isAccordionIcon'] = [
			'label'    => esc_html__( 'Is', 'bricks' ) . ' ' . esc_html__( 'Accordion', 'bricks' ) . ' (' . esc_html__( 'Nestable', 'bricks' ) . ')' . ' ' . esc_html__( 'Icon', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ '_parentSelector', '=', 'accordion-title-wrapper' ],
		];

		$this->controls['accordionTitleIconSep'] = [
			'type'        => 'separator',
			'label'       => esc_html__( 'Accordion', 'bricks' ) . ': ' . esc_html__( 'Collapsed', 'bricks' ) . '/' . esc_html__( 'Expanded', 'bricks' ),
			'description' => esc_html__( 'By default, this icon is always visible and rotates 90° when the accordion item is expanded. To display different icons for each state, duplicate this icon and set the "Show" option to "Collapsed" for one and "Expanded" for the other.', 'bricks' ),
			'required'    => [
				[ '_parentSelector', '=', 'accordion-title-wrapper' ],
				[ 'isAccordionIcon', '=', true ]
			],
		];

		$this->controls['accordionTitleIconState'] = [
			'label'       => esc_html__( 'Show', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'collapsed' => esc_html__( 'Collapsed', 'bricks' ),
				'expanded'  => esc_html__( 'Expanded', 'bricks' ),
			],
			'placeholder' => esc_html__( 'Always visible', 'bricks' ),
			'required'    => [
				[ '_parentSelector', '=', 'accordion-title-wrapper' ],
				[ 'isAccordionIcon', '=', true ]
			],
		];

		$this->controls['accordionTitleIconTransform'] = [
			'label'       => esc_html__( 'Transform', 'bricks' ) . ' (' . esc_html__( 'Expanded', 'bricks' ) . ')',
			'type'        => 'transform',
			'css'         => [
				[
					'property' => '--brx-icon-transform',
					'selector' => '',
				],
			],
			'required'    => [
				[ '_parentSelector', '=', 'accordion-title-wrapper' ],
				[ 'isAccordionIcon', '=', true ],
				[ 'accordionTitleIconState', '=', '' ],
			],
			'placeholder' => [
				'rotateZ' => 90,
			],
		];

		$this->controls['accordionTitleIconTransition'] = [
			'label'          => esc_html__( 'Transition', 'bricks' ),
			'class'          => 'ltr',
			'css'            => [
				[
					'property' => 'transition',
					'selector' => '',
				],
			],
			'type'           => 'text',
			'inline'         => true,
			'hasDynamicData' => false,
			'hasVariables'   => true,
			'placeholder'    => 'transform 0.1s',
			'required'       => [
				[ '_parentSelector', '=', 'accordion-title-wrapper' ],
				[ 'isAccordionIcon', '=', true ],
				[ 'accordionTitleIconState', '=', '' ],
			],
		];
	}

	public function render() {
		$settings = $this->settings;
		$icon     = $settings['icon'] ?? false;
		$link     = ! empty( $settings['link'] ) && bricks_is_frontend() ? $settings['link'] : false; // Front-end only (@since 1.10.2)

		if ( ! $icon ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No icon selected.', 'bricks' ),
				]
			);
		}

		// Linked icon: Remove custom attributes from '_root' to add to the 'link'
		if ( $link ) {
			$custom_attributes = $this->get_custom_attributes( $settings );

			if ( is_array( $custom_attributes ) ) {
				foreach ( $custom_attributes as $key => $value ) {
					if ( isset( $this->attributes['_root'][ $key ] ) ) {
						unset( $this->attributes['_root'][ $key ] );
					}
				}
			}
		}

		// Is accordion Icon (@since 2.0)
		$is_accordion_icon = $settings['isAccordionIcon'] ?? false;

		if ( $is_accordion_icon ) {
			$accordion_state = $settings['accordionTitleIconState'] ?? '';
			if ( $accordion_state ) {
				// Expanded/collapsed mode
				$this->attributes['_root']['class'][] = esc_attr( "brx-icon-$accordion_state" );
			} else {
				// Transform mode
				$this->attributes['_root']['class'][] = 'brx-icon-transform';
			}
		}

		// Support dynamic data color in loop
		if ( isset( $settings['iconColor']['raw'] ) && Query::is_looping() ) {
			if ( strpos( $settings['iconColor']['raw'], '{' ) !== false ) {
				$this->attributes['_root']['data-query-loop-index'] = Query::get_loop_index();
			}
		}

		// Run root attributes through filter (@since 1.10)
		if ( ! $link ) {
			$this->attributes = apply_filters( 'bricks/element/render_attributes', $this->attributes, '_root', $this );
		}

		$icon = self::render_icon( $icon, $this->attributes['_root'] );

		// Return: No icon, even after dynamic data (@since 2.0)
		if ( $icon && ! preg_match( '/<[^<]+>/', $icon, $matches ) && empty( $matches ) ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No icon selected.', 'bricks' ),
				]
			);
		}

		if ( $link ) {
			$this->set_link_attributes( 'link', $link );

			// Add custom class to the link wrapper so we can target it in CSS (@since 2.0)
			$this->set_attribute( 'link', 'class', 'bricks-link-wrapper' );

			// Add custom attributes to the link instead of the icon
			echo "<a {$this->render_attributes( 'link', true )}>";
			echo $icon;
			echo '</a>';
		} else {
			echo $icon;
		}
	}
}
