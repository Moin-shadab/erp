<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    /**
     * Test notification test-send route.
     */
    public function test_notification_routing_test_endpoint(): void
    {
        $user = \App\Models\User::first();
        if (!$user) {
            $roleId = \Illuminate\Support\Facades\DB::table('roles')->insertGetId([
                'name' => 'Test Role',
                'slug' => 'test-role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $user = \App\Models\User::factory()->create([
                'role_id' => $roleId,
            ]);
        }
        
        $this->actingAs($user);
        
        $receiver = \App\Models\User::factory()->create([
            'role_id' => $user->role_id,
        ]);
        
        \Illuminate\Support\Facades\DB::table('notification_routes')->insert([
            'sender_id' => $user->id,
            'receiver_id' => $receiver->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $response = $this->postJson('/api/notifications/test-send', [
            'sender_id' => $user->id,
            'title' => 'Test Notification Title',
            'message' => 'Test Notification Message',
        ]);
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'receivers' => [$receiver->name]
        ]);
        
        $this->assertDatabaseHas('notifications', [
            'user_id' => $receiver->id,
            'title' => 'Test Notification Title',
            'message' => 'Test Notification Message',
        ]);
    }

    /**
     * Test email pages load and initialize tables dynamically.
     */
    public function test_email_pages_ensure_tables_exist(): void
    {
        // 1. Create role and user
        $roleId = \Illuminate\Support\Facades\DB::table('roles')->insertGetId([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = \App\Models\User::factory()->create([
            'role_id' => $roleId,
        ]);
        $this->actingAs($user);

        // 2. Insert pages metadata
        $moduleId = \Illuminate\Support\Facades\DB::table('modules')->insertGetId([
            'name' => 'Communication',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $emailInboxPageId = \Illuminate\Support\Facades\DB::table('pages')->insertGetId([
            'module_id' => $moduleId,
            'name' => 'Email Inbox',
            'slug' => 'email-inbox',
            'custom_view' => 'modules/email/inbox',
            'is_custom' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Confirm tables do not exist before call
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('email_labels'));

        // 4. Load page via AJAX
        $response = $this->get('/erp/email-inbox', [
            'HTTP_X-Requested-With' => 'XMLHttpRequest'
        ]);

        // 5. Assert successful response and table exists!
        $response->assertStatus(200);
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('email_labels'));
    }
}
