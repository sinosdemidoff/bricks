<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Settings_Template extends Settings_Base {
	public function set_control_groups() {
		// Header template
		if ( Templates::get_template_type() === 'header' ) {
			$this->control_groups['header'] = [
				'title' => esc_html__( 'Header', 'bricks' ),
			];
		}

		// Popup template (@since 1.6)
		elseif ( Templates::get_template_type() === 'popup' ) {
			$this->control_groups['popup'] = [
				'title' => esc_html__( 'Popup', 'bricks' ),
			];
		}

		$this->control_groups['template-conditions'] = [
			'title'    => esc_html__( 'Conditions', 'bricks' ),
			'required' => [
				[ 'popupIsInfoBox', '!=', true ],
			],
		];

		// Add password protection group for password protected templates (@since 1.11.1)
		if ( in_array( Templates::get_template_type(), [ 'password_protection' ], true ) ) {
			$this->control_groups['password-protected'] = [
				'title' => esc_html__( 'Password protection', 'bricks' ),
			];
		}

		$this->control_groups['template-preview'] = [
			'title' => esc_html__( 'Populate Content', 'bricks' ),
		];
	}

	public function set_controls() {
		$registered_post_types = Helpers::get_registered_post_types();

		if ( Templates::get_template_type() === 'header' || Templates::get_template_type() === 'footer' ) {
			$registered_post_types[ BRICKS_DB_TEMPLATE_SLUG ] = esc_html__( 'Template', 'bricks' );
		}

		$supported_content_types = bricks_is_builder() ? Helpers::get_supported_content_types() : [];

		/**
		 * Header
		 */

		$this->controls['headerPosition'] = [
			'group'       => 'header',
			'label'       => esc_html__( 'Header location', 'bricks' ),
			'type'        => 'select',
			'options'     => [
				'right' => esc_html__( 'Right', 'bricks' ),
				'left'  => esc_html__( 'Left', 'bricks' ),
			],
			'inline'      => true,
			'placeholder' => esc_html__( 'Top', 'bricks' ),
		];

		$this->controls['headerWidth'] = [
			'group'       => 'header',
			'label'       => esc_html__( 'Header width', 'bricks' ),
			'type'        => 'number',
			'units'       => true,
			'css'         => [
				[
					'property' => 'width',
					'selector' => '.brx-header-right #brx-header, .brx-header-left #brx-header',
				],

				// Header position: Right
				[
					'property' => 'margin-right',
					'selector' => '.brx-header-right #brx-content, .brx-header-right #brx-footer',
				],

				// Header position: Left
				[
					'property' => 'margin-left',
					'selector' => '.brx-header-left #brx-content, .brx-header-left #brx-footer',
				],
			],
			'placeholder' => '200px',
			'required'    => [ 'headerPosition', '!=', '' ],
		];

		$this->controls['headerAbsolute'] = [
			'group'      => 'header',
			'label'      => esc_html__( 'Absolute header', 'bricks' ),
			'type'       => 'checkbox',
			'css'        => [
				[
					'property' => 'position',
					'selector' => '#brx-header',
					'value'    => 'absolute',
				],
				[
					'property' => 'width',
					'selector' => '#brx-header',
					'value'    => '100%',
				],
			],
			'deprecated' => true, // @since 1.3.2 (set 'headerPos' instead)
			'required'   => [ 'headerPosition', '=', '' ],
		];

		// Sticky header

		$this->controls['headerStickySeparator'] = [
			'group'    => 'header',
			'label'    => esc_html__( 'Sticky header', 'bricks' ),
			'type'     => 'separator',
			'required' => [ 'headerPosition', '=', '' ],
		];

		$this->controls['headerSticky'] = [
			'group'    => 'header',
			'label'    => esc_html__( 'Sticky header', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [ 'headerPosition', '=', '' ],
		];

		// Position header 'relative' on page load to not cover the content below. Set to fixed on scroll.
		$this->controls['headerStickyOnScroll'] = [
			'group'    => 'header',
			'label'    => esc_html__( 'Sticky on scroll', 'bricks' ),
			'type'     => 'checkbox',
			'required' => [
				[ 'headerPosition', '=', '' ],
				[ 'headerSticky', '!=', '' ],
			],
		];

		$this->controls['headerStickySlideUpAfter'] = [
			'group'    => 'header',
			'label'    => esc_html__( 'Slide up after', 'bricks' ) . ' (px)',
			'type'     => 'number',
			'required' => [
				[ 'headerPosition', '=', '' ],
				[ 'headerSticky', '!=', '' ],
			],
		];

		$this->controls['headerStickyScrollingColor'] = [
			'group'    => 'header',
			'label'    => esc_html__( 'Scrolling text color', 'bricks' ),
			'type'     => 'color',
			'css'      => [
				// Logo
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brxe-logo',
				],

				// Nav Menu
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .bricks-nav-menu > li > a',
				],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .bricks-nav-menu > li > .brx-submenu-toggle > *',
				],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brxe-nav-menu .bricks-mobile-menu-toggle',
				],

				// Nav (Nestable)
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brx-nav-nested-items > li > a',
				],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brx-nav-nested-items > li > .brx-submenu-toggle > *',
				],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brxe-nav-nested > .brxe-toggle .brxa-inner',
				],

				// Search
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brxe-search',
				],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brxe-search button',
				],
			],
			'required' => [
				[ 'headerPosition', '=', '' ],
				[ 'headerSticky', '!=', '' ],
			],
		];

		// Background color of the sticky header when scrolling, on hover (@since 2.0)
		$this->controls['headerStickyScrollingColorHover'] = [
			'group'    => 'header',
			'label'    => esc_html__( 'Scrolling text color', 'bricks' ) . ' (' . esc_html__( 'Hover', 'bricks' ) . ')',
			'type'     => 'color',
			'css'      => [
				// Logo
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brxe-logo:hover',
				],

				// Nav Menu
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .bricks-nav-menu > li > a:hover',
				],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .bricks-nav-menu > li > .brx-submenu-toggle:hover > *',
				],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brxe-nav-menu .bricks-mobile-menu-toggle:hover',
				],

				// Nav (Nestable)
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brx-nav-nested-items > li > a:hover',
				],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brx-nav-nested-items > li > .brx-submenu-toggle:hover > *',
				],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brxe-nav-nested > .brxe-toggle:hover .brxa-inner',
				],

				// // Search
				// [
				// 'property' => 'color',
				// 'selector' => '#brx-header.brx-sticky.scrolling .brxe-search',
				// ],
				[
					'property' => 'color',
					'selector' => '#brx-header.brx-sticky.scrolling .brxe-search button:hover',
				],
			],
			'required' => [
				[ 'headerPosition', '=', '' ],
				[ 'headerSticky', '!=', '' ],
			],
		];

		$this->controls['headerStickyScrollingBackground'] = [
			'group'    => 'header',
			'label'    => esc_html__( 'Scrolling background', 'bricks' ),
			'type'     => 'background',
			'css'      => [
				[
					'property' => 'background',
					'selector' => '
						#brx-header.brx-sticky.scrolling > .brxe-section,
						#brx-header.brx-sticky.scrolling > .brxe-container,
						#brx-header.brx-sticky.scrolling > .brxe-block,
						#brx-header.brx-sticky.scrolling > .brxe-div',
				],
			],
			'required' => [
				[ 'headerPosition', '=', '' ],
				[ 'headerSticky', '!=', '' ],
			],
		];

		$this->controls['headerStickyScrollingBoxShadow'] = [
			'group'    => 'header',
			'label'    => esc_html__( 'Scrolling box shadow', 'bricks' ),
			'type'     => 'box-shadow',
			'css'      => [
				[
					'property' => 'box-shadow',
					'selector' => '
						#brx-header.brx-sticky.scrolling:not(.slide-up) > .brxe-section,
						#brx-header.brx-sticky.scrolling:not(.slide-up) > .brxe-container,
						#brx-header.brx-sticky.scrolling:not(.slide-up) > .brxe-block,
						#brx-header.brx-sticky.scrolling:not(.slide-up) > .brxe-div',
				],
			],
			'required' => [
				[ 'headerPosition', '=', '' ],
				[ 'headerSticky', '!=', '' ],
			],
		];

		$this->controls['headerStickyTransition'] = [
			'group'          => 'header',
			'label'          => esc_html__( 'Transition', 'bricks' ),
			'type'           => 'text',
			'placeholder'    => 'background-color 0.2s, transform 0.4s',
			'hasDynamicData' => false,
			'css'            => [
				[
					'selector' => '#brx-header.brx-sticky',
					'property' => 'transition',
				],
				[
					'selector' => '
						#brx-header.brx-sticky > .brxe-section,
						#brx-header.brx-sticky > .brxe-container,
						#brx-header.brx-sticky > .brxe-block,
						#brx-header.brx-sticky > .brxe-div',
					'property' => 'transition',
				],

				// Logo
				[
					'selector' => '#brx-header.brx-sticky .brxe-logo',
					'property' => 'transition',
				],

				// Nav menu
				[
					'selector' => '#brx-header.brx-sticky .bricks-nav-menu > li > a',
					'property' => 'transition',
				],
				[
					'selector' => '#brx-header.brx-sticky .bricks-nav-menu > li > .brx-submenu-toggle > a',
					'property' => 'transition',
				],
				[
					'selector' => '#brx-header.brx-sticky .bricks-nav-menu > li > .brx-submenu-toggle > button > *',
					'property' => 'transition',
				],

				// Nav (Nestable)
				[
					'selector' => '#brx-header.brx-sticky .brx-nav-nested-items > li > a',
					'property' => 'transition',
				],
				[
					'selector' => '#brx-header.brx-sticky .brx-nav-nested-items > li > .brx-submenu-toggle',
					'property' => 'transition',
				],
				[
					'selector' => '#brx-header.brx-sticky .brx-nav-nested-items > li > .brx-submenu-toggle > *',
					'property' => 'transition',
				],
				[
					'selector' => '#brx-header.brx-sticky .brxe-nav-nested > .brxe-toggle .brxa-inner',
					'property' => 'transition',
				],

				// Search
				[
					'selector' => '#brx-header.brx-sticky .brxe-search',
					'property' => 'transition',
				],
				[
					'selector' => '#brx-header.brx-sticky .brxe-search button',
					'property' => 'transition',
				],
			],
			'required'       => [
				[ 'headerPosition', '=', '' ],
				[ 'headerSticky', '!=', '' ],
			],
		];

		/**
		 * Popup
		 *
		 * @since 1.6
		 */
		$popup_controls = Popups::get_controls();

		// Get popup controls from theme style controls
		if ( is_array( $popup_controls ) ) {
			foreach ( $popup_controls as $key => $popup_control ) {
				$this->controls[ $key ] = $popup_control;
			}
		}

		/**
		 * Interactions
		 *
		 * @since 1.6
		 */
		$this->controls['popupInteractionsSep'] = [
			'group'       => 'popup',
			'label'       => esc_html__( 'Interactions', 'bricks' ),
			'description' => esc_html__( 'Set interactions for this popup.', 'bricks' ),
			'type'        => 'separator',
			'required'    => [ 'popupIsInfoBox', '!=', true ],
		];

		// Control key: template_interactions
		$this->controls['template_interactions']          = Interactions::get_controls_data();
		$this->controls['template_interactions']['group'] = 'popup';

		// Template interactions not for Info Box popup (@since 2.0)
		$this->controls['template_interactions']['required'] = [ 'popupIsInfoBox', '!=', true ];

		// Add special popup triggers
		$this->controls['template_interactions']['fields']['trigger']['options']['popupGroupTitle'] = esc_html__( 'Popup', 'bricks' );
		$this->controls['template_interactions']['fields']['trigger']['options']['showPopup']       = esc_html__( 'Show popup', 'bricks' );
		$this->controls['template_interactions']['fields']['trigger']['options']['hidePopup']       = esc_html__( 'Hide popup', 'bricks' );

		// Show info about "Hide popup" trigger
		$this->controls['template_interactions']['fields'] = [
			'hidePopupInfo' => [
				'type'     => 'info',
				'content'  => esc_html__( 'Target a "CSS selector" on "Hide popup", but not a popup directly! As this action runs after the popup has been closed.', 'bricks' ),
				'required' => [
					[ 'trigger', '=', 'hidePopup' ],
				]
			]
		] + $this->controls['template_interactions']['fields'];

		/**
		 * Template Conditions
		 */

		$this->controls['templateConditionsInfo'] = [
			'group'   => 'template-conditions',
			'type'    => 'info',
			'content' => esc_html__( 'Set condition(s) to show template on specific areas of your site.', 'bricks' ),
		];

		$this->controls['templateConditions'] = [
			'group'         => 'template-conditions',
			'type'          => 'repeater',
			'placeholder'   => esc_html__( 'Condition', 'bricks' ),
			'titleProperty' => 'main',
			'fields'        => [
				'main'                        => [
					'type'        => 'select',
					'options'     => [
						'any'         => esc_html__( 'Entire website', 'bricks' ),
						'frontpage'   => esc_html__( 'Front page', 'bricks' ),
						'postType'    => esc_html__( 'Post type', 'bricks' ),
						'archiveType' => esc_html__( 'Archive', 'bricks' ),
						'search'      => esc_html__( 'Search results', 'bricks' ),
						'error'       => esc_html__( 'Error page', 'bricks' ),
						'terms'       => esc_html__( 'Terms', 'bricks' ),
						'ids'         => esc_html__( 'Individual', 'bricks' ),
					],
					'placeholder' => esc_html__( 'Select', 'bricks' ),
				],

				'archiveType'                 => [
					'type'        => 'select',
					'label'       => esc_html__( 'Archive type', 'bricks' ),
					'options'     => [
						'any'      => esc_html__( 'All archives', 'bricks' ),
						'postType' => esc_html__( 'Post type', 'bricks' ),
						'author'   => esc_html__( 'Author', 'bricks' ),
						'date'     => esc_html__( 'Date', 'bricks' ),
						'term'     => esc_html__( 'Categories & Tags', 'bricks' ),
					],
					'multiple'    => true,
					'placeholder' => esc_html__( 'Select archive type', 'bricks' ),
					'required'    => [ 'main', '=', 'archiveType' ],
				],

				'archivePostTypes'            => [
					'type'        => 'select',
					'label'       => esc_html__( 'Archive post types', 'bricks' ),
					'options'     => $registered_post_types,
					'multiple'    => true,
					'placeholder' => esc_html__( 'Select post type', 'bricks' ),
					'description' => esc_html__( 'Leave empty to apply template to all post types.', 'bricks' ),
					'required'    => [ 'archiveType', '=', 'postType' ],
				],

				'archiveTerms'                => [
					'type'        => 'select',
					'label'       => esc_html__( 'Archive terms', 'bricks' ),
					'multiple'    => true,
					'searchable'  => true,
					'optionsAjax' => [
						'action'                => 'bricks_get_terms_options',
						'postTypes'             => [ 'any' ],
						'addLanguageToTermName' => true,
						'includeAll'            => true,
					], // (@since 1.12)
					'placeholder' => esc_html__( 'Select archive term', 'bricks' ),
					'description' => esc_html__( 'Leave empty to apply template to all archive terms.', 'bricks' ),
					'required'    => [ 'archiveType', '=', 'term' ],
				],

				'archiveTermsIncludeChildren' => [
					'type'     => 'checkbox',
					'label'    => esc_html__( 'Apply to child terms', 'bricks' ),
					'required' => [ 'archiveType', '=', 'term' ],
				],

				'postType'                    => [
					'type'        => 'select',
					'label'       => esc_html__( 'Post type', 'bricks' ),
					'options'     => $registered_post_types,
					'multiple'    => true,
					'placeholder' => esc_html__( 'Select post type', 'bricks' ),
					'required'    => [ 'main', '=', 'postType' ],
				],

				'terms'                       => [
					'type'        => 'select',
					'label'       => esc_html__( 'Terms', 'bricks' ),
					'multiple'    => true,
					'searchable'  => true,
					'optionsAjax' => [
						'action'                => 'bricks_get_terms_options',
						'postTypes'             => [ 'any' ],
						'addLanguageToTermName' => true,
					], // (@since 1.12)
					'placeholder' => esc_html__( 'Select terms', 'bricks' ),
					'required'    => [ 'main', '=', 'terms' ],
				],

				'ids'                         => [
					'type'        => 'select',
					'label'       => esc_html__( 'Individual', 'bricks' ),
					'optionsAjax' => [
						'action'                 => 'bricks_get_posts',
						'postType'               => 'any',
						'addLanguageToPostTitle' => true,
					],
					'multiple'    => true,
					'searchable'  => true,
					'placeholder' => esc_html__( 'Select individual', 'bricks' ),
					'required'    => [ 'main', '=', 'ids' ],
				],

				'idsIncludeChildren'          => [
					'type'     => 'checkbox',
					'label'    => esc_html__( 'Apply to child pages', 'bricks' ),
					'required' => [ 'main', '=', 'ids' ],
				],

				// @since 1.9.1
				'hookName'                    => [
					'type'           => 'text',
					'label'          => esc_html__( 'Hook', 'bricks' ) . ': ' . esc_html__( 'Name', 'bricks' ),
					'placeholder'    => 'bricks_before_footer',
					'hasDynamicData' => false,
					'required'       => [
						[ 'main', '!=', '' ],
					],
				],

				// @since 1.9.1
				'hookPriority'                => [
					'type'           => 'number',
					'label'          => esc_html__( 'Hook', 'bricks' ) . ': ' . esc_html__( 'Priority', 'bricks' ),
					'placeholder'    => 10,
					'units'          => false,
					'hasDynamicData' => false,
					'required'       => [
						[ 'main', '!=', '' ],
						[ 'hookName', '!=', '' ],
					],
				],

				'exclude'                     => [
					'type'  => 'checkbox',
					'label' => esc_html__( 'Exclude', 'bricks' ),
				],
			],
		];

		/**
		 * Not a section template: Remove hook options in builder only (not wp-admin)
		 *
		 * @since 1.9.1
		 */
		if ( bricks_is_builder() && Templates::get_template_type() !== 'section' ) {
			unset( $this->controls['templateConditions']['fields']['main']['options']['hook'] );
			unset( $this->controls['templateConditions']['fields']['hookName'] );
			unset( $this->controls['templateConditions']['fields']['hookPriority'] );
		}

		/**
		 * Password protected
		 *
		 * @since 1.11.1
		 */
		$this->controls['passwordProtectionSource'] = [
			'group'       => 'password-protected',
			'type'        => 'select',
			'options'     => [
				'both'      => esc_html__( 'Template & post password', 'bricks' ),
				'bricks'    => esc_html__( 'Template password', 'bricks' ),
				'wordpress' => esc_html__( 'Post password', 'bricks' ),
			],
			'label'       => esc_html__( 'Password source', 'bricks' ),
			'desc'        => sprintf(
				// Translators: %s = Article link
				'%s %s',
				esc_html__( 'Choose how passwords are managed. "Template password" uses this template\'s password to protect content according to template conditions. "Post password" protects content that has an individual post password set. "Template & post password" uses the template password, but individual post passwords will be used instead if set.', 'bricks' ),
				Helpers::article_link( 'password-protection/#password-source-options', esc_html__( 'Learn more', 'bricks' ) )
			),
			'placeholder' => esc_html__( 'Template & post password', 'bricks' ),
		];

		$this->controls['passwordProtectionError'] = [
			'group'    => 'password-protected',
			'type'     => 'info',
			'error'    => true,
			'content'  => esc_html__( 'This template is currently not rendered on pages that meet the template conditions as no password has been set.', 'bricks' ),
			'required' => [
				[ 'passwordProtectionSource', '!=', 'wordpress' ],
				[ 'passwordProtectionPassword', '=', '' ],
			],
		];

		$this->controls['passwordProtectionPassword'] = [
			'group'    => 'password-protected',
			'type'     => 'text',
			'inline'   => true,
			'dd'       => false, // Don't allow to set password via dynamic data
			'label'    => esc_html__( 'Password', 'bricks' ),
			'required' => [ 'passwordProtectionSource', '!=', 'wordpress' ],
		];

		$this->controls['passwordProtectionExcludeHeader'] = [
			'group'      => 'password-protected',
			'type'       => 'checkbox',
			'label'      => esc_html__( 'Disable header', 'bricks' ),
			'required'   => [ 'passwordProtectionSource', '!=', 'wordpress' ],
			'deprecated' => true, // @since 2.0
		];

		$this->controls['passwordProtectionExcludeFooter'] = [
			'group'      => 'password-protected',
			'type'       => 'checkbox',
			'label'      => esc_html__( 'Disable footer', 'bricks' ),
			'required'   => [ 'passwordProtectionSource', '!=', 'wordpress' ],
			'deprecated' => true, // @since 2.0
		];

		$this->controls['passwordProtectionExcludePopups'] = [
			'group'      => 'password-protected',
			'type'       => 'checkbox',
			'label'      => esc_html__( 'Disable popups', 'bricks' ),
			'required'   => [ 'passwordProtectionSource', '!=', 'wordpress' ],
			'deprecated' => true, // @since 2.0
		];

		$this->controls['passwordProtectionBypassLoggedIn'] = [
			'group'    => 'password-protected',
			'type'     => 'checkbox',
			'label'    => esc_html__( 'Disable for logged-in users', 'bricks' ),
			'required' => [ 'passwordProtectionSource', '!=', 'wordpress' ],
		];

		$this->controls['passwordProtectionSchedule'] = [
			'group'    => 'password-protected',
			'type'     => 'checkbox',
			'label'    => esc_html__( 'Schedule', 'bricks' ),
			'required' => [ 'passwordProtectionSource', '!=', 'wordpress' ],
		];

		$this->controls['passwordProtectionStartDate'] = [
			'group'       => 'password-protected',
			'type'        => 'datepicker',
			'label'       => esc_html__( 'Start date', 'bricks' ),
			'info'        => esc_html__( 'Timezone', 'bricks' ) . ': UTC+0',
			'placeholder' => esc_html__( 'Select start date', 'bricks' ),
			'options'     => [
				'enableTime' => true,
				'altInput'   => true,
			],
			'required'    => [
				[ 'passwordProtectionSchedule', '=', true ],
				[ 'passwordProtectionSource', '!=', 'wordpress' ],
			],
		];

		$this->controls['passwordProtectionEndDate'] = [
			'group'       => 'password-protected',
			'type'        => 'datepicker',
			'label'       => esc_html__( 'End date', 'bricks' ),
			'info'        => esc_html__( 'Timezone', 'bricks' ) . ': UTC+0',
			'placeholder' => esc_html__( 'Select end date', 'bricks' ),
			'options'     => [
				'enableTime' => true,
				'altInput'   => true,
			],
			'required'    => [
				[ 'passwordProtectionSchedule', '=', true ],
				[ 'passwordProtectionSource', '!=', 'wordpress' ],
			],
		];

		/**
		 * Template Preview Content (Only visible when editing header or footer templates)
		 */

		$this->controls['templatePreviewInfo'] = [
			'group'   => 'template-preview',
			'type'    => 'info',
			'content' => esc_html__( 'Select type of content to show on canvas, then click "APPLY PREVIEW" to show the selected content on the canvas.', 'bricks' ),
		];

		$this->controls['templatePreviewType'] = [
			'group'       => 'template-preview',
			'type'        => 'select',
			'label'       => esc_html__( 'Content type', 'bricks' ),
			'options'     => $supported_content_types,
			'searchable'  => true,
			'placeholder' => esc_html__( 'Search', 'bricks' ) . ' / ' . esc_html__( 'Select content type', 'bricks' ),
		];

		$this->controls['templatePreviewAuthor'] = [
			'group'       => 'template-preview',
			'type'        => 'select',
			'label'       => esc_html__( 'Author', 'bricks' ),
			'optionsAjax' => [
				'action' => 'bricks_get_users',
			],
			'searchable'  => true,
			'placeholder' => esc_html__( 'Search', 'bricks' ) . ' / ' . esc_html__( 'Select author', 'bricks' ),
			'required'    => [ 'templatePreviewType', '=', 'archive-author' ],
		];

		$this->controls['templatePreviewPostType'] = [
			'group'       => 'template-preview',
			'type'        => 'select',
			'label'       => esc_html__( 'Post type', 'bricks' ),
			'options'     => $registered_post_types,
			'searchable'  => true,
			'placeholder' => esc_html__( 'Search' ) . ' / ' . esc_html__( 'Select post type', 'bricks' ),
			'required'    => [ 'templatePreviewType', '=', 'archive-cpt' ],
		];

		$this->controls['templatePreviewTerm'] = [
			'group'       => 'template-preview',
			'type'        => 'select',
			'label'       => esc_html__( 'Term', 'bricks' ),
			'searchable'  => true,
			'optionsAjax' => [
				'action'                => 'bricks_get_terms_options',
				'postTypes'             => [ 'any' ],
				'addLanguageToTermName' => true,
			], // (@since 1.12)
			'placeholder' => esc_html__( 'Search', 'bricks' ) . ' / ' . esc_html__( 'Select term', 'bricks' ),
			'required'    => [ 'templatePreviewType', '=', 'archive-term' ],
		];

		$this->controls['templatePreviewSearchTerm'] = [
			'group'       => 'template-preview',
			'type'        => 'text',
			'label'       => esc_html__( 'Search term', 'bricks' ),
			'searchable'  => true,
			'placeholder' => esc_html__( 'Enter search term', 'bricks' ),
			'required'    => [ 'templatePreviewType', '=', 'search' ],
		];

		$this->controls['templatePreviewPostId'] = [
			'group'       => 'template-preview',
			'type'        => 'select',
			'label'       => esc_html__( 'Single', 'bricks' ) . ' (' . esc_html__( 'Post', 'bricks' ) . '/' . esc_html__( 'Page', 'bricks' ) . '/' . 'CPT' . ')',
			'optionsAjax' => [
				'action'                 => 'bricks_get_posts',
				'postType'               => 'any',
				'addLanguageToPostTitle' => true,
			],
			'searchable'  => true,
			'placeholder' => esc_html__( 'Search', 'bricks' ) . ' / ' . esc_html__( 'Select', 'bricks' ),
			'required'    => [ 'templatePreviewType', '=', 'single' ],
		];

		$this->controls['apply'] = [
			'group'  => 'template-preview',
			'type'   => 'apply',
			'reload' => true,
			'label'  => esc_html__( 'Apply preview', 'bricks' ),
		];
	}
}
