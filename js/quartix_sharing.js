/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
(function () {
	'use strict';
	if (!window.jQuery || !window.jQuery.fn.dialog) return;
	window.jQuery(function ($) {
		var panel = $('#qx-sharing-dialog');
		if (!panel.length) return;
		// formconfirm serializes scalar fields to GET; keep the native Multicompany
		// form intact for its array selection and its protected POST submission.
		panel.removeAttr('hidden').dialog({
			autoOpen: false, modal: true, closeOnEscape: true, resizable: false,
			width: Math.min(1120, window.innerWidth - 32), height: 'auto',
			maxHeight: window.innerHeight - 32,
			close: function (event) {
				var widget = panel.find('#multiselect_shared_lmdbvehiclequartix').data('crlcu.multiselect');
				if (widget) {
					while (widget.undoStack.length) widget.undo(event);
					widget.redoStack = [];
				}
				panel.find('input[name="q"]').val('').trigger('keyup');
			}
		});
		$('#qx-sharing-open').on('click', function (event) {
			if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
			event.preventDefault(); panel.dialog('open');
		});
		$('#qx-sharing-cancel').on('click', function (event) {
			event.preventDefault(); panel.dialog('close');
		});
		$(window).on('resize.qxSharing', function () {
			panel.dialog('option', {width: Math.min(1120, window.innerWidth - 32), maxHeight: window.innerHeight - 32});
		});
		if (panel.attr('data-auto-open') === '1') panel.dialog('open');
	});
}());
