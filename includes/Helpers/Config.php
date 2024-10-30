<?php


namespace Printspot\ICP\Helpers;


class Config {
	private static $configValues = [];
	private static $configFile = null;

	public static function get($paramPath) {
		$plugin = 'imaxel-woocommerce';
		$hash = $paramPath . ':' . $plugin;

		if (!isset(self::$configValues[$hash])) {
			$rsConfig = self::getConfigFile($plugin);
			$paramsArray = explode('.', $paramPath);
			foreach ($paramsArray as $key) {
				$rsConfig = $rsConfig[$key];
			}
			self::$configValues[$hash] = $rsConfig;
		}

		return self::$configValues[$hash];
	}

	public static function getConfigFile($plugin) {
		if (self::$configFile == null) {
			self::$configFile = include(WP_PLUGIN_DIR . '/' . $plugin . '/config.php');
		}
		return self::$configFile;
	}


}
