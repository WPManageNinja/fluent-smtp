<?php

$app->get('/', 'DashboardController@index');
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
$app->get('settings/notification-settings', 'SettingsController@getNotificationSettings');
$app->post('settings/notification-settings', 'SettingsController@saveNotificationSettings');
$app->post('settings/gmail_auth_url', 'SettingsController@getGmailAuthUrl');
$app->post('settings/outlook_auth_url', 'SettingsController@getOutlookAuthUrl');

$app->get('/logs', 'LoggerController@get');
$app->get('/logs/show', 'LoggerController@show');
$app->post('/logs/retry', 'LoggerController@retry');
$app->post('/logs/retry-bulk', 'LoggerController@retryBulk');
$app->post('/logs/delete', 'LoggerController@delete');


$app->post('install_plugin', 'SettingsController@installPlugin');
$app->get('docs', 'DashboardController@getDocs');
