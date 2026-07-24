<?php
/**
 * Frontend shortcode handler.
 *
 * Usage: [today_football_predictions page="1" limit="50"]
 *
 * @package Today_Football_Prediction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TFP_Shortcode
 */
class TFP_Shortcode {

	/**
	 * Constructor – registers hooks.
	 */
	public function __construct() {
		add_shortcode( 'today_football_predictions', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets only when the shortcode is present on the page.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		global $post;

		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'today_football_predictions' ) ) {
			return;
		}

		wp_enqueue_style(
			'tfp-fira-sans',
			'https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&display=swap',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		);

		wp_enqueue_style(
			'tfp-public-css',
			TFP_PLUGIN_URL . 'assets/css/public.css',
			array( 'tfp-fira-sans' ),
			TFP_VERSION
		);

		wp_enqueue_script(
			'tfp-public-js',
			TFP_PLUGIN_URL . 'assets/js/public.js',
			array( 'jquery' ),
			TFP_VERSION,
			true
		);
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'page'  => 1,
				'limit' => 50,
			),
			$atts,
			'today_football_predictions'
		);

		$page  = absint( $atts['page'] );
		$limit = absint( $atts['limit'] );

		$api  = new TFP_API();
		$data = $api->get_predictions( $page, $limit );

		if ( is_wp_error( $data ) ) {
			return '<div class="fp-error">' . esc_html( $data->get_error_message() ) . '</div>';
		}

		$predictions = isset( $data['predictions'] ) && is_array( $data['predictions'] ) ? $data['predictions'] : array();

		if ( empty( $predictions ) ) {
			return '<div class="fp-no-predictions">' . esc_html__( 'No predictions available at the moment.', 'today-football-prediction' ) . '</div>';
		}

		// Group predictions by date.
		$by_date = array();
		foreach ( $predictions as $pred ) {
			$raw_date = isset( $pred['date'] ) ? sanitize_text_field( $pred['date'] ) : '';
			if ( '' === $raw_date ) {
				$raw_date = __( 'Unknown Date', 'today-football-prediction' );
			}
			$by_date[ $raw_date ][] = $pred;
		}

		ob_start();
		?>
		<div class="fp-predictions-container">
			<?php foreach ( $by_date as $date => $rows ) : ?>
				<div class="fp-day-table">
					<div class="fp-day-header">
						<h3><?php echo esc_html( $date ); ?></h3>
					</div>
					<table class="fp-predictions-table">
						<thead>
							<tr>
								<th class="fp-col-league"><?php esc_html_e( 'League', 'today-football-prediction' ); ?></th>
								<th class="fp-col-fixtures"><?php esc_html_e( 'Fixture', 'today-football-prediction' ); ?></th>
								<th class="fp-col-tips"><?php esc_html_e( 'Tip', 'today-football-prediction' ); ?></th>
								<th class="fp-col-result"><?php esc_html_e( 'Result', 'today-football-prediction' ); ?></th>
								<th class="fp-col-status"><?php esc_html_e( 'Status', 'today-football-prediction' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $pred ) : ?>
								<tr>
									<td class="fp-col-league">
										<span class="fp-flag">
											<?php echo esc_html( isset( $pred['flag'] ) ? $pred['flag'] : ( isset( $pred['country_flag'] ) ? $pred['country_flag'] : '' ) ); ?>
										</span>
									</td>
									<td class="fp-col-fixtures">
										<span class="fp-match">
											<?php echo esc_html( isset( $pred['fixture'] ) ? $pred['fixture'] : ( isset( $pred['match'] ) ? $pred['match'] : '' ) ); ?>
										</span>
										<?php if ( ! empty( $pred['competition'] ) || ! empty( $pred['league'] ) ) : ?>
											<span class="fp-competition">
												<?php echo esc_html( isset( $pred['competition'] ) ? $pred['competition'] : $pred['league'] ); ?>
											</span>
										<?php endif; ?>
									</td>
									<td class="fp-col-tips">
										<span class="fp-prediction-badge">
											<?php echo esc_html( isset( $pred['prediction'] ) ? $pred['prediction'] : ( isset( $pred['tip'] ) ? $pred['tip'] : '' ) ); ?>
										</span>
									</td>
									<td class="fp-col-result">
										<span class="fp-status-badge-pending">
											<?php echo esc_html( isset( $pred['result'] ) ? $pred['result'] : '-' ); ?>
										</span>
									</td>
									<td class="fp-col-status">
										<span class="fp-status-badge-pending">
											<?php echo esc_html( isset( $pred['status'] ) ? $pred['status'] : __( 'Pending', 'today-football-prediction' ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
