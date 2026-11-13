<?php

declare(strict_types=1);

namespace Admin\Security;

/** Sessão em memória: usada nos testes e como base da sessão nativa. */
class ArraySession implements Session
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public int $regenerations = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function destroy(): void
    {
        $this->data = [];
    }

    public function flash(string $type, string $message): void
    {
        $flashes = $this->get('_flash', []);
        $flashes[] = ['type' => $type, 'message' => $message];
        $this->set('_flash', $flashes);
    }

    public function takeFlashes(): array
    {
        $flashes = $this->get('_flash', []);
        $this->remove('_flash');

        return $flashes;
    }
}
