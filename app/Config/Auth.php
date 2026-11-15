<?php

declare(strict_types=1);

namespace Config;

use Ephraitech\Auth\Config\Auth as BaseAuth;
use Ephraitech\Auth\Entities\OneTimeCode;
use Ephraitech\Auth\Notifications\EmailNotifier;
use Ephraitech\Platform\Auth\ModulePermissionGate;

/**
 * Sign-in, roles and permissions (ephraitech/ci4-auth).
 *
 * Permissions and the grants that go with them are declared by each
 * module in its module.php and reach this config automatically, so adding
 * a module never means editing this file. After adding or changing a
 * module, run:  php spark auth:sync
 *
 * Single install or SaaS: $tenancy. Everything else in the platform follows it.
 */
class Auth extends BaseAuth
{
    /**
     * 'single': one install per client. 'multi': many tenants (workspaces)
     * in one install; roles are given per workspace. Also settable in .env
     * as auth.tenancy = multi.
     */
    public string $tenancy = 'single';

    /**
     * Switched-off modules fail every permission check, the super role's
     * included. deploy:check fails without it.
     *
     * @var class-string|null
     */
    public ?string $permissionGate = ModulePermissionGate::class;

    /**
     * Roles. Modules grant their permissions to these in module.php.
     *
     *   superadmin  you, the developer / platform owner: passes every check;
     *               in a multi-tenant install it is given platform-wide
     *   owner       runs the business (single) or a workspace (multi)
     *   admin       manages people and settings
     *   member      everyone else; modules give it their everyday permissions
     *
     * @var array<string, array{title: string, description?: string}>
     */
    public array $roles = [
        'superadmin' => ['title' => 'Super admin', 'description' => 'The platform owner. Can do everything.'],
        'owner'      => ['title' => 'Owner', 'description' => 'Runs the business or workspace: people, settings and every module.'],
        'admin'      => ['title' => 'Administrator', 'description' => 'Manages people, roles and settings.'],
        'member'     => ['title' => 'Member', 'description' => 'Everyday work in the modules they are given.'],
    ];

    public ?string $superRole = 'superadmin';

    /**
     * Declared by modules (module.php 'permissions'), not here. Add
     * permissions here only for app code outside modules.
     *
     * @var array<string, string>
     */
    public array $permissions = [];

    /**
     * Grants from modules are merged in; add your own here.
     *
     * @var array<string, list<string>>
     */
    public array $matrix = [];

    /**
     * @var list<string>
     */
    public array $defaultRoles = ['member'];

    /**
     * Accounts are created by an owner or admin (Administration → Staff).
     */
    public bool $allowRegistration = false;

    public string $defaultUserStatus = 'active';

    /**
     * Profile columns added by the platform migration AddProfileFieldsToUsers.
     *
     * @var list<string>
     */
    public array $userAllowedFields = ['first_name', 'last_name', 'job_title'];

    /**
     * @var list<string>
     */
    public array $identifiers = [self::IDENTIFIER_EMAIL];

    /**
     * @var list<string>
     */
    public array $loginIdentifiers = [self::IDENTIFIER_EMAIL];

    /**
     * @var list<string>
     */
    public array $requiredIdentifiers = [self::IDENTIFIER_EMAIL];

    /**
     * Sign people out after two hours without activity.
     */
    public int $sessionIdleTimeout = 7200;

    /**
     * @var array<string, class-string|null>
     */
    public array $notifiers = [
        OneTimeCode::CHANNEL_EMAIL => EmailNotifier::class,
        OneTimeCode::CHANNEL_SMS   => null,
    ];

    public string $viewLayout = 'Ephraitech\Platform\Views\layouts\auth';

    public string $viewSection = 'content';

    /**
     * The platform's sign-in pages: same fields and routes as ci4-auth's,
     * laid out for the platform's branded layout.
     *
     * @var array<string, string>
     */
    public array $views = [
        'login'           => 'Ephraitech\Platform\Views\auth\login',
        'forgot'          => 'Ephraitech\Platform\Views\auth\forgot',
        'reset'           => 'Ephraitech\Platform\Views\auth\reset',
        'verify'          => 'Ephraitech\Platform\Views\auth\verify',
        'change_password' => 'Ephraitech\Platform\Views\auth\change_password',
        'messages'        => 'Ephraitech\Platform\Views\auth\_messages',
    ];

    /**
     * @var array<string, string>
     */
    public array $redirects = [
        'login'         => '/login',
        'afterLogin'    => '/',
        'afterLogout'   => '/login',
        'forbidden'     => '/',
        'afterRegister' => '/',
    ];
}
