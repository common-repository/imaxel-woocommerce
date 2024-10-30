//INDEPENDENT FUNCTIONS

//function save design block
function saveDesignBlock() {
	window.onbeforeunload = function () {
		return null;
	}

	jQuery('#design_confirm_button').hide();
	jQuery('.confirm_design_button_box .lds-ellipsis').show();

	var productID = jQuery('#design_confirm_button').attr('product_id');
	var siteOrigin = jQuery('#design_confirm_button').attr('site_origin');
	var dealerID = jQuery('#design_confirm_button').attr('dealer_id');
	var blockID = jQuery('#design_confirm_button').attr('block_id');
	var icpProject = jQuery('#design_confirm_button').attr('icp_project');
	var attributeProyecto = jQuery('#design_confirm_button').attr('attribute_proyecto');
	var returnURL = jQuery('#design_confirm_button').attr('returnURL');
	var price = jQuery('#design_confirm_button').attr('price');
	var wproduct = jQuery('#design_confirm_button').attr("wproduct");

	jQuery.ajax({
		type: "POST",
		url: ajax_object.ajax_url,
		data: {
			action: 'confirmProjectDesign',
			siteOrigin: siteOrigin,
			productID: productID,
			dealerID: dealerID,
			blockID: blockID,
			icpProject: icpProject,
			attributeProyecto: attributeProyecto,
			returnURL: returnURL,
			price: price,
			wproduct: wproduct
		},
		success: function (response) {
			window.location.assign(response.data);
		},
		error: function () {
			console.log('Total fail');
		}
	});
}

//triggers function to return
function blockReturn() {

	window.onbeforeunload = function () {
		return null;
	}

	var productID = jQuery('.block_return').attr('product_id');
	var siteOrigin = jQuery('.block_return').attr('site_id');
	var blockID = jQuery('.block_return').attr('block_id');
	var icpProject = jQuery('.block_return').attr('project_id');
	var returnURL = jQuery('.block_return').attr('returnURL');
	var wproduct = jQuery('.block_return').attr('wproduct');

	if (jQuery('.block_return').attr('noellipsis') !== 'true') {
		jQuery('.block_return').hide();
		jQuery('#lds_ellipsis_' + blockID).show();
	}

	jQuery.ajax({
		type: "POST",
		url: ajax_object.ajax_url,
		data: {
			action: 'returnToBlock',
			icpProject: icpProject,
			siteOrigin: siteOrigin,
			productID: productID,
			wproduct: wproduct,
			blockID: blockID,
			returnURL: returnURL
		},
		success: function (response) {
			window.location.assign(response);
		},
		error: function () {
			console.log('Total fail');
		}
	});

}

//WHEN DOCUMENT READY ACTIONS
jQuery(document).ready(function ($) {

	/**
	 * design confirm button
	 */
	jQuery('#design_confirm_button').click(function () {

		window.onbeforeunload = function () {
			return null;
		}

		jQuery(this).hide();
		jQuery('.design-button-loader-box .lds-ellipsis').show();

		var productID = jQuery(this).attr('product_id');
		var siteOrigin = jQuery(this).attr('site_origin');
		var dealerID = jQuery(this).attr('dealer_id');
		var blockID = jQuery(this).attr('block_id');
		var icpProject = jQuery(this).attr('icp_project');
		var attributeProyecto = jQuery(this).attr('attribute_proyecto');
		var returnURL = jQuery(this).attr('returnURL');
		var wproduct = jQuery(this).attr('wproduct');
		var price = jQuery(this).attr('price');

		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'confirmProjectDesign',
				siteOrigin: siteOrigin,
				productID: productID,
				dealerID: dealerID,
				blockID: blockID,
				icpProject: icpProject,
				attributeProyecto: attributeProyecto,
				returnURL: returnURL,
				wproduct: wproduct,
				price: price
			},
			success: function (response) {
				window.location.assign(response.data);
			},
			error: function () {
				console.log('Total fail');
			}
		});
	});

	/**
	 * add to cart
	 */
	jQuery('#icp-add-to-cart').click(function () {

		window.onbeforeunload = function () {
			return null;
		}

		jQuery(this).hide();
		jQuery('.button-loader-box .lds-ellipsis').show();

		var icpProject = jQuery(this).attr('icp_project');
		var dealerID = jQuery(this).attr('dealer');
		var wproduct = jQuery(this).attr('wproduct');
		var siteOrigin = jQuery(this).attr('site_origin');

		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'icpAddItemToCart',
				icpProject: icpProject,
				wproduct: wproduct,
				dealerID: dealerID,
				siteOrigin: siteOrigin
			},
			success: function (response) {
				window.location.assign(response);
			},
			error: function () {
				console.log('Total fail');
			}
		});
	});

	/**
	 * return to design block
	 */
	//add hover effect
	jQuery('.block_return').hover(function () {
		jQuery(this).css("opacity", "0.5");
	}, function () {
		jQuery(this).css("opacity", "1");
	});

	/**
	 * triggers function to return
	 */
	jQuery('.block_return').click(function () {

		window.onbeforeunload = function () {
			return null;
		}

		var productID = jQuery(this).attr('product_id');
		var siteOrigin = jQuery(this).attr('site_id');
		var blockID = jQuery(this).attr('block_id');
		var icpProject = jQuery(this).attr('project_id');
		var returnURL = jQuery(this).attr('returnURL');
		var wproduct = jQuery(this).attr('wproduct');

		if (jQuery(this).attr('noellipsis') !== 'true') {
			jQuery(this).hide();
			jQuery('#lds_ellipsis_' + blockID).show();
		}

		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'returnToBlock',
				icpProject: icpProject,
				siteOrigin: siteOrigin,
				productID: productID,
				blockID: blockID,
				wproduct: wproduct,
				returnURL: returnURL
			},
			success: function (response) {
				window.location.assign(response);
			},
			error: function () {
				console.log('Total fail');
			}
		});

	});

	/**
	 * save project
	 */
	jQuery('#save_icp_project').click(function () {
		var icpProject = jQuery(this).attr('project_id');
		jQuery('.saving_message').show();
		jQuery('.save_icp_project').hide();
		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'saveICPproject',
				icpProject: icpProject
			},
			success: function (response) {
				jQuery('.saving_message').hide();
				jQuery('.save_icp_project').show();
			},
			error: function () {
				console.log('Total fail');
			}
		});
	});

	if(jQuery(".editor_imaxel_icp").length>0) {
		jQuery("form.cart").hide();
	}

	jQuery("button.editor_imaxel").click(function () {
		jQuery(this).hide();
		jQuery(this).parent().append("<div class='imx-loader'><i class='fas fa-spinner fa-spin'></i></div>");
	});

}) //document ready closes
