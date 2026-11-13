<?php

declare(strict_types=1);

namespace Admin\Domain;

enum Role: string
{
    case Admin = 'admin';
    case Moderator = 'moderador';
    case Support = 'suporte';

    /** @return list<Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Moderator => [
                Permission::WhitelistView, Permission::WhitelistEdit,
                Permission::BansView, Permission::BansCreate, Permission::BansRevoke,
                Permission::Export, Permission::AuditView,
            ],
            // suporte atende jogador: põe na whitelist e consulta banimento, mas não bane
            self::Support => [Permission::WhitelistView, Permission::WhitelistEdit, Permission::BansView],
        };
    }

    public function can(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Moderator => 'Moderador',
            self::Support => 'Suporte',
        };
    }
}
