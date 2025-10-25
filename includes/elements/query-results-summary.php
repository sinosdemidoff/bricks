<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Query_Results_Summary extends Element {
	public $category = 'query';
	public $name     = 'query-results-summary';
	public $icon     = 'ti-bar-chart-alt';

	public function get_label() {
		return esc_html__( 'Query Results Summary', 'bricks' );
	}

	public function set_controls() {
		$this->controls['elementInfo'] = [
			'type'    => 'info',
			'content' => esc_html__( 'Queries of the types "Post", "Term", and "User" are supported.', 'bricks' ),
		];

		$this->controls['queryId'] = [
			'label'            => esc_html__( 'Query', 'bricks' ),
			'type'             => 'query-list',
			'inline'           => true,
			'excludeMainQuery' => true, // @since 1.12.2
			'placeholder'      => esc_html__( 'Select', 'bricks' ),
		];

		$this->controls['statsFormat'] = [
			'label'       => esc_html__( 'Format', 'bricks' ),
			'type'        => 'text',
			'placeholder' => 'Results: %start% - %end% of %total% posts',
			'description' => esc_html__( 'Placeholders', 'bricks' ) . ': %start%, %end%, %total%',
		];

		$this->controls['oneResultText'] = [
			'label'       => esc_html( 'Text', 'bricks' ) . ': ' . esc_html__( 'One Result', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'One post found', 'bricks' ),
		];

		$this->controls['noResultsText'] = [
			'label'       => esc_html( 'Text', 'bricks' ) . ': ' . esc_html__( 'No results', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'No posts found', 'bricks' ),
		];
	}

	public function render() {
		$settings = $this->settings;
		$query_id = $settings['queryId'] ?? false;

		if ( ! $query_id ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No query selected', 'bricks' ),
				]
			);
		}

		// Maybe the target query is inside a Component (@since 1.12.3)
		$local_element = Helpers::get_element_data( $this->post_id, $query_id );

		if ( ! $local_element && ! empty( $this->element['instanceId'] ) ) {
			$local_element = Helpers::get_element_data( $this->post_id, $this->element['instanceId'] );

			// Correct the query ID if it's inside a Component
			if ( ! empty( $local_element['element']['id'] ) ) {
				$query_id = $query_id . '-' . $local_element['element']['id'];
			}
		}

		$html = '';
		// Data for JS
		$data         = [
			'noResultsText' => '',
			'oneResultText' => '',
			'statsFormat'   => '',
			'start'         => 0,
			'end'           => 0,
			'total'         => 0,
		];
		$query_object = Helpers::get_query_object_from_history_or_init( $query_id, $this->post_id );

		if ( is_a( $query_object, 'Bricks\Query' ) ) {
			$data['start'] = $query_object->start ?? 0;
			$data['end']   = $query_object->end ?? 0;
			$data['total'] = $query_object->count ?? 0;
		}

		// No results text
		$no_result = $settings['noResultsText'] ?? esc_html__( 'No posts found', 'bricks' );
		$no_result = $this->render_dynamic_data( $no_result );

		// One result text
		$one_result = $settings['oneResultText'] ?? esc_html__( 'One post found', 'bricks' );
		$one_result = $this->render_dynamic_data( $one_result );

		// Summary format
		$format = $settings['statsFormat'] ?? 'Results: %start% - %end% of %total% posts';
		$format = $this->render_dynamic_data( $format );

		// Sanitize
		$data['noResultsText'] = esc_html( $no_result );
		$data['oneResultText'] = esc_html( $one_result );
		$data['statsFormat']   = esc_html( $format );

		// Set data attribute for JS
		$this->set_attribute( '_root', 'data-brx-qr-stats', $query_id );
		$this->set_attribute( '_root', 'data-brx-qr-stats-data', wp_json_encode( $data ) );

		// Use noResultsText if no results found
		if ( $data['total'] < 1 ) {
			$html = $no_result;
		}

		// Use oneResultText if only 1 result found
		elseif ( $data['total'] === 1 ) {
			$html = $one_result;
		}

		// Use statsFormat if more than 1 result found
		else {
			// Replace placeholders
			$html = str_replace( [ '%start%', '%end%', '%total%' ], [ $data['start'], $data['end'], $data['total'] ], $format );
		}

		echo "<div {$this->render_attributes( '_root' )}>" . $html . '</div>';
	}
}
