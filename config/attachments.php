<?php

/**
 * Private attachment foundation (Phase 1 Prompt 6).
 *
 * Assumption (docs do not define limits): conservative allowlist for PDFs and
 * common images; max 5 MiB per file. Stakeholder may widen later for domain modules.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Must be a private local (or future private cloud) disk. Never use the
    | "public" disk or Storage::url() for attachments.
    |
    */
    'disk' => env('ATTACHMENTS_DISK', 'attachments'),

    /*
    |--------------------------------------------------------------------------
    | Maximum upload size (kilobytes) — Laravel `max:` rule unit
    |--------------------------------------------------------------------------
    */
    'max_kilobytes' => (int) env('ATTACHMENTS_MAX_KB', 5120),

    /*
    |--------------------------------------------------------------------------
    | Allowed extensions (lowercase, no leading dot)
    |--------------------------------------------------------------------------
    */
    'allowed_extensions' => [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME types (must match extension mapping below)
    |--------------------------------------------------------------------------
    */
    'allowed_mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extension → accepted MIME types
    |--------------------------------------------------------------------------
    */
    'extension_mime_map' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Morph aliases attachable today (extensible for future domain models)
    |--------------------------------------------------------------------------
    */
    'attachables' => [
        'company' => App\Models\Company::class,
        'warehouse' => App\Models\Warehouse::class,
        'customer' => App\Models\Customer::class,
        'delivery' => App\Models\Delivery::class,
        'customer_purchase_order' => App\Models\CustomerPurchaseOrder::class,
        'procurement_task' => App\Models\ProcurementTask::class,
        'supplier_purchase_order' => App\Models\SupplierPurchaseOrder::class,
        'supplier_goods_receipt' => App\Models\SupplierGoodsReceipt::class,
    ],

    /*
    | Customer photos / site images — image extensions only (no PDF).
    */
    'customer_photo_extensions' => [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
    ],
];
