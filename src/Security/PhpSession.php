<?php

declare(strict_types=1);

namespace Admin\Security;

/** Sessão nativa do PHP com cookie endurecido. */
final class PhpSession extends ArraySession
{
    public function __construct(bool $https)
    {
        session_name('painel');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,      // JavaScript não lê o cookie
            'samesite' => 'Lax',     // não vai em POST vindo de outro site
        ]);
        ini_set('session.use_strict_mode', '1');  // id inventado pelo cliente é recusado
        session_start();
        $this->data = &$_SESSION;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
        parent::regenerate();
    }

    public function destroy(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
