<?php
date_default_timezone_set("Asia/Taipei");
require_once(__DIR__.'/config/config.php');
require_once(__DIR__.'/function/cURL-HTTP-function/curl.php');
require_once(__DIR__.'/function/MStranslate.php');

$method = $_SERVER['REQUEST_METHOD'];
if ($method == 'GET' && $_GET['hub_mode'] == 'subscribe' &&  $_GET['hub_verify_token'] == $cfg['verify_token']) {
	echo $_GET['hub_challenge'];
} else if ($method == 'POST') {
	if ($cfg['MStranslate']['on']) {
		$MStranslate = new MStranslate;
	}
	$inputJSON = file_get_contents('php://input');
	$input = json_decode($inputJSON, true);
	foreach ($input['entry'] as $entry) {
		foreach ($entry['messaging'] as $messaging) {
			$page_id = $messaging['recipient']['id'];
			if ($page_id != $cfg['page_id']) {
				continue;
			}
			$user_id = $messaging['sender']['id'];
			if (!isset($messaging['message'])) {
				continue;
			}
			$input = $messaging['message']['text'];
			$server_message = "";
			$response = "";
			if (!file_exists("data/".$user_id.".json")) {
				$html = cURL_HTTP_Request('http://alice.pandorabots.com/',null,false,'cookie/'.$user_id.'.cookie')->html;
				$html = str_replace(array("\t","\r\n","\r","\n"), "", $html);
				preg_match('/<iframe src="http:\/\/sheepridge\.pandorabots\.com\/pandora\/talk\?botid=(.+?)&skin=custom_input"/', $html, $match);
				$botid = $match[1];
				file_put_contents("data/".$user_id.".json", json_encode(array("botid"=>$botid)));
			} else {
				$temp = json_decode(file_get_contents("data/".$user_id.".json"), true);
				$botid = $temp['botid'];
			}
			$error = false;
			if ($input == "") {
				$error = true;
				$server_message .= "[Server Message][Error] Only supports text.\n";
			}
			$input = str_replace("\n", "", $input);
			if (!$cfg['MStranslate']['on'] && !preg_match("/[A-Za-z0-9]/", $input)) {
				$error = true;
				$server_message .= "[Server Message][Error] Your message must include any alphanumeric character.\n";
			}
			if (!$cfg['MStranslate']['on'] && !preg_match("/^[\x20-\x7E]*$/", $input)) {
				$error = true;
				$server_message .= "[Server Message][Error] Only supports ASCII printable code (alphanumeric characters and some English punctuations).\n";
			}
			if ($cfg['MStranslate']['on']) {
				$input_lang = $MStranslate->getlangcode($input);
				if (!in_array($input_lang, array('en', 'zh-CHT', 'zh-CHS'))) {
					$error = true;
					$server_message .= "[Server Message][Error] Unsupported language.\n";
				}
			}
			if (!$error) {
				$transname = array("ALICE" => "Eve", "Alice" => "Eve", "alice" => "Eve",
					"EVE" => "ALICE", "Eve" => "Alice", "eve" => "alice");
				$input = strtr($input, $transname);
				if ($cfg['MStranslate']['on'] && $input_lang != 'en') {
					$input = $MStranslate->translate($input_lang, "en", $input);
				}
				$html = cURL_HTTP_Request('http://sheepridge.pandorabots.com/pandora/talk?botid='.$botid.'&skin=custom_input',array('input'=>$input),false,'cookie/'.$user_id.'.cookie');
				if($html == false){
					$server_message .= "[Server Message][Error] AI server is down. Please try again later.\n";
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
					$response = str_replace("Jabberwacky, Ultra Hal, JFred, and Suzette","Jill, Domen, VisitorIKC, Brad, and Lacy",$response);
					if(preg_match("/(\d\d : \d\d [AP]M)/", $response, $match)){
						$old_time = $match[1];
						$new_time = date("h : i A");
						$response = str_replace($old_time, $new_time, $response);
					}
					$response = str_replace("drwallace@alicebot.org","huangxuanyuxiplus@gmail.com",$response);
					$response = str_replace("www.pandorabots.com","http://xiplus.twbbs.org/eve/",$response);
					$response = str_replace("Www.AliceBot.Org","http://fb.com/1483388605304266",$response);
					$response = strtr($response, $transname);
				}
				if ($cfg['MStranslate']['on'] && $input_lang != 'en') {
					$response = $MStranslate->translate("en", "zh-CHT", $response);
				}
			}
			$messageData=array(
				"recipient"=>array("id"=>$user_id),
				"message"=>array("text"=>$server_message.$response)
			);
			system('curl -X POST -H "Content-Type: application/json" -d \''.json_encode($messageData,JSON_HEX_APOS|JSON_HEX_QUOT).'\' "https://graph.facebook.com/v2.6/me/messages?access_token='.$page_token.'"');
		}
	}
}
