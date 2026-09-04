<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/** Access checks for the protected PDF/ZIP family, including native public previews. */
trait LmdbVehicleDossierHooks
{
	/**
	 * Only generation may replace a protected dossier; ordinary uploads cannot.
	 * @param array<string,mixed> $parameters
	 * @param CommonObject|null $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function moveUploadedFile($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
		if (preg_match('/^lmdb-dossier-[0-9]+\.(pdf|zip)$/i', basename((string) ($parameters['dest_file'] ?? '')))) {
			$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
			// dol_move_uploaded_file ignores negative hook results on v20+.
			accessforbidden($langs->trans('LmdbDossierKeepFilename'));
		}
		return 0;
	}

	/** Keep the protected dossier identity when using the native document manager.
	 * @param array<string,mixed> $parameters
	 * @param CommonObject $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function renameUploadedFile($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
		foreach (array('filenamefrom', 'filenameto') as $key) {
			if (preg_match('/^lmdb-dossier-[0-9]+\.(pdf|zip)$/i', (string) ($parameters[$key] ?? ''))) {
				$langs->load('lmdbvehiclemanagement@lmdbvehiclemanagement');
				setEventMessages($langs->trans('LmdbDossierKeepFilename'), null, 'errors');
				return -1;
			}
		}
		return 0;
	}

	/**
	 * @param array<string,mixed> $parameters
	 * @param CommonObject|null $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	public function checkSecureAccess($parameters, &$object, &$action, $hookmanager)
	{
		$path = str_replace('\\', '/', (string) ($parameters['original_file'] ?? ''));
		if (!preg_match('~(?:^|/)lmdb-dossier-([0-9]+)(?:[./-]|$)~i', $path, $match)) return 0;
		$actor = $parameters['fuser'] ?? null;
		// The core ignores accessallowed=0 from this hook: denial must stop output.
		if (!is_object($actor) || empty($actor->id) || !empty($actor->socid) || !$actor->hasRight('lmdbvehiclemanagement', 'read') || !$actor->hasRight('fournisseur', 'facture', 'lire') || GETPOST('hashp', 'aZ09') !== '') httponly_accessforbidden('', 403);
		require_once __DIR__.'/lmdbvehicle.class.php';
		$vehicle = new LmdbVehicle($this->db);
		if ($vehicle->fetch((int) $match[1]) <= 0 || (int) $vehicle->entity !== (int) ($parameters['entity'] ?? 0)) httponly_accessforbidden('', 403);
		$directory = getMultidirOutput($vehicle, 'lmdbvehiclemanagement', 1);
		if (!is_string($directory) || strpos($path, rtrim(str_replace('\\', '/', $directory), '/').'/') !== 0) httponly_accessforbidden('', 403);
		return 0;
	}

	/**
	 * document.php initializes this context even without a logged-in session.
	 * @param array<string,mixed> $parameters @param object $object @param string $action @param HookManager $hookmanager @return int
	 */
	public function downloadDocument($parameters, &$object, &$action, $hookmanager)
	{
		global $user;
		$path = str_replace('\\', '/', (string) ($parameters['fullpath_original_file'] ?? ''));
		if (!preg_match('~(?:^|/)lmdb-dossier-([0-9]+)(?:[./-]|$)~i', $path, $match)) return 0;
		header('Cache-Control: private, no-store');
		header('Pragma: no-cache');
		header('Expires: 0');
		if (empty($user->id) || !empty($user->socid) || !$user->hasRight('lmdbvehiclemanagement', 'read') || !$user->hasRight('fournisseur', 'facture', 'lire') || GETPOST('hashp', 'aZ09') !== '') httponly_accessforbidden('', 403);
		require_once __DIR__.'/lmdbvehicle.class.php';
		$vehicle = new LmdbVehicle($this->db);
		if ($vehicle->fetch((int) $match[1]) <= 0) httponly_accessforbidden('', 403);
		$directory = getMultidirOutput($vehicle, 'lmdbvehiclemanagement', 1);
		if (!is_string($directory) || strpos($path, rtrim(str_replace('\\', '/', $directory), '/').'/') !== 0) httponly_accessforbidden('', 403);
		return 0;
	}

	/**
	 * Public viewimage.php does not initialize checkSecureAccess hooks. Its native
	 * top_httphead() always calls the report-only CSP hook (v20+), before any bytes.
	 * This narrowly scoped guard does not change either Content Security Policy.
	 * @param array<string,mixed> $parameters @param object|null $object @param string $action @param HookManager $hookmanager @return int
	 */
	public function setContentSecurityPolicy($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $fullpath_original_file;
		$this->resprints = '';
		if (!in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), array('viewimage.php', 'document.php'), true)) return 0;
		$path = str_replace('\\', '/', (string) $fullpath_original_file);
		if (!preg_match('~(?:^|/)lmdb-dossier-([0-9]+)(?:[./-]|$)~i', $path, $match)) return 0;
		header('Cache-Control: private, no-store');
		header('Pragma: no-cache');
		header('Expires: 0');
		if (empty($user->id) || !empty($user->socid) || !$user->hasRight('lmdbvehiclemanagement', 'read') || !$user->hasRight('fournisseur', 'facture', 'lire') || GETPOST('hashp', 'aZ09') !== '') {
			// Do not call top_httphead()/accessforbidden() recursively from its hook.
			http_response_code(403); header('Content-Length: 0'); exit;
		}
		require_once __DIR__.'/lmdbvehicle.class.php';
		$vehicle = new LmdbVehicle($this->db);
		if ($vehicle->fetch((int) $match[1]) <= 0) { http_response_code(403); header('Content-Length: 0'); exit; }
		$directory = getMultidirOutput($vehicle, 'lmdbvehiclemanagement', 1);
		if (!is_string($directory) || strpos($path, rtrim(str_replace('\\', '/', $directory), '/').'/') !== 0) { http_response_code(403); header('Content-Length: 0'); exit; }
		return 0;
	}
}
