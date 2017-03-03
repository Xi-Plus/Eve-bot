<?php
date_default_timezone_set("Asia/Taipei");
require(__DIR__.'/config/config.php');
require(__DIR__.'/function/cURL-HTTP-function/curl.php');
require(__DIR__.'/function/MStranslate.php');
require(__DIR__.'/log.php');

if ($C['MStranslate']['on']) {
	$MStranslate = new MStranslate;
}
function SendMessage($uid, $message) {
	global $C;
	$post = array(
		"recipient"=>array("id"=>$uid),
		"message"=>array("text"=>$message)
	);
	cURL_HTTP_Request("https://graph.facebook.com/v2.6/me/messages?access_token=".$C['page_token'],$post);
}
while (true) {
	$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}input` ORDER BY `time` ASC LIMIT 1");
	$res = $sth->execute();
	$data = $sth->fetch(PDO::FETCH_ASSOC);
	if (count($data) == 0) {
		break;
	}
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}input` WHERE `hash` = :hash");
	$sth->bindValue(":hash", $data["hash"]);
	$res = $sth->execute();
	WriteLog("delete queue: hash=".$data["hash"]);
	$input = json_decode($data["input"], true);
	foreach ($input['entry'] as $entry) {
		foreach ($entry['messaging'] as $messaging) {
			$page_id = $messaging['recipient']['id'];
			if ($page_id != $C['page_id']) {
				continue;
			}
			$uid = $messaging['sender']['id'];
			if (!isset($messaging['message']['text'])) {
				SendMessage($uid, "[Server Message][Error] Only supports text.");
				continue;
			}
			$input = $messaging['message']['text'];
			$input = str_replace("\n", "", $input);
			if (!file_exists("data/".$user_id.".json")) {
				$html = cURL_HTTP_Request('http://alice.pandorabots.com/',null,false,'cookie/'.$user_id.'.cookie')->html;
				$html = str_replace(array("\t","\r\n","\r","\n"), "", $html);
				preg_match('/<iframe src="http:\/\/sheepridge\.pandorabots\.com\/pandora\/talk\?botid=(.+?)&skin=custom_input"/', $html, $match);
				$botid = $match[1];
				if ($botid === null) {
					SendMessage($uid, "[Server Message][Error] There were some errors when setting AI. Please try later.");
					continue;
				} else {
					file_put_contents("data/".$user_id.".json", json_encode(array("botid"=>$botid)));
				}
			} else {
				$temp = json_decode(file_get_contents("data/".$user_id.".json"), true);
				$botid = $temp['botid'];
			}
			if (!$C['MStranslate']['on'] && !preg_match("/[A-Za-z0-9]/", $input)) {
				SendMessage($uid, "[Server Message][Error] Your message must include any alphanumeric character.");
				continue;
			}
			if (!$C['MStranslate']['on'] && !preg_match("/^[\x20-\x7E]*$/", $input)) {
				SendMessage($uid, "[Server Message][Error] Only supports ASCII printable code (alphanumeric characters and some English punctuations).");
				continue;
			}
			if ($C['MStranslate']['on']) {
				$input_lang = $MStranslate->getlangcode($input);
			}
			if ($C['MStranslate']['on'] && $input_lang != 'en') {
				if (strlen($input) > $C['MStranslate']['strlen_limit']) {
					SendMessage($uid, $C['MStranslate']['strlen_limit_msg']."\n".$MStranslate->translate("en", $input_lang, $C['MStranslate']['strlen_limit_msg'])." (".$input_lang.")");
					continue;
				}
			}
			if ($C['MStranslate']['on'] && $input_lang != 'en') {
				$input = $MStranslate->translate($input_lang, "en", $input);
				SendMessage($uid, "You said: ".$input." (".$input_lang.")");
			}
			$transname = array("ALICE" => "Eve", "Alice" => "Eve", "alice" => "Eve",
				"EVE" => "ALICE", "Eve" => "Alice", "eve" => "alice");
			$input = strtr($input, $transname);
			$html = cURL_HTTP_Request('http://sheepridge.pandorabots.com/pandora/talk?botid='.$botid.'&skin=custom_input',array('input'=>$input),false,'cookie/'.$user_id.'.cookie');
			if ($html === false) {
				SendMessage($uid, "[Server Message][Error] AI server is down. Please try again later.");
				continue;
			}
			if ($html->header["http_code"] == 502){
				SendMessage($uid, "[Server Message][Error] AI server is down. Please try again later.");
				continue;
			}
			$html = $html->html;
			$html = str_replace(array("\t","\r\n","\r","\n"), "", $html);
			preg_match('/<b>You said:<\/b>.+?<br\/><b>A.L.I.C.E.:<\/b> (.+?)<br\/>/', $html, $match);
			$response = $match[1];
			$response = str_replace("<br> ","\n",$response);
			$response = str_replace("<p></p> ","\n\n",$response);
			$response = strip_tags($response);
			$response = htmlspecialchars_decode($response);
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
			$response = str_replace(array(". ", "? ", "! "), array(".\n", "?\n", "!\n"), $response);
			$responses = explode("\n", $response);
			foreach ($responses as $response) {
				if (trim($response) == "") continue;
				if ($C['MStranslate']['on'] && $input_lang != 'en') {
					$response .= "\n".$MStranslate->translate("en", $input_lang, $response);
				}
				SendMessage($uid, $response);
			}
		}
	}
}
