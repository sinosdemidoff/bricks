<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Image extends Element {
	public $block             = 'core/image';
	public $category          = 'basic';
	public $name              = 'image';
	public $icon              = 'ti-image';
	public $tag               = 'figure';
	public $custom_attributes = false;
	public $wp_img_data       = []; // Image data for wp_get_attachment_image_src filter (@since 2.0.2)

	public function get_label() {
		return esc_html__( 'Image', 'bricks' );
	}

	/**
	 * Enqueue PhotoSwipe lightbox script file as needed (frontend only)
	 *
	 * @since 1.3.4
	 */
	public function enqueue_scripts() {
		if ( isset( $this->settings['link'] ) && $this->settings['link'] === 'lightbox' ) {
			wp_enqueue_script( 'bricks-photoswipe' );
			wp_enqueue_style( 'bricks-photoswipe' );

			// Lightbox caption (@since 1.10)
			if ( isset( $this->settings['lightboxCaption'] ) ) {
				wp_enqueue_script( 'bricks-photoswipe-caption' );
			}
		}
	}

	public function set_controls() {
		// Get breakpoints for "Sources" control
		$breakpoints        = Breakpoints::$breakpoints;
		$breakpoint_options = [];

		foreach ( $breakpoints as $index => $breakpoint ) {
			$breakpoint_options[ $breakpoint['key'] ] = isset( $breakpoint['base'] ) ? $breakpoint['label'] . ' (' . esc_html__( 'Base breakpoint', 'bricks' ) . ')' : $breakpoint['label'];
		}

		if ( ! Breakpoints::$is_mobile_first ) {
			$breakpoint_options = array_reverse( $breakpoint_options );
		}

		// Underscorce prefix to prevent conflict with user-created custom breakpoint
		$breakpoint_options['_custom'] = esc_html__( 'Custom', 'bricks' ) . ' (' . esc_html__( 'Media query', 'bricks' ) . ')';

		$img_css_selector = '&:not(.tag), img';

		// Apply CSS filters only to img tag
		$this->controls['_cssFilters']['css'] = [
			[
				'selector' => $img_css_selector,
				'property' => 'filter',
			],
		];

		$this->controls['_typography']['css'][0]['selector'] = 'figcaption';

		// IMAGE

		$this->controls['image'] = [
			'type' => 'image',
		];

		$this->controls['tag'] = [
			'label'       => esc_html__( 'HTML tag', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'figure' => 'figure',
				// 'picture' => 'picture', // NOTE: Removed as 'picture' is set when using "Sources" and there's no point to manually set it (@since 1.12)
				'div'    => 'div',
				'custom' => esc_html__( 'Custom', 'bricks' ),
			],
			'lowercase'   => true,
			'inline'      => true,
			'placeholder' => '-',
			'required'    => [ 'sources', '=', '' ],
		];

		$this->controls['customTag'] = [
			'label'       => esc_html__( 'Custom tag', 'bricks' ),
			'info'        => esc_html__( 'Without attributes', 'bricks' ),
			'type'        => 'text',
			'inline'      => true,
			'dd'          => false,
			'placeholder' => 'div',
			'required'    => [
				[ 'tag', '=', 'custom' ],
				[ 'sources', '=', '' ],
			]
		];

		$this->controls['sources'] = [
			'label'         => esc_html__( 'Sources', 'bricks' ),
			'type'          => 'repeater',
			'titleProperty' => 'breakpoint',
			'description'   => '<a href="https://developer.mozilla.org/en-US/docs/Web/HTML/Element/picture" target="_blank">' . esc_html__( 'Show different images per breakpoint.', 'bricks' ) . '</a>',
			'placeholder'   => esc_html__( 'Source', 'bricks' ),
			'fields'        => [
				'breakpoint' => [
					'label'       => esc_html__( 'Breakpoint', 'bricks' ),
					'type'        => 'select',
					'options'     => $breakpoint_options,
					'placeholder' => esc_html__( 'Select', 'bricks' ),
				],
				'media'      => [
					'label'       => esc_html__( 'Media query', 'bricks' ),
					'type'        => 'text',
					'placeholder' => '(max-width: 600px)',
					'required'    => [ 'breakpoint', '=', '_custom' ],
				],
				'image'      => [
					'label'    => esc_html__( 'Image', 'bricks' ),
					'type'     => 'image',
					'required' => [ 'breakpoint', '!=', '' ],
				],
			],
		];

		$this->controls['sourcesInfo'] = [
			'type'     => 'info',
			'content'  => esc_html__( 'Order matters. Start at smallest breakpoint. If using mobile-first start at largest breakpoint.', 'bricks' ) . ' ' . esc_html__( 'Set source image at base breakpoint to use main image as fallback image.', 'bricks' ),
			'required' => [ 'sources', '!=', '' ],
		];

		// Delete '_aspectRatio' control to add it here before the '_objectFit' (@since 1.9)
		if ( isset( $this->controls['_aspectRatio'] ) ) {
			unset( $this->controls['_aspectRatio'] );

			$this->controls['_aspectRatio'] = [
				'label'       => esc_html__( 'Aspect ratio', 'bricks' ),
				'type'        => 'text',
				'inline'      => true,
				'dd'          => false,
				'placeholder' => '',
				'css'         => [
					[
						'selector' => $img_css_selector,
						'property' => 'aspect-ratio',
					],
				],
			];
		}

		$this->controls['_objectFit'] = [
			'label'   => esc_html__( 'Object fit', 'bricks' ),
			'type'    => 'select',
			'inline'  => true,
			'options' => $this->control_options['objectFit'],
			'css'     => [
				[
					'selector' => $img_css_selector,
					'property' => 'object-fit',
				],
			],
		];

		$this->controls['_objectPosition'] = [
			'label'  => esc_html__( 'Object position', 'bricks' ),
			'type'   => 'text',
			'inline' => true,
			'dd'     => false,
			'css'    => [
				[
					'selector' => $img_css_selector,
					'property' => 'object-position',
				],
			],
		];

		// Alt text

		$this->controls['altText'] = [
			'label'    => esc_html__( 'Custom alt text', 'bricks' ),
			'type'     => 'text',
			'inline'   => true,
			'rerender' => false,
			'required' => [ 'image', '!=', '' ],
		];

		// Caption
		$caption_options = [
			'none'       => esc_html__( 'No caption', 'bricks' ),
			'attachment' => esc_html__( 'Attachment', 'bricks' ),
			'custom'     => esc_html__( 'Custom', 'bricks' ),
		];

		// Get caption placeholder from theme option value
		$show_caption = ! empty( $this->theme_styles['caption'] ) ? $this->theme_styles['caption'] : 'attachment';

		$this->controls['caption'] = [
			'label'       => esc_html__( 'Caption type', 'bricks' ),
			'type'        => 'select',
			'options'     => $caption_options,
			'inline'      => true,
			'placeholder' => ! empty( $caption_options[ $show_caption ] ) ? $caption_options[ $show_caption ] : esc_html__( 'Attachment', 'bricks' ),
		];

		$this->controls['captionCustom'] = [
			'label'       => esc_html__( 'Custom caption', 'bricks' ),
			'type'        => 'text',
			'placeholder' => esc_html__( 'Here goes your caption ...', 'bricks' ),
			'required'    => [ 'caption', '=', 'custom' ],
		];

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

		$this->controls['showTitle'] = [
			'label'    => esc_html__( 'Show title', 'bricks' ),
			'type'     => 'checkbox',
			'inline'   => true,
			'required' => [ 'image', '!=', '' ],
		];

		$this->controls['stretch'] = [
			'label' => esc_html__( 'Stretch', 'bricks' ),
			'type'  => 'checkbox',
			'css'   => [
				[
					'property' => 'width',
					'value'    => '100%',
				],
			],
		];

		$this->controls['popupOverlay'] = [
			// 'deprecated' => true, // Redundant: Use _gradient settings instead
			'label'    => esc_html__( 'Image Overlay', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'background-color',
					'selector' => '&.overlay::before',
				],
			],
			'rerender' => true,
		];

		// Link To
		$this->controls['linkToSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Link To', 'bricks' ),
		];

		$this->controls['link'] = [
			'type'        => 'select',
			'options'     => [
				'lightbox'   => esc_html__( 'Lightbox', 'bricks' ),
				'attachment' => esc_html__( 'Attachment Page', 'bricks' ),
				'media'      => esc_html__( 'Media File', 'bricks' ),
				'url'        => esc_html__( 'Other (URL)', 'bricks' ),
			],
			'rerender'    => true,
			'placeholder' => esc_html__( 'None', 'bricks' ),
		];

		// Lightbox separator control
		$this->controls['lightboxSep'] = [
			'label'    => esc_html__( 'Lightbox', 'bricks' ),
			'type'     => 'separator',
			'required' => [ 'link', '=', 'lightbox' ],
		];

		$this->controls['lightboxImageSize'] = [
			'label'       => esc_html__( 'Image size', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['imageSizes'],
			'placeholder' => esc_html__( 'Full', 'bricks' ),
			'required'    => [
				[ 'link', '=', 'lightbox' ],
				[ 'image.size', '!=', '' ] // Has size means its in the media library or dynamic data
			],
		];

		/**
		 * Custom lightbox image size (when image set via custom URL, etc.)
		 *
		 * @since 2.1.3
		 */
		$this->controls['lightboxWidth'] = [
			'tab'      => 'content',
			'label'    => esc_html__( 'Width', 'bricks' ) . ' (px)',
			'type'     => 'number',
			'required' => [ 'link', '=', 'lightbox' ],
		];

		$this->controls['lightboxHeight'] = [
			'tab'      => 'content',
			'label'    => esc_html__( 'Height', 'bricks' ) . ' (px)',
			'type'     => 'number',
			'required' => [ 'link', '=', 'lightbox' ],
		];

		$this->controls['lightboxAnimationType'] = [
			'label'       => esc_html__( 'Animation', 'bricks' ),
			'type'        => 'select',
			'options'     => $this->control_options['lightboxAnimationTypes'],
			'inline'      => true,
			'placeholder' => esc_html__( 'Zoom', 'bricks' ),
			'required'    => [ 'link', '=', 'lightbox' ],
		];

		$this->controls['lightboxCaption'] = [
			'tab'      => 'content',
			'label'    => esc_html__( 'Caption', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'link', '=', 'lightbox' ],
		];

		$this->controls['lightboxId'] = [
			'label'       => 'ID',
			'type'        => 'text',
			'inline'      => true,
			'required'    => [ 'link', '=', 'lightbox' ],
			'description' => esc_html__( 'Images of the same lightbox ID are grouped together.', 'bricks' ),
		];

		$this->controls['lightboxCropped'] = [
			'tab'      => 'content',
			'label'    => esc_html__( 'Cropped', 'bricks' ),
			'desc'     => esc_html__( 'Enable if image is cropped for a smooth lightbox image transition.', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'link', '=', 'lightbox' ],
		];

		$this->controls['lightboxPadding'] = [
			'tab'      => 'content',
			'label'    => esc_html__( 'Padding', 'bricks' ) . ' (px)',
			'type'     => 'dimensions',
			'required' => [ 'link', '=', 'lightbox' ],
		];

		$this->controls['newTab'] = [
			'label'    => esc_html__( 'Open in new tab', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'link', '=', [ 'attachment', 'media' ] ],
		];

		$this->controls['url'] = [
			'type'     => 'link',
			'required' => [ 'link', '=', 'url' ],
		];

		// Icon

		$this->controls['popupSep'] = [
			'label'  => esc_html__( 'Icon', 'bricks' ),
			'type'   => 'separator',
			'inline' => true,
			'small'  => true,
			'desc'   => esc_html__( 'Only rendered if link is set.', 'bricks' ),
		];

		// To hide icon for specific elements when image icon set in theme styles
		$this->controls['popupIconDisable'] = [
			'label' => esc_html__( 'Disable icon', 'bricks' ),
			'info'  => esc_html__( 'Settings', 'bricks' ) . ' > ' . esc_html__( 'Theme styles', 'bricks' ) . ' > ' . esc_html__( 'Image', 'bricks' ),
			'type'  => 'checkbox',
		];

		$this->controls['popupIcon'] = [
			'label'    => esc_html__( 'Icon', 'bricks' ),
			'type'     => 'icon',
			'inline'   => true,
			'small'    => true,
			'rerender' => true,
		];

		$this->controls['popupIconBackgroundColor'] = [
			'label'    => esc_html__( 'Icon background color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				[
					'property' => 'background-color',
					'selector' => '&{pseudo} .icon',
				],
			],
			'required' => [ 'popupIcon', '!=', '' ],
		];

		$this->controls['popupIconBorder'] = [
			'label'    => esc_html__( 'Icon border', 'bricks' ),
			'type'     => 'border',
			'css'      => [
				[
					'property' => 'border',
					'selector' => '&{pseudo} .icon',
				],
			],
			'required' => [ 'popupIcon', '!=', '' ],
		];

		$this->controls['popupIconBoxShadow'] = [
			'label'    => esc_html__( 'Icon box shadow', 'bricks' ),
			'type'     => 'box-shadow',
			'css'      => [
				[
					'property' => 'box-shadow',
					'selector' => '&{pseudo} .icon',
				],
			],
			'required' => [ 'popupIcon', '!=', '' ],
		];

		$this->controls['popupIconTypography'] = [
			'label'       => esc_html__( 'Icon typography', 'bricks' ),
			'type'        => 'typography',
			'css'         => [
				[
					'property' => 'font',
					'selector' => '&{pseudo} .icon',
				],
			],
			'exclude'     => [
				'font-family',
				'font-weight',
				'font-style',
				'text-align',
				'text-decoration',
				'text-transform',
				'line-height',
				'letter-spacing',
			],
			'placeholder' => [
				'font-size' => 60,
			],
			'required'    => [ 'popupIcon.icon', '!=', '' ],
		];

		$this->controls['popupIconHeight'] = [
			'label'    => esc_html__( 'Icon height', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'property' => 'line-height',
					'selector' => '&{pseudo} .icon',
				],
			],
			'required' => [ 'popupIcon', '!=', '' ],
		];

		$this->controls['popupIconWidth'] = [
			'label'    => esc_html__( 'Icon width', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'css'      => [
				[
					'property' => 'width',
					'selector' => '&{pseudo} .icon',
				],
			],
			'required' => [ 'popupIcon', '!=', '' ],
		];

		$this->controls['popupIconTransition'] = [
			'label'          => esc_html__( 'Icon transition', 'bricks' ),
			'type'           => 'text',
			'inline'         => true,
			'hasDynamicData' => false,
			'hasVariables'   => true,
			'css'            => [
				[
					'property' => 'transition',
					'selector' => '&{pseudo} .icon',
				],
			],
			'required'       => [ 'popupIcon', '!=', '' ],
		];

		// Image masking (@since 1.8.5)

		$this->controls['maskSep'] = [
			'type'  => 'separator',
			'label' => esc_html__( 'Mask', 'bricks' ),
		];

		$this->controls['mask'] = [
			'label'       => esc_html__( 'Mask', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'custom'                          => esc_html__( 'Custom', 'bricks' ),
				'mask-boom'                       => 'Boom',
				'mask-box'                        => 'Box',
				'mask-bubbles'                    => 'Bubbles',
				'mask-cirlce-dots'                => 'Circle dots',
				'mask-circle-line'                => 'Circle line',
				'mask-circle-waves'               => 'Circle waves',
				'mask-circle'                     => 'Circle',
				'mask-drop-2'                     => 'Drop 2',
				'mask-drop'                       => 'Drop',
				'mask-fire'                       => 'Fire',
				'mask-grid-circles'               => 'Grid circles',
				'mask-grid-dots'                  => 'Grid dots',
				'mask-grid-filled-diagonal'       => 'Grid filled diagonal',
				'mask-grid-lines-diagonal'        => 'Grid lines diagonal',
				'mask-grid'                       => 'Grid',
				'mask-heart'                      => 'Heart',
				'mask-hexagon-dent'               => 'Hexagon dent',
				'mask-hexagon'                    => 'Hexagon',
				'mask-hourglass'                  => 'Hourglass',
				'mask-masonry'                    => 'Masonry',
				'mask-ninja-star'                 => 'Ninja star',
				'mask-octagon-dent'               => 'Octagon dent',
				'mask-play'                       => 'Play',
				'mask-plus'                       => 'Plus',
				'mask-round-zig-zag'              => 'Round zig zag',
				'mask-splash'                     => 'Splash',
				'mask-square-rounded'             => 'Square rounded',
				'mask-squares-3-by-3'             => 'Squares 3x3',
				'mask-squares-4-by-4'             => 'Squares 4x4',
				'mask-squares-4-diagonal-rounded' => 'Squares 4 diagonal rounded',
				'mask-squares-4-diagonal'         => 'Squares 4 diagonal',
				'mask-squares-diagonal'           => 'Squares diagonal',
				'mask-squares-merged'             => 'Squares merged',
				'mask-tiles-2'                    => 'Tiles 2',
				'mask-tiles'                      => 'Tiles',
				'mask-waves'                      => 'Waves',
			],
			'placeholder' => esc_html__( 'Select', 'bricks' ),
		];

		$this->controls['maskCustom'] = [
			'type'     => 'image',
			'unsplash' => false,
			'required' => [ 'mask', '=', 'custom' ],
		];

		$this->controls['maskSize'] = [
			'label'       => esc_html__( 'Size', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'auto'    => esc_html__( 'Auto', 'bricks' ),
				'cover'   => esc_html__( 'Cover', 'bricks' ),
				'contain' => esc_html__( 'Contain', 'bricks' ),
				'custom'  => esc_html__( 'Custom', 'bricks' ),
			],
			'placeholder' => esc_html__( 'Contain', 'bricks' ),
			'required'    => [ 'mask', '!=', '' ],
		];

		$this->controls['maskSizeCustom'] = [
			'label'    => esc_html__( 'Custom size', 'bricks' ),
			'type'     => 'number',
			'units'    => true,
			'required' => [ 'maskSize', '=', 'custom' ],
		];

		$this->controls['maskPosition'] = [
			'label'       => esc_html__( 'Position', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'center center' => esc_html__( 'Center center', 'bricks' ),
				'center left'   => esc_html__( 'Center left', 'bricks' ),
				'center right'  => esc_html__( 'Center right', 'bricks' ),
				'top center'    => esc_html__( 'Top center', 'bricks' ),
				'top left'      => esc_html__( 'Top left', 'bricks' ),
				'top right'     => esc_html__( 'Top right', 'bricks' ),
				'bottom center' => esc_html__( 'Bottom center', 'bricks' ),
				'bottom left'   => esc_html__( 'Bottom left', 'bricks' ),
				'bottom right'  => esc_html__( 'Bottom right', 'bricks' ),
			],
			'placeholder' => esc_html__( 'Center center', 'bricks' ),
			'required'    => [ 'mask', '!=', '' ],
		];

		$this->controls['maskRepeat'] = [
			'label'       => esc_html__( 'Repeat', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'no-repeat' => esc_html__( 'No repeat', 'bricks' ),
				'repeat'    => esc_html__( 'Repeat', 'bricks' ),
				'repeat-x'  => esc_html__( 'Repeat-x', 'bricks' ),
				'repeat-y'  => esc_html__( 'Repeat-y', 'bricks' ),
				'round'     => esc_html__( 'Round', 'bricks' ),
				'space'     => esc_html__( 'Space', 'bricks' ),
			],
			'placeholder' => esc_html__( 'No repeat', 'bricks' ),
			'required'    => [ 'mask', '!=', '' ],
		];
	}

	public function get_mask_url( $settings ) {
		$mask     = ! empty( $settings['mask'] ) ? $settings['mask'] : '';
		$mask_url = '';

		// Custom mask file (SVG, PNG)
		if ( $mask === 'custom' ) {
			// Custom mask image from media library
			if ( ! empty( $settings['maskCustom']['id'] ) ) {
				$image_src = wp_get_attachment_image_src( $settings['maskCustom']['id'], 'full' );
				$mask_url  = ! empty( $image_src[0] ) ? $image_src[0] : '';
			}

			// Dynamic data mask image
			elseif ( ! empty( $settings['maskCustom']['useDynamicData'] ) ) {
				$rendered = $this->render_dynamic_data_tag( $settings['maskCustom']['useDynamicData'], 'image' );

				// STEP: Get the first image from the array or value itself (@since 2.0)
				$item = null;

				// If item is not an array, assign it
				if ( ! is_array( $rendered ) ) {
					$item = $rendered;
				}

				// If item is an array, get the first element, if exists
				elseif ( is_array( $rendered ) && ! empty( $rendered[0] ) ) {
					$item = $rendered[0];
				}

				// STEP: Get the URL (@since 2.0)

				// If item is number, get the URL from the attachment ID
				if ( is_numeric( $item ) ) {
					$image_src = wp_get_attachment_image_src( $item, 'full' );
					$mask_url  = ! empty( $image_src[0] ) ? $image_src[0] : '';
				}

				// If item contains "src" attribute, extract the URL
				elseif ( is_string( $item ) && strpos( $item, 'src=' ) !== false ) {
					// Extract URL from the image tag 'src' attribute
					preg_match( '/src="([^"]*)"/', $item, $matches );
					$mask_url = ! empty( $matches[1] ) ? $matches[1] : '';
				}

				// If item contains "href" attribute, extract the URL
				elseif ( is_string( $item ) && strpos( $item, 'href=' ) !== false ) {
					// Extract URL from the image tag 'href' attribute
					preg_match( '/href="([^"]*)"/', $item, $matches );
					$mask_url = ! empty( $matches[1] ) ? $matches[1] : '';
				}

				// If item is a string, assign it directly
				elseif ( is_string( $item ) ) {
					$mask_url = $item;
				}
			}

			// Custom URL image mask
			elseif ( ! empty( $settings['maskCustom']['url'] ) ) {
				$mask_url = $settings['maskCustom']['url'];
			}
		}

		// Predefined mask file (SVG)
		else {
			$mask_url = BRICKS_URL_ASSETS . "svg/masks/{$mask}.svg";
		}

		return $mask_url;
	}

	protected function set_mask_attributes( $mask_url, $mask_settings ) {
		if ( empty( $mask_settings['mask'] ) ) {
			return;
		}

		// Mask size
		$mask_size = ! empty( $mask_settings['maskSize'] ) ? $mask_settings['maskSize'] : 'contain';

		// Custom mask size
		if ( $mask_size === 'custom' && ! empty( $mask_settings['maskSizeCustom'] ) ) {
			$mask_size = is_numeric( $mask_settings['maskSizeCustom'] ) ? $mask_settings['maskSizeCustom'] . 'px' : $mask_settings['maskSizeCustom'];
		}

		$mask_position = $mask_settings['maskPosition'] ?? 'center center';
		$mask_repeat   = $mask_settings['maskRepeat'] ?? 'no-repeat';

		// Mask inline style (webkit and standard)
		$mask_style  = "-webkit-mask-image: url('{$mask_url}'); -webkit-mask-size: {$mask_size}; -webkit-mask-position: {$mask_position}; -webkit-mask-repeat: {$mask_repeat};";
		$mask_style .= "mask-image: url('{$mask_url}'); mask-size: {$mask_size}; mask-position: {$mask_position}; mask-repeat: {$mask_repeat};";

		// Apply mask style to image
		$this->set_attribute( 'img', 'style', $mask_style );
	}

	public function get_normalized_image_settings( $settings ) {
		if ( empty( $settings['image'] ) ) {
			return [
				'id'   => 0,
				'url'  => false,
				'size' => BRICKS_DEFAULT_IMAGE_SIZE,
			];
		}

		$image = $settings['image'];

		// Size
		$image['size'] = empty( $image['size'] ) ? BRICKS_DEFAULT_IMAGE_SIZE : $settings['image']['size'];

		// Image ID or URL from dynamic data
		if ( ! empty( $image['useDynamicData'] ) ) {
			$images = $this->render_dynamic_data_tag( $image['useDynamicData'], 'image', [ 'size' => $image['size'] ] );

			if ( ! empty( $images[0] ) ) {
				if ( is_numeric( $images[0] ) ) {
					$image['id'] = $images[0];
				} else {
					$image['url'] = $images[0];
				}
			}

			// No dynamic data image found (@since 1.6)
			else {
				return;
			}
		}

		$image['id'] = empty( $image['id'] ) ? 0 : $image['id'];

		// If External URL, $image['url'] is already set
		if ( ! isset( $image['url'] ) ) {
			$image['url'] = ! empty( $image['id'] ) ? wp_get_attachment_image_url( $image['id'], $image['size'] ) : false;
		} else {
			// Parse dynamic data in the external URL
			if ( ! empty( $image['external'] ) && strpos( $image['external'], '{' ) !== false && strpos( $image['external'], '}' ) !== false ) {
				// Use external url if contains a dynamic data tag (@since 1.11.1)
				$image['url'] = $this->render_dynamic_data( $image['external'] );
			} else {
				$image['url'] = $this->render_dynamic_data( $image['url'] );
			}
		}

		return $image;
	}

	public function render() {
		$settings   = $this->settings;
		$link       = ! empty( $settings['link'] ) ? $settings['link'] : false;
		$sources    = ! empty( $settings['sources'] ) ? $settings['sources'] : false;
		$image      = $this->get_normalized_image_settings( $settings );
		$image_id   = isset( $image['id'] ) ? $image['id'] : '';
		$image_url  = isset( $image['url'] ) ? $image['url'] : '';
		$image_size = isset( $image['size'] ) ? $image['size'] : '';

		// STEP: Dynamic data image not found: Show placeholder text
		if ( ! empty( $settings['image']['useDynamicData'] ) && ! $image ) {
			return $this->render_element_placeholder(
				[
					'title' => esc_html__( 'Dynamic data is empty.', 'bricks' )
				]
			);
		}

		$image_placeholder_url = \Bricks\Builder::get_template_placeholder_image();

		// STEP: Image caption
		$show_caption = isset( $this->theme_styles['caption'] ) ? $this->theme_styles['caption'] : 'attachment';

		if ( isset( $settings['caption'] ) ) {
			$show_caption = $settings['caption'];
		}

		$image_caption = false;

		if ( $show_caption === 'none' ) {
			$image_caption = false;
		} elseif ( $show_caption === 'custom' && ! empty( $settings['captionCustom'] ) ) {
			$image_caption = trim( $settings['captionCustom'] );
		} elseif ( $image_id ) {
			$image_data    = get_post( $image_id );
			$image_caption = $image_data ? $image_data->post_excerpt : '';
		}

		$has_overlay = false;

		// Check: Loop over settings that starts with 'popupOverlay' (@since 2.1)
		foreach ( $settings as $key => $value ) {
			if ( strpos( $key, 'popupOverlay' ) === 0 ) {
				$has_overlay = true;
				break;
			}
		}

		$has_html_tag = $image_caption || $has_overlay || isset( $settings['_gradient'] ) || isset( $settings['tag'] );

		// Check: Element classes for 'popupOverlay' setting to add .overlay class to make ::before work
		if ( ! $has_overlay && $this->element_classes_have( 'popupOverlay' ) ) {
			$has_overlay = true;
		}

		// Default: 'figure' HTML tag (needed to apply overlay::before to as not possible on self-closing 'img' tag)
		if ( $has_overlay ) {
			$has_html_tag = true;
		}

		// Check: Element classes for 'gradient' setting to add HTML tag to Image element to make ::before work
		if ( ! $has_html_tag && $this->element_classes_have( '_gradient' ) ) {
			$has_html_tag = true;
		}

		// Check: No image selected: No image ID provided && not a placeholder URL
		if ( ! isset( $image['external'] ) && ! $image_id && ! $image_url && $image_url !== $image_placeholder_url ) {
			return $this->render_element_placeholder( [ 'title' => esc_html__( 'No image selected.', 'bricks' ) ] );
		}

		// Check: Image with ID doesn't exist
		if ( ! isset( $image['external'] ) && ! $image_url ) {
			// translators: %s: Image ID
			return $this->render_element_placeholder( [ 'title' => sprintf( esc_html__( 'Image ID (%s) no longer exist. Please select another image.', 'bricks' ), $image_id ) ] );
		}

		$this->set_attribute( 'img', 'class', 'css-filter' );

		$this->set_attribute( 'img', 'class', "size-$image_size" );

		// Check for custom "Alt Text" setting
		if ( ! empty( $settings['altText'] ) ) {
			$this->set_attribute( 'img', 'alt', esc_attr( $settings['altText'] ) );
		}

		// Set 'loading' attribute: eager or lazy
		if ( ! empty( $settings['loading'] ) ) {
			$this->set_attribute( 'img', 'loading', esc_attr( $settings['loading'] ) );
		}

		// Show image 'title' attribute
		if ( isset( $settings['showTitle'] ) ) {
			$image_title = $image_id ? get_the_title( $image_id ) : false;

			if ( $image_title ) {
				$this->set_attribute( 'img', 'title', esc_attr( $image_title ) );
			}
		}

		// Wrap image element in 'figure' to allow for image caption, overlay, icon
		if ( $has_overlay ) {
			$this->set_attribute( '_root', 'class', 'overlay' );
		}

		/**
		 * Render: Wrap 'img' HTML tag in HTML tag (if 'tag' set) or anchor tag (if 'link' set)
		 */
		$output         = '';
		$output_sources = '';

		/**
		 * Responsive images: Add 'sources'
		 *
		 * @since 1.8.5
		 */
		$base_src    = null; // Base source image (@since 1.12; needeed for Lightbox)
		$width_range = Breakpoints::$is_mobile_first ? 'min-width' : 'max-width';

		if ( is_array( $sources ) && count( $sources ) ) {
			foreach ( $sources as $index => $source ) {
				$breakpoint_key = ! empty( $source['breakpoint'] ) ? $source['breakpoint'] : false;

				if ( ! $breakpoint_key ) {
					continue;
				}

				$breakpoint = $breakpoint_key ? Breakpoints::get_breakpoint_by( 'key', $breakpoint_key ) : false;

				// Set 'media' attribute from breakpoint width (if not 'base' breakpoint)
				if ( ! empty( $breakpoint['width'] ) && ! isset( $breakpoint['base'] ) ) {
					$this->set_attribute( "source_{$index}", 'media', "({$width_range}: {$breakpoint['width']}px)" );
				}

				// Set 'media' attribute from custom media query
				if ( $breakpoint_key === '_custom' && ! empty( $source['media'] ) ) {
					$this->set_attribute( "source_{$index}", 'media', esc_attr( $source['media'] ) );
				}

				// Get image ID, size, srcset (get_normalized_image_settings() in case image uses dynamic data)
				$source          = $this->get_normalized_image_settings( $source );
				$source_image    = ! empty( $source['image'] ) ? $source['image'] : $source;
				$source_image_id = ! empty( $source_image['id'] ) ? $source_image['id'] : false;

				if ( $source_image_id ) {
					$source_image_size = ! empty( $source_image['size'] ) ? $source_image['size'] : 'large';
					$source_image_url  = wp_get_attachment_image_url( $source_image_id, $source_image_size );

					// Skip iteration if image ULR is empty
					if ( ! $source_image_url ) {
						continue;
					}

					$this->set_attribute( "source_{$index}", 'srcset', esc_attr( $source_image_url ) );

					// Check if image is on Base breakpoint, then get image source data
					if ( isset( $breakpoint['base'] ) ) {
						$base_src = wp_get_attachment_image_src( $source_image_id, $source_image_size );
					}

					// Get MIME type of the image
					$source_image_mime_type = get_post_mime_type( $source_image_id );

					if ( $source_image_mime_type ) {
						$this->set_attribute( "source_{$index}", 'type', $source_image_mime_type );
					}
				}

				// External image URL
				elseif ( ! empty( $source_image['url'] ) ) {
					$source_image['url'] = str_replace( ' ', '%20', $source_image['url'] ); // URL should not contain space, we replace it with %20 (@since 1.12.3)
					$this->set_attribute( "source_{$index}", 'srcset', esc_attr( $source_image['url'] ) );
				}

				$source_attributes = $this->render_attributes( "source_{$index}" );

				if ( $source_attributes ) {
					$output_sources .= "<source $source_attributes />";
				}
			}
		}

		// Sources set, but no link: Wrap image in 'picture' tag
		if ( $output_sources && ! $link ) {
			$this->tag    = 'picture';
			$has_html_tag = true;
		}

		// Add _root attributes to outermost tag
		if ( $has_html_tag ) {
			$this->set_attribute( '_root', 'class', 'tag' );

			// Has image caption (add position: relative through class)
			if ( $image_caption ) {
				$this->set_attribute( '_root', 'class', 'caption' );
			}

			$output .= "<{$this->tag} {$this->render_attributes( '_root' )}>";
		}

		if ( $link ) {
			// Link is outermost tag: Merge _root attributes into link attributes it
			if ( ! $has_html_tag ) {
				foreach ( $this->attributes['_root'] as $key => $value ) {
					$this->attributes['link'][ $key ] = $value;
					unset( $this->attributes['_root'][ $key ] );
				}
			}

			$this->set_attribute( 'link', 'class', 'tag' );

			if ( isset( $settings['newTab'] ) ) {
				$this->set_attribute( 'link', 'target', '_blank' );
			}

			if ( $link === 'media' && $image_id ) {
				$this->set_attribute( 'link', 'href', wp_get_attachment_url( $image_id ) );
			} elseif ( $link === 'attachment' && $image_id ) {
				$this->set_attribute( 'link', 'href', get_permalink( $image_id ) );
			} elseif ( $link === 'url' && ! empty( $settings['url'] ) ) {
				$this->set_link_attributes( 'link', $settings['url'] );
			} elseif ( $link === 'lightbox' ) {
				$this->set_attribute( 'link', 'class', 'bricks-lightbox' );

				// Lightbox image size
				$lightbox_image_size = $settings['lightboxImageSize'] ?? 'full';

				if ( $image_id ) {
					$lightbox_image_src = wp_get_attachment_image_src( $image_id, $lightbox_image_size );
				} elseif ( $image_url ) {
					$lightbox_image_src = [ $image_url, 800, 600 ];
				} else {
					$lightbox_image_src = [ $image_placeholder_url, 800, 600 ]; // Placeholder image
				}

				$lightbox_image = $base_src ?? $lightbox_image_src;
				if ( $lightbox_image ) {
					$this->set_attribute( 'link', 'href', $lightbox_image[0] );
					$this->set_attribute( 'link', 'data-pswp-src', $lightbox_image[0] );

					// Use custom width if set, otherwise use image width (@since 2.1.3)
					$lightbox_width = ! empty( $settings['lightboxWidth'] ) ? intval( $settings['lightboxWidth'] ) : $lightbox_image[1];
					$this->set_attribute( 'link', 'data-pswp-width', $lightbox_width );

					// Use custom height if set, otherwise use image height (@since 2.1.3)
					$lightbox_height = ! empty( $settings['lightboxHeight'] ) ? intval( $settings['lightboxHeight'] ) : $lightbox_image[2];
					$this->set_attribute( 'link', 'data-pswp-height', $lightbox_height );
				}

				if ( ! empty( $settings['lightboxId'] ) ) {
					$this->set_attribute( 'link', 'data-pswp-id', esc_attr( $settings['lightboxId'] ) );
				}

				if ( ! empty( $settings['lightboxAnimationType'] ) ) {
					$this->set_attribute( 'link', 'data-animation-type', esc_attr( $settings['lightboxAnimationType'] ) );
				}

				if ( ! empty( $settings['lightboxPadding'] ) ) {
					$this->set_attribute( 'link', 'data-lightbox-padding', wp_json_encode( $settings['lightboxPadding'] ) );
				}

				// Lightbox caption (@since 1.10)
				if ( isset( $settings['lightboxCaption'] ) ) {
					$this->set_attribute( 'link', 'class', 'has-lightbox-caption' );

					$lightbox_caption = $image_id ? wp_get_attachment_caption( $image_id ) : false;
					if ( $lightbox_caption ) {
						$this->set_attribute( 'link', 'data-lightbox-caption', esc_attr( $lightbox_caption ) );
					}
				}

				/**
				 * Add 'data-cropped' attribute if lightboxCropped is set
				 *
				 * Needed for PhotoSwipe lightbox to work correctly with cropped images.
				 *
				 * https://photoswipe.com/getting-started/#required-html-markup
				 *
				 * @since 2.0
				 */
				if ( isset( $settings['lightboxCropped'] ) ) {
					$this->set_attribute( 'link', 'data-cropped', 'true' );
				}
			}

			$output .= "<a {$this->render_attributes( 'link' )}>";
		}

		// Show popup icon if link is set
		$icon = ! empty( $settings['popupIcon'] ) ? $settings['popupIcon'] : false;

		// Check: Theme style for video 'popupIcon' setting
		if ( ! $icon && ! empty( $this->theme_styles['popupIcon'] ) ) {
			$icon = $this->theme_styles['popupIcon'];
		}

		if ( ! isset( $settings['popupIconDisable'] ) && $link && $icon ) {
			$output .= self::render_icon( $icon, [ 'icon' ] );
		}

		// Render <source> tags
		if ( $output_sources ) {
			// Render <picture> tag if $link set
			if ( $link ) {
				$output .= '<picture>';
			}

			$output .= $output_sources;
		}

		// Determine the URL of the mask image
		$mask_url = $this->get_mask_url( $settings );

		// If a mask URL was found, apply the mask to the image
		if ( $mask_url ) {
			$this->set_mask_attributes( $mask_url, $settings );
		}

		// Lazy load atts set via 'wp_get_attachment_image_attributes' filter
		if ( $image_id ) {
			$image_attributes = [];

			// Run the filter to get data-query-loop-index attribute (@since 1.11)
			$this->attributes = apply_filters( 'bricks/element/render_attributes', $this->attributes, '_root', $this );

			// 'img' is root (no caption, no overlay)
			if ( ! $has_html_tag && ! $link ) {
				foreach ( $this->attributes['_root'] as $key => $value ) {
					$image_attributes[ $key ] = is_array( $value ) ? join( ' ', $value ) : $value;
				}
			}

			foreach ( $this->attributes['img'] as $key => $value ) {
				if ( isset( $image_attributes[ $key ] ) ) {
					$image_attributes[ $key ] .= ' ' . ( is_array( $value ) ? join( ' ', $value ) : $value );
				} else {
					$image_attributes[ $key ] = is_array( $value ) ? join( ' ', $value ) : $value;
				}
			}

			// Merge custom attributes with img attributes
			$custom_attributes = $this->get_custom_attributes( $settings );
			$image_attributes  = array_merge( $image_attributes, $custom_attributes );

			if ( isset( $image_attributes['width'] ) || isset( $image_attributes['height'] ) ) {
				$this->wp_img_data = [
					'id'     => $image_id,
					'width'  => isset( $image_attributes['width'] ) ? intval( $image_attributes['width'] ) : 0,
					'height' => isset( $image_attributes['height'] ) ? intval( $image_attributes['height'] ) : 0,
				];

				add_filter( 'wp_get_attachment_image_src', [ $this, 'amend_image_src' ], 10, 2 );
			}

			$output .= wp_get_attachment_image( $image_id, $image_size, false, $image_attributes );

			if ( isset( $image_attributes['width'] ) || isset( $image_attributes['height'] ) ) {
				remove_filter( 'wp_get_attachment_image_src', [ $this, 'amend_image_src' ], 10, 2 );
				$this->wp_img_data = [];
			}
		} elseif ( $image_url ) {
			if ( ! $has_html_tag && ! $link ) {
				foreach ( $this->attributes['_root'] as $key => $value ) {
					$this->attributes['img'][ $key ] = $value;
				}
			}

			$this->set_attribute( 'img', 'src', $image_url );

			// Set empty 'alt' attribute for a11y (@since 1.9.2)
			if ( ! isset( $this->attributes['img']['alt'] ) ) {
				$this->set_attribute( 'img', 'alt', '' );
			}

			$output .= "<img {$this->render_attributes( 'img', true )}>";
		}

		if ( $image_caption ) {
			// Assign a class to the caption element based on the theme style setting (@since 2.1)
			if ( isset( $this->theme_styles['captionCustomStyles'] ) ) {
				$this->set_attribute( 'figcaption', 'class', 'bricks-image-caption-custom' );
			} else {
				$this->set_attribute( 'figcaption', 'class', 'bricks-image-caption' );
			}

			$output .= "<figcaption {$this->render_attributes( 'figcaption' )}>" . $image_caption . '</figcaption>';
		}

		if ( $link ) {
			$output .= '</a>';
		}

		// Render <source> tags plus <picture> tag if $link set
		if ( $output_sources && $link ) {
			$output .= '</picture>';
		}

		if ( $has_html_tag ) {
			$output .= "</{$this->tag}>";
		}

		echo $output;
	}

	public function get_block_html( $settings ) {
		if ( empty( $settings['image'] ) ) {
			return;
		}

		$image_id   = empty( $settings['image']['id'] ) ? 0 : $settings['image']['id'];
		$image_size = empty( $settings['image']['size'] ) ? BRICKS_DEFAULT_IMAGE_SIZE : $settings['image']['size'];

		$figure_classes = [ 'wp-block-image', "size-$image_size" ];

		if ( isset( $settings['_typography']['text-align'] ) ) {
			$figure_classes[] = 'align' . $settings['_typography']['text-align'];
		}

		$this->set_attribute( 'figure', 'class', $figure_classes );

		$this->set_attribute( 'image', 'src', $settings['image']['url'] );
		$this->set_attribute( 'image', 'alt', isset( $settings['altText'] ) ? $settings['altText'] : '' );

		if ( $image_id ) {
			$this->set_attribute( 'image', 'class', 'wp-image-' . $image_id );
		}

		if ( isset( $settings['_width'] ) && strpos( $settings['_width'], 'px' ) !== false ) {
			$this->set_attribute( 'image', 'width', str_replace( 'px', '', $settings['_width'] ) );
		}

		if ( isset( $settings['_height'] ) && strpos( $settings['_height'], 'px' ) !== false ) {
			$this->set_attribute( 'image', 'height', str_replace( 'px', '', $settings['_height'] ) );
		}

		$block_html = "<figure {$this->render_attributes( 'figure' )}>";

		$link = ! empty( $settings['link'] ) ? $settings['link'] : false;

		if ( $link ) {
			if ( $link === 'media' ) {
				$this->set_link_attributes( 'a', 'href', $image_id ? wp_get_attachment_url( $image_id ) : $settings['image']['url'] );
			} elseif ( ! empty( $settings['url'] ) ) {
				$this->set_link_attributes( 'a', $settings['url'] );
			}

			$this->remove_attribute( 'a', 'class' );

			$block_html .= "<a {$this->render_attributes( 'a' )}>";
		}

		$block_html .= "<img {$this->render_attributes( 'image' )}>";

		if ( $link ) {
			$block_html .= '</a>';
		}

		$block_html .= '</figure>';

		return $block_html;
	}

	public function convert_element_settings_to_block( $settings ) {
		if ( empty( $settings['image'] ) ) {
			return;
		}

		$image = $this->get_normalized_image_settings( $settings );

		$block = [
			'blockName'    => $this->block,
			'attrs'        => [
				'id'       => empty( $image['id'] ) ? '' : $image['id'],
				'sizeSlug' => empty( $image['size'] ) ? BRICKS_DEFAULT_IMAGE_SIZE : $image['size'],
			],
			'innerContent' => [],
		];

		if ( isset( $settings['_typography']['text-align'] ) ) {
			$block['attrs']['align'] = $settings['_typography']['text-align'];
		}

		if ( isset( $settings['_width'] ) && strpos( $settings['_width'], 'px' ) !== false ) {
			$block['attrs']['width'] = intval( str_replace( 'px', '', $settings['_width'] ) );
		}

		if ( isset( $settings['_height'] ) && strpos( $settings['_height'], 'px' ) !== false ) {
			$block['attrs']['height'] = intval( str_replace( 'px', '', $settings['_height'] ) );
		}

		$link = ! empty( $settings['link'] ) ? $settings['link'] : false;

		if ( $link ) {
			$block['attrs']['linkDestination'] = $link === 'media' ? 'media' : 'custom';
		}

		$settings['image'] = $image;

		$inner_content = $this->get_block_html( $settings );

		$block['innerContent'] = [ $inner_content ];

		return $block;
	}

	/**
	 * Not done yet: Custom block alt & caption strings have to be extracted from $block['innerHTML']
	 */
	public function convert_block_to_element_settings( $block, $attributes ) {
		$element_settings = [];

		$image_id   = isset( $attributes['id'] ) ? intval( $attributes['id'] ) : 0;
		$image_size = isset( $attributes['sizeSlug'] ) ? $attributes['sizeSlug'] : BRICKS_DEFAULT_IMAGE_SIZE;
		$image_url  = wp_get_attachment_image_src( $image_id, $image_size );

		if ( is_array( $image_url ) && isset( $image_url[0] ) ) {
			$image_url = $image_url[0];
		}

		// External URL
		if ( ! $image_id ) {
			preg_match_all( '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $block['innerHTML'], $match );

			$image_url = isset( $match[0] ) ? $match[0] : false;

			if ( is_array( $image_url ) && isset( $image_url[0] ) ) {
				$image_url = $image_url[0];
			}

			$element_settings['image'] = [
				'external' => true,
				'url'      => $image_url,
				'filename' => basename( $image_url ),
				'full'     => $image_url,
				'size'     => $image_size,
			];
		}

		// WordPress image
		if ( $image_id && $image_url ) {
			$element_settings['image'] = [
				'id'       => $image_id,
				'filename' => basename( get_attached_file( $image_id ) ),
				'full'     => wp_get_attachment_image_src( $image_id, 'full' ),
				'size'     => $image_size,
				'url'      => $image_url,
			];
		}

		return $element_settings;
	}

	/**
	 * Amend image src with width and height attributes
	 *
	 * @param array $image Image attributes.
	 * @param int   $attachment_id Attachment ID.
	 *
	 * @return array
	 */
	public function amend_image_src( $image, $attachment_id ) {
		if ( ! isset( $this->wp_img_data['id'] ) || $this->wp_img_data['id'] !== $attachment_id ) {
			return $image;
		}

		if ( isset( $this->wp_img_data['width'] ) && $this->wp_img_data['width'] > 0 ) {
			$image[1] = $this->wp_img_data['width'];
		}

		if ( isset( $this->wp_img_data['height'] ) && $this->wp_img_data['height'] > 0 ) {
			$image[2] = $this->wp_img_data['height'];
		}

		return $image;
	}
}
