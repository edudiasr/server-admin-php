<?php

declare(strict_types=1);

namespace Admin\Tests\Unit;

use Admin\Db\Database;
use Admin\Domain\DomainError;
use Admin\Domain\Permission;
use Admin\Domain\Role;
use Admin\Domain\ServerFiles;
use Admin\Domain\SteamId;
use Admin\Http\Router;
use PHPUnit\Framework\TestCase;

final class SmallPiecesTest extends TestCase
{
    public function testSteamIdAcceptsTheNumberOrTheProfileLink(): void
    {
        $this->assertSame('76561198000010001', SteamId::parse(' 76561198000010001 '));
        $this->assertSame('76561198000010001', SteamId::parse('https://steamcommunity.com/profiles/76561198000010001/'));
    }

    public function testSteamIdRejectsWhatIsNotAnIndividualAccount(): void
    {
        foreach (['', '7656119800001000', '765611980000100011', 'steam:abc', '12345678901234567', '76561197960265727'] as $bad) {
            try {
                SteamId::parse($bad);
                $this->fail("aceitou $bad");
            } catch (DomainError) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRouterExtractsIdsAndSeparates404From405(): void
    {
        $router = new Router();
        $router->post('/bans/{id}/revogar', fn () => 'ok');
        $router->get('/bans', fn () => 'lista');

        [$handler, $params] = $router->match('POST', '/bans/42/revogar');
        $this->assertSame('ok', $handler());
        $this->assertSame(['id' => 42], $params);
        $this->assertSame(405, $router->match('GET', '/bans/42/revogar'));
        $this->assertSame(404, $router->match('POST', '/bans/abc/revogar'));
        $this->assertSame(404, $router->match('GET', '/nada'));
    }

    public function testRolesAreWhatTheTeamExpects(): void
    {
        $this->assertTrue(Role::Admin->can(Permission::UsersManage));
        $this->assertFalse(Role::Moderator->can(Permission::UsersManage));
        $this->assertTrue(Role::Moderator->can(Permission::BansCreate));
        $this->assertFalse(Role::Support->can(Permission::BansCreate));
        $this->assertFalse(Role::Support->can(Permission::Export));
        $this->assertTrue(Role::Support->can(Permission::WhitelistEdit));
    }

    public function testBanTxtFormatAndNoLineInjection(): void
    {
        $txt = ServerFiles::banTxt([
            ['steam_id' => '76561198000010001', 'player_name' => "Nome\n76561198000099999", 'reason' => 'cheat', 'expires_at' => null],
            ['steam_id' => '76561198000010002', 'player_name' => 'Outro', 'reason' => 'spam', 'expires_at' => '2026-12-01 00:00:00'],
        ], '2026-11-13 12:00:00');
        $lines = explode("\n", trim($txt));

        $this->assertCount(4, $lines, 'o nome com quebra de linha não pode virar uma linha nova');
        $this->assertSame('76561198000010001 // Nome 76561198000099999: cheat (permanente)', $lines[2]);
        $this->assertSame('76561198000010002 // Outro: spam (até 2026-12-01 00:00:00)', $lines[3]);
    }

    public function testLikeSearchTreatsWildcardsAsText(): void
    {
        $this->assertSame('%100!%!_ok!!%', Database::likeContains('100%_ok!'));
    }
}
