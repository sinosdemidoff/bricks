<?php
$comments = new Bricks\Element_Post_Comments(
	[
		'settings' => [
			'title'        => true,
			'avatar'       => true,
			'cookies'      => get_option( 'show_comments_cookies_opt_in' ), // Settings > Discussion > Show comments cookies opt-in checkbox
			'label'        => true,
			'formTitle'    => true,
			'formTitleTag' => 'h2',
		],
	]
);

$comments->load();

$comments->init();
