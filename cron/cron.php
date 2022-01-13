<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
require_once($_SERVER['DOCUMENT_ROOT']  . "/database.php");
require_once($_SERVER['DOCUMENT_ROOT']  . '/api/bitrix24/common.php');
require_once($_SERVER['DOCUMENT_ROOT']  . '/api/bitrix24/custom.php');
require_once($_SERVER['DOCUMENT_ROOT']  . '/api/telegram/bot.php');

use TelegramBot\ChatBot;

class Cron
{
	protected static function getSettings()
	{
		$settings = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/settings.ini');

		return array(
			"CLIENT_ID" => $settings['CLIENT_ID'],
			"CLIENT_SECRET" => $settings['CLIENT_SECRET'],
			"OAUTH_URL" => $settings['OAUTH_URL'],
            "DB_TABLE_NAME" => $settings['DB_TABLE_NAME']
		);
	}

	public static function updateRefreshTokens()
	{
		$settings = self::getSettings();
		$modules = Database::getInstance()->getAllModules();

		foreach ($modules as $module)
		{
			$content = array(
				'grant_type' => 'refresh_token',
				'client_id' => $settings['CLIENT_ID'],
				'client_secret' => $settings['CLIENT_SECRET'],
				'refresh_token' => $module['REFRESH_ID']
			);

			$header = array(
				"Content-Type: application/x-www-form-urlencoded",
			);

			$context = stream_context_create([
				'http' => [
					'method' => 'POST',
					'content' => http_build_query($content),
					'header' => implode("\r\n", $header)
				],
			]);

			$response = file_get_contents($settings['OAUTH_URL'], false, $context);
			$data = json_decode($response, true);

			if (!isset($data['error']) && isset($data['refresh_token']))
			{
				Database::getInstance()->updateRefreshID($module['ID'], $data['refresh_token']);
				Database::getInstance()->updateDateRefreshID($module['ID'], date('Y-m-d H:i:s'));
			}
		}
	}

	public static function sendTaskReport()
	{
		$modules = Database::getInstance()->getAllModules();

		$chatBot = new ChatBot();
		$chatBot->init();
		$today = date('Y-m-d');
		$chatBot->sendTaskReport($today, $today, $modules[0]);
	}

	public static function sendPlanTaskReport()
	{
		$modules = Database::getInstance()->getAllModules();

		$chatBot = new ChatBot();
		$chatBot->init();
		$chatBot->sendPlanTaskReport($modules[0]);
	}
}