<?php

namespace Printspot\ICP\Controllers;

use ImaxelOperations;
use Printspot\ICP\Models\IcpProductsProjectsModel;
use Printspot\ICP\View;
use WC_Order;
use WP_Query;

class MyAccountController
{
	public static function load()
	{
		self::defineFunctionsIcp();
	}

	public static function defineFunctionsIcp()
	{
		//register new endpoint
		add_action('init', 'Printspot\ICP\Controllers\MyAccountController::icpNewEndpoints');
		//get new endpoint content
		add_action('woocommerce_account_icp_projects_endpoint', 'Printspot\ICP\Controllers\MyAccountController::icpEndpointContent');
		//add endpoint to my account menu
		add_filter('woocommerce_account_menu_items', 'Printspot\ICP\Controllers\MyAccountController::icpMenuOrder', 20);

		add_action('wp_ajax_imaxel_edit_project', 'Printspot\ICP\Controllers\MyAccountController::editProject');
		add_action('wp_ajax_imaxel_delete_project', 'Printspot\ICP\Controllers\MyAccountController::deleteProject');
		add_action('wp_ajax_imaxel_duplicate_project', 'Printspot\ICP\Controllers\MyAccountController::cloneProject');
	}

	/**
	 * Register new endpoints to use inside My Account page.
	 */
	public static function icpNewEndpoints()
	{
		add_rewrite_endpoint('icp_projects', EP_ROOT | EP_PAGES);
	}

	/**
	 *  show icp projects table in my account section
	 */
	public static function icpEndpointContent()
	{

		$pluginName = "imaxel-woocommerce";
		global $woocommerce;

		//Get projects in cart
		$cart_object = $woocommerce->cart;
		$projectsInCart = array();
		foreach ($cart_object->cart_contents as $key => $value) {
			if (array_key_exists("variation", $value)) {
				if (array_key_exists("attribute_proyecto", $value["variation"])) {
					$projectsInCart[] = "services_" . $value["attribute_proyecto"];
				}
			}
			if (array_key_exists("icp_project", $value)) {
				$projectsInCart[] = "icp_" . $value["icp_project"];
			}
		}

		//Get orders from user
		$filters = array(
			'post_status' => 'any',
			'post_type' => 'shop_order',
			'posts_per_page' => 2000,
			'paged' => 1,
			'orderby' => 'modified',
			'order' => 'DESC',
			'meta_query' => array(
				array(
					'key' => '_customer_user',
					'value' => get_current_user_id(),
					'compare' => '='
				)
			)
		);
		$loop = new WP_Query($filters);
		//LOOP DATA ORDERS
		while ($loop->have_posts()) {
			$loop->the_post();
			$order = new WC_Order($loop->post->ID);
			$user_id = (method_exists($order, "get_user_id") ? $order->get_user_id() : $order->get_id());
			$data_extra = $order->get_items();
			foreach ($data_extra as $producto) {
				if (isset($producto["proyecto"])) {
					$projectID = $producto["proyecto"];
				} else if (isset($producto["icp_project"])) {
					$projectID = $producto["icp_project"];
				}

				if (isset($projectID)) {
					$order_data["" . $projectID . ""] = array(
						'order_id' => (method_exists($order, "get_id") ? $order->get_id() : $order->get_id()),
						'printspot_order_id' => $order->get_meta('_printspot_order_number'),
						'status_WC' => $order->get_status(),
						'line_total' => $producto["line_total"],
						'client_id' => '' . (method_exists($order, "get_billing_first_name") ? $order->get_billing_first_name() : $order->billing_first_name) . ' ' . (method_exists($order, "get_billing_first_name") ? $order->get_billing_last_name() : $order->get_billing_last_name) . '',
						'user_id' => '' . $user_id . ''
					);
					$order_data["" . $projectID . "_WC"] = new WC_Order($loop->post->ID);
				}
			}
		}
		wp_reset_query();

		//get user icp projects
		$userId = get_current_user_id();
		$userProjects = IcpProductsProjectsModel::origin()->getAll(['user' => $userId], ['id' => 'DESC']);
		$icpUrl = get_option('icp_url');

		foreach ($userProjects as $key => $project) {
			$project->icp = 1;
			$url = $icpUrl . '?id=' . $project->product . '&site=' . $project->site . '&block=' . $project->first_block . '&icp_project=' . $project->id;
			$url .= !empty($project->woo_product) ? '&wproduct=' . $project->woo_product : null;
			$userProjects[$key]->urlProject = $url;
			if (in_array("icp_" . $project->id, $projectsInCart)) {
				$userProjects[$key]->inCart = true;
			}
			if (isset($order_data)) {
				if (isset($order_data[$project->id])) {
					$order = $order_data[$project->id];
					$userProjects[$key]->order = $order;
				}
			}
		}

		//Services projects
		global $wpdb;
		$userID = get_current_user_id();
		$sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
            WHERE id_customer =' . $userID;
		$servicesProjects = $wpdb->get_results($sql);

		foreach ($servicesProjects as $key => $project) {
			$project->date = $project->date_project;
			$project->icp = 0;
			if (in_array("services_" . $project->id_project, $projectsInCart)) {
				$servicesProjects[$key]->inCart = true;
			}
			if (isset($order_data)) {
				if (isset($order_data[$project->id_project])) {
					$order = $order_data[$project->id_project];
					$servicesProjects[$key]->order = $order;
				}
			}
		}

		$allProjects = array_merge($servicesProjects, $userProjects);
		usort($allProjects, function ($a, $b) {
			return strcmp($b->date, $a->date);
		});

		View::addScript($pluginName . ':assets/js/imaxel_myaccount.js');
		wp_localize_script(
			'assets-js-imaxel_myaccount-js',
			'ajax_object',
			array(
				'url' => admin_url('admin-ajax.php'),
				'literal_delete_warning' => __('Are you sure you want to delete this project?', 'imaxel'),
				'backurl' => get_permalink()
			)
		);

		$data = [
			'userProjects' => $userProjects,
			'servicesProjects' => $servicesProjects,
			'allProjects' => $allProjects
		];


		View::load($pluginName . ':my_account/icp_projects.php', $data);
	}

	/**
	 * Edit my account menu order
	 * @return array
	 */
	public static function icpMenuOrder($accountMenu)
	{

		$menuOrder = [];
		$menuOrder['icp_projects'] = __('Projects', 'imaxel');
		$menuOrder = apply_filters('set_icp_menu_my_account', $menuOrder);

		$menuOrder = array_merge($menuOrder, $accountMenu);
		return $menuOrder;
	}

	public static function deleteProject()
	{
		global $wpdb;
		$projectID = intval($_POST["projectID"]);
		$projectICP = intval($_POST["projectICP"]);
		$userID = get_current_user_id();

		if ($projectICP == false) {
			if ($projectID > 0 && $userID) {
				$sql = 'DELETE FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                        WHERE id_customer =' . $userID . " AND id_project=" . $projectID;
				$wpdb->query($sql);
			}
		} else {
			if ($projectID > 0 && $userID) {
				$sql = 'DELETE FROM ' . $wpdb->prefix . 'icp_products_projects
                        WHERE user =' . $userID . " AND id=" . $projectID;
				$wpdb->query($sql);
			}
		}
		wp_send_json_success();
		wp_die();
	}

	public static function editProject($cart_item_id, $returnCheckoutPage = null)
	{
		global $wpdb;
		global $woocommerce;

		$privateKey = get_option("wc_settings_tab_imaxel_privatekey");
		$publicKey = get_option("wc_settings_tab_imaxel_publickey");
		$userID = get_current_user_id();
		if (!empty($cart_item_id)) {
			$projectID = WC()->cart->cart_contents[$cart_item_id]["attribute_proyecto"]; //#WST-26
			$id_product = WC()->cart->cart_contents[$cart_item_id]["product_id"];
			$variationID = WC()->cart->cart_contents[$cart_item_id]["variation_id"];
		} else {
			if (empty($projectID) && isset($_POST["projectID"])) {
				$projectID = intval($_POST["projectID"]);//#WST-26 No cart_item_id in myaccount -> edit
				$sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                        WHERE id_customer =' . $userID . " AND id_project=" . $projectID;
				$row = $wpdb->get_row($sql);
				$id_product = $row->id_product;
				$variationID = $row->id_product_attribute;
			}
		}

		if ($projectID > 0) {
			$urlCancel = esc_url($_POST["backURL"]);
			$urlCart = get_home_url();

			if (function_exists('WC') && (version_compare(WC()->version, "3.1.0") >= 0)) {
				$cartURLParameters = "?imx-add-to-cart=" . $id_product . "&attribute_proyecto=" . $projectID . "&imx_product_type=0";
			} else {
				$cartURLParameters = "?add-to-cart=" . $id_product . "&attribute_proyecto=" . $projectID . "&imx_product_type=0";
			}

			$product = wc_get_product($id_product);
			if (!$product->is_type('simple')) {
				$cartURLParameters = $cartURLParameters . "&variation_id=" . $variationID;
			}

			if (isset($_POST["returnURL"]))
				$urlCart = esc_url($_POST["returnURL"]);

			//#WST-26
			if (!empty($cart_item_id)) {
				$cartURLParameters = $cartURLParameters . "&imx_cart_item_id=" . $cart_item_id;
			}

			$imaxelOperations = new ImaxelOperations();
			$projectUrl = $imaxelOperations->editProject($publicKey, $privateKey, $projectID, $id_product, $urlCart, $cartURLParameters, $urlCancel, "", "");
			echo $projectUrl;
			die();
		}
	}

	public static function cloneProject()
	{
		global $wpdb;
		global $woocommerce;

		$privateKey = get_option("wc_settings_tab_imaxel_privatekey");
		$publicKey = get_option("wc_settings_tab_imaxel_publickey");

		$projectID = intval($_POST["projectID"]);
		if ($projectID > 0) {
			$userID = get_current_user_id();

			$sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                        WHERE id_customer =' . $userID . " AND id_project=" . $projectID;
			$row = $wpdb->get_row($sql);

			$product = wc_get_product($row->id_product);
			$variations = $product->get_children();

			$urlCancel = esc_url($_POST["backURL"]);
			$urlCart = get_home_url();
			if (function_exists('WC') && (version_compare(WC()->version, "3.1.0") >= 0)) {
				$cartURLParameters = "?imx-add-to-cart=" . $row->id_product . "&variation_id=" . $row->id_product_attribute . "&imx_product_type=" . $row->type_project;
			} else {
				$cartURLParameters = "?add-to-cart=" . $row->id_product . "&variation_id=" . $row->id_product_attribute . "&imx_product_type=" . $row->type_project;
			}

			$saveURL = get_home_url();
			$saveURLParameters = "?imx-add-to-project=" . $row->id_product . "&variation_id=" . $row->id_product_attribute . "&imx_product_type=" . $row->type_project;

			if (isset($_POST["returnURL"]))
				$urlCart = esc_url($_POST["returnURL"]);

			$imaxelOperations = new ImaxelOperations();

			$projectUrl = $imaxelOperations->duplicateProject($publicKey, $privateKey, $projectID, $row->id_product, $urlCart, $cartURLParameters, $urlCancel, $saveURL, $saveURLParameters);
			if ($projectUrl) {
				echo $projectUrl[1];
			}
			die();
		}
	}
}
