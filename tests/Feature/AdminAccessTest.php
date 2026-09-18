<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.local',
            'password' => Hash::make('secret123'),
            'is_admin' => true,
        ]);
    }

    private function makeRegularUser(): User
    {
        return User::create([
            'name'     => 'User',
            'email'    => 'user@test.local',
            'password' => Hash::make('secret123'),
            'is_admin' => false,
        ]);
    }

    public function test_unauthenticated_redirected_to_login(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    public function test_admin_login_page_returns_200(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    public function test_admin_user_can_access_panel(): void
    {
        $admin = $this->makeAdmin();
        $this->assertTrue($admin->canAccessPanel(app(\Filament\Panel::class)));
    }

    public function test_regular_user_cannot_access_panel(): void
    {
        $user = $this->makeRegularUser();
        $this->assertFalse($user->canAccessPanel(app(\Filament\Panel::class)));
    }

    public function test_storefront_routes_still_200(): void
    {
        $routes = [
            '/',
            '/catalog',
        ];

        foreach ($routes as $path) {
            $response = $this->get($path);
            $response->assertStatus(200, "Route {$path} returned non-200");
        }
    }

    public function test_cart_route_returns_200(): void
    {
        $response = $this->get(route('cart.index'));
        $response->assertStatus(200);
    }
}
