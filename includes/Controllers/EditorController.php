<?php

namespace Printspot\ICP\Controllers;

use Printspot\ICP\Services\EditorService;

class EditorController extends Controller {

	public static function load() {
		self::loadAJAX([
			'openEditor',
			'confirmProjectDesign'
		]);
	}

	/**
	 * parse post and send open editor service
	 */
	public static function openEditor() {
		$post = [];
		$post['siteOrigin'] = $_POST['siteOrigin'];
		$post['productID'] = $_POST['productID'];
		$post['attributeID'] = $_POST['attribute_id'];
		$post['valueID'] = $_POST['value_id'];
		$post['productID'] = $_POST['productID'];
		$post['productCode'] = $_POST['dealerProductCode'];
		$post['variations'] = $_POST['variationCode'];
		$post['dealerID'] = $_POST['dealerID'];
		$post['blockID'] = $_POST['blockID'];
		$post['icpProject'] = $_POST['icpProject'];
		$post['currentURL'] = $_POST['returnURL'];
		$post['price'] = $_POST['price'];
		$post['productModule'] = $_POST['product_module'];
		$post['useEditorPrice'] = $_POST['useEditorPrice'];

		try {
			$newUrl = EditorService::openEditor($post);
			wp_send_json_success($newUrl, 200);
		} catch (\Exception $e) {
			wp_send_json_error(__('Error accessing to editor: ' . $e->getMessage(), 'imaxel'), 500);
		}
	}

	/**
	 * @return \Printspot\ICP\Services\url
	 */
	public static function confirmProjectDesign(){
		$siteOrigin = $_POST['siteOrigin'];
		$productId = $_POST['productID'];
		$blockId = $_POST['blockID'];
		$projectId = $_POST['icpProject'];
		$attributeProyecto = $_POST['attributeProyecto'];
		$currentUrl = $_POST['returnURL'];
		$price = $_POST['price'];
        $wproduct = $_POST['wproduct'];

		try {
			$newUrl = EditorService::confirmProjectDesign($siteOrigin,$productId,$blockId,$projectId,$attributeProyecto,$currentUrl,$price,true,$wproduct);
			if (isset($_POST['siteOrigin'])) {
				wp_send_json_success($newUrl,200);
			} else {
				return $newUrl;
			}
		} catch (\Exception $e) {
			wp_send_json_error($e->getMessage(), 500);
		}
	}

}
