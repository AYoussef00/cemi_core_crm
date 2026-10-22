<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_explains_mail_configuration_when_using_log_mailer(): void
    {
        config(['mail.default' => 'log']);

        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('إرسال البريد غير متاح حاليًا', false);
        $response->assertDontSee('MAIL_MAILER', false);
        $response->assertDontSee('.env', false);
        $response->assertSee('dir="rtl"', false);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertSessionHas('status_ar');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $response = $this->get(route('password.reset', [
                'token' => $notification->token,
                'email' => $user->email,
            ]));

            $response->assertOk();
            $response->assertSee('تعيين كلمة مرور جديدة', false);

            $resetResponse = $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NewPass123',
                'password_confirmation' => 'NewPass123',
            ]);

            $resetResponse->assertRedirect(route('login'));
            $user->refresh();
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPass123', $user->password));

            return true;
        });
    }

    public function test_weak_password_is_rejected_on_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])->assertSessionHasErrors('password');

            return true;
        });
    }
}
