<?php

namespace Printspot\ICP\Controllers;

use Printspot\ICP\Helpers\Config;
use Printspot\ICP\Services\IcpService;
use Printspot\ICP\Services\ShopService;

/**
 * TODO: Pending review this controller and refactor everithing $this instance. At the moment this controller is unused.
 * Class IcpPdfController
 * @package Printspot\ICP\Controllers
 */
class IcpPdfController {


	/**
	 * PDF_UPLOADER: display pdf uploader
	 */

	public static function displayPDFuploader($projectData, $variationID = '', $blockID, $projectID, $siteID, $currentURL, $productID, $pdfAttributes, $productData, $wproduct = '', $blockAttributes) {

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

		//get existing discounts and apply them
		$savedProjectPrices = unserialize($projectData['price']);
		$volumeDiscount = floatval($savedProjectPrices['volume_discount']);
		$deliveryTimeDiscount = floatval($savedProjectPrices['delivery_time_discount']);
		if (!empty($volumeDiscount)) {
			$pagePrice = $pagePrice - (($pagePrice * $volumeDiscount) / 100) + (($pagePrice * $deliveryTimeDiscount) / 100);
			$areaPrice = $areaPrice - (($areaPrice * $volumeDiscount) / 100) + (($areaPrice * $deliveryTimeDiscount) / 100);
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
				$priceTotal = $unitPriceTotal * intval($quantity);
			}
		}

		//pdf attribute size
		if (empty($areaPrice)) {

			//pdf size restriction is setted
			if (!empty($pdfSize)) {
				echo '<p><strong>' . __('Size', 'imaxel') . ': </strong>';
				$pdfAttributeSize = unserialize($pdfSize);
				echo $pdfAttributeSize['width'] . ' x ' . $pdfAttributeSize['height'] . ' mm';
				echo '</p>';
			} else {
				echo '<p><strong>' . __('Size', 'imaxel') . ': </strong>';
				echo __('any', 'imaxel');
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
			} else {
				echo '<p><strong>' . __('Pages', 'imaxel') . ': </strong>';
				echo __('any', 'imaxel');
			}
		} else {
			echo '<p><strong>' . __('Pages', 'imaxel') . ': </strong>1';
		}

		//display price per page and area
		if (($pagePrice !== 0 && $pagePrice !== 0.0 && !empty($pagePrice)) && ($areaPrice == 0.0 && $areaPrice == 0)) {
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
			echo '<p style="margin-top: 12.5px;"><strong>' . __('Area printing price', 'imaxel') . ': </strong><span  id="pdf_price_area">'.drawPrice(0).'</span></p>';
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
				<input type="hidden" name="MAX_FILE_SIZE" value="1000"/>
				<?php echo '<input accept="application/pdf" name="pdf_file" id="pdf_file" project_id="' . $projectID . '" block_id="' . $blockID . '" site_id="' . $siteID . '" currentURL="' . $currentURL . '" product_id="' . $productID . '"'; ?>
				<?php if (!empty($wproduct)) {
					echo ' wproduct=' . $wproduct;
				} ?>
				<?php echo ' type="file" />'; ?>
			</form>
		</div>
		<?php

		//area calculator
		if (!empty($areaPrice)) {

			?>
			<script>
				//hide pdf form
				jQuery('#pdf_form').hide();
				jQuery('#pdf_form_help').show();

				//calculater pdf area
				jQuery('#pdf_height, #pdf_width').keydown(function (e) {
					setTimeout(function () {
						// var discounts = '<?php //echo $discounts
						?>';
						var quantity = '<?php echo $quantity; ?>';
						var pdfHeight = jQuery('#pdf_height').val();
						var pdfWidth = jQuery('#pdf_width').val();
						var areaPrice = '<?php echo $areaPrice; ?>';
						var areaMinWidth = '<?php echo $areaMinWidth; ?>';
						var areaMaxWidth = '<?php echo $areaMaxWidth; ?>';
						var areaMinHeight = '<?php echo $areaMinHeight; ?>';
						var areaMaxHeight = '<?php echo $areaMaxHeight; ?>';
						var projectPrice = '<?php echo $unitPriceTotal; ?>';

						var pdfArea = ((pdfHeight * pdfWidth) / 10000).toFixed(2); // cm2 to meters2
						var currency = '<?php echo get_woocommerce_currency_symbol(); ?>';
						var currencyPosition = '<?php echo get_option('woocommerce_currency_pos');?>';
						if (pdfHeight !== "" && pdfWidth !== "") {

							if (((parseFloat(areaMaxWidth) == 0) || (parseFloat(areaMaxWidth) > 0 && pdfWidth <= parseFloat(areaMaxWidth))) &&
									((parseFloat(areaMaxHeight) == 0) || (parseFloat(areaMaxHeight) > 0 && pdfHeight <= parseFloat(areaMaxHeight))) &&
									((parseFloat(areaMinWidth) == 0) || (parseFloat(areaMinWidth) > 0 && pdfWidth >= parseFloat(areaMinWidth))) &&
									((parseFloat(areaMinHeight) == 0) || (parseFloat(areaMinHeight) > 0 && pdfHeight >= parseFloat(areaMinHeight)))) {

								var displayProjectPrice = quantity + ' x ' + (parseFloat(projectPrice) + (parseFloat(areaPrice) * parseFloat(pdfArea))).toFixed(2) + ' = ' + ((parseInt(quantity) * (parseFloat(projectPrice) + (parseFloat(areaPrice) * parseFloat(pdfArea)))).toFixed(2)) + ' ' + currency;

								jQuery('#pdf_form').show();
								jQuery('#pdf_form_help').hide();
								jQuery('#pdf_total_area').html(pdfArea);
								jQuery('#pdf_price_area').html((parseFloat(areaPrice) * parseFloat(pdfArea)).toFixed(2));
								jQuery('#project_total_price').html((parseFloat(projectPrice) + (parseFloat(areaPrice) * parseFloat(pdfArea))).toFixed(2));
								jQuery('.projectPrice').html(displayProjectPrice);
								jQuery('#pdf_form_help_2').hide();
							} else {
								jQuery('#pdf_form_help_2').show();
							}
						} else {

							var displayProjectPrice = quantity + ' x ' + (parseFloat(projectPrice)).toFixed(2) + ' = ' + ((parseInt(quantity) * parseFloat(projectPrice)).toFixed(2)) + ' ' + currency;

							jQuery('#pdf_form').hide();
							jQuery('#pdf_form_help').show();
							jQuery('#pdf_total_area').html('0')
							jQuery('#pdf_price_area').html('0');
							jQuery('#project_total_price').html(parseFloat(projectPrice).toFixed(2));
							jQuery('.projectPrice').html(displayProjectPrice);
							jQuery('#pdf_form_help_2').hide();
						}

					}, 10);
				})
			</script>
			<?php
		}

		echo '</div>';

		echo '</div>';
		echo '</div>';

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
				<script>
					var areaWidth = '<?php echo $areaWidth; ?>';
					var areaHeight = '<?php echo $areaHeight; ?>';
					var pdfArea = (areaHeight * areaWidth).toFixed(2);
					var areaPrice = '<?php echo $areaPrice; ?>';
					jQuery('#pdf_form').show();
					jQuery('#pdf_form_help').hide();
					jQuery('#pdf_height').val(parseInt(areaHeight));
					jQuery('#pdf_width').val(parseInt(areaWidth));
					jQuery('#pdf_total_area').html(pdfArea);
					jQuery('#pdf_price_area').html((parseFloat(areaPrice) * parseFloat(pdfArea)).toFixed(2));
				</script>
				<?php
			}

			if (empty($areaPrice)) {
				echo '<p><strong>' . __('Pages', 'imaxel') . ': </strong>' . $recoverPdf->descriptor->numPages . '</p>';
				if (isset($pdfPrice) && floatval($pdfPrice) !== 0.0) {
					echo '<p><strong>' . __('Base price', 'imaxel') . ': </strong><span id="summaryBasePrice">' . $displayBasePrice . '</span></p>';
					echo '<p><strong>' . __('Pdf price', 'imaxel') . ': </strong>' . number_format(floatval($pdfPrice), 2) . ' ' . get_woocommerce_currency_symbol() . '</p>';
					//if( $isActiveDiscounts == 'on' ) {
					echo '<p><strong>' . __('Unit price', 'imaxel') . ': </strong><span id="summaryUnitPrice">' . number_format(floatval($unitPriceTotal), 2) . ' ' . get_woocommerce_currency_symbol() . '</span></p>';
					/*} else if($isActiveDiscounts == 'off') {
											echo '<p><strong>'.__('Project price','printspot-plugins').': </strong><span id="summaryUnitPrice">'.number_format(floatval($unitPriceTotal),2).' '.get_woocommerce_currency_symbol().'</span></p>';
										}*/
					//if( $isActiveDiscounts == 'on') {
					echo '<p><strong>' . __('Project price', 'imaxel') . ': </strong><span id="summaryProjectPrice">' . number_format(floatval($priceTotal), 2) . ' ' . get_woocommerce_currency_symbol() . '</span></p>';
					//}
				}
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
			echo '<div style="float:left;" class="button" style="margin-left: 2.5px;" onclick="savePdfBlock()" id="savePdfBlock" pdf_name="' . $pdfName . '" project_id="' . $projectID . '" area_price="' . $areaPrice . '" price="' . $updateProjectPrice . '" block_id="' . $blockID . '" site_id="' . $siteID . '" currentURL="' . $currentURL . '" pdf_id="' . $pdfID . '" product_id="' . $productID . '"';
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
			echo '<p>' . __('Upload a file in order to validate it..','imaxel') . '</p>';
		}
		echo '</div>';

		echo '</div>';
	}

	/**
	 * PDF_UPLOADER: validate pdf
	 */
	public static function validatePdfUploader() {

		//get data
		$projectID = $_POST['currentProject'];
		$blockID = $_POST['currentBlock'];
		$siteID = $_POST['currentSite'];
		$currentURL = $_POST['currentURL'];
		$productID = $_POST['productID'];
		$wproduct = $_POST['wproduct'];
		$pdfFile = $_FILES['pdf_file'];

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

			//get existing discounts and apply them
			$savedProjectPrices = unserialize($project['price']);
			$volumeDiscount = floatval($savedProjectPrices['volume_discount']);
			$deliveryTimeDiscount = floatval($savedProjectPrices['delivery_time_discount']);
			if (!empty($volumeDiscount)) {
				$pagePrice = $pagePrice - (($pagePrice * $volumeDiscount) / 100) + (($pagePrice * $deliveryTimeDiscount) / 100);
				$areaPrice = $areaPrice - (($areaPrice * $volumeDiscount) / 100) + (($areaPrice * $deliveryTimeDiscount) / 100);
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
				if (!empty($pdfSize)) {
					$pdfAttributeSize = unserialize($pdfSize);
					$pdfAttributeSize['width'] = $pdfAttributeSize['width'] * 2.83; // milimeters to pp
					$pdfAttributeSize['height'] = $pdfAttributeSize['height'] * 2.83; // milimeters to pp

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
				if ((isset($sizeValidation) && isset($pagesValidation)) && ($sizeValidation === false || $pagesValidation === false)) {
					if ($sizeValidation === false && $pagesValidation === false) {
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
					$displayBasePrice = number_format($basePrice, 2) . ' ' . get_woocommerce_currency_symbol();

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
							var unitPrice = '<?php echo $unitPrice . ' ' . get_woocommerce_currency_symbol(); ?>';
							var basePrice = '<?php echo $displayBasePrice; ?>';
							/*if(isActiveDiscounts == 'on') {*/
							var projectPrice = '<?php echo $quantity . ' x ' . $unitPrice . ' ' . get_woocommerce_currency_symbol() . ' = ' . $projectPrice . ' ' . get_woocommerce_currency_symbol(); ?>';
							/*} else if(isActiveDiscounts == 'off') {
								var projectPrice = '<?php echo $unitPrice . ' ' . get_woocommerce_currency_symbol(); ?>';
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
				echo 'Your validation has failed';
			}
		} else {
			echo $pdfResults['result']->message;
		}

		//finish the process
		wp_die();
	}

	/**
	 * PDF_UPLOADER: dislpay pdf thumbnail and continue button
	 */
	public static function displayPDFsummary($productData = '', $variationID = '', $pdfResults, $pdfFile, $projectID, $blockID, $siteID, $currentURL, $pdfKey, $productID, $wproduct, $updateProjectPrice = '', $areaPrice, $pagePrice) {

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
					echo '<p><strong>' . __('Pdf price', 'imaxel') . ': </strong>' . $pdfPrice . ' ' . get_woocommerce_currency_symbol() . '</p>';
					if ($isActiveDiscounts == 'on') {
						echo '<p><strong>' . __('Unit price', 'imaxel') . ': </strong><span id="summaryUnitPrice"></span></p>';
					} else if ($isActiveDiscounts == 'off') {
						echo '<p><strong>' . __('Project price', 'imaxel') . ': </strong><span id="summaryUnitPrice"></span></p>';
					}
					if ($isActiveDiscounts == 'on') {
						echo '<p><strong>' . __('Project price', 'imaxel') . ': </strong><span id="summaryProjectPrice"></span></p>';
					}
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

}
