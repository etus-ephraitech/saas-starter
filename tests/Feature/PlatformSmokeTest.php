<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * The app boots with the platform wired in: built-in modules registered,
 * the sign-in page served by the platform's views, the dashboard closed
 * to visitors.
 */
final class PlatformSmokeTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testBuiltInModulesAreRegistered(): void
    {
        $namespaces = service('autoloader')->getNamespace();

        $this->assertArrayHasKey('Ephraitech\Platform\Users', $namespaces);
        $this->assertTrue(service('moduleRegistry')->has('Core'));
    }

    public function testTheSignInPageUsesThePlatformLayout(): void
    {
        $result = $this->get('login');

        $result->assertOK();
        $result->assertSee('Sign in');
        $this->assertStringContainsString('vendor/platform/css/platform.css', (string) $result->getBody());
    }

    public function testTheDashboardNeedsASignIn(): void
    {
        $this->get('/')->assertRedirectTo('/login');
    }
}
