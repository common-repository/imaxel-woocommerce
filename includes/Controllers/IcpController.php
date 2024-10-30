<?php

namespace Printspot\ICP\Controllers;

use Printspot\ICP\Exceptions\ParamsNotFoundException;
use Printspot\ICP\Helpers\Config;
use Printspot\ICP\Models\IcpProductsProjectsModel;
use Printspot\ICP\Services\IcpOperationService;
use Printspot\ICP\Services\ShopService;
use Printspot\ICP\View;
use Printspot\ICP\Services\IcpService;

/**
 * IcpController
 *
 * Main controller for Custom Products Frontend
 */
class IcpController extends Controller {


	public static function load() {
		add_action('woocommerce_login_redirect', self::class . '::saveIcpProjectAfterLogin');
		add_action('woocommerce_before_cart_totals', self::class . '::unsetEditItemCartSession');

		self::loadAJAX([
			'createNewIcpProject',
			'editIcpProject',
			'showIcpFormModal',
			'saveIcpProjectName',
			'setEditItemCartSession',
			'removeBlockFromIcpProject'
		]);

		self::loadScripts();
	}


	public static function loadScripts() {

        $pluginName="imaxel-woocommerce";
        $swalPath = 'assets/js/imaxel_dialogs.js';
        View::addScript($pluginName . ':assets/js/sweetalert2.all.min.js');
        View::addScript($pluginName . ':assets/js/icp_helpers.js');
        View::addScript($pluginName . ':assets/js/icp.js',[
			'icpLocale' => [
				'upload_max_size' => Config::get('max_file_upload'),
				'currency_symbol' => ( ( class_exists('WooCommerce') ? get_woocommerce_currency_symbol() : '' ) ),
				'currency_position' => get_option('woocommerce_currency_pos')
			]
		]);
		View::addScript($pluginName . ':assets/js/icp_pdf.js');
        View::addScript($pluginName . ':' . $swalPath);


        $sanitize = sanitize_title($swalPath);
		wp_localize_script('icp_script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
		// locale js messages
		wp_localize_script($sanitize, 'modals', array(
			'btnPrimaryColor' => ShopService::getPrimaryColor(),
			'btnSecondaryColor' => ShopService::getSecondaryColor(),
			'btnConfirmText' => __('Accept', 'imaxel'),
			'btnCancelText' => __('Cancel', 'imaxel'),
			'successTitleModal' => __('Save/Update successfully', 'imaxel'),
			'errorTitleModal' => __('Error', 'imaxel'),
			'errorInfoProjectEmtpy' => __('Sorry, not found project info, browser will be reloaded. If persist error please contact with client support', 'imaxel'),
			'confirmTitleModal' => __('Are you sure ?', 'imaxel'),
			'confirmTextRemoveProject' => __('If you continue, your current design will be losed', 'imaxel'),
			'confirmExitTab' => __('Are you sure you want to leave?', 'imaxel'),

			'icpFormNameTitle' => __('Project name','imaxel'),
			'icpFormNameLabel' => __('Product project name', 'imaxel'),
			//pdf errors
			'pdfUploadRequest' => __('Upload a file in order to validate it..', 'imaxel'),
			'pdfErrorSize' => __('Too much weight. Please select another file.', 'imaxel'),
			'pdfUploadSelect' => __('Please select a PDF file.', 'imaxel'),
			'icpAttributeToggleDeleteTitle' => __('Existing rules using this attribute','imaxel'),
			'icpAttributeToggleNewTitle' => __('Existing rules detected','imaxel'),
			'icpAttributeRemoveMsg' => __("By disabling or deleting this attribute, all rules currently using it will be modified or deleted. Please review them after doing it. Do you want to continue?","imaxel"),
			'icpAttributeAddMsg' => __("The new attribute will be added with no value to the existing rules. Remember asing them a value after doing it. Do you want to continue?","imaxel"),

		));
	}

	public static function saveIcpProjectAfterLogin($redirect_to) {
		$user = get_current_user_id();
		$logged = is_user_logged_in();
		$redirect = $_GET['backUrl'] ?? '';
		return $redirect;
	}

	/**
	 * editIcpProject
	 *
	 * Launch editor for edit an existent icp project
	 *
	 * @return json $response - url to launch editor
	 */
	public static function editIcpProject() {
		try {
			$siteOrigin = $_POST['siteOrigin'];
			$productId = $_POST['productID'];
			$productId = $_POST['productID'];
			$productCode = $_POST['dealerProductCode'];
			$variations[] = $_POST['variationCode'];
			$dealerID = $_POST['dealerID'];
			$blockId = $_POST['blockID'];
			$icpProject = $_POST['icpProject'];
			$editorProjectId = $_POST['editorProjectId'];
			$currentUrl = $_POST['returnURL'];
			$isLastBlock = $_POST['isLastBlock'];
			$attributeId = $_POST['attributeId'];
			$valueId = $_POST['valueId'];
			$wproduct = $_POST['wproduct'];

			//get dealer credentials
			$credentials = IcpService::getDealerCredentials($siteOrigin, $dealerID);
			$privateKey = $credentials->privateKey;
			$publicKey = $credentials->publicKey;

			//read variant structure
			$codes[] = $productCode;
			$codesString = implode(',', $codes);
			$codes = '&codes=' . $codesString;
			$endpoint = "https://services.imaxel.com/api/v3";
			$simplified = '&simplified=1';
			$importProducts = json_decode(IcpOperationService::icpDownloadProducts($publicKey, $privateKey, $endpoint, $codes, $simplified));
			$priceModel = $importProducts[0]->variants[0]->price_model;

			//build editor url
			$currentUrlParameters = "?icp_add_design&id=" . $productId . "&site=" . $siteOrigin . "&block=" . $blockId . "&icp_project=" . $icpProject;

			//if 2 steps, return directo to cart.
			//if more steps return to current step
			$currentUrlParameters .= ($isLastBlock) ? '&icp_next_step=1' : '&icp_next_step=0';

			// ¡¡¡ Using woocommerce functionalities !!!
			$profileData = apply_filters('get_profile_data', null);

			$saveProjectParameters = IcpService::setSaveProjectToServicesParameter($profileData, $productId, $currentUrl, $currentUrlParameters);
			$colorParameters = IcpService::setColorToServicesParameter($profileData);

			//build back url from editor
			$backURL = get_option('icp_url') . "?id=" . $productId . "&site=" . $siteOrigin . "&block=" . $blockId . "&icp_project=" . $icpProject;

			$backURL .= isset($attributeId) ? '&attribute_id=' . $attributeId : '';
			$backURL .= isset($valueId) ? '&value_id=' . $valueId : '';

			$currentUrlParameters .= isset($attributeId) ? '&attribute_id=' . $attributeId : '';
			$currentUrlParameters .= isset($valueId) ? '&value_id=' . $valueId : '';


			$response = IcpService::icpEditProject($publicKey, $privateKey, $editorProjectId, $currentUrl, $currentUrlParameters, $backURL, $saveProjectParameters, $colorParameters);
			$response = apply_filters('set_hema_parameter', $response);

			wp_send_json_success($response, 200);
		} catch (\Exception $e) {
			wp_send_json_error($e->getMessage(), 500);
		}
		wp_die();
	}

	/**
	 * PRODUCT_DEFINITION: create new icp project
	 */
	public static function createNewIcpProject() {

		//get active project
		$activeProject = intval($_POST['activeProject']);

		//get selected variation
		$selectedVariation = $_POST['selectedvariation'];

		//get data form
		$productForm = $_POST['productform'];
		foreach ($productForm as $data) {
			$projectData[$data['name']] = $data['value'];
		}

		$productID = $projectData['product_id'];
		$dealerID = $projectData['dealer'];
		$productName = $projectData['product_name'];


		$quantity = isset($projectData['qty']) ? intval($projectData['qty']) : 1;
		$variationID = $projectData['variation_id'] ?: 0;
		$blockID = $projectData['block_id'];
		$variationPrice[$blockID]['unit_price'] = $projectData['variation_price'];
		$variationPrice[$blockID]['price_per_page'] = $projectData['variation_page_price'];
		$variationPrice[$blockID]['price_per_area'] = $projectData['variation_area_price'];
		$variationPrice[$blockID]['total_price'] = $projectData['variation_price'];
		$variationPrice['volumetric_value'] = $projectData['variation_volumetric'];
		$variationPrice['volume_discount'] = $projectData['volume_discount'];
		$variationPrice['delivery_time_discount'] = $projectData['delivery_time_discount'];
		$variationPrice['qty'] = $quantity;
		$price = serialize($variationPrice);
		$productionTime = intval($projectData['production_time']);
		$siteID = $projectData['site_origin'];
		$icpURL = $projectData['icp_url'];

        //if woocommerce produc is used
        if (isset($projectData['wproduct'])) {
            $wproduct = $projectData['wproduct'];
        } else {
            $wproduct = 0;
        }

		//get attribute components
		foreach ($projectData as $dataID => $data) {
			if (intval($dataID) !== 0) {
				$projectAttributeValues[$dataID] = $data;
			}
		}

		//translate attribute values to readable names
		$getProductData = IcpService::loadProductData($productID, $siteID);
		if (!empty($getProductData)) {
			foreach ($projectAttributeValues as $attributeId => $valueId) {

				//get attribute slug
				if (isset($getProductData->blocks[0]->attributes->$attributeId)) {
					$readableAttribute = $getProductData->blocks[0]->attributes->$attributeId->definition->attribute_slug;
				} else {
					$readableAttribute = 'No founded data';
				}

				//get value slug
				if (isset($getProductData->blocks[0]->attributes->$attributeId->values->$valueId)) {
					$readableValue = $getProductData->blocks[0]->attributes->$attributeId->values->$valueId->value_data;
				} else {
					$readableAttribute = 'No founded data';
				}

				//build array to save with project
				$readableAttributesValues[$readableAttribute] = $readableValue;
			}
		} else {
			$readableAttributesValues = NULL;
		}

		//generate values serialized array
		if ($selectedVariation !== NULL) {
			$variationData[$blockID]['variation'] = $selectedVariation;
		} else {
			if (isset($projectAttributeValues)) {
				$variationData[$blockID]['variation'] = serialize($projectAttributeValues);
			} else {
				$variationData[$blockID]['variation'] = 0;
			}
		}

		//if external_image url has been defined
		if (isset($projectData['external_url'])) {
			$variationData[$blockID]['external_url'] = $projectData['external_url'];
		}

		$blockValues = serialize($variationData);
		$blockReadableValues = serialize($readableAttributesValues);

		//create project or override it if already exists and skipCreationAndUpdate = false
		if ($_POST['skipCreationAndUpdate'] === 'false') {
			if ($activeProject == 0) {
				$newProject = IcpService::createIcpProjectDb($productID, $productName, $variationID, $price, $siteID, $blockID, $blockValues, $blockReadableValues, $dealerID, $productionTime, $quantity,$wproduct);
				$activeProject = $newProject['project'];
			} else {
				IcpService::updateIcpProjectDb($variationID, $price, $productionTime, $quantity, $activeProject, $blockValues, $blockReadableValues);
			}
		} else {
			//When edit icp project get current project and update variations
			$activeProject = IcpService::updateIcpFromBlock($activeProject, $quantity, $price, $blockValues, $blockReadableValues);
		}

		//stablish session id and associate project with it
		$_SESSION['sessionProject_' . get_current_blog_id()] = $activeProject;

		//get next block id
		$nextBlockURL = IcpService::getNextBlock($icpURL, $productID, $variationID, $blockID, $siteID, $activeProject,$wproduct);

		echo $nextBlockURL;

		wp_die();
	}

	/**
	 * load icp project name form view and send by ajax response
	 */
	public static function showIcpFormModal() {
		$projectId = $_POST['projectId'];
		$formId = 'icp-project-name-form';
        $pluginName="imaxel-woocommerce";
        $view = View::renderLoad($pluginName.':steps/modal_form_icp_name.php', [
			'id' => $formId,
			'projectId' => $projectId
		]);
		wp_send_json_success(['view' => $view, 'id' => $formId], 200);
		wp_die();
	}

	/**
	 * save via ajax icp project name
	 */
	public static function saveIcpProjectName() {
		$fields = $_POST['fields'];

		try {
			foreach ($fields as $field) {
				switch ($field['name']) {
					case 'icp-project-name':
						$projectName = $field['value'];
						break;
					case 'icp-project-id' :
						$projectId = $field['value'];
						break;
				}
			}
			if (!isset($projectName)) throw new \Exception(__('Error: project name not found', 'imaxel'));
			if (!isset($projectId)) throw new \Exception(__('Error: project id not found', 'imaxel'));

			IcpProductsProjectsModel::origin()->update(
				[
					'project_name' => $projectName
				],
				[
					'id' => $projectId
				]
			);
			wp_send_json_success(['name' => $projectName]);
		} catch (\Exception $e) {
			wp_send_json_error($e->getMessage(), 500);
		}

	}

	/**
	 * Set session for edit icp project
	 */
	public static function setEditItemCartSession() {
		$icpProject = $_POST['icpProject'];
		$_SESSION['sessionProject_' . get_current_blog_id()] = $icpProject;
		wp_die();
	}

	/**
	 * Remove session Project when load cart page
	 */
	public static function unsetEditItemCartSession() {
		unset($_SESSION['sessionProject_' . get_current_blog_id()]);
	}

	/**
	 * Remove icp project block info
	 */
	public static function removeBlockFromIcpProject() {
		try {
			$projectId = $_POST['icpProject'];
			$blockId = $_POST['blockID'];
			if (!$projectId || !$blockId) throw new ParamsNotFoundException();
			IcpService::removeBlockFromIcpProject($projectId, $blockId);
			self::responseAjax(true, 'success');
		} catch (\Exception $e) {
			self::responseAjax(false, $e->getMessage());
		}

	}

}
