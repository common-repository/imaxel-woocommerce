<?php

namespace Printspot\ICP\Services;

use Printspot\ICP\Helpers\ArrayHelper;
use Printspot\ICP\Helpers\Config;
use Printspot\ICP\Helpers\RequestHelper;
use Printspot_Management\Services\IcpAdminService;
use Printspot\ICP\Models\IcpProductsProjectsModel;
use ImaxelOperations;
use DateTime;

class CartService {

	/**
	 * addToCart
	 *
	 * @param int $projectID
	 * @param int $productID
	 * @param mixed $dealer
	 * @param mixed $siteOrigin
	 * @param mixed $product_code
	 * @return void
	 */
	public static function addToCart($projectID, $productID, $dealer, $siteOrigin, $product_code = "") {
		$domain = get_site_url();
		$product_id = $productID;
		$configProduct = self::getConfigProduct($domain, $_POST);
		$productID = $configProduct['productId'];
		$variationID = $configProduct['variationId'];
		$variations = array();

		$projectVariation = IcpProductsProjectsModel::origin()->getVariation($projectID);

		$cart_item_data['icp_volumetric_value'] = isset($projectVariation['volumetric_value']) ? $projectVariation['volumetric_value'] : null;
		$cart_item_data['icp_project'] = intval($projectID);
		$cart_item_data['icp_product'] = intval($product_id);
		//TODO: DPI Review in printspot
		$cart_item_data['dealerID'] = intval($dealer);
		$cart_item_data['dealerOriginID'] = intval($siteOrigin);
		if (!empty($product_code)) $cart_item_data['productID'] = $product_code;

		$addItemToCart = WC()->cart->add_to_cart($productID, 1, $variationID, $variations, $cart_item_data);
    }

	/**
	 * Get config product by domain
	 * @param $domain
	 * @param $post
	 * @return mixed|void
	 */
	public static function getConfigProduct($domain, $post) {
		if (!empty($post['wproduct']) && $post['wproduct'] !== 'undefined') {
			$productConfig['productId'] = $post['wproduct'];
			$productConfig['variationId'] = '';
		} else {
			$productConfig = Config::get('productConfig.default');
		}
		$productConfig = apply_filters('get_product_config_domain', $productConfig, $domain);

		return $productConfig;
	}

	/**
	 * updateIcpItemCartQuantity
	 *
	 * update quantity item on cart and set session
	 *
	 * @param int $icpProjectId
	 * @param int $quantity
	 * @return void
	 */
	public static function updateIcpItemCartQuantity($icpProjectId, $quantity) {
		$cartContents = WC()->cart->get_cart_contents();
		$itemCart = ArrayHelper::search($cartContents, 'icp_project', $icpProjectId);
		if (count($itemCart) > 0) {
			$itemCart = $itemCart[0];
			$itemCart['quantity'] = $quantity;
			WC()->cart->cart_contents[$itemCart['key']] = $itemCart;
			WC()->cart->set_session();
		}
	}

	/**
	 * removeDeletedProjectInCartCreative
	 *
	 * check if cart has any creative project deleted, if so, remove it to avoid register an order with it.
	 *
	 * @param array $item
	 * @param string $projectID
	 * @return void
	 */
	public static function removeDeletedProjectInCartCreative($item, $projectID)
	{
		global $wpdb;
		
		if (isset($projectID))
		{
			//TODO-marcs should be $row = ProjectModel::origin()->getProject($projectID);
			$sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
					WHERE id_project=' . $projectID;
			$row = $wpdb->get_row($sql);
			
			$intervalSinceLastUpdate = date_diff( date_create($row->date_project), new DateTime() );
			/* Projects are deleted in Services 3 months after the last update,
			check if it has been more than 3 months since the last update registered in the plugin (not Services) */
			if ( $intervalSinceLastUpdate->days >= 90 )
			{
				$imaxelOperations = new ImaxelOperations();
				$publicKey = get_option("wc_settings_tab_imaxel_publickey");
				$privateKey = get_option("wc_settings_tab_imaxel_privatekey");

				$projectData = json_decode( $imaxelOperations->readProject($publicKey, $privateKey, $projectID) );
				
				// Delete the item if the project is already deleted in Services and return a 404 request
				if ( property_exists($projectData, 'status') && $projectData->status === 404 )
				{
					WC()->cart->remove_cart_item($item);
				}
			}
		}
	}

	/**
	 * removeDeletedProjectInCartIcp
	 *
	 * check if cart has any icp project deleted, if so, remove it to avoid register an order with it.
	 *
	 * @param array $item
	 * @param int $projectID
	 * @return void
	 */
	public static function removeDeletedProjectInCartIcp($item, $projectID)
	{
		global $wpdb;
		
		if (isset($projectID))
		{
			$sql = 'SELECT * FROM ' . $wpdb->prefix . 'icp_products_projects
					WHERE id=' . $projectID;
			$row = $wpdb->get_row($sql);
			
			$intervalSinceLastUpdate = date_diff( date_create($row->date), new DateTime() );
			/* Projects are deleted in Services 3 months after the last update,
			check if it has been more than 3 months since the last update registered in the plugin (not Services) */
			if ( $intervalSinceLastUpdate->days >= 90 )
			{
				$sql = 'SELECT * FROM ' . $wpdb->prefix . 'icp_products_projects_components
				WHERE project=' . $projectID;
				$row = $wpdb->get_row($sql);
				
				if ( property_exists($row, 'value') && $row->value )
				{
					$componentValues = unserialize($row->value);
					foreach ($componentValues as $values => $value)
					{
						if ( array_key_exists("dealer_project", $value) ) $projectComponent = $value["dealer_project"];
					}

					if ($projectComponent)
					{
						$imaxelOperations = new ImaxelOperations();
						$publicKey = get_option("wc_settings_tab_imaxel_publickey");
						$privateKey = get_option("wc_settings_tab_imaxel_privatekey");

						$projectData = json_decode( $imaxelOperations->readProject($publicKey, $privateKey, $projectComponent) );
				
						// Delete the item if the project is already deleted in Services and return a 404 request
						if ( property_exists($projectData, 'status') && $projectData->status === 404 )
						{
							WC()->cart->remove_cart_item($item);
						}
					}
				}
			}
		}
	}
}