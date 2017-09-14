<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
* library for all
*/
class libs
{
	public $ci;
	public function __construct(){
		# code...
		$this->ci = & get_instance();
	}

	public function resJson($code=false, $data=false){
		if(!is_int($code) || !$code) $code = 400;
		if(!is_array($data)) $data = array("status" => false, "message" => "Bad data response", "data" => NULL);

		$this->ci->output->set_status_header($code)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($data))
			->_display();
		exit();
	}
}