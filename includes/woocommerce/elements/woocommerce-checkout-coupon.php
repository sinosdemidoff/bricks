<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Woocommerce_Checkout_Coupon extends Woo_Element {
	public $name = 'woocommerce-checkout-coupon';
	public $icon = 'ti-ticket';
	// NOTE: Don't limit to Checkout template only as user might add it on the Checkout page directly, outside the checkout form
	// public $panel_condition = [ 'templateType', '=', 'wc_form_checkout' ];

	public function get_label() {
		return esc_html__( 'Checkout coupon', 'bricks' );
	}

	public function set_control_groups() {
		$this->control_groups['toggle'] = [
			'title'    => esc_html__( 'Toggle', 'bricks' ),
			'required' => [ 'toggleableForm', '=', true ],
		];

		$this->control_groups['form'] = [
			'title' => esc_html__( 'Form', 'bricks' ),
		];

		$this->control_groups['fields'] = [
			'title' => esc_html__( 'Fields', 'bricks' ),
		];

		$this->control_groups['submitButton'] = [
			'title' => esc_html__( 'Submit button', 'bricks' ),
		];
	}

	public function set_controls() {
		$this->controls['location'] = [
			'label'       => esc_html__( 'Location', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'before_order_review_heading' => esc_html__( 'Before order review heading', 'bricks' ),
				'before_order_review'         => esc_html__( 'After order review heading', 'bricks' ),
				'order_review'                => esc_html__( 'Before payment', 'bricks' ),
			],
			'placeholder' => esc_html__( 'Current location', 'bricks' ),
		];

		$this->controls['locationInfo'] = [
			'content'  => esc_html__( 'Custom location only takes effect on the frontend. Ensure this element is placed at the top of this template so the element can hook on the desire location successfully.', 'bricks' ),
			'type'     => 'info',
			'required' => [ 'location', '!=', [ '', 'current' ] ],
		];

		$this->controls['toggleableForm'] = [
			'label'       => esc_html__( 'Toggleable form', 'bricks' ),
			'type'        => 'checkbox',
			'description' => esc_html__( 'Hide the form by default, and show it only when the toggle is clicked.', 'bricks' ),
		];

		// TOGGLE
		$this->controls['toggleText'] = [
			'group'       => 'toggle',
			'label'       => esc_html__( 'Text', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'Have a coupon?', 'woocommerce' ),
			'required'    => [ 'toggleableForm', '=', true ],
		];

		$this->controls['toggleDivJustifyContent'] = [
			'group' => 'toggle',
			'label' => esc_html__( 'Justify content', 'bricks' ),
			'type'  => 'justify-content',
			'css'   => [
				[
					'property' => 'justify-content',
					'selector' => '.coupon-toggle',
				],
			],
		];

		$this->controls['toggleDivGap'] = [
			'group' => 'toggle',
			'label' => esc_html__( 'Gap', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'property' => 'gap',
					'selector' => '.coupon-toggle',
				],
			],
		];

		$toggle_div_controls = $this->generate_standard_controls( 'toggleDiv', '.coupon-toggle' );
		$toggle_div_controls = $this->controls_grouping( $toggle_div_controls, 'toggle' );
		$this->controls      = array_merge( $this->controls, $toggle_div_controls );

		// TOGGLE BUTTON
		$this->controls['toggleButtonSep'] = [
			'group' => 'toggle',
			'type'  => 'separator',
			'label' => esc_html__( 'Button', 'bricks' ),
		];

		$this->controls['toggleButtonNoText'] = [
			'group'    => 'toggle',
			'label'    => esc_html__( 'Disable text', 'bricks' ),
			'type'     => 'checkbox',
			'default'  => false,
			'required' => [ 'toggleableForm', '=', true ],
		];

		$this->controls['toggleButtonText'] = [
			'group'       => 'toggle',
			'label'       => esc_html__( 'Text', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'Click here to enter your code', 'woocommerce' ),
			'required'    => [
				[ 'toggleableForm', '=', true ],
				[ 'toggleButtonNoText', '=', false ],
			],
		];

		$this->controls['toggleIcon'] = [
			'group' => 'toggle',
			'label' => esc_html__( 'Icon', 'bricks' ),
			'type'  => 'icon',
		];

		$this->controls['toggleIconTypography'] = [
			'group'    => 'toggle',
			'label'    => esc_html__( 'Icon typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.coupon-toggle .showcoupon i',
				],
			],
			'required' => [ 'toggleIcon.icon', '!=', '' ],
		];

		$toggle_button_controls = $this->generate_standard_controls( 'toggleButton', '.coupon-toggle .showcoupon' );
		$toggle_button_controls = $this->controls_grouping( $toggle_button_controls, 'toggle' );
		$this->controls         = array_merge( $this->controls, $toggle_button_controls );

		// FORM
		$this->controls['disableCouponMessage'] = [
			'group'   => 'form',
			'label'   => esc_html__( 'Disable coupon message', 'bricks' ),
			'type'    => 'checkbox',
			'default' => false,
		];

		$this->controls['couponMessage'] = [
			'group'       => 'form',
			'label'       => esc_html__( 'Coupon message', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'If you have a coupon code, please apply it below.', 'woocommerce' ),
			'required'    => [ 'disableCouponMessage', '=', false ],
		];

		$wrapper_controls = $this->generate_standard_controls( 'formWrapper', '.coupon-div' );
		$wrapper_controls = $this->controls_grouping( $wrapper_controls, 'form' );
		$this->controls   = array_merge( $this->controls, $wrapper_controls );

		// FIELDS
		$this->controls['fieldsWrapperFlexDirection'] = [
			'group'  => 'fields',
			'label'  => esc_html__( 'Flex direction', 'bricks' ),
			'type'   => 'direction',
			'inline' => true,
			'css'    => [
				[
					'property' => 'flex-direction',
					'selector' => '.coupon-form',
				],
			],
		];

		$fields_controls = $this->get_woo_form_fields_controls( '.coupon-div .coupon-form' );
		$fields_controls = $this->controls_grouping( $fields_controls, 'fields' );

		// Remove unnecessary controls
		unset( $fields_controls['hidePlaceholders'] );
		unset( $fields_controls['hideLabels'] );
		unset( $fields_controls['labelTypography'] );

		$this->controls = array_merge( $this->controls, $fields_controls );

		// SUBMIT BUTTON
		$this->controls['applyButtonText'] = [
			'group'       => 'submitButton',
			'label'       => esc_html__( 'Button text', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'Apply coupon', 'woocommerce' ),
		];

		$submit_controls = $this->get_woo_form_submit_controls();
		$submit_controls = $this->controls_grouping( $submit_controls, 'submitButton' );
		$this->controls  = array_merge( $this->controls, $submit_controls );
	}

	public function render() {
		$settings = $this->settings;

		if ( ! wc_coupons_enabled() ) {
			// translators: %1$s: opening a tag, %2$s: closing a tag
			return $this->render_element_placeholder( [ 'title' => sprintf( esc_html__( 'Coupons are disabled. To enable coupons go to %1$sWooCommerce settings%2$s', 'bricks' ), '<a href="' . admin_url( 'admin.php?page=wc-settings' ) . '">', '</a>' ) ] );
		}

		$checkout_coupon = $this->generate_html();
		$location        = isset( $settings['location'] ) ? $settings['location'] : 'current';

		if (
			$location !== 'current' &&
			! bricks_is_builder_main() &&
			! bricks_is_builder_iframe() &&
			! bricks_is_builder_call()
		) {
			// Use hook if this is actual frontend and location is not current
			add_action(
				'woocommerce_checkout_' . $location,
				function() use ( $checkout_coupon ) {
					echo $checkout_coupon;
				}
			);

			return;
		}

		echo $checkout_coupon;
	}

	private function generate_html() {
		$settings            = $this->settings;
		$disable_description = isset( $settings['disableCouponMessage'] );
		$apply_button_text   = isset( $settings['applyButtonText'] ) ? $this->render_dynamic_data( $settings['applyButtonText'] ) : esc_html__( 'Apply coupon', 'woocommerce' );
		$coupon_message      = isset( $settings['couponMessage'] ) ? $this->render_dynamic_data( $settings['couponMessage'] ) : esc_html__( 'If you have a coupon code, please apply it below.', 'woocommerce' );
		$toggleable          = isset( $settings['toggleableForm'] );

		ob_start();

		$this->set_attribute( 'coupon_div', 'class', 'coupon-div' );
		echo "<div {$this->render_attributes( '_root' )}>";

		if ( $toggleable ) {
			$toggle_text     = isset( $settings['toggleText'] ) ? $this->render_dynamic_data( $settings['toggleText'] ) : esc_html__( 'Have a coupon?', 'woocommerce' );
			$toggle_btn_text = isset( $settings['toggleButtonText'] ) ? $this->render_dynamic_data( $settings['toggleButtonText'] ) : esc_html__( 'Click here to enter your code', 'woocommerce' );
			$toggle_btn_text = isset( $settings['toggleButtonNoText'] ) ? '' : $toggle_btn_text; // Hide the text if no text is checked
			$toggle_btn_icon = isset( $settings['toggleIcon'] ) ? $this->render_icon( $settings['toggleIcon'] ) : '';
			// Do not output the link if no text and no icon
			$toggle_content = $toggle_btn_text === '' && $toggle_btn_icon === '' ? $toggle_text : $toggle_text . ' <a href="#" class="showcoupon" aria-hidden="true">' . $toggle_btn_text . $toggle_btn_icon . '</a>';
			$toggle_content = apply_filters( 'woocommerce_checkout_coupon_message', $toggle_content );

			// Hide the coupon_div in actual frontend except in the builder for design purposes
			if (
				! bricks_is_builder_main() &&
				! bricks_is_builder_iframe() &&
				! bricks_is_builder_call()
			) {
				$this->set_attribute( 'coupon_div', 'style', 'display: none;' );
			}

			$this->set_attribute( 'coupon_toggle_div', 'class', 'coupon-toggle' );
			// A11y
			$this->set_attribute( 'coupon_toggle_div', 'role', 'button' );
			$this->set_attribute( 'coupon_toggle_div', 'tabindex', '0' );
			$this->set_attribute( 'coupon_toggle_div', 'aria-expanded', 'false' );
			$this->set_attribute( 'coupon_toggle_div', 'aria-label', esc_html__( 'Toggle coupon form', 'bricks' ) );

			echo "<div {$this->render_attributes( 'coupon_toggle_div' )}>{$toggle_content}</div>";
		}

		echo "<div {$this->render_attributes( 'coupon_div' )}>";

		if ( ! $disable_description ) {
			$this->set_attribute( 'coupon_description', 'class', 'coupon-description' );
			echo "<p {$this->render_attributes( 'coupon_description' )}>{$coupon_message}</p>";
		}

		$this->set_attribute( 'coupon_form', 'class', 'coupon-form' );
		echo "<div {$this->render_attributes( 'coupon_form' )}>";
		?>
				<label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
				<input type="text" name="coupon_code" class="woocommerce-Input woocommerce-Input--text input-text" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" id="coupon_code" value="" />
				<button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="apply_coupon" value="<?php echo esc_attr( $apply_button_text ); ?>"><?php echo $apply_button_text; ?></button>
		<?php
		echo '</div>';
		echo '</div>';
		echo '</div>';

		return ob_get_clean();
	}
}
