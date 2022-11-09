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
                'title' => $doc['title']['rendered'],
                'content' => $doc['content']['rendered'],
                'link' => $doc['link'],
                'category' => $primaryCategory
            ];
        }

        return $this->send([
            'docs' => $formattedDocs
        ]);
    }

}
