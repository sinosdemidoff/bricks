<?php
$controls = [];

// Text Basic: Default HTML tag

$controls['tag'] = [
	'tab'         => 'content',
	'label'       => esc_html__( 'HTML tag', 'bricks' ) . ' (' . esc_html__( 'Default', 'bricks' ) . ')',
	'type'        => 'select',
	'inline'      => true,
	'options'     => [
		'div'        => 'div',
		'p'          => 'p',
		'span'       => 'span',
		'figcaption' => 'figcaption',
		'address'    => 'address',
		'figure'     => 'figure',
	],
	'placeholder' => 'div',
];

return [
	'name'        => 'text-basic',
	'controls'    => $controls,
	'cssSelector' => '',
];
