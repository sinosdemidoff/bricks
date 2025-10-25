<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Map_Connector extends Element {
	public $category = 'general';
	public $name     = 'map-connector';
	public $icon     = 'ti-pin-alt';

	public function get_label() {
		return esc_html__( 'Map Connector', 'bricks' );
	}

	public function set_control_groups() {
		$this->control_groups['markers'] = [
			'title' => esc_html__( 'Markers', 'bricks' ),
		];
	}

	public function set_controls() {
		$this->controls['elementInfo'] = [
			'type'    => 'info',
			'content' => esc_html__( 'This element works alongside a "Map" element when "Sync with query" has been set. An invisible <template> node is generated that stores location data. Allowing the map to retrieve and display markers dynamically.', 'bricks' ),
		];

		$this->controls['elementError'] = [
			'type'     => 'info',
			'error'    => true,
			'content'  => esc_html__( 'No latitude/longitude or address provided. Use dynamic data tags to retrieve the coordinates/address from your query loop results.', 'bricks' ),
			'required' => [
				[ 'latitude', '=', '' ],
				[ 'longitude', '=', '' ],
				[ 'address', '=', '' ],
			],
		];

		$this->controls['latitude'] = [
			'label'       => esc_html__( 'Latitude', 'bricks' ),
			'type'        => 'text',
			'placeholder' => '52.5164154966524',
		];

		$this->controls['longitude'] = [
			'label'       => esc_html__( 'Longitude', 'bricks' ),
			'type'        => 'text',
			'placeholder' => '13.3777594566345',
		];

		$this->controls['address'] = [
			'label'       => esc_html__( 'Address', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'Berlin, Germany', 'bricks' ),
			'description' => esc_html__( 'Alternative to Latitude/Longitude fields', 'bricks' ),
		];

		$this->controls['infoBoxTemplateId'] = [
			'label'       => esc_html__( 'Info Box', 'bricks' ) . ': ' . esc_html__( 'Template', 'bricks' ) . ' (' . esc_html__( 'Popup', 'bricks' ) . ')',
			'type'        => 'select',
			'searchable'  => true,
			'options'     => bricks_is_builder() ? Templates::get_templates_list( 'infobox' ) : [],
			'placeholder' => esc_html__( 'Select template', 'bricks' ) . ' (' . esc_html__( 'Popup', 'bricks' ) . ')',
		];

		$this->controls['infoBoxTemplateIdInfo'] = [
			'type'    => 'info',
			'content' => esc_html__( 'Required', 'bricks' ) . ': ' . esc_html__( 'When editing your popup template, you have to enable the "Info Box" setting under "Template Settings > Popup".', 'bricks' ),
		];

		$this->controls['markerInfo'] = [
			'group'   => 'markers',
			'type'    => 'info',
			'content' => esc_html__( 'Configure "Marker: Type" on the connected "Map" element to render text or image markers. The settings below will precede the settings on the "Map" element.', 'bricks' ),
		];

		// Add marker controls and group into 'markers' control group
		$map_marker_controls = $this->get_map_marker_controls( 'markers' );
		$this->controls      = array_merge( $this->controls, $map_marker_controls );
	}


	public function render() {

		$settings = $this->settings;

		// Set data-brx-latitude, data-brx-longitude, data-brx-address attributes
		$latitude  = isset( $settings['latitude'] ) ? $this->render_dynamic_data( $settings['latitude'] ) : '';
		$longitude = isset( $settings['longitude'] ) ? $this->render_dynamic_data( $settings['longitude'] ) : '';
		$address   = isset( $settings['address'] ) ? $this->render_dynamic_data( $settings['address'] ) : '';

		// Build marker data. No need default values here, as the map will handle missing data gracefully.
		$marker_data = [
			'markerText'       => isset( $settings['markerText'] ) ? $this->render_dynamic_data( $settings['markerText'] ) : '',
			'markerTextActive' => isset( $settings['markerTextActive'] ) ? $this->render_dynamic_data( $settings['markerTextActive'] ) : '',
			'marker'           => isset( $settings['marker'] ) ? $this->get_marker_image( $settings['marker'] ) : '',
			'markerActive'     => isset( $settings['markerActive'] ) ? $this->get_marker_image( $settings['markerActive'] ) : '',
			'markerAriaLabel'  => isset( $settings['markerAriaLabel'] ) ? $this->render_dynamic_data( $settings['markerAriaLabel'] ) : '',
		];

		$this->set_attribute( '_root', 'data-brx-latitude', $latitude );
		$this->set_attribute( '_root', 'data-brx-longitude', $longitude );
		$this->set_attribute( '_root', 'data-brx-address', $address );
		$this->set_attribute( '_root', 'data-brx-marker-data', wp_json_encode( $marker_data ) );
		$this->set_attribute( '_root', 'data-brx-infobox-selector', Query::get_looping_unique_identifier( 'interaction' ) );

		$this->maybe_render_info_box();

		// Use template node to store location data. Minimize the risk of conflicts with different CSS layouts
		echo "<template style='display:none' {$this->render_attributes( '_root' )}></template>";
	}

	// Render Info Box Popup template
	public function maybe_render_info_box() {
		$settings    = $this->settings;
		$template_id = isset( $settings['infoBoxTemplateId'] ) ? intval( $settings['infoBoxTemplateId'] ) : false;

		if ( ! $template_id || get_post_status( $template_id ) !== 'publish' ) {
			return;
		}

		// Avoid infinite loop
		if ( $template_id == get_the_ID() || ( Helpers::is_bricks_template( $this->post_id ) && $template_id == $this->post_id ) ) {
			return;
		}

		$template_type     = Templates::get_template_type( $template_id );
		$template_settings = Helpers::get_template_settings( $template_id );

		// Ensure this template is an Info Box and is a popup
		if ( ! isset( $template_settings['popupIsInfoBox'] ) || $template_type !== 'popup' ) {
			return;
		}

		// Mimic a template element
		$template_el = [
			'id'       => Helpers::generate_random_id( false ),
			'name'     => 'template',
			'settings' => [
				'template' => $template_id,
				'noRoot'   => true,
			],
		];

		// Render template
		Frontend::render_element( $template_el );

		$this->set_attribute( '_root', 'data-brx-infobox-template', $template_id );
	}

	// Generate marker image settings.
	public function get_marker_image( $image ) {
		$images = Helpers::get_normalized_image_settings( $this, [ 'items' => $image ] );

		if ( ! empty( $images['items']['images'] ) && isset( $images['items']['images'][0]['url'] ) ) {
			return $images['items']['images'][0];
		}

		return '';
	}
}
