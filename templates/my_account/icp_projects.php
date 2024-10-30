<h3> <?php echo __('Projects', 'imaxel') ?></h3>

<!--display projects list-->
<table class="icp_projects_list shop_table">
	<tr>
		<th><span> <?php echo __('Project', 'imaxel') ?></span></th>
		<th><span> <?php echo __('Last Update', 'imaxel') ?></span></th>
		<th><span> <?php echo __('Order', 'imaxel') ?></span></th>
		<th><span> <?php echo __('Product', 'imaxel') ?></span></th>
		<th><span> <?php echo __('Actions', 'imaxel') ?></span></th>
	</tr>
    <?php foreach ($allProjects as $project) : ?>
        <tr id="project-<?php echo $project->icp==1 ? $project->id : $project->id_project?>" data-icp="<?php echo $project->icp?>">
            <?php
            if($project->icp==1){
            ?>
                <td><?php echo $project->project_name ?: $project->id ?></td>
                <td><?php echo $project->date ?></td>
                <td><?php echo (isset($project->order) ? $project->order["order_id"] : "") ?></td>
                <td><?php echo $project->product_name ?></td>
                <td>
                    <?php echo (!isset($project->order)) ? '<a style="" class="imaxel-btn-icp-edit" title="" href="' . $project->urlProject . '"><i class="fas fa-edit"></i></a>' : '' ?>
                    <?php echo (!isset($project->inCart)) ? '<a id="delete" class="imaxel-btn-delete" style="" title="" href=""><i class="fas fa-trash"></i></a>' : '' ?>
                </td>
            <?php
            }
            else{
            ?>
                <td><p><?php echo $project->id_project ?></p></td>
                <td><p><?php echo $project->date_project ?></p></td>
                <td><p><?php echo (isset($project->order) ? $project->order["order_id"] : "") ?></p></td>
                <td><p><?php echo $project->description_project ?></p></td>
                <td>
                    <?php echo (!isset($project->order)) ? '<a id="edit" class="imaxel-btn-edit" style="" title="" href=""><i class="fas fa-edit"></i></a>' : '' ?>
                    <?php echo (!isset($project->inCart)) ? '<a id="delete" class="imaxel-btn-delete" style="" title="" href=""><i class="fas fa-trash"></i></a>' : '' ?>
                    <?php echo '<a id="duplicate" class="imaxel-btn-duplicate" style="" title="" href=""><i class="fas fa-copy"></i></a>'?>
                </td>
            <?php
            }
            ?>
        </tr>
    <?php endforeach; ?>
</table>
