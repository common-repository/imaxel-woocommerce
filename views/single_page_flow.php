<?php
//ICP single page flow
global $wpdb;

//get product blocks
foreach($productData->blocks as $block) {
    $blocksData[$block->definition->block_id]['name'] = $block->definition->block_name;
    $blocksData[$block->definition->block_id]['order'] = $block->definition->block_order;
    $blocksData[$block->definition->block_id]['type'] = $block->definition->block_type;
}

//if project exists, get project state

if(isset($_GET['icp_project'])) {
    $projectID = $_GET['icp_project'];
    $productProjectComponentsTable = $wpdb->prefix.'icp_products_projects_components';
    $getProjectData = $wpdb->get_row("SELECT value FROM ".$productProjectComponentsTable." WHERE project=$projectID");
    $projectData = unserialize($getProjectData->value);
}

//print products title
$productTitle = $productData->definition->name;
echo '<h1>'.$productTitle.'</h1>';

//print product blocks
foreach($blocksData as $productBlock) {
    //product definition
    if($productBlock['type'] == 'product_definition') {
        include('block_views/product_definition.php');
        include('block_models/product_definition_model.php');
    }




}

