<?php

declare(strict_types=1);

namespace Admin\Domain;

enum Permission: string
{
    case WhitelistView = 'whitelist.ver';
    case WhitelistEdit = 'whitelist.editar';
    case BansView = 'bans.ver';
    case BansCreate = 'bans.criar';
    case BansRevoke = 'bans.revogar';
    case Export = 'exportar';
    case AuditView = 'auditoria.ver';
    case UsersManage = 'usuarios.gerenciar';
}
