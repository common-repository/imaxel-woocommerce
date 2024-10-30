//================================================MARC EDIT PROJECT FROM THE CART===================================================//
jQuery(document).on("click", "#edit_from_cart", function(event){

    event.preventDefault();
    var projectID = jQuery(this).attr('project_id');
    var cart_item_id = jQuery(this).attr('cart_item_key');
    var backURL=ajax_object.backurl;
	var origin = jQuery(this).attr('origin');
	var forceReturnCheckoutPage = origin === 'checkout' ? true : false;
	var data = jQuery(this).data();

	jQuery(this).parent().children().hide();
    jQuery(this).parent().append('<div class="imx-loader"><i class="fas fa-spinner fa-spin"></i></div>');
    jQuery.ajax({
        url : ajax_object.url,
        type : 'POST',
        datatype: 'json',
        data : {
           	action: 'edit_project_form_cart_function',
           	projectID : projectID,
           	cart_item_id : cart_item_id,
           	backURL : backURL,
			catalogueId : data.catalogue_id,
			forceReturnCheckoutPage: forceReturnCheckoutPage
        },
        success: function(response) {
               console.log(response);
               window.location.replace(response);
         },
         error: function(response) {
	     	console.log(response);
			 alert("Error loading imaxel_wrapper, if this message keeps showing up please contact support.")
	     }
     })
});
//===================================================================================================================================//

jQuery(document).ready( function() {
    if(jQuery(".variations_form").length > 0) {
        if(jQuery(".variations select[name=attribute_proyecto]").length > 0) {
			jQuery(".variations select[name=attribute_proyecto]").closest('tr').hide();

            jQuery(".variations select[name=attribute_proyecto] option:eq(1)").prop('selected', true); //Hay que utilizar la función prop para compatibilidad con safari

            jQuery(".variations select").change(function () {
                jQuery(".variations select[name=attribute_proyecto] option:eq(1)").prop('selected', true);
            });

            jQuery(".variations_form").on("woocommerce_variation_select_change", function () {
                jQuery(".crear_ahora_wrapper").hide();
            });

            jQuery(".single_variation_wrap").on("show_variation", function (event, variation) {
                jQuery(".crear_ahora_wrapper").show();
            });

            jQuery(".crear_ahora_wrapper").hide();

			jQuery(".woocommerce-variation-add-to-cart").hide();
		}
    }

    if(jQuery(".editor_imaxel").length > 0) {
        jQuery(".woocommerce-variation-add-to-cart").hide();
        jQuery(".woocommerce-variation-add-to-cart").attr('style','display:none !important');
    }


    jQuery("a.editor_imaxel").click( function() {

      var productID = jQuery(this).attr("data-productid");

      jQuery(this).hide();
      jQuery("#imx-loader-" + productID).show();

      var arraySelectedVariations ={};

      jQuery('.variations select').each(function() {
          arraySelectedVariations[ jQuery(this).attr("name")] = jQuery(this).val();
      });

      var backURL=ajax_object.backurl;
      jQuery.ajax({
         url : ajax_object.url,
         type : 'POST',
         datatype: 'json',
         data : {
            action: 'imaxel_wrapper',
            productID:productID,
            selectedVariation:JSON.stringify(arraySelectedVariations),
            backURL:backURL
	     },
         success: function(imaxelresponse,myAjax) {
            if(myAjax == "success") {
               console.log(imaxelresponse);
               window.location.replace(imaxelresponse);
            }
            else {
               console.log(imaxelresponse);
               window.location.replace(imaxelresponse);
            }
         },
         error: function(imaxelresponse,myAjax) {
	     	console.log(imaxelresponse);
	     	alert("Error loading imaxel_wrapper, if this message keeps showing up please contact support.")
	     }
      })
	  return false;
   })

});
