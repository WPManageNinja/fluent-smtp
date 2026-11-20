<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Services\DomainAuthentication;
use FluentMail\Includes\Request\Request;

class DomainAuthenticationController extends Controller
{
    public function check(Request $request, DomainAuthentication $checker)
    {
        $this->verify();
        try {
            $result = $checker->check(
                $request->get('envelope_domain', ''),
                $request->get('signing_domain') ?? '',
                $request->get('selector') ?? ''
            );
        } catch (\InvalidArgumentException $e) {
            return $this->sendError(['message' => $e->getMessage()], 422);
        }
        return $this->sendSuccess($result);
    }
}
