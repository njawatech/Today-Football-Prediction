<?php
/**
 * Cache handler using WordPress transients.
 *
 * @package Today_Football_Prediction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TFP_Cache
 */
class TFP_Cache {

	/**
	 * Retrieve a cached value.
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached value or false on miss.
	 */
	public function get( $key ) {
		return get_transient( $key );
	}

	/**
	 * Store a value in cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   Time-to-live in seconds.
	 * @return bool
	 */
	public function set( $key, $value, $ttl = 3600 ) {
		return set_transient( $key, $value, absint( $ttl ) );
	}

	/**
	 * Delete a single cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( $key ) {
		return delete_transient( $key );
	}

	/**
	 * Remove all plugin-owned transients from the database.
	 *
	 * @return void
	 */
	public function clear_all() {
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_tfp_%',
				'_transient_timeout_tfp_%'
			)
		);
	}
}
