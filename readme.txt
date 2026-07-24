=== Betigolo Predictions ===
Contributors: yourname
Tags: predictions, sports, football, api, rapidapi
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fetches and displays football predictions from the Betigolo Predictions RapidAPI endpoint.

== Description ==

This plugin connects to the Betigolo Predictions API and displays all available prediction fields on your WordPress site.

Features:
- Secure API key storage via settings page or wp-config.php constant
- WordPress HTTP API integration
- Response caching with transients
- Shortcode `[betigolo_predictions]`
- Known endpoint support: sample, predictions, jackpot
- Attribute filtering by league, date, and status

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to Settings > Betigolo and enter your RapidAPI key.
4. Use the shortcode `[betigolo_predictions]` in any post or page.

== Shortcode examples ==

`[betigolo_predictions]`
`[betigolo_predictions endpoint="sample"]`
`[betigolo_predictions endpoint="predictions" limit="10"]`
`[betigolo_predictions league="Premier League"]`
`[betigolo_predictions date="2026-07-15"]`

== Securing your API key ==

For better security, define the key in wp-config.php:
`define( 'BETIGOLO_API_KEY', 'your-rapidapi-key' );`

== Changelog ==

= 1.0.0 =
* Initial release.