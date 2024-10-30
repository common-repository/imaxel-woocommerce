<?php

namespace Printspot\ICP\Helpers;


/**
 * ArrayHelper - Global helper functions for Arrays
 *
 */
class RequestHelper {

	public static function protocol() {
		return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
	}
}
