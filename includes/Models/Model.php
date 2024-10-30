<?php

namespace Printspot\ICP\Models;


class Model {

	private static $wpdb;
	private static $prefixes = [];

	protected static $all;

	protected $table;
	protected $originId = null;

	public function __construct($originId = null) {
		$this->originId = $originId;
	}

	public static function origin($originId = null) {
		return new static($originId);
	}

	public static function init() {
		global $wpdb;

		self::$wpdb = $wpdb;
		self::$prefixes[null] = self::$wpdb->prefix;
	}

	public function create($data) {
		self::$wpdb->insert($this->getTable(), $data);
		return self::$wpdb->insert_id;
	}

	public function update($data, $conditions) {
		self::$wpdb->update($this->getTable(), $data, $conditions);
	}

	public function delete($where) {
		self::$wpdb->delete($this->getTable(), $where);
	}

	public function query($sql) {
		self::$wpdb->query($sql);
	}

	public function getBy($filters) {
		$conditions = [];

		foreach ($filters as $field => $value) {
			$conditions[] = "$field = '$value'";
		}


		return $this->getRow("SELECT * FROM " . $this->getTable() . " WHERE " . implode(' AND ', $conditions) . " LIMIT 1");
	}

	public function getAll($filters = null,$orderBy = false) {
		if ($filters !== null) {
			foreach ($filters as $field => $value) {
				$conditions[] = is_array($value) ? "$field IN ('" . implode("', '", $value) . "')" : "$field = '$value'";
			}

			$sql = "SELECT * FROM " . $this->getTable() . " WHERE " . implode(' AND ', $conditions);
			if($orderBy){
				$orderText = '';
				$orderNext = '';
				foreach ($orderBy as $f => $t){
					$orderText .= $orderNext . $f . ' ' .$t;
					$orderNext = ', ';
				}
				$sql .= " ORDER BY " . $orderText;
			}
			return $this->getResults($sql);
		}

		if (static::$all === null) {
			static::$all = $this->getResults("SELECT * FROM " . $this->getTable());
		}

		return static::$all;
	}

	public function getTable($originId = null) {
		$originId = $originId ?: $this->originId;

		if (!isset(self::$prefixes[$originId])) {
			self::$prefixes[$originId] = self::$wpdb->get_blog_prefix($originId);
		}

		return self::$prefixes[$originId] . $this->table;
	}

	protected function getResults($sql) {
		return self::$wpdb->get_results($sql);
	}

	protected function getRow($sql) {
		return self::$wpdb->get_row($sql);
	}

	protected function getVar($sql) {
		return self::$wpdb->get_var($sql);
	}
}

Model::origin()->init();
