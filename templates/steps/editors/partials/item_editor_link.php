<div class="design_item" style="margin-top: 15px;">
	<!--image-->
	<div class="design_item_image_container">
		<div class="design_item_image"
			 returnURL="<?php echo $currentUrl ?>"
			 attribute_id="<?php echo $attributeId ?>"
			 value_id="<?php echo $valueId ?>"
			 price="<?php echo $price ?>"
			 use_editor_price="<?php echo $editorPrice ?>"
			 icp_project="<?php echo $projectId ?>"
			 block_id="<?php echo $blockId ?>"
			 dealer_id="<?php echo $dealerId ?>"
			 product_id="<?php echo $productId ?>"
			 dealer_product_code="<?php echo $link['data']['product_code'] ?>"
			 variant_code="<?php echo $variationCode ?>"
			 product_module="<?php echo $productModule ?>"
			 site_origin="<?php echo $siteId ?>"
			 wproduct="<?php echo $wproduct ?>"
			 style="background-image:url(<?php echo $image ?>);">
		</div>
	</div>

	<!--name-->
	<div style="margin-top: 7.5px;" class="design_item_name">
		<p>
			<?php if ($customName) { ?>
				<span><?php echo $customName ?></span>
			<?php } else { ?>
				<span><?php echo $productName ?></span>
				<br>
				<small><?php echo $variantName ?></small>
			<?php } ?>
		</p>
	</div>

	<!--template price-->
	<?php if (!empty($price)) : ?>
		<div class="design_item_price">
			<p><?php echo __('From', 'imaxel') . drawPrice($price) ?></p>
		</div>
	<?php endif; ?>

	<!--loader-->
	<div class="editor_loader" id="editor_loader_<?php echo $valueId ?>">
		<div class="lds-ellipsis">
			<div></div>
			<div></div>
			<div></div>
			<div></div>
		</div>
	</div>
</div>
