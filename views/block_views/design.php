<?php

//get project data

use Printspot\ICP\Services\EditorService;
use Printspot\ICP\Services\IcpOperationService;
use Printspot\ICP\Services\IcpService;
use Printspot\ICP\Services\ShopService;

global $wpdb;
$projectID = $_GET['icp_project'];
$blockID = $_GET['block'];
$productID = $_GET['id'];
$siteID = $_GET['site'];
$attributeID = $_GET['attribute_id'] ?? null;
$valueID = $_GET['value_id'] ?? null;
$wproduct = $_GET['wproduct'] ?? null;

$attributeProyecto = isset($_GET['attribute_proyecto']) ? $_GET['attribute_proyecto'] : NULL;
//TODO: DPI get_site_current_language() error
//$codLang = get_site_current_language();
$codLang = get_locale();

//get product data
$dealerID = $productData->definition->dealer;
$projectData = IcpService::getProjectData($projectID);

//set attribute project

if (empty($attributeProyecto) && isset($projectData['components'][$blockID]['dealer_project'])) {
	$attributeProyecto = $projectData['components'][$blockID]['dealer_project'];
}

//product variations from editor_link
$projectVariation = (isset($projectData['variation']) && $projectData['variation'] > 0) ? $projectData['variation'] : null;

//get pdf attributes
$pdfAttributes = $this->getPdfVariationAttributesData($projectData, $siteID, $productID, $productData, $blockID);

//get theme color
$primaryColor = ShopService::getPrimaryColor();
$shopSecondary = get_option('woocommerce_email_base_color');

//get step functions names==========================================================//
$blockAttributes = $this->getBlockAttributes($siteID, $productID, $blockID, $productData);


if (isset($blockAttributes['pdf_upload'])) {
	$settedPdf = array_filter((array)$block->attributes, function ($attribute) {
		return $attribute->definition->attribute_type === 'pdf_upload';
	});
	if (count($settedPdf) > 0) {
		$getPdfTitleArray = array_values($settedPdf)[0]->definition->attribute_name;
		$getPdfTitle = $getPdfTitleArray->$codLang ?? $getPdfTitleArray->default;

	}
	$pdfOptionTitle = isset($getPdfTitle) ? $getPdfTitle : __('Upload a PDf file', 'imaxel');
}

if (isset($blockAttributes['editor_link'])) {
	$settedEditor = array_filter((array)$block->attributes, function ($attribute) {
		return $attribute->definition->attribute_type === 'editor_link';
	});
	if (count($settedEditor) > 0) {
		$getEditorLinkTitleArray = array_values($settedEditor)[0]->definition->attribute_name;
		$getEditorLinkTitle = $getEditorLinkTitleArray->$codLang ?? $getEditorLinkTitleArray->default;
	}
	$editorOptionTitle = isset($getEditorLinkTitle) ? $getEditorLinkTitle : __('Create new design', 'imaxel');

}

//get dealer credentials============================================================//
if(!function_exists('is_plugin_active')) {
    include_once ( ABSPATH . 'wp-admin/includes/plugin.php' ); // required for is_plugin_active
}
if(is_plugin_active('imaxel-printspot/imaxel-printspot.php')) {
    if (intval($siteID) !== get_current_blog_id()) {
        //if product is shared
        $publicKey = get_option("standard_api_public_key");
        $privateKey = get_option("standard_api_private_key");
    } else {
        //if product is owned
        $sitePrefixTables = $wpdb->get_blog_prefix($siteID);
        $dealersTable = $sitePrefixTables . 'imaxel_printspot_shop_dealers';
        $dealerData = $wpdb->get_row("SELECT private_key, public_key FROM " . $dealersTable . " WHERE id=" . $dealerID . "");
        $publicKey = $dealerData->public_key;
        $privateKey = $dealerData->private_key;
    }
}
else
{
    $publicKey = get_option("wc_settings_tab_imaxel_publickey");
    $privateKey = get_option("wc_settings_tab_imaxel_privatekey");
}


//read project data================================================================//
if ( isset($attributeProyecto) || isset( $projectData['components'][$blockID]['dealer_project'] ) )
{
	(isset($attributeProyecto)) ? $attributeProject = $attributeProyecto : $attributeProject = $projectData['components'][$blockID]['dealer_project'];
	$projectInfo = IcpOperationService::icpReadProject($publicKey, $privateKey, $attributeProject);
	$projectInfo = json_decode($projectInfo);

	//if project in services is 404, redirect home page
	if ( isset($projectInfo->status) && $projectInfo->status == 404 )
	{ ?>
		<div id="error-project-info" data-message="<?php echo __("Sorry, this project no longer exists. Please create a new one." , 'imaxel') ?>" data-backUrl="<?php echo $backUrl ?>"></div>
		<?php
		return null;
	}

	//if project design is empty, show a message and don't let continue to the next step (reload page)
	if ( count( get_object_vars($projectInfo->design) ) === 0 )
	{
		$current_url = "http" . ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '' ) . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
		IcpService::removeBlockFromIcpProject($projectID,$blockID); ?>
		<div id="error-project-info" data-message="<?php echo __("Editor changes were not saved. Please, create the design again and finish the edition by clicking on the top-right button.", 'imaxel') ?>" data-backUrl="<?php echo $current_url ?>"></div>
		<?php
		return null;
	}
}

//get needed product data=============================================================//
foreach ($productData->blocks as $block) {
	$blocksDataByID[$block->definition->block_id]['definition'] = $block->definition;
	if (isset($block->attributes)) $blocksDataByID[$block->definition->block_id]['attributes'] = $block->attributes;
	$blockDataByOrder[$block->definition->block_order] = $block->definition;
	if ($block->definition->block_type == 'product_definition') {
		$productDefinitionBlockID = $block->definition->block_id;
	}
}
$currentPrice = unserialize($projectData['price']);
$unitPriceTotal = 0;


//if project comes form HTML5 editor get component price=============================//

if (isset($attributeProyecto)) {

	$componentPrice = 0;
	foreach ($blocksDataByID[$blockID]['attributes'] as $attribute) {
		foreach ($attribute->values as $value) {
			if ($value->id === $valueID && $value->attribute === $attributeID) {
				$valueData = unserialize($value->value_data);
				if (isset($valueData['use_editor_price']) && $valueData['use_editor_price'] === 'on') {

					//set project price
					switch ($projectInfo->product->module->code) {
						case "printspack":
						case "polaroids":
						case "simplephotobooks":
						case "simplephotobooks2":
							if (isset($projectInfo->design->price)) {
								$componentPrice = $projectInfo->design->price;
							}else {
								//if not design remove block info
								IcpService::removeBlockFromIcpProject($projectID,$blockID);
								?>
								<script>location.reload();</script>
								<?php
								die();
							}
							break;

						case "multigifts":
						case "gifts2d":
						case "wideformat":
						case "canvas":
						case "multipage":
							if (isset($projectInfo->product->variants[0]->price)) {
								$componentPrice = $projectInfo->product->variants[0]->price;
							}
							break;
					}

				} elseif (array_key_exists('price', $valueData)) {
					$componentPrice = $valueData['price'];
				} else {
					$componentPrice = NULL;
				}
			}
		}
	}
}

//go directly to next step
/**
 * TODO: COMMENTED BY NOT WORKS. FAILS TO ADD TO CART
 */
//if (isset($_GET['icp_next_step']) && $_GET['icp_next_step'] === '1') {
?>
<!--	<div class="cart_page_redirection_loading">-->
<!--		<div style="margin-top: 20px;" id="proceed_to_store_checkout_6_loader" class="lds-ring">-->
<!--			<div></div>-->
<!--			<div></div>-->
<!--			<div></div>-->
<!--			<div></div>-->
<!--		</div>-->
<!--		<p>--><?php //echo __('Redirecting to cart page', 'printspot-plugins') ?><!--</p>-->
<!--	</div>-->
<?php
//	$nextUrl = EditorService::confirmProjectDesign($siteID, $productID, $blockID, $projectID, $attributeProyecto, $currentURL, $componentPrice);

//	<script>
/*		var url = '<?php echo $nextUrl; ?>';*/
//		window.location.replace(url)
//	</script>


//	die();
//}
$saveComponentPrice = 0;
//set component price if a design without price has been selected====================//
if (!isset($componentPrice)) {

	$componentPrice = '';
}

//calculate price====================================================================//
$quantity = $currentPrice['qty'];
foreach ($currentPrice as $block => $blockPrice) {
	if (IcpService::isBlockData($block)) {

		//if is current block and load page from editor  project then not get blockPrice will be overwritten
		$unitPriceTotal = ($block == $blockID && $componentPrice) ? floatval($unitPriceTotal) : (floatval($unitPriceTotal) + floatval($blockPrice['total_price']));

		// if not set component price and is setted price in project data, we will set componenetnPrice = project compnent price
		if ($block == $blockID && !$componentPrice) {
			$saveComponentPrice = $blockPrice['total_price'];
		}

	}
}
$priceTotal = $unitPriceTotal * intval($quantity);

//price to display===================================================================//
$displayPrice = $quantity . ' x ' . number_format(floatval($unitPriceTotal), 2) . ' = ' . drawPrice($priceTotal);

//back and forth nav bar============================================================//
//check if project is the last, if yes show "AddToCart" if not show "Next"
$maxOrderBlock = max(array_keys($blockDataByOrder));
$isLastBlock = (intval($blocksDataByID[$blockID]['definition']->block_order) === $maxOrderBlock);
$nextButtonText = !$isLastBlock ?
		'<span class="projectPrice">' . $displayPrice . '</span>  | ' . __('Next', 'imaxel') :
		'<span class="projectPrice">' . $displayPrice . '</span>  | ' . __('End design', 'imaxel');


//load already existing design==============================*/
if (isset($attributeProyecto) || isset($projectData['components'][$blockID]['dealer_project'])) {

	?>
	<script>
		jQuery(document).ready(function () {
			jQuery('.icp-forth.disabled').hide();
			jQuery('.icp-forth.enabled.pdf-nav').hide();
			jQuery('.icp-forth.enabled.design-nav').show();
		});

	</script>

	<div class="confirm_project_creation design-button-loader-box animate__animated animate__fadeIn">

		<div class="confirm_project_creation_text" style="text-align: center;">

			<?php
			//show project price
			if (!empty($componentPrice)) {
				$saveComponentPrice = $componentPrice;
				//calculate and update project price
				$unitPriceTotal = $unitPriceTotal + floatval($componentPrice);
				$priceTotal = $unitPriceTotal * intval($quantity);
				?>
				<script>
					jQuery(document).ready(function () {
						var projectPrice = '<?php echo $quantity . ' x ' . drawPrice($unitPriceTotal) . ' = ' . drawPrice($priceTotal); ?>';
						jQuery('.projectPrice').html(projectPrice);
					});
				</script>
				<?php
			}

			//already defined editor design project but not coming from editor
			if (isset($attributeProyecto) || isset($projectData['components'][$blockID]['dealer_project'])) {
				//save data on printspot database
				EditorService::confirmProjectDesign($siteID, $productID, $blockID, $projectID, $attributeProyecto, $currentURL, $saveComponentPrice);
				?>

				<!-- message depends if isset custsom images or not -->
				<?php if (isset($projectInfo->collection) && count($projectInfo->collection->files) > 0) { ?>
					<p><?php printf(__('Your custom design has been created using a %s template.', 'imaxel'), $projectInfo->product->name->default); ?></p>
					<!--todo: error with images when template have a model-->
					<!--					<div class="project_images_box">-->
					<!--						--><?php
//						//sample of used images
//						$revertArray = array_reverse($projectInfo->collection->files);
//						for ($i = 0; $i < 3; $i++) {
//							if (isset($revertArray[$i])) {
//								echo '<img src="' . $revertArray[$i]->t_url . '">';
//							}
//						}
//						?>
					<!--					</div>-->
				<?php } else { ?>
					<p><?php printf(__('This project has an online design based on "%s" template', 'imaxel'), $projectInfo->product->name->default); ?></p>

				<?php } ?>

				<p><?php echo __('What do you want to do?', 'imaxel') ?></p>
				<!-- edit button -->
				<div id="edit_current_design"
					 data-project="<?php echo $attributeProyecto; ?>"
					 data-site-origin="<?php echo $siteID ?>"
					 data-product-id="<?php echo $productID ?>"
					 data-attribute-id="<?php echo $attributeID ?>"
					 data-value-id="<?php echo $valueID ?>"
					 data-product-code="<?php echo $productData->definition->code ?>"
					 data-variation-code="<?php echo '' ?>"
					 data-dealer-id="<?php echo $dealerID ?>"
					 data-block-id="<?php echo $blockID ?>"
					 data-icp-project="<?php echo $projectID ?>"
					 data-return-url="<?php echo $currentURL ?>"
					 data-price="<?php echo $priceTotal ?>"
					 data-product-module="<?php echo '' ?>"
					 data-use-editor-price="<?php echo false ?>"
					 data-last-block="<?php echo $isLastBlock ?>"
					 data-wproduct="<?php echo $wproduct ?>"
					 class="icp-button" style="background-color: <?php echo get_option('wc_settings_tab_imaxel_icp_color_theme')?>">
					<?php echo __('Edit your current design', 'imaxel') ?>
				</div>

				<!-- new dessign button -->
				<?php $urlReload = $currentURL . '?id=' . $productID . '&site=' . $siteID . '&block=' . $blockID . '&icp_project=' . $projectID ?>
				<div id="modify_design" data-return-url="<?php echo $urlReload ?>" data-icp-project="<?php echo $projectID ?>" data-block-id="<?php echo $blockID ?>" data-editing='<?php echo $attributeProyecto ?>'
					 class="icp-button" style="background-color: <?php echo get_option('wc_settings_tab_imaxel_icp_color_theme')?>"
				><?php echo __('Create new design', 'imaxel') ?></div>

				<!-- continues button -->
				<div onclick="saveDesignBlock()" class="icp-button" style="background-color: <?php echo get_option('wc_settings_tab_imaxel_icp_color_theme')?>;"><?php echo $nextButtonText; ?></div>
				<?php
			}
			?>

		</div>

		<?php echo '<div id="design_confirm_button" returnURL="' . $currentURL . '" attribute_proyecto="' . $attributeProyecto . '" icp_project="' . $projectID . '" block_id="' . $blockID . '" price="' . $saveComponentPrice . '" dealer_id="' . $dealerID . '" product_id="' . $productID . '" site_origin="' . $siteID . '" wproduct="' . $wproduct . '"></div>'; ?>

	</div>

	<?php

}

//design content
echo '<div id="desing_content_box"';
if (isset($attributeProyecto) || isset($projectData['components'][$blockID]['dealer_project'])) {
	echo ' style="display: none;">';
} else {
	echo ' style="">';
}

//display component title and short description=====================================//
echo '<div class="icp_short_description"><h4>' . $currentBlockData->block_title . '</h4></div>';
echo '<div class="icp_short_description"><p>' . $currentBlockData->block_short_description . '</p></div>';

//display block selector============================================================//
if ($blockAttributes !== null) {
	if (count($blockAttributes) > 1) { ?>
		<div class="design_block_container">

			<div class="design_block_container_option" id="design_block_container_option_editor_link"
				 block_type="editor_link">
				<?php include('icono_diseno20.svg') ?>
				<p><?php echo $editorOptionTitle ?></p>
			</div>

			<div class="design_block_container_option" id="design_block_container_option_upload_pdf"
				 block_type="pdf_upload">
				<?php include('icono_subepdf0.svg') ?>
				<p><?php echo $pdfOptionTitle ?></p>
			</div>

		</div>
	<?php }
} else {
	echo '<p>' . __('No configured functions for this step.', 'imaxel') . '</p>';
}

//editor_link function view
//=================================================================================//
if (isset($blockAttributes['editor_link'])) {
	include('function_views/editor_link_view.php');
}

//pdf_upload function view
//=================================================================================//
if (isset($blockAttributes['pdf_upload'])) {
	$display = (count($blockAttributes) == 1) || (isset($projectData['components'][$blockID]['pdf']) && !isset($_GET['attribute_proyecto'])) ? 'block' : 'none';
	$variationId = isset($projectData['variation']) ? $projectData['variation'] : null;
	ob_start();
	$this->displayPDFuploader($projectData, $variationId, $blockID, $projectID, $siteID, $currentURL, $productID, $pdfAttributes, $productData, $wproduct, $blockAttributes);
	$pdfUploader = ob_get_clean();
	icpLoadView('steps/pdf/partials/upload_pdf.php', [
			'display' => $display,
			'pdfOptionTitle' => $pdfOptionTitle,
			'pdfUploader' => $pdfUploader
	]);
}

//pdf_upload function view
//=================================================================================//
if (isset($blockAttributes['protransfer'])) {
	include('function_views/view/protransfer_view.php');
	include('function_views/business/protransfer_business.php');
}

echo '</div>';

?>

<div class="icp_short_description"><p><?php echo $currentBlockData->block_long_description; ?></p></div>
