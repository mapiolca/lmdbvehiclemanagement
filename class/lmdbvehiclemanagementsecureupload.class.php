<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Secure storage for the PDF and image evidence files accepted by the module.
 *
 * JPEG and PNG files are decoded and rewritten so useful orientation is applied
 * while EXIF, GPS and embedded thumbnails are discarded.
 */
class LmdbVehicleManagementSecureUpload
{
	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	/**
	 * Inspect one uploaded PDF, JPEG or PNG.
	 *
	 * @param array<string,mixed> $upload One $_FILES entry
	 * @param array<string,string> $errorKeys Error keys indexed by invalid_upload and invalid_mime
	 * @return array{mime:string,extension:string}|null
	 */
	public function inspect($upload, $errorKeys)
	{
		global $conf;

		if (!isset($upload['tmp_name'], $upload['name'], $upload['error']) || (int) $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name'])) {
			$this->setError(isset($errorKeys['invalid_upload']) ? $errorKeys['invalid_upload'] : 'SecureUploadInvalid');
			return null;
		}
		$size = isset($upload['size']) ? (int) $upload['size'] : (int) filesize((string) $upload['tmp_name']);
		$maxFileSize = isset($conf->maxfilesize) ? (int) $conf->maxfilesize : 0;
		if ($size <= 0 || ($maxFileSize > 0 && $size > $maxFileSize)) {
			$this->setError(isset($errorKeys['invalid_upload']) ? $errorKeys['invalid_upload'] : 'SecureUploadInvalid');
			return null;
		}
		$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
		$mime = $finfo !== false ? finfo_file($finfo, (string) $upload['tmp_name']) : false;
		if ($finfo !== false) {
			finfo_close($finfo);
		}
		$allowed = array('application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png');
		if (!is_string($mime) || !isset($allowed[$mime])) {
			$this->setError(isset($errorKeys['invalid_mime']) ? $errorKeys['invalid_mime'] : 'SecureUploadMimeInvalid');
			return null;
		}

		return array('mime' => $mime, 'extension' => $allowed[$mime]);
	}

	/**
	 * Store a previously inspected upload.
	 *
	 * @param array<string,mixed> $upload One $_FILES entry
	 * @param string $destination Verified absolute destination
	 * @param string $mime MIME returned by inspect()
	 * @param array<string,string> $errorKeys Error keys indexed by library, invalid_image and save
	 * @return int<-1,1>
	 */
	public function store($upload, $destination, $mime, $errorKeys)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$virusErrors = dolCheckVirus((string) $upload['tmp_name'], $destination);
		if (!empty($virusErrors)) {
			$this->setError('ErrorFileIsInfectedWithAVirus');
			return -1;
		}
		if ($mime === 'application/pdf') {
			$result = dol_move_uploaded_file((string) $upload['tmp_name'], $destination, 1, 1, (int) $upload['error'], 0);
			if (!is_numeric($result) || (int) $result <= 0) {
				$this->setError(is_string($result) && $result !== '' ? $result : (isset($errorKeys['save']) ? $errorKeys['save'] : 'SecureUploadFailed'));
				return -1;
			}

			return 1;
		}

		return $this->sanitizeImage((string) $upload['tmp_name'], $destination, $mime, $errorKeys);
	}

	/** @param string $error Error key or message @return void */
	private function setError($error)
	{
		$this->error = $error;
		$this->errors[] = $error;
	}

	/**
	 * @param string $source Temporary upload
	 * @param string $destination Destination
	 * @param string $mime MIME type
	 * @param array<string,string> $errorKeys Error keys
	 * @return int<-1,1>
	 */
	private function sanitizeImage($source, $destination, $mime, $errorKeys)
	{
		if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng') || !function_exists('imageflip')) {
			$this->setError(isset($errorKeys['library']) ? $errorKeys['library'] : 'SecureUploadImageLibraryUnavailable');
			return -1;
		}
		$image = $mime === 'image/jpeg' ? @imagecreatefromjpeg($source) : @imagecreatefrompng($source);
		if ($image === false) {
			$this->setError(isset($errorKeys['invalid_image']) ? $errorKeys['invalid_image'] : 'SecureUploadImageInvalid');
			return -1;
		}
		if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
			$exif = @exif_read_data($source);
			$orientation = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
			$angle = in_array($orientation, array(5, 6), true) ? -90 : (in_array($orientation, array(7, 8), true) ? 90 : ($orientation === 3 ? 180 : 0));
			if ($angle !== 0) {
				$rotated = imagerotate($image, $angle, 0);
				if ($rotated === false) {
					imagedestroy($image);
					$this->setError(isset($errorKeys['invalid_image']) ? $errorKeys['invalid_image'] : 'SecureUploadImageInvalid');
					return -1;
				}
				imagedestroy($image);
				$image = $rotated;
			}
			$flipMode = in_array($orientation, array(2, 5, 7), true) ? IMG_FLIP_HORIZONTAL : ($orientation === 4 ? IMG_FLIP_VERTICAL : null);
			if ($flipMode !== null && !imageflip($image, $flipMode)) {
				imagedestroy($image);
				$this->setError(isset($errorKeys['invalid_image']) ? $errorKeys['invalid_image'] : 'SecureUploadImageInvalid');
				return -1;
			}
		}
		if ($mime === 'image/png') {
			imagealphablending($image, false);
			imagesavealpha($image, true);
		}
		$result = $mime === 'image/jpeg' ? imagejpeg($image, $destination, 90) : imagepng($image, $destination, 6);
		imagedestroy($image);
		if (!$result) {
			$this->setError(isset($errorKeys['save']) ? $errorKeys['save'] : 'SecureUploadFailed');
			return -1;
		}

		return 1;
	}
}
