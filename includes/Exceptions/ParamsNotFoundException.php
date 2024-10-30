<?php

namespace Printspot\ICP\Exceptions;

class ParamsNotFoundException extends \Exception {

	public function __construct($field = null, $message = "") {
		if($field){
			$message =  sprintf(__('Parameter %s required not found','imaxel'),$field);
		}else{
			$message = $message ?: __('Parameters required not found','imaxel');
		}
		parent::__construct($message, 20100);
	}

}
