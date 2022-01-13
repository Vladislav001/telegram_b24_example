<?
// todo не в корне
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] . '/task-bot';

require_once($_SERVER['DOCUMENT_ROOT'] . '/api/bitrix24/common.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/api/telegram/bot.php');

use TelegramBot\ChatBot;

$chatBot = new ChatBot();
$chatBot->init();
$chatBot->onListenWebhookUpdate();