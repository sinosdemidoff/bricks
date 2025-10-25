<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Offcanvas extends Element {
	public $category = 'general';
	public $name     = 'offcanvas';
	public $icon     = 'ti-layout-sidebar-left';
	public $scripts  = [ 'bricksOffcanvas' ];
	public $nestable = true;

	public function get_label() {
		return esc_html__( 'Offcanvas', 'bricks' );
	}

	public function get_keywords() {
		return [ 'menu', 'mobile', 'nestable' ];
	}

	public function set_controls() {
		$this->controls['info'] = [
			'type'    => 'info',
			'content' => Helpers::article_link( 'menu-builder/#mobile-menu-offcanvas', esc_html__( 'Add a "Toggle" element to your page that targets this Offcanvas to open it.', 'bricks' ) ),
		];

		$this->controls['direction'] = [
			'label'       => esc_html__( 'Direction', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'placeholder' => esc_html__( 'Left', 'bricks' ),
			'options'     => [
				'top'    => esc_html__( 'Top', 'bricks' ),
				'right'  => esc_html__( 'Right', 'bricks' ),
				'bottom' => esc_html__( 'Bottom', 'bricks' ),
				'left'   => esc_html__( 'Left', 'bricks' ),
			],
			'rerender'    => true,
		];

		$this->controls['effect'] = [
			'label'       => esc_html__( 'Effect', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'placeholder' => esc_html__( 'Slide', 'bricks' ),
			'options'     => [
				'slide'  => esc_html__( 'Slide', 'bricks' ),
				'offset' => esc_html__( 'Offset', 'bricks' ),
			],
		];

		$this->controls['closeOn'] = [
			'label'       => esc_html__( 'Close on', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'backdrop' => esc_html__( 'Backdrop', 'bricks' ) . ' (' . esc_html__( 'Click', 'bricks' ) . ')',
				'esc'      => 'ESC (' . esc_html__( 'Key', 'bricks' ) . ')',
				'none'     => esc_html__( 'None', 'bricks' ),
			],
			'placeholder' => esc_html__( 'Backdrop', 'bricks' ) . ' & ESC',
		];

		$this->controls['width'] = [
			'label' => esc_html__( 'Width', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'width',
					'selector' => '&[data-direction] .brx-offcanvas-inner',
				],
			],
		];

		$this->controls['height'] = [
			'label' => esc_html__( 'Height', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'height',
					'selector' => '&[data-direction] .brx-offcanvas-inner',
				],
			],
		];

		// Transition duration (@since 2.0)
		$this->controls['transitionDuration'] = [
			'label'       => esc_html__( 'Transition', 'bricks' ) . ': ' . esc_html__( 'Duration', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'placeholder' => '0.2s',
			'description' => esc_html__( 'Set to "0s" to disable open/close animation.', 'bricks' ),
			'css'         => [
				[
					'property' => 'transition-duration',
					'selector' => '.brx-offcanvas-inner, .brx-offcanvas-backdrop',
				],
			],
		];

		// Transition timing function (@since 2.0)
		$this->controls['transitionTimingFunction'] = [
			'label'       => esc_html__( 'Transition', 'bricks' ) . ': ' . esc_html__( 'Timing function', 'bricks' ),
			'type'        => 'text',
			'placeholder' => 'cubic-bezier(0.25, 0, 0.25, 1)',
			'css'         => [
				[
					'property' => 'transition-timing-function',
					'selector' => '.brx-offcanvas-inner, .brx-offcanvas-backdrop',
				],
			],
		];

		$this->controls['ariaLabel'] = [
			'label'          => 'aria-label',
			'type'           => 'text',
			'inline'         => true,
			'hasDynamicData' => false,
			'placeholder'    => esc_html__( 'Offcanvas', 'bricks' ),
		];

		// NOTE: Undocumented: Stored in builder $_state._addedClasses only (@since 1.8)
		$this->controls['_addedClasses'] = [
			'label' => esc_html__( 'Keep open while styling', 'bricks' ),
			'type'  => 'checkbox',
			'class' => 'brx-open',
		];

		$this->controls['noScrollBody'] = [
			'label' => esc_html__( 'No scroll', 'bricks' ) . ' (body)',
			'type'  => 'checkbox',
		];

		$this->controls['scrollToTop'] = [
			'label'       => esc_html__( 'Scroll to top', 'bricks' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Scroll to top when offcanvas opens.', 'bricks' ),
		];

		$this->controls['noAutoFocus'] = [
			'label'       => esc_html__( 'Disable auto focus', 'bricks' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Don\'t auto focus on first focusable element when offcanvas opens.', 'bricks' ),
		];

		$this->controls['openByDefault'] = [
			'label' => esc_html__( 'Open on page load', 'bricks' ),
			'desc'  => esc_html__( 'Not applied in the builder.', 'bricks' ),
			'type'  => 'checkbox',
		];
	}

	public function get_nestable_children() {
		return [
			// Inner
			[
				'name'      => 'block',
				'label'     => esc_html__( 'Content', 'bricks' ),
				'deletable' => false, // Prevent deleting this element directly. NOTE: Undocumented (@since 1.8)
				'cloneable' => false, // Prevent cloning this element directly.  NOTE: Undocumented (@since 1.8)
				'children'  => [
					[
						'name'     => 'text-basic',
						'settings' => [
							'text' => esc_html__( 'Add your offcanvas content in here', 'bricks' ),
						],
					],
					[
						'name'  => 'toggle',
						'label' => esc_html__( 'Toggle', 'bricks' ) . ' (' . esc_html__( 'Close', 'bricks' ) . ')',
					],
				],
				'settings'  => [
					'_hidden' => [
						'_cssClasses' => 'brx-offcanvas-inner',
					],
				],
			],

			// Backdrop (delete to disable)
			[
				'name'     => 'block',
				'label'    => esc_html__( 'Backdrop', 'bricks' ),
				'children' => [],
				'settings' => [
					'_hidden' => [
						'_cssClasses' => 'brx-offcanvas-backdrop',
					],
				],
			],
		];
	}

	public function render() {
		$settings = $this->settings;

		$this->set_attribute( '_root', 'aria-label', ! empty( $settings['ariaLabel'] ) ? esc_attr( $settings['ariaLabel'] ) : esc_html__( 'Offcanvas', 'bricks' ) );
		$this->set_attribute( '_root', 'data-direction', ! empty( $settings['direction'] ) ? esc_attr( $settings['direction'] ) : 'left' );

		if ( ! empty( $settings['noScrollBody'] ) ) {
			$this->set_attribute( '_root', 'data-no-scroll', 'true' );
		}

		// No auto focus
		if ( isset( $settings['noAutoFocus'] ) ) {
			$this->set_attribute( '_root', 'data-no-auto-focus', 'true' );
		}

		// Scroll to top
		if ( isset( $settings['scrollToTop'] ) ) {
			$this->set_attribute( '_root', 'data-scroll-to-top', 'true' );
		}

		// Close on (Click on backdrop, ESC)
		if ( isset( $settings['closeOn'] ) ) {
			$this->set_attribute( '_root', 'data-close-on', esc_attr( $settings['closeOn'] ) );
		}

		// Open by default on the frontend (@since 2.0)
		if ( isset( $settings['openByDefault'] ) && ! bricks_is_builder() && ! bricks_is_builder_call() ) {
			$this->set_attribute( '_root', 'class', 'brx-open' );
		}

		if ( ! empty( $settings['effect'] ) ) {
			$this->set_attribute( '_root', 'data-effect', esc_attr( $settings['effect'] ) );
		}

		$output = "<{$this->tag} {$this->render_attributes( '_root' )}>";

		$output .= Frontend::render_children( $this );

		$output .= "</{$this->tag}>";

		echo $output;
	}
}
