<?php

namespace Tests\Feature\Customers;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class CustomerLocationAndPhotosTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
        Storage::fake((string) config('attachments.disk', 'attachments'));
    }

    public function test_authorized_user_can_save_and_update_location(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();

        $this->actingAs($rep)
            ->put(route('customers.location.update', $customer), [
                'address_ar' => 'الرياض — حي النرجس',
                'latitude' => '24.7136000',
                'longitude' => '46.6753000',
            ])
            ->assertRedirect(route('customers.location.edit', $customer));

        $customer->refresh();
        $this->assertSame('الرياض — حي النرجس', $customer->address_ar);
        $this->assertEqualsWithDelta(24.7136, (float) $customer->latitude, 0.0001);
        $this->assertEqualsWithDelta(46.6753, (float) $customer->longitude, 0.0001);

        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.location_updated']);

        $this->actingAs($rep)
            ->get(route('customers.location.edit', $customer))
            ->assertOk()
            ->assertSee('الرياض — حي النرجس', false)
            ->assertSee('customer-location-map', false);
    }

    public function test_coordinate_validation_ranges_and_pair_rule(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();

        $this->actingAs($rep)
            ->from(route('customers.location.edit', $customer))
            ->put(route('customers.location.update', $customer), [
                'latitude' => '95',
                'longitude' => '46',
            ])
            ->assertSessionHasErrors('latitude');

        $this->actingAs($rep)
            ->from(route('customers.location.edit', $customer))
            ->put(route('customers.location.update', $customer), [
                'latitude' => '24.7',
                'longitude' => '',
            ])
            ->assertSessionHasErrors('latitude');
    }

    public function test_sales_rep_denied_other_reps_location_and_photos(): void
    {
        $repA = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $repB = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($repA)->create([
            'latitude' => 24.7,
            'longitude' => 46.6,
        ]);

        $this->actingAs($repA)->post(route('customers.photos.store', $customer), [
            'file' => UploadedFile::fake()->image('site.jpg', 200, 200),
        ])->assertRedirect();

        $attachment = Attachment::query()->firstOrFail();

        $this->actingAs($repB)->get(route('customers.location.edit', $customer))->assertForbidden();
        $this->actingAs($repB)->put(route('customers.location.update', $customer), [
            'address_ar' => 'اختراق',
            'latitude' => 1,
            'longitude' => 1,
        ])->assertForbidden();
        $this->actingAs($repB)->get(route('customers.photos.index', $customer))->assertForbidden();
        $this->actingAs($repB)->get(route('customers.photos.download', [$customer, $attachment]))->assertForbidden();
        $this->actingAs($repB)->delete(route('customers.photos.destroy', [$customer, $attachment]))->assertForbidden();
    }

    public function test_photo_upload_download_remove_and_audit(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $customer = Customer::factory()->create();

        $this->actingAs($manager)
            ->post(route('customers.photos.store', $customer), [
                'file' => UploadedFile::fake()->image('front.png', 120, 120),
            ])
            ->assertRedirect(route('customers.photos.index', $customer));

        $attachment = Attachment::query()->firstOrFail();
        $this->assertSame('customer', $attachment->attachable_type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attachment.uploaded']);

        $this->actingAs($manager)
            ->get(route('customers.photos.download', [$customer, $attachment]))
            ->assertOk();

        $this->actingAs($manager)
            ->delete(route('customers.photos.destroy', [$customer, $attachment]))
            ->assertRedirect(route('customers.photos.index', $customer));

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attachment.removed']);
    }

    public function test_photo_rejects_non_image_and_oversize(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $customer = Customer::factory()->create();

        $this->actingAs($manager)
            ->from(route('customers.photos.index', $customer))
            ->post(route('customers.photos.store', $customer), [
                'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::query()->count());

        $tooBigKb = (int) config('attachments.max_kilobytes', 5120) + 100;
        $this->actingAs($manager)
            ->from(route('customers.photos.index', $customer))
            ->post(route('customers.photos.store', $customer), [
                'file' => UploadedFile::fake()->image('big.jpg')->size($tooBigKb),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_cross_customer_photo_uuid_denied(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $customerA = Customer::factory()->create(['name_ar' => 'أ']);
        $customerB = Customer::factory()->create(['name_ar' => 'ب']);

        $this->actingAs($manager)->post(route('customers.photos.store', $customerA), [
            'file' => UploadedFile::fake()->image('a.jpg'),
        ]);
        $attachment = Attachment::query()->firstOrFail();

        $this->actingAs($manager)
            ->get(route('customers.photos.download', [$customerB, $attachment]))
            ->assertNotFound();
    }

    public function test_executive_can_view_location_but_not_update_or_upload(): void
    {
        $executive = User::factory()->withRole(RoleSlug::Executive)->create();
        $customer = Customer::factory()->create(['address_ar' => 'عنوان مرئي']);

        $this->actingAs($executive)
            ->get(route('customers.location.edit', $customer))
            ->assertOk()
            ->assertSee('عرض فقط', false);

        $this->actingAs($executive)
            ->put(route('customers.location.update', $customer), [
                'address_ar' => 'تعديل مرفوض',
                'latitude' => 24,
                'longitude' => 46,
            ])
            ->assertForbidden();

        $this->actingAs($executive)
            ->post(route('customers.photos.store', $customer), [
                'file' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertForbidden();
    }
}
