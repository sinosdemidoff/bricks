<?php
$controls = [
	'stylesheet' => [
		'type'  => 'code',
		'mode'  => 'css',
		'label' => esc_html__( 'Custom CSS', 'bricks' ),
	]
];

return [
	'name'     => 'css',
	'controls' => $controls,
];
