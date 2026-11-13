<?php

declare(strict_types=1);

namespace Admin;

use Admin\Db\Database;
use Admin\Domain\AuditLog;
use Admin\Domain\Bans;
use Admin\Domain\DomainError;
use Admin\Domain\Permission;
use Admin\Domain\User;
use Admin\Domain\Users;
use Admin\Domain\Whitelist;
use Admin\Http\Request;
use Admin\Http\Response;
use Admin\Http\Router;
use Admin\Security\Auth;
use Admin\Security\Csrf;
use Admin\Support\Clock;

/**
 * Monta as peças e atende uma requisição. Não toca em $_SERVER nem em header():
 * recebe um Request e devolve um Response, então os testes chamam handle() direto.
 */
final class App
{
    public readonly Users $users;
    public readonly Whitelist $whitelist;
    public readonly Bans $bans;
    public readonly AuditLog $audit;

    public function __construct(
        public readonly Database $db,
        public readonly Clock $clock = new Clock(),
        public readonly string $serverName = 'Servidor',
    ) {
        $this->users = new Users($db, $clock);
        $this->whitelist = new Whitelist($db, $clock);
        $this->bans = new Bans($db, $clock);
        $this->audit = new AuditLog($db, $clock);
    }

    public function handle(Request $request): Response
    {
        $csrf = new Csrf($request->session);
        $auth = new Auth($this->db, $this->users, $this->audit, $request->session, $this->clock);
        $view = new View(dirname(__DIR__) . '/templates', ['csrf' => $csrf->token(), 'serverName' => $this->serverName]);

        try {
            $response = $this->dispatch($request, $auth, $csrf, $view);
        } catch (Forbidden) {
            $response = Response::html($view->render('error', ['title' => 'Sem permissão', 'message' => 'Seu cargo não dá acesso a essa página.']), 403);
        }

        return $this->secure($response, $request);
    }

    private function dispatch(Request $request, Auth $auth, Csrf $csrf, View $view): Response
    {
        if ($request->method === 'POST' && !$csrf->valid($request->input('_csrf'))) {
            return Response::html($view->render('error', [
                'title' => 'Formulário expirado',
                'message' => 'Recarregue a página e tente de novo.',
            ]), 419);
        }

        $match = $this->routes()->match($request->method, $request->path);
        if (is_int($match)) {
            return Response::html($view->render('error', ['title' => 'Página não encontrada', 'message' => '']), $match);
        }
        [$handler, $params] = $match;

        $public = in_array($request->path, ['/login', '/login/2fa'], true);
        $user = $auth->user();
        if (!$public && $user === null) {
            return Response::redirect('/login');
        }
        if ($user !== null) {
            $view->share('user', $user);
        }
        $view->share('flashes', []);

        $ctx = new Context($request, $auth, $view, $user, $this);
        try {
            $response = $handler($ctx, ...$params);
        } catch (DomainError $e) {
            // regra de negócio: volta pra página de onde veio com a mensagem
            $request->session->flash('erro', $e->getMessage());
            $response = Response::redirect($request->path === '/' ? '/' : $request->path);
        }

        return $response;
    }

    private function routes(): Router
    {
        $r = new Router();
        $auth = new Controllers\AuthController();
        $panel = new Controllers\PanelController();
        $people = new Controllers\UsersController();

        $r->get('/login', $auth->form(...));
        $r->post('/login', $auth->login(...));
        $r->get('/login/2fa', $auth->totpForm(...));
        $r->post('/login/2fa', $auth->totp(...));
        $r->post('/sair', $auth->logout(...));

        $r->get('/', $panel->dashboard(...));
        $r->get('/whitelist', $panel->whitelist(...));
        $r->post('/whitelist', $panel->whitelistAdd(...));
        $r->post('/whitelist/{id}/remover', $panel->whitelistRemove(...));
        $r->get('/bans', $panel->bans(...));
        $r->post('/bans', $panel->banCreate(...));
        $r->post('/bans/{id}/revogar', $panel->banRevoke(...));
        $r->get('/exportar/ban.txt', $panel->exportBans(...));
        $r->get('/exportar/whitelist.txt', $panel->exportWhitelist(...));
        $r->get('/auditoria', $panel->audit(...));

        $r->get('/usuarios', $people->index(...));
        $r->post('/usuarios', $people->create(...));
        $r->post('/usuarios/{id}/cargo', $people->role(...));
        $r->post('/usuarios/{id}/ativo', $people->toggle(...));
        $r->get('/conta', $people->account(...));
        $r->post('/conta/senha', $people->password(...));
        $r->post('/conta/2fa', $people->totpEnable(...));
        $r->post('/conta/2fa/desligar', $people->totpDisable(...));

        return $r;
    }

    private function secure(Response $response, Request $request): Response
    {
        $response->headers += [
            // só scripts e estilos do próprio painel; nada inline, nada de fora
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'X-Frame-Options' => 'DENY',
            'Cache-Control' => 'no-store',
        ];
        if ($request->https) {
            $response->headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        return $response;
    }
}
