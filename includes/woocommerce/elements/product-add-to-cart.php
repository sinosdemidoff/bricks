<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Product_Add_To_Cart extends Element {
	public $category = 'woocommerce_product';
	public $name     = 'product-add-to-cart';
	public $icon     = 'ti-shopping-cart';

	public function enqueue_scripts() {
		// Ensure variation form in AJAX Popup can init (@since 1.10.2)
		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-add-to-cart-variation' );
	}

	public function get_label() {
		return esc_html__( 'Add to cart', 'bricks' );
	}

	public function set_control_groups() {
		$this->control_groups['variations'] = [
			'title' => esc_html__( 'Variations', 'bricks' ),
			'tab'   => 'content',
		];

		// Variation swatches (@since 2.0)
		if ( \Bricks\Database::get_setting( 'woocommerceUseVariationSwatches' ) ) {
			$this->control_groups['variation-swatches'] = [
				'title' => esc_html__( 'Variation swatches', 'bricks' ),
				'tab'   => 'content',
			];
		}

		$this->control_groups['stock'] = [
			'title' => esc_html__( 'Stock', 'bricks' ),
			'tab'   => 'content',
		];

		$this->control_groups['form'] = [
			'title' => esc_html__( 'Form', 'bricks' ),
			'tab'   => 'content',
		];

		$this->control_groups['quantity'] = [
			'title' => esc_html__( 'Quantity', 'bricks' ),
			'tab'   => 'content',
		];

		$this->control_groups['button'] = [
			'title' => esc_html__( 'Button', 'bricks' ),
			'tab'   => 'content',
		];

		// @since 1.6.1
		if ( Woocommerce::enabled_ajax_add_to_cart() ) {
			$this->control_groups['ajax'] = [
				'title' => 'AJAX',
				'tab'   => 'content',
			];
		}
	}

	public function set_controls() {
		// VARIATIONS

		// NOTE: Variation settings not applicable in query loop (@since 1.6 @see #33v4yb9)

		$this->controls['variationsTypography'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => 'table.variations label',
				],
			],
		];

		$this->controls['variationsBackgroundColor'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => 'table.variations tr',
				],
			],
		];

		$this->controls['variationsBorder'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.cart .variations tr:not(:has(.reset_variations))',
				]
			],
		];

		$this->controls['variationsMargin'] = [
			'tab'         => 'content',
			'group'       => 'variations',
			'label'       => esc_html__( 'Margin', 'bricks' ),
			'type'        => 'spacing',
			'css'         => [
				[
					'selector' => '.cart table.variations',
					'property' => 'margin',
				],
			],
			'placeholder' => [
				'bottom' => 30,
			],
		];

		$this->controls['variationsPadding'] = [
			'tab'         => 'content',
			'group'       => 'variations',
			'label'       => esc_html__( 'Padding', 'bricks' ),
			'type'        => 'spacing',
			'css'         => [
				[
					'selector' => '.cart table.variations td',
					'property' => 'padding',
				],
				[
					'selector' => '.cart table.variations th', // @since 2.0
					'property' => 'padding',
				],
			],
			'placeholder' => [
				'top'    => 15,
				'bottom' => 15,
			],
		];

		$this->controls['variationsDescriptionTypography'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Description typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.woocommerce-variation-description',
				],
			],
		];

		$this->controls['variationsPriceTypography'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Price typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.woocommerce-variation-price',
				],
			],
		];

		$this->controls['variationsRegularPriceTypography'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Regular price typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'selector' => '.woocommerce-variation-price .price del, .woocommerce-variation-price .price > span',
					'property' => 'font',
				],
			],
		];

		$this->controls['variationsSalePriceTypography'] = [
			'tab'   => 'content',
			'group' => 'variations',
			'label' => esc_html__( 'Sale price typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'selector' => '.woocommerce-variation-price .price ins',
					'property' => 'font',
				],
			],
		];

		// Common swatch controls (for all types) (@since 2.0)

		$this->controls['swatchesWrap'] = [
			'group'   => 'variation-swatches',
			'label'   => esc_html__( 'Wrap', 'bricks' ),
			'type'    => 'select',
			'inline'  => true,
			'options' => [
				'nowrap'       => esc_html__( 'No wrap', 'bricks' ),
				'wrap'         => esc_html__( 'Wrap', 'bricks' ),
				'wrap-reverse' => esc_html__( 'Wrap reverse', 'bricks' ),
			],
			'css'     => [
				[
					'property' => 'flex-wrap',
					'selector' => '.bricks-variation-swatches',
				],
			],
		];

		$this->controls['swatchesDirection'] = [
			'group'  => 'variation-swatches',
			'label'  => esc_html__( 'Direction', 'bricks' ),
			'type'   => 'direction',
			'css'    => [
				[
					'property' => 'flex-direction',
					'selector' => '.bricks-variation-swatches',
				],
			],
			'inline' => true,
		];

		$this->controls['swatchesJustifyContent'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Align main axis', 'bricks' ),
			'type'  => 'justify-content',
			'css'   => [
				[
					'property' => 'justify-content',
					'selector' => '.bricks-variation-swatches',
				],
			],
		];

		$this->controls['swatchesAlignItems'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Align cross axis', 'bricks' ),
			'type'  => 'align-items',
			'css'   => [
				[
					'property' => 'align-items',
					'selector' => '.bricks-variation-swatches',
				],
			],
		];

		$this->controls['swatchesColumnGap'] = [
			'group'       => 'variation-swatches',
			'label'       => esc_html__( 'Column gap', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'column-gap',
					'selector' => '.bricks-variation-swatches',
				],
			],
			'placeholder' => '8px',
		];

		$this->controls['swatchesRowGap'] = [
			'group'       => 'variation-swatches',
			'label'       => esc_html__( 'Row gap', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'row-gap',
					'selector' => '.bricks-variation-swatches',
				],
			],
			'placeholder' => '8px',
		];

		// Color swatch specific controls
		$this->controls['colorSwatchSeparator'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Type', 'bricks' ) . ': ' . esc_html__( 'Color', 'bricks' ),
			'type'  => 'separator',
		];

		$this->controls['colorSwatchSize'] = [
			'group'       => 'variation-swatches',
			'label'       => esc_html__( 'Size', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'width',
					'selector' => '.bricks-variation-swatches.bricks-swatch-color li',
				],
				[
					'property' => 'height',
					'selector' => '.bricks-variation-swatches.bricks-swatch-color li',
				],
			],
			'placeholder' => '32px',
		];

		$this->controls['colorSwatchBorder'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.bricks-variation-swatches.bricks-swatch-color li',
				],
			],
		];

		$this->controls['colorSwatchActiveBorder'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Border', 'bricks' ) . ' (' . esc_html__( 'Active', 'bricks' ) . ')',
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.bricks-variation-swatches.bricks-swatch-color li.bricks-swatch-selected',
				],
			],
		];

		// Label swatch specific controls
		$this->controls['labelSwatchSeparator'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Type', 'bricks' ) . ': ' . esc_html__( 'Label', 'bricks' ),
			'type'  => 'separator',
		];

		$this->controls['labelSwatchPadding'] = [
			'group'       => 'variation-swatches',
			'label'       => esc_html__( 'Padding', 'bricks' ),
			'type'        => 'spacing',
			'css'         => [
				[
					'property' => 'padding',
					'selector' => '.bricks-variation-swatches.bricks-swatch-label li',
				],
			],
			'placeholder' => [
				'top'    => '0',
				'right'  => '10px',
				'bottom' => '0',
				'left'   => '10px',
			],
		];

		$this->controls['labelSwatchTypography'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.bricks-variation-swatches.bricks-swatch-label li',
				],
			],
		];

		$this->controls['labelSwatchActiveTypography'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Typography', 'bricks' ) . ' (' . esc_html__( 'Active', 'bricks' ) . ')',
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.bricks-variation-swatches.bricks-swatch-label li.bricks-swatch-selected',
				],
			],
		];

		$this->controls['labelSwatchBackgroundColor'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.bricks-variation-swatches.bricks-swatch-label li',
				],
			],
		];

		$this->controls['labelSwatchActiveBackgroundColor'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Background color', 'bricks' ) . ' (' . esc_html__( 'Active', 'bricks' ) . ')',
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.bricks-variation-swatches.bricks-swatch-label li.bricks-swatch-selected',
				],
			],
		];

		$this->controls['labelSwatchBorder'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.bricks-variation-swatches.bricks-swatch-label li',
				],
			],
		];

		$this->controls['labelSwatchActiveBorder'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Border', 'bricks' ) . ' (' . esc_html__( 'Active', 'bricks' ) . ')',
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.bricks-variation-swatches.bricks-swatch-label li.bricks-swatch-selected',
				],
			],
		];

		// Image swatch specific controls
		$this->controls['imageSwatchSeparator'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Type', 'bricks' ) . ': ' . esc_html__( 'Image', 'bricks' ),
			'type'  => 'separator',
		];

		$this->controls['imageSwatchWidth'] = [
			'group'       => 'variation-swatches',
			'label'       => esc_html__( 'Width', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'width',
					'selector' => '.bricks-variation-swatches.bricks-swatch-image li img',
				],
			],
			'placeholder' => '32px',
		];

		$this->controls['imageSwatchHeight'] = [
			'group'       => 'variation-swatches',
			'label'       => esc_html__( 'Height', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'height',
					'selector' => '.bricks-variation-swatches.bricks-swatch-image li img',
				],
			],
			'placeholder' => '32px',
		];

		$this->controls['imageSwatchBorder'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.bricks-variation-swatches.bricks-swatch-image li',
				],
			],
		];

		$this->controls['imageSwatchActiveBorder'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Border', 'bricks' ) . ' (' . esc_html__( 'Active', 'bricks' ) . ')',
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.bricks-variation-swatches.bricks-swatch-image li.bricks-swatch-selected',
				],
			],
		];

		// Swatch tooltip controls

		$this->controls['swatchTooltipSep'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Tooltip', 'bricks' ),
			'type'  => 'separator',
		];

		$this->controls['swatchTooltipPadding'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'property' => 'padding',
					'selector' => '.bricks-variation-swatches li[data-balloon]::after',
				],
			],
		];

		$this->controls['swatchTooltip'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.bricks-variation-swatches li[data-balloon]::after',
				],
			],
		];

		$this->controls['swatchTooltipBackground'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'property' => 'background-color',
					'selector' => '.bricks-variation-swatches li[data-balloon]::after',
				],

				[
					'property' => 'border-top-color',
					'selector' => '.bricks-variation-swatches li[data-balloon]::before',
				],
			],
		];

		$this->controls['swatchTooltipBorder'] = [
			'group' => 'variation-swatches',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.bricks-variation-swatches li[data-balloon]::after',
				],
			],
		];

		// STOCK

		// NOTE: Stock settings not applicable in query loop (@since 1.6 @see #33v4yb9)

		$this->controls['hideStock'] = [
			'tab'   => 'content',
			'group' => 'stock',
			'label' => esc_html__( 'Hide stock', 'bricks' ),
			'type'  => 'checkbox',
			'css'   => [
				[
					'selector' => '.stock',
					'property' => 'display',
					'value'    => 'none',
				],
			],
		];

		$this->controls['stockTypography'] = [
			'tab'      => 'content',
			'group'    => 'stock',
			'label'    => esc_html__( 'Typography', 'bricks' ),
			'type'     => 'typography',
			'css'      => [
				[
					'property' => 'font',
					'selector' => '.stock',
				],
			],
			'required' => [ 'hideStock', '=', '' ]
		];

		// FORM (@since 1.12.2)
		$this->controls['formInfo'] = [
			'tab'     => 'content',
			'group'   => 'form',
			'type'    => 'info',
			'content' => esc_html__( 'Only applicable if the add to cart display as form (e.g. on single product page).', 'bricks' ),
		];

		$this->controls['formDisplay'] = [
			'tab'       => 'content',
			'group'     => 'form',
			'type'      => 'select',
			'label'     => esc_html__( 'Display', 'bricks' ),
			'type'      => 'select',
			'options'   => [
				'flex'         => 'flex',
				'inline-flex'  => 'inline-flex',
				'block'        => 'block',
				'inline-block' => 'inline-block',
				'inline'       => 'inline',
				'none'         => 'none',
			],
			'add'       => true,
			'inline'    => true,
			'lowercase' => true,
			'css'       => [
				[
					'selector' => 'form.cart:not(.variations_form), form.cart.variations_form .woocommerce-variation-add-to-cart',
					'property' => 'display',
				],
			],
		];

		$this->controls['formFlexDirection'] = [
			'tab'      => 'content',
			'group'    => 'form',
			'label'    => esc_html__( 'Direction', 'bricks' ),
			'tooltip'  => [
				'content'  => 'flex-direction',
				'position' => 'top-left',
			],
			'type'     => 'direction',
			'css'      => [
				[
					'selector' => 'form.cart:not(.variations_form), form.cart.variations_form .woocommerce-variation-add-to-cart',
					'property' => 'flex-direction',
				],
			],
			'inline'   => true,
			'rerender' => true,
			'required' => [ 'formDisplay', '=', 'flex' ],
		];

		$this->controls['formAlignSelf'] = [
			'tab'     => 'content',
			'group'   => 'form',
			'label'   => esc_html__( 'Align self', 'bricks' ),
			'type'    => 'align-items',
			'tooltip' => [
				'content'  => 'align-self',
				'position' => 'top-left',
			],
			'css'     => [
				[
					'selector' => 'form.cart:not(.variations_form), form.cart.variations_form .woocommerce-variation-add-to-cart',
					'property' => 'align-self',
				],
			],
		];

		$this->controls['formJustifyContent'] = [
			'tab'      => 'content',
			'group'    => 'form',
			'label'    => esc_html__( 'Align main axis', 'bricks' ),
			'tooltip'  => [
				'content'  => 'justify-content',
				'position' => 'top-left',
			],
			'type'     => 'justify-content',
			'css'      => [
				[
					'selector' => 'form.cart:not(.variations_form), form.cart.variations_form .woocommerce-variation-add-to-cart',
					'property' => 'justify-content',
				],
			],
			'required' => [ 'formDisplay', '=', [ 'flex', 'inline-flex' ] ],
		];

		$this->controls['formAlignItems'] = [
			'tab'      => 'content',
			'group'    => 'form',
			'label'    => esc_html__( 'Align cross axis', 'bricks' ),
			'tooltip'  => [
				'content'  => 'align-items',
				'position' => 'top-left',
			],
			'type'     => 'align-items',
			'css'      => [
				[
					'selector' => 'form.cart:not(.variations_form), form.cart.variations_form .woocommerce-variation-add-to-cart',
					'property' => 'align-items',
				],
			],
			'required' => [ 'formDisplay', '=', [ 'flex', 'inline-flex' ] ],
		];

		$this->controls['formGap'] = [
			'tab'      => 'content',
			'group'    => 'form',
			'label'    => esc_html__( 'Gap', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'selector' => 'form.cart:not(.variations_form), form.cart.variations_form .woocommerce-variation-add-to-cart',
					'property' => 'gap',
				],
			],
			'required' => [ 'formDisplay', '=', [ 'flex', 'inline-flex' ] ],
		];

		$this->controls['formFlexGrow'] = [
			'tab'         => 'content',
			'group'       => 'form',
			'label'       => esc_html__( 'Flex grow', 'bricks' ),
			'type'        => 'number',
			'tooltip'     => [
				'content'  => 'flex-grow',
				'position' => 'top-left',
			],
			'css'         => [
				[
					'selector' => 'form.cart:not(.variations_form), form.cart.variations_form .woocommerce-variation-add-to-cart',
					'property' => 'flex-grow',
				],
			],
			'min'         => 0,
			'placeholder' => 0,
		];

		$this->controls['formFlexShrink'] = [
			'tab'         => 'content',
			'group'       => 'form',
			'label'       => esc_html__( 'Flex shrink', 'bricks' ),
			'type'        => 'number',
			'tooltip'     => [
				'content'  => 'flex-shrink',
				'position' => 'top-left',
			],
			'css'         => [
				[
					'selector' => 'form.cart:not(.variations_form), form.cart.variations_form .woocommerce-variation-add-to-cart',
					'property' => 'flex-shrink',
				],
			],
			'min'         => 0,
			'placeholder' => 1,
		];

		$this->controls['formFlexBasis'] = [
			'tab'            => 'content',
			'group'          => 'form',
			'label'          => esc_html__( 'Flex basis', 'bricks' ),
			'type'           => 'text',
			'tooltip'        => [
				'content'  => 'flex-basis',
				'position' => 'top-left',
			],
			'css'            => [
				[
					'selector' => 'form.cart:not(.variations_form), form.cart.variations_form .woocommerce-variation-add-to-cart',
					'property' => 'flex-basis',
				],
			],
			'inline'         => true,
			'hasDynamicData' => false,
			'hasVariables'   => true,
			'placeholder'    => 'auto',
		];

		// QUANTITY

		// NOTE: Variation settings not applicable in query loop (@since 1.6 @see #33v4yb9)

		$this->controls['quantityWidth'] = [
			'tab'   => 'content',
			'group' => 'quantity',
			'type'  => 'number',
			'units' => true,
			'label' => esc_html__( 'Width', 'bricks' ),
			'css'   => [
				[
					'selector' => '.cart .quantity',
					'property' => 'width',
				],
			],
		];

		$this->controls['quantityBackground'] = [
			'tab'   => 'content',
			'group' => 'quantity',
			'type'  => 'color',
			'label' => esc_html__( 'Background', 'bricks' ),
			'css'   => [
				[
					'selector' => '.cart .quantity',
					'property' => 'background-color',
				],
			],
		];

		$this->controls['quantityBorder'] = [
			'tab'   => 'content',
			'group' => 'quantity',
			'type'  => 'border',
			'label' => esc_html__( 'Border', 'bricks' ),
			'css'   => [
				[
					'selector' => '.qty',
					'property' => 'border',
				],
				[
					'selector' => '.minus',
					'property' => 'border',
				],
				[
					'selector' => '.plus',
					'property' => 'border',
				],
			],
		];

		// BUTTON

		$this->controls['buttonText'] = [
			'tab'         => 'content',
			'group'       => 'button',
			'type'        => 'text',
			'inline'      => true,
			'label'       => esc_html__( 'Simple product', 'bricks' ),
			'placeholder' => esc_html__( 'Add to cart', 'bricks' ),
		];

		$this->controls['variableText'] = [
			'tab'         => 'content',
			'group'       => 'button',
			'type'        => 'text',
			'inline'      => true,
			'label'       => esc_html__( 'Variable product', 'bricks' ),
			'placeholder' => esc_html__( 'Select options', 'bricks' ),
		];

		$this->controls['groupedText'] = [
			'tab'         => 'content',
			'group'       => 'button',
			'type'        => 'text',
			'inline'      => true,
			'label'       => esc_html__( 'Grouped product', 'bricks' ),
			'placeholder' => esc_html__( 'View products', 'bricks' ),
		];

		$this->controls['externalText'] = [
			'tab'         => 'content',
			'group'       => 'button',
			'type'        => 'text',
			'inline'      => true,
			'label'       => esc_html__( 'External product', 'bricks' ),
			'placeholder' => esc_html__( 'Buy product', 'bricks' ),
		];

		$this->controls['buttonMargin'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Margin', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
					'property' => 'margin',
				],
			],
		];

		$this->controls['buttonPadding'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Padding', 'bricks' ),
			'type'  => 'spacing',
			'css'   => [
				[
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
					'property' => 'padding',
				],
			],
		];

		$this->controls['buttonWidth'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Width', 'bricks' ),
			'type'  => 'number',
			'units' => true,
			'css'   => [
				[
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
					'property' => 'min-width',
				],
			],
		];

		$this->controls['buttonBackgroundColor'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Background color', 'bricks' ),
			'type'  => 'color',
			'css'   => [
				[
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
					'property' => 'background-color',
				],
			],
		];

		$this->controls['buttonBorder'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Border', 'bricks' ),
			'type'  => 'border',
			'css'   => [
				[
					'property' => 'border',
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
				],
			],
		];

		$this->controls['buttonTypography'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'selector' => '.cart .single_add_to_cart_button, a.button[data-product_id]',
					'property' => 'font',
				],
			],
		];

		// Button icon

		$this->controls['icon'] = [
			'tab'      => 'content',
			'group'    => 'button',
			'label'    => esc_html__( 'Icon', 'bricks' ),
			'type'     => 'icon',
			'rerender' => true,
		];

		$this->controls['iconTypography'] = [
			'tab'   => 'content',
			'group' => 'button',
			'label' => esc_html__( 'Icon typography', 'bricks' ),
			'type'  => 'typography',
			'css'   => [
				[
					'property' => 'font',
					'selector' => '.icon',
				],
			],
		];

		$this->controls['iconOnly'] = [
			'tab'         => 'content',
			'group'       => 'button',
			'label'       => esc_html__( 'Icon only', 'bricks' ),
			'type'        => 'checkbox',
			'inline'      => true,
			'placeholder' => esc_html__( 'Yes', 'bricks' ),
			'required'    => [ 'icon', '!=', '' ],
		];

		$this->controls['iconPosition'] = [
			'tab'         => 'content',
			'group'       => 'button',
			'label'       => esc_html__( 'Icon position', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['iconPosition'],
			'inline'      => true,
			'placeholder' => esc_html__( 'Left', 'bricks' ),
			'required'    => [
				[ 'icon', '!=', '' ],
				[ 'iconOnly', '=', '' ],
			],
		];

		// AJAX add to cart
		if ( Woocommerce::enabled_ajax_add_to_cart() ) {
			$this->controls['ajaxIfno'] = [
				'tab'     => 'content',
				'group'   => 'ajax',
				'type'    => 'info',
				'content' => sprintf(
					// translators: %s is a link to the global AJAX add to cart settings
					esc_html__( 'Set globally under %s', 'bricks' ),
					'<a href="' . Helpers::settings_url( '#tab-woocommerce' ) . '" target="_blank">Bricks > ' . esc_html__( 'Settings', 'bricks' ) . ' > WooCommerce > ' . esc_html__( 'AJAX add to cart', 'bricks' ) . '</a>.',
				),
			];

			$this->controls['addingSeparator'] = [
				'tab'   => 'content',
				'group' => 'ajax',
				'label' => esc_html__( 'Adding', 'bricks' ),
				'type'  => 'separator',
			];

			$this->controls['addingButtonText'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'type'        => 'text',
				'label'       => esc_html__( 'Button text', 'bricks' ),
				'inline'      => true,
				'placeholder' => Database::$global_settings['woocommerceAjaxAddingText'] ?? esc_html__( 'Adding', 'bricks' ),
			];

			$this->controls['addingButtonIcon'] = [
				'tab'      => 'content',
				'group'    => 'ajax',
				'label'    => esc_html__( 'Icon', 'bricks' ),
				'type'     => 'icon',
				'rerender' => true,
			];

			$this->controls['addingButtonIconOnly'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'label'       => esc_html__( 'Icon only', 'bricks' ),
				'type'        => 'checkbox',
				'inline'      => true,
				'placeholder' => esc_html__( 'Yes', 'bricks' ),
				'required'    => [ 'addingButtonIcon', '!=', '' ],
			];

			$this->controls['addingButtonIconPosition'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'label'       => esc_html__( 'Icon position', 'bricks' ),
				'type'        => 'select',
				'options'     => $this->control_options['iconPosition'],
				'inline'      => true,
				'placeholder' => esc_html__( 'Left', 'bricks' ),
				'required'    => [
					[ 'addingButtonIcon', '!=', '' ],
					[ 'addingButtonIconOnly', '=', '' ]
				],
			];

			$this->controls['addingButtonIconSpinning'] = [
				'tab'      => 'content',
				'group'    => 'ajax',
				'label'    => esc_html__( 'Icon spinning', 'bricks' ),
				'type'     => 'checkbox',
				'required' => [ 'addingButtonIcon', '!=', '' ],
			];

			// Added

			$this->controls['addedSeparator'] = [
				'tab'   => 'content',
				'group' => 'ajax',
				'label' => esc_html__( 'Added', 'bricks' ),
				'type'  => 'separator',
			];

			$this->controls['addedButtonText'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'type'        => 'text',
				'label'       => esc_html__( 'Button text', 'bricks' ),
				'inline'      => true,
				'placeholder' => Database::$global_settings['woocommerceAjaxAddedText'] ?? esc_html__( 'Added', 'bricks' ),
			];

			$this->controls['resetTextAfter'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'type'        => 'number',
				'label'       => esc_html__( 'Reset text after .. seconds', 'bricks' ),
				'inline'      => true,
				'placeholder' => 3,
			];

			$this->controls['addedButtonIcon'] = [
				'tab'      => 'content',
				'group'    => 'ajax',
				'label'    => esc_html__( 'Icon', 'bricks' ),
				'type'     => 'icon',
				'rerender' => true,
			];

			$this->controls['addedButtonIconOnly'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'label'       => esc_html__( 'Icon only', 'bricks' ),
				'type'        => 'checkbox',
				'inline'      => true,
				'placeholder' => esc_html__( 'Yes', 'bricks' ),
				'required'    => [ 'addedButtonIcon', '!=', '' ],
			];

			$this->controls['addedButtonIconPosition'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'label'       => esc_html__( 'Icon position', 'bricks' ),
				'type'        => 'select',
				'options'     => $this->control_options['iconPosition'],
				'inline'      => true,
				'placeholder' => esc_html__( 'Left', 'bricks' ),
				'required'    => [
					[ 'addedButtonIcon', '!=', '' ],
					[ 'addedButtonIconOnly', '=', '' ],
				],
			];

			// Show notice after added (@since 1.9)
			$this->controls['showNotice'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'label'       => esc_html__( 'Show notice', 'bricks' ),
				'type'        => 'select',
				'inline'      => true,
				'placeholder' => isset( Database::$global_settings['woocommerceAjaxShowNotice'] ) ? esc_html__( 'Yes', 'bricks' ) : esc_html__( 'No', 'bricks' ),
				'options'     => [
					'no'  => esc_html__( 'No', 'bricks' ),
					'yes' => esc_html__( 'Yes', 'bricks' ),
				],
			];

			// Scroll to notice after added (@since 1.9)
			$this->controls['scrollToNotice'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'label'       => esc_html__( 'Scroll to notice', 'bricks' ),
				'type'        => 'select',
				'inline'      => true,
				'placeholder' => isset( Database::$global_settings['woocommerceAjaxScrollToNotice'] ) ? esc_html__( 'Yes', 'bricks' ) : esc_html__( 'No', 'bricks' ),
				'options'     => [
					'no'  => esc_html__( 'No', 'bricks' ),
					'yes' => esc_html__( 'Yes', 'bricks' ),
				],
				'required'    => [ 'showNotice', '=', 'yes' ],
			];

			// Hide "View cart" button after added (@since 1.9)
			$this->controls['hideViewCart'] = [
				'tab'         => 'content',
				'group'       => 'ajax',
				'label'       => esc_html__( 'Hide "View cart" button', 'bricks' ),
				'type'        => 'select',
				'inline'      => true,
				'placeholder' => isset( Database::$global_settings['woocommerceAjaxHideViewCart'] ) ? esc_html__( 'Yes', 'bricks' ) : esc_html__( 'No', 'bricks' ),
				'options'     => [
					'inline-flex' => esc_html__( 'No', 'bricks' ), // Default .add_to_cart button display is inline-flex
					'none'        => esc_html__( 'Yes', 'bricks' ),
				],
				'css'         => [
					[
						'selector' => '.added_to_cart.wc-forward',
						'property' => 'display',
					],
				],
			];
		}
	}

	public function render() {
		$settings = $this->settings;

		global $product;

		// Add filter for variation select field if swatches are enabled
		if ( \Bricks\Database::get_setting( 'woocommerceUseVariationSwatches' ) ) {
			add_filter( 'woocommerce_dropdown_variation_attribute_options_html', [ $this, 'maybe_convert_to_swatches' ], 100, 2 );
		}

		$product = wc_get_product( $this->post_id );

		if ( empty( $product ) ) {
			return $this->render_element_placeholder(
				[
					'title'       => esc_html__( 'For better preview select content to show.', 'bricks' ),
					'description' => esc_html__( 'Go to: Settings > Template Settings > Populate Content', 'bricks' ),
				]
			);
		}

		$this->maybe_set_ajax_add_to_cart_data_attribute();

		add_filter( 'woocommerce_product_single_add_to_cart_text', [ $this, 'add_to_cart_text' ], 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', [ $this, 'add_to_cart_text' ], 10, 2 );
		add_filter( 'esc_html', [ $this, 'avoid_esc_html' ], 10, 2 );

		// Start output buffering (@since 2.0)
		ob_start();

		// In AJAX Popup, is_looping() is always true, but we want to show the single add to cart button (@since 1.10.2)
		$is_single_product_in_ajax_popup = Api::is_current_endpoint( 'load_popup_content' ) && (int) get_queried_object_id() === (int) $this->post_id;

		if ( Query::is_looping() && ! $is_single_product_in_ajax_popup ) {
			woocommerce_template_loop_add_to_cart();
		} else {
			woocommerce_template_single_add_to_cart();
		}

		// Get the buffered content (@since 2.0)
		$content = ob_get_clean();

		$output = true;

		if ( isset( $settings['hideStock'] ) && ! $product->is_in_stock() && ! $this->should_render_element( $content ) ) {
			$output = false;
		}

		// Only output the div if there's actual content
		if ( $output ) {
			echo "<div {$this->render_attributes( '_root' )}>";
			echo $content;
			echo '</div>';
		}else {

			// Output the placeholder if the product is out of stock and "Hide stock" is enabled
			return $this->render_element_placeholder(
				[
					'title'       => esc_html__( 'Product is out of stock.', 'bricks' ),
					'description' => esc_html__( 'Go to: WooCommerce > Products > Inventory', 'bricks' ),
				]
			);
		}

		remove_filter( 'woocommerce_product_single_add_to_cart_text', [ $this, 'add_to_cart_text' ], 10, 2 );
		remove_filter( 'woocommerce_product_add_to_cart_text', [ $this, 'add_to_cart_text' ], 10, 2 );
		remove_filter( 'esc_html', [ $this, 'avoid_esc_html' ], 10, 2 );
	}

	/**
	 * Add custom text and/or icon to the button
	 *
	 * @param string     $text
	 * @param WC_Product $product
	 *
	 * @since 1.6
	 */
	public function add_to_cart_text( $text, $product ) {
		$settings = $this->settings;

		// Support changing the text based on product type (simple, variable, grouped, external) (@since 1.9)
		// NOTE TODO: Sometime product not purchasable has different text... worth to add more text fields?
		switch ( $product->get_type() ) {
			case 'variable':
				$text = ! empty( $settings['variableText'] ) ? $settings['variableText'] : $text;
				break;
			case 'grouped':
				$text = ! empty( $settings['groupedText'] ) ? $settings['groupedText'] : $text;
				break;
			case 'external':
				$text = ! empty( $settings['externalText'] ) ? $settings['externalText'] : $text;
				break;
			case 'simple':
				$text = ! empty( $settings['buttonText'] ) ? $settings['buttonText'] : $text;
				break;
		}

		$icon          = ! empty( $settings['icon'] ) ? self::render_icon( $settings['icon'], [ 'icon' ] ) : false;
		$icon_position = isset( $settings['iconPosition'] ) ? $settings['iconPosition'] : 'left';
		$icon_only     = isset( $settings['iconOnly'] );

		// Build HTML
		$output = '';

		if ( $icon_only && $icon ) {
			// Icon only (@since 1.12.2)
			$output = $icon;
		} else {
			if ( $icon && $icon_position === 'left' ) {
				$output .= $icon;
			}

			$output .= "<span>$text</span>";

			if ( $icon && $icon_position === 'right' ) {
				$output .= $icon;
			}
		}

		return $output;
	}

	/**
	 * TODO: Needs description
	 *
	 * @since 1.6
	 */
	public function avoid_esc_html( $safe_text, $text ) {
		return $text;
	}

	/**
	 * Set AJAX add to cart data attribute: data-bricks-ajax-add-to-cart
	 *
	 * @since 1.6.1
	 */
	public function maybe_set_ajax_add_to_cart_data_attribute() {
		// Set data attribute if ajax add to cart is enabled
		if ( ! Woocommerce::enabled_ajax_add_to_cart() ) {
			return;
		}

		$settings = $this->settings;

		$default_icon          = isset( $settings['icon'] ) ? self::render_icon( $settings['icon'], [ 'icon' ] ) : false;
		$default_icon_position = isset( $settings['iconPosition'] ) ? $settings['iconPosition'] : 'left';

		$states = [ 'adding', 'added' ];

		$ajax_add_to_cart_data = [];

		foreach ( $states as $state ) {
			$default_add_to_cart_text = $state === 'adding' ? WooCommerce::global_ajax_adding_text() : WooCommerce::global_ajax_added_text();
			$state_text               = isset( $settings[ $state . 'ButtonText' ] ) ? $settings[ $state . 'ButtonText' ] : $default_add_to_cart_text;
			$icon_classes             = isset( $settings[ $state . 'ButtonIconSpinning' ] ) ? [ 'icon', 'spinning' ] : [ 'icon' ];
			$icon                     = isset( $settings[ $state . 'ButtonIcon' ] ) ? self::render_icon( $settings[ $state . 'ButtonIcon' ], $icon_classes ) : $default_icon;
			$icon_position            = isset( $settings[ $state . 'ButtonIconPosition' ] ) ? $settings[ $state . 'ButtonIconPosition' ] : $default_icon_position;
			$icon_only                = isset( $settings[ $state . 'ButtonIconOnly' ] );

			// Build HTML
			$output = '';

			if ( $icon_only && $icon ) {
				// Icon only (@since 1.12.2)
				$output = $icon;
			} else {
				if ( $icon && $icon_position === 'left' ) {
					$output .= $icon;
				}

				$output .= "<span>$state_text</span>";

				if ( $icon && $icon_position === 'right' ) {
					$output .= $icon;
				}
			}

			$ajax_add_to_cart_data[ $state . 'HTML' ] = $output;
		}

		$show_notice      = Woocommerce::global_ajax_show_notice();
		$scroll_to_notice = Woocommerce::global_ajax_scroll_to_notice();
		$reset_after      = Woocommerce::global_ajax_reset_text_after();

		if ( isset( $settings['showNotice'] ) ) {
			// Override global setting if set
			$show_notice = $settings['showNotice'];
		}

		if ( isset( $settings['scrollToNotice'] ) ) {
			// Override global setting if set
			$scroll_to_notice = $settings['scrollToNotice'];
		}

		if ( isset( $settings['resetTextAfter'] ) ) {
			// Override global setting if set
			$reset_after = absint( $settings['resetTextAfter'] );
		}

		$ajax_add_to_cart_data['showNotice']     = $show_notice;
		$ajax_add_to_cart_data['scrollToNotice'] = $scroll_to_notice;
		$ajax_add_to_cart_data['resetTextAfter'] = max( $reset_after, 1 );

		$this->set_attribute( '_root', 'data-bricks-ajax-add-to-cart', wp_json_encode( $ajax_add_to_cart_data ) );
	}

	/**
	 * Convert dropdown to swatches if applicable
	 *
	 * @since 2.0
	 *
	 * @param string $html
	 * @param array  $args
	 * @return string
	 */
	public function maybe_convert_to_swatches( $html, $args ) {
		// Only convert if the HTML contains a select element (avoid recursion)
		if ( strpos( $html, '<select' ) === false ) {
			return $html;
		}

		$swatches_html = $this->render_variation_swatches( $args );
		// Keep the original select but hide it with inline style
		return $swatches_html ? $swatches_html . '<div style="display:none">' . $html . '</div>' : $html;
	}

	/**
	 * Get the swatch type for an attribute
	 *
	 * @since 2.0
	 *
	 * @param string $taxonomy The attribute taxonomy.
	 * @return string The swatch type or empty string if none
	 */
	public function get_attribute_swatch_type( $taxonomy ) {
		$attribute_id = wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) );
		return $attribute_id ? get_term_meta( $attribute_id, 'bricks_swatch_type', true ) : '';
	}

	/**
	 * Render variation swatches for attribute select field
	 *
	 * @since 2.0
	 *
	 * @param array $args The field arguments.
	 * @return string HTML for the field.
	 */
	public function render_variation_swatches( $args ) {
		$args = wp_parse_args(
			$args,
			[
				'options'   => [],
				'attribute' => '',
				'product'   => false,
				'selected'  => false,
				'name'      => '',
				'id'        => '',
				'class'     => '',
			]
		);

		// Get swatch type
		$swatch_type = $this->get_attribute_swatch_type( $args['attribute'] );

		// Return default WooCommerce dropdown if swatches are disabled or no swatch type set
		if ( ! \Bricks\Database::get_setting( 'woocommerceUseVariationSwatches' ) || ! $swatch_type ) {
			return false;
		}

		$options   = $args['options'];
		$attribute = $args['attribute'];
		$name      = $args['name'] ? $args['name'] : 'attribute_' . sanitize_title( $attribute );
		$id        = $args['id'] ? $args['id'] : sanitize_title( $attribute );

		if ( empty( $options ) || ! $attribute ) {
			return false;
		}

		$output = '<ul class="bricks-variation-swatches bricks-swatch-' . esc_attr( $swatch_type ) . '">';

		foreach ( $options as $value ) {
			$term = get_term_by( 'slug', $value, $attribute );
			if ( ! $term ) {
				continue;
			}

			$selected = sanitize_title( $args['selected'] ) === $args['selected'] ? selected( $args['selected'], $value, false ) : selected( sanitize_title( $args['selected'] ), sanitize_title( $value ), false );

			// Render swatch based on type
			switch ( $swatch_type ) {
				case 'color':
					$output .= $this->render_color_swatch( $term->term_id, $name, $value, $selected );
					break;
				case 'label':
					$output .= $this->render_label_swatch( $term->term_id, $name, $value, $selected );
					break;
				case 'image':
					$output .= $this->render_image_swatch( $term->term_id, $name, $value, $selected );
					break;
			}
		}

		// Hidden input to store selected value
		$output .= '<input type="hidden" ' .
			'name="' . esc_attr( $name ) . '" ' .
			'class="variation-select" ' .
			'value="' . esc_attr( $args['selected'] ) . '" ' .
			'data-attribute_name="' . esc_attr( wc_variation_attribute_name( $attribute ) ) . '">';

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Render color swatch for attribute
	 *
	 * @param string $term_id The term ID.
	 * @param string $name The attribute name.
	 * @param string $value The term slug.
	 * @param bool   $selected Whether this term is selected.
	 * @return string HTML for the color swatch.
	 *
	 * @since 2.0
	 */
	public function render_color_swatch( $term_id, $name, $value, $selected ) {
		$color = get_term_meta( $term_id, 'bricks_swatch_color_value', true );

		// Check if color is empty or 'none'
		if ( empty( $color ) || $color === 'none' ) {
			// Get attribute ID - remove both 'attribute_' and 'pa_' prefixes
			$taxonomy_name = str_replace( [ 'attribute_pa_', 'pa_' ], '', $name );
			$attribute_id  = \wc_attribute_taxonomy_id_by_name( $taxonomy_name );

			if ( $attribute_id ) {
				// Get default color from the attribute taxonomy
				$default_color = get_term_meta( $attribute_id, 'bricks_swatch_default_color', true );
				$color         = $default_color && sanitize_hex_color( $default_color ) ? $default_color : '#ffffff';
			} else {
				$color = '#ffffff';
			}
		}

		$term  = get_term( $term_id );
		$label = $term ? $term->name : $value;

		$selected_class = $selected ? ' bricks-swatch-selected' : '';
		$value_attr     = esc_attr( $value );
		$label_attr     = esc_attr( $label );
		$color_attr     = esc_attr( $color );

		return '<li class="' . $selected_class . '" ' .
			'data-value="' . $value_attr . '" ' .
			'data-balloon="' . $label_attr . '">' .
			'<div style="background-color: ' . $color_attr . '"></div>' .
		'</li>';
	}

	/**
	 * Render label swatch for attribute
	 *
	 * @param string $term_id The term ID.
	 * @param string $name The attribute name.
	 * @param string $value The term slug.
	 * @param bool   $selected Whether this term is selected.
	 * @return string HTML for the label swatch.
	 *
	 * @since 2.0
	 */
	public function render_label_swatch( $term_id, $name, $value, $selected ) {
		$label_text = get_term_meta( $term_id, 'bricks_swatch_label_value', true );

		// Get attribute settings
		$attribute_id = wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $name ) );

		if ( ! $label_text ) {
			// Use fallback label or default to term name
			$fallback_label = get_term_meta( $attribute_id, 'bricks_swatch_default_label', true );
			$term           = get_term( $term_id );
			$label_text     = $fallback_label ? $fallback_label : ( $term ? $term->name : $value );
		}

		$selected_class = $selected ? ' bricks-swatch-selected' : '';
		$value_attr     = esc_attr( $value );
		$label_attr     = esc_attr( $label_text );

		return '<li class="' . $selected_class . '" ' .
			'data-value="' . $value_attr . '">' . $label_attr . '</li>';
	}

	/**
	 * Render image swatch for attribute
	 *
	 * @param string $term_id The term ID.
	 * @param string $name The attribute name.
	 * @param string $value The term slug.
	 * @param bool   $selected Whether this term is selected.
	 * @return string HTML for the image swatch.
	 *
	 * @since 2.0
	 */
	public function render_image_swatch( $term_id, $name, $value, $selected ) {
		$image_id     = get_term_meta( $term_id, 'bricks_swatch_image_value', true );
		$image_origin = 'term'; // default origin

		// Get attribute settings
		$taxonomy           = str_replace( 'attribute_', '', $name );
		$taxonomy_no_prefix = str_replace( 'pa_', '', $taxonomy );

		// Get attribute data
		$attribute    = wc_get_attribute( wc_attribute_taxonomy_id_by_name( $taxonomy_no_prefix ) );
		$attribute_id = $attribute ? $attribute->id : 0;

		if ( ! $image_id && $attribute_id ) {
			// Check if "Use variation image" fallback is enabled for this attribute
			$use_variation_image = get_term_meta( $attribute_id, 'bricks_swatch_use_variation_image', true );

			if ( $use_variation_image ) {
				// Attempt to fetch the image from the first matching product variation
				global $product;
				if ( $product && is_a( $product, 'WC_Product_Variable' ) ) {
					$variations = $product->get_children();
					$attr_key   = 'pa_' . $taxonomy_no_prefix;

					foreach ( $variations as $variation_id ) {
						$variation      = wc_get_product( $variation_id );
						$variation_attr = $variation ? $variation->get_attribute( $attr_key ) : '';

						// Compare using sanitized slugs to ensure match
						if ( sanitize_title( $variation_attr ) === $value ) {
							$variation_image_id = $variation->get_image_id();
							if ( $variation_image_id ) {
								$image_id     = $variation_image_id;
								$image_origin = 'variation';
								break;
							}
						}
					}
				}
			}

			// If still no image, use default fallback image from attribute settings
			if ( ! $image_id ) {
				$image_id     = get_term_meta( $attribute_id, 'bricks_swatch_default_image', true );
				$image_origin = $image_id ? 'default' : $image_origin;
			}
		}

		// Get image data
		$image = $image_id ? wp_get_attachment_image_src( $image_id, 'thumbnail' ) : false;

		// If no image (either term-specific or fallback), use placeholder
		if ( ! $image ) {
			return $this->render_placeholder_image( $term_id, $value, $selected );
		}

		$term  = get_term( $term_id );
		$label = $term ? $term->name : $value;

		$selected_class = $selected ? ' bricks-swatch-selected' : '';
		$value_attr     = esc_attr( $value );
		$label_attr     = esc_attr( $label );
		$image_url      = esc_url( $image[0] );

		return '<li class="' . $selected_class . '" ' .
			'data-value="' . $value_attr . '" ' .
			'data-image-origin="' . esc_attr( $image_origin ) . '" ' .
			'data-balloon="' . $label_attr . '">' .
			'<img src="' . $image_url . '" alt="' . $label_attr . '">' .
			'</li>';
	}

	protected function render_placeholder_image( $term_id, $value, $selected ) {
		$term            = get_term( $term_id );
		$label           = $term ? $term->name : $value;
		$placeholder_url = \Bricks\Builder::get_template_placeholder_image();

		$selected_class = $selected ? ' bricks-swatch-selected' : '';

		return '<li class="' . $selected_class . '" ' .
			'data-value="' . esc_attr( $value ) . '" ' .
			'data-balloon="' . esc_attr( $label ) . '">' .
			'<img src="' . esc_url( $placeholder_url ) . '" alt="' . esc_attr( $label ) . '">' .
			'</li>';
	}

	/**
	 * Check if content contains only an out-of-stock message.
	 *
	 * @param string $content The HTML content to check.
	 * @return boolean True if content contains only an out-of-stock message, false otherwise.
	 *
	 * @since 2.0
	 */
	private function should_render_element( $content ) {
		// If content is empty, it's not an out-of-stock message
		$content = trim( $content );
		if ( empty( $content ) ) {
			return false;
		}

		// Create a DOMDocument
		$dom = new \DOMDocument();

		// Suppress errors from potentially malformed HTML
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $content );
		libxml_clear_errors();

		// Find all elements with class containing "out-of-stock"
		$xpath                 = new \DOMXPath( $dom );
		$out_of_stock_elements = $xpath->query( '//*[contains(@class, "out-of-stock")]' );

		// If we found exactly one out-of-stock element
		if ( $out_of_stock_elements->length === 1 ) {
			// Check if this is the only significant element by comparing cleaned HTML
			$element_html    = $dom->saveHTML( $out_of_stock_elements->item( 0 ) );
			$cleaned_content = preg_replace( '/^\s+|\s+$/s', '', $content );
			$cleaned_element = preg_replace( '/^\s+|\s+$/s', '', $element_html );

			// If the cleaned content is equal to the out-of-stock element
			if ( $cleaned_content === $cleaned_element ) {
					return false;
			}
		}

		return true;
	}
}
