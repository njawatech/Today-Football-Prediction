<?php
/**
 * Admin settings page handler.
 *
 * @package Today_Football_Prediction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TFP_Admin
 */
class TFP_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var TFP_Admin|null
	 */
	private static $instance = null;

	/**
	 * Return (or create) the singleton instance.
	 *
	 * @return TFP_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor – registers WordPress hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_tfp_clear_cache', array( $this, 'handle_clear_cache' ) );
	}

	/**
	 * Register the settings sub-page under Settings.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_options_page(
			__( 'Today Football Prediction', 'today-football-prediction' ),
			__( 'Football Prediction', 'today-football-prediction' ),
			'manage_options',
			'today-football-prediction',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register plugin settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'tfp_settings_group', 'tfp_api_key', 'sanitize_text_field' );
		register_setting(
			'tfp_settings_group',
			'tfp_cache_ttl',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 3600,
			)
		);
		register_setting(
			'tfp_settings_group',
			'tfp_rate_limit',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 10,
			)
		);

		add_settings_section(
			'tfp_main_section',
			__( 'API Configuration', 'today-football-prediction' ),
			null,
			'today-football-prediction'
		);

		add_settings_field(
			'tfp_api_key',
			__( 'RapidAPI Key', 'today-football-prediction' ),
			array( $this, 'render_api_key_field' ),
			'today-football-prediction',
			'tfp_main_section'
		);

		add_settings_field(
			'tfp_cache_ttl',
			__( 'Cache TTL (seconds)', 'today-football-prediction' ),
			array( $this, 'render_cache_ttl_field' ),
			'today-football-prediction',
			'tfp_main_section'
		);

		add_settings_field(
			'tfp_rate_limit',
			__( 'Rate Limit (requests / minute)', 'today-football-prediction' ),
			array( $this, 'render_rate_limit_field' ),
			'today-football-prediction',
			'tfp_main_section'
		);
	}

	/**
	 * Render the RapidAPI key input field.
	 *
	 * @return void
	 */
	public function render_api_key_field() {
		$value = get_option( 'tfp_api_key', '' );
		printf(
			'<input type="password" id="tfp_api_key" name="tfp_api_key" value="%s" class="regular-text" autocomplete="off" />
			<p class="description">%s</p>',
			esc_attr( $value ),
			esc_html__( 'Enter your RapidAPI key for today-football-prediction.p.rapidapi.com. Alternatively, define TFP_API_KEY in wp-config.php.', 'today-football-prediction' )
		);
	}

	/**
	 * Render the cache TTL input field.
	 *
	 * @return void
	 */
	public function render_cache_ttl_field() {
		$value = absint( get_option( 'tfp_cache_ttl', 3600 ) );
		printf(
			'<input type="number" id="tfp_cache_ttl" name="tfp_cache_ttl" value="%d" min="60" step="60" class="small-text" />
			<p class="description">%s</p>',
			$value,
			esc_html__( 'How long (in seconds) to cache API responses. Default: 3600 (1 hour).', 'today-football-prediction' )
		);
	}

	/**
	 * Render the rate-limit input field.
	 *
	 * @return void
	 */
	public function render_rate_limit_field() {
		$value = absint( get_option( 'tfp_rate_limit', 10 ) );
		printf(
			'<input type="number" id="tfp_rate_limit" name="tfp_rate_limit" value="%d" min="1" step="1" class="small-text" />
			<p class="description">%s</p>',
			$value,
			esc_html__( 'Maximum number of API requests per minute. Default: 10.', 'today-football-prediction' )
		);
	}

	/**
	 * Output the admin settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require_once TFP_PLUGIN_DIR . 'admin/partials/admin-page.php';
	}

	/**
	 * Enqueue admin CSS and JS only on the plugin settings page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_today-football-prediction' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'tfp-admin-css',
			TFP_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			TFP_VERSION
		);

		wp_enqueue_script(
			'tfp-admin-js',
			TFP_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			TFP_VERSION,
			true
		);
	}

	/**
	 * Handle the "Clear Cache" form action.
	 *
	 * @return void
	 */
	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'today-football-prediction' ) );
		}

		check_admin_referer( 'tfp_clear_cache' );

		$cache = new TFP_Cache();
		$cache->clear_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'today-football-prediction',
					'cleared' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
