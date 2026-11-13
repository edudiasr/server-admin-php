<?php

declare(strict_types=1);

namespace Admin\Controllers;

use Admin\Context;
use Admin\Domain\Bans;
use Admin\Domain\Permission;
use Admin\Domain\ServerFiles;
use Admin\Http\Response;

final class PanelController
{
    public function dashboard(Context $c): Response
    {
        $app = $c->app;

        return $c->page('dashboard', [
            'whitelisted' => $app->whitelist->count(),
            'bans' => $app->bans->summary(),
            'recent' => $c->user->can(Permission::AuditView) ? $app->audit->page(1)['rows'] : [],
        ]);
    }

    public function whitelist(Context $c): Response
    {
        $c->require(Permission::WhitelistView);
        $search = $c->request->queryParam('busca');
        $page = $c->request->page();

        return $c->page('whitelist', [
            'result' => $c->app->whitelist->page($page, $search),
            'search' => $search,
            'currentPage' => $page,
            'perPage' => $c->app->whitelist::PER_PAGE,
        ]);
    }

    public function whitelistAdd(Context $c): Response
    {
        $user = $c->require(Permission::WhitelistEdit);
        $row = $c->app->whitelist->add(
            $user->id,
            $c->request->input('steam_id'),
            $c->request->input('nome'),
            $c->request->input('observacao'),
        );
        $c->audit('whitelist.adicionar', 'steam', $row['steam_id'], ['nome' => $row['player_name'], 'observacao' => $row['note']]);

        return $c->back('/whitelist', "{$row['player_name']} entrou na whitelist.");
    }

    public function whitelistRemove(Context $c, int $id): Response
    {
        $c->require(Permission::WhitelistEdit);
        $row = $c->app->whitelist->remove($id);
        if ($row === null) {
            return $c->back('/whitelist');
        }
        $c->audit('whitelist.remover', 'steam', $row['steam_id'], ['nome' => $row['player_name']]);

        return $c->back('/whitelist', "{$row['player_name']} saiu da whitelist.");
    }

    public function bans(Context $c): Response
    {
        $c->require(Permission::BansView);
        $status = $c->request->queryParam('status', 'ativos');
        if (!in_array($status, ['ativos', 'expirados', 'revogados', 'todos'], true)) {
            $status = 'ativos';
        }
        $search = $c->request->queryParam('busca');
        $page = $c->request->page();

        return $c->page('bans', [
            'result' => $c->app->bans->page($page, $status, $search),
            'status' => $status,
            'now' => $c->app->clock->stamp(),
            'search' => $search,
            'currentPage' => $page,
            'perPage' => Bans::PER_PAGE,
        ]);
    }

    public function banCreate(Context $c): Response
    {
        $user = $c->require(Permission::BansCreate);
        $ban = $c->app->bans->create(
            $user->id,
            $c->request->input('steam_id'),
            $c->request->input('nome'),
            $c->request->input('motivo'),
            $c->request->input('duracao'),
        );
        $c->audit('ban.criar', 'steam', $ban['steam_id'], [
            'ban' => $ban['id'], 'nome' => $ban['player_name'], 'motivo' => $ban['reason'], 'ate' => $ban['expires_at'],
        ]);

        return $c->back('/bans', "{$ban['player_name']} foi banido.");
    }

    public function banRevoke(Context $c, int $id): Response
    {
        $user = $c->require(Permission::BansRevoke);
        $reason = $c->request->input('motivo');
        $ban = $c->app->bans->revoke($user->id, $id, $reason);
        $c->audit('ban.revogar', 'steam', $ban['steam_id'], ['ban' => $id, 'nome' => $ban['player_name'], 'motivo' => trim($reason)]);

        return $c->back('/bans', "Banimento de {$ban['player_name']} revogado.");
    }

    public function exportBans(Context $c): Response
    {
        $c->require(Permission::Export);
        $c->audit('exportar.ban', 'arquivo', 'ban.txt');

        return Response::text(ServerFiles::banTxt($c->app->bans->allActive(), $c->app->clock->stamp()), 'ban.txt');
    }

    public function exportWhitelist(Context $c): Response
    {
        $c->require(Permission::Export);
        $c->audit('exportar.whitelist', 'arquivo', 'whitelist.txt');

        return Response::text(ServerFiles::whitelistTxt($c->app->whitelist->everything(), $c->app->clock->stamp()), 'whitelist.txt');
    }

    public function audit(Context $c): Response
    {
        $c->require(Permission::AuditView);
        $action = $c->request->queryParam('acao');
        $userParam = $c->request->queryParam('usuario');
        $userId = ctype_digit($userParam) ? (int) $userParam : null;
        $page = $c->request->page();

        return $c->page('audit', [
            'result' => $c->app->audit->page($page, $action, $userId),
            'actions' => $c->app->audit->actions(),
            'people' => $c->app->users->all(),
            'action' => $action,
            'userId' => $userId,
            'currentPage' => $page,
            'perPage' => $c->app->audit::PER_PAGE,
        ]);
    }
}
