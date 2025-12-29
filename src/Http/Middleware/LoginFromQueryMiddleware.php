<?php

declare(strict_types=1);

namespace Elfeffe\LocalLogin\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
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
            $tenantUserId = $this->resolveTenantOwnerUserIdForFilamentTenancy($request);

            if ($tenantUserId === null) {
                return redirect()->to($this->stripLoggedParameter($request));
            }

            $userId = $tenantUserId;
        }

        if ($guard->id() !== $userId) {
            $user = $guard->loginUsingId($userId);

            if ($user === false) {
                abort(404);
            }
        }

        return redirect()->to($this->stripLoggedParameter($request));
    }

    private function resolveTenantOwnerUserIdForFilamentTenancy(Request $request): ?int
    {
        if (! class_exists(Filament::class)) {
            return null;
        }

        $panel = Filament::getCurrentPanel();

        if (! $panel) {
            return null;
        }

        if (! $panel->hasTenancy()) {
            return null;
        }

        $tenantIdentifier = $this->extractTenantIdentifier($request);

        if ($tenantIdentifier === null) {
            return null;
        }

        $tenantModel = $panel->getTenantModel();

        if (! class_exists($tenantModel)) {
            return null;
        }

        $tenant = $this->resolveTenant($tenantModel, $panel->getTenantSlugAttribute(), $tenantIdentifier);

        if (! $tenant) {
            return null;
        }

        $userModel = Config::get('auth.providers.users.model');

        if (! is_string($userModel) || $userModel === '' || ! class_exists($userModel)) {
            return null;
        }

        $ownerId = $this->resolveTenantOwnerUserIdFromAttributes($tenant);

        if ($ownerId !== null) {
            return $ownerId;
        }

        if (method_exists($tenant, 'users')) {
            $user = $tenant->users()->orderBy('users.id')->first();

            return $user?->getKey() ? (int) $user->getKey() : null;
        }

        return null;
    }

    private function extractTenantIdentifier(Request $request): ?string
    {
        $routeTenant = $request->route('tenant');

        if (is_string($routeTenant) && $routeTenant !== '') {
            return $routeTenant;
        }

        $path = $request->path();

        if ($path === '') {
            return null;
        }

        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $path, $matches) === 1) {
            return (string) $matches[0];
        }

        return null;
    }

    private function resolveTenant(string $tenantModel, ?string $tenantSlugAttribute, string $tenantIdentifier): mixed
    {
        $query = $tenantModel::query();

        if (filled($tenantSlugAttribute)) {
            return $query->where($tenantSlugAttribute, $tenantIdentifier)->first();
        }

        if (ctype_digit($tenantIdentifier)) {
            return $query->find((int) $tenantIdentifier);
        }

        $instance = new $tenantModel;

        return $query->where($instance->getRouteKeyName(), $tenantIdentifier)->first();
    }

    private function resolveTenantOwnerUserIdFromAttributes(mixed $tenant): ?int
    {
        foreach (['created_by', 'owner_id', 'user_id'] as $key) {
            $value = $tenant->getAttribute($key);

            if ($value === null || is_array($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '' || ! ctype_digit($value)) {
                continue;
            }

            $id = (int) $value;

            if ($id > 0) {
                return $id;
            }
        }

        return null;
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
