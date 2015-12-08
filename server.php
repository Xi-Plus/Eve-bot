<?php
date_default_timezone_set("Asia/Taipei");
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
							if (!preg_match("/[A-Za-z0-9]/", $input)) {
								$error = true;
								$server_message .= "[Server Message][Error] Your message must include any alphanumeric character.\n";
							}
							if (!preg_match("/^[\x20-\x7E]*$/", $input)) {
								$error = true;
								$server_message .= "[Server Message][Error] Only supports ASCII printable code (alphanumeric characters and some English punctuations).\n";
							}
							if (!$error) {
								$html = cURL_HTTP_Request('http://sheepridge.pandorabots.com/pandora/talk?botid='.$botid.'&skin=custom_input',array('input'=>$input),false,'cookie/'.$conversation_id.'.txt');
								if($html == false){
									$server_message .= "[Server Message][Error] AI server is down.\n";
								} else {
									$html = $html->html;
									$html = str_replace(array("\t","\r\n","\r","\n"), "", $html);
									preg_match('/<b>You said:<\/b>.+?<br\/><b>A.L.I.C.E.:<\/b> (.+?)<br\/>/', $html, $match);
									$response = $match[1];
									$response = str_replace("<br> ","\n",$response);
									$response = str_replace("<p></p> ","\n\n",$response);
									$response = strip_tags($response);
									$response = str_replace("  "," ",$response);
									if(strpos($response, "he is the author") === false && strpos($response, "He is a famous computer scientist") === false){
										$response = str_replace("ALICE","Eve",$response);
										$response = str_replace("Artificial Linguistic Internet Computer Entity","Every day and night, I will be with you",$response);
										$response = str_replace("Dr. Richard S. Wallace","K.R.T.GIRLS xiplus",$response);
										$response = str_replace("Dr. Wallace","K.R.T.GIRLS xiplus",$response);
									}
									if(strpos($response, "No I don't think I have been to") === false){
										$response = str_replace("Oakland","Tainan",$response);
										$response = str_replace("California","Taiwan",$response);
										$response = str_replace("Bethlehem","Tainan",$response);
										$response = str_replace("Pennsylvania","Taiwan",$response);
									}
									$response = str_replace("Fake Captain Kirk","Leader of Pet, Jill",$response);
									$response = str_replace("Jabberwacky, Ultra Hal, JFred, and Suzette","Jill, Domen, VisitorIKC, and Lacy",$response);
									if(preg_match("/(\d\d : \d\d [AP]M)/", $response, $match)){
										$old_time = $match[1];
										$new_time = date("h : i A");
										$response = str_replace($old_time, $new_time, $response);
									}
									$response = str_replace("drwallace@alicebot.org","huangxuanyuxiplus@gmail.com",$response);
									$response = str_replace("www.pandorabots.com","http://eve-bot.cf",$response);
									$response = str_replace("Www.AliceBot.Org","http://fb.com/1483388605304266",$response);
								}
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
