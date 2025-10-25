<?php
namespace Bricks\Integrations\Query_Filters;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Fields {
	private static $instance = null;
	public static $providers = [];

	public function __construct() {
		// Must be called on init to ensure all providers are loaded (@since 1.12.2)
		add_action( 'init', [ $this, 'register_providers' ] );
	}

	/**
	 * Singleton - Get the instance of this class
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new Fields();
		}

		return self::$instance;
	}

	/**
	 * Register the field providers
	 *
	 * @since 1.12.2
	 */
	public function register_providers() {
		// bricks/query_filters_cf_integration/register_providers Undocumented (@since 2.0.2)
		$providers = apply_filters(
			'bricks/query_filters_cf_integration/register_providers',
			[
				'acf',
				'metabox'
			]
		);

		foreach ( $providers as $provider ) {
			$provider_class = 'Bricks\Integrations\Query_Filters\Field_' . ucfirst( $provider );

			if ( class_exists( $provider_class ) ) {
				self::$providers[ $provider ] = new $provider_class();
			}
		}
	}

	/**
	 * Builder: Retrieve the active provider list
	 *
	 * @since 1.12.2
	 */
	public static function get_active_provider_list() {
		$active_providers = [];

		foreach ( self::$providers as $provider => $instance ) {
			if ( $instance::is_active() ) {
				$active_providers[ $provider ] = $instance->get_name();
			}
		}

		return $active_providers;
	}
}
