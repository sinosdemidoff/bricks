<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Back_To_Top extends Element {
	public $category = 'general';
	public $name     = 'back-to-top';
	public $icon     = 'ti-arrow-up';
	public $nestable = true;
	public $tag      = 'button';

	public function get_label() {
		return esc_html__( 'Back to Top', 'bricks' );
	}

	public function set_controls() {
		$this->controls['_padding']['default'] = [
			'top'    => 10,
			'right'  => 10,
			'bottom' => 10,
			'left'   => 10,
		];

		$this->controls['tag'] = [
			'label'       => esc_html__( 'HTML tag', 'bricks' ),
			'type'        => 'text',
			'inline'      => true,
			'placeholder' => $this->tag,
		];

		$this->controls['ariaLabel'] = [
			'label'  => 'aria-label',
			'type'   => 'text',
			'inline' => true,
			'desc'   => esc_html__( 'Set if this element doesn\'t contain any descriptive text.', 'bricks' ),
		];

		// POSITION

		$this->controls['positionSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Position', 'bricks' ),
		];

		$this->controls['position'] = [
			'label'       => esc_html__( 'Position', 'bricks' ),
			'type'        => 'select',
			'options'     => Setup::$control_options['position'],
			'css'         => [
				[
					'property' => 'position',
					'selector' => '',
				],
			],
			'placeholder' => 'fixed',
			'inline'      => true,
		];

		$this->controls['positionTop'] = [
			'label' => esc_html__( 'Top', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'top',
					'selector' => '',
				],
			],
		];

		$this->controls['positionRight'] = [
			'label'       => esc_html__( 'Right', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'right',
					'selector' => '',
				],
			],
			'placeholder' => '20px',
		];

		$this->controls['positionBottom'] = [
			'label'       => esc_html__( 'Bottom', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'bottom',
					'selector' => '',
				],
			],
			'placeholder' => '20px',
		];

		$this->controls['positionLeft'] = [
			'label' => esc_html__( 'Left', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'left',
					'selector' => '',
				],
			],
		];

		// MISC

		$this->controls['miscSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Misc', 'bricks' ),
		];

		// z-index
		$control_z_index                = $this->controls['_zIndex'];
		$control_z_index['placeholder'] = 9999;
		unset( $control_z_index['tab'] );
		unset( $control_z_index['group'] );
		unset( $control_z_index['required'] );
		unset( $this->controls['_zIndex'] );

		$this->controls['_zIndex'] = $control_z_index;

		// Gap
		$control_gap            = $this->controls['_gap'];
		$control_gap['default'] = 10;
		unset( $control_gap['tab'] );
		unset( $control_gap['group'] );
		unset( $control_gap['required'] );
		unset( $this->controls['_gap'] );

		$this->controls['_gap'] = $control_gap;

		// CSS Transition
		$control_css_transition           = $this->controls['_cssTransition'];
		$control_css_transition['inline'] = true;
		unset( $control_css_transition['tab'] );
		unset( $control_css_transition['description'] );
		unset( $control_css_transition['group'] );
		unset( $control_css_transition['required'] );
		unset( $this->controls['_cssTransition'] );

		$this->controls['_cssTransition'] = $control_css_transition;

		$this->controls['visibleAfter'] = [
			'label' => esc_html__( 'Visible after', 'bricks' ) . ' ... px',
			'type'  => 'number',
		];

		$this->controls['visibleOnScrollUp'] = [
			'label' => esc_html__( 'Visible on scroll up', 'bricks' ),
			'type'  => 'checkbox',
		];

		$this->controls['smoothScroll'] = [
			'label' => esc_html__( 'Smooth scroll', 'bricks' ),
			'type'  => 'checkbox',
		];
	}

	public function get_nestable_children() {
		return [
			[
				'name'     => 'icon',
				'label'    => esc_html__( 'Icon', 'bricks' ),
				'settings' => [
					'icon'     => [
						'library' => 'ionicons',
						'icon'    => 'ion-ios-arrow-round-up',
					],
					'iconSize' => 30,
				],
			],
			[
				'name'     => 'text',
				'label'    => esc_html__( 'Text', 'bricks' ),
				'settings' => [
					'text' => esc_html__( 'Back to Top', 'bricks' ),
				],
			],
		];
	}

	public function render() {
		$settings = $this->settings;

		if ( ! empty( $settings['smoothScroll'] ) ) {
			$this->set_attribute( '_root', 'data-smooth-scroll', true );
		}

		if ( empty( $settings['visibleAfter'] ) && empty( $settings['visibleOnScrollUp'] ) ) {
			$this->set_attribute( '_root', 'class', 'visible' );
		}

		if ( ! empty( $settings['visibleAfter'] ) ) {
			$this->set_attribute( '_root', 'data-visible-after', esc_attr( $settings['visibleAfter'] ) );
		}

		if ( ! empty( $settings['visibleOnScrollUp'] ) ) {
			$this->set_attribute( '_root', 'class', 'up' );
		}

		if ( ! empty( $settings['ariaLabel'] ) ) {
			$this->set_attribute( '_root', 'aria-label', esc_attr( $settings['ariaLabel'] ) );
		}

		$output = "<{$this->tag} {$this->render_attributes('_root')}>";

		$output .= Frontend::render_children( $this );

		$output .= "</{$this->tag}>";

		echo $output;
	}
}
