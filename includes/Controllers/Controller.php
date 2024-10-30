<?php

namespace Printspot\ICP\Controllers;

class Controller {

	/**
	 * register ajax functions from controllers
	 * @param array $functions
	 * @param bool $isNamespace Control if is namespace or only method
	 */
	protected static function loadAJAX($functions) {
		foreach ($functions as $function) {

			if (strpos($function, '::') === false) {
				$namespace = static::class;
			}else{
				list($namespace, $function) = explode('::', $function);
			}
			$path = $namespace . '::' . $function;

			add_action('wp_ajax_nopriv_' . $function, $path);
			add_action('wp_ajax_' . $function, $path);
		}
	}

	//register ajax functions from controllers
	protected static function responseAjax($success, $message) {
		$return = array(
			'success' => $success,
			'message' => $message
		);
		http_response_code($success ? 200 : 500);

		print_r(json_encode($return));
		die();
	}
}
