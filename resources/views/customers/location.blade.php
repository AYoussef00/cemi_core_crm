@extends('layouts.app')

@section('title', 'موقع العميل — '.$customer->name_ar.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · العملاء · الموقع</span>
@endsection

@section('content')
    <div class="location-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="customer-location-heading">
            <div class="ds-page-bar-copy">
                <h1 id="customer-location-heading" class="admin-title">موقع العميل</h1>
                <p class="admin-lead">
                    {{ $customer->name_ar }} — عنوان وإحداثيات فقط.
                    انقر الخريطة لوضع الدبوس، أو أدخل خط العرض/الطول يدويًا. بلا ترميز جغرافي.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-glass">سجل العميل</a>
                <a href="{{ route('customers.photos.index', $customer) }}" class="btn btn-glass">الصور</a>
            </div>
        </header>

        <p id="customer-map-status" class="customer-map-status" role="status" hidden></p>

        <div class="location-stage" aria-label="خريطة وموقع العميل">
            <div class="location-map-frame">
                <div class="location-map-head">
                    <p class="location-map-kicker">الخريطة</p>
                    <p class="location-map-caption">
                        @if ($canUpdate)
                            انقر لتعيين الدبوس
                        @else
                            عرض فقط — لا يمكن تحريك الدبوس
                        @endif
                    </p>
                </div>
                <div
                    id="customer-location-map"
                    class="customer-location-map"
                    role="application"
                    aria-label="خريطة اختيار موقع العميل"
                    @if (! $mapConfig['interactive']) aria-readonly="true" @endif
                ></div>
                <script id="customer-map-config" type="application/json">{!! json_encode($mapConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
                <p class="customer-map-hint muted">
                    بلاطات الخريطة من البيئة (<span class="dir-ltr">MAPS_TILE_URL</span>).
                    لا يُرسل العنوان لأي خدمة ترميز جغرافي. عند تعطّل الخريطة تبقى حقول الإحداثيات متاحة.
                </p>
            </div>

            <aside class="location-form-panel" aria-labelledby="location-fields-heading">
                <div class="location-form-head">
                    <p class="location-form-kicker">{{ $canUpdate ? 'تحرير' : 'عرض' }}</p>
                    <h2 id="location-fields-heading" class="location-form-title">العنوان والإحداثيات</h2>
                    <p class="location-form-lead">
                        الحقول تعمل دون الخريطة — مناسبة للوحة المفاتيح وقارئ الشاشة.
                    </p>
                </div>

                @if ($canUpdate)
                    <form method="POST" action="{{ route('customers.location.update', $customer) }}"
                          class="auth-form admin-form customers-form location-form" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="auth-field">
                            <label for="address_ar">العنوان</label>
                            <textarea id="address_ar" name="address_ar" rows="3" maxlength="2000"
                                      aria-invalid="{{ $errors->has('address_ar') ? 'true' : 'false' }}">{{ old('address_ar', $customer->address_ar) }}</textarea>
                            @error('address_ar')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="customers-location-coords">
                            <div class="auth-field">
                                <label for="latitude">خط العرض</label>
                                <input id="latitude" name="latitude" type="number" step="any" min="-90" max="90"
                                       class="dir-ltr" inputmode="decimal"
                                       value="{{ old('latitude', $customer->latitude) }}"
                                       aria-invalid="{{ $errors->has('latitude') ? 'true' : 'false' }}">
                                @error('latitude')
                                    <p class="auth-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="auth-field">
                                <label for="longitude">خط الطول</label>
                                <input id="longitude" name="longitude" type="number" step="any" min="-180" max="180"
                                       class="dir-ltr" inputmode="decimal"
                                       value="{{ old('longitude', $customer->longitude) }}"
                                       aria-invalid="{{ $errors->has('longitude') ? 'true' : 'false' }}">
                                @error('longitude')
                                    <p class="auth-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="admin-form-actions">
                            <button type="submit" class="btn btn-glass-primary">حفظ الموقع</button>
                        </div>
                    </form>
                @else
                    <dl class="customers-detail location-readonly">
                        <div class="customers-detail-item">
                            <dt>العنوان</dt>
                            <dd>{{ $customer->address_ar ?: '—' }}</dd>
                        </div>
                        <div class="customers-detail-item">
                            <dt>خط العرض</dt>
                            <dd class="dir-ltr">{{ $customer->latitude !== null ? $customer->latitude : '—' }}</dd>
                        </div>
                        <div class="customers-detail-item">
                            <dt>خط الطول</dt>
                            <dd class="dir-ltr">{{ $customer->longitude !== null ? $customer->longitude : '—' }}</dd>
                        </div>
                    </dl>
                    <p class="customers-assign-note" role="note">عرض فقط — ليست لديك صلاحية تعديل موقع هذا العميل.</p>
                @endif
            </aside>
        </div>
    </div>
@endsection
