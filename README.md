# SaaS Starter

A CodeIgniter 4.7 starter for products built from switchable modules, on
[ephraitech/ci4-platform](https://github.com/etus-ephraitech/ci4-platform).
Run it as **one install per client** or as a **multi-tenant SaaS**; the
modules are the same.

You get sign-in (ci4-auth), a dashboard, settings, people and roles, modules,
workspaces, an audit log, global search, a JSON API with token sign-in, a
module generator and a cPanel deploy kit.

## Create a project

```bash
composer create-project ephraitech/saas-starter my-app
cd my-app
```

This copies `env` to `.env`, generates the encryption key and publishes the
platform's assets. Then set `app.baseURL` and `database.*` in `.env` and:

```bash
php spark platform:migrate
php spark auth:sync
php spark auth:user:create --email you@example.com --generate --role superadmin --verified
php spark serve
```

Commit `composer.lock` with your first commit: every server installs exactly
what you tested, and `deploy:check` fails without it.

## Add a module

```bash
php spark make:module Crm --entity Client --title Clients --group Sales --icon bi-people --api
php spark platform:migrate && php spark auth:sync
```

That gives a working module under `modules/Crm`: manifest, permissions and
role grants, a sidebar entry, a tenant-scoped table, list/add/edit/delete
pages, search and (with `--api`) a JSON API. Build from there.

## One install per client

The default (`Config\Auth::$tenancy = 'single'`). Choose what this client has:

```ini
; .env, read at boot
platform.modulesOnly = Crm, Sales, Finance
```

Optional modules (`'default' => false` in their manifest) are switched on by
an admin under Administration → Modules, or `php spark modules:on Payroll`.

## Multi-tenant (SaaS)

```ini
; .env
auth.tenancy = multi
platform.baseDomain = 'app.example.com'   ; acme.app.example.com, … (or leave empty: one domain, workspace picker)
cookie.domain = '.app.example.com'
```

Define plans in `app/Config/Platform.php`:

```php
public array $plans = [
    'basic' => ['title' => 'Basic', 'modules' => ['Crm']],
    'pro'   => ['title' => 'Pro',   'modules' => ['*']],
];
```

Then create workspaces under Administration → Workspaces (signed in as the
super admin, with no workspace selected) or:

```bash
php spark tenants:create acme --name "Acme Ltd" --plan basic --owner jane@acme.co.ug
php spark modules:on Sales --tenant acme --until 2026-12-31 --note "Trial"
```

API clients sign in at `POST /api/v1/auth/login`, then send
`Authorization: Bearer <token>` and `X-Tenant-ID: acme`.
`GET /api/v1/platform/context` tells them the person, workspace, modules,
permissions and menu.

## Where things are

| | |
|---|---|
| `app/Config/Auth.php` | roles, tenancy, sign-in settings |
| `app/Config/Platform.php` | product name, brand, menu sections, plans, tenant sources |
| `app/Config/Autoload.php`, `Filters.php`, `App.php`, `Routes.php` | platform wiring (see the comments) |
| `modules/` | your modules |
| `deploy/`, `.cpanel.yml`, `.env.production.example` | cPanel deployment ([deploy/DEPLOY.md](deploy/DEPLOY.md)) |

Read the [platform guide](https://github.com/etus-ephraitech/ci4-platform/blob/main/docs/GUIDE.md)
for manifests, the module gate, tenant-scoped data, extension points and the API.

Before going live: `php spark deploy:check`.
