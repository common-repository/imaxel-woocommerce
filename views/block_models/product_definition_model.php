<?php

//detect print on demand param

use Printspot\ICP\Services\IcpService;

$externalImageUrl = isset($_GET['image_origin']) ? $_GET['image_origin'] : false;

//add empty option to product data attribute values
foreach ( $productData->blocks as $block_id => $block )
{
    if ( $block->definition->block_type == 'product_definition' )
    {
        if ( isset($block->attributes) )
        {
            foreach ( $block->attributes as $attributeID => $attribute )
            {
                $selectedAttrValues[$attributeID] = 'empty';
                $initialSelectedAttrValues[$attributeID] = 'empty';
            }
        }
        else
        {
            $selectedAttrValues = null;
            $initialSelectedAttrValues = null;
        }
    }
}

//check if theres is an existing project
$projectID = $_GET['icp_project'] ?? NULL;
if ( isset($_GET['icp_project']) )
{
    global $wpdb;
    $siteID = $_GET['site'];
    $productID = $_GET['id'];
    $projectsTable = $wpdb->prefix.'icp_products_projects';
    $projectsTableComponents = $wpdb->prefix.'icp_products_projects_components';
    $projectDefinitionData = $wpdb->get_row("SELECT * FROM ".$projectsTable." WHERE id=$projectID");
    $projectDataValue = $wpdb->get_row("SELECT value FROM ".$projectsTableComponents." WHERE project=$projectID");
    $projectData = unserialize($projectDataValue->value);
    foreach($projectData as $block) {
        if(isset($block['variation'])) {
            $projectVariation = $block['variation'];
        }
    }

    //set active variation if project already exists
    if(isset($projectVariation) && $projectVariation !== 0) {
        if(!@unserialize($projectVariation)) {
            if(is_object($projectVariation)) {
                $activeVariation = $projectVariation->variation;
            } else {
                $activeVariation = $projectVariation;
            }
        } else {
            $activeVariation = unserialize($projectVariation);
        }
    } else {
        $activeVariation = '0';
    }

    //get project/variation attributes
    if( $activeVariation !== '0' ) {
        //set preselected attrbiute values id project already exists
        if(!is_array($activeVariation)) {
			$variationAttrbiuteData = IcpService::getVariationAttributeData($activeVariation, $siteID, $productID,$projectID );
        } else {
            $variationAttrbiuteData = IcpService::getProjectAttributesData($activeVariation, $productData);
            $activeVariation = 0;
        }
        foreach($variationAttrbiuteData['attributes'] as $attribute) {
            $selectedAttrValuesArray[$attribute['id']] = $attribute['value']['id'];
        }
        $selectedAttrValues = $selectedAttrValuesArray;
        $initialSelectedAttrValues = $selectedAttrValuesArray;
        $originalProductdata = json_encode($productData);
        $enableContinueButton = 'enabled';

    } else {
        $activeVariation = 0;
        $originalProductdata = json_encode($productData);
        $enableContinueButton = 'enabled';
    }

    //get production time and qty
    $itemsQty = intval($projectDefinitionData->quantity);
    if($projectDefinitionData->production_time !== "0") {
        $productionTime = $projectDefinitionData->production_time;
    } else {
        $productionTime = '';
    }

    //get project prices
    $projectPrice = unserialize($projectDefinitionData->variation_price);

    if($projectPrice) {
        $blockID = $_GET['block'];
		$volumetric =  $projectPrice['volumetric_value'];
        $unitPrice = $projectPrice[$blockID]['unit_price'];
        $pricePerPage = $projectPrice[$blockID]['price_per_page'];
        $pricePerArea = $projectPrice[$blockID]['price_per_area'];
        $totalPrice = floatval($projectPrice[$blockID]['unit_price']) * intval($projectPrice['qty']);
    }

}
else
{
    $activeVariation = 0;
    $projectVariation = NULL;

    //set itemsQty
    if($productData->definition->discount_type == 'fixed_volume_discount') {
        if(isset($productData->discounts->fixed_volume_discounts)) {
            $fixedVolumeDiscounts = (array) $productData->discounts->fixed_volume_discounts;
            unset($fixedVolumeDiscounts['name']);
            $fixedVolumeDiscountsArray = array_values($fixedVolumeDiscounts);
            foreach($fixedVolumeDiscountsArray as $fixedVolumeDiscount) {
                if(isset($fixedVolumeDiscount->default) && $fixedVolumeDiscount->default == 1) {
                    $itemsQty = $fixedVolumeDiscount->quantity;
                }
            }
            if(!isset($itemsQty)) {
                $itemsQty = $fixedVolumeDiscountsArray[0]->quantity;
            }
        } else {
            $itemsQty = 1;
        }
    } else {
        $itemsQty = 1;
    }

    //set productionCharges
    if(isset($productData->productionCharges)) {
        $x = 0;
        foreach($productData->productionCharges as $index => $value) {
            if($index !== 'name') {
                if(@unserialize($value->discount)) {
                    $unserializedCharge = unserialize($value->discount);
                    $productData->productionCharges->$index->discount = $unserializedCharge['discount'];
                    if(isset($unserializedCharge['default']) && $unserializedCharge['default'] == 1) {
                        $productionTime = $value->days;
                    }
                }
                if($x == 0) {
                    $firstIndex = $index;
                }
                $x++;
            }
        }
        if(!isset($productionTime)) {
            $productionTime = $productData->productionCharges->$firstIndex->days;
        }
    } else {
        $productionTime = '';
    }

    $unitPrice = 0;
    $volumetric = 0;
    $pricePerPage = 0;
    $pricePerArea = 0;
    $totalPrice = 0;
    $originalProductdata = json_encode($productData);
    $enableContinueButton = 'disabled';
}
?>

<script>

jQuery(document).ready(function($)
{
    var activeVariationPHP = <?php echo $activeVariation; ?>;

    //set active variation value if project exists
    if(activeVariationPHP > 1) {
        var activeVariation = activeVariationPHP;
    } else {
        var activeVariation = null;
    }
    var projectVariation ='<?php echo $projectVariation; ?>';
    var codeLanguage = '<?php echo get_locale(); ?>';
    var productDataFull = <?php echo $originalProductdata ?>;
    var productData = <?php echo $productDataJson ?>;
    var selectedAttrValue = <?php echo json_encode($selectedAttrValues) ?>;
    var activeBlockFull = productDataFull.blocks.find( block => block.definition.block_type==='product_definition' );
    var blockAttributesFull = activeBlockFull.attributes;
    var activeBlock = productData.blocks.find( block => block.definition.block_type==='product_definition' );
    var blockVariations = activeBlock.variations;
    var blockAttributes = activeBlock.attributes;
    var productDefinition = productData.definition;
    var blockData = productData.blocks;
    var siteOrigin = productData.site_origin;
    var quantity = '<?php echo $itemsQty; ?>';
    var selectedCharge = '<?php echo $productionTime; ?>';
    var unitPrice = '<?php echo $unitPrice; ?>';
    var volumetric = '<?php echo $volumetric ?>';
    var pricePerPage = '<?php echo $pricePerPage; ?>';
    var pricePerArea = '<?php echo $pricePerArea; ?>';
    var totalPrice = '<?php echo $totalPrice; ?>';
    var externalImageUrl = '<?php echo $externalImageUrl; ?>';
    var projectId = '<?php echo $projectID; ?>';

    // translatable activeBlock fields
    if((activeBlock.definition.block_name[codeLanguage] === "") || (activeBlock.definition.block_name[codeLanguage] === "undefined") || (activeBlock.definition.block_name[codeLanguage] === undefined)){ activeBlock.definition.block_name[codeLanguage] = activeBlock.definition.block_name['default'];}
    if((activeBlock.definition.block_title[codeLanguage] === "") || (activeBlock.definition.block_title[codeLanguage] === "undefined") || (activeBlock.definition.block_title[codeLanguage] === undefined)){ activeBlock.definition.block_title[codeLanguage] = activeBlock.definition.block_title['default'];}
    if((activeBlock.definition.block_short_description[codeLanguage] === "") || (activeBlock.definition.block_short_description[codeLanguage] === "undefined") || (activeBlock.definition.block_short_description[codeLanguage] === undefined)){ activeBlock.definition.block_short_description[codeLanguage] = activeBlock.definition.block_short_description['default'];}
    if((activeBlock.definition.block_long_description[codeLanguage] === "") || (activeBlock.definition.block_long_description[codeLanguage] === "undefined") || (activeBlock.definition.block_long_description[codeLanguage] === undefined)){ activeBlock.definition.block_long_description[codeLanguage] = activeBlock.definition.block_long_description['default'];}
    activeBlock.definition.block_name = activeBlock.definition.block_name[codeLanguage];
    activeBlock.definition.block_title = activeBlock.definition.block_title[codeLanguage];
    activeBlock.definition.block_short_description = activeBlock.definition.block_short_description[codeLanguage];
    activeBlock.definition.block_long_description = activeBlock.definition.block_long_description[codeLanguage];
    // translatable product fields

    if((productDefinition.name[codeLanguage] === "") || (productDefinition.name[codeLanguage] === "undefined") || (productDefinition.name[codeLanguage] === undefined)){ productDefinition.name[codeLanguage] = productDefinition.name['default'];}
    if((productDefinition.short_description[codeLanguage] === "") || (productDefinition.short_description[codeLanguage] === "undefined") || (productDefinition.short_description[codeLanguage] === undefined)){ productDefinition.short_description[codeLanguage] = productDefinition.short_description['default'];}
    if((productDefinition.long_description[codeLanguage] === "") || (productDefinition.long_description[codeLanguage] === "undefined") || (productDefinition.long_description[codeLanguage] === undefined)){ productDefinition.long_description[codeLanguage] = productDefinition.long_description['default'];}
    productDefinition.name = productDefinition.name[codeLanguage];
    productDefinition.short_description = productDefinition.short_description[codeLanguage];
    productDefinition.long_description = productDefinition.long_description[codeLanguage];

    // translatables attribute names
    for (let i in blockAttributesFull) {
        if((blockAttributesFull[i].definition.attribute_name[codeLanguage] === undefined) || (blockAttributesFull[i].definition.attribute_name[codeLanguage] === "") || (blockAttributesFull[i].definition.attribute_name[codeLanguage] === "undefined")){ blockAttributesFull[i].definition.attribute_name[codeLanguage] = blockAttributesFull[i].definition.attribute_name['default'];}
        blockAttributesFull[i].definition.attribute_name = blockAttributesFull[i].definition.attribute_name[codeLanguage];
    }

    //translate attribute values
    for (let attr in blockAttributesFull) {
        for (let v in blockAttributesFull[attr].values) {
            if((blockAttributesFull[attr].values[v].attribute_value[codeLanguage] === undefined) || (blockAttributesFull[attr].values[v].attribute_value[codeLanguage] === "") || (blockAttributesFull[attr].values[v].attribute_value[codeLanguage] === "undefined")){ blockAttributesFull[attr].values[v].attribute_value[codeLanguage] = blockAttributesFull[attr].values[v].attribute_value['default'];}
            blockAttributesFull[attr].values[v].value_key = blockAttributesFull[attr].values[v].attribute_value[codeLanguage];
        }
    }

    //discounts
    if(productData.discounts) {
        var discounts = productData.discounts;
        if(productData.definition.discount_type == 'range_volume_discount' || productData.definition.discount_type == null) {
            if(productData.discounts.range_volume_discounts !== undefined && productData.discounts.range_volume_discounts.name !== undefined) {
                if((productData.discounts.range_volume_discounts.name[codeLanguage] === "") || (productData.discounts.range_volume_discounts.name[codeLanguage] === "undefined") || (productData.discounts.range_volume_discounts.name[codeLanguage] === undefined)){ productData.discounts.range_volume_discounts.name[codeLanguage] = productData.discounts.range_volume_discounts.name['default'];}
                    productData.discounts.range_volume_discounts.name = productData.discounts.range_volume_discounts.name[codeLanguage];
                var discountLabel = productData.discounts.range_volume_discounts.name;
            } else {
                var discountLabel = false;
            }
        } else if(productData.definition.discount_type == 'fixed_volume_discount') {
            if(productData.discounts.fixed_volume_discounts !== undefined) {
                if(productData.discounts.fixed_volume_discounts.name !== undefined) {
                    if((productData.discounts.fixed_volume_discounts.name[codeLanguage] === "") || (productData.discounts.fixed_volume_discounts.name[codeLanguage] === "undefined") || (productData.discounts.fixed_volume_discounts.name[codeLanguage] === undefined)){ productData.discounts.fixed_volume_discounts.name[codeLanguage] = productData.discounts.fixed_volume_discounts.name['default'];}
                    productData.discounts.fixed_volume_discounts.name = productData.discounts.fixed_volume_discounts.name[codeLanguage];
                    var discountLabel = productData.discounts.fixed_volume_discounts.name;
                } else {
                    var discountLabel = false;
                }
                delete productData.discounts.fixed_volume_discounts.name;
                var fixedDiscountValues = Object.values(productData.discounts.fixed_volume_discounts);
            } else {
                var discountLabel = false;
                var fixedDiscountValues = false;
            }
        }
    } else {
        var discounts = false;
        var discountsLabel = false;
        var fixedDiscountValues = false;
    }

    //production charges
    if(productData.productionCharges) {

        var productionTime = Object.values(productDataFull.productionCharges);
        var productionCharges = productData.productionCharges;
        if((productionCharges.name[codeLanguage] === "") || (productionCharges.name[codeLanguage] === "undefined") || (productionCharges.name[codeLanguage] === undefined)){ productionCharges.name[codeLanguage] = productionCharges.name['default'];}
        productionCharges.name = productionCharges.name[codeLanguage];
    }

    //continue button
    if(typeof blockAttributes !== 'undefined') {
        var enableContinueButtonPHP = '<?php echo $enableContinueButton; ?>';
        if(enableContinueButtonPHP === 'enabled') {
            var enableContinueButton = true;
        } else {
            var enableContinueButton = false;
        }
    } else {
        var enableContinueButton = true;
    }
    jQuery('.icp-definition-box').show();
    jQuery('.page-loader').hide();

    //VUE component for attributes form
    Vue.component('selectAttribute', {
        props: {
            attributes: {
                type: Object
            },
            selectedAttrValue: {
                type: Object,
                default: () => ({})
            }
        },
        methods: {
            getAttrValue() {
                var selectedAttr = [this.selectedAttrValue]
                this.$emit('update-attr-value', selectedAttr)
            },
            isInt(n) {
                return n % 1 === 0;
            },
            showNumberPage(type,value_key) {
                var pages = value_key
                if (value_key.indexOf("-") !== -1){
                    var arr_pages = value_key.split("-")
                    if(this.isInt(arr_pages[0]) && (arr_pages[0] == arr_pages[1]) && (type == "pdf_upload")){
                        pages =  arr_pages[0]
                    }
                }
                return pages
            }
        },

        template: `
            <div class="selectFormBox">
                <li v-for="(attributeValues, attribute_id) in attributes">
                    <label><strong>{{attributeValues.definition.attribute_name}}</strong></label>
                    <select :name="attribute_id" v-model="selectedAttrValue[attribute_id]" @change="getAttrValue(attribute_id)">
                        <option disabled value="empty"><?php echo __('Select an option','imaxel');?></option>
                        <option v-for="value in attributeValues.values" :value="value.id">
                            {{showNumberPage(attributeValues.definition.attribute_type,value.value_key)}}
                        </option>
                    </select>
                </li>
            </div>
        `,
    })

    //VUE instance for product definition block
    var app = new Vue({
        el: '.icp-definition-box',
        data: {
            variations: blockVariations,
            initialAttributes: blockAttributesFull,
            attributes: blockAttributes,
            selectedAttrValue: selectedAttrValue,
            activeVariation: activeVariation,
            projectVariation: projectVariation,
            productDefinition: productDefinition,
            activeBlock: activeBlock,
            siteOrigin: siteOrigin,
            enableContinueButton: enableContinueButton,
            discounts: discounts,
            discountLabel: discountLabel,
            fixedDiscountValues: fixedDiscountValues,
            productionCharges:productionCharges,
            days: productionTime,
            qty: quantity,
            calculatedQty: quantity,
            sartingQty: quantity,

            projectId: projectId,

            price: unitPrice,
            totalPrice: totalPrice,

			variationVolumetric: volumetric,
            variationPrice: unitPrice,
            variationTotalPrice: totalPrice,
            variationPagePrice: pricePerPage,
            variationAreaPrice: pricePerArea,

            priceDiscount: 0,
            variationPriceDiscount: 0,
            variationPagePriceDiscount: 0,
            variationAreaPriceDiscount: 0,

            priceExtraCharge: 0,
            variationPriceExtraCharge: 0,
            variationPagePriceExtraCharge: 0,
            variationPageAreaExtraCharge: 0,

            activeExtraCharge: 0,
            activeDiscount: 0,

            selectedCharge: selectedCharge,
            lastSelectedCharge: '',
            lastSelectedDiscount: '',
            lastValidRange: '',

            externalImageUrl: externalImageUrl

        },
        methods: {

            udpateAttrValue(selectedAttr) {

                //set enable continue button
                this.enableContinueButton = enableContinueButtonAction(selectedAttr);

                //set active variation and price
                if(typeof this.variations !== 'undefined') {

                    this.activeVariation = getActiveVariations(this.variations, selectedAttr);

                    //SET FIRST VARIATION PRICE======================//
                    if(this.activeVariation) {

                        this.variationPrice = this.variations[this.activeVariation].price;

                        this.variationVolumetric = this.variations[this.activeVariation].volumetric;

                        this.variationPagePrice = 0;

                        this.variationAreaPrice = 0;

                    }

                }

                //UPDATE PRICES
                this.updatePrice();

            },

            updatePrice() {

                //SET DISCOUNTS AND CHARGES======================//

                //set product active type of discunt
                if(this.productDefinition.discount_type !== null) {
                    var discountType = this.productDefinition.discount_type;
                } else {
                    var discountType = 'range_volume_discount';
                }

                //set discounts
                if( (this.discounts !== undefined && this.discounts !== false) && ( (discountType === 'range_volume_discount' && this.discounts.range_volume_discounts !== undefined) || (discountType === 'fixed_volume_discount' && this.discounts.fixed_volume_discounts !== undefined) ) ) {

                    //when range_volume_discount is active of fixed_volume_discount is active but have no values
                    if(discountType === 'range_volume_discount') {
                        var discountsArray = Object.values(this.discounts.range_volume_discounts);
                        var majorLimit = discountsArray.reduce((major, p) => p.max > major ? p.max : major, discountsArray[0].max);
                        var validDiscount = discountsArray.find(discount => (discount.min <= parseInt(this.qty)) && (discount.max >= parseInt(this.qty)));

                    //when fixed_volume_discunt is active
                    } else if (discountType === 'fixed_volume_discount') {
                        var discountsArray = Object.values(this.discounts.fixed_volume_discounts);
                        var validDiscount = discountsArray.find(discount => (discount.quantity == parseInt(this.qty)));
                    }

                    //calculate discounts============================//
                    this.calculateDiscountValues();

                    //set last selected discount
                    this.lastSelectedDiscount = 0;

                    if(validDiscount !== undefined) {

                        //normal products
                        if(this.lastSelectedDiscount == this.priceDiscount) {
                            var productDiscount = 0;
                        } else {
                            var productDiscount = this.priceDiscount;
                        }

                        //variations
                        if(this.activeVariation) {
                            if(this.lastSelectedDiscount == this.variationPriceDiscount) {
                                var variationDiscount = 0;
                                var variationPageDiscount = 0;
                                var variationAreaDiscount = 0;
                            } else {
                                var variationDiscount = this.variationPriceDiscount;
                                var variationPageDiscount = this.variationPagePriceDiscount;
                                var variationAreaDiscount = this.variationAreaPriceDiscount;
                            }
                        }

                    } else {

                        if(this.qty < majorLimit) {

                            var productDiscount = 0;
                            var variationDiscount = 0;
                            var variationPageDiscount = 0;
                            var variationAreaDiscount = 0;

                        } else {

                            var productDiscount = this.priceDiscount;

                            //if variation discounts are not calculed, calculate them giving the last valid range
                            if(this.variationPriceDiscount == 0) {
                                this.calculateDiscountValues(majorLimit);
                            }

                            var variationDiscount = this.variationPriceDiscount;
                            var variationPageDiscount = this.variationPagePriceDiscount;
                            var variationAreaDiscount = this.variationAreaPriceDiscount;

                        }

                    }
                } else  {
                    var productDiscount = 0;
                    var variationDiscount = 0;
                    var variationPageDiscount = 0;
                    var variationAreaDiscount = 0;
                }

                //set extra charges
                if(this.selectedCharge !== 0) {


                    //calculate charges========================//
                    this.calculateExtraCharges();

                    //selected charge counter
                    this.lastSelectedCharge = 0;

                    //normal products
                    if(this.lastSelectedCharge == this.selectedCharge) {
                        var productCharge = 0;
                    } else {
                        var productCharge = this.priceExtraCharge;
                    }

                    //variations
                    if(this.activeVariation) {
                        if(this.lastSelectedCharge == this.selectedCharge) {
                            var productVariationCharge = 0;
                            var productVariationPageCharge = 0;
                            var productVariationAreaCharge = 0;
                        } else {
                            var productVariationCharge = this.variationPriceExtraCharge;
                            var productVariationPageCharge = this.variationPagePriceExtraCharge;
                            var productVariationAreaCharge = this.variationPageAreaExtraCharge;
                        }
                    }

                } else {
                    var productCharge = 0;
                    var productVariationCharge = 0;
                    var productVariationPageCharge = 0;
                    var productVariationAreaCharge = 0;
                }

                //APPLY DISCOUNTS AND CHARGES====================//
                this.price = (parseFloat(this.productDefinition.price) - parseFloat(productDiscount) + parseFloat(productCharge)).toFixed(2);
                if(this.activeVariation) {
                    this.variationAreaPrice = 0;
                    this.variationPrice = (parseFloat(this.variations[this.activeVariation].price) - parseFloat(variationDiscount) + parseFloat(productVariationCharge)).toFixed(2);

                    var variationPricePerPage = 0;
                    this.variationPagePrice = (parseFloat(variationPricePerPage) - parseFloat(variationPageDiscount) + parseFloat(productVariationPageCharge)).toFixed(2);

                    var variationPricePerArea = 0;
                    if(variationPricePerArea !== "0.00"){
                        this.variationAreaPrice = (parseFloat(variationPricePerArea) - parseFloat(variationAreaDiscount) + parseFloat(productVariationAreaCharge)).toFixed(2);
                    }
                }

                //SET TOTAL PRICES==============================//

                //set normal product price
                this.calculatedQty = this.qty;
                this.totalPrice = (this.price * this.qty).toFixed(2);

                //set variation price
                if(this.activeVariation) {
                    this.variationTotalPrice = (this.variationPrice * this.qty).toFixed(2);
                }

            },

            calculateDiscountValues(lastDiscount = false) {
                if(this.discounts !== undefined) {

                    //set product active type of discunt
                    if(this.productDefinition.discount_type !== null) {
                        var discountType = this.productDefinition.discount_type;
                    } else {
                        var discountType = 'range_volume_discount';
                    }

                    //set discount if range_volume_discount is active
                    if(discountType === 'range_volume_discount') {
                        var discountsArray = Object.values(this.discounts.range_volume_discounts);
                        if(lastDiscount == false) {
                            var validDiscount = discountsArray.find(discount => (discount.min <= parseInt(this.qty)) && (discount.max >= parseInt(this.qty)));
                        } else {
                            var validDiscount = discountsArray.find(discount => discount.max == lastDiscount);
                        }

                    //set discount if fixed_volume_discount is active
                    } else if (discountType === 'fixed_volume_discount') {
                        var discountsArray = Object.values(this.discounts.fixed_volume_discounts);
                        var validDiscount = discountsArray.find(discount => (discount.quantity == parseInt(this.qty)));
                    }

                    //apply discount
                    if(validDiscount !== undefined) {
                        this.priceDiscount = discountQtyPercent(this.productDefinition.price, validDiscount.discount);
                        this.activeDiscount = validDiscount.discount;
                        if(this.activeVariation) {

                            this.variationPriceDiscount = discountQtyPercent(this.variations[this.activeVariation].price, validDiscount.discount);

                            var variationPricePerPage = 0;
                            this.variationPagePriceDiscount = discountQtyPercent(variationPricePerPage, validDiscount.discount);

                            var variationPricePerArea = 0;
                            this.variationAreaPriceDiscount = discountQtyPercent(variationPricePerArea, validDiscount.discount);
                        }
                    }
                }
            },

            calculateExtraCharges() {
                if(this.selectedCharge !== 0 && this.selectedCharge !== "") {
                    var charge = this.days.find(day => day.days == this.selectedCharge);
                    this.priceExtraCharge = incrementCharge(this.productDefinition.price, charge.discount);
                    this.activeExtraCharge = charge.discount;
                    if(this.activeVariation) {
                        this.variationPriceExtraCharge = incrementCharge(this.variations[this.activeVariation].price, charge.discount);

                        var variationPricePerPage = 0;
                        this.variationPagePriceExtraCharge = incrementCharge(variationPricePerPage, charge.discount);

                        var variationPricePerArea = 0;
                        this.variationPageAreaExtraCharge = incrementCharge(variationPricePerArea, charge.discount);
                    }
                }
            },

            restartVariation() {
                this.activeVariation = null;
                this.selectedAttrValue = <?php echo json_encode($initialSelectedAttrValues);?>;
                if(this.selectedAttrValue == null) {
                    this.enableContinueButton = false;
                }
            },
        },

        mounted: function() {
            //UPDATE PRICES
            this.updatePrice();
        }
    })

    /**
     * increment charge function
     */
    function incrementCharge(price, charge) {
        return ( parseFloat(price) * parseFloat(charge / 100) ).toFixed(2);
    }

    /**
     * discount quantity percent function
     */
    function discountQtyPercent(price, discount) {
        return ( parseFloat(price) * parseFloat(discount / 100) ).toFixed(2);
    }


    /**
     * chech if all fields have value to enable continue button
     */
    function enableContinueButtonAction(selectedAttributes) {

        var attrArray = {};
        var findEmptyValue;
        selectedAttributes.forEach(function(attr) {
            for(var key in attr) {
                var attrVal = attr[key]
                attrArray[key] = attrVal
            }
        })
        var findEmptyValue = Object.values(attrArray).find(attr => attr === 'empty');
        if(typeof findEmptyValue === 'undefined') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * get active variation for selected attributes
     */
    function getActiveVariations(variations, selectedAttributes) {
        var attrArray = {}
        selectedAttributes.forEach(function(attr) {
            for(var key in attr) {
                var attrVal = attr[key]
                attrArray[key] = attrVal
            }
        })

        var varArray = {};

        Object.keys(variations).forEach(function(variation) {
            varArray[variation] = {}
        })

        Object.values(variations).forEach(function(variation) {
            Object.values(variation.attributes).forEach(function(attribute){
                varArray[attribute.variation][attribute.attribute] = {}
                    varArray[attribute.variation][attribute.attribute] = attribute.value
            })
        })

        var selectedVarId = Object.keys(varArray).find(varID=>isEqual(varArray[varID], attrArray));

        return selectedVarId;
    }

    /**
     * get valid atributes for the form fields
     */
    function getValidAttributes(blockVariations, selectedAttr) {
        var validVariation = {};
        var varAttributeValues = {};
        var activeAttributesArray = {};
        var variationsAttributeValues = {};

        const selectedAttributes = selectedAttr[0];
        const validVariations = Object.keys(selectedAttributes).reduce(
                (variations, attrKey)=>variations.filter( variation => !selectedAttributes[attrKey] ||  variation.attributes[attrKey].value === selectedAttributes[attrKey]),
                Object.values(blockVariations)
            );
        //return if no valid variations are found
        if(validVariations.length == 0) {
            return;
        }
        Object.values(validVariations).forEach(function(varData) {
            Object.keys(varData.attributes).forEach(function(attr_keys) {
                varAttributeValues[attr_keys] = [];
            })
        })

        Object.values(validVariations).forEach(function(varData) {
            Object.values(varData.attributes).forEach(function(attribute) {
                if(varAttributeValues[attribute.attribute].includes(attribute.value) == false) {
                    varAttributeValues[attribute.attribute].push(attribute.value);
                }
            })
        })

        return varAttributeValues;
    }

    /**
     * compare equal between two arrays
     */
    function isEqual(objA, objB) {
        // Create arrays of property names
        var aProps = Object.getOwnPropertyNames(objA);
        var bProps = Object.getOwnPropertyNames(objB);

        // If count of properties is different,
        // objects are not equivalent
        if (aProps.length != bProps.length) {
            return false;
        }

        for (var i = 0; i < aProps.length; i++) {

            var propName = aProps[i];
            // If values of same property are not equal,
            // objects are not equivalent
            if (objA[propName] !== objB[propName]) {
                return false;
            }
        }

        // If we made it this far, objects
        // are considered equivalent
        return true;

    }

}); //document ready closes

</script>

<?php //

