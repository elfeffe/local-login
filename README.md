## Elfeffe Local Login (`elfeffe/local-login`)

This package adds a **local-only** “login by URL query parameter” helper.

When enabled, any **GET/HEAD** request containing `logged=<userId>` will:

- Authenticate the session as that user ID
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

## Installation (this repository)

Already wired as a local `path` package in the root `composer.json`:

- `"repositories.local-login"` → `packages/elfeffe/local-login`
- `"require.elfeffe/local-login": "dev-main"`

Then run:

```bash
composer update elfeffe/local-login
```

## Installation (other Laravel apps)

### Option A — Path repository (recommended for internal projects)

1. Copy this folder into your target project, for example:
   - `packages/elfeffe/local-login`
2. Add a `path` repository and require the package in the target project `composer.json`:

```json
{
  "repositories": {
    "local-login": {
      "type": "path",
      "url": "packages/elfeffe/local-login",
      "options": {
        "symlink": true
      }
    }
  },
  "require": {
    "elfeffe/local-login": "dev-main"
  }
}
```

3. Install:

```bash
composer update elfeffe/local-login
```

### Option B — VCS repository

If you move this package to its own Git repository, add it as a Composer VCS repository and `composer require elfeffe/local-login`.

## Usage

Append `logged=<userId>` to any URL:

- `https://your-app.test/dashboard?logged=62`
- `https://your-app.test/dashboard/some/page?tool=adder&logged=62`

The first request will return a redirect to the same URL without `logged`. Follow the redirect and you’ll be authenticated.

## Filament v4 compatibility

Filament panels define their own middleware stacks, so being in Laravel’s `web` group isn’t enough.

When Filament v4 is installed, this package also:

- Injects `LoginFromQueryMiddleware` into every `Filament\Panel` `authMiddleware()` stack
- Adjusts Laravel’s middleware priority so the login middleware runs **before** auth middleware

This makes `logged=<userId>` work on routes like `/admin/...` and `/dashboard/...`.

## Behavior details

- **Invalid value** (missing / not numeric / `< 1`): redirect to the same URL without `logged`.
- **Unknown user ID**: returns **404**.
- **Already logged in as that user**: just strips `logged` and redirects.
- Only acts on **GET/HEAD** requests.


