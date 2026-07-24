<?php
/**
 * Plugin Name: Today Football Prediction
 * Plugin URI:  https://today-football-prediction.p.rapidapi.com/
 * Description: Fetches and displays today's football predictions from RapidAPI with admin settings, caching, and rate limiting.
 * Version:     1.0.0
 * Author:      njawatech
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: today-football-prediction
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TFP_VERSION', '1.0.0' );
define( 'TFP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TFP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'TFP_API_KEY' ) ) {
	define( 'TFP_API_KEY', '' );
}

require_once TFP_PLUGIN_DIR . 'includes/class-tfp-rate-limiter.php';
require_once TFP_PLUGIN_DIR . 'includes/class-tfp-cache.php';
require_once TFP_PLUGIN_DIR . 'includes/class-tfp-api.php';
require_once TFP_PLUGIN_DIR . 'includes/class-tfp-admin.php';
require_once TFP_PLUGIN_DIR . 'includes/class-tfp-shortcode.php';

add_action( 'plugins_loaded', 'tfp_init' );
/**
 * Initialise the plugin.
 */
function tfp_init() {
	TFP_Admin::instance();
	new TFP_Shortcode();
}

register_activation_hook( __FILE__, 'tfp_activate' );
/**
 * Plugin activation hook.
 */
function tfp_activate() {
	set_transient( 'tfp_activation_notice', true, 5 );
}

add_action( 'admin_notices', 'tfp_activation_notice' );
/**
 * Show activation notice.
 */
function tfp_activation_notice() {
	if ( get_transient( 'tfp_activation_notice' ) ) {
		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Today Football Prediction is active. Configure your API key, cache, and rate limits on the', 'today-football-prediction' ),
			esc_url( admin_url( 'options-general.php?page=today-football-prediction' ) ),
			esc_html__( 'settings page', 'today-football-prediction' )
		);
		delete_transient( 'tfp_activation_notice' );
	}
}
