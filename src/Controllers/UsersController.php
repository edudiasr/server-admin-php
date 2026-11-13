<?php

declare(strict_types=1);

namespace Admin\Controllers;

use Admin\Context;
use Admin\Domain\DomainError;
use Admin\Domain\Permission;
use Admin\Domain\Role;
use Admin\Http\Response;
use Admin\Security\Totp;

final class UsersController
{
    public function index(Context $c): Response
    {
        $c->require(Permission::UsersManage);

        return $c->page('users', ['people' => $c->app->users->all(), 'roles' => Role::cases()]);
    }

    public function create(Context $c): Response
    {
        $c->require(Permission::UsersManage);
        $role = Role::tryFrom($c->request->input('cargo')) ?? throw new DomainError('Cargo inválido.');
        $username = $c->request->input('usuario');
        $id = $c->app->users->create($username, $c->request->input('senha'), $role);
        $c->audit('usuario.criar', 'usuario', $id, ['usuario' => $username, 'cargo' => $role->value]);

        return $c->back('/usuarios', "Usuário $username criado. Passe a senha por um canal seguro e peça pra trocar no primeiro acesso.");
    }

    public function role(Context $c, int $id): Response
    {
        $actor = $c->require(Permission::UsersManage);
        $role = Role::tryFrom($c->request->input('cargo')) ?? throw new DomainError('Cargo inválido.');
        $before = $c->app->users->find($id) ?? throw new DomainError('Usuário não encontrado.');
        $c->app->users->changeRole($actor->id, $id, $role);
        $c->audit('usuario.cargo', 'usuario', $id, ['usuario' => $before->username, 'de' => $before->role->value, 'para' => $role->value]);

        return $c->back('/usuarios', "{$before->username} agora é {$role->label()}.");
    }

    public function toggle(Context $c, int $id): Response
    {
        $actor = $c->require(Permission::UsersManage);
        $target = $c->app->users->find($id) ?? throw new DomainError('Usuário não encontrado.');
        $c->app->users->setActive($actor->id, $id, !$target->active);
        $c->audit($target->active ? 'usuario.desativar' : 'usuario.ativar', 'usuario', $id, ['usuario' => $target->username]);

        return $c->back('/usuarios', $target->username . ($target->active ? ' foi desativado.' : ' foi reativado.'));
    }

    public function account(Context $c): Response
    {
        $session = $c->request->session;
        $secret = $session->get('totp_setup');
        if (!$c->user->totpEnabled && !is_string($secret)) {
            $secret = Totp::generateSecret();
            $session->set('totp_setup', $secret);
        }

        return $c->page('account', [
            'totpSecret' => $c->user->totpEnabled ? null : $secret,
            'totpUri' => $c->user->totpEnabled ? null : Totp::uri($c->app->serverName, $c->user->username, $secret),
        ]);
    }

    public function password(Context $c): Response
    {
        $row = $c->app->users->credentials($c->user->username);
        if (!password_verify($c->request->input('atual'), $row['password_hash'])) {
            throw new DomainError('A senha atual não confere.');
        }
        $new = $c->request->input('nova');
        if ($new !== $c->request->input('confirmacao')) {
            throw new DomainError('A confirmação não bate com a senha nova.');
        }
        $c->app->users->setPassword($c->user->id, $new);
        $c->request->session->regenerate();
        $c->audit('conta.senha', 'usuario', $c->user->id);

        return $c->back('/conta', 'Senha trocada.');
    }

    /** Liga o 2FA só depois de o usuário provar que o aplicativo gera o código certo. */
    public function totpEnable(Context $c): Response
    {
        $secret = $c->request->session->get('totp_setup');
        if (!is_string($secret)) {
            return $c->back('/conta');
        }
        $step = Totp::verify($secret, preg_replace('/\s/', '', $c->request->input('codigo')), $c->app->clock->now());
        if ($step === null) {
            throw new DomainError('Código não confere. Confira se o relógio do celular está certo e tente o código seguinte.');
        }
        $c->app->users->enableTotp($c->user->id, $secret, $step);
        $c->request->session->remove('totp_setup');
        $c->audit('conta.2fa_ligar', 'usuario', $c->user->id);

        return $c->back('/conta', 'Verificação em duas etapas ligada. Da próxima vez o login vai pedir o código.');
    }

    public function totpDisable(Context $c): Response
    {
        $row = $c->app->users->credentials($c->user->username);
        if (!password_verify($c->request->input('senha'), $row['password_hash'])) {
            throw new DomainError('Senha incorreta.');
        }
        $c->app->users->disableTotp($c->user->id);
        $c->audit('conta.2fa_desligar', 'usuario', $c->user->id);

        return $c->back('/conta', 'Verificação em duas etapas desligada.');
    }
}
