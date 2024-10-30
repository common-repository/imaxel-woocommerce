<?php


namespace Printspot\ICP\Services;


class IcpPdfService {
	/**
	 * Exception control for pdf upload
	 * @param $pdfFile
	 * @throws \Exception
	 */
	public static function checkUpload($pdfFile) {
		$errors = [
			UPLOAD_ERR_INI_SIZE => __('The uploaded file exceeds the max size directive', 'imaxel'),
			UPLOAD_ERR_FORM_SIZE => __('The uploaded file exceeds the max size form directive', 'imaxel'),
			UPLOAD_ERR_PARTIAL => __('The uploaded file was only partially uploaded.', 'imaxel'),
			UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'imaxel'),
			UPLOAD_ERR_NO_TMP_DIR => __('Missing a temporary folder.', 'imaxel'),
			UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk', 'imaxel'),
			UPLOAD_ERR_EXTENSION => __('Code extension not suported', 'imaxel'),
			'default' => __('Default error uploading file', 'imaxel'),
		];
		if ($pdfFile['error']) {
			$message = $errors[$pdfFile['error']] ?? $errors['default'];
			throw new \Exception($message);
		}
	}

}
