<?php

declare(strict_types=1);

namespace Admin\Db;

use PDO;

/** PDO com as opções certas e alguns atalhos. Funciona com MySQL e SQLite. */
final class Database
{
    public readonly PDO $pdo;
    public readonly string $driver;

    public function __construct(string $dsn, ?string $user = null, ?string $password = null)
    {
        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // prepared statement de verdade no servidor, não string montada pelo PDO
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    /** @param array<string, scalar|null> $params */
    public function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @param array<string, scalar|null> $params */
    public function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, scalar|null> $params */
    public function value(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string, scalar|null> $params */
    public function run(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /** @param array<string, scalar|null> $row */
    public function insert(string $table, array $row): int
    {
        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(fn ($c) => ':' . $c, $columns)),
        );
        $this->run($sql, $row);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Escapa % e _ pra busca com LIKE não virar curinga quando o usuário digita esses caracteres.
     * Usar com ESCAPE '!': a barra invertida seria o natural, mas no MySQL ela já é escape
     * dentro de string e no SQLite não, então o mesmo SQL não funcionaria nos dois.
     */
    public static function likeContains(string $term): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
    }

    public function isUniqueViolation(\PDOException $e): bool
    {
        // MySQL 1062 / SQLite 19 (constraint) com "UNIQUE" na mensagem
        return ($e->errorInfo[1] ?? null) === 1062
            || (($e->errorInfo[1] ?? null) === 19 && str_contains($e->getMessage(), 'UNIQUE'));
    }
}
