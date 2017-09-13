<?php
class MStranslate {
	public $access_token = '';
	function __construct() {
		require(__DIR__.'/../config/config.php');
		require_once(__DIR__.'/cURL-HTTP-function/curl.php');
		$param = array (
			'grant_type'    => 'client_credentials',
			'scope'         => 'http://api.microsofttranslator.com',
			'client_id'     => $C['MStranslate']['client_id'],
			'client_secret' => $C['MStranslate']['client_secret']
		);
		$res = cURL_HTTP_Request('https://datamarket.accesscontrol.windows.net/v2/OAuth2-13',$param)->html;
		$this->access_token = json_decode($res)->access_token;
	}
	function getlangcode($text) {
		$header = array (
			'Authorization: Bearer '.$this->access_token
		);
		$res = cURL_HTTP_Request("http://api.microsofttranslator.com/v2/Http.svc/Detect?text=".urlencode($text), false, $header)->html;
		if ($res === false) {
			return "null";
		}
		$xmlObj = simplexml_load_string($res);
		foreach((array)$xmlObj[0] as $val){
			$languageCode = $val;
		}
		return $languageCode;
	}
	function translate($from, $to, $text) {
		$header = array (
			'Authorization: Bearer '.$this->access_token
		);
		$res = cURL_HTTP_Request('http://api.microsofttranslator.com/v2/Http.svc/Translate?text='.urlencode($text).'&from='.$from.'&to='.$to, false, $header)->html;
		if ($res === false) {
			return "null";
		}
		$xmlObj = simplexml_load_string($res);
		foreach((array)$xmlObj[0] as $val){
			$return = $val;
		}
		return $return;
	}
}
?>