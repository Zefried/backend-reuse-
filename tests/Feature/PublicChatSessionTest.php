<?php

namespace Tests\Feature;

use App\Models\CampaignInformation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicChatSessionTest extends TestCase
{
    use RefreshDatabase;
    public function test_init_session_with_active_service()
    {
        $business = User::factory()->create([
            'business_id' => 'BUS-TEST01',
            'role'        => 'business',
            'name'        => 'Acme Interior',
            'bio'         => 'Luxury interior design.',
        ]);

        $service = Service::create([
            'business_id' => 'BUS-TEST01',
            'name'        => 'Modular Kitchen',
            'slug'        => 'modular-kitchen',
            'status'      => 'active',
        ]);

        $token = base64_encode('BUS-TEST01||rand123');

        CampaignInformation::create([
            'business_id'   => 'BUS-TEST01',
            'service_id'    => $service->id,
            'campaign_name' => 'Kitchen Promo',
            'campaign_link' => 'http://localhost:5173/chat/' . $token,
        ]);

        $response = $this->postJson('/api/public/chat/session/init', [
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'business_id'   => 'BUS-TEST01',
                    'service_id'    => $service->id,
                    'service_name'  => 'Modular Kitchen',
                    'options'       => [
                        'Tell me about the price of Modular Kitchen.',
                        'Share the portfolio of Modular Kitchen.',
                    ],
                ],
            ]);
    }

    public function test_init_session_with_nullable_service_never_falls_back()
    {
        $business = User::factory()->create([
            'business_id' => 'BUS-TEST02',
            'role'        => 'business',
            'name'        => 'Acme General',
        ]);

        // Create an existing service that must NOT be picked as a fallback
        Service::create([
            'business_id' => 'BUS-TEST02',
            'name'        => 'Living Room Decor',
            'slug'        => 'living-room-decor',
            'status'      => 'active',
        ]);

        $token = base64_encode('BUS-TEST02||rand456');

        CampaignInformation::create([
            'business_id'   => 'BUS-TEST02',
            'service_id'    => null, // Nullable service
            'campaign_name' => 'General Inquiries',
            'campaign_link' => 'http://localhost:5173/chat/' . $token,
        ]);

        $response = $this->postJson('/api/public/chat/session/init', [
            'token' => $token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'business_id'   => 'BUS-TEST02',
                    'service_id'    => null,
                    'service_name'  => null,
                    'options'       => [
                        'Tell me about your pricing.',
                        'Share your portfolio.',
                    ],
                ],
            ]);
    }

    public function test_init_session_rejects_invalid_token()
    {
        $response = $this->postJson('/api/public/chat/session/init', [
            'token' => 'invalid-non-base64',
        ]);

        $response->assertStatus(400);
    }
}
