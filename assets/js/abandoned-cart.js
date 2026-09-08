(function () {
	'use strict';

	var timer = null;
	var lastEmail = '';

	function field(names) {
		var i;
		for (i = 0; i < names.length; i++) {
			if (document.querySelector('[name="' + names[i] + '"]')) {
				return document.querySelector('[name="' + names[i] + '"]');
			}
		}
		return null;
	}

	function validEmail(value) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value || '');
	}

	function capture() {
		var config = window.ghlcsAbandonedCart || {};
		var email = field(['billing_email', 'email']);
		var data;

		if (!config.ajaxUrl || !config.nonce || !window.fetch || !email || !validEmail(email.value) || email.value === lastEmail) {
			return;
		}

		lastEmail = email.value;
		data = new FormData();
		data.append('action', 'ghlcs_capture_checkout_identity');
		data.append('nonce', config.nonce);
		data.append('email', email.value);
		data.append('first_name', (field(['billing_first_name', 'first_name']) || {}).value || '');
		data.append('last_name', (field(['billing_last_name', 'last_name']) || {}).value || '');
		data.append('phone', (field(['billing_phone', 'phone']) || {}).value || '');

		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		}).catch(function () {});
	}

	function queueCapture() {
		window.clearTimeout(timer);
		timer = window.setTimeout(capture, 900);
	}

	document.addEventListener('input', queueCapture);
	document.addEventListener('change', queueCapture);
	document.addEventListener('DOMContentLoaded', queueCapture);
}());
