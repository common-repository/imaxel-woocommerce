<?php

namespace Printspot\ICP\Models;

use Printspot\ICP\Models\Model;

class IcpProductsProjectsModel extends Model {

	protected $table = 'icp_products_projects';
	private static $project = [];

	/**
	 * Return icpProject saved statically or get data from query
	 * @param int $projectId
	 * @return mixed
	 */
	public function getById($projectId) {
		if (!isset(self::$project[$projectId])) {
			self::$project[$projectId] = $this->getRow("
			SELECT *
			FROM " . $this->getTable() . "
			WHERE id=" . $projectId);
		}
		return self::$project[$projectId];
	}

	/**
	 * get variation price unserialized
	 * @param int $projectId
	 * @return mixed
	 */
	public function getVariation($projectId){
		$sql = "SELECT variation_price FROM " . $this->getTable() . ' WHERE id = ' .$projectId;
		$variation = $this->getRow($sql);
		return unserialize($variation->variation_price);
	}

	/**
	 * update variation data
	 * @param array $variationArray
	 * @param int $projectId
	 */
	public function updateVariation($variationArray,$projectId){
		$variationPrice = serialize($variationArray);
		$this->update(['variation_price' => $variationPrice],['id'=>$projectId]);
	}

	/**
	 * Update owner for icp project
	 * @param int $projectId
	 * @return int $projectId
	 */
	public function updateUserOwner($projectId) {
		$this->update(['user' => get_current_user_id()], ['id' => $projectId]);
		return $projectId;
	}

	/**
	 * Update 'date' field for icp project (used to check if project is deleted in cart)
	 * @param int $projectId
	 */
	public function updateDateIcpProject($projectId) {
		$updateTime = current_time('mysql');
		$this->update(['date' => $updateTime], ['id' => $projectId]);
	}
}