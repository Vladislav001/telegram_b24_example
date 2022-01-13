<?
require_once('messages.php');

function getMessage($key, $replace = [])
{
	global $MESS;
	if (array_key_exists($key, $MESS))
	{
		$message = $MESS[$key];
		if ($replace)
		{
			foreach ($replace as $search => $replacement)
			{
				$message = str_replace($search, $replacement, $message);
			}
		}

		return $message;
	} else
	{
		return $key;
	}
}