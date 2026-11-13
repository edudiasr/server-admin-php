<?php

declare(strict_types=1);

namespace Admin\Security;

interface Session
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** Troca o id da sessão mantendo os dados (contra fixação de sessão, no login). */
    public function regenerate(): void;

    public function destroy(): void;

    /** Mensagem que aparece uma vez na próxima página. */
    public function flash(string $type, string $message): void;

    /** @return list<array{type: string, message: string}> */
    public function takeFlashes(): array;
}
