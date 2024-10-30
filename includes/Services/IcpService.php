<?php

namespace Printspot\ICP\Services;

use DateTime;

use Printspot\ICP\Helpers\Config;
use Printspot\ICP\Services\CartService;
use Printspot\ICP\Helpers\RequestHelper;
use Printspot\ICP\Models\IcpProductsProjectsComponentsModel;
use Printspot\ICP\Models\IcpProductsProjectsModel;
use Printspot_Woocommerce\Cache;
use Printspot_Woocommerce\Exceptions\ImaxelArrayException;
use Printspot_Woocommerce\Exceptions\ImaxelDatabaseException;
use Printspot_Woocommerce\Exceptions\ImaxelDBEmtpyResultsExeption;
use Printspot_Woocommerce\Models\IcpProductsAttributesValuesModel;
use stdClass;

class IcpService {

	/**
	 * getDealerCredentials
	 *
	 * @param int $siteOrigin
	 * @param int $dealerId
	 * @return stdClass
	 */
	public static function getDealerCredentials($siteOrigin, $dealerId) {
        $resultObject = new stdClass();
        $keys=array();
        if(is_plugin_active('imaxel-printspot/imaxel-printspot.php')) {
            $keys = apply_filters('get_dealer_credentials', $keys, $siteOrigin, $dealerId);
            $resultObject->privateKey = $keys["private"];
            $resultObject->publicKey = $keys["public"];
        }
        else{
            $resultObject->privateKey = get_option("wc_settings_tab_imaxel_privatekey");
            $resultObject->publicKey = get_option("wc_settings_tab_imaxel_publickey");
        }
		return $resultObject;
	}


	/**
	 * icpEditProject
	 *
	 * Get services url for redirect
	 *
	 * @param mixed $publicKey
	 * @param mixed $privateKey
	 * @param mixed $projectID
	 * @param mixed $urlCart
	 * @param mixed $urlCartParameters
	 * @param mixed $urlCancel
	 * @param mixed $endpoint
	 * @return string url
	 */
	public static function icpEditProject($publicKey, $privateKey, $projectID, $urlCart, $urlCartParameters, $urlCancel, $saveProjectParams, $colorParams, $endpoint = "https://services.imaxel.com/api/v3/") {

        $datetime = new DateTime("" . date('y-m-d H:i:s.u'));
		date_add($datetime, date_interval_create_from_date_string('10 minutes'));

		$urlCart .= urlencode($urlCartParameters . "&attribute_proyecto=" . $projectID);
		// $urlSave .=urlencode($urlSaveParameters);

		$urlCancel = urlencode($urlCancel);
		$policy = '{
            "projectId": "' . $projectID . '",
            "backURL": "' . $urlCancel . '",
            "addToCartURL": "' . $urlCart . '",
            "publicKey": "' . $publicKey . '",
            "expirationDate": "' . $datetime->format('c') . '"
		}';

		// set url with optinal parameters
		$projectParams = $saveProjectParams ? '&' . $saveProjectParams : null;

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $privateKey, true));
		$url = $endpoint . 'projects/' . (int)$projectID . '/editUrl?backURL=' . $urlCancel . '&addToCartURL=' . $urlCart . '&policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . $projectParams . $colorParams . '&redirect=1';
		return $url;
	}

	/**
	 * setColorToServicesParameter
	 *
	 * define color template to service api
	 *
	 * @param object $profileData
	 * @return string $servicesColors - parameter for primary color on services
	 */
	public static function setColorToServicesParameter($profileData) {
		$servicesColors = '';

		if ($profileData->enable_editors_customization == 'on') {
			$editorUseContrastColor = get_option('printspot_editor_use_contrast_color');
			$editorUseTopbarFontColor = get_option('printspot_editor_use_top_bar_font_color');
			$primaryColor = ShopService::getPrimaryColor();
			if (isset($editorUseContrastColor) && $editorUseContrastColor) $servicesColors .= $primaryColor ? '&contrastColor=' . urlencode($primaryColor) : '';

			if (isset($editorUseTopbarFontColor) && $editorUseTopbarFontColor) $servicesColors .= get_option('printspot_topbar_font_color') ? '&contrastFontColor=' . urlencode(get_option('printspot_topbar_font_color')) : '';
		}

		return $servicesColors;
	}

	/**
	 * setSaveProjectToServicesParameter
	 *
	 * define save functionality parameter to service api
	 *
	 * @param object $profileData User logged profile
	 * @param int $projectId
	 * @param string $urlCart url to add cart
	 * @param string $urlCartParameters parameters url
	 * @return string $saveProjectBehaviour parameters to add to url services request
	 */
	public static function setSaveProjectToServicesParameter($profileData, $projectId, $urlCart, $urlCartParameters) {

		$saveProjectButton = empty($profileData->save_project_button) ? '0' : '1';
		$saveProjectBehaviour = null;
		if ($saveProjectButton == '1') {
			if (is_user_logged_in()) {
				$saveProjectBehaviour = 'saveProjectBehaviour=allow';
			} else {
				$saveProjectBehaviour = 'saveProjectBehaviour=anonymous_user&userSignInUrl=' . $urlCart . urlencode($urlCartParameters . "&attribute_proyecto=" . $projectId) . urlencode('&unregisted_user_redirect_page=' . explode('/', get_permalink(get_option('woocommerce_myaccount_page_id')))[count(explode('/', get_permalink(get_option('woocommerce_myaccount_page_id')))) - 2]);
			}
		} else {
			$saveProjectBehaviour = 'saveProjectBehaviour=hide';
		}

		return $saveProjectBehaviour;
	}


	/**
	 * updateIcpCartQty
	 *
	 * Is executed after cart is updated
	 * - update icp project on db
	 *
	 * @param bool $updated
	 * @return bool $updated
	 */
	public static function updateIcpCartQtyFromCart($updated) {

		global $wpdb;
		$cart = WC()->cart;
		$cartItems = $cart->get_cart_contents();
		$icpItems = array_filter($cartItems, function ($item) {
			return isset($item['icp_project']);
		});

		$errors = [];
		foreach ($icpItems as $icpKey => $icp) {
			$projectId = $icp['icp_project'];
			$projectData = IcpProductsProjectsModel::origin()->getById($projectId);
			$variation = unserialize($projectData->variation_price);
			$variation['qty'] = $icp['quantity'];

			IcpProductsProjectsModel::origin()->update([
				'quantity' => $icp['quantity'],
				'variation_price' => serialize($variation)
			], ['id' => $projectId]);
			$cart->set_quantity($icpKey, $icp['quantity']);
			if ($wpdb->last_error != null) $errors[] = $wpdb->last_error;
		}

		if (count($errors) > 0) {
			$updated = false;
		}

		return $updated;
	}

	/**
	 * updateIcpProject
	 *
	 * @param int $projectId
	 * @param serialized array $newVariation
	 * @param serialized array $newQuantity
	 * @return int $activeProjectId
	 */
	public static function updateIcpVariationProject($projectId, $newVariation, $newQuantity = null) {

		$projectData = IcpProductsProjectsModel::origin()->getById($projectId);

		$currentVariation = unserialize($projectData->variation_price);

		$newVariation = unserialize($newVariation);
		foreach ($newVariation as $key => $variation) {
			//add variation or replace
			$currentVariation[$key] = $variation;
		}

		$newData = [
			'variation_price' => serialize($currentVariation)
		];
		if ($newQuantity) $newData['quantity'] = $newQuantity;
		IcpProductsProjectsModel::origin()->update($newData, ['id' => $projectId]);

		return $projectId;
	}

	/**
	 * updateIcpFromBlock
	 *
	 * Update printspot db from edit icp block.
	 *
	 * @param int $activeProject
	 * @param int $quantity
	 * @param serialize array $newVariation
	 * @return int
	 */
	public static function updateIcpFromBlock($activeProject, $quantity, $newVariation, $blockValues, $blockReadableValues) {
		//update icp project
		$activeProjectId = self::updateIcpVariationProject($activeProject, $newVariation, $quantity);

		CartService::updateIcpItemCartQuantity($activeProject, $quantity);

		//update icp components
		IcpService::updateProjectComponentValues($activeProject, $blockValues, $blockReadableValues);

		return $activeProjectId;
	}

	/**
	 * updateIcpProjectDb
	 *
	 * Update ICP project and components on pintspot db from cart
	 *
	 * @param mixed $variationId
	 * @param mixed $price
	 * @param mixed $productionTime
	 * @param mixed $quantity
	 * @param mixed $activeProject
	 * @param mixed $blockValues
	 * @param mixed $blockReadableValues
	 * @return array
	 */
	public static function updateIcpProjectDb($variationId, $price, $productionTime, $quantity, $activeProject, $blockValues, $blockReadableValues) {
		//update project variation
		$updateProject = IcpProductsProjectsModel::origin()->update([
			'variation' => $variationId,
			'variation_price' => $price,
			'production_time' => $productionTime,
			'quantity' => $quantity,
		], ['id' => $activeProject]);


		//update project components
		$updateProjectComponents = IcpProductsProjectsComponentsModel::origin()->update([
			'value' => $blockValues,
			'readable_value' => $blockReadableValues
		], ['project' => $activeProject]);

		return [
			'project' => $updateProject,
			'components' => $updateProjectComponents
		];
	}

	/**
	 * createIcpProjectDb
	 *
	 * Create Icp project and componentes on printspot db
	 *
	 * @param mixed $productId
	 * @param mixed $productName
	 * @param mixed $variationId
	 * @param mixed $price
	 * @param mixed $siteId
	 * @param mixed $blockId
	 * @param mixed $blockValues
	 * @param mixed $blockReadableValues
	 * @param mixed $dealerId
	 * @param mixed $productionTime
	 * @param mixed $quantity
	 * @return array
	 */
	public static function createIcpProjectDb($productId, $productName, $variationId, $price, $siteId, $blockId, $blockValues, $blockReadableValues, $dealerId, $productionTime, $quantity, $wproduct=NULL) {
		$userId = !empty(get_current_user_id()) ? get_current_user_id() : null;

		//save new project
		$activeProject = IcpProductsProjectsModel::origin()->create([
			'product' => $productId,
			'product_name' => $productName,
			'variation' => $variationId,
			'variation_price' => $price,
			'site' => $siteId,
			'first_block' => $blockId,
			'user' => $userId,
			'woo_product' => $wproduct,
			'dealer' => $dealerId,
			'production_time' => $productionTime,
			'quantity' => $quantity,
		]);

		//save project components
		$activeProjectComponents = IcpProductsProjectsComponentsModel::origin()->create([
			'project' => $activeProject,
			'block' => $blockId,
			'value' => $blockValues,
			'readable_value' => $blockReadableValues
		]);

		return [
			'project' => $activeProject,
			'components' => $activeProjectComponents
		];
	}


	/**
	 * loadProductData
	 *
	 * @param int $productID
	 * @param int $siteID
	 * @return object
	 */
	public static function loadProductData($productID, $siteID) {

        if(is_multisite()) {
            if ($siteID == get_current_blog_id()) {
                $siteOriginEndpoint = RequestHelper::protocol() . '://' . (get_option('icp_printspot_endpoint') ?: get_option('wc_settings_tab_imaxel_icp_endpoint')) . '/';
            } else {
                $siteOriginEndpoint = get_blog_option($siteID, 'siteurl') . '/icp/' . $siteID;
            }
        }
        else{
            //TODO: DPI Comment with Jesus
            if (!empty(get_option('wc_settings_tab_imaxel_icp_endpoint'))) {
                $endpoint = get_option('wc_settings_tab_imaxel_icp_endpoint');
                $protocol = strpos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https' : 'http';
                $recoverProductDataURL = $protocol . '://' . $endpoint . '/' . $productID;
                $siteOriginEndpoint=$recoverProductDataURL;
            }
            else {
                $siteOriginEndpoint = get_blog_option($siteID, 'siteurl') . '/icp/' . $siteID;
            }
        }

		$productDataJSON = wp_remote_get(trailingslashit($siteOriginEndpoint) . $productID . '/', [
			'timeout' => 15000
		]);
		$productData = json_decode($productDataJSON['body']);

		return $productData;
	}


	/**
	 * getNextBlock
	 *
	 * get next block for the edition flow
	 *
	 * @param mixed $icpURL
	 * @param mixed $productID
	 * @param mixed $variationID
	 * @param mixed $currentBlock
	 * @param mixed $siteID
	 * @param mixed $projectID
	 * @return void
	 */
	public static function getNextBlock($icpURL, $productID, $variationID = '', $currentBlock, $siteID, $projectID, $wproduct = '') {

		//get product data
		$productData = self::loadProductData($productID, $siteID);

		//if product has just one single block return add to cart, if not, get next block
		if (count($productData->blocks) > 1) {

			$numberOfBlocks = 2;
			foreach ($productData->blocks as $block) {
				$blocksDataByID[$block->definition->block_id] = $block->definition;
				$blockDataByOrder[$block->definition->block_order] = $block->definition;
			}
			$currentBlockOrder = $blocksDataByID[$currentBlock]->block_order;

			//check if block is the last: continue if not, got to cart if yes
			$maxOrderBlock = max(array_keys($blockDataByOrder));
			if (intval($blocksDataByID[$currentBlock]->block_order) !== $maxOrderBlock) {

				$orderIncrement = 1;
				for ($x = 1; $x <= $numberOfBlocks; $x++) {
					$nextBlockOrder = $currentBlockOrder + $orderIncrement;
					$nextBlockID = $blockDataByOrder[$nextBlockOrder]->block_id;
					if ($nextBlockID) {
						break;
					} else {
						$orderIncrement++;
					}
				}
				$nextBlockType = $blockDataByOrder[$nextBlockOrder]->block_type;

				if ($nextBlockType == 'confirmation') {

					//update project
					$currentProjectDataRaw = IcpService::getProjectData($projectID);
					$currentProjectData = $currentProjectDataRaw['components'];
					$currentProjectData[$nextBlockID]['confirmation'] = 1;
					$updateProjectData['components'] = $currentProjectData;
					$newProjectData = serialize($updateProjectData['components']);

					//Update project components
					$price = null;
					IcpService::updateProjectComponent($newProjectData, $projectID, $price, $currentBlock);
				}

				//build new url for next block
				$newBlockURL = trim(strtok($icpURL, '?')) . '?id=' . $productID . '&site=' . $siteID . '&block=' . $nextBlockID . '&icp_project=' . $projectID;
                if ($wproduct !== 0) {
                    $newBlockURL .= '&wproduct=' . $wproduct;
                }
				return $newBlockURL;
			} else {
				CartService::addToCart($projectID, $productID, $productData->definition->dealer, $productData->site_origin, $productData->definition->code);

				$cartURL = wc_get_cart_url();
				return $cartURL;
			}
		} else {
			CartService::addToCart($projectID, $productID, $productData->definition->dealer, $productData->site_origin, $productData->definition->code);
			$cartURL = wc_get_cart_url();
			return $cartURL;
		}
	}

	/**
	 * getProjectData
	 *
	 * @param int $projectID
	 * @return array $currentProjectData
	 */
	public static function getProjectData($projectId) {
		$icpProject = IcpProductsProjectsModel::origin()->getById($projectId);
		$icpProjectComponents = IcpProductsProjectsComponentsModel::origin()->getByProjectId($projectId);

		$currentProjectData['components'] = unserialize($icpProjectComponents->value);
		$currentProjectData['price'] = $icpProject->variation_price;
		$currentProjectData['variation'] = $icpProject->variation;
		return $currentProjectData;
	}


	/**
	 * getVariationAttributeData
	 *
	 * get variation and attribute data
	 *
	 * @param mixed $variation
	 * @param mixed $siteID
	 * @param mixed $productID
	 * @return array $variationData
	 */
	public static function getVariationAttributeData($variation, $siteID, $productID, $icpProject = null) {
		$variationID = intval($variation);

		//get product data
		$productData = self::loadProductData($productID, $siteID);

		//reorganize data by product type
		foreach ($productData->blocks as $block) {
			$blocksData[$block->definition->block_type] = $block;
		}

		//group variation data
		$variation = $blocksData['product_definition']->variations->$variationID;
		$attributes = $blocksData['product_definition']->attributes;

		//generate variation data
		$variationData['image'] = $variation->image;
		$variationData['price'] = $variation->price;
		$attributes = (array)$attributes;
		foreach ($attributes as $attribute) {
			$attributeID = $attribute->definition->attribute_id;
			//control rules
			if (isset($variation->attributes->$attributeID)) {
				$valueID = $variation->attributes->$attributeID->value;
				$variationData['attributes'][$attribute->definition->attribute_slug]['id'] = $attributeID;
				$variationData['attributes'][$attribute->definition->attribute_slug]['value']['id'] = $variation->attributes->$attributeID->value;
				$variationData['attributes'][$attribute->definition->attribute_slug]['value']['key'] = $attribute->values->$valueID->value_key;
				$variationData['attributes'][$attribute->definition->attribute_slug]['value']['data'] = $attribute->values->$valueID->value_data;
				$variationData['attributes'][$attribute->definition->attribute_slug]['type'] = $attribute->definition->attribute_type;
			} else if ($icpProject) {
				//attribute disable in variation rules needs in definition icp
				$attributeData = self::getProjectAttributeValues($icpProject, $attributeID, $attribute->definition->attribute_slug);

				$variationData['attributes'][$attribute->definition->attribute_slug]['id'] = $attributeID;
				$variationData['attributes'][$attribute->definition->attribute_slug]['value']['id'] = $attributeData->value->id;
				$variationData['attributes'][$attribute->definition->attribute_slug]['value']['key'] = $attributeData->value->value_key;
				$variationData['attributes'][$attribute->definition->attribute_slug]['value']['data'] = $attributeData->value->value_data;
				$variationData['attributes'][$attribute->definition->attribute_slug]['type'] = $attribute->definition->attribute_type;
			}
		}

		return $variationData;
	}

	/**
	 * Set attribute value data without variation
	 * @param $projectId
	 * @param $attributeBlockId
	 * @param $attributeSlug
	 * @return stdClass
	 * @throws ImaxelDBEmtpyResultsExeption
	 */
	public static function getProjectAttributeValues($projectId, $attributeBlockId, $attributeSlug) {
		$dbValues = IcpProductsProjectsComponentsModel::origin()->getBy(['project' => $projectId], 'readable_value');
		$arrayValues = unserialize($dbValues->readable_value);
		$value = $arrayValues[$attributeSlug];
		global $wpdb;
		$valueData = IcpProductsAttributesValuesModel::origin()->getBy([
			'attribute_block' => $attributeBlockId,
			'value_data' => $value
		]);
		if (!$valueData) throw new ImaxelDBEmtpyResultsExeption();
		$attributeData = new \stdClass();
		$attributeData->id = $valueData->attribute;
		$attributeData->slug = $attributeSlug;
		$attributeData->value = new \stdClass();
		$attributeData->value->id = $valueData->id;
		$attributeData->value->value_data = $valueData->value_data;
		$attributeData->value->value_key = $valueData->value_key;
		$attributeData->value->attributeBlock = $valueData->attribute_block;

		return $attributeData;
	}

	/**
	 * getProjectAttributesData
	 *
	 * get project attributes data
	 *
	 * @param mixed $projectAttributesData
	 * @param mixed $productData
	 * @return array $variationData
	 */
	public static function getProjectAttributesData($projectAttributesData, $productData) {
		//get attributes data
		foreach ($projectAttributesData as $blockAttributeID => $valueID) {
			$attributesData = $productData->blocks[0]->attributes->$blockAttributeID->definition;
			$valuesData = $productData->blocks[0]->attributes->$blockAttributeID->values->$valueID;
			$variationData['attributes'][$attributesData->attribute_slug] = [
				'value' => [
					'key' => $valuesData->value_key,
					'data' => $valuesData->value_data,
					'id' => $valuesData->id],
				'id' => $blockAttributeID,
				'type' => $attributesData->attribute_type];
		}

		return $variationData;
	}

	/**
	 * generateToken
	 *
	 * TODO: REFACTOR PENDING
	 *
	 * PDF UPLOADER: get authentification form API
	 *
	 * @param mixed $scope
	 * @return curl response
	 */
	public static function generateToken($scope) {
		$timeout = 5;

		$postData = http_build_query(array(
			"grant_type" => "client_credentials",
			"scope" => $scope
		));

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://services.imaxel.com/apis/auth/v1/token");

		//get account keys

		//if it's a wordpress environment
		if (!empty(get_option('wc_settings_tab_imaxel_icp_publickey')) && !empty(get_option('wc_settings_tab_imaxel_icp_privatekey'))) {
			$icpPublicKey = get_option('wc_settings_tab_imaxel_icp_publickey');
			$icpPrivateKey = get_option('wc_settings_tab_imaxel_icp_privatekey');

			//if it's a printspot environment
		} elseif (!empty(get_option('icp_printspot_primary_key')) && !empty(get_option('icp_printspot_secondary_key'))) {
			$icpPublicKey = get_option('icp_printspot_primary_key');
			$icpPrivateKey = get_option('icp_printspot_secondary_key');
		} else {
			//printspot ICP API keys by default
			$icpPublicKey = 'm8G0EbdD353GbaoqAz4hn5';
			$icpPrivateKey = 'b7KUIzhLebAetqMuMX0RugvNxOzCZKQ39YaqWQshlQ';
		}

		$credentials = $icpPublicKey . ':' . $icpPrivateKey;

		curl_setopt($ch, CURLOPT_USERPWD, $credentials);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, false);
		curl_setopt($ch, CURLOPT_POST, strlen($postData));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

		$output = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpcode === 200) {
			$json = json_decode($output);
			return $json->access_token;
		} else {
			return null;
		}
	}

	/**
	 * remove block variations icp project and icp project components
	 * @param int $projectId
	 * @param int $blockId
	 */
	public static function removeBlockFromIcpProject($projectId, $blockId) {
		$projectPrice = IcpProductsProjectsModel::origin()->getVariation($projectId);
		unset($projectPrice[$blockId]);
		IcpProductsProjectsModel::origin()->updateVariation($projectPrice, $projectId);

		$projectComponent = IcpProductsProjectsComponentsModel::origin()->getByProjectId($projectId);
		$componentValue = unserialize($projectComponent->value);
		unset($componentValue[$blockId]);
		IcpProductsProjectsComponentsModel::origin()->updateVariation($componentValue, $projectId);

	}

	/**
	 * icordersCreateOrder
	 *
	 * IMAXEL CUSTOM ORDERS: create order
	 *
	 * TODO: Pending refactor
	 *
	 * @param mixed $orderData
	 * @param mixed $token
	 * @return curl response
	 */
	public static function icordersCreateOrder($orderData, $token) {
		$timeout = 15;

		$postData = $orderData;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Authorization: Bearer $token",
			"Content-Type:application/json"
		));
		curl_setopt($ch, CURLOPT_URL, "https://services.imaxel.com/apis/icorders/v1/sent-orders");

		//curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

		$output = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		if ($httpcode === 200) {
			return json_decode($output);
		}
		$message = !json_decode($orderData) ? 'API Error - malformed json post' : 'API Error';
		$data = json_decode($output) ?: $output;
		throw new ImaxelArrayException($message, $data, 20310);
	}

	/**
	 * make login url to redirect when user guest wants save project
	 * @param array $getParams
	 * @return string $urlRedirect
	 */
	public static function getUrlLoginFromEditorGuest($getParams) {
		global $wp;
		$backUrlAfterLogin = home_url($wp->request);
		$urlRedirect = site_url() . '/' . $getParams['unregisted_user_redirect_page'] . '/?save_icp_project=true';
		$urlRedirect .= '&project_id=' . $getParams['icp_project'] ?? null;
		$urlRedirect .= '&product_id=' . $getParams['id'];
		$urlRedirect .= '&block_id=' . $getParams['block'];
		$urlRedirect .= '&site_id=' . $getParams['site'];
		$urlRedirect .= '&backUrl=' . $backUrlAfterLogin;
		return $urlRedirect;
	}

	/**
	 * make url to redirect design icp product from login page after getUrlLoginFromEditorGuest()
	 * @param array $getParams
	 * @return string $url
	 */
	public static function getUrlRedirectFromLogin($getParams) {
		$url = $getParams['backUrl'];
		$url .= '?id=' . $getParams['product_id'];
		$url .= '&site=' . $getParams['site_id'];
		$url .= '&block=' . $getParams['block_id'];
		$url .= '&icp_project=' . $getParams['project_id'];
		$url .= '&redirect_from_login=true';
		return $url;
	}

	/**
	 * check if code combinations isset in array
	 * @param array $exceptions
	 * @param int $variantCode
	 * @param int $productCode
	 * @return bool
	 */
	public static function checkVariationInExceptions($exceptions, $variantCode, $productCode) {
		$concat = $variantCode . $productCode;
		if (!array_key_exists($variantCode, $exceptions) && !array_key_exists($concat, $exceptions)) return false;
		return true;
	}


	/**
	 * update project component
	 * @param $newProjectData
	 * @param $projectId
	 * @param string $price
	 * @param $blockID
	 * @param string $pdfWidth
	 * @param string $pdfHeight
	 */
	public static function updateProjectComponent($newProjectData, $projectId, $price = '', $blockID, $pdfWidth = '', $pdfHeight = '') {
		$projectVariants = self::getVariantsById($projectId);
		$projectVariants[$blockID]['total_price'] = $price;
		$projectVariants[$blockID]['pdf_width'] = $pdfWidth;
		$projectVariants[$blockID]['pdf_height'] = $pdfHeight;
		$projectVariants = serialize($projectVariants);
		self::updateIcpVariationProject($projectId, $projectVariants);

		IcpService::updateProjectComponentValues($projectId, $newProjectData);
	}

	/**
	 * updateValues
	 *
	 * Update serialize values in component from project id
	 *
	 * @param int $projectId
	 * @param serialized array $newValues
	 * @param serialized array $newReadableValues
	 * @return int $itemId
	 */
	public static function updateProjectComponentValues($projectId, $newValues, $newReadableValues = null) {
		$projectComponentsData = IcpProductsProjectsComponentsModel::origin()->getByProjectId($projectId);
		$newComponentValues = unserialize($newValues);
		$currentComponentValue = unserialize($projectComponentsData->value);
		foreach ($newComponentValues as $k => $newValue) {
			$currentComponentValue[$k] = $newValue;
		}

		if ($newReadableValues) {
			$newComponentReadableValues = unserialize($newReadableValues);
			$currentComponentReadableValues = unserialize($projectComponentsData->readable_value);
			foreach ($newComponentReadableValues as $k => $newValue) {
				$currentComponentReadableValues[$k] = $newValue;
			}
		}

		$data['value'] = serialize($currentComponentValue);
		if (isset($newReadableValues)) $data['readable_value'] = serialize($currentComponentReadableValues);

		IcpProductsProjectsComponentsModel::origin()->update($data, [
			'project' => $projectId
		]);

		return $projectComponentsData->id;
	}

	public static function getVariantsbyProjectId($projectId) {
		$row = IcpProductsProjectsComponentsModel::origin()->getByProjectId($projectId);
		return ['value' => unserialize($row->value), 'readable_value' => unserialize($row->readable_value)];
	}

	/**
	 * getVariantsById
	 *
	 * get unserialized variation price
	 *
	 * @param mixed $projectId
	 * @return array
	 */
	public static function getVariantsById($projectId) {
		$project = IcpProductsProjectsModel::origin()->getById($projectId);
		return unserialize($project->variation_price);
	}

	/**
	 * Return if block is a block data and not key data
	 * @param $block
	 * @return bool
	 */
	public static function isBlockData($block){
		return (
			$block !== 'total' &&
			$block !== 'qty' &&
			$block !== 'volumetric_value' &&
			$block !== 'volume_discount' &&
			$block !== 'delivery_time_discount'
		);
	}

}
