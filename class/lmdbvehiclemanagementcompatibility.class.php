<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Central compatibility registry.
 *
 * @phpstan-type CompatibilityFeature array{
 *   label:string,
 *   min_dolibarr:string,
 *   min_php:string,
 *   available:bool,
 *   reason:string
 * }
 */
class LmdbVehicleManagementCompatibility
{
	/**
	 * Return the compatibility feature registry.
	 *
	 * @return array<string,CompatibilityFeature>
	 */
	public static function getCompatibilityFeatures()
	{
		$dolibarr20 = version_compare(DOL_VERSION, '20.0.0', '>=');
		$php80 = version_compare(PHP_VERSION, '8.0.0', '>=');

		return array(
			'supplier_invoice_links' => array(
				'label' => 'LmdbSupplierInvoiceLinks', 'min_dolibarr' => '20.0.0', 'min_php' => '8.0.0',
				'available' => $dolibarr20 && $php80 && isModEnabled('supplier_invoice'),
				'reason' => !$dolibarr20 ? 'RequiresDolibarr20' : (!$php80 ? 'RequiresPhp80' : (!isModEnabled('supplier_invoice') ? 'LmdbRequiresSupplierInvoices' : '')),
			),
			'vehicle_dossier' => array(
				'label' => 'LmdbVehicleDossier', 'min_dolibarr' => '20.0.0', 'min_php' => '8.0.0',
				'available' => $dolibarr20 && $php80 && class_exists('ZipArchive') && isModEnabled('supplier_invoice'),
				'reason' => !$dolibarr20 ? 'RequiresDolibarr20' : (!$php80 ? 'RequiresPhp80' : (!class_exists('ZipArchive') ? 'LmdbRequiresZipArchive' : (!isModEnabled('supplier_invoice') ? 'LmdbRequiresSupplierInvoices' : ''))),
			),
			'native_documents' => array(
				'label' => 'FeatureNativeDocuments',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $dolibarr20 && $php80,
				'reason' => !$dolibarr20 ? 'RequiresDolibarr20' : (!$php80 ? 'RequiresPhp80' : ''),
			),
			'native_agenda' => array(
				'label' => 'FeatureNativeAgenda',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $dolibarr20 && $php80 && isModEnabled('agenda'),
				'reason' => !isModEnabled('agenda') ? 'RequiresAgendaModule' : (!$dolibarr20 ? 'RequiresDolibarr20' : (!$php80 ? 'RequiresPhp80' : '')),
			),
			'multicompany_sharing' => array(
				'label' => 'FeatureMulticompanySharing',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $dolibarr20 && $php80 && isModEnabled('multicompany'),
				'reason' => !isModEnabled('multicompany') ? 'RequiresMulticompanyModule' : (!$dolibarr20 ? 'RequiresDolibarr20' : (!$php80 ? 'RequiresPhp80' : '')),
			),
			'resource_link' => array(
				'label' => 'FeatureResourceLink',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $dolibarr20 && $php80 && isModEnabled('resource'),
				'reason' => !isModEnabled('resource') ? 'RequiresResourceModule' : (!$dolibarr20 ? 'RequiresDolibarr20' : (!$php80 ? 'RequiresPhp80' : '')),
			),
			'insurance_reminders' => array(
				'label' => 'FeatureInsuranceReminders',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $dolibarr20 && $php80 && isModEnabled('cron'),
				'reason' => !isModEnabled('cron') ? 'RequiresCronModule' : (!$dolibarr20 ? 'RequiresDolibarr20' : (!$php80 ? 'RequiresPhp80' : '')),
			),
			'insurance_image_sanitization' => array(
				'label' => 'FeatureInsuranceImageSanitization',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $dolibarr20 && $php80 && function_exists('finfo_open') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng') && function_exists('imageflip'),
				'reason' => !function_exists('finfo_open') ? 'RequiresFileinfoExtension' : ((!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng') || !function_exists('imageflip')) ? 'RequiresGdExtension' : (!$dolibarr20 ? 'RequiresDolibarr20' : (!$php80 ? 'RequiresPhp80' : ''))),
			),
			'consumption_various_payment' => array(
				'label' => 'FeatureConsumptionVariousPayment',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $dolibarr20 && $php80 && isModEnabled('bank') && function_exists('finfo_open') && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng') && function_exists('imageflip'),
				'reason' => !isModEnabled('bank') ? 'RequiresBankModule' : (!function_exists('finfo_open') ? 'RequiresFileinfoExtension' : ((!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng') || !function_exists('imageflip')) ? 'RequiresGdExtension' : (!$dolibarr20 ? 'RequiresDolibarr20' : (!$php80 ? 'RequiresPhp80' : '')))),
			),
		);
	}

	/**
	 * Check a feature with the same predicate used by pages and actions.
	 *
	 * @param string $feature Stable feature code
	 * @return bool
	 */
	public static function isFeatureAvailable($feature)
	{
		$features = self::getCompatibilityFeatures();

		return isset($features[$feature]) && $features[$feature]['available'];
	}

	/**
	 * Return unavailable features.
	 *
	 * @return array<string,CompatibilityFeature>
	 */
	public static function getUnavailableFeatures()
	{
		return array_filter(
			self::getCompatibilityFeatures(),
			static function ($feature) {
				return !$feature['available'];
			}
		);
	}
}

