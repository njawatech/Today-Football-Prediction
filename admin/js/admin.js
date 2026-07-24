/* jshint esversion: 6 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		// Toggle API key visibility.
		var $keyField = $( '#tfp_api_key' );

		if ( $keyField.length ) {
			var $toggle = $( '<button type="button" class="button button-secondary" style="margin-left:6px">Show</button>' );
			$keyField.after( $toggle );

			$toggle.on( 'click', function () {
				if ( 'password' === $keyField.attr( 'type' ) ) {
					$keyField.attr( 'type', 'text' );
					$toggle.text( 'Hide' );
				} else {
					$keyField.attr( 'type', 'password' );
					$toggle.text( 'Show' );
				}
			} );
		}
	} );
} )( jQuery );
