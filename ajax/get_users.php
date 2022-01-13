<?
// todo не в корне
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] . '/task-bot';

require_once($_SERVER['DOCUMENT_ROOT'] . '/api/bitrix24/custom.php');

$users = B24Custom::getAllUsers(array("ACTIVE" => "Y"));

$appOptions = B24Custom::getApplicationOptions();
$optionUserReports = $appOptions['USER_REPORTS'];
?>

<? foreach ($users as $key => $user): ?>
    <tr>
        <th scope="row"><?= $key + 1 ?></th>
        <td data-id="<?= $user['ID'] ?>"><?= $user['LAST_NAME'] . " " . $user['NAME'] ?></td>
        <td><input type="number" name="chat_id" class="form-control" placeholder="chat_id" min="1" value="<?=$optionUserReports[$user['ID']]['CHAT_ID']?>"></td>
        <td>
            <select class="custom-select" multiple>
				<? foreach ($users as $userForSelect): ?>
                    <option value="<?= $userForSelect['ID'] ?>" <?if(in_array($userForSelect['ID'], $optionUserReports[$user['ID']]['SELECTED_USERS'])):?>selected<?endif;?>><?= $userForSelect['LAST_NAME'] . " " . $userForSelect['NAME'] ?></option>
				<? endforeach; ?>
            </select>
        </td>
    </tr>
<? endforeach; ?>