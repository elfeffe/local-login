## Elfeffe Local Login (`elfeffe/local-login`)

This package adds a **local-only** “login by URL query parameter” helper.

When enabled, any **GET/HEAD** request containing `logged` will:

- Authenticate the session as a user
- Redirect back to the same URL **without** the `logged` parameter

This is handy when debugging apps behind tunnels like Expose/Ngrok (public URL, but still running with `APP_ENV=local` on your machine).

## Security (important)

This feature is **hard disabled** outside local:

- The service provider **only registers** hooks when `app()->isLocal()`
- The middleware itself also checks `app()->isLocal()`

So even if someone appends `?logged=...` in production/staging, it does nothing.

## Requirements

- PHP `^8.4`
- Laravel `^12.0`
- Optional: Filament v4 (if installed, the middleware is automatically injected into all panels)

## Installation

```bash
composer require elfeffe/local-login:^1.0
```

## Usage

### Option A (generic): log in by user ID

Append `logged=<userId>` to any URL:

- `https://your-app.test/dashboard?logged=62`
- `https://your-app.test/dashboard/some/page?tool=adder&logged=62`

The first request will return a redirect to the same URL without `logged`. Follow the redirect and you’ll be authenticated.

### Option B (Filament tenancy): `?logged` (no value)

If you are using **Filament v4 with tenancy**, you can append `?logged` (without a value) to a tenant URL (where the tenant identifier / UUID is present in the route):

- `https://your-app.test/dashboard/<tenant-uuid>/.../import?logged`

In this mode, the middleware extracts the tenant identifier from the route (or the URL), resolves the tenant, and logs you in as:

- `created_by` (or `owner_id` / `user_id`) if present on the tenant model, otherwise
- the first related tenant user (`$tenant->users()->orderBy('users.id')->first()`) if a `users()` relationship exists.

## Filament v4 compatibility

Filament panels define their own middleware stacks, so being in Laravel’s `web` group isn’t enough.

When Filament v4 is installed, this package also:

- Injects `LoginFromQueryMiddleware` into every `Filament\Panel` `authMiddleware()` stack
- Adjusts Laravel’s middleware priority so the login middleware runs **before** auth middleware

This makes `logged=<userId>` work on routes like `/admin/...` and `/dashboard/...`.

## Non-standard middleware stacks (non-Filament)

This package registers the middleware into Laravel’s `web` group. If your application does **not** use the `web` group (or does not start sessions), you must add the middleware to the middleware stack that handles browser requests.

## Behavior details

- **Invalid value** (missing / not numeric / `< 1`): redirect to the same URL without `logged`.
- **Unknown user ID**: returns **404**.
- **Already logged in as that user**: just strips `logged` and redirects.
- Only acts on **GET/HEAD** requests.


