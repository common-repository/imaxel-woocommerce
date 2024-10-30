<?php
//get project data

use Printspot\ICP\Services\IcpService;

$projectID = $_GET['icp_project'];
$blockID = $_GET['block'];
$productID = $_GET['id'];
$siteID = $_GET['site'];
if(isset($_GET['wproduct'])) {
    $wproduct = $_GET['wproduct'];
} else {
    $wproduct = '';
}

//get block name
$blockTitle = $this->getBlockTitle($blockID, $productData);

//get project data
$projectData = IcpService::getProjectData($projectID);
$pdfAttributes = $this->getPdfVariationAttributesData($projectData, $siteID, $productID);

//display pdf uploader

$pdfuploader = $this->displayPDFuploader($projectData, $blockID, $projectID, $siteID, $currentURL, $productID, $pdfAttributes, $productData, $wproduct);

?>
