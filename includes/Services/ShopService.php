<?php


namespace Printspot\ICP\Services;


class ShopService {

	private static $primaryColor = null;
	private static $secondaryColor = null;

	/**
	 * Get shop color from shop config
	 * @return false|mixed|void
	 */
	public static function getPrimaryColor() {
		if (!isset(self::$primaryColor) || self::$primaryColor == null){
			self::$primaryColor = get_option('shop_primary_color') ?: get_option('wc_settings_tab_imaxel_icp_color_theme');
		}
		return self::$primaryColor;
	}

	/**
	 * Get shop color from shop config
	 * @return false|mixed|void
	 */
	public static function getSecondaryColor() {
		if (!isset(self::$secondaryColor) || self::$secondaryColor == null){
			self::$secondaryColor = get_option('shop_secondary_color') ?:  '#FF6961';
		}
		return self::$secondaryColor;
	}
}
