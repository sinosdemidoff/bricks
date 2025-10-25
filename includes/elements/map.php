<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Map extends Element {
	public $category  = 'general';
	public $name      = 'map';
	public $icon      = 'ti-location-pin';
	public $scripts   = [ 'bricksMap' ];
	public $draggable = false;

	public function get_label() {
		return esc_html__( 'Map', 'bricks' ) . ' (Google)';
	}

	/**
	 * Triggered when element->load()
	 *
	 * @since 2.0
	 */
	public function add_actions() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_script' ], 12 );
	}

	/**
	 * Register scripts (previously in setup.php)
	 *
	 * @since 2.0
	 */
	public function register_script() {
		// New separate script for map element
		wp_register_script( 'bricks-map', BRICKS_URL_ASSETS . 'js/elements/map.min.js', [ 'bricks-scripts' ], filemtime( BRICKS_PATH_ASSETS . 'js/elements/map.min.js' ), [ 'in_footer' => true ] );

		if ( ! empty( Database::$global_settings['apiKeyGoogleMaps'] ) ) {
			// loading=async, Infobox script will be enqueued via JS (@since 2.0)
			wp_register_script( 'bricks-google-maps', 'https://maps.googleapis.com/maps/api/js?callback=bricksMap&loading=async&key=' . Database::$global_settings['apiKeyGoogleMaps'], [ 'bricks-scripts' ], null, [ 'in_footer' => true ] );
		}
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'bricks-map' );
		wp_enqueue_script( 'bricks-google-maps' );
	}

	public function set_control_groups() {
		$this->control_groups['addresses'] = [
			'title'    => esc_html__( 'Addresses', 'bricks' ),
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		$this->control_groups['markers'] = [
			'title'    => esc_html__( 'Markers', 'bricks' ),
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		$this->control_groups['map'] = [
			'title'    => esc_html__( 'Map', 'bricks' ),
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];
	}

	public function set_controls() {
		$this->controls['infoNoApiKey'] = [
			'content'  => sprintf(
				// translators: %s: Link to settings page
				esc_html__( 'Enter your Google Maps API key under %s to access all options.', 'bricks' ),
				'<a href="' . Helpers::settings_url( '#tab-api-keys' ) . '" target="_blank">Bricks > ' . esc_html__( 'Settings', 'bricks' ) . ' > API keys</a>'
			),
			'type'     => 'info',
			'required' => [ 'apiKeyGoogleMaps', '=', '', 'globalSettings' ],
		];

		// No API key: Single address
		$this->controls['address'] = [
			'label'       => esc_html__( 'Address', 'bricks' ),
			'desc'        => esc_html__( 'To ensure showing a marker please provide the latitude and longitude, separated by comma.', 'bricks' ),
			'type'        => 'text',
			'placeholder' => 'Berlin, Germany',
			'required'    => [ 'apiKeyGoogleMaps', '=', '', 'globalSettings' ],
		];

		// ADDRESSES

		$this->controls['addresses'] = [
			'group'         => 'addresses',
			'label'         => esc_html__( 'Addresses', 'bricks' ),
			'placeholder'   => esc_html__( 'Addresses', 'bricks' ),
			'checkLoop'     => true,
			'description'   => esc_html__( 'Please enter the latitude/longitude when using multiple markers.', 'bricks' ),
			'type'          => 'repeater',
			'titleProperty' => 'address',
			'fields'        => [
				'latitude'          => [
					'label'       => esc_html__( 'Latitude', 'bricks' ),
					'type'        => 'text',
					'trigger'     => [ 'blur', 'enter' ],
					'placeholder' => '52.5164154966524',
				],

				'longitude'         => [
					'label'       => esc_html__( 'Longitude', 'bricks' ),
					'type'        => 'text',
					'trigger'     => [ 'blur', 'enter' ],
					'placeholder' => '13.377643715349544',
				],

				'address'           => [
					'label'       => esc_html__( 'Address', 'bricks' ),
					'type'        => 'text',
					'trigger'     => [ 'blur', 'enter' ],
					'placeholder' => esc_html__( 'Berlin, Germany', 'bricks' ),
					'description' => esc_html__( 'Alternative to Latitude/Longitude fields', 'bricks' )
				],

				'infoTemplateInUse' => [
					'type'     => 'info',
					'content'  => esc_html__( 'Info Box template enabled.', 'bricks' ),
					'required' => [ 'infoBoxTemplateId', '!=', '' ],
				],

				// Infobox: Toggle on marker click
				'infoboxSeparator'  => [
					'label'       => esc_html__( 'Infobox', 'bricks' ),
					'type'        => 'separator',
					'description' => esc_html__( 'Infobox appears on map marker click.', 'bricks' ),
					'required'    => [ 'infoBoxTemplateId', '=', '' ],
				],

				'infoTitle'         => [
					'label'    => esc_html__( 'Title', 'bricks' ),
					'type'     => 'text',
					'trigger'  => [ 'blur', 'enter' ],
					'required' => [ 'infoBoxTemplateId', '=', '' ],
				],

				'infoSubtitle'      => [
					'label'    => esc_html__( 'Subtitle', 'bricks' ),
					'type'     => 'text',
					'trigger'  => [ 'blur', 'enter' ],
					'required' => [ 'infoBoxTemplateId', '=', '' ],
				],

				'infoOpeningHours'  => [
					'label'    => esc_html__( 'Content', 'bricks' ),
					'type'     => 'textarea',
					'trigger'  => [ 'blur', 'enter' ],
					'required' => [ 'infoBoxTemplateId', '=', '' ],
				],

				'infoImages'        => [
					'label'    => esc_html__( 'Images', 'bricks' ),
					'type'     => 'image-gallery',
					'unsplash' => false,
					'required' => [ 'infoBoxTemplateId', '=', '' ],
				],

				'infoWidth'         => [
					'label'       => esc_html__( 'Width', 'bricks' ),
					'type'        => 'number',
					'inline'      => true,
					'placeholder' => '300',
					'required'    => [ 'infoBoxTemplateId', '=', '' ],
				],
			],
			'default'       => [
				[
					'latitude'  => '52.5164154966524',
					'longitude' => '13.377643715349544'
				],
			],
			'required'      => [
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
				[ 'syncQuery', '=', '' ],
			],
		];

		$repeater_marker_controls = $this->get_map_marker_controls(
			'',
			[
				'markerAriaLabel'            => [
					'trigger' => [ 'blur', 'enter' ],
				],
				'markerTextSeparator'        => [
					'required' => [ 'markerType', '=', 'text' ]
				],
				'markerTextActiveSeparator'  => [
					'required' => [ 'markerType', '=', 'text' ]
				],
				'markerText'                 => [
					'required' => [ 'markerType', '=', 'text' ],
					'trigger'  => [ 'blur', 'enter' ],
				],
				'markerTextActive'           => [
					'required' => [ 'markerType', '=', 'text' ],
					'trigger'  => [ 'blur', 'enter' ],
				],
				'markerImageSeparator'       => [
					'required' => [ 'markerType', '!=', 'text' ]
				],
				'markerImageActiveSeparator' => [
					'required' => [ 'markerType', '!=', 'text' ]
				],
				'marker'                     => [
					'required' => [ 'markerType', '!=', 'text' ],
				],
				'markerActive'               => [
					'required' => [ 'markerType', '!=', 'text' ],
				],
			]
		);

		// Add marker controls to fields (repeater)
		$this->controls['addresses']['fields'] = array_merge( $this->controls['addresses']['fields'], $repeater_marker_controls );

		/**
		 * Query loop
		 *
		 * @since 2.0
		 */

		$this->controls['queryLoopSep'] = [
			'group'    => 'addresses',
			'type'     => 'separator',
			'label'    => esc_html__( 'Query loop', 'bricks' ),
			'required' => [
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
				[ 'syncQuery', '=', '' ],
			],
		];

		$query_controls = $this->get_loop_builder_controls( 'addresses' );

		// Add required for $query_controls
		$query_controls['hasLoop']['required'] = [
			[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			[ 'syncQuery', '=', '' ],
		];

		$query_controls['query']['required'] = [
			[ 'hasLoop', '!=', '' ],
			[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			[ 'syncQuery', '=', '' ],
		];

		$this->controls['queryLoopInfo'] = [
			'group'    => 'addresses',
			'type'     => 'info',
			'content'  => esc_html__( 'Populate the query loop address above through dynamic data tags.', 'bricks' ),
			'required' => [
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
				[ 'hasLoop', '=', true ],
				[ 'syncQuery', '=', '' ],
			],
		];

		// Insert query controls
		$this->controls = array_replace_recursive( $this->controls, $query_controls );

		$this->controls['infoBoxTemplateId'] = [
			'group'       => 'addresses',
			'label'       => esc_html__( 'Info Box', 'bricks' ) . ': ' . esc_html__( 'Template', 'bricks' ) . ' (' . esc_html__( 'Popup', 'bricks' ) . ')',
			'type'        => 'select',
			'searchable'  => true,
			'options'     => bricks_is_builder() ? Templates::get_templates_list( 'infobox' ) : [],
			'placeholder' => esc_html__( 'Select template', 'bricks' ) . ' (' . esc_html__( 'Popup', 'bricks' ) . ')',
			'required'    => [
				[ 'hasLoop', '!=', '' ],
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
				[ 'syncQuery', '=', '' ],
			],
		];

		$this->controls['infoBoxTemplateIdInfo'] = [
			'group'    => 'addresses',
			'type'     => 'info',
			'content'  => esc_html__( 'Required', 'bricks' ) . ': ' . esc_html__( 'When editing your popup template, you have to enable the "Info Box" setting under "Template Settings > Popup".', 'bricks' ),
			'required' => [
				[ 'hasLoop', '!=', '' ],
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
				[ 'syncQuery', '=', '' ],
				[ 'infoBoxTemplateId', '!=', '' ],
			],
		];

		/**
		 * Sync with query
		 *
		 * @since 2.0
		 */

		$this->controls['syncQuerySep'] = [
			'group'       => 'addresses',
			'type'        => 'separator',
			'label'       => esc_html__( 'Sync with query', 'bricks' ),
			'description' => esc_html__( 'Dynamically retrieve map addresses from a connected query on the same page, allowing real-time updates with query-based content, including query filter support.', 'bricks' ),
			'required'    => [
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
				[ 'hasLoop', '=', '' ],
			],
		];

		$this->controls['syncQuery'] = [
			'group'            => 'addresses',
			'type'             => 'query-list',
			'excludeMainQuery' => true,
			'required'         => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'placeholder'      => esc_html__( 'Select query loop', 'bricks' ),
			'required'         => [
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
				[ 'hasLoop', '=', '' ],
			],
		];

		$this->controls['syncAddressInfo'] = [
			'group'    => 'addresses',
			'type'     => 'info',
			'content'  => esc_html__( 'Required', 'bricks' ) . ': ' . esc_html__( 'Place and configure a "Map Connector" element inside the query loop to enable dynamic address retrieval. On the frontend, actual location markers will be displayed based on the retrieved addresses. In the builder, a placeholder marker will be shown using your Map Center settings for styling purposes.', 'bricks' ) . ' ' . Helpers::article_link( 'map-element#sync-query', esc_html__( 'Learn more', 'bricks' ) ),
			'required' => [
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
				[ 'syncQuery', '!=', '' ],
			],
		];

		$this->controls['mapNoResultsText'] = [
			'group'       => 'addresses',
			'label'       => esc_html__( 'Text', 'bricks' ) . ': ' . esc_html__( 'No results', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'No locations found', 'bricks' ),
			'required'    => [
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
				[ 'syncQuery', '!=', '' ],
			],
		];

		// MARKERS

		// Cluster (@since 2.0)
		$this->controls['markerCluster'] = [
			'group'       => 'markers',
			'label'       => esc_html__( 'Cluster', 'bricks' ) . ': ' . esc_html__( 'Markers', 'bricks' ),
			'type'        => 'checkbox',
			'required'    => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'description' => esc_html__( 'Enable to automatically group markers that are close to each other into a cluster.', 'bricks' ),
		];

		$this->controls['markerClusterBgColor'] = [
			'group'    => 'markers',
			'label'    => esc_html__( 'Cluster', 'bricks' ) . ': ' . esc_html__( 'Background color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'fill',
					'selector' => '.brx-map-cluster',
				]
			],
			'required' => [ 'markerCluster', '=', true ],
		];

		$this->controls['markerClusterTextColor'] = [
			'group'    => 'markers',
			'label'    => esc_html__( 'Cluster', 'bricks' ) . ': ' . esc_html__( 'Text color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'fill',
					'selector' => '.brx-map-cluster text',
				]
			],
			'required' => [ 'markerCluster', '=', true ],
		];

		// MARKER
		$this->controls['markerSeparator'] = [
			'group'       => 'markers',
			'label'       => esc_html__( 'Marker', 'bricks' ),
			'type'        => 'separator',
			'required'    => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'description' => esc_html__( 'Markers set on individual addresses or map connectors precede these settings.', 'bricks' ),
		];

		$this->controls['markerType'] = [
			'group'       => 'markers',
			'label'       => esc_html__( 'Type', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'image' => esc_html__( 'Image', 'bricks' ),
				'text'  => esc_html__( 'Text', 'bricks' ),
			],
			'inline'      => true,
			'placeholder' => esc_html__( 'Image', 'bricks' ),
			'required'    => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		// Add main marker controls (@since 2.0)
		$this->controls = array_replace_recursive( $this->controls, $this->generate_main_map_marker_controls() );

		// SETTINGS

		// Google Map ID via Cloud Console (@since 2.0)
		$this->controls['googleMapId'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Map ID', 'bricks' ) . ' (' . esc_html__( 'Optional', 'bricks' ) . ')',
			'type'        => 'text',
			'required'    => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'description' => sprintf(
				// translators: %s: link to Google Cloud Console
				esc_html__( 'Enter your Google Maps ID, which you can find in the %s, to enable the latest Google Maps features.', 'bricks' ),
				'<a href="https://developers.google.com/maps/documentation/javascript/map-ids/get-map-id#create_map_ids" target="_blank">Google Cloud Console</a>',
			),
		];

		// Map ID information: Cannot use style once present (@since 2.0)
		$this->controls['mapIdInfo'] = [
			'group'    => 'map',
			'content'  => sprintf(
				// translators: %s: link to Google Cloud Console
				esc_html__( 'When a Map ID is present, map styles are controlled via the %s.', 'bricks' ),
				'<a href="https://developers.google.com/maps/documentation/javascript/styling#cloud_tooling" target="_blank">Google Cloud Console</a>'
			),
			'type'     => 'info',
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		// Map Center (@since 2.0)
		$this->controls['mapCenterSeparator'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Map center', 'bricks' ),
			'type'        => 'separator',
			'required'    => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'description' => esc_html__( 'Default center of the map.', 'bricks' ),
		];

		$this->controls['mapCenterLat'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Latitude', 'bricks' ) . ' (' . esc_html__( 'Center', 'bricks' ) . ')',
			'type'        => 'text',
			'placeholder' => '52.5164154966524',
			'required'    => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		$this->controls['mapCenterLng'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Longitude', 'bricks' ) . ' (' . esc_html__( 'Center', 'bricks' ) . ')',
			'type'        => 'text',
			'placeholder' => '13.377643715349544',
			'required'    => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		$this->controls['mapCenterAddress'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Address', 'bricks' ) . ' (' . esc_html__( 'Center', 'bricks' ) . ')',
			'type'        => 'text',
			'placeholder' => 'Berlin, Germany',
			'required'    => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		$this->controls['mapSettingsSeparator'] = [
			'group' => 'map',
			'label' => esc_html__( 'General settings', 'bricks' ),
			'type'  => 'separator',
		];

		$this->controls['height'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Height', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'height',
				],
			],
			'placeholder' => '300px',
		];

		$this->controls['zoom'] = [
			'group'          => 'map',
			'label'          => esc_html__( 'Zoom level', 'bricks' ),
			'type'           => 'number',
			'step'           => 1,
			'min'            => 0,
			'max'            => 20,
			'placeholder'    => 12,
			'hasDynamicData' => true,
		];

		$this->controls['type'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Map type', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'roadmap'   => esc_html__( 'Roadmap', 'bricks' ),
				'satellite' => esc_html__( 'Satellite', 'bricks' ),
				'hybrid'    => esc_html__( 'Hybrid', 'bricks' ),
				'terrain'   => esc_html__( 'Terrain', 'bricks' ),
			],
			'placeholder' => esc_html__( 'Roadmap', 'bricks' ),
		];

		// STEP: No API key controls
		if ( empty( Database::$global_settings['apiKeyGoogleMaps'] ) ) {
			unset( $this->controls['height']['group'] );
			unset( $this->controls['zoom']['group'] );
			unset( $this->controls['type']['group'] );

			// Loading attribute for no-key map (@since 2.0.2)
			$this->controls['loading'] = [
				'label'       => esc_html__( 'Loading', 'bricks' ),
				'type'        => 'select',
				'inline'      => true,
				'options'     => [
					'eager' => 'eager',
					'lazy'  => 'lazy',
				],
				'placeholder' => 'lazy',
			];
		}

		$map_styles                   = bricks_is_builder() ? Setup::get_map_styles() : [];
		$map_styles_options['custom'] = esc_html__( 'Custom', 'bricks' );

		foreach ( $map_styles as $key => $value ) {
			$map_styles_options[ $key ] = $value['label'];
		}

		// Requires map type: Roadmap
		$this->controls['style'] = [
			'group'    => 'map',
			'label'    => esc_html__( 'Map style', 'bricks' ),
			'type'     => 'select',
			'options'  => $map_styles_options,
			'required' => [
				[ 'googleMapId', '=', '' ],
				[ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			],
		];

		$this->controls['customStyle'] = [
			'group'        => 'map',
			'label'        => esc_html__( 'Custom map style', 'bricks' ),
			'type'         => 'code',
			'mode'         => 'json', // Must be JSON for proper escaping (@since 1.11)
			'hasVariables' => false,
			// translators: %s: Link to snazzymaps.com
			'description'  => sprintf( esc_html__( 'Copy+paste code from one of the maps over at %s', 'bricks' ), '<a target="_blank" href="https://snazzymaps.com/explore">snazzymaps.com/explore</a>' ),
			'required'     => [
				[ 'googleMapId', '=', '' ],
				[ 'style', '=', 'custom' ],
			],
		];

		// Needed to parse custom map style (@since 1.11)
		$this->controls['customStyleApply'] = [
			'group'    => 'map',
			'type'     => 'apply',
			'reload'   => true,
			'required' => [
				[ 'googleMapId', '=', '' ],
				[ 'style', '=', 'custom' ],
				[ 'customStyle', '!=', '' ],
			],
			'label'    => esc_html__( 'Apply', 'bricks' ) . ': ' . esc_html__( 'Custom map style', 'bricks' ),
		];

		// @since 2.0
		$this->controls['fitMapOnMarkersChange'] = [
			'group'       => 'map',
			'label'       => esc_html__( 'Fit map on markers change', 'bricks' ),
			'type'        => 'checkbox',
			'required'    => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'description' => esc_html__( 'Automatically adjust the map view to fit all markers when markers are added or removed.', 'bricks' ),
		];

		$this->controls['scrollwheel'] = [
			'group'    => 'map',
			'label'    => esc_html__( 'Scroll', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'default'  => true,
		];

		$this->controls['draggable'] = [
			'group'    => 'map',
			'label'    => esc_html__( 'Draggable', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'default'  => true,
		];

		$this->controls['fullscreenControl'] = [
			'group'    => 'map',
			'label'    => esc_html__( 'Fullscreen Control', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		$this->controls['mapTypeControl'] = [
			'group'    => 'map',
			'label'    => esc_html__( 'Map Type Control', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		$this->controls['streetViewControl'] = [
			'group'    => 'map',
			'label'    => esc_html__( 'Street View Control', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'default'  => true,
		];

		$this->controls['disableDefaultUI'] = [
			'group'    => 'map',
			'label'    => esc_html__( 'Disable Default UI', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		// Clicking on places do nothing (@since 2.0)
		$this->controls['disableClickPOI'] = [
			'group'    => 'map',
			'label'    => esc_html__( 'Disable clickable POI', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
		];

		$this->controls['zoomControl'] = [
			'group'    => 'map',
			'label'    => esc_html__( 'Zoom Control', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ],
			'default'  => true,
		];

		$this->controls['minZoom'] = [
			'group'          => 'map',
			'label'          => esc_html__( 'Zoom level', 'bricks' ) . ' (' . esc_html__( 'Min', 'bricks' ) . ')',
			'type'           => 'number',
			'step'           => 1,
			'min'            => 0,
			'required'       => [ 'zoomControl', '!=', '' ],
			'hasDynamicData' => true,
		];

		$this->controls['maxZoom'] = [
			'group'          => 'map',
			'label'          => esc_html__( 'Zoom level', 'bricks' ) . ' (' . esc_html__( 'Max', 'bricks' ) . ')',
			'type'           => 'number',
			'step'           => 1,
			'min'            => 0,
			'required'       => [ 'zoomControl', '!=', '' ],
			'hasDynamicData' => true,
		];
	}

	public function render() {
		$settings    = $this->settings;
		$map_type    = $settings['type'] ?? 'roadmap';
		$marker_type = $settings['markerType'] ?? 'image';
		$zoom        = isset( $settings['zoom'] ) ? intval( $this->render_dynamic_data( $settings['zoom'] ) ) : 12;

		/**
		 * STEP: Use Google Maps Embed API
		 *
		 * No API key required, but limited functionality: Zoom level, map type //, fullscreen control, street view control, map type control, zoom control, disable default UI
		 *
		 * @since 1.10.2
		 */
		if ( empty( Database::$global_settings['apiKeyGoogleMaps'] ) ) {
			$address = isset( $settings['address'] ) ? $this->render_dynamic_data( $settings['address'] ) : 'Berlin, Germany';

			if ( $map_type === 'satellite' ) {
				$map_type = 'SATELLITE';
			} elseif ( $map_type === 'hybrid' ) {
				$map_type = 'HYBRID';
			} elseif ( $map_type === 'terrain' ) {
				$map_type = 'TERRAIN';
			} else {
				$map_type = 'ROADMAP';
			}

			$this->set_attribute( 'iframe', 'width', '100%' );
			$this->set_attribute( 'iframe', 'height', '100%' );
			$this->set_attribute( 'iframe', 'loading', $settings['loading'] ?? 'lazy' ); // (@since 2.0.2)
			$this->set_attribute( 'iframe', 'src', 'https://maps.google.com/maps?q=' . urlencode( $address ) . '&t=' . $map_type . '&z=' . $zoom . '&output=embed&iwloc=near' );
			$this->set_attribute( 'iframe', 'allowfullscreen' );
			$this->set_attribute( 'iframe', 'title', esc_attr( $address ) ); // @since 1.12 (a11y)

			$this->set_attribute( '_root', 'class', 'no-key' );

			// Div needed in builder for DnD and click to edit
			echo "<div {$this->render_attributes( '_root' )}>";

			echo "<iframe {$this->render_attributes( 'iframe' )}></iframe>" . PHP_EOL;

			echo '</div>';

			return;
		}

		// Static: Addresses, filter all the fields to render dynamic data before render
		$addresses = ! empty( $settings['addresses'] ) ? $settings['addresses'] : [ [ 'address' => 'Berlin, Germany' ] ];
		$map_mode  = 'static';

		// Sync query mode
		$sync_query = $settings['syncQuery'] ?? false;

		if ( $sync_query ) {
			$map_mode = 'sync';
			// Ensure hasLoop is false
			$settings['hasLoop'] = false;

			// Ensure $addresses are empty (retrieving addresses from connected query)
			$addresses = [];
		}

		// Query mode
		$has_loop = isset( $settings['hasLoop'] );

		if ( $has_loop && ! $sync_query ) {
			$map_mode = 'query';
			$query    = new Query(
				[
					'id'       => $this->id,
					'settings' => $settings,
				]
			);

			$addresses = $addresses[0];

			// InfoBox template
			if ( ! empty( $settings['infoBoxTemplateId'] ) ) {
				$template_id = $this->get_info_box_template_id();

				if ( $template_id ) {
					// Unset irrelevant fields
					unset( $addresses['infoTitle'] );
					unset( $addresses['infoSubtitle'] );
					unset( $addresses['infoOpeningHours'] );
					unset( $addresses['infoImages'] );
					unset( $addresses['infoWidth'] );

					$addresses['infoBoxTemplateId'] = $template_id;
				}
			}

			// Populate the addresses array with the loop data
			$addresses_array = $query->render( [ $this, 'repeater_item_from_query' ], [ $addresses ], true );

			// Expect each array item is an array, if not , remove the item <template data-brx-loop-start>...</template>
			$addresses = array_filter( $addresses_array, 'is_array' );

			// Reset the array keys
			$addresses = array_values( $addresses );

			// Destroy query to explicitly remove it from the global store
			$query->destroy();
			unset( $query );
		}

		// InfoImages Gallery may use a custom field (handle it before), except query mode as it was already handled in repeater_item_from_query (@since 2.0)
		if ( $map_mode !== 'query' ) {
			foreach ( $addresses as $index => $address ) {
				if ( empty( $address['infoImages'] ) ) {
					continue;
				}

				$addresses[ $index ]['infoImages']['images'] = self::parse_info_images( $address['infoImages'], $this );

				if ( isset( $addresses[ $index ]['infoImages']['useDynamicData'] ) ) {
					unset( $addresses[ $index ]['infoImages']['useDynamicData'] );
				}
			}
		}

		// Handle remaining text fields to replace dynamic data
		add_filter( 'bricks/acf/google_map/text_output', 'wp_strip_all_tags' );

		$addresses = map_deep( $addresses, [ $this, 'render_dynamic_data' ] );

		remove_filter( 'bricks/acf/google_map/text_output', 'wp_strip_all_tags' );

		// Set map center (@since 2.0)
		if ( ! empty( $settings['mapCenterLat'] ) && ! empty( $settings['mapCenterLng'] ) ) {
			// Lat and Lng
			$map_center = [
				'lat' => $this->render_dynamic_data( $settings['mapCenterLat'] ),
				'lng' => $this->render_dynamic_data( $settings['mapCenterLng'] ),
			];
		} elseif ( ! empty( $settings['mapCenterAddress'] ) ) {
			// Address only
			$map_center = [
				'address' => $this->render_dynamic_data( $settings['mapCenterAddress'] ),
			];
		} else {
			// Default: Berlin, Germany 52.5164154966524, 13.377643715349544
			$map_center = [
				'lat' => '52.5164154966524',
				'lng' => '13.377643715349544',
			];
		}

		$map_options = [
			'addresses'             => $addresses,
			'center'                => $map_center,
			'zoom'                  => $zoom,
			'scrollwheel'           => isset( $settings['scrollwheel'] ),
			'draggable'             => isset( $settings['draggable'] ),
			'fullscreenControl'     => isset( $settings['fullscreenControl'] ),
			'mapTypeControl'        => isset( $settings['mapTypeControl'] ),
			'streetViewControl'     => isset( $settings['streetViewControl'] ),
			'zoomControl'           => isset( $settings['zoomControl'] ),
			'disableDefaultUI'      => isset( $settings['disableDefaultUI'] ),
			'type'                  => $map_type,
			'mapMode'               => $map_mode, // 'static', 'query', 'sync' (@since 2.0)
			'clickableIcons'        => ! isset( $settings['disableClickPOI'] ), // (@since 2.0)
			'markerCluster'         => isset( $settings['markerCluster'] ), // (@since 2.0)
			'syncQuery'             => $sync_query,
			'noLocationsText'       => isset( $settings['mapNoResultsText'] ) ? $this->render_dynamic_data( $settings['mapNoResultsText'] ) : esc_html__( 'No locations found', 'bricks' ),
			'fitMapOnMarkersChange' => isset( $settings['fitMapOnMarkersChange'] ) ? (bool) $settings['fitMapOnMarkersChange'] : false,
		];

		// Min zoom
		if ( isset( $settings['minZoom'] ) ) {
			$map_options['minZoom'] = intval( $this->render_dynamic_data( $settings['minZoom'] ) );
		}

		// Max zoom
		if ( isset( $settings['maxZoom'] ) ) {
			$map_options['maxZoom'] = intval( $this->render_dynamic_data( $settings['maxZoom'] ) );
		}

		// Marker type
		$map_options['markerType'] = $marker_type;

		// Marker text
		$map_options['markerText'] = isset( $settings['markerText'] ) ? $this->render_dynamic_data( $settings['markerText'] ) : esc_html__( 'Marker', 'bricks' );

		$map_options['markerTextActive'] = isset( $settings['markerTextActive'] ) ? $this->render_dynamic_data( $settings['markerTextActive'] ) : esc_html__( 'Marker', 'bricks' );

		// Custom marker
		if ( isset( $settings['marker']['url'] ) ) {
			$map_options['marker'] = $settings['marker']['url'];
		}

		if ( isset( $settings['markerHeight'] ) ) {
			$map_options['markerHeight'] = $settings['markerHeight'];
		}

		if ( isset( $settings['markerWidth'] ) ) {
			$map_options['markerWidth'] = $settings['markerWidth'];
		}

		// Custom active marker
		if ( isset( $settings['markerActive']['url'] ) ) {
			$map_options['markerActive'] = $settings['markerActive']['url'];
		}

		if ( isset( $settings['markerActiveHeight'] ) ) {
			$map_options['markerActiveHeight'] = $settings['markerActiveHeight'];
		}

		if ( isset( $settings['markerActiveWidth'] ) ) {
			$map_options['markerActiveWidth'] = $settings['markerActiveWidth'];
		}

		// Support mapId and AdvancedMarker (@since 2.0)
		if ( isset( $settings['googleMapId'] ) ) {
			$map_options['googleMapId'] = $settings['googleMapId'];
			// Do not use map style if googleMapId is set, user should configure it in Google Cloud Console
			unset( $settings['style'] );
			unset( $settings['customStyle'] );
		}

		// Add pre-defined or custom map style
		$map_style = $settings['style'] ?? '';

		/**
		 * Set map style
		 *
		 * @since 1.9.3: Pass every map style as JSON string
		 */
		if ( $map_style ) {
			// Custom map style
			if ( $map_style === 'custom' ) {
				if ( ! empty( $settings['customStyle'] ) ) {
					$map_options['styles'] = wp_json_encode( $settings['customStyle'] );
				}
			}

			// Pre-defined map style
			else {
				$map_style             = Setup::get_map_styles( $map_style );
				$map_options['styles'] = $map_style;
			}
		}

		$this->set_attribute( '_root', 'data-bricks-map-options', wp_json_encode( $map_options ) );

		// No more inner .map as DnD only works in structure panel anyway (@since 1.5.4)
		echo "<div {$this->render_attributes( '_root' )}></div>";
	}

	/**
	 * Process repeater item from query data
	 *
	 * @param array $data
	 * @return array
	 * @since 2.0
	 */
	public function repeater_item_from_query( $data ) {
		// Recursive function to process nested arrays
		$process_data = function( $value ) use ( &$process_data ) {
			if ( is_array( $value ) ) {
				// Recursively process nested arrays
				return array_map( $process_data, $value );
			}

			// Render dynamic data for non-array values
			return $this->render_dynamic_data( $value );
		};

		$processed_data = [];

		foreach ( $data as $key => $value ) {
			// Handle address ID
			if ( $key === 'id' ) {
				// Generate a unique ID for each item
				$processed_data['id'] = Helpers::generate_random_id( false );
				continue;
			}

			// Handle infoImages
			if ( $key === 'infoImages' ) {
				$images                           = self::parse_info_images( $value, $this );
				$processed_data[ $key ]['images'] = $images;

				if ( isset( $processed_data[ $key ]['useDynamicData'] ) ) {
					unset( $processed_data[ $key ]['useDynamicData'] );
				}
				continue;
			}

			// Handle infoBoxTemplateId
			if ( $key === 'infoBoxTemplateId' ) {
				// Mimic a template element
				$template_el = [
					'id'       => Helpers::generate_random_id( false ),
					'name'     => 'template',
					'settings' => [
						'template' => $value,
						'noRoot'   => true,
					],
				];

				// Ensure inline CSS is generated if CSS loading is set to 'file' (#86c4rgke5; @since 2.1)
				if ( Database::get_setting( 'cssLoading' ) === 'file' ) {
					$template_el['settings']['_onHook'] = true; // Custom setting to force inline CSS generation
				}

				// Render template
				Frontend::render_element( $template_el );
				$processed_data[ $key ] = $value;

				// Populate required data
				$processed_data['popupTemplatId']  = $value;
				$processed_data['infoBoxSelector'] = Query::get_looping_unique_identifier( 'interaction' );

				continue;
			}

			// Handle marker and markerActive images
			if ( $key === 'marker' || $key === 'markerActive' ) {
				$images = Helpers::get_normalized_image_settings( $this, [ 'items' => $value ] );

				if ( ! empty( $images['items']['images'] ) && isset( $images['items']['images'][0]['id'] ) ) {
					$processed_data[ $key ] = $images['items']['images'][0];
				}
				continue;
			}

			// Process other fields
			$processed_data[ $key ] = $process_data( $value );
		}

		return $processed_data;
	}

	/**
	 * Refactor function to parse infoImages
	 *
	 * @param array   $settings
	 * @param Element $element
	 * @since 2.0
	 */
	public static function parse_info_images( $settings, $element ) {
		// Get infoImages data
		$info_images = Helpers::get_normalized_image_settings( $element, [ 'items' => $settings ] );

		if ( empty( $info_images['items']['images'] ) ) {
			return $settings; // Return original settings if no images found
		}

		$images = [];

		foreach ( $info_images['items']['images'] as $info_image ) {
			$image_id = $info_image['id'] ?? '';

			if ( ! $image_id ) {
				continue;
			}

			$image_size = $info_images['items']['size'] ?? 'thumbnail';
			$image_src  = wp_get_attachment_image_src( $image_id, $image_size );

			$images[] = [
				'id'        => $image_id,
				'src'       => $image_src[0],
				'width'     => $image_src[1],
				'height'    => $image_src[2],
				'thumbnail' => wp_get_attachment_image_url( $image_id, $image_size ),
			];
		}

		return $images;
	}

	/**
	 * Render Info Box Popup template
	 *
	 * @since 2.0
	 */
	public function get_info_box_template_id() {
		$settings    = $this->settings;
		$template_id = isset( $settings['infoBoxTemplateId'] ) ? intval( $settings['infoBoxTemplateId'] ) : false;

		if ( ! $template_id || get_post_status( $template_id ) !== 'publish' ) {
			return false;
		}

		// Avoid infinite loop
		if ( $template_id == get_the_ID() || ( Helpers::is_bricks_template( $this->post_id ) && $template_id == $this->post_id ) ) {
			return false;
		}

		$template_type     = Templates::get_template_type( $template_id );
		$template_settings = Helpers::get_template_settings( $template_id );

		// Ensure this template is an Info Box and is a popup
		if ( ! isset( $template_settings['popupIsInfoBox'] ) || $template_type !== 'popup' ) {
			return false;
		}

		return $template_id;
	}

	/**
	 * Generate main map marker controls
	 * Refer to get_map_marker_controls() in base.php
	 *
	 * @return array
	 * @since 2.0
	 */
	private function generate_main_map_marker_controls() {
		$group            = 'markers';
		$common_condition = [ 'apiKeyGoogleMaps', '!=', '', 'globalSettings' ];
		$is_main          = true;

		$text_marker_fields = [
			'markerTextSeparator',
			'markerText',
			'markerTextMaxWidth',
			'markerTextTypography',
			'markerTextBackgroundColor',
			'markerTextBorder',
			'markerTextBoxShadow',
			'markerTextPadding',
			'markerTextActiveSeparator',
			'markerTextActive',
			'markerTextActiveTypography',
			'markerTextActiveBackgroundColor',
			'markerTextActiveBorder',
			'markerTextActiveBoxShadow',
			'markerTextActivePadding',
		];

		$image_marker_fields = [
			'markerImageSeparator',
			'marker',
			'markerHeight',
			'markerWidth',
			'markerBorder',
			'markerBoxShadow',
			'markerActive',
			'markerActiveHeight',
			'markerActiveWidth',
			'markerActiveBorder',
			'markerActiveBoxShadow',
		];

		$controls = [
			'markerAriaLabel' => [
				'required' => $common_condition,
			],
		];

		// Set conditions for text marker fields
		foreach ( $text_marker_fields as $field ) {
			$controls[ $field ] = [
				'required' => [
					$common_condition,
					[ 'markerType', '=', 'text' ],
				],
			];
		}

		// Set conditions for image marker fields
		foreach ( $image_marker_fields as $field ) {
			$controls[ $field ] = [
				'required' => [
					$common_condition,
					[ 'markerType', '!=', 'text' ],
				],
			];
		}

		return $this->get_map_marker_controls(
			$group,
			$controls,
			$is_main
		);
	}
}
