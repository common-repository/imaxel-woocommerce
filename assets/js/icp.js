let icp = {

	load() {
		jQuery(document).ready(() => {
			icp.checkErrorsData();
			icp.loadViewComponents();
			jQuery(window).on('resize', icp.resizeWindow);
			if (jQuery(window).width() <= 770) icp.resizeWindow();
		});
		this.bind();
	},

	bind() {
		jQuery(document).on('click', '.design_block_container_option', this.showDessignOption)
		jQuery(document).on('click', '#modify_design', this.confirmNewDessign)
		jQuery(document).on('click', '#edit_current_design', this.editDessign);
		jQuery(document).on('click', '.design_item_image', this.loadDesignEditor);
		jQuery(document).on('click', '#icp-project-name', this.showModalIcpForm)
		jQuery(document).on('change', '#pdf_file', this.pushPdf);
		jQuery(document).on('click', '.edit-from-cart-button-link', this.editItemCartHandler);
	},

	loadViewComponents() {
		if (jQuery(".confirm-exit").length > 0) icp.confirmExit();
	},

	// show error and reload browser
	checkErrorsData() {
		const element = jQuery("#error-project-info");
		if (element.length > 0) {
			const data = element.data();
			dialogs.emitError(data.message, () => {
				window.onbeforeunload = () => null;
				location.replace(data.backurl);
			});
		}
	},

	confirmExit() {
		//warning when leaving page
		window.onbeforeunload = function () {
			return modals.confirmExitTab;
		}
	},

	confirmNewDessign() {
		const editParams = jQuery(this).data();
		dialogs.emitConfirm(modals.confirmTitleModal, modals.confirmTextRemoveProject, icp.showNewDesign, editParams);
	},

	showNewDesign(objectData) {
		//remove block info
		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'removeBlockFromIcpProject',
				blockID: objectData.blockId,
				icpProject: objectData.icpProject,
			},
			success: function (response) {
				window.onbeforeunload = () => null;
				window.location.replace(objectData.returnUrl);
			},
			error: function (error) {
				dialogs.emitWpError(error);
			}
		});
	},

	//click on dessign o pdf option
	showDessignOption() {
		var blockType = jQuery(this).attr('block_type');
		if (blockType === 'editor_link') return icp.enableEditorLink();
		if (blockType === 'pdf_upload') return icp.enablePdfUpload();
	},

	enableEditorLink() {
		jQuery('#design_block_container_option_editor_link').addClass('design_block_container_option_active');
		jQuery('#design_block_container_option_upload_pdf').removeClass('design_block_container_option_active');
		jQuery('.design_pdf_uploader').hide();
		jQuery('.design_editor_links').show();
		jQuery('.ajax_responses').hide();
		jQuery('.recover_pdf_data_box').hide();
		jQuery("#editor_link_all_content").show();
	},

	enablePdfUpload() {
		jQuery('#design_block_container_option_upload_pdf').addClass('design_block_container_option_active');
		jQuery('#design_block_container_option_editor_link').removeClass('design_block_container_option_active');
		jQuery('.design_editor_links').hide();
		jQuery('.design_pdf_uploader').show();
		jQuery('.ajax_responses').show();
		jQuery('.recover_pdf_data_box').show();
	},

	editDessign() {
		icpHelpers.showBlockLoading(jQuery(".confirm_project_creation"), true, true);
		let post = jQuery(this).data();

		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'editIcpProject',
				dealerProductCode: post.productCode,
				variationCode: post.variationCode,
				siteOrigin: post.siteOrigin,
				productID: post.productId,
				dealerID: post.dealerId,
				blockID: post.blockId,
				icpProject: post.icpProject,
				returnURL: post.returnUrl,
				editorProjectId: post.project,
				attributeId: post.attributeId,
				valueId: post.valueId,
				wproduct: post.wproduct,
				price: post.price,
				product_module: post.productModule,
				attribute_id: post.attributeId,
				value_id: post.valueId,
				useEditorPrice: false,
				isLastBlock: post.lastBlock

			},
			success: function (response) {
				icpHelpers.hideBlockLoading();
				window.onbeforeunload = function () {
					return null;
				};
				window.location.replace(response.data);
			},
			error: function (error) {
				icpHelpers.hideBlockLoading();
				dialogs.emitWpError(error);
			}
		});
	},

	/**
	 * call the editor
	 */
	loadDesignEditor() {
		window.onbeforeunload = () => null;

		var dealerProductCode = jQuery(this).attr('dealer_product_code');
		var productID = jQuery(this).attr('product_id');
		var attribute_id = jQuery(this).attr('attribute_id');
		var value_id = jQuery(this).attr('value_id');
		var variationCode = jQuery(this).attr('variant_code');
		var siteOrigin = jQuery(this).attr('site_origin');
		var dealerID = jQuery(this).attr('dealer_id');
		var blockID = jQuery(this).attr('block_id');
		var icpProject = jQuery(this).attr('icp_project');
		var returnURL = jQuery(this).attr('returnURL');
		var wproduct = jQuery(this).attr('wproduct');
		var price = jQuery(this).attr('price');
		var product_module = jQuery(this).attr('product_module');
		var useEditorPrice = jQuery(this).attr('use_editor_price');

		var element = jQuery(this);
		element.hide();
		icpHelpers.showBlockLoading(jQuery(element), true);

		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'openEditor',
				dealerProductCode: dealerProductCode,
				variationCode: variationCode,
				siteOrigin: siteOrigin,
				productID: productID,
				dealerID: dealerID,
				blockID: blockID,
				icpProject: icpProject,
				returnURL: returnURL,
				wproduct: wproduct,
				price: price,
				product_module: product_module,
				attribute_id: attribute_id,
				value_id: value_id,
				useEditorPrice: useEditorPrice
			},
			success: function (response) {
				icpHelpers.hideBlockLoading();
				window.location.assign(response.data);
			},
			error: function (error) {
				icpHelpers.hideBlockLoading();
				element.show();
				dialogs.emitWpError(error);
			}
		});
	},

	//create and edit form icp block
	createNewIcpProject(skipCreationAndUpdate) {

		window.onbeforeunload = function () {
			return null;
		}

		var skipCreationAndUpdate = skipCreationAndUpdate;
		var selectedvariation = jQuery('#icp-start-customizing').attr('active_variation');
		var activeProject = jQuery('#icp-start-customizing').attr('project_id');
		var productform = jQuery("#variation_attributes_form").serializeArray();
		jQuery('#icp-start-customizing').hide();
		jQuery('.button-loader-box .lds-ellipsis').css('display', 'inline-block');

		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'createNewIcpProject',
				productform: productform,
				selectedvariation: selectedvariation,
				activeProject: activeProject,
				skipCreationAndUpdate: skipCreationAndUpdate
			},
			success: function (response) {
				window.location.assign(response);
			},
			error: function () {
				console.log('Total fail');
			}
		});
	},

	resizeWindow() {
		if (window.innerWidth <= 770) {
			jQuery('#design_open_pdf_uploader_link').attr("href", "#design_pdf_uploader");
			jQuery('#design_open_editor_links_link').attr("href", "#design_editor_links");
			jQuery('#design_open_pdf_uploader_link, #design_open_editor_links_link').on("click", function (e) {
				e.preventDefault();
				jQuery('html, body').animate({
					scrollTop: jQuery(jQuery.attr(this, 'href')).offset().top
				}, 500);
				return false;
			});
		} else {
			jQuery('#design_open_pdf_uploader_link').removeAttr("href");
			jQuery('#design_open_editor_links_link').removeAttr("href");
			jQuery('#design_open_pdf_uploader_link, #design_open_editor_links_link').off();
		}
	},

	showModalIcpForm() {
		const element = jQuery(this);
		const currentProject = element.data('projectId');

		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'showIcpFormModal',
				projectId: currentProject
			},
			success: function (response) {
				dialogs.emitForm(response.data.view, response.data.id, icp.showModalIcpFormSave)
			},
			error: function (error) {
				dialogs.emitWpError(error);
			}
		});

	},

	showModalIcpFormSave(attributes) {
		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'saveIcpProjectName',
				fields: attributes
			},
			success: function (response) {
				const title = "<h3>" + response.data.name + "</h3>";
				jQuery('#project-name').html(title);
			},
			error: function (error) {
				dialogs.emitWpError(error);
			}
		});
	},

	/**
	 * PDF Manage
	 * @param e
	 */
	pushPdf(e) {
		e.preventDefault();
		var file = e.target.files;

		if (file[0].type == 'application/pdf') {

			if (file[0].size <= parseInt(icpLocale.upload_max_size)) {
				jQuery('.pdf-nav .icp-forth.enabled').hide();
				jQuery('.pdf-nav .icp-forth.disabled').show();
				jQuery('.pdf_summary_box').html('<div class="lds-ring"><div></div><div></div><div></div><div></div></div>');

				var currentProject = jQuery(this).attr('project_id');
				var currentBlock = jQuery(this).attr('block_id');
				var currentSite = jQuery(this).attr('site_id');
				var currentURL = jQuery(this).attr('currentURL');
				var productID = jQuery(this).attr('product_id');
				var wproduct = jQuery(this).attr('wproduct');

				var data = new FormData();
				data.append("action", "validatePdfUploader");
				data.append("currentProject", currentProject);
				data.append("currentBlock", currentBlock);
				data.append("currentSite", currentSite);
				data.append("currentURL", currentURL);
				data.append("productID", productID);
				data.append("wproduct", wproduct);
				jQuery.each(file, function (key, value) {
					data.append("pdf_file", value);
				});

				jQuery.ajax({
					type: "POST",
					url: ajax_object.ajax_url,
					dataType: 'text',
					processData: false, // Don't process the files
					contentType: false, // Set content type to false as jQuery will tell the server its a query string request
					cache: false,
					data: data,
					success: function (response) {
						jQuery('.pdf_summary_box').html(response);
					},
					error: function (error) {
						jQuery('.pdf_summary_box').html(modals.pdfUploadRequest);
						dialogs.emitWpError(error)
					}
				});
			} else {
				dialogs.emitError(modals.pdfErrorSize);
			}
		} else {
			dialogs.emitError(modals.pdfUploadSelect);
		}
	},

	editItemCartHandler(e) {
		e.preventDefault();
		jQuery(this).parent().children().hide();
		jQuery(this).parent().append('<div class="imx-loader"><i class="fas fa-spinner fa-spin"></i></div>');

		const urlRedirect = jQuery(this).attr('href');
		const icpProject = jQuery(this).data('icpProject');
		jQuery.ajax({
			type: "POST",
			url: ajax_object.ajax_url,
			data: {
				action: 'setEditItemCartSession',
				icpProject
			},
			success: function () {
				window.location.assign(urlRedirect);
			},
			error: function () {
				console.log('cartHandler fails');
			}
		});
	}

};
icp.load();
