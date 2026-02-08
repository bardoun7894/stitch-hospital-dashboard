<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    public function test_health_endpoint_returns_ok_payload(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'version',
            ])
            ->assertJson([
                'status' => 'ok',
            ]);
    }

    public function test_mobile_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/mobile/profile');

        $this->assertContains($response->status(), [401, 503]);
    }

    public function test_mobile_profile_bootstrap_requires_authentication(): void
    {
        $response = $this->postJson('/api/mobile/profile/bootstrap', [
            'name' => 'Test User',
        ]);

        $this->assertContains($response->status(), [401, 503]);
    }
}
