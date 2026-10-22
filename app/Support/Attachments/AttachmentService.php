<?php

namespace App\Support\Attachments;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Reusable private attachment store. Future domain modules attach to any Model
 * that uses HasAttachments — do not bypass this service for storage paths.
 */
class AttachmentService
{
    public function __construct(private AuditLogger $audit) {}

    public function store(Model $attachable, UploadedFile $file, User $uploader): Attachment
    {
        $extension = $this->resolvedExtension($file);
        $mime = $this->resolvedMime($file);
        $this->assertAllowed($extension, $mime);

        if ($attachable instanceof Customer) {
            $photoExt = config('attachments.customer_photo_extensions', []);
            if (! in_array($extension, $photoExt, true)) {
                throw new RuntimeException('صور العميل تقبل ملفات الصور فقط.');
            }
        }

        $originalName = $this->sanitizeOriginalName($file->getClientOriginalName(), $extension);
        $sizeBytes = $file->getSize() ?: 0;

        return $this->persistStoredFile(
            $attachable,
            $uploader,
            $extension,
            $mime,
            $originalName,
            $sizeBytes,
            function (string $directory, string $uuid, string $extension) use ($file): string {
                $filename = $uuid.'.'.$extension;
                $stored = Storage::disk((string) config('attachments.disk', 'attachments'))
                    ->putFileAs($directory, $file, $filename);

                if ($stored === false) {
                    throw new RuntimeException('تعذر حفظ الملف على القرص الخاص.');
                }

                return $directory.'/'.$filename;
            },
        );
    }

    /**
     * Store a generated/binary file (e.g. system PDF) as a private attachment.
     */
    public function storeContents(
        Model $attachable,
        string $binary,
        string $originalName,
        string $extension,
        string $mime,
        User $uploader,
    ): Attachment {
        $extension = strtolower($extension);
        $mime = strtolower($mime);
        $this->assertAllowed($extension, $mime);
        $originalName = $this->sanitizeOriginalName($originalName, $extension);

        return $this->persistStoredFile(
            $attachable,
            $uploader,
            $extension,
            $mime,
            $originalName,
            strlen($binary),
            function (string $directory, string $uuid, string $extension) use ($binary): string {
                $disk = Storage::disk((string) config('attachments.disk', 'attachments'));
                $path = $directory.'/'.$uuid.'.'.$extension;
                if (! $disk->put($path, $binary)) {
                    throw new RuntimeException('تعذر حفظ الملف على القرص الخاص.');
                }

                return $path;
            },
        );
    }

    /**
     * @param  callable(string, string, string): string  $writer  receives directory, uuid, extension; returns full path
     */
    private function persistStoredFile(
        Model $attachable,
        User $uploader,
        string $extension,
        string $mime,
        string $originalName,
        int $sizeBytes,
        callable $writer,
    ): Attachment {
        $disk = (string) config('attachments.disk', 'attachments');
        $uuid = (string) Str::uuid();
        $directory = $this->directoryFor($attachable);
        $path = $writer($directory, $uuid, $extension);

        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('تعذر حفظ الملف على القرص الخاص.');
        }

        try {
            return DB::transaction(function () use (
                $attachable,
                $uploader,
                $disk,
                $path,
                $originalName,
                $mime,
                $extension,
                $sizeBytes,
                $uuid,
            ): Attachment {
                $attachment = $attachable->attachments()->create([
                    'uuid' => $uuid,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $originalName,
                    'mime_type' => $mime,
                    'extension' => $extension,
                    'size_bytes' => $sizeBytes,
                    'uploaded_by_user_id' => $uploader->id,
                ]);

                $this->audit->record('attachment.uploaded', $attachment, [
                    'attachable_type' => $attachable->getMorphClass(),
                    'attachable_id' => $attachable->getKey(),
                    'original_name' => $originalName,
                    'mime_type' => $mime,
                    'extension' => $extension,
                    'size_bytes' => $attachment->size_bytes,
                ], $uploader);

                return $attachment;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404, 'الملف غير موجود.');
        }

        return $disk->download(
            $attachment->path,
            $attachment->downloadBasename(),
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * Open the file in the browser (inline) instead of forcing a download.
     */
    public function stream(Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            abort(404, 'الملف غير موجود.');
        }

        return $disk->response(
            $attachment->path,
            $attachment->downloadBasename(),
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    /**
     * Remove DB row and storage object without leaving orphans when possible.
     * File is deleted only after the database row is removed successfully.
     */
    public function delete(Attachment $attachment, ?User $actor = null): void
    {
        $disk = $attachment->disk;
        $path = $attachment->path;
        $meta = [
            'attachable_type' => $attachment->attachable_type,
            'attachable_id' => $attachment->attachable_id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'extension' => $attachment->extension,
            'size_bytes' => $attachment->size_bytes,
        ];

        DB::transaction(function () use ($attachment, $actor, $meta): void {
            $this->audit->record('attachment.removed', null, array_merge($meta, [
                'attachment_uuid' => $attachment->uuid,
            ]), $actor);
            $attachment->delete();
        });

        Storage::disk($disk)->delete($path);
    }

    public function assertBelongsTo(Attachment $attachment, Model $attachable): void
    {
        if (
            $attachment->attachable_type !== $attachable->getMorphClass()
            || (int) $attachment->attachable_id !== (int) $attachable->getKey()
        ) {
            abort(404);
        }
    }

    private function directoryFor(Model $attachable): string
    {
        $aliases = config('attachments.attachables', []);
        $alias = array_search($attachable::class, $aliases, true);
        $folder = is_string($alias) ? $alias : Str::snake(class_basename($attachable));

        return 'attachments/'.$folder.'/'.$attachable->getKey();
    }

    private function resolvedExtension(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());

        if ($ext === '' && is_string($file->extension())) {
            $ext = strtolower($file->extension());
        }

        return $ext;
    }

    private function resolvedMime(UploadedFile $file): string
    {
        return strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
    }

    private function assertAllowed(string $extension, string $mime): void
    {
        $allowedExt = config('attachments.allowed_extensions', []);
        $map = config('attachments.extension_mime_map', []);

        if (! in_array($extension, $allowedExt, true)) {
            throw new RuntimeException('نوع الملف غير مسموح.');
        }

        $accepted = $map[$extension] ?? [];
        if ($accepted === [] || ! in_array($mime, $accepted, true)) {
            throw new RuntimeException('نوع محتوى الملف لا يطابق الامتداد المسموح.');
        }
    }

    private function sanitizeOriginalName(string $name, string $extension): string
    {
        $name = str_replace(["\0", '/', '\\'], '', $name);
        $name = basename($name);
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file.'.$extension;
        }

        return Str::limit($name, 255, '');
    }
}
