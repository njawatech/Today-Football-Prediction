<?php
/**
 * Rate limiter – prevents exceeding the RapidAPI per-minute quota.
 *
 * @package Today_Football_Prediction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TFP_Rate_Limiter
 */
class TFP_Rate_Limiter {

	/**
	 * Transient key used to store the request log.
	 */
	const LOG_KEY = 'tfp_request_log';

	/**
	 * Sliding-window size in seconds.
	 */
	const WINDOW = 60;

	/**
	 * Determine whether a new API request is allowed.
	 *
	 * @return bool True when under the configured limit.
	 */
	public function allow_request() {
		$max_requests = absint( get_option( 'tfp_rate_limit', 10 ) );
		$log          = $this->get_log();
		$cutoff       = time() - self::WINDOW;

		$recent = array_filter(
			$log,
			function ( $ts ) use ( $cutoff ) {
				return $ts >= $cutoff;
			}
		);

		return count( $recent ) < $max_requests;
	}

	/**
	 * Record a request timestamp in the log.
	 *
	 * @return void
	 */
	public function record_request() {
		$log   = $this->get_log();
		$log[] = time();
		set_transient( self::LOG_KEY, $log, HOUR_IN_SECONDS );
	}

	/**
	 * Retrieve the stored request log.
	 *
	 * @return array
	 */
	private function get_log() {
		$log = get_transient( self::LOG_KEY );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Clear the request log.
	 *
	 * @return bool
	 */
	public function clear_log() {
		return delete_transient( self::LOG_KEY );
	}
}
