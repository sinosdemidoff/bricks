<?php
namespace Bricks\Integrations\Dynamic_Data;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Dynamic_Data_Parser
 *
 * Parses arguments for dynamic data tags, including filters and key-value pairs.
 */
class Dynamic_Data_Parser {
	/**
	 * The input string to parse
	 *
	 * @var string
	 */
	private $input;

	/**
	 * List of allowed keys for arguments
	 *
	 * @var array
	 */
	private static $allowed_keys;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->set_allowed_keys();
	}

	/**
	 * Parse the given input string
	 *
	 * @param string $input The input string to parse.
	 * @return array Associative array with 'tag', 'args', and 'original_tag'.
	 */
	public function parse( $input ) {
		$this->input = $input;
		return $this->parse_tag_and_args();
	}

	/**
	 * Parse the tag and its arguments
	 *
	 * @return array Associative array with 'tag', 'args', and 'original_tag'
	 */
	private function parse_tag_and_args() {
		// Special handling for echo tags to prevent incorrect colon splitting (@since 2.0)
		// NOTE: Not in use due to array value filter bug!!!
		// if ( strpos( trim( $this->input ), 'echo:' ) === 0 ) {
		// return $this->parse_echo_tag();
		// }

		// Generate regex pattern for allowed keys
		$allowed_keys_pattern = implode( '|', array_map( 'preg_quote', self::get_allowed_keys() ) );

		// Split the input at the first '@' followed by an allowed key
		$pattern = '/\s+(?=@(?:' . $allowed_keys_pattern . '):)/';
		$parts   = preg_split( $pattern, $this->input, 2 );

		// Parse the tag and filters
		$tag_and_filters = explode( ':', $parts[0] );
		$tag             = array_shift( $tag_and_filters );

		$args = [];

		// Add filters to args with numeric keys
		foreach ( $tag_and_filters as $index => $filter ) {
			$args[ $index ] = $filter;
		}

		// Parse key-value arguments if they exist
		if ( isset( $parts[1] ) ) {
			$kv_args = $this->parse_kv_args( $parts[1] );
			$args    = array_merge( $args, $kv_args );
		}

		return [
			'tag'          => $tag,
			'args'         => $args,
			'original_tag' => $this->input
		];
	}

	/**
	 * Parse the key-value arguments of the tag
	 *
	 * @param string $args_string The string containing all arguments.
	 * @return array Associative array of arguments
	 */
	private function parse_kv_args( $args_string ) {
		$args = [];
		preg_match_all( '/@(\w+(?:-\w+)*):(.+?)(?=\s+@|$)/s', $args_string, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$key   = $match[1];
			$value = trim( $match[2] );

			// Remove surrounding quotes if present
			if ( preg_match( '/^([\'"])(.*)\1$/', $value, $quote_matches ) ) {
				$value = $quote_matches[2];
			}

			if ( in_array( $key, self::get_allowed_keys(), true ) ) {
				$args[ $key ] = $value;
			}
		}

		return $args;
	}

	/**
	 * Set the allowed keys for arguments
	 * Uses the 'bricks/dynamic_data/allowed_keys' filter to allow modification of the allowed keys.
	 *
	 * TEXT: @fallback:'Just some text'
	 * IMAGE: @fallback-image:123 (Image ID or URL)
	 * SANITIZE: @sanitize:false (@since 1.11.1)
	 * EXCLUDE: @exclude:q1w2e3,880712 ({active_filters_count @query:'mn9456' @exclude:'q1w2e3,880712'} @since 2.0)
	 * START-AT: @start-at:1 (query_loop_index; @since 2.1)
	 * PAD: @pad:3 (query_loop_index; @since 2.1)
	 * KEY: @key:'title|rendered' (For {query_api} @since 2.1)
	 */
	public function set_allowed_keys() {
		$default_keys = [ 'fallback', 'fallback-image', 'sanitize', 'exclude', 'start-at', 'pad', 'key' ];

		// NOTE: Undocumented
		self::$allowed_keys = apply_filters( 'bricks/dynamic_data/allowed_keys', $default_keys );
	}

	/**
	 * Get the allowed keys
	 *
	 * @return array
	 *
	 * @since 2.0
	 */
	public static function get_allowed_keys() {
		return (array) self::$allowed_keys;
	}

	/**
	 * Parse echo tags specially to preserve function arguments with colons
	 *
	 * @return array Associative array with 'tag', 'args', and 'original_tag'
	 * @since 2.0 (#86c45bh2y)
	 */
	private function parse_echo_tag() {
		// Check for key-value arguments (starting with @)
		$allowed_keys_pattern = implode( '|', array_map( 'preg_quote', self::get_allowed_keys() ) );
		$pattern              = '/\s+(?=@(?:' . $allowed_keys_pattern . '):)/';
		$parts                = preg_split( $pattern, $this->input, 2 );

		$echo_part = trim( $parts[0] ); // This contains "echo:function_name(args)"

		// For echo tags, find the first colon and treat everything after as the function call
		$colon_pos = strpos( $echo_part, ':' );

		if ( $colon_pos === false ) {
			// No colon found, treat as simple echo tag
			$tag           = $echo_part;
			$function_call = '';
		} else {
			$tag           = substr( $echo_part, 0, $colon_pos ); // "echo"
			$function_call = trim( substr( $echo_part, $colon_pos + 1 ) ); // "function_name(args)"
		}

		$args = [];

		// Add the complete function call as the first argument (meta_key)
		if ( ! empty( $function_call ) ) {
			$args[0] = $function_call;
		}

		// Parse key-value arguments if they exist
		if ( isset( $parts[1] ) ) {
			$kv_args = $this->parse_kv_args( $parts[1] );
			$args    = array_merge( $args, $kv_args );
		}

		return [
			'tag'          => $tag,
			'args'         => $args,
			'original_tag' => $this->input
		];
	}
}
