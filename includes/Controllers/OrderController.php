<?php

namespace Printspot\ICP\Controllers;

use ImaxelOperations;
use Printspot\ICP\Models\IcpProductsProjectsModel;
use Printspot\ICP\View;
use Printspot\ICP\Services\OrderService;
use WC_Order;

class OrderController
{

    /**
     * load generic method
     */
    public static function load()
    {
        self::defineFunctionsIcp();
    }

    /**
     * defineFunctionsIcp handles function hooks
     */
    public static function defineFunctionsIcp()
    {
        if (!wp_next_scheduled('Printspot\ICP\Controllers\OrderController::imaxel_cron_process_orders')) {
            wp_schedule_event(time(), 'hourly', 'Printspot\ICP\Controllers\OrderController::imaxel_cron_process_orders');
        }
        add_action('woocommerce_order_status_processing', "Printspot\ICP\Controllers\OrderController::imaxel_woocommerce_order_status_processing");
    }

    /**
     * imaxel_cron_process_orders handles cron process defined in wp_schedule_event
     */
    public static function imaxel_cron_process_orders()
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "imaxel_woo_orders WHERE imaxel_creative_id IS NULL or imaxel_icp_id IS NULL");
        foreach ($rows as $row) {
            if (!$row["imaxel_creative_id"] || $row["imaxel_creative_id"]==-1 || !$row["imaxel_icp_id"] || !$row["imaxel_icp_id"]==-1) {
                //TODO: DPI Add log
                $orderResponse=OrderController::imaxel_woocommerce_order_status_processing($row["woocommerce_id"], false, false);
            }
        }
    }

    /**
     * imaxel_woocommerce_order_status_processing handles processing of wocommerce ordesr
     * @param $orderID
     * @param bool $checkAutomaticProcessing
     * @param bool $isReprocess
     * @return array|bool|void
     * @throws \Exception
     */
    public static function imaxel_woocommerce_order_status_processing($orderID, $checkAutomaticProcessing = true, $isReprocess = false)
    {
        global $wpdb;
        $response = array();

        $existsOrder = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "imaxel_woo_orders WHERE woocommerce_id=" . $orderID);
        if(!$existsOrder) {
            //Check if there are ICP or Creative
            $order = wc_get_order($orderID);
            $orderItems = $order->get_items();
            $existsCreativeProducts=false;
            $existsICPProducts=false;
            foreach ($orderItems as $item) {
                if (isset($item['icp_product'])) {
                    $existsICPProducts=true;
                }
                if (isset($item['proyecto'])) {
                    $existsCreativeProducts=true;
                }
            }

            if(!$existsICPProducts && !$existsCreativeProducts){
                $response["msg"]="No creative product available";
                return false;
            }

            $existsICPProducts= $existsICPProducts==true ? -1 : null;
            $existsCreativeProducts= $existsCreativeProducts==true ? -1 : null;
            $sql = "INSERT INTO " . $wpdb->prefix . "imaxel_woo_orders (woocommerce_id, imaxel_creative_id, imaxel_icp_id, created_date)
                        VALUES (
                        " . $orderID . "," . $existsCreativeProducts . ",". $existsICPProducts . ",NOW()"
                . ")";
            $wpdb->query($sql);
        }

        if ($checkAutomaticProcessing == false) {
            $automaticProcessing = get_option("wc_settings_tab_imaxel_automaticproduction");
            if ($automaticProcessing != "yes")
                return;
        }

        //ICP Processing
        $icpOrderResponse = OrderService::newICOorder($orderID);
        if (isset($icpOrderResponse)) {
            $sql = "UPDATE " . $wpdb->prefix . "imaxel_woo_orders SET imaxel_icp_id=" . $icpOrderResponse->id . ",updated_date=NOW() WHERE woocommerce_id=" . $orderID;
            $wpdb->query($sql);
            $response["icp"] = "ICP:" . $icpOrderResponse->id;
        }

        //Creative processing
        $imaxelOperations = new ImaxelOperations();
        $privateKey = get_option("wc_settings_tab_imaxel_privatekey");
        $publicKey = get_option("wc_settings_tab_imaxel_publickey");
        $order = new WC_Order($orderID);
        $items = $order->get_items();
        $customer = $order->get_address("billing");
        $shipping = $order->get_address("shipping");
        $itemsHtml = array();

        foreach ($items as $item) {
            if (function_exists('WC') && (version_compare(WC()->version, "3.0.0") >= 0)) {
                $projectID = $item["item_meta"]["proyecto"];
            }
            else {
                $projectID = $item["item_meta"]["proyecto"][0];
            }
            if ($projectID) {
				//TODO-dpi should be $row = ProjectModel::origin()->getProject($projectID);
                $sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                        WHERE id_project =' . $projectID;
                $row = $wpdb->get_row($sql);
                if ($row) {
                    $itemsHtml[] = $item;
                }
            }
        }

        if (sizeof($itemsHtml) > 0) {
            $creativeOrderResponse = $imaxelOperations->processOrder($publicKey, $privateKey, $order, $itemsHtml, $customer, $shipping,$isReprocess);
            if ($creativeOrderResponse !== null) {
                $creativeOrderResponse=json_decode($creativeOrderResponse);
                if($creativeOrderResponse->id) {
                    $dealerOrderId = $creativeOrderResponse->id;
                    update_post_meta($orderID, 'dealer_order_id', $dealerOrderId);
                    $sql = "UPDATE " . $wpdb->prefix . "imaxel_woo_orders SET imaxel_creative_id=" . $dealerOrderId . ",updated_date=NOW() WHERE woocommerce_id=" . $orderID;
                    $wpdb->query($sql);

					$itemsArray = array();
					foreach ($order->get_items() as $item_id => $item) {
						$itemsArray[] = $item_id;
					}
					$jobsArray = array();
					foreach ($creativeOrderResponse->jobs as $job) {
						$jobsArray[] = $job->id;
					}
					$itemsJobsArray = array_combine($itemsArray, $jobsArray);
					$itemJobsIDS = array();
					foreach ($itemsJobsArray as $key => $value) {
						$itemJobsIDS[] = array(
							'item' => $key,
							'job' => $value
						);
					}
					foreach ($itemJobsIDS as $itemJobsID) {
						wc_update_order_item_meta($itemJobsID['item'], 'imaxel_editors_jobs_ids', $itemJobsID['job']);
					}

                    $response["creative"] = "Creative:" . $dealerOrderId;
                }
                else{
                    $response["creative"] = "Creative:" . $creativeOrderResponse->message;
                }
            }
        }

        return $response;
    }
}
