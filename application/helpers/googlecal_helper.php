<?php defined('BASEPATH') OR exit('No direct script access allowed');

// ===================================================================================================
// please use composer and type "composer require google/apiclient:^2.0"
// client_secret.json generate from http://console.developers.google.com
// save it in helper
// ===================================================================================================

define('APPLICATION_NAME', 'Google Calendar API PHP Quickstart');
define('CREDENTIALS_PATH', APPPATH.'sessions/calendar-php-quickstart.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json');

define('SCOPES', implode(' ', array(
  Google_Service_Calendar::CALENDAR_READONLY)
));


class gCal
{
	private $client;
	private $date;
	public $event, $max, $order, $redirect;

	public function __construct($year=false, $month=false, $redirect=false,$max=10, $order=true){
		// generate date
		$ci = &get_instance();
		$this->max = $max;
		$this->order = $order;
		$this->redirect = $redirect?(strpos($redirect,"://")>0?$redirect:current_url()):current_url();
		// format date 2017-09-12T22:31:15+07:00
		$dt = ($year?($month?date($year."-".sprintf("%0.2d",$month)."-01"):date($year."-m-01")):($month?date("Y-".sprintf("%0.2d",$month)."01"):date("Y-m-01")));
		$this->date = array($dt."T00:00:01+07:00",date("Y-m-d",strtotime($dt." +".(date('t', strtotime($dt))-1)." day"))."T23:59:59+07:00");
		// get client object
		$this->_client();
		$this->event = $this->_run();
	}

	private function _client() {
		$this->client = new Google_Client();
		$this->client->setApplicationName(APPLICATION_NAME);
		$this->client->setScopes(SCOPES);
		$this->client->setAuthConfig(CLIENT_SECRET_PATH);
		$this->client->setAccessType('offline');
		$this->client->setRedirectUri($this->redirect);
		// $this->client->setDeveloperKey('AIzaSyBClBlZDXzkWfCZNAzvspRXtLsfKCOiCbc');
		// $this->client->setPrompt('select_account');

		$authUrl = $this->client->createAuthUrl();
		if(!file_exists(CREDENTIALS_PATH)){
			if(isset($_GET['code'])){
				$authCode = trim($_GET['code']);
				file_put_contents(CREDENTIALS_PATH, json_encode($this->client->authenticate($authCode)));
			}
			else
				redirect($authUrl);
		}

		$accessToken = json_decode(file_get_contents(CREDENTIALS_PATH), true);
		// Load previously authorized credentials from a file.
		if(!isset($accessToken['error'])){
			$this->client->setAccessToken($accessToken);
			// Refresh the token if it's expired.
			if ($this->client->isAccessTokenExpired()) {
				unlink(CREDENTIALS_PATH);
				redirect($authUrl);
			}
		} else{
			unlink(CREDENTIALS_PATH);
			redirect($authUrl);
		}

	}

	private function expandHomeDirectory($path) {
		$homeDirectory = getenv('HOME');
		if (empty($homeDirectory)) {
			$homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
		}
		return str_replace('~', realpath($homeDirectory), $path);
	}

	public function _run(){
		$service = new Google_Service_Calendar($this->client);

		// Print the next 10 events on the user's calendar.
		$calendarId = 'in.indonesian#holiday@group.v.calendar.google.com';
		$optParams = array(
			'maxResults' => $this->max,
			'orderBy' => $this->order?'startTime':'updated',
			'singleEvents' => TRUE,
			'timeMin' => $this->date[0],
			'timeMax' => $this->date[1],
		);
		$results = $service->events->listEvents($calendarId, $optParams);
		$data = array(); 
		if(count(($res = $results->getItems())) > 0){
			foreach ($res as $i => $item) {
				$date = $item->start->dateTime;
				if (empty($date)) {
					$date = $item->start->date;
				}
				$data[] = array('date' => $date, 'event' => $item->getSummary());
			}
		}
		return $data;
	}
}