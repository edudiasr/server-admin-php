<?php

declare(strict_types=1);

namespace Admin\Domain;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly Role $role,
        public readonly bool $active,
        public readonly bool $totpEnabled,
        public readonly ?string $lastLoginAt = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['username'],
            Role::from((string) $row['role']),
            (bool) $row['active'],
            ($row['totp_secret'] ?? null) !== null,
            $row['last_login_at'] ?? null,
        );
    }

    public function can(Permission $permission): bool
    {
        return $this->active && $this->role->can($permission);
    }
}
