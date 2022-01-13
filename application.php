<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/database.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/api/bitrix24/common.php');

class B24Application
{
	protected static function getSettings()
	{
        $settings = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/settings.ini');
        return array(
			"DEBUG_MODE" => $settings['DEBUG_MODE'],
		);
	}

	public static function install()
	{
		$errors = [];

		if (!$_REQUEST['DOMAIN'] || !$_REQUEST['AUTH_ID'] || !$_REQUEST['REFRESH_ID'])
		{
			$errors[] = getMessage('ERROR_INSTALLATION_OUTSIDE_BITRIX24');
			return $errors;
		}

		try
		{
			$domainID = Database::getInstance()->getDomainID($_REQUEST['DOMAIN']);

			if (!$domainID)
			{
				// никогда не устанавливали (нет в бд)
				Database::getInstance()->addB24($_REQUEST);
				//B24Common::log(getMessage('MODULE_INSTALLED'), $_REQUEST);
			} else
			{
				// переустановка
				//B24Common::log(getMessage('MODULE_REINSTALLED'), $_REQUEST);
				Database::getInstance()->updateAuthID($domainID, $_REQUEST['AUTH_ID']);
				Database::getInstance()->updateRefreshID($domainID, $_REQUEST['REFRESH_ID']);
				Database::getInstance()->updateDateRefreshID($domainID, date('Y-m-d H:i:s'));
			}

		} catch (Exception $exception)
		{
			$errors[] = $exception->getMessage();
		}

		return $errors;
	}

	public static function run()
	{
		require "templates/index.php";
	}
}