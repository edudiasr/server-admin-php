<?php

declare(strict_types=1);

namespace Admin;

use Admin\Db\Database;

/**
 * Configuração por variável de ambiente (ou um arquivo .env na raiz, pra rodar local).
 *
 *   PAINEL_DB_DSN    mysql:host=127.0.0.1;dbname=painel;charset=utf8mb4  ou  sqlite:/caminho/painel.sqlite
 *   PAINEL_DB_USER / PAINEL_DB_PASS
 *   PAINEL_NOME      nome do servidor que aparece no painel e no aplicativo de 2FA
 */
final class Bootstrap
{
    public static function app(): App
    {
        $root = dirname(__DIR__);
        self::loadEnvFile($root . '/.env');
        $dsn = self::env('PAINEL_DB_DSN', 'sqlite:var/painel.sqlite');
        // caminho relativo do SQLite é relativo à raiz do projeto, não à pasta de onde o comando rodou
        if (str_starts_with($dsn, 'sqlite:') && !str_starts_with($dsn, 'sqlite:/') && $dsn !== 'sqlite::memory:') {
            $dsn = 'sqlite:' . $root . '/' . substr($dsn, 7);
        }
        $db = new Database(
            $dsn,
            self::env('PAINEL_DB_USER') ?: null,
            self::env('PAINEL_DB_PASS') ?: null,
        );

        return new App($db, new Support\Clock(), self::env('PAINEL_NOME', 'Servidor'));
    }

    private static function env(string $key, string $default = ''): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }

    private static function loadEnvFile(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (getenv($key) === false) {  // variável de ambiente de verdade tem prioridade
                putenv("$key=" . trim($value, "\"'"));
            }
        }
    }
}
