<?php
/**
 * API client for the Today Football Prediction RapidAPI endpoint.
 *
 * @package Today_Football_Prediction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TFP_API
 */
class TFP_API {

	/**
	 * Base URL of the predictions endpoint.
	 */
	const API_URL = 'https://today-football-prediction.p.rapidapi.com/predictions/list';

	/**
	 * RapidAPI host header value.
	 */
	const API_HOST = 'today-football-prediction.p.rapidapi.com';

	/**
	 * Retrieve the configured API key.
	 *
	 * Prefers the TFP_API_KEY constant over the database option so that
	 * server-level configuration can override user input.
	 *
	 * @return string
	 */
	private function get_api_key() {
		if ( defined( 'TFP_API_KEY' ) && '' !== TFP_API_KEY ) {
			return TFP_API_KEY;
		}
		return sanitize_text_field( (string) get_option( 'tfp_api_key', '' ) );
	}

	/**
	 * Fetch predictions for the given page.
	 *
	 * Results are cached via transients and subject to rate limiting.
	 *
	 * @param int $page  Page number (1-based).
	 * @param int $limit Maximum number of predictions to return (0 = all).
	 * @return array|WP_Error Decoded response array or WP_Error on failure.
	 */
	public function get_predictions( $page = 1, $limit = 0 ) {
		$page  = absint( $page );
		$limit = absint( $limit );

		// Rate limiting check.
		$rate_limiter = new TFP_Rate_Limiter();
		if ( ! $rate_limiter->allow_request() ) {
			return new WP_Error(
				'tfp_rate_limited',
				__( 'Too many requests. Please try again later.', 'today-football-prediction' )
			);
		}

		// Cache lookup.
		$cache     = new TFP_Cache();
		$cache_key = 'tfp_predictions_page_' . $page;
		$cached    = $cache->get( $cache_key );

		if ( false !== $cached ) {
			return $this->maybe_limit( $cached, $limit );
		}

		// Validate API key.
		$api_key = $this->get_api_key();
		if ( '' === $api_key ) {
			return new WP_Error(
				'tfp_missing_api_key',
				__( 'RapidAPI key is not configured. Please visit the plugin settings page.', 'today-football-prediction' )
			);
		}

		// Record this request before making the remote call.
		$rate_limiter->record_request();

		$url = add_query_arg( 'page', $page, self::API_URL );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'    => 'application/json',
					'x-rapidapi-host' => self::API_HOST,
					'x-rapidapi-key'  => $api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( 200 !== (int) $status_code ) {
			return new WP_Error(
				'tfp_api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'API returned status code %d.', 'today-football-prediction' ),
					$status_code
				)
			);
		}

		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'tfp_json_error',
				__( 'Failed to parse the API response.', 'today-football-prediction' )
			);
		}

		$ttl = absint( get_option( 'tfp_cache_ttl', 3600 ) );
		$cache->set( $cache_key, $data, $ttl );

		return $this->maybe_limit( $data, $limit );
	}

	/**
	 * Optionally cap the predictions array to $limit items.
	 *
	 * @param array $data  Full decoded API response.
	 * @param int   $limit Maximum number of prediction rows (0 = no limit).
	 * @return array
	 */
	private function maybe_limit( $data, $limit ) {
		if ( 0 === $limit || ! isset( $data['predictions'] ) || ! is_array( $data['predictions'] ) ) {
			return $data;
		}
		$data['predictions'] = array_slice( $data['predictions'], 0, $limit );
		return $data;
	}
}
