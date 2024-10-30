<?php

namespace Printspot\ICP\Services;

use DateTime;
use icpOperations;
use Printspot\ICP\Models\IcpProductsProjectsModel;
use Printspot\ICP\Models\IcpProductsProjectsComponentsModel;

class EditorService {


	/**
	 * icpDoPostProject
	 *
	 * do curl request
	 *
	 * @param mixed $url
	 * @param mixed $postData
	 * @return void
	 */
	public static function icpDoPostProject($url, $postData) {

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

		$output = curl_exec($ch);

		curl_close($ch);
		return $output;

	}

	/**
	 * createEditorProject
	 *
	 * create project in editor and return id
	 *
	 * @param string $publicKey
	 * @param string $privateKey
	 * @param mixed $productCode
	 * @param mixed $productPatch
	 * @param url $endpoint
	 * @return int $projectId
	 */
	public static function createEditorProject($publicKey, $privateKey, $productCode, $productPatch, $endpoint = null) {
		$endpoint = $endpoint ?: "https://services.imaxel.com/api/v3/";
		$PUBLIC_KEY = $publicKey;
		$PRIVATE_KEY = $privateKey;

        $datetime = new DateTime("" . date('y-m-d H:i:s.u'));
		date_add($datetime, date_interval_create_from_date_string('10 minutes'));

		$policy = '{
	        "productCode": "' . $productCode . '",
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

		$params = '{
            "productCode": "' . $productCode . '",
            "productPatch": ' . $productPatch . ',
            "policy": "' . $policy . '",
            "signedPolicy": "' . $signedPolicy . '",
			"currencyCode": "' . get_woocommerce_currency() . '"';

		if (!empty($variations)) {
			$params .= ',"variantsCodes":["' . implode('","', $variations) . '"]';
		}

		$params = $params . '}';

		$newProject = self::icpDoPostProject($endpoint . "projects", $params);
		$newProject = json_decode($newProject);
		$newProjectId = $newProject->id;
		return $newProjectId ?: null;
	}

	/**
	 *
	 * getEditorUrl
	 *
	 * get url to redirect and edit a project editor already existent
	 *
	 * @param $publicKey
	 * @param $privateKey
	 * @param $projectEditorId
	 * @param $currentURL
	 * @param $currentURLParameters
	 * @param $backURL
	 * @param $saveProjectParams
	 * @param $colorParams
	 * @param null $endpoint
	 * @return mixed|void
	 * @throws \Exception
	 */
	public static function getEditorUrl($publicKey, $privateKey, $projectEditorId, $currentURL, $currentURLParameters, $backURL, $saveProjectParams = null, $colorParams = null, $endpoint = null) {
		$endpoint = $endpoint ?: "https://services.imaxel.com/api/v3/";
		$currentURL .= urlencode($currentURLParameters . "&attribute_proyecto=" . $projectEditorId);

		$urlCancel = urlencode($backURL);
        $datetime = new DateTime("" . date('y-m-d H:i:s.u'));
		date_add($datetime, date_interval_create_from_date_string('10 minutes'));
		$policy = '{
            "projectId": "' . $projectEditorId . '",
            "backURL": "' . $backURL . '",
            "addToCartURL": "' . $currentURL . '",
            "publicKey": "' . $publicKey . '",
            "redirect": "1",
            "expirationDate": "' . $datetime->format('c') . '"
        }';

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $privateKey, true));

		// set url with optinal parameters
		$projectParams = $saveProjectParams ? '&' . $saveProjectParams : null;

		$printspotOutput = $endpoint . "projects/" . $projectEditorId . '/editUrl?backURL=' . $urlCancel . '&addToCartURL=' . $currentURL . '&policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . $projectParams . $colorParams . '&redirect=1';

		$printspotOutput = apply_filters('set_hema_parameter', $printspotOutput);
		
		return $printspotOutput;
	}

	/**
	 * openEditor
	 *
	 * DESIGN: Create project and open editor
	 *
	 * @return void
	 */
	public static function openEditor($post) {

		$siteOrigin = $post['siteOrigin'];
		$productID = $post['productID'];
		$attributeID = $post['attributeID'];
		$valueID = $post['valueID'];
		$productID = $post['productID'];
		$productCode = $post['productCode'];
		$variations[] = $post['variations'];
		$dealerID = $post['dealerID'];
		$blockID = $post['blockID'];
		$icpProject = $post['icpProject'];
		$currentURL = $post['currentURL'];
		$price = $post['price'];
        $wproduct = $_POST['wproduct'];
        $productModule = $post['productModule'];
		$useEditorPrice = $post['useEditorPrice'];


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

		if (isset($importProducts->msg)) throw new \Exception($importProducts->msg);

		$priceModel = $importProducts[0]->variants[0]->price_model;

		//build editor url
		$currentURLParameters = "?icp_add_design&id=" . $productID . "&site=" . $siteOrigin . "&block=" . $blockID . "&icp_project=" . $icpProject . "&attribute_id=" . $attributeID . "&value_id=" . $valueID . '&icp_next_step=1';
        if (!empty($wproduct)) {
            $currentURLParameters .= "&wproduct=" . $wproduct;
        }

		//build back url from editor
		$backURL = get_option('icp_url') . "?id=" . $productID . "&site=" . $siteOrigin . "&block=" . $blockID . "&icp_project=" . $icpProject . "&attribute_id=" . $attributeID . "&value_id=" . $valueID;
        if (!empty($wproduct)) {
            $backURL .= "&wproduct=" . $wproduct;
        }

		//build product patch
		switch ($productModule) {
			case "printspack":
			case "polaroids":
			case "simplephotobooks":
			case "simplephotobooks2":
				$productPatch = "
                    {
                        \"patchVersion\":\"1.0.0\"
                    }";
				break;

			case "multigifts":
			case "gifts2d":
			case "wideformat":
			case "canvas":
			case "multipage":

				if (!empty($useEditorPrice) && ($useEditorPrice === 'on')) {
					$productPatch = " {
                            \"patchVersion\":\"1.0.0\"
                        }";
				} else {
					$productPatch = "
                        {
                            \"patchVersion\":\"1.0.0\",";

					$productPatch .= "
                                \"variants\": [
                            ";

					$productPatch .= "
                            {
                                \"code\":\"" . $variations[0] . "\",
                                \"price_model\":\"" . $priceModel . "\",
                                \"price\": " . intval($price) . "
                            }";

					$productPatch .= "]}";
				}

				break;
		}


		//editor call
        //TODO: DPI
        if(is_plugin_active('imaxel-printspot/imaxel-printspot.php')) {
            $profileData = apply_filters('get_profile_data', null);
        }
		$saveProjectParameters = IcpService::setSaveProjectToServicesParameter($profileData, $productCode, $currentURL, $currentURLParameters);
		$colorParameters = IcpService::setColorToServicesParameter($profileData);

		//create new editor project
		$projectEditorId = self::createEditorProject($publicKey, $privateKey, $productCode, $productPatch);
		if(!$projectEditorId) throw new \Exception(__('Error try create editor project','imaxel'));

		//get editor url to redirect
		//$response = self::getEditorUrl($publicKey, $privateKey, $projectEditorId, $currentURL, $currentURLParameters, $backURL, $saveProjectParameters, $colorParameters);
        $response = self::getEditorUrl($publicKey, $privateKey, $projectEditorId, $currentURL, $currentURLParameters, $backURL);
		$response = apply_filters('set_hema_parameter', $response);


		//update icp with project id
		$values = serialize([$blockID => ['dealer_project' => $projectEditorId]]);
		IcpService::updateProjectComponentValues($icpProject, $values);

		return $response;
	}

	/**
	 * confirmProjectDesign
	 *
	 * execute when editor finished
	 *
	 * @param mixed $siteOrigin
	 * @param mixed $productID
	 * @param mixed $blockID
	 * @param mixed $projectID
	 * @param mixed $attributeProyecto
	 * @param mixed $currentURL
	 * @param mixed $price
	 * @return url $newURL url to redirect, block or cart
	 */
	public static function confirmProjectDesign($siteOrigin = '', $productID = '', $blockID = '', $projectID = '', $attributeProyecto = '', $currentURL = '', $price = '',$getUrl = false,$wproduct='') {

		//current project data
		$projectData = IcpService::getProjectData($projectID);
		if (isset($projectData['components'][$blockID])) {
			unset($projectData['components'][$blockID]);
		}
		$projectData['components'][$blockID]['dealer_project'] = $attributeProyecto;
		$newProjectData = serialize($projectData['components']);

		//Update project components
		IcpService::updateProjectComponent($newProjectData, $projectID, $price, $blockID);

		//get project active variation
		$activeVariation = $projectData['variation'];

		//response new block url
		if($getUrl){
			//update db because project has been edited
			IcpProductsProjectsModel::origin()->updateDateIcpProject($projectID);
			
			$newURL = IcpService::getNextBlock($currentURL, $productID, $activeVariation, $blockID, $siteOrigin, $projectID,$wproduct);
			return $newURL;
		}


	}

}
