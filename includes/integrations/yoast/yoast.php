<?php
namespace Bricks\Integrations\Yoast;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Yoast {
	public function __construct() {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 10 );
		add_action( 'admin_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 10 );
	}

	/**
	 * Add Bricks integration with Yoast SEO to to the Gutenberg editor
	 *
	 * @since 1.11
	 */
	public function wp_enqueue_scripts( $hook_suffix ) {
		if ( bricks_is_builder() || ( is_admin() && $hook_suffix == 'post.php' ) ) {
			wp_enqueue_script( 'bricks-yoast', BRICKS_URL_ASSETS . 'js/integrations/yoast.min.js', [], filemtime( BRICKS_PATH_ASSETS . 'js/integrations/yoast.min.js' ), true );

			if ( bricks_is_builder() ) {
				$nonce = wp_create_nonce( 'bricks-nonce-builder' );
			} elseif ( is_admin() ) {
				$nonce = wp_create_nonce( 'bricks-nonce-admin' );
			} else {
				$nonce = wp_create_nonce( 'bricks-nonce' );
			}

			wp_localize_script(
				'bricks-yoast',
				'bricksYoast',
				[
					'postId'           => get_the_ID(),
					'nonce'            => $nonce,
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'renderWithBricks' => \Bricks\Helpers::render_with_bricks(),
					'contentData'      => '',
					'postIsUpdated'    => false,
				]
			);
		}
	}
}
