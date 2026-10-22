@extends('layouts.app')

@section('title', $product->name_ar.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · المنتجات</span>
@endsection

@section('content')
    <div class="products-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="product-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">سجل المنتج</p>
                <h1 id="product-show-heading" class="admin-title">{{ $product->name_ar }}</h1>
                <p class="admin-lead">
                    بيانات الكتالوج ووحدة القياس والمواصفات الفنية.
                    @if ($canViewStock ?? false)
                        رصيد المستودع الواحد يظهر أدناه (المتاح = الرصيد − المحجوز).
                    @else
                        أسعار البيع غير معروضة هنا وفق صلاحياتك.
                    @endif
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('products.index') }}" class="btn btn-glass">القائمة</a>
                @if ($canViewStock ?? false)
                    <a href="{{ route('inventory.stock.index') }}" class="btn btn-glass">أرصدة المخزون</a>
                @endif
                @if ($canUpdate)
                    <a href="{{ route('products.edit', $product) }}" class="btn btn-glass-primary">تعديل</a>
                @endif
            </div>
        </header>

        <section class="products-summary" aria-labelledby="product-summary-heading">
            <div class="visit-panel-head">
                <p class="visit-panel-kicker visit-panel-kicker-locked">كتالوج</p>
                <h2 id="product-summary-heading" class="visit-panel-title">بيانات المنتج</h2>
                <p class="visit-panel-lead">بلا رقم SKU أو أسعار بيع في هذه الشاشة.</p>
            </div>

            <div class="inventory-metrics products-catalog-metrics" role="group" aria-label="بيانات الكتالوج">
                <div class="inventory-metric">
                    <span class="inventory-metric-label">اسم المنتج</span>
                    <span class="inventory-metric-value">{{ $product->name_ar }}</span>
                </div>
                <div class="inventory-metric">
                    <span class="inventory-metric-label">وحدة القياس</span>
                    <span class="inventory-metric-value">
                        <span class="products-unit-chip">{{ $product->unit?->displayLabel() ?? '—' }}</span>
                    </span>
                </div>
                @if ($canViewCost)
                    <div class="inventory-metric inventory-metric-available">
                        <span class="inventory-metric-label">تكلفة الشراء</span>
                        <span class="inventory-metric-value dir-ltr products-cost-cell">
                            @if ($product->purchase_cost !== null)
                                {{ money($product->purchase_cost) }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                @endif
                <div class="inventory-metric">
                    <span class="inventory-metric-label">تاريخ الإنشاء</span>
                    <span class="inventory-metric-value dir-ltr products-metric-datetime">
                        {{ $product->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    </span>
                </div>
            </div>
        </section>

        @if ($canViewStock ?? false)
            <section class="products-stock-panel" aria-labelledby="product-stock-heading">
                <div class="visit-panel-head">
                    <p class="visit-panel-kicker visit-panel-kicker-locked">مخزون</p>
                    <h2 id="product-stock-heading" class="visit-panel-title">رصيد المخزون</h2>
                    <p class="visit-panel-lead">مستودع واحد. المتاح = الرصيد − المحجوز. لا تُعرض أسعار البيع هنا.</p>
                </div>
                <div class="inventory-metrics" role="group" aria-label="أرقام الرصيد">
                    <div class="inventory-metric">
                        <span class="inventory-metric-label">الرصيد</span>
                        <span class="inventory-metric-value dir-ltr">{{ qty(($stockBalance?->on_hand ?? 0)) }}</span>
                    </div>
                    <div class="inventory-metric">
                        <span class="inventory-metric-label">المحجوز</span>
                        <span class="inventory-metric-value dir-ltr">{{ qty(($stockBalance?->reserved ?? 0)) }}</span>
                    </div>
                    <div class="inventory-metric inventory-metric-available">
                        <span class="inventory-metric-label">المتاح</span>
                        <span class="inventory-metric-value dir-ltr">{{ qty(($stockBalance?->available() ?? '0.000')) }}</span>
                    </div>
                </div>
                @unless ($stockBalance)
                    <p class="muted" role="note">لا يوجد صف رصيد بعد — يُعامل كصفر حتى أول تسوية معتمدة أو حركة مخزون.</p>
                @endunless
            </section>
        @endif

        @if ($canUpdateCost && ! $canUpdate)
            <section class="products-cost-only" aria-labelledby="product-cost-only-heading">
                <div class="visit-panel-head">
                    <p class="visit-panel-kicker visit-panel-kicker-schedule">مشتريات · تكلفة فقط</p>
                    <h2 id="product-cost-only-heading" class="visit-panel-title">تحديث تكلفة الشراء</h2>
                    <p class="visit-panel-lead">صلاحيتك تسمح بتعديل تكلفة الشراء فقط — دون تغيير بيانات المنتج الأخرى.</p>
                </div>
                <form method="POST" action="{{ route('products.purchase-cost.update', $product) }}"
                      class="auth-form admin-form products-cost-form" novalidate>
                    @csrf
                    @method('PUT')
                    <div class="auth-field">
                        <label for="purchase_cost">تكلفة الشراء <span class="required-mark" title="مطلوب">*</span></label>
                        <input id="purchase_cost" name="purchase_cost" type="number" step="0.01" min="0" required
                               value="{{ old('purchase_cost', $product->purchase_cost) }}"
                               class="dir-ltr"
                               aria-required="true"
                               aria-invalid="{{ $errors->has('purchase_cost') ? 'true' : 'false' }}">
                        @error('purchase_cost')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass-primary">حفظ التكلفة</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="products-specs-panel" aria-labelledby="product-specs-heading">
            <div class="visit-panel-head">
                <p class="visit-panel-kicker visit-panel-kicker-locked">مواصفات</p>
                <h2 id="product-specs-heading" class="visit-panel-title">المواصفات الفنية</h2>
                <p class="visit-panel-lead">الكثافة حقل موثّق؛ المواصفات الأخرى تسمية/قيمة/وحدة اختيارية.</p>
            </div>

            @if (! $density && $otherSpecs->isEmpty())
                <div class="ds-data-empty is-compact" role="status">
                    <strong>لا توجد مواصفات</strong>
                    <p class="muted">لم تُسجَّل كثافة أو مواصفات فنية أخرى بعد.</p>
                </div>
            @else
                <dl class="products-spec-readout">
                    @if ($density)
                        <div class="products-spec-readout-item is-density">
                            <dt>{{ $density->label_ar }}</dt>
                            <dd>{{ $density->displayValue() }}</dd>
                        </div>
                    @endif
                    @foreach ($otherSpecs as $spec)
                        <div class="products-spec-readout-item">
                            <dt>{{ $spec->label_ar }}</dt>
                            <dd>{{ $spec->displayValue() }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>
    </div>
@endsection
