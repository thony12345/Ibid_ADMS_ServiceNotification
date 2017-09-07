<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see https://codeigniter.com/user_guide/general/urls.html
	 */
	public function __construct(){
		parent::__construct();
	}

	public function index(){
		$this->json_rest(400, array("status" => false, "message" => "You aren't allow to this API", "data" => null));
	}

	public function notification(){
		$data = $this->input->post('data');
		// $data = '{"token":"kosong","type":"email","to":["faujiakbar@gmail.com","faujiakbar@hotmail.co.id"],"cc":"evan_di@yahoo.com","subject":"Test","body":"Body saja karena tidak tahu isinya apa","baca":"saja"}';
		$data = '{"type":"push", "token":["AIzaSyCn1qvXzYEtrN1iLElVIZCBVmoFFD-S6f0"], "subject":"test", "body":"test notification"}';
		$tp = new ADMSNotification($data);
		// $tp::FCMData();
		// $tp::debug();
		$tp::process();
	}

	public function json_rest($code,$data){
		$this->output->set_status_header($code)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($data))
			->_display();
		exit();
	}
}
