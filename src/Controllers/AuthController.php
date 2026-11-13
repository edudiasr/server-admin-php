<?php

declare(strict_types=1);

namespace Admin\Controllers;

use Admin\Context;
use Admin\Http\Response;
use Admin\Security\Auth;
use Admin\Security\LoginResult;

final class AuthController
{
    public function form(Context $c): Response
    {
        if ($c->user) {
            return Response::redirect('/');
        }

        return $c->page('login', ['username' => ''], 200);
    }

    public function login(Context $c): Response
    {
        $username = $c->request->input('usuario');
        $result = $c->auth->attempt($username, $c->request->input('senha'), $c->request->ip);

        return match ($result) {
            LoginResult::Ok => Response::redirect('/'),
            LoginResult::NeedsTotp => Response::redirect('/login/2fa'),
            // a mesma mensagem pra usuário inexistente e senha errada: não confirma quem existe
            LoginResult::Invalid => $c->page('login', ['username' => $username, 'error' => 'Usuário ou senha incorretos.'], 401),
            LoginResult::Locked => $c->page('login', [
                'username' => $username,
                'error' => 'Muitas tentativas erradas. Espere ' . intdiv(Auth::WINDOW, 60) . ' minutos e tente de novo.',
            ], 429),
        };
    }

    public function totpForm(Context $c): Response
    {
        if ($c->auth->pendingUser() === null) {
            return Response::redirect('/login');
        }

        return $c->page('login_2fa');
    }

    public function totp(Context $c): Response
    {
        if ($c->auth->pendingUser() === null) {
            return Response::redirect('/login');
        }

        return match ($c->auth->attemptTotp($c->request->input('codigo'), $c->request->ip)) {
            LoginResult::Ok => Response::redirect('/'),
            LoginResult::Locked => $c->page('login_2fa', ['error' => 'Muitas tentativas erradas. Espere alguns minutos.'], 429),
            default => $c->page('login_2fa', ['error' => 'Código inválido ou já usado.'], 401),
        };
    }

    public function logout(Context $c): Response
    {
        $c->auth->logout($c->request->ip);

        return Response::redirect('/login');
    }
}
