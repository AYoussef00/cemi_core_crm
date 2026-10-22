# UX Writing Guide

**Audience:** Internal Arabic RTL business users (Saudi B2B operations)  
**Locale:** `APP_LOCALE=ar`

## Voice

Professional, clear, calm. Prefer short sentences. State what the user can do now; do not expose roadmap jargon (`المرحلة ٠`, `لم يُبنَ بعد`).

## Canon terms

| Concept | Prefer | Avoid |
|---|---|---|
| Sign in | تسجيل الدخول | دخول (alone as CTA) |
| Notifications | الإشعارات | مهامي وتنبيهاتي |
| Approvals config | جهات الاعتماد | صلاحيات الاعتماد |
| Mark read | تعليم كمقروء | تعيين كمقروء |
| Assigned rep | مندوب المبيعات المُعيَّن | المندوب المُعيَّن |
| Commercial contact | جهة الاتصال التجارية | جهة اتصال الحساب |
| Accounting contact | جهة اتصال المحاسبة | — |
| Future module | غير متاح حاليًا | لم يُبنَ بعد |
| Labels | ملصقات | الستيكرات |

## Technical detail policy

Hide slugs, workflow keys, `.env` names, and mailer internals from primary UI.  
Ops-only detail may live under «تفاصيل للمشغّل» on admin mail settings.

## Validation

Use Arabic lang packs under `lang/ar/`. Custom Form Request `messages()` may still override for product-specific phrasing. Password strength messages come from `validation.password.*`.

## Preserve

Credit exposure rules, POD/invoice constraints, one company / one warehouse, no self-registration, and security explanations about secrets.
