<?php defined('BASEPATH') OR exit('No direct script access allowed');
$ci = & get_instance();
// define template config
define("ITYPECONF", serialize(array("email", "sms", "push")));
define("ICONFIG", serialize(array("type", "token", "to", "cc", "subject", "body", "phone", "user", "pwd", "sender", "msisdn", "message", "description", "schedule", "campaign", "attachment")));
define("IEMAILCONF", serialize(array("type", "to", "cc", "subject", "body", "attachment")));
define("ISMSCONF", serialize(array("type", "user", "pwd", "sender", "msisdn", "message", "description", "schedule", "campaign")));
define("IPUSHCONF", serialize(array("type", "token", "subject", "body")));
define("IEMAILSERVER", $ci->config->item('default_email_service')); // choose one (mandrill, sendgrid)

interface iNotification
{
	public static function _parse($data);
	public static function _validateConfig();
	public static function _validateType();
	public static function _send();
	public static function _rest();


	public static function generate();
	public static function process();
	public static function sendError($code,$description);
}

interface iMandrill
{
	public static function _validateMandrillEmail();
	public static function mandrillData();
}

interface iSendgrid
{
	public static function _validateSendgridEmail();
	public static function sendgridData();
}

interface iFirebase
{
	public static function _validatePush();
	//public static function FCMData();
}