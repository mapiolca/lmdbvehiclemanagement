/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */
/* Native modal iframe; GET reads the cache, only the user-initiated POST can enqueue QWS. */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';
	var form = document.getElementById('qx-route-form');
	if (!form) return;
	var options = JSON.parse(document.getElementById('qx-route-options').textContent);
	var status = document.getElementById('qx-route-status');
	var button = document.getElementById('qx-route-load');
	var container = document.getElementById('qx-route-map');
	var map = null, layers = null, timer = null, busy = false, first = true, signature = '', stopped = false;
	var controller = null;
	// The native hidden input named "action" shadows HTMLFormElement.action.
	var url = new URL(form.getAttribute('action'), window.location.href);
	url.search = new URLSearchParams({day: form.elements.day.value, trip: form.elements.trip.value, format: 'json'}).toString();
	function clearMap() {
		if (map) { map.remove(); map = null; layers = null; }
		signature = ''; container.classList.add('hidden');
	}
	function draw(state) {
		status.textContent = state.message;
		button.disabled = !state.can_request;
		button.classList.toggle('hidden', !state.can_request);
		document.getElementById('qx-route-fetched').textContent = state.fetched_label;
		document.getElementById('qx-route-provisional').classList.toggle('hidden', !state.in_progress);
		if (state.state !== 'ready' || state.points.length < 2) { clearMap(); return; }
		container.classList.remove('hidden');
		if (!map) {
			map = L.map(container);
			// Treat administrator attribution as text, never executable markup.
			var credit = document.createElement('span'); credit.textContent = options.attribution;
			var attribution = credit.innerHTML;
			if (options.tiles === 'https://tile.openstreetmap.org/{z}/{x}/{y}.png') attribution = '<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">' + attribution + '</a>';
			L.tileLayer(options.tiles, {maxZoom: 19, attribution: attribution}).on('tileerror', function () { status.textContent = options.tilesFailure; }).addTo(map);
			layers = L.layerGroup().addTo(map);
		}
		var next = JSON.stringify(state.points) + state.in_progress;
		if (signature !== next) {
			layers.clearLayers();
			var line = L.polyline(state.points).addTo(layers);
			L.marker(state.points[0]).bindTooltip(options.start).addTo(layers);
			if (!state.in_progress) L.marker(state.points[state.points.length - 1]).bindTooltip(options.end).addTo(layers);
			map.invalidateSize(); map.fitBounds(line.getBounds(), {padding: [24, 24], maxZoom: 16}); signature = next;
		}
	}
	async function load(mutate) {
		if (busy || stopped) return;
		busy = true; button.disabled = true; clearTimeout(timer);
		if (mutate) status.textContent = options.loading;
		try {
			controller = new AbortController();
			var init = {credentials: 'same-origin', cache: 'no-store', signal: controller.signal};
			if (mutate) { init.method = 'POST'; init.body = new URLSearchParams(new FormData(form)); }
			var response = await fetch(url.toString(), init);
			var result = await response.json();
			if (stopped) return;
			if (!result.data) { clearMap(); button.classList.add('hidden'); status.textContent = result.error || options.failure; return; }
			draw(result.data);
			if (first && result.data.state === 'missing' && result.data.can_request) {
				first = false; busy = false; return load(true);
			}
			first = false;
			// Cache-only polling also rechecks permissions/privacy while the modal is open.
			timer = setTimeout(function () { load(false); }, 15000);
		} catch (error) {
			if (stopped) return;
			// A failed authorization/network check must not leave an unverified map visible.
			clearMap(); status.textContent = options.failure; button.disabled = false;
		} finally { busy = false; }
	}
	form.addEventListener('submit', function (event) { event.preventDefault(); first = false; load(true); });
	function stop() {
		stopped = true; clearTimeout(timer); if (controller) controller.abort(); clearMap();
	}
	// The native dialog hides its iframe on close. Stop polling immediately;
	// reopening recreates the iframe through the core helper.
	if (window.frameElement && window.parent.jQuery) window.parent.jQuery(window.frameElement.parentElement).one('dialogclose.qxroute', stop);
	window.addEventListener('pagehide', stop);
	window.addEventListener('resize', function () { if (map) map.invalidateSize(); });
	load(false);
});
