/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/* Dolibarr jQuery UI dialog or direct consultation; GET only reads the cache. */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';
	var form = document.getElementById('qx-route-form');
	if (!form) return;
	var options = JSON.parse(document.getElementById('qx-route-options').textContent);
	var status = document.getElementById('qx-route-status');
	var button = document.getElementById('qx-route-load');
	var actions = document.getElementById('qx-route-actions');
	var provisional = document.getElementById('qx-route-provisional');
	var fetched = document.getElementById('qx-route-fetched');
	var container = document.getElementById('qx-route-map');
	var dialog = document.getElementById('qx-route-dialog');
	var map = null, layers = null, timer = null, busy = false, first = true, signature = '', stopped = true;
	var generation = 0, retryRead = false, tileFailed = false;
	var controller = null;
	// The native hidden input named "action" shadows HTMLFormElement.action.
	var url = new URL(form.getAttribute('action'), window.location.href);
	function message(text, level) {
		status.textContent = text;
		status.className = ['info', 'warning', 'error'].indexOf(level) >= 0 ? level : 'info';
		status.classList.toggle('hidden', !text);
	}
	function clearMap() {
		if (map) { map.remove(); map = null; layers = null; }
		signature = ''; tileFailed = false; container.classList.add('hidden');
	}
	function clearContent() {
		clearMap(); fetched.textContent = '';
		provisional.classList.add('hidden'); actions.classList.add('hidden');
		message('', 'info');
	}
	function draw(state) {
		message(state.message, state.message_level);
		retryRead = false;
		button.disabled = !state.can_request;
		actions.classList.toggle('hidden', !state.can_request);
		fetched.textContent = state.fetched_label;
		provisional.classList.toggle('hidden', state.in_progress !== true);
		if (state.state !== 'ready' || state.points.length < 2) { clearMap(); return; }
		container.classList.remove('hidden');
		if (!map) {
			map = L.map(container);
			// Treat administrator attribution as text, never executable markup.
			var credit = document.createElement('span'); credit.textContent = options.attribution;
			var attribution = credit.innerHTML;
			if (options.tiles === 'https://tile.openstreetmap.org/{z}/{x}/{y}.png') attribution = '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">' + attribution + '</a>';
			L.tileLayer(options.tiles, {maxZoom: 19, attribution: attribution}).on('tileerror', function () {
				if (stopped) return;
				tileFailed = true; message(options.tilesFailure, 'warning');
			}).addTo(map);
			layers = L.layerGroup().addTo(map);
		}
		var next = JSON.stringify(state.points) + state.in_progress;
		if (signature !== next) {
			layers.clearLayers();
			var line = L.polyline(state.points).addTo(layers);
			L.marker(state.points[0]).bindTooltip(options.start).addTo(layers);
			if (!state.in_progress) L.marker(state.points[state.points.length - 1]).bindTooltip(options.end).addTo(layers);
			map.invalidateSize(); map.fitBounds(line.getBounds(), {padding: [40, 48], maxZoom: 16}); signature = next;
		}
		if (tileFailed) message(options.tilesFailure, 'warning');
	}
	async function load(mutate) {
		if (busy || stopped) return;
		busy = true; button.disabled = true; clearTimeout(timer);
		var requestGeneration = generation, retrieveMissing = false;
		if (mutate || first) message(options.loading, 'info');
		try {
			controller = new AbortController();
			var init = {credentials: 'same-origin', cache: 'no-store', signal: controller.signal};
			if (mutate) { init.method = 'POST'; init.body = new URLSearchParams(new FormData(form)); }
			var response = await fetch(url.toString(), init);
			var result = await response.json();
			if (stopped || requestGeneration !== generation) return;
			if (!result.data) { clearContent(); message(result.error || options.failure, 'error'); return; }
			draw(result.data);
			retrieveMissing = first && result.data.state === 'missing' && result.data.can_request;
			first = false;
			// Cache-only polling also rechecks permissions/privacy while the modal is open.
			if (!retrieveMissing) timer = setTimeout(function () { load(false); }, 15000);
		} catch (error) {
			if (stopped || requestGeneration !== generation) return;
			// A failed authorization/network check must not leave an unverified map visible.
			clearContent(); message(options.failure, 'error');
			retryRead = true; actions.classList.remove('hidden'); button.disabled = false;
		} finally { if (requestGeneration === generation) busy = false; }
		if (retrieveMissing && !stopped && requestGeneration === generation) load(true);
	}
	form.addEventListener('submit', function (event) { event.preventDefault(); load(!retryRead); });
	function stop() {
		stopped = true; generation++; clearTimeout(timer);
		if (controller) controller.abort();
		controller = null; busy = false; clearContent();
	}
	function start() {
		stopped = false; first = true; retryRead = false;
		actions.classList.add('hidden');
		url.search = new URLSearchParams({day: form.elements.day.value, trip: form.elements.trip.value, format: 'json'}).toString();
		load(false);
	}
	function sizeDialog() {
		window.jQuery(dialog).dialog('option', {
			width: Math.min(1400, window.innerWidth - 32), height: window.innerHeight - 32,
			position: {my: 'center', at: 'center', of: window}
		});
	}
	window.addEventListener('pagehide', stop);
	window.addEventListener('resize', function () {
		if (dialog && !stopped) sizeDialog();
		if (map) map.invalidateSize();
	});
	if (dialog) {
		// Keep the real consultation links usable when Ajax or jQuery UI is unavailable.
		if (!window.jQuery || !window.jQuery.fn.dialog) return;
		window.jQuery(dialog).removeClass('hidden').dialog({
			autoOpen: false, modal: true, closeOnEscape: true, title: options.title,
			close: stop, resizeStop: function () { if (map) map.invalidateSize(); }
		});
		document.querySelectorAll('a.qx-route-open').forEach(function (link) {
			link.addEventListener('click', function (event) {
				if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
				event.preventDefault(); stop();
				var selection = new URL(link.href).searchParams;
				form.elements.day.value = selection.get('day') || '';
				form.elements.trip.value = selection.get('trip') || '';
				sizeDialog(); window.jQuery(dialog).dialog('open'); start();
			});
		});
	} else { start(); }
});
