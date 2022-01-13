<?
// todo не в корне
$_SERVER['DOCUMENT_ROOT'] =  dirname(dirname(__FILE__));

require_once $_SERVER['DOCUMENT_ROOT'] . "/cron/cron.php";

Cron::updateRefreshTokens();