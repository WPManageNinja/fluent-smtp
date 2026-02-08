<?php
!defined('WPINC') && die;

$router = new \FluentMail\App\Services\Router('fluent-smtp');

$permissions = ['manage_options'];

$router->get('dashboard', [\FluentMail\App\Http\Controllers\DashboardController::class, 'index'], $permissions)
    ->get('day-time-stats', [\FluentMail\App\Http\Controllers\DashboardController::class, 'getDayTimeStats'], $permissions)
    ->get('sending_stats', [\FluentMail\App\Http\Controllers\DashboardController::class, 'getSendingStats'], $permissions)
    // settings
    ->get('settings', [\FluentMail\App\Http\Controllers\SettingsController::class, 'index'], $permissions)
    ->post('settings', [\FluentMail\App\Http\Controllers\SettingsController::class, 'store'], $permissions)
    ->post('settings/validate', [\FluentMail\App\Http\Controllers\SettingsController::class, 'validate'], $permissions)
    ->post('settings/delete', [\FluentMail\App\Http\Controllers\SettingsController::class, 'delete'], $permissions)
    ->post('settings/misc', [\FluentMail\App\Http\Controllers\SettingsController::class, 'storeMiscSettings'], $permissions)
    ;
