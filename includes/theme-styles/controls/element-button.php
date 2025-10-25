<?php
$controls = [];

// Default

$controls['defaultSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Default', 'bricks' ),
];

$controls['typography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => '.bricks-button',
		],
	],
];

$controls['background'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => '.bricks-button:not([class*="bricks-background-"]):not([class*="bricks-color-"]):not(.outline)',
		],
	],
];

$controls['border'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => '.bricks-button',
		],
	],
];

$controls['boxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => '.bricks-button',
		],
	],
];

$controls['transition'] = [
	'label'          => esc_html__( 'Transition', 'bricks' ),
	'css'            => [
		[
			'property' => 'transition',
			'selector' => '.bricks-button',
		],
	],
	'hasDynamicData' => false,
	'hasVariables'   => true,
	'type'           => 'text',
	'description'    => sprintf( '<a href="https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Transitions/Using_CSS_transitions" target="_blank">%s</a>', esc_html__( 'Learn more about CSS transitions', 'bricks' ) ),
];

$controls['outlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => '.bricks-button.outline',
		],
	],
];

$controls['outlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => '.bricks-button.outline',
		],
	],
];

$controls['outlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => '.bricks-button.outline',
		],
	],
];

$controls['outlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => '.bricks-button.outline',
		],
	],
];

// Primary

$controls['primarySeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Primary', 'bricks' ),
];

$controls['primaryTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="primary"]',
		],
	],
];

$controls['primaryBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="primary"]:not(.outline)',
		],
	],
];

$controls['primaryBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="primary"]',
		],
	],
];

$controls['primaryBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="primary"]',
		],
	],
];

// Outline

$controls['primaryOutlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="primary"].outline',
		],
	],
];

$controls['primaryOutlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="primary"].outline',
		],
	],
];

$controls['primaryOutlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="primary"].outline',
		],
	],
];

$controls['primaryOutlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="primary"].outline',
		],
	],
];

// Secondary

$controls['secondarySeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Secondary', 'bricks' ),
];

$controls['secondaryTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="secondary"]',
		],
	],
];

$controls['secondaryBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="secondary"]:not(.outline)',
		],
	],
];

$controls['secondaryBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="secondary"]',
		],
	],
];

$controls['secondaryBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="secondary"]',
		],
	],
];

// Outline

$controls['secondaryOutlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="secondary"].outline',
		],
	],
];

$controls['secondaryOutlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="secondary"].outline',
		],
	],
];

$controls['secondaryOutlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="secondary"].outline',
		],
	],
];

$controls['secondaryOutlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="secondary"].outline',
		],
	],
];

// Light

$controls['lightSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Light', 'bricks' ),
];

$controls['lightTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="light"]:not(.bricks-lightbox)',
		],
	],
];

$controls['lightBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="light"]:not(.outline):not(.bricks-lightbox)',
		],
	],
];

$controls['lightBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="light"]:not(.bricks-lightbox)',
		],
	],
];

$controls['lightBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="light"]:not(.bricks-lightbox)',
		],
	],
];

// Outline

$controls['lightOutlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="light"].outline',
		],
	],
];

$controls['lightOutlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="light"].outline',
		],
	],
];

$controls['lightOutlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="light"].outline',
		],
	],
];

$controls['lightOutlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="light"].outline',
		],
	],
];

// Dark

$controls['darkSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Dark', 'bricks' ),
];

$controls['darkTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="dark"]',
		],
	],
];

$controls['darkBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="dark"]:not(.outline)',
		],
	],
];

$controls['darkBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="dark"]',
		],
	],
];

$controls['darkBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="dark"]',
		],
	],
];

// Outline

$controls['darkOutlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="dark"].outline',
		],
	],
];

$controls['darkOutlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="dark"].outline',
		],
	],
];

$controls['darkOutlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="dark"].outline',
		],
	],
];

$controls['darkOutlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="dark"].outline',
		],
	],
];

// Muted

$controls['mutedSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Muted', 'bricks' ),
];

$controls['mutedTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="muted"]',
		],
	],
];

$controls['mutedBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="muted"]:not(.outline)',
		],
	],
];

$controls['mutedBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="muted"]',
		],
	],
];

$controls['mutedBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="muted"]',
		],
	],
];

// Outline

$controls['mutedOutlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="muted"].outline',
		],
	],
];

$controls['mutedOutlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="muted"].outline',
		],
	],
];

$controls['mutedOutlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="muted"].outline',
		],
	],
];

$controls['mutedOutlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="muted"].outline',
		],
	],
];

// Info

$controls['infoSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Info', 'bricks' ),
];

$controls['infoTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="info"]',
		],
	],
];

$controls['infoBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="info"]:not(.outline)',
		],
	],
];

$controls['infoBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="info"]',
		],
	],
];

$controls['infoBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="info"]',
		],
	],
];

// Outline

$controls['infoOutlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="info"].outline',
		],
	],
];

$controls['infoOutlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="info"].outline',
		],
	],
];

$controls['infoOutlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="info"].outline',
		],
	],
];

$controls['infoOutlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="info"].outline',
		],
	],
];

// Success

$controls['successSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Success', 'bricks' ),
];

$controls['successTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="success"]',
		],
	],
];

$controls['successBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="success"]:not(.outline)',
		],
	],
];

$controls['successBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="success"]',
		],
	],
];

$controls['successBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="success"]',
		],
	],
];

// Outline

$controls['successOutlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="success"].outline',
		],
	],
];

$controls['successOutlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="success"].outline',
		],
	],
];

$controls['successOutlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="success"].outline',
		],
	],
];

$controls['successOutlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="success"].outline',
		],
	],
];

// Warning

$controls['warningSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Warning', 'bricks' ),
];

$controls['warningTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="warning"]',
		],
	],
];

$controls['warningBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="warning"]:not(.outline)',
		],
	],
];

$controls['warningBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="warning"]',
		],
	],
];

$controls['warningBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="warning"]',
		],
	],
];

// Outline

$controls['warningOutlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="warning"].outline',
		],
	],
];

$controls['warningOutlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="warning"].outline',
		],
	],
];

$controls['warningOutlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="warning"].outline',
		],
	],
];

$controls['warningOutlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="warning"].outline',
		],
	],
];

// Danger

$controls['dangerSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Style', 'bricks' ) . ' - ' . esc_html__( 'Danger', 'bricks' ),
];

$controls['dangerTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="danger"]',
		],
	],
];

$controls['dangerBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Background color', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="danger"]:not(.outline)',
		],
	],
];

$controls['dangerBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="danger"]',
		],
	],
];

$controls['dangerBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="danger"]',
		],
	],
];

// Outline

$controls['dangerOutlineBackground'] = [
	'type'  => 'color',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Background', 'bricks' ),
	'css'   => [
		[
			'property' => 'background-color',
			'selector' => ':root .bricks-button[class*="danger"].outline',
		],
	],
];

$controls['dangerOutlineBorder'] = [
	'type'  => 'border',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Border', 'bricks' ),
	'css'   => [
		[
			'property' => 'border',
			'selector' => ':root .bricks-button[class*="danger"].outline',
		],
	],
];

$controls['dangerOutlineBoxShadow'] = [
	'type'  => 'box-shadow',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Box shadow', 'bricks' ),
	'css'   => [
		[
			'property' => 'box-shadow',
			'selector' => ':root .bricks-button[class*="danger"].outline',
		],
	],
];

$controls['dangerOutlineTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Outline', 'bricks' ) . ' - ' . esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => ':root .bricks-button[class*="danger"].outline',
		],
	],
];

// Size - Default

$controls['sizeDefaultSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Size - Default', 'bricks' ),
];

$controls['sizeDefaultPadding'] = [
	'type'        => 'spacing',
	'label'       => esc_html__( 'Padding', 'bricks' ),
	'css'         => [
		[
			'property' => 'padding',
			'selector' => '.bricks-button',
		],
	],
	'placeholder' => [
		'top'    => '0.5em',
		'right'  => '1em',
		'bottom' => '0.5em',
		'left'   => '1em',
	],
];

// Size - Small

$controls['sizeSmSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Size - Small', 'bricks' ),
];

$controls['sizeSmPadding'] = [
	'type'        => 'spacing',
	'label'       => esc_html__( 'Padding', 'bricks' ),
	'css'         => [
		[
			'property' => 'padding',
			'selector' => '.bricks-button.sm',
		],
	],
	'placeholder' => [
		'top'    => '0.4em',
		'right'  => '1em',
		'bottom' => '0.4em',
		'left'   => '1em',
	],
];

$controls['sizeSmTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => '.bricks-button.sm',
		],
	],
];

// Size - Medium

$controls['sizeMdSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Size - Medium', 'bricks' ),
];

$controls['sizeMdPadding'] = [
	'type'        => 'spacing',
	'label'       => esc_html__( 'Padding', 'bricks' ),
	'css'         => [
		[
			'property' => 'padding',
			'selector' => '.bricks-button.md',
		],
	],
	'placeholder' => [
		'top'    => '0.5em',
		'right'  => '1em',
		'bottom' => '0.5em',
		'left'   => '1em',
	],
];


$controls['sizeMdTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => '.bricks-button.md',
		],
	],
];

// Size - Large

$controls['sizeLgSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Size - Large', 'bricks' ),
];

$controls['sizeLgPadding'] = [
	'type'        => 'spacing',
	'label'       => esc_html__( 'Padding', 'bricks' ),
	'css'         => [
		[
			'property' => 'padding',
			'selector' => '.bricks-button.lg',
		],
	],
	'placeholder' => [
		'top'    => '0.6em',
		'right'  => '1em',
		'bottom' => '0.6em',
		'left'   => '1em',
	],
];

$controls['sizeLgTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => '.bricks-button.lg',
		],
	],
];

// Size - Extra Large

$controls['sizeXlSeparator'] = [
	'type'  => 'separator',
	'label' => esc_html__( 'Size - Extra Large', 'bricks' ),
];

$controls['sizeXlPadding'] = [
	'type'        => 'spacing',
	'label'       => esc_html__( 'Padding', 'bricks' ),
	'css'         => [
		[
			'property' => 'padding',
			'selector' => '.bricks-button.xl',
		],
	],
	'placeholder' => [
		'top'    => '0.8em',
		'right'  => '1em',
		'bottom' => '0.8em',
		'left'   => '1em',
	],
];

$controls['sizeXlTypography'] = [
	'type'  => 'typography',
	'label' => esc_html__( 'Typography', 'bricks' ),
	'css'   => [
		[
			'property' => 'font',
			'selector' => '.bricks-button.xl',
		],
	],
];

/**
 * STEP: Wrap every 'selector' rules in a ":where()" to lower specificity
 *
 * Example: Button background-color set on class should precede background-color set in the theme styles.
 *
 * @see #86c4q0j31
 * @since 2.1
 * @since 2.1.2 No longer in use as the "Colors" theme styles now precede the "Button" theme styles.
 */
// foreach ( $controls as $key => $control ) {
// if ( ! empty( $control['css'] ) && is_array( $control['css'] ) ) {
// foreach ( $control['css'] as $index => $css ) {
// if ( isset( $css['selector'] ) ) {
// $controls[ $key ]['css'][ $index ]['selector'] = ':where(' . $css['selector'] . ')';
// }
// }
// }
// }

return [
	'name'     => 'button',
	'controls' => $controls,
];
