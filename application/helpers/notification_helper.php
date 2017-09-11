<?php defined('BASEPATH') OR exit('No direct script access allowed');

// required for push notification FCM
use sngrl\PhpFirebaseCloudMessaging\Client;
use sngrl\PhpFirebaseCloudMessaging\Message;
use sngrl\PhpFirebaseCloudMessaging\Recipient\Device;
use sngrl\PhpFirebaseCloudMessaging\Notification;

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
		self::$ci = & get_instance();
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
					self::sendError(400, "Make sure your variable. (You: ".implode(", ", $cache).") (Template: ".implode(", ", $var).")");
				else return TRUE;
			} else
				self::sendError(400, "Use (".implode(", ", $type).") in type variable");
		} else
			self::sendError(400,"Type variable must define");
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
			} else if(self::$config->type == "push"){
				self::FCMData();
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
						throw new \Exception("Can't send notification");
					return $response;
				} catch(\Exception $e){
					self::sendError(401, $e->getMessage());
					return array("error" => $e->getMessage());
				}
			}
			// for push notification
			else if(self::$config->type == "push"){
				if(self::$element){
					$message = new Message();
					$message->setPriority('high');
					foreach (self::$config->token as $i => $token) {
						$message->addRecipient(new Device($token));
					}
					$message->setNotification(new Notification(self::$config->subject, self::$config->body));

					try{
						if(!($response = self::$element->send($message)))
							throw new \Exception("Can't send notification");
						return $response;
					} catch(\Exception $e){
						self::sendError(401, $e->getMessage());
						return array("error" => $e->getMessage());
					}
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
		foreach (self::$config->to as $i => $email) {
			self::$data->addTo($email);
		}
		if(count(self::$config->cc) > 0 && is_array(self::$config->cc))
			foreach (self::$config->cc as $i => $email) {
				self::$data->addCc($email);
			}

	}

	public static function FCMData(){
		try{
			self::$element = new Client();
			self::$element->setApiKey(self::$ci->config->item('FCM_APIKEY'));
			if(self::$element->injectGuzzleHttpClient(new \GuzzleHttp\Client()))
				throw new \Exception("Please Check your API KEY");
		} catch(\Exception $e){
			self::sendError(401, $e->getMessage());
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
		self::$json["message"] = "Success send notification";
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
			CURLOPT_HEADER         => false,    // don't return headers
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
				foreach($params as $k => $v){
					$postData .= $k . '='.$v.'&';
				}
				$postData = rtrim($postData, '&');
				$options[CURLOPT_POST] = true;
				$options[CURLOPT_POSTFIELDS] = $postData;
			}
		}

		$curl      = curl_init($url);
		curl_setopt_array( $curl, $options );

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


}