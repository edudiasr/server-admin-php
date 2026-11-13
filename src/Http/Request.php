<?php

declare(strict_types=1);

namespace Admin\Http;

use Admin\Security\Session;

final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, mixed>  $post
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly string $ip,
        public readonly Session $session,
        public readonly bool $https = false,
    ) {
    }

    public static function fromGlobals(Session $session): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim($path, '/'),
            array_map('strval', array_filter($_GET, 'is_scalar')),
            $_POST,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $session,
            ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off',
        );
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function queryParam(string $key, string $default = ''): string
    {
        return trim($this->query[$key] ?? $default);
    }

    public function page(): int
    {
        return max(1, (int) ($this->query['pagina'] ?? 1));
    }
}
