<script src="//api.bitrix24.com/api/v1/"></script>
<link href="templates/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet"/>
<script src="templates/libs/jquery/jquery-3.5.1.slim.min.js"></script>
<script src="templates/libs/bootstrap/js/bootstrap.min.js"></script>
<script src="templates/libs/jquery/jquery-3.4.1.min.js"></script>
<script src="templates/js/common.js"></script>
<link href="templates/css/main.css" rel="stylesheet"/>

<ul class="nav nav-tabs" id="tabNav" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="settings-tab" data-toggle="tab" href="#settings" role="tab"
           aria-controls="settings"
           aria-selected="true"><?= getMessage('APP_PANEL_SETTINGS') ?></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="second-tab" data-toggle="tab" href="#second" role="tab"
           aria-controls="second"
           aria-selected="false"><?= getMessage('APP_PANEL_SECOND') ?></a>
    </li>
</ul>

<div id="preloader" style="display: none">
    <div id="loader"></div>
</div>

<div class="tab-content">
	<?
	//error_reporting(E_ALL);
	error_reporting(0);

	include_once "settings.php";
	include_once "second.php";
	?>
</div>

<script>
    BX24.init(function () {
        initSettings();
        initEventHandlers();

        function initSettings() {
            showSettingsData();
        }

        //Init eventhandlers
        function initEventHandlers() {
            //Save settings
            $("#settingsForm").on('submit', function (event) {
                event.preventDefault();
                saveSettingsData();
            });
        }

        //Wrappers for API calls
        function saveOptions(settings, callback) {
            if (!callback) callback = function () {
            };
            BX24.callMethod('app.option.set', {options: settings}, callback);
        }

        function getOptions(callback) {
            if (!callback) callback = function () {
            };
            return BX24.callMethod('app.option.get', [], callback);
        }

        function showSettingsData() {
            request('/ajax/get_users.php', 'POST')
                .then(response => {
                    $('#settingsForm').find('tbody').html(response);
                });
        }

        function saveSettingsData() {

            let userReports = [];

            $('#settingsForm tbody tr').each(function(){
               let userId = $(this).find('[data-id]').attr('data-id');
               let chatId = $(this).find('input').val();

                if (chatId.length > 0)
                {
                    let userReport = {
                        USER_ID: userId,
                        CHAT_ID: chatId,
                        SELECTED_USERS: []
                    };

                    let selectedUserIDs = $(this).find('select option:selected');

                    selectedUserIDs.each(function(){
                        userReport.SELECTED_USERS.push($(this).val());
                    });

                    userReports[userId] = userReport;
                }
            });

            saveOptions({
                USER_REPORTS: userReports,
            }, function (res) {
                alert("<?=getMessage('APP_DATA_SAVED')?>");
            });
        }
    });

    function request(address, httpMethod = "POST", inputData = {}) {
        let initData = {
            DOMAIN: "<?=$_REQUEST['DOMAIN']?>",
            member_id: "<?=$_REQUEST['member_id']?>"
        };

        let data = Object.assign(initData, inputData);

        if (!(data instanceof FormData)) {
            let formData = new FormData();
            data = objToForm(data, formData)
        }

        // let url = location.origin + address;
        let url = location.origin + '/task-bot' + address; // todo не в корне

        if (httpMethod === "GET")
        {
            url = url + '?' + objToGetParams(data);
        }

        let init = {
            type: httpMethod,
            url: url,
            data: data,
            contentType: false,
            processData: false,
            beforeSend: function () {
                showPreloader();
            },
            success: function () {
                hidePreloader();
            },
            error: function () {
                hidePreloader();
            }
        };

        return $.ajax(init);
    }
</script>