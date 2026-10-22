<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductStockBalance::class);

        $warehouse = Warehouse::current();
        $q = trim((string) $request->query('q', ''));

        $rows = null;

        if ($warehouse !== null) {
            $rows = Product::query()
                ->search($q !== '' ? $q : null)
                ->with('unit')
                ->leftJoin('product_stock_balances', function ($join) use ($warehouse): void {
                    $join->on('product_stock_balances.product_id', '=', 'products.id')
                        ->where('product_stock_balances.warehouse_id', '=', $warehouse->id);
                })
                ->orderBy('products.name_ar')
                ->select([
                    'products.id',
                    'products.name_ar',
                    'products.unit_id',
                    'product_stock_balances.on_hand',
                    'product_stock_balances.reserved',
                ])
                ->paginate(20)
                ->withQueryString();
        }

        return view('inventory.stock.index', [
            'warehouse' => $warehouse,
            'rows' => $rows,
            'q' => $q,
            'canRecordAdjustment' => $request->user()?->can('create', InventoryAdjustment::class) ?? false,
        ]);
    }
}
