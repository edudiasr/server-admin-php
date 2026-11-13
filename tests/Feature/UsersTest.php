<?php

declare(strict_types=1);

namespace Admin\Tests\Feature;

use Admin\Domain\Role;
use Admin\Tests\AppTestCase;

final class UsersTest extends AppTestCase
{
    public function testAdminCreatesUsersWithValidation(): void
    {
        $this->actingAs('admin');
        $this->post('/usuarios', ['usuario' => 'raposa', 'senha' => 'senha-inicial-forte', 'cargo' => 'suporte']);
        $this->assertStringContainsString('raposa criado', $this->flashes('ok')[0]);

        $this->session->takeFlashes();
        $this->post('/usuarios', ['usuario' => 'RAPOSA', 'senha' => 'senha-inicial-forte', 'cargo' => 'suporte']);
        $this->assertStringContainsString('Já existe', $this->flashes('erro')[0]);

        $this->session->takeFlashes();
        $this->post('/usuarios', ['usuario' => 'x y', 'senha' => 'senha-inicial-forte', 'cargo' => 'suporte']);
        $this->assertStringContainsString('3 a 40 caracteres', $this->flashes('erro')[0]);

        $this->session->takeFlashes();
        $this->post('/usuarios', ['usuario' => 'tatu', 'senha' => 'senha-inicial-forte', 'cargo' => 'dono']);
        $this->assertSame(['Cargo inválido.'], $this->flashes('erro'));
    }

    public function testTheLastAdminCannotBeDemotedOrDeactivated(): void
    {
        $id = $this->actingAs('admin');
        $this->post("/usuarios/$id/cargo", ['cargo' => 'moderador']);
        $this->assertStringContainsString('último administrador', $this->flashes('erro')[0]);
        $this->assertSame(Role::Admin, $this->app->users->find($id)->role);

        // com um segundo admin, aí pode
        $this->session->takeFlashes();
        $this->createUser('admin2');
        $this->post("/usuarios/$id/cargo", ['cargo' => 'moderador']);
        $this->assertSame(Role::Moderator, $this->app->users->find($id)->role);
    }

    public function testNobodyDeactivatesThemselves(): void
    {
        $id = $this->actingAs('admin');
        $this->createUser('admin2');
        $this->post("/usuarios/$id/ativo");
        $this->assertSame(['Você não pode desativar a própria conta.'], $this->flashes('erro'));
        $this->assertTrue($this->app->users->find($id)->active);
    }

    public function testRoleChangeTakesEffectOnTheNextRequest(): void
    {
        $this->createUser('admin');
        $id = $this->actingAs('lobo', Role::Moderator);
        $this->assertSame(200, $this->get('/exportar/ban.txt')->status);

        $this->app->users->changeRole(1, $id, Role::Support);
        $this->assertSame(403, $this->get('/exportar/ban.txt')->status);
    }

    public function testModeratorCannotManageTheTeam(): void
    {
        $this->actingAs('lobo', Role::Moderator);
        $this->assertSame(403, $this->post('/usuarios', ['usuario' => 'eu2', 'senha' => 'senha-inicial-forte', 'cargo' => 'admin'])->status);
        $this->assertSame(1, $this->app->users->count());
    }

    public function testOldPasswordHashesAreUpgradedOnLogin(): void
    {
        $id = $this->createUser('lobo');
        $weak = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->app->db->run('UPDATE users SET password_hash = :h WHERE id = :id', ['h' => $weak, 'id' => $id]);

        $this->post('/login', ['usuario' => 'lobo', 'senha' => self::PASSWORD]);
        $hash = $this->app->db->value('SELECT password_hash FROM users WHERE id = :id', ['id' => $id]);
        $this->assertNotSame($weak, $hash);
        $this->assertFalse(password_needs_rehash($hash, PASSWORD_DEFAULT));
    }
}
