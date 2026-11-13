<?php

declare(strict_types=1);

namespace Admin\Domain;

use Admin\Db\Database;
use Admin\Support\Clock;

/** Quem fez o quê, quando e de onde. Só tem insert: o painel não apaga nem edita auditoria. */
final class AuditLog
{
    public const PER_PAGE = 30;

    public function __construct(private readonly Database $db, private readonly Clock $clock)
    {
    }

    /** @param array<string, mixed> $details */
    public function record(?int $userId, string $action, string $targetType, string|int $targetId, array $details, string $ip): void
    {
        $this->db->insert('audit_log', [
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => (string) $targetId,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip' => $ip,
            'created_at' => $this->clock->stamp(),
        ]);
    }

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function page(int $page, string $action = '', ?int $userId = null): array
    {
        $where = [];
        $params = [];
        if ($action !== '') {
            $where[] = 'a.action = :action';
            $params['action'] = $action;
        }
        if ($userId !== null) {
            $where[] = 'a.user_id = :user';
            $params['user'] = $userId;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) $this->db->value("SELECT COUNT(*) FROM audit_log a $whereSql", $params);
        $rows = $this->db->all(
            "SELECT a.*, u.username FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
             $whereSql ORDER BY a.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params,
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return list<string> */
    public function actions(): array
    {
        return array_column($this->db->all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action');
    }
}
