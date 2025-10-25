<?php

use Bricks\Setup;

$controls = [];

// Icon

$controls['popupIcon'] = [
	'label' => esc_html__( 'Icon', 'bricks' ),
	'type'  => 'icon',
];

// NOTE: Set popup CSS control outside of control 'link' (CSS is not applied to nested controls)
$controls['popupIconBackgroundColor'] = [
	'label' => esc_html__( 'Icon background color', 'bricks' ),
	'type'  => 'color',
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => '.brxe-image .icon',
		],
	],
];

$controls['popupIconBorder'] = [
	'label' => esc_html__( 'Icon border', 'bricks' ),
	'type'  => 'border',
	'css'   => [
		[
			'property' => 'border',
			'selector' => '.brxe-image .icon',
		],
	],
];

$controls['popupIconBoxShadow'] = [
	'label' => esc_html__( 'Icon box shadow', 'bricks' ),
	'type'  => 'box-shadow',
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => '.brxe-image .icon',
		],
	],
];

$controls['popupIconHeight'] = [
	'label' => esc_html__( 'Icon height', 'bricks' ),
	'type'  => 'number',
	'units' => true,
	'css'   => [
		[
			'property' => 'line-height',
			'selector' => '.brxe-image .icon',
		],
	],
];

$controls['popupIconWidth'] = [
	'label' => esc_html__( 'Icon width', 'bricks' ),
	'type'  => 'number',
	'units' => true,
	'css'   => [
		[
			'property' => 'width',
			'selector' => '.brxe-image .icon',
		],
	],
];

$controls['popupIconTypography'] = [
	'label'    => esc_html__( 'Icon typography', 'bricks' ),
	'type'     => 'typography',
	'css'      => [
		[
			'property' => 'font',
			'selector' => '.brxe-image .icon',
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
	'required' => [ 'popupIcon.icon', '!=', '' ],
];

$controls['captionSep'] = [
	'label' => esc_html__( 'Caption', 'bricks' ),
	'type'  => 'separator',
];

$controls['caption'] = [
	'label'       => esc_html__( 'Caption type', 'bricks' ),
	'type'        => 'select',
	'options'     => [
		'none'       => esc_html__( 'No caption', 'bricks' ),
		'attachment' => esc_html__( 'Attachment', 'bricks' ),
	],
	'inline'      => true,
	'placeholder' => esc_html__( 'Attachment', 'bricks' ),
];

/**
 * Caption: Custom styles
 *
 * @since 2.1
 */
$controls['captionCustomStyles'] = [
	'label'    => esc_html__( 'Custom styles', 'bricks' ),
	'type'     => 'checkbox',
	'desc'     => esc_html__( 'These styles will also apply to all Gutenberg captions.', 'bricks' ),
	'rerender' => true,
];

$controls['captionMargin'] = [
	'label'    => esc_html__( 'Margin', 'bricks' ),
	'type'     => 'spacing',
	'css'      => [
		[
			'property' => 'margin',
			'selector' => '.brxe-image .bricks-image-caption-custom, .wp-element-caption:not(.wp-block-gallery *)',
		],
	],
	'required' => [ 'captionCustomStyles', '!=', '' ],
];

$controls['captionPadding'] = [
	'label'    => esc_html__( 'Padding', 'bricks' ),
	'type'     => 'spacing',
	'css'      => [
		[
			'property' => 'padding',
			'selector' => '.brxe-image .bricks-image-caption-custom, .wp-element-caption:not(.wp-block-gallery *)',
		],
	],
	'required' => [ 'captionCustomStyles', '!=', '' ],
];

$controls['captionPosition'] = [
	'type'     => 'select',
	'label'    => esc_html__( 'Position', 'bricks' ),
	'options'  => Setup::$control_options['position'],
	'css'      => [
		[
			'property' => 'position',
			'selector' => '.brxe-image .bricks-image-caption-custom, .wp-element-caption:not(.wp-block-gallery *)',
		],
		[
			'property' => 'position',
			'selector' => '.wp-block-image:has(.wp-element-caption:not(.wp-block-gallery *))',
			'value'    => 'relative',
		],
	],
	'inline'   => true,
	'required' => [ 'captionCustomStyles', '!=', '' ],
];

$controls['captionPositions'] = [
	'label'      => esc_html__( 'Position', 'bricks' ),
	'type'       => 'dimensions',
	'linkedIcon' => false,
	'css'        => [
		[
			'selector' => '.brxe-image .bricks-image-caption-custom, .wp-element-caption:not(.wp-block-gallery *)',
		],
	],
	'required'   => [ 'captionCustomStyles', '!=', '' ],
];

$controls['captionBackgroundColor'] = [
	'label'    => esc_html__( 'Background color', 'bricks' ),
	'type'     => 'color',
	'css'      => [
		[
			'property' => 'background', // Don't use background-color to overwrite linear-gradient background
			'selector' => '.brxe-image .bricks-image-caption-custom, .wp-element-caption:not(.wp-block-gallery *)',
		],
		[
			'property' => 'text-shadow',
			'selector' => '.brxe-image .bricks-image-caption-custom, .wp-element-caption:not(.wp-block-gallery *)',
			'value'    => 'none',
		],
	],
	'required' => [ 'captionCustomStyles', '!=', '' ],
];

$controls['captionBorder'] = [
	'label'    => esc_html__( 'Border', 'bricks' ),
	'type'     => 'border',
	'css'      => [
		[
			'property' => 'border',
			'selector' => '.brxe-image .bricks-image-caption-custom, .wp-element-caption:not(.wp-block-gallery *)',
		],
	],
	'required' => [ 'captionCustomStyles', '!=', '' ],
];

$controls['captionBoxShadow'] = [
	'label'    => esc_html__( 'Box shadow', 'bricks' ),
	'type'     => 'box-shadow',
	'css'      => [
		[
			'property' => 'box-shadow',
			'selector' => '.brxe-image .bricks-image-caption-custom, .wp-element-caption:not(.wp-block-gallery *)',
		],
	],
	'required' => [ 'captionCustomStyles', '!=', '' ],
];

$controls['captionTypography'] = [
	'label'    => esc_html__( 'Typography', 'bricks' ),
	'type'     => 'typography',
	'css'      => [
		[
			'property' => 'font',
			'selector' => '.brxe-image .bricks-image-caption-custom, .wp-element-caption:not(.wp-block-gallery *)',
		],
	],
	'required' => [ 'captionCustomStyles', '!=', '' ],
];

return [
	'name'     => 'image',
	'controls' => $controls,
	// 'cssSelector' => '.brxe-image', // Because we also apply custom caption styles to the WP Gutenberg captions, we cannot use the default selector '.bricks-image' here.
];
