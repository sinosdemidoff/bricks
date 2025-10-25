<?php
/**
 * Checkout Form
 * This template part might replace the default WooCommerce template if Bricks Form Checkout template exists
 *
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$template_data = Bricks\Woocommerce::get_template_data_by_type( 'wc_form_checkout' );
$form_classes  = [ 'woocommerce-checkout', 'checkout' ];

if ( ! $template_data ) {
	// Use default WooCommerce checkout styles
	$form_classes[] = 'bricks-default-checkout';
	$form_classes[] = 'brxe-container';
}

ob_start();
do_action( 'woocommerce_before_checkout_form', $checkout );
$before_checkout_form_html = ob_get_clean();

if ( $before_checkout_form_html ) {
	echo '<div class="brxe-container before-checkout">';
	echo $before_checkout_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';
}

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );

	return;
}
?>

<form name="checkout" method="post" class="<?php echo implode( ' ', $form_classes ); ?>" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">
	<?php
	// Render Bricks template
	if ( $template_data ) {
		echo $template_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// WooCommerce template
	else {
		if ( $checkout->get_checkout_fields() ) {
			do_action( 'woocommerce_checkout_before_customer_details' );
			?>
			<div class="col2-set" id="customer_details">
				<div class="col-1">
					<?php do_action( 'woocommerce_checkout_billing' ); ?>
				</div>

				<div class="col-2">
					<?php do_action( 'woocommerce_checkout_shipping' ); ?>
				</div>
			</div>

			<?php
			do_action( 'woocommerce_checkout_after_customer_details' );
		}

		do_action( 'woocommerce_checkout_before_order_review_heading' );
		?>

		<div>
			<h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>

			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

			<div id="order_review" class="woocommerce-checkout-review-order">
				<?php do_action( 'woocommerce_checkout_order_review' ); ?>
			</div>
		</div>

		<?php
		do_action( 'woocommerce_checkout_after_order_review' );
	}
	?>
</form>

<?php
do_action( 'woocommerce_after_checkout_form', $checkout );
