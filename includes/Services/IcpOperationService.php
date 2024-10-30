<?php

namespace Printspot\ICP\Services;

use DateTime;

class IcpOperationService {

	public static function icpDownloadProducts($publicKey, $privateKey, $endpoint, $codes, $simplified) {

		$PUBLIC_KEY = $publicKey;
		$PRIVATE_KEY = $privateKey;

		$endpoint .= "/products";

        $datetime = new DateTime("" . date('y-m-d H:i:s.u'));
        date_add($datetime, date_interval_create_from_date_string('10 minutes'));

		if ($PUBLIC_KEY == "") {
			return "";
		} else {
			$policy = '{
	            "publicKey": "' . $PUBLIC_KEY . '",
	            "expirationDate": "' . $datetime->format('c') . '"
	        }';

			$policy = base64_encode($policy);
			$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

			$params = array(
				"policy" => "" . $policy . "",
				"signedPolicy" => "" . urlencode($signedPolicy) . ""
			);

			$productos = self::icpDoRequest($endpoint . '?policy=' . urlencode($policy) . '&signedPolicy=' . urlencode($signedPolicy) . '' . $codes . '' . $simplified);

			return $productos;
		}
	}

	public static function icpReadMedia($publicKey, $privateKey, $mediaID, $endpoint = "https://services.imaxel.com/api/v3/") {

		$PUBLIC_KEY = $publicKey;
		$PRIVATE_KEY = $privateKey;

		$endpoint .= '/medias/' . (int)$mediaID . '';
        $datetime = new DateTime("" . date('y-m-d H:i:s.u'));
		date_add($datetime, date_interval_create_from_date_string('10 minutes'));

		$policy = '{
	        "id": "' . (int)$mediaID . '",
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

		$proyecto_datos = self::icpDoRequest($endpoint . '?policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '');

		return $proyecto_datos;
	}

	public static function icpCreateProject($publicKey, $privateKey, $productCode, $productPatch, $variations, $currentURL, $currentURLParameters, $backURL, $saveProjectParams, $colorParams, $endpoint = "https://services.imaxel.com/api/v3/") {

		$PUBLIC_KEY = $publicKey;
		$PRIVATE_KEY = $privateKey;

		$printspotOutput = '';
        $datetime = new DateTime("" . date('y-m-d H:i:s.u'));
		date_add($datetime, date_interval_create_from_date_string('10 minutes'));

		$policy = '{
	        "productCode": "' . $productID . '",
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

		$params = '{
            "productCode": "' . $productCode . '",
            "productPatch": ' . $productPatch . ',
            "policy": "' . $policy . '",
            "signedPolicy": "' . $signedPolicy . '"';

		if (!empty($variations)) {
			$params .= ',"variantsCodes":["' . implode('","', $variations) . '"]';
		}

		$params = $params . '}';

		$newProject = self::icpDoPostProject($endpoint . "projects", $params);
		$newProject = json_decode($newProject);
		$newProjectID = $newProject->id;
		if ($newProjectID == 0) {
			return null;
		}

		$currentURL .= urlencode($currentURLParameters . "&attribute_proyecto=" . $newProjectID);

		$urlCancel = urlencode($backURL);

		$policy = '{
            "projectId": "' . $newProjectID . '",
            "backURL": "' . $backURL . '",
            "addToCartURL": "' . $currentURL . '",
            "publicKey": "' . $PUBLIC_KEY . '",
            "redirect": "1",
            "expirationDate": "' . $datetime->format('c') . '"
        }';

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

		// set url with optinal parameters
		$projectParams = $saveProjectParams ? '&' . $saveProjectParams : null;

		$printspotOutput .= $endpoint . "projects/" . $newProjectID . '/editUrl?backURL=' . $urlCancel . '&addToCartURL=' . $currentURL . '&policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . $projectParams . $colorParams . '&redirect=1';

		return $printspotOutput;

	}

	public static function icpReadProject($publicKey, $privateKey, $projectID, $endpoint = "https://services.imaxel.com/api/v3/") {

		$PUBLIC_KEY = $publicKey;
		$PRIVATE_KEY = $privateKey;

		$endpoint .= '/projects/' . (int)$projectID . '';
        $datetime = new DateTime("" . date('y-m-d H:i:s.u'));
		date_add($datetime, date_interval_create_from_date_string('10 minutes'));

		$policy = '{
	        "projectId": "' . (int)$projectID . '",
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

		$proyecto_datos = self::icpDoRequest($endpoint . '?policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '');

		return $proyecto_datos;
	}


	private static function icpDoRequest($Url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $Url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$content = curl_exec($ch);
		if (FALSE === $content)
			throw new \Exception(curl_error($ch), curl_errno($ch));
		curl_close($ch);
		return $content;
	}

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

}
