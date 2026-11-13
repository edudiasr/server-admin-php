<?php

declare(strict_types=1);

namespace Admin\Domain;

use Admin\Db\Database;
use Admin\Support\Clock;

final class Users
{
    public const MIN_PASSWORD = 12;

    public function __construct(private readonly Database $db, private readonly Clock $clock)
    {
    }

    public function find(int $id): ?User
    {
        $row = $this->db->one('SELECT * FROM users WHERE id = :id', ['id' => $id]);

        return $row ? User::fromRow($row) : null;
    }

    /** @return array<string, mixed>|null linha inteira, com hash e segredo do 2FA (uso interno do login) */
    public function credentials(string $username): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE username = :u', ['u' => $username]);
    }

    /** @return list<User> */
    public function all(): array
    {
        return array_map(User::fromRow(...), $this->db->all('SELECT * FROM users ORDER BY username'));
    }

    public function create(string $username, string $password, Role $role): int
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,40}$/', $username)) {
            throw new DomainError('Usuário: de 3 a 40 caracteres, só letras, números, ponto, hífen e sublinhado.');
        }
        self::checkPassword($password);
        try {
            return $this->db->insert('users', [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role->value,
                'active' => 1,
                'created_at' => $this->clock->stamp(),
            ]);
        } catch (\PDOException $e) {
            if ($this->db->isUniqueViolation($e)) {
                throw new DomainError("Já existe o usuário \"$username\".");
            }
            throw $e;
        }
    }

    public static function checkPassword(string $password): void
    {
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            throw new DomainError('A senha precisa ter pelo menos ' . self::MIN_PASSWORD . ' caracteres.');
        }
    }

    public function setPassword(int $id, string $password): void
    {
        self::checkPassword($password);
        $this->db->run('UPDATE users SET password_hash = :h WHERE id = :id', [
            'h' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    /** Hash antigo (custo menor, algoritmo trocado) é refeito no login, quando a senha está em mãos. */
    public function rehashIfNeeded(int $id, string $hash, string $password): void
    {
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->db->run('UPDATE users SET password_hash = :h WHERE id = :id', [
                'h' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $id,
            ]);
        }
    }

    public function changeRole(int $actorId, int $id, Role $role): void
    {
        $this->guardLastAdmin($id, $role === Role::Admin, 'mudar o cargo do último administrador');
        $this->db->run('UPDATE users SET role = :r WHERE id = :id', ['r' => $role->value, 'id' => $id]);
    }

    public function setActive(int $actorId, int $id, bool $active): void
    {
        if (!$active && $actorId === $id) {
            throw new DomainError('Você não pode desativar a própria conta.');
        }
        if (!$active) {
            $this->guardLastAdmin($id, false, 'desativar o último administrador');
        }
        $this->db->run('UPDATE users SET active = :a WHERE id = :id', ['a' => $active ? 1 : 0, 'id' => $id]);
    }

    /** O painel nunca pode ficar sem nenhum admin ativo: ninguém mais conseguiria criar usuário. */
    private function guardLastAdmin(int $id, bool $staysAdmin, string $what): void
    {
        if ($staysAdmin) {
            return;
        }
        $user = $this->find($id) ?? throw new DomainError('Usuário não encontrado.');
        if ($user->role !== Role::Admin || !$user->active) {
            return;
        }
        $admins = (int) $this->db->value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
        if ($admins <= 1) {
            throw new DomainError("Não dá pra $what.");
        }
    }

    public function enableTotp(int $id, string $secret, int $step): void
    {
        $this->db->run('UPDATE users SET totp_secret = :s, totp_last_step = :st WHERE id = :id', [
            's' => $secret, 'st' => $step, 'id' => $id,
        ]);
    }

    public function disableTotp(int $id): void
    {
        $this->db->run('UPDATE users SET totp_secret = NULL, totp_last_step = NULL WHERE id = :id', ['id' => $id]);
    }

    /**
     * Marca o intervalo de 30 s como usado. O UPDATE só pega se o passo for maior que o último,
     * então o mesmo código não serve duas vezes nem com dois logins ao mesmo tempo.
     */
    public function consumeTotpStep(int $id, int $step): bool
    {
        return $this->db->run(
            'UPDATE users SET totp_last_step = :st WHERE id = :id AND (totp_last_step IS NULL OR totp_last_step < :st2)',
            ['st' => $step, 'st2' => $step, 'id' => $id],
        ) === 1;
    }

    public function touchLogin(int $id): void
    {
        $this->db->run('UPDATE users SET last_login_at = :t WHERE id = :id', ['t' => $this->clock->stamp(), 'id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM users');
    }
}
