(function () {
	'use strict';

	function getConfig() {
		return window.ghlcsFrontend || {};
	}

	function setResponse(form, message, type) {
		var response = form.querySelector('.ghlcs-response');

		if (!response) {
			return;
		}

		response.textContent = message || '';
		response.classList.remove('is-success', 'is-error');

		if (type) {
			response.classList.add('is-' + type);
		}
	}

	function setSubmitting(form, submitting) {
		var button = form.querySelector('.ghlcs-submit');

		if (!button) {
			return;
		}

		if (submitting) {
			button.dataset.originalText = button.textContent;
			button.textContent = form.dataset.loadingText || button.textContent;
			button.disabled = true;
			form.classList.add('is-submitting');
			return;
		}

		button.textContent = button.dataset.originalText || button.textContent;
		button.disabled = false;
		form.classList.remove('is-submitting');
	}

	function appendTracking(formData) {
		var params = new URLSearchParams(window.location.search);
		var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

		formData.append('ghlcs_landing_page', window.location.href);
		formData.append('ghlcs_referrer', document.referrer || '');

		keys.forEach(function (key) {
			if (params.has(key)) {
				formData.append(key, params.get(key));
			}
		});
	}

	function onSubmit(event) {
		var form = event.target;
		var config = getConfig();
		var formData;

		if (!form.classList.contains('ghlcs-form')) {
			return;
		}

		event.preventDefault();

		if (!config.ajaxUrl || !window.fetch) {
			form.submit();
			return;
		}

		if (form.checkValidity && !form.checkValidity()) {
			form.reportValidity();
			return;
		}

		formData = new FormData(form);
		formData.append('action', config.action || 'ghlcs_submit_form');
		appendTracking(formData);

		setResponse(form, '', '');
		setSubmitting(form, true);

		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (response) {
				var data = response.data || {};

				if (!response.success) {
					throw new Error(data.message || 'Something went wrong. Please try again.');
				}

				setResponse(form, data.message || '', 'success');
				form.reset();

				if ('redirect' === data.success_behavior && data.redirect_url) {
					window.location.href = data.redirect_url;
				}
			})
			.catch(function (error) {
				setResponse(form, error.message, 'error');
			})
			.finally(function () {
				setSubmitting(form, false);
			});
	}

	document.addEventListener('submit', onSubmit);
}());
