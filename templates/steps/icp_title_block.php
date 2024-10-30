<?php

$productDataBreadcrumb = isset($productData) ? $productData : null;
$catalogueDataBreadcrumb = isset($catalogueData) ? $catalogueData : null;
?>
<div class="icp-header-box">
	<?php
	do_action('draw_custom_products_breadcrumb', $productDataBreadcrumb, $catalogueDataBreadcrumb);
	do_action('draw_business_presentation',$productPagePresentation);
	?>

	<?php if (isset($productName)) { ?>
		<div class="icp_flow_box_header">
			<div class="icp-product-title">
				<h2><?php echo $productName ?></h2>
				<div id="project-name">
					<?php if ($icpProjectData) { ?>
						<h3><?php echo ucfirst(mb_strtolower($icpProjectData->project_name)) ?></h3>
					<?php } ?>
				</div>
			</div>
			<?php if (isset($icpProjectData) && $icpProjectData) { ?>
			<div class="icp_save_section">
				<i class="far fa-save fa-lg" title="<?php echo __('Save and name','imaxel')?>" data-project-id="<?php echo $icpProjectData->id ?>" id="icp-project-name"></i>
			</div>
			<?php } ?>
		</div>
	<?php } ?>
</div>
