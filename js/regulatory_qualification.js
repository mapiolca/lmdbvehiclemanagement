/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
(function () {
	'use strict';
	if (!window.jQuery || !window.jQuery.fn.dialog) return;
	window.jQuery(function ($) {
		var panel = $('#regulatory-qualification[data-modal="1"]');
		if (!panel.length) return;
		// Keep the native date fields, profile array and CSRF-protected POST form
		// intact: formconfirm's scalar confirmation fields do not cover this form.
		panel.removeAttr('hidden').dialog({
			autoOpen: false, modal: true, closeOnEscape: true, resizable: false,
			width: Math.min(1200, window.innerWidth - 32), height: 'auto',
			maxHeight: window.innerHeight - 32
		});
		$('#regulatory-qualification-open').on('click', function (event) {
			if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
			event.preventDefault(); panel.dialog('open');
		});
		$('#regulatory-qualification-close').on('click', function (event) {
			event.preventDefault(); panel.dialog('close');
		});
		$(window).on('resize.regulatoryQualification', function () {
			panel.dialog('option', {width: Math.min(1200, window.innerWidth - 32), maxHeight: window.innerHeight - 32});
		});
		if (panel.attr('data-auto-open') === '1') panel.dialog('open');
	});
}());
