<?php
/**
 * Plugin Name: Betigolo Predictions
 * Plugin URI:  https://example.com/
 * Description: Fetches and displays Betigolo predictions with admin, cache, and rate limiting.
 * Version:     1.2.1
 * Author:      Your Name
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: betigolo-predictions
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BETIGOLO_VERSION', '1.2.1' );
define( 'BETIGOLO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BETIGOLO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'BETIGOLO_API_KEY' ) ) {
	define( 'BETIGOLO_API_KEY', '' );
}

require_once BETIGOLO_PLUGIN_DIR . 'includes/class-betigolo-rate-limiter.php';
require_once BETIGOLO_PLUGIN_DIR . 'includes/class-betigolo-api.php';
require_once BETIGOLO_PLUGIN_DIR . 'includes/class-betigolo-admin.php';
require_once BETIGOLO_PLUGIN_DIR . 'includes/class-betigolo-shortcode.php';

add_action( 'plugins_loaded', 'betigolo_init' );
function betigolo_init() {
	Betigolo_Admin::instance();
}

register_activation_hook( __FILE__, 'betigolo_activate' );
function betigolo_activate() {
	set_transient( 'betigolo_activation_notice', true, 5 );
}

add_action( 'admin_notices', 'betigolo_activation_notice' );
function betigolo_activation_notice() {
	if ( get_transient( 'betigolo_activation_notice' ) ) {
		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Betigolo is active. Configure API key, cache, and rate limits on the', 'betigolo-predictions' ),
			esc_url( admin_url( 'admin.php?page=betigolo-predictions' ) ),
			esc_html__( 'dashboard', 'betigolo-predictions' )
		);
		delete_transient( 'betigolo_activation_notice' );
	}
}