<?php
namespace Bricks;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Helpers {
	/**
	 * Get template data from post meta
	 *
	 * @since 1.0
	 */
	public static function get_template_settings( $post_id ) {
		return get_post_meta( $post_id, BRICKS_DB_TEMPLATE_SETTINGS, true );
	}

	/**
	 * Store template settings
	 *
	 * @since 1.0
	 */
	public static function set_template_settings( $post_id, $settings ) {
		update_post_meta( $post_id, BRICKS_DB_TEMPLATE_SETTINGS, $settings );
	}

	/**
	 * Remove template settings from store
	 *
	 * @since 1.0
	 */
	public static function delete_template_settings( $post_id ) {
		delete_post_meta( $post_id, BRICKS_DB_TEMPLATE_SETTINGS );
	}

	/**
	 * Get individual template setting by key
	 *
	 * @since 1.0
	 */
	public static function get_template_setting( $key, $post_id ) {
		$template_settings = self::get_template_settings( $post_id );

		return isset( $template_settings[ $key ] ) ? $template_settings[ $key ] : '';
	}

	/**
	 * Store a specific template setting
	 *
	 * @since 1.0
	 */
	public static function set_template_setting( $post_id, $key, $setting_value ) {
		$template_settings = self::get_template_settings( $post_id );

		if ( ! is_array( $template_settings ) ) {
			$template_settings = [];
		}

		$template_settings[ $key ] = $setting_value;

		self::set_template_settings( $post_id, $template_settings );
	}

	/**
	 * Get terms
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $post_type Post type name.
	 * @param string $include_all Includes meta terms like "All terms (taxonomy name)".
	 * @param string $search Search term. (@since 1.12)
	 * @since 1.0
	 */
	public static function get_terms_options( $taxonomy = null, $post_type = null, $include_all = false, $search = '' ) {
		// Always limit to 100 terms for performance reasons (@since 1.12)
		$term_args = [
			'hide_empty' => false,
			'number'     => 100,
			'lang'       => '', // Get all terms in builder for Polylang (@since 2.0)
		];

		if ( isset( $taxonomy ) ) {
			$term_args['taxonomy'] = $taxonomy;
		}

		$search = trim( $search );
		if ( ! empty( $search ) ) {
			$term_args['search'] = $search;
		}

		$cache_key = 'get_terms_options' . md5( 'taxonomy' . wp_json_encode( $taxonomy ) . 'post_type' . wp_json_encode( $post_type ) . 'include' . $include_all . 'search' . $search );

		$response = wp_cache_get( $cache_key, 'bricks' );

		if ( $response !== false ) {
			return $response;
		}

		/**
		 * New include_all logic
		 * Should not get from get_terms() since we are searching by search term. We can use this to exclude non-registered taxonomies terms too.
		 *
		 * @since 1.12
		 */
		$all_taxonomies = get_taxonomies( [], 'objects' );
		// We only need the labels and names
		$all_taxonomies = array_map(
			function( $tax ) {
				return [
					'name'  => $tax->name,
					'label' => $tax->label
				];
			},
			$all_taxonomies
		);

		// Allow exlucde taxonomies from the list (@since 2.0)
		$excluded_taxonomies = (array) apply_filters(
			'bricks/get_terms_options/excluded_taxonomies',
			[
				'nav_menu',
				'link_category',
				'post_format',
			// BRICKS_DB_TEMPLATE_TAX_TAG
			]
		);

		// Filter out excluded taxonomies
		$all_taxonomies = array_filter(
			$all_taxonomies,
			static function( $tax ) use ( $excluded_taxonomies ) {
				return ! in_array( $tax['name'], $excluded_taxonomies, true );
			}
		);

		// Extract valid taxonomy slugs
		$valid_tax_slugs = array_column( $all_taxonomies, 'name' );

		if ( ! isset( $taxonomy ) ) {
			// (Undocumented) For large site with humongous amount of taxonomies, but don't want to increase Memory Limit
			$enable_limit = apply_filters( 'bricks/get_terms_options/enable_limit', false );

			if ( $enable_limit ) {
				/**
				 * Get the first 20 taxonomies if there are more than 20
				 *
				 * Gradually increase the limit based on the search term length and cap it to 250
				 *
				 * Potential issues:
				 * If all terms with a very short slug, and the taxonomy is not in the first N taxonomies, can't get the expected term. No ideal solution for this yet.
				 */
				$limit         = 20;
				$search_length = isset( $term_args['search'] ) ? strlen( $search ) : 0;

				// if has search term, can increase the included taxonomies gradually (avoid memory exhaustion)
				if ( $search_length >= 3 ) {
					// If more than 3 characters, increase the limit
					$limit = 100 + ( ( $search_length - 3 ) * 50 );
				}

				if ( count( $valid_tax_slugs ) > $limit ) {
					$valid_tax_slugs = array_slice( $valid_tax_slugs, 0, $limit );
				}
			}

			// Always set the taxonomy parameter
			$term_args['taxonomy'] = $valid_tax_slugs;
		}

		$terms = get_terms( $term_args );

		$response = [];

		$all_terms = [];

		foreach ( $terms as $term ) {
			// Skip term if term taxonomy is not a taxonomy of requested post type
			if ( isset( $post_type ) && $post_type !== 'any' ) {
				$post_type_taxonomies = get_object_taxonomies( $post_type );

				if ( ! in_array( $term->taxonomy, $post_type_taxonomies ) ) {
					continue;
				}
			}

			// Skip if the term is not a valid taxonomy (Maybe a custom taxonomy that is not registered after a plugin/theme deactivation)
			if ( ! in_array( $term->taxonomy, $valid_tax_slugs, true ) ) {
				continue;
			}

			// Store taxonomy name and term ID as WP_Query tax_query needs both (name and term ID)
			$taxonomy_label = self::generate_taxonomy_label( $term->taxonomy );
			$taxonomy_label = ! empty( $taxonomy_label ) ? "($taxonomy_label)" : '';

			$response[ $term->taxonomy . '::' . $term->term_id ] = "{$term->name} {$taxonomy_label}";
		}

		if ( $include_all ) {
			// Build "All terms" option from all taxonomies
			foreach ( $all_taxonomies as $taxonomy ) {
				$all_terms[ $taxonomy['name'] . '::all' ] = esc_html__( 'All terms', 'bricks' ) . ' (' . $taxonomy['label'] . ')';
			}

			$response = array_merge( $all_terms, $response );
		}

		wp_cache_set( $cache_key, $response, 'bricks', 5 * MINUTE_IN_SECONDS );

		return $response;
	}

	/**
	 * Get users (for templatePreview)
	 *
	 * @param array $args Query args.
	 * @param bool  $show_role Show user role.
	 *
	 * @uses templatePreviewAuthor
	 *
	 * @since 1.0
	 */
	public static function get_users_options( $args, $show_role = false ) {
		$users = [];

		foreach ( get_users( $args ) as $user ) {
			$user_id = $user->ID;

			$user_roles = array_values( $user->roles );

			$value = get_the_author_meta( 'display_name', $user_id );

			if ( $show_role && ! empty( $user_roles[0] ) ) {
				global $wp_roles;

				$value .= ' (' . $wp_roles->roles[ $user_roles[0] ]['name'] . ')';
			}

			$users[ $user_id ] = $value;
		}

		return $users;
	}

	/**
	 * Get post edit link with appended query string to trigger builder
	 *
	 * @since 1.0
	 */
	public static function get_builder_edit_link( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$url = get_permalink( $post_id );
		$url = add_query_arg( BRICKS_BUILDER_PARAM, 'run', $url );

		// NOTE: Undocumented filter (@since 1.10)
		return apply_filters( 'bricks/get_builder_edit_link', $url, $post_id );
	}

	/**
	 * Get supported post types
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_supported_post_types() {
		$supported_post_types = Database::get_setting( 'postTypes', [] );
		$post_types_options   = [];

		foreach ( $supported_post_types as $post_type_slug ) {
			if ( $post_type_slug === 'attachment' ) {
				continue;
			}

			$post_type_object = get_post_type_object( $post_type_slug );

			$post_types_options[ $post_type_slug ] = is_object( $post_type_object ) ? $post_type_object->labels->name : ucwords( str_replace( '_', ' ', $post_type_slug ) );
		}

		return $post_types_options;
	}

	/**
	 * Get registered post types
	 *
	 * Key: Post type name
	 * Value: Post type label
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_registered_post_types() {
		/**
		 * Hook to customise post type arguments
		 *
		 * Example: Return all registered post types, instead of only 'public' post types.
		 *
		 * https://academy.bricksbuilder.io/article/filter-bricks-registered_post_types_args/
		 *
		 * @since 1.6
		 */
		$registered_post_types_args = apply_filters(
			'bricks/registered_post_types_args',
			[
				'public' => true,
			]
		);

		$registered_post_types = get_post_types( $registered_post_types_args, 'objects' );

		// Remove post type: Bricks template (always has builder support)
		unset( $registered_post_types[ BRICKS_DB_TEMPLATE_SLUG ] );

		$post_types = [];

		foreach ( $registered_post_types as $key => $object ) {
			$post_types[ $key ] = $object->labels->singular_name ?? $object->label;
		}

		return $post_types;
	}

	/**
	 * Is current post type supported by builder
	 *
	 * @since 1.0
	 *
	 * @return boolean
	 */
	public static function is_post_type_supported( $post_id = 0 ) {
		$post_id = ! empty( $post_id ) ? $post_id : get_the_ID();

		// NOTE: Set post ID to posts page.
		if ( empty( $post_id ) && is_home() ) {
			$post_id = get_option( 'page_for_posts' );
		}

		$current_post_type = get_post_type( $post_id );

		// Bricks templates always have builder support
		if ( $current_post_type === BRICKS_DB_TEMPLATE_SLUG ) {
			return true;
		}

		$supported_post_types = Database::get_setting( 'postTypes', [] );

		return in_array( $current_post_type, $supported_post_types );
	}

	/**
	 * Return page-specific title
	 *
	 * @param int  $post_id
	 * @param bool $context
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_the_archive_title/
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function get_the_title( $post_id = 0, $context = false ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$preview_type = '';

		// Check if loading a Bricks template
		if ( self::is_bricks_template( $post_id ) ) {
			$preview_type = self::get_template_setting( 'templatePreviewType', $post_id );

			if ( $preview_type === 'archive-term' ) {
				$preview_term = self::get_template_setting( 'templatePreviewTerm', $post_id );
				if ( ! empty( $preview_term ) ) {
					$preview_term      = explode( '::', $preview_term );
					$preview_taxonomy  = isset( $preview_term[0] ) ? $preview_term[0] : '';
					$preview_term_id   = isset( $preview_term[1] ) ? intval( $preview_term[1] ) : '';
					$preview_term      = get_term_by( 'id', $preview_term_id, $preview_taxonomy );
					$preview_term_name = $preview_term ? $preview_term->name : '';
				}
			} elseif ( $preview_type == 'archive-cpt' ) {
				$preview_post_type = self::get_template_setting( 'templatePreviewPostType', $post_id );
			}
		}

		if ( Query::is_looping() ) {
			if ( Query::get_loop_object_type() === 'post' ) {
				// Looping post query we can retrieve the title via $post_id
				$title = get_the_title( $post_id );
			} else {
				// Looping but don't use $post_id as it might be a term id, author id, etc. (@since 1.11) (#86c0arcxn)
				$title = get_the_title();
			}
		} elseif ( is_home() ) {
			$post_id = get_option( 'page_for_posts' );
			$title   = get_the_title( $post_id );
		} elseif ( is_404() ) {
			$title = isset( Database::$active_templates['error'] ) ? get_the_title( Database::$active_templates['error'] ) : esc_html__( 'Page not found', 'bricks' );
		} elseif ( is_category() || ( isset( $preview_taxonomy ) && $preview_taxonomy === 'category' ) ) {
			$category = isset( $preview_term_name ) ? $preview_term_name : single_cat_title( '', false );
			$category = apply_filters( 'single_cat_title', $category );
			// translators: %s: Category name
			$title = $context ? sprintf( esc_html__( 'Category: %s', 'bricks' ), $category ) : $category;
		} elseif ( is_tag() || ( isset( $preview_taxonomy ) && $preview_taxonomy === 'post_tag' ) ) {
			$tag = isset( $preview_term_name ) ? $preview_term_name : single_tag_title( '', false );
			$tag = apply_filters( 'single_tag_title', $tag );
			// translators: %s: Tag name
			$title = $context ? sprintf( esc_html__( 'Tag: %s', 'bricks' ), $tag ) : $tag;
		} elseif ( is_author() || $preview_type === 'archive-author' ) {
			if ( $preview_type === 'archive-author' ) {
				// Get author ID from template preview (as no $authordata exists)
				$template_preview_author = self::get_template_setting( 'templatePreviewAuthor', $post_id );
				$author                  = get_the_author_meta( 'display_name', $template_preview_author );
			} else {
				// @since 1.7.1 - get_the_author() might be wrong if some other query is running on the author archive page
				$author = get_the_author_meta( 'display_name', $post_id );
			}
			$author = ! empty( $author ) ? $author : '';
			// translators: %s: Author name
			$title = $context ? sprintf( esc_html__( 'Author: %s', 'bricks' ), $author ) : $author;
		} elseif ( is_year() || $preview_type === 'archive-date' ) {
			$date = $preview_type === 'archive-date' ? date( 'Y' ) : get_the_date( _x( 'Y', 'yearly archives date format' ) );
			// translators: %s: Year
			$title = $context ? sprintf( esc_html__( 'Year: %s', 'bricks' ), $date ) : $date;
		} elseif ( is_month() ) {
			$date = get_the_date( _x( 'F Y', 'monthly archives date format' ) );
			// translators: %s: Month
			$title = $context ? sprintf( esc_html__( 'Month: %s', 'bricks' ), $date ) : $date;
		} elseif ( is_day() ) {
			$date = get_the_date( _x( 'F j, Y', 'daily archives date format' ) );
			// translators: %s: Day
			$title = $context ? sprintf( esc_html__( 'Day: %s', 'bricks' ), $date ) : $date;
		} elseif ( is_tax( 'post_format' ) ) {
			if ( is_tax( 'post_format', 'post-format-aside' ) ) {
				$title = esc_html__( 'Asides', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-gallery' ) ) {
				$title = esc_html__( 'Galleries', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-image' ) ) {
				$title = esc_html__( 'Images', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-video' ) ) {
				$title = esc_html__( 'Videos', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-quote' ) ) {
				$title = esc_html__( 'Quotes', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-link' ) ) {
				$title = esc_html__( 'Links', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-status' ) ) {
				$title = esc_html__( 'Statuses', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-audio' ) ) {
				$title = esc_html__( 'Audio', 'bricks' );
			} elseif ( is_tax( 'post_format', 'post-format-chat' ) ) {
				$title = esc_html__( 'Chats', 'bricks' );
			}
		} elseif ( is_tax() || isset( $preview_taxonomy ) ) {
			$tax = isset( $preview_taxonomy ) ? $preview_taxonomy : get_queried_object()->taxonomy;
			$tax = get_taxonomy( $tax );

			$term  = isset( $preview_term_name ) ? $preview_term_name : single_term_title( '', false );
			$term  = apply_filters( 'single_term_title', $term );
			$title = $context ? $tax->labels->singular_name . ': ' . $term : $term;
		} elseif ( is_post_type_archive() || ! empty( $preview_post_type ) ) {
			// Check if post type actually exists (@since 1.9.2)
			$post_type_obj = ! empty( $preview_post_type ) ? get_post_type_object( $preview_post_type ) : false;

			if ( $post_type_obj ) {
				$post_type_archive_title = apply_filters( 'post_type_archive_title', $post_type_obj->labels->name, $preview_post_type );
			} else {
				$post_type_archive_title = post_type_archive_title( '', false );
			}

			// translators: %s: Post type archive title
			$title = $context ? sprintf( esc_html__( 'Archives: %s', 'bricks' ), $post_type_archive_title ) : $post_type_archive_title;
		} elseif ( is_search() || $preview_type === 'search' ) {
			$search_query = $preview_type === 'search' ? self::get_template_setting( 'templatePreviewSearchTerm', $post_id ) : get_search_query();

			// translators: %s: Search query
			$title = $context ? sprintf( esc_html__( 'Results for: %s', 'bricks' ), $search_query ) : $search_query;

			if ( get_query_var( 'paged' ) ) {
				// translators: %s: Page number
				$title .= ' - ' . sprintf( esc_html__( 'Page %s', 'bricks' ), get_query_var( 'paged' ) );
			}
		} else {
			$preview_id = self::get_template_setting( 'templatePreviewPostId', $post_id );
			$preview_id = ! empty( $preview_id ) ? $preview_id : $post_id;
			$title      = get_the_title( $preview_id );
		}

		// NOTE: Undocumented
		return apply_filters( 'bricks/get_the_title', $title, $post_id );
	}

	/**
	 * Get the queried object which could also be set if previewing a template
	 *
	 * @see: https://developer.wordpress.org/reference/functions/get_queried_object/
	 *
	 * @param int $post_id
	 *
	 * @return WP_Term|WP_User|WP_Post|WP_Post_Type
	 */
	public static function get_queried_object( $post_id ) {
		$looping_query_id = Query::is_any_looping();
		$queried_object   = '';

		// Check if loading a Bricks template
		if ( self::is_bricks_template( $post_id ) ) {
			$preview_type = self::get_template_setting( 'templatePreviewType', $post_id );

			if ( $preview_type == 'single' ) {
				$preview_id     = self::get_template_setting( 'templatePreviewPostId', $post_id );
				$queried_object = get_post( $preview_id );
			} elseif ( $preview_type === 'archive-term' ) {
				$preview_term = self::get_template_setting( 'templatePreviewTerm', $post_id );

				if ( ! empty( $preview_term ) ) {
					$preview_term     = explode( '::', $preview_term );
					$preview_taxonomy = isset( $preview_term[0] ) ? $preview_term[0] : '';
					$preview_term_id  = isset( $preview_term[1] ) ? intval( $preview_term[1] ) : '';
					$queried_object   = get_term_by( 'id', $preview_term_id, $preview_taxonomy );
				}
			} elseif ( $preview_type == 'archive-cpt' ) {
				$preview_post_type = self::get_template_setting( 'templatePreviewPostType', $post_id );

				$queried_object = get_post_type_object( $preview_post_type );
			} elseif ( $preview_type == 'archive-author' ) {
				$template_preview_author = self::get_template_setting( 'templatePreviewAuthor', $post_id );

				$queried_object = get_user_by( 'id', $template_preview_author );
			}
		}

		// It is an ajax call but it is not inside a template
		// elseif ( bricks_is_ajax_call() && isset( $_POST['action'] ) && strpos( $_POST['action'], 'bricks_' ) === 0 ) {
		// Use is_bricks_preview() to apply for Api::render_element() too (@since 1.12.2)
		elseif ( self::is_bricks_preview() ) {
			$queried_object = get_post( $post_id );
		}

		// In a query loop
		elseif ( $looping_query_id ) {
			$queried_object = Query::get_loop_object( $looping_query_id );
		}

		if ( empty( $queried_object ) ) {
			$queried_object = get_queried_object();
		}

		return $queried_object;
	}

	/**
	 * Calculate the excerpt of a post (product, or any other cpt)
	 *
	 * @param WP_Post $post
	 * @param int     $excerpt_length
	 * @param string  $excerpt_more
	 * @param boolean $keep_html
	 */
	public static function get_the_excerpt( $post, $excerpt_length, $excerpt_more = null, $keep_html = false ) {
		$post = get_post( $post );

		if ( empty( $post ) ) {
			return '';
		}

		if ( post_password_required( $post ) ) {
			return esc_html__( 'There is no excerpt because this is a protected post.', 'bricks' );
		}

		/**
		 * Relevanssi compatibility (the modified excerpt is stored in the global post_excerpt field)
		 *
		 * Bricks will not trim the Relevanssi excerpt any further.
		 *
		 * @since 1.9.1
		 */
		if ( is_search() && function_exists( 'relevanssi_do_excerpt' ) ) {
			global $post;
			return $post->post_excerpt;
		}

		$text = $post->post_excerpt;

		// No excerpt, generate one
		if ( $text == '' ) {
			$text = get_the_content( '', false, $post );
			$text = strip_shortcodes( $text );
			$text = function_exists( 'excerpt_remove_blocks' ) ? excerpt_remove_blocks( $text ) : $text; // Run function_exists for ClassicPress
			$text = str_replace( ']]>', ']]&gt;', $text );
		}

		/**
		 * Apply excerpt length filter, if default $excerpt_length of 55 words is used
		 *
		 * To apply correct excerpt limit length in-loop in the builder: {post_excerpt:10}
		 *
		 * @since 1.8.6
		 */
		if ( $excerpt_length === 55 ) {
			$excerpt_length = apply_filters( 'excerpt_length', $excerpt_length );
		}

		$excerpt_more = isset( $excerpt_more ) ? $excerpt_more : '&hellip;';

		$excerpt_more = apply_filters( 'excerpt_more', $excerpt_more );

		$text = self::trim_words( $text, $excerpt_length, $excerpt_more, $keep_html );

		/**
		 * Filters the trimmed excerpt string.
		 *
		 * @param string $text The trimmed text.
		 * @param string $raw_excerpt The text prior to trimming.
		 *
		 * @since 2.8.0
		 */
		return apply_filters( 'wp_trim_excerpt', $text, $post->post_excerpt );
	}

	/**
	 * Trim a text string to a certain number of words.
	 *
	 * @since 1.6.2
	 *
	 * @param string  $text
	 * @param int     $length
	 * @param string  $more
	 * @param boolean $keep_html
	 */
	public static function trim_words( $text = '', $length = 15, $more = null, $keep_html = false, $wpautop = true ) {
		if ( $text === '' ) {
			return '';
		}

		$more = isset( $more ) ? $more : '&hellip;';

		/**
		 * Ensure length is an integer and not larger than PHP_INT_MAX or it will be converted to float
		 *
		 * @since 1.12
		 */
		$length = (int) $length;

		if ( defined( 'PHP_INT_MAX' ) && $length >= PHP_INT_MAX ) {
			$length = PHP_INT_MAX - 1;
		}

		/**
		 * Strip all HTML tags (wp_trim_words)
		 *
		 * We also need the ability to keep them.
		 * Example: {woo_product_excerpt}.
		 *
		 * Refers to: https://stackoverflow.com/questions/36078264/i-want-to-allow-html-tag-when-use-the-wp-trim-words
		 *
		 * @since 1.6
		 */
		if ( $keep_html ) {
			// False for Rich & basic text element (@since 1.9.8)
			if ( $wpautop ) {
				$text = wpautop( $text );
			}

			$text = force_balance_tags( html_entity_decode( wp_trim_words( htmlentities( $text ), $length, $more ) ) );
		} else {
			$text = wp_trim_words( $text, $length, $more );
		}

		return $text;
	}

	/**
	 * Posts navigation
	 *
	 * @return string
	 *
	 * @since 1.0
	 */
	public static function posts_navigation( $current_page, $total_pages ) {
		$posts_navigation_html = '<div class="bricks-pagination" role="navigation" aria-label="' . esc_attr__( 'Pagination', 'bricks' ) . '">';

		if ( $total_pages < 2 ) {
			return $posts_navigation_html . '</div>';
		}

		$args = [
			'type'      => 'list',
			'current'   => $current_page,
			'total'     => $total_pages,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
		];

		// NOTE: Undocumented
		$args = apply_filters( 'bricks/paginate_links_args', $args );

		$pagination_links = paginate_links( $args );

		// Adding 'aria-label' attributes to previous & next links (@since 1.9)
		if ( $pagination_links ) {
			$pagination_links = str_replace( '<a class="prev page-numbers"', '<a class="prev page-numbers" aria-label="' . esc_attr__( 'Previous page', 'bricks' ) . '"', $pagination_links );
			$pagination_links = str_replace( '<a class="next page-numbers"', '<a class="next page-numbers" aria-label="' . esc_attr__( 'Next page', 'bricks' ) . '"', $pagination_links );
		}

		$posts_navigation_html .= $pagination_links;

		$posts_navigation_html .= '</div>';

		return $posts_navigation_html;
	}

	/**
	 * Pagination within post
	 *
	 * To add ul > li structure as 'link_before' & 'link_after' are not working.
	 *
	 * @since 1.8
	 */
	public static function page_break_navigation() {
		$pagination_html = wp_link_pages(
			[
				'before' => '<div class="bricks-pagination"><ul><span class="title">' . esc_html__( 'Pages:', 'bricks' ) . '</span>',
				'after'  => '</ul></div>',
				'echo'   => false,
			]
		);

		// Wrap each <a> in a <li>
		$pagination_html = str_replace( '<a', '<li><a', $pagination_html );
		$pagination_html = str_replace( '</a>', '</a></li>', $pagination_html );

		// Wrap each <span> (current page) in a <li>
		$pagination_html = str_replace( '<span', '<li><span', $pagination_html );
		$pagination_html = str_replace( '</span>', '</span></li>', $pagination_html );

		return $pagination_html;
	}

	/** Get global class data by ID
	 *
	 * @param string $class_id
	 * @return array|null
	 *
	 * @since 2.0.2
	 */
	public static function get_global_class_by_id( $class_id, $key ) {
		// Get global classes by ID
		if ( empty( Database::$global_data['globalClassesById'] ) ) {
			$global_classes                             = Database::$global_data['globalClasses'] ?? [];
			Database::$global_data['globalClassesById'] = [];

			foreach ( $global_classes as $class ) {
				if ( isset( $class['id'] ) ) {
					Database::$global_data['globalClassesById'][ $class['id'] ] = $class;
				}
			}
		}

		$global_class = Database::$global_data['globalClassesById'][ $class_id ] ?? null;

		return $key ? $global_class[ $key ] ?? null : $global_class;
	}

	/**
	 * Element placeholder HTML
	 *
	 * @since 1.0
	 */
	public static function get_element_placeholder( $data = [], $type = 'info' ) {
		// Placeholder style for Shortcode element 'showPlaceholder' (@since 1.7.2)
		$styles = $data['style'] ?? '';
		$style  = '';

		if ( is_array( $styles ) ) {
			foreach ( $styles as $css_property => $css_value ) {
				if ( $css_value !== '' ) {
					// Value is number: Add defaultUnit 'px'  to the end
					if ( is_numeric( $css_value ) ) {
						$css_value .= 'px';
					}

					// Add !important to supercede other !important styles (#86bycnj5j)
					$style .= "$css_property: $css_value !important;";
				}
			}
		}

		// Add support for custom class (@since 1.12.2)
		$classes = [ 'bricks-element-placeholder' ];

		if ( ! empty( $data['class'] ) ) {
			$classes[] = esc_attr( $data['class'] );
		}

		$classes = implode( ' ', $classes );

		if ( $style ) {
			$output = '<div class="' . $classes . '" data-type="' . esc_attr( $type ) . '" style="' . esc_attr( $style ) . '">';
		} else {
			$output = '<div class="' . $classes . '" data-type="' . esc_attr( $type ) . '">';
		}

		if ( ! empty( $data['icon-class'] ) ) {
			$output .= '<i class="' . esc_attr( $data['icon-class'] ) . '"></i>';
		}

		$output .= '<div class="placeholder-inner">';

		if ( ! empty( $data['title'] ) ) {
			$output .= '<div class="placeholder-title">' . $data['title'] . '</div>';
		}

		if ( ! empty( $data['description'] ) ) {
			$output .= '<div class="placeholder-description">' . $data['description'] . '</div>';
		}

		$output .= '</div>';

		$output .= '</div>';

		return $output;
	}

	/**
	 * Retrieves the element, the complete set of elements and the template/page ID where element belongs to
	 *
	 * NOTE: This function does not check for global element settings.
	 *
	 * @since 1.5
	 */
	public static function get_element_data( $post_id, $element_id ) {
		// $post_id can be zero if home page is set to latest posts
		if ( empty( $element_id ) ) {
			return false;
		}

		// Ensure the ID is a string as it could be 6 digit number (@since 1.12)
		$element_id = (string) $element_id;

		$output = [
			'element'   => [], // The element we want to find
			'elements'  => [], // The complete set of elements where the element is included
			'source_id' => 0   // The post_id of the page or template where the element was set
		];

		// Get page_data via passed post_id
		if ( bricks_is_ajax_call() || bricks_is_rest_call() ) {
			Database::set_active_templates( $post_id );
		}

		/**
		 * If element_id contains dashes, MAYBE it is an element inside a component instance.
		 * - User might pass in unkown element ID with dashes (eg: brxe-h6j7k8) via {query_results_count:brxe-h6j7k8} (#86c4y35pt)
		 *
		 * @since 2.0
		 */
		if ( strpos( $element_id, '-' ) !== false ) {
			/**
			 * Extract the string, treat first part as element ID and second part as instance ID
			 * Example: q1w2e3r4-h6j7k8 => element ID: element, instance ID:
			 */
			$temp_parts       = explode( '-', $element_id );
			$temp_element_id  = $temp_parts[0] ?? '';
			$temp_instance_id = $temp_parts[1] ?? '';
			$instance_element = self::get_element_data( $post_id, $temp_instance_id );

			// STEP: Verify if instance ID is valid
			if ( $instance_element && isset( $instance_element['element'] ) && is_array( $instance_element['element'] ) ) {
				$component_instance = self::get_component_instance( $instance_element['element'] );

				// STEP: Verify if component instance is valid
				if ( $component_instance && isset( $component_instance['elements'] ) && is_array( $component_instance['elements'] ) ) {
					/**
					 * Confirmed the $element_id is an element inside a component instance
					 * Find the query element from the component instance
					 */
					$query_element = array_filter(
						$component_instance['elements'],
						function( $element ) use ( $temp_element_id ) {
							return $element['id'] === $temp_element_id;
						}
					);

					$query_element = reset( $query_element );

					// Return: Query element not found
					if ( ! $query_element ) {
						return false;
					}

					// Set the element ID to include the instance ID
					$query_element['id'] = "{$temp_element_id}-{$temp_instance_id}";
					$output['element']   = $query_element;
					$output['elements']  = $component_instance['elements'] ?? [];
					$output['source_id'] = 'component';

					return $output;
				}
			}
		}

		$templates = [];
		$areas     = [ 'content', 'header', 'footer' ];

		foreach ( $areas as $area ) {
			$elements = Database::get_data( Database::$active_templates[ $area ], $area );

			if ( ! empty( $elements ) && is_array( $elements ) ) {
				foreach ( $elements as $element ) {
					if ( $element['id'] === $element_id ) {
						$output = [
							'element'   => $element,
							'elements'  => $elements,
							'source_id' => Database::$active_templates[ $area ]
						];

						break ( 2 );
					}

					// STEP: Collect possible template IDs from elements
					if ( $element['name'] === 'template' && ! empty( $element['settings']['template'] ) ) {
						$templates[] = $element['settings']['template'];
					}

					if ( $element['name'] === 'post-content' && ! empty( $element['settings']['dataSource'] ) && $element['settings']['dataSource'] == 'bricks' ) {
						$templates[] = $post_id;
					}

					/**
					 * To collect template/post IDs from custom element's settings
					 * Will retrieve the element data from the template/post and search for the target element if it is not found in the current element set.
					 *
					 * @return integer (post ID)
					 *
					 * @see https://academy.bricksbuilder.io/article/filter-bricks-get_element_data/maybe_from_post_id/
					 *
					 * @since 1.11
					 */
					$maybe_from_post_id = absint( apply_filters( 'bricks/get_element_data/maybe_from_post_id', false, $element ) );
					if ( $maybe_from_post_id > 0 ) {
						$templates[] = $maybe_from_post_id;
					}
				}
			}
		}

		/**
		 * Element not found: Try the current post
		 *
		 * Example: Post content element with Source "Bricks", which is not part of the template data.
		 *
		 * @since 1.9.9
		 */
		if ( empty( $output['element'] ) ) {
			// Collect possible current post IDs
			$current_post_ids = array_filter(
				[
					$post_id ?? null,
					Database::$page_data['preview_or_post_id'] ?? null,
					Query::is_looping() && Query::get_loop_object_type() === 'post' ? Query::get_loop_object_id() : null
				]
			);

			// Remove all null values and ensure unique IDs
			$current_post_ids = array_unique( $current_post_ids );

			foreach ( $current_post_ids as $post_id ) {
				$elements = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );

				if ( ! empty( $elements ) && is_array( $elements ) ) {
					foreach ( $elements as $element ) {
						if ( $element['id'] === $element_id ) {
							$output = [
								'element'   => $element,
								'elements'  => $elements,
								'source_id' => $post_id,
							];

							break ( 2 );
						}
					}
				}
			}
		}

		// Not found yet?
		if ( empty( $output['element'] ) ) {
			// If we are still here, try to run through the found templates first, and remaining templates later
			$all_templates_query = Templates::get_templates_query( [ 'fields' => 'ids' ] );
			$all_templates       = ! empty( $all_templates_query->found_posts ) ? $all_templates_query->posts : [];

			$templates = array_merge( $templates, $all_templates );
			$templates = array_unique( $templates );

			foreach ( $templates as $template_id ) {
				$elements = get_post_meta( $template_id, BRICKS_DB_PAGE_CONTENT, true );

				if ( empty( $elements ) || ! is_array( $elements ) ) {
					continue;
				}

				foreach ( $elements as $element ) {
					if ( $element['id'] === $element_id ) {
						$output = [
							'element'   => $element,
							'elements'  => $elements,
							'source_id' => $template_id
						];

						break ( 2 );
					}
				}
			}
		}

		if ( empty( $output['element'] ) ) {
			return false;
		}

		return $output;
	}

	/**
	 * Get element settings
	 *
	 * For use in AJAX functions such as form submit, pagination, etc.
	 *
	 * @since 1.0
	 * @since 1.12: Check for component instance settings
	 */
	public static function get_element_settings( $post_id = 0, $element_id = 0, $global_id = 0 ) {
		if ( ! $element_id ) {
			return false;
		}

		// Get global element settings
		if ( $global_id ) {
			$global_settings = self::get_global_element( [ 'global' => $global_id ], 'settings' );

			if ( is_array( $global_settings ) ) {
				return $global_settings;
			}
		}

		// Get element
		$data    = self::get_element_data( $post_id, $element_id );
		$element = $data['element'] ?? false;

		// No element found
		if ( ! $element ) {
			// Component child: Check element in Frontend array (@since 1.12)
			if ( isset( Frontend::$elements[ $element_id ] ) ) {
				$element = Frontend::$elements[ $element_id ];
			} else {
				return false;
			}
		}

		// Check: Component root (@since 1.12)
		$component_instance_settings = ! empty( $element['cid'] ) ? self::get_component_instance( $element, 'settings' ) : false;

		if ( $component_instance_settings ) {
			return $component_instance_settings;
		}

		// Get global element settings (@since 1.12.2)
		$global_settings = self::get_global_element( $element, 'settings' );

		if ( is_array( $global_settings ) ) {
			return $global_settings;
		}

		// Return: element settings
		return $element['settings'] ?? '';
	}

	/**
	 * Get component by 'cid'
	 *
	 * @param array $element
	 *
	 * @return boolean|array false if no component found, else return the component data.
	 *
	 * @since 1.12
	 */
	public static function get_component_by_cid( $component_id ) {
		if ( ! $component_id ) {
			return false;
		}

		// Check if component exists (@since 2.1)
		if ( empty( Database::$global_data ) || ! is_array( Database::$global_data ) || ! isset( Database::$global_data['components'] ) || ! is_array( Database::$global_data['components'] ) ) {
			return false;
		}

		$components      = Database::$global_data['components'];
		$component_index = array_search( $component_id, array_column( $components, 'id' ) );

		// Return false if component not found (@since 2.1)
		if ( $component_index === false ) {
			return false;
		}

		$component = $components[ $component_index ] ?? false;

		if ( \Bricks\Integrations\Wpml\Wpml::is_wpml_active() && $component ) { // @since 2.1
			$component = \Bricks\Integrations\Wpml\Wpml::get_translated_component( $component );
		}

		return $component;
	}

	/**
	 * Get component element by 'id'
	 *
	 * @param array $component_element_id
	 *
	 * @return boolean|array false if no component found, else return the component child element.
	 *
	 * @since 1.12
	 */
	public static function get_component_element_by_id( $component_element_id ) {
		foreach ( Database::$global_data['components'] as $component ) {
			if ( ! empty( $component['elements'] ) && is_array( $component['elements'] ) ) {
				$component_child_index = array_search( $component_element_id, array_column( $component['elements'], 'id' ) );

				// Return the component element
				if ( $component_child_index !== false ) {
					$element = $component['elements'][ $component_child_index ];

					// Apply WPML translation on-the-fly if WPML is active
					if ( \Bricks\Integrations\Wpml\Wpml::is_wpml_active() ) {
						$translated_component = \Bricks\Integrations\Wpml\Wpml::get_translated_component( $component );
						if ( isset( $translated_component['elements'][ $component_child_index ] ) ) {
							$element = $translated_component['elements'][ $component_child_index ];
						}
					}

					return $element;
				}
			}
		}
	}

	/**
	 * Get data of specific component
	 *
	 * @param array  $element
	 * @param string $key (optional)
	 *
	 * @return boolean|array false if no component found, else return the component data.
	 *
	 * @since 1.12
	 */
	public static function get_component( $element = [], $key = '' ) {
		$component_id = $element['cid'] ?? false;

		if ( ! $component_id ) {
			return;
		}

		$component = self::get_component_by_cid( $component_id );

		return $component && $key && isset( $component[ $key ] ) ? $component[ $key ] : $component;
	}

	/**
	 * Return component instance
	 *
	 * Set root component settings and child component settings based on connected property settings.
	 *
	 * Use custom property settings or fallback to default property value.
	 *
	 * @param array  $element
	 * @param string $key settings, element (return specific component element)
	 *
	 * @return boolean|array false if no component found, else return the component or if $key such as 'settings' provided, the specific component element data.
	 *
	 * @since 1.12
	 */
	public static function get_component_instance( $element = [], $key = '' ) {
		$component_id = $element['cid'] ?? false;
		$component    = $component_id ? self::get_component_by_cid( $component_id ) : false;

		if ( ! $component ) {
			return;
		}

		$component_props = $component['properties'] ?? [];
		$instance_props  = $element['properties'] ?? [];
		$instance_css_id = $element['settings']['_cssId'] ?? '';

		// Get component element
		$component_element = self::get_component_element_by_id( $component_id );

		// Use component element key-value in passed $element $key (i.e. settings)
		if ( $key && isset( $element[ $key ] ) && isset( $component_element[ $key ] ) ) {
			$element[ $key ] = $component_element[ $key ];
		}

		// Loop over connected properties to populate component element settings with custom or default values
		foreach ( $component_props as $prop ) {
			$instance_value            = isset( $instance_props[ $prop['id'] ] ) ? $instance_props[ $prop['id'] ] : '';
			$default_value             = isset( $prop['default'] ) ? $prop['default'] : '';
			$connections_by_element_id = $prop['connections'] ?? [];

			foreach ( $connections_by_element_id as $element_id => $setting_keys ) {
				// Update component element settings
				foreach ( $component['elements'] as &$component_child ) {
					if ( $component_child['id'] == $element_id ) { // Use "==" instead of "===" as $element_id could be an integer

						foreach ( $setting_keys as $setting_key ) {
							// Handle connected controls inside query control (i.e. ignore_sticky_posts; @since 2.0)
							$setting_parts   = explode( ':', $setting_key );
							$setting_key     = count( $setting_parts ) > 1 ? $setting_parts[0] : $setting_key;
							$sub_setting_key = $setting_parts[1] ?? null;

							// Is element condition setting (@since 2.0)
							if ( strpos( $setting_key, '_conditions' ) === 0 ) {
								$parts = explode( '|', $setting_key );
								if ( count( $parts ) !== 3 ) {
									continue;
								}

								if ( ! isset( $component_child['settings']['_conditions'] ) || ! is_array( $component_child['settings']['_conditions'] ) ) {
									continue;
								}

								$condition_id  = $parts[1];
								$condition_key = $parts[2];

								// Find target condition
								foreach ( $component_child['settings']['_conditions'] as $conditions_idx => $conditions ) {
									foreach ( $conditions as $cidx => $condition ) {
										// Cannot check if $condition_key is set as it could be empty but connected
										if (
											isset( $condition['id'] ) && $condition['id'] === $condition_id && isset( $condition['key'] )
										) {
											// Use instance value if set > use default value > unset the setting
											if ( $instance_value !== '' ) {
												$component_child['settings']['_conditions'][ $conditions_idx ][ $cidx ][ $condition_key ] = $instance_value;
											} elseif ( $default_value !== '' ) {

												$component_child['settings']['_conditions'][ $conditions_idx ][ $cidx ][ $condition_key ] = $default_value;
											} else {
												// If no value is set, we set it to null, so we know that it's empty (@since 2.0)
												$component_child['settings']['_conditions'][ $conditions_idx ][ $cidx ][ $condition_key ] = null;
											}
											break 2;
										}
									}
								}

								continue;
							}

							// STEP: Handle settings property mapping

							// Subsetting key is set: Set it to the component child settings (@since 2.0)
							elseif ( $sub_setting_key ) {
								if ( $instance_value !== '' ) {
									if ( ! isset( $component_child['settings'][ $setting_key ] ) ) {
										$component_child['settings'][ $setting_key ] = [];
									}

									$component_child['settings'][ $setting_key ][ $sub_setting_key ] = $instance_value;
								} else {
									unset( $component_child['settings'][ $setting_key ][ $sub_setting_key ] );
								}

								continue;
							}

							// Property type "toggle" set to 'off': Clear the setting (@since 2.0)
							elseif ( $prop['type'] === 'toggle' && $instance_value === 'off' ) {
								unset( $component_child['settings'][ $setting_key ] );

								continue;
							}

							/**
							 * STEP: Property type "class": Merge with or replace element classes
							 *
							 * Check: Not in builder to avoid adding classes on all instances (see: loadData.htmlAttributes)
							 *
							 * @since 2.0
							 */
							elseif ( $prop['type'] === 'class' && ! bricks_is_builder() ) {
								$property_global_class_ids = ! empty( $instance_value ) ? $instance_value : $default_value;

								if ( $property_global_class_ids ) {
									if ( ! is_array( $property_global_class_ids ) ) {
										// Move single global class ID into an array
										$property_global_class_ids = [ $property_global_class_ids ];
									}

									// Custom options: Get global class IDs from the property option.value
									if ( ! empty( $prop['options'] ) ) {
										$property_option_ids = $property_global_class_ids;

										foreach ( $property_option_ids as $property_option_id ) {
											$option_index     = array_search( $property_option_id, array_column( $prop['options'], 'id' ) );
											$option_class_ids = $prop['options'][ $option_index ]['value'] ?? false;

											if ( is_array( $option_class_ids ) && ! empty( $option_class_ids ) ) {
												$property_global_class_ids = array_merge( $property_global_class_ids, $option_class_ids );
											}
										}
									}

									// Add property instance class IDs to self::$global_classes_elements[ $css_class_id ][] = $element['name'];

									/**
									 * NOTE: Outcomment this as it's creating unrelated style
									 *
									 * The global class ids is not connected to this $element
									 *
									 * @see #86c4957mc
									 */
									// foreach ( $property_global_class_ids as $property_global_class_id ) {
									// Assets::$global_classes_elements[ $property_global_class_id ][] = $element['name'];
									// }

									// Check: Replace global classes set on the element
									if ( isset( $prop['replace'] ) ) {
										$component_child['settings']['_cssGlobalClassesPropsReplace'] = true;
									}

									// Merge global classes of this property with existing property global classes
									$component_child['settings']['_cssGlobalClassesProps'] = array_merge( $component_child['settings']['_cssGlobalClassesProps'] ?? [], $property_global_class_ids );
								}
							}

							// Use instance value if set
							elseif ( $instance_value !== '' ) {
								$component_child['settings'][ $setting_key ] = $instance_value;
							}

							// Use default value
							elseif ( $default_value !== '' ) {
								$component_child['settings'][ $setting_key ] = $default_value;
							}

							// No value set on the instance, nor as a property default: Set it to null, so we know that it's empty (@since 2.0)
							else {
								$component_child['settings'][ $setting_key ] = null;
							}
						}
					}

					// Update key-value with update property values
					if (
						$key &&
						isset( $element[ $key ] ) &&
						( $element['id'] === $component_child['id'] || $element['cid'] === $component_child['id'] )
					) {
						$element[ $key ] = $component_child[ $key ];
					}
				}
			}
		}

		// Loop over all component elements to merge or replace global classes (@since 2.0)
		if ( ! empty( $component['elements'] ) && is_array( $component['elements'] ) ) {
			foreach ( $component['elements'] as &$component_child ) {
				$global_class_ids = $component_child['settings']['_cssGlobalClassesProps'] ?? [];

				// Set instance CSS ID (@since 2.1)
				if ( $instance_css_id && $component_id === $component_child['id'] ) {
					$component_child['settings']['_cssId'] = $instance_css_id;
				}

				// Without "Multiple options" enabled, global class ID is stored as a string
				if ( is_string( $global_class_ids ) ) {
					$global_class_ids = explode( ' ', $global_class_ids );
				}

				if ( is_array( $global_class_ids ) && ! empty( $global_class_ids ) ) {
					// Replace global classes set on the element
					if ( isset( $component_child['settings']['_cssGlobalClassesPropsReplace'] ) ) {
						$component_child['settings']['_cssGlobalClasses'] = $global_class_ids;
					}

					// Merge global classes of this property with existing element global classes
					else {
						$component_child['settings']['_cssGlobalClasses'] = array_merge( $component_child['settings']['_cssGlobalClasses'] ?? [], $global_class_ids );
					}

					unset( $component_child['settings']['_cssGlobalClassesProps'] );
					unset( $component_child['settings']['_cssGlobalClassesPropsReplace'] );
				}
			}
		}

		// Return specific element
		if ( $key === 'element' ) {
			return $component_element;
		}

		// Return value of passed $element $key
		if ( $key && isset( $element[ $key ] ) ) {
			return $element[ $key ];
		}

		// Return component or specific key-value like 'elements'
		return $key && isset( $component[ $key ] ) ? $component[ $key ] : $component;
	}

	/**
	 * Get data of specific global element
	 *
	 * @param array $element
	 *
	 * @return boolean|array false if no global element found, else return the global element data.
	 *
	 * @since 1.3.5
	 */
	public static function get_global_element( $element = [], $key = '' ) {
		$data            = false;
		$global_elements = is_array( Database::$global_data['elements'] ) ? Database::$global_data['elements'] : [];

		foreach ( $global_elements as $global_element ) {
			// @since 1.2.1 (check against element 'global' property)
			if (
				! empty( $global_element['global'] ) &&
				! empty( $element['global'] ) &&
				$global_element['global'] === $element['global']
			) {
				$data = $key && isset( $global_element[ $key ] ) ? $global_element[ $key ] : $global_element;
			}

			// @pre 1.2.1 (check against element 'id' property)
			elseif (
				! empty( $global_element['id'] ) &&
				! empty( $element['id'] ) &&
				$global_element['id'] === $element['id']
			) {
				$data = $key && isset( $global_element[ $key ] ) ? $global_element[ $key ] : $global_element;
			}

			if ( $data ) {
				break;
			}
		}

		return $data;
	}

	/**
	 * Get posts options (max 50 results)
	 *
	 * @since 1.0
	 */
	public static function get_posts_by_post_id( $query_args = [] ) {
		// NOTE: Undocumented
		$query_args = apply_filters( 'bricks/helpers/get_posts_args', $query_args );

		$query_args = wp_parse_args(
			$query_args,
			[
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'orderby'        => 'post_type',
				'order'          => 'DESC',
				'no_found_rows'  => true
			]
		);

		// Query max. 100 posts to avoid running into any memory limits
		if ( $query_args['posts_per_page'] == -1 ) {
			$query_args['posts_per_page'] = 100;
		}

		unset( $query_args['fields'] ); // Make sure the output is standard

		// Don't specify meta_key to get all posts for 'templatePreviewPostId'
		$posts = get_posts( $query_args );

		$posts_options = [];

		foreach ( $posts as $post ) {
			// Skip non-content templates (header template, footer template)
			if ( $post->post_type === BRICKS_DB_TEMPLATE_SLUG && Templates::get_template_type( $post->ID ) !== 'content' ) {
				continue;
			}

			$post_type_object = get_post_type_object( $post->post_type );

			$post_title  = get_the_title( $post );
			$post_title .= $post_type_object ? ' (' . $post_type_object->labels->singular_name . ')' : ' (' . ucfirst( $post->post_type ) . ')';

			$posts_options[ $post->ID ] = $post_title;
		}

		return $posts_options;
	}

	/**
	 * Get a list of supported content types for template preview
	 *
	 * @return array
	 */
	public static function get_supported_content_types() {
		$types = [
			'archive-recent-posts' => esc_html__( 'Archive', 'bricks' ) . ' (' . esc_html__( 'Recent posts', 'bricks' ) . ')',
			'archive-author'       => esc_html__( 'Archive', 'bricks' ) . ' (' . esc_html__( 'Author', 'bricks' ) . ')',
			'archive-date'         => esc_html__( 'Archive', 'bricks' ) . ' (' . esc_html__( 'Date', 'bricks' ) . ')',
			'archive-cpt'          => esc_html__( 'Archive', 'bricks' ) . ' (' . esc_html__( 'Posts', 'bricks' ) . ')',
			'archive-term'         => esc_html__( 'Archive', 'bricks' ) . ' (' . esc_html__( 'Term', 'bricks' ) . ')',
			'single'               => esc_html__( 'Single', 'bricks' ) . ' (' . esc_html__( 'Post', 'bricks' ) . '/' . esc_html__( 'Page', 'bricks' ) . '/' . 'CPT' . ')',
			'search'               => esc_html__( 'Search results', 'bricks' ),
		];

		// NOTE: Undocumented
		$types = apply_filters( 'bricks/template_preview/supported_content_types', $types );

		return $types;
	}

	/**
	 * Get editor mode of requested page
	 *
	 * @param int $post_id
	 *
	 * @since 1.0
	 */
	public static function get_editor_mode( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return get_post_meta( $post_id, BRICKS_DB_EDITOR_MODE, true );
	}

	/**
	 * Check if post/page/cpt renders with Bricks
	 *
	 * @param int $post_id / $queried_object_id The post ID.
	 *
	 * @return boolean
	 */
	public static function render_with_bricks( $post_id = 0 ) {
		// When editing with Elementor we need to tell Bricks to render templates as WordPress
		// @see https://elementor.com/help/the-content-area-was-not-found-error/
		if ( isset( $_GET['elementor-preview'] ) ) {
			return false;
		}

		// NOTE: Undocumented (@since 1.5.4)
		$render = apply_filters( 'bricks/render_with_bricks', null, $post_id );

		// Returm only if false otherwise it doesn't perform other important checks (@since 1.5.4)
		if ( $render === false ) {
			return false;
		}

		// Skip WooCommerce, if disabled on Bricks Settings in case is_shop
		if ( ! Woocommerce::$is_active && function_exists( 'is_shop' ) && is_shop() ) {
			return false;
		}

		// Check current page type
		$current_page_type = isset( Database::$page_data['current_page_type'] ) ? Database::$page_data['current_page_type'] : '';

		/**
		 * Password protected
		 *
		 * Execute post_password_required() only for posts or pages (@since 1.8.4 (#863h700vb))
		 * Otherwise will return incorrect results if the $post_id is a taxonomy ID, etc.
		 * https://developer.wordpress.org/reference/functions/post_password_required/
		 */
		if ( $current_page_type === 'post' && post_password_required( $post_id ) ) {
			return false;
		}

		$editor_mode = self::get_editor_mode( $post_id );

		if ( $editor_mode === 'wordpress' ) {
			return false;
		}

		/**
		 * Paid Memberships Pro: Restrict Bricks content (@since 1.5.4)
		 * Only execute if current page is a post (@since 1.8.4 (#863h700vb))
		 * https://www.paidmembershipspro.com/hook/pmpro_has_membership_access_filter/
		 */
		if ( $current_page_type === 'post' && function_exists( 'pmpro_has_membership_access' ) ) {
			$user_id                     = null; // Retrieve inside pmpro_has_membership_access directly
			$return_membership_levels    = false; // Return boolean
			$pmpro_has_membership_access = pmpro_has_membership_access( $post_id, $user_id, $return_membership_levels );

			return $pmpro_has_membership_access;
		}

		return true;
	}

	/**
	 * Get Bricks data for requested page
	 *
	 * @param int     $post_id The post ID.
	 * @param string  $type header, content, footer.
	 * @param boolean $force_post_data Force checking only the specific post data without considering templates.
	 *
	 * @since 1.3.4
	 *
	 * @return boolean|array
	 */
	public static function get_bricks_data( $post_id = 0, $type = 'content', $force_post_data = false ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		// Return if requested post is not rendered with Bricks
		if ( ! self::render_with_bricks( $post_id ) ) {
			return false;
		}

		$bricks_data = Database::get_template_data( $type, $force_post_data );

		if ( empty( $bricks_data ) || ! is_array( $bricks_data ) ) {
			return false;
		}

		return $bricks_data;
	}

	public static function delete_bricks_data_by_post_id( $post_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$edit_post_link = get_edit_post_link();

		if ( ! $edit_post_link ) {
			return false;
		}

		// Return post edit URL: No post ID found
		if ( ! $post_id ) {
			return $edit_post_link;
		}

		return add_query_arg(
			[
				'bricks_delete_post_meta' => $post_id,
				'bricks_notice'           => 'post_meta_deleted',
			],
			$edit_post_link
		);
	}

	/**
	 * Generate random hash
	 *
	 * Default: 6 characters long
	 *
	 * @return string
	 *
	 * @since 1.0
	 */
	public static function generate_hash( $string, $length = 6 ) {
		// Generate SHA1 hexadecimal string (40-characters)
		$sha1        = sha1( $string );
		$sha1_length = strlen( $sha1 );
		$hash        = '';

		// Generate random site hash based on SHA1 string
		for ( $i = 0; $i < $length; $i++ ) {
			$hash .= $sha1[ rand( 0, $sha1_length - 1 ) ];
		}

		// Convert site path to lowercase
		$hash = strtolower( $hash );

		return $hash;
	}

	public static function generate_random_id( $echo = true ) {
		$hash = self::generate_hash( md5( uniqid( rand(), true ) ) );

		if ( $echo ) {
			echo $hash;
		}

		return $hash;
	}

	/**
	 * Generate new & unique IDs for Bricks elements
	 *
	 * @since 1.9.8
	 *
	 * @param array $elements
	 * @param array $element_names_only - For WPML to only regenerate for filter elements (@since 1.12.2)
	 */
	public static function generate_new_element_ids( $elements = [], $element_names_only = [] ) {
		if ( empty( $elements ) ) {
			return $elements;
		}

		// STEP: Parse the entire Bricks data postmeta as a string
		$bricks_data_string = wp_json_encode( $elements );

		$ids_lookup = [];

		// STEP: Loop over all elements to store old and new element ID in $ids_lookup array
		foreach ( $elements as $element ) {
			// Skip elements that are not in the $element_names array
			if ( is_array( $element_names_only ) && ! empty( $element_names_only ) && ! in_array( $element['name'], $element_names_only, true ) ) {
				continue;
			}

			$old_id = $element['id'] ?? '';
			$new_id = self::generate_random_id( false );

			if ( $old_id && $new_id ) {
				$ids_lookup[ $old_id ] = $new_id;
			}
		}

		// STEP: Search for old element ID in the Bricks data string and replace with newly-generated element ID
		$new_elements_string = str_replace( array_keys( $ids_lookup ), array_values( $ids_lookup ), $bricks_data_string );

		// STEP: Convert Bricks data string back to array, and use as new Bricks data
		$new_elements = json_decode( $new_elements_string, true );

		return $new_elements;
	}

	/**
	 * Get file contents from file system
	 *
	 * .svg, .json (Google fonts), etc.
	 *
	 * @since 1.8.1
	 */
	public static function file_get_contents( $file_path, ...$args ) {
		// Return: File not found
		if ( ! file_exists( $file_path ) ) {
			return '';
		}

		// Return: File not readable
		if ( ! is_readable( $file_path ) ) {
			return '';
		}

		// STEP: Get file contents
		$file_contents = file_get_contents( $file_path, ...$args );

		// Return: Empty file contents
		if ( empty( $file_contents ) ) {
			return '';
		}

		return $file_contents;
	}

	/**
	 * Return WP dashboard Bricks settings url
	 *
	 * @since 1.0
	 */
	public static function settings_url( $params = '' ) {
		return admin_url( "/admin.php?page=bricks-settings$params" );
	}

	/**
	 * Return WP dashboard Bricks elements manager url
	 *
	 * @since 2.0
	 */
	public static function elements_manager_url( $params = '' ) {
		return admin_url( "/admin.php?page=bricks-elements$params" );
	}

	/**
	 * Return Bricks Academy link
	 *
	 * @since 1.0
	 */
	public static function article_link( $path, $text ) {
		return '<a href="https://academy.bricksbuilder.io/article/' . $path . '" target="_blank" rel="noopener">' . $text . '</a>';
	}

	/**
	 * Return the edit post link (ot the preview post link)
	 *
	 * @param int $post_id The post ID.
	 *
	 * @since 1.2.1
	 */
	public static function get_preview_post_link( $post_id ) {
		$template_preview_post_id = self::get_template_setting( 'templatePreviewPostId', $post_id );

		if ( $template_preview_post_id ) {
			$post_id = $template_preview_post_id;
		}

		return get_edit_post_link( $post_id );
	}

	/**
	 * Dev helper to var dump nicely formatted
	 *
	 * @since 1.0
	 */
	public static function pre_dump( $data ) {
		echo '<pre>';
		var_dump( $data );
		echo '</pre>';
	}

	/**
	 * Dev helper to error log array values
	 *
	 * @since 1.0
	 */
	public static function log( $data ) {
		error_log( print_r( $data, true ) );
	}

	/**
	 * Logs a message if WordPress debug is enabled.
	 *
	 * @param string $message The message to log.
	 *
	 * @since 1.10.2 (for WPML)
	 */
	public static function maybe_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( print_r( $message, true ) ); // phpcs:ignore
			}
		}
	}

	/**
	 * Custom wp_remote_get function
	 */
	public static function remote_get( $url, $args = [] ) {
		if ( ! isset( $args['timeout'] ) ) {
			$args['timeout'] = 30;
		}

		// Disable to avoid Let's Encrypt SSL root certificate expiration issue
		if ( ! isset( $args['sslverify'] ) ) {
			$args['sslverify'] = false;
		}

		$args = apply_filters( 'bricks/remote_get', $args, $url );

		return wp_remote_get( $url, $args );
	}

	/**
	 * Custom wp_remote_post function
	 *
	 * @since 1.3.5
	 */
	public static function remote_post( $url, $args = [] ) {
		if ( ! isset( $args['timeout'] ) ) {
			$args['timeout'] = 30;
		}

		// Disable to avoid Let's Encrypt SSL root certificate expiration issue
		if ( ! isset( $args['sslverify'] ) ) {
			$args['sslverify'] = false;
		}

		$args = apply_filters( 'bricks/remote_post', $args, $url );

		return wp_remote_post( $url, $args );
	}

	/**
	 * Generate swiperJS breakpoint data-options (carousel, testimonial)
	 *
	 * Set slides to show & scroll per breakpoint.
	 * Swiper breakpoint values use "min-width". so descent breakpoints from largest to smallest.
	 *
	 * https://swiperjs.com/swiper-api#param-breakpoints
	 *
	 * @since 1.3.5
	 *
	 * @since 1.5.1: removed old 'responsive' repeater controls due to custom breakpoints
	 */
	public static function generate_swiper_breakpoint_data_options( $settings ) {
		$breakpoints = [];

		foreach ( Breakpoints::$breakpoints as $index => $breakpoint ) {
			$key = $breakpoint['key'];

			// Get min-width value from width of next smaller breakpoint
			$min_width = ! empty( Breakpoints::$breakpoints[ $index + 1 ]['width'] ) ? intval( Breakpoints::$breakpoints[ $index + 1 ]['width'] ) + 1 : 1;

			// 'desktop' breakpoint (plain setting key)
			if ( $key === 'desktop' ) {
				if ( ! empty( $settings['slidesToShow'] ) ) {
					$breakpoints[ $min_width ]['slidesPerView'] = intval( $settings['slidesToShow'] );
				}

				if ( ! empty( $settings['slidesToScroll'] ) ) {
					$breakpoints[ $min_width ]['slidesPerGroup'] = intval( $settings['slidesToScroll'] );
				}
			}

			// Non-desktop breakpoint
			else {
				if ( ! empty( $settings[ "slidesToShow:{$key}" ] ) ) {
					$breakpoints[ $min_width ]['slidesPerView'] = intval( $settings[ "slidesToShow:{$key}" ] );
				}

				if ( ! empty( $settings[ "slidesToScroll:{$key}" ] ) ) {
					$breakpoints[ $min_width ]['slidesPerGroup'] = intval( $settings[ "slidesToScroll:{$key}" ] );
				}
			}
		}

		return $breakpoints;
		// return array_reverse( $breakpoints, true );
	}

	/**
	 * Generate swiperJS autoplay options (carousel, slider, testimonial)
	 *
	 * @since 1.5.7
	 */
	public static function generate_swiper_autoplay_options( $settings ) {
		return [
			'delay'                => isset( $settings['autoplaySpeed'] ) ? intval( $settings['autoplaySpeed'] ) : 3000,

			// Set to false if 'pauseOnHover' is true to prevent swiper stopping after first hover
			'disableOnInteraction' => ! isset( $settings['pauseOnHover'] ),

			// Pause autoplay on mouse enter (new in v6.6: autoplay.pauseOnMouseEnter)
			'pauseOnMouseEnter'    => isset( $settings['pauseOnHover'] ),

			// Stop autoplay on last slide (@since 1.4)
			'stopOnLastSlide'      => isset( $settings['stopOnLastSlide'] ),
		];
	}

	/**
	 * Verifies the integrity of the data using a hash
	 *
	 * @param string $hash The hash to compare against.
	 * @param mixed  $data The data to verify.
	 *
	 * @since 1.9.7
	 *
	 * @return bool True if the data is valid, false otherwise.
	 */
	public static function verify_code_signature( $hash, $data ) {
		// Compute the hash of the data
		$computed_hash = wp_hash( $data );

		// Compare the computed hash with the provided hash
		return $computed_hash === $hash;
	}

	/**
	 * Code element settings: code, + executeCode
	 * Query Loop settings: useQueryEditor + queryEditor
	 */
	public static function sanitize_element_php_code( $post_id, $element_id, $code, $signature ) {
		// Return no code: Code execution not enabled (Bricks setting or filter)
		if ( ! self::code_execution_enabled() ) {
			return [ 'error' => esc_html__( 'Code execution is disabled', 'bricks' ) ];
		}

		// Filter $code content to prevent dangerous calls
		$disallow_keywords = apply_filters( 'bricks/code/disallow_keywords', [] );

		// Check if code contains any disallowed keywords
		if ( is_array( $disallow_keywords ) && ! empty( $disallow_keywords ) ) {
			foreach ( $disallow_keywords as $keyword ) {
				if ( stripos( $code, $keyword ) !== false ) {
					// Return error: Disallowed keyword found
					return [ 'error' => esc_html__( 'Disallowed keyword found', 'bricks' ) . " ($keyword)" ];
				}
			}
		}

		// Return error: Code signature verification failed
		if ( ! self::verify_code_signature( $signature, $code ) ) {
			// Return error: Nor signature found
			if ( ! $signature ) {
				return [ 'error' => esc_html__( 'No signature', 'bricks' ) ];
			} else {
				return [ 'error' => esc_html__( 'Invalid signature', 'bricks' ) ];
			}
		}

		// Return verified code in builder (current user has full access & execute_code capability)
		if ( Capabilities::$full_access && Capabilities::$execute_code && bricks_is_builder_call() ) {
			return $code;
		}

		// Clear code to populate with setting from database
		$code = '';

		/**
		 * STEP: Get element settings from database
		 *
		 * Checks also for:
		 * - Component
		 * - Global element
		 */
		$global_id        = $element_id;
		$element_settings = self::get_element_settings( $post_id, $element_id, $global_id );

		// STEP: Get code element setting from database
		if ( isset( $element_settings['code'] ) ) {
			$code      = $element_settings['code'];
			$signature = $element_settings['signature'] ?? false;
		}

		// STEP: Get query editor setting from database
		elseif ( isset( $element_settings['query']['queryEditor'] ) ) {
			$code      = $element_settings['query']['queryEditor'];
			$signature = $element_settings['query']['signature'] ?? false;
		}

		// STEP: Get global query (@since 2.1)
		elseif ( ! empty( $element_settings['query']['id'] ) ) {
			$global_query_id = $element_settings['query']['id'];

			foreach ( Database::$global_data['globalQueries'] as $global_query ) {
				if ( isset( $global_query['id'] ) && $global_query['id'] === $global_query_id ) {
					$code      = $global_query['settings']['queryEditor'] ?? '';
					$signature = $global_query['settings']['signature'] ?? false;

					break;
				}
			}
		}

		// Return error: Code signature verification from database failed
		if ( ! self::verify_code_signature( $signature, $code ) ) {
			// Return error: No signature found
			if ( ! $signature ) {
				return [ 'error' => esc_html__( 'No signature', 'bricks' ) ];
			} else {
				return [ 'error' => esc_html__( 'Invalid signature', 'bricks' ) ];
			}
		}

		// Return: Code safely retrieved from database
		return $code;
	}

	/**
	 * Check if code execution is enabled
	 *
	 * Via filter or Bricks setting.
	 *
	 * @since 1.9.7: Code execution is disabled by default.
	 *
	 * @return boolean
	 */
	public static function code_execution_enabled() {
		$filter_result = apply_filters( 'bricks/code/disable_execution', false );

		// Filter explicitly returns true: Disable code execution
		if ( $filter_result === true ) {
			return false;
		}

		// Deprecated since 1.9.7, but kept for those already using it. Only evaluate when set to false.
		if ( apply_filters( 'bricks/code/allow_execution', null ) === false ) {
			return false;
		}

		// Check the database setting if code execution is enabled
		return Database::get_setting( 'executeCodeEnabled', false );
	}

	/**
	 * Sanitize value
	 */
	public static function sanitize_value( $value ) {
		// URL value
		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $value );
		}

		// Email value
		if ( is_email( $value ) ) {
			return sanitize_email( $value );
		}

		// Text value
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize Bricks data
	 *
	 * During template import, etc.
	 *
	 * @since 1.3.7
	 */
	public static function sanitize_bricks_data( $elements ) {
		if ( is_array( $elements ) ) {
			foreach ( $elements as $index => $element ) {
				// Code element: Unset "Execute Code" setting to prevent executing potentially malicious code
				if ( isset( $element['settings']['executeCode'] ) ) {
					unset( $elements[ $index ]['settings']['executeCode'] );
				}

				// Query editor: Unset "query.useQueryEditor" setting to prevent executing potentially malicious code
				if ( isset( $element['settings']['query']['useQueryEditor'] ) ) {
					unset( $elements[ $index ]['settings']['useQueryEditor'] );
				}

				// Query editor: Unset "query.queryEditor" setting to prevent executing potentially malicious code
				if ( isset( $element['settings']['query']['queryEditor'] ) ) {
					unset( $elements[ $index ]['settings']['queryEditor'] );
				}
			}
		}

		return $elements;
	}

	/**
	 * Set is_frontend = false to a element
	 *
	 * Use: $elements = array_map( 'Bricks\Helpers::set_is_frontend_to_false', $elements );
	 *
	 * @since 1.4
	 */
	public static function set_is_frontend_to_false( $element ) {
		$element['is_frontend'] = false;

		return $element;
	}

	/**
	 * Get post IDs of all Bricks-enabled post types
	 *
	 * @see admin.php get_converter_items()
	 * @see files.php get_css_files_list()
	 *
	 * @param array $custom_args Custom get_posts() arguments (@since 1.8; @see get_css_files_list).
	 *
	 * @since 1.4
	 */
	public static function get_all_bricks_post_ids( $custom_args = [] ) {
		$args = array_merge(
			[
				'post_type'              => array_keys( self::get_supported_post_types() ),
				'posts_per_page'         => -1,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => [
					[
						'key'     => BRICKS_DB_PAGE_CONTENT,
						'value'   => '',
						'compare' => '!=',
					],
				],
			],
			$custom_args
		);

		return get_posts( $args );
	}

	/**
	 * Search & replace: Works for strings & arrays
	 *
	 * @param string $search  The value being searched for.
	 * @param string $replace The replacement value that replaces found search values.
	 * @param string $data    The string or array being searched and replaced on, otherwise known as the haystack.
	 *
	 * @see templates.php import_template()
	 *
	 * @since 1.4
	 */
	public static function search_replace( $search, $replace, $data ) {
		$is_array = is_array( $data );

		// Stringify array
		if ( $is_array ) {
			$data = wp_json_encode( $data );
		}

		// Replace string
		$data = str_replace( $search, $replace, $data );

		// Convert back to array
		if ( $is_array ) {
			$data = json_decode( $data, true );
		}

		return $data;
	}

	/**
	 * Google fonts are disabled (via filter OR Bricks setting)
	 *
	 * @see https://academy.bricksbuilder.io/article/filter-bricks-assets-load_webfonts
	 *
	 * @since 1.4
	 */
	public static function google_fonts_disabled() {
		return ! apply_filters( 'bricks/assets/load_webfonts', true ) || isset( Database::$global_settings['disableGoogleFonts'] );
	}

	/**
	 * Sort variable Google Font axis (all lowercase before all uppercase)
	 *
	 * https://developers.google.com/fonts/docs/css2#strictness
	 *
	 * @since 1.8
	 */
	public static function google_fonts_get_axis_rank( $axis ) {
		// lowercase axis first
		if ( $axis === strtolower( (string) $axis ) ) {
			return 0;
		}

		// uppercase axis second
		return 1;
	}

	/**
	 * Stringify HTML attributes
	 *
	 * @param array $attributes key = attribute key; value = attribute value (string|array).
	 *
	 * @see bricks/header/attributes
	 * @see bricks/footer/attributes
	 * @see bricks/popup/attributes
	 *
	 * @return string
	 *
	 * @since 1.5
	 */
	public static function stringify_html_attributes( $attributes ) {
		$strings = [];

		foreach ( $attributes as $key => $value ) {
			// Skip invalid HTML attribute name
			if ( ! self::is_valid_html_attribute_name( $key ) ) {
				continue;
			}

			// Array: 'class', etc.
			if ( is_array( $value ) ) {
				$value = join( ' ', $value );
			}

			// To escape json strings (@since 1.6)
			$value = esc_attr( $value );

			$strings[] = "{$key}=\"$value\"";
		}

		return join( ' ', $strings );
	}

	/**
	 * Validate HTML attributes
	 *
	 * NOTE: Not in use yet to prevent breaking changes of unintended removal of attributes.
	 *
	 * @since 1.10.2
	 */
	public static function is_valid_html_attribute_name( $attribute_name ) {
		return true;
		// return preg_match( '/^[\p{L}_:][\p{L}0-9_:.\\-]*$/u', $attribute_name ) === 1;
	}

	/**
	 * Return element attribute 'id'
	 *
	 * @since 1.5.1
	 *
	 * @since 1.7.1: Parse dynamic data for _cssId (same for _cssClasses)
	 */
	public static function get_element_attribute_id( $id, $settings ) {
		$attribute_id = "brxe-{$id}";

		if ( ! empty( $settings['_cssId'] ) ) {
			$attribute_id = bricks_render_dynamic_data( $settings['_cssId'] );
		}

		return esc_attr( $attribute_id );
	}

	/**
	 * Based on the current user capabilities, check if the new elements could be changed on save (AJAX::save_post())
	 *
	 * If user can only edit the content:
	 *  - Check if the number of elements is the same
	 *  - Check if the new element already existed before
	 *
	 * If user cannot execute code:
	 *  - Replace any code element (with execution enabled) by the saved element,
	 *  - or disable the execution (in case the element is new)
	 *
	 * @since 1.5.4
	 *
	 * @param array  $new_elements Array of elements.
	 * @param int    $post_id The post ID.
	 * @param string $area 'header', 'content', 'footer'.
	 *
	 * @return array Array of elements
	 */
	public static function security_check_elements_before_save( $new_elements, $post_id, $area ) {
		$user_can_execute_code = Capabilities::current_user_can_execute_code();

		// Return elements (user can add/remove elements & execute code permission)
		if ( Builder_Permissions::user_can_modify_element_count() && $user_can_execute_code ) {
			return $new_elements;
		}

		// Get old data structure from the database
		if ( $area === 'global' ) {
			$old_elements = get_option( BRICKS_DB_GLOBAL_ELEMENTS, [] );
		} else {
			$area_key     = Database::get_bricks_data_key( $area );
			$old_elements = get_post_meta( $post_id, $area_key, true );

			// wp_slash the postmeta value as update_post_meta removes backslashes via wp_unslash (@since 1.9.7)
			if ( is_array( $old_elements ) ) {
				$old_elements = wp_slash( $old_elements );
			}
		}

		// Initial orphaned elements scan
		$new_elements = is_array( $new_elements ) ? $new_elements : [];
		$old_elements = is_array( $old_elements ) ? $old_elements : [];

		// STEP: Return old elements: User is not allowed to edit the structure, but the number of new elements differs from old structure
		if ( ! Builder_Permissions::user_can_modify_element_count() && count( $new_elements ) !== count( $old_elements ) ) {
			return $old_elements;
		}

		/**
		 * Current user has no code execution (Bricks) capability nor 'unfiltered_html' (WordPress) capability
		 *
		 * Run all non-code elements through wp_filter_post_kses as WordPress does.
		 *
		 * @since 1.9.8
		 */
		if ( ! $user_can_execute_code && ! current_user_can( 'unfiltered_html' ) ) {
			foreach ( $new_elements as $i => $new_element ) {
				if ( $new_element['name'] !== 'code' ) {
					// Use array_walk_recursive to apply wp_kses to all element settings (@since 1.11)
					array_walk_recursive( $new_element, [ 'Bricks\Helpers', 'apply_wp_filter_post_kses_to_array' ] );
				}

				if ( isset( $new_element['id'] ) ) {
					$new_elements[ $i ] = $new_element;
				}
			}
		}

		$old_elements_indexed = [];

		// Index the old elements for faster check
		foreach ( $old_elements as $element ) {
			$old_elements_indexed[ $element['id'] ] = $element;
		}

		foreach ( $new_elements as $index => $element ) {
			// STEP: Orphaned elements scan: New elements found despite the user can only edit existing content: Remove element
			if ( ! Builder_Permissions::user_can_modify_element_count() && ! isset( $old_elements_indexed[ $element['id'] ] ) ) {
				unset( $new_elements[ $index ] );

				continue;
			}

			// STEP: Code element: User doesn't have permission and code execution is enabled on element
			if ( ! $user_can_execute_code && $element['name'] === 'code' && isset( $element['settings']['executeCode'] ) ) {
				// Replace new element with old element (if it exists)
				if ( isset( $old_elements_indexed[ $element['id'] ] ) ) {
					$new_elements[ $index ] = $old_elements_indexed[ $element['id'] ];
				}

				// Disable execution mode
				else {
					unset( $new_elements[ $index ]['settings']['executeCode'] );
				}
			}

			// STEP: SVG element: User doesn't have permission and SVG has 'code'
			if ( ! $user_can_execute_code && $element['name'] === 'svg' && isset( $element['settings']['code'] ) ) {
				// Replace new element with old element (if it exists)
				if ( isset( $old_elements_indexed[ $element['id'] ] ) ) {
					$new_elements[ $index ] = $old_elements_indexed[ $element['id'] ];
				}

				// Remove code
				else {
					unset( $new_elements[ $index ]['settings']['code'] );
				}
			}

			// STEP: 'query' setting: User doesn't have permission and 'useQueryEditor' is enabled on element, plus 'queryEditor' (PHP code) exists
			if ( ! $user_can_execute_code && ( isset( $element['settings']['query']['useQueryEditor'] ) || isset( $element['settings']['query']['queryEditor'] ) ) ) {
				// Replace new element with old element (if it exists)
				if ( isset( $old_elements_indexed[ $element['id'] ] ) ) {
					$new_elements[ $index ] = $old_elements_indexed[ $element['id'] ];
				}

				// Remove code
				else {
					if ( isset( $new_elements[ $index ]['settings']['query']['useQueryEditor'] ) ) {
						unset( $new_elements[ $index ]['settings']['query']['useQueryEditor'] );
					}

					if ( isset( $new_elements[ $index ]['settings']['query']['queryEditor'] ) ) {
						unset( $new_elements[ $index ]['settings']['query']['queryEditor'] );
					}
				}
			}

			// STEP: Modified 'echo' DD tag found for user without code execution capabilities
			if ( ! $user_can_execute_code ) {
				// Extract all DD echo tags from new data
				$new_element_string = wp_json_encode( $element );
				preg_match_all( '/\{echo:([^\}]+)\}/', $new_element_string, $new_matches );
				$new_matches = isset( $new_matches[1] ) && is_array( $new_matches[1] ) ? $new_matches[1] : [];

				// Remove all 'echo' DD tags
				if ( $new_matches ) {
					// Extract all DD echo tags from old data
					$old_element        = $old_elements_indexed[ $element['id'] ];
					$old_element_string = wp_json_encode( $old_element );
					preg_match_all( '/\{echo:([^\}]+)\}/', $old_element_string, $old_matches );
					$old_matches = isset( $old_matches[1] ) && is_array( $old_matches[1] ) ? $old_matches[1] : [];

					foreach ( $new_matches as $tag_index => $new_tag ) {
						$old_tag = $old_matches[ $tag_index ] ?? '';

						// New tag doesn't match old tag: Replace new tag with old tag
						if ( $old_tag ) {
							$new_elements[ $index ] = json_decode( str_replace( "{echo:$new_tag}", "{echo:$old_tag}", $new_element_string ), true );
						}

						// No old tag found: Remove new tag
						else {
							$new_elements[ $index ] = json_decode( str_replace( "{echo:$new_tag}", '', $new_element_string ), true );
						}
					}
				}
			}
		}

		// Reindex array
		$new_elements = array_values( $new_elements );

		// Apply filter to new elements (@since 1.12.3)
		$new_elements = apply_filters( 'bricks/security_check_before_save/new_elements', $new_elements, $old_elements_indexed );

		return $new_elements;
	}

	/**
	 * Parse CSS & return empty string if checks are not fulfilled
	 *
	 * @since 1.6.2
	 */
	public static function parse_css( $css ) {
		if ( ! $css ) {
			return $css;
		}

		// CSS syntax error: Number of opening & closing tags differs
		if ( substr_count( $css, '{' ) !== substr_count( $css, '}' ) ) {
			return '';
		}

		return $css;
	}

	/**
	 * Save global classes in options table
	 *
	 * Skip saving empty global classes array.
	 *
	 * Triggered in:
	 *
	 * ajax.php:      wp_ajax_bricks_save_post (save post in builder)
	 * templates.php: wp_ajax_bricks_import_template (template import)
	 * converter.php: wp_ajax_bricks_run_converter (run converter from Bricks settings)
	 *
	 * @since 1.7
	 *
	 * @param array  $global_classes
	 * @param string $action
	 */
	public static function save_global_classes_in_db( $global_classes ) {
		$response  = '';
		$timestamp = time();
		$user_id   = get_current_user_id();

		// Update global classes (if not empty)
		// NOTE: Global empty classes array can be saved, since we store it in the trash (@since 1.11)
		if ( is_array( $global_classes ) ) {
			// Remove any empty classes, then reindex classes array (#86bxgebwg)
			$global_classes = array_values( array_filter( $global_classes ) );

			if ( count( $global_classes ) ) {
				$response = update_option( BRICKS_DB_GLOBAL_CLASSES, $global_classes );

				// Update global classes timestamp & user_id for builder nofifications & conflicts (@since 1.9.9)
				update_option( BRICKS_DB_GLOBAL_CLASSES_TIMESTAMP, $timestamp );
				update_option( BRICKS_DB_GLOBAL_CLASSES_USER, $user_id );
			} else {
				$response = delete_option( BRICKS_DB_GLOBAL_CLASSES );
			}
		}

		return [
			'response'  => $response,
			'timestamp' => $timestamp,
			'user_id'   => $user_id,
		];
	}

	/**
	 * Save global variables in options table
	 *
	 * @param array $global_variables
	 * @return mixed
	 * @since 1.9.8
	 */
	public static function save_global_variables_in_db( $global_variables ) {
		$response = '';

		// Update global variables (if not empty)
		if ( is_array( $global_variables ) && count( $global_variables ) ) {
			$response = update_option( BRICKS_DB_GLOBAL_VARIABLES, $global_variables );
		}

		return $response;
	}

	/**
	 * Parse TinyMCE editor control data
	 *
	 * Use instead of applying 'the_content' filter to prevent rendering third-party content in within non "Post Content" elements.
	 *
	 * Available as static function to use in get_dynamic_data_preview_content as well (DD tag render on canvas)
	 *
	 * @see accordion, alert, icon-box, slider, tabs, text
	 *
	 * @since 1.7
	 */
	public static function parse_editor_content( $content = '' ) {
		// Return: Not a text string (e.g. ACF field type color array)
		if ( ! is_string( $content ) ) {
			return $content;
		}

		/**
		 * Remove outermost <p> tag (from rich text element) if it contains a block-level HTML tag (like an <div>, <h2>, etc.)
		 *
		 * Example: <p>{acf_eysiwyg}</p>, and the ACF DD tag contains: <h2>ACF heading</h2>
		 * Rendered as: <p></p><h2>ACF heading</h2><p></p>
		 * Expected: <h2>ACF heading</h2>
		 *
		 * @since 1.7
		 */
		if ( strpos( $content, '<p>' ) === 0 && strpos( $content, '</p>' ) !== false ) {
			$content = preg_replace( '/^<p>(.*)<\/p>$/is', '$1', $content );
		}

		/**
		 * WordPress code default-filters.php reference
		 *
		 * Priority: 8
		 * run_shortcode
		 * autoembed
		 *
		 * Priority: 9
		 * do_blocks
		 *
		 * Priority: 10
		 * wptexturize
		 * wpautop
		 * shortcode_unautop
		 * prepend_attachment
		 * wp_filter_content_tags
		 * wp_replace_insecure_home_url
		 *
		 * Priority: 11
		 * capital_P_dangit
		 * do_shortcode
		 *
		 * Priority: 20
		 * convert_smilies
		 */

		// Passes any unlinked URLs that are on their own line to WP_Embed::shortcode() for potential embedding (audio, video)
		if ( $GLOBALS['wp_embed'] instanceof \WP_Embed ) {
			$content = $GLOBALS['wp_embed']->run_shortcode( $content ); // (#86c2v39x2)
			$content = $GLOBALS['wp_embed']->autoembed( $content );
		}

		// Priority: 10
		$content = wptexturize( $content );
		$content = wpautop( $content );
		$content = shortcode_unautop( $content );
		// $content = prepend_attachment( $content );

		// Add srcset, sizes, and loading attributes to img HTML tags; and loading attributes to iframe HTML tags
		$content = wp_filter_content_tags( $content );
		$content = wp_replace_insecure_home_url( $content );

		// Priority: 11
		$content = do_shortcode( $content );

		// Priority: 20
		$content = convert_smilies( $content );

		return $content;
	}

	/**
	 * Check if post_id is a Bricks template
	 *
	 * Previously used get_post_type( $post_id ) === BRICKS_DB_TEMPLATE_SLUG
	 * But this method might accidentally return true if $post_id is a term_id or user_id, etc.
	 *
	 * @since 1.8
	 */
	public static function is_bricks_template( $post_id ) {
		// Check current page type
		$current_page_type = isset( Database::$page_data['current_page_type'] ) ? Database::$page_data['current_page_type'] : '';

		// In loop: Get object type of loop
		if ( Query::is_any_looping() ) {
			$looping_query_id    = Query::is_any_looping();
			$looping_object_type = Query::get_loop_object_type( $looping_query_id );
			$current_page_type   = $looping_object_type;
		}

		return $current_page_type === 'post' && get_post_type( $post_id ) === BRICKS_DB_TEMPLATE_SLUG;
	}

	/**
	 * Check if current request is Bricks preview
	 *
	 * @since 1.9.5
	 */
	public static function is_bricks_preview() {
		global $wp_query;

		return $wp_query->get( 'post_type' ) === BRICKS_DB_TEMPLATE_SLUG || bricks_is_builder_call();
	}

	/**
	 * Check if the element settings contain a specific value
	 *
	 * Useful if the setting has diffrent keys in different breakpoints.
	 *
	 * Example: 'overlay', 'overlay:mobile_portrait', 'overlay:tablet_landscape', etc.
	 *
	 * Usage:
	 * Helpers::element_setting_has_value( 'overlay', $settings ); // Check if $settings contains 'overlay' setting in any breakpoint
	 * Helpers::element_setting_has_value( 'overlay:mobile', $settings ); // Check if $settings contains 'overlay' setting in mobile breakpoint
	 *
	 * @since 1.8
	 *
	 * @param string $key
	 * @param array  $settings
	 *
	 * @return bool
	 */
	public static function element_setting_has_value( $key = '', $settings = [] ) {
		if ( ! is_array( $settings ) || empty( $key ) ) {
			return false;
		}

		$has_setting = false;

		if ( is_array( $settings ) && count( $settings ) ) {
			// Search array keys for where starts with $key
			$setting_keys = array_filter(
				array_keys( $settings ),
				function ( $setting_key ) use ( $key ) {
					return strpos( $setting_key, $key ) === 0;
				}
			);

			if ( count( $setting_keys ) ) {
				// Assume the first key is the one we're looking for
				$first_key = reset( $setting_keys );
				// Check if the value is not empty
				$has_setting = ! empty( $settings[ $first_key ] );
			}
		}

		return $has_setting;
	}

	/**
	 * Check if the provided url string is the current landed page
	 * Logic improvement for performance (@since 2.0)
	 * - Should not set ancestor page/post as current page. The active state handled by frontend JS.
	 * - Not used in the builder.
	 *
	 * @since 1.8
	 *
	 * @param string $url
	 * @return bool
	 */
	public static function maybe_set_aria_current_page( $url = '' ) {
		if ( empty( $url ) || self::is_bricks_preview() ) {
			return false;
		}

		global $wp;

		// Normalize the provided URL
		$url = trailingslashit( rawurldecode( $url ) );

		// Get the current page URL
		$current_page_url = trailingslashit( rawurldecode( home_url( $wp->request ) ) );

		$set_aria_current = false;

		// Check if the provided URL matches the current page URL
		if ( $url === $current_page_url ) {
			$set_aria_current = true;
		}

		else {

			// Legacy logic to compare URLs
			if ( is_front_page() ) {
				// Front page
				$front_page_id = absint( get_option( 'page_on_front' ) );

				// Static page as front page
				if ( $front_page_id > 0 ) {
					$set_aria_current = '/' === $url;
				}

				// Latest posts as front page ($front_page_id === 0)
				// Check if homepage URL is the same as the URL we are checking after removing trailing slashes, maybe user will use '/' as well
				else {
					$set_aria_current = untrailingslashit( home_url( '/' ) ) === untrailingslashit( $url ) || '/' === $url;
				}
			}

			// Posts page(is_home()), Category, tag, archive etc.
			else {
				// URL starts with a slash (e.g. /category/business/): Add home URL
				if ( substr( $url, 0, 1 ) === '/' && substr( $url, 0, 2 ) !== '/' ) {
					$url = home_url( $url );
				}

				$url = trailingslashit( $url );

				$set_aria_current = strcmp( rtrim( $url ), rtrim( $current_page_url ) ) === 0;
			}
		}

		// Undocumented: Currently used in includes/woocommerce.php
		return apply_filters( 'bricks/element/maybe_set_aria_current_page', $set_aria_current, $url );
	}

	/**
	 * Parse textarea content to account for dynamic data usage
	 *
	 * Useful in 'One option / feature per line' situations
	 *
	 * Examples: Form element options (Checkbox, Select, Radio) or Pricing Tables (Features)
	 *
	 * @since 1.9
	 *
	 * @param string $options
	 * @return array
	 */
	public static function parse_textarea_options( $options ) {
		// Render possible dynamic data tags within the options string
		$options = bricks_render_dynamic_data( $options );

		// Strip tags like <p> or <br> when using ACF textarea or WYSIWYG fields
		// @pre 1.9.2 we used strip_tags, but we don't want to remove all HTML tags.
		$options = preg_replace( '~</?(p|br)[^>]*>~', '', $options );

		// At this point the parsed value might contain a trailing line break – remove it
		$options = rtrim( $options );

		// Finally return an array of options
		return explode( "\n", $options );
	}

	/**
	 * Use user agent string to detect browser
	 *
	 * @since 1.9.2
	 */
	public static function user_agent_to_browser( $user_agent ) {
		$value = '';

		if ( preg_match( '/chrome/i', $user_agent ) ) {
			$value = 'chrome';
		} elseif ( preg_match( '/firefox/i', $user_agent ) ) {
			$value = 'firefox';
		} elseif ( preg_match( '/safari/i', $user_agent ) ) {
			$value = 'safari';
		} elseif ( preg_match( '/edge/i', $user_agent ) ) {
			$value = 'edge';
		} elseif ( preg_match( '/opera/i', $user_agent ) ) {
			$value = 'opera';
		} elseif ( preg_match( '/msie/i', $user_agent ) ) {
			$value = 'msie';
		}

		return $value;
	}

	/**
	 * Use user agent string to detect operating system
	 *
	 * @since 1.9.2
	 * @since 1.10.2 Sequence matters: Start with the most specific one!
	 */
	public static function user_agent_to_os( $user_agent ) {
		$value = '';

		if ( preg_match( '/iphone/i', $user_agent ) ) {
			// Mozilla/5.0 (iPhone; CPU OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Mobile/15E148 Safari/604.1
			$value = 'iphone';
		} elseif ( preg_match( '/ipad/i', $user_agent ) ) {
			// Mozilla/5.0 (iPad; CPU OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Mobile/15E148 Safari/604.1
			$value = 'ipad';
		} elseif ( preg_match( '/ipod/i', $user_agent ) ) {
			$value = 'ipod';
		} elseif ( preg_match( '/android/i', $user_agent ) ) {
			// Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Mobile Safari/537.36
			$value = 'android';
		} elseif ( preg_match( '/blackberry/i', $user_agent ) ) {
			$value = 'blackberry';
		} elseif ( preg_match( '/webos/i', $user_agent ) ) {
			$value = 'webos';
		} elseif ( preg_match( '/ubuntu/i', $user_agent ) ) {
			// Mozilla/5.0 (X11; Ubuntu; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36
			$value = 'ubuntu';
		} elseif ( preg_match( '/linux/i', $user_agent ) ) {
			// Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36
			$value = 'linux';
		} elseif ( preg_match( '/mac/i', $user_agent ) ) {
			// Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:129.0) Gecko/20100101 Firefox/129.0
			$value = 'mac';
		} elseif ( preg_match( '/win/i', $user_agent ) ) {
			// Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36
			$value = 'windows';
		}

		return $value;
	}

	/**
	 * Get user IP address
	 *
	 * @since 1.9.2
	 */
	public static function user_ip_address() {
		$ip_address = '';

		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			// Cloudflare
			$ip_address = $_SERVER['HTTP_CF_CONNECTING_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Forwarded for
			$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			// Default
			$ip_address = $_SERVER['REMOTE_ADDR'];
		}

		// Validate IP address - compatible with both IPv4 & IPv6
		$ip_address = filter_var( $ip_address, FILTER_VALIDATE_IP );

		return $ip_address;
	}

	/**
	 * Populate query_vars to be used in Bricks template preview based on "Populate content" settings
	 *
	 * @since 1.9.1
	 */
	public static function get_template_preview_query_vars( $post_id ) {
		if ( ! $post_id ) {
			return [];
		}

		$query_args = [];

		$template_settings     = self::get_template_settings( $post_id );
		$template_preview_type = self::get_template_setting( 'templatePreviewType', $post_id );

		// @since 1.8 - Set preview type if direct edit page or post with Bricks (#861m48kv4)
		if ( bricks_is_builder_call() && empty( $template_settings ) && ! self::is_bricks_template( $post_id ) ) {
			$template_preview_type = 'direct-edit';
		}

		switch ( $template_preview_type ) {
			// Archive: Recent posts
			case 'archive-recent-posts':
				$query_args['post_type'] = 'post';
				break;

			// Archive: author
			case 'archive-author':
				$template_preview_author = self::get_template_setting( 'templatePreviewAuthor', $post_id );

				if ( $template_preview_author ) {
					$query_args['author'] = $template_preview_author;
				}
				break;

			// Author date
			case 'archive-date':
				$query_args['year'] = date( 'Y' );
				break;

			// Archive CPT
			case 'archive-cpt':
				$template_preview_post_type = self::get_template_setting( 'templatePreviewPostType', $post_id );

				if ( $template_preview_post_type ) {
					$query_args['post_type'] = $template_preview_post_type;
				}
				break;

			// Archive term
			case 'archive-term':
				$template_preview_term_id_parts = isset( $template_settings['templatePreviewTerm'] ) ? explode( '::', $template_settings['templatePreviewTerm'] ) : '';
				$template_preview_taxnomy       = isset( $template_preview_term_id_parts[0] ) ? $template_preview_term_id_parts[0] : '';
				$template_preview_term_id       = isset( $template_preview_term_id_parts[1] ) ? $template_preview_term_id_parts[1] : '';

				if ( $template_preview_taxnomy && $template_preview_term_id ) {
					$query_args['tax_query'] = [
						[
							'taxonomy' => $template_preview_taxnomy,
							'terms'    => $template_preview_term_id,
							'field'    => 'term_id',
						],
					];
				}
				break;

			// Search
			case 'search':
				$template_preview_search_term = self::get_template_setting( 'templatePreviewSearchTerm', $post_id );

				if ( $template_preview_search_term ) {
					$query_args['s'] = $template_preview_search_term;
				}
				break;

			// Single
			case 'direct-edit': // Editing template directly
			case 'single': // (template condition "Content type" = "Single post/page")
				$template_preview_post_id = self::get_template_setting( 'templatePreviewPostId', $post_id );

				if ( $template_preview_post_id ) {
					$query_args['p']         = $template_preview_post_id;
					$query_args['post_type'] = get_post_type( $template_preview_post_id );
				}

				break;
		}

		// NOTE: Undocumented
		$query_args = apply_filters( 'bricks/element/builder_setup_query', $query_args, $post_id );

		return $query_args;
	}

	/**
	 * Populate query vars if the element is not using query type controls
	 * Currently used in related-posts element and query_results_count when targeting related-posts element
	 *
	 * @return array
	 * @since 1.9.3
	 */
	public static function populate_query_vars_for_element( $element_data, $post_id ) {
		$element_name = $element_data['name'] ?? '';

		if ( empty( $element_name ) || is_null( $post_id ) ) {
			return [];
		}

		$settings = $element_data['settings'] ?? [];

		$args = [];

		if ( $element_name === 'related-posts' ) {
			$args = [
				'posts_per_page'         => $settings['count'] ?? 3,
				'post__not_in'           => [ $post_id ],
				'no_found_rows'          => true, // No pagination
				'orderby'                => $settings['orderby'] ?? 'rand',
				'order'                  => $settings['order'] ?? 'DESC',
				'post_status'            => 'publish',
				'objectType'             => 'post', // @since 1.9.3
				'bricks_skip_query_vars' => true, // skip Query::prepare_query_vars_from_settings() @since 1.9.3
			];

			if ( ! empty( $settings['post_type'] ) ) {
				$args['post_type'] = $settings['post_type'];
			}

			$taxonomies = $settings['taxonomies'] ?? [];

			foreach ( $taxonomies as $taxonomy ) {
				$terms_ids = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );

				if ( ! empty( $terms_ids ) ) {
					$args['tax_query'][] = [
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $terms_ids,
					];
				}
			}

			if ( count( $taxonomies ) > 1 && isset( $args['tax_query'] ) ) {
				$args['tax_query']['relation'] = 'OR';
			}

			// NOTE: Undocumented (not ideal naming as it's not only for related-posts element)
			$args = apply_filters( 'bricks/related_posts/query_vars', $args, $settings );
		}

		if ( $element_name === 'carousel' ) {
			$type = $settings['type'] ?? 'media';

			// Only populate query vars if type is 'media'
			if ( $type === 'media' ) {
				// STEP: Media type carousel might use dynamic data, so we need to normalize the settings (logic originally inside carousel element)
				$carousel_class_name = Elements::$elements['carousel']['class'] ?? false;

				if ( $carousel_class_name && ! empty( $settings['items']['useDynamicData'] ) ) {
					$carousel = new $carousel_class_name();

					$carousel->set_post_id( $post_id );

					$settings = self::get_normalized_image_settings( $carousel, $settings );
				}

				// STEP: Populate query vars based on the 'items' settings, if no items are set, no query vars are populated
				if ( ! empty( $settings['items']['images'] ) ) {
					$args = [
						'post_status'            => 'any',
						'post_type'              => 'attachment',
						'orderby'                => 'post__in',
						'objectType'             => 'post', // @since 1.9.3
						'bricks_skip_query_vars' => true, // Skip Query::prepare_query_vars_from_settings() @since 1.9.3
						'no_found_rows'          => true, // No pagination
					];

					$images = $settings['items']['images'] ?? $settings['items'];

					foreach ( $images as $image ) {
						if ( isset( $image['id'] ) ) {
							$args['post__in'][] = $image['id'];
						}
					}

					if ( isset( $args['post__in'] ) ) {
						$args['posts_per_page'] = count( $args['post__in'] );
					}
				}
			}
		}

		return $args;
	}

	/**
	 * Check if Query Filters are enabled (in Bricks settings)
	 *
	 * @since 1.9.6
	 */
	public static function enabled_query_filters() {
		return Database::get_setting( 'enableQueryFilters', false );
	}

	/**
	 * Check if Query Filters integration is enabled in Bricks settings
	 *
	 * @since 1.11.1
	 */
	public static function enabled_query_filters_integration() {
		return self::enabled_query_filters() && Database::get_setting( 'enableQueryFiltersIntegration', false );
	}

	/**
	 * Implode an array safely to avoid PHP warnings
	 *
	 * @since 1.10
	 */
	public static function safe_implode( $sep, $value ) {
		// Return: Value is not an array
		if ( ! is_array( $value ) ) {
			return (string) $value;
		}

		// Flattern the array and convert it to a string
		$flattern = array_map(
			function( $item ) use ( $sep ) {
				if ( is_array( $item ) ) {
					  return self::safe_implode( $sep, $item ); // Recursively flattern the array value
				}

				return (string) $item;
			},
			$value
		);

		return implode( $sep, $flattern );
	}

	/**
	 * Builds a hierarchical tree structure from a flat array of Bricks elements.
	 *
	 * The tree structure is used to process each element in a depth-first manner to maintain the hierarchy.
	 *
	 * Each element in the input array should be an associative array with the following structure:
	 * - id (string): Unique identifier for the element.
	 * - name (string): The name/type of the element (e.g., 'section', 'container', 'heading').
	 * - parent (string|int|null): The ID of the parent element, or 0/null if it is a root element.
	 * - children (array): An array of child element IDs (strings). Defaults to an empty array.
	 * - settings (array): An associative array of settings specific to the element. Defaults to an empty array.
	 *
	 * Example:
	 * [
	 *   [
	 *     "id" => "puikcj",
	 *     "name" => "section",
	 *     "parent" => 0,
	 *     "children" => ["vtjutb"],
	 *     "settings" => []
	 *   ],
	 *   [
	 *     "id" => "vtjutb",
	 *     "name" => "container",
	 *     "parent" => "puikcj",
	 *     "children" => ["jjnqht", "yvldmi", "cvrpll", "paayqb"],
	 *     "settings" => []
	 *   ],
	 *   [
	 *     "id" => "jjnqht",
	 *     "name" => "heading",
	 *     "parent" => "vtjutb",
	 *     "children" => [],
	 *     "settings" => ["text" => "I am a heading 1"]
	 *   ]
	 *   // More elements...
	 * ]
	 *
	 * @param array $elements
	 * @return array
	 *
	 * @since 1.10.2
	 */
	public static function build_elements_tree( $elements ) {
		// Validate input
		if ( ! is_array( $elements ) || empty( $elements ) ) {
			self::maybe_log( 'Bricks: Invalid or empty elements array provided to build_elements_tree' );
			return [];
		}

		$tree           = [];
		$indexed        = [];
		$children_order = [];

		// First pass: Index elements and store children order
		foreach ( $elements as $element ) {
			if ( ! isset( $element['id'] ) || ! is_string( $element['id'] ) ) {
				self::maybe_log( 'Bricks: Invalid element encountered: missing or invalid ID' );
				continue;
			}

			// Index the element and initialize its children array
			$indexed[ $element['id'] ]             = $element;
			$indexed[ $element['id'] ]['children'] = [];

			// Store the original children order if available
			if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
				$children_order[ $element['id'] ] = array_values( array_filter( $element['children'], 'is_string' ) );
			}
		}

		// Second pass: Build the hierarchical tree
		foreach ( $elements as $element ) {
			if ( ! isset( $element['id'] )) continue; // Skip invalid elements

			if ( ! empty( $element['parent'] ) && isset( $indexed[ $element['parent'] ] ) ) {
				// Add as child to parent
				$indexed[ $element['parent'] ]['children'][] = &$indexed[ $element['id'] ];
			} else {
				// Add to root of tree if no parent or parent not found
				$tree[] = &$indexed[ $element['id'] ];
			}
		}

		// Sort children based on the preserved order
		self::sort_elements_tree_children( $tree, $children_order );

		return $tree;
	}

	/**
	 * Recursively sorts children of elements based on the original order.
	 *
	 * @param array $elements Array of elements to sort.
	 * @param array $children_order Original order of children for each element.
	 *
	 * @since 1.10.2
	 */
	private static function sort_elements_tree_children( &$elements, $children_order ) {
		if ( ! is_array( $elements ) ) {
			self::maybe_log( 'Bricks: Invalid elements provided to sort_children' );
			return;
		}

		foreach ( $elements as &$element ) {
			if ( ! isset( $element['id'] ) || ! is_array( $element['children'] ) ) {
				continue; // Skip invalid elements
			}

			if ( ! empty( $element['children'] ) && isset( $children_order[ $element['id'] ] ) ) {
				$order = array_flip( $children_order[ $element['id'] ] );
				usort(
					$element['children'],
					function( $a, $b ) use ( $order ) {
						if ( ! isset( $a['id'] ) || ! isset( $b['id'] ) ) {
							self::maybe_log( 'Bricks: Invalid child element encountered during sorting' );
							return 0; // Don't change order for invalid elements
						}
						$pos_a = isset( $order[ $a['id'] ] ) ? $order[ $a['id'] ] : PHP_INT_MAX;
						$pos_b = isset( $order[ $b['id'] ] ) ? $order[ $b['id'] ] : PHP_INT_MAX;
						return $pos_a - $pos_b;
					}
				);
			}
			// Recursively sort children of this element
			self::sort_elements_tree_children( $element['children'], $children_order );
		}
	}

	/**
	 * Get HTML tag name from element settings
	 *
	 * @param array  $settings Element settings.
	 * @param string $default_tag Default tag name.
	 *
	 * @since 1.10.2
	 */
	public static function get_html_tag_from_element_settings( $settings, $default_tag ) {
		$custom_tag = $settings['customTag'] ?? '';
		$tag        = $settings['tag'] ?? '';

		// Use custom tag if set
		if ( $tag === 'custom' && $custom_tag ) {
			// Get HTML tag from string (first word, no attributes)
			$tag = explode( ' ', trim( $custom_tag ) )[0];
		}

		// Allowed HTML tags
		return self::sanitize_html_tag( $tag, $default_tag );
	}

	/**
	 * Sanitize HTML tag
	 *
	 * @since 1.10.2
	 */
	public static function sanitize_html_tag( $tag, $default_tag ) {
		// Get allowed HTML tags
		$allowed_html_tags = self::get_allowed_html_tags();

		// Custom tag not allowed: Use default tag
		if ( ! is_array( $allowed_html_tags ) || ! in_array( $tag, $allowed_html_tags, true ) ) {
			$tag = $default_tag;
		}

		return esc_html( $tag );
	}

	/**
	 * Return all allowed HTML tags
	 *
	 * @since 1.10.3
	 */
	public static function get_allowed_html_tags() {
		// Allowed HTML tags
		$allowed_html_tags = array_keys( wp_kses_allowed_html( 'post' ) );

		// Filter to extend HTML tag whitelist
		$extended_allowed_html_tags = apply_filters( 'bricks/allowed_html_tags', $allowed_html_tags );

		// Merge default and extended HTML tags
		$allowed_html_tags = is_array( $extended_allowed_html_tags ) ? array_merge( $allowed_html_tags, $extended_allowed_html_tags ) : $allowed_html_tags;

		// Remove duplicates
		$allowed_html_tags = array_unique( $allowed_html_tags );

		return array_values( $allowed_html_tags );
	}

	/**
	 * Return all valid pseudo classes
	 *
	 * Pseudo classes source of truth: https://developer.mozilla.org/en-US/docs/Web/CSS/Pseudo-classes
	 *
	 * @since 1.12
	 */
	public static function get_valid_pseudo_classes() {
		return [
			':active',
			':any-link',
			':autofill',
			':blank', // Experimental
			':checked',
			':current', // Experimental
			':default',
			':defined',
			':dir(', // Experimental
			':disabled',
			':empty',
			':enabled',
			':first',
			':first-child',
			':first-of-type',
			':fullscreen',
			':future', // Experimental
			':focus',
			':focus-visible',
			':focus-within',
			':has(', // Experimental
			':host',
			':host(',
			':host-context(', // Experimental
			':hover',
			':indeterminate',
			':in-range',
			':invalid',
			':is(',
			':lang(',
			':last-child',
			':last-of-type',
			':left',
			':link',
			':local-link', // Experimental
			':modal',
			':not(',
			':nth-child(',
			':nth-col(', // Experimental
			':nth-last-child(',
			':nth-last-col(', // Experimental
			':nth-last-of-type(',
			':nth-of-type(',
			':only-child',
			':only-of-type',
			':optional',
			':out-of-range',
			':past', // Experimental
			':picture-in-picture',
			':placeholder-shown',
			':paused',
			':playing',
			':popover-open', // @since 1.12
			':read-only',
			':read-write',
			':required',
			':right',
			':root',
			':scope',
			':state(', // Experimental
			':target',
			':target-within', // Experimental
			':user-invalid', // Experimental
			':valid',
			':visited',
			':where(',
		];
	}

	/**
	 * Return all valid pseudo elements
	 *
	 * Pseudo elements source of truth: https://developer.mozilla.org/en-US/docs/Web/CSS/Pseudo-elements
	 *
	 * @since 1.12
	 */
	public static function get_valid_pseudo_elements() {
		return [
			'::after',
			':after',

			'::backdrop',
			':backdrop',

			'::before',
			':before',

			'::cue',
			':cue',

			'::cue-region',
			':cue-region',

			'::first-letter',
			':first-letter',

			'::first-line',
			':first-line',

			'::file-selector-button',
			':file-selector-button',

			'::grammar-error',
			':grammar-error',

			// @since 1.12
			'::highlight(',
			':highlight(',

			'::marker',
			':marker',

			// @since 1.12: Uncommented
			'::part(',
			':part(',

			'::placeholder',
			':placeholder',

			'::selection',
			':selection',

			// @since 1.12: Uncommented
			'::slotted(',
			':slotted(',

			'::spelling-error',
			':spelling-error',

			'::target-text',
			':target-text',

			// @since 1.12
			'::view-transition',
			':view-transition',

			// @since 1.12
			'::view-transition-image-pair(',
			':view-transition-image-pair(',

			// @since 1.12
			'::view-transition-group(',
			':view-transition-group(',

			// @since 1.12
			'::view-transition-new',
			':view-transition-new',

			// @since 1.12
			'::view-transition-old',
			':view-transition-old',
		];
	}

	/**
	 * Apply wp_filter_post_kses to all string values in an array
	 *
	 * @since 1.11
	 */
	public static function apply_wp_filter_post_kses_to_array( &$item, $key ) {
		if ( is_string( $item ) ) {
			$item = wp_filter_post_kses( $item );
		}
	}

	/**
	 * Scan site for global class usage
	 *
	 * @since 1.12
	 *
	 * @return array ['usedClasses' => array, 'unusedClasses' => array]
	 */
	public static function scan_global_classes_site_usage() {
		// Get all global classes
		$global_classes = get_option( BRICKS_DB_GLOBAL_CLASSES, [] );

		// Get array of just the IDs from the global classes objects
		$class_ids = array_map(
			function( $class ) {
				return $class['id'];
			},
			$global_classes
		);

		// Initialize usage tracking
		$used_classes = [];

		// Get all posts/templates that use Bricks
		$post_types = array_merge(
			array_keys( self::get_supported_post_types() ),
			[ BRICKS_DB_TEMPLATE_SLUG ]
		);

		$query = new \WP_Query(
			[
				'post_type'      => $post_types,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => 'any',
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => BRICKS_DB_PAGE_HEADER,
						'compare' => 'EXISTS',
					],
					[
						'key'     => BRICKS_DB_PAGE_CONTENT,
						'compare' => 'EXISTS',
					],
					[
						'key'     => BRICKS_DB_PAGE_FOOTER,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		// Scan each post's Bricks data
		foreach ( $query->posts as $post_id ) {
			self::scan_elements_for_global_classes( Database::get_data( $post_id, 'header' ), $used_classes );
			self::scan_elements_for_global_classes( Database::get_data( $post_id, 'content' ), $used_classes );
			self::scan_elements_for_global_classes( Database::get_data( $post_id, 'footer' ), $used_classes );
		}

		// Also scan global elements
		$global_elements = get_option( BRICKS_DB_GLOBAL_ELEMENTS, [] );
		self::scan_elements_for_global_classes( $global_elements, $used_classes );

		// NOTE: Should we also scan BRICKS_DB_PAGE_SETTINGS and BRICKS_DB_GLOBAL_SETTINGS?
		// These might contain global class references in custom CSS or other settings

		// Make used classes unique AND only include ones that exist in global classes
		$used_classes = array_values(
			array_intersect(
				array_unique( $used_classes ), // First make unique
				$class_ids                   // Then only keep ones that exist in global classes
			)
		);

		// Determine unused classes
		$unused_classes = array_values( array_diff( $class_ids, $used_classes ) );

		return [
			'usedClasses'   => $used_classes,
			'unusedClasses' => $unused_classes
		];
	}

	/**
	 * Recursively scan elements for global classes
	 *
	 * @since 1.12
	 *
	 * @param array $elements Array of Bricks elements.
	 * @param array &$used_classes Reference to array tracking used class IDs.
	 */
	private static function scan_elements_for_global_classes( $elements, &$used_classes ) {
		if ( ! is_array( $elements ) ) {
			return;
		}

		foreach ( $elements as $element ) {
			// Check element's global classes
			if ( ! empty( $element['settings']['_cssGlobalClasses'] ) ) {
				foreach ( $element['settings']['_cssGlobalClasses'] as $class_id ) {
					// Add to array directly instead of using as key
					$used_classes[] = $class_id;
				}
			}
		}
	}

	/**
	 * Generate a label for a taxonomy
	 *
	 * @param string $taxonomy
	 * @return string $taxonomy_label
	 * @since 1.12
	 */
	public static function generate_taxonomy_label( $taxonomy ) {
		$taxonomy_label  = '';
		$taxonomy_object = get_taxonomy( $taxonomy );
		$taxonomy_label  = '';

		if ( gettype( $taxonomy_object ) === 'object' ) {
			$taxonomy_label = $taxonomy_object->labels->name;
		} else {
			if ( $taxonomy === BRICKS_DB_TEMPLATE_TAX_TAG ) {
				$taxonomy_label = esc_html__( 'Template tag', 'bricks' );
			}

			if ( $taxonomy === BRICKS_DB_TEMPLATE_TAX_BUNDLE ) {
				$taxonomy_label = esc_html__( 'Template bundle', 'bricks' );
			}
		}

		// Avoid empty taxonomy label that will be confusing (@since 1.12)
		if ( $taxonomy_label === '' ) {
			$taxonomy_label = ucwords( str_replace( '_', ' ', $taxonomy ) );
		}

		return $taxonomy_label;
	}

	/**
	 * Add category metadata to classes for transfer operations
	 *
	 * @param array $classes Array of classes to process
	 * @return array Classes with category metadata
	 *
	 * @since 1.12.2
	 */
	public static function add_category_metadata_to_classes( $classes ) {
		if ( empty( $classes ) || ! is_array( $classes ) ) {
			return $classes;
		}

		// Get categories from database
		$categories = get_option( BRICKS_DB_GLOBAL_CLASSES_CATEGORIES, [] );

		if ( empty( $categories ) ) {
			return $classes;
		}

		// Add category data for each class that has a category
		foreach ( $classes as &$class ) {
			if ( ! empty( $class['category'] ) ) {
				// Find category data
				foreach ( $categories as $category ) {
					if ( $category['id'] === $class['category'] ) {
						$class['_categoryData'] = [
							'id'   => $category['id'],
							'name' => $category['name']
						];
						break;
					}
				}
			}
		}

		return $classes;
	}

	/**
	 * Get query object for dynamic tags {query_results_count} and Query Results Summary element
	 * If unable to get query object from history, init query object based on element settings
	 *
	 * Originally located in provider-wp.php
	 *
	 * @since 1.12.2
	 */
	public static function get_query_object_from_history_or_init( $element_id, $post_id ) {
		$query_object = false;

		// Element ID provided: Get query object from query history
		if ( ! empty( $element_id ) ) {
			$query_object = Query::get_query_by_element_id( $element_id, true );
		} else {
			// No element ID provided, get the current query object
			$query_object = Query::get_query_object( Query::is_any_looping() );
		}

		// No query object found. Init query (@since 1.9.1.1)
		if ( ! $query_object ) {
			// Set $post_id or element_data will be empty (@since 1.10.1; @see #86bzwjx3u)
			if ( Query::is_any_looping() && isset( Database::$page_data['preview_or_post_id'] ) ) {
				$post_id = Database::$page_data['preview_or_post_id'];
			}

			$element_data = self::get_element_data( $post_id, $element_id );
			$element_name = $element_data['element']['name'] ?? '';

			// Support query element is a component (root) (@since 1.12.2)
			if ( ! empty( $element_data['element']['cid'] ) ) {
				$component_settings                  = self::get_component_instance( $element_data['element'], 'settings' );
				$element_data['element']['settings'] = $component_settings;
			}

			if ( ! empty( $element_name ) && isset( $element_data['element']['settings'] ) ) {
				// Populate query settings for elements that is not using standard query controls (@since 1.9.3)
				if ( in_array( $element_name, [ 'carousel', 'related-posts' ] ) ) {
					$query_settings = self::populate_query_vars_for_element( $element_data['element'], $post_id );

					/**
					 * Override query settings.
					 * Carousel 'posts' type should returning empty from this function as it is using standard query controls
					 */
					if ( ! empty( $query_settings ) ) {
						$element_data['element']['settings']['query'] = $query_settings;
					}

					/**
					 * If this is a carousel 'posts' type, $query_settings should be empty as the query_settings is populated from standard query controls
					 * However, if the standard query controls is empty (user use default posts query), then we need to set 'query' key so or we are not able to init query in next step
					 */
					elseif ( $element_name === 'carousel' && empty( $query_settings ) ) {
						$carousel_type = $element_data['element']['settings']['type'] ?? 'posts';
						if ( $carousel_type === 'posts' && empty( $element_data['element']['settings']['query'] ) ) {
							$element_data['element']['settings']['query'] = [];
						}
					}
				}

				// Add query setting key for Posts element when default (no settings), otherwise count always zero (@since 1.9.8)
				if ( $element_name === 'posts' && empty( $element_data['element']['settings']['query'] ) ) {
					$element_data['element']['settings']['query'] = [];
				}

				// Only init query if query settings is available
				if ( isset( $element_data['element']['settings']['query'] ) ) {
					$query_object = new Query( $element_data['element'] );
					if ( $query_object ) {
						$query_object->destroy();
					}
				}
			}
		}

		return $query_object;
	}

	/**
	 * Determine whether to run additional logic to enqueue no results children script
	 *
	 * @since 1.12.2
	 *
	 * @return bool
	 */
	public static function handle_no_results_children_elements() {
		$run = self::enabled_query_filters();

		return apply_filters( 'bricks/handle_no_results_children_elements', $run );
	}

	/**
	 * Get normalized image settings
	 *
	 * @since 2.0
	 */
	public static function get_normalized_image_settings( $instance, $settings ) {
		$items = $settings['items'] ?? [];
		$size  = $items['size'] ?? BRICKS_DEFAULT_IMAGE_SIZE;

		// Dynamic data
		if ( ! empty( $items['useDynamicData'] ) ) {
			$items['images'] = [];
			$images          = $instance->render_dynamic_data_tag( $items['useDynamicData'], 'image' );

			if ( is_array( $images ) ) {
				foreach ( $images as $image_id ) {
					$items['images'][] = [
						'id'   => $image_id,
						'full' => wp_get_attachment_image_url( $image_id, 'full' ),
						'url'  => wp_get_attachment_image_url( $image_id, $size )
					];
				}
			}
		}

		// Old data structure (images were saved as one array directly on $items)
		if ( ! isset( $items['images'] ) ) {
			$images = ! empty( $items ) ? $items : [];

			unset( $items );

			$items['images'] = $images;
		}

		// Get 'size' from first image if not set
		$first_image_size = ! empty( $items['images'][0]['size'] ) ? $items['images'][0]['size'] : false;
		$size             = empty( $items['size'] ) && $first_image_size ? $first_image_size : $size;

		// Get image 'url' for requested $size
		foreach ( $items['images'] as $key => $image ) {
			if ( ! empty( $image['id'] ) ) {
				$items['images'][ $key ]['url'] = wp_get_attachment_image_url( $image['id'], $size );
			}
		}

		$settings['items']         = $items;
		$settings['items']['size'] = $size;

		return $settings;
	}

	/**
	 * Check if file is valid SVG
	 *
	 * @param string $file SVG content
	 *
	 * @since 2.0
	 */
	public static function is_valid_svg( $file ) {
		// Check if $file is a string
		if ( ! is_string( $file ) ) {
			return false;
		}

		// Check if SVG is valid
		$file = trim( $file );

		// Check if SVG is empty
		if ( empty( $file ) ) {
			return false;
		}

		// Check if SVG starts with <svg or <xml, and contains </svg>
		return ( strpos( $file, '<svg' ) === 0 || strpos( $file, '<?xml' ) === 0 ) && strpos( $file, '</svg>' ) !== false;
	}

	/**
	 * Send user activation email
	 *
	 * @param number $user_id User ID
	 * @param string $email_type Email type that should be sent (activation, resend-activation)
	 *
	 * @since 2.1
	 */
	public static function send_user_activation_email( $user_id, $email_type ) {
		// Return: Required data missing
		if ( ! $user_id || ! isset( $email_type ) || ! in_array( $email_type, [ 'activation', 'resend-activation' ] ) ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return;
		}

		// STEP: Prepare global variables
		$from_email = Database::get_setting( 'userActivationLinkEmailFrom' );
		$from_name  = Database::get_setting( 'userActivationLinkEmailFromName' );
		$subject    = Database::get_setting( 'userActivationLinkEmailSubject' );
		$content    = Database::get_setting( 'userActivationLinkEmailContent' );
		$is_html    = Database::get_setting( 'userActivationLinkEmailIsHtml', false );

		$additional_params = [];

		// STEP: Prepare headers
		$headers = [];

		// Header: 'From'
		$from_email = ! empty( $from_email ) ? sanitize_email( $from_email ) : false;

		if ( $from_email ) {
			$from_name = ! empty( $from_name ) ? sanitize_text_field( $from_name ) : false;
			$headers[] = $from_name ? "From: $from_name <$from_email>" : "From: $from_email";
		}

		// Header: 'Content-Type'
		$headers[] = $is_html ? 'Content-Type: text/html; charset=UTF-8' : 'Content-Type: text/plain; charset=UTF-8';

		// STEP: $email_type = activation or resend-activation
		$activation_key = get_user_meta( $user_id, 'bricks_user_activation_key', true );

		// STEP: Prepare activation link
		$activation_link = '';
		$activation_url  = '';

		$activation_url = add_query_arg(
			[
				'user_id'        => $user_id,
				'activation_key' => $activation_key
			],
			// Redirect to custom success page or home page
			get_permalink( Database::get_setting( 'userActivationLinkSuccessPage', null ) ) ?: home_url()
		);

		$activation_link = $is_html
			? '<a href="' . esc_url( $activation_url ) . '" target="_blank">' . esc_html( $activation_url ) . '</a>'
			: $activation_url;

		$additional_params['activation_url']  = $activation_url; // https://<site-url>/?params...
		$additional_params['activation_link'] = $activation_link; // <a href="...">...</a>

		// Add line breaks if HTML email is enabled and no HTML template exists (@since 1.11.1)
		if ( $is_html && strpos( $content, '<html' ) === false ) {
			$content = nl2br( $content );
		}

		$email['subject'] = self::replace_user_activation_email_parameters( $subject, $user, $additional_params, false );
		$email['message'] = self::replace_user_activation_email_parameters( $content, $user, $additional_params, $is_html );

		if ( empty( $email['subject'] ) ) {
			$email['subject'] = esc_html__( 'Activate your account', 'bricks' );
		}

		if ( empty( $email['message'] ) ) {
			$email['message']  = esc_html__( 'Please click the link below to activate your account', 'bricks' ) . ':';
			$email['message'] .= $is_html ? "<br>$activation_link" : "\n$activation_url";
		}

		// STEP: Send email
		$email_sent = wp_mail( $user->user_email, $email['subject'], $email['message'], $headers );

		// Log email send error
		if ( ! $email_sent ) {
			self::maybe_log( 'Bricks: Failed to send user activation email: ' . print_r( error_get_last(), true ) );
		}

		// STEP: Return
		return $email_sent;
	}

	/**
	 * Set user meta: activation key and status
	 *
	 * @param int $user_id
	 *
	 * @since 2.1
	 */
	public static function set_activation_meta( $user_id ) {
		$activation_key = null;

		// "Link" activation key
		$activation_key = wp_generate_password( 20, false );

		// Update user meta with activation key and status
		update_user_meta( $user_id, 'bricks_user_activation_key', $activation_key );
		update_user_meta( $user_id, 'bricks_user_activation_status', 'pending' );

		return $activation_key;
	}

	/**
	 * Private function, that will replace the available parameters for user activation email
	 *
	 * @param string $content
	 * @param object $user
	 * @param array  $additional_params Optional additional parameters
	 * @param bool   $is_html Optional flag to indicate if the content is HTML
	 *
	 * @since 2.1
	 * @return string
	 */
	private static function replace_user_activation_email_parameters( $content, $user, $additional_params = [], $is_html = false ) {
		if ( ! $content || ! $user ) {
			return $content;
		}

		// Site parameters
		$site_name    = get_bloginfo( 'name' );
		$site_tagline = get_bloginfo( 'description' );
		$site_url     = home_url();

		// User parameters
		$username          = $user->user_login;
		$user_display_name = $user->display_name;
		$user_first_name   = $user->first_name;
		$user_last_name    = $user->last_name;
		$user_email        = $user->user_email;

		// Additional parameters (activation link, activation url,...)
		$activation_link = isset( $additional_params['activation_link'] ) ? $additional_params['activation_link'] : '';
		$activation_url  = isset( $additional_params['activation_url'] ) ? $additional_params['activation_url'] : '';

		// Replace parameters
		$search = [
			'{{site_name}}',
			'{{site_tagline}}',
			'{{site_url}}',
			'{{username}}',
			'{{user_display_name}}',
			'{{user_first_name}}',
			'{{user_last_name}}',
			'{{user_email}}',
			'{{activation_link}}',
			'{{activation_url}}',
		];

		$replace = [
			$site_name,         // Replaces {{site_name}}
			$site_tagline,      // Replaces {{site_tagline}}
			$site_url,          // Replaces {{site_url}}
			$username,          // Replaces {{username}}
			$user_display_name, // Replaces {{user_display_name}}
			$user_first_name,   // Replaces {{user_first_name}}
			$user_last_name,    // Replaces {{user_last_name}}
			$user_email,        // Replaces {{user_email}}
			$activation_link,   // Replaces {{activation_link}}
			$activation_url,        // Replaces {{activation_url}}
		];

		// Apply replacement
		$content = str_replace( $search, $replace, $content );

		return $content;
	}

	/**
	 * Process settings to update variable references
	 *
	 * @param array  $settings Settings to process.
	 * @param string $old_name Old variable name.
	 * @param string $new_name New variable name.
	 *
	 * @return array Updated settings.
	 * @since 2.0
	 */
	public static function process_settings_for_variable_rename( $settings, $old_name, $new_name ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		// Create regex pattern to match var(--name) with any whitespace variations
		$var_pattern     = '/var\(\s*--\s*' . preg_quote( $old_name, '/' ) . '\s*\)/';
		$var_replacement = 'var(--' . $new_name . ')';

		foreach ( $settings as $key => $value ) {
			// Process nested arrays recursively
			if ( is_array( $value ) ) {
				$settings[ $key ] = self::process_settings_for_variable_rename( $value, $old_name, $new_name );
			}
			// Process string values that might contain CSS variable references
			elseif ( is_string( $value ) ) {
				$updated_value = preg_replace( $var_pattern, $var_replacement, $value );
				if ( $updated_value !== $value ) {
					$settings[ $key ] = $updated_value;
				}
			}
		}

		return $settings;
	}

	/**
	 * Process elements to update variable references
	 *
	 * @since 2.0
	 *
	 * @param array  $elements Array of elements.
	 * @param string $old_name Old variable name.
	 * @param string $new_name New variable name.
	 *
	 * @return array Updated elements
	 */
	public static function process_elements_for_variable_rename( $elements, $old_name, $new_name ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}

		// Process each element's settings
		foreach ( $elements as $key => $element ) {
			// Process element settings
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$elements[ $key ]['settings'] = self::process_settings_for_variable_rename( $element['settings'], $old_name, $new_name );
			}
		}

		return $elements;
	}

	/**
	 * Update all references to a renamed global CSS variable across the site
	 *
	 * @since 2.0
	 *
	 * @param string $old_name Old variable name (without the -- prefix).
	 * @param string $new_name New variable name (without the -- prefix).
	 *
	 * @return array Results with counts of updated items.
	 */
	public static function update_global_variable_references( $old_name, $new_name ) {
		// Initialize results array to track updates
		$results = [
			'posts_updated'             => 0,
			'elements_updated'          => 0,
			'classes_updated'           => 0,
			'components_updated'        => 0,
			'settings_updated'          => false,
			'palette_updated'           => false,
			'styles_updated'            => false,
			'template_settings_updated' => false,
			'page_settings_updated'     => 0,
		];

		// Skip if old and new names are the same
		if ( $old_name === $new_name ) {
			return $results;
		}

		// Create regex pattern to match var(--name) with any whitespace variations
		$var_pattern     = '/var\(\s*--\s*' . preg_quote( $old_name, '/' ) . '\s*\)/';
		$var_replacement = 'var(--' . $new_name . ')';

		// STEP 1: Process posts with Bricks data (header, content, footer)
		// Get all posts/templates that use Bricks
		$post_types = array_merge(
			array_keys( self::get_supported_post_types() ),
			[ BRICKS_DB_TEMPLATE_SLUG ]
		);

		$query    = new \WP_Query(
			[
				'post_type'      => $post_types,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => 'any',
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => BRICKS_DB_PAGE_HEADER,
						'compare' => 'EXISTS',
					],
					[
						'key'     => BRICKS_DB_PAGE_CONTENT,
						'compare' => 'EXISTS',
					],
					[
						'key'     => BRICKS_DB_PAGE_FOOTER,
						'compare' => 'EXISTS',
					],
				],
			]
		);
		$post_ids = $query->posts;

		foreach ( $post_ids as $post_id ) {
			$post_updated = false;

			// Check header, content, footer
			foreach ( [ 'header', 'content', 'footer' ] as $area ) {
				$elements = get_post_meta( $post_id, Database::get_bricks_data_key( $area ), true );

				if ( ! empty( $elements ) && is_array( $elements ) ) {
					$updated_elements = self::process_elements_for_variable_rename( $elements, $old_name, $new_name );

					if ( $updated_elements !== $elements ) {
						update_post_meta( $post_id, Database::get_bricks_data_key( $area ), $updated_elements );
						$post_updated = true;
						$results['elements_updated']++;
					}
				}
			}

			// Check page settings
			$page_settings = get_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS, true );
			if ( ! empty( $page_settings ) && is_array( $page_settings ) ) {
				$updated_settings = self::process_settings_for_variable_rename( $page_settings, $old_name, $new_name );
				if ( $updated_settings !== $page_settings ) {
					update_post_meta( $post_id, BRICKS_DB_PAGE_SETTINGS, $updated_settings );
					$post_updated = true;
					$results['page_settings_updated']++;
				}
			}

			// Check template settings (if this is a template)
			if ( get_post_type( $post_id ) === BRICKS_DB_TEMPLATE_SLUG ) {
				$template_settings = get_post_meta( $post_id, BRICKS_DB_TEMPLATE_SETTINGS, true );
				if ( ! empty( $template_settings ) && is_array( $template_settings ) ) {
					$updated_settings = self::process_settings_for_variable_rename( $template_settings, $old_name, $new_name );
					if ( $updated_settings !== $template_settings ) {
						update_post_meta( $post_id, BRICKS_DB_TEMPLATE_SETTINGS, $updated_settings );
						$post_updated                         = true;
						$results['template_settings_updated'] = true;
					}
				}
			}

			if ( $post_updated ) {
				$results['posts_updated']++;
			}
		}

		// STEP 2: Process global elements
		$global_elements = get_option( BRICKS_DB_GLOBAL_ELEMENTS, [] );
		if ( is_array( $global_elements ) && ! empty( $global_elements ) ) {
			$elements_updated = false;

			foreach ( $global_elements as $key => $element ) {
				$updated_element = self::process_settings_for_variable_rename( $element, $old_name, $new_name );
				if ( $updated_element !== $element ) {
					$global_elements[ $key ] = $updated_element;
					$elements_updated        = true;
					$results['elements_updated']++;
				}
			}

			if ( $elements_updated ) {
				update_option( BRICKS_DB_GLOBAL_ELEMENTS, $global_elements );
			}
		}

		// STEP 3: Process global classes
		$global_classes = get_option( BRICKS_DB_GLOBAL_CLASSES, [] );
		if ( is_array( $global_classes ) && ! empty( $global_classes ) ) {
			$classes_updated = false;

			foreach ( $global_classes as $key => $class ) {
				if ( isset( $class['settings'] ) && is_array( $class['settings'] ) ) {
					$updated_settings = self::process_settings_for_variable_rename( $class['settings'], $old_name, $new_name );
					if ( $updated_settings !== $class['settings'] ) {
						$global_classes[ $key ]['settings'] = $updated_settings;
						$classes_updated                    = true;
						$results['classes_updated']++;
					}
				}
			}

			if ( $classes_updated ) {
				update_option( BRICKS_DB_GLOBAL_CLASSES, $global_classes );
			}
		}

		// STEP 4: Process global settings (custom CSS)
		$global_settings = get_option( BRICKS_DB_GLOBAL_SETTINGS, [] );
		if ( is_array( $global_settings ) && ! empty( $global_settings ) ) {
			$settings_updated = false;

			// Check custom CSS
			if ( isset( $global_settings['customCss'] ) ) {
				$custom_css  = $global_settings['customCss'];
				$updated_css = preg_replace( $var_pattern, $var_replacement, $custom_css );

				if ( $updated_css !== $custom_css ) {
					$global_settings['customCss'] = $updated_css;
					$settings_updated             = true;
				}
			}

			// Check other settings that might contain CSS variables
			foreach ( $global_settings as $key => $value ) {
				if ( is_array( $value ) ) {
					$updated_value = self::process_settings_for_variable_rename( $value, $old_name, $new_name );
					if ( $updated_value !== $value ) {
						$global_settings[ $key ] = $updated_value;
						$settings_updated        = true;
					}
				} elseif ( is_string( $value ) ) {
					$updated_value = preg_replace( $var_pattern, $var_replacement, $value );
					if ( $updated_value !== $value ) {
						$global_settings[ $key ] = $updated_value;
						$settings_updated        = true;
					}
				}
			}

			if ( $settings_updated ) {
				update_option( BRICKS_DB_GLOBAL_SETTINGS, $global_settings );
				$results['settings_updated'] = true;
			}
		}

		// STEP 5: Process color palette (check for CSS variable references in color values)
		$color_palette = get_option( BRICKS_DB_COLOR_PALETTE, [] );
		if ( is_array( $color_palette ) && ! empty( $color_palette ) ) {
			$palette_updated = false;

			foreach ( $color_palette as $palette_key => $palette ) {
				// Check if this palette has a colors array
				if ( isset( $palette['colors'] ) && is_array( $palette['colors'] ) ) {
					foreach ( $palette['colors'] as $color_key => $color ) {
						// Check for CSS variable references in 'raw' property
						if ( isset( $color['raw'] ) && is_string( $color['raw'] ) ) {
							$updated_value = preg_replace( $var_pattern, $var_replacement, $color['raw'] );
							if ( $updated_value !== $color['raw'] ) {
								$color_palette[ $palette_key ]['colors'][ $color_key ]['raw'] = $updated_value;
								$palette_updated = true;
							}
						}
					}
				}
			}

			if ( $palette_updated ) {
				update_option( BRICKS_DB_COLOR_PALETTE, $color_palette );
				$results['palette_updated'] = true;
			}
		}

		// STEP 6: Process theme styles
		$theme_styles = get_option( BRICKS_DB_THEME_STYLES, [] );
		if ( is_array( $theme_styles ) && ! empty( $theme_styles ) ) {
			$styles_updated = false;

			foreach ( $theme_styles as $key => $style ) {
				if ( isset( $style['settings'] ) && is_array( $style['settings'] ) ) {
					$updated_settings = self::process_settings_for_variable_rename( $style['settings'], $old_name, $new_name );
					if ( $updated_settings !== $style['settings'] ) {
						$theme_styles[ $key ]['settings'] = $updated_settings;
						$styles_updated                   = true;
					}
				}
			}

			if ( $styles_updated ) {
				update_option( BRICKS_DB_THEME_STYLES, $theme_styles );
				$results['styles_updated'] = true;
			}
		}

		// STEP 7: Process components
		$components = get_option( BRICKS_DB_COMPONENTS, [] );
		if ( is_array( $components ) && ! empty( $components ) ) {
			$components_updated = false;

			foreach ( $components as $key => $component ) {
				if ( isset( $component['elements'] ) && is_array( $component['elements'] ) ) {
					$updated_elements = self::process_elements_for_variable_rename( $component['elements'], $old_name, $new_name );
					if ( $updated_elements !== $component['elements'] ) {
						$components[ $key ]['elements'] = $updated_elements;
						$components_updated             = true;
						$results['components_updated']++;
					}
				}
			}

			if ( $components_updated ) {
				update_option( BRICKS_DB_COMPONENTS, $components );
			}
		}

		return $results;
	}

	/**
	 * Render a "NEW" badge if BRICKS_VERSION is not larger than the next minor $version
	 *
	 * Example: BRICKS_VERSION = 2.1, $version_added = 2.0 => "New" badge is no longer rendered as its the next minor version.
	 *
	 * @since 2.0
	 */
	public static function render_badge( $version_added ) {
		// Split version2, fall back to 0 for missing parts
		$parts = array_map( 'intval', explode( '.', $version_added ) );
		$major = $parts[0] ?? 0;
		$minor = $parts[1] ?? 0;

		// Build the next-minor version string, patch resets to 0
		$next_minor = sprintf( '%d.%d.0', $major, $minor + 1 );

		// version_compare understands SemVer, pre-release tags, etc.
		if ( ! version_compare( $next_minor, BRICKS_VERSION, '<=' ) ) {
			return '<span class="badge">' . esc_html__( 'New', 'bricks' ) . '</span>';
		}
	}

	/**
	 * Find orphaned elements across all Bricks posts and templates
	 *
	 * @since 2.0
	 *
	 * @return array {
	 *     @type array $orphaned_by_post_id Array of post IDs with orphaned elements
	 *     @type int   $total_orphans       Total number of orphaned elements found
	 *     @type int   $total_posts         Total number of posts with orphaned elements
	 * }
	 */
	public static function find_orphaned_elements_across_site() {
		$result = [
			'orphaned_by_post_id' => [],
			'total_orphans'       => 0,
			'total_posts'         => 0,
		];

		// Get all Bricks post IDs (templates and content)
		$template_ids = Templates::get_all_template_ids();
		$content_ids  = self::get_all_bricks_post_ids();
		$all_post_ids = array_merge( $template_ids, $content_ids );

		foreach ( $all_post_ids as $post_id ) {
			$post_orphans = self::find_orphaned_elements_in_post( $post_id );

			if ( ! empty( $post_orphans['orphaned_elements'] ) && $post_orphans['total_orphans'] > 0 ) {
				$result['orphaned_by_post_id'][ $post_id ] = array_merge(
					$post_orphans,
					[
						'post_title' => get_the_title( $post_id ),
						'permalink'  => get_permalink( $post_id ),
					]
				);
				$result['total_orphans']                  += $post_orphans['total_orphans'];
				$result['total_posts']++;
			}
		}

		return $result;
	}

	/**
	 * Find orphaned elements in a specific post
	 *
	 * @since 2.0
	 *
	 * @param int $post_id The post ID to check
	 *
	 * @return array {
	 *     @type array $orphaned_elements Array of orphaned elements by area
	 *     @type int   $total_orphans     Total number of orphaned elements in this post
	 * }
	 */
	public static function find_orphaned_elements_in_post( $post_id ) {
		$result = [
			'orphaned_elements' => [],
			'total_orphans'     => 0,
		];

		$areas_to_check = [ 'content', 'header', 'footer' ];

		foreach ( $areas_to_check as $area ) {
			$elements = Database::get_data( $post_id, $area );

			if ( ! is_array( $elements ) || empty( $elements ) ) {
				continue;
			}

			$orphaned_data = self::find_orphaned_elements( $elements );

			if ( ! empty( $orphaned_data['orphaned_by_area'] ) ) {
				$result['orphaned_elements'][ $area ] = $orphaned_data['orphaned_by_area'];
				$result['total_orphans']             += $orphaned_data['total_orphans'];
			}
		}

		return $result;
	}

	/**
	 * Find orphaned elements within an array of elements
	 *
	 * @since 2.0
	 *
	 * @param array $elements Array of Bricks elements
	 *
	 * @return array {
	 *     @type array $orphaned_by_area Array of orphaned elements
	 *     @type int   $total_orphans    Total number of orphaned elements
	 * }
	 */
	public static function find_orphaned_elements( $elements ) {
		if ( ! is_array( $elements ) || empty( $elements ) ) {
			return [
				'orphaned_by_area' => [],
				'total_orphans'    => 0,
			];
		}

		// Create a map of all element IDs for quick lookup
		$element_ids = [];
		foreach ( $elements as $element ) {
			if ( isset( $element['id'] ) ) {
				$element_ids[ $element['id'] ] = true;
			}
		}

		$orphaned_elements = [];

		// Check each element for orphaned status
		foreach ( $elements as $element ) {
			if ( ! isset( $element['id'] ) || ! isset( $element['parent'] ) ) {
				continue;
			}

			$parent_id = $element['parent'];

			// Root-level elements have no parent or parent === 0 / '0'
			if ( ! $parent_id || $parent_id === 0 || $parent_id === '0' ) {
				continue;
			}

			// Check if parent exists
			if ( ! isset( $element_ids[ $parent_id ] ) ) {
				$orphaned_elements[] = $element['id'];
			}
		}

		return [
			'orphaned_by_area' => $orphaned_elements,
			'total_orphans'    => count( $orphaned_elements ),
		];
	}

	/**
	 * Clean up orphaned elements across all posts with orphaned elements
	 *
	 * @since 2.0
	 *
	 * @param array $orphaned_data Data from find_orphaned_elements_across_site()
	 *
	 * @return array {
	 *     @type bool $success True if cleanup was successful
	 *     @type int  $total_cleaned Total number of elements cleaned up
	 *     @type int  $posts_cleaned Number of posts that were cleaned up
	 * }
	 */
	public static function cleanup_orphaned_elements_across_site( $orphaned_data ) {
		$total_cleaned = 0;
		$posts_cleaned = 0;

		if ( empty( $orphaned_data['orphaned_by_post_id'] ) ) {
			return [
				'success'       => true,
				'total_cleaned' => 0,
				'posts_cleaned' => 0,
			];
		}

		foreach ( $orphaned_data['orphaned_by_post_id'] as $post_id => $post_orphaned_data ) {
			$cleanup_result = self::cleanup_orphaned_elements_in_post( $post_id, $post_orphaned_data );

			if ( $cleanup_result['success'] && $cleanup_result['cleaned_count'] > 0 ) {
				$total_cleaned += $cleanup_result['cleaned_count'];
				$posts_cleaned++;
			}
		}

		return [
			'success'       => true,
			'total_cleaned' => $total_cleaned,
			'posts_cleaned' => $posts_cleaned,
		];
	}

	/**
	 * Clean up orphaned elements from a specific post
	 *
	 * @since 2.0
	 *
	 * @param int   $post_id The post ID to clean up
	 * @param array $orphaned_data Orphaned elements data from find_orphaned_elements_in_post()
	 *
	 * @return array {
	 *     @type bool $success True if cleanup was successful
	 *     @type int  $cleaned_count Number of elements cleaned up
	 * }
	 */
	public static function cleanup_orphaned_elements_in_post( $post_id, $orphaned_data ) {
		$cleaned_count = 0;

		if ( empty( $orphaned_data['orphaned_elements'] ) ) {
			return [
				'success'       => true,
				'cleaned_count' => 0,
			];
		}

		$areas_to_check = [ 'content', 'header', 'footer' ];

		foreach ( $areas_to_check as $area ) {
			if ( ! isset( $orphaned_data['orphaned_elements'][ $area ] ) ) {
				continue;
			}

			$elements = Database::get_data( $post_id, $area );

			if ( empty( $elements ) ) {
				continue;
			}

			$orphaned_ids     = $orphaned_data['orphaned_elements'][ $area ];
			$cleaned_elements = self::cleanup_orphaned_elements( $elements, $orphaned_ids );

			// Save cleaned elements back to the post
			$area_key = Database::get_bricks_data_key( $area );
			update_post_meta( $post_id, $area_key, $cleaned_elements );

			$cleaned_count += count( $orphaned_ids );
		}

		return [
			'success'       => true,
			'cleaned_count' => $cleaned_count,
		];
	}

	/**
	 * Clean up orphaned elements from an array of elements
	 *
	 * @since 2.0
	 *
	 * @param array $elements Array of Bricks elements
	 * @param array $orphaned_ids Array of orphaned element IDs to remove
	 *
	 * @return array Cleaned array of elements
	 */
	public static function cleanup_orphaned_elements( $elements, $orphaned_ids ) {
		if ( ! is_array( $elements ) || empty( $elements ) || empty( $orphaned_ids ) ) {
			return $elements;
		}

		// Create element ID map for quick lookup
		$element_map = [];
		foreach ( $elements as $element ) {
			if ( isset( $element['id'] ) ) {
				$element_map[ $element['id'] ] = $element;
			}
		}

		// Collect all IDs to remove (orphaned elements and their descendants)
		$ids_to_remove = [];
		foreach ( $orphaned_ids as $orphaned_id ) {
			self::collect_descendants( $orphaned_id, $element_map, $ids_to_remove );
		}

		// Filter out orphaned elements and their descendants
		$cleaned_elements = array_filter(
			$elements,
			function( $element ) use ( $ids_to_remove ) {
				return ! isset( $element['id'] ) || ! in_array( $element['id'], $ids_to_remove );
			}
		);

		// Clean up children arrays (remove references to deleted elements)
		foreach ( $cleaned_elements as $key => $element ) {
			if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
				$cleaned_elements[ $key ]['children'] = array_filter(
					$element['children'],
					function( $child_id ) use ( $ids_to_remove ) {
						return ! in_array( $child_id, $ids_to_remove );
					}
				);
			}
		}

		// Re-index array to avoid gaps
		return array_values( $cleaned_elements );
	}

	/**
	 * Recursively collect an element and all its descendants
	 *
	 * @since 2.0
	 *
	 * @param string $element_id The element ID to collect
	 * @param array  $element_map Map of element ID to element data
	 * @param array  &$ids_to_remove Reference to array of IDs to remove
	 */
	private static function collect_descendants( $element_id, $element_map, &$ids_to_remove ) {
		if ( in_array( $element_id, $ids_to_remove ) ) {
			return;
		}

		$ids_to_remove[] = $element_id;

		if ( isset( $element_map[ $element_id ]['children'] ) && is_array( $element_map[ $element_id ]['children'] ) ) {
			foreach ( $element_map[ $element_id ]['children'] as $child_id ) {
				self::collect_descendants( $child_id, $element_map, $ids_to_remove );
			}
		}
	}

	/**
	 * If a query is set to use a global query, replace its settings with the global query settings
	 *
	 * @param array $query_settings The query settings to check and potentially replace
	 * @return array The updated query settings
	 * @since 2.1
	 */
	public static function maybe_get_global_query_settings( $query_settings ) {
		$global_queries  = Database::$global_data['globalQueries'] ?? [];
		$global_query_id = $query_settings['id'] ?? '';

		// Find global query by 'id'
		if ( $global_query_id && ! empty( $global_queries ) ) {
			$global_query_index = array_search( $global_query_id, array_column( $global_queries, 'id' ) );

			if ( $global_query_index !== false ) {
				$global_query = $global_queries[ $global_query_index ];

				// Use global query settings
				if ( isset( $global_query['settings'] ) && is_array( $global_query['settings'] ) ) {
					$query_settings = $global_query['settings'];
				}
			}
		}

		return $query_settings;
	}
}
