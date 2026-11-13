<?php

declare(strict_types=1);

namespace Admin\Domain;

use Admin\Db\Database;
use Admin\Support\Clock;

/**
 * Banimento nunca é apagado: ele expira ou é revogado (com motivo e quem revogou).
 * Assim dá pra ver o histórico de um jogador mesmo depois de ele voltar.
 */
final class Bans
{
    public const PER_PAGE = 25;

    /** duração em dias; null = permanente */
    public const DURATIONS = ['1' => 1, '3' => 3, '7' => 7, '30' => 30, 'perm' => null];

    public function __construct(private readonly Database $db, private readonly Clock $clock)
    {
    }

    /** Condição SQL de "vale agora". O :now vem do relógio do PHP pra os testes controlarem o tempo. */
    private const ACTIVE = '(b.revoked_at IS NULL AND (b.expires_at IS NULL OR b.expires_at > :now))';

    /**
     * @param 'ativos'|'expirados'|'revogados'|'todos' $status
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function page(int $page, string $status = 'ativos', string $search = ''): array
    {
        $where = [];
        $params = [];
        switch ($status) {
            case 'ativos':
                $where[] = self::ACTIVE;
                $params['now'] = $this->clock->stamp();
                break;
            case 'expirados':
                $where[] = 'b.revoked_at IS NULL AND b.expires_at IS NOT NULL AND b.expires_at <= :now';
                $params['now'] = $this->clock->stamp();
                break;
            case 'revogados':
                $where[] = 'b.revoked_at IS NOT NULL';
                break;
        }
        if ($search !== '') {
            $where[] = "(b.steam_id LIKE :q ESCAPE '!' OR b.player_name LIKE :q2 ESCAPE '!')";
            $params['q'] = Database::likeContains($search);
            $params['q2'] = Database::likeContains($search);
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = (int) $this->db->value("SELECT COUNT(*) FROM bans b $whereSql", $params);
        $rows = $this->db->all(
            "SELECT b.*, c.username AS created_by_name, r.username AS revoked_by_name
             FROM bans b JOIN users c ON c.id = b.created_by LEFT JOIN users r ON r.id = b.revoked_by
             $whereSql ORDER BY b.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params,
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function activeFor(string $steamId): ?array
    {
        return $this->db->one(
            'SELECT b.* FROM bans b WHERE b.steam_id = :s AND ' . self::ACTIVE,
            ['s' => $steamId, 'now' => $this->clock->stamp()],
        );
    }

    /** @return array<string, mixed> a linha criada */
    public function create(int $userId, string $steamInput, string $name, string $reason, string $duration): array
    {
        $steamId = SteamId::parse($steamInput);
        $name = trim($name);
        $reason = trim($reason);
        if ($name === '' || mb_strlen($name) > 64) {
            throw new DomainError('Nome do jogador: de 1 a 64 caracteres.');
        }
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 200) {
            throw new DomainError('Explique o motivo (de 5 a 200 caracteres). Ele aparece no ban.txt e no histórico.');
        }
        if (!array_key_exists($duration, self::DURATIONS)) {
            throw new DomainError('Duração inválida.');
        }
        $days = self::DURATIONS[$duration];
        $now = $this->clock->now();

        // a checagem e o insert na mesma transação: dois moderadores banindo o mesmo jogador
        // ao mesmo tempo não geram dois banimentos ativos
        return $this->db->transaction(function () use ($userId, $steamId, $name, $reason, $days, $now) {
            if ($this->db->driver === 'mysql') {
                $this->db->value('SELECT GET_LOCK(:k, 5)', ['k' => 'ban-' . $steamId]);
            }
            try {
                if ($existing = $this->activeFor($steamId)) {
                    throw new DomainError(sprintf(
                        'Esse jogador já tem um banimento ativo (#%d, %s).',
                        $existing['id'],
                        $existing['expires_at'] ? 'até ' . $existing['expires_at'] . ' UTC' : 'permanente',
                    ));
                }
                $row = [
                    'steam_id' => $steamId,
                    'player_name' => $name,
                    'reason' => $reason,
                    'created_by' => $userId,
                    'created_at' => $this->clock->stamp($now),
                    'expires_at' => $days === null ? null : $this->clock->stamp($now + $days * 86400),
                ];
                $row['id'] = $this->db->insert('bans', $row);

                return $row;
            } finally {
                if ($this->db->driver === 'mysql') {
                    $this->db->value('SELECT RELEASE_LOCK(:k)', ['k' => 'ban-' . $steamId]);
                }
            }
        });
    }

    /** @return array<string, mixed> a linha antes da revogação */
    public function revoke(int $userId, int $id, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 200) {
            throw new DomainError('Explique por que o banimento está sendo revogado (de 5 a 200 caracteres).');
        }
        $ban = $this->db->one('SELECT * FROM bans WHERE id = :id', ['id' => $id]) ?? throw new DomainError('Banimento não encontrado.');
        $changed = $this->db->run(
            'UPDATE bans SET revoked_at = :t, revoked_by = :u, revoke_reason = :r WHERE id = :id AND revoked_at IS NULL',
            ['t' => $this->clock->stamp(), 'u' => $userId, 'r' => $reason, 'id' => $id],
        );
        if ($changed === 0) {
            throw new DomainError('Esse banimento já tinha sido revogado.');
        }

        return $ban;
    }

    /** @return list<array<string, mixed>> */
    public function allActive(): array
    {
        return $this->db->all(
            'SELECT b.* FROM bans b WHERE ' . self::ACTIVE . ' ORDER BY b.steam_id',
            ['now' => $this->clock->stamp()],
        );
    }

    /** @return array{active: int, expiring: int, today: int} */
    public function summary(): array
    {
        $now = $this->clock->now();

        return [
            'active' => (int) $this->db->value('SELECT COUNT(*) FROM bans b WHERE ' . self::ACTIVE, ['now' => $this->clock->stamp()]),
            'expiring' => (int) $this->db->value(
                'SELECT COUNT(*) FROM bans b WHERE b.revoked_at IS NULL AND b.expires_at > :now AND b.expires_at <= :limit',
                ['now' => $this->clock->stamp(), 'limit' => $this->clock->stamp($now + 7 * 86400)],
            ),
            'today' => (int) $this->db->value(
                'SELECT COUNT(*) FROM bans b WHERE b.created_at >= :since',
                ['since' => $this->clock->stamp($now - 86400)],
            ),
        ];
    }
}
