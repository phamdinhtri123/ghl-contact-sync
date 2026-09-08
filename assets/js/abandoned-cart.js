(function () {
	'use strict';

	var timer = null;
	var lastEmail = '';

	function field(selectors) {
		var i;
		for (i = 0; i < selectors.length; i++) {
			if (document.querySelector(selectors[i])) {
				return document.querySelector(selectors[i]);
			}
		}
		return null;
	}

	function validEmail(value) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value || '');
	}

	function capture() {
		var config = window.ghlcsAbandonedCart || {};
		var email = field([
			'[name="billing_email"]',
			'[name="email"]',
			'#billing_email',
			'#email',
			'[autocomplete="email"]',
			'input[type="email"]'
		]);
		var data;

		if (!config.ajaxUrl || !config.nonce || !window.fetch || !email || !validEmail(email.value) || email.value === lastEmail) {
			return;
		}

		lastEmail = email.value;
		data = new FormData();
		data.append('action', 'ghlcs_capture_checkout_identity');
		data.append('nonce', config.nonce);
		data.append('email', email.value);
		data.append('first_name', (field(['[name="billing_first_name"]', '[name="first_name"]', '#billing_first_name', '[autocomplete="given-name"]']) || {}).value || '');
		data.append('last_name', (field(['[name="billing_last_name"]', '[name="last_name"]', '#billing_last_name', '[autocomplete="family-name"]']) || {}).value || '');
		data.append('phone', (field(['[name="billing_phone"]', '[name="phone"]', '#billing_phone', '[autocomplete="tel"]']) || {}).value || '');

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
