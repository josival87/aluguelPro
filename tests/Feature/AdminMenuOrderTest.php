<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_charges_menu_appears_immediately_after_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $html = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $dashboardPosition = strpos($html, 'Dashboard');
        $chargesPosition = strpos($html, 'Cobranças');
        $assistantPosition = strpos($html, 'Assistente IA');

        $this->assertNotFalse($dashboardPosition);
        $this->assertNotFalse($chargesPosition);
        $this->assertNotFalse($assistantPosition);
        $this->assertGreaterThan($dashboardPosition, $chargesPosition);
        $this->assertLessThan($assistantPosition, $chargesPosition);
    }
}
