<?php

namespace Tests\Feature;

use Tests\TestCase;

class DesegoHttpTest extends TestCase
{
    public function test_administration_and_store_render_with_laravel_csrf_tokens(): void
    {
        $this->get('/admin/legacy')->assertRedirect('/ingresar?staff=1');
        $this->get('/ingresar')->assertOk()->assertSee('csrf-token', false);
        $this->get('/tienda')->assertRedirect('/');
        $this->get('/index.php')->assertRedirect('/admin');
        $this->get('/tienda.php')->assertRedirect('/');
    }

    public function test_setup_scripts_and_private_information_are_not_routable(): void
    {
        foreach (['/setup.php', '/config/database.php', '/includes/bootstrap.php', '/database/desego/schema.sql', '/api/unknown.php'] as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->get('/storage/backups/test.dsgbk')->assertForbidden();
    }

    public function test_mutations_reject_missing_csrf_tokens(): void
    {
        $this->postJson('/api/auth.php?action=login', ['email' => 'nobody@example.test', 'password' => 'invalid'])->assertStatus(419);
    }

    public function test_laravel_middleware_rejects_invalid_csrf_tokens_outside_testing_bypass(): void
    {
        $this->app['env'] = 'local';
        $this->withSession(['_token' => 'valid-token'])->postJson('/api/auth.php?action=login', [], ['X-CSRF-TOKEN' => 'wrong-token'])->assertStatus(419);
    }

    public function test_external_api_requires_a_bearer_token(): void
    {
        $this->getJson('/api/external.php?resource=products')->assertUnauthorized()->assertJsonPath('ok', false)->assertHeader('Vary', 'Authorization');
    }
}
