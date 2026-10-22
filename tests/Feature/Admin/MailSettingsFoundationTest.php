<?php

namespace Tests\Feature\Admin;

use App\Mail\MailConfigurationTestMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Mail\MailTestSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class MailSettingsFoundationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_administrator_can_view_mail_status_with_masked_secrets(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'smtp-user',
            'mail.mailers.smtp.password' => 'super-secret-password',
            'mail.from.address' => 'noreply@example.test',
            'mail.from.name' => 'Cemi',
        ]);

        $admin = User::factory()->administrator()->create([
            'email' => 'admin.mail@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.mail.show'));

        $response->assertOk();
        $response->assertSee('lang="ar"', false);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('إعداد البريد الإلكتروني', false);
        $response->assertSee('smtp.example.test', false);
        $response->assertSee('••••••••', false);
        $response->assertDontSee('super-secret-password', false);
        $response->assertDontSee('smtp-user', false);
        $response->assertSee('تفاصيل للمشغّل', false);
        $response->assertDontSee('MAIL_PASSWORD', false);
        $html = $response->getContent();
        $this->assertStringNotContainsString('super-secret-password', $html);
        $this->assertStringNotContainsString('MAIL_PASSWORD', $html);
    }

    public function test_log_mailer_is_labelled_as_local_only(): void
    {
        config([
            'mail.default' => 'log',
            'mail.from.address' => 'noreply@example.test',
        ]);

        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.mail.show'))
            ->assertOk()
            ->assertSee('اختبار محلي فقط', false)
            ->assertSee('لا يصل إلى صندوق الوارد', false)
            ->assertSee('تسجيل محلي', false);
    }

    public function test_non_administrators_and_guests_cannot_access_mail_settings(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();

        $this->get(route('admin.settings.mail.show'))->assertRedirect(route('login'));
        $this->post(route('admin.settings.mail.test'))->assertRedirect(route('login'));

        $this->actingAs($manager)->get(route('admin.settings.mail.show'))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.settings.mail.test'))->assertForbidden();
        $this->actingAs($rep)->get(route('admin.settings.mail.show'))->assertForbidden();
        $this->actingAs($rep)->post(route('admin.settings.mail.test'))->assertForbidden();
    }

    public function test_administrator_can_send_test_email_to_own_account_using_fake_mailer(): void
    {
        Mail::fake();

        config([
            'mail.default' => 'array',
            'mail.from.address' => 'noreply@example.test',
        ]);

        $admin = User::factory()->administrator()->create([
            'email' => 'admin.mail@example.com',
            'name' => 'مدير النظام',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.mail.test'))
            ->assertRedirect(route('admin.settings.mail.show'))
            ->assertSessionHas('status_ar')
            ->assertSessionHas('mail_test_local_only', true);

        Mail::assertSent(MailConfigurationTestMail::class, function (MailConfigurationTestMail $mail) use ($admin) {
            return $mail->hasTo($admin->email)
                && $mail->adminName === $admin->name;
        });

        $audit = AuditLog::query()->where('action', 'mail.test_sent')->first();
        $this->assertNotNull($audit);
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertTrue(data_get($audit->properties, 'local_only'));
        $this->assertStringNotContainsString('password', strtolower((string) json_encode($audit->properties)));
        $encoded = (string) json_encode($audit->properties);
        $this->assertStringNotContainsString($admin->email, $encoded);
    }

    public function test_provider_failure_is_handled_without_exposing_secrets(): void
    {
        $admin = User::factory()->administrator()->create([
            'email' => 'admin.mail@example.com',
        ]);

        $this->mock(MailTestSender::class, function ($mock) use ($admin): void {
            $mock->shouldReceive('sendToSelf')
                ->once()
                ->withArgs(fn (User $user) => $user->is($admin))
                ->andThrow(new RuntimeException('تعذر إرسال رسالة الاختبار. تحقق من إعدادات المزود في بيئة النشر دون مشاركة الأسرار.'));
        });

        $response = $this->actingAs($admin)
            ->from(route('admin.settings.mail.show'))
            ->post(route('admin.settings.mail.test'));

        $response->assertRedirect(route('admin.settings.mail.show'));
        $response->assertSessionHasErrors('mail_test');
        $this->assertStringNotContainsString('password', strtolower(session('errors')->first('mail_test')));
        $this->assertStringNotContainsString('Exception', session('errors')->first('mail_test'));
    }

    public function test_mail_test_is_throttled(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'array',
            'mail.from.address' => 'noreply@example.test',
        ]);

        $admin = User::factory()->administrator()->create([
            'email' => 'admin.mail@example.com',
        ]);

        RateLimiter::clear('Illuminate\Routing\Middleware\ThrottleRequests:'.sha1($admin->id.'|'.'127.0.0.1'));

        $this->actingAs($admin);

        foreach (range(1, 3) as $i) {
            $this->post(route('admin.settings.mail.test'))->assertRedirect(route('admin.settings.mail.show'));
        }

        $this->post(route('admin.settings.mail.test'))->assertStatus(429);
    }

    public function test_settings_hub_links_to_mail_page(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee(route('admin.settings.mail.show'), false)
            ->assertSee('حالة البريد', false);
    }

    public function test_password_reset_flow_still_works_with_array_mailer(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset.user@example.com',
        ]);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertSessionHas('status_ar');

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $user,
            \App\Notifications\ResetPasswordNotification::class,
        );
    }
}
