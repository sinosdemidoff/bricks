<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Woocommerce_Breadcrumbs extends Element {
	public $category = 'woocommerce';
	public $name     = 'woocommerce-breadcrumbs';
	public $icon     = 'ti-line-dashed';
	private $custom_home_url;

	public function get_label() {
		return esc_html__( 'Breadcrumbs', 'bricks' ) . ' (WooCommerce)';
	}

	public function set_control_groups() {
		$this->control_groups['separator'] = [
			'title' => esc_html__( 'Separator', 'bricks' ),
		];
	}

	public function set_controls() {
		$this->controls['beforeLabel'] = [
			'tab'    => 'content',
			'label'  => esc_html__( 'Before', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
		];

		$this->controls['homeURL'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Home', 'bricks' ) . ': URL',
			'type'        => 'text',
			'inline'      => true,
			'placeholder' => home_url(), // = WooCommerce default
		];

		$this->controls['homeLabel'] = [
			'type'        => 'text',
			'tab'         => 'content',
			'label'       => esc_html__( 'Home', 'bricks' ) . ': ' . esc_html__( 'Label', 'bricks' ),
			'inline'      => true,
			'placeholder' => esc_html__( 'Home', 'bricks' ),
		];

		$this->controls['homeIcon'] = [
			'label'    => esc_html__( 'Home', 'bricks' ) . ': ' . esc_html__( 'Icon', 'bricks' ),
			'type'     => 'icon',
			'css'      => [
				[
					'selector' => 'svg.home',
				],
			],
			'rerender' => true,
		];

		$this->controls['homeIconGap'] = [
			'label' => esc_html__( 'Home', 'bricks' ) . ': ' . esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => '.navigation > a:has(.home)',
				],
			],
		];

		$this->controls['homeIconSize'] = [
			'label' => esc_html__( 'Home', 'bricks' ) . ': ' . esc_html__( 'Icon Size', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'font-size',
					'selector' => 'i.home',
				],
				[
					'property' => 'width',
					'selector' => 'svg.home',
				],
				[
					'property' => 'height',
					'selector' => 'svg.home',
				],
			],
		];

		$this->controls['hideHomeLabel'] = [
			'label'    => esc_html__( 'Hide label', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'homeIcon', '!=', '' ],
		];

		$this->controls['prefix'] = [
			'label'  => esc_html__( 'Prefix', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
		];

		$this->controls['suffix'] = [
			'label'  => esc_html__( 'Suffix', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
		];

		// SEPARATOR

		$this->controls['separatorType'] = [
			'group'       => 'separator',
			'label'       => esc_html__( 'Type', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'text' => esc_html__( 'Text', 'bricks' ),
				'icon' => esc_html__( 'Icon', 'bricks' ),
			],
			'inline'      => true,
			'placeholder' => esc_html__( 'Text', 'bricks' ),
		];

		$this->controls['separatorText'] = [
			'group'    => 'separator',
			'label'    => esc_html__( 'Separator', 'bricks' ),
			'type'     => 'text',
			'inline'   => true,
			'default'  => '/',
			'required' => [ 'separatorType', '!=', 'icon' ],
		];

		$this->controls['separatorIcon'] = [
			'group'    => 'separator',
			'label'    => esc_html__( 'Icon', 'bricks' ),
			'type'     => 'icon',
			'css'      => [
				[
					'selector' => 'svg.separator',
				],
			],
			'rerender' => true,
			'required' => [ 'separatorType', '=', 'icon' ],
		];

		$this->controls['separatorIconTypography'] = [
			'group'    => 'separator',
			'label'    => esc_html__( 'Icon typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.separator',
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
			'required' => [ 'separatorIcon.icon', '!=', '' ],
		];

		$this->controls['separatorGap'] = [
			'group'       => 'separator',
			'label'       => esc_html__( 'Gap', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'gap',
					'selector' => '.navigation',
				],
			],
			'placeholder' => [
				'top'    => 0,
				'right'  => 10,
				'bottom' => 0,
				'left'   => 10,
			],
		];

		$this->controls['separatorMargin'] = [
			'group'       => 'separator',
			'label'       => esc_html__( 'Margin', 'bricks' ),
			'type'        => 'spacing',
			'css'         => [
				[
					'property' => 'margin',
					'selector' => '.separator',
				],
			],
			'placeholder' => [
				'top'    => 0,
				'right'  => 10,
				'bottom' => 0,
				'left'   => 10,
			],
		];
	}

	public function render() {
		$settings = $this->settings;

		// Separator
		$separator_type = ! empty( $settings['separatorType'] ) ? $settings['separatorType'] : 'text';
		if ( $separator_type === 'icon' ) {
			$separator = ! empty( ! empty( $settings['separatorIcon'] ) ) ? self::render_icon( $settings['separatorIcon'], [ 'separator' ] ) : '';
		} elseif ( ! empty( $settings['separatorText'] ) ) {
			$separator = '<span class="separator">' . esc_html( $settings['separatorText'] ) . '</span>';
		} else {
			$separator = '<span class="separator"></span>';
		}

		$before     = ! empty( $settings['beforeLabel'] ) ? '<span class="before">' . $settings['beforeLabel'] . '</span>' : '';
		$prefix     = ! empty( $settings['prefix'] ) ? esc_html( $settings['prefix'] ) : '';
		$suffix     = ! empty( $settings['suffix'] ) ? esc_html( $settings['suffix'] ) : '';
		$home_label = ! empty( $settings['homeLabel'] ) ? $settings['homeLabel'] : esc_html__( 'Home', 'bricks' );

		$args = [
			'delimiter'   => $separator,
			'wrap_before' => '<nav>' . $before . '<span class="navigation">',
			'wrap_after'  => '</span></nav>',
			'before'      => $prefix,
			'after'       => $suffix,
			'home'        => $home_label,
		];

		$this->custom_home_url = ! empty( $settings['homeURL'] ) ? $this->render_dynamic_data( $settings['homeURL'], $this->post_id ) : false;

		echo "<div {$this->render_attributes( '_root' )}>";

		if ( ! empty( $this->custom_home_url ) ) {
			add_action( 'woocommerce_breadcrumb_home_url', [ $this, 'custom_home_url' ] );
		}

		ob_start();
		woocommerce_breadcrumb( $args );
		$breadcrumbs_html = ob_get_clean();

		// Replace home text with homeIcon (@since 2.0.2)
		$home_icon = ! empty( $settings['homeIcon'] ) ? self::render_icon( $settings['homeIcon'], [ 'home' ] ) : false;
		if ( $home_label && $home_icon ) {
			// Replace first occurence of $args['home'] value with $settings['homeIcon']
			$breadcrumbs_html = preg_replace(
				'/(<a[^>]*>)([^<]+)(<\/a>)/',
				isset( $settings['hideHomeLabel'] ) ? "$1$home_icon$3" : "$1$home_icon $home_label$3",
				$breadcrumbs_html,
				1
			);
		}

		echo $breadcrumbs_html;

		if ( ! empty( $this->custom_home_url ) ) {
			remove_action( 'woocommerce_breadcrumb_home_url', [ $this, 'custom_home_url' ] );
		}

		echo '</div>';
	}

	/**
	 * Custom home URL: 'woocommerce_breadcrumb_home_url' filter callback
	 *
	 * @since 1.10.1
	 */
	public function custom_home_url( $url ) {
		$custom_home_url = esc_url( $this->custom_home_url );
		return $custom_home_url ?? $url;
	}
}
