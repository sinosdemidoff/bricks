<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Product_Stock extends Element {
	public $category = 'woocommerce_product';
	public $name     = 'product-stock';
	public $icon     = 'ti-package';

	public function get_label() {
		return esc_html__( 'Product stock', 'bricks' );
	}

	public function set_control_groups() {
		$this->control_groups['inStock'] = [
			'title' => esc_html__( 'In stock', 'bricks' ),
			'tab'   => 'content',
		];

		$this->control_groups['lowStock'] = [
			'title' => esc_html__( 'Low stock', 'bricks' ) . '/' . esc_html__( 'On backorder', 'bricks' ),
			'tab'   => 'content',
		];

		$this->control_groups['outOfStock'] = [
			'title' => esc_html__( 'Out of stock', 'bricks' ),
			'tab'   => 'content',
		];
	}

	public function set_controls() {
		// In Stock

		$this->controls['inStockText'] = [
			'tab'            => 'content',
			'group'          => 'inStock',
			'type'           => 'text',
			'hasDynamicData' => 'text',
			'placeholder'    => esc_html__( 'Custom text', 'bricks' ),
		];

		$this->controls['inStockTypography'] = [
			'tab'   => 'content',
			'group' => 'inStock',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.in-stock',
				],
			],
		];

		$this->controls['inStockBackgroundColor'] = [
			'tab'   => 'style',
			'group' => 'inStock',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.in-stock',
				]
			],
		];

		// Low Stock

		$this->controls['lowStockText'] = [
			'tab'            => 'content',
			'group'          => 'lowStock',
			'type'           => 'text',
			'hasDynamicData' => 'text',
			'placeholder'    => esc_html__( 'Custom text', 'bricks' ),
		];

		$this->controls['lowStockTypography'] = [
			'tab'   => 'content',
			'group' => 'lowStock',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.low-stock, .available-on-backorder',
				],
			],
		];

		$this->controls['lowStockBackgroundColor'] = [
			'tab'   => 'style',
			'group' => 'lowStock',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.low-stock, .available-on-backorder',
				]
			],
		];

		// Out of Stock

		$this->controls['outOfStockText'] = [
			'tab'            => 'content',
			'group'          => 'outOfStock',
			'type'           => 'text',
			'hasDynamicData' => 'text',
			'placeholder'    => esc_html__( 'Custom text', 'bricks' ),
		];

		$this->controls['outOfStockTypography'] = [
			'tab'   => 'content',
			'group' => 'outOfStock',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.out-of-stock',
				],
			],
		];

		$this->controls['outOfStockBackgroundColor'] = [
			'tab'   => 'style',
			'group' => 'outOfStock',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.out-of-stock',
				]
			],
		];
	}

	public function render() {
		global $product;

		$product = wc_get_product( $this->post_id );

		if ( empty( $product ) ) {
			return $this->render_element_placeholder(
				[
					'title'       => esc_html__( 'For better preview select content to show.', 'bricks' ),
					'description' => esc_html__( 'Go to: Settings > Template Settings > Populate Content', 'bricks' ),
				]
			);
		}

		add_filter( 'woocommerce_get_availability', [ $this, 'woocommerce_get_availability' ], 10, 2 );

		$stock_html = wc_get_stock_html( $product );

		if ( ! $stock_html ) {
			remove_filter( 'woocommerce_get_availability', [ $this, 'woocommerce_get_availability' ], 10, 2 );

			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'Stock management not enabled for this product.', 'bricks' ),
				]
			);
		}

		echo "<div {$this->render_attributes( '_root' )}>" . $stock_html . '</div>';

		remove_filter( 'woocommerce_get_availability', [ $this, 'woocommerce_get_availability' ], 10, 2 );
	}

	public function woocommerce_get_availability( $availability, $product ) {
		$settings        = $this->settings;
		$is_manage_stock = $this->is_product_or_variations_managing_stock( $product );
		// Get stock via helper function if we don't manage stock on product level (to enable low stock for example)
		$stock_quantity      = $is_manage_stock ? Woocommerce_Helpers::get_stock_amount( $product ) : $product->get_stock_quantity();
		$is_variable_product = $product->is_type( 'variable' );

		// Changed the way to get low stock amount
		$low_stock_amount = wc_get_low_stock_amount( $product );

		// If it's variable product, and we do not manage stock on main product, we need to set class and availability manually
		if ( $is_variable_product && $is_manage_stock ) {
			if ( $stock_quantity <= 0 ) {
				$availability['class'] = 'out-of-stock';
			} elseif ( $stock_quantity <= $low_stock_amount ) {
				$availability['class'] = 'low-stock';
			} else {
				$availability['class'] = 'in-stock';
			}
			// Set default availability text
			$availability['availability'] = Woocommerce_Helpers::format_stock_for_display( $product, $stock_quantity );

		} else {
			// Set availability class if stock is low and only if stock management is enabled, is_in_stock will be true if not managing stock
			if ( $product->is_in_stock() && $stock_quantity <= $low_stock_amount && $is_manage_stock ) {
				$availability['class'] = 'low-stock';
			}
		}

		// Set availability text based on user input
		switch ( $availability['class'] ) {
			case 'in-stock':
				$availability['availability'] = ! empty( $settings['inStockText'] ) && ( $is_manage_stock || $is_variable_product ) ? $settings['inStockText'] : $availability['availability'];
				break;

			case 'available-on-backorder':
			case 'low-stock':
				$availability['availability'] = ! empty( $settings['lowStockText'] ) ? $settings['lowStockText'] : $availability['availability'];
				break;

			case 'out-of-stock':
				$availability['availability'] = ! empty( $settings['outOfStockText'] ) ? $settings['outOfStockText'] : $availability['availability'];
				break;
		}

		return $availability;
	}

	/**
	 * Check if a WooCommerce product or all its variations manage stock
	 *
	 * @param WC_Product $product The product object.
	 * @return bool True if the product (and any one variations, if variable) manage stock, false otherwise.
	 *
	 * @since 1.12
	 */
	public function is_product_or_variations_managing_stock( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) ) {
			// For variable products, check each variation
			$variations = $product->get_children(); // Get all variation IDs
			foreach ( $variations as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation && $variation->managing_stock() ) {
					return true; // If any variation manages stock, return true
				}
			}

			return false;
		}

		// For simple and other product types.
		return $product->managing_stock();
	}
}
