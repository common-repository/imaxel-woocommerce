<?php

class icpOperations
{

    public function icpDownloadProducts($publicKey, $privateKey, $endpoint, $codes, $simplified)
    {

        $PUBLIC_KEY = $publicKey;
        $PRIVATE_KEY = $privateKey;

        $endpoint .= "/products";

        $datetime = new DateTime(date('m/d/Y' . ' ' . 'g:i A'));

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

            $productos = $this->icpDoRequest($endpoint . '?policy=' . urlencode($policy) . '&signedPolicy=' . urlencode($signedPolicy) . '' . $codes . '' . $simplified);

            //$productos = $this->icpDoRequest($endpoint.'?policy='.urlencode($policy).'&signedPolicy='.urlencode($signedPolicy).''.$codes.''.$simplified);

            if ($productos == "") {
                downloadProducts($publicKey, $privateKey);
            } else {
                return $productos;
            }
        }
    }

    private function icpDoRequest($Url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $Url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($ch);
        if (FALSE === $content)
            throw new Exception(curl_error($ch), curl_errno($ch));
        curl_close($ch);
        return $content;
    }

    public function icpReadMedia($publicKey, $privateKey, $mediaID, $endpoint = "https://services.imaxel.com/api/v3/")
    {

        $PUBLIC_KEY = $publicKey;
        $PRIVATE_KEY = $privateKey;

        $endpoint .= '/medias/' . (int)$mediaID . '';
        $datetime = new DateTime("" . date('m/d/Y' . ' ' . 'g:i A'));
        date_add($datetime, date_interval_create_from_date_string('10 minutes'));

        $policy = '{
	        "id": "' . (int)$mediaID . '",
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

        $policy = base64_encode($policy);
        $signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

        $proyecto_datos = $this->icpDoRequest($endpoint . '?policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '');

        if ($proyecto_datos == "") {
            $this->readMedia($publicKey, $privateKey, $mediaID, $endpoint);
        } else {
            return $proyecto_datos;
        }
    }

    public function icpListMedias($publicKey, $privateKey, $mediaIDs, $endpoint = "https://services.imaxel.com/api/v3/")
    {

        $PUBLIC_KEY = $publicKey;
        $PRIVATE_KEY = $privateKey;

        $endpoint .= '/medias';
        $datetime = new DateTime("" . date('m/d/Y' . ' ' . 'g:i A'));
        date_add($datetime, date_interval_create_from_date_string('10 minutes'));

        $policy = '{	        
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

        $policy = base64_encode($policy);
        $signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

        $body = [
            'policy' => $policy,
            'signedPolicy' => $signedPolicy,
            'ids' => $mediaIDs
        ];
        $proyecto_datos = $this->doGetWithBody($endpoint, $body);


        return $proyecto_datos;

    }

    private function doGetWithBody($url, $body)
    {

        $serializedBody = http_build_query($body);

        $timeout = 5;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $serializedBody);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        $output = curl_exec($ch);

        curl_close($ch);
        return $output;
    }

    public function icpCreateProject($publicKey, $privateKey, $projectID, $productCode, $productPatch, $variations, $currentURL, $currentURLParameters, $backURL, $endpoint = "https://services.imaxel.com/api/v3/")
    {
        $PUBLIC_KEY = $publicKey;
        $PRIVATE_KEY = $privateKey;

        $printspotOutput = '';
        $datetime = new DateTime("" . date('m/d/Y' . ' ' . 'g:i A'));
        date_add($datetime, date_interval_create_from_date_string('10 minutes'));

        $policy = '{
            "productCode": "' . $productID . '",
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

        $policy = base64_encode($policy);
        $signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

        $params = '{
            "projectId": "' . $projectID . '",
            "productCode": "' . $productCode . '",
            "productPatch": ' . $productPatch . ',
            "policy": "' . $policy . '",
            "signedPolicy": "' . $signedPolicy . '"';

        if (!empty($variations)) {
            $params .= ',"variantsCodes":["' . implode('","', $variations) . '"]';
        }

        $params = $params . '}';

        $newProject = $this->icpDoPostProject($endpoint . "projects", $params);
        return $newProject;

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

        $printspotOutput .= $endpoint . "projects/" . $newProjectID . '/editUrl?backURL=' . $urlCancel . '&addToCartURL=' . $currentURL . '&policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '&redirect=1';

        return $printspotOutput;
    }

    public static function icpDoPostProject($url, $postData)
    {

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

    public function icpReadProject($publicKey, $privateKey, $projectID, $endpoint = "https://services.imaxel.com/api/v3/")
    {

        $PUBLIC_KEY = $publicKey;
        $PRIVATE_KEY = $privateKey;

        $endpoint .= '/projects/' . (int)$projectID . '';
        $datetime = new DateTime("" . date('m/d/Y' . ' ' . 'g:i A'));
        date_add($datetime, date_interval_create_from_date_string('10 minutes'));

        $policy = '{
	        "projectId": "' . (int)$projectID . '",
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

        $policy = base64_encode($policy);
        $signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

        $proyecto_datos = $this->icpDoRequest($endpoint . '?policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '');

        if ($proyecto_datos == "") {
            $this->readProject($publicKey, $privateKey, $projectID);
        } else {
            return $proyecto_datos;
        }
    }

    public function icpEditProject($publicKey, $privateKey, $projectID, $urlCart, $urlCartParameters, $urlCancel, $urlSave, $urlSaveParameters, $endpoint = "https://services.imaxel.com/api/v3/")
    {

        $productIWEB = false;
        if ($endpoint != "https://services.imaxel.com/api/v3/") {
            $productIWEB = true;
        }

        $PUBLIC_KEY = $publicKey;
        $PRIVATE_KEY = $privateKey;

        $datetime = new DateTime("" . date('m/d/Y' . ' ' . 'g:i A'));
        date_add($datetime, date_interval_create_from_date_string('10 minutes'));

        $urlCart .= urlencode($urlCartParameters);
        $urlSave .= urlencode($urlSaveParameters);

        if ($productIWEB == false) {
            $policy = '{
                "projectId": "' . $projectID . '",
                "backURL": "' . $urlCancel . '",
                "addToCartURL": "' . $urlCart . '",
                "publicKey": "' . $PUBLIC_KEY . '",
                "expirationDate": "' . $datetime->format('c') . '"
	        }';
        } else {
            $locale = get_locale();
            $policy = '{
                "projectId": "' . $projectID . '",
                "lng":"' . $locale . '",
                "backURL": "' . $urlCancel . '",
                "addToCartURL": "' . $urlCart . '",
                "saveURL": "' . $urlSave . '",
                "publicKey": "' . $PUBLIC_KEY . '",
                "expirationDate": "' . $datetime->format('c') . '"
	        }';
        }

        $policy = base64_encode($policy);
        $signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

        if ($productIWEB == false) {
            $url = $endpoint . '/projects/' . (int)$projectID . '/editUrl?backURL=' . $urlCancel . '&addToCartURL=' . $urlCart . '&policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '&redirect=1';
        } else {
            $url = $endpoint . '/projects/' . (int)$projectID . '/editUrl?backURL=' . $urlCancel . '&addToCartURL=' . urlencode($urlCart) . '&lng=' . $locale . '&policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '&redirect=1';
        }

        return $url;
    }

    public function icpDuplicateProject($publicKey, $privateKey, $projectID, $urlCart, $urlCartParameters, $urlCancel, $urlSave, $urlSaveParameters, $endpoint = "https://services.imaxel.com/api/v3/")
    {

        $PUBLIC_KEY = $publicKey;
        $PRIVATE_KEY = $privateKey;

        $datetime = new DateTime("" . date('m/d/Y' . ' ' . 'g:i A'));
        date_add($datetime, date_interval_create_from_date_string('10 minutes'));

        $policy = '{
	        "projectId": "' . $projectID . '",
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

        $policy = base64_encode($policy);
        $signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

        $params = array(
            "projectId" => "" . (int)$projectID . "",
            "policy" => "" . $policy . "",
            "signedPolicy" => "" . $signedPolicy . ""
        );

        $newProject = $this->icpDoPost($endpoint . "/projects", $params);

        if ($newProject) {
            $newProject = json_decode($newProject);

            if ($newProject->id) {
                $urlCartParameters .= "&attribute_proyecto=" . $newProject->id;
                if (strlen($urlSaveParameters) > 0)
                    $urlSaveParameters .= "&attribute_proyecto=" . $newProject->id;
                return array($newProject->id, $this->editProject($publicKey, $privateKey, $newProject->id, $urlCart, $urlCartParameters, $urlCancel, $urlSave, $urlSaveParameters, $endpoint));
            }
        }

        return "";
    }

    private function icpDoPost($url, $params)
    {

        $postData = http_build_query($params);

        $timeout = 5;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_POST, count($postData));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        $output = curl_exec($ch);

        curl_close($ch);
        return $output;
    }

    public function icpProcessOrder($publicKey, $privateKey, $order, $products, $customer, $addressDelivery, $endpoint = "https://services.imaxel.com/api/v3/", $iweb = false)
    {

        $PUBLIC_KEY = $publicKey;
        $PRIVATE_KEY = $privateKey;

        date_default_timezone_set('Europe/Madrid');
        $datetime = new DateTime("" . date('m/d/Y' . ' ' . 'g:i A'));
        date_add($datetime, date_interval_create_from_date_string('10 minutes'));

        $jobs = $products;

        $dataA = "";
        $aux = 0;
        foreach ($jobs as $position => $job) {
            $dataA .= "{";
            $dataA .= "\"project\":{\"id\": \"" . $job["proyecto"] . "\"}";
            $dataA .= ",\"units\":" . $job["qty"];


            if (!empty($job["order_behaviour_on_validation"]) && $job["order_behaviour_on_validation"] == "pause") {
                $dataA .= ",\"allowDownload\":false";
            }

            $order_id = $order->get_id();
            $orderPrintspotID = $order->get_meta('_printspot_order_number');


            //separation page for orders
            global $wpdb;
            $profiles = $wpdb->prefix . 'imaxel_printspot_shop_profiles';
            $profileID = getProfileId();
            $profileConfig = $wpdb->get_row("SELECT * FROM " . $profiles . " WHERE id='$profileID'");
            $currentSiteID = get_current_blog_id();
            $currentSiteURL = get_site_url() . '/separation-sheet/?order_id=' . $order_id . '&show_image=1';


            if ($profileConfig->separation_page == 'on') {

                $separationSheetPos = -1;
                $separationSheetLabel = '';
                if (get_option("separation_sheet_position_" . $profileID) === 'last_page') {
                    $separationSheetPos = count($jobs) - 1;
                    $separationSheetLabel = 'after';
                } else if (get_option("separation_sheet_position_" . $profileID) === 'first_page') {
                    $separationSheetPos = 0;
                    $separationSheetLabel = 'before';
                } else if (get_option("separation_sheet_position_" . $profileID) === 'none') {
                    $separationSheetPos = -1;
                } else {
                    $separationSheetPos = count($jobs) - 1;
                    $separationSheetLabel = 'after';
                }

                if ($position === $separationSheetPos) {
                    $dataA .= ",\"meta\":{
                        \"separation_sheet_url\":\"" . $currentSiteURL . "\",
                        \"separation_sheet_position\":\"" . addcslashes($separationSheetLabel, '"\\/') . "\"
                     }";
                }
            }


            //=========================================================================================================//

            if (empty($orderPrintspotID)) {
                $orderID = $order_id;
            } else {
                $orderID = $orderPrintspotID;
            }

            $dataA .= "}";
            if ((count($jobs) - 1) == $aux) {
            } else {
                $dataA .= ',';
            }
            $aux++;
        }

        $dataA .= "";

        $dataB = "{";

        $dataB .= "\"billing\":{
				\"email\":\"" . $customer["email"] . "\",
				\"firstName\":\"" . addcslashes($customer["first_name"], '"\\/') . "\",
				\"lastName\":\"" . addcslashes($customer["last_name"], '"\\/') . "\",
				\"phone\": \"" . addcslashes($customer["phone"], '"\\/') . "\"
			},
			\"saleNumber\":\"" . $orderID . "\",";

        $arrayPaymentsBankTransfer = array("bacs", "cheque");
        $arrayPaymentsCreditCard = array("paypal", "redsys", "myredsys");
        if (in_array($order->payment_method, $arrayPaymentsBankTransfer)) {
            $paymentTypeID = 6;
        } else if (in_array($order->payment_method, $arrayPaymentsCreditCard)) {
            $paymentTypeID = 2;
        } else {
            $paymentTypeID = 3;
        }
        $dataB .= "\"payment\":{
				\"name\": \"" . $order->payment_method_title . "\",
				\"instructions\":\"\",
				\"type\": \"" . $paymentTypeID . "\"
            },";

        $pickpointsTable = $wpdb->prefix . 'imaxel_printspot_shop_pickpoints';
        $shopTable = $wpdb->prefix . 'imaxel_printspot_shop_config';
        $orderData = wc_get_order($order);

        if (empty($printspotPickpointID) && !empty(get_post_meta($order->id, "printspot_pickpoint"))) {
            $printspotPickpointID = get_post_meta($order->id, "printspot_pickpoint")[0];
        }

        $printspotShopData = $wpdb->get_row("SELECT * FROM " . $shopTable . "");
        if (!empty($printspotShopData)) {
            $shopData = "
            \"shop\":{
                \"name\":\"" . addcslashes($printspotShopData->name, '"\\/') . "\",
                \"address\": \"" . addcslashes($printspotShopData->address, '"\\/') . "\",
                \"email\": \"" . addcslashes($printspotShopData->email, '"\\/') . "\",
                \"city\":\"" . addcslashes($printspotShopData->city, '"\\/') . "\",
                \"postalCode\":\"" . addcslashes($printspotShopData->zip, '"\\/') . "\",
                \"province\":\"" . addcslashes($printspotShopData->state, '"\\/') . "\",
                \"country\":\"" . addcslashes("", '"\\/') . "\",
                \"phone\":\"" . addcslashes($printspotShopData->phone, '"\\/') . "\",
                \"code\":\"" . addcslashes($printspotShopData->shop_code, '"\\/') . "\",
                \"accountingCode\":\"" . addcslashes($printspotShopData->account_code, '"\\/') . "\"
            },";
        } else {
            $shopData = "
            \"shop\":{
                \"name\":\"" . addcslashes("", '"\\/') . "\",
                \"address\": \"" . addcslashes("", '"\\/') . addcslashes("", '"\\/') . "\",
                \"city\":\"" . addcslashes("", '"\\/') . "\",
                \"email\":\"" . addcslashes("", '"\\/') . "\",
                \"postalCode\":\"" . addcslashes("", '"\\/') . "\",
                \"province\":\"" . addcslashes("", '"\\/') . "\",
                \"country\":\"" . addcslashes("", '"\\/') . "\",
                \"phone\":\"" . addcslashes("", '"\\/') . "\",
                \"code\":\"" . addcslashes("", '"\\/') . "\",
                \"accountingCode\":\"" . addcslashes("", '"\\/') . "\"
            },";

        }

        if (!empty($printspotPickpointID)) {
            $printspotPickpointData = $wpdb->get_row("SELECT * FROM " . $pickpointsTable . " WHERE id='$printspotPickpointID'");
            $dataB .= "\"pickpoint\":{
                \"name\":\"" . addcslashes($printspotPickpointData->title, '"\\/') . "\",
                \"address\": \"" . addcslashes($printspotPickpointData->address, '"\\/') . "\",
                \"city\":\"" . addcslashes($printspotPickpointData->city, '"\\/') . "\",
                \"postalCode\":\"" . addcslashes($printspotPickpointData->zip, '"\\/') . "\",
                \"province\":\"" . addcslashes($printspotPickpointData->state, '"\\/') . "\",
                \"country\":\"" . addcslashes("", '"\\/') . "\",
                \"firstName\":\"" . addcslashes("", '"\\/') . "\",
                \"phone\":\"" . addcslashes($printspotPickpointData->phone, '"\\/') . "\",
                \"code\":\"" . addcslashes($printspotPickpointData->code, '"\\/') . "\"
            }," . $shopData . "";
        } else {
            if ($order->has_shipping_method('local_pickup')) {
                $dataB .= "\"pickpoint\":{
                        \"name\":\"" . addcslashes($order->get_shipping_method(), '"\\/') . "\",
                        \"address\": \"" . addcslashes("", '"\\/') . addcslashes("", '"\\/') . "\",
                        \"city\":\"" . addcslashes("", '"\\/') . "\",
                        \"postalCode\":\"" . addcslashes("", '"\\/') . "\",
                        \"province\":\"" . addcslashes("", '"\\/') . "\",
                        \"country\":\"" . addcslashes("", '"\\/') . "\",
                        \"firstName\":\"" . addcslashes("", '"\\/') . "\",
                        \"phone\":\"" . addcslashes("", '"\\/') . "\"
                    }," . $shopData . "";
            } else {
                $countryISO = $addressDelivery["country"];
                $provinceName = html_entity_decode(WC()->countries->states[$order->shipping_country][$addressDelivery["state"]], ENT_NOQUOTES, 'UTF-8');
                $dataB .= "\"recipient\":{
                    \"address\": \"" . addcslashes($addressDelivery["address_1"], '"\\/') . addcslashes($addressDelivery["address_2"], '"\\/') . "\",
                    \"city\":\"" . addcslashes($addressDelivery["city"], '"\\/') . "\",
                    \"postalCode\":\"" . addcslashes($addressDelivery["postcode"], '"\\/') . "\",
                    \"province\":\"" . addcslashes($provinceName, '"\\/') . "\",
                    \"country\":\"" . addcslashes($countryISO, '"\\/') . "\",
                    \"email\":\"" . $customer["email"] . "\",
                    \"firstName\":\"" . addcslashes($addressDelivery["first_name"], '"\\/') . "\",
                    \"lastName\":\"" . addcslashes($addressDelivery["last_name"], '"\\/') . "\",
                    \"phone\":\"" . addcslashes($customer["phone"], '"\\/') . "\"
                }," . $shopData . "";

                $dataB .= "\"shippingMethod\": {
                    \"amount\": " . $order->get_total_shipping() . ",
                    \"name\":\"" . $order->get_shipping_method() . "\",
                    \"instructions\":\"" . "" . "\"
                },";
            }
        }

        $dataB .=
            "\"discount\": {
            \"amount\": 0,
            \"name\": \"\",
            \"code\": \"\"
        },
        \"total\": " . $order->get_total() . "";

        $dataB .= "
			}
		";

        $dataNotes = $order->get_customer_note();
        $dataNotes = addcslashes($dataNotes, '"\\/');
        $dataNotes = json_encode($dataNotes);

        $policy = '{
	        "jobs": [' . $dataA . '],
	        "checkout":' . $dataB . ',
	        "notes":' . $dataNotes . ',
	        "publicKey":"' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

        $policy = base64_encode($policy);
        $signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

        $paramsb = '{
	        "jobs":[' . $dataA . '],
	        "checkout":' . $dataB . ',
	        "notes":' . $dataNotes . ',
            "policy":"' . $policy . '",
	        "signedPolicy": "' . $signedPolicy . '"
	    }';

        $pedido_datos = $this->icpDoPostOrder($endpoint . "/orders", $paramsb);

        return $pedido_datos;
    }

    function icpDoPostOrder($url, $params)
    {
        $postData = $params;

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

    function icpDoPutOrder($url, $putData)
    {


        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $putData);

        $response = curl_exec($ch);
        return $response;
    }

}
