<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Element_Code extends Element {
	public $block    = [ 'core/code', 'core/preformatted' ];
	public $category = 'general';
	public $name     = 'code';
	public $icon     = 'ion-ios-code';
	public $scripts  = [ 'bricksPrettify' ];

	public function enqueue_scripts() {
		// Load prettify scripts and styles if code execution is not enabled (@since 2.0)
		if ( ! isset( $this->settings['executeCode'] ) &&
			( ! empty( $this->settings['prettify'] ) || ! empty( $this->theme_styles['prettify'] ) )
		) {
			wp_enqueue_script( 'bricks-prettify' );
			wp_enqueue_style( 'bricks-prettify' );
		}
	}

	public function get_label() {
		return esc_html__( 'Code', 'bricks' );
	}

	public function get_keywords() {
		return [ 'code', 'css', 'html', 'javascript', 'js', 'php', 'script', 'snippet', 'style' ];
	}

	public function set_controls() {
		$user_can_execute_code = Capabilities::current_user_can_execute_code();
		if ( $user_can_execute_code ) {
			$this->controls['executeCode'] = [
				'label' => esc_html__( 'Execute code', 'bricks' ),
				'type'  => 'checkbox',
			];

			// @since 1.9.8
			$this->controls['parseDynamicData'] = [
				'label'    => esc_html__( 'Parse dynamic data', 'bricks' ),
				'type'     => 'checkbox',
				'required' => [ 'executeCode', '!=', '' ],
			];

			$this->controls['supressPhpErrors'] = [
				'label'    => esc_html__( 'Suppress PHP errors', 'bricks' ),
				'type'     => 'checkbox',
				'desc'     => esc_html__( 'Add "brx_code_errors" as an URL parameter to show PHP errors if needed.', 'bricks' ),
				'required' => [ 'executeCode', '!=', '' ],
			];

			$this->controls['noRoot'] = [
				'label'    => esc_html__( 'Render without wrapper', 'bricks' ),
				'type'     => 'checkbox',
				'desc'     => esc_html__( 'Render on the front-end without the div wrapper.', 'bricks' ),
				'required' => [ 'executeCode', '!=', '' ],
			];

			$this->controls['noRootInfo'] = [
				'type'     => 'info',
				'content'  => esc_html__( 'When rendering without wrapper your settings under the style tab won\'t have any effect.', 'bricks' ),
				'required' => [ 'noRoot', '=', true ],
			];
		}

		// Code execution not allowed
		else {
			$this->controls['infoExecuteCodeOff'] = [
				// translators: %s: 'Bricks settings path'
				'content' => '<strong>' . esc_html__( 'Code execution not allowed.', 'bricks' ) . '</strong> ' . sprintf(
					esc_html__( 'You can manage code execution permissions under: %s', 'bricks' ),
					'<a href="' . admin_url( 'admin.php?page=bricks-settings#tab-custom-code' ) . '" target="_blank">Bricks > ' . esc_html__( 'Settings', 'bricks' ) . ' > ' . esc_html__( 'Custom code', 'bricks' ) . ' > ' . esc_html__( 'Code execution', 'bricks' ) . '</a>'
				),
				'type'    => 'info',
			];
		}

		if ( $user_can_execute_code ) {
			$this->controls['infoExecuteCode'] = [
				'content'  => esc_html__( 'The executed code will run on your site! Only add code that you consider safe.', 'bricks' ),
				'type'     => 'info',
				'required' => [ 'executeCode', '!=', '' ],
			];
		}

		// Code (PHP & HTML, CSS, JavaScript)
		$css_logo        = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="16" viewBox="0 0 124 141.53" fill="none" ><path d="M10.383 126.892L0 0l124 .255-10.979 126.637-50.553 14.638z" fill="#1b73ba"/><path d="M62.468 129.275V12.085l51.064.17-9.106 104.85z" fill="#1c88c7"/><path d="M100.851 27.064H22.298l2.128 15.318h37.276l-36.68 15.745 2.127 14.808h54.043l-1.958 20.68-18.298 3.575-16.595-4.255-1.277-11.745H27.83l2.042 24.426 32.681 9.106 31.32-9.957 4-47.745H64.765l36.085-14.978z" fill="#fff"/></svg>';
		$javascript_logo = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="16" viewBox="0 0 1052 1052"><path fill="#f0db4f" d="M0 0h1052v1052H0z"/><path d="M965.9 801.1c-7.7-48-39-88.3-131.7-125.9-32.2-14.8-68.1-25.399-78.8-49.8-3.8-14.2-4.3-22.2-1.9-30.8 6.9-27.9 40.2-36.6 66.6-28.6 17 5.7 33.1 18.801 42.8 39.7 45.4-29.399 45.3-29.2 77-49.399-11.6-18-17.8-26.301-25.4-34-27.3-30.5-64.5-46.2-124-45-10.3 1.3-20.699 2.699-31 4-29.699 7.5-58 23.1-74.6 44-49.8 56.5-35.6 155.399 25 196.1 59.7 44.8 147.4 55 158.6 96.9 10.9 51.3-37.699 67.899-86 62-35.6-7.4-55.399-25.5-76.8-58.4-39.399 22.8-39.399 22.8-79.899 46.1 9.6 21 19.699 30.5 35.8 48.7 76.2 77.3 266.899 73.5 301.1-43.5 1.399-4.001 10.6-30.801 3.199-72.101zm-394-317.6h-98.4c0 85-.399 169.4-.399 254.4 0 54.1 2.8 103.7-6 118.9-14.4 29.899-51.7 26.2-68.7 20.399-17.3-8.5-26.1-20.6-36.3-37.699-2.8-4.9-4.9-8.7-5.601-9-26.699 16.3-53.3 32.699-80 49 13.301 27.3 32.9 51 58 66.399 37.5 22.5 87.9 29.4 140.601 17.3 34.3-10 63.899-30.699 79.399-62.199 22.4-41.3 17.6-91.3 17.4-146.6.5-90.2 0-180.4 0-270.9z" fill="#323330"/></svg>';
		$php_logo        = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="16" viewBox="0 0 256 134"  preserveAspectRatio="xMinYMin meet"><g fill-rule="evenodd"><ellipse fill="#8993BE" cx="128" cy="66.63" rx="128" ry="66.63"/><path d="M35.945 106.082l14.028-71.014H82.41c14.027.877 21.041 7.89 21.041 20.165 0 21.041-16.657 33.315-31.562 32.438H56.11l-3.507 18.411H35.945zm23.671-31.561L64 48.219h11.397c6.137 0 10.52 2.63 10.52 7.89-.876 14.905-7.89 17.535-15.78 18.412h-10.52zM100.192 87.671l14.027-71.013h16.658l-3.507 18.41h15.78c14.028.877 19.288 7.89 17.535 16.658l-6.137 35.945h-17.534l6.137-32.438c.876-4.384.876-7.014-5.26-7.014H124.74l-7.89 39.452h-16.658zM153.425 106.082l14.027-71.014h32.438c14.028.877 21.042 7.89 21.042 20.165 0 21.041-16.658 33.315-31.562 32.438h-15.781l-3.507 18.411h-16.657zm23.67-31.561l4.384-26.302h11.398c6.137 0 10.52 2.63 10.52 7.89-.876 14.905-7.89 17.535-15.78 18.412h-10.521z" fill="#232531"/></g></svg>';
		$html_logo       = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="16" viewBox="0 0 124 141.53199999999998" fill="none"><path d="M10.383 126.894L0 0l124 .255-10.979 126.639-50.553 14.638z" fill="#e34f26"/><path d="M62.468 129.277V12.085l51.064.17-9.106 104.851z" fill="#ef652a"/><path d="M99.49 41.362l1.446-15.49H22.383l4.34 47.49h54.213L78.81 93.617l-17.362 4.68-17.617-5.106-.936-12.085H27.319l2.128 24.681 32 8.936 32.255-8.936 4.34-48.17H41.107L39.49 41.362z" fill="#fff"/></svg>';

		$this->controls['code'] = [
			'label'          => $html_logo . 'PHP & HTML',
			'default'        => "<h1 class='my-heading'>Just some HTML</h1><?php echo 'The year is ' . date('Y'); ?>",
			'type'           => 'code',
			'mode'           => 'application/x-httpd-php-open',
			'required'       => [ 'useDynamicData', '=', '' ],
			'hasDynamicData' => true,
			'hasVariables'   => true,
			'syncExpand'     => false,
			'signCode'       => true,
		];

		// CSS (@since 1.10)
		$this->controls['cssCode'] = [
			'label'          => $css_logo . 'CSS',
			'desc'           => esc_html__( 'CSS is automatically wrapped in style tags.', 'bricks' ),
			'type'           => 'code',
			'mode'           => 'css',
			'placeholder'    => ".my-heading {\n  color: crimson;\n}",
			'required'       => [ 'useDynamicData', '=', '' ],
			'hasDynamicData' => true,
			'hasVariables'   => true,
			'syncExpand'     => false,
		];

		// JavaScript (@since 1.10)
		$this->controls['javascriptCode'] = [
			'label'          => $javascript_logo . 'JavaScript',
			'desc'           => esc_html__( 'JavaScript is automatically wrapped in script tags.', 'bricks' ),
			'type'           => 'code',
			'mode'           => 'javascript',
			'placeholder'    => "console.log('Hello, World!');",
			'required'       => [ 'useDynamicData', '=', '' ],
			'hasDynamicData' => true,
			'hasVariables'   => false,
			'syncExpand'     => false,
		];

		$this->controls['useDynamicData'] = [
			'deprecated'  => '1.9.5',
			'label'       => '',
			'type'        => 'text',
			'placeholder' => esc_html__( 'Select dynamic data', 'bricks' ),
			'required'    => [ 'code', '=', '' ],
		];

		// Prettify (@since 1.10)
		$this->controls['prettify'] = [
			'label'       => esc_html__( 'Theme', 'bricks' ),
			'type'        => 'select',
			'inline'      => true,
			'options'     => [
				'github'         => 'Github (light)',
				'tomorrow'       => 'Tomorrow (light)',
				'tomorrow-night' => 'Tomorrow Night (dark)',
				'tranquil-heart' => 'Tranquil Heart (dark)',
			],
			'desc'        => esc_html__( 'Can also be set globally via theme styles.', 'bricks' ),
			'placeholder' => esc_html__( 'None', 'bricks' ),
			'required'    => [ 'executeCode', '=', false ],
		];
	}

	public function render() {
		$settings = $this->settings;
		$code     = $settings['code'] ?? false;

		// STEP: Parse dynamic data
		if ( ! empty( $settings['useDynamicData'] ) ) {
			$dynamic_data_code = $this->render_dynamic_data_tag( $settings['useDynamicData'] );

			if ( empty( $dynamic_data_code ) ) {
				return $this->render_element_placeholder(
					[
						'title' => esc_html__( 'Dynamic data is empty.', 'bricks' )
					]
				);
			}

			$code = $dynamic_data_code;
		}

		// STEP: Execute code
		if ( isset( $settings['executeCode'] ) ) {
			// Return: Code execution not enabled (Bricks setting or filter)
			if ( ! Helpers::code_execution_enabled() ) {
				return $this->render_element_placeholder(
					[
						'title'       => esc_html__( 'Code execution not allowed.', 'bricks' ),
						// translators: %s: 'Bricks settings path'
						'description' => esc_html__( 'Code execution not allowed.', 'bricks' ) . ' ' . sprintf(
							esc_html__( 'You can manage code execution permissions under: %s', 'bricks' ),
							'Bricks > ' . esc_html__( 'Settings', 'bricks' ) . ' > ' . esc_html__( 'Custom code', 'bricks' ) . ' > ' . esc_html__( 'Code execution', 'bricks' )
						),
					]
				);
			}

			// Sanitize element code
			$post_id = Database::$page_data['preview_or_post_id'] ?? $this->post_id;

			// Verify code signature
			$verified_code = isset( $settings['code'] ) && isset( $settings['signature'] ) ? Helpers::sanitize_element_php_code( $post_id, $this->id, $settings['code'], $settings['signature'] ) : '';

			// Return error: Code signature not valid
			if ( isset( $verified_code['error'] ) ) {
				return $this->render_element_placeholder( [ 'title' => $verified_code['error'] ], 'error' );
			}

			// Parse dynamic data (@since 1.9.8)
			if ( isset( $settings['parseDynamicData'] ) ) {
				$verified_code = $this->render_dynamic_data( $verified_code );
			}

			// Prepare PHP/HTML code for execution
			$php_html_output = $verified_code;

			// Sets context on AJAX/REST API calls or when reloading the builder
			if ( bricks_is_builder() || bricks_is_builder_call() ) {
				global $post;

				$post = get_post( $this->post_id );

				setup_postdata( $post );
			}

			ob_start();

			// Prepare & set error reporting
			$error_reporting = error_reporting( E_ALL );
			$display_errors  = ini_get( 'display_errors' );

			/**
			 * Show PHP errors only if not suppressed
			 *
			 * brx_code_errors can force PHP errors to be shown.
			 *
			 * @since 1.9.8
			 */
			$show_php_errors = isset( $settings['supressPhpErrors'] ) && ! isset( $_GET['brx_code_errors'] ) ? 0 : 1;
			ini_set( 'display_errors', $show_php_errors );

			try {
				$result = eval( ' ?>' . $php_html_output . '<?php ' );
			} catch ( \Throwable $error ) {
				$result = false;
			}

			// Reset error reporting
			ini_set( 'display_errors', $display_errors );
			error_reporting( $error_reporting );

			// @see https://www.php.net/manual/en/function.eval.php
			if ( version_compare( PHP_VERSION, '7', '<' ) && $result === false || ! empty( $error ) ) {
				if ( ! $show_php_errors ) {
					$output = '';
				} else {
					$error_type = get_class( $error ) ?? 'Error';
					$output     = $error_type . ': ' . $error->getMessage();
				}

				ob_end_clean();
			} else {
				$output = ob_get_clean();
			}

			if ( bricks_is_builder() || bricks_is_builder_call() ) {
				wp_reset_postdata();
			}

			// STEP: Get CSS code
			$css_code = $settings['cssCode'] ?? '';
			if ( $css_code ) {
				// Wrap in <style> tags if necessary
				if ( strpos( $css_code, '<style>' ) === false ) {
					$css_code = "<style>$css_code</style>";
				}

				$output .= trim( $css_code );
			}

			// STEP: Get JavaScript code
			$js_code = $settings['javascriptCode'] ?? '';
			if ( $js_code ) {
				// Regular expression to detect script tags, including those with attributes
				$script_tag_pattern = '/<script\b[^>]*>.*?<\/script>/is';

				// Wrap the code in <script> tags if no valid script tags are found
				if ( ! preg_match( $script_tag_pattern, $js_code ) ) {
					$js_code = "<script>$js_code</script>";
				}

				// Append the resulting JavaScript code to the output
				$output .= trim( $js_code );
			}

			// No root wrapper (frontend only, wrapper required in builder to get all inner nodes)
			$no_root = isset( $settings['noRootForce'] ) || isset( $settings['noRoot'] );
			if ( $no_root &&
				( ! bricks_is_builder() && ! bricks_is_builder_call() ) ||
				( isset( $settings['isStaticArea'] ) && $settings['isStaticArea'] === true )
			) {
				echo $output;
			} else {
				if ( $no_root ) {
					$this->attributes['_root']['id'] = '';
				}

				echo "<div {$this->render_attributes('_root')}>{$output}</div>";
			}
		}

		// STEP: Render code snippet
		else {
			$output = '';

			if ( ! empty( $settings['code'] ) ) {
				$output .= $this->get_code_snippet( $settings['code'], 'html' );
			}

			if ( ! empty( $settings['cssCode'] ) ) {
				$output .= $this->get_code_snippet( $settings['cssCode'], 'css' );
			}

			if ( ! empty( $settings['javascriptCode'] ) ) {
				$output .= $this->get_code_snippet( $settings['javascriptCode'], 'js' );
			}

			echo "<div {$this->render_attributes('_root')}>";
			echo $output;
			echo '</div>';
		}
	}

	public function get_code_snippet( $code, $language ) {
		if ( ! empty( $this->settings['prettify'] ) ) {
			$theme = $this->settings['prettify'];
		} elseif ( ! empty( $this->theme_styles['prettify'] ) ) {
			$theme = $this->theme_styles['prettify'];
		} else {
			$theme = false;
		}

		$code = esc_html( $code );

		// Code is already formatted (set language if necessary)
		if ( strpos( $code, '<pre' ) === 0 ) {
			return $theme ? str_replace( 'class="prettyprint', 'class="prettyprint lang-' . $language . ' ', $code ) : $code;
		}

		// Prettyprint theme
		if ( $theme ) {
			return "<pre class=\"prettyprint $theme lang-$language\"><code>$code</code></pre>";
		}

		// Default: Code snippet
		return "<pre {$this->render_attributes('_root')}>$code</pre>";
	}

	public function convert_element_settings_to_block( $settings ) {
		if ( isset( $settings['executeCode'] ) ) {
			return;
		}

		$code = '';

		if ( ! empty( $settings['useDynamicData'] ) ) {
			$code = $this->render_dynamic_data_tag( $settings['useDynamicData'] );

			// If code comes already formatted, extract the code only
			if ( strpos( $code, '<pre' ) === 0 ) {
				preg_match( '#<\s*?code\b[^>]*>(.*?)</code\b[^>]*>#s', $code, $matches );
				$code = isset( $matches[1] ) ? $matches[1] : $code;
			}
		} else {
			$code = ! empty( $settings['code'] ) ? trim( $settings['code'] ) : '';

			if ( ! empty( $settings['cssCode'] ) ) {
				$css_code = $settings['cssCode'];
				$code    .= "<style>$css_code</style>\n";
			}

			if ( ! empty( $settings['javascriptCode'] ) ) {
				$js_code = $settings['javascriptCode'];
				$code   .= "<script>$js_code</script>\n";
			}
		}

		$html = '<pre class="wp-block-code"><code>' . esc_html( $code ) . '</code></pre>';

		$block = [
			'blockName'    => 'core/code',
			'attrs'        => [],
			'innerContent' => [ $html ],
		];

		return $block;
	}

	public function convert_block_to_element_settings( $block, $attributes ) {
		$code = trim( $block['innerHTML'] );
		$code = substr( $code, strpos( $code, '>' ) + 1 ); // Remove starting <pre>
		$code = substr_replace( $code, '', -6 ); // Remove last </pre>

		// Remove <code> (core/code block)
		if ( substr( $code, 0, 6 ) === '<code>' ) {
			$code = substr( $code, strpos( $code, '>' ) + 1 ); // Remove starting <code>
			$code = substr_replace( $code, '', -7 ); // Remove last </code>
		}

		return [ 'code' => $code ];
	}
}
