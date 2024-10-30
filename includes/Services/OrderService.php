<?php

namespace Printspot\ICP\Services;


use Exception;

class OrderService
{

    /**
     * newICOorder
     *
     * Imaxel Custom Order
     *
     * TODO: refactor this method
     *
     * @param mixed $orderID
     * @param mixed $dealerID
     * @param mixed $dealerOriginID
     * @param mixed $fromCheckout
     * @return void
     */
    public static function newICOorder($orderID = '', $dealerID = '', $dealerOriginID = '', $fromCheckout = false)
    {
        //set order state
        do_action('icp_order_update_state', $orderID, $fromCheckout);

        //process icp order items
        global $wpdb;
        $icpProjectsTable = $wpdb->prefix . 'icp_products_projects';
        $icpProjectsComponentsTable = $wpdb->prefix . 'icp_products_projects_components';
        $pickpointsTable = $wpdb->prefix . 'imaxel_printspot_shop_pickpoints';
        $shopTable = $wpdb->prefix . 'imaxel_printspot_shop_config';

        if (empty($orderID)) {
            //get icp products from cart
            $cart = WC()->cart->get_cart();
            foreach ($cart as $item) {
                if (isset($item['icp_product'])) {
                    $icpProductsToProcess[] = $item;
                }
            }
        } else {
            //get icp products from order
            $order = wc_get_order($orderID);
            $orderItems = $order->get_items();
            foreach ($orderItems as $item) {
                if (isset($item['icp_product'])) {
                    $icpProductsToProcess[] = $item;
                }
            }
            $printspotPickpointID = empty($order) ? null : $order->get_meta('printspot_pickpoint');

            if (empty($printspotPickpointID) && !empty(get_post_meta($order->get_id(), "printspot_pickpoint"))) {
                $printspotPickpointID = get_post_meta($order->get_id(), "printspot_pickpoint")[0];
            }
        }

        if (!isset($icpProductsToProcess)) {
            return;
        }

        //build shop data
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

        $data_checkout = "";

        //build billing data
        $customer = $order->get_address("billing");
        $data_checkout .= "\"total\": " . $order->get_total() . ",";
        $data_checkout .= "\"discount\": {
                \"amount\":" . $order->get_discount_total() . ",
                \"name\": \"\",
                \"code\": \"\"
            },";
        $data_checkout .= "\"billing\":{
                \"email\":\"" . $customer["email"] . "\",
                \"firstName\":\"" . addcslashes($customer["first_name"], '"\\/') . "\",
                \"lastName\":\"" . addcslashes($customer["last_name"], '"\\/') . "\",
                \"phone\": \"" . addcslashes($customer["phone"], '"\\/') . "\"
            },";

        //build payment data
        if (isset($order->payment_method)) {
            $arrayPaymentsBankTransfer = array("bacs", "cheque");
            $arrayPaymentsCreditCard = array("paypal", "redsys", "myredsys");
            if (in_array($order->payment_method, $arrayPaymentsBankTransfer)) {
                $paymentTypeID = 6;
            } else if (in_array($order->payment_method, $arrayPaymentsCreditCard)) {
                $paymentTypeID = 2;
            } else {
                $paymentTypeID = 3;
            }
            $data_checkout .= "\"payment\":{
                                    \"name\": \"" . $order->payment_method_title . "\",
                                    \"instructions\":\"\",
                                    \"type\": \"" . $paymentTypeID . "\"
                                },";
        }

        //build pickpoint data
        if (isset($printspotPickpointID) && !empty($printspotPickpointID)) {
            $printspotPickpointData = $wpdb->get_row("SELECT * FROM " . $pickpointsTable . " WHERE id='$printspotPickpointID'");
            $data_checkout .= "\"pickpoint\":{
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
                $data_checkout .= "\"pickpoint\":{
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
                $customer = $order->get_address("billing");
                $shipping = $order->get_address("shipping");
                $countryISO = $shipping["country"];
                $provinceName = html_entity_decode(WC()->countries->states[$order->get_shipping_country()][$shipping["state"]], ENT_NOQUOTES, 'UTF-8');
                $data_checkout .= "\"recipient\":{
                        \"address\": \"" . addcslashes($shipping["address_1"], '"\\/') . addcslashes($shipping["address_2"], '"\\/') . "\",
                        \"city\":\"" . addcslashes($shipping["city"], '"\\/') . "\",
                        \"postalCode\":\"" . addcslashes($shipping["postcode"], '"\\/') . "\",
                        \"province\":\"" . addcslashes($provinceName, '"\\/') . "\",
                        \"country\":\"" . addcslashes($countryISO, '"\\/') . "\",
                        \"email\":\"" . $customer["email"] . "\",
                        \"firstName\":\"" . addcslashes($shipping["first_name"], '"\\/') . "\",
                        \"lastName\":\"" . addcslashes($shipping["last_name"], '"\\/') . "\",
                        \"phone\":\"" . addcslashes($customer["phone"], '"\\/') . "\"
                    }," . $shopData . "";

                $data_checkout .= "\"shippingMethod\": {
                        \"amount\": " . $order->get_total_shipping() . ",
                        \"name\":\"" . $order->get_shipping_method() . "\",
                        \"instructions\":\"" . "" . "\"
                    },";
            }
        }

        //build orderData object
        $orderData = "{";

        //checkout data
        $orderPrintspotID = $order->get_meta('_printspot_order_number');
        $checkoutData = "\"saleNumber\":\"" . $orderPrintspotID . "\"";
        $checkoutData = "\"saleNumber\":\"" . $order->id . "\"";//TODO DPI Review in ICP

        if ($data_checkout) {
            $checkoutData = $data_checkout . $checkoutData;
        }

        $orderData .= "\"checkout\": {" . $checkoutData . "},";
        $orderData .= "\"jobs\": [";
        $countProjects = 1;

        foreach ($icpProductsToProcess as $icpProduct) {
            $icpProjects = $icpProduct['icp_project'];
            $getProjectsData = $wpdb->get_row("SELECT * FROM " . $icpProjectsTable . " WHERE id=" . $icpProjects . "");
            $getProjectComponentstData = $wpdb->get_row("SELECT value FROM " . $icpProjectsComponentsTable . " WHERE project=" . $icpProjects . "");
            $getProjectComponentstData->value = str_replace("'", "\'", $getProjectComponentstData->value);
            $projectData = unserialize($getProjectComponentstData->value);

            //get attributes data from project
            foreach ($projectData as $attribute) {
                //build attribute-value data array
                if (isset($attribute['variation'])) {
                    $projectVariation = $attribute['variation'];
                }

                //get external_image_url
                if (isset($attribute['external_url'])) {
                    $externalImageUrl = $attribute['external_url'];
                }
            }

            //get attributes data from variation
            if (!@unserialize($projectVariation)) {
                $variationData = IcpService::getVariationAttributeData($projectVariation, $getProjectsData->site, $getProjectsData->product);
                $projectVariation = array('attributes' => $variationData['attributes']);
            } else {
                $productData = IcpService::loadProductData($getProjectsData->product, $getProjectsData->site);
                $projectAttributesData = unserialize($projectVariation);
                $projectVariation = IcpService::getProjectAttributesData($projectAttributesData, $productData);
            }

            $orderData .= "{
                    \"product\": {
                        \"code\": \"" . $getProjectsData->product . "\",
                        \"name\": { \"default\": \"" . trim($getProjectsData->product_name) . "\" }
                    },";

            $orderData .= "\"units\": $getProjectsData->quantity,";
            $orderData .= "\"form\":[";

            if ($projectVariation['attributes'] !== NULL) {

                //build project attrbiute-values form info

                foreach ($projectVariation['attributes'] as $attribute => $value) {
                    $orderData .= "{
                            \"code\": \"" . $value['id'] . "\",
                            \"name\": {\"default\": \"" . addcslashes(trim($attribute), '"\\/') . "\"},
                            \"value\": {
                                \"type\": \"code_and_name\",
                                \"code\": \"" . addcslashes($value['value']['id'], '"\\/') . "\",
                                \"name\": {\"default\": \"" . addcslashes(trim($value['value']['key']), '"\\/') . "\"}
                            }
                        },";
                }

                //add externaĺ_image url
                if (isset($externalImageUrl)) {
                    $orderData .= "{
                            \"code\": \"external_image_url\",
                            \"name\": {\"default\": \"external_image_url\"},
                            \"value\": {
                                \"type\": \"code_and_name\",
                                \"code\": \"" . $externalImageUrl . "\",
                                \"name\": {\"default\": \"" . trim($externalImageUrl) . "\"}
                            }
                        },";
                }

                $orderData = substr_replace($orderData, "", -1);
            }


            $orderData .= "],";

            //set blocks projects
            $orderData .= "\"blocks\":[";

            $x = 0;
            foreach ($projectData as $k => $block) {

                //set pdf or project data
                if (isset($block['pdf']) || isset($block['dealer_project'])) {

                    if ($x > 0) {
                        $orderData .= ',';
                    }

                    //set pdf data
                    if (isset($block['pdf'])) {
                        $orderData .= "{
                                \"type\": \"pdf\",
                                \"pdf_key\": \"" . $block['pdf']['key'] . "\"
                            }";
                    }

                    //set template editor data
                    if (isset($block['dealer_project'])) {
                        $orderData .= "{
                                \"type\": \"project\",
                                \"project_id\": \"" . $block['dealer_project'] . "\"
                            }";
                    }

                    $x++;
                }
            }

            $orderData .= "]";

            $orderData .= "}";
            if (count($icpProductsToProcess) > $countProjects) $orderData .= ",";
            $countProjects++;
            //set item status to process it
            $itemsState = $icpProduct->get_meta('printspot_state');//TODO DPI Review in ICP
            $itemsState = "accepted";

            $itemsBehaviour = $icpProduct->get_meta('order_behaviour_on_validation');//TODO DPI Review in ICP
            $itemsBehaviour = "nothing";
        }

        $orderData .= "]";
        $orderData .= "}";

        //process order
        if ($itemsState == 'accepted' && $itemsBehaviour == 'nothing') {
            $ids = array_map(function ($item) {
                return $item->get_id();
            }, $icpProductsToProcess);

            do_action('icp_order_remove_error', $ids);

            try {
                $putOrder = self::icpPostOrder($orderData);
            } catch (Exception $e) {
                do_action('icp_order_revert_state', $ids);
                do_action('icp_order_set_error', $ids, $e->getMessage());
                throw $e;
            }

            //asign job_id to order items

            //generate array with order jobs
            $jobsArray = array();
            foreach ($putOrder->jobs as $job) {
                $jobsArray[] = $job->id;
            }

            //generat array with order items
            $icpProductsToProcess;
            foreach ($icpProductsToProcess as $item) {
                $itemsArray[] = $item->get_id();
            }

            //verify that each job corresponds to one item and build ItemsJobsIDS array
            $itemsJobsArray = array_combine($itemsArray, $jobsArray);
            $itemJobsIDS = array();

            foreach ($itemsJobsArray as $key => $value) {
                $itemJobsIDS[] = array(
                    'item' => $key,
                    'job' => $value
                );
            }
            //assign "imaxel_editors_jobs_ids" meta to each item
            foreach ($itemJobsIDS as $itemJobsID) {
                wc_update_order_item_meta($itemJobsID['item'], 'imaxel_editors_jobs_ids', $itemJobsID['job']);
            }

            return $putOrder;
        }
    }

    /**
     * icpPostOrder
     *
     * Imaxel Custom Orders: post order
     *
     * @param mixed $orderData
     * @return void
     */
    public static function icpPostOrder($orderData)
    {
        $token = IcpService::generateToken("icorders:send");

        if ($token !== null) {
            return IcpService::icordersCreateOrder($orderData, $token);
        }

        throw new Exception(__('Invalid credentials', 'imaxel'), 401);
    }

    /**
     * newICOorderJSON
     *
     * IMAXEL CUSTOM ORDERS: new order
     *
     * @param mixed $orderID
     * @param mixed $dealerID
     * @param mixed $dealerOriginID
     * @param mixed $fromCheckout
     * @return json $orderData
     */
    public static function newICOorderJSON($orderID = '', $dealerID = '', $dealerOriginID = '', $fromCheckout = false)
    {
        //process icp order items
        global $wpdb;
        $icpProjectsTable = $wpdb->prefix . 'icp_products_projects';
        $icpProjectsComponentsTable = $wpdb->prefix . 'icp_products_projects_components';
        $pickpointsTable = $wpdb->prefix . 'imaxel_printspot_shop_pickpoints';
        $shopTable = $wpdb->prefix . 'imaxel_printspot_shop_config';

        if (empty($orderID)) {
            //get icp products from cart
            $cart = WC()->cart->get_cart();
            foreach ($cart as $item) {
                if (isset($item['icp_product'])) {
                    $icpProductsToProcess[] = $item;
                }
            }
        } else {
            //get icp products from order
            $order = wc_get_order($orderID);
            $orderItems = $order->get_items();
            foreach ($orderItems as $item) {
                if (isset($item['icp_product'])) {
                    $icpProductsToProcess[] = $item;
                }
            }
            $printspotPickpointID = empty($order) ? null : $order->get_meta('printspot_pickpoint');

            if (empty($printspotPickpointID) && !empty(get_post_meta($order->get_id(), "printspot_pickpoint"))) {
                $printspotPickpointID = get_post_meta($order->get_id(), "printspot_pickpoint")[0];
            }
        }

        //build shop data
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

        $data_checkout = "";

        //build billing data
        $customer = $order->get_address("billing");
        $data_checkout .= "\"total\": " . $order->get_total() . ",";
        $data_checkout .= "\"billing\":{
                \"email\":\"" . $customer["email"] . "\",
                \"firstName\":\"" . addcslashes($customer["first_name"], '"\\/') . "\",
                \"lastName\":\"" . addcslashes($customer["last_name"], '"\\/') . "\",
                \"phone\": \"" . addcslashes($customer["phone"], '"\\/') . "\"
            },";

        //build payment data
        if ($order->get_payment_method()) {
            $arrayPaymentsBankTransfer = array("bacs", "cheque");
            $arrayPaymentsCreditCard = array("paypal", "redsys", "myredsys");
            if (in_array($order->get_payment_method(), $arrayPaymentsBankTransfer)) {
                $paymentTypeID = 6;
            } else if (in_array($order->get_payment_method(), $arrayPaymentsCreditCard)) {
                $paymentTypeID = 2;
            } else {
                $paymentTypeID = 3;
            }
            $data_checkout .= "\"payment\":{
                                    \"name\": \"" . $order->get_payment_method_title() . "\",
                                    \"instructions\":\"\",
                                    \"type\": \"" . $paymentTypeID . "\"
                                },";
        }

        //build pickpoint data
        if (isset($printspotPickpointID) && !empty($printspotPickpointID)) {
            $printspotPickpointData = $wpdb->get_row("SELECT * FROM " . $pickpointsTable . " WHERE id='$printspotPickpointID'");
            $data_checkout .= "\"pickpoint\":{
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
                $data_checkout .= "\"pickpoint\":{
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
                $customer = $order->get_address("billing");
                $shipping = $order->get_address("shipping");
                $countryISO = $shipping["country"];
                $provinceName = html_entity_decode(WC()->countries->states[$order->get_shipping_country()][$shipping["state"]], ENT_NOQUOTES, 'UTF-8');
                $data_checkout .= "\"recipient\":{
                        \"address\": \"" . addcslashes($shipping["address_1"], '"\\/') . addcslashes($shipping["address_2"], '"\\/') . "\",
                        \"city\":\"" . addcslashes($shipping["city"], '"\\/') . "\",
                        \"postalCode\":\"" . addcslashes($shipping["postcode"], '"\\/') . "\",
                        \"province\":\"" . addcslashes($provinceName, '"\\/') . "\",
                        \"country\":\"" . addcslashes($countryISO, '"\\/') . "\",
                        \"email\":\"" . $customer["email"] . "\",
                        \"firstName\":\"" . addcslashes($shipping["first_name"], '"\\/') . "\",
                        \"lastName\":\"" . addcslashes($shipping["last_name"], '"\\/') . "\",
                        \"phone\":\"" . addcslashes($customer["phone"], '"\\/') . "\"
                    }," . $shopData . "";

                $data_checkout .= "\"shippingMethod\": {
                        \"amount\": " . $order->get_total_shipping() . ",
                        \"name\":\"" . $order->get_shipping_method() . "\",
                        \"instructions\":\"" . "" . "\"
                    },";
            }
        }

        //build orderData object
        $orderData = "{";

        //checkout data
        $orderPrintspotID = $order->get_meta('_printspot_order_number');
        //$checkoutData = "\"saleNumber\":\"" . $orderPrintspotID . "\"";
        $checkoutData = "\"saleNumber\":\"" . $order->id . "\"";
        if ($data_checkout) $checkoutData = $data_checkout . $checkoutData;
        $orderData .= "\"checkout\": {" . $checkoutData . "},";
        $orderData .= "\"jobs\": [";
        $countProjects = 1;
        foreach ($icpProductsToProcess as $icpProduct) {
            $icpProjects = $icpProduct['icp_project'];
            $getProjectsData = $wpdb->get_row("SELECT * FROM " . $icpProjectsTable . " WHERE id=" . $icpProjects . "");
            $getProjectComponentstData = $wpdb->get_row("SELECT value FROM " . $icpProjectsComponentsTable . " WHERE project=" . $icpProjects . "");
            $getProjectComponentstData->value = str_replace("'", "\'", $getProjectComponentstData->value);
            $projectData = unserialize($getProjectComponentstData->value);

            //get attributes data from project
            foreach ($projectData as $attribute) {

                //build attribute-value data array
                if (isset($attribute['variation'])) {
                    $projectVariation = $attribute['variation'];
                }

                //get external_image_url
                if (isset($attribute['external_url'])) {
                    $externalImageUrl = $attribute['external_url'];
                }
            }

            //get attributes data from variation
            if (!@unserialize($projectVariation)) {
				$variationData = IcpService::getVariationAttributeData($projectVariation, $getProjectsData->site, $getProjectsData->product,$icpProjects);
				$projectVariation = array('attributes' => $variationData['attributes']);
            } else {
                $productData = IcpService::loadProductData($getProjectsData->product, $getProjectsData->site);
                $projectAttributesData = unserialize($projectVariation);
                $projectVariation = IcpService::getProjectAttributesData($projectAttributesData, $productData);
            }

            $orderData .= "{
                    \"product\": {
                        \"code\": \"" . $getProjectsData->product . "\",
                        \"name\": { \"default\": \"" . trim($getProjectsData->product_name) . "\" }
                    },";

            $orderData .= "\"form\":[";

            if ($projectVariation['attributes'] !== NULL) {

                //build project attrbiute-values form info
                foreach ($projectVariation['attributes'] as $attribute => $value) {
                    $orderData .= "{
                            \"code\": \"" . $value['id'] . "\",
                            \"name\": {\"default\": \"" . addcslashes(trim($attribute), '"\\/') . "\"},
                            \"value\": {
                                \"type\": \"code_and_name\",
                                \"code\": \"" . addcslashes($value['value']['id'], '"\\/') . "\",
                                \"name\": {\"default\": \"" . addcslashes(trim($value['value']['key']), '"\\/') . "\"}
                            }
                        },";
                }


                //add externaĺ_image url
                if (isset($externalImageUrl)) {
                    $orderData .= "{
                            \"code\": \"external_image_url\",
                            \"name\": {\"default\": \"external_image_url\"},
                            \"value\": {
                                \"type\": \"code_and_name\",
                                \"code\": \"" . $externalImageUrl . "\",
                                \"name\": {\"default\": \"" . trim($externalImageUrl) . "\"}
                            }
                        },";
                }

                $orderData = substr_replace($orderData, "", -1);
            }


            $orderData .= "],";

            //set blocks projects
            $orderData .= "\"blocks\":[";

            $x = 0;
            foreach ($projectData as $k => $block) {

                //set pdf or project data
                if (isset($block['pdf']) || isset($block['dealer_project'])) {

                    if ($x > 0) {
                        $orderData .= ',';
                    }

                    //set pdf data
                    if (isset($block['pdf'])) {
                        $orderData .= "{
                                \"type\": \"pdf\",
                                \"pdf_key\": \"" . $block['pdf']['key'] . "\"
                            }";
                    }

                    //set template editor data
                    if (isset($block['dealer_project'])) {
                        $orderData .= "{
                                \"type\": \"project\",
                                \"project_id\": \"" . $block['dealer_project'] . "\"
                            }";
                    }

                    $x++;
                }
            }

            $orderData .= "]";

            $orderData .= "}";
            if (count($icpProductsToProcess) > $countProjects) $orderData .= ",";
            $countProjects++;
            //set item status to process it
            $itemsState = $icpProduct->get_meta('printspot_state');
            $itemsBehaviour = $icpProduct->get_meta('order_behaviour_on_validation');
        }

        $orderData .= "]
                }";

        return $orderData;
    }
}
