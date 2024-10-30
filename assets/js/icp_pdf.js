icppdf = {
	load() {
		this.bind()
		jQuery(document).ready(() => {
			if(jQuery("#icp-pdf-project-data").length > 0){
				icppdf.loadPdfProjectData()
			}
		});
	},
	bind() {
		jQuery(document).on('keydown', '#pdf_height, #pdf_width', this.calculatePdfArea);
	},

	/**
	 * set pdf data when exist project
	 */
	loadPdfProjectData() {
		data = jQuery("#icp-pdf-project-data").data();
		var areaWidth = data.areaWidth;
		var areaHeight = data.areaHeight;
		var projectPrice = data.projectPrice;
		var pdfArea = ((areaWidth * areaHeight) / 10000).toFixed(2);
		var areaPrice = data.areaPrice;
		var quantity = data.quantity;
		jQuery('#pdf_form').show();
		jQuery('#pdf_form_help').hide();
		jQuery('#pdf_height').val(parseInt(areaHeight));
		jQuery('#pdf_width').val(parseInt(areaWidth));
		jQuery('#pdf_total_area').html(pdfArea);
		icppdf.setPriceArea(areaPrice,pdfArea);
		jQuery('#project_total_price').html(icpHelpers.drawPrice((parseFloat(projectPrice)).toFixed(2)));
	},

	setPriceArea(areaPrice,pdfArea){
		element = jQuery("#pdf_price_area");
		const price = (parseFloat(areaPrice) * parseFloat(pdfArea)).toFixed(2);
		element.html(icpHelpers.drawPrice(price));
		element.data('price',price)
	},

	/**
	 * save pdf url
	 */
	savePdfBlock() {
		window.onbeforeunload = function () {
			return null;
		}
		const element = jQuery('#savePdfBlock');
		element.hide();
		jQuery('.button-loader-box .lds-ellipsis').show();
		var currentProject = element.attr('project_id');
		var currentBlock = element.attr('block_id');
		var currentURL = element.attr('currentURL');
		var currentSite = element.attr('site_id');
		var productID = element.attr('product_id');
		var pdfID = element.attr('pdf_id');
		var pdfName = element.attr('pdf_name');
		var wproduct = element.attr('wproduct');
		if (element.attr('area_price') !== '0' && element.attr('area_price') !== '') {
			var price = jQuery('#pdf_price_area').data('price');
			var pdfHeight = jQuery('#pdf_height').val();
			var pdfWidth = jQuery('#pdf_width').val();
		} else {
			var price = element.attr('price');
			var pdfHeight = '';
			var pdfWidth = '';
		}

		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'savePdfUploader',
				currentProject: currentProject,
				currentBlock: currentBlock,
				currentURL: currentURL,
				currentSite: currentSite,
				productID: productID,
				pdfID: pdfID,
				pdfName: pdfName,
				wproduct: wproduct,
				price: price,
				pdfHeight: pdfHeight,
				pdfWidth: pdfWidth
			},
			success: function (response) {
				window.location.assign(response);
			},
			error: function () {
				console.log('Total fail');
			}
		});
	},

	/**
	 * Calculate PDF area when keydown
	 * @param e
	 */
	calculatePdfArea(e) {
		setTimeout(function () {
			data = jQuery("#keydown-calculate-data").data();
			var quantity = data.quantity;
			var pdfHeight = jQuery('#pdf_height').val();
			var pdfWidth = jQuery('#pdf_width').val();
			var areaPrice = data.areaPrice;
			var areaMinWidth = data.areaMinWidth;
			var areaMaxWidth = data.areaMaxWidth;
			var areaMinHeight = data.areaMinHeight;
			var areaMaxHeight = data.areaMaxHeight;
			var projectPrice = data.projectPrice;

			var pdfArea = ((pdfHeight * pdfWidth) / 10000).toFixed(2); // cm2 to meters2

			if (pdfHeight !== "" && pdfWidth !== "") {

				if (((parseFloat(areaMaxWidth) == 0) || (parseFloat(areaMaxWidth) > 0 && pdfWidth <= parseFloat(areaMaxWidth))) &&
					((parseFloat(areaMaxHeight) == 0) || (parseFloat(areaMaxHeight) > 0 && pdfHeight <= parseFloat(areaMaxHeight))) &&
					((parseFloat(areaMinWidth) == 0) || (parseFloat(areaMinWidth) > 0 && pdfWidth >= parseFloat(areaMinWidth))) &&
					((parseFloat(areaMinHeight) == 0) || (parseFloat(areaMinHeight) > 0 && pdfHeight >= parseFloat(areaMinHeight)))) {

					var displayProjectPrice = quantity + ' x ' + (parseFloat(projectPrice) + (parseFloat(areaPrice) * parseFloat(pdfArea))).toFixed(2) + ' = ' + ((parseInt(quantity) * (parseFloat(projectPrice) + (parseFloat(areaPrice) * parseFloat(pdfArea)))).toFixed(2));

					jQuery('#pdf_form').show();
					jQuery('#pdf_form_help').hide();
					jQuery('#pdf_total_area').html(pdfArea);
					icppdf.setPriceArea(areaPrice,pdfArea);
					jQuery('#project_total_price').html(icpHelpers.drawPrice((parseFloat(projectPrice) + (parseFloat(areaPrice) * parseFloat(pdfArea))).toFixed(2)));
					jQuery('.projectPrice').html(displayProjectPrice);
					jQuery('#pdf_form_help_2').hide();
				} else {
					jQuery('#pdf_form_help_2').show();
				}
			} else {

				var displayProjectPrice = quantity + ' x ' + (parseFloat(projectPrice)).toFixed(2) + ' = ' + ((parseInt(quantity) * parseFloat(projectPrice)).toFixed(2));

				jQuery('#pdf_form').hide();
				jQuery('#pdf_form_help').show();
				jQuery('#pdf_total_area').html('0')
				icppdf.setPriceArea(0,0);
				jQuery('#project_total_price').html(icpHelpers.drawPrice(parseFloat(projectPrice).toFixed(2)));
				jQuery('.projectPrice').html(displayProjectPrice);
				jQuery('#pdf_form_help_2').hide();
			}

		}, 10);
	}

}

icppdf.load();
