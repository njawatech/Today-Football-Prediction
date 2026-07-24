<?php
/**
 * Admin settings page partial.
 *
 * @package Today_Football_Prediction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap tfp-admin-wrap">
	<h1><?php esc_html_e( 'Today Football Prediction – Settings', 'today-football-prediction' ); ?></h1>

	<?php if ( isset( $_GET['cleared'] ) && '1' === $_GET['cleared'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Plugin cache cleared successfully.', 'today-football-prediction' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="tfp-admin-columns">
		<div class="tfp-admin-main">
			<form method="post" action="options.php">
				<?php
				settings_fields( 'tfp_settings_group' );
				do_settings_sections( 'today-football-prediction' );
				submit_button( __( 'Save Settings', 'today-football-prediction' ) );
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Cache Management', 'today-football-prediction' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tfp_clear_cache" />
				<?php wp_nonce_field( 'tfp_clear_cache' ); ?>
				<?php submit_button( __( 'Clear All Cached Data', 'today-football-prediction' ), 'secondary' ); ?>
			</form>
		</div>

		<div class="tfp-admin-sidebar">
			<div class="tfp-admin-card">
				<h3><?php esc_html_e( 'Shortcode Usage', 'today-football-prediction' ); ?></h3>
				<p><?php esc_html_e( 'Paste one of the following shortcodes into any page or post:', 'today-football-prediction' ); ?></p>
				<code>[today_football_predictions]</code><br />
				<code>[today_football_predictions page="1" limit="50"]</code>

				<h3><?php esc_html_e( 'API Key Constant', 'today-football-prediction' ); ?></h3>
				<p><?php esc_html_e( 'You can define the API key in wp-config.php to override the setting above:', 'today-football-prediction' ); ?></p>
				<code>define( 'TFP_API_KEY', 'your-key-here' );</code>
			</div>
		</div>
	</div>
</div>
