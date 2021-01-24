<?php

namespace FluentMail\App\Models;

use Exception;
use FluentMail\App\Services\Mailer\EmailQueueProcessor;

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
        'subject',
        'body',
        'response',
        'extra'
    ];

    protected $table = null;

    public function __construct()
    {
        parent::__construct();

        $this->table = $this->db->prefix . FLUENT_MAIL_DB_PREFIX . 'email_logs';
    }

    public function get($data)
    {
        foreach (['date', 'daterange'] as $field) {
            if ($data['filter_by'] == $field) {
                $data['filter_by'] = 'created_at';
            }
        }

        $total = 0;
        $page = intval($data['page']);
        $perPage = intval($data['per_page']);
        $offset = ($page - 1) * $perPage;

        list($where, $args) = $this->buildWhere($data);

        $query = $this->db->prepare(
            "SELECT * FROM `{$this->table}` {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
            array_merge($args, [$perPage, $offset])
        );


        $result = $this->db->get_results($query);

        if ($this->db->num_rows) {
            $total = (int)$this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(id) FROM `{$this->table}` {$where}", $args
                )
            );
        }

        $result = $this->formatResult($result);

        return [
            'data'  => $result,
            'total' => $total
        ];
    }

    protected function buildWhere($data)
    {
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
                        $args[] = '%'.$this->db->esc_like($itemValue).'%';
                        $nestedOr .= " OR `{$key}` LIKE '%s'";
                    }
                    $orWhere .= ' OR (' . trim($nestedOr, 'OR ') . ')';
                } else {
                    $args[] = '%'.$this->db->esc_like($value).'%';
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

            $this->db->insert($this->table, $data);

            return $this->db->insert_id;
        } catch (Exception $e) {
            return $e;
        }
    }

    public function delete(array $id)
    {
        if ($id && $id[0] == 'all') {
            return $this->db->query("TRUNCATE TABLE {$this->table}");
        }

        $placeHolders = array_fill(0, count($id), '%d');

        $query = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE id IN (" . implode(',', $placeHolders) . ")",
            $id
        );

        return $this->db->query($query);
    }

    public function navigate($data)
    {
        foreach (['date', 'daterange', 'datetime', 'datetimerange'] as $field) {
            if ($data['filter_by'] == $field) {
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
        $query = $this->db->prepare(
            "SELECT * FROM `{$this->table}` WHERE `id` = %d",
            [$id]
        );

        $row = $this->db->get_row($query, ARRAY_A);

        $row['extra'] = maybe_unserialize($row['extra']);

        $row['response'] = maybe_unserialize($row['response']);

        return $row;
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

            wp_mail(
                $to,
                $email['subject'],
                $email['body'],
                $headers,
                $email['attachments']
            );

            $updateData = [
                'status' => 'sent',
                'updated_at' =>  current_time('mysql'),
            ];
            
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

            $date = date('Y-m-d', current_time('timestamp') - $days * DAY_IN_SECONDS);

            $query = "DELETE FROM {$this->table} WHERE `created_at` < $date";

            return $this->db->query($query);

        } catch (Exception $e) {
            if (wp_get_environment_type() != 'production') {
                error_log('Message: ' . $e->getMessage());
            }
        }
    }

    public function hasPendingEmails()
    {
        $status = 'pending';

        $query = $this->db->prepare(
            "SELECT count(*) as total FROM `{$this->table}` WHERE status = '%s'",
            $status
        );

        $col = $this->db->get_col($query);

        return reset($col);
    }

    public function getPendingEmails($limit = 10, $order = 'ASC', $exclude = [])
    {
        return $this->getEmails($limit, $order, 'pending', $exclude);
    }

    public function getEmails($limit = 10, $order = 'ASC', $status = null, $exclude = [])
    {
        $where = '';

        if (!$status) {
            $where = "WHERE `status` != '{$status}'";
        } else {
            $where = "WHERE `status` = '{$status}'";
        }

        if (is_array($exclude) && $exclude) {
            $ids = implode(',', $exclude);
            $where .= " AND id NOT IN ({$ids})";
        }

        $orderBy = "ORDER BY `created_at` {$order}";

        $query = "SELECT * FROM `{$this->table}` {$where} {$orderBy} LIMIT %d";
        $query = $this->db->prepare($query, $limit);
        return $this->db->get_results($query);
    }
}
