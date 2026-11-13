<?php

declare(strict_types=1);

namespace Admin;

use Admin\Domain\Permission;
use Admin\Domain\User;
use Admin\Http\Request;
use Admin\Http\Response;
use Admin\Security\Auth;

/** O que cada handler recebe: a requisição, quem está logado e onde renderizar. */
final class Context
{
    public function __construct(
        public readonly Request $request,
        public readonly Auth $auth,
        public readonly View $view,
        public readonly ?User $user,
        public readonly App $app,
    ) {
    }

    public function require(Permission $permission): User
    {
        if ($this->user === null || !$this->user->can($permission)) {
            throw new Forbidden();
        }

        return $this->user;
    }

    /** @param array<string, mixed> $vars */
    public function page(string $template, array $vars = [], int $status = 200): Response
    {
        $this->view->share('flashes', $this->request->session->takeFlashes());
        $this->view->share('path', $this->request->path);

        return Response::html($this->view->render($template, $vars), $status);
    }

    public function back(string $to, string $message = ''): Response
    {
        if ($message !== '') {
            $this->request->session->flash('ok', $message);
        }

        return Response::redirect($to);
    }

    /** @param array<string, mixed> $details */
    public function audit(string $action, string $targetType, string|int $targetId, array $details = []): void
    {
        $this->app->audit->record($this->user?->id, $action, $targetType, $targetId, $details, $this->request->ip);
    }
}
