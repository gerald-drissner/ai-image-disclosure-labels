(function ($) {
	'use strict';

	var fetchedModels = [];
	var activeModelIndex = -1;

	function config() {
		return window.gdaiidlAiAdmin || {};
	}

	function currentProvider() {
		return $('#gdaiidl-ai-provider').val() || '';
	}

	function refreshModelFieldCopy(provider) {
		var i18n = config().i18n || {};
		var isWordPressClient = provider === 'wordpress_ai_client';
		$('#gdaiidl-ai-model-label').text(isWordPressClient ? (i18n.wpModelLabel || 'Model preference (optional)') : (i18n.manualModelLabel || 'Model / policy ID (manual entry)'));
		$('#gdaiidl-ai-model-help').text(isWordPressClient ? (i18n.wpModelHelp || 'Leave blank to let WordPress select any compatible configured model. Or fetch compatible models below and choose one as a preference; WordPress may fall back if needed.') : (i18n.manualModelHelp || 'You can type or paste an exact model/policy ID here. Or fetch the current catalogue below and choose a model there; the selected catalogue ID is copied into this field.'));
	}

	function refreshProviderFields() {
		var provider = currentProvider();
		$('.gdaiidl-ai-provider-fields').each(function () {
			var values = String($(this).data('provider') || '').split(',');
			$(this).toggle(values.indexOf(provider) !== -1);
		});
		$('#gdaiidl-ai-auth-section').toggle(['gd_cloudflare_connector', 'wordpress_ai_client'].indexOf(provider) === -1);
		refreshModelFieldCopy(provider);
		resetFetchedModelUi();
	}

	function refreshPricingFields() {
		var mode = $('select[name="gdaiidl_ai_analysis_settings[pricing_mode]"]').val() || 'auto';
		$('.gdaiidl-ai-pricing-manual').hide().filter('[data-pricing="' + mode + '"]').show();
	}

	function ajax(action, payload) {
		payload = payload || {};
		payload.action = action;
		payload.nonce = config().nonce;
		return $.ajax({
			url: config().ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: payload
		});
	}

	function modelInput() {
		return $('#gdaiidl-ai-model');
	}

	function modelFilter() {
		return $('#gdaiidl-ai-model-filter');
	}

	function modelResults() {
		return $('#gdaiidl-ai-model-results');
	}

	function setModelStatus(message, state) {
		var $status = $('#gdaiidl-ai-model-status');
		message = String(message || '');
		state = state || 'info';
		$status.removeClass('is-loading is-success is-error is-info');
		if (!message) {
			$status.text('').prop('hidden', true);
			return;
		}
		$status.addClass('is-' + state).text(message).prop('hidden', false);
	}

	function setModelValue(value) {
		value = value || '';
		modelInput().val(value);
	}

	function closeModelResults() {
		activeModelIndex = -1;
		modelFilter().attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
		modelResults().empty().prop('hidden', true);
	}

	function resetFetchedModelUi() {
		fetchedModels = [];
		modelFilter().val('').prop('disabled', true).attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
		closeModelResults();
		setModelStatus('', 'info');
	}

	function sortModelsAlphabetically(models) {
		return (models || []).slice().sort(function (a, b) {
			var aId = String((a && a.id) || '').toLowerCase();
			var bId = String((b && b.id) || '').toLowerCase();
			if (aId < bId) {
				return -1;
			}
			if (aId > bId) {
				return 1;
			}
			return 0;
		});
	}

	function filteredModels() {
		var filterValue = String(modelFilter().val() || '').toLowerCase().trim();
		if (!filterValue) {
			return fetchedModels.slice();
		}
		return fetchedModels.filter(function (item) {
			var id = String((item && item.id) || '').toLowerCase();
			var label = String((item && item.label) || '').toLowerCase();
			return id.indexOf(filterValue) !== -1 || label.indexOf(filterValue) !== -1;
		});
	}

	function setActiveModelIndex(index) {
		var $options = modelResults().find('.gdaiidl-ai-model-result');
		if (!$options.length) {
			activeModelIndex = -1;
			modelFilter().removeAttr('aria-activedescendant');
			return;
		}
		index = Math.max(0, Math.min(index, $options.length - 1));
		activeModelIndex = index;
		$options.removeClass('is-active').attr('aria-selected', 'false');
		var $active = $options.eq(index).addClass('is-active').attr('aria-selected', 'true');
		modelFilter().attr('aria-activedescendant', $active.attr('id'));
		var node = $active.get(0);
		if (node && typeof node.scrollIntoView === 'function') {
			node.scrollIntoView({ block: 'nearest' });
		}
	}

	function openModelResults() {
		if (!fetchedModels.length || modelFilter().prop('disabled')) {
			return { filteredCount: 0, totalCount: fetchedModels.length, containsCurrent: false };
		}
		var currentValue = String(modelInput().val() || '');
		var models = filteredModels();
		var $results = modelResults().empty();
		activeModelIndex = -1;

		if (!models.length) {
			$('<div>').addClass('gdaiidl-ai-model-no-results').text('No fetched models match this search.').appendTo($results);
		} else {
			models.slice(0, 100).forEach(function (item, index) {
				var id = item && item.id ? String(item.id) : '';
				if (!id) {
					return;
				}
				var label = item && item.label ? String(item.label) : id;
				var text = label === id ? id : id + ' — ' + label;
				$('<button>')
					.attr({ type: 'button', role: 'option', id: 'gdaiidl-ai-model-option-' + index, 'aria-selected': 'false' })
					.addClass('gdaiidl-ai-model-result')
					.attr('data-model-id', id)
					.text(text)
					.appendTo($results);
			});
			if (models.length > 100) {
				$('<div>').addClass('gdaiidl-ai-model-more-results').text('Showing the first 100 matches. Type more characters to narrow the list.').appendTo($results);
			}
		}

		$results.prop('hidden', false);
		modelFilter().attr('aria-expanded', 'true');
		return {
			filteredCount: models.length,
			totalCount: fetchedModels.length,
			containsCurrent: fetchedModels.some(function (item) {
				return String((item && item.id) || '') === currentValue;
			})
		};
	}

	function chooseModelFromResults(value) {
		if (!value) {
			return;
		}
		setModelValue(value);
		modelFilter().val(value);
		closeModelResults();
		modelFilter().focus();
	}

	function fillModels(models) {
		fetchedModels = sortModelsAlphabetically(models);
		modelFilter().prop('disabled', !fetchedModels.length).val('');
		closeModelResults();
		return {
			filteredCount: fetchedModels.length,
			totalCount: fetchedModels.length,
			containsCurrent: fetchedModels.some(function (item) {
				return String((item && item.id) || '') === String(modelInput().val() || '');
			})
		};
	}

	function resetModelSelection() {
		setModelValue( '' );
		modelFilter().val('');
		closeModelResults();

		if ( fetchedModels.length ) {
			modelFilter().prop('disabled', false);
			setModelStatus( 'Model selection cleared. The fetched catalogue is still available; search or choose another model without fetching again.', 'info' );
		} else {
			setModelStatus( 'Model selection cleared. You can enter a model manually or fetch the current catalogue.', 'info' );
		}

		modelInput().focus();
	}

	function fetchModels() {
		setModelStatus((config().i18n || {}).fetching || 'Fetching current models…', 'loading');
		$('#gdaiidl-ai-fetch-models').prop('disabled', true);

		ajax('gdaiidl_ai_fetch_models', {
			provider: currentProvider(),
			cloudflare_account_id: $('input[name="gdaiidl_ai_analysis_settings[cloudflare_account_id]"]').val() || '',
			custom_endpoint: $('input[name="gdaiidl_ai_analysis_settings[custom_endpoint]"]').val() || '',
			compatible_models_endpoint: $('input[name="gdaiidl_ai_analysis_settings[compatible_models_endpoint]"]').val() || ''
		}).done(function (response) {
			if (response && response.success && response.data) {
				var models = response.data.models || [];
				var info = fillModels(models);
				if (models.length) {
					var message = info.totalCount + ' ' + ((config().i18n || {}).loadedAt || 'model(s) loaded');
					if (response.data.checked_at) {
						message += ' · ' + response.data.checked_at;
					}
					if (String(modelInput().val() || '') && !info.containsCurrent) {
						message += ' · The current model field is not in this live catalogue.';
					}
					message += ' · The fetched catalogue is sorted alphabetically.';
					message += ' · ' + ((config().i18n || {}).selectedHint || 'Choose a model returned by the live catalogue below, then test it. The plugin does not guess or substitute model IDs.');
					setModelStatus(message, 'success');
					return;
				}
				setModelStatus((config().i18n || {}).noModels || 'No models returned.', 'info');
				return;
			}
			setModelStatus(response && response.data && response.data.message ? response.data.message : ((config().i18n || {}).requestFailed || 'Request failed.'), 'error');
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : ((config().i18n || {}).requestFailed || 'Request failed.');
			if (currentProvider() !== 'gd_cloudflare_connector') {
				message += ' ' + ((config().i18n || {}).saveKey || '');
			}
			setModelStatus(message, 'error');
		}).always(function () {
			$('#gdaiidl-ai-fetch-models').prop('disabled', false);
		});
	}

	function testModel() {
		var $button = $('#gdaiidl-ai-test-model');
		setModelStatus((config().i18n || {}).testing || 'Testing the selected model…', 'loading');
		$button.prop('disabled', true);

		ajax('gdaiidl_ai_test_model', {
			provider: currentProvider(),
			model: $('#gdaiidl-ai-model').val() || '',
			cloudflare_account_id: $('input[name="gdaiidl_ai_analysis_settings[cloudflare_account_id]"]').val() || '',
			custom_endpoint: $('input[name="gdaiidl_ai_analysis_settings[custom_endpoint]"]').val() || '',
			compatible_endpoint: $('input[name="gdaiidl_ai_analysis_settings[compatible_endpoint]"]').val() || '',
			compatible_models_endpoint: $('input[name="gdaiidl_ai_analysis_settings[compatible_models_endpoint]"]').val() || ''
		}).done(function (response) {
			if (response && response.success && response.data) {
				var message = response.data.message || ((config().i18n || {}).testOk || 'Model test succeeded.');
				if (response.data.resolved_model) {
					message += ' · ' + response.data.resolved_model;
				}
				setModelStatus(message, 'success');
				return;
			}
			setModelStatus(response && response.data && response.data.message ? response.data.message : ((config().i18n || {}).requestFailed || 'Request failed.'), 'error');
		}).fail(function (xhr) {
			var data = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
			var message = data.message || ((config().i18n || {}).requestFailed || 'Request failed.');
			if (currentProvider() !== 'gd_cloudflare_connector') {
				message += ' ' + ((config().i18n || {}).saveKey || '');
			}
			setModelStatus(message, 'error');
		}).always(function () {
			$button.prop('disabled', false);
		});
	}

	function money(value) {
		if (value === null || value === undefined || isNaN(Number(value))) {
			return '';
		}
		return '$' + Number(value).toFixed(6).replace(/0+$/, '').replace(/\.$/, '');
	}

	function prepareAndStart(scope, $button) {
		$button.prop('disabled', true);
		ajax('gdaiidl_ai_prepare_job', {scope: scope}).done(function (response) {
			if (!response || !response.success || !response.data) {
				window.alert(response && response.data && response.data.message ? response.data.message : ((config().i18n || {}).requestFailed || 'Request failed.'));
				return;
			}
			var d = response.data;
			var estimate = d.estimate && d.estimate.known ? money(d.estimate.usd) : ((config().i18n || {}).unknownCost || 'Cost estimate unavailable.');
			var message = ((config().i18n || {}).confirmCost || 'This operation may incur API charges.') + '\n\n' +
				'Images in scope: ' + d.count + '\n' +
				'Images this job will process: ' + d.eligible + '\n' +
				'Estimated cost: ' + estimate + '\n\n' +
				'Continue?';
			if (!window.confirm(message)) {
				return;
			}
			ajax('gdaiidl_ai_start_job', {scope: scope}).done(function (startResponse) {
				if (startResponse && startResponse.success && startResponse.data) {
					$('#gdaiidl-ai-job-status').html(startResponse.data.html || '');
					pollJob(startResponse.data.job_id);
				} else {
					window.alert(startResponse && startResponse.data && startResponse.data.message ? startResponse.data.message : ((config().i18n || {}).requestFailed || 'Request failed.'));
				}
			});
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : ((config().i18n || {}).requestFailed || 'Request failed.');
			window.alert(message);
		}).always(function () {
			$button.prop('disabled', false);
		});
	}

	var pollTimer = null;
	function pollJob(jobId) {
		if (!jobId) {
			return;
		}
		if (pollTimer) {
			window.clearTimeout(pollTimer);
		}
		ajax('gdaiidl_ai_job_status', {job_id: jobId}).done(function (response) {
			if (!response || !response.success || !response.data) {
				return;
			}
			$('#gdaiidl-ai-job-status').html(response.data.html || '');
			var status = response.data.job && response.data.job.status ? response.data.job.status : '';
			if (status === 'queued' || status === 'running') {
				pollTimer = window.setTimeout(function () { pollJob(jobId); }, 5000);
			}
		});
	}

	function activeBulkAction($form) {
		var top = $form.find('select[name="action"]').val();
		var bottom = $form.find('select[name="action2"]').val();
		return top && top !== '-1' ? top : bottom;
	}

	$(function () {
		refreshProviderFields();
		refreshPricingFields();
		$('#gdaiidl-ai-provider').on('change', refreshProviderFields);
		$('select[name="gdaiidl_ai_analysis_settings[pricing_mode]"]').on('change', refreshPricingFields);
		$('#gdaiidl-ai-fetch-models').on('click', fetchModels);
		$('#gdaiidl-ai-test-model').on('click', testModel);
		$('#gdaiidl-ai-reset-model').on('click', resetModelSelection);
		$('#gdaiidl-ai-model-filter').on('focus input', function () {
			var info = openModelResults();
			if (fetchedModels.length && $(this).val()) {
				setModelStatus(info.filteredCount + ' of ' + info.totalCount + ' fetched models match the current search.', 'info');
			}
		}).on('keydown', function (event) {
			var $options = modelResults().find('.gdaiidl-ai-model-result');
			if (event.key === 'ArrowDown') {
				event.preventDefault();
				if (modelResults().prop('hidden')) { openModelResults(); }
				setActiveModelIndex(activeModelIndex < 0 ? 0 : activeModelIndex + 1);
			} else if (event.key === 'ArrowUp') {
				event.preventDefault();
				if (modelResults().prop('hidden')) { openModelResults(); }
				setActiveModelIndex(activeModelIndex < 0 ? Math.max(0, $options.length - 1) : activeModelIndex - 1);
			} else if (event.key === 'Enter' && activeModelIndex >= 0) {
				event.preventDefault();
				chooseModelFromResults(String($options.eq(activeModelIndex).data('model-id') || ''));
			} else if (event.key === 'Escape') {
				event.preventDefault();
				closeModelResults();
			}
		});

		$(document).on('mousedown', '.gdaiidl-ai-model-result', function (event) {
			event.preventDefault();
			chooseModelFromResults(String($(this).data('model-id') || ''));
		});

		$(document).on('mousedown', function (event) {
			if (!$(event.target).closest('.gdaiidl-ai-catalogue-combobox').length) {
				closeModelResults();
			}
		});

		$(document).on('click', '.gdaiidl-ai-start-job', function () {
			prepareAndStart(String($(this).data('scope') || ''), $(this));
		});

		$(document).on('click', '.gdaiidl-ai-cancel-job', function () {
			var id = String($(this).data('job-id') || '');
			if (!id) {
				return;
			}
			ajax('gdaiidl_ai_cancel_job', {job_id: id}).done(function (response) {
				if (response && response.success && response.data) {
					$('#gdaiidl-ai-job-status').html(response.data.html || '');
				}
			});
		});

		var initialJob = $('#gdaiidl-ai-job-status .gdaiidl-ai-job').data('job-id');
		if (initialJob) {
			pollJob(String(initialJob));
		}

		$('#posts-filter').on('submit.gdaiidlAnalysis', function (event) {
			var action = activeBulkAction($(this));
			if (action !== 'gdaiidl_analyze_selected' && action !== 'gdaiidl_reanalyze_selected') {
				return;
			}
			var selected = $(this).find('tbody th.check-column input[type="checkbox"]:checked').length;
			if (selected && !window.confirm(((config().i18n || {}).confirmCost || 'This operation may incur API charges.') + '\n\nSelected images: ' + selected + '\n\nContinue?')) {
				event.preventDefault();
			}
		});
	});
})(jQuery);
