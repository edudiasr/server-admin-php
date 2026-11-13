<?php

declare(strict_types=1);

namespace Admin\Security;

/**
 * Código de 6 dígitos que muda a cada 30 s (RFC 6238), compatível com Google Authenticator,
 * Authy, 1Password etc. Feito à mão em cima do hash_hmac: são umas 40 linhas.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const STEP = 30;
    private const DIGITS = 6;

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function code(string $secret, int $time, string $algo = 'sha1', int $digits = self::DIGITS): string
    {
        $counter = intdiv($time, self::STEP);
        $hash = hash_hmac($algo, pack('J', $counter), self::base32Decode($secret), true);

        // "dynamic truncation": os 4 bits finais do hash dizem de onde tirar os 4 bytes do código
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 10 ** $digits), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Aceita o código do intervalo atual e do anterior/seguinte (relógio do celular adiantado
     * ou atrasado). Devolve o contador usado, pra quem chamar poder recusar o mesmo código de novo.
     */
    public static function verify(string $secret, string $code, int $time, int $window = 1): ?int
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        for ($i = -$window; $i <= $window; $i++) {
            $t = $time + $i * self::STEP;
            if (hash_equals(self::code($secret, $t), $code)) {
                return intdiv($t, self::STEP);
            }
        }

        return null;
    }

    public static function uri(string $issuer, string $account, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer),
        );
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=]/', '', $text));
        $bits = '';
        foreach (str_split($text) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException('segredo base32 inválido');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
