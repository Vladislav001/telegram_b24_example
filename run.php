<?
// todo не в корне
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] . '/task-bot';

require_once($_SERVER['DOCUMENT_ROOT'] . '/langs.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/application.php');

try {
	B24Application::run();
} catch (Exception $exception) {
	B24Common::exceptionLog($exception);
}