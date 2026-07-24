(function ($) {
	'use strict';

	$(function () {
		var $key = $('#betigolo_api_key');
		var $wrap = $key.closest('td');

		if ($key.length && $key.attr('type') === 'password') {
			var $toggle = $('<button type="button" class="button button-small">Show</button>');
			$wrap.append(' ', $toggle);

			$toggle.on('click', function () {
				if ($key.attr('type') === 'password') {
					$key.attr('type', 'text');
					$toggle.text('Hide');
				} else {
					$key.attr('type', 'password');
					$toggle.text('Show');
				}
			});
		}
	});
})(jQuery);