<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_arabic_rtl_and_has_no_registration(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('lang="ar"', false);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('تسجيل الدخول', false);
        $response->assertSee('auth-pane-visual', false);
        $response->assertDontSee('نسيت كلمة المرور؟', false);
        $response->assertDontSee('/register', false);
        $response->assertDontSee('إنشاء حساب', false);
    }

    public function test_users_can_authenticate_and_session_is_regenerated(): void
    {
        $user = User::factory()->create();

        $previousSessionId = session()->getId();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('account.profile'));
        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_can_logout_and_session_is_invalidated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('login'));

        $this->get(route('account.profile'))->assertRedirect(route('login'));
    }

    public function test_authenticated_routes_are_protected(): void
    {
        $this->get(route('account.profile'))->assertRedirect(route('login'));
        $this->put(route('account.password'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_redirected_away_from_guest_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_registration_routes_do_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }
}
