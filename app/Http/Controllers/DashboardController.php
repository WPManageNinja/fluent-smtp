<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Logger;
use FluentMail\App\Services\ConnectionHealth;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Reporting;
use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\Arr;
use FluentMail\App\Services\Documentation;

class DashboardController extends Controller
{
    public function index(Logger $logger, Manager $manager)
    {
        $this->verify();

        $connections = $manager->getSettings('connections', []);

        return $this->send([
            'stats'              => $logger->getStats(),
            'unhealthy_settings' => array_values((new ConnectionHealth())->getFailing()),
            'settings_stat'      => [
                'connection_counts' => count($connections),
                'active_senders'    => count($manager->getSettings('mappings', [])),
                'auto_delete_days'  => $manager->getSettings('misc.log_saved_interval_days'),
                'log_enabled'       => $manager->getSettings('misc.log_emails')
            ]
        ]);
    }

    public function getDayTimeStats()
    {
        $this->verify();

        // Validate and sanitize input with absint() and constrain to reasonable range
        $lastDay = 0;
        if (isset($_REQUEST['last_day'])) {
            $lastDay = absint($_REQUEST['last_day']);
            // Constrain to reasonable range: 0-365 days
            $lastDay = min(max($lastDay, 0), 365);
        }

        global $wpdb;
        $tableName = $wpdb->prefix . 'fsmpt_email_logs';

        if ($lastDay > 6) {
            // Use wpdb->prepare() with proper placeholder for the interval value
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                        DAYNAME(created_at) AS day_of_week,
                        HOUR(created_at) AS hour_of_day,
                        COUNT(*) AS count
                    FROM {$tableName}
                    WHERE created_at >= NOW() - INTERVAL %d DAY
                    GROUP BY
                        DAYNAME(created_at),
                        HOUR(created_at)",
                    $lastDay
                )
            );
        } else {
            // Query for all time data when lastDay <= 6
            // Table name is safe - constructed from WordPress prefix and hard-coded table suffix
            // No user input in this query, so prepare() is not needed
            $results = $wpdb->get_results(
                "SELECT
                    DAYNAME(created_at) AS day_of_week,
                    HOUR(created_at) AS hour_of_day,
                    COUNT(*) AS count
                FROM {$tableName}
                GROUP BY
                    DAYNAME(created_at),
                    HOUR(created_at)"
            );
        }

        // Assuming $results is the array of records fetched from the database.
        $dataItems = [
            'Mon' => [], 'Tue' => [], 'Wed' => [], 'Thu' => [], 'Fri' => [], 'Sat' => [], 'Sun' => []
        ];

        $hours = ['0:00', '1:00', '2:00', '3:00', '4:00', '5:00', '6:00', '7:00', '8:00', '9:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'];

        foreach ($dataItems as $day => $data) {
            foreach ($hours as $hour) {
                $dataItems[$day][$hour] = 0;
            }
        }

        foreach ($results as $row) {
            $day = substr($row->day_of_week, 0, 3); // Shorten 'Monday' to 'Mon', etc.
            $hour = $row->hour_of_day . ":00";      // Format hour as '0:00', '1:00', etc.
            $dataItems[$day][$hour] = (int)$row->count;
        }

        return $this->send([
            'stats' => $dataItems
        ]);

    }

    public function getSendingStats(Request $request, Reporting $reporting)
    {
        $this->verify();

        list($from, $to) = $request->get('date_range');

        return $this->send([
            'stats' => $reporting->getSendingStats($from, $to)
        ]);

    }

    public function getDocs(Manager $manager)
    {
        $this->verify();

        $documentation = new Documentation();
        $docs = $documentation->getDocs();
        if (is_wp_error($docs)) {
            return $this->sendError(['message' => $docs->get_error_message()], 503);
        }

        // The guides for the services this site actually sends through, so the page
        // can put them first. Keyed by article, valued by provider key so the page
        // can show the provider's logo on the tile.
        $providers = [];
        foreach ($manager->getSettings('connections', []) as $connection) {
            $providers[] = Arr::get($connection, 'provider_settings.provider');
        }

        return $this->send([
            'docs'      => $docs,
            'suggested' => $documentation->suggestedFor($docs, $providers),
            'providers' => $this->providerCards($manager)
        ]);
    }

    /**
     * Title and logo per provider, which is all the docs page needs to label a
     * suggested article with the connection it is for.
     */
    private function providerCards(Manager $manager)
    {
        $cards = [];
        foreach ($manager->getConfig('providers', []) as $key => $provider) {
            $cards[$key] = [
                'title' => Arr::get($provider, 'title', $key),
                'image' => Arr::get($provider, 'image', '')
            ];
        }
        return $cards;
    }

}
