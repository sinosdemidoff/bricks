<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$all_elements                = Elements::$elements;
$bricks_native_element_names = Elements::$native;
$element_manager             = Elements::manager();
$builder_i18n                = Builder::i18n();

foreach ( $all_elements as $index => $element ) {
	$element_class_name = $element['class'];
	$element_instance   = new $element_class_name();

	$all_elements[ $index ]['category'] = $element_instance->category;
	$all_elements[ $index ]['icon']     = $element_instance->icon;
	$all_elements[ $index ]['label']    = $element_instance->get_label();
	$all_elements[ $index ]['status']   = $element_manager[ $element['name'] ]['status'] ?? 'active';
}
?>

<div class="wrap">
	<h1 class="admin-notices-placeholder"></h1>

	<div class="bricks-admin-title-wrapper">
		<h1 class="title"><?php echo esc_html__( 'Element Manager', 'bricks' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Set the status of each element, native and custom, in the builder and frontend.', 'bricks' ); ?></p>

		<ul>
			<li><strong><?php echo esc_html__( 'Active', 'bricks' ); ?>:</strong> <?php esc_html_e( 'Element is visible in the builder and frontend. This is the default behaviour.', 'bricks' ); ?></li>
			<li><strong><?php echo esc_html__( 'Hidden', 'bricks' ) . ' (' . esc_html__( 'Builder' ) . ')'; ?>:</strong> <?php esc_html_e( 'Element is not visible in the builder elements panel, but still rendered in the builder and frontend.', 'bricks' ); ?></li>
			<li><strong><?php esc_html_e( 'Disabled', 'bricks' ); ?>:</strong> <?php esc_html_e( 'Element is not visible in the builder nor on the frontend.', 'bricks' ); ?></li>
		</ul>
	</div>

	<h3><?php esc_html_e( 'Filter by', 'bricks' ); ?>:</h3>

	<button class="bricks-button-filter button button-secondary" data-filter-by="unused">
		<?php echo '<i class="dashicons dashicons-insert"></i>'; ?>
		<?php esc_html_e( 'Unused elements', 'bricks' ); ?>
	</button>

	<button class="bricks-button-filter button button-secondary" data-filter-by="native">
		<?php echo '<i class="dashicons dashicons-insert"></i>'; ?>
		<?php esc_html_e( 'Native elements', 'bricks' ); ?>
	</button>

	<button class="bricks-button-filter button button-secondary" data-filter-by="custom">
		<?php echo '<i class="dashicons dashicons-insert"></i>'; ?>
		<?php esc_html_e( 'Custom elements', 'bricks' ); ?>
	</button>

	<form id="bricks-element-manager" method="post" autocomplete="off">
		<table class="wp-list-table widefat fixed table-view-list elements" data-filters="">
			<thead>
				<tr>
					<th scope="col" id="element_title" class="manage-column column-element_title"><?php esc_html_e( 'Name', 'bricks' ); ?></th>
					<th scope="col" id="element_status" class="manage-column column-element_status"><?php esc_html_e( 'Status', 'bricks' ); ?></th>
					<th scope="col" id="element_usage" class="manage-column column-element_usage"><?php esc_html_e( 'Usage', 'bricks' ); ?></th>
					<th scope="col" id="element_category" class="manage-column column-element_category"><?php esc_html_e( 'Category', 'bricks' ); ?></th>
				</tr>
			</thead>

			<tbody id="the-list" data-wp-lists="list:submission">
				<?php
				$mandatory_elements = Elements::mandatory_elements();

				foreach ( $all_elements as $element ) {
					$element_name   = $element['name'];
					$element_label  = $element['label'];
					$element_status = $element_manager[ $element_name ]['status'] ?? 'active';
					$element_type   = in_array( $element['name'], $bricks_native_element_names ) ? 'native' : 'custom';

					echo '<tr data-name="' . esc_attr( $element_name ) . '" data-status="' . esc_attr( $element_status ) . '" data-type="' . esc_attr( $element_type ) . '">';

					// STEP: Title
					echo '<td class="element-title">';

					$element_title = '';

					if ( ! empty( $element['icon'] ) ) {
						$element_title .= '<i class="icon ' . esc_attr( $element['icon'] ) . '"></i>';
					}

					// Is custom element
					if ( $element_type === 'custom' ) {
						$element_title .= '<button class="custom-element">' . esc_html__( 'Custom', 'bricks' ) . '</button>';
					}

					$element_title .= '<span class="name" title="' . esc_attr( $element_name ) . '">' . esc_html( $element_label ) . '</span>';

					echo $element_title;

					echo '</td>';

					/**
					 * STEP: Element status
					 *
					 * Options: active, hidden_builder, disabled (global)
					 */
					echo '<td class="element-status">';
					echo '<div class="buttons">';

					if ( $element_status === 'active' ) {
						echo '<span class="active current" data-status="active">' . esc_html__( 'Active', 'bricks' ) . '</span>';
					} else {
						echo '<span class="active" data-status="active">' . esc_html__( 'Active', 'bricks' ) . '</span>';
					}

					// Container element is always active
					if ( in_array( $element_name, $mandatory_elements, true ) ) {
						echo ' (' . esc_html__( 'Mandatory', 'bricks' ) . ')';
					}

					// All other elements (can be hidden in the builder or disabled globally)
					else {
						if ( $element_status === 'hidden_builder' ) {
							echo '<span class="hidden_builder current" data-status="hidden_builder">' . esc_html__( 'Hidden', 'bricks' ) . ' (' . esc_html__( 'Builder', 'bricks' ) . ')</span>';
						} else {
							echo '<span class="hidden_builder" data-status="hidden_builder">' . esc_html__( 'Hidden', 'bricks' ) . ' (' . esc_html__( 'Builder', 'bricks' ) . ')</span>';
						}

						if ( $element_status === 'disabled' ) {
							echo '<span class="disabled current" data-status="disabled">' . esc_html__( 'Disabled', 'bricks' ) . '</span>';
						} else {
							echo '<span class="disabled" data-status="disabled">' . esc_html__( 'Disabled', 'bricks' ) . '</span>';
						}
					}

					echo '</div>';

					echo '</td>';

					// STEP: Usage - Now shows a loading indicator, to be populated via AJAX
					echo '<td class="element-usage" data-element-name="' . esc_attr( $element_name ) . '">';
					echo '<span class="spinner is-active" style="float: none; margin: 0;"></span>';
					echo '</td>';

					// STEP: Category
					$element_category = $element['category'];
					$category_label   = $builder_i18n[ $element_category ] ?? $element_category;

					if ( $element_category === 'woocommerce' ) {
						$category_label = 'WooCommerce';
					}

					echo '<td class="element-category">' . esc_html( $category_label ) . '</td>';

					echo '</tr>';
				}
				?>
			</tbody>
		</table>

		<div class="submit-wrapper">
			<input type="submit" name="save" value="<?php esc_html_e( 'Save', 'bricks' ); ?>" class="button button-primary button-large">
			<input type="submit" name="reset" value="<?php esc_html_e( 'Reset', 'bricks' ); ?>" class="button button-secondary button-large">
		</div>

		<span class="spinner saving"></span>
	</form>
</div>
