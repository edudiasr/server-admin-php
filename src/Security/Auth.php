<?php

declare(strict_types=1);

namespace Admin\Security;

use Admin\Db\Database;
use Admin\Domain\AuditLog;
use Admin\Domain\User;
use Admin\Domain\Users;
use Admin\Support\Clock;

/**
 * Login em duas etapas quando o usuário tem 2FA: a senha certa deixa a sessão "pendente"
 * por 5 minutos, e só o código do aplicativo completa o login.
 */
final class Auth
{
    public const MAX_USER_FAILURES = 5;   // por usuário, na janela
    public const MAX_IP_FAILURES = 20;    // por IP, na janela (alguém testando vários usuários)
    public const WINDOW = 900;            // 15 minutos
    private const PENDING_TTL = 300;

    private ?User $current = null;

    public function __construct(
        private readonly Database $db,
        private readonly Users $users,
        private readonly AuditLog $audit,
        private readonly Session $session,
        private readonly Clock $clock,
    ) {
    }

    public function attempt(string $username, string $password, string $ip): LoginResult
    {
        $username = trim($username);
        if ($this->locked($username, $ip)) {
            $this->audit->record(null, 'login.bloqueado', 'usuario', $username, [], $ip);

            return LoginResult::Locked;
        }

        $row = $this->users->credentials($username);
        // com usuário inexistente ainda roda um password_verify, pra resposta demorar o mesmo tempo
        // e não dar pra descobrir quais usuários existem medindo o tempo
        // (um hash de verdade: com string inválida o password_verify volta na hora e o truque não funciona)
        static $dummy = null;
        $dummy ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $hash = $row['password_hash'] ?? $dummy;
        $ok = password_verify($password, $hash) && $row !== null && (bool) $row['active'];

        $this->db->insert('login_attempts', [
            'username' => mb_strtolower($username),
            'ip' => $ip,
            'success' => $ok ? 1 : 0,
            'attempted_at' => $this->clock->stamp(),
        ]);

        if (!$ok) {
            $this->audit->record(null, 'login.falhou', 'usuario', $username, [], $ip);

            return LoginResult::Invalid;
        }

        $this->users->rehashIfNeeded((int) $row['id'], $row['password_hash'], $password);

        if ($row['totp_secret'] !== null) {
            $this->session->set('pending_user', (int) $row['id']);
            $this->session->set('pending_since', $this->clock->now());

            return LoginResult::NeedsTotp;
        }

        $this->complete((int) $row['id'], $ip);

        return LoginResult::Ok;
    }

    public function attemptTotp(string $code, string $ip): LoginResult
    {
        $id = $this->pendingUser();
        if ($id === null) {
            return LoginResult::Invalid;
        }
        $row = $this->db->one('SELECT username, totp_secret, active FROM users WHERE id = :id', ['id' => $id]);
        if (!$row || !$row['active'] || $row['totp_secret'] === null) {
            return LoginResult::Invalid;
        }
        if ($this->locked($row['username'], $ip)) {
            return LoginResult::Locked;
        }

        $step = Totp::verify($row['totp_secret'], preg_replace('/\s/', '', $code), $this->clock->now());
        $ok = $step !== null && $this->users->consumeTotpStep($id, $step);

        $this->db->insert('login_attempts', [
            'username' => mb_strtolower($row['username']),
            'ip' => $ip,
            'success' => $ok ? 1 : 0,
            'attempted_at' => $this->clock->stamp(),
        ]);
        if (!$ok) {
            $this->audit->record($id, 'login.2fa_falhou', 'usuario', $id, [], $ip);

            return LoginResult::Invalid;
        }

        $this->complete($id, $ip);

        return LoginResult::Ok;
    }

    public function pendingUser(): ?int
    {
        $id = $this->session->get('pending_user');
        $since = (int) $this->session->get('pending_since', 0);
        if (!is_int($id) || $this->clock->now() - $since > self::PENDING_TTL) {
            return null;
        }

        return $id;
    }

    private function complete(int $id, string $ip): void
    {
        // id de sessão novo no login: quem plantou um id antes (fixação de sessão) fica com um id morto
        $this->session->regenerate();
        $this->session->remove('pending_user');
        $this->session->remove('pending_since');
        $this->session->set('user_id', $id);
        $this->users->touchLogin($id);
        $this->audit->record($id, 'login', 'usuario', $id, [], $ip);
        $this->current = null;
    }

    public function locked(string $username, string $ip): bool
    {
        $since = $this->clock->stamp($this->clock->now() - self::WINDOW);
        $byUser = (int) $this->db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE username = :u AND success = 0 AND attempted_at > :s',
            ['u' => mb_strtolower(trim($username)), 's' => $since],
        );
        $byIp = (int) $this->db->value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND success = 0 AND attempted_at > :s',
            ['ip' => $ip, 's' => $since],
        );

        return $byUser >= self::MAX_USER_FAILURES || $byIp >= self::MAX_IP_FAILURES;
    }

    /** Usuário logado. Conta desativada perde o acesso na hora, mesmo com sessão aberta. */
    public function user(): ?User
    {
        if ($this->current !== null) {
            return $this->current;
        }
        $id = $this->session->get('user_id');
        if (!is_int($id)) {
            return null;
        }
        $user = $this->users->find($id);
        if ($user === null || !$user->active) {
            $this->session->remove('user_id');

            return null;
        }

        return $this->current = $user;
    }

    public function logout(string $ip): void
    {
        if ($user = $this->user()) {
            $this->audit->record($user->id, 'logout', 'usuario', $user->id, [], $ip);
        }
        $this->session->destroy();
        $this->current = null;
    }
}
