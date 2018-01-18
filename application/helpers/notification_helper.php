<?php defined('BASEPATH') OR exit('No direct script access allowed');

// required for push notification FCM
// use sngrl\PhpFirebaseCloudMessaging\Client;
// use sngrl\PhpFirebaseCloudMessaging\Message;
// use sngrl\PhpFirebaseCloudMessaging\Recipient\Device;
// use sngrl\PhpFirebaseCloudMessaging\Notification;

// require BASEPATH."../vendor/sngrl/php-firebase-cloud-messaging/src/Client.php";
// require BASEPATH."../vendor/sngrl/php-firebase-cloud-messaging/src/Message.php";
// require BASEPATH."../vendor/sngrl/php-firebase-cloud-messaging/src/Recipient/Device.php";
// require BASEPATH."../vendor/sngrl/php-firebase-cloud-messaging/src/Notification.php";

// required mandrill library
require_once APPPATH.'libraries/Mandrill.php';
// required sendgrid library
require BASEPATH."../vendor/sendgrid/sendgrid/lib/SendGrid.php";

class ADMSNotification implements iNotification, iMandrill, iFirebase, iSendgrid
{

	private static $config;
	private static $server;
	public static $error;
	private static $ci;
	private static $json;
	private static $data;
	private static $element;

	public function __construct($data){
		// define variable on load
		self::$server = $_SERVER;
		self::$error = (object) array();
		self::$config = self::_parse($data);
		self::$ci = &get_instance();
		self::$ci->load->library('user_agent');
		self::$json = array("status"=>FALSE, "message" => NULL, "data" => NULL);
		// check config template
		self::_validateConfig();
		// generate data
		self::generate();
	}

	public static function _parse($data){
		return (($r = json_decode($data))?(object)$r:(object)array());
	}

	public static function _validateConfig(){
		$ICONFIG = unserialize(ICONFIG);
		foreach (self::$config as $conf => $val) {
			if(!in_array($conf, $ICONFIG)){
				unset(self::$config->{$conf});
			}
		}
		if(IEMAILSERVER == "mandrill"){
			// check email recepient format for mandrill
			self::_validateMandrillEmail();
		} else if(IEMAILSERVER == "sendgrid"){
			// check email recepient format for mandrill
			self::_validateSendgridEmail();
		}

		// check config to validate type request
		self::_validateType();

		// check config for push notification
		self::_validatePush();
	}

	public static function _validateMandrillEmail(){
		if(isset(self::$config->to) && isset(self::$config->type)){
			if(self::$config->type == "email"){
				// ========================================================================
				// for email to
				// ========================================================================
				// validate for array('test1@email.com','test2@email.com','test3@email.com')
				if(is_array(self::$config->to) && isset(self::$config->to[0]) && !is_array(self::$config->to[0])){
					$cache = self::$config->to;
					self::$config->to = array();
					foreach ($cache as $i => $email) {
						if(!empty($email) && !is_null($email)) self::$config->to[] = array("email" => $email);
					}
				}
				// validate for "test1@email.com,test2@email.com,test3@email.com"
				else if(!is_array(self::$config->to)){
					$data = explode(",", self::$config->to);
					if(count($data) > 1){
						self::$config->to = array();
						foreach ($data as $i => $email) {
							if(!empty($email) && !is_null($email)) self::$config->to[] = array("email" => $email);
						}
					} else{
						// validate for "test1@email.com"
						if(!empty(self::$config->to) && !is_null(self::$config->to))
							self::$config->to = array(array("email" => self::$config->to));
					}
				}
				// ========================================================================
				// for email cc
				// ========================================================================
				// validate for array('test1@email.com','test2@email.com','test3@email.com')
				if(is_array(self::$config->cc) && isset(self::$config->cc[0]) && !is_array(self::$config->cc[0])){
					$cache = self::$config->cc;
					foreach ($cache as $i => $email) {
						if(!empty($email) && !is_null($email)) self::$config->to[] = array("email" => $email, "type" => "cc");
					}
				}
				// validate for "test1@email.com,test2@email.com,test3@email.com"
				else if(!is_array(self::$config->cc)){
					$data = explode(",", self::$config->cc);
					if(count($data) > 1){
						foreach ($data as $i => $email) {
							if(!empty($email) && !is_null($email)) self::$config->to[] = array("email" => $email, "type" => "cc");
						}
					} else{
						// validate for "test1@email.com"
						if(!empty(self::$config->cc) && !is_null(self::$config->cc))
							self::$config->to[] = array("email" => self::$config->cc, "type" => "cc");
					}
				}
			}
		}
	}

	public static function _validateSendgridEmail(){
		if(isset(self::$config->to) && isset(self::$config->type)){
			if(self::$config->type == "email"){
				// ========================================================================
				// for email to
				// ========================================================================
				// validate for "test1@email.com,test2@email.com,test3@email.com"
				if(!is_array(self::$config->to)){
					$data = explode(",", self::$config->to);
					if(count($data) > 1){
						self::$config->to = array();
						foreach ($data as $i => $email) {
							if(!empty($email) && !is_null($email)) self::$config->to[] = $email;
						}
					} else{
						// validate for "test1@email.com"
						if(!empty(self::$config->to) && !is_null(self::$config->to))
							self::$config->to = array(self::$config->to);
					}
				}
				// ========================================================================
				// for email to
				// ========================================================================
				// validate for "test1@email.com,test2@email.com,test3@email.com"
				if(isset(self::$config->cc)){
					if(!is_array(self::$config->cc)){
						$data = explode(",", self::$config->cc);
						if(count($data) > 1){
							self::$config->cc = array();
							foreach ($data as $i => $email) {
								if(!empty($email) && !is_null($email)) self::$config->cc[] = $email;
							}
						} else{
							// validate for "test1@email.com"
							if(!empty(self::$config->cc) && !is_null(self::$config->cc))
								self::$config->cc = array(self::$config->cc);
						}
					}
				}

				// ========================================================================
				// for attachment
				// ========================================================================
				if(isset(self::$config->attachment)){
					if(is_array(self::$config->attachment)){
						$fd = ($win = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'))?APPPATH."..\\temp\\attach\\":APPPATH."../temp/attach/";
						$tmps = array();
						$i=1;
						// for window
						if($win && !is_dir(APPPATH."..\\temp"))
							mkdir(APPPATH."..\\temp",0777);
						if($win && !is_dir(APPPATH."..\\temp\\attach"))
							mkdir(APPPATH."..\\temp",0777);
						if(!$win && !is_dir(APPPATH."../temp"))
							mkdir(APPPATH."..\\temp",0777);
						if(!$win && !is_dir(APPPATH."../temp/attach"))
							mkdir(APPPATH."..\\temp",0777);

						foreach (self::$config->attachment as $name => $base64) {
							$tmpext = explode('.',$name);
							$fn = "(".date("Ymd").")attach_".$i.".".$name;
							$fp = $fd.$fn;
							$file = fopen($fp, 'wb');
							fwrite($file, base64_decode($base64));
							fclose($file);
							$tmps[$fn] = $fp;
							$i++;
						}
						self::$config->attachment = $tmps;
					}
				}
			}
		}
	}

	public static function _validateType(){
		$type = unserialize(ITYPECONF);
		if (isset(self::$config->type)) {
			self::$config->type = strtolower(self::$config->type);
			if(in_array(strtolower(self::$config->type), $type)){
				$var = strtolower(self::$config->type) == "email"?unserialize(IEMAILCONF):(strtolower(self::$config->type) == "sms"?unserialize(ISMSCONF):unserialize(IPUSHCONF));
				$cache = array();
				foreach (self::$config as $conf => $val) {
					if(!in_array($conf, $var)){
						unset(self::$config->{$conf});
					} else
						$cache[] = $conf;
				}
				if(count($cache) !== count($var))
					self::sendError(400, lang('make_sure_type_head').implode(", ", $cache).lang('make_sure_type_foot').implode(", ", $var).")");
				else return TRUE;
			} else
				self::sendError(400, lang('use')." (".implode(", ", $type).") ".lang('in_type_variable'));
		} else
			self::sendError(400, lang('type_must_define'));
		// empty config
		self::$config = array();
		return FALSE;
	}

	public static function _validatePush(){
		if(isset(self::$config->type)){
			if(self::$config->type == "push"){
				// validate for "token1,token2,token3"
				if(!is_array(self::$config->token)){
					$data = explode(",", self::$config->token);
					if(count($data) > 1){
						self::$config->token = array();
						foreach ($data as $i => $token) {
							if(!empty($token) && !is_null($token)) self::$config->token[] = $token;
						}
					} else{
						// validate for "token1"
						if(!empty(self::$config->token) && !is_null(self::$config->token))
							self::$config->token = array(self::$config->token);
					}
				}
			}
		}
	}

	public static function generate(){
		if(isset(self::$config->type)){
			if(self::$config->type == "email"){
				if(IEMAILSERVER == 'mandrill')
					self::mandrillData();
				else if(IEMAILSERVER == 'sendgrid')
					self::sendgridData();
			}

		}
	}

	public static function _send(){
		if(isset(self::$config->type)){
			// for email
			if(self::$config->type == "email"){
				if(IEMAILSERVER == "sendgrid")
					// sendgrid
					$sendgrid = new SendGrid(self::$ci->config->item('sendgrid_username'), self::$ci->config->item('sendgrid_password'));
				else if(IEMAILSERVER == "mandrill")
					// mandrill
					$mandrill = new Mandrill(self::$ci->config->item('mandrill_apikey'));
				try{
					if(!($response = (IEMAILSERVER == "mandrill")?$mandrill->messages->send(self::$data, false):((IEMAILSERVER == "sendgrid")?$sendgrid->send(self::$data):false)))
						throw new \Exception(lang('failed_notif'));
					if(isset(self::$config->attachment)){
						foreach (self::$config->attachment as $key => $file) {
							unlink($file);
						}
					}
					return $response;
				} catch(\Exception $e){
					self::sendError(401, $e->getMessage());
					return array("error" => $e->getMessage());
				}
			}
			// for push notification
			else if(self::$config->type == "push"){
				try{
					if(!($response = self::pushNotification(self::$config->subject,self::$config->body)))
						throw new \Exception(lang('failed_notif'));
					return $response;
				} catch(\Exception $e){
					self::sendError(401, $e->getMessage());
					return array("error" => $e->getMessage());
				}
			}
			// for sms notification
			else if(self::$config->type == "sms"){
				// unset type to clean data
				unset(self::$config->type);

				// send data sms
				self::_curl(self::$ci->config->item('sms_url'), (array) self::$config);
			}
		}
		return NULL;
	}

	public static function mandrillData(){
		self::$data = array(
			"html" => self::$config->body,
			"text" => null,
			"from_email" => self::$ci->config->item('mandrill_email_from'),
			"from_name" => self::$ci->config->item('mandrill_email_name'),
			"subject" => self::$config->subject,
			"to" => self::$config->to, // array(array("email" => "test@email.com")),
			"track_opens" => true,
			"track_clicks" => true,
			// "async" => false,
			// "merge" => true,
			// "merge_language" => "mailchimp",
			"auto_text" => true
		);
	}

	public static function sendgridData(){
		self::$data = new SendGrid\Email();
		self::$data->setFrom(self::$ci->config->item('sendgrid_from'))
			->setFromName(self::$ci->config->item('sendgrid_from_name'))
			->setSubject(self::$config->subject)
			->setHtml(self::$config->body);

		if(isset(self::$config->attachment)){
			if(count(self::$config->attachment) > 0)
				self::$data->setAttachments(self::$config->attachment);
		}

		foreach (self::$config->to as $i => $email) {
			self::$data->addTo($email);
		}
		if(count(self::$config->cc) > 0 && is_array(self::$config->cc))
			foreach (self::$config->cc as $i => $email) {
				self::$data->addCc($email);
			}

	}

	public static function process(){
		// =========================
		// processing
		// =========================
		self::$json['data'] = self::_send();
		// result json
		self::_rest();
		// =========================
	}

	public static function sendError($code=FALSE, $desc=FALSE){
		if(isset(self::$error->{$code}) && $desc)
			self::$error->{$code}[] = $desc;
		else
			self::$error->{$code} = array($desc);
	}

	public static function _rest(){
		$code = 201;
		self::$json["status"] = TRUE;
		self::$json["message"] = lang('success_notif');
		if(count(self::$error) > 0){
			foreach (self::$error as $err_code => $message) {
				$code = $err_code;
				self::$json["status"] = FALSE;
				self::$json["message"] = $message[0];
				break;
			}
		}
		self::$ci->output->set_status_header($code)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode(self::$json))
			->_display();
		exit();
	}

	public static function _curl($url=false, $data=false){
		$options = array(
			CURLOPT_RETURNTRANSFER => true,     // return web page
			CURLOPT_HEADER         => isset(self::$config->header)?self::$config->header:false,    // don't return headers
			CURLOPT_FOLLOWLOCATION => true,     // follow redirects
			CURLOPT_ENCODING       => "",       // handle all encodings
			CURLOPT_USERAGENT      => self::$ci->agent->platform(), // who am i
			CURLOPT_AUTOREFERER    => true,     // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
			CURLOPT_TIMEOUT        => 120,      // timeout on response
			CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
			CURLOPT_SSL_VERIFYPEER => false     // Disabled SSL Cert checks
		);

		if($data){
			if(is_array($data)){
				$postData = '';
				foreach($data as $k => $v){
					if(is_array($v)){
						foreach ($v as $k2 => $v2) {
							$postData .= $k."[".$k2."]=".$v2;
						}
					} else
						$postData .= $k.'='.$v.'&';
				}
				$postData = rtrim($postData, '&');
				$options[CURLOPT_POST] = true;
				$options[CURLOPT_POSTFIELDS] = $postData;
			}
		}

		$curl      = curl_init($url);
		curl_setopt_array( $curl, $options );

		print_r($response); die();
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		return $response;
	}

	public static function debug(){
		echo "<pre>Config :<br>"; print_r(self::$config);
		echo "<br><br>Data :<br>"; print_r(self::$data);
		echo "<br><br>Error :<br>";print_r(self::$error);
		die();
	}

	public static function pushNotification($subject=false, $body=false){
		//FCM API end-point
		$url = 'https://fcm.googleapis.com/fcm/send';
				
		$fields = array();
		$fields['data'] = array('message' => $body,'title' => $subject);
		$fields['registration_ids'] = self::$config->token;
		// $fields['to'] = self::$config->token[0];
		//header with content_type api key
		$headers = array(
		'Content-Type:application/json',
		    'Authorization:key='.self::$ci->config->item('FCM_APIKEY')
		);
		//CURL request to route notification to FCM connection server (provided by Google)			
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
		$result = curl_exec($ch);

		curl_close($ch);
		return $result?json_decode($result):FALSE;
	}


}