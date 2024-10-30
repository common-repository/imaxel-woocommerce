<?php

namespace Printspot\ICP\Controllers;

use Printspot\ICP\Models\IcpProductsProjectsModel;
use Printspot\ICP\View;
use WC_Imaxel;
use ImaxelOperations;
use WC_Order;


class AdminController
{

	public static function load()
	{
		add_action('admin_menu', self::class . '::imaxel_register_submenu_projects', 90);

		add_action('wp_ajax_imaxel_admin_edit_project', self::class . '::imaxel_admin_edit_project');
		add_action('wp_ajax_imaxel_admin_delete_project', self::class . '::imaxel_admin_delete_project');
		add_action('wp_ajax_imaxel_admin_duplicate_project', self::class . '::imaxel_admin_duplicate_project');
	}

	public static function imaxel_register_submenu_projects()
	{
		add_submenu_page('woocommerce', 'Imaxel Projects', 'Imaxel Projects', 'manage_options', 'imaxel-projects-submenu-page', self::class . '::imaxel_projects_submenu_page_callback');
		//add_submenu_page('woocommerce', 'Imaxel', 'Imaxel', 'manage_options', 'imaxel-admin-submenu-page',  self::class .  '::imaxel_admin_submenu_page_callback');
	}

	public static function imaxel_admin_submenu_page_callback()
	{

	}

	public static function imaxel_projects_submenu_page_callback()
	{
		global $wpdb;
		wp_enqueue_style('style', plugins_url('/imaxel-woocommerce/assets/css/creative_styles.css'));

		wp_enqueue_script(
			'imaxel_script',
			plugins_url('/imaxel-woocommerce/assets/js/imaxel_admin.js?'),
			array('jquery'),
			WC_Imaxel::plugin_version()
		);
		wp_localize_script(
			'imaxel_script',
			'ajax_object',
			array(
				'url' => admin_url('admin-ajax.php'),
				'backurl' => admin_url('admin.php?page=imaxel-projects-submenu-page'),
				'returnurl' => admin_url('admin.php?page=imaxel-projects-submenu-page')
			)
		);

		echo '<h3>' . __("Imaxel projects", "imaxel") . '</h3>';

		//Search by ID
		$ID_search = isset($_GET['numberid_f']) ? abs((int)$_GET['numberid_f']) : '';
		if ($ID_search != "" && $ID_search > 0) {
			$filter_query = " AND id_project='" . $ID_search . "'";
		} else {
			$filter_query = '';
			$ID_search = "";
		}

		//Search by user
		$ID_user = isset($_GET['imaxel_customer_id']) ? abs((int)$_GET['imaxel_customer_id']) : '';
		if ($ID_user != "") {
			$filter_user_query = " AND id_customer='" . $ID_user . "'";
		} else {
			$filter_user_query = '';
		}

		$query = "SELECT * FROM " . $wpdb->prefix . "imaxel_woo_projects WHERE 1=1  " . $filter_query . " " . $filter_user_query . "";

		$project_array = $wpdb->get_results($query . " ORDER BY id_project DESC LIMIT 100");

		$users = get_users();
		if (empty($users))
			return;

		//Print the filter form
		echo '<form id="posts-filter" class="search-box-imaxel" method="get">
                    <p>
                    <input type="hidden" name="page" value="imaxel-projects-submenu-page"/>
                    <span>
                    <input type="search" style="display: inline-block;vertical-align: middle;"  id="numberid_f" name="numberid_f" value="' . $ID_search . '" placeholder="' . __('Project ID', 'imaxel') . '">
                    </span>
                    ';

		echo '<span><select name="imaxel_customer_id" style="display: inline-block;vertical-align: middle;">';
		echo '<option value="">' . __('Select customer', 'imaxel') . '</option>';
		foreach ($users as $user) {
			echo '<option ';
			if ($ID_user == $user->ID) {
				echo ' selected="selected" ';
			}
			echo ' value="' . $user->ID . '">' . $user->data->display_name . '</option>';
		}
		echo '</select></span>';

		echo '<span>
                <input type="submit" id="search-submit" class="button" value="' . __('Filter', 'imaxel') . '">
                </span>
                </p>
                </form>';

		//here we go with the table head
		echo '<table class="wp-list-table widefat fixed striped pages">
            <thead>
            <tr>
                <th style="width: 110px;">' . __('Project', 'imaxel') . '</th>
                <th style="width: 80px;">' . __('Woo Order', 'imaxel') . '</th>
                <th>' . __('User name', 'imaxel') . '</th>
                <th>' . __('Products', 'imaxel') . '</th>
                <th style="width: 80px;">' . __('Price', 'imaxel') . '</th>
                <th>' . __('Woo Status', 'imaxel') . '</th>
                <th style="width: 110px;display:none">' . __('Status', 'imaxel') . '</th>
                <th>' . __('Action', 'imaxel') . '</th>
            </tr>
            </thead>';


		$pathImg = plugins_url('/imaxel-woocommerce/assets/img/');

		foreach ($project_array as $project) {
			//Cargamos pedido
			$query = "SELECT * FROM " . $wpdb->prefix . "woocommerce_order_itemmeta
                INNER JOIN " . $wpdb->prefix . "woocommerce_order_items ON  " . $wpdb->prefix . "woocommerce_order_items.order_item_id=" . $wpdb->prefix . "woocommerce_order_itemmeta.order_item_id
                WHERE meta_key='proyecto' and meta_value='" . $project->id_project . "'";
			$row = $wpdb->get_row($query);
			unset($order);
			unset($user);
			if ($row) {
				$order = new WC_Order($row->order_id);
			}

			echo '<tr id="project-' . $project->id_project . '">
                    <td>' . $project->id_project . '</td>
                    <td>' . (isset($order) ? "<a href='" . admin_url('post.php?post=' . absint($order->get_id()) . "&action=edit'") . "'>" . $order->get_id() . "</a>" : "") . '</td>
                    ';
			echo '<td>';


			//The customer - link to profile
			$user = get_userdata($project->id_customer);
			if ($user) {
				echo '<a href="' . get_edit_user_link($user->ID) . '">' . esc_attr($user->user_nicename) . '</a>';
			}
			echo '</td>';

			//Product name
			echo '<td>';
			$product = wc_get_product($project->id_product);
			if ($product)
				echo '<a href="' . get_permalink($product->get_id()) . '">' . esc_attr($product->get_title()) . '</a>';
			echo '</td>';
			echo '<td>';

			//Price
			echo $project->price_project;

			//Status in Woo
			echo '</td>
                    <td>' . (isset($order) ? $order->get_status() : "") . '</td>';
			echo '<td style="display:none">';

			//Status in Imaxel
			'</td>';
			echo '<td>';
			echo '<div>
                    ' . (!isset($order) ? '<a id="delete" style="" class="imaxel-btn-delete" title="" href=""><img  src="' . $pathImg . 'delete.png"/></a>' : "")
				. '<a id="edit" style="" class="imaxel-btn-edit" title="" href=""><img  src="' . $pathImg . 'edit.png"/></a>'
				. ($user != false ? '<a id="duplicate" title="" class="imaxel-btn-duplicate" href=""><img  src="' . $pathImg . 'duplicate.png"/></a>' : "")
				. '<a id="buy" title="" href="" style="display:none"><img  src="' . $pathImg . 'buy.png"/></a>
                </div>';

			echo '</td>
            </tr>';
		}
		echo '</table>';
	}

	public static function imaxel_admin_edit_project()
	{
		global $wpdb;
		global $woocommerce;
		$privateKey = get_option("wc_settings_tab_imaxel_privatekey");
		$publicKey = get_option("wc_settings_tab_imaxel_publickey");

		$projectID = intval($_POST["projectID"]);
		if ($projectID > 0) {
			//TODO-dpi should be $row = ProjectModel::origin()->getProject($projectID);
			$sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                        WHERE id_project=' . $projectID;
			$row = $wpdb->get_row($sql);

			$product = wc_get_product($row->id_product);
			$variations = $product->get_children();

			$urlCancel = esc_url($_POST["backURL"]);
			$urlCart = $woocommerce->cart->get_cart_url();
			$cartURLParameters = "";

			$urlSave = "";
			$urlSaveParameters = "";

			if (isset($_POST["returnURL"]))
				$urlCart = esc_url($_POST["returnURL"]);

			$imaxelOperations = new ImaxelOperations();
			$projectUrl = $imaxelOperations->editProject($publicKey, $privateKey, $projectID, $row->id_product, $urlCart, $cartURLParameters, $urlCancel, $urlSave, $urlSaveParameters);
			
			if ($projectUrl)
			{
				$updateTime = current_time('mysql',1);
				
				//TODO-marcs should be $row = ProjectModel::origin()->getProject($projectID);
				$sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
						WHERE id_project =' . $projectID;
				$row = $wpdb->get_row($sql);
				if ($row)
				{
					$sql = "UPDATE " . $wpdb->prefix . "imaxel_woo_projects
							SET date_project='" .  $updateTime .
							"' WHERE id_project=" . $projectID;
					$wpdb->query($sql);
				}
			}
			
			echo $projectUrl;
			die();
		}
	}

	public static function imaxel_admin_duplicate_project()
	{
		global $wpdb;
		global $woocommerce;

		$privateKey = get_option("wc_settings_tab_imaxel_privatekey");
		$publicKey = get_option("wc_settings_tab_imaxel_publickey");

		$projectID = intval($_POST["projectID"]);
		if ($projectID > 0) {

			//TODO-dpi should be $row = ProjectModel::origin()->getProject($projectID);
			$sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                        WHERE id_project=' . $projectID;
			$row = $wpdb->get_row($sql);

			$product = wc_get_product($row->id_product);
			$variations = $product->get_children();

			$urlCancel = esc_url($_POST["backURL"]);

			$urlCart = get_home_url();
			$cartURLParameters = "";

			$urlSave = "";
			$urlSaveParameters = "";

			if (isset($_POST["returnURL"]))
				$urlCart = esc_url($_POST["returnURL"]);

			$imaxelOperations = new ImaxelOperations();

			$projectInfo = $imaxelOperations->duplicateProject($publicKey, $privateKey, $projectID, $row->id_product, $urlCart, $cartURLParameters, $urlCancel, $urlSave, $urlSaveParameters);
			if ($projectInfo)
			{
				$sql = "INSERT INTO " . $wpdb->prefix . "imaxel_woo_projects (id_customer, id_project, type_project,id_product, id_product_attribute, price_project, services_sku)
                        VALUES (
                        " . $row->id_customer . "," . $projectInfo[0] . "," . $row->type_project . "," . $row->id_product . "," . $row->id_product_attribute . "," . $row->price_project .
					", '" . $row->services_sku ."')";
				$wpdb->query($sql);
			}

			echo $projectInfo[1];
			die();
		}
	}

	public static function imaxel_admin_delete_project()
	{
		global $wpdb;
		$projectID = intval($_POST["projectID"]);
		if ($projectID > 0) {
			$sql = 'DELETE FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                        WHERE id_project=' . $projectID;
			$wpdb->query($sql);
		}
		die();
	}
}
