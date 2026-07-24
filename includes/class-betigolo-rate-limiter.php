<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Betigolo_Rate_Limiter {

	public static function log_request( $endpoint ) {
		$window = max( 1, absint( get_option( 'betigolo_rate_window', 60 ) ) );
		$log    = (array) get_option( 'betigolo_request_log', array() );

		$now = time();
		$new = array();
		foreach ( $log as $entry ) {
			if ( isset( $entry['time'] ) && $now - (int) $entry['time'] <= $window ) {
				$new[] = $entry;
			}
		}

		$new[] = array(
			'time'     => $now,
			'endpoint' => sanitize_text_field( $endpoint ),
		);

		update_option( 'betigolo_request_log', array_slice( array_values( $new ), -200 ), false );
	}

	public static function get_log() {
		return (array) get_option( 'betigolo_request_log', array() );
	}

	public static function get_current_count() {
		$window = max( 1, absint( get_option( 'betigolo_rate_window', 60 ) ) );
		$log    = self::get_log();
		$now    = time();
		$count  = 0;

		foreach ( $log as $entry ) {
			if ( isset( $entry['time'] ) && $now - (int) $entry['time'] <= $window ) {
				$count++;
			}
		}

		return $count;
	}

	public static function is_allowed() {
		$limit = max( 1, absint( get_option( 'betigolo_rate_limit', 100 ) ) );
		return self::get_current_count() < $limit;
	}

	public static function clear_log() {
		delete_option( 'betigolo_request_log' );
	}

	public static function get_status() {
		$limit  = max( 1, absint( get_option( 'betigolo_rate_limit', 100 ) ) );
		$window = max( 1, absint( get_option( 'betigolo_rate_window', 60 ) ) );
		$count  = self::get_current_count();

		return array(
			'limit'         => $limit,
			'window'        => $window,
			'count'         => $count,
			'remaining'     => max( 0, $limit - $count ),
			'usage_percent' => $limit ? round( ( $count / $limit ) * 100, 2 ) : 0,
		);
	}
}