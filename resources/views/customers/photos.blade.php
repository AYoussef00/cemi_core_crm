@extends('layouts.app')

@section('title', 'صور العميل — '.$customer->name_ar.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · العملاء · الصور</span>
@endsection

@section('content')
    @php
        $extList = implode(', ', $photoLimits['extensions']);
        $maxMb = number_format($photoLimits['max_kilobytes'] / 1024, 1);
    @endphp

    <div class="photos-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="customer-photos-heading">
            <div class="ds-page-bar-copy">
                <h1 id="customer-photos-heading" class="admin-title">صور العميل</h1>
                <p class="admin-lead">
                    {{ $customer->name_ar }} — تخزين خاص عبر أساس المرفقات، بلا رابط عام.
                    الأنواع: <span class="dir-ltr">{{ $extList }}</span> · حتى {{ $maxMb }} ميجابايت للصورة.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-glass">سجل العميل</a>
                <a href="{{ route('customers.location.edit', $customer) }}" class="btn btn-glass">الموقع</a>
            </div>
        </header>

        @if ($canManage)
            <form method="POST" action="{{ route('customers.photos.store', $customer) }}"
                  enctype="multipart/form-data" class="attachments-upload photos-upload" aria-label="رفع صورة عميل">
                @csrf
                <div class="attachments-upload-main">
                    <div class="auth-field attachments-file-field">
                        <label for="customer-photo-file">اختر صورة</label>
                        <input id="customer-photo-file" name="file" type="file" required
                               accept="{{ collect($photoLimits['extensions'])->map(fn ($e) => '.'.$e)->implode(',') }}"
                               aria-invalid="{{ $errors->has('file') ? 'true' : 'false' }}">
                        @error('file')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-glass-primary attachments-upload-btn">رفع الصورة</button>
                </div>
            </form>
        @else
            <p class="photos-readonly-note" role="note">عرض وتنزيل فقط — ليست لديك صلاحية رفع أو حذف صور هذا العميل.</p>
        @endif

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
                        @forelse ($customer->attachments as $attachment)
                            <tr>
                                <td>
                                    <span class="attachments-name" title="{{ $attachment->original_name }}">
                                        {{ $attachment->original_name }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-neutral dir-ltr">{{ strtoupper($attachment->extension) }}</span>
                                </td>
                                <td class="dir-ltr ds-cell-muted attachments-size">
                                    {{ number_format($attachment->size_bytes / 1024, 1) }} كيلوبايت
                                </td>
                                <td>{{ $attachment->uploader?->name ?? '—' }}</td>
                                <td class="ds-cell-actions">
                                    <details class="ds-row-menu">
                                        <summary aria-label="إجراءات الصورة">⋮</summary>
                                        <div class="ds-row-menu-panel">
                                            <a href="{{ route('customers.photos.download', [$customer, $attachment]) }}">تنزيل</a>
                                            @if ($canManage)
                                                <form method="POST"
                                                      action="{{ route('customers.photos.destroy', [$customer, $attachment]) }}"
                                                      class="inline-form"
                                                      onsubmit="return confirm('حذف هذه الصورة؟');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="ds-row-menu-danger">حذف</button>
                                                </form>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="ds-data-empty" role="status">
                                        <strong>لا توجد صور</strong>
                                        <p>لا توجد صور لهذا العميل بعد.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
