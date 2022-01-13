<?

namespace TelegramBot;

require_once($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/api/bitrix24/common.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/api/bitrix24/custom.php');
require_once($_SERVER['DOCUMENT_ROOT'] . "/database.php");

use Database;
use B24Common;
use B24Custom;
use Telegram\Bot\Api;

/**
 * @lince_task_bot - адрес бота
 *
 * Class ChatBot
 * @package TelegramBot
 */
class ChatBot
{
	const API_TOKEN = '';
	const COMMANDS = [
		"plan_today" => "План на сегодня",
		"report_today" => "Отчёт за сегодня",
		"report_yesterday" => "Отчёт за вчера",
		"total_fact_today" => "Итого сегодня",
		"total_fact_yesterday" => "Итого вчера",
		"total_fact_week" => "Итого за неделю",
		"total_fact_last_week" => "Итого за прошлую неделю",
		"total_fact_month" => "Итого за месяц",
		"subscribe" => "Подписаться на рассылку",
		"unsubscribe" => "Отписаться от рассылки",
	];
	const MESSAGE_LENGTH = 4096;
	const SEPARATOR = '------------------';

	const FILE_SUBSCRIBES = "subscribers.json";
	const FILE_CONFIRMED_SUBSCRIBES = "confirmed_subscribers.json";
	const FILE_WORKING_WEBHOOK_IDS = "working_webhook_ids.json";

	private $listSubscribes;
	private $listConfirmedSubscribes;
	private $telegramApi;
	private $workingWebhookIDs;

	/**Webhook
	 * Инициализация класса телеграм апи
	 */
	public function init()
	{
		//Устанавливаем токен, полученный у BotFather
		$this->telegramApi = new Api(self::API_TOKEN);

		$fileSubscribers = $_SERVER['DOCUMENT_ROOT'] . '/api/telegram/' . self::FILE_SUBSCRIBES;
		$fileConfirmedSubscribers = $_SERVER['DOCUMENT_ROOT'] . '/api/telegram/' . self::FILE_CONFIRMED_SUBSCRIBES;
		$fileWorkingWebhookIDs = $_SERVER['DOCUMENT_ROOT'] . '/api/telegram/' . self::FILE_WORKING_WEBHOOK_IDS;

		if (is_file($fileSubscribers))
		{
			$json = file_get_contents($fileSubscribers);
			$subscribersData = json_decode($json, 1);
			$this->listSubscribes = $subscribersData;
			foreach ($subscribersData as $subscriber)
			{
				$this->listSubscribeIDs[] = $subscriber['chat_id'];
			}
		} else
		{
			$this->listSubscribes = [];
			file_put_contents($fileSubscribers, json_encode($this->listSubscribes));
		}

		if (is_file($fileConfirmedSubscribers))
		{
			$json = file_get_contents($fileConfirmedSubscribers);
			$this->listConfirmedSubscribes = json_decode($json, 1);
		} else
		{
			$this->listConfirmedSubscribes = [];
			file_put_contents($fileConfirmedSubscribers, json_encode($this->listConfirmedSubscribes));
		}

		if (is_file($fileWorkingWebhookIDs))
		{
			$json = file_get_contents($fileWorkingWebhookIDs);
			$this->workingWebhookIDs = json_decode($json, 1);
		} else
		{
			$this->workingWebhookIDs = [];
			file_put_contents($fileWorkingWebhookIDs, json_encode($this->workingWebhookIDs));
		}
	}

	/**
	 * Получить доступые на данный момент команды
	 *
	 * @param $chat_id
	 *
	 * @return array
	 */
	private function getCommands($chat_id)
	{
		$key = $this->getKeySubscribes($chat_id);
		$commands = self::COMMANDS;

		if ($key !== false)
		{
			unset($commands["subscribe"]);
		} else
		{
			unset($commands["unsubscribe"]);
		}

		return $commands;
	}

	public function getKeySubscribes($chat_id)
	{
		$keyExists = false;
		foreach ($this->listSubscribes as $key => $itemSubscribe)
		{
			if ($itemSubscribe["chat_id"] == $chat_id)
			{
				$keyExists = $key;
			}
		}

		return $keyExists;
	}

	/**
	 * Слушатель вебхука
	 */
	public function onListenWebhookUpdate()
	{
		//Передаем в переменную $result полную информацию о сообщении пользователя
		$result = $this->telegramApi->getWebhookUpdates();

		if (!empty($result["message"]))
		{
			$this->triggerWebhook($result);
		}
	}

	/**
	 * Тригер на сработанный вебхук
	 *
	 * @param $result
	 *
	 * @throws \Exception
	 */
	private function triggerWebhook($result)
	{
		// т.к долго ждет ответ иногда, то телега посылает повторно вебхуки и там запускаются обработки новые -> контролируем момент этот
		if (in_array($result['update_id'], $this->workingWebhookIDs))
		{
			return;
		}
		$this->workingWebhookIDs[$result['update_id']] = $result['update_id'];
		file_put_contents(self::FILE_WORKING_WEBHOOK_IDS, json_encode($this->workingWebhookIDs));

		//Текст сообщения
		$text = $result["message"]["text"];
		//Уникальный идентификатор пользователя
		$chat_id = $result["message"]["chat"]["id"];
		//Юзернейм пользователя
		$name = $result["message"]["from"]["username"];

		// проверка, что не начальные команды + подписан и подтвержден
		if ($text != "/start" && $text != self::COMMANDS['unsubscribe'] && $text != self::COMMANDS['subscribe'] && (!in_array($chat_id, $this->listSubscribeIDs) || !in_array($chat_id, $this->listConfirmedSubscribes)))
		{
			$this->telegramApi->sendMessage([
				'chat_id' => $chat_id,
				'text' => "Для вас недоступен функционал"
			]);

			// удалить id работающего вебхука
			unset($this->workingWebhookIDs[$result['update_id']]);
			file_put_contents(self::FILE_WORKING_WEBHOOK_IDS, json_encode($this->workingWebhookIDs));

			return;
		}

		if ($text)
		{
			switch ($text)
			{
				case "/start":
					$reply = "Добро пожаловать в бота Lince: отчет по задачам!\n";
					$reply .= "Вы были подписаны на ежедневное получение отчётов.";

					$this->subscribe($chat_id, $name, false);
					$this->sendMessage($chat_id, $reply);
					break;
				case self::COMMANDS['report_today']:
					$this->sendTaskReport(date('Y-m-d'), date('Y-m-d'), false, $chat_id);
					break;
				case self::COMMANDS['plan_today']:
					$this->sendPlanTaskReport(false, $chat_id);
					break;
				case self::COMMANDS['total_fact_today']:
					$this->sendTaskReport(date('Y-m-d'), date('Y-m-d'), false, $chat_id, false, false);
					break;
				case self::COMMANDS['subscribe']:
					$this->subscribe($chat_id, $name);
					break;
				case self::COMMANDS['unsubscribe']:
					$this->unsubscribe($chat_id);
					break;
				default:
					$reply = "По запросу \"<b>" . $text . "</b>\" ничего не найдено.";
					$this->sendMessage($chat_id, $reply);
					break;
			}
		} else
		{
			$this->telegramApi->sendMessage([
				'chat_id' => $chat_id,
				'text' => "Отправьте текстовое сообщение."
			]);
		}

		// удалить id работающего вебхука
		unset($this->workingWebhookIDs[$result['update_id']]);
		file_put_contents(self::FILE_WORKING_WEBHOOK_IDS, json_encode($this->workingWebhookIDs));
	}

	/**
	 * Подписаться на рассылку
	 * @param $chat_id
	 * @param $name
	 */
	private function subscribe($chat_id, $name, $isMsg = true)
	{
		$keyExists = $this->getKeySubscribes($chat_id);

		if ($keyExists === false)
		{
			$this->listSubscribes[] = [
				"chat_id" => $chat_id,
				"name" => $name
			];
			file_put_contents(self::FILE_SUBSCRIBES, json_encode($this->listSubscribes));

			$reply = "Вы были подписаны на рассылку ежедневных отчётов.";
		} else
		{
			$reply = "Вы уже подписаны на рассылку.";
		}

		if ($isMsg)
		{
			$this->sendMessage($chat_id, $reply);
		}
	}

	/**
	 * Отписаться от рассылки
	 *
	 * @param $chat_id
	 * @param bool $isMsg
	 */
	private function unsubscribe($chat_id, $isMsg = true)
	{
		$keyRemove = $this->getKeySubscribes($chat_id);

		if ($keyRemove !== false)
		{
			unset($this->listSubscribes[$keyRemove]);
			$this->listSubscribes = array_values($this->listSubscribes);
			file_put_contents(self::FILE_SUBSCRIBES, json_encode($this->listSubscribes));

			$reply = "Вы были отписаны от рассылки.";
		} else
		{
			$reply = "Вы не были подписаны на рассылку.";
		}

		if ($isMsg)
		{
			$this->sendMessage($chat_id, $reply);
		}
	}

	public function sendMessage($chatId, $reply)
	{
		$commands = $this->getCommands($chatId);

		$replyMarkup = $this->telegramApi->replyKeyboardMarkup([
			'keyboard' => [
				[
					$commands['plan_today'],
					$commands['report_today'],
					$commands['report_yesterday']
				],
				[
					$commands['total_fact_today'],
					$commands['total_fact_yesterday'],
					$commands['total_fact_week'],
					$commands['total_fact_last_week'],
					$commands['total_fact_month']
				],
				[
					$commands['subscribe'] ? $commands['subscribe'] : $commands['unsubscribe']
				]
			],
			'resize_keyboard' => true,
			'one_time_keyboard' => false
		]);

		$this->telegramApi->sendMessage([
			'chat_id' => $chatId,
			'text' => $reply,
			'parse_mode' => 'HTML',
			'reply_markup' => $replyMarkup
		]);
	}

	/**
	 * Отправить отчет о задачах за день (на которые тратили время)
	 *
	 * @param $startDate
	 * @param $endDate
	 * @param bool $module
	 * @param bool $chat_id
	 * @param bool $showPlannedTime
	 * @param bool $byTasks
	 * @throws \ErrorException
	 */
	public function sendTaskReport($startDate, $endDate, $module = false, $chat_id = false, $showPlannedTime = true, $byTasks = true)
	{
		// для прокида в настройки
		if ($module)
		{
			$_REQUEST = $module; // случай для cron
		} else
		{
			$modules = Database::getInstance()->getAllModules(); // случай для чата
			$_REQUEST = $modules[0];
		}

		// получать отчеты подопечных под каждого юзера
		$appOptions = B24Custom::getApplicationOptions();
		$optionUserReports = $appOptions['USER_REPORTS'];
		$responsibleIds = array();

		if ($chat_id)
		{
			// ответ
			foreach ($optionUserReports as $userData)
			{
				if ($chat_id == $userData['CHAT_ID'])
				{
					$responsibleIds = $userData['SELECTED_USERS'];
					break;
				}
			}

			$taskInfo = self::getTaskInfoForReport($startDate, $endDate, $responsibleIds);

			$textForBot = self::createReportTaskText($startDate, $endDate, $taskInfo, $responsibleIds, $showPlannedTime, $byTasks);
			$this->sendMessageWithCheckAvailable($textForBot, $chat_id);
		} else
		{
			// cron
			$listSubscribes = $this->listSubscribes;
			$listConfirmedSubscribes = $this->listConfirmedSubscribes;

			foreach ($listSubscribes as $subscribe)
			{
				foreach ($optionUserReports as $userData)
				{
					if ($subscribe['chat_id'] == $userData['CHAT_ID'] && in_array($subscribe['chat_id'], $listConfirmedSubscribes))
					{
						$responsibleIds = array_merge($responsibleIds, $userData['SELECTED_USERS']);
						break;
					}
				}
			}

			$responsibleIds = array_unique($responsibleIds);

			// взять инфу по задачам для всех необходимых юзеров Б24
			$taskInfo = self::getTaskInfoForReport($startDate, $endDate, $responsibleIds);

			// отправить инфу о нужных озерах Б24 нужным юзерам телеги
			foreach ($listSubscribes as $subscribe)
			{
				foreach ($optionUserReports as $userData)
				{
					if ($subscribe['chat_id'] == $userData['CHAT_ID'] && in_array($subscribe['chat_id'], $listConfirmedSubscribes))
					{
						$userIDs = array_intersect($responsibleIds, $userData['SELECTED_USERS']);
						$textForBot = self::createReportTaskText($startDate, $endDate, $taskInfo, $userIDs, $showPlannedTime, $byTasks);
						$this->sendMessageWithCheckAvailable($textForBot, $subscribe['chat_id']);
					}
				}
			}
		}
	}

	/**
	 * Сформировать инфу по задачам, которая будет юзаться при создании текстового отчета
	 *
	 * @param $startDate
	 * @param $endDate
	 * @param $responsibleIds
	 * @return array
	 * @throws \ErrorException
	 */
	protected static function getTaskInfoForReport($startDate, $endDate, $responsibleIds)
	{
		// выбрать несуществующего
		if (!$responsibleIds)
		{
			$responsibleIds = [0];
		}

		$usersTaskTimes = array();

		$startDate = date('Y-m-d', strtotime($startDate));
		$endDate = date('Y-m-d', strtotime($endDate));

		$rangeDatesStrTime = array(
			">=CHANGED_DATE" => strtotime($startDate . "T00:00:00+03:00"),
			"<=CHANGED_DATE" => strtotime($endDate . "T23:59:59+03:00"),
		);

		// получить задачи, измененные за даты (CHANGED_DATE не менятеся, если просто время изменили)
		$tasksChangedDB = B24Custom::getAllTasks(array(
			">=CHANGED_DATE" => $startDate . "T00:00:00+03:00",
			"<=CHANGED_DATE" => $endDate . "T23:59:59+03:00",
			"RESPONSIBLE_ID" => $responsibleIds
		), array(
			"ID",
			"TITLE",
			"GROUP_ID",
			"TIME_ESTIMATE",
			"STATUS",
		), array("ID" => "ASC"));

		$tasksNotCompletedDB = B24Custom::getAllTasks(array(
			"!REAL_STATUS" => B24Common::TASK_STATUSES[5]["VALUE"],
			// *чтобы не выгрузить все завершенные
			"RESPONSIBLE_ID" => $responsibleIds
		), array(
			"ID",
			"TITLE",
			"GROUP_ID",
			"TIME_ESTIMATE",
			"STATUS",
		), array("ID" => "ASC"));

		$tasks = array();

		// сформируем по измененным
		foreach ($tasksChangedDB as $key => $value)
		{
			$tasks[$value['id']] = $value;
		}

		// докинем не завершенные (без дублей с измененными)
		foreach ($tasksNotCompletedDB as $key => $value)
		{
			if (!isset($tasks[$value['id']]))
			{
				$tasks[$value['id']] = $value;
			}
		}

		// собрать затраченное время по юзерам и задачам
		foreach ($tasks as $task)
		{
			$taskTimes = B24Custom::getTasksSpentTimeByTaskId($task['id']);

			if ($taskTimes)
			{
				foreach ($taskTimes as $taskTime)
				{
					$taskTimeStrTime = strtotime($taskTime['CREATED_DATE']);

					if ($taskTimeStrTime >= $rangeDatesStrTime['>=CHANGED_DATE'] && $taskTimeStrTime <= $rangeDatesStrTime['<=CHANGED_DATE'])
					{
						$usersTaskTimes[$taskTime['USER_ID']][$taskTime['TASK_ID']] += $taskTime['MINUTES'];
					}
				}
			}
		}

		// собрать инфу по юзерам
		$usersDB = B24Custom::getAllUsers(array("ACTIVE" => "Y"));

		$users = array();
		foreach ($usersDB as $key => $value)
		{
			$users[$value['ID']] = $value;
		}

		// сформировать данные для бота
		$dataBot = array();

		foreach ($usersTaskTimes as $userID => $userTasks)
		{
			$dataBot[$userID] = array(
				"ID" => $userID,
				"NAME" => $users[$userID]['LAST_NAME'] . " " . $users[$userID]['NAME'],
				"TOTAL_SPENT_TIME" => array(
					"HOURS" => 0,
					"MINUTES" => 0
				),
				"TOTAL_ESTIMATE_TIME" => array(
					"HOURS" => 0,
					"MINUTES" => 0
				),
				"TASKS" => array()
			);

			foreach ($userTasks as $taskID => $taskTime)
			{
				$dataBot[$userID]["TASKS"][$taskID] = array(
					"NAME" => $tasks[$taskID]['title'],
					"LINK" => "https://" . $_REQUEST['URL_BITRIX24'] . "/workgroups/group/" . $tasks[$taskID]['groupId'] . "/tasks/task/view/" . $taskID . "/",
					"STATUS_TEXT" => B24Common::TASK_STATUSES[$tasks[$taskID]['status']]['TEXT'],
					"SPENT_TIME" => array(
						"HOURS" => 0,
						"MINUTES" => 0
					),
					"ESTIMATE_TIME" => array(
						"HOURS" => 0,
						"MINUTES" => 0
					)
				);

				// ПЛАНИРУЕМОЕ ЗАТРАЧЕННОЕ ВРЕМЯ START //
				$estimateHours = 0;
				$estimateMinutes = 0;
				$estimateSeconds = $tasks[$taskID]['timeEstimate'];

				// переведем в минуты
				if ($estimateSeconds)
				{
					$estimateMinutes = self::translateTime($estimateSeconds, 'sec', 'min');

					// переведем в часы
					if ($estimateMinutes >= 60)
					{
						$estimateHours = self::translateTime($estimateMinutes, 'min', 'hour');
						$estimateMinutes = $estimateMinutes % 60;
					}

					if ($estimateHours)
					{
						$dataBot[$userID]["TASKS"][$taskID]["ESTIMATE_TIME"]["HOURS"] = $estimateHours;
						$dataBot[$userID]['TOTAL_ESTIMATE_TIME']['HOURS'] += $estimateHours;
					}
				}

				$dataBot[$userID]["TASKS"][$taskID]["ESTIMATE_TIME"]["MINUTES"] = $estimateMinutes;
				$dataBot[$userID]['TOTAL_ESTIMATE_TIME']['MINUTES'] += $estimateMinutes;
				// ПЛАНИРУЕМОЕ ЗАТРАЧЕННОЕ ВРЕМЯ END //

				// ФАКТИЧЕСКИ ЗАТРАЧЕННОЕ ВРЕМЯ START //
				$spentHours = 0;
				$spentMinutes = $taskTime;

				// переведем в часы
				if ($taskTime >= 60)
				{
					$spentHours = self::translateTime($spentMinutes, 'min', 'hour');
					$spentMinutes = $taskTime % 60;
				}

				$dataBot[$userID]["TASKS"][$taskID]["SPENT_TIME"]["MINUTES"] = $spentMinutes;

				if ($spentHours)
				{
					$dataBot[$userID]["TASKS"][$taskID]["SPENT_TIME"]["HOURS"] = $spentHours;
					$dataBot[$userID]['TOTAL_SPENT_TIME']['HOURS'] += $spentHours;
				}

				$dataBot[$userID]['TOTAL_SPENT_TIME']['MINUTES'] += $spentMinutes;
				// ФАКТИЧЕСКИ ЗАТРАЧЕННОЕ ВРЕМЯ END //
			}
		}

		return $dataBot;
	}

	/**
	 * Создать текст для отчета о задачах за день (на которые тратили время)
	 *
	 * @param $startDate
	 * @param $endDate
	 * @param $dataBot
	 * @param $showPlannedTime
	 * @param $userIDs
	 * @param $byTasks
	 * @return string
	 * @throws \ErrorException
	 */
	public static function createReportTaskText($startDate, $endDate, $dataBot, $userIDs, $showPlannedTime, $byTasks = true)
	{
		// сформировать текст для бота
		if ($startDate == $endDate)
		{
			$textForBot = "Отчет <b>" . date('d.m.Y', strtotime($startDate)) . "</b>" . PHP_EOL;
		} else
		{
			$textForBot = "Отчет <b>" . date('d.m.Y', strtotime($startDate)) . " - " . date('d.m.Y', strtotime($endDate)) . "</b>" . PHP_EOL;
		}

		$textForBot .= self::SEPARATOR . PHP_EOL;

		foreach ($dataBot as $user)
		{
			if (!in_array($user['ID'], $userIDs))
			{
				continue;
			}

			$textForBot .= "<b>" . $user['NAME'] . "</b>" . PHP_EOL;

			$textForBot .= $showPlannedTime ? "Итого (план/факт): " : "Итого (факт): ";

			// переведем в часы
			if ($user['TOTAL_ESTIMATE_TIME']['MINUTES'] >= 60)
			{
				$user['TOTAL_ESTIMATE_TIME']['HOURS'] += self::translateTime($user['TOTAL_ESTIMATE_TIME']['MINUTES'], 'min', 'hour');
				$user['TOTAL_ESTIMATE_TIME']['MINUTES'] = $user['TOTAL_ESTIMATE_TIME']['MINUTES'] % 60;
			}

			if ($user['TOTAL_SPENT_TIME']['MINUTES'] >= 60)
			{
				$user['TOTAL_SPENT_TIME']['HOURS'] += self::translateTime($user['TOTAL_SPENT_TIME']['MINUTES'], 'min', 'hour');
				$user['TOTAL_SPENT_TIME']['MINUTES'] = $user['TOTAL_SPENT_TIME']['MINUTES'] % 60;
			}

			// планируемое затраченное время
			if ($showPlannedTime)
			{
				if ($user['TOTAL_ESTIMATE_TIME']['HOURS'] && !$user['TOTAL_ESTIMATE_TIME']['MINUTES'])
				{    // только часы
					$textForBot .= self::formattingTimeForReport($user['TOTAL_ESTIMATE_TIME']['HOURS']);
				} elseif ($user['TOTAL_ESTIMATE_TIME']['HOURS'] && $user['TOTAL_ESTIMATE_TIME']['MINUTES'])
				{
					//часы и минуты
					$textForBot .= self::formattingTimeForReport($user['TOTAL_ESTIMATE_TIME']['HOURS'], $user['TOTAL_ESTIMATE_TIME']['MINUTES']);
				} else
				{
					// только минуты
					$textForBot .= self::formattingTimeForReport(false, $user['TOTAL_ESTIMATE_TIME']['MINUTES']);
				}

				$textForBot .= " / ";
			}

			// фактическое затраченное время
			if ($user['TOTAL_SPENT_TIME']['HOURS'] && !$user['TOTAL_SPENT_TIME']['MINUTES'])
			{
				// только часы
				$textForBot .= self::formattingTimeForReport($user['TOTAL_SPENT_TIME']['HOURS']) . PHP_EOL;
			} elseif ($user['TOTAL_SPENT_TIME']['HOURS'] && $user['TOTAL_SPENT_TIME']['MINUTES'])
			{
				// часы и минуты
				$textForBot .= self::formattingTimeForReport($user['TOTAL_SPENT_TIME']['HOURS'], $user['TOTAL_SPENT_TIME']['MINUTES']) . PHP_EOL;
			} else
			{
				// только минуты
				$textForBot .= self::formattingTimeForReport(false, $user['TOTAL_SPENT_TIME']['MINUTES']) . PHP_EOL;
			}

			// * если большой отчет (по кол-ву текста, то повиснет/вроде лимит на кол-во символов в телеге - 4096 символов)
			if ($byTasks)
			{
				$indexTask = 1;
				foreach ($user['TASKS'] as $key => $task)
				{
					$textForBot .= $indexTask . ") <a href='" . $task['LINK'] . "'>" . $task['NAME'] . "</a>" . PHP_EOL;

					// планируемое затраченное время
					if ($showPlannedTime)
					{
						if ($task['ESTIMATE_TIME']['HOURS'] && !$task['ESTIMATE_TIME']['MINUTES'])
						{    // только часы
							$textForBot .= self::formattingTimeForReport($task['ESTIMATE_TIME']['HOURS']);
						} elseif ($task['ESTIMATE_TIME']['HOURS'] && $task['ESTIMATE_TIME']['MINUTES'])
						{
							//часы и минуты
							$textForBot .= self::formattingTimeForReport($task['ESTIMATE_TIME']['HOURS'], $task['ESTIMATE_TIME']['MINUTES']);
						} else
						{
							// только минуты
							$textForBot .= self::formattingTimeForReport(false, $task['ESTIMATE_TIME']['MINUTES']);
						}

						$textForBot .= " / ";
					}

					// фактическое затраченное время
					if ($task['SPENT_TIME']['HOURS'] && !$task['SPENT_TIME']['MINUTES'])
					{
						// только часы
						$textForBot .= self::formattingTimeForReport($task['SPENT_TIME']['HOURS']);
					} elseif ($task['SPENT_TIME']['HOURS'] && $task['SPENT_TIME']['MINUTES'])
					{
						//часы и минуты
						$textForBot .= self::formattingTimeForReport($task['SPENT_TIME']['HOURS'], $task['SPENT_TIME']['MINUTES']);
					} else
					{
						// только минуты
						$textForBot .= self::formattingTimeForReport(false, $task['SPENT_TIME']['MINUTES']);
					}

					$textForBot .= " / <b>" . $task['STATUS_TEXT'] . "</b>" . PHP_EOL;

					$indexTask++;
				}
			}

			$textForBot .= self::SEPARATOR . PHP_EOL;
		}

		return $textForBot;
	}

	/**
	 * Отправить отчет о планируемых задачах на день (в статусе выполняется)
	 *
	 * @param $module
	 * @param $chat_id
	 */
	public function sendPlanTaskReport($module = false, $chat_id = false)
	{
		// для прокида в настройки
		if ($module)
		{
			$_REQUEST = $module; // случай для cron
		} else
		{
			$modules = Database::getInstance()->getAllModules(); // случай для чата
			$_REQUEST = $modules[0];
		}

		// получать отчеты подопечных под каждого юзера
		$appOptions = B24Custom::getApplicationOptions();
		$optionUserReports = $appOptions['USER_REPORTS'];
		$responsibleIds = array();

		if ($chat_id)
		{
			// ответ
			foreach ($optionUserReports as $userData)
			{
				if ($chat_id == $userData['CHAT_ID'])
				{
					$responsibleIds = $userData['SELECTED_USERS'];
					break;
				}
			}

			$taskInfo = self::getTaskInfoForPlan($responsibleIds);
			$textForBot = self::createPlanTaskText($taskInfo, $responsibleIds);
			$this->sendMessageWithCheckAvailable($textForBot, $chat_id);
		} else
		{
			// cron
			$listSubscribes = $this->listSubscribes;
			$listConfirmedSubscribes = $this->listConfirmedSubscribes;

			// собрать задачи по всем необходимым юзерам
			foreach ($listSubscribes as $subscribe)
			{
				foreach ($optionUserReports as $userData)
				{
					if ($subscribe['chat_id'] == $userData['CHAT_ID'] && in_array($subscribe['chat_id'], $listConfirmedSubscribes))
					{
						$responsibleIds = array_merge($responsibleIds, $userData['SELECTED_USERS']);
						break;
					}
				}
			}

			$responsibleIds = array_unique($responsibleIds);

			// взять инфу по задачам для всех необходимых юзеров Б24
			$taskInfo = self::getTaskInfoForPlan($responsibleIds);

			// отправить инфу о нужных озерах Б24 нужным юзерам телеги
			foreach ($listSubscribes as $subscribe)
			{
				foreach ($optionUserReports as $userData)
				{
					if ($subscribe['chat_id'] == $userData['CHAT_ID'] && in_array($subscribe['chat_id'], $listConfirmedSubscribes))
					{
						$userIDs = array_intersect($responsibleIds, $userData['SELECTED_USERS']);
						$textForBot = self::createPlanTaskText($taskInfo, $userIDs);
						$this->sendMessageWithCheckAvailable($textForBot, $subscribe['chat_id']);
					}
				}
			}
		}
	}

	/**
	 * Сформировать инфу по задачам, которая будет юзаться при создании текстового плана
	 * @param $responsibleIds
	 * @return array
	 */
	protected static function getTaskInfoForPlan($responsibleIds)
	{
		// выбрать несуществующего
		if (!$responsibleIds)
		{
			$responsibleIds = [0];
		}

		// получить задачи в статусе выполняется
		$tasksInProgress = B24Custom::getAllTasks(array(
			"STATUS" => B24Common::TASK_STATUSES[3]['VALUE'],
			"RESPONSIBLE_ID" => $responsibleIds
		), array(
			"ID",
			"TITLE",
			"RESPONSIBLE_ID",
			"GROUP_ID",
			"TIME_ESTIMATE",
			"TIME_SPENT_IN_LOGS",
			"STATUS",
		), array("ID" => "ASC"));

		// сформировать данные для бота
		$dataBot = array();

		foreach ($tasksInProgress as $task)
		{
			$user = $task['responsible'];
			$dataBot[$user['id']] = array(
				"ID" => $user['id'],
				"NAME" => $user['name'],
				"TOTAL_SPENT_TIME" => array(
					"HOURS" => 0,
					"MINUTES" => 0
				),
				"TOTAL_ESTIMATE_TIME" => array(
					"HOURS" => 0,
					"MINUTES" => 0
				),
				"TASKS" => array()
			);
		}

		foreach ($tasksInProgress as $task)
		{
			$user = $task['responsible'];
			$dataBot[$user['id']]["TASKS"][$task['id']]["NAME"] = $task['title'];
			$dataBot[$user['id']]["TASKS"][$task['id']]["LINK"] = "https://" . $_REQUEST['URL_BITRIX24'] . "/workgroups/group/" . $task['groupId'] . "/tasks/task/view/" . $task['id'] . "/";

			// ПЛАНИРУЕМОЕ ЗАТРАЧЕННОЕ ВРЕМЯ START //
			$estimateHours = 0;
			$estimateMinutes = 0;
			$estimateSeconds = $task['timeEstimate'];

			// переведем в минуты
			if ($estimateSeconds)
			{
				$estimateMinutes = self::translateTime($estimateSeconds, 'sec', 'min');

				// переведем в часы
				if ($estimateMinutes >= 60)
				{
					$estimateHours = self::translateTime($estimateMinutes, 'min', 'hour');
					$estimateMinutes = $estimateMinutes % 60;
				}

				if ($estimateHours)
				{
					$dataBot[$user['id']]["TASKS"][$task['id']]["ESTIMATE_TIME"]["HOURS"] = $estimateHours;
					$dataBot[$user['id']]['TOTAL_ESTIMATE_TIME']['HOURS'] += $estimateHours;
				}
			}

			$dataBot[$user['id']]["TASKS"][$task['id']]["ESTIMATE_TIME"]["MINUTES"] = $estimateMinutes;
			$dataBot[$user['id']]['TOTAL_ESTIMATE_TIME']['MINUTES'] += $estimateMinutes;
			// ПЛАНИРУЕМОЕ ЗАТРАЧЕННОЕ ВРЕМЯ END //

			// ФАКТИЧЕСКИ ЗАТРАЧЕННОЕ ВРЕМЯ START //
			$spentHours = 0;
			$spentMinutes = 0;
			$spentSeconds = $task['timeSpentInLogs'];

			// переведем в минуты
			if ($estimateSeconds)
			{
				$spentMinutes = self::translateTime($spentSeconds, 'sec', 'min');

				// переведем в часы
				if ($spentMinutes >= 60)
				{
					$spentHours = self::translateTime($spentMinutes, 'min', 'hour');
					$spentMinutes = $estimateMinutes % 60;
				}

				if ($spentHours)
				{
					$dataBot[$user['id']]["TASKS"][$task['id']]["SPENT_TIME"]["HOURS"] = $spentHours;
					$dataBot[$user['id']]['TOTAL_SPENT_TIME']['HOURS'] += $spentHours;
				}
			}

			$dataBot[$user['id']]["TASKS"][$task['id']]["SPENT_TIME"]["MINUTES"] = $spentMinutes;
			$dataBot[$user['id']]['TOTAL_SPENT_TIME']['MINUTES'] += $spentMinutes;
			// ФАКТИЧЕСКИ ЗАТРАЧЕННОЕ ВРЕМЯ END //
		}

		return $dataBot;
	}

	/**
	 * Создать текст для отчета о планируемых задачах на день (в статусе выполняется)
	 *
	 * @param $dataBot
	 * @param $userIDs
	 * @return string
	 */
	protected static function createPlanTaskText($dataBot, $userIDs)
	{
		// сформировать текст для бота
		$textForBot = "План <b>" . date('d.m.Y') . "</b>" . PHP_EOL;
		$textForBot .=  self::SEPARATOR . PHP_EOL;

		foreach ($dataBot as $user)
		{
			if (!in_array($user['ID'], $userIDs))
			{
				continue;
			}

			$textForBot .= "<b>" . $user['NAME'] . "</b>" . PHP_EOL;

			$textForBot .= "Итого (план/факт): ";

			// переведем в часы
			if ($user['TOTAL_ESTIMATE_TIME']['MINUTES'] >= 60)
			{
				$user['TOTAL_ESTIMATE_TIME']['HOURS'] += self::translateTime($user['TOTAL_ESTIMATE_TIME']['MINUTES'], 'min', 'hour');
				$user['TOTAL_ESTIMATE_TIME']['MINUTES'] = $user['TOTAL_ESTIMATE_TIME']['MINUTES'] % 60;
			}

			if ($user['TOTAL_SPENT_TIME']['MINUTES'] >= 60)
			{
				$user['TOTAL_SPENT_TIME']['HOURS'] += self::translateTime($user['TOTAL_SPENT_TIME']['MINUTES'], 'min', 'hour');
				$user['TOTAL_SPENT_TIME']['MINUTES'] = $user['TOTAL_SPENT_TIME']['MINUTES'] % 60;
			}

			// планируемое затраченное время
			if ($user['TOTAL_ESTIMATE_TIME']['HOURS'] && !$user['TOTAL_ESTIMATE_TIME']['MINUTES'])
			{    // только часы
				$textForBot .= self::formattingTimeForReport($user['TOTAL_ESTIMATE_TIME']['HOURS']);
			} elseif ($user['TOTAL_ESTIMATE_TIME']['HOURS'] && $user['TOTAL_ESTIMATE_TIME']['MINUTES'])
			{
				//часы и минуты
				$textForBot .= self::formattingTimeForReport($user['TOTAL_ESTIMATE_TIME']['HOURS'], $user['TOTAL_ESTIMATE_TIME']['MINUTES']);
			} else
			{
				// только минуты
				$textForBot .= self::formattingTimeForReport(false, $user['TOTAL_ESTIMATE_TIME']['MINUTES']);
			}

			$textForBot .= " / ";

			// фактическое затраченное время
			if ($user['TOTAL_SPENT_TIME']['HOURS'] && !$user['TOTAL_SPENT_TIME']['MINUTES'])
			{
				// только часы
				$textForBot .= self::formattingTimeForReport($user['TOTAL_SPENT_TIME']['HOURS']) . PHP_EOL;
			} elseif ($user['TOTAL_SPENT_TIME']['HOURS'] && $user['TOTAL_SPENT_TIME']['MINUTES'])
			{
				//часы и минуты
				$textForBot .= self::formattingTimeForReport($user['TOTAL_SPENT_TIME']['HOURS'], $user['TOTAL_SPENT_TIME']['MINUTES']) . PHP_EOL;
			} else
			{
				// только минуты
				$textForBot .= self::formattingTimeForReport(false, $user['TOTAL_SPENT_TIME']['MINUTES']) . PHP_EOL;
			}

			$indexTask = 1;
			foreach ($user['TASKS'] as $key => $task)
			{
				$textForBot .= $indexTask . ") <a href='" . $task['LINK'] . "'>" . $task['NAME'] . "</a>" . PHP_EOL;

				// планируемое затраченное время
				if ($task['ESTIMATE_TIME']['HOURS'] && !$task['ESTIMATE_TIME']['MINUTES'])
				{    // только часы
					$textForBot .= self::formattingTimeForReport($task['ESTIMATE_TIME']['HOURS']);
				} elseif ($task['ESTIMATE_TIME']['HOURS'] && $task['ESTIMATE_TIME']['MINUTES'])
				{
					//часы и минуты
					$textForBot .= self::formattingTimeForReport($task['ESTIMATE_TIME']['HOURS'], $task['ESTIMATE_TIME']['MINUTES']);
				} else
				{
					// только минуты
					$textForBot .= self::formattingTimeForReport(false, $task['ESTIMATE_TIME']['MINUTES']);
				}

				$textForBot .= " / ";

				// фактическое затраченное время
				if ($task['SPENT_TIME']['HOURS'] && !$task['SPENT_TIME']['MINUTES'])
				{
					// только часы
					$textForBot .= self::formattingTimeForReport($task['SPENT_TIME']['HOURS']) . PHP_EOL;
				} elseif ($task['SPENT_TIME']['HOURS'] && $task['SPENT_TIME']['MINUTES'])
				{
					//часы и минуты
					$textForBot .= self::formattingTimeForReport($task['SPENT_TIME']['HOURS'], $task['SPENT_TIME']['MINUTES']) . PHP_EOL;
				} else
				{
					// только минуты
					$textForBot .= self::formattingTimeForReport(false, $task['SPENT_TIME']['MINUTES']) . PHP_EOL;
				}

				$indexTask++;
			}

			$textForBot .=  self::SEPARATOR . PHP_EOL;
		}

		return $textForBot;
	}

	/**
	 * Отправить сообщение (ответ/по cron)
	 *
	 * @param $text
	 * @param $chatId
	 */
	protected function sendMessageWithCheckAvailable($text, $chatId)
	{
		// если подписаны и подтверждены
		$listSubscribes = $this->listSubscribes;
		$listConfirmedSubscribes = $this->listConfirmedSubscribes;
		$chatIdAvailable = false;

		foreach ($listSubscribes as $subscribe)
		{
			if ($chatId == $subscribe['chat_id'] && in_array($chatId, $listConfirmedSubscribes))
			{
				$chatIdAvailable = true;
				break;
			}
		}

		if ($chatIdAvailable)
		{
			$arrTexts = $this->createArrTexts($text);

			foreach ($arrTexts as $value)
			{
				$this->telegramApi->sendMessage([
					'chat_id' => $chatId,
					'parse_mode' => 'HTML',
					'text' => $value
				]);
			}
		}
	}

	/**
	 * Перевод времени
	 *
	 * @param $time
	 * @param $from (sec, min)
	 * @param $to (min, hour)
	 * @return bool|int
	 */
	public static function translateTime($time, $from, $to)
	{
		$result = false;

		// перевод секунд в минуты
		if ($from == 'sec' && $to == 'min')
		{
			$result = intdiv($time, 60);
		}

		// перевод секунд в часы
		if ($from == 'sec' && $to == 'hour')
		{
			$result = intdiv($time, 3600);
		}

		// перевод минут в часы
		if ($from == 'min' && $to == 'hour')
		{
			$result = intdiv($time, 60);
		}
		return $result;
	}

	/**
	 * Форматирование времени для отчета
	 *
	 * @param string $hour
	 * @param string $minutes
	 * @return string
	 */
	public static function formattingTimeForReport($hour = "", $minutes = "")
	{
		if ($hour && $minutes)
		{
			$result = $hour . ":" . (strlen($minutes) == 2 ? $minutes : "0" . $minutes);

		} elseif ($hour)
		{
			$result = $hour . ":00";
		} elseif ($minutes)
		{
			$result = "0:" . (strlen($minutes) == 2 ? $minutes : "0" . $minutes);
		} else
		{
			$result = "0:00";
		}

		return $result;
	}

	/**
	 *  Т.к у телеги 4096 символов лимит на 1 смс, то разбивку сделать текста
	 * @param $text
	 * @return array
	 */
	public function createArrTexts($text)
	{
		$result = array();
		$currentLength = 0;
		$messageIndex = 0;

		// разобьем на логические части (т.е, если не умещается инфа о человеке - в след.элемент массива переносим)
		$textArr = explode(self::SEPARATOR, $text);

		foreach ($textArr as $value)
		{
			$valLength = mb_strlen($value);
			$currentLength += $valLength;

			if ($valLength <= 1)
			{
				continue;
			}

			if ($currentLength <= self::MESSAGE_LENGTH)
			{
				$result[$messageIndex] .= $value .  self::SEPARATOR;
			} else
			{
				$messageIndex++;
				$currentLength = $valLength;
				$result[$messageIndex] .= $value .  self::SEPARATOR;
			}
		}

		return $result;
	}
}