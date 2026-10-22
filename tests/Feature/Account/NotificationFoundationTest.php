<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Notifications\InternalNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class NotificationFoundationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_notifier_creates_notification_for_one_user(): void
    {
        $user = User::factory()->administrator()->create();
        $notifier = app(InternalNotifier::class);

        $count = $notifier->notifyUser($user, [
            'title_ar' => 'تم حفظ الشركة',
            'body_ar' => 'اكتمل تحديث ملف الشركة.',
            'action_url' => '/admin/settings/company',
            'category' => 'settings',
            'password' => 'secret-should-not-persist',
            'token' => 'tok_abc',
        ]);

        $this->assertSame(1, $count);
        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(1, $user->unreadNotifications()->count());

        $data = $user->notifications()->first()->data;
        $this->assertSame('تم حفظ الشركة', $data['title_ar']);
        $this->assertSame('/admin/settings/company', $data['action_url']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('token', $data);
        $encoded = json_encode($data);
        $this->assertStringNotContainsString('secret-should-not-persist', (string) $encoded);
        $this->assertStringNotContainsString('tok_abc', (string) $encoded);
    }

    public function test_notifier_delivers_to_active_role_members_only(): void
    {
        $smA = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $smB = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $inactive = User::factory()->withRole(RoleSlug::SalesManager)->inactive()->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();

        $count = app(InternalNotifier::class)->notifyRole(RoleSlug::SalesManager, [
            'title_ar' => 'طلب اعتماد',
            'body_ar' => 'يوجد طلب بانتظار الاعتماد.',
        ]);

        $this->assertSame(2, $count);
        $this->assertSame(1, $smA->notifications()->count());
        $this->assertSame(1, $smB->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $rep->notifications()->count());
    }

    public function test_user_can_view_mark_one_and_mark_all_as_read(): void
    {
        $user = User::factory()->administrator()->create();
        $notifier = app(InternalNotifier::class);
        $notifier->notifyUser($user, ['title_ar' => 'إشعار ١']);
        $notifier->notifyUser($user, ['title_ar' => 'إشعار ٢']);

        $this->actingAs($user)
            ->get(route('account.notifications.index'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('الإشعارات', false)
            ->assertSee('إشعار ١', false)
            ->assertSee('إشعار ٢', false)
            ->assertSee('تعليم الكل كمقروء', false);

        $this->assertSame(2, $user->unreadNotifications()->count());

        $firstId = $user->notifications()->oldest()->value('id');

        $this->actingAs($user)
            ->post(route('account.notifications.read', $firstId))
            ->assertRedirect();

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());

        $this->actingAs($user)
            ->post(route('account.notifications.read-all'))
            ->assertRedirect()
            ->assertSessionHas('status_ar');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_empty_state_has_no_fabricated_notifications(): void
    {
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)
            ->get(route('account.notifications.index'))
            ->assertOk()
            ->assertSee('لا توجد إشعارات', false)
            ->assertDontSee('إشعار تجريبي', false)
            ->assertDontSee('example notification', false);
    }

    public function test_users_cannot_access_or_mutate_another_users_notifications(): void
    {
        $owner = User::factory()->administrator()->create();
        $other = User::factory()->withRole(RoleSlug::SalesManager)->create();

        app(InternalNotifier::class)->notifyUser($owner, ['title_ar' => 'خاص بالمالك']);
        $id = $owner->notifications()->value('id');

        $this->actingAs($other)
            ->get(route('account.notifications.index'))
            ->assertOk()
            ->assertDontSee('خاص بالمالك', false);

        $this->actingAs($other)
            ->post(route('account.notifications.read', $id))
            ->assertNotFound();

        $this->assertNull($owner->fresh()->notifications()->first()->read_at);

        auth()->logout();
        $this->get(route('account.notifications.index'))->assertRedirect(route('login'));
        $this->post(route('account.notifications.read', $id))->assertRedirect(route('login'));
    }

    public function test_guessed_notification_id_returns_404_for_owner_mismatch(): void
    {
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)
            ->post(route('account.notifications.read', '00000000-0000-0000-0000-000000000000'))
            ->assertNotFound();
    }

    public function test_external_action_urls_are_stripped(): void
    {
        $user = User::factory()->administrator()->create();

        app(InternalNotifier::class)->notifyUser($user, [
            'title_ar' => 'رابط خارجي مرفوض',
            'action_url' => 'https://evil.example/phish',
        ]);

        $data = $user->notifications()->first()->data;
        $this->assertNull($data['action_url']);
    }

    public function test_title_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(InternalNotifier::class)->sanitize(['body_ar' => 'بدون عنوان']);
    }

    public function test_notifications_use_database_channel_only(): void
    {
        $user = User::factory()->administrator()->create();
        app(InternalNotifier::class)->notifyUser($user, ['title_ar' => 'قناة قاعدة البيانات']);

        $row = DB::table('notifications')->where('notifiable_id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(\App\Notifications\InternalDatabaseNotification::class, $row->type);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_nav_shows_unread_badge(): void
    {
        $user = User::factory()->administrator()->create();
        app(InternalNotifier::class)->notifyUser($user, ['title_ar' => 'شارة']);

        $this->actingAs($user)
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee('nav-badge', false)
            ->assertSee(route('account.notifications.index'), false);
    }

    public function test_alert_room_feed_returns_newest_first_json(): void
    {
        $user = User::factory()->administrator()->create();
        $notifier = app(InternalNotifier::class);

        $this->travel(-2)->minutes();
        $notifier->notifyUser($user, [
            'title_ar' => 'أقدم',
            'body_ar' => 'جسم قديم',
            'category' => 'settings',
            'action_url' => '/account',
        ]);
        $this->travelBack();
        $notifier->notifyUser($user, [
            'title_ar' => 'أحدث',
            'action_url' => 'https://evil.example/x',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('account.notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread_count', 2);

        $items = $response->json('items');
        $this->assertIsArray($items);
        $this->assertCount(2, $items);
        $this->assertSame('أحدث', $items[0]['title_ar']);
        $this->assertSame('أقدم', $items[1]['title_ar']);
        $this->assertNull($items[0]['action_url']);
        $this->assertSame('/account', $items[1]['action_url']);
        $this->assertSame($items[0]['id'], $response->json('latest_id'));
        $this->assertTrue($items[0]['unread']);
    }

    public function test_alert_room_markup_has_no_chat_input(): void
    {
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee('id="alert-room"', false)
            ->assertSee('غرفة التنبيهات', false)
            ->assertSee('بلا محادثة وبلا إدخال', false)
            ->assertDontSee('alert-room-composer', false)
            ->assertDontSee('name="chat_message"', false)
            ->assertDontSee('<textarea', false);
    }

    public function test_guest_cannot_access_alert_room_feed(): void
    {
        $this->getJson(route('account.notifications.feed'))
            ->assertUnauthorized();
    }

    public function test_mark_as_read_json_from_alert_room(): void
    {
        $user = User::factory()->administrator()->create();
        app(InternalNotifier::class)->notifyUser($user, ['title_ar' => 'من الغرفة']);
        $id = $user->notifications()->value('id');

        $this->actingAs($user)
            ->postJson(route('account.notifications.read', $id))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull($user->fresh()->notifications()->first()->read_at);

        $this->actingAs($user)
            ->postJson(route('account.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
