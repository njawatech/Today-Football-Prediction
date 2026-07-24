<?php
/**
 * Betigolo API client with caching and rate-limit tracking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Betigolo_API {

	/**
	 * Default endpoints.
	 */
	private static $endpoints = array(
		'sample'      => 'https://betigolo-predictions.p.rapidapi.com/sample',
		'predictions' => 'https://betigolo-predictions.p.rapidapi.com/predictions',
		'jackpot'     => 'https://betigolo-predictions.p.rapidapi.com/jackpot',
	);

	/**
	 * Stats option key.
	 */
	private static $stats_key = 'betigolo_api_stats';

	/**
	 * Get API key.
	 */
	public static function get_api_key() {
		$key = BETIGOLO_API_KEY;
		if ( empty( $key ) ) {
			$key = get_option( 'betigolo_api_key', '' );
		}
		return sanitize_text_field( $key );
	}

	/**
	 * Get API host.
	 */
	public static function get_host() {
		$host = get_option( 'betigolo_api_host', 'betigolo-predictions.p.rapidapi.com' );
		return sanitize_text_field( $host );
	}

	/**
	 * Get cache duration in seconds.
	 */
	public static function get_cache_duration() {
		return absint( get_option( 'betigolo_cache_duration', 900 ) );
	}

	/**
	 * Should cache failures?
	 */
	public static function cache_failures() {
		return 'yes' === get_option( 'betigolo_cache_failures', 'no' );
	}

	/**
	 * Get failure cache duration.
	 */
	public static function get_failure_cache_duration() {
		return absint( get_option( 'betigolo_failure_cache_duration', 60 ) );
	}

	/**
	 * Return known endpoints.
	 */
	public static function get_known_endpoints() {
		return self::$endpoints;
	}

	/**
	 * Fetch predictions from the API.
	 *
	 * @param string $endpoint Endpoint key or full URL.
	 * @return array|WP_Error
	 */
	public static function fetch( $endpoint = 'sample' ) {
		$api_key = self::get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'Betigolo API key is not configured.', 'betigolo-predictions' ) );
		}

		// Enforce rate limit before hitting the API.
		if ( ! Betigolo_Rate_Limiter::is_allowed() ) {
			return new WP_Error( 'rate_limited', __( 'API rate limit reached. Please wait before making another request.', 'betigolo-predictions' ) );
		}

		$url       = isset( self::$endpoints[ $endpoint ] ) ? self::$endpoints[ $endpoint ] : $endpoint;
		$cache_key = 'betigolo_response_' . md5( $url );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			self::increment_stats( 'cache_hits' );
			return $cached;
		}

		$rate_status = Betigolo_Rate_Limiter::log_request( $endpoint );
		self::increment_stats( 'requests' );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Content-Type'    => 'application/json',
					'x-rapidapi-host' => self::get_host(),
					'x-rapidapi-key'  => $api_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::maybe_cache_error( $cache_key, $response );
			self::increment_stats( 'errors' );
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( 200 !== $status ) {
			self::increment_stats( 'errors' );
			$error = new WP_Error(
				'api_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'API returned status %d.', 'betigolo-predictions' ), $status )
			);
			self::maybe_cache_error( $cache_key, $error );
			return $error;
		}

		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			self::increment_stats( 'errors' );
			$error = new WP_Error( 'json_error', __( 'Could not parse API response.', 'betigolo-predictions' ) );
			self::maybe_cache_error( $cache_key, $error );
			return $error;
		}

		self::increment_stats( 'success' );
		$cache_duration = self::get_cache_duration();
		if ( $cache_duration > 0 ) {
			set_transient( $cache_key, $data, $cache_duration );
		}

		return $data;
	}

	/**
	 * Cache an error response to avoid hammering the API.
	 *
	 * @param string $cache_key Cache key.
	 * @param WP_Error $error Error object.
	 */
	private static function maybe_cache_error( $cache_key, $error ) {
		if ( ! self::cache_failures() ) {
			return;
		}

		$duration = self::get_failure_cache_duration();
		if ( $duration > 0 ) {
			set_transient( $cache_key, $error, $duration );
		}
	}

	/**
	 * Clear all cached responses.
	 */
	public static function clear_cache() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_betigolo_response_%',
			'_transient_timeout_betigolo_response_%'
		) );
	}

	/**
	 * Increment API stats.
	 *
	 * @param string $key Stat key.
	 */
	public static function increment_stats( $key ) {
		$stats = get_option( self::$stats_key, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		$stats[ $key ] = isset( $stats[ $key ] ) ? $stats[ $key ] + 1 : 1;
		update_option( self::$stats_key, $stats, false );
	}

	/**
	 * Get API stats.
	 */
	public static function get_stats() {
		$defaults = array(
			'requests'   => 0,
			'success'    => 0,
			'errors'     => 0,
			'cache_hits' => 0,
		);
		$stats = get_option( self::$stats_key, array() );
		return wp_parse_args( (array) $stats, $defaults );
	}

	/**
	 * Reset API stats.
	 */
	public static function reset_stats() {
		delete_option( self::$stats_key );
	}
}