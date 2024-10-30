<?php

namespace Printspot\ICP\Models;

use Printspot\ICP\Models\Model;
use Printspot\ICP\Services\IcpService;

class IcpProductsProjectsComponentsModel extends Model {

	protected $table = 'icp_products_projects_components';
	protected static $components = [];

	public function getByProjectId($projectId) {
		if (!isset(self::$components[$projectId])) {
			self::$components[$projectId] = $this->getRow("
													SELECT id,value, readable_value
													FROM " . $this->getTable() . "
													WHERE project=" . $projectId);
		}

		return self::$components[$projectId];
	}

	/**
	 * update variation data
	 * @param array $variationArray
	 * @param int $projectId
	 */
	public function updateVariation($variationArray,$projectId){
		$variationValue = serialize($variationArray);
		$this->update(['value' => $variationValue],['project'=>$projectId]);
	}

}
