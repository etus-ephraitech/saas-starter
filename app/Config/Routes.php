<?php

use CodeIgniter\Router\RouteCollection;

/**
 * App routes. The dashboard ('/'), settings, modules, workspaces and the
 * platform API come from ephraitech/ci4-platform; sign-in pages from
 * ephraitech/ci4-auth; each module adds its own in modules/<Name>/Config/Routes.php.
 *
 * Put a filter on every route you add here, in one piece:
 *     $routes->get('reports', 'Reports::index', ['filter' => 'can:session,reports.view']);
 * deploy:check fails for module routes without one.
 *
 * @var RouteCollection $routes
 */

// Sign-in, password reset, verification and change-password pages.
// Registration is off: accounts are created by an owner or admin.
service('auth')->routes($routes, except: ['register']);

// Token sign-in for mobile apps, SPAs and integrations: POST /api/v1/auth/login,
// GET /api/v1/auth/me, ... Then call module APIs with "Authorization: Bearer <token>"
// (and, multi-tenant, "X-Tenant-ID: <workspace>").
service('auth')->apiRoutes($routes, except: ['register'], prefix: 'api/v1/auth');
