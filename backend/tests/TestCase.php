<?php

namespace Tests;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * The container (and its auth guards) persists across HTTP calls inside
     * one test. In production each request is a fresh process, so flush the
     * guard cache AND tymon's JWT token cache here to faithfully simulate that.
     * (tymon's JWT manager is a singleton that caches the parsed token.)
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        Auth::forgetGuards();

        if ($this->app->bound('tymon.jwt')) {
            $this->app['tymon.jwt']->unsetToken();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** Log in via the real API and return the JWT bearer token. */
    protected function loginAs(string $email, string $password = 'password123'): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertOk();

        return $response->json('data.token');
    }

    /** Authorization header array for an already-issued token. */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
