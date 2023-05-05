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
            'message' => "{$subject} deleted successfully."
        ]);
    }

    public function retry(Request $request, Logger $logger)
    {
        $this->verify();

        try {
            $this->app->addAction('wp_mail_failed', function ($response) use ($logger, $request) {
                $log = $logger->find($id = $request->get('id'));
                $log['retries'] = $log['retries'] + 1;
                $logger->updateLog($log, ['id' => $id]);

                $this->sendError([
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

            throw new \Exception('Something went wrong', 400);
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
        $this->app->addAction('wp_mail_failed', function ($response) use (&$failedCount) {
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
        $message = 'Selected Emails have been proceed to send.';

        if ($failedCount) {
            $message .= ' But ' . $failedCount . ' emails are reported to failed to send.';
        }

        if ($failedInitiated) {
            $message .= ' And ' . $failedInitiated . ' emails are failed to init the emails';
        }

        return $this->sendSuccess([
            'message' => $message
        ]);
    }

    public function export(Request $request, Logger $logger)
    {
        $this->verify();
        $format = $request->get('format', 'txt'); // csv, txt, json
        $results = $logger->getAll();

        $filename = 'fsmtp-logs-' . date('Y-m-d-H-i-s') . '.' . $format;
        $content = '';
        $mime_type = 'text/plain';

        switch ($format) {
            case 'csv':
                $mime_type = 'text/csv';
                $content = $this->convertToCSV($results);
                break;
            case 'txt':
                $mime_type = 'text/plain';
                foreach ($results as $result) {
                    $content .= json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
                }
                break;
            case 'json':
                $mime_type = 'application/json';
                $content = json_encode($results, JSON_PRETTY_PRINT);
                break;
            default:
                break;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
        exit();
    }

    public function convertToCSV(array $objArray)
    {
        if (!is_array($objArray) || empty($objArray)) {
            return null;
        }

        $headers = implode(',', array_keys($objArray[0])) . "\n";

        // Process each row
        $rows = array_map(function ($row) {
            // Create an array of values
            $values = [];

            // Process each property in the row
            foreach ($row as $key => $value) {
                // If the value is an object or array, convert to JSON string
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_PRETTY_PRINT);
                }

                // Escape any double quotes in the value
                if (is_string($value) || is_array($value)) {
                    $value = str_replace('"', '\"', $value);
                }

                // Wrap the value in double quotes
                $value = '"' . $value . '"';

                // Add the value to the array
                $values[] = $value;
            }
            // Join the values with commas and return the row as a string
            return implode(',', $values) . "\n";
        }, $objArray);

        // Join the headers and rows and return as CSV string
        return $headers . implode('', $rows);
    }
}
