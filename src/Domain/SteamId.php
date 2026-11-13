<?php

declare(strict_types=1);

namespace Admin\Domain;

/**
 * SteamID64 de conta individual: 17 dígitos a partir de 76561197960265728.
 * Aceita colar o link do perfil, que é o que o pessoal do suporte normalmente tem em mãos.
 */
final class SteamId
{
    private const MIN = '76561197960265728';
    private const MAX = '76561202255233023';

    public static function parse(string $input): string
    {
        $input = trim($input);
        if (preg_match('#steamcommunity\.com/profiles/(\d{17})#', $input, $m)) {
            $input = $m[1];
        }
        if (!preg_match('/^\d{17}$/', $input)) {
            throw new DomainError('SteamID64 precisa ter 17 dígitos (ou cole o link steamcommunity.com/profiles/...).');
        }
        // comparação como texto funciona porque os dois têm 17 dígitos
        if (strcmp($input, self::MIN) < 0 || strcmp($input, self::MAX) > 0) {
            throw new DomainError('Esse número não é um SteamID64 de conta individual.');
        }

        return $input;
    }
}
