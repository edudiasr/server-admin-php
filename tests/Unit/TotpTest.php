<?php

declare(strict_types=1);

namespace Admin\Tests\Unit;

use Admin\Security\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    /** Tabela do apêndice B da RFC 6238 (SHA-1, 8 dígitos, segredo "12345678901234567890"). */
    public static function rfcVectors(): array
    {
        return [
            [59, '94287082'],
            [1111111109, '07081804'],
            [1111111111, '14050471'],
            [1234567890, '89005924'],
            [2000000000, '69279037'],
            [20000000000, '65353130'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function testMatchesTheRfcVectors(int $time, string $expected): void
    {
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame($expected, Totp::code($secret, $time, 'sha1', 8));
    }

    public function testAcceptsOneStepOfClockDriftButNotTwo(): void
    {
        $secret = Totp::generateSecret();
        $now = 1_800_000_000;
        $this->assertNotNull(Totp::verify($secret, Totp::code($secret, $now - 30), $now));
        $this->assertNotNull(Totp::verify($secret, Totp::code($secret, $now + 30), $now));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $now - 90), $now));
    }

    public function testReturnsTheStepThatMatched(): void
    {
        $secret = Totp::generateSecret();
        $now = 1_800_000_015;
        $this->assertSame(intdiv($now, 30) - 1, Totp::verify($secret, Totp::code($secret, $now - 30), $now));
    }

    public function testRejectsGarbage(): void
    {
        $secret = Totp::generateSecret();
        foreach (['', '12345', '1234567', 'abcdef', '12 345'] as $code) {
            $this->assertNull(Totp::verify($secret, $code, 1_800_000_000), $code);
        }
    }

    public function testBase32RoundTrip(): void
    {
        foreach (['', 'f', 'fo', 'foo', 'foob', 'fooba', 'foobar', random_bytes(20)] as $bytes) {
            $this->assertSame($bytes, Totp::base32Decode(Totp::base32Encode($bytes)));
        }
        $this->assertSame('MZXW6YTBOI', Totp::base32Encode('foobar'));  // vetor da RFC 4648
        $this->assertSame(32, strlen(Totp::generateSecret()));
    }

    public function testUriForAuthenticatorApps(): void
    {
        $uri = Totp::uri('Servidor Sul', 'lobo', 'ABC');
        $this->assertSame('otpauth://totp/Servidor%20Sul:lobo?secret=ABC&issuer=Servidor%20Sul&digits=6&period=30', $uri);
    }
}
