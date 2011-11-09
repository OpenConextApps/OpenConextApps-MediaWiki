<?php


class ConextGroup {
	public $_id;
	public $_name;

	/**
	 * Set context in one call; bypass parameterless constructor
	 * @param $id
	 * @param $name
	 */
	function set($id, $name) {
		$this->_id = $id;
		$this->_name = $name;
	}
	
	function __toString() {
		return $this->_id;
	}
}