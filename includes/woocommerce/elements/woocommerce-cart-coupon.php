<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Woocommerce_Cart_Coupon extends Element {
	public $category        = 'woocommerce';
	public $name            = 'woocommerce-cart-coupon';
	public $icon            = 'ti-ticket';
	public $panel_condition = [ 'templateType', '=', 'wc_cart' ];

	public function get_label() {
		return esc_html__( 'Cart coupon', 'bricks' );
	}

	public function set_controls() {
		// General
		$this->controls['generalSeparator'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'General', 'bricks' ),
		];

		// @since 2.0.2
		$this->controls['ajaxUpdate'] = [
			'type'  => 'checkbox',
			'label' => esc_html__( 'Update cart via AJAX', 'bricks' ),
		];

		$this->controls['direction'] = [
			'label'   => esc_html__( 'Direction', 'bricks' ),
			'tooltip' => [
				'content'  => 'flex-direction',
				'position' => 'top-left',
			],
			'type'    => 'direction',
			'css'     => [
				[
					'selector' => '.coupon',
					'property' => 'flex-direction',
				],
			],
			'inline'  => true,
		];

		$this->controls['inputSeperator'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Input', 'bricks' ),
		];

		$this->controls['inputPlaceholder'] = [
			'label'       => esc_html__( 'Placeholder', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'Coupon code', 'woocommerce' ),
			'inline'      => true,
		];

		$this->controls['inputWidth'] = [
			'label' => esc_html__( 'Width', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'width',
					'selector' => '.coupon input',
				],
			],
		];

		$this->controls['inputBackground'] = [
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.coupon input',
				],
			],
		];

		$this->controls['inputBorder'] = [
			'type'  => 'border',
			'label' => esc_html__( 'Border', 'bricks' ),
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.coupon input',
				],
			],
		];

		$this->controls['inputPlaceholderTypography'] = [
			'label' => esc_html__( 'Placeholder typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.coupon input::placeholder',
				],
			],
		];

		// Button

		$this->controls['buttonSeperator'] = [
			'label' => esc_html__( 'Button', 'bricks' ),
			'tab'   => 'content',
		];

		$this->controls['buttonText'] = [
			'label'       => esc_html__( 'Text', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'Apply coupon', 'woocommerce' ),
			'inline'      => true,
		];

		$this->controls['buttonWidth'] = [
			'label' => esc_html__( 'Width', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'width',
					'selector' => '.coupon button',
				],
			],
		];

		$this->controls['buttonMargin'] = [
			'label'       => esc_html__( 'Margin', 'bricks' ),
			'type'        => 'spacing',
			'css'         => [
				[
					'property' => 'margin',
					'selector' => '.coupon button',
				],
			],
			'placeholder' => [
				'top'    => 0,
				'right'  => 0,
				'bottom' => 0,
				'left'   => '15px',
			],
		];

		$this->controls['buttonBackground'] = [
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.coupon button',
				],
			],
		];

		$this->controls['buttonBorder'] = [
			'type'  => 'border',
			'label' => esc_html__( 'Border', 'bricks' ),
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.coupon button',
				],
			],
		];

		$this->controls['buttonTypography'] = [
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.coupon button',
				],
			],
		];
	}

	public function render() {
		$settings = $this->settings;

		Woocommerce_Helpers::maybe_init_cart_context();

		// Avoid Fatal error if WC()->cart is not defined (@since 2.0)
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		if ( ! wc_coupons_enabled() ) {
			// translators: %1$s: opening a tag, %2$s: closing a tag
			return $this->render_element_placeholder( [ 'title' => sprintf( esc_html__( 'Coupons are disabled. To enable coupons go to %1$sWooCommerce settings%2$s', 'bricks' ), '<a href="' . admin_url( 'admin.php?page=wc-settings' ) . '">', '</a>' ) ] );
		}

		$placeholder  = isset( $settings['inputPlaceholder'] ) ? $settings['inputPlaceholder'] : __( 'Coupon code', 'woocommerce' );
		$button_label = isset( $settings['buttonText'] ) ? $settings['buttonText'] : __( 'Apply coupon', 'woocommerce' );

		// Set ajax update attribute
		if ( isset( $settings['ajaxUpdate'] ) && $settings['ajaxUpdate'] ) {
			$this->set_attribute( '_root', 'data-ajax-update', 'true' );
		}

		$this->set_attribute( '_root', 'action', esc_url( wc_get_cart_url() ) );
		$this->set_attribute( '_root', 'method', 'post' );
		?>

		<form <?php echo $this->render_attributes( '_root' ); ?>>
			<div class="coupon">
				<label for="coupon_code"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
				<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php echo esc_html( $placeholder ); ?>" />

				<button type="submit" class="button" name="apply_coupon" value="<?php echo esc_attr( $button_label ); ?>"><?php echo esc_html( $button_label ); ?></button>

				<?php do_action( 'woocommerce_cart_coupon' ); ?>
			</div>
		</form>

		<?php
	}
}
