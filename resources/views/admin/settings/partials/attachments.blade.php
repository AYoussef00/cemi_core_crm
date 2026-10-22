{{--
  Reusable private attachments panel for settings records.
  Required: $attachments, $attachmentLimits, $storeRoute, $downloadRouteName, $destroyRouteName
--}}
@php
    $extList = implode(', ', $attachmentLimits['extensions']);
    $maxMb = number_format($attachmentLimits['max_kilobytes'] / 1024, 1);
@endphp

<section class="attachments-panel" aria-labelledby="attachments-heading">
    <div class="attachments-header">
        <p class="attachments-kicker">ملفات خاصة · التنزيل لمن لديه الصلاحية</p>
        <h2 id="attachments-heading" class="attachments-title">المرفقات</h2>
        <p class="attachments-lead">
            تُحفظ المرفقات بشكل خاص ولا تتاح عبر رابط عام.
            الأنواع المسموحة:
            <span class="attachments-limits dir-ltr">{{ $extList }}</span>
            — الحد الأقصى
            <strong>{{ $maxMb }} ميجابايت</strong>
            لكل ملف.
        </p>
    </div>

    <form
        method="POST"
        action="{{ $storeRoute }}"
        enctype="multipart/form-data"
        class="attachments-upload"
        aria-label="رفع مرفق"
    >
        @csrf
        <div class="attachments-upload-main">
            <div class="auth-field attachments-file-field">
                <label for="attachment-file">اختر ملفًا</label>
                <input
                    id="attachment-file"
                    name="file"
                    type="file"
                    required
                    accept="{{ collect($attachmentLimits['extensions'])->map(fn ($e) => '.'.$e)->implode(',') }}"
                    aria-invalid="{{ $errors->has('file') ? 'true' : 'false' }}"
                    @if ($errors->has('file')) aria-describedby="attachment-file-error attachment-file-hint" @else aria-describedby="attachment-file-hint" @endif
                >
                <p id="attachment-file-hint" class="attachments-file-hint muted">
                    PDF أو صورة · حتى {{ $maxMb }} ميجابايت
                </p>
                @error('file')
                    <p id="attachment-file-error" class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="btn btn-glass-primary attachments-upload-btn">رفع الملف</button>
        </div>
    </form>

    <div class="ds-table-surface">
        <div class="ds-data-body">
        <table class="admin-table attachments-table">
            <thead>
                <tr>
                    <th scope="col">الاسم</th>
                    <th scope="col">النوع</th>
                    <th scope="col">الحجم</th>
                    <th scope="col">بواسطة</th>
                    <th scope="col">إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attachments as $attachment)
                    <tr>
                        <td>
                            <span class="attachments-name ds-cell-primary ds-cell-truncate" title="{{ $attachment->original_name }}">
                                {{ $attachment->original_name }}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-neutral dir-ltr">{{ strtoupper($attachment->extension) }}</span>
                        </td>
                        <td class="attachments-size dir-ltr muted">
                            {{ number_format($attachment->size_bytes / 1024, 1) }} كيلوبايت
                        </td>
                        <td class="attachments-uploader">{{ $attachment->uploader?->name ?? '—' }}</td>
                        <td>
                            <details class="ds-row-menu">
                                <summary aria-label="إجراءات المرفق">⋮</summary>
                                <div class="ds-row-menu-panel">
                                    <a href="{{ route($downloadRouteName, $attachment) }}">تنزيل</a>
                                    <form
                                        method="POST"
                                        action="{{ route($destroyRouteName, $attachment) }}"
                                        class="inline-form"
                                        onsubmit="return confirm('هل تريد حذف هذا المرفق؟ لا يمكن التراجع عن الحذف.');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">حذف</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="ds-data-empty" role="status">
                                <strong>لا توجد مرفقات بعد</strong>
                                <p>
                                    ارفع ملف PDF أو صورة ضمن الأنواع والحجم أعلاه.
                                    التنزيل والحذف متاحان لمن لديه الصلاحية فقط.
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</section>
