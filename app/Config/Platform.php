<?php

declare(strict_types=1);

namespace Config;

use Ephraitech\Platform\Config\Platform as BasePlatform;

/**
 * Platform settings. Every property can also be set in .env as
 * platform.<property>, e.g. platform.appName = 'Acme ERP'.
 *
 * Which modules are installed is chosen in .env (platform.modulesOff,
 * platform.modulesOnly), because it is read at boot.
 */
class Platform extends BasePlatform
{
    public string $appName = 'SaaS Starter';

    public string $tagline = 'Everything your team runs on, in one place.';

    public string $themeColor = '#021f5e';

    /**
     * Your logos, under public/ (e.g. 'assets/brand/logo-on-dark.svg').
     * NULL shows the default mark with the company or workspace name.
     *
     * @var array{logo: ?string, logoOnDark: ?string, mark: ?string, markOnDark: ?string}
     */
    public array $brand = [
        'logo'       => null,
        'logoOnDark' => null,
        'mark'       => null,
        'markOnDark' => null,
    ];

    /**
     * Sidebar sections shown first and last; others go between, A–Z.
     *
     * @var list<string>
     */
    public array $menuGroupsFirst = ['Overview'];

    /**
     * @var list<string>
     */
    public array $menuGroupsLast = ['Administration'];

    /**
     * Multi-tenant: which modules each plan includes ('*' = all installed).
     * Per-workspace add-ons and trials are set under Workspaces.
     *
     * @var array<string, array{title: string, modules: list<string>, description?: string}>
     */
    public array $plans = [
        'standard' => ['title' => 'Standard', 'modules' => ['*'], 'description' => 'Every installed module.'],
    ];

    public string $defaultPlan = 'standard';

    /**
     * Multi-tenant: how requests find their workspace, in order.
     *   domain   <slug>.<baseDomain> or the workspace's own domain
     *   header   X-Tenant-ID (API clients); tokens bound to a workspace imply it
     *   session  picked under /workspaces, on one shared domain
     *
     * @var list<'domain'|'header'|'session'>
     */
    public array $tenantSources = ['domain', 'header', 'session'];

    /**
     * Multi-tenant subdomains: 'app.example.com' serves acme.app.example.com.
     * Needs a wildcard DNS record and certificate. Empty: one shared domain.
     */
    public string $baseDomain = '';
}
