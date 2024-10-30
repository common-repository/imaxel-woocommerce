jQuery(document).ready(function () {

    jQuery("#btnImaxelUpdateProducts").click(function () {
        jQuery("#btnImaxelUpdateProducts").hide();
        jQuery(".imx-loader").show();
        jQuery.ajax({
            url: ajax_object.url,
            type: 'POST',
            datatype: 'json',
            data: {
                action: 'imaxel_update_products'
            },
            success: function (imaxelresponse, myAjax) {
                location.reload();
            },
            error: function (imaxelresponse, myAjax) {
                console.log(imaxelresponse);
            }
        });
        return false;
    })

    jQuery(".imaxel-btn-duplicate, .imaxel-btn-edit, .imaxel-btn-delete").click(function () {

        jQuery(this).parent().children().hide();
        jQuery(this).parent().append('<div class="imx-loader"></div>');
        var action = "imaxel_admin_duplicate_project";
        if (jQuery(this).attr("class") == "imaxel-btn-edit")
            action = "imaxel_admin_edit_project";
        if (jQuery(this).attr("class") == "imaxel-btn-delete")
            action = "imaxel_admin_delete_project";

        var projectID = jQuery(this).closest("tr").attr("id");
        projectID = projectID.split("-")[1];
        var backURL = ajax_object.backurl;
        var returnURL = ajax_object.returnurl;
        jQuery.ajax({
            url: ajax_object.url,
            type: 'POST',
            datatype: 'json',
            data: {
                action: action,
                projectID: projectID,
                backURL: backURL,
                returnURL: returnURL
            },
            success: function (imaxelresponse, myAjax) {
                if (myAjax == "success") {
                    console.log(imaxelresponse);
                    window.location.replace(imaxelresponse);
                } else {
                    console.log(imaxelresponse);
                    window.location.replace(imaxelresponse);
                }
            },
            error: function (imaxelresponse, myAjax) {
                console.log(imaxelresponse);
				alert("Error loading imaxel_admin_wrapper, if this message keeps showing up please contact support.")
			}
        })
        return false;
    });

    load_product_imaxel_variations();

    jQuery("#_imaxel_selected_product_variant").attr("multiple", "multiple");

    jQuery('#_imaxel_selected_product').on('change', function () {
        load_product_imaxel_variations();
    });

});

function load_product_imaxel_variations(target_product, target_variants, selected_product_variations) {

    if (!target_product) {
        target_product = "#_imaxel_selected_product";
    }

    if (!target_variants) {
        target_variants = "#_imaxel_selected_product_variant";
    }

    if (!selected_product_variations) {
        selected_product_variations = ajax_object.selected_product_variations;
    }

    jQuery(target_variants).parent().hide();
    jQuery(target_variants).empty();

    jQuery.ajax({
        url: ajax_object.url,
        type: 'POST',
        datatype: 'text',
        data: {
            action: 'imaxel_product_get_variants',
            productID: jQuery(target_product).val()
        },
        success: function (imaxelresponse, myAjax) {
            if (imaxelresponse) {
                imaxelresponse = JSON.parse(imaxelresponse);
                if (imaxelresponse.length > 1) {
                    jQuery(target_variants).append('<option value=-1>' + ajax_object.literal_all_variants + '</option>');
                    jQuery.each(imaxelresponse, function () {
                        jQuery(target_variants).append('<option value="' + this.code + '">' + this.name + '</option>');
                    });
                    if (selected_product_variations) {
                        jQuery(target_variants).val(selected_product_variations.join(",").split(','));
                    }
                    jQuery(target_variants).parent().show();
                }
            }
        },
        error: function (imaxelresponse, myAjax) {
            console.log(imaxelresponse);
        }
    });
}
