<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductPurchaseCostRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Inventory\InventoryService;
use App\Support\Products\ProductWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        /** @var User $user */
        $user = $request->user();

        $q = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->search($q !== '' ? $q : null)
            ->with(['unit', 'specifications'])
            ->orderBy('name_ar')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'q' => $q,
            'canManage' => $user->can('create', Product::class),
            'canViewCost' => $user->can('viewPurchaseCost', Product::class),
            'canManageUnits' => $user->can('create', UnitOfMeasure::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Product::class);

        return view('products.create', [
            'units' => UnitOfMeasure::query()->active()->orderBy('name_ar')->get(),
            'blankSpecRows' => 2,
        ]);
    }

    public function store(StoreProductRequest $request, ProductWorkflow $workflow): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $product = $workflow->create($request->productPayload(), $user);

        return redirect()
            ->route('products.show', $product)
            ->with('status_ar', 'تم إنشاء المنتج بنجاح.');
    }

    public function show(Request $request, Product $product, InventoryService $inventory): View
    {
        $this->authorize('view', $product);

        $product->load(['unit', 'specifications']);

        /** @var User $user */
        $user = $request->user();
        $canViewStock = $user->can('stock.view');

        return view('products.show', [
            'product' => $product,
            'canUpdate' => $user->can('update', $product),
            'canUpdateCost' => $user->can('updatePurchaseCost', $product),
            'canViewCost' => $user->can('viewPurchaseCost', Product::class),
            'canViewStock' => $canViewStock,
            'stockBalance' => $canViewStock ? $inventory->balanceFor($product) : null,
            'density' => $product->densitySpecification(),
            'otherSpecs' => $product->specifications->filter(
                fn ($s) => $s->label_ar !== Product::DENSITY_LABEL_AR
            ),
        ]);
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorize('update', $product);

        $product->load('specifications');

        $units = UnitOfMeasure::query()
            ->where(function ($q) use ($product): void {
                $q->where('is_active', true)->orWhere('id', $product->unit_id);
            })
            ->orderBy('name_ar')
            ->get();

        $density = $product->densitySpecification();
        $otherSpecs = $product->specifications
            ->filter(fn ($s) => $s->label_ar !== Product::DENSITY_LABEL_AR)
            ->values();

        return view('products.edit', [
            'product' => $product,
            'units' => $units,
            'density' => $density,
            'otherSpecs' => $otherSpecs,
            'blankSpecRows' => max(1, 2 - $otherSpecs->count()),
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        ProductWorkflow $workflow,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $workflow->update($product, $request->productPayload(), $user);

        return redirect()
            ->route('products.show', $product)
            ->with('status_ar', 'تم تحديث المنتج بنجاح.');
    }

    public function updatePurchaseCost(
        UpdateProductPurchaseCostRequest $request,
        Product $product,
        ProductWorkflow $workflow,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $workflow->updatePurchaseCost(
            $product,
            (string) $request->validated('purchase_cost'),
            $user,
        );

        return redirect()
            ->route('products.show', $product)
            ->with('status_ar', 'تم تحديث تكلفة الشراء بنجاح.');
    }
}
