<?php

namespace Tests\Feature\Design;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_preview_url_redirects_to_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('design.dashboard-preview'))
            ->assertRedirect(route('home'));
    }

    public function test_legacy_preview_unavailable_outside_local_and_testing(): void
    {
        $user = User::factory()->create();
        $this->app['env'] = 'production';

        $this->actingAs($user)
            ->get(route('design.dashboard-preview'))
            ->assertNotFound();
    }
}
