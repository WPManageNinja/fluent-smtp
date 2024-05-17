<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Logger;
use FluentMail\App\Services\Mailer\Manager;
use FluentMail\App\Services\Reporting;
use FluentMail\Includes\Request\Request;
use FluentMail\Includes\Support\Arr;

class DashboardController extends Controller
{
    public function index(Logger $logger, Manager $manager)
    {
        $this->verify();

        $connections = $manager->getSettings('connections', []);

        return $this->send([
            'stats'         => $logger->getStats(),
            'settings_stat' => [
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

        $lastDay = 0;
        if (isset($_REQUEST['last_day'])) {
            $lastDay = (int)$_REQUEST['last_day'];
        }

        global $wpdb;
        if ($lastDay > 6) {
            $results = $wpdb->get_results("SELECT
  DAYNAME(created_at) AS day_of_week,
  HOUR(created_at) AS hour_of_day,
  COUNT(*) AS count
FROM
  {$wpdb->prefix}fsmpt_email_logs
WHERE
  created_at >= NOW() - INTERVAL {$lastDay} DAY
GROUP BY
  DAYNAME(created_at),
  HOUR(created_at)
ORDER BY
  FIELD(DAYNAME(created_at), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
  HOUR(created_at)");
        } else {
            $results = $wpdb->get_results("SELECT
  DAYNAME(created_at) AS day_of_week,
  HOUR(created_at) AS hour_of_day,
  COUNT(*) AS count
FROM
  {$wpdb->prefix}fsmpt_email_logs
GROUP BY
  DAYNAME(created_at),
  HOUR(created_at)
ORDER BY
  FIELD(DAYNAME(created_at), 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
  HOUR(created_at)");
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

    public function getDocs()
    {
        $this->verify();

        $request = wp_remote_get('https://fluentsmtp.com/wp-json/wp/v2/docs?per_page=100');

        $docs = json_decode(wp_remote_retrieve_body($request), true);


        $formattedDocs = [];

        foreach ($docs as $doc) {
            $primaryCategory = Arr::get($doc, 'taxonomy_info.doc_category.0', ['value' => 'none', 'label' => 'Other']);
            $formattedDocs[] = [
                'title'    => $doc['title']['rendered'],
                'content'  => $doc['content']['rendered'],
                'link'     => $doc['link'],
                'category' => $primaryCategory
            ];
        }

        return $this->send([
            'docs' => $formattedDocs
        ]);
    }

}
