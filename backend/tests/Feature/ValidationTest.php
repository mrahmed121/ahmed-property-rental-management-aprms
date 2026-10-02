<?php

namespace Tests\Feature;

use Tests\TestCase;

class ValidationTest extends TestCase
{
    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_malformed_email(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_user_validates_input(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->postJson('/api/v1/users', [
            'name' => '',
            'email' => 'bad-email',
            'password' => 'short',
        ], $this->bearer($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_create_user_rejects_duplicate_email(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->postJson('/api/v1/users', [
            'name' => 'Duplicate',
            'email' => 'manager@ahmedestates.local', // already seeded
            'password' => 'password123',
        ], $this->bearer($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_settings_update_rejects_invalid_type(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'currency', 'value' => 'PKR', 'type' => 'nonsense'],
            ],
        ], $this->bearer($token))->assertStatus(422);
    }

    public function test_settings_update_rejects_bad_key_format(): void
    {
        $token = $this->loginAs('admin@ahmedestates.local');

        $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'BAD KEY!', 'value' => 'x'],
            ],
        ], $this->bearer($token))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.0.key']);
    }
}
