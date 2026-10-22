<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreUnitOfMeasureRequest;
use App\Http\Requests\Products\UpdateUnitOfMeasureRequest;
use App\Models\UnitOfMeasure;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitOfMeasureController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', UnitOfMeasure::class);

        $q = trim((string) $request->query('q', ''));

        $units = UnitOfMeasure::query()
            ->search($q !== '' ? $q : null)
            ->withCount('products')
            ->orderBy('name_ar')
            ->paginate(20)
            ->withQueryString();

        return view('products.units.index', [
            'units' => $units,
            'q' => $q,
            'canManage' => $request->user()?->can('create', UnitOfMeasure::class) ?? false,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', UnitOfMeasure::class);

        return view('products.units.create');
    }

    public function store(StoreUnitOfMeasureRequest $request, AuditLogger $audit): RedirectResponse
    {
        $unit = UnitOfMeasure::query()->create([
            'name_ar' => (string) $request->validated('name_ar'),
            'symbol_ar' => $request->validated('symbol_ar'),
            'is_active' => (bool) $request->validated('is_active', true),
        ]);

        $audit->record('unit_of_measure.created', $unit, [
            'name_ar' => $unit->name_ar,
            'symbol_ar' => $unit->symbol_ar,
            'is_active' => $unit->is_active,
        ]);

        return redirect()
            ->route('products.units.index')
            ->with('status_ar', 'تم إنشاء وحدة القياس بنجاح.');
    }

    public function edit(UnitOfMeasure $unit): View
    {
        $this->authorize('update', $unit);

        return view('products.units.edit', [
            'unit' => $unit,
            'productsCount' => $unit->products()->count(),
        ]);
    }

    public function update(
        UpdateUnitOfMeasureRequest $request,
        UnitOfMeasure $unit,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $unit->only(['name_ar', 'symbol_ar', 'is_active']);

        $unit->fill([
            'name_ar' => (string) $request->validated('name_ar'),
            'symbol_ar' => $request->validated('symbol_ar'),
            'is_active' => (bool) $request->validated('is_active', false),
        ])->save();

        $changes = $audit->changes(
            $before,
            $unit->only(['name_ar', 'symbol_ar', 'is_active']),
            ['name_ar', 'symbol_ar', 'is_active'],
        );

        $audit->record('unit_of_measure.updated', $unit, $changes);

        return redirect()
            ->route('products.units.index')
            ->with('status_ar', 'تم تحديث وحدة القياس بنجاح.');
    }
}
