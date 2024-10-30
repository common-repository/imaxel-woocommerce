<?php

//get project id if defined
$projectID = $_GET['icp_project'] ?? null;

//Get current catalogue data
$catalogueId = $catalogueID = apply_filters('imaxel_get_shop_catalogue_id', $productID);
$currentCatalog = apply_filters('imaxel_get_shop_catalogue_data', $catalogueId);
$currencyPosition = get_option('woocommerce_currency_pos');

//check if project is the first, if yes show "Exit" if not show "Back"
//get needed product data
foreach($productData->blocks as $block) {
	$blocksDataByID[$block->definition->block_id]['definition'] = $block->definition;
	$blockDataByOrder[$block->definition->block_order] = $block->definition;
}
$backUrl = wc_get_page_permalink( 'shop' );

$minOrderBlock = min(array_keys($blockDataByOrder));
(intval($blocksDataByID[$blockID]['definition']->block_order) === $minOrderBlock) ? $backButtonText =  __('Exit','imaxel') :  $backButtonText =  __('Back','imaxel');
?>
<div class="page-loader">
	<div class="lds-ellipsis">
		<div></div>
		<div></div>
		<div></div>
		<div></div>
	</div>
</div>

<div class="icp-definition-box">

	<?php if (count($productData->blocks) > 1) { ?>
		<div class="icp_title"><h4>{{activeBlock.definition.block_title}}</h4></div>
		<div v-if="productDefinition.short_description" class="icp_short_description">
			<span class="printspot_highlight"><h3><strong><?php echo __('Features', 'imaxel'); ?></strong></h3></span>
			<p v-html="productDefinition.short_description">{{productDefinition.short_description}}</p>
		</div>
		<div v-else="productDefinition.short_description" class="icp_short_description">
			<p v-html="activeBlock.definition.block_short_description">
				{{activeBlock.definition.block_short_description}}</p></div>
	<?php } ?>

	<div class="icp-definition-box-content">

		<div class="icp-definition-box-left">

			<div class="icp-definition-product-image">

				<div v-if="externalImageUrl">
					<img class="animate__animated animate__zoomIn animate__fast" :src="externalImageUrl">
				</div>

				<div v-else="externalImageUrl">

					<div v-if="activeVariation">
						<img class="animate__animated animate__zoomIn animate__fast" v-if="variations[activeVariation].image" :src="variations[activeVariation].image">
						<img class="animate__animated animate__zoomIn animate__fast" v-else="variations[activeVariation].image" :src="productDefinition.image">
					</div>

					<div v-else="activeVariation">
						<img class="animate__animated animate__zoomIn animate__fast" :src="productDefinition.image">
					</div>

				</div>

			</div>

		</div>

		<div class="icp-definition-box-right">

			<?php if (count($productData->blocks) <= 1) { ?>
				<div class="icp_title"><h4 style="margin-top: 0px;">{{activeBlock.definition.block_title}}</h4></div>
				<div v-if="productDefinition.short_description" class="icp_short_description">
					<span class="printspot_highlight"><h3><strong><?php echo __('Features', 'imaxel'); ?></strong></h3></span>
					<p v-html="productDefinition.short_description">{{productDefinition.short_description}}</p>
				</div>
				<div v-else="productDefinition.short_description" class="icp_short_description">
					<p v-html="activeBlock.definition.block_short_description">
						{{activeBlock.definition.block_short_description}}</p></div>

			<?php } ?>

			<div class="product_definition_form">
				<form id="variation_attributes_form" name="variation_attributes_form" method="POST" action="">
                    <?php if(isset($_GET['wproduct'])) { ?>
                        <input type="hidden" name="wproduct" id="wproduct" value=<?php echo $_GET['wproduct'];?>>
                    <?php } ?>
					<input type="hidden" name="icp_url" id="icp_url" value=<?php echo $currentURL; ?>>
					<input type="hidden" name="product_id" id="product_id" :value="productDefinition.id">
					<input type="hidden" name="product_name" id="product_name" :value="productDefinition.name">
					<input type="hidden" name="site_origin" id="site_origin" :value="siteOrigin">
					<input type="hidden" name="dealer" id="dealer" :value="productDefinition.dealer">
					<input type="hidden" v-if="activeVariation" name="variation_price" id="variation_price" :value="variationPrice">
					<input type="hidden" v-if="activeVariation" name="variation_volumetric" id="variation_volumetric" :value="variationVolumetric">
					<input type="hidden" v-if="activeVariation" name="variation_page_price" id="variation_page_price" :value="variationPagePrice">
					<input type="hidden" v-if="activeVariation" name="variation_area_price" id="variation_area_price" :value="variationAreaPrice">
					<input type="hidden" v-if="activeVariation" name="variation_total_price" id="variation_total_price" :value="variationTotalPrice">
					<input type="hidden" v-else="activeVariation" name="variation_price" id="variation_price" :value="price">
					<input type="hidden" v-if="days" name="production_time" id="production_time" :value="selectedCharge">
					<input type="hidden" name="qty" id="qty" :value="qty">
					<input type="hidden" name="volume_discount" id="volume_discount" :value="activeDiscount">
					<input type="hidden" name="delivery_time_discount" id="delivery_time_discount" :value="activeExtraCharge">
					<input v-if="activeVariation" type="hidden" name="variation_id" :value="variations[activeVariation].id">
					<input v-if="externalImageUrl" type="hidden" name="external_url" :value="externalImageUrl">
					<input type="hidden" name="block_id" id="block_id" :value="activeBlock.definition.block_id">
					<select-attribute v-if="initialAttributes" :attributes="initialAttributes" :selected-attr-value="selectedAttrValue" @update-attr-value="udpateAttrValue"></select-attribute>
				</form>
			</div>

			<div class="active_varitation_actions_right">

				<div v-if="activeVariation">

					<div style="margin-bottom: 7.5px;" class="production_time_button" v-if="days">
						<label for="production_time_field"><strong>{{productionCharges.name}}</strong></label>
						<select v-model="selectedCharge" @change="updatePrice">
							<option v-if="day.days != null " v-for="day in days" :value="day.days">
								{{day.days}} <?php echo __('days', 'imaxel'); ?> </option>
						</select>
					</div>

					<div v-if="discounts == false || productDefinition.discount_type=='range_volume_discount' || (productDefinition.discount_type == 'fixed_volume_discount' && discounts.fixed_volume_discounts == undefined)" class="qty_button">
						<label v-if="discountLabel" for="qty_field"><strong>{{discountLabel}}</strong></label>
						<label v-else="discountLabel" for="qty_field"><strong><?php echo __('Quantity', 'imaxel'); ?></strong></label>
						<input v-model="qty" id="qty_field" @change="updatePrice" type="number" min="1" max="9999999" step="1">
					</div>

					<div v-if="productDefinition.discount_type == 'fixed_volume_discount' && discounts.fixed_volume_discounts" class="qty_button">
						<label v-if="discountLabel" for="qty_field"><strong>{{discountLabel}}</strong></label>
						<label v-else="discountLabel" for="qty_field"><strong><?php echo __('Quantity', 'imaxel'); ?></strong></label>
						<select v-model="qty" @change="updatePrice">
							<option v-for="discounts in fixedDiscountValues" :value="discounts.quantity">
								{{discounts.quantity}}
							</option>
							<select>
					</div>

					<div class="variation_price_box">

						<div class="variation_price_box_price">

							<p v-if="variationPrice>0" style="font-size: 14px;">
								<span class="final_price_tag"><strong><?php echo __('Unit price', 'imaxel'); ?>: </strong></span>
								<span class="final_price_value">
                                        <strong>
											<?php if ($currencyPosition == 'left' || $currencyPosition == 'left_space') { ?>
												<?php echo get_woocommerce_currency_symbol(); ?>  {{variationPrice}}
											<?php } else if ($currencyPosition == 'hidden') { ?>
												{{variationPrice}}
											<?php } else { ?>
												{{variationPrice}} <?php echo get_woocommerce_currency_symbol(); ?>
											<?php } ?>
                                        </strong>
                                    </span>
							</p>

							<p v-if="variationPrice>0" style="font-size: 16px;">
								<span class="final_price_tag"><strong><?php echo __('Total price', 'imaxel'); ?>: </strong></span>
								<span class="final_price_value">
                                        <strong>
												<?php if ($currencyPosition == 'left' || $currencyPosition == 'left_space') { ?>
													<span>{{calculatedQty}} x  <?php echo get_woocommerce_currency_symbol(); ?> {{variationPrice}} =</span>
													<?php echo get_woocommerce_currency_symbol(); ?> {{variationTotalPrice}}
												<?php } else if ($currencyPosition == 'hidden') { ?>
													<span>{{calculatedQty}} x  {{variationPrice}} =</span>  {{variationTotalPrice}}
												<?php } else { ?>
													<span>{{calculatedQty}} x {{variationPrice}} <?php echo get_woocommerce_currency_symbol(); ?> =</span>
													{{variationTotalPrice}} <?php echo get_woocommerce_currency_symbol(); ?>
												<?php } ?>
                                        </strong>
                                    </span>
							</p>

						</div>

						<?php include('assets/product_definition/nextStepButton.php') ?>

					</div>

				</div>

				<div v-else="activeVariation">

					<div v-show="enableContinueButton">

						<div style="margin-bottom: 7.5px;" class="production_time_button" v-if="days">
							<label for="production_time_field"><strong>{{productionCharges.name}}</strong></label>
							<select v-model="selectedCharge" @change="updatePrice">
								<option v-if="day.days != null" v-for="day in days" :value="day.days">
									{{day.days}} <?php echo __('days', 'imaxel'); ?> </option>
							</select>
						</div>

						<div v-if="discounts == false || productDefinition.discount_type=='range_volume_discount' || (productDefinition.discount_type == 'fixed_volume_discount' && discounts.fixed_volume_discounts == undefined)" class="qty_button">
							<label v-if="discountLabel" for="qty_field"><strong>{{discountLabel}}</strong></label>
							<label v-else="discountLabel" for="qty_field"><strong><?php echo __('Quantity', 'imaxel'); ?></strong></label>
							<input v-model="qty" id="qty_field" @change="updatePrice" type="number" min="1" max="9999999" step="1">
						</div>

						<div v-if="productDefinition.discount_type == 'fixed_volume_discount' && discounts.fixed_volume_discounts" class="qty_button">
							<label v-if="discountLabel" for="qty_field"><strong>{{discountLabel}}</strong></label>
							<label v-else="discountLabel" for="qty_field"><strong><?php echo __('Quantity', 'imaxel'); ?></strong></label>
							<select v-model="qty" @change="updatePrice">
								<option v-for="discounts in fixedDiscountValues" :value="discounts.quantity">
									{{discounts.quantity}}
								</option>
								<select>
						</div>

						<div class="variation_price_box">

							<div class="variation_price_box_price">

								<p v-if="price>0" style="font-size: 14px;">
									<span class="final_price_tag"><strong><?php echo __('Unit price', 'imaxel'); ?>: </strong></span>
									<span class="final_price_value">
                                            <strong>
													<?php if ($currencyPosition == 'left' || $currencyPosition == 'left_space') { ?>
														<?php echo get_woocommerce_currency_symbol(); ?> {{price}}
													<?php } else if ($currencyPosition == 'hidden') { ?>
														{{price}}
													<?php } else { ?>
														{{price}} <?php echo get_woocommerce_currency_symbol(); ?>
													<?php } ?>

                                            </strong>
                                        </span>
								</p>

								<p v-if="totalPrice>0" style="font-size: 16px;">
									<span class="final_price_tag"><strong><?php echo __('Total price', 'imaxel'); ?>: </strong></span>
									<span class="final_price_value">
                                            <strong>
												<?php if ($currencyPosition == 'left' || $currencyPosition == 'left_space') { ?>
													<span>{{calculatedQty}} x  <?php echo get_woocommerce_currency_symbol(); ?> {{price}} =</span>
													<?php echo get_woocommerce_currency_symbol(); ?> {{totalPrice}}
												<?php } else if ($currencyPosition == 'hidden') { ?>
													<span>{{calculatedQty}} x {{price}} =</span> {{totalPrice}}
												<?php } else { ?>
													<span>{{calculatedQty}} x {{price}} <?php echo get_woocommerce_currency_symbol(); ?> =</span>
													{{totalPrice}} <?php echo get_woocommerce_currency_symbol(); ?>
												<?php } ?>

                                            </strong>
                                        </span>
								</p>

							</div>

							<?php include('assets/product_definition/nextStepButton.php') ?>

						</div>

					</div>
				</div>

			</div>

		</div>

	</div>

	<div v-if="productDefinition.long_description" class="icp_long_description">
		<p v-html="productDefinition.long_description">{{productDefinition.long_description}}</p></div>
	<div v-else="productDefinition.long_description" class="icp_long_description">
		<p v-html="activeBlock.definition.block_long_description">{{activeBlock.definition.block_long_description}}</p>
	</div>

</div>

<div id="ajax_responses"></div>
