<?php

declare(strict_types=1);

namespace Admin\Support;

/** Relógio injetável: nos testes o tempo é fixo e avança quando o teste manda. */
class Clock
{
    public function __construct(private ?int $fixed = null)
    {
    }

    public function now(): int
    {
        return $this->fixed ?? time();
    }

    public function advance(int $seconds): void
    {
        $this->fixed = $this->now() + $seconds;
    }

    /** Formato guardado no banco: UTC, ordenável como texto em MySQL e SQLite. */
    public function stamp(?int $time = null): string
    {
        return gmdate('Y-m-d H:i:s', $time ?? $this->now());
    }
}
