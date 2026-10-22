<?php

namespace Tests\Feature\Admin;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Attachments\AttachmentService;
use App\Support\Authorization\RoleSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class AttachmentFoundationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
        Storage::fake('attachments');
        config(['attachments.disk' => 'attachments']);
    }

    public function test_administrator_can_upload_list_download_and_remove_company_attachment(): void
    {
        $admin = User::factory()->administrator()->create();
        $company = Company::saveSingleton(['name_ar' => 'شركة سيمي']);

        $file = UploadedFile::fake()->create('license.pdf', 120, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('admin.settings.company.attachments.store'), ['file' => $file])
            ->assertRedirect(route('admin.settings.company.edit'))
            ->assertSessionHas('status_ar');

        $attachment = Attachment::query()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('company', $attachment->attachable_type);
        $this->assertSame($company->id, $attachment->attachable_id);
        $this->assertSame('license.pdf', $attachment->original_name);
        Storage::disk('attachments')->assertExists($attachment->path);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attachment.uploaded',
            'actor_user_id' => $admin->id,
        ]);

        $uploadAudit = AuditLog::query()->where('action', 'attachment.uploaded')->first();
        $this->assertStringNotContainsString('content', strtolower((string) json_encode($uploadAudit->properties)));
        $this->assertArrayNotHasKey('path', $uploadAudit->properties ?? []);

        $this->actingAs($admin)
            ->get(route('admin.settings.company.edit'))
            ->assertOk()
            ->assertSee('license.pdf', false)
            ->assertSee('المرفقات', false)
            ->assertSee('dir="rtl"', false);

        $this->actingAs($admin)
            ->get(route('admin.settings.company.attachments.download', $attachment))
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('admin.settings.company.attachments.destroy', $attachment))
            ->assertRedirect(route('admin.settings.company.edit'));

        $this->assertDatabaseCount('attachments', 0);
        Storage::disk('attachments')->assertMissing($attachment->path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attachment.removed',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_invalid_type_and_oversize_are_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        Company::saveSingleton(['name_ar' => 'شركة']);

        $exe = UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload');
        $this->actingAs($admin)
            ->from(route('admin.settings.company.edit'))
            ->post(route('admin.settings.company.attachments.store'), ['file' => $exe])
            ->assertRedirect(route('admin.settings.company.edit'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('attachments', 0);

        config(['attachments.max_kilobytes' => 10]);
        $big = UploadedFile::fake()->create('big.pdf', 50, 'application/pdf');
        $this->actingAs($admin)
            ->from(route('admin.settings.company.edit'))
            ->post(route('admin.settings.company.attachments.store'), ['file' => $big])
            ->assertRedirect(route('admin.settings.company.edit'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_unauthorized_roles_cannot_manage_attachments(): void
    {
        Company::saveSingleton(['name_ar' => 'شركة']);
        $attachment = $this->storeViaService(
            Company::current(),
            UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            User::factory()->administrator()->create(),
        );

        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();

        foreach ([$manager, $rep] as $user) {
            $this->actingAs($user)
                ->post(route('admin.settings.company.attachments.store'), [
                    'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.settings.company.attachments.download', $attachment))
                ->assertForbidden();

            $this->actingAs($user)
                ->delete(route('admin.settings.company.attachments.destroy', $attachment))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('attachments', 1);
        Storage::disk('attachments')->assertExists($attachment->path);
    }

    public function test_cross_record_download_is_denied(): void
    {
        $admin = User::factory()->administrator()->create();
        Company::saveSingleton(['name_ar' => 'شركة']);
        Warehouse::saveSingleton(['name_ar' => 'مستودع']);

        $companyAttachment = $this->storeViaService(
            Company::current(),
            UploadedFile::fake()->create('company.pdf', 10, 'application/pdf'),
            $admin,
        );

        $this->actingAs($admin)
            ->get(route('admin.settings.warehouse.attachments.download', $companyAttachment))
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.settings.warehouse.attachments.destroy', $companyAttachment))
            ->assertNotFound();

        $this->assertDatabaseHas('attachments', ['uuid' => $companyAttachment->uuid]);
    }

    public function test_guessed_uuid_cannot_download_without_auth_and_missing_returns_404(): void
    {
        Company::saveSingleton(['name_ar' => 'شركة']);
        $admin = User::factory()->administrator()->create();
        $attachment = $this->storeViaService(
            Company::current(),
            UploadedFile::fake()->create('secret.pdf', 10, 'application/pdf'),
            $admin,
        );

        $this->get(route('admin.settings.company.attachments.download', $attachment))
            ->assertRedirect(route('login'));

        $this->actingAs($admin)
            ->get(route('admin.settings.company.attachments.download', '00000000-0000-0000-0000-000000000000'))
            ->assertNotFound();
    }

    public function test_warehouse_attachments_work_and_public_url_is_not_used(): void
    {
        $admin = User::factory()->administrator()->create();
        Warehouse::saveSingleton(['name_ar' => 'المستودع']);

        $file = UploadedFile::fake()->image('plan.png', 40, 40);

        $this->actingAs($admin)
            ->post(route('admin.settings.warehouse.attachments.store'), ['file' => $file])
            ->assertRedirect(route('admin.settings.warehouse.edit'));

        $attachment = Attachment::query()->first();
        $this->assertSame('warehouse', $attachment->attachable_type);
        $this->assertStringNotContainsString('/storage/', $attachment->path);
        $this->assertFalse(str_starts_with($attachment->path, 'http'));

        $this->actingAs($admin)
            ->get(route('admin.settings.warehouse.edit'))
            ->assertOk()
            ->assertSee('plan.png', false)
            ->assertDontSee('/storage/', false);
    }

    public function test_failed_persist_cleans_up_stored_file(): void
    {
        $admin = User::factory()->administrator()->create();
        $company = Company::saveSingleton(['name_ar' => 'شركة']);
        $service = app(AttachmentService::class);

        Attachment::creating(function (): void {
            throw new \RuntimeException('forced db failure');
        });

        $before = Storage::disk('attachments')->allFiles();

        try {
            $service->store(
                $company,
                UploadedFile::fake()->create('orphan.pdf', 10, 'application/pdf'),
                $admin,
            );
            $this->fail('Expected store to throw');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced db failure', $e->getMessage());
        }

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame($before, Storage::disk('attachments')->allFiles());
    }

    public function test_delete_removes_database_row_and_storage_object(): void
    {
        $admin = User::factory()->administrator()->create();
        $company = Company::saveSingleton(['name_ar' => 'شركة']);
        $attachment = $this->storeViaService(
            $company,
            UploadedFile::fake()->create('keep.pdf', 10, 'application/pdf'),
            $admin,
        );
        $path = $attachment->path;

        app(AttachmentService::class)->delete($attachment, $admin);

        $this->assertDatabaseCount('attachments', 0);
        Storage::disk('attachments')->assertMissing($path);
    }

    public function test_attachments_ui_hidden_until_company_exists(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('admin.settings.company.edit'))
            ->assertOk()
            ->assertDontSee('attachments-panel', false)
            ->assertSee('حالة أولية فارغة', false);
    }

    private function storeViaService($attachable, UploadedFile $file, User $user): Attachment
    {
        return app(AttachmentService::class)->store($attachable, $file, $user);
    }
}
