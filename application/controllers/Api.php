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
		// generate for library
		$this->libs = new libs;
		// change language
		$this->lang->load('system','indonesia');
		$this->load->helper('googlecal');
	}

	public function index(){
		$this->libs->resJson(400, array("status" => false, "message" => lang('not_allow'), "data" => null));
	}

	public function notification(){
		// get data from request
		$data = $this->input->post('data');
		if(empty($data))
			$data = $this->input->post()?$this->input->post():$this->input->get();
		if(is_array($data)){
			$data = json_encode($data);
		}
		// example for email notification
		//===========================================================================================
		// $data = '{"token":"kosong","type":"email","to":["faujiakbar@gmail.com","faujiakbar@hotmail.co.id"],"cc":"evan_di@yahoo.com","subject":"Test","body":"Body saja karena tidak tahu isinya apa","baca":"saja"}';

		// example for push notification
		//===========================================================================================
		// $data = '{"type":"push", "token":["AIzaSyCn1qvXzYEtrN1iLElVIZCBVmoFFD-S6f0"], "subject":"test", "body":"test notification"}';

		// example for sms notification
		//===========================================================================================
		// $data = '{"type":"sms", "user":"user", "pwd":"password", "sender":"sender_id", "msisdn":"msisdn number", "message":"message push notification", "description":"Can NULL", "schedule":"date in urlencode", "campaign":"campaign type"}';

		// generate notification
		$tp = new ADMSNotification($data);
		// $tp::FCMData();
		// $tp::debug();
		$tp::process();
	}

	public function holidayCalendar(){
		// definition
		$gCal = new gCal(2017,9,"http://notification.dev/api/holidayCalendar");
		// get result
		echo json_encode($gCal->event);
	}
}
