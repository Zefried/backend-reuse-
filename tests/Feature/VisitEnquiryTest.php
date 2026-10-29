<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VisitEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VisitEnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_submit_visit_enquiry_with_valid_data(): void
    {
        $payload = [
            'name' => 'Arunav Sharma',
            'contact' => '+91 98765 43210',
            'visit_date' => '2026-11-15',
            'message' => 'Interested in touring the medicinal garden and tea tasting.',
        ];

        $response = $this->postJson('/api/visit-enquiries', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'name' => 'Arunav Sharma',
                    'contact' => '+91 98765 43210',
                    'status' => 'new',
                ],
            ]);

        $this->assertDatabaseHas('visit_enquiries', [
            'name' => 'Arunav Sharma',
            'contact' => '+91 98765 43210',
            'status' => 'new',
        ]);
    }

    public function test_visit_enquiry_validation_fails_when_name_or_contact_is_missing(): void
    {
        $response = $this->postJson('/api/visit-enquiries', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'contact']);
    }

    public function test_unauthenticated_or_non_admin_cannot_access_admin_visit_enquiries(): void
    {
        // 1. Unauthenticated guest
        $this->getJson('/api/admin/visit-enquiries')->assertStatus(401);

        // 2. Regular user (non-admin)
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/visit-enquiries')
            ->assertStatus(403);
    }

    public function test_admin_can_list_filter_and_search_visit_enquiries(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@heritagehouse.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        VisitEnquiry::create([
            'name' => 'Priyanka Borah',
            'contact' => 'priyanka@example.com',
            'status' => 'new',
        ]);

        VisitEnquiry::create([
            'name' => 'Rahul Saikia',
            'contact' => '+91 99999 88888',
            'status' => 'contacted',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/visit-enquiries?status=new');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'metrics' => [
                    'total' => 2,
                    'new' => 1,
                    'contacted' => 1,
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Priyanka Borah', $response->json('data.0.name'));

        // Search test
        $searchResponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/visit-enquiries?search=Rahul');

        $searchResponse->assertStatus(200);
        $this->assertCount(1, $searchResponse->json('data'));
        $this->assertEquals('Rahul Saikia', $searchResponse->json('data.0.name'));
    }

    public function test_admin_can_update_enquiry_status_and_add_call_notes(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@heritagehouse.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $enquiry = VisitEnquiry::create([
            'name' => 'Debabrata Gogoi',
            'contact' => '+91 91234 56789',
            'status' => 'new',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/visit-enquiries/{$enquiry->id}", [
                'status' => 'contacted',
                'admin_notes' => 'Called guest. They will visit with family of 4 on Sunday.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $enquiry->id,
                    'status' => 'contacted',
                    'admin_notes' => 'Called guest. They will visit with family of 4 on Sunday.',
                ],
            ]);

        $this->assertDatabaseHas('visit_enquiries', [
            'id' => $enquiry->id,
            'status' => 'contacted',
            'admin_notes' => 'Called guest. They will visit with family of 4 on Sunday.',
        ]);

        $this->assertNotNull(VisitEnquiry::find($enquiry->id)->last_contacted_at);
    }

    public function test_admin_can_delete_enquiry(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@heritagehouse.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $enquiry = VisitEnquiry::create([
            'name' => 'Spam Test',
            'contact' => 'spam@test.com',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/visit-enquiries/{$enquiry->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('visit_enquiries', ['id' => $enquiry->id]);
    }
}
