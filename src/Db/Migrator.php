<?php

declare(strict_types=1);

namespace Admin\Db;

/**
 * Roda os arquivos de migrations/<driver>/ em ordem, uma vez cada.
 * O MySQL e o SQLite têm arquivo próprio porque os tipos e o auto incremento mudam.
 */
final class Migrator
{
    public function __construct(private readonly Database $db, private readonly string $dir)
    {
    }

    /** @return list<string> migrações aplicadas agora */
    public function migrate(): array
    {
        $this->db->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(100) PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)');
        $done = array_column($this->db->all('SELECT name FROM schema_migrations'), 'name');

        $files = glob($this->dir . '/' . $this->db->driver . '/*.sql') ?: [];
        sort($files);
        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            // cada comando separado por ";" no fim da linha (o MySQL não aceita vários num exec preparado)
            foreach (preg_split('/;\s*$/m', (string) file_get_contents($file)) as $statement) {
                if (trim($statement) !== '') {
                    $this->db->pdo->exec($statement);
                }
            }
            $this->db->insert('schema_migrations', ['name' => $name, 'applied_at' => gmdate('Y-m-d H:i:s')]);
            $applied[] = $name;
        }

        return $applied;
    }
}
