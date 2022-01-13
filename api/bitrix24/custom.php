<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/api/bitrix24/common.php');

class B24Custom extends B24Common
{

	/**
	 * @see https://dev.1c-bitrix.ru/rest_help/tasks/task/tasks/tasks_task_list.php
	 *
	 * @param array $order
	 * @param array $filter
	 * @param array $select
	 * @param int $start
	 * @return array|mixed
	 * @throws ErrorException
	 */
	public static function getListTasks($filter = [], $select = [], $order = [], $start = 0)
	{
		$data = static::request('tasks.task.list.json', 'POST', array(
			"start" => $start,
			"order" => $order,
			"filter" => $filter,
			"select" => $select
		));

		return $data;
	}

	public static function getAllTasks($filter = [], $select = [], $order = [])
	{
		$start = 0;
		$result = array();

		do
		{
			$data = self::getListTasks($filter, $select, $order, $start);

			if ($data['result'])
			{
				$result = array_merge($result, $data['result']['tasks']);
			}

			if (isset($data['next']))
			{
				$start = $data['next'];
			}
			else
			{
				$start = 0;
			}

		} while ($start);

		return $result;
	}

	/**
	 * @see https://dev.1c-bitrix.ru/rest_help/tasks/task/elapseditem/getlist.php
	 *
	 * @param $taskId
	 * @return array|mixed
	 * @throws ErrorException
	 */
	public static function getTasksSpentTimeByTaskId($taskId)
	{
		$result = array();

		$data = static::request('task.elapseditem.getlist.json', 'POST', array(
			 $taskId
		));

		if ($data['result'])
		{
			$result = $data['result'];
		}

		return $result;
	}

	/**
	 * @see https://dev.1c-bitrix.ru/rest_help/users/user_get.php
	 *
	 * @param string $sort
	 * @param string $order
	 * @param array $filter
	 * @param int $start
	 * @return array|mixed
	 * @throws ErrorException
	 */
	public static function getListUsers($filter = [], $sort = "", $order = "", $start = 0)
	{
		$data = static::request('user.get.json', 'POST', array(
			"start" => $start,
			"sort" => $sort,
			"order" => $order,
			"filter" => $filter
		));

		return $data;
	}

	public static function getAllUsers($filter = [], $sort = "", $order = "")
	{
		$start = 0;
		$result = array();

		do
		{
			$data = self::getListUsers($filter, $sort, $order, $start);

			if ($data['result'])
			{
				foreach ($data['result'] as $element)
				{
					$result[] = $element;
				}
			}

			if ($data['next'])
			{
				$start = $data['next'];
			}
			else
			{
				$start = 0;
			}

		} while ($start);

		return $result;
	}
}