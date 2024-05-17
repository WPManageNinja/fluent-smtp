<?php

$app->get('/', 'DashboardController@index');
$app->get('/day-time-stats', 'DashboardController@getDayTimeStats');
$app->get('sending_stats', 'DashboardController@getSendingStats');

$app->get('/settings', 'SettingsController@index');
$app->post('/settings/validate', 'SettingsController@validate');
$app->post('/settings', 'SettingsController@store');
$app->post('/misc-settings', 'SettingsController@storeMiscSettings');
$app->post('/settings/delete', 'SettingsController@delete');
$app->post('/settings/misc', 'SettingsController@storeGlobals');
$app->post('/settings/test', 'SettingsController@sendTestEmil');
$app->post('/settings/subscribe', 'SettingsController@subscribe');
$app->post('/settings/subscribe-dismiss', 'SettingsController@subscribeDismiss');
$app->get('settings/connection_info', 'SettingsController@getConnectionInfo');
$app->post('settings/add_new_sender_email', 'SettingsController@addNewSenderEmail');
$app->post('settings/remove_sender_email', 'SettingsController@removeSenderEmail');


$app->get('settings/notification-settings', 'SettingsController@getNotificationSettings');
$app->post('settings/notification-settings', 'SettingsController@saveNotificationSettings');
$app->post('settings/gmail_auth_url', 'SettingsController@getGmailAuthUrl');
$app->post('settings/outlook_auth_url', 'SettingsController@getOutlookAuthUrl');

/*
 * Telegram Routes
 */
$app->post('settings/telegram/issue-pin-code', 'TelegramController@issuePinCode');
$app->post('settings/telegram/confirm', 'TelegramController@confirmConnection');
$app->get('settings/telegram/info', 'TelegramController@getTelegramConnectionInfo');
$app->post('settings/telegram/send-test', 'TelegramController@sendTestMessage');
$app->post('settings/telegram/disconnect', 'TelegramController@disconnect');

/*
 * Slack Routes
 */
$app->post('settings/slack/register', 'SlackController@registerSite');
$app->post('settings/slack/send-test', 'SlackController@sendTestMessage');
$app->post('settings/slack/disconnect', 'SlackController@disconnect');

/*
 * Discord Routes
 */
$app->post('settings/discord/register', 'DiscordController@registerSite');
$app->post('settings/discord/send-test', 'DiscordController@sendTestMessage');
$app->post('settings/discord/disconnect', 'DiscordController@disconnect');



$app->get('/logs', 'LoggerController@get');
$app->get('/logs/show', 'LoggerController@show');
$app->post('/logs/retry', 'LoggerController@retry');
$app->post('/logs/retry-bulk', 'LoggerController@retryBulk');
$app->post('/logs/delete', 'LoggerController@delete');


$app->post('install_plugin', 'SettingsController@installPlugin');
$app->get('docs', 'DashboardController@getDocs');
