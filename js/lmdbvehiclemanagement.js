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

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeVehicleCapacityRows);
	} else {
		initializeVehicleCapacityRows();
	}
})(window, document);
