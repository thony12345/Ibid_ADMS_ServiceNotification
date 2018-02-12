<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
* token push notification
*
* id 		: for user_id
* token 	: from google developer
*/
class Token
{
	public $ci, 
			$last_id = null,
			$now = null,
			$error = null;
	private $table = "PushToken",
			$config = array();

	public function __construct(){
		$this->ci = &get_instance();
		$this->config = (array) $_POST;
		$this->now = date("Y-m-d H:i:s");
	}

	public function update(){
		if($this->_check_var()){
			if($this->_check_user())
				$this->_update();
			else
				$this->_add();
		}

		return $this;
	}

	public function id(){
		return $this->last_id;
	}

	private function _check_var(){
		$var = array('id', 'token');
		foreach ($this->config as $key => $data) {
			if(!in_array($key,$var))
				unset($this->config[$key]);
		}

		if(count($this->config) != count($var))
			$this->error = array('error' => 'Variable not right');

		return !$this->error;
	}

	private function _add(){
		$this->ci->db->insert($this->table, array('user_id' => $this->config['id'], 'token' => $this->config['token'], 'CreateDate' => $this->now));
		$this->last_id = $this->ci->db->insert_id();
	}

	private function _update(){
		$this->ci->db->update($this->table, array('token' => $this->config['token'], 'ModifyDate' => $this->now), array('token_id' => $this->last_id));
	}

	private function _check_user(){
		$q = $this->ci->db->query("SELECT token_id FROM ".$this->table." WHERE user_id = ".$this->config['id']);
		if($q->num_rows() > 0){
			$row = $q->row();
			$this->last_id = $row->token_id;
		}
		return !is_null($this->last_id);
	}
}