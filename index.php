<?php
require_once(__DIR__.'/config/config.php');
require_once($config['sql_path']);
require_once($config['curl_path']);
require_once($config['facebook_sdk_path']);

$fb = new Facebook\Facebook([
	'app_id'=>$config['app_id'],
	'app_secret'=>$config['app_secret'],
	'default_access_token'=>$config['access_token'],
	'default_graph_version'=>'v2.5',
]);
$response = $fb->get('/me/accounts')->getDecodedBody();
foreach($response['data'] as $temp){
	if($temp['id'] == $config['page_id']){
		$page_token = $temp['access_token'];
		break;
	}
}
$method = $_SERVER['REQUEST_METHOD'];
if ($method == 'GET' && $_GET['hub_mode'] == 'subscribe' &&  $_GET['hub_verify_token'] == $config['verify_token']) {
	echo $_GET['hub_challenge'];
} else if ($method == 'POST') {
	$inputJSON = file_get_contents('php://input');
	$input = json_decode($inputJSON, true);
	foreach ($input['entry'] as $entry) {
		foreach ($entry['changes'] as $change) {
			$page_id = $change['value']['page_id'];
			$conversation_id = $change['value']['thread_id'];
			$field = $change['field'];
			if ($field == 'conversations') {
				$query = new query;
				$query->dbname = $config['database_name'];
				$query->table = 'conversations_botid';
				$query->where = array('conversation_id',$conversation_id);
				$temp = fetchone(SELECT($query));
				if ($temp == null) {
					$html = cURL_HTTP_Request('http://alice.pandorabots.com/',null,false,'cookie/'.$conversation_id.'.txt')->html;
					$html = str_replace(array("\t","\r\n","\r","\n"), "", $html);
					preg_match('/<iframe src="http:\/\/sheepridge\.pandorabots\.com\/pandora\/talk\?botid=(.+?)&skin=custom_input"/', $html, $match);
					$botid = $match[1];
					$created_time = date("c",0);
				} else {
					$botid = $temp['botid'];
					$created_time = $temp['created_time'];
				}
				$conversations = $fb->get('/'.$conversation_id.'/messages?fields=message,from,created_time',$page_token)->getDecodedBody();
				$query = new query;
				$query->dbname = $config['database_name'];
				$query->table = 'conversations_botid';
				$query->where = array('conversation_id',$conversation_id);
				DELETE($query);
				$query = new query;
				$query->dbname = $config['database_name'];
				$query->table = 'conversations_botid';
				$query->value = array(
					array('conversation_id',$conversation_id),
					array('botid',$botid),
					array('created_time',$conversations['data'][0]['created_time'])
				);
				INSERT($query);
				while (count($conversations['data']) > 0) {
					foreach ($conversations['data'] as $message) {
						if ($message['from']['id'] != $page_id && $message['created_time'] > $created_time) {
							$input = $message['message'];
							$server_message = "";
							$response = "";
							$error = false;
							if ($input == "") {
								$error = true;
								$server_message .= "[Server Message][Error] Only supports text.\n";
							}
							if (preg_match("/\n/", $input)) {
								$server_message .= "[Server Message][Notice] Wrap will be ignored.\n";
							}
							$input = str_replace("\n", "", $input);
							if (!preg_match("/^[\x20-\x7E]*$/", $input)) {
								$error = true;
								$server_message .= "[Server Message][Error] Only supports English words and punctuations.\n";
							}
							if (!$error) {
								$html = cURL_HTTP_Request(
									'http://sheepridge.pandorabots.com/pandora/talk?botid='.$botid.'&skin=custom_input',
									array('input'=>$input),
									false,
									'cookie/'.$conversation_id.'.txt'
								)->html;
								$html = str_replace(array("\t","\r\n","\r","\n"), "", $html);
								$response = substr($html, strrpos($html, 'ALICE:')+8);
							}
							$fb->post('/'.$conversation_id.'/messages',array('message'=>$server_message.$response),$page_token)->getDecodedBody();
							break 2;
						}
					}
					$conversations = $fb->get($conversations['paging']['next'])->getDecodedBody();
				}
			}
		}
	}
}