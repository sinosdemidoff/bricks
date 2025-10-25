<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Pagination extends Element {
	public $category = 'query';
	public $name     = 'pagination';
	public $icon     = 'ti-angle-double-right';

	public function get_label() {
		return esc_html__( 'Pagination', 'bricks' );
	}

	public function set_controls() {
		$this->controls['queryId'] = [
			'tab'    => 'content',
			'label'  => esc_html__( 'Query', 'bricks' ),
			'type'   => 'query-list',
			'inline' => true,
		];

		$this->controls['justifyContent'] = [
			'tab'     => 'content',
			'label'   => esc_html__( 'Alignment', 'bricks' ),
			'type'    => 'justify-content',
			'title'   => 'justify-content',
			'exclude' => 'space',
			'inline'  => true,
			'css'     => [
				[
					'selector' => '.bricks-pagination ul',
					'property' => 'justify-content',
				],
			],
		];

		$this->controls['navigationHeight'] = [
			'tab'   => 'content',
			'type'  => 'number',
			'units' => true,
			'label' => esc_html__( 'Height', 'bricks' ),
			'css'   => [
				[
					'property' => 'height',
					'selector' => '.bricks-pagination ul .page-numbers',
				],
			],
		];

		$this->controls['navigationWidth'] = [
			'tab'   => 'content',
			'type'  => 'number',
			'units' => true,
			'label' => esc_html__( 'Width', 'bricks' ),
			'css'   => [
				[
					'property' => 'width',
					'selector' => '.bricks-pagination ul .page-numbers',
				],
			],
		];

		$this->controls['gap'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Spacing', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'selector' => '.bricks-pagination ul',
					'property' => 'gap',
				],
			],
			'placeholder' => 20,
		];

		$this->controls['navigationBackground'] = [
			'tab'   => 'content',
			'type'  => 'color',
			'label' => esc_html__( 'Background', 'bricks' ),
			'css'   => [
				[
					'property' => 'background',
					'selector' => '.bricks-pagination ul .page-numbers',
				],
			],
		];

		$this->controls['navigationBorder'] = [
			'tab'   => 'content',
			'type'  => 'border',
			'label' => esc_html__( 'Border', 'bricks' ),
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.bricks-pagination ul .page-numbers',
				],
			],
		];

		$this->controls['navigationTypography'] = [
			'tab'   => 'content',
			'type'  => 'typography',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.bricks-pagination ul .page-numbers',
				],
			],
		];

		// CURRENT PAGE

		$this->controls['navigationActiveSeparator'] = [
			'tab'   => 'content',
			'type'  => 'separator',
			'label' => esc_html__( 'Current', 'bricks' ),
		];

		$this->controls['navigationBackgroundActive'] = [
			'tab'   => 'content',
			'type'  => 'color',
			'label' => esc_html__( 'Background', 'bricks' ),
			'css'   => [
				[
					'property' => 'background',
					'selector' => '.bricks-pagination ul .page-numbers.current',
				],
			],
		];

		$this->controls['navigationBorderActive'] = [
			'tab'   => 'content',
			'type'  => 'border',
			'label' => esc_html__( 'Border', 'bricks' ),
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.bricks-pagination ul .page-numbers.current',
				],
			],
		];

		$this->controls['navigationTypographyActive'] = [
			'tab'   => 'content',
			'type'  => 'typography',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.bricks-pagination ul .page-numbers.current',
				],
			],
		];

		// ICONS

		$this->controls['iconSeparator'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Icons', 'bricks' ),
		];

		$this->controls['prevIcon'] = [
			'tab'   => 'content',
			'label' => esc_html__( 'Previous Icon', 'bricks' ),
			'type'  => 'icon',
		];

		$this->controls['nextIcon'] = [
			'tab'   => 'content',
			'label' => esc_html__( 'Next Icon', 'bricks' ),
			'type'  => 'icon',
		];

		// MISC

		$this->controls['miscSeparator'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Miscellaneous', 'bricks' ),
		];

		$this->controls['endSize'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'End Size', 'bricks' ),
			'type'        => 'number',
			'min'         => 1,
			'placeholder' => 1,
			'description' => esc_html__( 'How many numbers on either the start and the end list edges.', 'bricks' ),
		];

		$this->controls['midSize'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Mid Size', 'bricks' ),
			'type'        => 'number',
			'min'         => 0,
			'placeholder' => 2,
			'description' => esc_html__( 'How many numbers on either side of the current page.', 'bricks' ),
		];

		$this->controls['ajax'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Enable AJAX', 'bricks' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Navigate through the different query pages without reloading the page.', 'bricks' ),
		];
	}

	public function render() {
		$settings         = $this->settings;
		$query_id         = $settings['queryId'] ?? 'main'; // Default: Main query (@since 1.12.2)
		$element_id       = $query_id;
		$element_settings = [];
		$query_element_id = $query_id;
		$main_query_id    = (string) Database::$main_query_id;

		// Query from a query Loop
		if ( $query_id && $query_id !== 'main' ) {
			$local_element = Helpers::get_element_data( $this->post_id, $element_id );

			/**
			 * No local element found: Try getting query element settings from component instance via 'instanceId'
			 *
			 * @since 1.12
			 */
			if ( ! $local_element ) {
				if ( ! empty( $this->element['instanceId'] ) ) {
					$local_element = Helpers::get_element_data( $this->post_id, $this->element['instanceId'] );
				}

				// Get component instance settings
				$component_instance = ! empty( $local_element['element'] ) ? Helpers::get_component_instance( $local_element['element'] ) : false;
				$component_elements = $component_instance['elements'] ?? [];

				// Prepend local element id to query element id prevent getting other instance of query (see: $query_instance in Query.php l64)
				if ( ! empty( $local_element['element']['id'] ) ) {
					$query_element_id = $query_id . '-' . $local_element['element']['id']; // Use dash instead of colon, easier for frontend when using querySelector (@since 1.12.2)
				}

				// Get query element settings from component element
				foreach ( $component_elements as $component_element ) {
					if ( $component_element['id'] === $query_id ) {
						$element_settings = $component_element['settings'] ?? [];
						break;
					}
				}
			}

			// Is local element
			else {
				$element_settings = Helpers::get_element_settings( $this->post_id, $element_id );
			}

			if ( empty( $element_settings ) ) {
				// Retun: No element nor component instance settings found
				return $this->render_element_placeholder(
					[
						'title' => esc_html__( 'The query element doesn\'t exist.', 'bricks' ),
					]
				);
			}

			// STEP: Ensure query_id is updated after the component logic, will be using in set_ajax_attributes() (@since 1.12.2)
			$query_id = $query_element_id;

			$query_obj = new Query(
				[
					'id'       => $query_element_id,
					'settings' => $element_settings,
				]
			);

			// Support pagination for post, user and term query object type
			if ( ! in_array( $query_obj->object_type, [ 'post','user','term', 'api' ] ) ) {
				return $this->render_element_placeholder(
					[
						'title' => esc_html__( 'This query type doesn\'t support pagination.', 'bricks' ),
					]
				);
			}

			// Use Bricks query object to get the current page and total pages as global $wp_query might be changed and inconsistent (#86bwqwa31)
			$current_page = isset( $query_obj->query_vars['paged'] ) ? max( 1, $query_obj->query_vars['paged'] ) : 1;
			$total_pages  = $query_obj->max_num_pages;

			// Destroy query to explicitly remove it from the global store
			$query_obj->destroy();
			unset( $query_obj );
		}

		// Handle main query setting in Bricks API endpoints (@since 2.0)
		elseif ( $main_query_id !== '' && $query_id === 'main' && ( Api::is_current_endpoint( 'query_result' ) || Api::is_current_endpoint( 'load_query_page' ) ) ) {
			$total_pages  = 1;
			$current_page = 1;
			$query_obj    = Helpers::get_query_object_from_history_or_init( $main_query_id, $this->post_id );

			if ( isset( $query_obj->query_vars ) && isset( $query_obj->query_vars['is_archive_main_query'] ) && $query_obj->query_vars['is_archive_main_query'] ) {
				$current_page = isset( $query_obj->query_vars['paged'] ) ? max( 1, $query_obj->query_vars['paged'] ) : 1;
				$total_pages  = isset( $query_obj->max_num_pages ) ? $query_obj->max_num_pages : 1;
			}
		}

		// Default: Main query
		else {
			global $wp_query;
			$current_page = max( 1, $wp_query->get( 'paged', 1 ) );
			$total_pages  = $wp_query->max_num_pages;
		}

		// Return: Less than two pages (@since 1.9.1)
		if ( $total_pages <= 1 && ( bricks_is_builder_call() || bricks_is_builder() ) ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No pagination results.', 'bricks' ),
				]
			);
		}

		// Hooks
		add_filter( 'bricks/paginate_links_args', [ $this, 'pagination_args' ] );

		// Render
		$pagination = Helpers::posts_navigation( $current_page, $total_pages );

		// Hide pagination on the frontend if there is only one page (@since 1.11)
		if ( $total_pages <= 1 && ! bricks_is_builder_call() && ! bricks_is_builder() ) {
			$this->set_attribute( '_root', 'style', 'display: none;' );
		}

		// Reset hooks
		remove_filter( 'bricks/paginate_links_args', [ $this, 'pagination_args' ] );

		if ( is_singular() && ! strlen( $pagination ) ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'No pagination on singular posts/pages.', 'bricks' ),
				]
			);
		}

		$this->set_ajax_attributes( $query_id );

		echo "<div {$this->render_attributes( '_root' )}>" . $pagination . '</div>';
	}

	public function pagination_args( $args ) {
		$settings = $this->settings;

		if ( ! empty( $settings['prevIcon'] ) ) {
			$args['prev_text'] = self::render_icon( $settings['prevIcon'] );
		}

		if ( ! empty( $settings['nextIcon'] ) ) {
			$args['next_text'] = self::render_icon( $settings['nextIcon'] );
		}

		if ( ! empty( $settings['endSize'] ) ) {
			$args['end_size'] = $settings['endSize'];
		}

		// midSize could be 0 (@since 1.12.2)
		if ( isset( $settings['midSize'] ) ) {
			$mid_size         = (int) $settings['midSize'];
			$mid_size         = $mid_size < 0 ? 0 : $mid_size;
			$args['mid_size'] = $mid_size;
		}

		return $args;
	}

	/**
	 * Set AJAX attributes
	 */
	private function set_ajax_attributes( $query_id ) {
		$settings = $this->settings;

		if ( ! isset( $settings['ajax'] ) || empty( $query_id ) ) {
			return;
		}

		// Retrieve main_query_id from Database class (@since 1.12.2)
		$main_query_id = (string) Database::$main_query_id;

		// Only replace the actual query id if main_query_id is set and loadMoreQuery is 'main'
		if ( $main_query_id !== '' && $query_id === 'main' ) {
			$query_id = $main_query_id;
		}

		// Do not set AJAX attributes if main query is set but it's not a bricks query
		if ( $query_id === 'main' ) {
			return;
		}

		// For AJAX pagination (@since 1.10)
		$this->set_attribute( '_root', 'data-query-element-id', $query_id );
		$this->set_attribute( '_root', 'class', 'brx-ajax-pagination' );
		$this->set_attribute( '_root', 'data-pagination-id', Query::is_any_looping() ? Helpers::generate_random_id( false ) : $this->id );

		if ( Helpers::enabled_query_filters() ) {
			// Filter type AJAX pagination (No need to enqueue 'bricks-filters' as pagination element will only use the filter logic when used together with a filter element)
			$filter_settings = [
				'filterId'            => $this->id,
				'targetQueryId'       => $query_id,
				'filterAction'        => 'filter',
				'filterType'          => 'pagination',
				'filterMethod'        => 'ajax',
				'filterApplyOn'       => 'change',
				'filterInputDebounce' => 500,
			];

			$this->set_attribute( '_root', 'data-brx-filter', wp_json_encode( $filter_settings ) );
		}
	}
}
