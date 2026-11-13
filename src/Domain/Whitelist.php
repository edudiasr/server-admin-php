<?php

declare(strict_types=1);

namespace Admin\Domain;

use Admin\Db\Database;
use Admin\Support\Clock;

final class Whitelist
{
    public const PER_PAGE = 25;

    public function __construct(private readonly Database $db, private readonly Clock $clock)
    {
    }

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    public function page(int $page, string $search = ''): array
    {
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE w.steam_id LIKE :q ESCAPE '!' OR w.player_name LIKE :q2 ESCAPE '!'";
            $params = ['q' => Database::likeContains($search), 'q2' => Database::likeContains($search)];
        }
        $total = (int) $this->db->value("SELECT COUNT(*) FROM whitelist w $where", $params);
        $rows = $this->db->all(
            "SELECT w.*, u.username AS added_by_name FROM whitelist w JOIN users u ON u.id = w.added_by
             $where ORDER BY w.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params,
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string, mixed> a linha criada */
    public function add(int $userId, string $steamInput, string $name, string $note): array
    {
        $steamId = SteamId::parse($steamInput);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 64) {
            throw new DomainError('Nome do jogador: de 1 a 64 caracteres.');
        }
        if (mb_strlen($note) > 200) {
            throw new DomainError('Observação: no máximo 200 caracteres.');
        }
        $row = [
            'steam_id' => $steamId,
            'player_name' => $name,
            'note' => trim($note),
            'added_by' => $userId,
            'created_at' => $this->clock->stamp(),
        ];
        try {
            $row['id'] = $this->db->insert('whitelist', $row);
        } catch (\PDOException $e) {
            if ($this->db->isUniqueViolation($e)) {
                throw new DomainError("O SteamID $steamId já está na whitelist.");
            }
            throw $e;
        }

        return $row;
    }

    /** @return array<string, mixed>|null a linha removida */
    public function remove(int $id): ?array
    {
        $row = $this->db->one('SELECT * FROM whitelist WHERE id = :id', ['id' => $id]);
        if ($row) {
            $this->db->run('DELETE FROM whitelist WHERE id = :id', ['id' => $id]);
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function everything(): array
    {
        return $this->db->all('SELECT steam_id, player_name, note FROM whitelist ORDER BY player_name');
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM whitelist');
    }
}
