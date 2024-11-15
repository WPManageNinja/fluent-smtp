<?php

namespace FluentMail\App\Http\Controllers;

use FluentMail\App\Models\Logger;
use FluentMail\Includes\Request\Request;

class LoggerController extends Controller
{
    public function get(Request $request, Logger $logger)
    {
        $this->verify();

        return $this->send(
            $logger->get(
                $request->except(['nonce', 'action'])
            )
        );
    }

    public function show(Request $request, Logger $logger)
    {
        $this->verify();

        $result = $logger->navigate($request->all());

        return $this->sendSuccess($result);
    }

    public function delete(Request $request, Logger $logger)
    {
        $this->verify();

        $id = (array) $request->get('id');

        $logger->delete($id);

        if ($id && $id[0] == 'all') {
            $subject = 'All logs';
        } else {
            $count = count($id);
            $subject = $count > 1 ? "{$count} Logs" : 'Log';
        }
        
        return $this->sendSuccess([
            'message' => sprintf(__('%s deleted successfully.', 'fluent-smtp'), $subject)
        ]);
    }

    public function retry(Request $request, Logger $logger)
    {
        $this->verify();

        try {
            $this->app->addAction('wp_mail_failed', function($response) use ($logger, $request) {
                $log = $logger->find($id = $request->get('id'));
                $log['retries'] = $log['retries'] + 1;
                $logger->updateLog($log, ['id' => $id]);

                return $this->sendError([
                    'message' => $response->get_error_message(),
                    'errors' => $response->get_error_data()
                ], $response->get_error_code());
            });

            if ($email = $logger->resendEmailFromLog($request->get('id'), $request->get('type'))) {
                return $this->sendSuccess([
                    'email' => $email,
                    'message' => __('Email sent successfully.', 'fluent-smtp')
                ]);
            }

            throw new \Exception(esc_html__('Something went wrong', 'fluent-smtp'), 400);

        } catch (\Exception $e) {
            return $this->sendError([
                'message' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function retryBulk(Request $request, Logger $logger)
    {
        $this->verify();
        $logIds = $request->get('log_ids', []);

        $failedCount = 0;
        $this->app->addAction('wp_mail_failed', function($response) use (&$failedCount) {
            $failedCount++;
        });

        $failedInitiated = 0;
        $successCount = 0;
        foreach ($logIds as $logId) {
            try {
                $email = $logger->resendEmailFromLog($logId, 'check_realtime');
                $successCount++;
            } catch (\Exception $exception) {
                $failedInitiated++;
            }
        }
        $message = __('Selected Emails have been proceed to send.', 'fluent-smtp');

        if ($failedCount) {
            $message .= sprintf(__(' But %d emails are reported to failed to send.', 'fluent-smtp'), $failedCount);
        }

        if ($failedInitiated) {
            $message .= sprintf(__(' And %d emails are failed to init the emails', 'fluent-smtp'), $failedInitiated);
        }

        return $this->sendSuccess([
            'message' => $message
        ]);

    }
}
