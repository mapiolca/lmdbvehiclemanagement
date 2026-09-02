/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

(function (window, document) {
	'use strict';

	function getEnergySelect() {
		return document.querySelector('select[name="fk_energy"]');
	}

	function updateVehicleCapacityRows() {
		var energySelect = getEnergySelect();
		if (!energySelect) {
			return;
		}

		var selectedEnergy = parseInt(energySelect.value || '0', 10);
		document.querySelectorAll('.lmdb-capacity-row').forEach(function (row) {
			var compatibleEnergyIds = (row.getAttribute('data-energy-ids') || '').split(',').map(Number);
			var visible = selectedEnergy > 0 && compatibleEnergyIds.indexOf(selectedEnergy) !== -1;
			row.style.display = visible ? '' : 'none';

			var input = row.querySelector('input[name^="capacity_"]');
			if (input) {
				input.disabled = !visible;
			}
		});
	}

	function initializeVehicleCapacityRows() {
		if (!getEnergySelect()) {
			return;
		}

		updateVehicleCapacityRows();
		document.addEventListener('change', function (event) {
			if (event.target && event.target.matches('select[name="fk_energy"]')) {
				updateVehicleCapacityRows();
			}
		});

		if (window.jQuery) {
			window.jQuery(document).on('select2:select select2:clear', 'select[name="fk_energy"]', updateVehicleCapacityRows);
		}
	}

	function clearColumnSelectorOverlay(menu) {
		var responsiveWrapper = menu.closest('.div-table-responsive, .div-table-responsive-no-min');
		if (responsiveWrapper) {
			responsiveWrapper.classList.remove('lmdb-column-selector-wrapper-open');
		}
		menu.classList.remove('lmdb-column-selector-overlay');
		menu.style.removeProperty('top');
		menu.style.removeProperty('right');
		menu.style.removeProperty('bottom');
		menu.style.removeProperty('left');
	}

	function positionColumnSelectorOverlay(menu) {
		var dropdown = menu.closest('dl.dropdown');
		var trigger = dropdown ? dropdown.querySelector('dt a.multiselectpicto') : null;
		if (!trigger) {
			return;
		}

		var responsiveWrapper = menu.closest('.div-table-responsive, .div-table-responsive-no-min');
		if (responsiveWrapper && document.body.classList.contains('page-regulatorycontrol-schedule')) {
			responsiveWrapper.classList.add('lmdb-column-selector-wrapper-open');
		}

		menu.classList.add('lmdb-column-selector-overlay');
		menu.style.right = 'auto';
		menu.style.bottom = 'auto';

		var triggerRect = trigger.getBoundingClientRect();
		var menuRect = menu.getBoundingClientRect();
		var viewportPadding = 8;
		var menuTop = triggerRect.bottom + 2;
		var roomBelow = window.innerHeight - menuTop - viewportPadding;
		var roomAbove = triggerRect.top - viewportPadding;

		if (menuRect.height > roomBelow && roomAbove > roomBelow) {
			menuTop = Math.max(viewportPadding, triggerRect.top - menuRect.height - 2);
		} else {
			menuTop = Math.min(menuTop, Math.max(viewportPadding, window.innerHeight - menuRect.height - viewportPadding));
		}

		var menuLeft = menu.classList.contains('selectedfieldsleft')
			? triggerRect.left
			: triggerRect.right - menuRect.width;
		menuLeft = Math.max(viewportPadding, Math.min(menuLeft, window.innerWidth - menuRect.width - viewportPadding));

		menu.style.top = Math.round(menuTop) + 'px';
		menu.style.left = Math.round(menuLeft) + 'px';
	}

	function refreshColumnSelectorOverlays() {
		document.querySelectorAll('.mod-lmdbvehiclemanagement.page-list .dropdown dd ul.selectedfields, .mod-lmdbvehiclemanagement.page-list .dropdown dd ul.selectedfieldsleft').forEach(function (menu) {
			if (menu.classList.contains('open')) {
				positionColumnSelectorOverlay(menu);
			} else if (menu.classList.contains('lmdb-column-selector-overlay')) {
				clearColumnSelectorOverlay(menu);
			}
		});
	}

	function initializeColumnSelectorOverlays() {
		if (!document.querySelector('.mod-lmdbvehiclemanagement.page-list')) {
			return;
		}

		var refreshPending = false;
		var scheduleRefresh = function () {
			if (refreshPending) {
				return;
			}
			refreshPending = true;
			window.requestAnimationFrame(function () {
				refreshPending = false;
				refreshColumnSelectorOverlays();
			});
		};

		// Dolibarr toggles the native "open" class from its delegated click
		// handler. Refresh on the next frame so the menu keeps all native
		// behavior while escaping the responsive table overflow.
		document.addEventListener('click', scheduleRefresh);
		window.addEventListener('resize', scheduleRefresh);
		document.addEventListener('scroll', scheduleRefresh, true);
	}

	function initializeModuleInterface() {
		initializeVehicleCapacityRows();
		initializeColumnSelectorOverlays();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeModuleInterface);
	} else {
		initializeModuleInterface();
	}
})(window, document);
