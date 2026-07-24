<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Betigolo_Shortcode {

	/**
	 * Manual date corrections for fixtures where the API date is wrong.
	 * Format: 'Home Team vs Away Team' => 'Y-m-d'
	 */
	private static $date_corrections = array(
		// Belarus women's league fixture with the correct date.
		'FK Smorgon vs FK Vitebsk' => '2026-07-19',
	);

	public function __construct() {
		add_shortcode( 'betigolo_predictions', array( $this, 'render_betigolo_shortcode' ) );
		add_shortcode( 'double_chance_predictions', array( $this, 'render_double_chance_shortcode' ) );
		add_shortcode( 'betigolo_over_under', array( $this, 'render_over_under_shortcode' ) );
		add_shortcode( 'over_25_predictions', array( $this, 'render_over_25_shortcode' ) );
		add_shortcode( 'under_25_predictions', array( $this, 'render_under_25_shortcode' ) );
		add_shortcode( 'betigolo_btts', array( $this, 'render_btts_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
	}

	public function enqueue_frontend_scripts() {
		wp_enqueue_style(
			'fira-sans-font',
			'https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'fp-frontend-style',
			BETIGOLO_PLUGIN_URL . 'assets/css/betigolo-public.css',
			array( 'fira-sans-font' ),
			BETIGOLO_VERSION
		);

		wp_add_inline_style(
			'fp-frontend-style',
			'
			.fp-col-tips { text-align: center !important; }
			.fp-col-result { text-align: center !important; }
			th.fp-col-league, td.fp-col-league { width: 40px !important; min-width: 40px !important; max-width: 40px !important; padding: 0 5px !important; text-align: center !important; overflow: hidden !important; white-space: nowrap !important; }
			.fp-col-league .fp-flag { font-size: 1.2em; line-height: 1; white-space: nowrap; display: inline-block; }
			td.fp-col-fixtures { white-space: normal !important; word-wrap: break-word !important; overflow-wrap: break-word !important; }
			@media (max-width: 600px) {
				th.fp-col-league, td.fp-col-league { width: 32px !important; min-width: 32px !important; max-width: 32px !important; padding: 0 3px !important; }
				.fp-col-league .fp-flag { font-size: 1.1em; }
			}
			'
		);
	}

	/**
	 * Main shortcode.
	 * Usage: [betigolo_predictions endpoint="sample" limit="50" league="" date="" odds_margin="0.05"]
	 */
	public function render_betigolo_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'endpoint'     => 'sample',
				'limit'        => 50,
				'league'       => '',
				'date'         => '',
				'odds_margin'  => 0.05,
			),
			$atts,
			'betigolo_predictions'
		);

		$atts['endpoint']    = sanitize_text_field( $atts['endpoint'] );
		$atts['limit']       = absint( $atts['limit'] );
		$atts['league']      = sanitize_text_field( $atts['league'] );
		$atts['date']        = sanitize_text_field( $atts['date'] );
		$atts['odds_margin'] = (float) $atts['odds_margin'];

		$predictions = $this->fetch_predictions( $atts );

		if ( is_wp_error( $predictions ) ) {
			return '<div class="fp-error">' . esc_html( $predictions->get_error_message() ) . '</div>';
		}

		return $this->render_predictions_table( $predictions, __( 'Football Predictions', 'betigolo-predictions' ), false, $atts['odds_margin'] );
	}

	/**
	 * Double chance shortcode.
	 * Usage: [double_chance_predictions endpoint="sample" limit="50" min_prob="0.65" odds_margin="0.05"]
	 */
	public function render_double_chance_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'endpoint'     => 'sample',
				'limit'        => 50,
				'min_prob'     => 0.65,
				'league'       => '',
				'date'         => '',
				'odds_margin'  => 0.05,
			),
			$atts,
			'double_chance_predictions'
		);

		$atts['endpoint']    = sanitize_text_field( $atts['endpoint'] );
		$atts['limit']       = absint( $atts['limit'] );
		$atts['min_prob']    = (float) $atts['min_prob'];
		$atts['league']      = sanitize_text_field( $atts['league'] );
		$atts['date']        = sanitize_text_field( $atts['date'] );
		$atts['odds_margin'] = (float) $atts['odds_margin'];

		$predictions = $this->fetch_predictions( $atts );

		if ( is_wp_error( $predictions ) ) {
			return '<div class="fp-error">' . esc_html( $predictions->get_error_message() ) . '</div>';
		}

		foreach ( $predictions as $key => $p ) {
			$home                                    = self::get_float( $p, 'rank_htw_ft' );
			$draw                                    = self::get_float( $p, 'rank_drw_ft' );
			$away                                    = self::get_float( $p, 'rank_atw_ft' );
			$double                                  = self::get_best_double_chance( $home, $draw, $away );
			$predictions[ $key ]['dc_value']       = $double['value'];
			$predictions[ $key ]['dc_probability'] = $double['probability'];
		}

		$predictions = array_filter(
			$predictions,
			function ( $p ) use ( $atts ) {
				return ( $p['dc_probability'] >= $atts['min_prob'] );
			}
		);

		usort(
			$predictions,
			function ( $a, $b ) {
				return $b['dc_probability'] <=> $a['dc_probability'];
			}
		);

		return $this->render_predictions_table( $predictions, __( 'Double Chance Predictions (1X, X2, 12)', 'betigolo-predictions' ), true, $atts['odds_margin'] );
	}

	/**
	 * Over/Under 2.5 shortcode.
	 * Usage: [betigolo_over_under endpoint="sample" limit="50" min_prob="0.55" threshold="2.5" odds_margin="0.05"]
	 */
	public function render_over_under_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'endpoint'     => 'sample',
				'limit'        => 50,
				'min_prob'     => 0.55,
				'threshold'    => 2.5,
				'league'       => '',
				'date'         => '',
				'odds_margin'  => 0.05,
			),
			$atts,
			'betigolo_over_under'
		);

		$atts['endpoint']    = sanitize_text_field( $atts['endpoint'] );
		$atts['limit']       = absint( $atts['limit'] );
		$atts['min_prob']    = (float) $atts['min_prob'];
		$atts['threshold']   = (float) $atts['threshold'];
		$atts['league']      = sanitize_text_field( $atts['league'] );
		$atts['date']        = sanitize_text_field( $atts['date'] );
		$atts['odds_margin'] = (float) $atts['odds_margin'];

		$predictions = $this->fetch_predictions( $atts );

		if ( is_wp_error( $predictions ) ) {
			return '<div class="fp-error">' . esc_html( $predictions->get_error_message() ) . '</div>';
		}

		$key = 'rank_to_' . str_replace( '.', '', (string) $atts['threshold'] ) . '_ft';

		foreach ( $predictions as $index => $p ) {
			$over                    = self::get_float( $p, $key );
			$under                   = 1 - $over;
			$prediction              = self::get_over_under_prediction( $over, $under, $atts['min_prob'] );
			$predictions[ $index ]['ou_value']       = $prediction['value'];
			$predictions[ $index ]['ou_probability'] = $prediction['probability'];
		}

		$predictions = array_filter(
			$predictions,
			function ( $p ) use ( $atts ) {
				return ! empty( $p['ou_value'] );
			}
		);

		usort(
			$predictions,
			function ( $a, $b ) {
				return $b['ou_probability'] <=> $a['ou_probability'];
			}
		);

		return $this->render_predictions_table( $predictions, sprintf( __( 'Over/Under %.1f Predictions', 'betigolo-predictions' ), $atts['threshold'] ), 'ou', $atts['odds_margin'] );
	}

	/**
	 * Over 2.5 shortcode.
	 * Usage: [over_25_predictions endpoint="sample" limit="50" min_prob="0.55" odds_margin="0.05"]
	 */
	public function render_over_25_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'endpoint'     => 'sample',
				'limit'        => 50,
				'min_prob'     => 0.55,
				'league'       => '',
				'date'         => '',
				'odds_margin'  => 0.05,
			),
			$atts,
			'over_25_predictions'
		);

		$atts['endpoint']    = sanitize_text_field( $atts['endpoint'] );
		$atts['limit']       = absint( $atts['limit'] );
		$atts['min_prob']    = (float) $atts['min_prob'];
		$atts['league']      = sanitize_text_field( $atts['league'] );
		$atts['date']        = sanitize_text_field( $atts['date'] );
		$atts['odds_margin'] = (float) $atts['odds_margin'];

		$predictions = $this->fetch_predictions( $atts );

		if ( is_wp_error( $predictions ) ) {
			return '<div class="fp-error">' . esc_html( $predictions->get_error_message() ) . '</div>';
		}

		foreach ( $predictions as $index => $p ) {
			$over                    = self::get_float( $p, 'rank_to_25_ft' );
			$predictions[ $index ]['ou_value']       = ( $over >= $atts['min_prob'] ) ? 'Over 2.5' : '';
			$predictions[ $index ]['ou_probability'] = $over;
		}

		$predictions = array_filter(
			$predictions,
			function ( $p ) {
				return ! empty( $p['ou_value'] );
			}
		);

		usort(
			$predictions,
			function ( $a, $b ) {
				return $b['ou_probability'] <=> $a['ou_probability'];
			}
		);

		return $this->render_predictions_table( $predictions, __( 'Over 2.5 Predictions', 'betigolo-predictions' ), 'ou', $atts['odds_margin'] );
	}

	/**
	 * Under 2.5 shortcode.
	 * Usage: [under_25_predictions endpoint="sample" limit="50" min_prob="0.55" odds_margin="0.05"]
	 */
	public function render_under_25_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'endpoint'     => 'sample',
				'limit'        => 50,
				'min_prob'     => 0.55,
				'league'       => '',
				'date'         => '',
				'odds_margin'  => 0.05,
			),
			$atts,
			'under_25_predictions'
		);

		$atts['endpoint']    = sanitize_text_field( $atts['endpoint'] );
		$atts['limit']       = absint( $atts['limit'] );
		$atts['min_prob']    = (float) $atts['min_prob'];
		$atts['league']      = sanitize_text_field( $atts['league'] );
		$atts['date']        = sanitize_text_field( $atts['date'] );
		$atts['odds_margin'] = (float) $atts['odds_margin'];

		$predictions = $this->fetch_predictions( $atts );

		if ( is_wp_error( $predictions ) ) {
			return '<div class="fp-error">' . esc_html( $predictions->get_error_message() ) . '</div>';
		}

		foreach ( $predictions as $index => $p ) {
			$over                    = self::get_float( $p, 'rank_to_25_ft' );
			$under                   = 1 - $over;
			$predictions[ $index ]['ou_value']       = ( $under >= $atts['min_prob'] ) ? 'Under 2.5' : '';
			$predictions[ $index ]['ou_probability'] = $under;
		}

		$predictions = array_filter(
			$predictions,
			function ( $p ) {
				return ! empty( $p['ou_value'] );
			}
		);

		usort(
			$predictions,
			function ( $a, $b ) {
				return $b['ou_probability'] <=> $a['ou_probability'];
			}
		);

		return $this->render_predictions_table( $predictions, __( 'Under 2.5 Predictions', 'betigolo-predictions' ), 'ou', $atts['odds_margin'] );
	}

	/**
	 * BTTS shortcode.
	 * Usage: [betigolo_btts endpoint="sample" limit="50" min_prob="0.55" odds_margin="0.05"]
	 */
	public function render_btts_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'endpoint'     => 'sample',
				'limit'        => 50,
				'min_prob'     => 0.55,
				'league'       => '',
				'date'         => '',
				'odds_margin'  => 0.05,
			),
			$atts,
			'betigolo_btts'
		);

		$atts['endpoint']    = sanitize_text_field( $atts['endpoint'] );
		$atts['limit']       = absint( $atts['limit'] );
		$atts['min_prob']    = (float) $atts['min_prob'];
		$atts['league']      = sanitize_text_field( $atts['league'] );
		$atts['date']        = sanitize_text_field( $atts['date'] );
		$atts['odds_margin'] = (float) $atts['odds_margin'];

		$predictions = $this->fetch_predictions( $atts );

		if ( is_wp_error( $predictions ) ) {
			return '<div class="fp-error">' . esc_html( $predictions->get_error_message() ) . '</div>';
		}

		foreach ( $predictions as $index => $p ) {
			$btts          = self::get_float( $p, 'rank_btts_ft' );
			$prediction    = self::get_btts_prediction( $btts, $atts['min_prob'] );
			$predictions[ $index ]['btts_value']       = $prediction['value'];
			$predictions[ $index ]['btts_probability'] = $prediction['probability'];
		}

		$predictions = array_filter(
			$predictions,
			function ( $p ) use ( $atts ) {
				return ! empty( $p['btts_value'] );
			}
		);

		usort(
			$predictions,
			function ( $a, $b ) {
				return $b['btts_probability'] <=> $a['btts_probability'];
			}
		);

		return $this->render_predictions_table( $predictions, __( 'BTTS Predictions', 'betigolo-predictions' ), 'btts', $atts['odds_margin'] );
	}

	/**
	 * Fetch predictions.
	 */
	private function fetch_predictions( $atts ) {
		$data = Betigolo_API::fetch( $atts['endpoint'] );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$predictions = $data;

		if ( isset( $data['predictions'] ) && is_array( $data['predictions'] ) ) {
			$predictions = $data['predictions'];
		} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$predictions = $data['data'];
		}

		if ( ! is_array( $predictions ) ) {
			$predictions = array( $predictions );
		}

		$predictions = array_values( self::filter_predictions( $predictions, $atts ) );

		// Apply manual date corrections.
		foreach ( $predictions as $i => $p ) {
			$home_raw = self::get_value( $p, 'home_team_name' );
			$away_raw = self::get_value( $p, 'away_team_name' );
			$corrected_date = self::get_date_correction( $home_raw, $away_raw );
			if ( false !== $corrected_date ) {
				$predictions[ $i ]['match_dat'] = $corrected_date;
			}
		}

		if ( ! empty( $atts['limit'] ) ) {
			$predictions = array_slice( $predictions, 0, absint( $atts['limit'] ) );
		}

		usort(
			$predictions,
			function ( $a, $b ) {
				$a_date = self::get_value( $a, 'match_dat' );
				$b_date = self::get_value( $b, 'match_dat' );

				if ( is_numeric( $a_date ) && is_numeric( $b_date ) ) {
					return (int) $a_date - (int) $b_date;
				}

				return strcmp( (string) $a_date, (string) $b_date );
			}
		);

		return $predictions;
	}

	/**
	 * Look up a manual date correction for a fixture.
	 *
	 * @return string|false Corrected date ('Y-m-d') or false if no override.
	 */
	private static function get_date_correction( $home, $away ) {
		$home = trim( (string) $home );
		$away = trim( (string) $away );

		foreach ( self::$date_corrections as $fixture => $date ) {
			if ( ! is_string( $fixture ) || false === strpos( $fixture, ' vs ' ) ) {
				continue;
			}

			$parts = array_map( 'trim', explode( ' vs ', $fixture ) );
			if ( count( $parts ) !== 2 ) {
				continue;
			}

			// Allow partial, case-insensitive matching.
			if ( false !== stripos( $home, $parts[0] ) && false !== stripos( $away, $parts[1] ) ) {
				return sanitize_text_field( $date );
			}
		}

		return false;
	}

	/**
	 * Render predictions table grouped by day.
	 */
	private function render_predictions_table( $predictions, $title, $market_type = false, $odds_margin = 0.05 ) {
		if ( empty( $predictions ) ) {
			return '<div class="fp-no-predictions">' . esc_html__( 'There are currently no predictions available. Kindly visit again tomorrow.', 'betigolo-predictions' ) . '</div>';
		}

		$columns = array(
			array( 'key' => 'flag',       'class' => 'fp-col-league',   'label' => '' ),
			array( 'key' => 'match',      'class' => 'fp-col-fixtures', 'label' => __( 'Fixtures', 'betigolo-predictions' ) ),
			array( 'key' => 'prediction', 'class' => 'fp-col-tips',     'label' => __( 'Pred', 'betigolo-predictions' ) ),
			array( 'key' => 'result',     'class' => 'fp-col-result',   'label' => __( 'Odds', 'betigolo-predictions' ) ),
			array( 'key' => 'status',     'class' => 'fp-col-status',   'label' => '' ),
		);

		// Group predictions by formatted match date.
		$grouped = array();
		foreach ( $predictions as $prediction ) {
			$date_key = self::format_timestamp( self::get_value( $prediction, 'match_dat' ), 'date' );
			if ( empty( $date_key ) ) {
				$date_key = __( 'Upcoming', 'betigolo-predictions' );
			}
			if ( ! isset( $grouped[ $date_key ] ) ) {
				$grouped[ $date_key ] = array();
			}
			$grouped[ $date_key ][] = $prediction;
		}

		ob_start();
		?>
		<div class="fp-predictions-container">
			<div class="fp-predictions-header">
				<h2><?php echo esc_html( $title ); ?></h2>
			</div>
			<?php
			foreach ( $grouped as $date_key => $day_predictions ) :
				?>
				<div class="fp-day-table">
					<div class="fp-predictions-header fp-day-header">
						<h4><?php echo esc_html( $date_key ); ?></h4>
					</div>

					<table class="fp-predictions-table" width="100%">
						<thead>
							<tr>
								<?php foreach ( $columns as $column ) : ?>
									<th class="<?php echo esc_attr( $column['class'] ); ?>"><?php echo esc_html( $column['label'] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $day_predictions as $prediction ) {
								$this->render_prediction_row( $prediction, $market_type, $odds_margin );
							}
							?>
						</tbody>
					</table>
				</div>
				<?php
			endforeach;
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render single prediction row.
	 */
	private function render_prediction_row( $p, $market_type = false, $odds_margin = 0.05 ) {
		$home_raw = self::get_value( $p, 'home_team_name' );
		$away_raw = self::get_value( $p, 'away_team_name' );
		$league   = self::get_value( $p, 'league_name' );
		$country  = trim( (string) self::get_value( $p, 'country_name' ) );
		$cluster  = trim( (string) self::get_value( $p, 'competition_cluster' ) );

		$home = self::clean_team_name( $home_raw ? $home_raw : __( 'Home', 'betigolo-predictions' ) );
		$away = self::clean_team_name( $away_raw ? $away_raw : __( 'Away', 'betigolo-predictions' ) );

		$home_win = self::get_float( $p, 'rank_htw_ft' );
		$draw     = self::get_float( $p, 'rank_drw_ft' );
		$away_win = self::get_float( $p, 'rank_atw_ft' );

		if ( 'ou' === $market_type ) {
			$prediction_value = self::get_value( $p, 'ou_value' );
			$probability      = (float) self::get_value( $p, 'ou_probability' );
		} elseif ( 'btts' === $market_type ) {
			$prediction_value = self::get_value( $p, 'btts_value' );
			$probability      = (float) self::get_value( $p, 'btts_probability' );
		} elseif ( $market_type ) {
			$prediction_value = self::get_value( $p, 'dc_value' );
			$probability      = (float) self::get_value( $p, 'dc_probability' );
		} else {
			$prediction       = self::get_1x2_prediction( $home_win, $draw, $away_win, $home, $away );
			$prediction_value = $prediction['value'];
			$probability      = $prediction['probability'];
		}

		$flag = self::get_country_flag( $country );
		if ( empty( $flag ) ) {
			$flag = self::get_country_flag( $cluster );
		}
		if ( empty( $flag ) ) {
			$flag = self::get_team_country_flag( $home_raw );
		}
		if ( empty( $flag ) ) {
			$flag = self::get_team_country_flag( $away_raw );
		}

		$has_flag    = ! empty( $flag );
		$implied_odd = self::calculate_bookmaker_odd( $probability, $odds_margin );
		$match_status = self::get_match_status( $p );
		?>
		<tr class="fp-prediction-row fp-status-pending">
			<td class="fp-col-league" title="<?php echo esc_attr( $league ); ?>">
				<?php if ( $has_flag ) : ?>
					<span class="fp-flag"><?php echo $flag; ?></span>
				<?php endif; ?>
			</td>
			<td class="fp-col-fixtures">
				<span class="fp-match">
					<?php echo esc_html( $home . ' vs ' . $away ); ?>
				</span>
				<?php if ( ! $has_flag && $cluster ) : ?>
					<small class="fp-competition"><?php echo esc_html( self::short_league( $cluster ) ); ?></small>
				<?php endif; ?>
			</td>
			<td class="fp-col-tips">
				<span class="fp-prediction-badge"><?php echo esc_html( $prediction_value ); ?></span>
			</td>
			<td class="fp-col-result">
				<span class="fp-status-badge-pending"><?php echo esc_html( number_format( $implied_odd, 2 ) ); ?></span>
			</td>
			<td class="fp-col-status">
				<?php echo self::get_status_icon( $match_status ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Determine match status based on available API data.
	 */
	private static function get_match_status( $p ) {
		// If the API ever supplies scores or status, use them here.
		if ( isset( $p['match_status'] ) && ! empty( $p['match_status'] ) ) {
			return sanitize_text_field( $p['match_status'] );
		}

		if ( isset( $p['home_score'] ) && isset( $p['away_score'] ) ) {
			return sanitize_text_field( $p['home_score'] . ' - ' . $p['away_score'] );
		}

		if ( isset( $p['result'] ) && ! empty( $p['result'] ) ) {
			return sanitize_format( $p['result'] );
		}

		return 'pending';
	}

	/**
	 * Return a status icon for a match.
	 */
	private static function get_status_icon( $status ) {
		$status = strtolower( trim( (string) $status ) );

		if ( 'pending' === $status ) {
			return '<span class="fp-status-icon fp-status-icon-pending" title="' . esc_attr__( 'Pending', 'betigolo-predictions' ) . '">⏳</span>';
		}

		if ( false !== strpos( $status, '-' ) ) {
			return '<span class="fp-status-icon fp-status-icon-finished" title="' . esc_attr__( 'Finished', 'betigolo-predictions' ) . '">⚽ ' . esc_html( $status ) . '</span>';
		}

		return '<span class="fp-status-icon fp-status-icon-live" title="' . esc_attr__( 'Live', 'betigolo-predictions' ) . '">⚡</span>';
	}

	/**
	 * Calculate 1X2 prediction.
	 */
	private static function get_1x2_prediction( $home_win, $draw, $away_win, $home, $away ) {
		$map = array(
			'1' => array( 'probability' => $home_win, 'label' => $home . ' ' . __( 'Win', 'betigolo-predictions' ) ),
			'X' => array( 'probability' => $draw, 'label' => __( 'Draw', 'betigolo-predictions' ) ),
			'2' => array( 'probability' => $away_win, 'label' => $away . ' ' . __( 'Win', 'betigolo-predictions' ) ),
		);

		$best = '1';
		foreach ( array( 'X', '2' ) as $key ) {
			if ( $map[ $key ]['probability'] > $map[ $best ]['probability'] ) {
				$best = $key;
			}
		}

		return array(
			'value'       => $best,
			'label'       => $map[ $best ]['label'],
			'probability' => $map[ $best ]['probability'],
		);
	}

	/**
	 * Calculate best double chance.
	 */
	private static function get_best_double_chance( $home_win, $draw, $away_win ) {
		$options = array(
			'1X' => $home_win + $draw,
			'X2' => $draw + $away_win,
			'12' => $home_win + $away_win,
		);

		arsort( $options );
		$value = array_key_first( $options );

		return array(
			'value'       => $value,
			'probability' => $options[ $value ],
		);
	}

	/**
	 * Calculate Over/Under prediction.
	 */
	private static function get_over_under_prediction( $over, $under, $min_prob ) {
		if ( $over >= $min_prob ) {
			return array(
				'value'       => 'Over 2.5',
				'probability' => $over,
			);
		}

		if ( $under >= $min_prob ) {
			return array(
				'value'       => 'Under 2.5',
				'probability' => $under,
			);
		}

		return array(
			'value'       => '',
			'probability' => 0,
		);
	}

	/**
	 * Calculate BTTS prediction.
	 */
	private static function get_btts_prediction( $btts_yes, $min_prob ) {
		$btts_no = 1 - $btts_yes;

		if ( $btts_yes >= $min_prob ) {
			return array(
				'value'       => 'BTTS Yes',
				'probability' => $btts_yes,
			);
		}

		if ( $btts_no >= $min_prob ) {
			return array(
				'value'       => 'BTTS No',
				'probability' => $btts_no,
			);
		}

		return array(
			'value'       => '',
			'probability' => 0,
		);
	}

	/**
	 * Apply bookmaker margin to fair implied odds.
	 */
	private static function calculate_bookmaker_odd( $probability, $margin = 0.05 ) {
		if ( $probability <= 0 ) {
			return 0;
		}

		$fair_odd      = 1 / $probability;
		$adjusted_odd  = $fair_odd * ( 1 - $margin );

		return round( max( $adjusted_odd, 1.01 ), 2 );
	}

	/**
	 * Filter predictions by attributes.
	 */
	private static function filter_predictions( $predictions, $atts ) {
		return array_filter(
			$predictions,
			function ( $prediction ) use ( $atts ) {
				if ( ! is_array( $prediction ) ) {
					return true;
				}

				if ( ! empty( $atts['league'] ) ) {
					$league = self::get_value( $prediction, 'league_name' );
					if ( empty( $league ) || false === stripos( (string) $league, $atts['league'] ) ) {
						return false;
					}
				}

				if ( ! empty( $atts['date'] ) ) {
					$date = self::format_timestamp( self::get_value( $prediction, 'match_dat' ), 'date' );
					if ( empty( $date ) || false === stripos( (string) $date, $atts['date'] ) ) {
						return false;
					}
				}

				return true;
			}
		);
	}

	/**
	 * Robustly parse a match date value into a Unix timestamp.
	 *
	 * @param mixed $value Date string or Unix timestamp.
	 * @return int|false Timestamp or false on failure.
	 */
	private static function parse_match_date( $value ) {
		if ( empty( $value ) ) {
			return false;
		}

		// Already a Unix timestamp.
		if ( is_numeric( $value ) && strlen( (string) $value ) === 10 ) {
			return (int) $value;
		}

		$date_string = trim( (string) $value );

		// Explicit known formats (leading ! resets unparsed parts to epoch).
		$formats = array( 'Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y' );
		foreach ( $formats as $fmt ) {
			$dt = DateTime::createFromFormat( '!' . $fmt, $date_string );
			if ( false !== $dt ) {
				return $dt->getTimestamp();
			}
		}

		// Fallback.
		$ts = strtotime( $date_string );
		if ( false !== $ts ) {
			return $ts;
		}

		return false;
	}

	/**
	 * Format a date/timestamp value for display.
	 *
	 * @param mixed  $value Date string or Unix timestamp.
	 * @param string $format 'full' or 'date'.
	 * @return string Formatted date.
	 */
	private static function format_timestamp( $value, $format = 'full' ) {
		$timestamp = self::parse_match_date( $value );

		if ( false === $timestamp ) {
			return (string) $value;
		}

		if ( 'date' === $format ) {
			return wp_date( 'l, jS F Y', $timestamp );
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Safe array getter.
	 */
	private static function get_value( $array, $key ) {
		return isset( $array[ $key ] ) ? $array[ $key ] : null;
	}

	/**
	 * Get float value.
	 */
	private static function get_float( $array, $key ) {
		if ( ! isset( $array[ $key ] ) ) {
			return 0.0;
		}
		return self::to_float( $array[ $key ] );
	}

	/**
	 * Convert to float.
	 */
	private static function to_float( $value ) {
		if ( is_string( $value ) ) {
			$value = str_replace( ',', '.', $value );
		}
		return (float) $value;
	}

	/**
	 * Clean and shorten team name.
	 */
	private static function clean_team_name( $team_name ) {
		$mappings = array(
			'Manchester United'        => 'Man Utd',
			'Manchester City'          => 'Man City',
			'Tottenham Hotspur'        => 'Tottenham',
			'Wolverhampton Wanderers'  => 'Wolves',
			'Nottingham Forest'        => 'Nott\'m Forest',
			'Brighton & Hove Albion'   => 'Brighton',
			'AFC Bournemouth'          => 'Bournemouth',
			'Sheffield United'         => 'Sheffield Utd',
			'Newcastle United'         => 'Newcastle',
			'West Ham United'          => 'West Ham',
			'Paris Saint-Germain'      => 'PSG',
			'Olympique de Marseille'   => 'Marseille',
			'Olympique Lyonnais'       => 'Lyon',
			'FC Bayern München'        => 'Bayern',
			'Borussia Dortmund'        => 'Dortmund',
			'Bayer 04 Leverkusen'      => 'Leverkusen',
			'Borussia Mönchengladbach' => 'Mönchengladbach',
			'Real Madrid'              => 'Real Madrid',
			'FC Barcelona'             => 'Barcelona',
			'Atlético Madrid'          => 'Atlético',
			'Juventus FC'              => 'Juventus',
			'AC Milan'                 => 'Milan',
			'FC Internazionale'        => 'Inter',
			'Ajax Amsterdam'           => 'Ajax',
			'Feyenoord Rotterdam'      => 'Feyenoord',
			'PSV Eindhoven'            => 'PSV',
			'SL Benfica'               => 'Benfica',
			'FC Porto'                 => 'Porto',
			'Sporting CP'              => 'Sporting',
			'HJK Helsinki'             => 'HJK',
			'Klubi-04 Helsinki'        => 'Klubi-04',
			'Gerasdorf/Stammersdorf'   => 'Gerasdorf',
			'SV Donau Wien'            => 'Donau Wien',
			'SSV Jeddeloh'             => 'Jeddeloh',
			'Preußen Münster'          => 'Münster',
			'Preussen Muenster'        => 'Münster',
			'FSV Zwickau'              => 'Zwickau',
			'CSKA 1948 Sofia'          => 'CSKA Sofia',
			'Lokomotiva Zagreb'        => 'Lokomotiva',
			'MFK Frydek-Mistek'        => 'Frydek',
			'Pärnu JK Vaprus II'       => 'Parnu V.',
			'Parnu JK Vaprus II'       => 'Parnu V.',
			'Jeunesse Schieren'        => 'Jeunesse S.',
			'Brisbane Roar Youth'      => 'Brisbane R.',
		);

		if ( isset( $mappings[ $team_name ] ) ) {
			return $mappings[ $team_name ];
		}

		$team_name = trim( (string) $team_name );
		$team_name = preg_replace( '/\s*\([^)]*\)/', '', $team_name );

		// Strip standalone founding years like "1912", "1948" but keep historic names such as "1860".
		$team_name = preg_replace( '/\s+\d{4}(?!\d)/', '', $team_name );

		// Strip common club prefixes.
		$team_name = preg_replace( '/^\s*(AFC|FC|SC|AC|AS|CD|CF|FF|FSV|IF|IK|MFK|SK|SSV|SV|TSV)\s+/i', '', $team_name );
		// Strip common club suffixes.
		$team_name = preg_replace( '/\s+(AFC|FC|SC|AC|AS|CD|CF|FF|FSV|IF|IK|MFK|SK|SSV|SV|TSV)\s*$/i', '', $team_name );

		// Shorten slash/hyphen-combined place names, e.g. "A/B" or "A-B" → "A".
		if ( false !== strpos( $team_name, '/' ) ) {
			$parts     = explode( '/', $team_name );
			$team_name = trim( $parts[0] );
		}
		if ( false !== strpos( $team_name, '-' ) ) {
			$parts     = explode( '-', $team_name );
			$team_name = trim( $parts[0] );
		}

		$team_name = trim( $team_name );

		// Abbreviate overly long names automatically.
		return self::abbreviate_team_name( $team_name );
	}

	/**
	 * Abbreviate long team names to first-word + initial of last main word.
	 */
	private static function abbreviate_team_name( $team_name ) {
		$team = remove_accents( trim( (string) $team_name ) );
		$team = trim( $team );

		// Names that are already short are left untouched.
		if ( strlen( $team ) <= 15 ) {
			return $team;
		}

		$words = preg_split( '/\s+/', $team );
		if ( count( $words ) <= 1 ) {
			return $team;
		}

		// Suffixes that are dropped during abbreviation.
		$suffixes = array( 'Youth', 'II', 'III', 'IV', 'V', 'B', 'U19', 'U21', 'U23' );

		// Peel trailing suffixes off the end.
		while ( count( $words ) > 1 ) {
			$last = end( $words );
			if ( in_array( strtoupper( $last ), array_map( 'strtoupper', $suffixes ), true ) || preg_match( '/^[IVX]+$/i', $last ) ) {
				array_pop( $words );
			} else {
				break;
			}
		}

		if ( count( $words ) <= 1 ) {
			return implode( ' ', $words );
		}

		$first = array_shift( $words );
		$last  = array_pop( $words );

		return $first . ' ' . mb_substr( $last, 0, 1 ) . '.';
	}

	/**
	 * Shorten league/cluster name.
	 */
	private static function short_league( $league ) {
		$league = trim( (string) $league );

		if ( strlen( $league ) <= 25 ) {
			return $league;
		}

		return substr( $league, 0, 22 ) . '...';
	}

	/**
	 * Get country flag from known club names when API country is missing.
	 */
	private static function get_team_country_flag( $team_name ) {
		$team = strtolower( self::normalize_team_name( (string) $team_name ) );

		if ( empty( $team ) ) {
			return '';
		}

		$clubs = array(

			// Argentina.
			'boca juniors'          => '🇦🇷',
			'river plate'           => '🇦🇷',
			'racing club'           => '🇦🇷',
			' Independiente'        => '🇦🇷',

			// Austria.
			'fk austria wien'       => '🇦🇹',
			'rapid wien'            => '🇦🇹',
			'red bull salzburg'     => '🇦🇹',
			'lask linz'             => '🇦🇹',
			'sturm graz'            => '🇦🇹',
			'wolfsberger ac'        => '🇦🇹',
			'hartberg'              => '🇦🇹',
			' Austria Wien'         => '🇦🇹',
			' Rapid Wien'           => '🇦🇹',
			' Salzburg'             => '🇦🇹',

			// Belgium.
			'anderlecht'            => '🇧🇪',
			'club brugge'           => '🇧🇪',
			'genk'                  => '🇧🇪',
			'gent'                  => '🇧🇪',
			'standard liege'        => '🇧🇪',
			'charleroi'             => '🇧🇪',
			'kortrijk'              => '🇧🇪',
			'oud-heverlee leuven'   => '🇧🇪',
			'eupen'                 => '🇧🇪',
			'antwerp'               => '🇧🇪',
			'mechelen'              => '🇧🇪',
			'sint-truiden'          => '🇧🇪',
			'cercle brugge'         => '🇧🇪',
			'union sg'              => '🇧🇪',
			'kv oostende'           => '🇧🇪',
			'zulte waregem'         => '🇧🇪',
			'ksv oudenaarde'        => '🇧🇪',
			'club nxt'              => '🇧🇪',
			'oudenaarde'            => '🇧🇪',

			// Brazil.
			'flamengo'              => '🇧🇷',
			'palmeiras'             => '🇧🇷',
			'atletico mineiro'      => '🇧🇷',
			'corinthians'           => '🇧🇷',
			'internacional'         => '🇧🇷',
			'fluminense'            => '🇧🇷',
			'sao paulo'             => '🇧🇷',
			'bota fogo'             => '🇧🇷',
			'gremio'                => '🇧🇷',
			'athletico paranaense'  => '🇧🇷',
			'cruzeiro'              => '🇧🇷',
			'santos'                => '🇧🇷',
			'vasco da gama'         => '🇧🇷',

			// Bulgaria.
			'ludogorets'            => '🇧🇬',
			'cska sofia'            => '🇧🇬',
			'levski sofia'          => '🇧🇬',
			'cherno more varna'     => '🇧🇬',
			'botev plovdiv'         => '🇧🇬',
			'beroe stara zagora'    => '🇧🇬',
			'slavia sofia'          => '🇧🇬',
			'lokomotiv plovdiv'     => '🇧🇬',
			'lokomotiv sofia'       => '🇧🇬',
			'aramaes sfc'           => '🇧🇬',
			'oborishte'             => '🇧🇬',
			'gigant saedinenie'     => '🇧🇬',
			'spartak varna'         => '🇧🇬',

			// Chile.
			'colo-colo'             => '🇨🇱',
			'universidad de chile'  => '🇨🇱',
			'universidad catolica'  => '🇨🇱',

			// China.
			'shanghai sipg'         => '🇨🇳',
			'guangzhou'             => '🇨🇳',
			'shandong taishan'      => '🇨🇳',
			'beijing guoan'         => '🇨🇳',

			// Colombia.
			'millonarios'           => '🇨🇴',
			'nacional'              => '🇨🇴',
			'america de cali'       => '🇨🇴',
			'deportivo cali'        => '🇨🇴',
			'junior'                => '🇨🇴',
			'independiente medellin' => '🇨🇴',

			// Croatia.
			'dinamo zagreb'         => '🇭🇷',
			'hajduk split'          => '🇭🇷',
			'osijek'                => '🇭🇷',
			'rijeka'                => '🇭🇷',
			'lokomotiva zagreb'     => '🇭🇷',
			'slaven belupo'         => '🇭🇷',
			'istra 1961'            => '🇭🇷',
			'hnk gorica'            => '🇭🇷',
			'lokomotiva'            => '🇭🇷',

			// Czech Republic.
			'slavia praha'          => '🇨🇿',
			'sparta praha'          => '🇨🇿',
			'viktoria plzen'        => '🇨🇿',
			'banik ostrava'         => '🇨🇿',
			'bohemians 1905'        => '🇨🇿',
			'fc copenhagen'         => '🇨🇿',
			'slovan liberec'        => '🇨🇿',
			'fk jablonec'           => '🇨🇿',
			'fc fastav zlin'        => '🇨🇿',
			'fk mlada boleslav'     => '🇨🇿',
			'fk teplice'            => '🇨🇿',
			'sigma olomouc'         => '🇨🇿',
			'fk pardubice'          => '🇨🇿',
			'hnk hradec kralove'    => '🇨🇿',
			'1. fcn'                => '🇨🇿',
			'1 fc slovacko'         => '🇨🇿',
			'zbrojovka brno'        => '🇨🇿',
			'mfk frydek-mistek'     => '🇨🇿',
			'frydek-mistek'         => '🇨🇿',
			'frydek'                => '🇨🇿',
			'valasske mezirici'     => '🇨🇿',

			// Denmark.
			'fc copenhagen'         => '🇩🇰',
			'brondby'               => '🇩🇰',
			'midtjylland'           => '🇩🇰',
			'nordsjaelland'         => '🇩🇰',
			'aarhus gf'             => '🇩🇰',
			'ob odense'             => '🇩🇰',
			'randers'               => '🇩🇰',
			'silkeborg'             => '🇩🇰',
			'viborg'                => '🇩🇰',
			'lyngby'                => '🇩🇰',
			'hvidovre'              => '🇩🇰',

			// England.
			'manchester city'       => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'manchester united'     => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'liverpool'             => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'arsenal'               => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'chelsea'               => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'tottenham'             => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'newcastle'             => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'aston villa'           => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'brighton'              => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'west ham'              => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'brentford'             => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'crystal palace'        => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'nottingham forest'     => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'everton'               => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'fulham'                => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'wolves'                => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'bournemouth'           => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'burnley'               => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'sheffield united'      => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'luton town'            => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',

			// Estonia.
			'flora tallinn'         => '🇪🇪',
			'nomme united'          => '🇪🇪',
			'levadia'               => '🇪🇪',
			'paide linnameeskond'   => '🇪🇪',
			'kalju'                 => '🇪🇪',
			'trans narva'           => '🇪🇪',
			'kuressaare'            => '🇪🇪',

			// Finland.
			'hjk helsinki'          => '🇫🇮',
			'kups'                  => '🇫🇮',
			'sjk'                   => '🇫🇮',
			'honka'                 => '🇫🇮',
			'ilves'                 => '🇫🇮',
			'vps'                   => '🇫🇮',
			'inter turku'           => '🇫🇮',
			'lahti'                 => '🇫🇮',
			'mariehamn'             => '🇫🇮',
			'kapa helsinki'         => '🇫🇮',
			'sjk akatemia'          => '🇫🇮',

			// France.
			'paris saint-germain'   => '🇫🇷',
			'marseille'             => '🇫🇷',
			'lyon'                  => '🇫🇷',
			'monaco'                => '🇫🇷',
			'lille'                 => '🇫🇷',
			'rennes'                => '🇫🇷',
			'nice'                  => '🇫🇷',
			'lens'                  => '🇫🇷',
			'strasbourg'            => '🇫🇷',
			'montpellier'           => '🇫🇷',
			'reims'                 => '🇫🇷',
			'nantes'                => '🇫🇷',
			'toulouse'              => '🇫🇷',
			'brest'                 => '🇫🇷',
			'le havre'              => '🇫🇷',
			'metz'                  => '🇫🇷',
			'lorient'               => '🇫🇷',
			'clermont'              => '🇫🇷',

			// Germany.
			'bayern munich'         => '🇩🇪',
			'borussia dortmund'     => '🇩🇪',
			'leverkusen'            => '🇩🇪',
			'rb leipzig'            => '🇩🇪',
			'eintracht frankfurt'   => '🇩🇪',
			'wolfsburg'             => '🇩🇪',
			'freiburg'              => '🇩🇪',
			'union berlin'          => '🇩🇪',
			'borussia monchengladbach' => '🇩🇪',
			'mainz'                 => '🇩🇪',
			'augsburg'              => '🇩🇪',
			'hoffenheim'            => '🇩🇪',
			'werder bremen'         => '🇩🇪',
			'heidenheim'            => '🇩🇪',
			'stuttgart'             => '🇩🇪',
			'bochum'                => '🇩🇪',
			'cologne'               => '🇩🇪',
			'darmstadt'             => '🇩🇪',
			'spvgg bayreuth'        => '🇩🇪',
			'carl zeiss jena'       => '🇩🇪',
			'astoria walldorf'      => '🇩🇪',
			'ssv jeddeloh'          => '🇩🇪',
			'preussen munster'      => '🇩🇪',
			'fsv zwickau'           => '🇩🇪',

			// Greece.
			'olympiacos'            => '🇬🇷',
			'paok'                  => '🇬🇷',
			'aek athens'            => '🇬🇷',
			'panathinaikos'         => '🇬🇷',
			'arist thessaloniki'    => '🇬🇷',

			// Hungary.
			'ferencvaros'           => '🇭🇺',
			'mtk budapest'          => '🇭🇺',
			'debrecen'              => '🇭🇺',
			'puskas akademia'       => '🇭🇺',
			'fehervar'              => '🇭🇺',

			// Italy.
			'inter'                 => '🇮🇹',
			'juventus'              => '🇮🇹',
			'ac milan'              => '🇮🇹',
			'napoli'                => '🇮🇹',
			'roma'                  => '🇮🇹',
			'atalanta'              => '🇮🇹',
			'fiorentina'            => '🇮🇹',
			'bologna'               => '🇮🇹',
			'torino'                => '🇮🇹',
			'monza'                 => '🇮🇹',
			'sassuolo'              => '🇮🇹',
			'udinese'               => '🇮🇹',
			'empoli'                => '🇮🇹',
			'verona'                => '🇮🇹',
			'lece'                  => '🇮🇹',
			'cagliari'              => '🇮🇹',
			'frosinone'             => '🇮🇹',
			'genoa'                 => '🇮🇹',
			'salernitana'           => '🇮🇹',

			// Japan.
			'kashima antlers'       => '🇯🇵',
			'kawasaki frontale'     => '🇯🇵',
			'yokohama f. marinos'   => '🇯🇵',
			'urawa red diamonds'    => '🇯🇵',
			'vissel kobe'           => '🇯🇵',
			'nagoya grampus'        => '🇯🇵',
			'gamba osaka'           => '🇯🇵',
			'fc tokyo'              => '🇯🇵',
			'consadole sapporo'     => '🇯🇵',

			// Kazakhstan.
			'kairat almaty'         => '🇰🇿',
			'astana'                => '🇰🇿',
			'tobol kostanay'        => '🇰🇿',
			'aktobe'                => '🇰🇿',
			'ordabasy'              => '🇰🇿',
			'atyrau'                => '🇰🇿',

			// Netherlands.
			'ajax'                  => '🇳🇱',
			'psv'                   => '🇳🇱',
			'feyenoord'             => '🇳🇱',
			'az alkmaar'            => '🇳🇱',
			'twente'                => '🇳🇱',
			'utrecht'               => '🇳🇱',
			'heerenveen'            => '🇳🇱',
			'nec'                   => '🇳🇱',
			'sparta rotterdam'      => '🇳🇱',
			'go ahead eagles'       => '🇳🇱',
			'fortuna sittard'       => '🇳🇱',
			'pec zwolle'            => '🇳🇱',

			// Norway.
			'bodo glimt'            => '🇳🇴',
			'molde'                 => '🇳🇴',
			'rosenborg'             => '🇳🇴',
			'viking'                => '🇳🇴',
			'lillestrom'            => '🇳🇴',
			'brann'                 => '🇳🇴',
			'tromso'                => '🇳🇴',
			'sarpsborg'             => '🇳🇴',
			'odd'                   => '🇳🇴',
			'haugesund'             => '🇳🇴',

			// Poland.
			'legia warsaw'          => '🇵🇱',
			'lech poznan'           => '🇵🇱',
			'rakow czestochowa'     => '🇵🇱',
			'pogon szczecin'        => '🇵🇱',
			'gornik zabrze'         => '🇵🇱',
			'cracovia'              => '🇵🇱',
			'widzew lodz'           => '🇵🇱',
			'zaglebie lubin'        => '🇵🇱',
			'slask wroclaw'         => '🇵🇱',
			'wisla krakow'          => '🇵🇱',

			// Portugal.
			'benfica'               => '🇵🇹',
			'porto'                 => '🇵🇹',
			'sporting cp'           => '🇵🇹',
			'braga'                 => '🇵🇹',
			'vitoria guimaraes'     => '🇵🇹',
			'boavista'              => '🇵🇹',
			'famalicao'             => '🇵🇹',
			'casa pia'              => '🇵🇹',
			'arouca'                => '🇵🇹',
			'gil vicente'           => '🇵🇹',
			'vizela'                => '🇵🇹',
			'estoril'               => '🇵🇹',
			'rio ave'               => '🇵🇹',
			'portimonense'          => '🇵🇹',
			'estrela'               => '🇵🇹',
			'chaves'                => '🇵🇹',
			'moreirense'            => '🇵🇹',
			'farense'               => '🇵🇹',

			// Romania.
			'fcsb'                  => '🇷🇴',
			'cfr cluj'              => '🇷🇴',
			'universitatea craiova' => '🇷🇴',
			'rapid bucuresti'       => '🇷🇴',
			'fc voluntari'          => '🇷🇴',
			'sepsi'                 => '🇷🇴',
			'petrolul ploiesti'     => '🇷🇴',
			'u cluj'                => '🇷🇴',
			'farul constanta'       => '🇷🇴',

			// Russia.
			'zenit'                 => '🇷🇺',
			'spartak moscow'        => '🇷🇺',
			'cska moscow'           => '🇷🇺',
			'lokomotiv moscow'      => '🇷🇺',
			'dynamo moscow'         => '🇷🇺',
			'krasnoda'              => '🇷🇺',
			'sochi'                 => '🇷🇺',
			'rostov'                => '🇷🇺',

			// Scotland.
			'celtic'                => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'rangers'               => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'hibernian'             => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'hearts'                => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'aberdeen'              => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'dundee united'         => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'motherwell'            => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'kilmarnock'            => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'st mirren'             => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'livingston'            => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'ross county'           => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'st johnstone'          => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',

			// Serbia.
			'crvena zvezda'         => '🇷🇸',
			'partizan'              => '🇷🇸',
			'radnicki nis'          => '🇷🇸',
			'cukaricki'             => '🇷🇸',
			'vojvodina'             => '🇷🇸',
			'tsc backa topola'      => '🇷🇸',

			// Slovakia.
			'slovan bratislava'     => '🇸🇰',
			'spartak trnava'        => '🇸🇰',
			'zilina'                => '🇸🇰',
			'ruzomberok'            => '🇸🇰',
			'as trencin'            => '🇸🇰',
			'zemplin michalovce'    => '🇸🇰',
			'dac 1904'              => '🇸🇰',
			'podbrezova'            => '🇸🇰',
			'zeleziarne podbrezova' => '🇸🇰',

			// Spain.
			'real madrid'           => '🇪🇸',
			'barcelona'             => '🇪🇸',
			'atletico madrid'       => '🇪🇸',
			'sevilla'               => '🇪🇸',
			'real sociedad'         => '🇪🇸',
			'villarreal'            => '🇪🇸',
			'athletic bilbao'       => '🇪🇸',
			'betis'                 => '🇪🇸',
			'valencia'              => '🇪🇸',
			'getafe'                => '🇪🇸',
			'celta vigo'            => '🇪🇸',
			'osasuna'               => '🇪🇸',
			'rayo vallecano'        => '🇪🇸',
			'mallorca'              => '🇪🇸',
			'alaves'                => '🇪🇸',
			'las palmas'            => '🇪🇸',
			'granada'               => '🇪🇸',
			'cadiz'                 => '🇪🇸',

			// Sweden.
			'malmo ff'              => '🇸🇪',
			'djurgardens'           => '🇸🇪',
			'hammarby'              => '🇸🇪',
			'norrkoping'            => '🇸🇪',
			'ifk goteborg'          => '🇸🇪',
			'elfsborg'              => '🇸🇪',
			'kalmar'                => '🇸🇪',
			'sirius'                => '🇸🇪',
			'hacken'                => '🇸🇪',
			'varnamo'               => '🇸🇪',
			'degerfors'             => '🇸🇪',
			'gefle'                 => '🇸🇪',

			// Switzerland.
			'basel'                 => '🇨🇭',
			'young boys'            => '🇨🇭',
			'servette'              => '🇨🇭',
			'lugano'                => '🇨🇭',
			'zurich'                => '🇨🇭',
			'grasshopper'           => '🇨🇭',
			'luzern'                => '🇨🇭',
			'sion'                  => '🇨🇭',
			'st gallen'             => '🇨🇭',
			'winterthur'            => '🇨🇭',
			'lausanne'              => '🇨🇭',

			// Turkey.
			'galatasaray'           => '🇹🇷',
			'fenerbahce'            => '🇹🇷',
			'besiktas'              => '🇹🇷',
			'trabzonspor'           => '🇹🇷',
			'basaksehir'            => '🇹🇷',
			'konyaspor'             => '🇹🇷',
			'antalyaspor'           => '🇹🇷',
			'ankaragucu'            => '🇹🇷',
			'alanyaspor'            => '🇹🇷',
			'kasimpasa'             => '🇹🇷',
			'sivasspor'             => '🇹🇷',
			'gaziantep'             => '🇹🇷',

			// Ukraine.
			'shakhtar donetsk'      => '🇺🇦',
			'dynamo kyiv'           => '🇺🇦',
			'dnipro-1'              => '🇺🇦',
			'zorya luhansk'         => '🇺🇦',
			'vorskla poltava'       => '🇺🇦',
			'kryvbas'               => '🇺🇦',
			'karpaty lviv'          => '🇺🇦',
			'metalist'              => '🇺🇦',
			'livyi bereh'           => '🇺🇦',
			'obolon kyiv'           => '🇺🇦',
			'karpaty'               => '🇺🇦',
			'lviv'                  => '🇺🇦',

			// Uzbekistan.
			'pakhtakor'             => '🇺🇿',
			'lokomotiv tashkent'    => '🇺🇿',
			'bunyodkor'             => '🇺🇿',
			'nasaf'                 => '🇺🇿',
			'nasaf qarshi'          => '🇺🇿',
			'mashal mubarek'        => '🇺🇿',
			'qoqon'                 => '🇺🇿',
			'kokand 1912'           => '🇺🇿',
		);

		if ( isset( $clubs[ $team ] ) ) {
			return $clubs[ $team ];
		}

		foreach ( $clubs as $club => $flag ) {
			if ( false !== strpos( $team, $club ) || false !== strpos( $club, $team ) ) {
				return $flag;
			}
		}

		return '';
	}

	/**
	 * Normalize team name for club matching.
	 */
	private static function normalize_team_name( $team_name ) {
		$team = strtolower( trim( (string) $team_name ) );
		// Remove diacritics.
		$team = remove_accents( $team );
		// Strip common prefixes/suffixes.
		$team = preg_replace( '/^\s*(fc|sc|ac|as|cd|cf|ff|fsv|if|ik|mfk|sk|ssv|sv|tsv|tzov)\s+/i', '', $team );
		$team = preg_replace( '/\s+(fc|sc|ac|as|cd|cf|ff|fsv|if|ik|mfk|sk|ssv|sv|tsv|tzov)\s*$/i', '', $team );
		// Strip parentheses content.
		$team = preg_replace( '/\s*\([^)]*\)/', '', $team );
		// Strip standalone years.
		$team = preg_replace( '/\s+\d{4}(?!\d)/', '', $team );
		return trim( $team );
	}

	/**
	 * Get country flag emoji.
	 */
	private static function get_country_flag( $country ) {
		$country = strtolower( trim( (string) $country ) );

		if ( empty( $country ) ) {
			return '';
		}

		$flags = array(
			// Specific first to avoid false partial matches.
			'northern ireland' => '🇬🇧',
			'saudi arabia'     => '🇸🇦',
			'south africa'     => '🇿🇦',
			'costa rica'       => '🇨🇷',
			'czech republic'   => '🇨🇿',
			'united kingdom'   => '🇬🇧',
			'great britain'    => '🇬🇧',
			'united states'    => '🇺🇸',

			// Countries.
			'england'          => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'english'          => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
			'spain'            => '🇪🇸',
			'spanish'          => '🇪🇸',
			'espana'           => '🇪🇸',
			'españa'           => '🇪🇸',
			'germany'          => '🇩🇪',
			'german'           => '🇩🇪',
			'deutschland'      => '🇩🇪',
			'france'           => '🇫🇷',
			'french'           => '🇫🇷',
			'italy'            => '🇮🇹',
			'italian'          => '🇮🇹',
			'netherlands'      => '🇳🇱',
			'dutch'            => '🇳🇱',
			'holland'          => '🇳🇱',
			'portugal'         => '🇵🇹',
			'portuguese'       => '🇵🇹',
			'belgium'          => '🇧🇪',
			'belgian'          => '🇧🇪',
			'turkey'           => '🇹🇷',
			'turkish'          => '🇹🇷',
			'türkiye'          => '🇹🇷',
			'denmark'          => '🇩🇰',
			'danish'           => '🇩🇰',
			'sweden'           => '🇸🇪',
			'swedish'          => '🇸🇪',
			'norway'           => '🇳🇴',
			'norwegian'        => '🇳🇴',
			'finland'          => '🇫🇮',
			'finnish'          => '🇫🇮',
			'iceland'          => '🇮🇸',
			'icelandic'        => '🇮🇸',
			'austria'          => '🇦🇹',
			'austrian'         => '🇦🇹',
			'switzerland'      => '🇨🇭',
			'swiss'            => '🇨🇭',
			'greece'           => '🇬🇷',
			'greek'            => '🇬🇷',
			'scotland'         => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'scottish'         => '🏴󠁧󠁢󠁳󠁣󠁴󠁿',
			'wales'            => '🏴󠁧󠁢󠁷󠁬󠁳󠁿',
			'welsh'            => '🏴󠁧󠁢󠁷󠁬󠁳󠁿',
			'ireland'          => '🇮🇪',
			'irish'            => '🇮🇪',
			'britain'          => '🇬🇧',
			'british'          => '🇬🇧',

			// Americas.
			'brazil'           => '🇧🇷',
			'brazilian'        => '🇧🇷',
			'brasil'           => '🇧🇷',
			'argentina'        => '🇦🇷',
			'argentinian'      => '🇦🇷',
			'usa'              => '🇺🇸',
			'america'          => '🇺🇸',
			'american'         => '🇺🇸',
			'mexico'           => '🇲🇽',
			'mexican'          => '🇲🇽',
			'canada'           => '🇨🇦',
			'canadian'         => '🇨🇦',
			'chile'            => '🇨🇱',
			'colombia'         => '🇨🇴',
			'ecuador'          => '🇪🇨',
			'peru'             => '🇵🇪',
			'uruguay'          => '🇺🇾',
			'venezuela'        => '🇻🇪',
			'paraguay'         => '🇵🇾',
			'bolivia'          => '🇧🇴',

			// Asia / Oceania.
			'japan'            => '🇯🇵',
			'japanese'         => '🇯🇵',
			'korea'            => '🇰🇷',
			'korean'           => '🇰🇷',
			'australia'        => '🇦🇺',
			'australian'       => '🇦🇺',
			'china'            => '🇨🇳',
			'chinese'          => '🇨🇳',
			'india'            => '🇮🇳',
			'indian'           => '🇮🇳',
			'thailand'         => '🇹🇭',
			'malaysia'         => '🇲🇾',
			'singapore'        => '🇸🇬',
			'indonesia'        => '🇮🇩',
			'vietnam'          => '🇻🇳',
			'kazakhstan'       => '🇰🇿',
			'macau'            => '🇲🇴',
			'macao'            => '🇲🇴',
			'uzbekistan'       => '🇺🇿',

			// Middle East.
			'egypt'            => '🇪🇬',
			'egyptian'         => '🇪🇬',
			'morocco'          => '🇲🇦',
			'moroccan'         => '🇲🇦',
			'nigeria'          => '🇳🇬',
			'ghana'            => '🇬🇭',
			'saudi'            => '🇸🇦',
			'qatar'            => '🇶🇦',
			'uae'              => '🇦🇪',
			'israel'           => '🇮🇱',
			'jordan'           => '🇯🇴',
			'kuwait'           => '🇰🇼',
			'bahrain'          => '🇧🇭',
			'oman'             => '🇴🇲',

			// Europe more.
			'poland'           => '🇵🇱',
			'polish'           => '🇵🇱',
			'czech'            => '🇨🇿',
			'czechia'          => '🇨🇿',
			'slovakia'         => '🇸🇰',
			'slovak'           => '🇸🇰',
			'slovenia'         => '🇸🇮',
			'slovenian'        => '🇸🇮',
			'croatia'          => '🇭🇷',
			'croatian'         => '🇭🇷',
			'serbia'           => '🇷🇸',
			'serbian'          => '🇷🇸',
			'romania'          => '🇷🇴',
			'romanian'         => '🇷🇴',
			'bulgaria'         => '🇧🇬',
			'bulgarian'        => '🇧🇬',
			'hungary'          => '🇭🇺',
			'hungarian'        => '🇭🇺',
			'ukraine'          => '🇺🇦',
			'ukrainian'        => '🇺🇦',
			'russia'           => '🇷🇺',
			'belarus'          => '🇧🇾',
			'estonia'          => '🇪🇪',
			'latvia'           => '🇱🇻',
			'lithuania'        => '🇱🇹',

			// International / other.
			'international'    => '🌍',
			'world'            => '🌍',
			'fifa'             => '🌍',
			'uefa'             => '🇪🇺',
			'concacaf'         => '🌎',
			'caf'              => '🌍',
			'afc'              => '🌏',
			'conmebol'         => '🌎',
			'club friendly'    => '🤝',
			'friendly'         => '🤝',
		);

		if ( isset( $flags[ $country ] ) ) {
			return $flags[ $country ];
		}

		foreach ( $flags as $key => $flag ) {
			if ( false !== strpos( $country, $key ) || false !== strpos( $key, $country ) ) {
				return $flag;
			}
		}

		return '';
	}
}

new Betigolo_Shortcode();