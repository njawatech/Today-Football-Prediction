<?php
/**
 * Admin dashboard, settings and cache/rate-limit UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Betigolo_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_betigolo_clear_cache', array( $this, 'handle_clear_cache' ) );
		add_action( 'admin_post_betigolo_reset_stats', array( $this, 'handle_reset_stats' ) );
		add_action( 'admin_post_betigolo_test_api', array( $this, 'handle_test_api' ) );
		add_action( 'admin_post_betigolo_clear_rate_log', array( $this, 'handle_clear_rate_log' ) );
	}

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Betigolo', 'betigolo-predictions' ),
			__( 'Betigolo', 'betigolo-predictions' ),
			'manage_options',
			'betigolo-predictions',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-line',
			26
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		$fields = array(
			'betigolo_api_key'              => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'betigolo_api_host'             => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'betigolo-predictions.p.rapidapi.com' ),
			'betigolo_cache_duration'       => array( 'sanitize_callback' => 'absint', 'default' => 900 ),
			'betigolo_cache_failures'       => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'no' ),
			'betigolo_failure_cache_duration' => array( 'sanitize_callback' => 'absint', 'default' => 60 ),
			'betigolo_rate_limit'           => array( 'sanitize_callback' => 'absint', 'default' => 100 ),
			'betigolo_rate_window'          => array( 'sanitize_callback' => 'absint', 'default' => 60 ),
		);

		foreach ( $fields as $option => $args ) {
			register_setting( 'betigolo_settings_group', $option, $args );
		}
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_betigolo-predictions' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'betigolo-admin',
			BETIGOLO_PLUGIN_URL . 'assets/css/betigolo-admin.css',
			array(),
			BETIGOLO_VERSION
		);

		wp_enqueue_script(
			'betigolo-admin',
			BETIGOLO_PLUGIN_URL . 'assets/js/betigolo-admin.js',
			array( 'jquery' ),
			BETIGOLO_VERSION,
			true
		);
	}

	/**
	 * Render the admin dashboard.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'betigolo-predictions' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
		$sample     = Betigolo_API::fetch( 'sample' );
		$stats      = Betigolo_API::get_stats();
		$rate       = Betigolo_Rate_Limiter::get_status();
		?>
		<div class="wrap betigolo-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=betigolo-predictions&tab=dashboard' ) ); ?>" class="nav-tab <?php echo 'dashboard' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Dashboard', 'betigolo-predictions' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=betigolo-predictions&tab=settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'betigolo-predictions' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=betigolo-predictions&tab=tools' ) ); ?>" class="nav-tab <?php echo 'tools' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Tools & Logs', 'betigolo-predictions' ); ?></a>
			</h2>

			<?php $this->display_notices(); ?>

			<?php if ( 'dashboard' === $active_tab ) : ?>
				<div class="betigolo-dashboard">
					<div class="betigolo-cards">
						<div class="betigolo-card-stat">
							<h3><?php esc_html_e( 'Total Requests', 'betigolo-predictions' ); ?></h3>
							<p class="betigolo-big"><?php echo esc_html( number_format_i18n( (int) $stats['requests'] ) ); ?></p>
						</div>
						<div class="betigolo-card-stat">
							<h3><?php esc_html_e( 'Successful', 'betigolo-predictions' ); ?></h3>
							<p class="betigolo-big"><?php echo esc_html( number_format_i18n( (int) $stats['success'] ) ); ?></p>
						</div>
						<div class="betigolo-card-stat">
							<h3><?php esc_html_e( 'Errors', 'betigolo-predictions' ); ?></h3>
							<p class="betigolo-big"><?php echo esc_html( number_format_i18n( (int) $stats['errors'] ) ); ?></p>
						</div>
						<div class="betigolo-card-stat">
							<h3><?php esc_html_e( 'Cache Hits', 'betigolo-predictions' ); ?></h3>
							<p class="betigolo-big"><?php echo esc_html( number_format_i18n( (int) $stats['cache_hits'] ) ); ?></p>
						</div>
					</div>

					<div class="betigolo-section">
						<h2><?php esc_html_e( 'Rate Limit Status', 'betigolo-predictions' ); ?></h2>
						<p>
							<?php
							printf(
								/* translators: 1: current count, 2: limit, 3: window in seconds, 4: remaining */
								esc_html__( '%1$s of %2$s requests used in the last %3$s seconds (%4$s remaining).', 'betigolo-predictions' ),
								'<strong>' . esc_html( number_format_i18n( $rate['count'] ) ) . '</strong>',
								'<strong>' . esc_html( number_format_i18n( $rate['limit'] ) ) . '</strong>',
								'<strong>' . esc_html( number_format_i18n( $rate['window'] ) ) . '</strong>',
								'<strong>' . esc_html( number_format_i18n( $rate['remaining'] ) ) . '</strong>'
							);
							?>
						</p>
						<div class="betigolo-progress">
							<div class="betigolo-progress-bar" style="width: <?php echo esc_attr( min( 100, $rate['usage_percent'] ) ); ?>%"></div>
						</div>
						<p class="description"><?php esc_html_e( 'Limit can be changed on the Settings tab.', 'betigolo-predictions' ); ?></p>
					</div>

					<div class="betigolo-section">
						<h2><?php esc_html_e( 'Live Sample Preview', 'betigolo-predictions' ); ?></h2>
						<p><?php esc_html_e( 'Shortcode:', 'betigolo-predictions' ); ?> <code>[betigolo_predictions endpoint="sample"]</code></p>
						<?php if ( is_wp_error( $sample ) ) : ?>
							<div class="notice notice-error inline">
								<p><?php echo esc_html( $sample->get_error_message() ); ?></p>
							</div>
						<?php else : ?>
							<div class="betigolo-json">
								<pre><?php echo esc_html( wp_json_encode( $sample, JSON_PRETTY_PRINT ) ); ?></pre>
							</div>
						<?php endif; ?>
					</div>
				</div>

			<?php elseif ( 'settings' === $active_tab ) : ?>
				<form action="options.php" method="post" class="betigolo-form">
					<?php
					settings_fields( 'betigolo_settings_group' );
					?>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="betigolo_api_key"><?php esc_html_e( 'RapidAPI Key', 'betigolo-predictions' ); ?></label></th>
							<td>
								<?php
								$defined = defined( 'BETIGOLO_API_KEY' ) && ! empty( BETIGOLO_API_KEY );
								$value   = $defined ? BETIGOLO_API_KEY : get_option( 'betigolo_api_key', '' );
								?>
								<input type="<?php echo $defined ? 'password' : 'text'; ?>" id="betigolo_api_key" name="betigolo_api_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" <?php echo $defined ? 'readonly' : ''; ?>>
								<?php if ( $defined ) : ?>
									<p class="description"><?php esc_html_e( 'Key is defined via the BETIGOLO_API_KEY constant in wp-config.php.', 'betigolo-predictions' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="betigolo_api_host"><?php esc_html_e( 'RapidAPI Host', 'betigolo-predictions' ); ?></label></th>
							<td>
								<input type="text" id="betigolo_api_host" name="betigolo_api_host" value="<?php echo esc_attr( get_option( 'betigolo_api_host', 'betigolo-predictions.p.rapidapi.com' ) ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="betigolo_cache_duration"><?php esc_html_e( 'Cache Duration', 'betigolo-predictions' ); ?></label></th>
							<td>
								<input type="number" id="betigolo_cache_duration" name="betigolo_cache_duration" value="<?php echo esc_attr( get_option( 'betigolo_cache_duration', 900 ) ); ?>" class="small-text"> <?php esc_html_e( 'seconds', 'betigolo-predictions' ); ?>
								<p class="description"><?php esc_html_e( 'How long successful responses are cached (default 900 = 15 minutes). Use 0 to disable.', 'betigolo-predictions' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Cache Failures', 'betigolo-predictions' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="betigolo_cache_failures" value="yes" <?php checked( 'yes', get_option( 'betigolo_cache_failures', 'no' ) ); ?>>
									<?php esc_html_e( 'Temporarily cache failed responses to avoid hammering the API.', 'betigolo-predictions' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="betigolo_failure_cache_duration"><?php esc_html_e( 'Failure Cache Duration', 'betigolo-predictions' ); ?></label></th>
							<td>
								<input type="number" id="betigolo_failure_cache_duration" name="betigolo_failure_cache_duration" value="<?php echo esc_attr( get_option( 'betigolo_failure_cache_duration', 60 ) ); ?>" class="small-text"> <?php esc_html_e( 'seconds', 'betigolo-predictions' ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="betigolo_rate_limit"><?php esc_html_e( 'Rate Limit', 'betigolo-predictions' ); ?></label></th>
							<td>
								<input type="number" id="betigolo_rate_limit" name="betigolo_rate_limit" value="<?php echo esc_attr( get_option( 'betigolo_rate_limit', 100 ) ); ?>" class="small-text"> <?php esc_html_e( 'requests', 'betigolo-predictions' ); ?>
								<p class="description"><?php esc_html_e( 'Maximum number of API requests allowed per rolling window.', 'betigolo-predictions' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="betigolo_rate_window"><?php esc_html_e( 'Rate Limit Window', 'betigolo-predictions' ); ?></label></th>
							<td>
								<input type="number" id="betigolo_rate_window" name="betigolo_rate_window" value="<?php echo esc_attr( get_option( 'betigolo_rate_window', 60 ) ); ?>" class="small-text"> <?php esc_html_e( 'seconds', 'betigolo-predictions' ); ?>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save Settings', 'betigolo-predictions' ) ); ?>
				</form>

			<?php elseif ( 'tools' === $active_tab ) : ?>
				<div class="betigolo-tools">
					<h2><?php esc_html_e( 'Tools', 'betigolo-predictions' ); ?></h2>

					<div class="betigolo-tool">
						<h3><?php esc_html_e( 'Clear Cache', 'betigolo-predictions' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Remove all cached Betigolo responses immediately.', 'betigolo-predictions' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'betigolo_clear_cache', 'betigolo_nonce' ); ?>
							<input type="hidden" name="action" value="betigolo_clear_cache">
							<?php submit_button( __( 'Clear Cache', 'betigolo-predictions' ), 'secondary' ); ?>
						</form>
					</div>

					<div class="betigolo-tool">
						<h3><?php esc_html_e( 'Reset Stats', 'betigolo-predictions' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Reset request, success, error and cache-hit counters.', 'betigolo-predictions' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'betigolo_reset_stats', 'betigolo_nonce' ); ?>
							<input type="hidden" name="action" value="betigolo_reset_stats">
							<?php submit_button( __( 'Reset Stats', 'betigolo-predictions' ), 'secondary' ); ?>
						</form>
					</div>

					<div class="betigolo-tool">
						<h3><?php esc_html_e( 'Reset Rate Log', 'betigolo-predictions' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Clear the rolling request log used for rate-limiting.', 'betigolo-predictions' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'betigolo_clear_rate_log', 'betigolo_nonce' ); ?>
							<input type="hidden" name="action" value="betigolo_clear_rate_log">
							<?php submit_button( __( 'Reset Rate Log', 'betigolo-predictions' ), 'secondary' ); ?>
						</form>
					</div>

					<div class="betigolo-tool">
						<h3><?php esc_html_e( 'Test API Connection', 'betigolo-predictions' ); ?></h3>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'betigolo_test_api', 'betigolo_nonce' ); ?>
							<input type="hidden" name="action" value="betigolo_test_api">
							<?php submit_button( __( 'Test Connection', 'betigolo-predictions' ), 'secondary' ); ?>
						</form>
					</div>

					<h2><?php esc_html_e( 'Rate Log', 'betigolo-predictions' ); ?></h2>
					<?php
					$log = Betigolo_Rate_Limiter::get_log();
					$log = array_reverse( array_slice( $log, -50 ) );
					?>
					<?php if ( empty( $log ) ) : ?>
						<p><?php esc_html_e( 'No requests logged yet.', 'betigolo-predictions' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Time', 'betigolo-predictions' ); ?></th>
									<th><?php esc_html_e( 'Endpoint', 'betigolo-predictions' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $log as $entry ) : ?>
									<tr>
										<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $entry['time'] ) ); ?></td>
										<td><?php echo esc_html( $entry['endpoint'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display admin notices.
	 */
	private function display_notices() {
		if ( isset( $_GET['betigolo_message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['betigolo_message'] ) );
			$type    = isset( $_GET['betigolo_type'] ) ? sanitize_key( $_GET['betigolo_type'] ) : 'info';
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $message ) );
		}
	}

	/**
	 * Redirect helper.
	 */
	private function redirect_with_message( $message, $type = 'info' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'betigolo_message' => rawurlencode( $message ),
					'betigolo_type'    => $type,
				),
				wp_get_referer()
			)
		);
		exit;
	}

	/**
	 * Clear cache action.
	 */
	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['betigolo_nonce'] ) ), 'betigolo_clear_cache' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'betigolo-predictions' ) );
		}
		Betigolo_API::clear_cache();
		$this->redirect_with_message( __( 'Cache cleared successfully.', 'betigolo-predictions' ), 'success' );
	}

	/**
	 * Reset stats action.
	 */
	public function handle_reset_stats() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['betigolo_nonce'] ) ), 'betigolo_reset_stats' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'betigolo-predictions' ) );
		}
		Betigolo_API::reset_stats();
		$this->redirect_with_message( __( 'Stats reset successfully.', 'betigolo-predictions' ), 'success' );
	}

	/**
	 * Clear rate log action.
	 */
	public function handle_clear_rate_log() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['betigolo_nonce'] ) ), 'betigolo_clear_rate_log' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'betigolo-predictions' ) );
		}
		Betigolo_Rate_Limiter::clear_log();
		$this->redirect_with_message( __( 'Rate log cleared successfully.', 'betigolo-predictions' ), 'success' );
	}

	/**
	 * Test API action.
	 */
	public function handle_test_api() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['betigolo_nonce'] ) ), 'betigolo_test_api' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'betigolo-predictions' ) );
		}

		$result = Betigolo_API::fetch( 'sample' );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_message( $result->get_error_message(), 'error' );
		}
		$this->redirect_with_message( __( 'API connection successful.', 'betigolo-predictions' ), 'success' );
	}
}