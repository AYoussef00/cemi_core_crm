<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile_in_arabic_rtl(): void
    {
        $user = User::factory()->create([
            'name' => 'مستخدم تجريبي',
            'email' => 'user@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('account.profile'));

        $response->assertOk();
        $response->assertSee('lang="ar"', false);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('مستخدم تجريبي', false);
        $response->assertSee('user@example.com', false);
        $response->assertSee('تغيير كلمة المرور', false);
    }

    public function test_user_can_update_password_with_strong_rules(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $response = $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status_ar');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPass123', $user->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.password'), [
            'current_password' => 'wrong-password',
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertSessionHasErrors('current_password');
    }
}
