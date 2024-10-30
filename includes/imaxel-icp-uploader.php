<?php

//TODO DPI comment this file and remove, next release

use Printspot\ICP\Helpers\Config;
use Printspot\ICP\Services\IcpService;
use Printspot\ICP\Models\IcpProductsProjectsComponentsModel;
use Printspot\ICP\Services\ShopService;

class icpUplaoder extends Printspot_Custom_Products
{


    /**
     * PDF_UPLOADER: validate pdf
     */
    function validatePdfUploader()
    {

        //get data
        $projectID = $_POST['currentProject'];
        $blockID = $_POST['currentBlock'];
        $siteID = $_POST['currentSite'];
        $currentURL = $_POST['currentURL'];
        $productID = $_POST['productID'];
        $wproduct = $_POST['wproduct'];
        $uploadFile = $_FILES['files'];

        //write uplaod files names
        echo '<ul>';
        foreach ($uploadFile['name'] as $fileName) {
            echo '<li>' . $fileName . '</li>';
        }
        echo '</ul>';
        return;
        /*STOP*/

        //validate file extension
        $csv_mimetypes = array(
            'text/csv',
            'text/plain',
            'application/csv',
            'text/comma-separated-values',
            'application/excel',
            'application/vnd.ms-excel',
            'application/vnd.msexcel',
            'text/anytext',
            'application/octet-stream',
            'application/txt',
        );
        if (in_array($_FILES['csv_file']['type'], $csv_mimetypes)) {
            $validFile = true;
        }

        //get product data
        $productData = IcpService::loadProductData($productID, $siteID);

        //send pdf file to Imaxel Service API
        $pdfResults = $this->icpDoPostCreate($uploadFile);
        $pdfKey = $pdfResults['result']->key;
        if ($pdfResults['status'] == 200) {

            //number of pages
            $pdfPages = $pdfResults['result']->descriptor->numPages;

            //current project
            $project = IcpService::getProjectData($projectID);
            foreach ($project['components'] as $data) {
                foreach ($data as $type => $value) {
                    ($type == 'variation') ? $projectData[$type] = $value : $projectData[$type][] = $value;
                }
            }

            //current variation data
            if (!@unserialize($projectData['variation'])) {
                $variationID = $projectData['variation'];
                $variationData = IcpService::getVariationAttributeData($variationID, $siteID, $productID);
            } else {
                $variationData = IcpService::getProjectAttributesData($projectData['variation'], $productData);
            }

            $pdfAttributes = $this->getPdfVariationAttributesData($projectData, $siteID, $productID, $productData, $blockID);

            //get pdf attributes
            foreach ($variationData['attributes'] as $name => $attribute) {
                if ($attribute['type'] == 'pdf_upload') {
                    $pdfAttributes[$name] = $attribute;
                }
            }

            //get current block type
            foreach ($productData->blocks as $block) {
                $blocksData[$block->definition->block_id] = $block->definition;
            }
            $blockType = $blocksData[$blockID]->block_type;

            //validate pdf pages
            if (isset($pdfAttributes['pages'])) {
                switch ($blockType) {

                    //for pdf_upload blocks
                    case 'pdf_upload':
                        if (intval($pdfAttributes['pages']['value']['data']) === $pdfPages) {
                            $pagesValidation = true;
                        } else {
                            $pagesValidation = false;
                        }
                        break;

                    //for design blocks
                    case 'design':
                        if ($pdfAttributes['pages']['value']['data'] !== NULL) {
                            if (intval($pdfAttributes['pages']['value']['data']) === $pdfPages) {
                                $pagesValidation = true;
                            } else {
                                $pagesValidation = false;
                            }
                        } else {
                            $pagesValidation = true;
                        }
                        break;
                }
            }

            //validate pdf size
            if (isset($pdfAttributes['size'])) {
                //attribute size to validate
                if ($pdfAttributes['size']['value']['data'] !== NULL) {
                    $pdfAttributeSize = unserialize($pdfAttributes['size']['value']['data']);
                    //check with pdf real data
                    foreach ($pdfResults['result']->descriptor->pages as $key => $pages) {
                        $realPdfWidth = intval($pages->size->width);
                        $realPdfHeight = intval($pages->size->height);
                        $widthDiff = intval($pdfAttributeSize['width']) - $realPdfWidth;
                        $heightDiff = intval($pdfAttributeSize['height']) - $realPdfHeight;
                        if (((intval($pdfAttributeSize['width']) - $realPdfWidth >= -1) && (intval($pdfAttributeSize['width']) - $realPdfWidth <= 1)) && ((intval($pdfAttributeSize['height']) - $realPdfHeight >= -1) && (intval($pdfAttributeSize['height']) - $realPdfHeight <= 1))) {
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
            }

            //echo response
            if (isset($sizeValidation) || isset($pagesValidation)) {

                if (isset($sizeValidation) && isset($pagesValidation)) {

                    if ($sizeValidation === false || $pagesValidation === false) {
                        if ($sizeValidation === false && $pagesValidation === false) {
                            echo '<p>' . __('Sorry, but your PDF size and pages seems to be incorrect.', 'imaxel') . '</p>';
                        } else if ($sizeValidation === false) {
                            echo '<p>' . __('Sorry, but your PDF size seems to be incorrect.', 'imaxel') . '</p>';
                        } else if ($pagesValidation === false) {
                            echo '<p>' . __('Sorry, but your PDF pages seems to be incorrect.', 'imaxel') . '</p>';
                        }
                    } else {
                        //update project price
                        $blockAttributes = $this->getBlockAttributes($siteID, $productID, $blockID, $productData);

                        /** get page price */
                        if (empty($variationID)) {
                            $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                            $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]);
                        } else {
                            $blockBehaviourKey = array_search('pdf_constraint_behaviour', $blockAttributes['pdf_upload']['value_key']);
                            $blockBehaviour = $blockAttributes['pdf_upload']['value_data'][$blockBehaviourKey];
                            if ($blockBehaviour == 'specific_constraints') {
                                $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                                $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]);
                            } else {
                                $pagePrice = $productData->blocks[0]->variations->$variationID->price_per_page;
                            }
                        }
                        /** */

                        $updateProjectPrice = floatval($pagePrice) * intval($pdfPages);
                        $currentPrice = unserialize($project['price']);
                        $currentPrice[$blockID] = $updateProjectPrice;
                        $priceTotal = 0;
                        foreach ($currentPrice as $block => $blockPrice) {
                            if ($block !== 'total') {
                                $priceTotal = floatval($priceTotal) + floatval($blockPrice);
                            }
                        }
                        $projectPrice = $priceTotal;
                        ?>
                        <script>
                            jQuery(document).ready(function () {
                                var projectPrice = '<?php echo $projectPrice . ' ' . get_woocommerce_currency_symbol(); ?>';
                                jQuery('#projectPrice').html(projectPrice);
                                jQuery('#summaryProjectPrice').html(projectPrice);
                            });
                        </script>
                        <?php
                        if (isset($project['variation'])) {
                            $variationID = $project['variation'];
                        }
                        $displayPDfsummary = $this->displayPDFsummary($productData, $variationID, $pdfResults, $uploadFile, $projectID, $blockID, $siteID, $currentURL, $pdfKey, $productID, $wproduct, $updateProjectPrice);
                    }

                } else if (!isset($sizeValidation) && isset($pagesValidation)) {

                    if ($pagesValidation === false) {
                        echo '<p>' . __('Sorry, but your PDF pages seems to be incorrect.', 'imaxel') . '</p>';
                    } else {
                        //update project price
                        $blockAttributes = $this->getBlockAttributes($siteID, $productID, $blockID, $productData);

                        /** get page price */
                        if (empty($variationID)) {
                            $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                            $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]);
                        } else {
                            $blockBehaviourKey = array_search('pdf_constraint_behaviour', $blockAttributes['pdf_upload']['value_key']);
                            $blockBehaviour = $blockAttributes['pdf_upload']['value_data'][$blockBehaviourKey];
                            if ($blockBehaviour == 'specific_constraints') {
                                $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                                $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]);
                            } else {
                                $pagePrice = $productData->blocks[0]->variations->$variationID->price_per_page;
                            }
                        }
                        /** */
                        $updateProjectPrice = floatval($pagePrice) * intval($pdfPages);
                        $currentPrice = unserialize($project['price']);
                        $projectPrice = floatval($currentPrice['total']) + $updateProjectPrice;
                        ?>
                        <script>
                            jQuery(document).ready(function () {
                                var projectPrice = '<?php echo drawPrice($projectPrice); ?>';
                                jQuery('#projectPrice').html(projectPrice);
                                jQuery('#summaryProjectPrice').html(projectPrice);
                            });
                        </script>
                        <?php
                        if (isset($project['variation'])) {
                            $variationID = $project['variation'];
                        }
                        $displayPDfsummary = $this->displayPDFsummary($productData, $variationID, $pdfResults, $uploadFile, $projectID, $blockID, $siteID, $currentURL, $pdfKey, $productID, $wproduct, $updateProjectPrice);
                    }

                } else if (isset($sizeValidation) && !isset($pagesValidation)) {

                    if ($sizeValidation === false) {
                        echo '<p>' . __('Sorry, but your PDF size seems to be incorrect.', 'imaxel') . '</p>';
                    } else {
                        //update project price
                        $blockAttributes = $this->getBlockAttributes($siteID, $productID, $blockID, $productData);

                        /** get page price */
                        if (empty($variationID)) {
                            $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                            $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]);
                        } else {
                            $blockBehaviourKey = array_search('pdf_constraint_behaviour', $blockAttributes['pdf_upload']['value_key']);
                            $blockBehaviour = $blockAttributes['pdf_upload']['value_data'][$blockBehaviourKey];
                            if ($blockBehaviour == 'specific_constraints') {
                                $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                                $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]);
                            } else {
                                $pagePrice = $productData->blocks[0]->variations->$variationID->price_per_page;
                            }
                        }
                        /** */
                        $pagePrice = $blockAttributes['pdf_upload']['value_data'][$valueKey];
                        $updateProjectPrice = floatval($pagePrice) * intval($pdfPages);
                        $currentPrice = unserialize($project['price']);
                        $projectPrice = floatval($currentPrice['total']) + $updateProjectPrice;
                        ?>
                        <script>
                            jQuery(document).ready(function () {
                                var projectPrice = '<?php echo $projectPrice . ' ' . get_woocommerce_currency_symbol(); ?>';
                                jQuery('#projectPrice').html(projectPrice);
                                jQuery('#summaryProjectPrice').html(projectPrice);
                            });
                        </script>
                        <?php
                        if (isset($project['variation'])) {
                            $variationID = $project['variation'];
                        }
                        $displayPDfsummary = $this->displayPDFsummary($productData, $variationID, $pdfResults, $uploadFile, $projectID, $blockID, $siteID, $currentURL, $pdfKey, $productID, $wproduct, $updateProjectPrice);
                    }
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
     * PDF_UPLOADER: recive pdf values from API
     */
    function icpDoPostCreate($file)
    {
        $token = IcpService::generateToken("pdfs:create");
        if ($token !== null) {
            return $this->pdfstorageCreateFile($file["tmp_name"], $token);
        } else {
            return null;
        }
    }

    /**
     * PDF_UPLOADER: upload pdf file to imaxel API
     */
    function pdfstorageCreateFile($path, $token)
    {
        if (function_exists('curl_file_create')) { // php 5.5+
            $cFile = curl_file_create($path);
        } else { //
            $cFile = '@' . realpath($path);
        }
        $cFile->mime = "application/pdf";

        $post = array(/*'access_token' => $token,*/
            'file_content' => $cFile);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $token"
        ));
        curl_setopt($ch, CURLOPT_URL, "https://services.imaxel.com/apis/pdfstorage/v2/pdfs");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array("status" => $httpcode, "result" => json_decode($result));
    }

    /**
     * PDF_UPLOADER: dislpay pdf thumbnail and continue button
     */
    function displayPDFsummary($productData = '', $variationID = '', $pdfResults, $uploadFile, $projectID, $blockID, $siteID, $currentURL, $pdfKey, $productID, $wproduct, $updateProjectPrice = '')
    {
        //get and set theme color
        $primaryColor = ShopService::getPrimaryColor();
        echo '<style>
                .lds-ring div {border-color: ' . $primaryColor . ' transparent transparent transparent !important;}
                .lds-ellipsis div {background: ' . $primaryColor . '}
            </style>';
        echo '<div class="pdf_summary_box">';

        //print response
        echo '<p>' . __('Great, your PDF has been uploaded!', 'imaxel') . '</p>';
        //pdf file
        if (isset($pdfResults['result']->descriptor->pages[0]->thumbnail_512)) {
            //pdf thumbnail
            echo '<div class="pdf_file_box">';

            echo '<div class="pdf_thumbanil">';
            echo '<img src="' . $pdfResults['result']->descriptor->pages[0]->thumbnail_512 . '">';
            echo '</div>';

            $blockAttributes = $this->getBlockAttributes($siteID, $productID, $blockID, $productData);

            /** get page price */
            if (empty($variationID)) {
                $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]);
            } else {
                $blockBehaviourKey = array_search('pdf_constraint_behaviour', $blockAttributes['pdf_upload']['value_key']);
                $blockBehaviour = $blockAttributes['pdf_upload']['value_data'][$blockBehaviourKey];
                if ($blockBehaviour == 'specific_constraints') {
                    $valueKey = array_search('pdf_constraint_page_price', $blockAttributes['pdf_upload']['value_key']);
                    $pagePrice = floatval($blockAttributes['pdf_upload']['value_data'][$valueKey]);
                } else {
                    $pagePrice = $productData->blocks[0]->variations->$variationID->price_per_page;
                }
            }

            /** */
            $pdfPrice = floatval($pagePrice) * floatval($pdfResults['result']->descriptor->numPages);

            //pdf summary
            echo '<div class="pdf_file_summary">';
            echo '<p><strong>' . __('File name', 'imaxel') . ': </strong>' . $uploadFile['name'] . '</p>';
            //echo '<p><strong>'.__('Real Size','printspot-plugins').': </strong>'.$pdfResults['result']->descriptor->pages[0]->size->width.' / '.$pdfResults['result']->descriptor->pages[0]->size->height.'pt</p>';
            echo '<p><strong>' . __('Pages', 'imaxel') . ': </strong>' . $pdfResults['result']->descriptor->numPages . '</p>';
            if (isset($pdfPrice) && floatval($pdfPrice) !== 0.0) {
                echo '<p><strong>' . __('Pdf Price', 'imaxel') . ': </strong>' . $pdfPrice . ' ' . get_woocommerce_currency_symbol() . '</p>';
                echo '<p><strong>' . __('Project price', 'imaxel') . ': </strong><span id="summaryProjectPrice"></span></p>';
            }
            echo '<div class="button-loader-box">';
			echo '<div class="button" onclick="icppdf.savePdfBlock()" id="savePdfBlock" pdf_name="'.$uploadFile['name'].'" project_id="'.$projectID.'" block_id="'.$blockID.'" site_id="'.$siteID.'" currentURL="'.$currentURL.'" price="'.$updateProjectPrice.'" pdf_id="'.$pdfKey.'" product_id="'.$productID.'"';
			if (!empty($wproduct)) {
                echo ' wproduct="' . $wproduct . '"';
            }
            echo '>' . __('Continue', 'imaxel') . '</div>';
            echo '<div  class="lds-ellipsis"><div></div><div></div><div></div><div></div></div>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
        }
    }

    /**
     * PDF_UPLOADER: save pdf data
     */
    function savePdfUploader()
    {

        //get data
        $projectID = $_POST['currentProject'];
        $blockID = $_POST['currentBlock'];
        $siteID = $_POST['currentSite'];
        $currentURL = $_POST['currentURL'];
        $productID = $_POST['productID'];
        $pdfID = $_POST['pdfID'];
        $pdfName = $_POST['pdfName'];
        $price = $_POST['price'];

        //Update project components
        $projectData = $currentProjectData = IcpService::getProjectData($projectID);
        if (isset($currentProjectData['components'][$blockID])) {
            unset($currentProjectData['components'][$blockID]);
        }
        $currentProjectData['components'][$blockID]['pdf']['key'] = $pdfID;
        $currentProjectData['components'][$blockID]['pdf']['name'] = $pdfName;
        $newProjectData = serialize($currentProjectData['components']);
        IcpService::updateProjectComponent($newProjectData, $projectID, $price, $blockID);

        //get project active variation
        $activeVariation = $projectData['variation'];

        //response new block url
        $newURL = IcpService::getNextBlock($currentURL, $productID, $activeVariation, $blockID, $siteID, $projectID);
        echo $newURL;

        //finish
        wp_die();

    }

    /**
     * PDF_UPLOADER: read pdf file
     */
    function readuploadFile($pdfKey)
    {
        $token = IcpService::generateToken("pdfs:read");
        $curl = curl_init();
        $headers = array(
            'Content-Type: application/json',
            sprintf('Authorization: Bearer %s', $token)
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, "https://services.imaxel.com/apis/pdfstorage/v2/pdfs/" . $pdfKey);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $resultData = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpcode === 200) {
            $result = json_decode($resultData);
            return $result;
        } else {
            return null;
        }
    }
}

// finally instantiate our plugin class and add it to the set of globals
$GLOBALS['icpUplaoder'] = new icpUplaoder();

?>
