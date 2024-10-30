<?php

use Printspot\ICP\View;

/**
 * loadView
 *
 * @param string $plugin
 * @param string $file - path from plugin_name/includes
 * @param array $data - optional array data
 * @return html
 */
function icpLoadView($file, $data = []) {
    $pluginName="imaxel-woocommerce";
    return View::load($pluginName . ':' . $file, $data);
}


/**
 * setImaxelLog
 *
 * Set log file
 *
 * @param string $plugin
 * @param string $file
 * @param string $type
 * @param string $title
 * @param array $data
 * @return void
 */
if (!function_exists('setIcpLog')) {
	function setIcpLog($plugin, $file, $type = 'DEBUG', $title, $data = []) {
        $pluginName="imaxel-woocommerce";

        $pluginLogPath = __DIR__ . '/../themes/' . $pluginName . '/logs/' . $plugin;
		if (!file_exists($pluginLogPath)) {
			mkdir($pluginLogPath, 0777, true);
		}

		$file = __DIR__ . '/../themes/'. $pluginName . '/logs/' . $plugin . '/' . $file . '_' . date('Y-m-d') . '.log';
		file_put_contents(
			$file,
			"\n\n[" . date('d-m-Y H:i:s') . "] - " . $type . " - " . $title . " \n" . json_encode($data),
			FILE_APPEND
		);

	}
}

if (!function_exists('drawPrice')) {
	function drawPrice($price) {
		$currencyPosition = get_option('woocommerce_currency_pos');
		$price = wc_format_decimal($price,2,true);
		switch ($currencyPosition) {
			case 'left':
				$price =  ' ' . get_woocommerce_currency_symbol() . $price;
				break;
			case 'right':
				$price = ' ' . $price . get_woocommerce_currency_symbol();
				break;
			case 'left_space':
				$price = ' ' . get_woocommerce_currency_symbol() . ' ' . $price;
				break;
			case 'right_space':
				$price = ' ' . $price . ' ' . get_woocommerce_currency_symbol();
				break;
			default:
				$price = ' ' . $price;
		}

		return $price;
	}
}

if (!function_exists('renderLoad')) {
	function renderLoad($view, $data = []) {
		return View::renderLoad($view, $data);
	}
}
