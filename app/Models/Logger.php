<?php

namespace FluentMail\App\Models;

use Exception;
use FluentMail\Includes\Support\Arr;

class Logger extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED = 'failed';
    const STATUS_SENT = 'sent';

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
        $db = $this->getDb();
        $page = isset($data['page']) ? (int)$data['page'] : 1;
        $perPage = isset($data['per_page']) ? (int)$data['per_page'] : 15;
        $offset = ($page - 1) * $perPage;

        $query = $db->table(FLUENT_MAIL_DB_PREFIX . 'email_logs')
            ->limit($perPage)
            ->offset($offset)
            ->orderBy('id', 'DESC');

        if (!empty($data['status'])) {
            $query->where('status', sanitize_text_field($data['status']));
        }

        if (!empty($data['date_range']) && is_array($data['date_range']) && count($data['date_range']) == 2) {
            $dateRange = $data['date_range'];
            $from = $dateRange[0] . ' 00:00:01';
            $to = $dateRange[1] . ' 23:59:59';
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
            $result[$key] = array_map('maybe_unserialize', (array)$row);
            $result[$key]['id'] = (int)$result[$key]['id'];
            $result[$key]['retries'] = (int)$result[$key]['retries'];
            $result[$key]['from'] = htmlspecialchars($result[$key]['from']);
        }

        return $result;
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

        $row->extra = maybe_unserialize($row->extra);

        $row->response = maybe_unserialize($row->response);

        return (array)$row;
    }

    public function resendEmailFromLog($id, $type = 'retry')
    {
        $email = $this->find($id);

        $email['to'] = maybe_unserialize($email['to']);
        $email['headers'] = maybe_unserialize($email['headers']);
        $email['attachments'] = maybe_unserialize($email['attachments']);
        $email['extra'] = maybe_unserialize($email['extra']);

        $headers = [];

        foreach ($email['headers'] as $key => $value) {
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

        $to = [];
        foreach ($email['to'] as $recipient) {
            if (isset($recipient['name'])) {
                $to[] = $recipient['name'] . ' <' . $recipient['email'] . '>';
            } else {
                $to[] = $recipient['email'];
            }
        }

        try {
            if (!defined('FLUENTMAIL_LOG_OFF')) {
                define('FLUENTMAIL_LOG_OFF', true);
            }

            $result = wp_mail(
                $to,
                $email['subject'],
                $email['body'],
                $headers,
                $email['attachments']
            );

            $updateData = [
                'status'     => 'sent',
                'updated_at' => current_time('mysql'),
            ];

            if (!$result && $type == 'check_realtime' && $email['status'] == 'failed') {
                $updateData['status'] = 'failed';
            }

            if ($type == 'resend') {
                $updateData['resent_count'] = intval($email['resent_count']) + 1;
            } else {
                $updateData['retries'] = intval($email['retries']) + 1;
            }

            if ($this->updateLog($updateData, ['id' => $id])) {
                $email = $this->find($id);
                $email['to'] = maybe_unserialize($email['to']);
                $email['headers'] = maybe_unserialize($email['headers']);
                $email['attachments'] = maybe_unserialize($email['attachments']);
                $email['extra'] = maybe_unserialize($email['extra']);
                return $email;
            }
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            throw $e;
        }
    }

    public function updateLog($data, $where)
    {
        return $this->db->update($this->table, $data, $where);
    }

    public function getStats()
    {
        $succeeded = $this->db->get_var("select COUNT(id) from {$this->table} where status='sent'");
        $failed = $this->db->get_var("select COUNT(id) from {$this->table} where status='failed'");

        return [
            'sent'   => $succeeded,
            'failed' => $failed
        ];
    }

    public function deleteLogsOlderThan($days)
    {
        try {

            $date = date('Y-m-d H:i:s', current_time('timestamp') - $days * DAY_IN_SECONDS);
            $query = $this->db->prepare("DELETE FROM {$this->table} WHERE `created_at` < %s", $date);
            return $this->db->query($query);

        } catch (Exception $e) {
            if (wp_get_environment_type() != 'production') {
                error_log('Message: ' . $e->getMessage());
            }
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
        $query = $this->db->prepare(
            "SELECT subject,
			COUNT(DISTINCT id) AS emails_sent
			FROM {$this->table}
			WHERE created_at >= %s
			AND created_at <= %s
			AND status = %s
			GROUP BY subject
			ORDER BY emails_sent DESC
			LIMIT {$limit}",
            $statDate,
            $endDate,
            $status
        );

        return $this->db->get_results($query, ARRAY_A);
    }

}
