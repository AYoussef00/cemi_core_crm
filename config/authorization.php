<?php

/**
 * v1 authorization catalog — mirrors docs/ROLES_AND_PERMISSIONS.md.
 * Seeders sync these definitions idempotently. Do not invent permissions here.
 */

use App\Support\Authorization\RecordScope;
use App\Support\Authorization\RoleSlug;

return [

    'roles' => [
        RoleSlug::SalesRepresentative->value => [
            'name_ar' => 'مندوب مبيعات',
            'name_en' => 'Sales Representative',
            'description_ar' => 'عملاء مُعيَّنون؛ مسودات أوامر البيع؛ نطاق Own/Cust/Assigned.',
        ],
        RoleSlug::SalesManager->value => [
            'name_ar' => 'مدير مبيعات',
            'name_en' => 'Sales Manager',
            'description_ar' => 'اعتماد الأسعار وأوامر البيع والإلغاء والائتمان والملاحظات.',
        ],
        RoleSlug::Procurement->value => [
            'name_ar' => 'مشتريات',
            'name_en' => 'Procurement',
            'description_ar' => 'تعديل تكلفة الشراء فقط؛ أوامر البيع المُحالة.',
        ],
        RoleSlug::Warehouse->value => [
            'name_ar' => 'مستودع',
            'name_en' => 'Warehouse',
            'description_ar' => 'التحضير والتسليم وتسجيل تسويات المخزون. بلا أسعار أو فواتير.',
        ],
        RoleSlug::Accounts->value => [
            'name_ar' => 'حسابات',
            'name_en' => 'Accounts',
            'description_ar' => 'فواتير وتحصيلات وملاحظات ائتمان ومراجعة حدود الائتمان وإغلاق الصفقات.',
        ],
        RoleSlug::Executive->value => [
            'name_ar' => 'إدارة عليا',
            'name_en' => 'Executive',
            'description_ar' => 'قراءة وتقارير فقط — دون اعتماد معاملات المبيعات.',
        ],
        RoleSlug::Administrator->value => [
            'name_ar' => 'مدير النظام',
            'name_en' => 'Administrator',
            'description_ar' => 'المستخدمون والإعدادات وسجلات المنتجات — دون اعتماد معاملات المبيعات.',
        ],
    ],

    /*
    | Permission definitions: slug => [name_ar, group, description_ar]
    | Groups help the admin UI.
    */
    'permissions' => [
        // Platform
        'users.view' => ['name_ar' => 'عرض المستخدمين', 'group' => 'platform', 'description_ar' => 'عرض قائمة المستخدمين'],
        'users.manage' => ['name_ar' => 'إدارة المستخدمين', 'group' => 'platform', 'description_ar' => 'إنشاء وتعديل وتفعيل/إيقاف وتعيين دور واحد وإعادة تعيين كلمة المرور'],
        'roles.manage' => ['name_ar' => 'إدارة الأدوار والصلاحيات', 'group' => 'platform', 'description_ar' => 'تعديل تعيينات الصلاحيات للأدوار'],
        'approval_authority.configure' => ['name_ar' => 'ضبط جهات الاعتماد', 'group' => 'platform', 'description_ar' => 'تعيين الدور المسؤول عن كل مسار اعتماد'],
        'audit.view' => ['name_ar' => 'عرض سجل التدقيق', 'group' => 'platform', 'description_ar' => 'عرض سجلات التدقيق'],
        'settings.manage' => ['name_ar' => 'إدارة إعدادات النظام', 'group' => 'platform', 'description_ar' => 'البريد والأسرار والشركة والمستودع'],

        // Customers / visits
        'customers.view' => ['name_ar' => 'عرض العملاء', 'group' => 'customers', 'description_ar' => 'عرض سجلات العملاء ضمن النطاق'],
        'customers.manage' => ['name_ar' => 'إدارة العملاء', 'group' => 'customers', 'description_ar' => 'إنشاء وتعديل العملاء'],
        'visits.view' => ['name_ar' => 'عرض الزيارات', 'group' => 'customers', 'description_ar' => 'عرض الزيارات ضمن النطاق'],
        'visits.manage' => ['name_ar' => 'إدارة الزيارات', 'group' => 'customers', 'description_ar' => 'إنشاء وتعديل وإرسال وإلغاء الزيارات'],
        'reports.customer_activity' => ['name_ar' => 'تقرير نشاط العملاء', 'group' => 'reports', 'description_ar' => 'تقارير نشاط العملاء'],

        // Products / inventory
        'products.view' => ['name_ar' => 'عرض المنتجات', 'group' => 'products', 'description_ar' => 'عرض كتالوج المنتجات'],
        'products.manage' => ['name_ar' => 'إدارة سجلات المنتجات', 'group' => 'products', 'description_ar' => 'إنشاء وتعديل سجلات المنتجات الرئيسية'],
        'products.edit_purchase_cost' => ['name_ar' => 'تعديل تكلفة الشراء فقط', 'group' => 'products', 'description_ar' => 'تعديل حقول تكلفة الشراء فقط'],
        'stock.view' => ['name_ar' => 'عرض أرصدة المخزون', 'group' => 'products', 'description_ar' => 'عرض أرصدة المخزون'],
        'inventory_adjustment.record' => ['name_ar' => 'تسجيل تسوية مخزون', 'group' => 'products', 'description_ar' => 'تسجيل تسوية مخزون للموافقة'],
        'inventory_adjustment.approve' => ['name_ar' => 'اعتماد تسوية المخزون', 'group' => 'products', 'description_ar' => 'اعتماد أو رفض تسوية المخزون'],
        'prices.view' => ['name_ar' => 'عرض أسعار البيع', 'group' => 'products', 'description_ar' => 'عرض أسعار البيع'],
        'reports.inventory' => ['name_ar' => 'تقرير المخزون', 'group' => 'reports', 'description_ar' => 'تقارير المخزون'],

        // Sales requests / quotations
        'sales_requests.view' => ['name_ar' => 'عرض طلبات البيع', 'group' => 'sales', 'description_ar' => 'عرض طلبات البيع ضمن النطاق'],
        'sales_requests.manage' => ['name_ar' => 'إدارة طلبات البيع', 'group' => 'sales', 'description_ar' => 'إنشاء وتعديل وإرسال وإلغاء طلبات البيع'],
        'quotations.view' => ['name_ar' => 'عرض عروض الأسعار', 'group' => 'sales', 'description_ar' => 'عرض عروض الأسعار (شامل الأسعار) ضمن النطاق'],
        'quotations.manage' => ['name_ar' => 'إدارة مسودات عروض الأسعار', 'group' => 'sales', 'description_ar' => 'إنشاء وتعديل مسودات عروض الأسعار'],
        'quotations.submit_price' => ['name_ar' => 'إرسال عرض السعر لاعتماد السعر', 'group' => 'sales', 'description_ar' => 'إرسال عرض السعر لاعتماد السعر والهامش'],
        'quotations.approve_price' => ['name_ar' => 'اعتماد السعر والهامش', 'group' => 'sales', 'description_ar' => 'اعتماد أو رفض السعر والهامش'],
        'quotations.send' => ['name_ar' => 'إرسال عرض السعر بالبريد', 'group' => 'sales', 'description_ar' => 'إرسال عرض السعر المعتمد بالبريد'],
        'quotations.cancel' => ['name_ar' => 'إلغاء أو استبدال عرض السعر', 'group' => 'sales', 'description_ar' => 'إلغاء أو استبدال عرض السعر'],
        'reports.quotation_pipeline' => ['name_ar' => 'تقرير مسار عروض الأسعار', 'group' => 'reports', 'description_ar' => 'تقارير مسار عروض الأسعار'],

        // Procurement
        'procurement.view' => ['name_ar' => 'عرض مهام المشتريات', 'group' => 'procurement', 'description_ar' => 'عرض مهام المشتريات'],
        'procurement.manage' => ['name_ar' => 'إدارة عروض الموردين', 'group' => 'procurement', 'description_ar' => 'إنشاء وتعديل وإرسال عروض أسعار الموردين'],
        'procurement.cancel' => ['name_ar' => 'إلغاء مهمة مشتريات', 'group' => 'procurement', 'description_ar' => 'إلغاء مهمة مشتريات'],

        // Customer POs / Sales Orders
        'customer_pos.view' => ['name_ar' => 'عرض أوامر شراء العملاء', 'group' => 'sales', 'description_ar' => 'عرض أوامر شراء العملاء ضمن النطاق'],
        'customer_pos.manage' => ['name_ar' => 'إدارة أوامر شراء العملاء', 'group' => 'sales', 'description_ar' => 'إنشاء وربط وتعديل وإرسال أوامر شراء العملاء'],
        'customer_pos.cancel' => ['name_ar' => 'إلغاء أمر شراء العميل', 'group' => 'sales', 'description_ar' => 'إلغاء أمر شراء العميل'],
        'sales_orders.view' => ['name_ar' => 'عرض أوامر البيع', 'group' => 'sales', 'description_ar' => 'عرض أوامر البيع ضمن النطاق'],
        'sales_orders.create_draft' => ['name_ar' => 'إنشاء/تعديل مسودة أمر بيع', 'group' => 'sales', 'description_ar' => 'إنشاء وتعديل مسودات أوامر البيع'],
        'sales_orders.submit' => ['name_ar' => 'إرسال أمر البيع للاعتماد', 'group' => 'sales', 'description_ar' => 'إرسال أمر البيع للاعتماد'],
        'sales_orders.approve' => ['name_ar' => 'اعتماد أو رفض أمر البيع', 'group' => 'sales', 'description_ar' => 'اعتماد أو رفض أمر البيع'],
        'sales_orders.cancel_approved' => ['name_ar' => 'إلغاء أمر بيع معتمد', 'group' => 'sales', 'description_ar' => 'إلغاء أمر بيع معتمد (مدير المبيعات فقط افتراضيًا)'],
        'reports.po_so' => ['name_ar' => 'تقرير أوامر الشراء/البيع', 'group' => 'reports', 'description_ar' => 'تقارير أوامر الشراء والبيع'],

        // Warehouse / delivery
        'delivery.view' => ['name_ar' => 'عرض التحضير والتسليم', 'group' => 'warehouse', 'description_ar' => 'عرض حالة التحضير والتسليم (قراءة فقط)'],
        'delivery.manage' => ['name_ar' => 'إدارة التحضير والتسليم', 'group' => 'warehouse', 'description_ar' => 'تحديث التحضير والتسليم وإثبات التسليم والتأكيد'],
        'delivery.cancel' => ['name_ar' => 'إلغاء التسليم قبل التأكيد', 'group' => 'warehouse', 'description_ar' => 'إلغاء التسليم قبل التأكيد'],
        'reports.delivery' => ['name_ar' => 'تقرير تقدم التسليم', 'group' => 'reports', 'description_ar' => 'تقارير تقدم التسليم'],

        // Invoices / collections / credit
        'invoices.view' => ['name_ar' => 'عرض الفواتير', 'group' => 'finance', 'description_ar' => 'عرض الفواتير ضمن النطاق'],
        'invoices.issue' => ['name_ar' => 'إصدار فاتورة بعد إثبات التسليم', 'group' => 'finance', 'description_ar' => 'إصدار فاتورة للكمية المسلَّمة'],
        'credit_notes.create' => ['name_ar' => 'إنشاء إشعار دائن/مدين', 'group' => 'finance', 'description_ar' => 'إنشاء إشعار دائن أو مدين'],
        'credit_notes.approve' => ['name_ar' => 'اعتماد إشعار دائن/مدين', 'group' => 'finance', 'description_ar' => 'اعتماد أو رفض إشعار دائن/مدين'],
        'collection_reversal.request' => ['name_ar' => 'طلب عكس تحصيل', 'group' => 'finance', 'description_ar' => 'طلب عكس تحصيل'],
        'collection_reversal.approve' => ['name_ar' => 'اعتماد عكس تحصيل', 'group' => 'finance', 'description_ar' => 'اعتماد أو رفض عكس تحصيل'],
        'collections.record' => ['name_ar' => 'تسجيل وتخصيص التحصيلات', 'group' => 'finance', 'description_ar' => 'تسجيل وتخصيص التحصيلات'],
        'credit_limit.request' => ['name_ar' => 'طلب تغيير حد الائتمان', 'group' => 'finance', 'description_ar' => 'طلب تغيير حد الائتمان'],
        'credit_limit.review' => ['name_ar' => 'مراجعة طلب حد الائتمان', 'group' => 'finance', 'description_ar' => 'مراجعة طلب حد الائتمان'],
        'credit_limit.approve' => ['name_ar' => 'الاعتماد النهائي لحد الائتمان', 'group' => 'finance', 'description_ar' => 'الاعتماد أو الرفض النهائي لحد الائتمان'],
        'deals.close' => ['name_ar' => 'إغلاق الصفقة يدويًا', 'group' => 'finance', 'description_ar' => 'إغلاق الصفقة عند سداد جميع الفواتير'],
        'overdue.resend' => ['name_ar' => 'إعادة إرسال تذكير التأخر', 'group' => 'finance', 'description_ar' => 'إعادة إرسال تذكير التأخر'],
        'reports.ar' => ['name_ar' => 'تقرير الذمم والكشوف', 'group' => 'reports', 'description_ar' => 'تقارير الذمم والكشوف'],

        // Communications
        'email.send_templates' => ['name_ar' => 'إرسال قوالب البريد المعتمدة', 'group' => 'communications', 'description_ar' => 'إرسال قوالب البريد المعتمدة'],
        'email.manage_templates' => ['name_ar' => 'إدارة قوالب/مزود البريد', 'group' => 'communications', 'description_ar' => 'إدارة القوالب ومزود البريد'],

        // Reports
        'reports.operational' => ['name_ar' => 'التقارير التشغيلية/التجارية', 'group' => 'reports', 'description_ar' => 'التقارير التشغيلية والتجارية'],
        'reports.financial' => ['name_ar' => 'التقارير المالية', 'group' => 'reports', 'description_ar' => 'الكشوف والتقارير المالية'],
        'reports.export' => ['name_ar' => 'تصدير ضمن النطاق المسموح', 'group' => 'reports', 'description_ar' => 'تصدير البيانات ضمن النطاق المسموح'],
    ],

    /*
    | Role → permission grants.
    | Value true = allow (no record scope), or a RecordScope value string.
    */
    'matrix' => [
        RoleSlug::SalesRepresentative->value => [
            'customers.view' => RecordScope::Assigned->value,
            'customers.manage' => true,
            'visits.view' => RecordScope::Cust->value,
            'visits.manage' => true,
            'reports.customer_activity' => RecordScope::Own->value,
            'products.view' => true,
            'stock.view' => true,
            'prices.view' => true,
            'sales_requests.view' => RecordScope::Cust->value,
            'sales_requests.manage' => true,
            'quotations.view' => RecordScope::Cust->value,
            'quotations.manage' => true,
            // Rep creates draft / sends approved — margin pricing is SM only (PM1/PM2)
            'quotations.send' => true,
            'quotations.cancel' => true,
            'reports.quotation_pipeline' => RecordScope::Own->value,
            'customer_pos.view' => RecordScope::Cust->value,
            'customer_pos.manage' => true,
            // Cust: مندوب المبيعات يرى أوامر بيع عملاؤه المسندين فقط (بياناته)
            'sales_orders.view' => RecordScope::Cust->value,
            'sales_orders.create_draft' => true,
            'sales_orders.submit' => true,
            'reports.po_so' => RecordScope::Own->value,
            'invoices.view' => RecordScope::Cust->value,
            'credit_limit.request' => true,
            'reports.ar' => RecordScope::Own->value,
            'email.send_templates' => true,
            'reports.operational' => RecordScope::Own->value,
            'reports.export' => true,
        ],
        RoleSlug::SalesManager->value => [
            'audit.view' => true,
            'customers.view' => RecordScope::All->value,
            'customers.manage' => true,
            'visits.view' => RecordScope::All->value,
            'visits.manage' => true,
            'reports.customer_activity' => RecordScope::All->value,
            'products.view' => true,
            'stock.view' => true,
            'inventory_adjustment.approve' => true,
            'prices.view' => true,
            'reports.inventory' => true,
            'sales_requests.view' => RecordScope::All->value,
            'sales_requests.manage' => true,
            'quotations.view' => RecordScope::All->value,
            'quotations.manage' => true,
            'quotations.approve_price' => true,
            'quotations.send' => true,
            'quotations.cancel' => true,
            'reports.quotation_pipeline' => RecordScope::All->value,
            'procurement.view' => RecordScope::All->value,
            'procurement.cancel' => true,
            'customer_pos.view' => RecordScope::All->value,
            'customer_pos.manage' => true,
            'customer_pos.cancel' => true,
            'sales_orders.view' => RecordScope::All->value,
            'sales_orders.create_draft' => true,
            'sales_orders.submit' => true,
            'sales_orders.approve' => true,
            'sales_orders.cancel_approved' => true,
            'reports.po_so' => RecordScope::All->value,
            'delivery.view' => RecordScope::All->value,
            'delivery.manage' => true,
            'delivery.cancel' => true,
            'reports.delivery' => true,
            'invoices.view' => RecordScope::All->value,
            'credit_notes.approve' => true,
            'collection_reversal.approve' => true,
            'credit_limit.approve' => true,
            'reports.ar' => RecordScope::All->value,
            'email.send_templates' => true,
            'reports.operational' => RecordScope::All->value,
            'reports.export' => true,
        ],
        RoleSlug::Procurement->value => [
            'products.view' => true,
            'products.edit_purchase_cost' => true,
            'stock.view' => true,
            'reports.inventory' => true,
            'sales_requests.view' => RecordScope::Referred->value,
            'procurement.view' => true,
            'procurement.manage' => true,
            'procurement.cancel' => true,
            'sales_orders.view' => RecordScope::Referred->value,
            'reports.po_so' => RecordScope::Referred->value,
            'email.send_templates' => true,
            'reports.operational' => RecordScope::Referred->value,
            'reports.export' => true,
        ],
        RoleSlug::Warehouse->value => [
            'customers.view' => RecordScope::Work->value,
            'visits.view' => RecordScope::Work->value,
            'products.view' => true,
            'stock.view' => true,
            'inventory_adjustment.record' => true,
            'reports.inventory' => true,
            'customer_pos.view' => RecordScope::Work->value,
            'sales_orders.view' => RecordScope::All->value,
            'reports.po_so' => RecordScope::All->value,
            'delivery.view' => RecordScope::All->value,
            'delivery.manage' => true,
            'delivery.cancel' => true,
            'reports.delivery' => true,
            'reports.operational' => RecordScope::Work->value,
            'reports.export' => true,
            // Explicit denials encoded by absence: prices.view, invoices.view
        ],
        RoleSlug::Accounts->value => [
            'audit.view' => true,
            'customers.view' => RecordScope::Work->value,
            'visits.view' => RecordScope::Work->value,
            'reports.customer_activity' => RecordScope::All->value,
            'products.view' => true,
            'stock.view' => true,
            'prices.view' => true,
            'reports.inventory' => true,
            'sales_requests.view' => RecordScope::Work->value,
            'quotations.view' => RecordScope::All->value,
            'reports.quotation_pipeline' => RecordScope::All->value,
            'customer_pos.view' => RecordScope::Work->value,
            'sales_orders.view' => RecordScope::All->value,
            'reports.po_so' => RecordScope::All->value,
            'reports.delivery' => true,
            'invoices.view' => RecordScope::All->value,
            'invoices.issue' => true,
            'credit_notes.create' => true,
            'collection_reversal.request' => true,
            'collections.record' => true,
            'credit_limit.review' => true,
            'deals.close' => true,
            'overdue.resend' => true,
            'reports.ar' => RecordScope::All->value,
            'email.send_templates' => true,
            'reports.operational' => RecordScope::All->value,
            'reports.financial' => RecordScope::All->value,
            'reports.export' => true,
        ],
        RoleSlug::Executive->value => [
            'audit.view' => true,
            'customers.view' => RecordScope::All->value,
            'visits.view' => RecordScope::All->value,
            'reports.customer_activity' => RecordScope::All->value,
            'products.view' => true,
            'stock.view' => true,
            'prices.view' => true,
            'reports.inventory' => true,
            'sales_requests.view' => RecordScope::All->value,
            'quotations.view' => RecordScope::All->value,
            'reports.quotation_pipeline' => RecordScope::All->value,
            'procurement.view' => RecordScope::All->value,
            'customer_pos.view' => RecordScope::All->value,
            'sales_orders.view' => RecordScope::All->value,
            'reports.po_so' => RecordScope::All->value,
            'delivery.view' => RecordScope::All->value,
            'reports.delivery' => true,
            'invoices.view' => RecordScope::All->value,
            'reports.ar' => RecordScope::All->value,
            'reports.operational' => RecordScope::All->value,
            'reports.financial' => RecordScope::All->value,
            'reports.export' => true,
            // No sales-transaction approvals
        ],
        RoleSlug::Administrator->value => [
            'users.view' => true,
            'users.manage' => true,
            'roles.manage' => true,
            'approval_authority.configure' => true,
            'audit.view' => true,
            'settings.manage' => true,
            'customers.view' => RecordScope::Admin->value,
            'visits.view' => RecordScope::Admin->value,
            'products.view' => true,
            'products.manage' => true,
            'products.edit_purchase_cost' => true,
            'stock.view' => true,
            'prices.view' => true,
            'reports.inventory' => true,
            'sales_requests.view' => RecordScope::Admin->value,
            'quotations.view' => RecordScope::Admin->value,
            'procurement.view' => RecordScope::Admin->value,
            'customer_pos.view' => RecordScope::Admin->value,
            'sales_orders.view' => RecordScope::Admin->value,
            'delivery.view' => RecordScope::Admin->value,
            'invoices.view' => RecordScope::Admin->value,
            'email.manage_templates' => true,
            'reports.export' => true,
            // Explicit: no sales_orders.approve, cancel_approved, price approve, credit final, notes approve, etc.
        ],
    ],

    /*
    | Configurable approval authority defaults (workflow_key => role slug).
    | Administrator may reassign the role; permission gates still require the
    | matching permission on that role (no silent bypass).
    */
    'approval_authorities' => [
        'price_margin' => [
            'label_ar' => 'اعتماد السعر والهامش',
            'permission' => 'quotations.approve_price',
            'default_role' => RoleSlug::SalesManager->value,
        ],
        'sales_order' => [
            'label_ar' => 'اعتماد أمر البيع',
            'permission' => 'sales_orders.approve',
            'default_role' => RoleSlug::SalesManager->value,
        ],
        'sales_order_cancel' => [
            'label_ar' => 'إلغاء أمر بيع معتمد',
            'permission' => 'sales_orders.cancel_approved',
            'default_role' => RoleSlug::SalesManager->value,
        ],
        'inventory_adjustment' => [
            'label_ar' => 'اعتماد تسوية المخزون',
            'permission' => 'inventory_adjustment.approve',
            'default_role' => RoleSlug::SalesManager->value,
        ],
        'credit_limit_review' => [
            'label_ar' => 'مراجعة حد الائتمان',
            'permission' => 'credit_limit.review',
            'default_role' => RoleSlug::Accounts->value,
        ],
        'credit_limit_final' => [
            'label_ar' => 'الاعتماد النهائي لحد الائتمان',
            'permission' => 'credit_limit.approve',
            'default_role' => RoleSlug::SalesManager->value,
        ],
        'collection_reversal' => [
            'label_ar' => 'اعتماد عكس التحصيل',
            'permission' => 'collection_reversal.approve',
            'default_role' => RoleSlug::SalesManager->value,
        ],
        'credit_debit_note' => [
            'label_ar' => 'اعتماد إشعار دائن/مدين',
            'permission' => 'credit_notes.approve',
            'default_role' => RoleSlug::SalesManager->value,
        ],
    ],

];
