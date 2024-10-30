<?php

if ($urlRedirect) { ?>
	<script>
		window.onbeforeunload = () => null;
		var backUrl = "<?php echo $urlRedirect ?>"
		window.location.assign(backUrl);

	</script>
<?php } ?>

<?php

if (isset($simpleEditorURL) && $simpleEditorURL) { ?>
	<script>
		window.onbeforeunload = () => null;
		var redirectURL = "<?php echo $simpleEditorURL ?>"
		window.location.assign(redirectURL);

	</script>
<?php } ?>

<div id="app" class="icp-box confirm-exit">

	<?php
	//if there's an active project
	if ($grantAccess) { ?>

		<?php

		//get product catalogue data and build breadcrumbs
		$productDataBreadcrumb = [
				'productId' => $_GET['id'],
				'siteId' => $_GET['site'],
				'productName' => $productName
		];


		icpLoadView('steps/icp_title_block.php', [
				'productName' => $productName,
				'productData' => $productDataBreadcrumb,
				'productPagePresentation' => true,
				'icpProjectData' => $icpProjectData,
		]);


		echo $navbarBlocks;
		?>

		<div class="block_content">
			<?php switch ($blogType) {

				//product definition block
				case 'product_definition':
					include('block_models/product_definition_model.php');
					include('block_views/product_definition.php');

					break;

				//pdf uploader block
				case 'pdf_upload':
					include('block_views/pdf_upload.php');
					break;

				//design block
				case 'design':
					include('block_views/design.php');
					break;

			} ?>

		</div>

	<?php } else { ?>
		<p><?php echo __('Sorry but your are not allowed to be here!', 'imaxel') ?></p>
	<?php } ?>

</div>
