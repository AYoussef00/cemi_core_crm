<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Support\Authorization\RoleSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class InactiveUserTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()
            ->withRole(RoleSlug::SalesRepresentative)
            ->inactive()
            ->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_is_logged_out_from_protected_routes(): void
    {
        $user = User::factory()
            ->withRole(RoleSlug::SalesRepresentative)
            ->create();

        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        $user->deactivate();

        $this->get(route('account.profile'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_active_user_can_still_authenticate(): void
    {
        $user = User::factory()
            ->withRole(RoleSlug::SalesRepresentative)
            ->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('account.profile'));

        $this->assertAuthenticatedAs($user);
    }
}
