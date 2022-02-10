Примет telegram бота для отчета по задачам из Bitrix24

Описание логики:

1. В свагере или постмане нет смысла, т.к тут нет АПИ внешнего/не предусмотрено под него
2. Структура:
- ajax тут фронт для вывода в Б24 панели настройки юзеров и кто чьи отчеты может получать
- api/bitrix24 тут чисто АПИ для работы с Б24
- api/telegram тут АПИ для работы с telegram (в частности bot.php), subscribers.json - кто подписался/отписался на telegram (автоматически работает), confirmed_subscribers.json - подтвержденные id для работы (в ручную правится всегда),
  working_webhook_ids.json - id вебхуков в работе (нужно, что если долго ответ идет, то telegram снова пытается и чтобы не вошло в цикл это),
  файл webhook.php чисто принимает данные от telegram (НО по факту это точка входа, т.к начало работы с файлами, выбор команды и уже от команды логика)
- cron тут логика для отсылки планов и отчетов по крону (но на серваке отключили пока), также updateRefreshToken.php обновляет рефреш токен от Б24 (работает на кроне,
  обязательно ДОЛЖЕН работать)
- logs логи если какие то нужно сделать
- templates - фронт для Б24 (совместно с ajax/get_users.php фактически используется)
- application.php (при установке модуля в Б24 вызывается или при заходе в него в Б24)
- database.php работа с MySql
- install.php (при установке модуля в Б24 вызывается)
- run.php (при запуске модуля в Б24 вызывается)
- messages.php (словарик)
- settings.ini (доступы к БД, обновлению токенов и тп)
3. Установить/переустановить в Б24 (если вдруг например рефреш токены перестанут обновлять больше месяца), то тут https://lince.bitrix24.ru/marketplace/ или
   https://lince.bitrix24.ru/devops/list/ (по доступам для этого приложения нужны: Пользователи, Задачи)
4. Функционал:
- Подписаться/отписаться на канал (но вручную надо id указывать также в api/telegram/confirmed_subscribers.json)
- Получить webhook от telegram (api/telegram/webhook.php)->запустить определение команды и логику(\TelegramBot\ChatBot::triggerWebhook в api/telegram/bot.php)
- Настроить какой человек чьи отчеты может видить (в Б24 https://lince.bitrix24.ru/marketplace/app/41/ , сам код на js saveSettingsData()  в templates/index.php)
- Также в api/telegram/bot.php каждый метод описан

--------------------------------------------------------------

Описание cron:
1. План на сегодня
   /home/admin/web/lince.studio/public_html/task-bot/cron/sendPlanTaskReport.php
2. Отчет за сегодня
   /home/admin/web/lince.studio/public_html/task-bot/cron/sendTaskReport.php
3. Обновить refresh токен в Б24 (работает сейчас и должен работать ВСЕГДА)
   /home/admin/web/lince.studio/public_html/task-bot/cron/updateRefreshToken.php

--------------------------------------------------------------

Требуемые права от Б24:
- Пользователи (user)
- Задачи (task)

--------------------------------------------------------------

Доп. инфа по telegram ботам:
1. Инструкция по созданию бота через папу ботов
   https://netology.ru/blog/bot-php/amp
2. Актуальная инструкция по настройки вебхуков (но из п.1 все понятно)
   https://retifrav.github.io/blog/2018/12/02/telegram-bot-webhook-ru/