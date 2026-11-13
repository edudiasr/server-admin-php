<?php

declare(strict_types=1);

namespace Admin\Security;

/**
 * Um token por sessão, conferido em todo POST. Um site de fora consegue fazer o navegador
 * mandar o formulário, mas não consegue ler o token pra colocar nele.
 */
final class Csrf
{
    private const KEY = '_csrf';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    public function valid(string $sent): bool
    {
        $token = $this->session->get(self::KEY);

        // hash_equals compara em tempo constante: o tempo da resposta não vaza quantos caracteres bateram
        return is_string($token) && $sent !== '' && hash_equals($token, $sent);
    }
}
