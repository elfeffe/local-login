<?php

declare(strict_types=1);

namespace Elfeffe\LocalLogin;

use Elfeffe\LocalLogin\Http\Middleware\LoginFromQueryMiddleware;
use Filament\Panel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class LocalLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! $this->app->isLocal()) {
            return;
        }

        if (! class_exists(Panel::class)) {
            return;
        }

        $this->app->resolving(Panel::class, function (Panel $panel): void {
            $panel->authMiddleware([
                LoginFromQueryMiddleware::class,
            ]);
        });
    }

    public function boot(Router $router): void
    {
        if (! $this->app->isLocal()) {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);

        if (method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
            $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, LoginFromQueryMiddleware::class);
        }

        $router->pushMiddlewareToGroup('web', LoginFromQueryMiddleware::class);
    }
}


