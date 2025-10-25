<article id="brx-content" <?php post_class( 'wordpress' ); ?>>
	<?php
	$title              = get_the_title();
	$default_page_title = $title ? "<h1>$title</h1>" : '';
	$default_page_title = apply_filters( 'bricks/default_page_title', $default_page_title, get_the_ID() );

	if ( ! empty( $default_page_title ) ) {
		echo $default_page_title;
	}

	the_content();

	echo Bricks\Helpers::page_break_navigation();
	?>
</article>
