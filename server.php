<?php
require(__DIR__.'/config/config.php');
if (!in_array(PHP_SAPI, $C["allowsapi"])) {
	exit("No permission");
}

date_default_timezone_set("Asia/Taipei");
require(__DIR__.'/function/cURL-HTTP-function/curl.php');
require(__DIR__.'/function/MStranslate.php');
require(__DIR__.'/function/log.php');

if ($C['MStranslate']['on']) {
	$MStranslate = new MStranslate;
}
function SendMessage($sid, $message) {
	global $C;
	$post = array(
		"recipient"=>array("id"=>$sid),
		"message"=>array("text"=>$message)
	);
	$res = cURL_HTTP_Request("https://graph.facebook.com/v2.6/me/messages?access_token=".$C['page_token'], $post)->html;
	$res = json_decode($res, true);
	if (isset($res["error"])) {
		WriteLog("[smsg][error] res=".json_encode($res)." sid=".$sid." msg=".$message);
		return false;
	}
}
while (true) {
	$sth = $G["db"]->prepare("SELECT * FROM `{$C['DBTBprefix']}input` ORDER BY `time` ASC LIMIT 1");
	$res = $sth->execute();
	$data = $sth->fetch(PDO::FETCH_ASSOC);
	if ($data === false) {
		break;
	}
	$sth = $G["db"]->prepare("DELETE FROM `{$C['DBTBprefix']}input` WHERE `hash` = :hash");
	$sth->bindValue(":hash", $data["hash"]);
	$res = $sth->execute();
	$input = json_decode($data["input"], true);
	foreach ($input['entry'] as $entry) {
		foreach ($entry['messaging'] as $messaging) {
			$sid = $messaging['sender']['id'];
			$cookiepath = __DIR__.'/cookie/'.$sid.'.cookie';

			if (!file_exists($cookiepath)) {
				$res = cURL_HTTP_Request("https://graph.facebook.com/v2.8/{$sid}?access_token={$C['page_token']}")->html;
				WriteLog("[ser][info] newuser sid=".$sid." res=".$res);
				$res = json_decode($res, true);
				$username = $res["first_name"];

				$message = "My name is ".$username;
				$res = cURL_HTTP_Request($C['Server_URL'], array('input'=>$message), false, $cookiepath);
				WriteLog("$sid send $message");
				if ($res === false) {
					SendMessage($sid, "[Server Message][Error] There were some errors when setting AI. Please try later.");
					continue;
				}
				SendMessage($sid, "Nice to meet you.\n".
					"I will call you {$username}.\n".
					"You can type \"My name is ...\" to set your nickname.");
			}

			if (!isset($messaging['message']['text'])) {
				SendMessage($sid, "Please send me plain text message.");
				continue;
			}
			$input = $messaging['message']['text'];
			$input = str_replace("\n", "", $input);
			if ($C['MStranslate']['on']) {
				$input_lang = $MStranslate->getlangcode($input);
			}
			if ($C['MStranslate']['on'] && $input_lang != 'en') {
				if (strlen($input) > $C['MStranslate']['strlen_limit']) {
					SendMessage($sid, $C['MStranslate']['strlen_limit_msg']."\n".$MStranslate->translate("en", $input_lang, $C['MStranslate']['strlen_limit_msg'])." (".$input_lang.")");
					continue;
				}
			}
			if ($C['MStranslate']['on'] && $input_lang != 'en') {
				$input = $MStranslate->translate($input_lang, "en", $input);
				SendMessage($sid, "You said: ".$input." (".$input_lang.")");
			}

			$input = strtolower($input);
			$transname = array("ALICE" => "Eve", "Alice" => "Eve", "alice" => "Eve",
				"EVE" => "ALICE", "Eve" => "Alice", "eve" => "alice",
				"xiplus" => "Dr. Wallace", "Dr. Wallace"=> "xiplus");
			$input = strtr($input, $transname);
			$html = cURL_HTTP_Request($C['Server_URL'],array('input'=>$input), false, $cookiepath);
			if ($html === false) {
				SendMessage($sid, "[Server Message][Error] AI server is down. Please try again later.");
				WriteLog("[ser][error] fetch page 1");
				continue;
			}
			if ($html->header["http_code"] == 502){
				SendMessage($sid, "[Server Message][Error] AI server is down. Please try again later.");
				WriteLog("[ser][error] fetch page http 502");
				continue;
			}
			$html = $html->html;
			$html = str_replace(array("\t","\r\n","\r","\n"), "", $html);
			preg_match('/<font size="2" face="Verdana" color=darkred>(.+?)<\/font>/', $html, $match);
			$response = $match[1];
			$response = str_replace("<br> ","\n",$response);
			$response = str_replace("<p></p> ","\n\n",$response);
			$response = strip_tags($response);
			$response = htmlspecialchars_decode($response);
			$response = str_replace("  "," ",$response);
			if(strpos($response, "he is the author") === false && strpos($response, "He is a famous computer scientist") === false){
				$response = str_replace("Artificial Linguistic Internet Computer Entity","Every day and night, I will be with you",$response);
				$response = str_replace("Dr. Richard S. Wallace","xiplus",$response);
				$response = str_replace("Dr. Wallace","xiplus",$response);
			}
			if(strpos($response, "No I don't think I have been to") === false){
				$response = str_replace("Oakland","Tainan",$response);
				$response = str_replace("California","Taiwan",$response);
				$response = str_replace("Bethlehem","Tainan",$response);
				$response = str_replace("Pennsylvania","Taiwan",$response);
			}
			$response = str_replace("Fake Captain Kirk","Jill",$response);
			$response = str_replace("Jabberwacky, Ultra Hal, JFred, and Suzette","Jill, Domen, VisitorIKC, Brad, and Lacy",$response);
			if(preg_match("/(\d\d : \d\d [AP]M)/", $response, $match)){
				$old_time = $match[1];
				$new_time = date("h : i A");
				$response = str_replace($old_time, $new_time, $response);
			}
			$response = str_replace("drwallace@alicebot.org","huangxuanyuxiplus@gmail.com",$response);
			$response = str_replace("www.pandorabots.com","http://xiplus.twbbs.org/eve/",$response);
			$response = str_replace("Www.AliceBot.Org","http://fb.com/Eve.talker",$response);
			$response = strtr($response, $transname);
			$response = str_replace(array(". ", "? ", "! "), array(".\n", "?\n", "!\n"), $response);
			$response = str_replace(array("Dr.\n", "Ph.D.\n"), array("Dr. ", "Ph.D. "), $response);
			$responses = explode("\n", $response);
			foreach ($responses as $response) {
				if (trim($response) == "") continue;
				if ($C['MStranslate']['on'] && $input_lang != 'en') {
					$response .= "\n".$MStranslate->translate("en", $input_lang, $response);
				}
				SendMessage($sid, $response);
			}
		}
	}
}
