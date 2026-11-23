<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiHealthAndAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Test health check endpoint diagnostics
     */
    public function test_health_check_endpoint_returns_healthy_diagnostics(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'healthy',
                    'services' => [
                        'database' => [
                            'status' => 'connected',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test authentication failure on bad credentials
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@nexus.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test login triggering two-factor authentication challenge
     */
    public function test_login_returns_2fa_challenge_for_protected_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@nexus.com',
            'password' => 'AdminSecurePass2026!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'requires2FA' => true,
            ]);
    }

    /**
     * Test 2FA passcode verification and token generation
     */
    public function test_verify_2fa_issues_sanctum_token(): void
    {
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@nexus.com',
            'password' => 'AdminSecurePass2026!',
        ]);

        $userId = $loginRes->json('userId');

        $verifyRes = $this->postJson('/api/v1/auth/verify-2fa', [
            'user_id' => $userId,
            'code' => '123456',
        ]);

        $verifyRes->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role'],
                ],
            ]);
    }
}
