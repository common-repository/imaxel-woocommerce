<?php

/**
* Plugin Name: Imaxel WooCommerce
* Plugin URI: http://www.imaxel.com
* Description: A WordPress plugin to integrate Imaxel editors with WooCommerce and WordPress.
* Version: 2.5.61
* WC requires at least: 3.0.0
* WC tested up to: 5.9.3
* Text Domain: imaxel
* Domain Path: /language/
* Author: Imaxel
* Author URI: http://www.imaxel.com
* License: All right reserved 2021
*/

use Printspot\ICP\Controllers\AdminController;
use Printspot\ICP\Controllers\ProductController;
use Printspot\ICP\Controllers\EditorController;
use Printspot\ICP\Controllers\IcpController;
use Printspot\ICP\Controllers\MyAccountController;
use Printspot\ICP\Controllers\OrderController;
use Printspot\ICP\Services\EditorService;
use Printspot\ICP\Services\IcpPdfService;
use Printspot\ICP\Services\IcpService;
use Printspot\ICP\Models\IcpProductsProjectsModel;
use Printspot\ICP\Services\CartService;
use Printspot\ICP\Services\OrderService;
use Printspot\ICP\Services\ProductService;
use Printspot\ICP\Services\ShopService;
use Printspot\ICP\View;

include_once __DIR__ . '/vendor/autoload.php';

if ( ! class_exists( 'WC_Imaxel' ) ) {

    class WC_Imaxel
    {

        var $imaxel_db_version="1.0.0.0";
		var $plugin_version;

		public static function plugin_version(){
			$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
			return $plugin_data['Version'];
		}

        public function __construct()
        {
			$this->plugin_version = WC_Imaxel::plugin_version();

            register_activation_hook(__FILE__, array($this, 'imaxel_woocommerce_install'));
            add_action('wpmu_new_blog',  array($this, 'imaxel_woocommerce_install'), 10, 6);
			add_action( 'wp_insert_site', array($this, 'imaxel_woocommerce_install'), 10, 1);

            add_action('init', array($this, 'myplugin_load_textdomain'));
            add_action('init', array($this, 'imaxel_init'));
            add_action('init', array($this, 'imaxel_icp_init'));

            // called only after woocommerce has finished loading
            add_action('woocommerce_init', array(&$this, 'woocommerce_loaded'));

            // called after all plugins have loaded
            add_action('plugins_loaded', array(&$this, 'plugins_loaded'));

            // called after all plugins, active theme and WP have loaded
            add_action('wp_loaded', array($this, 'wp_loaded_icp_view'));

            /*Tab de configuracion de woocommerce*/
            add_filter('woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50);

            //#HELP-8 ICP (imaxel_icp_woocommerce_install)
            add_action('woocommerce_settings_tabs_settings_tab_imaxel', __CLASS__ . '::settings_tab');
            add_action('woocommerce_settings_tabs_settings_tab_imaxel_icp', __CLASS__ . '::settings_tab_icp');

            add_action('woocommerce_update_options_settings_tab_imaxel', __CLASS__ . '::update_settings');
            add_action('woocommerce_update_options_settings_tab_imaxel_icp', __CLASS__ . '::update_settings_icp');

            /*Hooks de configuracion de producto woocommerce*/
            add_filter('woocommerce_product_data_tabs', array($this, 'imaxel_product_data_tab'));
            add_action('woocommerce_product_data_panels', array($this, 'imaxel_product_data_fields'));
            add_action('woocommerce_product_after_variable_attributes', array($this, 'imaxel_product_data_variable_fields'), 10, 3);
            add_action('woocommerce_save_product_variation', array($this, 'imaxel_product_data_variable_fields_save'), 10, 2);
            add_action('woocommerce_process_product_meta', array($this, 'imaxel_product_data_fields_save'));
            add_action('save_post', array($this, 'imaxel_product_save'));

            /*MICUENTA*/
            #HELP-8 Moved to ICP
            //add_action('woocommerce_before_my_account', array($this, 'imaxel_my_projects_imaxel'));

            /*AJAX*/
            if (is_admin()) {
                add_action('wp_ajax_imaxel_update_products', array($this, 'imaxel_update_products'));
                add_action('wp_ajax_imaxel_product_get_variants', array($this, 'imaxel_product_get_variants'));
            }

            add_action('wp_ajax_imaxel_wrapper', array($this, 'imaxel_wrapper'));
            add_action('wp_ajax_nopriv_imaxel_wrapper', array($this, 'imaxel_wrapper'));

            /*Cart*/
            add_action('woocommerce_add_to_cart', array($this, 'imaxel_add_to_cart'), 10, 2);
            add_action('woocommerce_before_calculate_totals', array($this, 'imaxel_custom_cart_price'), 10);
            add_filter('woocommerce_get_item_data', array($this, 'imaxel_cart_prints'), 10, 2);
            add_filter('woocommerce_cart_item_quantity', array($this, 'blockQtyForPrintsOnCartUpdate'), 10, 2);
            add_filter('woocommerce_cart_item_quantity', array($this, 'icp_block_cart_qty'), 10, 2);
            add_action('woocommerce_update_cart_action_cart_updated', IcpService::class . '::updateIcpCartQtyFromCart');
            add_action( 'woocommerce_before_cart', array($this, 'check_deleted_project_in_cart'), 10, 2);

            /*Checkout*/
            add_action('woocommerce_checkout_create_order_line_item', array($this,'imaxel_checkout_create_order_line_item'), 10, 4 ); //#WST-26
            add_action( 'woocommerce_before_checkout_form', array($this, 'check_deleted_project_in_cart'), 10, 2);

            /*ADMIN*/
            add_action('woocommerce_order_item_add_action_buttons', array($this, 'action_imaxel_woocommerce_order_item_add_action_buttons'), 10, 1);
            add_action('save_post', array($this, 'action_imaxel_order_reprocess'), 10, 3);
            add_action('woocommerce_order_item_meta_end', array($this, 'action_imaxel_woocommerce_order_item_meta_end'), 10, 3);

            /*Redirecciones*/
            add_action('template_redirect', array($this, 'imaxel_redirection_function'), 1, 2);
            add_action('after_setup_theme', array($this, 'imaxel_after_setup_theme'));

            /*HOOK LOGIN/REGISTER USER*/
            add_action('wp_login', array($this, 'imaxel_login_user'), 10, 3);
            add_action('user_register', array($this, 'imaxel_register_user'));

			// show the custom meta data on the cart and checkout pages
			add_filter( 'woocommerce_order_item_get_formatted_meta_data', ProductService::class . '::translateItemMeta', 10, 2 );

            include_once('includes/imaxel_operations.php');
            include_once('includes/imx-admin-notices.php');
            include_once('includes/imaxel-icp-operations.php');//#HELP-8 ICP
            //=======================================================================================================//

            //MARC: EDIT PROJECT FROM CART PAGE
            add_filter('woocommerce_get_item_data', array($this, 'imaxel_edit_project_form_cart_button'), 10, 2);
            add_action('wp_ajax_edit_project_form_cart_function', array($this, 'imaxel_edit_project_form_cart_function'));
            add_action('wp_ajax_nopriv_edit_project_form_cart_function', array($this, 'imaxel_edit_project_form_cart_function'));

            //=======================================================================================================//

            //ICP #HELP-8
            //generate rewrite rules and query vars
            add_filter('query_vars', array($this, 'icpQueryVars'));
            add_action('wp_enqueue_scripts', array($this, 'icpLoadScripts'));
            add_shortcode('icp_view', array($this, 'load_icp_view'));

            MyAccountController::load();			//this controller is called now otherwise not works


            //AJAX Calls

            //validate pdf
            add_action('wp_ajax_nopriv_validatePdfUploader', array($this, 'validatePdfUploader'));
            add_action('wp_ajax_validatePdfUploader', array($this, 'validatePdfUploader'));

            //save pdf url
            add_action('wp_ajax_nopriv_savePdfUploader', array($this, 'savePdfUploader'));
            add_action('wp_ajax_savePdfUploader', array($this, 'savePdfUploader'));

            //add item to cart
            add_action('wp_ajax_nopriv_icpAddItemToCart', array($this, 'icpAddItemToCart'));
            add_action('wp_ajax_icpAddItemToCart', array($this, 'icpAddItemToCart'));

            //return to block
            add_action('wp_ajax_nopriv_returnToBlock', array($this, 'returnToBlock'));
            add_action('wp_ajax_returnToBlock', array($this, 'returnToBlock'));

            //save project
            //TODO: DPI no esta en repositorio printspot-custom-products
            //add_action('wp_ajax_nopriv_saveICPproject', array($this, 'saveICPproject'));
            //add_action('wp_ajax_saveICPproject', array($this, 'saveICPproject'));

			//IcpController::load();
            // indicates we are running the admin
            if (is_admin()) {
            }

            // indicates we are being served over ssl
            if (is_ssl()) {
            }
        }

        #region create tables db y idiomas

        //Funcion para crear tablas en la activacion del plugin
        public function imaxel_woocommerce_install($network_wide) {

            function imaxel_woocommerce_install_single_site() {

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                global $wpdb;

                $table_name = $wpdb->prefix . 'imaxel_woo_products';
                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE $table_name (
                    `id` int(10) NOT NULL AUTO_INCREMENT,
                      `code` varchar(255) CHARACTER SET utf8 NOT NULL,
                      `name` varchar(255) CHARACTER SET utf8 NOT NULL,
                      `type` tinyint(4) NOT NULL,
                      `price` float NOT NULL,
                      `variants` TEXT NULL,
                      PRIMARY KEY (`id`)

                ) $charset_collate;";
                dbDelta($sql);

                $table_name = $wpdb->prefix . 'imaxel_woo_projects';
                $sql = "CREATE TABLE $table_name (
                    `id_customer` int(10) unsigned NOT NULL,
                    `id_project` int(10) NOT NULL,
                    `type_project` tinyint(4) NOT NULL,
                    `id_product` int(10) NOT NULL,
                    `id_product_attribute` int(10) NOT NULL,
                    `price_project` float NOT NULL,
                    `weight_project` FLOAT NULL DEFAULT 0,
                    `description_project` TEXT NULL,
                    `prints_project` tinyint(1) NOT NULL DEFAULT 0,
                    `prints_requested_project` int NOT NULL DEFAULT 0,
                    `date_project` DATETIME NULL DEFAULT 0,
                    `services_sku` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
                    KEY `id_customer` (`id_customer`),
                    PRIMARY KEY (`id_customer`,`id_project`)
                ) $charset_collate;";
                dbDelta($sql);

                //#HELP-8 ICP (imaxel_icp_woocommerce_install)
                $table_name = $wpdb->prefix . 'icp_products_woocommerce';
                $sql = "CREATE TABLE $table_name (
                    `id` int(10) NOT NULL AUTO_INCREMENT,
                    `code` varchar(255) CHARACTER SET utf8 NOT NULL,
                    `name` varchar(255) CHARACTER SET utf8 NOT NULL,
                    `site` varchar(255) CHARACTER SET utf8 NOT NULL,
                    `first_block` int(10),
                    PRIMARY KEY (`id`)
                ) $charset_collate;";
                dbDelta($sql);

                //#HELP-8 ICP (imaxel-printspot-custom-products-wordpress)
                //create wp_imaxel_printspot_custom_products_products
                $table_name = $wpdb->prefix . 'icp_products_projects';
                $sql = "CREATE TABLE $table_name (
                    `id` int(10) NOT NULL AUTO_INCREMENT,
                    `product` varchar(255) CHARACTER SET utf8,
                    `product_name` LONGTEXT CHARACTER SET utf8,
                    `woo_product` int(10),
                    `variation` int(10),
                    `variation_price` LONGTEXT CHARACTER SET utf8,
                    `first_block` int(10),
                    `site` int(10),
                    `user` int(10),
                    `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `dealer` int(10),
                    `production_time` int(10),
                    `quantity` int(10),
                    `project_name` varchar(255) CHARACTER SET utf8,
                    PRIMARY KEY (`id`)
                ) $charset_collate;";
                //create table
                dbDelta($sql);

                //create imaxel_printspot_custom_products_projects_components
                $table_name = $wpdb->prefix . 'icp_products_projects_components';
                $sql = "CREATE TABLE $table_name (
                    `id` int(10) NOT NULL AUTO_INCREMENT,
                    `project` int(10),
                    `block` int(10),
                    `value` longtext CHARACTER SET utf8,
                    `readable_value` longtext CHARACTER SET utf8,
                    PRIMARY KEY (`id`),
                    CONSTRAINT " . $wpdb->prefix . "icp_project_comp_proj FOREIGN KEY (project) REFERENCES " . $wpdb->prefix . "icp_products_projects(id) ON DELETE CASCADE ON UPDATE RESTRICT
                ) $charset_collate;";
                //create table
                dbDelta($sql);

                //#HELP-8 ICP
                $table_name = $wpdb->prefix . 'imaxel_woo_orders';
                $sql = "CREATE TABLE $table_name (
                    `woocommerce_id` int(10) NOT NULL,
                    `imaxel_creative_id` int(10) NULL,
                    `imaxel_icp_id` int(10) NULL,
                    `created_date` DATETIME NOT NULL,
                    `updated_date` DATETIME NULL,
                    PRIMARY KEY (`woocommerce_id`)
                ) $charset_collate;";
                //create table
                dbDelta($sql);
            }

            // This function registers the custom table with WordPress and should be called early on every request.
            function myplugin_register_table() {
                global $wpdb;

                // If the table name is already registered, just bail.
                if (in_array('imaxel_woo_products', $wpdb->tables, true)) {
                    return;
                }

                $wpdb->myplugin_table = $wpdb->prefix . 'imaxel_woo_products';
                $wpdb->tables[] = 'imaxel_woo_products';
            }

            // This function registers the custom table with WordPress and should be called early on every request.
            function myplugin_register_table_2() {
                global $wpdb;

                // If the table name is already registered, just bail.
                if (in_array('imaxel_woo_projects', $wpdb->tables, true)) {
                    return;
                }

                $wpdb->myplugin_table = $wpdb->prefix . 'imaxel_woo_products';
                $wpdb->tables[] = 'imaxel_woo_projects';
            }

            function myplugin_register_table_3() {
                global $wpdb;

                // If the table name is already registered, just bail.
                if (in_array('icp_products_woocommerce', $wpdb->tables, true)) {
                    return;
                }

                $wpdb->myplugin_table = $wpdb->prefix . 'icp_products_woocommerce';
                $wpdb->tables[] = 'icp_products_woocommerce';
            }

            if (
                in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))
                || is_plugin_active_for_network("woocommerce/woocommerce.php")
            ) {
                global $wpdb;

                // Register the name of your custom table with WordPress (see function below).
                // This function call remains here since it needs to happen globally.
                myplugin_register_table();
                myplugin_register_table_2();

                if ($network_wide) {

                    // Retrieve all site IDs from this network (WordPress >= 4.6 provides easy to use functions for that).
                    if (function_exists('get_sites') && function_exists('get_current_network_id')) {
                        $site_ids = get_sites(array('fields' => 'ids', 'network_id' => get_current_network_id()));
                    } else {
                        $site_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs WHERE site_id = $wpdb->siteid;");
                    }

                    foreach ($site_ids as $site_id) {
                        switch_to_blog($site_id);
                        imaxel_woocommerce_install_single_site();
                        restore_current_blog();
                    }
                } else {
                    imaxel_woocommerce_install_single_site();
                }
            } else {
                wp_die('Imaxel WooCommerce plugin requires woocommerce to be active');
            }
        }

        public function myplugin_load_textdomain() {
            $plugin_dir = basename(dirname(__FILE__)) . '/language/';
            load_plugin_textdomain('imaxel', false, $plugin_dir);
        }

        #4864
        public function imaxel_init() {
            remove_all_filters("woocommerce_registration_redirect");
            //Funciones de redireccion con la prioridad mas alta
            add_filter('woocommerce_login_redirect', array($this, 'imaxel_wc_custom_user_redirect'), PHP_INT_MAX, 2);
            add_filter('woocommerce_registration_redirect', array($this, 'imaxel_wc_custom_user_redirect'), PHP_INT_MAX, 2);
        }

        public function imaxel_icp_init()
        {
            //#HELP-8 ICP (imaxel_icp_woocommerce_install)
            if (!session_id()) {
                session_start();
            }

            IcpController::load();
            EditorController::load();
            OrderController::load();
			ProductController::load();
			AdminController::load();

            // DEV-1060 - Close the session to avoid interfering with the REST API.
            session_write_close();
        }

        #endregion

        #region Funciones administracion edicion de pedido
        public function action_imaxel_woocommerce_order_item_add_action_buttons($order) {
            $items = $order->get_items();
            $showImaxelButton = false;
            foreach ($items as $item) {
                if ($item["proyecto"] || $item["icp_product"]) {
                    $showImaxelButton = true;
                    break;
                }
            }
            if ($showImaxelButton == true) {
                echo '<button type="button" onclick="document.getElementById(' . "'imaxel_reprocess_order'" . ').value=1;document.post.submit();" class="button generate-items">' . __('Imaxel reprocess order', 'imaxel') . '</button>';
                echo '<input type="hidden" value="0" name="imaxel_reprocess_order" id="imaxel_reprocess_order" />';
            }
        }

        public function action_imaxel_order_reprocess($post_id, $post, $update) {
            if (is_string(get_post_status($post_id))) {
                $slug = 'shop_order';
                if (is_admin()) {
                    if ($slug != $post->post_type) {
                        return;
                    }
                    if (isset($_POST['imaxel_reprocess_order']) && $_POST['imaxel_reprocess_order'] == 1) {
                        $responseProcessing=OrderController::imaxel_woocommerce_order_status_processing($post->ID,false,true);
                        if (isset($responseProcessing)) {
                            IMX_Admin_Notices::add_success("Imaxel: order reprocessed. Remote info: " . implode(" ",$responseProcessing));
                        } else {
                            IMX_Admin_Notices::add_error("Imaxel error, can't reprocess order");
                        }
                    }
                }
            }
        }

        public function action_imaxel_woocommerce_order_item_meta_end($item_id, $item, $order) {
            global $wpdb;
            $projectID = $item->get_meta("proyecto");

            if ($projectID) {
				//TODO-dpi should be $row = ProjectModel::origin()->getProject($projectID);
                $sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                            WHERE id_project =' . $projectID;
                $row = $wpdb->get_row($sql);
                if ($row->prints_project == true) {
                    echo '<div class="product-imaxel-prints"><strong>' . __('Cantidad de copias', 'imaxel') . '</strong><p>' . $row->prints_requested_project  . '</p></div>';
                }
            }
        }
        #endregion

        #region Funciones BACKEND configuración genérica de Woocommerce

        public static function add_settings_tab($settings_tabs) {
            $settings_tabs['settings_tab_imaxel'] = __('Imaxel Services', 'imaxel');
            //#HELP-8 ICP (imaxel_icp_woocommerce_install)
            $settings_tabs['settings_tab_imaxel_icp'] = __('Imaxel Printspot', 'imaxel');
            return $settings_tabs;
        }

        public static function settings_tab() {
            woocommerce_admin_fields(self::get_settings());
        }

        public static function settings_tab_icp() {
            //#HELP-8 ICP (imaxel_icp_woocommerce_install)
			if(empty(get_option('wc_settings_tab_imaxel_icp_endpoint'))){
				woocommerce_admin_fields(
						array(
								'section_title' => array(
										'name' => __('Imaxel Printspot', 'imaxel'),
										'type' => 'title',
										'desc' => __('If you don\'t have a Imaxel Printspot account and are interested in enabling this section, please contact us at support@imaxel.com', 'imaxel'),
										'id' => 'wc_settings_tab_imaxel_icp_section_info'
								)
						)
				);
			}
            woocommerce_admin_fields(self::get_icp_settings());
        }

        public static function update_settings() {
            woocommerce_update_options(self::get_settings());
            WC_Imaxel::update_products();
        }

        public static function update_settings_icp() {
            //#HELP-8 ICP (imaxel_icp_woocommerce_install)
            woocommerce_update_options(self::get_icp_settings());
            WC_Imaxel::update_icp_products();
        }

        private static function update_products() {
            global $wpdb;
            $imaxelOperations = new ImaxelOperations();
            $privateKey = get_option("wc_settings_tab_imaxel_privatekey");
            $publicKey = get_option("wc_settings_tab_imaxel_publickey");
            
            WC_Imaxel::writeLog("START: downloadProducts");
            $imaxelProducts = $imaxelOperations->downloadProducts($publicKey, $privateKey);
            WC_Imaxel::writeCheckLog("END: downloadProducts", "empty", $imaxelProducts);

            if (!empty($imaxelProducts)) 
            {
                // check if the server can handle the JSON decoding, memory_limit to -1 equals use memory leftovers from OS
                $php_memory_limit = intval(ini_get('memory_limit'));
                $server_memory_exhausted = ( $php_memory_limit !== -1 ) ? ( strlen($imaxelProducts) * 10 ) > ( $php_memory_limit * 1024 * 1024 ) : false;
                if ($server_memory_exhausted) 
                {
                    WC_Imaxel::writeLog("SERVER MEMORY EXHAUSTED: " . strlen($imaxelProducts) * 10 . " is greater than " . $php_memory_limit * 1024 * 1024);
                    wp_die (__("Operation aborted: Making this request exhausts your server's memory. Your server cannot handle this call.", 'imaxel'));
                }

                //Guardar en base de datos
                WC_Imaxel::writeLog("START: JSON decoding");
                $imaxelProducts = json_decode($imaxelProducts);
                WC_Imaxel::writeCheckLog("END: JSON decoding", "empty", $imaxelProducts);

                // another check because sometimes get 504 timeout answer and the json decoding converts to NULL
                if (isset($imaxelProducts)) 
                {
                    $rowsBeforeSave = $wpdb->get_var("SELECT count(*) FROM " . $wpdb->prefix . "imaxel_woo_products");
                    WC_Imaxel::writeLog("START: saving JSON in database, products: " . $rowsBeforeSave);
                    foreach ($imaxelProducts as $product) 
                    {
                        $row = $wpdb->query("SELECT * FROM " . $wpdb->prefix . "imaxel_woo_products WHERE type=0 AND code='" . $product->code . "'");
                        $productPrice = 0;
                        $arrayVariants = array();
                        foreach ($product->variants as $variant) 
                        {
                            $arrayVariants[] =
                                [
                                    'code' => $variant->code,
                                    'name' => $variant->name->default
                                ];
                        }
                        $jsonVariants = json_encode($arrayVariants);
                        if ($row) 
                        {
                            $sql = "UPDATE " . $wpdb->prefix . "imaxel_woo_products SET
                                    price=" . $productPrice . ",
                                    variants='" . esc_sql($jsonVariants) . "',
                                    name='" . esc_sql($product->name->default) . "'
                                    WHERE code='" . $product->code . "' AND type=0";
                            $wpdb->query($sql);
                        } 
                        else
                        {
                            $sql = "INSERT INTO `" . $wpdb->prefix . "imaxel_woo_products` (code,name,variants,price,type) VALUES
                            (
                            '" . $product->code . "',
                            '" . esc_sql($product->name->default)  . "',"
                                . "'" . esc_sql($jsonVariants)  . "',"
                                . $productPrice
                                . ",0)";
                            $wpdb->query($sql);
                        }
                    }
                    $rowsAfterSave = $wpdb->get_var("SELECT count(*) FROM " . $wpdb->prefix . "imaxel_woo_products");
                    WC_Imaxel::writeLog("END: saving JSON in database, products: " . $rowsAfterSave);

                    $rowsBeforeDelete = $wpdb->get_var("SELECT count(*) FROM " . $wpdb->prefix . "imaxel_woo_products");
                    WC_Imaxel::writeLog("START: delete products that do not exist remotely, products: " . $rowsBeforeDelete);
                    //Borrado de productos que no existen en remoto
                    $rows = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "imaxel_woo_products");
                    foreach ($rows as $row) 
                    {
                        $delete = false;
                        $exist = false;
                        if ($row->type == 0) 
                        {
                            if (!$imaxelProducts)
                                $delete = true;
                            else 
                            {
                                foreach ($imaxelProducts as $imxProduct) 
                                {
                                    if (strcmp($imxProduct->code, $row->code) == 0) 
                                    {
                                        $exist = true;
                                        break;
                                    }
                                }
                                if ($exist == false)
                                    $delete = true;
                            }
                        }
                        if ($delete == true) 
                        {
                            $sql = "DELETE FROM " . $wpdb->prefix . "imaxel_woo_products WHERE id=" . $row->id;
                            $wpdb->query($sql);
                        }
                    }
                    $rowsAfterDelete = $wpdb->get_var("SELECT count(*) FROM " . $wpdb->prefix . "imaxel_woo_products");
                    WC_Imaxel::writeLog("END: delete products that do not exist remotely, products: " . $rowsAfterDelete);
                }
                else
                {
                    WC_Imaxel::writeCheckLog("JSON DECODING FAIL: the request is not set, probably getted a NULL", "null", $imaxelProducts);
                    wp_die (__("The operation has not been completed successfully. Please try again in a few minutes.", 'imaxel'));
                }
            } 
            else
            {
                WC_Imaxel::writeLog("EMPTY REQUEST: the request is empty, NULL, false or 0, request: " . strval($imaxelProducts));
                wp_die (__("The operation has not been completed successfully. Please try again in a few minutes.", 'imaxel'));
            }
        }

        //#HELP-8 ICP (imaxel_icp_woocommerce_install)
        private static function update_icp_products()
        {
            //get products list from ICP API
            $APIendpoint = get_option('wc_settings_tab_imaxel_icp_endpoint');
            
            if ( !empty($APIendpoint) )
            {
                global $wpdb;
                $endpoint = 'https://'.$APIendpoint;

                //fiel_get_content() option
                $icpProductsJSON = file_get_contents($endpoint);
                $icpProducts = json_decode($icpProductsJSON);

                //remove previous products
				$icpWoocommerceProductsTable = $wpdb->prefix.'icp_products_woocommerce';
				$sql = "DELETE FROM ".$icpWoocommerceProductsTable;
                $removeProducts = $wpdb->query($sql);

                //save products list on local database
                $siteID = $icpProducts->site_origin;
                foreach ( $icpProducts->products as $product )
                {
                    $sql = "
                        INSERT INTO ".$icpWoocommerceProductsTable."
                            (code, name, site, first_block)
                        VALUES
                            ($product->id, '". $product->name->default ."', $siteID, $product->first_block)
                    ";
                    $saveProducts = $wpdb->query($sql);
                }
            }
        }

        public static function get_settings()
        {
            $settings = array(
                'section_title' => array(
                    'name' => __('Configuration', 'imaxel'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'wc_settings_tab_imaxel_section_title'
                ),
                'publickey' => array(
                    'name' => __('Public key', 'imaxel'),
                    'type' => 'text',
                    'css' => 'min-width:300px;',
                    'desc' => __('Introduce Public key supplied by Imaxel', 'imaxel'),
                    'id' => 'wc_settings_tab_imaxel_publickey'
                ),
                'privatekey' => array(
                    'name' => __('Private key', 'imaxel'),
                    'type' => 'text',
                    'css' => 'min-width:300px;',
                    'desc' => __('Introduce Private key supplied by Imaxel', 'imaxel'),
                    'id' => 'wc_settings_tab_imaxel_privatekey'
                ),
                'automaticproduction' => array(
                    'name' => __('Activate automatic production', 'imaxel'),
                    'type' => 'checkbox',
                    'id' => 'wc_settings_tab_imaxel_automaticproduction'
                ),
                'allowguest' => array(
                    'name' => __('Allow guest mode', 'imaxel'),
                    'type' => 'checkbox',
                    'id' => 'wc_settings_tab_imaxel_allow_guest'
                ),
                'showcreatebuttonshop' => array(
                    'name' => __('Show create button in shop page', 'imaxel'),
                    'type' => 'checkbox',
                    'id' => 'wc_settings_tab_imaxel_shop_show_button'
                ),
                'section_end' => array(
                    'type' => 'sectionend',
                    'id' => 'wc_settings_tab_imaxel_section_end'
                ),
                'section_advanced_title' => array(
                    'name' => __('Advanced configuration', 'imaxel'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'wc_settings_tab_imaxel_advanced_section_title'
                ),
                'dont_override_product_price' => array(
                    'name' => __('Don\'t override product price', 'imaxel'),
                    'type' => 'checkbox',
                    'id' => 'wc_settings_tab_imaxel_dont_override_product_price'
                ),
                'override_product_description' => array(
                    'name' => __('Override product description', 'imaxel'),
                    'type' => 'checkbox',
                    'id' => 'wc_settings_tab_imaxel_override_product_description'
                ),
                'section_advanced_end' => array(
                    'type' => 'sectionend',
                    'id' => 'wc_settings_tab_imaxel_section_end'
                )
            );
            return apply_filters('wc_settings_tab_imaxel_settings', $settings);
        }

        //#HELP-8 ICP (imaxel_icp_woocommerce_install)
        public static function get_icp_settings() {
            $settings = array(
                'section_title' => array(
                    'name' => __('ICP API Conection', 'imaxel'),
                    'type' => 'title',
                    'desc' => __('Enter the endpoint, public key and private key and save changes to sincronize your woocommerce with your ICP products.', 'imaxel'),
                    'id' => 'wc_settings_tab_imaxel_icp_section_title'
                ),
                'endpoint' => array(
                    'name' => __('Endpoint', 'imaxel'),
                    'type' => 'text',
                    'css' => 'min-width:300px;',
                    'desc' => __('Introduce the address of your printspot account (no https://)', 'imaxel'),
                    'id' => 'wc_settings_tab_imaxel_icp_endpoint'
                ),
                'publickey' => array(
                    'name' => __('Public key', 'imaxel'),
                    'type' => 'text',
                    'css' => 'min-width:300px;',
                    'desc' => __('Introduce Public key supplied by Imaxel', 'imaxel'),
                    'id' => 'wc_settings_tab_imaxel_icp_publickey'
                ),
                'privatekey' => array(
                    'name' => __('Private key', 'imaxel'),
                    'type' => 'text',
                    'css' => 'min-width:300px;',
                    'desc' => __('Introduce Private key supplied by Imaxel', 'imaxel'),
                    'id' => 'wc_settings_tab_imaxel_icp_privatekey'
                ),
                'interface_color' => array(
                    'name' => __('Theme Color', 'imaxel'),
                    'type' => 'color',
                    'css' => 'min-width:300px;',
                    'desc' => __('Introduce the hexadecimal color code to user on your plugin interface (ex: #010101)', 'imaxel'),
                    'id' => 'wc_settings_tab_imaxel_icp_color_theme'
                ),
                'secondary_interface_color' => array(
                    'name' => __('Secondary Theme Color', 'imaxel'),
                    'type' => 'color',
                    'css' => 'min-width:300px;',
                    'desc' => __('Introduce the hexadecimal color code to user on your plugin interface as secondary color (ex: #010101)', 'imaxel'),
                    'id' => 'wc_settings_tab_imaxel_icp_secondary_color_theme'
                ),
                'section_end' => array(
                    'type' => 'sectionend',
                    'id' => 'wc_settings_tab_imaxel_icp_section_end'
                ),

            );
            return apply_filters('wc_settings_tab_imaxel_icp_settings', $settings);
        }

        public function wp_loaded_icp_view()
        {
            if (is_admin())
            {
				if(is_multisite())
                {
					global $wpdb;
					// Retrieve all site IDs from this network (WordPress >= 4.6 provides easy to use functions for that).
					if (function_exists('get_sites') && function_exists('get_current_network_id')) {
						$site_ids = get_sites(array('fields' => 'ids', 'network_id' => get_current_network_id()));
					} else {
						$site_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs WHERE site_id = $wpdb->siteid;");
					}

					foreach ($site_ids as $site_id) {
						switch_to_blog($site_id);
                        $this->create_icp_view();
						restore_current_blog();
					}
				}
				else
                {
                    $this->create_icp_view();
				}
            }
        }

        public function create_icp_view()
        {
            $checkIfPageExists = get_page_by_title('Imaxel Custom Products', 'OBJECT', 'page');
            if(empty($checkIfPageExists))
            {
                wp_insert_post(
                    array(
                    'comment_status' => 'close',
                    'ping_status'    => 'close',
                    'post_title'     => 'Imaxel Custom Products',
                    'post_name'      => 'imaxel-custom-products',
                    'post_status'    => 'publish',
                    'post_content'   => '<!-- wp:html -->[icp_view]<!-- /wp:html -->',
                    'post_type'      => 'page',
                    )
                );
            }
        }

        #endregion

        #region Funciones BACKEND configuracion de producto

        public function imaxel_product_data_tab($product_data_tabs) {
            $product_data_tabs['imaxel-product-tab'] = array(
                'label' => __('Imaxel Services', 'imaxel'),
                'target' => 'imaxel_product_data'
            );
            //#HELP-8 ICP (imaxel_icp_woocommerce_install)
            $product_data_tabs['imaxel-icp-product-tab'] = array(
                'label' => __('Imaxel Printspot', 'imaxel'),
                'target' => 'imaxel_icp_product_data'
            );
            return $product_data_tabs;
        }

        public function imaxel_product_data_fields() {
            global $post;
            global $wpdb;

            $imaxel_products = $wpdb->get_results("SELECT id, CONCAT(code,' ',name) as name FROM " . $wpdb->prefix . "imaxel_woo_products" . " WHERE type=0 ORDER BY " . $wpdb->prefix . "imaxel_woo_products.name asc");
            $arrayProducts = array(-1 => __('None', 'imaxel'));
            foreach ($imaxel_products as $item) {
                $arrayProducts[$item->id] = $item->name;
            }

            $selectedProduct = get_post_meta($post->ID, "_imaxel_selected_product", true);
            $selectedProductVariations = get_post_meta($post->ID, "_imaxel_selected_product_variant", true);
            $product = wc_get_product($post->ID);

            wp_enqueue_style('style', plugins_url('/assets/css/creative_styles.css', __FILE__));

            wp_enqueue_script(
                'imaxel_script',
                plugins_url('/assets/js/imaxel_admin.js', __FILE__),
                array('jquery'),
				$this->plugin_version
            );

            wp_localize_script(
                'imaxel_script',
                'ajax_object',
                array(
                    'url' => admin_url('admin-ajax.php'),
                    'literal_all_variants' => __('All variants', 'imaxel'),
                    'available_product_variations' => $product->is_type('variable') ? $product->get_available_variations() : null,
                    'selected_product_variations' => $product->is_type('variable') && (!empty($selectedProductVariations) && count($selectedProductVariations) > 0) ?  array_values($selectedProductVariations) : null
                )
            );

?>
            <div id="imaxel_product_data" class="panel woocommerce_options_panel" style="padding-left:15px;padding-top:15px">
                <div class="imx-loader" style="display:none"></div>
                <button type="button" class="button button-primary" id="btnImaxelUpdateProducts"><?php _e('Update products', 'imaxel'); ?></button>

                <div>HTML products</div>
                <script>
                    var $ = jQuery.noConflict();
                </script>
                <style>
                    .imaxel_selected_product,
                    ._imaxel_selected_product_variant {
                        width: 50%
                    }
                </style>

                <?php

                woocommerce_wp_text_input(
                    array(
                        'id' => '_imaxel_filter_products',
                        'label' => __('Filter products', 'imaxel'),
                        'placeholder' => '',
                        'class' => 'imaxel_filter_products'
                    )
                );

                woocommerce_wp_select(
                    array(
                        'id' => '_imaxel_selected_product',
                        'class' => 'imaxel_selected_product',
                        'label' => __('Select one product', 'imaxel'),
                        'options' => $arrayProducts,
                        'value' => $selectedProduct
                    )
                );

                woocommerce_wp_select(
                    array(
                        'id' => '_imaxel_selected_product_variant',
                        'name' => '_imaxel_selected_product_variant[]',
                        'class' => '_imaxel_selected_product_variant',
                        'label' => __('Select one variant', 'imaxel'),
                        'options' => "",
                        'value' => ""
                    )
                );
                
                ?>

                <script>
                    jQuery.fn.filterByText = function(textbox) {
                        return this.each(function() {
                            var select = this;
                            var options = [];
                            $(select).find('option').each(function() {
                                options.push({
                                    value: $(this).val(),
                                    text: $(this).text()
                                });
                            });
                            $(select).data('options', options);

                            $(textbox).bind('change keyup', function() {
                                var options = $(select).empty().data('options');
                                var search = $.trim($(this).val());
                                var regex = new RegExp(search, "gi");

                                $.each(options, function(i) {
                                    var option = options[i];
                                    if (option.text.match(regex) !== null) {
                                        $(select).append(
                                            $('<option>').text(option.text).val(option.value)
                                        );
                                    }
                                });
                            });
                        });
                    };
                </script>

                <script>
                    jQuery(function($) {
                        jQuery('.imaxel_selected_product').filterByText(jQuery('.imaxel_filter_products'));
                    });
                </script>

            </div>
            <?php

            //#HELP-8 ICP (imaxel_icp_woocommerce_install)
            $icpWoocommerceProductsTable = $wpdb->prefix.'icp_products_woocommerce';
            $icpProducts = $wpdb->get_results("SELECT * FROM ".$icpWoocommerceProductsTable);
            foreach($icpProducts as $product) {
                $icpProductsArray[0] = '';
                $icpProductsArray[$product->code] = $product->code . " " . $product->name;
            }

            //selected product
            $selectedProduct = get_post_meta($post->ID, "_imaxel_icp_products", true);

            //show products list
            ?><div id="imaxel_icp_product_data" class="panel woocommerce_options_panel" style="padding-left:15px;padding-top:15px"><?php
            ?>Printspot Products<?php

                woocommerce_wp_text_input(
                    array(
                        'id' => '_imaxel_icp_filter_products',
                        'label' => __('Filter products', 'imaxel'),
                        'placeholder' => '',
                        'class' => 'imaxel_icp_filter_products'
                    )
                );

                woocommerce_wp_select(array(
                        'id' => '_imaxel_icp_products',
                        'class' => '_imaxel_icp_products',
                        'label' => __('Select a product', 'imaxel'),
                        'options' => $icpProductsArray,
                        'value' => $selectedProduct
                    )
            );
            ?></div>
            <script>
                var $ = jQuery.noConflict();

                jQuery(function($) {
                    jQuery('._imaxel_icp_products').filterByText(jQuery('.imaxel_icp_filter_products'));
                    jQuery("#_imaxel_selected_product").change(function(){
                        jQuery("#_imaxel_icp_products").val("");
                    });
                    jQuery("#_imaxel_icp_products").change(function(){
                        jQuery("#_imaxel_selected_product").val("");
                    });
                });
            </script>


            <?php
        }

        public function imaxel_product_data_variable_fields($loop, $variation_data, $variation) {

            global $post;
            global $wpdb;
            $imaxel_products = $wpdb->get_results("SELECT id, CONCAT(code,' ',name) as name FROM " . $wpdb->prefix . "imaxel_woo_products" . " WHERE type=0 ORDER BY " . $wpdb->prefix . "imaxel_woo_products.name asc");
            $arrayProducts = array(-1 => __('None', 'imaxel'));
            foreach ($imaxel_products as $item) {
                $arrayProducts[$item->id] = $item->name;
            }

            $selectedProduct = get_post_meta($variation->ID, "_imaxel_selected_variation_product", true);
            $selectedProductVariations = get_post_meta($variation->ID, "_imaxel_selected_variation_product_variant", true);
            if (is_array($selectedProductVariations))
                $selectedProductVariations = array_values($selectedProductVariations);
            else
                $selectedProductVariations = null;

            woocommerce_wp_select(
                array(
                    'id'    => '_imaxel_selected_variation_product[' . $variation->ID . ']',
                    'class' => '_imaxel_selected_variation_product',
                    'label' => __('Select one product', 'imaxel'),
                    'options' => $arrayProducts,
                    'value' => $selectedProduct
                )
            );

            woocommerce_wp_select(
                array(
                    'id'    => '_imaxel_selected_variation_product_variant[' . $variation->ID . ']',
                    'name'    => '_imaxel_selected_variation_product_variant[' . $variation->ID . '][]',
                    'class' => '_imaxel_selected_variation_product_variant',
                    'label' => __('Select one variant', 'imaxel'),
                    'options' => "",
                    'value' => ""
                )
            );
            if (isset($selectedProductVariations) && strlen($selectedProductVariations > 0)) {
                $selectedProductVariations = '["' . implode('", "', $selectedProductVariations) . '"]';
            } else {
                $selectedProductVariations = '[""]';
            }

            echo '
             <script>
                    jQuery(document).ready( function() {
                        jQuery("._imaxel_selected_variation_product_variant").attr("multiple", "multiple");
                        var targetVariant="#_imaxel_selected_variation_product_variant\\\\[' . $variation->ID . '\\\\]";
                        var targetProduct="#_imaxel_selected_variation_product\\\\[' . $variation->ID . '\\\\]";

                        var targetSelectedVariations=' . $selectedProductVariations . ';

                        load_product_imaxel_variations(targetProduct,targetVariant,targetSelectedVariations);

                        jQuery(targetProduct).on("change", function() {
                            console.log(jQuery(this).attr("id"));
                            load_product_imaxel_variations(targetProduct,targetVariant,targetSelectedVariations);
                        });
                    });
              </script>';
        }

        public function imaxel_product_data_fields_save($post_id) {
            update_post_meta($post_id, '_imaxel_selected_product', $_POST['_imaxel_selected_product']);

            $arraySelectedProductVariants = $_POST['_imaxel_selected_product_variant'];
            if (isset($arraySelectedProductVariants)) {
                if (($key = array_search(-1, $arraySelectedProductVariants)) !== false) {
                    unset($arraySelectedProductVariants[$key]);
                }
            }
            update_post_meta($post_id, '_imaxel_selected_product_variant', $arraySelectedProductVariants);

            //#HELP-8 ICP (imaxel_icp_woocommerce_install)
            update_post_meta($post_id, '_imaxel_icp_products', $_POST['_imaxel_icp_products']);
        }

        public function imaxel_product_data_variable_fields_save($post_id) {

            $select = $_POST['_imaxel_selected_variation_product'][$post_id];
            if (!empty($select)) {
                update_post_meta($post_id, '_imaxel_selected_variation_product', esc_attr($select));
            }

            $arraySelectedProductVariants = $_POST['_imaxel_selected_variation_product_variant'][$post_id];
            if (($key = array_search(-1, $arraySelectedProductVariants)) !== false) {
                unset($arraySelectedProductVariants[$key]);
            }
            update_post_meta($post_id, '_imaxel_selected_variation_product_variant', $arraySelectedProductVariants);
        }

        public function imaxel_product_save($post_id) {
            $product = wc_get_product($post_id);
            $selectedProduct = get_post_meta($post_id, "_imaxel_selected_product", true);

            if ($product && $selectedProduct > 0) {
                wp_set_object_terms($post_id, 'variable', 'product_type');
                $product = wc_get_product($post_id);
                if (!$product->is_type("variable") || !$product->get_variation_attributes()["proyecto"]) {
                    $thedata = array('proyecto' => array(
                        'name' => 'proyecto',
                        'value' => 'CUSTOM_TEXT',
                        'is_visible' => '0',
                        'is_variation' => '1',
                        'is_taxonomy' => '0'
                    ));
                    update_post_meta($post_id, '_product_attributes', $thedata);

                    $variation = array(
                        'post_title' => 'Product #' . $product->get_id() . ' Variation',
                        'post_content' => '',
                        'post_status' => 'publish',
                        'post_parent' => $product->get_id(),
                        'post_type' => 'product_variation'
                    );

                    $variation_id = wp_insert_post($variation);

                    update_post_meta($variation_id, '_price', "0");
                    update_post_meta($variation_id, '_regular_price', "0");
                }
            }

            //#HELP-8 ICP (imaxel_icp_woocommerce_install)
            $selectedProduct = get_post_meta($post_id, "_imaxel_icp_products", true);
            if ($product && $selectedProduct > 0) {
                wp_set_object_terms($post_id, 'simple', 'product_type');
            }
        }

        public function imaxel_product_get_variants() {
            global $wpdb;
            $productID = intval($_POST["productID"]);
            $sql = "SELECT * FROM " . $wpdb->prefix . "imaxel_woo_products WHERE id=" . $productID;
            $imaxelProduct = $wpdb->get_row($sql);
            echo $imaxelProduct->variants;
            die();
        }

        public function imaxel_update_products() {
            WC_Imaxel::update_products();
        }

        #endregion

        #region Funciones FRONTEND Product Template

		/**
         * handles editor launch for creative products
         */
        public function imaxel_wrapper() {
            global $wpdb;
            global $woocommerce;
            admin_url('admin-ajax.php');
            $currentURL = get_permalink();
            $product = wc_get_product($_POST["productID"]);

			$selectedWebVariation = json_decode(stripslashes($_POST['selectedVariation']), true);
			$variations = $product->get_available_variations();

			$selectedVariation = null;
			if (!empty($selectedWebVariation) && count($selectedWebVariation) > 1) {
				foreach ($variations as $variation) {
					$selectedWebVariation["attribute_proyecto"] = $variation["attributes"]["attribute_proyecto"];
					$aux = array_diff($variation["attributes"], $selectedWebVariation);
					if (count($aux) == 0) {
						$selectedVariation = $variation["variation_id"];
					}
				}
			} else {
				$selectedVariation = $variations[0]["variation_id"];
			}

			$selectedProductID = get_post_meta( $product->get_id() , "_imaxel_selected_product", true);
			$selectedProductVariations = get_post_meta( $product->get_id() , "_imaxel_selected_product_variant", true);

			$alternativeVariationProductID = get_post_meta($selectedVariation, "_imaxel_selected_variation_product", true);
			if ($alternativeVariationProductID && $alternativeVariationProductID != -1) {
				$selectedProductID = $alternativeVariationProductID;
				$alternativeProductVariations = get_post_meta($selectedVariation, "_imaxel_selected_variation_product_variant", true);
				if ($alternativeProductVariations && $alternativeProductVariations != -1) {
					$selectedProductVariations = $alternativeProductVariations;
				}
			}

			$sql = "SELECT * FROM " . $wpdb->prefix . "imaxel_woo_products WHERE id=" . $selectedProductID;
			$imaxelProduct = $wpdb->get_row($sql);

            $backURL = esc_url($_POST["backURL"]);

            $cartURL = get_home_url();
            $cartURLParameters = "?imx-add-to-cart=" . $product->get_id() . "&variation_id=" . $selectedVariation . "&imx_product_type=" . $imaxelProduct->type;

            $saveURL = get_home_url();
            $saveURLParameters = "?imx-add-to-project=" . $product->get_id() . "&variation_id=" . $selectedVariation . "&imx_product_type=" . $imaxelProduct->type;

            $imaxelOperations = new ImaxelOperations();
            $privateKey = get_option("wc_settings_tab_imaxel_privatekey");
            $publicKey = get_option("wc_settings_tab_imaxel_publickey");

            $response = $imaxelOperations->createProject($publicKey, $privateKey, $product->get_id(), $selectedVariation, $imaxelProduct->code, $selectedProductVariations, $cartURL, $cartURLParameters, $backURL, $saveURL, $saveURLParameters);
            echo $response;
            die();
        }

        #endregion

        #region Funciones FRONTEND Cart / Checkout

        /**
         * Adds edit button to cart (creative and icp products)
         * @param $item_data
         * @param $cart_item
         * @return mixed
         */
        public function imaxel_edit_project_form_cart_button($item_data, $cart_item) {
            wp_enqueue_script('imaxel_script', plugins_url('/assets/js/imaxel.js', __FILE__), array('jquery'), $this->plugin_version);
            wp_localize_script('imaxel_script', 'ajax_object', array(
            		'url' => admin_url('admin-ajax.php'),
					'backurl' => get_permalink(),
					'ajax_url' => admin_url('admin-ajax.php')
			));

            if (!empty($cart_item["variation"]["attribute_proyecto"])) {
                $projectID = $cart_item["attribute_proyecto"]; //#WST-26
                echo '<div style="display:inline; margin-left: 15px; color: orange; cursor: pointer" cart_item_key="' . $cart_item["key"] . '" project_id="' . $projectID . '" id="edit_from_cart"><i class="fa fa-edit fa-lg"></i> ' . __('Edit', 'imaxel') . '</div>';
                return $item_data;
            }
            //ICP Products
            if (isset($cart_item["icp_product"])) {
                $icpUrl = get_option('icp_url');
                $projectData = IcpProductsProjectsModel::origin($cart_item['dealerOriginID'])->getBy(['id' => $cart_item["icp_project"]]);
                $url = $icpUrl . '?id=' . $projectData->product . '&site=' . $projectData->site . '&block=' . $projectData->first_block . '&icp_project=' . $projectData->id."&wproduct=".$cart_item["product_id"];
                ?>
                <a data-icp-project="<?php echo $cart_item['icp_project'] ?>" class="edit-from-cart-button-link" href="<?php echo $url ?>">
                    <div class="edit_from_cart_button" style="display:inline; margin-left: 15px; color: #ffa500; cursor: pointer">
                        <i class="fa fa-edit fa-lg"></i> <?php echo __('Edit', 'imaxel') ?>
                    </div>
                </a>
                <?php if ($projectData->project_name) { ?>
                    <p style="font-size:small;margin-top:auto; margin-bottom: auto;">
                        <?php echo $projectData->project_name ?>
                    </p>
                <?php } ?>
                <!--<img class='animate__animated animate__zoomIn' style='box-shadow: -3px 3px 5px ' src='--><?php //echo $projectData->image ?><!--'/>-->
                <!--<br/>-->
                <?php
                return $item_data;
            }
			return $item_data;
		}

        /**
         * Handles edit button action from cart (creative products)
         */
        public function imaxel_edit_project_form_cart_function() {
            $cart_item_id = $_POST['cart_item_id'];//#WST-26
			$returnCheckoutPage = isset($_POST['forceReturnCheckoutPage']) && $_POST['forceReturnCheckoutPage'] === 'true' ? true : false;
			MyAccountController::editProject($cart_item_id,$returnCheckoutPage);
            wp_die();
        }

        /**
         * Handles order meta line properties (creative and icp products)
         * @param $item
         * @param $cart_item_key
         * @param $values
         * @param $order
         */
        public function imaxel_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {

            //#WST-26
            if (!empty($item->get_meta("proyecto"))) {
                $item->update_meta_data('proyecto', $values['attribute_proyecto']);
            }
            else {
                $item->add_meta_data('proyecto', $values['attribute_proyecto']);
            }

            //#HELP-8
            if (isset($values['icp_project'])) $item->add_meta_data('icp_project', $values['icp_project'], true);
            if (isset($values['icp_product'])) $item->add_meta_data('icp_product', $values['icp_product'], true);
            if (isset($values['icp_volumetric_value'])) $item->add_meta_data('icp_volumetric_value', $values['icp_volumetric_value'], true);
            if (isset($values['dealerID'])) $item->add_meta_data('dealerID', $values['dealerID'], true);
            if (isset($values['dealerOriginID'])) $item->add_meta_data('dealerOriginID', $values['dealerOriginID'], true);

            // DEV-1472 save Services SKU in to the order item meta
            if (isset($values['services_sku'])) $item->add_meta_data('services_sku', $values['services_sku'], true);
        }

        public function imaxel_add_to_cart($cart_item_key, $product_id)
        {
            global $wpdb;

            $guestModeEnabled = get_option("wc_settings_tab_imaxel_allow_guest");
            $userID = get_current_user_id();

            if ( $userID > 0 || $guestModeEnabled == "yes" )
            {
                if ( isset($_GET["attribute_proyecto"]) )
                {
                    $imaxelOperations = new ImaxelOperations();
                    $privateKey = get_option("wc_settings_tab_imaxel_privatekey");
                    $publicKey = get_option("wc_settings_tab_imaxel_publickey");

                    $projectID = $_GET["attribute_proyecto"];
                    $id_product_attribute = $_GET["variation_id"];
                    $productType = 0; //HTML product

                    $projectInfo = $imaxelOperations->readProject($publicKey, $privateKey, $projectID);
                    
                    if ($projectInfo)
                    {
                        $projectInfo = json_decode($projectInfo);
                        
                        if ( isset($projectInfo) && isset($projectInfo->status) && $projectInfo->status === 404 ) wp_die (__("Operation aborted: You tried to add a non-existent project to the cart.",'imaxel'));

                        $productName = $projectInfo->product->name->default;
                        
                        if ( $projectInfo->design->variant_code )
                        {
                            foreach ( $projectInfo->product->variants as $variant )
                            {
                                if ( $projectInfo->design->variant_code == $variant->code )
                                {
                                    $productName = $productName . " " . $variant->name->default;
                                    break;
                                }
                            }
                        }
                        
                        $productPrice = $projectInfo->design->price;
                        $productWeight = $projectInfo->design->volumetricWeight;
                        $productPrints = 0;
                        $variantSku = !empty($projectInfo->design->sku) ? $projectInfo->design->sku : NULL;
                        //$printsRequested=count($projectInfo->design->pages);

                        //Added with the 09/2018 Prints Editor Updates
                        $printsRequested = 0;
                        
                        foreach ( $projectInfo->design->pages as $page )
                        {
                            $printsRequested = isset($page->copies) ? $printsRequested + $page->copies : $printsRequested + 1;
                        }

                        //=============================================//

                        if ( $projectInfo->product->module->code == "printspack" || $projectInfo->product->module->code == "polaroids" ) $productPrints = 1;
                        $productName = esc_sql($productName);
                        $projectDate = $projectInfo->updatedAt;

                        // Avoid saving de SKU in the cart item if function is called from imaxel_redirection_function
                        if ( isset($cart_item_key) )
                        {
                            global $woocommerce;
                            $cart = $woocommerce->cart;
                            $cart_item = $cart->get_cart_item($cart_item_key);
                            $cart_item['services_sku'] = $variantSku;
    
                            // Update and save the cart item in the cart
                            $cart->cart_contents[$cart_item_key] = $cart_item;
                            $cart->set_session();
                        }
                    }

                    $_product = wc_get_product($product_id);
                    $_product_price = $_product->get_price();

                    $exists = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "imaxel_woo_projects WHERE id_customer=" . $userID . " AND id_project=" . $projectID);
                    
                    if (!$exists)
                    {
                        $sql = "INSERT INTO " . $wpdb->prefix . "imaxel_woo_projects (id_customer, id_project, type_project,id_product, id_product_attribute, price_project, weight_project,description_project,prints_project,prints_requested_project,date_project,services_sku)
                        VALUES (
                        " . $userID . "," . $projectID . "," . $productType . "," . $product_id . "," . $id_product_attribute . "," . $productPrice . "," . $productWeight . ",'" . $productName . "'," . (int)$productPrints . "," . $printsRequested . ",'" . $projectDate . "','" . $variantSku . "'".
                        ")";
                        $wpdb->query($sql);
                    }
                    else
                    {
                        $sql = "UPDATE " . $wpdb->prefix . "imaxel_woo_projects
                        SET
                          price_project=" . $productPrice . ",
                          description_project='" . $productName . "',
                          weight_project=" . $productWeight . ",
                          prints_project=" . $productPrints . ",
                          date_project='" . $projectDate . "',
                          services_sku='" . $variantSku . "',
                          prints_requested_project=" . $printsRequested . "
                        WHERE
                          id_customer=" . $userID . " and id_project = " . $projectID;
                        $updateProject = $wpdb->query($sql);
                    }
                }
            }
        }

        public function imaxel_cart_prints($other_data, $cart_item) {

            //TODO DPI Review this hook and remove if not needed
            $other_data = array();
            /*global $wpdb;

            if(array_key_exists("variation",$cart_item)) {
                if (array_key_exists("attribute_proyecto", $cart_item["variation"])) {
                    $projectID = $cart_item["variation"]["attribute_proyecto"];
                    if ($projectID) {
                        $sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                            WHERE id_project =' . $projectID;
                        $row = $wpdb->get_row($sql);
                        if ($row) {
                            if($row->prints_project==true){
                                $other_data[] = array( "name" => __('Cantidad de copias', 'imaxel') , "value" => $row->prints_requested_project );
                            }
                        }
                    }
                }
            }*/

            return $other_data;
        }

        public function blockQtyForPrintsOnCartUpdate($product_quantity, $cart_item_key) {
            global $wpdb;
            $cart_item = WC()->cart->cart_contents[$cart_item_key];
            if (array_key_exists("variation", $cart_item)) {
                if (array_key_exists("attribute_proyecto", $cart_item["variation"])) {
                    $projectID = $cart_item["attribute_proyecto"]; //#WST-26
                    if ($projectID) {
                    	//TODO-dpi should be 						$row = ProjectModel::origin()->getProject($projectID);
                        $sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                        WHERE id_project =' . $projectID;
                        $row = $wpdb->get_row($sql);
                        if ($row->prints_project == true) {
                            $product_quantity = sprintf($row->prints_requested_project . ' <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key);
                        }
                    }
                }
            }
            return $product_quantity;
        }

        /**
         * block cart items qty if it is icp product
         */
        function icp_block_cart_qty($product_quantity, $cart_item_key) {
            //TODO: DPI Move to Controller
            global $wpdb;
            $cart_item = WC()->cart->cart_contents[$cart_item_key];
            if (array_key_exists("icp_project", $cart_item)) {
                $product_quantity = sprintf($cart_item['quantity'] . ' <input type="hidden" name="cart[%s][qty]" value="1" /></p>', $cart_item_key);
            }
            return $product_quantity;
        }

        public function imaxel_custom_cart_price($cart_object) {
            //TODO: DPI Move to Controller
            global $woocommerce;
            global $wpdb;
            $dontOverridePrice = get_option("wc_settings_tab_imaxel_dont_override_product_price");
            $overrideProductDescription = get_option("wc_settings_tab_imaxel_override_product_description");

            if (did_action('woocommerce_before_calculate_totals') >= 3)
                return;

            if (is_admin() && !defined('DOING_AJAX'))
                return;

            foreach ($cart_object->cart_contents as $key => $value) {
                if (array_key_exists("variation", $value)) {
                    if (array_key_exists("attribute_proyecto", $value["variation"])) {
                        $projectID = $value["attribute_proyecto"];//#WST-26
                        if ($projectID) {
                            $sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                            WHERE id_project =' . $projectID;
                            $row = $wpdb->get_row($sql);
                            if ($row) {
                                if (function_exists('WC') && (version_compare(WC()->version, "3.0.0") >= 0)) {
                                    if ($dontOverridePrice != "yes") {
                                        $value['data']->set_price($row->price_project);
                                    }
                                    if ($overrideProductDescription == "yes" && $row->description_project) {
                                        $value['data']->set_name($row->description_project);
                                    }
                                    if ($row->prints_project == true) {
                                        $printPrice = floatval($row->price_project) / intval($row->prints_requested_project);
                                        //#WST-6 Polaroids module - rounding to 4 digits
                                        $printPriceFormated = number_format($printPrice, 4);
                                        $value['data']->set_price($printPriceFormated);
                                        $cart_object->set_quantity($key, intval($row->prints_requested_project), false);
                                    }
                                    $value['data']->set_weight($row->weight_project);
                                } else {
                                    if ($dontOverridePrice != "yes") {
                                        $value['data']->price = $row->price_project;
                                    }
                                    if ($overrideProductDescription == "yes" && $row->description_project) {
                                        $value['data']->name = $row->description_project;
                                    }
                                    if ($row->prints_project == true) {
                                        $value['data']->sold_individually = "yes";
                                    }
                                    $value['data']->weight = $row->weight_project;
                                }
                            }
                        }
                    }
                }
                //#HELP-8 ICP
                $projectsTable = $wpdb->prefix . 'icp_products_projects';
                if (isset($value['icp_project'])) {
                    $projectID = $value['icp_project'];
                    if (isset($value['icp_product'])) {

                        //get project data
                        $projectData = IcpProductsProjectsModel::origin()->getById($projectID);

                        //override name and price
                        $unitPriceTotal = 0;
                        $value['data']->set_name($projectData->product_name);
                        $rawPrice = unserialize($projectData->variation_price);
                        $quantity = $rawPrice['qty'];
                        foreach ($rawPrice as $block => $blockPrice) {
                            if (IcpService::isBlockData($block)) {
                                $unitPriceTotal = (floatval($unitPriceTotal) + floatval($blockPrice['total_price']));
                            }
                        }

                        //set price (divided per quantity)
                        $value['data']->set_price(floatval($unitPriceTotal));

                        //set quantity
                        $cart_object->set_quantity($key, $quantity, false);
                    }
                }
            }
        }

        public function check_deleted_project_in_cart()
        {
            global $woocommerce;
            
            $items = $woocommerce->cart->get_cart();
            foreach ($items as $item => $values)
            {
                if ( isset($productType) ) unset($productType);
                if ( isset($values["attribute_proyecto"]) )
                {
                    $productType = "creative";
                    $projectID = $values["attribute_proyecto"];
                }
                
                if ( isset($values["icp_product"]) && isset($values["icp_project"]) )
                {
                    $productType = "icp";
                    $projectID = $values["icp_project"];
                }

                if ( !empty($productType) )
                {
                    switch ($productType)
                    {
                        case "icp":
                            CartService::removeDeletedProjectInCartIcp($item, $projectID);
                            break;
                        case "creative":
                            CartService::removeDeletedProjectInCartCreative($item, $projectID);
                            break;
                    }
                }
            }
        }

        #endregion

        #region Funciones FRONTEND Redirección
         //HELP-8
        function icpQueryVars($query_vars)
        {
            $query_vars[] = 'id';
            $query_vars[] = 'site';
            $query_vars[] = 'block';
            $query_vars[] = 'wproduct';
            return $query_vars;
        }

        public function imaxel_wc_custom_user_redirect($redirect) {
            global $woocommerce;

            if (isset($_GET["imx-add-to-cart"])) {
                $url = get_home_url();
                if (function_exists('WC') && (version_compare(WC()->version, "3.1.0") >= 0)) {
                    $url = add_query_arg("imx-add-to-cart", $_GET["imx-add-to-cart"], $url);
                } else {
                    $url = add_query_arg("add-to-cart", $_GET["imx-add-to-cart"], $url);
                }
                $url = add_query_arg("variation_id", $_GET["variation_id"], $url);
                $url = add_query_arg("imx_product_type", $_GET["imx_product_type"], $url);
                $url = add_query_arg("attribute_proyecto", $_GET["attribute_proyecto"], $url);
            } else if (isset($_GET["imx-add-to-project"])) {
                $url = get_home_url();
                $url = add_query_arg("imx-add-to-project", $_GET["imx-add-to-project"], $url);
                $url = add_query_arg("variation_id", $_GET["variation_id"], $url);
                $url = add_query_arg("imx_product_type", $_GET["imx_product_type"], $url);
                $url = add_query_arg("attribute_proyecto", $_GET["attribute_proyecto"], $url);
            } else {
                $url = get_permalink(get_option('woocommerce_myaccount_page_id'));
            }

            if (isset($url)) {
                wp_safe_redirect($url);
                exit;
            }
        }

        public function imaxel_redirection_function() {
            global $woocommerce;
            $guestModeEnabled = get_option("wc_settings_tab_imaxel_allow_guest");
            if (isset($_GET["imx-add-to-project"])) {
                if (is_front_page() && (is_user_logged_in() == false)) {
                    $url = get_permalink(get_option('woocommerce_myaccount_page_id'));
                    $url = add_query_arg("imx-add-to-project", $_GET["imx-add-to-project"], $url);
                    $url = add_query_arg("variation_id", $_GET["variation_id"], $url);
                    $url = add_query_arg("imx_product_type", $_GET["imx_product_type"], $url);
                    $url = add_query_arg("attribute_proyecto", $_GET["attribute_proyecto"], $url);
                } else if (is_front_page() && (is_user_logged_in() == true)) {
                    $this->imaxel_insert_project($_GET["imx-add-to-project"]);
                    $url = get_permalink(get_option('woocommerce_myaccount_page_id'));
                }
            } else {
                if (is_front_page() && (is_user_logged_in() == false) && $guestModeEnabled == "no") {
                    if (isset($_GET["imx-add-to-cart"])) {
                        $url = get_permalink(get_option('woocommerce_myaccount_page_id'));
                        $url = add_query_arg("imx-add-to-cart", $_GET["imx-add-to-cart"], $url);
                        $url = add_query_arg("variation_id", $_GET["variation_id"], $url);
                        $url = add_query_arg("imx_product_type", $_GET["imx_product_type"], $url);
                        $url = add_query_arg("attribute_proyecto", $_GET["attribute_proyecto"], $url);
                    }
                } elseif (is_front_page() && (is_user_logged_in() == true || $guestModeEnabled == "yes")) {
                    if (isset($_GET["imx-add-to-cart"])) {
                        if (function_exists('WC') && (version_compare(WC()->version, "3.1.0") >= 0)) {
                            //#WST-26
                            if(isset($_GET["imx_cart_item_id"])){
                                WC()->cart->remove_cart_item($_GET["imx_cart_item_id"]);
                            }
                            $empty_array=array(
                                'attribute_proyecto'=>"CUSTOM_TEXT"
                            );
                            $arr = array();
                            $arr['attribute_proyecto'] = $_GET["attribute_proyecto"];
                            WC()->cart->add_to_cart( $_GET["imx-add-to-cart"], 1,  $_GET["variation_id"],$empty_array,$arr);
                            $this->imaxel_add_to_cart(null,$_GET["imx-add-to-cart"]);
                            $url=wc_get_cart_url();
                            //END #WST-26
                        }
                        else {
                            $url = get_home_url();
                            $url = add_query_arg("add-to-cart", $_GET["imx-add-to-cart"], $url);
                            $url = add_query_arg("variation_id", $_GET["variation_id"], $url);
                            $url = add_query_arg("imx_product_type", $_GET["imx_product_type"], $url);
                            $url = add_query_arg("attribute_proyecto", $_GET["attribute_proyecto"], $url);
                        }
                    }
                    if (isset($_GET["add-to-cart"])) {
                        $url = $woocommerce->cart->get_cart_url();
                    }
                }
            }
            if (isset($url)) {
                wp_safe_redirect($url);
                exit;
            }
        }

        #endregion

        public function woocommerce_loaded() {
            global $woocommerce;
            global $product;
        }

        public function plugins_loaded() {
            if (class_exists('WC_Local_Pickup_Plus')) {
            }
            if (is_admin()) {
				if(is_multisite()) {
					global $wpdb;
					// Retrieve all site IDs from this network (WordPress >= 4.6 provides easy to use functions for that).
					if (function_exists('get_sites') && function_exists('get_current_network_id')) {
						$site_ids = get_sites(array('fields' => 'ids', 'network_id' => get_current_network_id()));
					} else {
						$site_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs WHERE site_id = $wpdb->siteid;");
					}

					foreach ($site_ids as $site_id) {
						switch_to_blog($site_id);
						$this->update_plugin_db();
						restore_current_blog();
					}
				}
				else {
					$this->update_plugin_db();
				}
            }
        }

        private function update_plugin_db() {

            global $wpdb;

			$this->imaxel_db_version=get_option("imaxel_db_version");

            $table_name = $wpdb->prefix . 'imaxel_woo_projects';
            $result = $wpdb->query("SHOW COLUMNS FROM " . $table_name . " LIKE 'weight_project'");
            if ($result == 0) {
                $wpdb->query("ALTER TABLE " . $table_name . " ADD weight_project FLOAT NULL DEFAULT 0");
            }

            $table_name = $wpdb->prefix . 'imaxel_woo_products';
            $result = $wpdb->query("SHOW COLUMNS FROM " . $table_name . " LIKE 'variants'");
            if ($result == 0) {
                $wpdb->query("ALTER TABLE " . $table_name . " ADD variants TEXT NULL");
            }

            $table_name = $wpdb->prefix . 'imaxel_woo_projects';
            $result = $wpdb->query("SHOW COLUMNS FROM " . $table_name . " LIKE 'description_project'");
            if ($result == 0) {
                $wpdb->query("ALTER TABLE " . $table_name . " ADD description_project TEXT NULL");
            }

            $table_name = $wpdb->prefix . 'imaxel_woo_projects';
            $result = $wpdb->query("SHOW COLUMNS FROM " . $table_name . " LIKE 'prints_project'");
            if ($result == 0) {
                $wpdb->query("ALTER TABLE " . $table_name . " ADD prints_project tinyint(1) NOT NULL DEFAULT 0");
            }

            $table_name = $wpdb->prefix . 'imaxel_woo_projects';
            $result = $wpdb->query("SHOW COLUMNS FROM " . $table_name . " LIKE 'prints_requested_project'");
            if ($result == 0) {
                $wpdb->query("ALTER TABLE " . $table_name . " ADD prints_requested_project int NOT NULL DEFAULT 0");
            }

            $table_name = $wpdb->prefix . 'imaxel_woo_projects';
            $result = $wpdb->query("SHOW COLUMNS FROM " . $table_name . " LIKE 'services_sku'");
            if ($result == 0) $wpdb->query("ALTER TABLE " . $table_name . " ADD services_sku varchar(255) CHARACTER SET utf8 DEFAULT NULL");

            //#HELP-8
            if(strcmp("2.0.0.0",$this->imaxel_db_version)>0) {
                $this->imaxel_db_version="2.0.0.0";
                $sql = "DELETE FROM " . $wpdb->prefix . "imaxel_woo_products WHERE type=1";
                $wpdb->query($sql);

                $table_name = $wpdb->prefix . 'imaxel_woo_projects';
                $result = $wpdb->query("SHOW COLUMNS FROM " . $table_name . " LIKE 'date_project'");
                if ($result == 0) {
                    $wpdb->query("ALTER TABLE " . $table_name . " ADD date_project DATETIME NULL DEFAULT 0");
                }

                $table_name = $wpdb->prefix . 'icp_products_projects';
                $result = $wpdb->query("SHOW COLUMNS FROM " . $table_name . " LIKE 'project_name'");
                if ($result == 0) {
                    $wpdb->query("ALTER TABLE " . $table_name . " ADD project_name varchar(255) CHARACTER SET utf8");
                }
            }

			if(strcmp("2.1.0.0",$this->imaxel_db_version)>0) {
				$table_name = $wpdb->prefix . 'imaxel_woo_orders';
				$wpdb->query( "DROP TABLE IF EXISTS " . $table_name);
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				$charset_collate = $wpdb->get_charset_collate();
				$table_name = $wpdb->prefix . 'imaxel_woo_orders';
				$sql = "CREATE TABLE $table_name (
					`woocommerce_id` int(10) NOT NULL,
					`imaxel_creative_id` int(10) NULL,
					`imaxel_icp_id` int(10) NULL,
					`created_date` DATETIME NOT NULL,
					`updated_date` DATETIME NULL,
					PRIMARY KEY (`woocommerce_id`)
				) $charset_collate;";
				//create table
				$result=dbDelta($sql);
				$this->imaxel_db_version="2.1.0.0";
			}

            update_option('imaxel_db_version', $this->imaxel_db_version);
        }

        public function imaxel_after_setup_theme() {
        }

        #region Funciones asignacion proyectos anonimos
        private function imaxel_assign_anonymous_project($userID)
        {
            //Analizar carrito
            global $woocommerce;
            global $wpdb;

            if($woocommerce->cart!=null)
            {
                $items = $woocommerce->cart->get_cart();
                foreach ($items as $key => $value)
                {
                    if (array_key_exists("variation", $value))
                    {
                        if (array_key_exists("attribute_proyecto", $value["variation"]))
                        {
                            $projectID = $value["attribute_proyecto"];//#WST-26
                            if ($projectID)
                            {
                                $sql = 'SELECT * FROM ' . $wpdb->prefix . 'imaxel_woo_projects
                                        WHERE id_project =' . $projectID . " AND id_customer=0";
                                $row = $wpdb->get_row($sql);
                                if ($row)
                                {
                                    $sql = "UPDATE " . $wpdb->prefix . "imaxel_woo_projects
                                            SET id_customer=" . $userID .
                                            " WHERE id_project=" . $projectID;
                                    $wpdb->query($sql);
                                }
                            }
                        }
                    }
                }
            }
        }

        public function imaxel_register_user($userID) {
            $guestModeEnabled = get_option("wc_settings_tab_imaxel_allow_guest");
            if ($guestModeEnabled == "yes") {
                if (!is_admin()) {
                    $this->imaxel_assign_anonymous_project($userID);
                }
            }
        }

        public function imaxel_login_user($user_login, $user) {
            $guestModeEnabled = get_option("wc_settings_tab_imaxel_allow_guest");
            if ($guestModeEnabled == "yes") {

                $userID = $user->ID;
                $this->imaxel_assign_anonymous_project($userID);
            }
        }
        #endregion

        #region Funciones privadas

        private function imaxel_insert_project($product_id)
        {
            global $wpdb;

            $imaxelOperations = new ImaxelOperations();
            $privateKey = get_option("wc_settings_tab_imaxel_privatekey");
            $publicKey = get_option("wc_settings_tab_imaxel_publickey");

            $userID = get_current_user_id();
            $projectID = $_GET["attribute_proyecto"];
            $id_product_attribute = $_GET["variation_id"];
            $productType = 0; //HTML Product

            $projectInfo = $imaxelOperations->readProject($publicKey, $privateKey, $projectID);
            if ($projectInfo)
            {
                $projectInfo = json_decode($projectInfo);
                $productName = $projectInfo->product->name->default;
                $productPrice = $projectInfo->design->price;
                $productVariant = $projectInfo->design->variant_code;
                $variantSku = !empty($projectInfo->design->sku) ? $projectInfo->design->sku : NULL;
            }

            $_product = wc_get_product($product_id);
            $_product_price = $_product->get_price();

            $exists = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "imaxel_woo_projects WHERE id_customer=" . $userID . " AND id_project=" . $projectID);
            if (!$exists) {
                $sql = "INSERT INTO " . $wpdb->prefix . "imaxel_woo_projects (id_customer, id_project, type_project,id_product, id_product_attribute, price_project, services_sku)
                        VALUES (
                        " . $userID . "," . $projectID . "," . $productType . "," . $product_id . "," . $id_product_attribute . "," . $productPrice .
                    ",'" . $variantSku . "')";
                $wpdb->query($sql);
            }
        }

        #HELP-8
        function icpLoadScripts()
        {
            wp_enqueue_script('vue', 'https://cdn.jsdelivr.net/npm/vue/dist/vue.js', TRUE);
            wp_enqueue_script('vue-upload-component', 'https://cdn.jsdelivr.net/npm/vue-upload-component', TRUE);
            wp_enqueue_script('font-awesome', 'https://kit.fontawesome.com/86d5966c90.js', TRUE);
            wp_enqueue_style('icp-product', plugins_url('/assets/css/icp_styles.css', __FILE__));
            wp_enqueue_script('icp_script', plugins_url('/assets/js/icp_main.js', __FILE__), array('jquery'), $this->plugin_version);
            wp_localize_script('icp_script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
            $primaryColor = ShopService::getPrimaryColor();
            icpLoadView('icp_style.php', ['primaryColor' => $primaryColor]);
        }

        #endregion

        #region icp

        #region ICP View functions

        function load_icp_view()
        {
            //TODO: DPI Review page edit mode                 if ( get_current_screen()->parent_base == 'edit' ) {
            //get permalink where shortcode is beign executed and save it
            $postID = get_the_ID();
            $currentURL = get_permalink($postID);
            update_option('icp_url',$currentURL);

            //load icp page structure
            if (!is_admin()) {
                $productID = get_query_var('id');
                $siteID = get_query_var('site');
                $blockID = get_query_var('block');
                if (!empty($productID) && !empty($siteID) && !empty($blockID)) {
                    $this->loadProductViewData($productID, $siteID, $blockID, $currentURL);
                } else {
                    echo '<p>' . __('Please select a valid product.', 'imaxel') . '</p>';
                }
            }
        }

        /**
         * laod product view data
         */
        function loadProductViewData($productID, $siteID, $blockID, $currentURL) {

            $productData = IcpService::loadProductData($productID, $siteID);
            $productDataJson = json_encode($productData);

            $saveGuestIcpProject = !is_user_logged_in() && isset($_GET['unregisted_user_redirect_page']) ? true : false;
            $urlRedirect = $saveGuestIcpProject ? IcpService::getUrlLoginFromEditorGuest($_GET) : '';

            $blockID = $_GET['block'];
            $projectID = $_GET['icp_project'] ?? null;
            $wproduct = $_GET['wproduct'] ?? null;
            $icpProjectData = isset($projectID) ? IcpProductsProjectsModel::origin()->getById($projectID) : null;
            $primaryColor = ShopService::getPrimaryColor();
            //TODO: DPI incompatible con PRINTSPOT
            if(is_multisite()) {
                $codLang = get_site_current_language();
            }
            else{
                $language = get_locale();
                $codLang = substr( $language, 0, 2 );
            }
            $backUrl = wc_get_page_permalink('shop');
            $productName = $productData->definition->name->$codLang ?? $productData->definition->name->default;

            //if is called after login, then we update icp project
            if (isset($_GET['redirect_from_login'])) {
                // if is redirected from login, update owner icp project
                IcpProductsProjectsModel::origin()->updateUserOwner($_GET['icp_project']);
            }

            //get user id and project user id and  data
            if ((is_user_logged_in()) && (isset($_GET['icp_project']))) {
                $userID = get_current_user_id();
                $getCurrentProjectUser = IcpProductsProjectsModel::origin()->getById($projectID);
                (intval($getCurrentProjectUser->user) == intval($userID)) ? $projectBelongsToUser = true : $projectBelongsToUser = false;
                $dealerID = $getCurrentProjectUser->dealer;
            }

            $grantAccess = ((isset($_GET['icp_project']) && (isset($_SESSION['sessionProject_' . get_current_blog_id()]) && $_SESSION['sessionProject_' . get_current_blog_id()] == $_GET['icp_project'])) || !isset($_GET['icp_project']) || (isset($projectBelongsToUser) && $projectBelongsToUser == true)) ? true : false;

            if ($grantAccess) {

                //get blocks translation
                foreach ($productData->blocks as $k => $block) {
					$productData->blocks[$k]->definition->block_name = (empty($block->definition->block_name->$codLang) || !$block->definition->block_name->$codLang) ? (isset($productData->blocks[$k]->definition->block_name->default) ? $productData->blocks[$k]->definition->block_name->default : '') : $block->definition->block_name->$codLang;                    $productData->blocks[$k]->definition->block_title = $block->definition->block_title->$codLang ?? $productData->blocks[$k]->definition->block_title->default;
                    $productData->blocks[$k]->definition->block_short_description = $block->definition->block_short_description->$codLang ?? $block->definition->block_short_description->default;
                    $productData->blocks[$k]->definition->block_long_description = $block->definition->block_long_description->$codLang ?? $block->definition->block_long_description->default;
                }

                //TODO: ERROR EN PARAMETROS
                $navbarBlocks = $this->getProductBlocksFlow($productData, $productID, $productName, $siteID, $blockID, $currentURL, $wproduct);

                $currentBlockData = $this->getCurrentBlockData($productData);
                $blogType = $this->getCurrentBlogType($productData);

                $productType=$productData->definition->product_type;
                if($productType=="simple"){
					$backURL = get_home_url();
					$cartURL = get_home_url();
					$cartURLParameters = "?imx-add-to-cart=" . $wproduct;
					$saveURL = get_home_url();
					$saveURLParameters = "?imx-add-to-project=" . $wproduct;
					$imaxelOperations = new ImaxelOperations();
					if(!function_exists('is_plugin_active')) {
						include_once ( ABSPATH . 'wp-admin/includes/plugin.php' ); // required for is_plugin_active
					}
					$credentials = IcpService::getDealerCredentials($siteID, $dealerID);
					$privateKey = $credentials->privateKey;
					$publicKey = $credentials->publicKey;

					$simpleEditorURL = $imaxelOperations->createProject($publicKey, $privateKey,$productID,null, $productData->definition->simple_product_code, null, $cartURL, $cartURLParameters, $backURL, $saveURL, $saveURLParameters);
				}
            }
            //view
            include('views/product_view.php');
        }

        #endregion

        #region AJAX calls


        function savePdfUploader()
        {

            //get data
            $projectID = $_POST['currentProject'];
            $blockID = $_POST['currentBlock'];
            $siteID = $_POST['currentSite'];
            $currentURL = $_POST['currentURL'];
            $productID = $_POST['productID'];
            $pdfID = $_POST['pdfID'];
            $pdfName = $_POST['pdfName'];
            $pdfWidth = $_POST['pdfWidth'];
            $pdfHeight = $_POST['pdfHeight'];
            $price = $_POST['price'];
            if (!empty($_POST['wproduct'])) {
                $wproduct = $_POST['wproduct'];
            } else {
                $wproduct = '';
            }

            //Update project components
            $projectData = $currentProjectData = IcpService::getProjectData($projectID);
            if (isset($currentProjectData['components'][$blockID])) {
                unset($currentProjectData['components'][$blockID]);
            }
            $currentProjectData['components'][$blockID]['pdf']['key'] = $pdfID;
            $currentProjectData['components'][$blockID]['pdf']['name'] = $pdfName;
			$currentProjectData['components'][$blockID]['pdf']['width'] = $pdfWidth;
			$currentProjectData['components'][$blockID]['pdf']['height'] = $pdfHeight;
            $newProjectData = serialize($currentProjectData['components']);
            IcpService::updateProjectComponent($newProjectData, $projectID, $price, $blockID, $pdfWidth, $pdfHeight);

            //get project active variation
            $activeVariation = $projectData['variation'];

            //response new block url
            $newURL = IcpService::getNextBlock($currentURL, $productID, $activeVariation, $blockID, $siteID, $projectID,$wproduct);
            echo $newURL;

            //finish
            wp_die();
        }

        private function pdfstorageCreateFile($path, $token)
        {
            if (function_exists('curl_file_create')) { // php 5.5+
                $cFile = curl_file_create($path);
            } else { //
                $cFile = '@' . realpath($path);
            }

            //TENGO QUE DEDUCIR EL MIME ANTES DE MANADRLO
            $cFile->mime = "application/pdf";
            //$cFile->mime = "image/jpg";

            $post = array(/*'access_token' => $token,*/
                'file_content' => $cFile);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Authorization: Bearer $token"
            ));
            //production service
            curl_setopt($ch, CURLOPT_URL, "https://services.imaxel.com/apis/pdfstorage/v2/pdfs");

            //local service
            //curl_setopt($ch, CURLOPT_URL,"http://localhost:8084/apis/mediastorage/v3/medias");

            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return array("status" => $httpcode, "result" => json_decode($result));
        }

        /**
         * PDF_UPLOADER: recive pdf values from API
         */
        private function icpDoPostCreate($file)
        {
            $token = IcpService::generateToken("pdfs:create");
            if ($token !== null) {
                return $this->pdfstorageCreateFile($file["tmp_name"], $token);
            } else {
                return null;
            }
        }

        /**
         * PDF_UPLOADER: read pdf file
         */
        private function readPDFfile($pdfKey)
        {
            $token = IcpService::generateToken("pdfs:read");
            $curl = curl_init();
            $headers = array(
                'Content-Type: application/json',
                sprintf('Authorization: Bearer %s', $token)
            );
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_URL, "https://services.imaxel.com/apis/pdfstorage/v2/pdfs/" . $pdfKey);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $resultData = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpcode === 200) {
                $result = json_decode($resultData);
                return $result;
            } else {
                return null;
            }
        }

        /**
         * PDF_UPLOADER: display pdf uploader
         */
        function displayPDFuploader($projectData, $variationID = '', $blockID, $projectID, $siteID, $currentURL, $productID, $pdfAttributes, $productData, $wproduct = '', $blockAttributes)
        {


            echo '<div class="design_pdf_uploader_content">';
            //constrains table and uploader form
            /*===================================================================*/
            echo '<div class="pdf_uploader_form">';
            echo '<div class="pdf_constrains_table">';

            echo '<div class="pdf_file_characteristics_box">';

            $primaryColor = ShopService::getPrimaryColor();
            echo '<div class="pdf_file_characteristics_box_icon"><i style="color:' . $primaryColor . '; margin-bottom: 10px;" class="fas fa-file-pdf fa-2x"></i></div>';
            echo '<p>' . __('Upload a PDF file with the following characteristics', 'imaxel') . ':</p>';

            //max size
            echo '<p><strong>' . __('Max. Size', 'imaxel') . ': </strong> 10MB';

            //set pdf options
            $blockBehaviourKey = array_search('pdf_constraint_behaviour', $blockAttributes['pdf_upload']['value_key']);
            $blockBehaviour = $blockAttributes['pdf_upload']['value_data'][$blockBehaviourKey]['data'];

            //if specific_constraints
            if ($blockBehaviour == 'specific_constraints') {

                //page size
                $valueKey = array_search('pdf_constraint_size', $blockAttributes['pdf_upload']['value_key']);
                if ($valueKey !== false) {
                    $pdfSize = $blockAttributes['pdf_upload']['value_data'][$valueKey]['data'];
                } else {
                    $pdfSize = 0;
                }

                //pages limit
                $valueKey = array_search('pdf_constraint_pages', $blockAttributes['pdf_upload']['value_key']);
                if ($valueKey !== false) {
                    $pdfPages = $blockAttributes['pdf_upload']['value_data'][$valueKey]['data'];
                } else {
                    $pdfPages = 0;
                }

                //page price
                $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                if ($valueKey !== false) {
                    $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]['data']);
                } else {
                    $pagePrice = 0;
                }

                //price per area
                $valueKey = array_search('pdf_constraint_price_per_area', $blockAttributes['pdf_upload']['value_key']);
                if ($valueKey !== false) {
                    $areaPrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]['data']);
                } else {
                    $areaPrice = null;
                }

                //area size
                $valueKey = array_search('pdf_constraint_area_size', $blockAttributes['pdf_upload']['value_key']);
                if (($valueKey !== false) && (@unserialize($blockAttributes['pdf_upload']['value_data'][$valueKey]['data']))) {
                    $areaSize = unserialize($blockAttributes['pdf_upload']['value_data'][$valueKey]['data']);
                } else {
                    $areaSize = null;
                }

                //if set to take rules constranis
            } elseif ($blockBehaviour == 'use_variation_data' || $blockBehaviour == 'variation_constraint') {

                //if rule is selected
                if (!empty($variationID)) {

                    //COMPATIBILITY WITH OLD MODEL: get project values in case OLD PDF ATTRIBUTES are used======================================//
                    $getVariationData = $productData->blocks[0]->variations->$variationID->attributes;
                    foreach ($getVariationData as $attributeID => $attributeValue) {
                        if ($productData->blocks[0]->attributes->$attributeID->definition->attribute_type === 'pdf_upload') {
                            $attrValue = $attributeValue->value;
                            $projectPdfAttribute[$productData->blocks[0]->attributes->$attributeID->definition->attribute_slug] = $productData->blocks[0]->attributes->$attributeID->values->$attrValue->value_data;
                        }
                    }

                    $ruleData = $productData->blocks[0]->variations->$variationID;

                    //price per page
                    if (@unserialize($ruleData->price_per_page)) {
                        $rulePricePerPageData = unserialize($ruleData->price_per_page);
                        $pagePrice = $rulePricePerPageData[$blockID];
                    } else {
                        $pagePrice = $ruleData->price_per_page;
                    }

                    //price per area
                    if (@unserialize($ruleData->price_per_area)) {
                        $rulePricePerAreaData = unserialize($ruleData->price_per_area);
                        $areaPrice = $rulePricePerAreaData[$blockID];
                    } else {
                        $areaPrice = NULL;
                    }

                    //area limits
                    if (@unserialize($ruleData->area_size)) {
                        $rulePriceAreaSizeData = unserialize($ruleData->area_size);
                        if (isset($rulePriceAreaSizeData['max_width'])) {
                            $areaSize = $rulePriceAreaSizeData;
                        } else {
                            $areaSize = unserialize($rulePriceAreaSizeData[$blockID]);
                        }
                    } else {
                        $areaSize = $ruleData->price_per_area;
                    }

                    //page size
                    //COMPATIBILITY WITH OLD MODEL: while pdf attribute option exists and is asigned to a variation, it will prevail
                    $rulePriceAreaSizeData = unserialize($ruleData->pdf_size);
                    $pdfSize = is_array($rulePriceAreaSizeData) ? $rulePriceAreaSizeData[$blockID] : null;
                    if (empty($pdfSize) && (isset($projectPdfAttribute['size']) && !empty($projectPdfAttribute['size']))) {
                        $pdfSize = $projectPdfAttribute['size'];
                    }

                    //page limit
                    $rulePriceAreaSizeData = unserialize($ruleData->pdf_pages);
                    $pdfPages = is_array($rulePriceAreaSizeData) ? $rulePriceAreaSizeData[$blockID] : null;
                    if (empty($pdfPages) && (isset($projectPdfAttribute['pages']) && !empty($projectPdfAttribute['pages']))) {
                        $pdfPages = $projectPdfAttribute['pages'];
                    }

                    //if no rule is selected
                } else {

                    //COMPATIBILITY WITH OLD MODEL: get project values in case OLD PDF ATTRIBUTES are used======================================//
                    if (@unserialize($projectData['components'][$productData->blocks[0]->definition->block_id]['variation'])) {
                        $projectStepDefinition = unserialize($projectData['components'][$productData->blocks[0]->definition->block_id]['variation']);
                        foreach ($projectStepDefinition as $attributeID => $attributeValue) {
                            if ($productData->blocks[0]->attributes->$attributeID->definition->attribute_type === 'pdf_upload') {
                                $projectPdfAttribute[$productData->blocks[0]->attributes->$attributeID->definition->attribute_slug] = $productData->blocks[0]->attributes->$attributeID->values->$attributeValue->value_data;
                            }
                        }
                    }

                    //set pdf pages
                    if (isset($projectPdfAttribute['pages'])) {
                        $pdfPages = $projectPdfAttribute['pages'];
                    } else {
                        $pdfPages = null;
                    }

                    //set pdf size
                    if (isset($projectPdfAttribute['size'])) {
                        $pdfSize = $projectPdfAttribute['size'];
                    } else {
                        $pdfSize = null;
                    }

                    //===========================================================================================================================//

                    $pagePrice = 0;
                    $areaPrice = 0;
                    $areaSize = null;
                }
            } else {

                $areaSize = null;
                $pagePrice = 0;
                $areaPrice = 0;
                $pdfSize = null;
                $pdfPages = null;
            }


            //base price
            $basePrice = 0;
            $projectPriceData = unserialize($projectData['price']);
            foreach ($projectPriceData as $block => $blockPrice) {
                if (IcpService::isBlockData($block) && $block !== intval($blockID)) {
                    $basePrice = floatval($basePrice) + floatval($blockPrice['total_price']);
                }
            }
            $displayBasePrice = drawPrice($basePrice);

            //project price
            $unitPriceTotal = 0;
            $projectPriceData = unserialize($projectData['price']);
            $quantity = $projectPriceData['qty'];
            foreach ($projectPriceData as $block => $blockPrice) {
                if (IcpService::isBlockData($block)) {
                    $unitPriceTotal = (floatval($unitPriceTotal) + floatval($blockPrice['total_price']));
                }
            }

            $priceTotal = $unitPriceTotal * intval($quantity);

            //pdf attribute size
            if (empty($areaPrice)) {

                //pdf size restriction is setted
                if (!empty($pdfSize)) {
                    $pdfAttributeSize = unserialize($pdfSize);
                    if ($pdfAttributeSize['width'] != 0 && $pdfAttributeSize['height'] != 0) {
                        echo '<p><strong>' . __('Size', 'imaxel') . ': </strong>';
                        echo $pdfAttributeSize['width'] . ' x ' . $pdfAttributeSize['height'] . ' mm';
                        echo '</p>';
                    }

                }
            }

            //pdf size restriction is setted
            if (empty($areaPrice)) {
                if (!empty($pdfPages)) {
                    echo '<p><strong>' . __('Pages', 'imaxel') . ': </strong>';
                    $pdfAttributePage = unserialize($pdfPages);
                    if (intval($pdfAttributePage['min']) === intval($pdfAttributePage['max'])) {
                        echo $pdfAttributePage['max'];
                    } else {
                        echo $pdfAttributePage['min'] . "-" . $pdfAttributePage['max'];
                    }
                    echo '</p>';
                }
            } else {
                echo '<p><strong>' . __('Pages', 'imaxel') . ': </strong>1';
            }

            //display price per page and area
            if (($pagePrice !== 0 && $pagePrice !== 0.0 && !empty($pagePrice)) && ($areaPrice == 0.0 || $areaPrice == 0)) {
                echo '<p><strong>' . __('Price per page', 'imaxel') . ':</strong> ' . drawPrice($pagePrice) . '</span></p>';
            }

            if ($areaPrice !== 0.0 && $areaPrice !== 0 && !empty($areaPrice)) {
                echo '<p><strong>' . __('Price per area', 'imaxel') . ' (m²):</strong> ' . drawPrice($areaPrice) . '</span></p>';
            }

            //display area selector
            if (!empty($areaPrice)) {
                $areaMinWidth = 0;
                $areaMaxWidth = 0;
                $areaMinHeight = 0;
                $areaMaxHeight = 0;

                if (isset($areaSize) && !empty($areaSize)) {
                    $areaMinWidth = ($areaSize["min_width"] > 0) ? $areaSize["min_width"] : 0;
                    $areaMaxWidth = ($areaSize["max_width"] > 0) ? $areaSize["max_width"] : 0;
                    $areaMinHeight = ($areaSize["min_height"] > 0) ? $areaSize["min_height"] : 0;
                    $areaMaxHeight = ($areaSize["max_height"] > 0) ? $areaSize["max_height"] : 0;
                }

                $shopColor = ShopService::getPrimaryColor();
                echo '<p><strong>' . __('Select an area', 'imaxel') . ': </strong></p>';
                echo '<div style="display: none;" id="pdf_form_help_2"><p style="color:' . $shopColor . '!important;"><small>' . __("Please, enter a correct area size", "imaxel") . '</small></p></div>';
                if ($areaMinHeight || $areaMinWidth || $areaMaxHeight || $areaMaxWidth) {
                    echo '<p>' . __('Height', 'imaxel') . ' (' . __('min.', 'imaxel') . ' ' . $areaMinHeight . ' cm ';
                    if ($areaMaxHeight) echo __('max.', 'imaxel') . ' ' . $areaMaxHeight . ' cm';
                    echo ')<input required id="pdf_height" type="number" step="0.01"   min="' . $areaMinHeight . '" max="' . $areaMaxHeight . '"></input></p>';
                    echo '<p>' . __('Width', 'imaxel') . ' (' . __('min.', 'imaxel') . ' ' . $areaMinWidth . ' cm ';
                    if ($areaMaxWidth) echo __('max.', 'imaxel') . ' ' . $areaMaxWidth . ' cm';
                    echo ')<input required id="pdf_width" type="number" step="0.01"   min="' . $areaMinWidth . '" max="' . $areaMaxWidth . '"></input></p>';
                } else {
                    echo '<p>' . __('Height', 'imaxel') . ' (cm) <input required id="pdf_height" type="number" step="0.01"   min="0"></input></p>';
                    echo '<p>' . __('Width', 'imaxel') . ' (cm) <input required id="pdf_width" type="number" step="0.01"   min="0"></input></p>';
                }
                echo '<p><strong>' . __('Selected area to print', 'imaxel') . ': </strong><span id="pdf_total_area">0</span> m²</p>';
				echo '<p style="margin-top: 12.5px;"><strong>' . __('Area printing price', 'imaxel') . ': </strong><span  data-price="0" id="pdf_price_area">' . drawPrice(0) . '</span></p>';
				echo '<p style="margin-top: 12.5px;"><strong>' . __('Unitary price', 'imaxel') . ': </strong><span id="project_total_price">' . drawPrice($priceTotal) . '</span></p>';
            }

            //upload file form
            ?>
            <div style="margin-top: 12.5px;">
                <p><strong><?php echo __('Select a PDF file', 'imaxel'); ?>:</strong></p>
                <div style="display: none;" id="pdf_form_help">
                    <p>
                        <small><i><?php echo __('Once an area is selected you will be able to upload your PDF.', 'imaxel'); ?></i></small>
                    </p>
                </div>
                <form enctype="multipart/form-data" name="pdf_form" id="pdf_form" method="POST">
                    <?php echo '<input accept="application/pdf" name="pdf_file" id="pdf_file" project_id="' . $projectID . '" block_id="' . $blockID . '" site_id="' . $siteID . '" currentURL="' . $currentURL . '" product_id="' . $productID . '"'; ?>
                    <?php if (!empty($wproduct)) {
                        echo ' wproduct=' . $wproduct;
                    } ?>
                    <?php echo ' type="file" />'; ?>
                </form>
            </div>
			<?php if (!empty($areaPrice)) { ?>
			<input type="hidden" id="keydown-calculate-data"
				   data-quantity="<?php echo $quantity ?>"
				   data-area-price="<?php echo $areaPrice ?>"
				   data-area-min-width="<?php echo $areaMinWidth ?>"
				   data-area-max-width="<?php echo $areaMaxWidth ?>"
				   data-area-min-height="<?php echo $areaMinHeight ?>"
				   data-area-max-height="<?php echo $areaMaxHeight ?>"
				   data-project-price="<?php echo $unitPriceTotal ?>"
			>
			<script>
				//hide pdf form
				jQuery('#pdf_form').hide();
				jQuery('#pdf_form_help').show();

			</script>
		<?php } ?>

			</div>
			</div>
			</div>
			<?php

            //pdf summary box
            /*===================================================================*/
            //get and set theme color
            $primaryColor = ShopService::getPrimaryColor();
            echo '<style>
                        .lds-ring div {border-color: ' . $primaryColor . ' transparent transparent transparent !important;}
                        .lds-ellipsis div {background: ' . $primaryColor . '}
                </style>';

            echo '<div class="pdf_summary_box">';
            if (isset($projectData['components'][$blockID]['pdf'])) {

                ?>
                <script>
                    jQuery('.icp-forth.disabled').hide();
                    jQuery('.icp-forth.enabled.pdf-nav').show();
                    jQuery('.icp-forth.enabled.design-nav').hide();
                </script>
                <?php

                //recover pdf data
                $pdfID = $projectData['components'][$blockID]['pdf']['key'];
                $pdfName = $projectData['components'][$blockID]['pdf']['name'];
                $pdfData = $this->readPDFfile($pdfID);
                if (!empty($pdfData)) {
                    $recoverPdf = $pdfData;
                } else {
                    $recoverPdf = '<p>This PDF no longer exists</p>';
                }

                //is discounts enabled
                if (isset($productData->discounts)) {
                    $isActiveDiscounts = 'on';
                } else {
                    $isActiveDiscounts = 'off';
                }

                echo '<p>' . __('Your project is currently using this PDF file:', 'imaxel') . '</p>';

                echo '<div class="pdf_file_box">';

                //pdf thumbnail
                echo '<div class="pdf_thumbanil">';
                echo '<img src="' . $recoverPdf->descriptor->pages[0]->thumbnail_512 . '">';
                echo '</div>';

                //pdf summary
                echo '<div class="pdf_file_summary">';

                echo '<div class="pdf_file_summary_text">';
                echo '<p><strong>' . __('File name', 'imaxel') . ': </strong>' . $pdfName . '</p>';

                //calculate page prices
                $pdfPrice = floatval($pagePrice) * floatval($pdfData->descriptor->numPages);

                //calculate area prices
                if (!empty($areaPrice)) {
                    $areaWidth = $projectPriceData[$blockID]['pdf_width'];
                    $areaHeight = $projectPriceData[$blockID]['pdf_height'];
                    $realPdfHeight = number_format(floatval($pdfData->descriptor->pages[0]->size->height) / 2835, 2);
                    $realPdfWidth = number_format(floatval($pdfData->descriptor->pages[0]->size->width) / 2835, 2);
                    ?>
					<input type="hidden" id="icp-pdf-project-data"
						   data-area-width="<?php echo $areaWidth ?>"
						   data-area-height="<?php echo $areaHeight ?>"
						   data-project-price="<?php echo $unitPriceTotal ?>"
						   data-area-price="<?php echo $areaPrice ?>"
						   data-quantity="<?php echo $quantity ?>"
                    <?php
                }

                if (empty($areaPrice)) {
                    echo '<p><strong>' . __('Pages', 'imaxel') . ': </strong>' . $recoverPdf->descriptor->numPages . '</p>';
                    if (isset($pdfPrice) && floatval($pdfPrice) !== 0.0) { ?>
                        <p>
                            <strong><?php echo __('Base price', 'imaxel') ?>: </strong>
                            <span id="summaryBasePrice"><?php echo $displayBasePrice ?></span>
                        </p>
                        <p>
                            <strong><?php echo __('Pdf price', 'imaxel') ?>: </strong>
                            <?php echo drawPrice($pdfPrice) ?>
                        </p>
                        <p>
                            <strong><?php echo __('Unit price', 'imaxel') ?>: </strong>
                            <span id="summaryUnitPrice"><?php echo drawPrice($unitPriceTotal) ?></span>
                        </p>
                        <p>
                            <strong><?php echo __('Project price', 'imaxel') ?>: </strong>
                            <span id="summaryProjectPrice">
								<?php echo $quantity . ' x ' . drawPrice($unitPriceTotal) . ' = ' . drawPrice($priceTotal) ?>
							</span>
                        </p>

                    <?php }
                } else {
                    $realPdfWidth = $realPdfWidth * 100;
                    $realPdfHeight = $realPdfHeight * 100;
                    echo '<p><strong>' . __('Size', 'imaxel') . ': </strong>' . $realPdfWidth . ' / ' . $realPdfHeight . ' cm</p>';
                }
                echo '</div>';

                //check if project is the last, if yes show "AddToCart" if not show "Next"
                foreach ($productData->blocks as $block) {
                    $blocksDataByID[$block->definition->block_id] = $block->definition;
                    $blockDataByOrder[$block->definition->block_order] = $block->definition;
                }
                $maxOrderBlock = max(array_keys($blockDataByOrder));
                (intval($blocksDataByID[$blockID]->block_order) !== $maxOrderBlock) ? $nextButtonText = __('Next', 'imaxel') : $nextButtonText = __('Add To cart', 'imaxel');

                //pdf next button
                echo '<div class="pdf_next_button_box">';
                echo '<div class="button-loader-box">';
				echo '<div style="float:left;" class="button" style="margin-left: 2.5px;" onclick="icppdf.savePdfBlock()" id="savePdfBlock" pdf_name="' . $pdfName . '" project_id="' . $projectID . '" area_price="' . $areaPrice . '" block_id="' . $blockID . '" site_id="' . $siteID . '" currentURL="' . $currentURL . '" pdf_id="' . $pdfID . '" price="' . $pagePrice . '" product_id="' . $productID . '"';
				if (!empty($wproduct)) {
                    echo 'wproduct="' . $wproduct . '"';
                }
                echo '>' . $nextButtonText . '  <i class="fas fa-chevron-circle-right"></i></div>';
                echo '<div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div>';
                echo '</div>';
                echo '</div>';

                echo '</div>';

                echo '</div>';
            } else {
                echo '<p>' . __('Upload a file in order to validate it..') . '</p>';
            }
            echo '</div>';

            echo '</div>';
        }

        function validatePdfUploader()
        {

            try {
                //get data
                $projectID = $_POST['currentProject'];
                $blockID = $_POST['currentBlock'];
                $siteID = $_POST['currentSite'];
                $currentURL = $_POST['currentURL'];
                $productID = $_POST['productID'];
                $wproduct = $_POST['wproduct'];
                $pdfFile = $_FILES['pdf_file'];
                IcpPdfService::checkUpload($pdfFile);
                //get product data
                $productData = IcpService::loadProductData($productID, $siteID);

                //send pdf file to Imaxel Service API
                $pdfResults = $this->icpDoPostCreate($pdfFile);
                $pdfKey = $pdfResults['result']->key;
                if ($pdfResults['status'] == 200) {

                    //number of pages
                    $realPdfPages = $pdfResults['result']->descriptor->numPages;

                    //pages area
                    $realPdfWidth = floatval($pdfResults['result']->descriptor->pages[0]->size->width);
                    $realPdfHeight = floatval($pdfResults['result']->descriptor->pages[0]->size->height);

                    //current project
                    $project = IcpService::getProjectData($projectID);
                    foreach ($project['components'] as $data) {
                        foreach ($data as $type => $value) {
                            ($type == 'variation') ? $projectData[$type] = $value : $projectData[$type][] = $value;
                        }
                    }

                    //get project price
                    $blockAttributes = $this->getBlockAttributes($siteID, $productID, $blockID, $productData);
                    if (isset($project['variation'])) {
                        $variationID = $project['variation'];
                    }

                    //set pdf options
                    $blockBehaviourKey = array_search('pdf_constraint_behaviour', $blockAttributes['pdf_upload']['value_key']);
                    $blockBehaviour = $blockAttributes['pdf_upload']['value_data'][$blockBehaviourKey]['data'];

                    //if specific_constraints
                    if ($blockBehaviour == 'specific_constraints') {

                        //page size
                        $valueKey = array_search('pdf_constraint_size', $blockAttributes['pdf_upload']['value_key']);
                        if ($valueKey !== false) {
                            $pdfSize = $blockAttributes['pdf_upload']['value_data'][$valueKey]['data'];
                        } else {
                            $pdfSize = 0;
                        }

                        //pages limit
                        $valueKey = array_search('pdf_constraint_pages', $blockAttributes['pdf_upload']['value_key']);
                        if ($valueKey !== false) {
                            $pdfPages = $blockAttributes['pdf_upload']['value_data'][$valueKey]['data'];
                        } else {
                            $pdfPages = 0;
                        }

                        //page price
                        $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                        if ($valueKey !== false) {
                            $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]['data']);
                        } else {
                            $pagePrice = 0;
                        }

                        //price per area
                        $valueKey = array_search('pdf_constraint_price_per_area', $blockAttributes['pdf_upload']['value_key']);
                        if ($valueKey !== false) {
                            $areaPrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]['data']);
                        } else {
                            $areaPrice = null;
                        }

                        //area size
                        $valueKey = array_search('pdf_constraint_area_size', $blockAttributes['pdf_upload']['value_key']);
                        if (($valueKey !== false) && (@unserialize($blockAttributes['pdf_upload']['value_data'][$valueKey]['data']))) {
                            $areaSize = unserialize($blockAttributes['pdf_upload']['value_data'][$valueKey]['data']);
                        } else {
                            $areaSize = null;
                        }

                        //if set to take rules constranis
                    } elseif ($blockBehaviour == 'use_variation_data' || $blockBehaviour == 'variation_constraint') {

                        //if rule is selected
                        if (!empty($variationID)) {

                            //COMPATIBILITY WITH OLD MODEL: get project values in case OLD PDF ATTRIBUTES are used======================================//
                            $getVariationData = $productData->blocks[0]->variations->$variationID->attributes;
                            foreach ($getVariationData as $attributeID => $attributeValue) {
                                if ($productData->blocks[0]->attributes->$attributeID->definition->attribute_type === 'pdf_upload') {
                                    $attrValue = $attributeValue->value;
                                    $projectPdfAttribute[$productData->blocks[0]->attributes->$attributeID->definition->attribute_slug] = $productData->blocks[0]->attributes->$attributeID->values->$attrValue->value_data;
                                }
                            }

                            $ruleData = $productData->blocks[0]->variations->$variationID;

                            //price per page
                            if (@unserialize($ruleData->price_per_page)) {
                                $rulePricePerPageData = unserialize($ruleData->price_per_page);
                                $pagePrice = $rulePricePerPageData[$blockID];
                            } else {
                                $pagePrice = $ruleData->price_per_page;
                            }

                            //price per area
                            if (@unserialize($ruleData->price_per_area)) {
                                $rulePricePerAreaData = unserialize($ruleData->price_per_area);
                                $areaPrice = $rulePricePerAreaData[$blockID];
                            } else {
                                $areaPrice = NULL;
                            }

                            //area limits
                            if (@unserialize($ruleData->area_size)) {
                                $rulePriceAreaSizeData = unserialize($ruleData->area_size);
                                if (isset($rulePriceAreaSizeData['max_width'])) {
                                    $areaSize = $rulePriceAreaSizeData;
                                } else {
                                    $areaSize = unserialize($rulePriceAreaSizeData[$blockID]);
                                }
                            } else {
                                $areaSize = $ruleData->price_per_area;
                            }

                            //page size
                            //COMPATIBILITY WITH OLD MODEL: while pdf attribute option exists and is asigned to a variation, it will prevail
                            $rulePriceAreaSizeData = unserialize($ruleData->pdf_size);
                            $pdfSize = $rulePriceAreaSizeData[$blockID];
                            if (empty($pdfSize) && (isset($projectPdfAttribute['size']) && !empty($projectPdfAttribute['size']))) {
                                $pdfSize = $projectPdfAttribute['size'];
                            }

                            //page limit
                            $rulePriceAreaSizeData = unserialize($ruleData->pdf_pages);
                            $pdfPages = $rulePriceAreaSizeData[$blockID];
                            if (empty($pdfPages) && (isset($projectPdfAttribute['pages']) && !empty($projectPdfAttribute['pages']))) {
                                $pdfPages = $projectPdfAttribute['pages'];
                            }

                            //if no rule is selected
                        } else {

                            //COMPATIBILITY WITH OLD MODEL: get project values in case OLD PDF ATTRIBUTES are used======================================//
                            $projectStepDefinition = unserialize($projectData['components'][$productData->blocks[0]->definition->block_id]['variation']);
                            foreach ($projectStepDefinition as $attributeID => $attributeValue) {
                                if ($productData->blocks[0]->attributes->$attributeID->definition->attribute_type === 'pdf_upload') {
                                    $projectPdfAttribute[$productData->blocks[0]->attributes->$attributeID->definition->attribute_slug] = $productData->blocks[0]->attributes->$attributeID->values->$attributeValue->value_data;
                                }
                            }

                            //set pdf pages
                            if (isset($projectPdfAttribute['pages'])) {
                                $pdfPages = $projectPdfAttribute['pages'];
                            } else {
                                $pdfPages = null;
                            }

                            //set pdf size
                            if (isset($projectPdfAttribute['size'])) {
                                $pdfSize = $projectPdfAttribute['size'];
                            } else {
                                $pdfSize = null;
                            }

                            //===========================================================================================================================//

                            $pagePrice = 0;
                            $areaPrice = 0;
                            $areaSize = null;
                        }
                    } else {

                        $areaSize = null;
                        $pagePrice = 0;
                        $areaPrice = 0;
                        $pdfSize = null;
                        $pdfPages = null;
                    }


                    //get current project options
                    $currentPrice = unserialize($project['price']);


                    if (!empty($areaPrice) && $realPdfPages === 1) {
                        $pagesValidation = true;
                    } elseif (!empty($areaPrice) && $realPdfPages !== 1) {
                        $pagesValidation = false;
                    } else {
                        if (!empty($pdfPages)) {
                            $arr_pdfAttributeData = unserialize($pdfPages);
                            if ((intval($arr_pdfAttributeData['min']) <= $realPdfPages) && (intval($arr_pdfAttributeData['max']) >= $realPdfPages)) {
                                $pagesValidation = true;
                            } else {
                                $pagesValidation = false;
                            }
                        } else {
                            $pagesValidation = true;
                        }
                    }

                    //validate pdf size
                    if (empty($areaPrice)) {

                        //attribute size to validate
                        $issetPdfSize = false;
                        if (!empty($pdfSize)) {
                            $pdfAttributeSize = unserialize($pdfSize);
                            $pdfAttributeSize['width'] = $pdfAttributeSize['width'] * 2.83; // milimeters to pp
                            $pdfAttributeSize['height'] = $pdfAttributeSize['height'] * 2.83; // milimeters to pp

                            $issetPdfSize = ($pdfAttributeSize['width'] == 0 && $pdfAttributeSize['height'] == 0) ? false : true;
                        }

                        if ($issetPdfSize) {
                            //check with pdf real data
                            foreach ($pdfResults['result']->descriptor->pages as $key => $pages) {
                                $realPdfWidth = intval($pages->size->width);
                                $realPdfHeight = intval($pages->size->height);
                                $widthDiff = intval($pdfAttributeSize['width']) - $realPdfWidth;
                                $heightDiff = intval($pdfAttributeSize['height']) - $realPdfHeight;
                                if (((intval($pdfAttributeSize['width']) - $realPdfWidth >= -10) && (intval($pdfAttributeSize['width']) - $realPdfWidth <= 10)) && ((intval($pdfAttributeSize['height']) - $realPdfHeight >= -10) && (intval($pdfAttributeSize['height']) - $realPdfHeight <= 10))) {
                                    $pageSizeCheck[$key++] = 'ok';
                                } else {
                                    $pageSizeCheck[$key++] = 'dif';
                                }
                            }

                            //if there is a page with a non valid size
                            $arraySearch = array_search('dif', $pageSizeCheck, true);
                            if (array_search('dif', $pageSizeCheck, true) !== false) {
                                $sizeValidation = false;
                            } else {
                                $sizeValidation = true;
                            }
                        } else {
                            $sizeValidation = true;
                        }
                    } else {
                        $sizeValidation = true;
                    }

                    //echo response
                    if (isset($sizeValidation) || isset($pagesValidation)) {

                        //erros for size and pages validation
                        if ((isset($sizeValidation) && isset($pagesValidation)) && ($sizeValidation == false || $pagesValidation == false)) {
                            if ($sizeValidation == false && $pagesValidation == false) {
                                echo '<p>' . __('Sorry, but your PDF size and pages seems to be incorrect.', 'imaxel') . '</p>';
                            } else if ($sizeValidation === false) {
                                echo '<p>' . __('Sorry, but your PDF size seems to be incorrect.', 'imaxel') . '</p>';
                            } else if ($pagesValidation === false) {
                                echo '<p>' . __('Sorry, but your PDF pages seems to be incorrect.', 'imaxel') . '</p>';
                            }

                            //errors just for pages validation
                        } elseif ((!isset($sizeValidation) && isset($pagesValidation)) && ($pagesValidation === false)) {
                            echo '<p>' . __('Sorry, but your PDF pages seems to be incorrect.', 'imaxel') . '</p>';

                            //erros just for size validation
                        } elseif ((isset($sizeValidation) && !isset($pagesValidation)) && ($sizeValidation === false)) {
                            echo '<p>' . __('Sorry, but your PDF size seems to be incorrect.', 'imaxel') . '</p>';

                            //response for correct validation
                        } else {

                            //base price
                            $basePrice = 0;
                            foreach ($currentPrice as $block => $blockPrice) {
                                if (IcpService::isBlockData($block) && $block !== intval($blockID)) {
                                    $basePrice = floatval($basePrice) + floatval($blockPrice['total_price']);
                                }
                            }
                            $displayBasePrice = drawPrice($basePrice);

                            //update project price
                            if (empty($areaPrice)) {
                                //update price per pages
                                $updateProjectPrice = floatval($pagePrice) * intval($realPdfPages);
                            } else {
                                //update price per area
                                $area = $realPdfWidth * $realPdfHeight;
                                $updateProjectPrice = floatval($areaPrice) / $area;
                            }

                            //update
                            $currentPrice[$blockID]['total_price'] = $updateProjectPrice;
                            $priceTotal = 0;
                            foreach ($currentPrice as $block => $blockPrice) {
                                if (IcpService::isBlockData($block)) {
                                    $priceTotal = floatval($priceTotal) + floatval($blockPrice['total_price']);
                                } elseif ($block == 'qty') {
                                    $quantity = intval($blockPrice);
                                }
                            }
                            $unitPrice = number_format($priceTotal, 2);
                            $projectPrice = number_format($unitPrice * $quantity, 2);

                            if (isset($productData->discounts)) {
                                $isActiveDiscounts = 'on';
                            } else {
                                $isActiveDiscounts = 'off';
                            }

                            ?>
                            <script>
                                jQuery(document).ready(function () {
                                    var isActiveDiscounts = '<?php echo $isActiveDiscounts; ?>';
                                    var unitPrice = '<?php echo drawPrice($unitPrice) ?>';
                                    var basePrice = '<?php echo $displayBasePrice; ?>';
                                    /*if(isActiveDiscounts == 'on') {*/
                                    var projectPrice = '<?php echo $quantity . ' x ' . drawPrice($unitPrice) . ' = ' . drawPrice($projectPrice) ?>';
                                    /*} else if(isActiveDiscounts == 'off') {
                                        var projectPrice = '<?php echo drawPrice($unitPrice) ?>';
                                } */
                                    var areaPrice = '<?php echo $areaPrice; ?>'

                                    if (areaPrice == '0' || areaPrice == '') {

                                        jQuery('.projectPrice').html(projectPrice);

                                        /*if(isActiveDiscounts == 'on') {*/
                                        jQuery('#summaryUnitPrice').html(unitPrice);
                                        /*} else if(isActiveDiscounts == 'off') {
                                            jQuery('#summaryUnitPrice').html(projectPrice);
                                        }*/

                                        jQuery('#summaryBasePrice').html(basePrice);
                                        jQuery('#summaryProjectPrice').html(projectPrice);

                                    }
                                    jQuery('.icp-forth.disabled').hide();
                                    jQuery('.icp-forth.enabled.pdf-nav').show();
                                    jQuery('.icp-forth.enabled.design-nav').hide();
                                });
                            </script>
                            <?php

                            $displayPDfsummary = $this->displayPDFsummary($productData, $variationID, $pdfResults, $pdfFile, $projectID, $blockID, $siteID, $currentURL, $pdfKey, $productID, $wproduct, $updateProjectPrice, $areaPrice, $pagePrice);
                        }
                    } else {
                        throw new \Exception(__('Your validation has failed', 'imaxel'));
                    }
                } else {
                    throw new \Exception($pdfResults['result']->message);
                }
            } catch (\Exception $e) {
                wp_send_json_error($e->getMessage(), 500);
            }

            //finish the process
            wp_die();
        }

        function displayPDFsummary($productData = '', $variationID = '', $pdfResults, $pdfFile, $projectID, $blockID, $siteID, $currentURL, $pdfKey, $productID, $wproduct, $updateProjectPrice = '', $areaPrice, $pagePrice)
        {
            //get and set theme color
            $primaryColor = ShopService::getPrimaryColor();
            echo '<style>
                    .lds-ring div {border-color: ' . $primaryColor . ' transparent transparent transparent !important;}
                    .lds-ellipsis div {background: ' . $primaryColor . '}
                </style>';

            //print response
            echo '<p>' . __('Great, your PDF has been uploaded!', 'imaxel') . '</p>';

            //pdf file
            if (isset($pdfResults['result']->descriptor->pages[0]->thumbnail_512)) {

                //pdf thumbnail
                echo '<div class="pdf_file_box">';

                echo '<div class="pdf_thumbanil">';
                echo '<img src="' . $pdfResults['result']->descriptor->pages[0]->thumbnail_512 . '">';
                echo '</div>';

                //calculate pdf price
                if (empty($areaPrice)) {
                    $pdfPrice = floatval($pagePrice) * floatval($pdfResults['result']->descriptor->numPages);
                }

                //check if project is the last, if yes show "AddToCart" if not show "Next"
                foreach ($productData->blocks as $block) {
                    $blocksDataByID[$block->definition->block_id] = $block->definition;
                    $blockDataByOrder[$block->definition->block_order] = $block->definition;
                }
                $maxOrderBlock = max(array_keys($blockDataByOrder));
                (intval($blocksDataByID[$blockID]->block_order) !== $maxOrderBlock) ? $nextButtonText = __('Next', 'imaxel') : $nextButtonText = __('Add To cart', 'imaxel');

                //convert pdf size from points to m
                $pdfHeight = number_format(floatval($pdfResults['result']->descriptor->pages[0]->size->height) / 2835, 2);
                $pdfWidth = number_format(floatval($pdfResults['result']->descriptor->pages[0]->size->width) / 2835, 2);

                if (isset($productData->discounts)) {
                    $isActiveDiscounts = 'on';
                } else {
                    $isActiveDiscounts = 'off';
                }

                //pdf summary
                echo '<div class="pdf_file_summary">';

                //pdf summary text
                echo '<div class="pdf_file_summary_text">';
                echo '<p><strong>' . __('File name', 'imaxel') . ': </strong>' . $pdfFile['name'] . '</p>';

                if (empty($areaPrice)) {
                    echo '<p><strong>' . __('Pages', 'imaxel') . ': </strong>' . $pdfResults['result']->descriptor->numPages . '</p>';
                    if (isset($pdfPrice) && floatval($pdfPrice) !== 0.0) {
                        echo '<p><strong>' . __('Base price', 'imaxel') . ': </strong><span id="summaryBasePrice"></span></p>';
                        echo '<p><strong>' . __('Pdf price', 'imaxel') . ': </strong>' . drawPrice($pdfPrice) . '</p>';
                        echo '<p><strong>' . __('Unit price', 'imaxel') . ': </strong><span id="summaryUnitPrice"></span></p>';
                        echo '<p><strong>' . __('Project price', 'imaxel') . ': </strong><span id="summaryProjectPrice"></span></p>';
                    }
                } else {
                    $pdfWidth = $pdfWidth * 100;
                    $pdfHeight = $pdfHeight * 100;

                    echo '<p><strong>' . __('Size', 'imaxel') . ': </strong>' . $pdfWidth . ' / ' . $pdfHeight . ' cm</p>';
                }
                echo '</div>';

                //pdf next button
                echo '<div class="pdf_next_button_box">';
                //current button
                echo '<div class="button-loader-box">';
				echo '<div style="float:left;" class="button" style="margin-left: 2.5px;" onclick="icppdf.savePdfBlock()" id="savePdfBlock" pdf_name="' . $pdfFile['name'] . '" project_id="' . $projectID . '" block_id="' . $blockID . '" site_id="' . $siteID . '" currentURL="' . $currentURL . '" pdf_id="' . $pdfKey . '" product_id="' . $productID . '" price="' . $updateProjectPrice . '" area_price="' . $areaPrice . '"';
				if (!empty($wproduct)) {
                    echo 'wproduct="' . $wproduct . '"';
                }
                echo '>' . $nextButtonText . '</i></div>';
                echo '<div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div>';
                echo '</div>';

                echo '</div>';

                echo '</div>';


                echo '</div>';
            }
        }





        /**
         * save project
         */
       /* function saveICPproject()
        {
            //check if the user is logged
            if (is_user_logged_in()) {
                global $wpdb;
                $icpProject = $_POST['icpProject'];
                $userID = get_current_user_id();

                //associate project with user id
                $projectsTable = $wpdb->prefix . 'icp_products_projects';
                $sql = "
                    UPDATE " . $projectsTable . "
                    SET user = $userID
                    WHERE id= $icpProject
                ";
                $saveProject = $wpdb->query($sql);
                wp_die();
            }
        }*/


        #endregion



        #region BLOCK calls

        /**
         * get current block type
         */
        function getCurrentBlogType($productData)
        {
            global $wpdb;
            $blockID = intval($_GET['block']);
            $siteID = $_GET['site'];
            foreach ($productData->blocks as $block) {
                $productBlocks[$block->definition->block_id] = $block->definition;
            }
            $blockType = $productBlocks[$blockID]->block_type;
            return $blockType;
        }

        /**
         * get current block data
         */
        function getCurrentBlockData($productData)
        {
            $blockID = intval($_GET['block']);
            foreach ($productData->blocks as $block) {
                if ($block->definition->block_id == $blockID) {
                    $currentBlockData = $block->definition;
                }
            }
            return $currentBlockData;
        }



        /**
         * load product data
         */
        function loadProductData($productID, $siteID)
        {
            if (is_multisite()) {

                if ($siteID == get_current_blog_id()) {
                    if (!empty(get_option('icp_printspot_endpoint'))) {
                        $endpoint = get_option('icp_printspot_endpoint');
                    } elseif (!empty(get_option('wc_settings_tab_imaxel_icp_endpoint'))) {
                        $endpoint = get_option('wc_settings_tab_imaxel_icp_endpoint');
                    }
                    $protocol = strpos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https' : 'http';
                    $recoverProductDataURL = $protocol . '://' . $endpoint . '/' . $productID;
                } else {
                    if(!empty(get_option('wc_settings_tab_imaxel_icp_endpoint'))){
                        $endpoint = get_option('wc_settings_tab_imaxel_icp_endpoint');
                        $protocol = strpos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https' : 'http';
                        $recoverProductDataURL = $protocol . '://' . $endpoint . '/' . $productID;
                    } else {
                        $icpOriginSiteURL = get_blog_option($siteID, 'siteurl') . '/icp/' . $siteID;
                        $recoverProductDataURL = $icpOriginSiteURL . '/' . $productID;
                    }
                }
            } else {

                if (!empty(get_option('wc_settings_tab_imaxel_icp_endpoint'))) {
                    $endpoint = get_option('wc_settings_tab_imaxel_icp_endpoint');
                    $protocol = strpos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https' : 'http';
                    $recoverProductDataURL = $protocol . '://' . $endpoint . '/' . $productID;
                }
            }

            $productDataJSON = wp_remote_get($recoverProductDataURL);
            $productData = json_decode($productDataJSON['body']);

            return $productData;
        }



        /**
         * get block title
         */
        function getBlockTitle($blockID, $productData)
        {
            global $wpdb;
            //get block title
            foreach ($productData->blocks as $block) {
                $blocksData[$block->definition->block_id] = $block->definition;
            }

            echo '<div class="block_title_header">';
            echo '<div class="block_title"><h2>' . $blocksData[$blockID]->block_name . '</h2></div>';
            echo '</div>';
        }

        /**
         * get product blocks
         */
        function getProductBlocksFlow($productData, $productID, $productName, $siteID, $blockID, $currentURL, $wproduct)
        {
            foreach ($productData->blocks as $block) {
                $blocksData[$block->definition->block_id]['name'] = $block->definition->block_name;
                $blocksData[$block->definition->block_id]['order'] = $block->definition->block_order;
                $blocksData[$block->definition->block_id]['type'] = $block->definition->block_type;
            }

            //return if product has single block
            if (count($productData->blocks) <= 1) {
                return;
            }

            //if project exists, get project state

            if (isset($_GET['icp_project'])) {
                $projectID = $_GET['icp_project'];
                $getProjectData = IcpService::getVariantsbyProjectId($projectID);
                $projectData = $getProjectData['value'];
            }

            //get theme color
            $primaryColor = ShopService::getPrimaryColor();

            $counterBlocks = 1;
            foreach ($blocksData as $block => $flowBlock) {
                $blocksData[$block]['label'] = $counterBlocks . '. ' . ($flowBlock['name'] ?? __('Product definition', 'imaxel'));
                $blocksData[$block]['class'] = 'icp_flow_navigator_item';
                $blocksData[$block]['attributes'] = '';

                if (!isset($_GET['icp_project'])) {
                    if ($blockID == $block) {
                        $blocksData[$block]['class'] .= ' icp_flow_navigator_item_active';
                    }
                } else {
                    $projectID = $_GET['icp_project'];
                    $icpProjectData = IcpProductsProjectsModel::origin()->getById($projectID);
                    if ($blockID == $block) {
                        $blocksData[$block]['class'] .= ' icp_flow_navigator_item_active';
                    }
                    if (isset($projectData[$block])) {
                        $blocksData[$block]['class'] .= ' block_return';
                        $blocksData[$block]['attributes'] .= ' block="' . $block . '" style="cursor: pointer;"  returnURL="' . $currentURL . '" product_id="' . $productID . '" site_id="' . $siteID . '" block_id="' . $block . '" project_id="' . $projectID . '" wproduct="' . $wproduct . '"';
                    } else if ($blockID == $block) {
                        $blocksData[$block]['class'] .= ' icp_flow_navigator_item_active';
                    }
                }
                $counterBlocks++;
            }
            $renderView = View::renderLoad('imaxel-woocommerce:steps/navbar_blocks.php', [
                'projectId' => isset($projectID) ? $projectID : null,
                'productName' => $productName,
                'primaryColor' => $primaryColor,
                'blocksData' => $blocksData,
                'icpProjectData' => isset($icpProjectData) ? $icpProjectData : null,
            ]);

            return $renderView;
        }

        /**
         * get block attributes
         */
        function getBlockAttributes($siteOrigin, $productID, $blockID, $productData)
        {

            foreach ($productData->blocks as $block) {
                if (isset($block->attributes)) {
                    $blockAttributes[$block->definition->block_id] = $block->attributes;
                }
            }

            if (isset($blockAttributes[$blockID])) {
                foreach ($blockAttributes[$blockID] as $attribute) {

                    //check for attribute values except if attribute is "protransfer"
                    if ($attribute->definition->attribute_type !== 'protransfer') {

                        //get values
                        foreach ($attribute->values as $valueId => $values) {
                            $attributeValues[$attribute->definition->attribute_type]['value_key'][$valueId] =  $values->value_key;
                            $attributeValues[$attribute->definition->attribute_type]['value_data'][$valueId]['data'] =  $values->value_data;
                            $attributeValues[$attribute->definition->attribute_type]['value_data'][$valueId]['attribute_id'] =  $values->attribute;
                            $attributeValues[$attribute->definition->attribute_type]['value_data'][$valueId]['value_id'] =  $values->id;
                        }
                    } else {
                        $attributeValues[$attribute->definition->attribute_type] = '';
                    }
                }
            }

            if (isset($attributeValues)) {
                return $attributeValues;
            } else {
                return null;
            }
        }

        /**
         * get PDF attrbiutes data
         */
        function getPdfVariationAttributesData($projectData = '', $siteID, $productID, $productData = '', $blockID = '')
        {
            //get attribute blocks
            $blockAttributes = $this->getBlockAttributes($siteID, $productID, $blockID, $productData);
            if (isset($blockAttributes['pdf_upload'])) {
                $pdfBehaviourKey = array_search('pdf_constraint_behaviour', $blockAttributes['pdf_upload']['value_key']);
                $pdfSizeKey = array_search('pdf_constraint_size', $blockAttributes['pdf_upload']['value_key']);
                $pdfPageKey = array_search('pdf_constraint_pages', $blockAttributes['pdf_upload']['value_key']);
                $behaviour = $blockAttributes['pdf_upload']['value_data'][$pdfBehaviourKey]['data'];
                if ($pdfPageKey) {
                    $pages = $blockAttributes['pdf_upload']['value_data'][$pdfPageKey]['data'];
                } else {
                    $pages = null;
                }
                if ($pdfSizeKey) {
                    $size = $blockAttributes['pdf_upload']['value_data'][$pdfSizeKey]['data'];
                } else {
                    $size = null;
                }
            }
            $variationID = $projectData['variation'];

            if (isset($behaviour) && $behaviour == 'specific_constraints') {

                //size
                $pdfAttributes = array();
                if (@unserialize($size)) {
                    $sizes = unserialize($size);
                    $zeroValue = array_search('', $sizes);
                    if ($zeroValue == NULL) {
                        $pdfAttributes['size']['value']['data'] = $size;
                    } else {
                        //get variation data
                        if (intval($variationID) !== 0) {
                            if (!@unserialize($variationID)) {
                                $variationData = IcpService::getVariationAttributeData($variationID, $siteID, $productID);
                                if (isset($variationData['attributes']['size']) && ($variationData['attributes']['size']['type'] == 'pdf_upload')) {
                                    $pdfAttributes['size']['value']['data'] = $variationData['attributes']['size']['value']['data'];
                                    $pdfAttributes['size']['value']['key'] = $variationData['attributes']['size']['value']['key'];
                                } else {
                                    $pdfAttributes['size']['value']['data'] = null;
                                }
                            } else {
                                $pdfAttributes['size']['value']['data'] = null;
                            }
                        }
                    }
                } else {
                    $pdfAttributes['size']['value']['data'] = null;
                }

                //pages
                if (@unserialize($pages)) {
                    $p = unserialize($pages);
                    $zeroValue = array_search('', $p);
                    if ($zeroValue == NULL) {
                        $pdfAttributes['pages']['value']['data'] = $pages;
                    } else {
                        //get variation data
                        if (intval($variationID) !== 0) {
                            if (!@unserialize($variationID)) {
                                if (isset($variationData['attributes']['size']) && ($variationData['attributes']['size']['type'] == 'pdf_upload')) {
                                    $pdfAttributes['pages']['value']['data'] = $variationData['attributes']['pages']['value']['data'];
                                    $pdfAttributes['pages']['value']['key'] = $variationData['attributes']['pages']['value']['key'];
                                } else {
                                    $pdfAttributes['pages']['value']['data'] = null;
                                }
                            } else {
                                $pdfAttributes['pages']['value']['data'] = null;
                            }
                        }
                    }
                } else {
                    $pdfAttributes['pages']['value']['data'] = null;
                }
                return $pdfAttributes;
            } elseif (isset($behaviour) && $behaviour == 'no_constraints') {

                $pdfAttributes['size']['value']['data'] = null;
                $pdfAttributes['pages']['value']['data'] = null;
                return $pdfAttributes;
            } else {

                //order blocks by type
                foreach ($projectData['components'] as $data) {
                    foreach ($data as $type => $value) {
                        ($type == 'variation') ? $projectData[$type] = $value : $projectData[$type][] = $value;
                    }
                }
                $variationID = $projectData['variation'];

                //get variation data
                if ($variationID !== 0) {
                    if (@unserialize($variationID)) {
                        $projectAttributesData = unserialize($variationID);
                        $variationData = IcpService::getProjectAttributesData($projectAttributesData, $productData);
                    } else {
                        $variationData = IcpService::getVariationAttributeData($variationID, $siteID, $productID);
                    }

                    //get pdf attributes
                    $pdfAttributes = array();
                    foreach ($variationData['attributes'] as $name => $attribute) {

                        if ($attribute['type'] == 'pdf_upload') {
                            $pdfAttributes[$name] = $attribute;

                            //load with empty data in case there are no attributes
                            if (!isset($pdfAttributes['pages'])) {
                                $pdfAttributes['pages']['value']['data'] = null;
                            }

                            if (!isset($pdfAttributes['size'])) {
                                $pdfAttributes['size']['value']['data'] = null;
                            }
                        }
                    }
                    return $pdfAttributes;
                } else {
                    return null;
                }
            }
        }


        /**
         * return to edited block
         */
        function returnToBlock() {
            $currentURL = $_POST['returnURL'];
            $projectID = $_POST['icpProject'];
            $siteID = $_POST['siteOrigin'];
            $productID = $_POST['productID'];
            $blockID = $_POST['blockID'];
            $wproduct = $_POST['wproduct'];

            //build new url for next block
            $blockURL = trim(strtok($currentURL, '?')) . '?id=' . $productID . '&site=' . $siteID . '&block=' . $blockID . '&icp_project=' . $projectID;
            if (!empty($wproduct)) {
                $blockURL .= '&wproduct=' . $wproduct;
            }

            echo $blockURL;
            wp_die();
        }

        #endregion

        #endregion

        #region Funciones de LOG

        public static function writeLog($text)
        {
            $logsFolder = dirname(__FILE__).'/logs';
            WC_Imaxel::checkFolderExists($logsFolder);

            $logFilename = dirname(__FILE__).'/logs/imaxel.log';
            if (is_file($logFilename)) WC_Imaxel::deleteLargeFile($logFilename, 26214400);

            file_put_contents($logFilename, date('M d Y G:i:s') . ' -- ' . $text . "\r\n", is_file($logFilename)?FILE_APPEND:0);         
        }

        public static function writeCheckLog($text, $check, $var) 
        {
            $logsFolder = dirname(__FILE__).'/logs';
            WC_Imaxel::checkFolderExists($logsFolder);

            $logFilename = dirname(__FILE__).'/logs/imaxel.log';
            if (is_file($logFilename)) WC_Imaxel::deleteLargeFile($logFilename, 26214400);

            switch ($check) 
            {
                case "empty":
                    $result = empty($var) ? 'yes' : 'no';
                    break;
                case "int":
                    $result = is_int($var) ? 'yes' : 'no';
                    break;
                case "null":
                    $result = is_null($var) ? 'yes' : 'no';
                    break;
            }

            file_put_contents($logFilename, date('M d Y G:i:s') . ' -- ' . $text . ' -- ' . $check . ': ' . $result . ' ' . "\r\n", is_file($logFilename)?FILE_APPEND:0);
        }

        private static function checkFolderExists($folderPath)
        {
            if (!is_dir($folderPath)) {
                mkdir($folderPath);
            }
        }

        private static function deleteLargeFile($filename, $threshold)
        {
            $fileSize = filesize($filename);

            if ($fileSize > $threshold) {
                if (is_file($filename)) unlink($filename);
            }
        }

        #endregion
    }
}
// finally instantiate our plugin class and add it to the set of globals
$GLOBALS['wc_imaxel'] = new WC_Imaxel();
