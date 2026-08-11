<?php

namespace FluentMail\App\Models;

use Exception;
use InvalidArgumentException;
use FluentMail\Includes\Support\Arr;

class Logger extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED  = 'failed';
    const STATUS_SENT    = 'sent';

    protected $fillables = [
        'to',
        'from',
        'subject',
        'body',
        'status',
        'response',
        'extra',
        'created_at'
    ];

    protected $searchables = [
        'to',
        'from',
        'subject'
    ];

    protected $table = null;

    public function __construct()
    {
        parent::__construct();

        $this->table = $this->db->prefix . FLUENT_MAIL_DB_PREFIX . 'email_logs';
    }

    public function get($data)
    {
        $db      = $this->getDb();
        $page    = isset($data['page']) ? (int)$data['page'] : 1;
        $perPage = isset($data['per_page']) ? (int)$data['per_page'] : 15;
        $offset  = ($page - 1) * $perPage;

        $query = $db->table(FLUENT_MAIL_DB_PREFIX . 'email_logs')
            ->limit($perPage)
            ->offset($offset)
            ->orderBy('id', 'DESC');

        if (!empty($data['status'])) {
            $query->where('status', sanitize_text_field($data['status']));
        }

        if (!empty($data['date_range']) && is_array($data['date_range']) && count($data['date_range']) == 2) {
            $dateRange = $data['date_range'];
            $from      = $dateRange[0] . ' 00:00:01';
            $to        = $dateRange[1] . ' 23:59:59';
            $query->whereBetween('created_at', $from, $to);
        }

        if (!empty($data['search'])) {
            $search = trim(sanitize_text_field($data['search']));
            $query->where(function ($q) use ($search) {
                $searchColumns = $this->searchables;

                $columnSearch = false;
                if (strpos($search, ':')) {
                    $searchArray = explode(':', $search);
                    $column = array_shift($searchArray);
                    if (in_array($column, $this->fillables)) {
                        $columnSearch = true;
                        $q->where($column, 'LIKE', '%' . trim(implode(':', $searchArray)) . '%');
                    }
                }

                if (!$columnSearch) {
                    $firstColumn = array_shift($searchColumns);
                    $q->where($firstColumn, 'LIKE', '%' . $search . '%');
                    foreach ($searchColumns as $column) {
                        $q->orWhere($column, 'LIKE', '%' . $search . '%');
                    }
                }

            });
        }

        $result = $query->paginate();
        $result['data'] = $this->formatResult($result['data']);

        return $result;
    }

    protected function buildWhere($data)
    {
        $where = [];

        if (isset($data['filter_by_value'])) {
            $where[$data['filter_by']] = $data['filter_by_value'];
        }

        if (isset($data['query'])) {
            foreach ($this->searchables as $column) {
                if (isset($where[$column])) {
                    $where[$column] .= '|' . $data['query'];
                } else {
                    $where[$column] = $data['query'];
                }
            }
        }

        $args = [1];
        $andWhere = $orWhere = '';
        $whereClause = "WHERE 1 = '%d'";

        foreach ($where as $key => $value) {
            if (in_array($key, ['status', 'created_at'])) {
                if ($key == 'created_at') {
                    if (is_array($value)) {
                        $args[] = $value[0];
                        $args[] = $value[1];
                    } else {
                        $args[] = $value;
                        $args[] = $value;
                    }
                    $andWhere .= " AND `{$key}` >= '%s' AND `{$key}` < '%s' + INTERVAL 1 DAY";
                } else {
                    $args[] = $value;
                    $andWhere .= " AND `{$key}` = '%s'";
                }
            } else {
                if (strpos($value, '|')) {
                    $nestedOr = '';
                    $values = explode('|', $value);
                    foreach ($values as $itemValue) {
                        $args[] = '%' . $this->db->esc_like($itemValue) . '%';
                        $nestedOr .= " OR `{$key}` LIKE '%s'";
                    }
                    $orWhere .= ' OR (' . trim($nestedOr, 'OR ') . ')';
                } else {
                    $args[] = '%' . $this->db->esc_like($value) . '%';
                    $orWhere .= " OR `{$key}` LIKE '%s'";
                }
            }
        }

        if ($orWhere) {
            $orWhere = 'AND (' . trim($orWhere, 'OR ') . ')';
        }

        $whereClause = implode(' ', [$whereClause, trim($andWhere), $orWhere]);

        return [$whereClause, $args];
    }

    protected function formatResult($result)
    {
        $result = is_array($result) ? $result : func_get_args();
        foreach ($result as $key => $row) {
            $result[$key]            = $this->maybeUnserialize((array)$row);
            $result[$key]['id']      = (int)$result[$key]['id'];
            $result[$key]['retries'] = (int)$result[$key]['retries'];
            $result[$key]['from']    = htmlspecialchars($result[$key]['from']);
            /*
             * No wp_kses_post() here. Both consumers render the subject as text
             * — {{ }} in Logs.vue and LogViewer.vue, which escapes on its own —
             * so kses adds no safety, and its entity normalization rewrites a
             * subject reading "Tom & Jerry" to "Tom &amp; Jerry", which the
             * page then shows verbatim. Anything that puts a subject into
             * HTML has to escape it at that point, as digest_email.php does.
             */
            $result[$key]['subject'] = wp_unslash($result[$key]['subject']);
        }

        return $result;
    }

    protected function maybeUnserialize(array $data)
    {
        foreach ($data as $key => $value) {
            if ($this->isUnserializable($key)) {
                $data[$key] = $this->unserialize($value);
            }
        }
        
        return $data;
    }

    protected function isUnserializable($key)
    {
        $allowedFields = [
            'to',
            'headers',
            'attachments',
            'response',
            'extra'
        ];
        
        return in_array($key, $allowedFields);
    }

    protected function unserialize($data)
    {
        if (is_serialized($data)) {
            if (preg_match('/(^|;)O:[0-9]+:/', $data)) {
                return $data;
            }
            return unserialize(trim($data), ['allowed_classes' => false]);
        }

        return $data;
    }

    protected function formatHeaders($headers)
    {
        foreach ((array)$headers as $key => $header) {
            if (is_array($header)) {
                $header = $this->formatHeaders($header);
            } else {
                $header = htmlspecialchars($header);
            }

            $headers[$key] = $header;
        }

        return $headers;
    }

    public function add($data)
    {
        try {
            $data = array_merge($data, [
                'created_at' => current_time('mysql')
            ]);

            return $this->getDb()->table(FLUENT_MAIL_DB_PREFIX . 'email_logs')
                ->insert($data);

        } catch (Exception $e) {
            return $e;
        }
    }

    public function delete(array $id)
    {
        if ($id && $id[0] == 'all') {
            // TRUNCATE doesn't support parameterization
            // Table name is safe - constructed from constants in __construct()
            return $this->db->query("TRUNCATE TABLE {$this->table}");
        }

        $ids = array_filter($id, 'intval');

        if ($ids) {
            return $this->getDb()->table(FLUENT_MAIL_DB_PREFIX . 'email_logs')
                ->whereIn('id', $ids)
                ->delete();
        }

        return false;
    }

    public function navigate($data)
    {
        $filterBy = Arr::get($data, 'filter_by');
        foreach (['date', 'daterange', 'datetime', 'datetimerange'] as $field) {
            if ($filterBy == $field) {
                $data['filter_by'] = 'created_at';
            }
        }

        $id = $data['id'];

        $dir = isset($data['dir']) ? $data['dir'] : null;

        list($where, $args) = $this->buildWhere($data);

        $args = array_merge($args, [$id]);

        $sqlNext = "SELECT * FROM {$this->table} {$where} AND `id` > '%d' ORDER BY id LIMIT 2";
        $sqlPrev = "SELECT * FROM {$this->table} {$where} AND `id` < '%d' ORDER BY id DESC LIMIT 2";

        if ($dir == 'next') {
            $query = $this->db->prepare($sqlNext, $args);
        } else if ($dir == 'prev') {
            $query = $this->db->prepare($sqlPrev, $args);
        } else {
            foreach (['next' => $sqlNext, 'prev' => $sqlPrev] as $key => $sql) {

                $keyResult = $this->db->get_results(
                    $this->db->prepare($sql, $args)
                );

                $result[$key] = $this->formatResult($keyResult);
            }

            return $result;
        }

        $result = $this->db->get_results($query);

        if (count($result) > 1) {
            $next = true;
            $prev = true;
        } else {
            if ($dir == 'next') {
                $next = false;
                $prev = true;
            } else {
                $next = true;
                $prev = false;
            }
        }

        return [
            'log'  => $result ? $this->formatResult($result[0])[0] : null,
            'next' => $next,
            'prev' => $prev
        ];
    }

    public function find($id)
    {

        $row = $this->getDb()->table(FLUENT_MAIL_DB_PREFIX . 'email_logs')
            ->where('id', $id)
            ->first();

        $row->extra = $this->unserialize($row->extra);

        $row->response = $this->unserialize($row->response);

        return (array)$row;
    }

    public function resendEmailFromLog($id, $type = 'retry', $recipients = [])
    {
        $email = $this->find($id);

        $email['to']          = $this->unserialize($email['to']);
        $email['headers']     = $this->unserialize($email['headers']);
        $email['attachments'] = $this->unserialize($email['attachments']);
        $email['extra']       = $this->unserialize($email['extra']);

        // Convert PHPMailer attachment format to wp_mail format
        $wpMailAttachments = [];
        if (!empty($email['attachments']) && is_array($email['attachments'])) {
            foreach ($email['attachments'] as $attachment) {
                if (is_array($attachment)) {
                    // PHPMailer format: [path, filename, name, encoding, type, isString, disposition, cid]
                    if (isset($attachment[0]) && is_string($attachment[0])) {
                        $filePath = $attachment[0];
                        if (file_exists($filePath) && is_readable($filePath)) {
                            $wpMailAttachments[] = $filePath;
                        }
                    }
                } elseif (is_string($attachment)) {
                    if (file_exists($attachment) && is_readable($attachment)) {
                        $wpMailAttachments[] = $attachment;
                    }
                }
            }
        }

        // When custom recipients are provided, drop cc/bcc headers so the email
        // is only delivered to the requested address(es).
        $hasCustomRecipients = !empty($recipients) && is_array($recipients);
        $skipHeaderKeys = $hasCustomRecipients ? ['cc', 'bcc'] : [];

        $headers = [];

        foreach ($email['headers'] as $key => $value) {

            if (in_array(strtolower((string) $key), $skipHeaderKeys, true)) {
                continue;
            }

            if($key == 'content-type' && $value == 'multipart/alternative') {
                $value = 'text/html';
            }

            if (is_array($value)) {
                $values = [];
                $value = array_filter($value);
                foreach ($value as $v) {
                    if (is_array($v) && isset($v['email'])) {
                        $v = $v['email'];
                    }
                    $values[] = $v;
                }
                if ($values) {
                    $headers[] = "{$key}: " . implode(';', $values);
                }
            } else {
                if ($value) {
                    $headers[] = "{$key}: $value";
                }
            }
        }

        $headers = array_merge($headers, [
            'From: ' . $email['from']
        ]);

        if ($hasCustomRecipients) {
            $to = array_values($recipients);
        } else {
            $to = [];
            foreach ($email['to'] as $recipient) {
                if (isset($recipient['name'])) {
                    $to[] = $recipient['name'] . ' <' . $recipient['email'] . '>';
                } else {
                    $to[] = $recipient['email'];
                }
            }
        }

        try {
            if (!defined('FLUENTMAIL_LOG_OFF')) {
                define('FLUENTMAIL_LOG_OFF', true);
            }

            $startedAt = microtime(true);

            $result = wp_mail(
                $to,
                $email['subject'],
                $email['body'],
                $headers,
                $wpMailAttachments  // Use the converted attachment format
            );

            $durationMs = round((microtime(true) - $startedAt) * 1000, 1);

            $updateData = [
                'status'     => 'sent',
                'updated_at' => current_time('mysql'),
            ];

            if (!$result && $type == 'check_realtime' && $email['status'] == 'failed') {
                $updateData['status'] = 'failed';
            }

            if ($type == 'resend') {
                $updateData['resent_count'] = intval($email['resent_count']) + 1;
                $updateData['extra'] = maybe_serialize(
                    $this->appendResendRecord($email['extra'], $to, (bool)$result, $durationMs)
                );
            } else {
                $updateData['retries'] = intval($email['retries']) + 1;
            }

            if ($this->updateLog($updateData, ['id' => $id])) {
                $email                = $this->find($id);
                $email['to']          = $this->unserialize($email['to']);
                $email['headers']     = $this->unserialize($email['headers']);
                $email['attachments'] = $this->unserialize($email['attachments']);
                $email['extra']       = $this->unserialize($email['extra']);
                return $email;
            }
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            throw $e;
        }
    }

    /**
     * Record where a resend actually went.
     *
     * A resend can now be redirected to an address other than the original
     * recipient, and resent_count alone only says that it happened, not where
     * it landed - so a log row could show three resends with nothing to say
     * that two of them went to somebody else entirely. The trail lives in the
     * existing `extra` column, which needs no schema change, and the row's own
     * `to` stays untouched as the record of the original send.
     *
     * @param mixed $extra The unserialized `extra` column.
     * @param array $to    Recipients this resend was addressed to.
     * @param bool  $sent  Whether the send itself reported success.
     * @param float $ms    How long the send took, in milliseconds.
     * @return array
     */
    protected function appendResendRecord($extra, $to, $sent, $ms = null)
    {
        $extra = is_array($extra) ? $extra : [];

        $resends = [];

        if (!empty($extra['resends']) && is_array($extra['resends'])) {
            $resends = $extra['resends'];
        }

        $user = wp_get_current_user();

        $resends[] = [
            'at'   => current_time('mysql'),
            'to'   => array_values(array_map('strval', (array)$to)),
            // The display name as it stood at the time. Storing an ID would
            // leave the trail unreadable once the account is deleted, which is
            // exactly when it matters.
            'by'   => ($user && $user->exists()) ? $user->display_name : '',
            'sent' => $sent,
            'ms'   => $ms
        ];

        // Keep the tail. A row that gets resent all day should not grow its
        // extra column without bound.
        $limit = apply_filters('fluentsmtp_resend_history_limit', 20);

        if (count($resends) > $limit) {
            $resends = array_slice($resends, -$limit);
        }

        $extra['resends'] = array_values($resends);

        return $extra;
    }

    public function updateLog($data, $where)
    {
        return $this->db->update($this->table, $data, $where);
    }

    public function getStats()
    {
        // Status values are hardcoded, no need for prepare()
        $succeeded = $this->db->get_var("SELECT COUNT(id) FROM {$this->table} WHERE status = 'sent'");
        $failed = $this->db->get_var("SELECT COUNT(id) FROM {$this->table} WHERE status = 'failed'");

        return [
            'sent'   => $succeeded,
            'failed' => $failed
        ];
    }

    public function deleteLogsOlderThan($days)
    {
        try {

            $date = gmdate('Y-m-d H:i:s', current_time('timestamp') - $days * DAY_IN_SECONDS);

            /*
             * Deleted in batches rather than in one statement. A site that has
             * been logging for months can have this cron pass hit hundreds of
             * thousands of rows, and a single unbounded DELETE holds locks for
             * the whole scan - long enough to time out and leave the backlog
             * permanently uncleared, since the next run faces the same pile.
             * Batches let each statement commit and release.
             */
            $batchSize = (int)apply_filters('fluentmail_log_delete_batch_size', 2000);
            $batchSize = max(1, $batchSize);

            $deleted = 0;

            do {
                $query = $this->db->prepare(
                    "DELETE FROM {$this->table} WHERE `created_at` < %s LIMIT %d",
                    $date,
                    $batchSize
                );

                $result = $this->db->query($query);

                if (!$result) {
                    break;
                }

                $deleted += $result;
            } while ($result >= $batchSize);

            return $deleted;

        } catch (Exception $e) {
            fluentMailDebugLog('Failed to delete old email logs - ' . $e->getMessage());
        }
    }

    public function getTotalCountStat($status, $startDate, $endDate = false)
    {
        if ($endDate) {
            $query = $this->db->prepare(
                "SELECT COUNT(*)
                FROM {$this->table}
                WHERE status = %s
                AND created_at >= %s
                AND created_at <= %s",
                $status,
                $startDate,
                $endDate
            );
        } else {
            $query = $this->db->prepare(
                "SELECT COUNT(*)
                FROM {$this->table}
                WHERE status = %s
                AND created_at >= %s",
                $status,
                $startDate
            );
        }

        return (int)$this->db->get_var($query);
    }

    public function getSubjectCountStat($status, $startDate, $endDate)
    {
        $query = $this->db->prepare(
            "SELECT COUNT(DISTINCT(subject))
			FROM {$this->table}
			WHERE status = %s
			AND created_at >= %s
			AND created_at <= %s",
            $status,
            $startDate,
            $endDate
        );

        return (int)$this->db->get_var($query);
    }

    public function getSubjectStat($status, $statDate, $endDate, $limit = 5)
    {
        // Sanitize and validate limit as positive integer
        $limit = max(1, absint($limit)) ?: 5;

        $query = $this->db->prepare(
            "SELECT subject,
			COUNT(DISTINCT id) AS emails_sent
			FROM {$this->table}
			WHERE created_at >= %s
			AND created_at <= %s
			AND status = %s
			GROUP BY subject
			ORDER BY emails_sent DESC
			LIMIT %d",
            $statDate,
            $endDate,
            $status,
            $limit
        );

        return $this->db->get_results($query, ARRAY_A);
    }

}
