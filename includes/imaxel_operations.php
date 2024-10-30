<?php

class ImaxelOperations
{

	#region Public functions

	#region Project management functions

	public function createProject($publicKey, $privateKey, $productID, $productAttributeID, $productCode, $productVariations, $urlCart, $urlCartParameters, $urlCancel, $urlSave, $urlSaveParameters, $endpoint = "https://services.imaxel.com/api/v3/")
	{
		$PUBLIC_KEY = $publicKey;
		$PRIVATE_KEY = $privateKey;

		$output = '';
		$datetime = new DateTime("" . date('y-m-d H:i:s.u'));
		date_add($datetime, date_interval_create_from_date_string('10 minutes'));

		$policy = '{
	        "productCode": "' . $productCode . '",
	        "publicKey": "' . $PUBLIC_KEY . '",
	        "expirationDate": "' . $datetime->format('c') . '"
	    }';

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

		$params = array(
			"currencyCode" => get_woocommerce_currency(),
			"productCode" => "" . $productCode . "",
			"policy" => "" . $policy . "",
			"signedPolicy" => "" . $signedPolicy . ""
			//"signedPolicy" => "".urlencode($signedPolicy).""
		);

		if ($productVariations) {
			$params["variantsCodes"] = $productVariations;
		} else {
		}

		$newProject = $this->doPost($endpoint . "projects", $params);
		$newProject = json_decode($newProject);
		$newProjectID = $newProject->id;
		if ($newProjectID == 0) {
			return null;
		}
		$productModule = $newProject->product->module->code;

		$urlCart .= urlencode($urlCartParameters . "&attribute_proyecto=" . $newProjectID);
		$urlSave .= urlencode($urlSaveParameters . "&attribute_proyecto=" . $newProjectID);
		$urlCancel = urlencode($urlCancel);

		$policy = '{
          "projectId": "' . $newProjectID . '",
          "backURL": "' . $urlCancel . '",
          "addToCartURL": "' . $urlCart . '",
          "publicKey": "' . $PUBLIC_KEY . '",
          "redirect": "1",
          "expirationDate": "' . $datetime->format('c') . '"
        }';

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

		$output .= $endpoint . "projects/" . $newProjectID . '/editUrl?backURL=' . $urlCancel . '&addToCartURL=' . $urlCart . '&policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '&redirect=1';

		$bEnableSave = false;
		if ($productModule == "simplephotobooks2") {
			$bEnableSave = true;
		}

		if ($bEnableSave == true)
		{
			if ( is_user_logged_in() )
			{
				global $wpdb;
				$exists = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "imaxel_woo_projects WHERE id_customer=" . get_current_user_id() . " AND id_project=" . $newProjectID);
				
				if (!$exists)
				{
					$projectName = $newProject->product->name->default;
					$projectName = esc_sql($projectName);
					$projectDate = $newProject->updatedAt;
					$variantSku = !empty( $newProject->design->sku ) ? "'" . $newProject->design->sku . "'" : "NULL";

					$sql = "INSERT INTO " . $wpdb->prefix . "imaxel_woo_projects (id_customer, id_project, id_product, id_product_attribute, description_project, date_project, services_sku)
                      VALUES (
                      " . get_current_user_id() . "," . $newProjectID . "," . $productID . "," . $productAttributeID . ",'" . $projectName . "','" . $projectDate . "'," . $variantSku . ")";
					$wpdb->query($sql);
				}

				$output = $output . '&saveProjectBehaviour=allow';
			}
			else
			{
				$output = $output . '&saveProjectBehaviour=anonymous_user&userSignInUrl=' . $urlSave;
			}
		}

		return $output;
	}

	private function doPost($url, $params)
	{
		$postData = http_build_query($params);

		$timeout = 5;
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

		$output = curl_exec($ch);

		curl_close($ch);
		return $output;
	}

	public function duplicateProject($publicKey, $privateKey, $projectID, $productID, $urlCart, $urlCartParameters, $urlCancel, $urlSave, $urlSaveParameters, $endpoint = "https://services.imaxel.com/api/v3/")
	{

		$PUBLIC_KEY = $publicKey;
		$PRIVATE_KEY = $privateKey;

		$datetime = new DateTime("" . date('y-m-d H:i:s.u'));
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

		$newProject = $this->doPost($endpoint . "/projects", $params);

		if ($newProject) {
			$newProject = json_decode($newProject);

			if ($newProject->id) {
				$urlCartParameters .= "&attribute_proyecto=" . $newProject->id;
				if (strlen($urlSaveParameters) > 0)
					$urlSaveParameters .= "&attribute_proyecto=" . $newProject->id;
				return array($newProject->id, $this->editProject($publicKey, $privateKey, $newProject->id, $productID, $urlCart, $urlCartParameters, $urlCancel, $urlSave, $urlSaveParameters, $endpoint));
			}
		}

		return "";
	}

	public function editProject($publicKey, $privateKey, $projectID, $productID, $urlCart, $urlCartParameters, $urlCancel, $urlSave, $urlSaveParameters, $endpoint = "https://services.imaxel.com/api/v3/")
	{
		$PUBLIC_KEY = $publicKey;
		$PRIVATE_KEY = $privateKey;
		$bEnableSave = false;

		$projectInfo = $this->readProject($publicKey, $privateKey, $projectID);
		if (!empty($projectInfo)) {
			$projectInfo = json_decode($projectInfo);
			$projectModule = $projectInfo->product->module->code;
			if ($projectModule == "simplephotobooks2") {
				$bEnableSave = true;
			}
		}

		$datetime = new DateTime("" . date('y-m-d H:i:s.u'));
		date_add($datetime, date_interval_create_from_date_string('10 minutes'));

		$urlCart .= urlencode($urlCartParameters);

		$policy = '{
            "projectId": "' . $projectID . '",
            "backURL": "' . $urlCancel . '",
            "addToCartURL": "' . $urlCart . '",
            "publicKey": "' . $PUBLIC_KEY . '",
            "expirationDate": "' . $datetime->format('c') . '"
        }';

		$policy = base64_encode($policy);
		$signedPolicy = base64_encode(hash_hmac("SHA256", $policy, $PRIVATE_KEY, true));

		$url = $endpoint . '/projects/' . (int)$projectID . '/editUrl?backURL=' . $urlCancel . '&addToCartURL=' . $urlCart . '&policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '&redirect=1';

		if ($bEnableSave == true) {
			if (is_user_logged_in()) {
				$url = $url . '&saveProjectBehaviour=allow';
			} else {
				$url = $url . '&saveProjectBehaviour=anonymous_user&userSignInUrl=' . $urlSave;
			}
		}

		return $url;
	}

	#endregion

	#region Products / Orders functions

	public function readProject($publicKey, $privateKey, $projectID, $endpoint = "https://services.imaxel.com/api/v3/")
	{

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

		$proyecto_datos = $this->doRequest($endpoint . '?policy=' . $policy . '&signedPolicy=' . urlencode($signedPolicy) . '');

		if ($proyecto_datos == "") {
			$this->readProject($publicKey, $privateKey, $projectID);
		} else {
			return $proyecto_datos;
		}
	}

	private function doRequest($Url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $Url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		WC_Imaxel::writeLog("START: cURL session");
		$content = curl_exec($ch);
		WC_Imaxel::writeCheckLog("END: cURL session", "empty", $content);

		if (FALSE === $content)
			throw new Exception(curl_error($ch), curl_errno($ch));
		curl_close($ch);
		return $content;
	}

	#endregion

	#endregion

	#region Private functions

	public function downloadProducts($publicKey, $privateKey, $endpoint = "https://services.imaxel.com/api/v3")
	{

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

			WC_Imaxel::writeLog("START: doRequest");
			$productos = $this->doRequest($endpoint . '?policy=' . urlencode($policy) . '&signedPolicy=' . urlencode($signedPolicy) . '');
            WC_Imaxel::writeCheckLog("END: doRequest", "empty", $productos);

			if ($productos == "") {
				WC_Imaxel::writeLog("EMPTY REQUEST: calling function recursively...");
				downloadProducts($publicKey, $privateKey);
			} else {
				return $productos;
			}
		}
	}

	public function processOrder($publicKey, $privateKey, $order, $products, $customer, $addressDelivery, $reprocess = false, $endpoint = "https://services.imaxel.com/api/v3/")
	{

		if (empty(get_post_meta($order->id, 'dealer_order_id', true)) || $reprocess == true) {

			$PUBLIC_KEY = $publicKey;
			$PRIVATE_KEY = $privateKey;

			date_default_timezone_set('Europe/Madrid');
			$datetime = new DateTime("" . date('y-m-d H:i:s.u'));
			date_add($datetime, date_interval_create_from_date_string('10 minutes'));

			$jobs = $products;

			$dataA = "";
			$aux = 0;
			foreach ($jobs as $job) {
				if ($job->get_meta('proyecto')) {
					//check if it is prints and set 1 if printspack or polaroids and equal to qty if another
					$projectModule = json_decode($this->readProject($PUBLIC_KEY, $PRIVATE_KEY, $job->get_meta('proyecto'), $endpoint = "https://services.imaxel.com/api/v3/"))->product->module->code;

					if ($projectModule) {
						($projectModule == 'printspack' || $projectModule == 'polaroids') ? $qty = 1 : $qty = $job['qty'];
					} else {
						$qty = 1;
					}

					$dataA .= "{\"project\":{\"id\": \"" . $job["proyecto"] . "\"},\"units\":" . $qty . "},";
				}
				$aux++;
			}

			$dataA = substr_replace($dataA, "", -1);
			$dataA .= "";

			$dataB = "{";

			$dataB .= "\"billing\":{
                    \"email\":\"" . $customer["email"] . "\",
                    \"firstName\":\"" . addcslashes($customer["first_name"], '"\\/') . "\",
                    \"lastName\":\"" . addcslashes($customer["last_name"], '"\\/') . "\",
                    \"phone\": \"" . addcslashes($customer["phone"], '"\\/') . "\"
                },
                \"saleNumber\":\"" . $order->id . "\",";

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

			$pickup_locations = array();
			if (class_exists('WC_Local_Pickup_Plus')) {
				$local_pickup = wc_local_pickup_plus();
				$local_pickup_version = $local_pickup->get_version();
				if (version_compare($local_pickup_version, "2.2.0") >= 0) {
					$orders_handler = $local_pickup->get_orders_instance();
					if ($orders_handler && ($pickup_data = $orders_handler->get_order_pickup_data($order, true))) {
						foreach ($pickup_data as $pickup_info) {
							$pickup_location = new WC_Local_Pickup_Plus_Pickup_Location($pickup_info["pickup_location_id"]);
							$pickup_locations[] = $pickup_location;
						}
					}
				} else {
					foreach ($order->get_shipping_methods() as $shipping_item) {
						if (isset($shipping_item['pickup_location'])) {
							$location = maybe_unserialize($shipping_item['pickup_location']);
							$pickup_locations[] = $location;
						}
					}
				}
			}

			if (count($pickup_locations) > 0 || $order->has_shipping_method('local_pickup')) {
				if (class_exists('WC_Local_Pickup_Plus') && count($pickup_locations) > 0) {
					if (version_compare($local_pickup_version, "2.2.0") >= 0) {
						$pickup_location_address = $pickup_locations[0]->get_address();
						$countryISO = $pickup_location_address->get_country();
						$provinceName = WC()->countries->states[$pickup_location_address->get_country()][$pickup_location_address->get_state()];
						$dataB .= "\"pickpoint\":{
                                \"name\":\"" . addcslashes($pickup_locations[0]->get_name(), '"\\/') . "\",
                                \"address\": \"" . addcslashes($pickup_location_address->get_address_line_1(), '"\\/') . addcslashes($pickup_location_address->get_address_line_2(), '"\\/') . "\",
                                \"city\":\"" . addcslashes($pickup_location_address->get_city(), '"\\/') . "\",
                                \"postalCode\":\"" . addcslashes($pickup_location_address->get_postcode(), '"\\/') . "\",
                                \"province\":\"" . addcslashes($provinceName, '"\\/') . "\",
                                \"country\":\"" . addcslashes($countryISO, '"\\/') . "\",
                                \"firstName\":\"" . addcslashes($pickup_locations[0]->get_name(), '"\\/') . "\",
                                \"phone\":\"" . addcslashes($pickup_locations[0]->get_phone(), '"\\/') . "\"
                            },";
					} else {
						$countryISO = $pickup_locations[0]["country"];
						$provinceName = WC()->countries->states[$pickup_locations[0]["country"]][$pickup_locations[0]["state"]];
						$dataB .= "\"pickpoint\":{
                                \"name\":\"" . addcslashes($pickup_locations[0]["company"], '"\\/') . "\",
                                \"address\": \"" . addcslashes($pickup_locations[0]["address_1"], '"\\/') . addcslashes($pickup_locations[0]["address_2"], '"\\/') . "\",
                                \"city\":\"" . addcslashes($pickup_locations[0]["city"], '"\\/') . "\",
                                \"postalCode\":\"" . addcslashes($pickup_locations[0]["postcode"], '"\\/') . "\",
                                \"province\":\"" . addcslashes($provinceName, '"\\/') . "\",
                                \"country\":\"" . addcslashes($countryISO, '"\\/') . "\",
                                \"firstName\":\"" . addcslashes($pickup_locations[0]["company"], '"\\/') . "\",
                                \"phone\":\"" . addcslashes($pickup_locations[0]["phone"], '"\\/') . "\",
                                \"instructions\":\"" . addcslashes($pickup_locations[0]["note"], '"\\/') . "\"
                            },";
					}
				} else if ($order->has_shipping_method('local_pickup')) {
					$dataB .= "\"pickpoint\":{
                                \"name\":\"" . addcslashes($order->get_shipping_method(), '"\\/') . "\",
                                \"address\": \"" . addcslashes("", '"\\/') . addcslashes("", '"\\/') . "\",
                                \"city\":\"" . addcslashes("", '"\\/') . "\",
                                \"postalCode\":\"" . addcslashes("", '"\\/') . "\",
                                \"province\":\"" . addcslashes("", '"\\/') . "\",
                                \"country\":\"" . addcslashes("", '"\\/') . "\",
                                \"firstName\":\"" . addcslashes("", '"\\/') . "\",
                                \"phone\":\"" . addcslashes("", '"\\/') . "\"
                            },";
				}
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
                },";

				$dataB .= "\"shippingMethod\": {
                    \"amount\": " . $order->get_total_shipping() . ",
                    \"name\":\"" . $order->get_shipping_method() . "\",
                    \"instructions\":\"" . "" . "\"
                },";
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

			$proyecto_datos = $this->doPostOrder($endpoint . "/orders", $paramsb);

			return $proyecto_datos;
		}
		return null;

	}

	private function doPostOrder($url, $params)
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

	#endregion

}
