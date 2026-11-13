<?php

declare(strict_types=1);

/** Escapa pra HTML (texto e atributo). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** "2026-11-13 21:04:00" (UTC no banco) -> "13/11/2026 18:04" no fuso do painel. */
function data_br(?string $utc, string $tz = 'America/Sao_Paulo'): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }
    $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

    return $date->setTimezone(new DateTimeZone($tz))->format('d/m/Y H:i');
}

/** Link de paginação mantendo os outros filtros da URL. */
function pagina_url(array $query, int $page): string
{
    return '?' . http_build_query(['pagina' => $page] + $query);
}
