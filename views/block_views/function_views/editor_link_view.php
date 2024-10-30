<?php

use Printspot\ICP\Services\IcpOperationService;
use Printspot\ICP\Services\IcpService;

echo '<div id="design_editor_links" class="design_editor_links"';
if ((count($blockAttributes) == 1) || (isset($_GET['attribute_proyecto']) || isset($projectData['components'][$blockID]['dealer_project']))) {
	echo ' style="">';
} else {
	echo ' style="display: none;">';
}

//templates catalogue div
$display = '';
if (isset($_GET['attribute_proyecto'])) {
	$display = 'display:none;';
}
echo '<div id="editor_link_all_content" style="' . $display . '">';

//new design===============================================*/
echo '<h4>' . $editorOptionTitle . '</h4>';
echo '<div class="editor_links_container">';

//get valid variation values
if ($projectVariation) {
	$blockExceptionsData = $productData->blocks[0]->variations->$projectVariation->block_exceptions;
	if (!empty($blockExceptionsData)) {
		$blockExceptionsArray = unserialize($blockExceptionsData);
		if (array_keys($blockExceptionsArray)[0] == array_values($blockExceptionsArray)[0]) {
			$blockExceptions = $blockExceptionsArray;
		} else {
			if (isset($blockExceptionsArray[$blockID])) {
				$blockExceptions = $blockExceptionsArray[$blockID];
			}
		}
	}
}

$codesArray = array();
foreach ($blockAttributes['editor_link']['value_data'] as $linkID => $editorLink) {

	if (@unserialize($editorLink['data'])) {

		$linkData = unserialize($editorLink['data']);
		$linkVariantCode = isset($linkData['variant_code']) ? $linkData['variant_code'] : $linkData['variation_code'];

		if (!$projectVariation) {

			//set price
			if (array_key_exists('price', $linkData)) {
				$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['price'] = $linkData['price'];
			}

			//set image
			if (array_key_exists('image', $linkData)) {
				$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['image'] = $linkData['image'];
			}

			//set attribute and value id
			$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['attribute_id'] = $editorLink['attribute_id'];
			$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['value_id'] = $editorLink['value_id'];
			$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['data'] = $linkData;
			$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['data']['product_code'] = $linkData['product_code'];
			$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['data']['variant_code'] = $linkVariantCode;

			//product codes string
			$codesArray[] = $linkData['product_code'];

		} else {
			$variationCodeEval = isset($linkVariantCode) ? $linkVariantCode : null;
			if (!empty($blockExceptions)) {
				if (IcpService::checkVariationInExceptions($blockExceptions, $linkData['variant_code'], $linkData['product_code'])) {
					//set price
					if (array_key_exists('price', $linkData)) {

						//set price
						$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['price'] = $linkData['price'];

						//set attribute and value id
						$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['attribute_id'] = $editorLink['attribute_id'];
						$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['value_id'] = $editorLink['value_id'];

					}

					//set image
					if (array_key_exists('image', $linkData)) {
						$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['image'] = $linkData['image'];
					}

					//set rest of the data
					$groupLinkValues[$blockAttributes['editor_link']['value_key'][$linkID]]['data'] = $linkData;

					//product codes string
					$codesArray[] = $linkData['product_code'];
				}
			}

		}

	}
}

$codes = '&codes=' . implode(',', $codesArray);
$importProducts = json_decode(IcpOperationService::icpDownloadProducts($publicKey, $privateKey, 'https://services.imaxel.com/api/v3', $codes, '&simplified=1'));
if (!empty($importProducts) && !isset($importProducts->msg)) { //Si services devuelve la propiedad "msg" significa que hay un error e algún tipo.
	if (isset($groupLinkValues)) {

		foreach ($groupLinkValues as $link) {

			//get dealer product data
			$productCode = $link['data']['product_code'];
			$variationCode = $link['data']['variant_code'] ?? $link['data']['variation_code'];

			$dealerData = array_filter($importProducts, function ($e) use ($productCode) {
				return $e->code == $productCode;
			});
			$variantDealerData = array_filter(array_values($dealerData)[0]->variants, function ($e) use ($variationCode) {
				return $e->code == $variationCode;
			});

			if( !empty($variantDealerData) ) $variantData = array_values($variantDealerData)[0];
			$productModule = array_values($dealerData)[0]->module->code;

			//set attribute_id and value_id
			$attribute_id = $link['attribute_id'] ?? '';
			$value_id = $link['value_id'] ?? '';
			$editorPrice = isset($link['data']['use_editor_price']) ? $link['data']['use_editor_price'] : '';


			//get link image
			if (isset($link['image']) && !empty($link['image'])) {
				$image = $link['image'];
			} else {
				$productCode = $link['data']['product_code'];
				$image = "";
				if (isset(array_values($dealerData)[0]->image->id)) {
					$image = json_decode(IcpOperationService::icpReadMedia($publicKey, $privateKey, array_values($dealerData)[0]->image->id))->storageInfo->url;
				}
			}

			//get template name
			$productName = array_values($dealerData)[0]->name->$codLang ?? array_values($dealerData)[0]->name->default;
			$variantName = !empty($variantData) ? ($variantData->name->$codLang ?? $variantData->name->default) : null;
			if(function_exists('getTranslatedValue')) {
                $customName = isset($link['data']['custom_name']) ? getTranslatedValue($link['data']['custom_name'], $value_id) : '';
            }
			else {
                $customName = ''; //TODO: Review no icp
            }
			//get template price
			if ($editorPrice === 'on') {
				//get lowest editor variant price
				if (in_array($productModule, array('printspack', 'polaroids', 'simplephotobooks', 'simplephotobooks2'))) {
					$price = array_reduce(array_values($dealerData)[0]->variants, function ($memo, $value) {
						return array_reduce($value->price_values, function ($memo, $value) {
							return (!empty($memo) && ($memo < $value->price)) ? $memo : $value->price;
						});
					});
				} elseif (in_array($productModule, array('multigifts', 'gifts2d', 'wideformat', 'canvas', 'multipage'))) {
					$price = array_reduce(array_values($dealerData)[0]->variants, function ($memo, $value) {
						return (!empty($memo) && ($memo < $value->price)) ? $memo : $value->price;
					});
				}
			} else {
				//get template link price
				$price = $link['price'] ?? 0;
			}

			icpLoadView('steps/editors/partials/item_editor_link.php', [
				'currentUrl' => $currentURL,
				'attributeId' => $attribute_id,
				'valueId' => $value_id,
				'price' => $price,
				'editorPrice' => $editorPrice,
				'projectId' => $projectID,
				'blockId' => $blockID,
				'dealerId' => $dealerID,
				'productId' => $productID,
				'link' => $link,
				'variationCode' => $variationCode,
				'productModule' => $productModule,
				//'siteId' => $siteID,
                'siteId' => get_current_blog_id(), //TODO: Talk with Jesus
				'wproduct' => $wproduct,
				'image' => $image,
				'customName' => $customName,
				'productName' => $productName,
				'variantName' => $variantName
			]);
		}

	} else {
		echo '<p>' . __('Not allowed templates for this product.', 'imaxel') . '</p>';
	}
} else {
	echo '<p>' . __('No products found with this variations codes in this dealer.', 'imaxel') . '</p>';
}

echo '</div>';
echo '</div>';
echo '</div>';
