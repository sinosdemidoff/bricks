<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Custom_Render_Element extends Element {
	/**
	 * Hold the Bricks query instance
	 *
	 * To render in-loop data correctly.
	 *
	 * Used for Carousel, Posts, Related Posts, Woo Products.
	 *
	 * Bricks\Query || null
	 */
	private $bricks_query = null;

	/**
	 * Set Bricks query instance
	 *
	 * @param Bricks\Query $bricks_query
	 */
	public function set_bricks_query( $bricks_query ) {
		if ( is_a( $bricks_query, 'Bricks\Query' ) ) {
			$this->bricks_query = $bricks_query;
		}
	}

	/**
	 * Start the iteration
	 *
	 * @see includes/query.php render() method
	 */
	public function start_iteration() {
		if ( is_a( $this->bricks_query, 'Bricks\Query' ) ) {
			$this->bricks_query->is_looping = true;
			$this->bricks_query->loop_index = $this->bricks_query->init_loop_index();
			add_filter( 'bricks/query/loop_object_type', [ $this, 'set_loop_object_type' ], 10, 3 );
		}
	}

	/**
	 * Set the loop object for the current iteration.
	 *
	 * @param object $object
	 */
	public function set_loop_object( $object ) {
		if ( is_a( $this->bricks_query, 'Bricks\Query' ) ) {
			$this->bricks_query->loop_object = $object;
		}
	}

	/**
	 * Move to the next iteration
	 */
	public function next_iteration() {
		if ( is_a( $this->bricks_query, 'Bricks\Query' ) ) {
			$this->bricks_query->loop_index++;
		}
	}

	/**
	 * End the iteration
	 */
	public function end_iteration() {
		if ( is_a( $this->bricks_query, 'Bricks\Query' ) ) {
			$this->bricks_query->is_looping  = false;
			$this->bricks_query->loop_object = null;
			remove_filter( 'bricks/query/loop_object_type', [ $this, 'set_loop_object_type' ], 10, 3 );
			// Destroy query to explicitly remove it from the global store
			$this->bricks_query->destroy();
			// Set to null
			$this->bricks_query = null;
		}
	}

	/**
	 * Set loop object type to 'post'
	 * Posts element, Carousel element, Products element, Related Posts element are supported post loop only.
	 */
	public function set_loop_object_type( $object_type, $object, $query_id ) {
		return 'post';
	}
}
