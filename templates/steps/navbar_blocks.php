
<div class="icp_flow_navigator_box" style="border-bottom-color:  <?php echo $primaryColor ?> ">

    <?php foreach ($blocksData as $block => $flowBlock) {?>
        <div class="icp_flow_navigator_items_box">
            <div class='<?php echo $flowBlock['class'] ?>' <?php echo $flowBlock['attributes'] ?>>
                <p><strong> <?php echo $flowBlock['label'] ?> </strong></p>
            </div>
            <div id="lds_ellipsis_<?php echo $block ?>" class="lds-ellipsis">
                <div></div>
                <div></div>
                <div></div>
                <div></div>
            </div>
        </div>

    <?php } ?>
</div>
