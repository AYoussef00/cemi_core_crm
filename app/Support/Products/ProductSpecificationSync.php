<?php

namespace App\Support\Products;

use App\Models\Product;
use App\Models\ProductSpecification;

class ProductSpecificationSync
{
    /**
     * @param  list<array{label_ar: string, value: string, unit_ar?: string|null}>  $rows
     * @return list<ProductSpecification>
     */
    public function sync(Product $product, array $rows): array
    {
        $product->specifications()->delete();

        $saved = [];
        $order = 0;

        foreach ($rows as $row) {
            $label = trim((string) ($row['label_ar'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            $saved[] = $product->specifications()->create([
                'label_ar' => $label,
                'value' => $value,
                'unit_ar' => filled($row['unit_ar'] ?? null) ? trim((string) $row['unit_ar']) : null,
                'sort_order' => $order++,
            ]);
        }

        return $saved;
    }
}
