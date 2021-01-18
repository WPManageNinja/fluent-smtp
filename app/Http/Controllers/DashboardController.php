<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Logger;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Reporting;
use FluentMail\Includes\Request\Request;

class DashboardController extends Controller
{
    public function index(Logger $logger, Manager $manager)
    {
        $this->verify();

        $connections = $manager->getSettings('connections', []);

        return $this->send([
            'stats' => $logger->getStats(),
            'settings_stat' => [
                'connection_counts' => count($connections),
                'active_senders' => count($manager->getSettings('mappings', [])),
                'auto_delete_days' => $manager->getSettings('misc.log_saved_interval_days'),
                'log_enabled' => $manager->getSettings('misc.log_emails')
            ]
        ]);
    }

    public function getSendingStats(Request $request, Reporting $reporting)
    {
        list($from, $to) = $request->get('date_range');

        return $this->send([
            'stats' => $reporting->getSendingStats($from, $to)
        ]);

    }

}
