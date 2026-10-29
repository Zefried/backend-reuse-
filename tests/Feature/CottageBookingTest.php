<?php

namespace Tests\Feature;

use App\Models\CottageBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CottageBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_submit_cottage_booking_enquiry_with_valid_data(): void
    {
        $payload = [
            'cottage_id' => 'air',
            'cottage_name' => 'House on Air',
            'guest_name' => 'Dr. Ananya Sarmah',
            'phone' => '+91 98765 12345',
            'email' => 'ananya@heritage.com',
            'arrival_date' => '2026-12-20',
            'nights' => '3 Nights',
            'guests_count' => '2 Guests',
            'special_requests' => 'Prefer quiet garden-facing stilt view.',
        ];

        $response = $this->postJson('/api/cottage-bookings', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data' => [
                    'cottage_id' => 'air',
                    'cottage_name' => 'House on Air',
                    'guest_name' => 'Dr. Ananya Sarmah',
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('cottage_bookings', [
            'cottage_id' => 'air',
            'cottage_name' => 'House on Air',
            'guest_name' => 'Dr. Ananya Sarmah',
            'status' => 'pending',
        ]);
    }

    public function test_cottage_booking_fails_when_required_fields_are_missing(): void
    {
        $response = $this->postJson('/api/cottage-bookings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cottage_id', 'cottage_name', 'guest_name', 'phone']);
    }

    public function test_admin_can_list_filter_and_search_cottage_bookings(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@heritagehouse.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        CottageBooking::create([
            'cottage_id' => 'air',
            'cottage_name' => 'House on Air',
            'guest_name' => 'Guest One',
            'phone' => '+91 91111 22222',
            'status' => 'pending',
        ]);

        CottageBooking::create([
            'cottage_id' => 'wheels',
            'cottage_name' => 'House on Wheels',
            'guest_name' => 'Guest Two',
            'phone' => '+91 93333 44444',
            'status' => 'confirmed',
        ]);

        // Filter by cottage_id
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/cottage-bookings?cottage_id=wheels');

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'metrics' => [
                    'total' => 2,
                    'pending' => 1,
                    'confirmed' => 1,
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Guest Two', $response->json('data.0.guest_name'));
    }

    public function test_admin_can_update_booking_status_quote_price_and_notes(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@heritagehouse.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $booking = CottageBooking::create([
            'cottage_id' => 'water',
            'cottage_name' => 'House on Water',
            'guest_name' => 'Vikram Roy',
            'phone' => '+91 97777 88888',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/cottage-bookings/{$booking->id}", [
                'status' => 'confirmed',
                'price_quoted' => 12500.00,
                'admin_notes' => 'Confirmed over phone call. Advance receipt generated.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $booking->id,
                    'status' => 'confirmed',
                    'price_quoted' => '12500.00',
                    'admin_notes' => 'Confirmed over phone call. Advance receipt generated.',
                ],
            ]);

        $this->assertDatabaseHas('cottage_bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
            'price_quoted' => 12500.00,
        ]);

        $this->assertNotNull(CottageBooking::find($booking->id)->last_contacted_at);
    }

    public function test_admin_can_delete_booking(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@heritagehouse.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $booking = CottageBooking::create([
            'cottage_id' => 'cave',
            'cottage_name' => 'Underground Stone Cave House',
            'guest_name' => 'Test Lead',
            'phone' => '+91 90000 00000',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/cottage-bookings/{$booking->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cottage_bookings', ['id' => $booking->id]);
    }
}
