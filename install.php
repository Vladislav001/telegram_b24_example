<?
// todo не в корне
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] . '/task-bot';

require_once($_SERVER['DOCUMENT_ROOT'] . '/langs.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/application.php');
?>

    <script src="//api.bitrix24.com/api/v1/"></script>
    <h2><?= getMessage('APP_INSTALL_START') ?></h2>

<?
$result = \B24Application::install();

if (count($result) == 0): ?>
	<?= getMessage('APP_INSTALL_SUCCESS') ?>
    <script>
        BX24.init(function () {
            BX24.installFinish();
        });
    </script>
<? else: ?>
	<?= getMessage('APP_INSTALL_ERROR') ?>: <br>
	<?
	foreach ($result as $error)
	{
		echo $error . '<br>';
	}
	?>
<? endif; ?>