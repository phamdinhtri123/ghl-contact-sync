(function () {
	'use strict';

	function getConfig() {
		return window.ghlcsFrontend || {};
	}

	function query(root, selector) {
		try {
			return selector ? root.querySelector(selector) : null;
		} catch (error) {
			return null;
		}
	}

	function matches(element, selector) {
		try {
			return selector && element && element.matches(selector);
		} catch (error) {
			return false;
		}
	}

	function closest(element, selector) {
		try {
			return selector && element ? element.closest(selector) : null;
		} catch (error) {
			return null;
		}
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

	function getExternalResponse(wrapper, submitButton) {
		var response = wrapper.querySelector('.ghlcs-external-response');

		if (response) {
			return response;
		}

		response = document.createElement('div');
		response.className = 'ghlcs-response ghlcs-external-response';
		response.setAttribute('role', 'status');
		response.setAttribute('aria-live', 'polite');

		if (submitButton && submitButton.parentNode) {
			submitButton.parentNode.insertBefore(response, submitButton.nextSibling);
			return response;
		}

		wrapper.appendChild(response);

		return response;
	}

	function setExternalResponse(wrapper, submitButton, message, type) {
		var response = getExternalResponse(wrapper, submitButton);

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

	function setExternalSubmitting(wrapper, button, loadingText, submitting) {
		if (!button) {
			return;
		}

		if (submitting) {
			button.dataset.ghlcsOriginalText = button.textContent;
			button.textContent = loadingText || button.textContent;
			button.disabled = true;
			wrapper.classList.add('ghlcs-is-submitting');
			return;
		}

		button.textContent = button.dataset.ghlcsOriginalText || button.textContent;
		button.disabled = false;
		wrapper.classList.remove('ghlcs-is-submitting');
	}

	function closeExternalPopup(wrapper, formConfig) {
		var closeEvent;
		var closeDelay = 2500;

		if (!formConfig.isPopup) {
			return;
		}

		window.setTimeout(function () {
			try {
				closeEvent = new CustomEvent('ghlcs:external-popup-close', {
					bubbles: true,
					detail: {
						formId: formConfig.id,
						container: formConfig.container
					}
				});
				wrapper.dispatchEvent(closeEvent);
			} catch (error) {}

			wrapper.hidden = true;
			wrapper.style.display = 'none';
			wrapper.classList.remove('is-active', 'active', 'show', 'open');
		}, closeDelay);
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

	function submitRequest(formData) {
		var config = getConfig();

		formData.append('action', config.action || 'ghlcs_submit_form');
		appendTracking(formData);

		return fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json();
		});
	}

	function onSubmit(event) {
		var form = event.target;
		var formData;
		var config = getConfig();

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
		setResponse(form, '', '');
		setSubmitting(form, true);

		submitRequest(formData)
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

	function findExternalMatch(element) {
		var config = getConfig();
		var externalForms = config.externalForms || [];
		var wrapper;
		var i;

		for (i = 0; i < externalForms.length; i++) {
			wrapper = closest(element, externalForms[i].container);

			if (wrapper) {
				return {
					config: externalForms[i],
					wrapper: wrapper
				};
			}
		}

		return null;
	}

	function submitExternal(match) {
		var config = getConfig();
		var formConfig = match.config;
		var wrapper = match.wrapper;
		var button = query(wrapper, formConfig.submit);
		var formData = new FormData();
		var missing = false;

		if (!config.ajaxUrl || !window.fetch) {
			return;
		}

		formData.append('ghlcs_form_id', formConfig.id);
		formData.append('ghlcs_external_nonce', config.externalNonce || '');

		(formConfig.externalFields || []).forEach(function (field) {
			var input = query(wrapper, field.selector);
			var value = input ? input.value : '';

			if (!input) {
				missing = true;
			}

			formData.append(field.key, value);
		});

		if (missing) {
			setExternalResponse(wrapper, button, formConfig.errorMessage || 'Something went wrong. Please try again.', 'error');
			return;
		}

		setExternalResponse(wrapper, button, '', '');
		setExternalSubmitting(wrapper, button, formConfig.loadingText, true);

		submitRequest(formData)
			.then(function (response) {
				var data = response.data || {};

				if (!response.success) {
					throw new Error(data.message || formConfig.errorMessage || 'Something went wrong. Please try again.');
				}

				setExternalResponse(wrapper, button, data.message || '', 'success');

				(wrapper.querySelectorAll('input, textarea, select') || []).forEach(function (input) {
					if ('hidden' !== input.type && 'button' !== input.type && 'submit' !== input.type) {
						input.value = '';
					}
				});

				closeExternalPopup(wrapper, formConfig);

				if ('redirect' === data.success_behavior && data.redirect_url) {
					window.location.href = data.redirect_url;
				}
			})
			.catch(function (error) {
				setExternalResponse(wrapper, button, error.message, 'error');
			})
			.finally(function () {
				setExternalSubmitting(wrapper, button, formConfig.loadingText, false);
			});
	}

	function onExternalSubmit(event) {
		var match = findExternalMatch(event.target);

		if (!match || matches(event.target, '.ghlcs-form')) {
			return;
		}

		event.preventDefault();
		submitExternal(match);
	}

	function onExternalClick(event) {
		var match = findExternalMatch(event.target);
		var button;

		if (!match) {
			return;
		}

		button = closest(event.target, match.config.submit);

		if (!button) {
			return;
		}

		event.preventDefault();
		submitExternal(match);
	}

	document.addEventListener('submit', onSubmit);
	document.addEventListener('submit', onExternalSubmit);
	document.addEventListener('click', onExternalClick);
}());
