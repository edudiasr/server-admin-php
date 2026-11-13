<?php

declare(strict_types=1);

namespace Admin\Http;

/**
 * Rotas com parâmetros no caminho: '/bans/{id}/revogar'.
 * Parâmetro só aceita dígitos, que é tudo que o painel usa (ids).
 */
final class Router
{
    /** @var list<array{string, string, string, callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
        $this->routes[] = [$method, $pattern, $regex, $handler];
    }

    /**
     * @return array{0: callable, 1: array<string, int>}|int  handler e parâmetros, ou 404/405
     */
    public function match(string $method, string $path): array|int
    {
        $pathExists = false;
        foreach ($this->routes as [$routeMethod, , $regex, $handler]) {
            if (!preg_match($regex, $path, $m)) {
                continue;
            }
            $pathExists = true;
            if ($routeMethod === $method) {
                $params = array_map('intval', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));

                return [$handler, $params];
            }
        }

        return $pathExists ? 405 : 404;
    }
}
