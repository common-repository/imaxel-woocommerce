<?php
$primaryColor = get_option('wc_settings_tab_imaxel_icp_color_theme');
$bgColor = get_option('wc_settings_tab_imaxel_icp_secondary_color_theme');
?>
<style>

    .icp-product-title h2{
        color: <?php echo $primaryColor ?>;
    }

    .single_add_to_cart_button{
        /*background-color: <?php echo $bgColor ?> !important;
        color: <?php echo $primaryColor ?> !important;*/
    }

    .editor_imaxel{
        background-color: <?php echo $bgColor ?>;
        color: <?php echo $primaryColor ?>;
    }

    .design_block_container_option_active{
        background-color: <?php echo $primaryColor ?>;
    }

    .button-loader-box .button{
        background-color: <?php echo $primaryColor ?>;
    }

    .button-loader-box .button:hover{
        filter: brightness(85%);
        background-color: <?php echo $primaryColor ?>;
    }


    #savePdfBlock{
        background-color: <?php echo $primaryColor ?>;
    }

    /*.button,
    .woocommerce button.button.alt,
    .wizzard-button,
    .wizzard-button-long,
    .woocommerce button.button.alt, .spinner > div,
    .small_button,
    .admin_button,*/
    /*input[type="submit"],*/
    .icp-button,
    .lds-ellipsis div,
    .icp-box .button,
    .design_block_box,
    .lds-spinner div:after,
    .lds-ellipsis div,
    .icp_flow_navigator_item_active,
    .design_block_container_option_active {
        background-color: <?php echo $primaryColor ?> !important
    }

    /*.cat_products_list li h2 {
        background-color: <?php echo $primaryColor ?>;
    }
    */

    mark.count {
        border-color: transparent transparent transparent<?php echo $primaryColor ?>;
    }

    a,
    .design_block_container i,
    .printspot_highlight h3,
	.selectFormBox label,
    .qty_button label,
    .production_time_button label,
    .icp-box h4,
    .icp-box h1,
    .variation_price_box p {
        color: <?php echo $primaryColor ?>;
    }

    /*
    a,
    .product_thumbnail_edit_icon,
    .new_profile_button i,
    .kiosk-player .color i,
    .modal_popup_content label i,
    .highlighted,
    .import_product_circle_button,
    .add_icp_to_catalogue,
    .asign_site_tag_circle_button,
    .new_printer_button,
    .new_tag_button,
    .new_shipping_method_button i,
    #clean-attributes-button,
    .design_block_container i,
    .icp_projects_list i,
    .icp_flow_box_header i,
    .shipping_options_headers i,
    .woocommerce-MyAccount-navigation a,
    .woocommerce-MyAccount-content a,
    .printspot_highlight h3,
    .kiosk_mode_breadcrumbs p,
    .kiosk_mode_breadcrumbs a,
    .woocommerce-MyAccount-navigation ul li.is-active > a,
    .selectFormBox label,
    .qty_button label,
    .production_time_button label,
    .category_container .mark_container span,
    .icp-box h4,
    .icp-box h1,
    .variation_price_box p {
        color: <?php echo $primaryColor ?>;
    }*/

    .shop-page-content-right {
        border-left: 1px solid<?php echo $primaryColor ?>;
    }

    .landing_separator,
    .printspot_page_title,
    #genesis-nav-primary .wrap,
    .printspot_page_titles,
    #customer_login h2,
    .block_title,
    .icp_flow_navigator_box,
    .separator,
    .design_block_container_option,
    .design_block_container_option_active {
        border-color: <?php echo $primaryColor ?> !important;
    }

    .lds-ring div {
        border-color: <?php echo $primaryColor ?> transparent transparent transparent !important;
    }

    .lds-dual-ring:after {
        border: 5px solid<?php echo $primaryColor ?>;
        border-color: <?php echo $primaryColor ?> transparent <?php echo $primaryColor ?>  transparent;
    }

    .tap-screen-qr-main, .tap-mobile-qr-main {
        fill: <?php echo $primaryColor ?> !important;
    }

    .language-change-loading:after {
        border: 5px solid<?php echo $primaryColor ?>;
        border-color: <?php echo $primaryColor ?> transparent <?php echo $primaryColor ?>  transparent;
    }

    .entry {
        background-color: <?php echo $bgColor ?> !important
    }

    .customization_button.active, .payment-method-subtitle {
        border-bottom: 2px solid <?php echo $primaryColor ?>;
        color: <?php echo $primaryColor ?>
    }

    .active-email-template {
        border-color: <?php echo $primaryColor ?>;
    }

    .active-custom-email, .active-email-template .delete-custom-element {
        color: <?php echo $primaryColor ?>
    }



</style>
