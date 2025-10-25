<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Map_Leaflet extends Element {
	public $category  = 'general';
	public $name      = 'map-leaflet';
	public $icon      = 'ti-map-alt';
	public $scripts   = [ 'bricksMapLeaflet' ];
	public $draggable = false;

	public function get_label() {
		return esc_html__( 'Map', 'bricks' ) . ' (Leaflet)';
	}

	public function get_keywords() {
		return [ 'osm', 'openstreetmap' ];
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'bricks-leaflet' );
		wp_enqueue_style( 'bricks-leaflet' );
	}

	public function set_control_groups() {
		$this->control_groups['layers'] = [
			'title' => esc_html__( 'Layers', 'bricks' ),
		];

		$this->control_groups['markers'] = [
			'title' => esc_html__( 'Markers', 'bricks' ),
		];

		$this->control_groups['map'] = [
			'title' => esc_html__( 'Map', 'bricks' ),
		];
	}

	public function set_controls() {
		/**
		 * Group: LAYERS
		 */
		$this->controls['layers'] = [
			'group'         => 'layers',
			'placeholder'   => esc_html__( 'Layers', 'bricks' ),
			'type'          => 'repeater',
			'titleProperty' => 'name',
			'fields'        => [
				'name'         => [
					'label'       => esc_html__( 'Name', 'bricks' ),
					'type'        => 'text',
					'placeholder' => 'OpenStreetMap',
				],
				'url'          => [
					'label'       => esc_html__( 'URL', 'bricks' ),
					'type'        => 'text',
					'placeholder' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
				],
				'minZoom'      => [
					'label'       => esc_html__( 'Min Zoom', 'bricks' ),
					'type'        => 'number',
					'placeholder' => '0',
				],
				'maxZoom'      => [
					'label'       => esc_html__( 'Max Zoom', 'bricks' ),
					'type'        => 'number',
					'placeholder' => '18',
				],
				'errorTileUrl' => [
					'label'       => esc_html__( 'Error Tile URL', 'bricks' ),
					'type'        => 'text',
					'placeholder' => '',
				],
				'attribution'  => [
					'label' => esc_html__( 'Attribution', 'bricks' ),
					'type'  => 'text',
				],
			],
			'default'       => [
				[
					'name' => 'OpenStreetMap',
					'url'  => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
				],
			],
		];

		/**
		 * Group: MARKERS
		 */
		$this->controls['markers'] = [
			'group'         => 'markers',
			'placeholder'   => esc_html__( 'Markers', 'bricks' ),
			'type'          => 'repeater',
			'titleProperty' => 'label',
			'fields'        => [
				'coordinates'      => [
					'label'       => esc_html__( 'Coordinates', 'bricks' ),
					'type'        => 'text',
					'desc'        => esc_html__( 'Format', 'bricks' ) . ': ' . esc_html__( 'Latitude', 'bricks' ) . ', ' . esc_html__( 'Longitude', 'bricks' ),
					'placeholder' => '52.5164154966524, 13.377643715349544',
				],

				'label'            => [
					'label' => esc_html__( 'Label', 'bricks' ),
					'type'  => 'text',
					'desc'  => esc_html__( 'To distinguish markers in the builder.', 'bricks' ),
				],

				'customIcon'       => [
					'label'    => esc_html__( 'Icon', 'bricks' ),
					'type'     => 'image',
					'dd'       => false,
					'unsplash' => false,
				],

				'customIconHeight' => [
					'group'       => 'markers',
					'label'       => esc_html__( 'Icon', 'bricks' ) . ': ' . esc_html__( 'Height', 'bricks' ) . ' (px)',
					'type'        => 'number',
					'placeholder' => '40',
					'required'    => [ 'customIcon', '!=', '' ],
				],

				'customIconWidth'  => [
					'group'       => 'markers',
					'label'       => esc_html__( 'Icon', 'bricks' ) . ': ' . esc_html__( 'Width', 'bricks' ) . ' (px)',
					'type'        => 'number',
					'placeholder' => '40',
					'required'    => [ 'customIcon', '!=', '' ],
				],

				'popupText'        => [
					'label' => esc_html__( 'Popup', 'bricks' ) . ' (' . esc_html__( 'Text', 'bricks' ) . ')',
					'type'  => 'editor',
				],
			]
		];

		// Marker icon

		$this->controls['markerIconSep'] = [
			'group'    => 'markers',
			'label'    => esc_html__( 'Marker', 'bricks' ) . ' (' . esc_html__( 'Default', 'bricks' ) . ')',
			'type'     => 'separator',
			'dd'       => false,
			'unsplash' => false,
		];

		$this->controls['markerIcon'] = [
			'group'    => 'markers',
			'label'    => esc_html__( 'Icon', 'bricks' ),
			'type'     => 'image',
			'dd'       => false,
			'unsplash' => false,
		];

		$this->controls['markerIconHeight'] = [
			'group'       => 'markers',
			'label'       => esc_html__( 'Icon', 'bricks' ) . ': ' . esc_html__( 'Height', 'bricks' ) . ' (px)',
			'type'        => 'number',
			'placeholder' => '40',
			'required'    => [ 'markerIcon', '!=', '' ],
		];

		$this->controls['markerIconWidth'] = [
			'group'       => 'markers',
			'label'       => esc_html__( 'Icon', 'bricks' ) . ': ' . esc_html__( 'Width', 'bricks' ) . ' (px)',
			'type'        => 'number',
			'placeholder' => '40',
			'required'    => [ 'markerIcon', '!=', '' ],
		];

		// MAP

		$this->controls['height'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Height', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'reload'      => true,
			'css'         => [
				[
					'property' => 'height',
				],
			],
			'placeholder' => '300px',
		];

		$this->controls['center'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Map center', 'bricks' ),
			'desc'        => esc_html__( 'Format', 'bricks' ) . ': ' . esc_html__( 'Latitude', 'bricks' ) . ', ' . esc_html__( 'Longitude', 'bricks' ),
			'type'        => 'text',
			'placeholder' => '52.5164154966524, 13.377643715349544',
		];

		$this->controls['zoom'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Zoom level', 'bricks' ) . ' (' . esc_html__( 'Initial', 'bricks' ) . ')',
			'type'        => 'number',
			'placeholder' => '13',
			'small'       => true,
		];

		$this->controls['minZoom'] = [
			'group' => 'map',
			'label' => esc_html__( 'Zoom level', 'bricks' ) . ' (' . esc_html__( 'Min', 'bricks' ) . ')',
			'type'  => 'number',
			'small' => true,
		];

		$this->controls['maxZoom'] = [
			'group' => 'map',
			'label' => esc_html__( 'Zoom level', 'bricks' ) . ' (' . esc_html__( 'Max', 'bricks' ) . ')',
			'type'  => 'number',
			'small' => true,
		];

		$this->controls['zoomSnap'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Zoom', 'bricks' ) . ': ' . esc_html__( 'Snap', 'bricks' ),
			'type'        => 'number',
			'small'       => true,
			'inline'      => true,
			'placeholder' => '1',
		];

		$this->controls['zoomDelta'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Zoom', 'bricks' ) . ': ' . esc_html__( 'Delta', 'bricks' ),
			'type'        => 'number',
			'small'       => true,
			'inline'      => true,
			'placeholder' => '1',
		];

		$this->controls['doubleClickZoom'] = [
			'group'   => 'map',
			'label'   => esc_html__( 'Zoom', 'bricks' ) . ': ' . esc_html__( 'Double-click', 'bricks' ),
			'type'    => 'select',
			'inline'  => true,
			'options' => [
				'true'   => esc_html__( 'Enabled', 'bricks' ),
				'center' => esc_html__( 'Enabled', 'bricks' ) . ' (' . esc_html__( 'Center', 'bricks' ) . ')',
				'false'  => esc_html__( 'Disabled', 'bricks' ),
			],
		];

		$this->controls['boxZoom'] = [
			'group'   => 'map',
			'label'   => esc_html__( 'Box Zoom', 'bricks' ),
			'type'    => 'checkbox',
			'inline'  => true,
			'default' => true,
		];

		$this->controls['zoomControl'] = [
			'group'   => 'map',
			'label'   => esc_html__( 'Zoom Control', 'bricks' ),
			'type'    => 'checkbox',
			'inline'  => true,
			'default' => true,
		];

		$this->controls['attributionControl'] = [
			'group'   => 'map',
			'label'   => esc_html__( 'Attribution Control', 'bricks' ),
			'type'    => 'checkbox',
			'inline'  => true,
			'default' => true,
		];

		$this->controls['closePopupOnClick'] = [
			'group'   => 'map',
			'label'   => esc_html__( 'Close popup on click', 'bricks' ),
			'type'    => 'checkbox',
			'inline'  => true,
			'default' => true,
		];

		$this->controls['dragging'] = [
			'group'   => 'map',
			'label'   => esc_html__( 'Dragging', 'bricks' ),
			'type'    => 'checkbox',
			'inline'  => true,
			'default' => true,
		];

		$this->controls['trackResize'] = [
			'group'   => 'map',
			'label'   => esc_html__( 'Track resize', 'bricks' ),
			'type'    => 'checkbox',
			'inline'  => true,
			'default' => true,
			'desc'    => esc_html__( 'Learn more', 'bricks' ) . ': <a href="https://leafletjs.com/reference.html#map" target="_blank">https://leafletjs.com/reference.html#map</a>',
		];
	}

	public function render() {
		$settings = $this->settings;

		// STEP: Prepare layers
		$layers = $settings['layers'] ?? [];

		// STEP: Prepare markers
		$markers = [];

		if ( ! empty( $settings['markers'] ) ) {
			foreach ( $settings['markers'] as $marker ) {

				// Skip: No coordinates defined
				if ( empty( $marker['coordinates'] ) || strpos( $marker['coordinates'], ',' ) === false ) {
					continue;
				}

				$m = $this->get_lat_lng( $marker['coordinates'] );

				// Add popup settings
				if ( ! empty( $marker['popupText'] ) ) {
					$m['popupText'] = $marker['popupText'];
				}

				// Add custom marker icon
				if ( isset( $marker['customIcon']['url'] ) ) {
					$m['icon'] = [
						'iconUrl'  => $marker['customIcon']['url'],
						'iconSize' => [
							$marker['customIconWidth'] ?? 40,
							$marker['customIconHeight'] ?? 40,
						],
					];
				}

				// Add default marker icon
				elseif ( isset( $settings['markerIcon']['url'] ) ) {
					$m['icon'] = [
						'iconUrl'  => $settings['markerIcon']['url'],
						'iconSize' => [
							$settings['markerIconWidth'] ?? 40,
							$settings['markerIconHeight'] ?? 40,
						],
					];
				}

				$markers[] = $m;
			}
		}

		// STEP: Prepare map options
		$double_click_zoom = false;

		if ( isset( $settings['doubleClickZoom'] ) ) {
			if ( $settings['doubleClickZoom'] === 'center' ) {
				$double_click_zoom = 'center';
			} else {
				$double_click_zoom = $settings['doubleClickZoom'] == 'true';
			}
		}

		$map_options = [
			'map'     => [
				// Controls
				'attributionControl' => $settings['attributionControl'] ?? false,
				'zoomControl'        => $settings['zoomControl'] ?? false,
				'center'             => $this->get_lat_lng( $settings['center'] ?? '' ),

				// Interactions
				'closePopupOnClick'  => $settings['closePopupOnClick'] ?? false,
				'boxZoom'            => $settings['boxZoom'] ?? false,
				'doubleClickZoom'    => $double_click_zoom,
				'dragging'           => $settings['dragging'] ?? false,
				'zoomSnap'           => isset( $settings['zoomSnap'] ) ? $settings['zoomSnap'] : 1,
				'zoomDelta'          => isset( $settings['zoomDelta'] ) ? $settings['zoomDelta'] : 1,
				'trackResize'        => $settings['trackResize'] ?? false,

				// Zoom
				'zoom'               => isset( $settings['zoom'] ) ? $settings['zoom'] : 13,
			],
			'markers' => $markers,
			'layers'  => $layers,
		];

		// Add min zoom if defined
		if ( isset( $settings['minZoom'] ) ) {
			$map_options['map']['minZoom'] = $settings['minZoom'];
		}

		// Add max zoom if defined
		if ( isset( $settings['maxZoom'] ) ) {
			$map_options['map']['maxZoom'] = $settings['maxZoom'];
		}

		// Add setings as data attribute
		$this->set_attribute( '_root', 'data-map-options', wp_json_encode( $map_options ) );

		echo "<div {$this->render_attributes( '_root' )}></div>";
	}

	/**
	 * Get 'lat' and 'lng' from string
	 *
	 * @param string $coordinates
	 * @param array  $default If coordinates are empty or invalid
	 *
	 * @return array
	 */
	private function get_lat_lng( $coordinates, $default = [
		'lat' => 52.5164154966524,
		'lng' => 13.377643715349544
	] ) {

		// Return: No coordinates defined
		if ( empty( $coordinates ) || strpos( $coordinates, ',' ) === false ) {
			return $default;
		}

		$coordinates = explode( ',', $coordinates );

		if ( count( $coordinates ) === 2 ) {
			return [
				'lat' => trim( $coordinates[0] ),
				'lng' => trim( $coordinates[1] ),
			];
		}

		return $default;
	}
}
