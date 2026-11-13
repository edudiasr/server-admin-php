<?php

declare(strict_types=1);

namespace Admin;

/**
 * Template em PHP puro. Tudo que vem de usuário passa por e() antes de ir pra página;
 * o template nunca imprime variável crua.
 */
final class View
{
    /** @param array<string, mixed> $shared variáveis disponíveis em todo template (usuário, csrf...) */
    public function __construct(private readonly string $dir, private array $shared = [])
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $vars */
    public function render(string $template, array $vars = [], ?string $layout = 'layout'): string
    {
        [$content, $title] = $this->capture($template, $vars + $this->shared);
        if ($layout === null) {
            return $content;
        }

        // o template da página define $title; o layout usa no <title>
        return $this->capture($layout, ['content' => $content, 'title' => $title] + $vars + $this->shared)[0];
    }

    /**
     * @param array<string, mixed> $vars
     * @return array{string, ?string}
     */
    private function capture(string $template, array $vars): array
    {
        $file = $this->dir . '/' . $template . '.php';
        $render = static function (string $__file, array $__vars): ?string {
            extract($__vars, EXTR_SKIP);
            require $__file;

            return isset($title) && is_string($title) ? $title : null;
        };
        ob_start();
        try {
            $title = $render($file, $vars);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return [(string) ob_get_clean(), $title];
    }
}
