<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Woocommerce_Checkout_Login extends Woo_Element {
	public $name = 'woocommerce-checkout-login';
	public $icon = 'fa fa-address-card';
	// Not limit to Checkout template only, user might use this on Checkout page directly (outside of checkout form)
	// public $panel_condition = [ 'templateType', '=', 'wc_form_checkout' ];

	public function get_label() {
		return esc_html__( 'Checkout login', 'bricks' );
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

		$this->control_groups['rememberMe'] = [
			'title' => esc_html__( 'Remember me', 'bricks' ),
		];

		$this->control_groups['submitButton'] = [
			'title' => esc_html__( 'Submit button', 'bricks' ),
		];

		$this->control_groups['lostPassword'] = [
			'title' => esc_html__( 'Lost password', 'bricks' ),
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
			'placeholder' => esc_html__( 'Returning customer?', 'woocommerce' ),
			'required'    => [ 'toggleableForm', '=', true ],
		];

		$this->controls['toggleDivJustifyContent'] = [
			'group' => 'toggle',
			'label' => esc_html__( 'Justify content', 'bricks' ),
			'type'  => 'justify-content',
			'css'   => [
				[
					'property' => 'justify-content',
					'selector' => '.login-toggle',
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
					'selector' => '.login-toggle',
				],
			],
		];

		$toggle_div_controls = $this->generate_standard_controls( 'toggleDiv', '.login-toggle' );
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
			'placeholder' => esc_html__( 'Click here to login', 'woocommerce' ),
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
					'selector' => '.login-toggle .showlogin i',
				],
			],
			'required' => [ 'toggleIcon.icon', '!=', '' ],
		];

		$toggle_button_controls = $this->generate_standard_controls( 'toggleButton', '.login-toggle .showlogin' );
		$toggle_button_controls = $this->controls_grouping( $toggle_button_controls, 'toggle' );
		$this->controls         = array_merge( $this->controls, $toggle_button_controls );

		// FORM
		$this->controls['disableLoginMessage'] = [
			'group'   => 'form',
			'label'   => esc_html__( 'Disable login message', 'bricks' ),
			'type'    => 'checkbox',
			'default' => false,
		];

		$this->controls['loginMessage'] = [
			'group'       => 'form',
			'label'       => esc_html__( 'Login message', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'If you have shopped with us before, please enter your details below. If you are a new customer, please proceed to the Billing section.', 'woocommerce' ),
			'required'    => [ 'disableLoginMessage', '=', false ],
		];

		$wrapper_controls = $this->generate_standard_controls( 'formWrapper', '.login-div' );
		$wrapper_controls = $this->controls_grouping( $wrapper_controls, 'form' );
		$this->controls   = array_merge( $this->controls, $wrapper_controls );

		// FIELDS
		$fields_controls = $this->get_woo_form_fields_controls( '.login-div .credential' );
		$fields_controls = $this->controls_grouping( $fields_controls, 'fields' );

		// Remove unnecessary controls
		unset( $fields_controls['fieldsWidth'] );
		unset( $fields_controls['fieldsInputMargin'] );
		unset( $fields_controls['hideLabels'] );
		unset( $fields_controls['hidePlaceholders'] );
		unset( $fields_controls['placeholderTypography'] );

		$this->controls = array_merge( $this->controls, $fields_controls );

		// SUBMIT BUTTON
		$submit_controls = $this->get_woo_form_submit_controls();
		$submit_controls = $this->controls_grouping( $submit_controls, 'submitButton' );
		$this->controls  = array_merge( $this->controls, $submit_controls );

		// REMEMBER ME
		$this->controls['rememberMeDisable'] = [
			'group' => 'rememberMe',
			'type'  => 'checkbox',
			'label' => esc_html__( 'Disable', 'bricks' ),
		];

		$this->controls['rememberMeTypography'] = [
			'group'    => 'rememberMe',
			'label'    => esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.woocommerce-form-login__rememberme',
				]
			],
			'required' => [ 'rememberMeDisable', '=', false ],
		];

		// LOST PASSWORD
		$this->controls['lostPasswordDisable'] = [
			'group' => 'lostPassword',
			'type'  => 'checkbox',
			'label' => esc_html__( 'Disable', 'bricks' ),
		];

		$this->controls['lostPasswordTypography'] = [
			'group'    => 'lostPassword',
			'label'    => esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.woocommerce-LostPassword a',
				]
			],
			'required' => [ 'lostPasswordDisable', '=', false ],
		];
	}

	public function render() {
		$settings = $this->settings;

		// Indicate that login during checkout is disabled
		if ( 'no' === get_option( 'woocommerce_enable_checkout_login_reminder' ) ) {
			// translators: %1$s: opening a tag, %2$s: closing a tag
			return $this->render_element_placeholder( [ 'title' => sprintf( esc_html__( 'Enable log-in during checkout disabled. Check %1$sWooCommerce settings%2$s', 'bricks' ), '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=account' ) . '">', '</a>' ) ] );
		}

		$location = $settings['location'] ?? 'current';

		// Do not show the login form if the user is already logged in (except in the builder or preview mode)
		if (
			! bricks_is_builder_main() &&
			! bricks_is_builder_iframe() &&
			! bricks_is_builder_call() &&
			! isset( $_GET['bricks_preview'] ) &&
			is_user_logged_in()
		) {
			return;
		}

		$login_form_html = $this->generate_html();

		if (
			$location !== 'current' &&
			! bricks_is_builder_main() &&
			! bricks_is_builder_iframe() &&
			! bricks_is_builder_call()
		) {
			// Use hook if this is actual frontend and location is not current
			add_action(
				'woocommerce_checkout_' . $location,
				function() use ( $login_form_html ) {
					echo $login_form_html;
				}
			);

			return;
		}

		echo $login_form_html;
	}

	private function generate_html() {
		ob_start();
		echo "<div {$this->render_attributes( '_root' )}>";
		echo $this->get_login_form_content();
		echo '</div>';
		$login_form_html = ob_get_clean();

		return $login_form_html;
	}

	/**
	 * @see global/form-login.php
	 */
	private function get_login_form_content() {
		$settings          = $this->settings;
		$credential_layout = isset( $settings['credentialLayout'] ) ? esc_attr( $settings['credentialLayout'] ) : 'default';
		$login_message     = isset( $settings['loginMessage'] ) ? esc_html( $settings['loginMessage'] ) : esc_html__( 'If you have shopped with us before, please enter your details below. If you are a new customer, please proceed to the Billing section.', 'woocommerce' );
		$toggleable        = isset( $settings['toggleableForm'] );
		$disable_message   = isset( $settings['disableLoginMessage'] );

		ob_start();

		if ( $toggleable ) {
			$toggle_text     = isset( $settings['toggleText'] ) ? $this->render_dynamic_data( $settings['toggleText'] ) : esc_html__( 'Returning customer?', 'woocommerce' );
			$toggle_btn_text = isset( $settings['toggleButtonText'] ) ? $this->render_dynamic_data( $settings['toggleButtonText'] ) : esc_html__( 'Click here to login', 'woocommerce' );
			$toggle_btn_text = isset( $settings['toggleButtonNoText'] ) ? '' : $toggle_btn_text; // Hide the text if no text is checked
			$toggle_btn_icon = isset( $settings['toggleIcon'] ) ? $this->render_icon( $settings['toggleIcon'] ) : '';
			// Do not output the link if no text and no icon
			$toggle_content = $toggle_btn_text === '' && $toggle_btn_icon === '' ? $toggle_text : $toggle_text . ' <a href="#" class="showlogin" aria-hidden="true">' . $toggle_btn_text . $toggle_btn_icon . '</a>';
			$toggle_content = apply_filters( 'woocommerce_checkout_login_message', $toggle_content );

			// Hide the form in actual frontend except in the builder for design purposes
			if (
				! bricks_is_builder_main() &&
				! bricks_is_builder_iframe() &&
				! bricks_is_builder_call()
			) {
				$this->set_attribute( 'login_div', 'style', 'display: none;' );
			}

			$this->set_attribute( 'login_toggle_div', 'class', 'login-toggle' );
			// A11y
			$this->set_attribute( 'login_toggle_div', 'role', 'button' );
			$this->set_attribute( 'login_toggle_div', 'tabindex', '0' );
			$this->set_attribute( 'login_toggle_div', 'aria-expanded', 'false' );
			$this->set_attribute( 'login_toggle_div', 'aria-label', esc_html__( 'Toggle login form', 'bricks' ) );
			echo "<div {$this->render_attributes( 'login_toggle_div' )}>{$toggle_content}</div>";
		}

		$this->set_attribute( 'login_div', 'class', 'login-div' );
		echo "<div {$this->render_attributes( 'login_div' )}>";

		do_action( 'woocommerce_login_form_start' );

		echo ( $login_message && ! $disable_message ) ? wpautop( wptexturize( $login_message ) ) : '';
		?>

		<div class="credential <?php echo $credential_layout; ?>">

		<div class="form-group username">
			<?php
			$username       = ! empty( $_POST['username'] ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : '';
			$username_label = esc_html__( 'Username or email address', 'woocommerce' );

			echo '<label for="username">' . $username_label . ' <span class="required">*</span></label>';
			echo '<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" spellcheck="false" value="' . $username . '" required aria-required="true" />';
			?>
		</div>

		<div class="form-group password">
			<?php
			echo '<label for="password">' . esc_html__( 'Password', 'woocommerce' ) . ' <span class="required">*</span></label>';

			// Builder: Add span to wrap password input manually (no JS enqueued) to show password toggle icon
			if ( bricks_is_builder() || bricks_is_builder_call() || isset( $_GET['bricks_preview'] ) ) {
				echo '<span class="password-input">';
			}

			echo '<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />';

			if ( bricks_is_builder() || bricks_is_builder_call() || isset( $_GET['bricks_preview'] ) ) {
				echo '<span class="show-password-input"></span>';
				echo '</span>';
			}
			?>
		</div>

		</div>

		<?php do_action( 'woocommerce_login_form' ); ?>

		<?php if ( ! isset( $settings['rememberMeDisable'] ) ) { ?>
		<div class="form-group remember">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
				<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
			</label>
		</div>
		<?php } ?>

		<div class="form-group submit">
			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
			<input type="hidden" name="redirect" value="<?php echo esc_url( wc_get_checkout_url() ); ?>" />
			<button type="submit" class="woocommerce-button button woocommerce-form-login__submit<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="login" value="<?php esc_attr_e( 'Login', 'woocommerce' ); ?>">
				<?php esc_html_e( 'Login', 'woocommerce' ); ?>
			</button>
		</div>

		<?php if ( ! isset( $settings['lostPasswordDisable'] ) ) { ?>
		<div class="woocommerce-LostPassword lost_password">
			<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
		</div>
		<?php } ?>

		<?php
		do_action( 'woocommerce_login_form_end' );
		echo '</div>';

		return ob_get_clean();
	}
}
