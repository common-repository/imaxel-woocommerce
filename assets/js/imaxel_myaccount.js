jQuery(document).ready(function () {

    jQuery(".imaxel-btn-edit").click(function () {

        jQuery(this).parent().children().hide();
        jQuery(this).parent().append('<div class="imx-loader"><i class="fas fa-spinner fa-spin"></i></div>');
        var projectID = jQuery(this).closest("tr").attr("id");
        projectID = projectID.split("-")[1];
        var backURL = ajax_object.backurl;
        jQuery.ajax({
            url: ajax_object.url,
            type: 'POST',
            //datatype: 'json',
            data: {
                action: 'imaxel_edit_project',
                projectID: projectID,
                backURL: backURL
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
				alert("Error loading imaxel_edit_project, if this message keeps showing up please contact support.")
			}
        })
        return false;
    })

    jQuery(".imaxel-btn-duplicate").click(function () {

        jQuery(this).parent().children().hide();
        jQuery(this).parent().append('<div class="imx-loader"><i class="fas fa-spinner fa-spin"></i></div>');
        var projectID = jQuery(this).closest("tr").attr("id");

        projectID = projectID.split("-")[1];
        var backURL = ajax_object.backurl;
        jQuery.ajax({
            url: ajax_object.url,
            type: 'POST',
            //datatype: 'json',
            data: {
                action: 'imaxel_duplicate_project',
                projectID: projectID,
                backURL: backURL
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
				alert("Error loading imaxel_duplicate_project, if this message keeps showing up please contact support.")
			}
        })
        return false;
    })

    jQuery(".imaxel-btn-delete").click(function () {

        var r = confirm(ajax_object.literal_delete_warning);
        if (r == true) {
            jQuery(this).parent().children().hide();
            jQuery(this).parent().append('<div class="imx-loader"><i class="fas fa-spinner fa-spin"></i></div>');
            var projectID = jQuery(this).closest("tr").attr("id");
            projectID = projectID.split("-")[1];
            var projectICP = jQuery(this).closest("tr").data("icp");
            var backURL = ajax_object.backurl;
            jQuery.ajax({
                url: ajax_object.url,
                type: 'POST',
                //dataType: "json",
                data: {
                    action: 'imaxel_delete_project',
                    projectID: projectID,
                    projectICP: projectICP,
                    backURL: backURL
                },
                success: function (imaxelresponse, myAjax) {
                    if (myAjax == "success") {
                        console.log(imaxelresponse);
                        location.reload();
                    } else {
                        console.log(imaxelresponse);
                        location.reload();
                    }
                },
                error: function (imaxelresponse, myAjax) {
                    console.log(imaxelresponse);
					alert("Error loading imaxel_delete_project, if this message keeps showing up please contact support.")
				}
            })
        } else {
        }
        return false;

    })

});
