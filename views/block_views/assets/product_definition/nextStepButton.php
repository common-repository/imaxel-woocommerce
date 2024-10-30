<?php

use Printspot_Woocommerce\Services\ShopGalleriesService;

//TODO: DPI review with MARC print_on_demand, imaxel_printspot_shop_galleries se estan utilizando?
?>

<div class="button-loader-box">

	<?php if ($productData->definition->product_type == 'print_on_demand' && !isset($_GET['image_origin'])) {
		$galleryData = ShopGalleriesService::getProductGallery($productData->definition->id);

		if($galleryData && $galleryData->slug) { ?>
			<div class="button" id="icp-start-customizing">
				<a href="<?php echo get_site_url() . '/gallery/' . $galleryData->slug ?>">
					<?php echo __('Choose image', 'imaxel'); ?>
				</a>
			</div>
		<?php } else { ?>
			<p style="color:rgb(85, 86, 90); margin-top: 5px;">
				<?php echo __('This product does not have any gallery', 'imaxel'); ?>
			</p>
		<?php }
	} else { ?>
		<!--project id defined-->
		<div v-if="projectId && ( !activeVariation || (projectVariation == activeVariation) )" v-show="enableContinueButton" onclick="icp.createNewIcpProject(true)" class="button" project_id="<?php echo $projectID; ?>" id="icp-start-customizing" :active_variation="activeVariation">
			<?php if (count($productData->blocks) > 1) {
				echo __('Next', 'imaxel') . ' <i class="fas fa-chevron-circle-right"></i>';
			} else {
				echo __('Add to Cart', 'imaxel');
			} ?>
		</div>

		<!--project id NOT defined-->
		<div v-if="!projectId || ( activeVariation && (projectVariation != activeVariation) )" v-show="enableContinueButton" onclick="icp.createNewIcpProject(false)" class="button" project_id="<?php echo $projectID; ?>" id="icp-start-customizing" :active_variation="activeVariation">
			<?php if (count($productData->blocks) > 1) {
				echo __('Next', 'imaxel') . ' <i class="fas fa-chevron-circle-right"></i>';
			} else {
				echo __('Add to Cart', 'imaxel');
			} ?>
		</div>

		<p style="color:rgb(85, 86, 90); margin-top: 5px;" v-if="projectId && ( activeVariation && (projectVariation != activeVariation) )">
			<?php echo __('If you modify the options of your project the current design will be lost.', 'imaxel'); ?>
		</p>

	<?php } ?>
	<div class="lds-ellipsis">
		<div></div>
		<div></div>
		<div></div>
		<div></div>
	</div>

</div>
