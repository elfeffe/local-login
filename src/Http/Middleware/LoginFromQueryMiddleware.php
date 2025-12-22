<?php

declare(strict_types=1);

namespace Elfeffe\LocalLogin\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class LoginFromQueryMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldHandle($request)) {
            return $next($request);
        }

        $guard = $this->resolveGuard();
        $userId = $this->extractUserId($request);

        if ($userId === null) {
            return redirect()->to($this->stripLoggedParameter($request));
        }

        if ($guard->id() !== $userId) {
            $user = $guard->loginUsingId($userId);

            if ($user === false) {
                abort(404);
            }
        }

        return redirect()->to($this->stripLoggedParameter($request));
    }

    private function resolveGuard(): StatefulGuard
    {
        if (! class_exists(Filament::class)) {
            return Auth::guard();
        }

        try {
            if (! Filament::getCurrentPanel()) {
                return Auth::guard();
            }

            return Auth::guard(Filament::getAuthGuard());
        } catch (\Throwable) {
            return Auth::guard();
        }
    }

    private function shouldHandle(Request $request): bool
    {
        if (! app()->isLocal()) {
            return false;
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        return $request->query->has('logged');
    }

    private function extractUserId(Request $request): ?int
    {
        $value = $request->query('logged');

        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $id = (int) $value;

        if ($id < 1) {
            return null;
        }

        return $id;
    }

    private function stripLoggedParameter(Request $request): string
    {
        $query = Arr::except($request->query(), ['logged']);
        $url = $request->url();

        if ($query === []) {
            return $url;
        }

        return $url.'?'.Arr::query($query);
    }
}


