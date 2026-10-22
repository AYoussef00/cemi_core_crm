<?php

namespace Database\Factories;

use App\Models\ProcurementTask;
use App\Models\Product;
use App\Models\SalesRequest;
use App\Models\SalesRequestLine;
use App\Support\Procurement\ProcurementTaskStatus;
use App\Support\SalesRequests\LineAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementTask>
 */
class ProcurementTaskFactory extends Factory
{
    protected $model = ProcurementTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $request = SalesRequest::factory()->awaitingProcurement()->create();
        $product = Product::factory()->create();
        $line = SalesRequestLine::query()->create([
            'sales_request_id' => $request->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
            'availability' => LineAvailability::NeedsProcurement,
            'sort_order' => 0,
        ]);

        return [
            'sales_request_id' => $request->id,
            'sales_request_line_id' => $line->id,
            'product_id' => $product->id,
            'status' => ProcurementTaskStatus::Open,
        ];
    }
}
