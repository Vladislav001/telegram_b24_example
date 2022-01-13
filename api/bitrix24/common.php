<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/database.php');

class B24Common
{
	private static $NUMBER_ATTEMPTS = 0;
	protected static $TIMEOUT = 60; //seconds
	protected static $applicationOptions = false;

	const TASK_STATUSES = [
		1 => ["VALUE" => 1, "TEXT" => "Новая"],
		2 => ["VALUE" => 2, "TEXT" => "В ожидании"],
		3 => ["VALUE" => 3, "TEXT" => "Выполняется"],
		4 => ["VALUE" => 4, "TEXT" => "Ждет контроля"],
		5 => ["VALUE" => 5, "TEXT" => "Завершена"],
		6 => ["VALUE" => 6, "TEXT" => "Отложена"],
		7 => ["VALUE" => 7, "TEXT" => "Отклонена"],
	];

	protected static function getSettings()
	{
		$settings = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/settings.ini');

		if ($_REQUEST['URL_BITRIX24'] && $_REQUEST['AUTH_ID'] && $_REQUEST['REFRESH_ID'] && $_REQUEST['ID'])
		{
			// если с cron
			$module = $_REQUEST;
		} else
		{
			// если в приложения в б24
			$module = Database::getInstance()->getModuleInformationByDomain($_REQUEST['DOMAIN'], $_REQUEST['member_id']);
		}

		return array(
			"DEBUG_MODE" => $settings['DEBUG_MODE'],
			"URL_BITRIX24" => $module['URL_BITRIX24'],
			"AUTH_ID" => $module['AUTH_ID'],
			"REFRESH_ID" => $module['REFRESH_ID'],
			"CLIENT_ID" => $settings['CLIENT_ID'],
			"CLIENT_SECRET" => $settings['CLIENT_SECRET'],
			"MODULE_ID" => $module['ID'],
			"PROTOCOL" => 'https://',
			"OAUTH_URL" => $settings['OAUTH_URL']
		);
	}

	public static function getApplicationOptions()
	{
		if (!self::$applicationOptions) {
			$data = static::request('app.option.get', 'POST', array());
			self::$applicationOptions = $data['result'];
		}

		return self::$applicationOptions;
	}

	protected static function request($methodBX24, $httpMethod, array $content)
	{
		$settings = static::getSettings();

		if ($settings['URL_BITRIX24'] && $settings['AUTH_ID'])
		{
			$url = $settings['PROTOCOL'] . $settings['URL_BITRIX24'] . '/rest/' . $methodBX24;

			$content['access_token'] = $settings['AUTH_ID'];
			$context = stream_context_create([
				'http' => [
					'method' => $httpMethod,
					'content' => http_build_query($content),
					'header' => 'Content-Type: application/x-www-form-urlencoded',
					'timeout' => self::$TIMEOUT,
				],
			]);

			$response = file_get_contents($url, false, $context);

//			static::log('B24 request', [
//				'method' => $methodBX24,
//				'params' => $content,
//				'response' => $response,
//				'url' => $settings['URL_BITRIX24']
//			]);

			$data = json_decode($response, true);

			// the extension of the access_token and refresh_token if necessary
			if (empty($data))
			{
				if (self::$NUMBER_ATTEMPTS == 0)
				{
					static::refreshOAuth();
					self::$NUMBER_ATTEMPTS++;
					$data = self::request($methodBX24, $httpMethod, $content);
				} elseif (self::$NUMBER_ATTEMPTS < 5)
				{
					// сможет войти сюда после 1го if, и попытаемся еще несколько раз, т.к иногда просто кидает: 503 Service Temporarily Unavailable
					sleep(3);
					self::$NUMBER_ATTEMPTS++;
					$data = self::request($methodBX24, $httpMethod, $content);
				} else
				{
					throw new ErrorException('REST-API request failed with status ' . $http_response_header[0]);
				}
			} else
			{
				self::$NUMBER_ATTEMPTS = 0;
			}

			return $data;
		}

		return array();
	}

	// The extension of the OAuth 2.0 authorization
	protected static function refreshOAuth()
	{
		$settings = static::getSettings();

		if ($settings['CLIENT_ID'] && $settings['CLIENT_SECRET'] && $settings['REFRESH_ID'])
		{
			$content = array(
				'grant_type' => 'refresh_token',
				'client_id' => $settings['CLIENT_ID'],
				'client_secret' => $settings['CLIENT_SECRET'],
				'refresh_token' => $settings['REFRESH_ID']
			);
			$context = stream_context_create([
				'http' => [
					'method' => 'POST',
					'content' => http_build_query($content),
					'header' => 'Content-Type: application/x-www-form-urlencoded',
					'timeout' => self::$TIMEOUT,
				],
			]);

			$response = file_get_contents($settings['OAUTH_URL'], false, $context);
			$data = json_decode($response, true);

			if (!$data['error'] && $data['access_token'])
			{
				$_REQUEST['AUTH_ID'] = $data['access_token']; // для cron
				Database::getInstance()->updateAuthID($settings['MODULE_ID'], $data['access_token']);

				// если понадобится обновлять refresh_token вместе с access_token
//				Database::getInstance()->updateRefreshID($settings['MODULE_ID'], $data['refresh_token']);
//				Database::getInstance()->updateDateRefreshID($settings['MODULE_ID'], date('Y-m-d H:i:s'));
				return true;
			}
		}

		return false;
	}

	/**
	 * Log to file
	 *
	 * @param mixed $data
	 * @param string $name
	 */
	public static function log($name, $data)
	{
		$settings = static::getSettings();

		if ($settings['DEBUG_MODE'])
		{
			$tempFile = fopen(static::getLogFilePath(), 'a');

			if ($tempFile)
			{
				fwrite($tempFile, __METHOD__ . PHP_EOL . '(' . date('Y-m-d H:i:s') . ')' . PHP_EOL . $name . ' = ' . PHP_EOL . print_r($data, true) . PHP_EOL . PHP_EOL);
				fclose($tempFile);
			}
		}
	}

	protected static function getLogFilePath()
	{
		return $_SERVER['DOCUMENT_ROOT'] . '/logs/' . date('Y-m-d') . '.log';
	}
}