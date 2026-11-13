<?php

declare(strict_types=1);

namespace Admin\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $body = '',
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $body, string $filename = ''): self
    {
        $headers = ['Content-Type' => 'text/plain; charset=utf-8'];
        if ($filename !== '') {
            $headers['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
        }

        return new self(200, $body, $headers);
    }

    public static function redirect(string $to): self
    {
        // 303: depois de um POST o navegador volta com GET (evita reenviar o formulário no F5)
        return new self(303, '', ['Location' => $to]);
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
