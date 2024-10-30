<?php

namespace Printspot\ICP\Controllers;

use Printspot\ICP\Controllers;
use Printspot\ICP\Helpers\Config;
use Printspot\ICP\View;
use WC_Imaxel;

class ProductController extends Controller
{

	public static function load()
	{

		add_action("woocommerce_product_meta_start", self::class . '::imaxel_custom_buy_button');
		$showbuttonshop = get_option("wc_settings_tab_imaxel_shop_show_button");
		if ($showbuttonshop == "yes") {
			add_action('woocommerce_after_shop_loop_item', self::class . '::imaxel_custom_buy_button', 10, 0);
		}

		//Ticket 6075
		//add_filter('woocommerce_is_purchasable', self::class . '::imaxel_is_purchasable', 10, 2);
		add_filter('woocommerce_loop_add_to_cart_link', self::class . '::imaxel_loop_add_to_cart_link', 10, 2);
	}

	public static function imaxel_loop_add_to_cart_link($button, $product)
	{
		$selectedProduct = get_post_meta($product->get_id(), "_imaxel_selected_product", true);
		$selectedICPProduct = get_post_meta($product->get_id(), "_imaxel_icp_products", true);
		if (($selectedProduct && $selectedProduct > 0) || ($selectedICPProduct && $selectedICPProduct > 0)) {
			$button = '';
		}
		return $button;
	}

	public static function imaxel_is_purchasable($purchasable, $product)
	{
		$selectedProduct = get_post_meta($product->get_id(), "_imaxel_selected_product", true);
		$selectedICPProduct = get_post_meta($product->get_id(), "_imaxel_icp_products", true);
		if (($selectedProduct && $selectedProduct > 0) || ($selectedICPProduct && $selectedICPProduct > 0)) {
			$purchasable = false;
		}
		return $purchasable;
	}

	public static function imaxel_custom_buy_button()
	{
		$current_hook = current_filter();
		global $product;
		global $wpdb;
		global $wp;

		$selectedProduct = get_post_meta($product->get_id(), "_imaxel_selected_product", true);
		if ($selectedProduct > 0) {
			remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
			$sql = "SELECT * FROM " . $wpdb->prefix . "imaxel_woo_products WHERE id=" . $selectedProduct;
			$imaxelProduct = $wpdb->get_row($sql);
			if ($imaxelProduct) {

				wp_enqueue_style('style', plugins_url('/imaxel-woocommerce/assets/css/creative_styles.css'));
				if (wp_is_mobile() && $imaxelProduct->type == 1) {
					echo '<div>' . __('Desktop only', 'imaxel') . '</a>
                    </div>';
				} else {
					if (strcmp($current_hook, "woocommerce_after_shop_loop_item") === 0) {
						$current_url = home_url(add_query_arg(array(), $wp->request));
						echo '
                            <div class="crear_ahora_wrapper" data-productid=' . $product->get_id() . '>

                                <div
                                    id="imx-loader-' . $product->get_id() . '"
                                    class="imx-loader imx-loader-2"
                                    style="display:none"
                                ></div>

                                <a
                                    class="single_add_to_cart_button secondary button alt editor_imaxel"
                                    data-productid="' . $product->get_id() . '"
                                 >'
								. __('Create now', 'imaxel') . '
                                </a>

                            </div>';
					} else {
						$current_url = get_permalink();

						//$variations = $product->get_available_variations();
						echo '
                                <div class="crear_ahora_wrapper">
                                    <div
                                        id="imx-loader-' . $product->get_id() . '"
                                        class="imx-loader"
                                        style="display:none"
                                    ></div>
                                    <a class="single_add_to_cart_button secondary button alt editor_imaxel" data-productid="' . $product->get_id() . '" >'
								. __('Create now', 'imaxel') . '
                                    </a>
                                </div>';
					}


					wp_enqueue_script(
							'imaxel_script',
							plugins_url('/imaxel-woocommerce/assets/js/imaxel.js'),
							array('jquery'),
							WC_Imaxel::plugin_version()
					);
					wp_localize_script(
							'imaxel_script',
							'ajax_object',
							array(
									'url' => admin_url('admin-ajax.php'),
									'backurl' => $current_url
							)
					);
				}
			}
		}

		//#HELP-8
		$icpProduct = get_post_meta($product->get_id(), "_imaxel_icp_products", true);
		if (!empty($icpProduct)) {
			$wproduct = $product->get_id();
		}
		if (!empty($icpProduct)) {
			//remove woocommerce add to cart button
			remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);

			//get ICP url page
			$icpURL = get_option('icp_url');
			//get product data;
			$icpWoocommerceProducstTable = $wpdb->prefix . 'icp_products_woocommerce';
			$icpProductData = $wpdb->get_row("SELECT site, first_block FROM " . $icpWoocommerceProducstTable . " WHERE code=$icpProduct");
			if ($icpProductData)
			{
				$icpFinalURL = $icpURL . '?id=' . $icpProduct . '&site=' . $icpProductData->site . '&block=' . $icpProductData->first_block . '';
				if ( isset($wproduct) ) $icpFinalURL .= '&wproduct=' . $wproduct;
				echo '<a href="' . $icpFinalURL . '"><button style="margin-bottom: 15px;" class="single_add_to_cart_button secondary button alt editor_imaxel editor_imaxel_icp">' . __('Customize', 'imaxel') . '</button></a>';
			}
		}
	}
}