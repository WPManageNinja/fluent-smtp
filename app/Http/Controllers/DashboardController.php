<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Logger;

class DashboardController extends Controller
{
    public function index(Logger $logger)
    {
        $this->verify();
        return $this->send([
            'stats' => $logger->getStats()
        ]);
    }
}
