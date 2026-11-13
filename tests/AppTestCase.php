<?php

declare(strict_types=1);

namespace Admin\Tests;

use Admin\App;
use Admin\Db\Database;
use Admin\Db\Migrator;
use Admin\Domain\Role;
use Admin\Http\Request;
use Admin\Http\Response;
use Admin\Security\ArraySession;
use Admin\Support\Clock;
use PHPUnit\Framework\TestCase;

/**
 * Cada teste ganha um banco novo e um "navegador" (sessão em memória) e conversa com o painel
 * pelo App::handle, do mesmo jeito que o index.php faz, só que sem servidor HTTP.
 *
 * Por padrão usa SQLite em memória. Com PAINEL_TEST_MYSQL="mysql:host=127.0.0.1;port=3306"
 * (mais PAINEL_TEST_MYSQL_USER/PASS) roda tudo contra um MySQL de verdade, num banco criado
 * e apagado a cada teste.
 */
abstract class AppTestCase extends TestCase
{
    public const PASSWORD = 'senha-de-teste-123';

    protected App $app;
    protected Clock $clock;
    protected ArraySession $session;
    private ?string $mysqlDb = null;

    protected function setUp(): void
    {
        $this->clock = new Clock(strtotime('2026-11-13 12:00:00 UTC'));
        $this->app = new App($this->database(), $this->clock, 'Servidor Teste');
        (new Migrator($this->app->db, dirname(__DIR__) . '/migrations'))->migrate();
        $this->session = new ArraySession();
    }

    protected function tearDown(): void
    {
        if ($this->mysqlDb !== null) {
            $this->app->db->pdo->exec("DROP DATABASE `{$this->mysqlDb}`");
        }
    }

    private function database(): Database
    {
        $server = getenv('PAINEL_TEST_MYSQL');
        if (!$server) {
            return new Database('sqlite::memory:');
        }
        $user = getenv('PAINEL_TEST_MYSQL_USER') ?: 'root';
        $pass = getenv('PAINEL_TEST_MYSQL_PASS') ?: '';
        $this->mysqlDb = 'painel_teste_' . bin2hex(random_bytes(4));
        (new Database($server, $user, $pass))->pdo->exec("CREATE DATABASE `{$this->mysqlDb}` CHARACTER SET utf8mb4");

        return new Database("$server;dbname={$this->mysqlDb};charset=utf8mb4", $user, $pass);
    }

    protected function createUser(string $name, Role $role = Role::Admin): int
    {
        return $this->app->users->create($name, self::PASSWORD, $role);
    }

    /** Cria o usuário e já faz login com ele (sessão nova, como um navegador limpo). */
    protected function actingAs(string $name, Role $role = Role::Admin): int
    {
        $id = $this->createUser($name, $role);
        $this->session = new ArraySession();
        $response = $this->post('/login', ['usuario' => $name, 'senha' => self::PASSWORD]);
        $this->assertSame('/', $response->header('Location'), 'login de teste falhou');

        return $id;
    }

    protected function get(string $path, array $query = [], string $ip = '203.0.113.10'): Response
    {
        return $this->app->handle(new Request('GET', $path, $query, [], $ip, $this->session));
    }

    /** POST com o token CSRF da sessão, como o formulário do painel manda. */
    protected function post(string $path, array $data = [], string $ip = '203.0.113.10', bool $withCsrf = true): Response
    {
        if ($withCsrf) {
            $data['_csrf'] ??= (new \Admin\Security\Csrf($this->session))->token();
        }

        return $this->app->handle(new Request('POST', $path, [], $data, $ip, $this->session));
    }

    /** @return list<string> mensagens que vão aparecer na próxima página */
    protected function flashes(string $type): array
    {
        $all = $this->session->get('_flash', []);

        return array_column(array_filter($all, fn ($f) => $f['type'] === $type), 'message');
    }

    protected function lastAudit(): array
    {
        return $this->app->db->one('SELECT * FROM audit_log ORDER BY id DESC LIMIT 1') ?? [];
    }
}
