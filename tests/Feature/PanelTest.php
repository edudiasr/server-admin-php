<?php

declare(strict_types=1);

namespace Admin\Tests\Feature;

use Admin\Domain\Role;
use Admin\Tests\AppTestCase;

final class PanelTest extends AppTestCase
{
    private const LOBO = '76561198000010001';
    private const CORVO = '76561198000010002';

    public function testEveryPostWithoutTheCsrfTokenIsRefusedAndChangesNothing(): void
    {
        $this->actingAs('admin');
        $response = $this->post('/whitelist', ['steam_id' => self::LOBO, 'nome' => 'Lobo'], withCsrf: false);

        $this->assertSame(419, $response->status);
        $this->assertSame(0, $this->app->whitelist->count());
    }

    public function testWhitelistAcceptsTheProfileLinkAndRefusesDuplicates(): void
    {
        $this->actingAs('raposa', Role::Support);
        $this->post('/whitelist', ['steam_id' => 'https://steamcommunity.com/profiles/' . self::LOBO, 'nome' => 'Lobo', 'observacao' => 'veio do Discord']);
        $this->assertSame(['Lobo entrou na whitelist.'], $this->flashes('ok'));

        $this->session->takeFlashes();
        $this->post('/whitelist', ['steam_id' => self::LOBO, 'nome' => 'Lobo de novo']);
        $this->assertSame(['O SteamID ' . self::LOBO . ' já está na whitelist.'], $this->flashes('erro'));
        $this->assertSame(1, $this->app->whitelist->count());

        $audit = $this->lastAudit();
        $this->assertSame('whitelist.adicionar', $audit['action']);
        $this->assertSame(self::LOBO, $audit['target_id']);
    }

    public function testSearchTreatsPercentAsText(): void
    {
        $this->actingAs('admin');
        $this->post('/whitelist', ['steam_id' => self::LOBO, 'nome' => 'Lobo']);
        $this->post('/whitelist', ['steam_id' => self::CORVO, 'nome' => '100% Corvo']);

        $this->assertSame(2, $this->app->whitelist->page(1, 'o')['total']);
        $this->assertSame(1, $this->app->whitelist->page(1, '%')['total'], 'sem escape, "%" casaria com todo mundo');
        $this->assertSame(1, $this->app->whitelist->page(1, '10001')['total']);
    }

    public function testPlayerNameIsEscapedInThePage(): void
    {
        $this->actingAs('admin');
        $this->post('/whitelist', ['steam_id' => self::LOBO, 'nome' => '<script>alert(1)</script>']);
        $page = $this->get('/whitelist')->body;

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $page);
    }

    public function testSupportCanSeeBansButCannotBanOrExport(): void
    {
        $this->actingAs('raposa', Role::Support);
        $this->assertSame(200, $this->get('/bans')->status);
        $this->assertSame(403, $this->post('/bans', ['steam_id' => self::LOBO, 'nome' => 'Lobo', 'motivo' => 'teste de permissão', 'duracao' => '1'])->status);
        $this->assertSame(403, $this->get('/exportar/ban.txt')->status);
        $this->assertSame(403, $this->get('/usuarios')->status);
        $this->assertSame(0, (int) $this->app->db->value('SELECT COUNT(*) FROM bans'));
    }

    public function testTemporaryBanExpiresByItselfAndThenThePlayerCanBeBannedAgain(): void
    {
        $this->actingAs('lobo', Role::Moderator);
        $this->post('/bans', ['steam_id' => self::CORVO, 'nome' => 'Corvo', 'motivo' => 'combat log repetido', 'duracao' => '7']);
        $this->assertSame(['Corvo foi banido.'], $this->flashes('ok'));
        $this->assertSame('2026-11-20 12:00:00', $this->app->bans->activeFor(self::CORVO)['expires_at']);

        // segundo banimento enquanto o primeiro vale: recusado
        $this->session->takeFlashes();
        $this->post('/bans', ['steam_id' => self::CORVO, 'nome' => 'Corvo', 'motivo' => 'de novo', 'duracao' => '1']);
        $this->assertStringContainsString('já tem um banimento ativo', $this->flashes('erro')[0]);

        $this->clock->advance(7 * 86400);
        $this->assertNull($this->app->bans->activeFor(self::CORVO), 'no instante do vencimento o banimento já não vale');
        $this->assertSame(1, $this->app->bans->page(1, 'expirados')['total']);

        $this->session->takeFlashes();
        $this->post('/bans', ['steam_id' => self::CORVO, 'nome' => 'Corvo', 'motivo' => 'voltou a fazer', 'duracao' => 'perm']);
        $this->assertNull($this->app->bans->activeFor(self::CORVO)['expires_at']);
    }

    public function testRevokingNeedsAReasonAndHappensOnce(): void
    {
        $this->actingAs('lobo', Role::Moderator);
        $this->post('/bans', ['steam_id' => self::CORVO, 'nome' => 'Corvo', 'motivo' => 'suspeita de cheat', 'duracao' => '30']);
        $id = (int) $this->app->bans->activeFor(self::CORVO)['id'];

        $this->post("/bans/$id/revogar", ['motivo' => 'ok']);
        $this->assertStringContainsString('Explique por que', $this->flashes('erro')[0]);
        $this->assertNotNull($this->app->bans->activeFor(self::CORVO));

        $this->session->takeFlashes();
        $this->post("/bans/$id/revogar", ['motivo' => 'replay mostrou que era lag']);
        $this->assertNull($this->app->bans->activeFor(self::CORVO));
        $this->assertSame('ban.revogar', $this->lastAudit()['action']);

        $this->session->takeFlashes();
        $this->post("/bans/$id/revogar", ['motivo' => 'de novo, por engano']);
        $this->assertSame(['Esse banimento já tinha sido revogado.'], $this->flashes('erro'));
    }

    public function testExportedFilesHaveOnlyWhatIsValidNow(): void
    {
        $this->actingAs('lobo', Role::Moderator);
        $this->post('/bans', ['steam_id' => self::LOBO, 'nome' => 'Lobo', 'motivo' => 'vai expirar logo', 'duracao' => '1']);
        $this->post('/bans', ['steam_id' => self::CORVO, 'nome' => 'Corvo', 'motivo' => 'cheat confirmado', 'duracao' => 'perm']);
        $this->clock->advance(2 * 86400);

        $response = $this->get('/exportar/ban.txt');
        $this->assertSame('text/plain; charset=utf-8', $response->header('Content-Type'));
        $this->assertSame('attachment; filename="ban.txt"', $response->header('Content-Disposition'));
        $this->assertStringContainsString(self::CORVO . ' // Corvo: cheat confirmado (permanente)', $response->body);
        $this->assertStringNotContainsString(self::LOBO, $response->body);
    }

    public function testSecurityHeadersOnEveryResponse(): void
    {
        foreach ([$this->get('/login'), $this->get('/nada'), $this->post('/login', [], withCsrf: false)] as $response) {
            $this->assertStringContainsString("default-src 'self'", $response->header('Content-Security-Policy'));
            $this->assertSame('DENY', $response->header('X-Frame-Options'));
            $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        }
    }

    public function testDashboardAndAuditShowWhatHappened(): void
    {
        $this->actingAs('lobo', Role::Moderator);
        $this->post('/bans', ['steam_id' => self::CORVO, 'nome' => 'Corvo', 'motivo' => 'combat log repetido', 'duracao' => '3']);

        $dashboard = $this->get('/')->body;
        $this->assertMatchesRegularExpression('#<b>1</b><span>banimentos ativos#', $dashboard);
        $this->assertMatchesRegularExpression('#<b>1</b><span>vencem nos próximos 7 dias#', $dashboard);

        $audit = $this->get('/auditoria', ['acao' => 'ban.criar'])->body;
        $this->assertStringContainsString('combat log repetido', $audit);
        $this->assertStringNotContainsString('entrou no painel', $audit);
    }
}
