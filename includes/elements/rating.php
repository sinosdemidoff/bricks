<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Rating extends Element {
	public $category = 'general';
	public $name     = 'rating';
	public $icon     = 'ti-star';

	public function get_label() {
		return esc_html__( 'Rating', 'bricks' );
	}

	public function get_keywords() {
		return [ 'rating', 'stars', 'review' ];
	}

	public function set_controls() {
		$this->controls['rating'] = [
			'label'          => esc_html__( 'Rating', 'bricks' ),
			'type'           => 'number',
			'min'            => 0,
			'step'           => 0.1,
			'hasDynamicData' => true,
			'default'        => 3.5,
			'placeholder'    => 0,
		];

		$this->controls['maxRating'] = [
			'label'          => esc_html__( 'Max. rating', 'bricks' ),
			'type'           => 'number',
			'min'            => 1,
			'hasDynamicData' => true,
			'placeholder'    => 5,
		];

		$this->controls['gap'] = [
			'label' => esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => '',
				],
			],
		];

		// ICON

		$this->controls['iconSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Icon', 'bricks' ),
		];

		$this->controls['icon'] = [
			'label' => esc_html__( 'Icon', 'bricks' ),
			'type'  => 'icon',
		];

		$this->controls['iconColorFull'] = [
			'label'  => esc_html__( 'Icon color', 'bricks' ) . ': ' . esc_html__( 'Full', 'bricks' ),
			'type'   => 'color',
			'inline' => true,
			'css'    => [
				[
					'property' => 'color',
					'selector' => '.icon.full-color',
				],
			],
		];

		$this->controls['iconColorEmpty'] = [
			'label'  => esc_html__( 'Icon color', 'bricks' ) . ': ' . esc_html__( 'Empty', 'bricks' ),
			'type'   => 'color',
			'inline' => true,
			'css'    => [
				[
					'property' => 'color',
					'selector' => '.icon.empty-color',
				],
			],
		];

		$this->controls['iconSize'] = [
			'label'  => esc_html__( 'Icon size', 'bricks' ),
			'type'   => 'number',
			'units'  => true,
			'inline' => true,
			'css'    => [
				[
					'property' => 'font-size',
					'selector' => '.icon',
				]
			],
		];

		// REVIEW SCHEMA

		$this->controls['schemaSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Schema', 'bricks' ),
			'desc'  => 'schema.org <a href="https://schema.org/Review" target="_blank">' . esc_html__( 'Review', 'bricks' ) . '</a> & <a href="https://schema.org/Rating" target="_blank">' . esc_html__( 'Rating', 'bricks' ) . '</a>',
		];

		$this->controls['schema'] = [
			'label' => esc_html__( 'Generate review schema', 'bricks' ),
			'type'  => 'checkbox',
		];

		$this->controls['schemaType'] = [
			'label'       => esc_html__( 'Required', 'bricks' ) . ': ' . esc_html__( 'Reviewed item type', 'bricks' ),
			'type'        => 'text',
			'placeholder' => 'E.g., Product, Service, Restaurant, Recipe',
			'required'    => [ 'schema', '=', true ],
		];

		$this->controls['schemaName'] = [
			'label'       => esc_html__( 'Required', 'bricks' ) . ': ' . esc_html__( 'Reviewed item name', 'bricks' ),
			'type'        => 'text',
			'placeholder' => 'Thing',
			'required'    => [ 'schema', '=', true ],
		];

		$this->controls['reviewAuthor'] = [
			'label'    => esc_html__( 'Optional', 'bricks' ) . ': ' . esc_html__( 'Review author', 'bricks' ),
			'type'     => 'text',
			'required' => [ 'schema', '=', true ],
		];

		// Additional properties (@since 1.11.1)
		$this->controls['schemaProperties'] = [
			'label'    => esc_html__( 'Additional properties', 'bricks' ),
			'type'     => 'repeater',
			'required' => [ 'schema', '=', true ],
			'fields'   => [
				'key'   => [
					'label'       => esc_html__( 'Property name', 'bricks' ),
					'type'        => 'text',
					'placeholder' => 'startDate, location, price, etc.',
				],
				'value' => [
					'label'       => esc_html__( 'Property value', 'bricks' ),
					'type'        => 'text',
					'placeholder' => '2024-06-12, $19.99, etc.',
				],
				'type'  => [
					'label'       => esc_html__( 'Value type', 'bricks' ),
					'type'        => 'select',
					'options'     => [
						'Text'   => 'Text',
						'Number' => 'Number',
						'Object' => 'Nested object',
					],
					'placeholder' => 'Text',
				],
			],
		];
	}

	public function render() {
		$settings = $this->settings;

		// Sanity check for maxRating
		$max_rating = isset( $settings['maxRating'] ) ? $settings['maxRating'] : 5;
		if ( is_string( $max_rating ) && strpos( $max_rating, '{' ) !== false && strpos( $max_rating, '}' ) !== false ) {
			$max_rating = intval( $this->render_dynamic_data( $max_rating ) );
		}

		// Invalid max.rating: Default to 5
		if ( ! is_numeric( $max_rating ) || $max_rating < 1 ) {
			$max_rating = 5;
		}

		// Sanity check for rating
		$rating = isset( $settings['rating'] ) ? $settings['rating'] : 0;
		$rating = isset( $settings['rating'] ) ? $settings['rating'] : 0;
		if ( is_string( $rating ) && strpos( $rating, '{' ) !== false && strpos( $rating, '}' ) !== false ) {
			$rating = floatval( $this->render_dynamic_data( $rating ) );
		}

		if ( ! is_numeric( $rating ) ) {
			return $this->render_element_placeholder(
				[
					'icon-class' => 'ion-md-warning',
					'title'      => esc_html__( 'Rating', 'bricks' ) . ': ' . esc_html__( 'Invalid', 'bricks' ),
				]
			);
		}

		// If rating is less than 0, default to 0 and if it's higher than max rating, default to max rating
		$rating = max( 0, min( $rating, $max_rating ) );

		$full_rating    = floor( $rating );
		$partial_rating = $rating - $full_rating;
		$empty_rating   = $max_rating - $full_rating - ( $partial_rating > 0 ? 1 : 0 );

		// Custom icon
		$icon = ! empty( $settings['icon'] ) ? self::render_icon( $settings['icon'] ) : null;

		// Fallback: star icon
		if ( ! $icon ) {
			$icon = Helpers::file_get_contents( BRICKS_PATH_ASSETS . 'svg/frontend/star.svg' );
		}

		$output = "<div {$this->render_attributes('_root')}>";

		// STEP: Full rating
		for ( $i = 0; $i < $full_rating; $i++ ) {
			$output .= '<div class="icon full-color">' . $icon . '</div>';
		}

		// STEP: Partial rating
		if ( $partial_rating > 0 ) {
			$percentage_fill = $partial_rating * 100;
			$output         .= '<div class="icon-wrapper">';
			$output         .= '<div class="icon empty-color">' . $icon . '</div>';
			$output         .= '<div class="icon overlay full-color" style="width:' . $percentage_fill . '%">' . $icon . '</div>';
			$output         .= '</div>';
		}

		// STEP: Empty rating
		for ( $i = 0; $i < $empty_rating; $i++ ) {
			$output .= '<div class="icon empty-color">' . $icon . '</div>';
		}

		$output .= '</div>';

		// Generate schema.org markup if enabled and required fields are filled
		if ( isset( $settings['schema'] ) && isset( $settings['schemaType'] ) && isset( $settings['schemaName'] ) ) {
			$schema_type = trim( $settings['schemaType'] );
			$schema_name = trim( $settings['schemaName'] );

			// Generate schema if required fields are filled
			$schema = [
				'@context'     => 'https://schema.org',
				'@type'        => 'Review',
				'itemReviewed' => [
					'@type' => $schema_type,
					'name'  => $schema_name,
				]
			];

			// Add rating information if available
			if ( $max_rating > 0 ) {
				$rating_value           = $rating / $max_rating * 5;
				$schema['reviewRating'] = [
					'@type'       => 'Rating',
					'ratingValue' => number_format( $rating_value, 1 ),
					'bestRating'  => '5',
				];
			}

			// Add author if provided
			if ( isset( $settings['reviewAuthor'] ) ) {
				$schema['author'] = [
					'@type' => 'Person',
					'name'  => trim( $settings['reviewAuthor'] ),
				];
			}

			// Add additional properties from repeater (@since 1.11.1)
			if ( ! empty( $settings['schemaProperties'] ) ) {
				foreach ( $settings['schemaProperties'] as $property ) {
					if ( empty( $property['key'] ) || empty( $property['value'] ) ) {
						continue;
					}

					$value = $property['value'];
					$key   = $property['key'] ?? '';
					$type  = $property['type'] ?? 'Text';

					// Convert value based on type
					switch ( $type ) {
						case 'Number':
							$value = is_numeric( $value ) ? floatval( $value ) : $value;
							break;

						case 'Object':
							$value = json_decode( $value, true ) ?? $value;
							break;
					}

					$schema['itemReviewed'][ $key ] = $value;
				}
			}

			$output .= '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';
		}

		echo $output;
	}
}
