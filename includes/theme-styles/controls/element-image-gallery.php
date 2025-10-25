<?php
use Bricks\Setup;

$controls = [];

$controls['layout'] = [
	'label'       => esc_html__( 'Layout', 'bricks' ),
	'type'        => 'select',
	'options'     => [
		'grid'    => esc_html__( 'Grid', 'bricks' ),
		'masonry' => 'Masonry',
		'metro'   => 'Metro',
	],
	'placeholder' => esc_html__( 'Grid', 'bricks' ),
	'inline'      => true,
];

$controls['imageRatio'] = [
	'label'       => esc_html__( 'Image ratio', 'bricks' ),
	'description' => esc_html__( 'Precedes image height setting.', 'bricks' ),
	'type'        => 'text',
	'inline'      => true,
	'css'         => [
		[
			'selector' => '.image',
			'property' => 'aspect-ratio',
		],
	],
	'required'    => [ 'layout', '!=', [ 'masonry', 'metro' ] ],
];

$controls['columns'] = [
	'label'       => esc_html__( 'Columns', 'bricks' ),
	'type'        => 'number',
	'min'         => 1,
	'placeholder' => 3,
	'required'    => [ 'layout', '!=', [ 'metro' ] ],
];

$controls['imageHeight'] = [
	'label'       => esc_html__( 'Image height', 'bricks' ),
	'type'        => 'number',
	'units'       => true,
	'css'         => [
		[
			'property'  => 'padding-top',
			'selector'  => '.brxe-image-gallery .image',
			'important' => true,
		],
	],
	'placeholder' => '',
	'required'    => [ 'layout', '!=', [ 'masonry', 'metro' ] ],
];

$controls['gutter'] = [
	'label'       => esc_html__( 'Spacing', 'bricks' ),
	'type'        => 'number',
	'units'       => true,
	'css'         => [
		[
			'property' => '--gutter',
			'selector' => '.brxe-image-gallery',
		],
	],
	'placeholder' => 0,
];


/**
 * Caption: Custom styles
 *
 * @since 2.1
 */
$controls['captionSep'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Caption', 'bricks' ),
	'desc'  => esc_html__( 'These styles also apply to all Gutenberg captions.', 'bricks' ),
];

$controls['captionMargin'] = [
	'label' => esc_html__( 'Margin', 'bricks' ),
	'type'  => 'spacing',
	'css'   => [
		[
			'property' => 'margin',
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
		],
	],
];

$controls['captionPadding'] = [
	'label' => esc_html__( 'Padding', 'bricks' ),
	'type'  => 'spacing',
	'css'   => [
		[
			'property' => 'padding',
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
		],
	],
];

$controls['captionPosition'] = [
	'label'   => esc_html__( 'Position', 'bricks' ),
	'type'    => 'select',
	'options' => Setup::$control_options['position'],
	'inline'  => true,
	'css'     => [
		[
			'property' => 'position',
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
		],
		[
			'property' => 'flex',
			'selector' => '.wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
			'value'    => '0 0 auto',
		],
		[
			'property' => 'display',
			'selector' => '.wp-block-gallery.has-nested-images figure.wp-block-image:has(figcaption):before',
			'value'    => 'none',
		],
	],
];

$controls['captionPositions'] = [
	'label' => esc_html__( 'Position', 'bricks' ),
	'type'  => 'dimensions',
	'css'   => [
		[
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
		],
		[
			'property' => 'width',
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
			'value'    => 'auto',
		],
	],
];

$controls['captionBackgroundColor'] = [
	'label' => esc_html__( 'Background color', 'bricks' ),
	'type'  => 'color',
	'css'   => [
		[
			'property' => 'background', // Don't use background-color to overwrite linear-gradient background
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
		],
		[
			'property' => 'text-shadow',
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
			'value'    => 'none',
		],
	],
];

$controls['captionBorder'] = [
	'label' => esc_html__( 'Border', 'bricks' ),
	'type'  => 'border',
	'css'   => [
		[
			'property' => 'border',
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
		],
	],
];

$controls['captionBoxShadow'] = [
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'type'  => 'box-shadow',
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
		],
	],
];

$controls['captionTypography'] = [
	'label' => esc_html__( 'Typography', 'bricks' ),
	'type'  => 'typography',
	'css'   => [
		[
			'property' => 'font',
			'selector' => '.brxe-image-gallery .bricks-image-caption, .wp-block-gallery.has-nested-images figure.wp-block-image figcaption.wp-element-caption',
		],
	],
];

return [
	'name'        => 'image-gallery',
	'controls'    => $controls,
	'cssSelector' => '',
];
