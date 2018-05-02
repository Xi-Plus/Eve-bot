<?php
$C['page_id'] = '1483388605304266';

$C['verify_token'] = 'verify_token';

$C['Server_URL'] = "";

$C['MStranslate']['on'] = false;
$C['MStranslate']['client_id'] = 'client_id';
$C['MStranslate']['client_secret'] = 'client_secret';
$C['MStranslate']['strlen_limit'] = 150;
$C['MStranslate']['strlen_limit_msg'] = 'Sorry! Your message too long to translate.';

$C['LogKeep'] = 86400*7;

$C["allowsapi"] = array("cli");

$C["DBhost"] = 'localhost';
$C['DBname'] = 'xiplus_fbpage';
$C['DBuser'] = 'xiplus';
$C['DBpass'] = 'au4a83';
$C['DBTBprefix'] = 'eve_';

$C['page_token'] = 'page_token';

$G["db"] = new PDO ('mysql:host='.$C["DBhost"].';dbname='.$C["DBname"].';charset=utf8', $C["DBuser"], $C["DBpass"]);
