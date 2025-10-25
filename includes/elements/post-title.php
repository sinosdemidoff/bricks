<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Post_Title extends Element {
	public $category = 'single';
	public $name     = 'post-title';
	public $icon     = 'ti-text';
	public $tag      = 'h3';

	public function get_label() {
		return esc_html__( 'Post Title', 'bricks' );
	}

	public function set_controls() {
		$this->controls['titleInfo'] = [
			'content'  => '<a href="#">' . esc_html__( 'Edit title: Settings > Page Settings > SEO', 'bricks' ) . '</a>',
			'type'     => 'info',
			'required' => [ 'postTitle', '=', '', 'pageSettings' ],
		];

		$this->controls['tag'] = [
			'label'       => esc_html__( 'HTML tag', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'h1' => 'h1',
				'h2' => 'h2',
				'h3' => 'h3',
				'h4' => 'h4',
				'h5' => 'h5',
				'h6' => 'h6',
			],
			'inline'      => true,
			'placeholder' => 'h3',
			'default'     => 'h1',
		];

		$this->controls['type'] = [
			'label'       => esc_html__( 'Type', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'hero' => esc_html__( 'Hero', 'bricks' ),
				'lead' => esc_html__( 'Lead', 'bricks' ),
			],
			'inline'      => true,
			'placeholder' => esc_html__( 'None', 'bricks' ),
		];

		$this->controls['style'] = [
			'label'       => esc_html__( 'Style', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['styles'],
			'inline'      => true,
			'placeholder' => esc_html__( 'None', 'bricks' ),
		];

		$this->controls['linkToPost'] = [
			'label' => esc_html__( 'Link to post', 'bricks' ),
			'type'  => 'checkbox',
		];

		if ( get_post_type() === BRICKS_DB_TEMPLATE_SLUG ) {
			$this->controls['context'] = [
				'label'       => esc_html__( 'Add context', 'bricks' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Add context to title on archive/search templates.', 'bricks' ),
			];
		}

		// Prefix

		$this->controls['prefixSep'] = [
			'label' => esc_html__( 'Prefix', 'bricks' ),
			'type'  => 'separator',
		];

		$this->controls['prefix'] = [
			'label'  => esc_html__( 'Prefix', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
		];

		$this->controls['prefixSpacing'] = [
			'label'    => esc_html__( 'Spacing', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'selector' => '.post-prefix',
					'property' => 'margin-inline-end',
				],
			],
			'required' => [ 'prefix', '!=', '' ],
		];

		$this->controls['prefixBlock'] = [
			'label'    => esc_html__( 'Block', 'bricks' ),
			'type'     => 'checkbox',
			'css'      => [
				[
					'selector' => '.post-prefix',
					'property' => 'display',
					'value'    => 'block',
				],
			],
			'required' => [ 'prefix', '!=', '' ],
		];

		$this->controls['prefixTypography'] = [
			'label'    => esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'selector' => '.post-prefix',
					'property' => 'font',
				],
			],
			'required' => [ 'prefix', '!=', '' ],
		];

		// Suffix

		$this->controls['suffixSep'] = [
			'label' => esc_html__( 'Suffix', 'bricks' ),
			'type'  => 'separator',
		];

		$this->controls['suffix'] = [
			'label'  => esc_html__( 'Suffix', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
		];

		$this->controls['suffixSpacing'] = [
			'label'    => esc_html__( 'Spacing', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'selector' => '.post-suffix',
					'property' => 'margin-inline-start',
				],
			],
			'required' => [ 'suffix', '!=', '' ],
		];

		$this->controls['suffixBlock'] = [
			'label'    => esc_html__( 'Block', 'bricks' ),
			'type'     => 'checkbox',
			'css'      => [
				[
					'selector' => '.post-suffix',
					'property' => 'display',
					'value'    => 'block',
				],
			],
			'required' => [ 'suffix', '!=', '' ],
		];

		$this->controls['suffixTypography'] = [
			'label'    => esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'selector' => '.post-suffix',
					'property' => 'font',
				],
			],
			'required' => [ 'suffix', '!=', '' ],
		];
	}

	public function render() {
		$settings     = $this->settings;
		$prefix       = ! empty( $settings['prefix'] ) ? $settings['prefix'] : false;
		$suffix       = ! empty( $settings['suffix'] ) ? $settings['suffix'] : false;
		$context      = isset( $settings['context'] ) ? $settings['context'] : false;
		$link_to_post = isset( $settings['linkToPost'] );
		$output       = '';

		if ( $link_to_post ) {
			$output .= '<a href="' . get_the_permalink( $this->post_id ) . '">';
		}

		if ( $prefix ) {
			$this->set_attribute( 'prefix', 'class', [ 'post-prefix' ] );

			$output .= "<span {$this->render_attributes( 'prefix' )}>{$prefix}</span>";
		}

		$output .= Helpers::get_the_title( $this->post_id, $context );

		if ( $suffix ) {
			$this->set_attribute( 'suffix', 'class', [ 'post-suffix' ] );

			$output .= "<span {$this->render_attributes( 'suffix' )}>{$suffix}</span>";
		}

		if ( $link_to_post ) {
			$output .= '</a>';
		}

		if ( isset( $settings['type'] ) ) {
			$this->set_attribute( '_root', 'class', "bricks-type-{$settings['type']}" );
		}

		if ( isset( $settings['style'] ) ) {
			$this->set_attribute( '_root', 'class', "bricks-color-{$settings['style']}" );
		}

		echo "<{$this->tag} {$this->render_attributes( '_root' )}>$output</{$this->tag}>";
	}
}
