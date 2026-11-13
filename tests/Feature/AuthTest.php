<?php

declare(strict_types=1);

namespace Admin\Tests\Feature;

use Admin\Domain\Role;
use Admin\Security\Auth;
use Admin\Security\Totp;
use Admin\Tests\AppTestCase;

final class AuthTest extends AppTestCase
{
    public function testAnonymousVisitorIsSentToLogin(): void
    {
        foreach (['/', '/whitelist', '/bans', '/usuarios', '/exportar/ban.txt'] as $path) {
            $this->assertSame('/login', $this->get($path)->header('Location'), $path);
        }
        $this->assertSame(200, $this->get('/login')->status);
    }

    public function testLoginChangesTheSessionIdAndRecordsIt(): void
    {
        $this->createUser('lobo');
        $response = $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD]);

        $this->assertSame(303, $response->status);
        $this->assertSame('/', $response->header('Location'));
        $this->assertSame(1, $this->session->regenerations, 'sem trocar o id no login, a sessão fica sujeita a fixação');
        $this->assertSame('login', $this->lastAudit()['action']);
        $this->assertSame(200, $this->get('/')->status);
    }

    public function testUsernameIsCaseInsensitive(): void
    {
        $this->createUser('Lobo');
        $this->assertSame('/', $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD])->header('Location'));
    }

    public function testWrongPasswordAndUnknownUserLookTheSame(): void
    {
        $this->createUser('lobo');
        $wrong = $this->post('/login', ['usuario' => 'lobo', 'senha' => 'errada-errada-errada']);
        $ghost = $this->post('/login', ['usuario' => 'ninguem', 'senha' => 'errada-errada-errada']);

        $this->assertSame(401, $wrong->status);
        $this->assertSame(401, $ghost->status);
        $this->assertStringContainsString('Usuário ou senha incorretos.', $wrong->body);
        $this->assertStringContainsString('Usuário ou senha incorretos.', $ghost->body);
    }

    public function testFiveFailuresLockTheAccountForFifteenMinutesEvenWithTheRightPassword(): void
    {
        $this->createUser('lobo');
        for ($i = 0; $i < Auth::MAX_USER_FAILURES; $i++) {
            $this->post('/login', ['usuario' => 'lobo', 'senha' => "chute-$i-xxxxxxx"]);
        }
        $locked = $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD]);
        $this->assertSame(429, $locked->status);
        $this->assertSame('login.bloqueado', $this->lastAudit()['action']);

        $this->clock->advance(Auth::WINDOW + 1);
        $this->assertSame('/', $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD])->header('Location'));
    }

    public function testOneIpTryingManyUsersGetsBlocked(): void
    {
        $this->createUser('lobo');
        for ($i = 0; $i < Auth::MAX_IP_FAILURES; $i++) {
            $this->post('/login', ['usuario' => "usuario$i", 'senha' => 'qualquer-coisa-123'], '198.51.100.7');
        }
        $this->assertSame(429, $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD], '198.51.100.7')->status);
        // de outro IP a conta continua acessível: o bloqueio por IP não tranca o dono da conta
        $this->assertSame(303, $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD], '203.0.113.99')->status);
    }

    public function testDeactivatedUserLosesAnOpenSessionImmediately(): void
    {
        $this->createUser('admin2');
        $id = $this->actingAs('lobo', Role::Moderator);
        $this->assertSame(200, $this->get('/bans')->status);

        $this->app->users->setActive(0, $id, false);
        $this->assertSame('/login', $this->get('/bans')->header('Location'));
    }

    public function testLogoutNeedsCsrfAndEndsTheSession(): void
    {
        $this->actingAs('lobo');
        $this->assertSame(419, $this->post('/sair', ['_csrf' => 'falso'])->status);
        $this->assertSame(200, $this->get('/')->status);

        $this->post('/sair');
        $this->assertSame('/login', $this->get('/')->header('Location'));
    }

    public function testTwoFactorFlowFromSetupToLogin(): void
    {
        $this->actingAs('lobo');
        $this->get('/conta');
        $secret = $this->session->get('totp_setup');
        $this->assertIsString($secret);

        // código errado não liga
        $this->post('/conta/2fa', ['codigo' => '000000']);
        $this->assertNotEmpty($this->flashes('erro'));
        $this->assertNull($this->app->db->value("SELECT totp_secret FROM users WHERE username = 'lobo'"));

        $this->post('/conta/2fa', ['codigo' => Totp::code($secret, $this->clock->now())]);
        $this->assertSame($secret, $this->app->db->value("SELECT totp_secret FROM users WHERE username = 'lobo'"));

        // novo login: a senha sozinha não entra
        $this->post('/sair');
        $this->assertSame('/login/2fa', $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD])->header('Location'));
        $this->assertSame('/login', $this->get('/')->header('Location'));

        // o código que ligou o 2FA (mesmo intervalo de 30 s) não serve de novo
        $this->assertSame(401, $this->post('/login/2fa', ['codigo' => Totp::code($secret, $this->clock->now())])->status);

        $this->clock->advance(30);
        $code = Totp::code($secret, $this->clock->now());
        $this->assertSame('/', $this->post('/login/2fa', ['codigo' => $code])->header('Location'));
        $this->assertSame(200, $this->get('/')->status);

        // e depois de usado, o mesmo código não abre outra sessão
        $this->post('/sair');
        $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD]);
        $this->assertSame(401, $this->post('/login/2fa', ['codigo' => $code])->status);
    }

    public function testPendingTwoFactorExpires(): void
    {
        $id = $this->createUser('lobo');
        $secret = Totp::generateSecret();
        $this->app->users->enableTotp($id, $secret, 0);

        $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD]);
        $this->clock->advance(301);
        $response = $this->post('/login/2fa', ['codigo' => Totp::code($secret, $this->clock->now())]);
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testPasswordChangeChecksTheCurrentOneAndTheMinimumLength(): void
    {
        $this->actingAs('lobo');
        $this->post('/conta/senha', ['atual' => 'errada', 'nova' => 'nova-senha-boa-1', 'confirmacao' => 'nova-senha-boa-1']);
        $this->assertSame(['A senha atual não confere.'], $this->flashes('erro'));

        $this->session->takeFlashes();
        $this->post('/conta/senha', ['atual' => self::PASSWORD, 'nova' => 'curta', 'confirmacao' => 'curta']);
        $this->assertStringContainsString('12 caracteres', $this->flashes('erro')[0]);

        $this->session->takeFlashes();
        $this->post('/conta/senha', ['atual' => self::PASSWORD, 'nova' => 'nova-senha-boa-1', 'confirmacao' => 'nova-senha-boa-1']);
        $this->assertSame(['Senha trocada.'], $this->flashes('ok'));

        $this->post('/sair');
        $this->assertSame(401, $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD])->status);
        $this->assertSame(303, $this->post('/login', ['usuario' => 'lobo', 'senha' => 'nova-senha-boa-1'])->status);
    }
}
