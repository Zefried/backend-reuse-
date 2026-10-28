<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleBasedAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_create_users_with_different_roles()
    {
        // 1. Create Admin
        $admin = User::create([
            'name'     => 'zeffali7',
            'email'    => 'zeffali7@gmail.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        // 2. Admin Login
        $loginRes = $this->postJson('/api/login', [
            'email'    => 'zeffali7@gmail.com',
            'password' => 'password',
        ]);

        $loginRes->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'user' => [
                        'email' => 'zeffali7@gmail.com',
                        'role'  => 'admin',
                    ],
                ],
            ]);

        $adminToken = $loginRes->json('data.token');

        // 3. Admin creates a Sub-Admin
        $createSubAdminRes = $this->withHeader('Authorization', "Bearer $adminToken")
            ->postJson('/api/admin/users', [
                'name'     => 'Operations Manager',
                'email'    => 'subadmin@example.com',
                'password' => 'secret123',
                'role'     => 'sub_admin',
            ]);

        $createSubAdminRes->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'user' => [
                        'email' => 'subadmin@example.com',
                        'role'  => 'sub_admin',
                    ],
                ],
            ]);

        // 4. Admin creates a regular User
        $createUserRes = $this->withHeader('Authorization', "Bearer $adminToken")
            ->postJson('/api/admin/users', [
                'name'     => 'John Client',
                'email'    => 'client@example.com',
                'password' => 'secret123',
                'role'     => 'user',
            ]);

        $createUserRes->assertStatus(201);

        // 5. Newly created Sub-Admin can log in
        $subAdminLoginRes = $this->postJson('/api/login', [
            'email'    => 'subadmin@example.com',
            'password' => 'secret123',
        ]);

        $subAdminLoginRes->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'user' => [
                        'role' => 'sub_admin',
                    ],
                ],
            ]);

        $subAdminToken = $subAdminLoginRes->json('data.token');

        // 6. Sub-Admin cannot call admin user creation API (Unauthorized / 403)
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $subAdminUnauthorized = $this->withHeader('Authorization', "Bearer $subAdminToken")
            ->postJson('/api/admin/users', [
                'name'     => 'Unauthorized Account',
                'email'    => 'hack@example.com',
                'password' => 'secret123',
                'role'     => 'user',
            ]);

        $subAdminUnauthorized->assertStatus(403);

        // 7. Logout revokes token
        $logoutRes = $this->withHeader('Authorization', "Bearer $subAdminToken")
            ->postJson('/api/logout');

        $logoutRes->assertStatus(200)
            ->assertJson([
                'status' => true,
            ]);
    }
}
