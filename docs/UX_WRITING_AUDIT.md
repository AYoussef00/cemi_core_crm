# UX Writing & Product Content Audit

**Mode:** Study only — no implementation files changed  
**Date:** 2026-10-16  
**Stack:** Laravel Blade, Arabic RTL UI, `APP_LOCALE=en`  
**Domain context:** Saudi B2B operations CRM (see `docs/WORKFLOWS.md`, `docs/DOMAIN_GLOSSARY.md`)

---

## 1. Verdict

The product already speaks Arabic on most screens and is often honest about phase boundaries. The main quality gaps are: **English leaking into Arabic UI** (validation, pagination, technical keys), **ops/dev wording on end-user or admin-primary surfaces**, and **inconsistent terms** across nav, buttons, and flashes. Fixing locale + a terminology pass will raise perceived quality more than rewriting every sentence.

**Out of scope today (not built as user UI):** sales documents, inventory screens, invoices, collections, generated PDFs/e-invoices, WhatsApp. Backend notification strings for credit-limit workflow exist and are included as content that users can already see in `/account/notifications` when triggered.

---

## 2. Evidence standard

| Tag | Meaning |
|---|---|
| **Verified** | Quoted or observed from application source (views, controllers, Form Requests, support classes, config, `.env`) |
| **Assumption** | Inferred from locale/config without a live browser session for that exact state |
| **Not found** | Surface requested in the brief does not exist yet in routed UI |

Browser visual pass was **not** re-run for this writing audit; findings are from source inspection + prior route inventory context. `docs/ROUTE_INVENTORY.md` is **stale** (lists 5 pages / 13 routes; artisan currently shows **46** routes).

---

## 3. Surfaces reviewed

### 3.1 User-facing pages (GET UI) — Verified routed

| Page | Route | Primary copy sources |
|---|---|---|
| Home | `/` | `foundation/home.blade.php` |
| Login | `/login` | `auth/login`, `LoginRequest`, `EnsureUserIsActive` |
| Forgot password | `/forgot-password` | `auth/forgot-password`, `PasswordResetLinkController` |
| Reset password | `/reset-password/{token}` | `auth/reset-password`, `ResetPasswordRequest`, `NewPasswordController` |
| Account profile | `/account` | `account/profile`, `UpdatePasswordRequest`, `ProfileController` |
| Notifications | `/account/notifications` | `account/notifications/index`, `NotificationController` |
| Customers index/create/show/edit | `/customers…` | `customers/*`, `CustomerController`, Form Requests |
| Users index/create/edit | `/admin/users…` | `admin/users/*`, `UserController`, Form Requests |
| Roles & permissions | `/admin/roles` | `admin/roles/index`, `RolePermissionController` |
| Approval authorities | `/admin/approvals` | `admin/approvals/edit`, config workflow labels |
| Settings hub | `/admin/settings` | `admin/settings/index` |
| Company settings | `/admin/settings/company` | company view + controller + attachments partial |
| Warehouse settings | `/admin/settings/warehouse` | warehouse view + controller + attachments partial |
| Mail settings | `/admin/settings/mail` | mail view + `MailConfigurationInspector` + `MailTestSender` |
| Audit log | `/admin/audit` | `admin/audit/index`, `config/audit.php` labels |

### 3.2 Shared chrome

| Surface | Notes |
|---|---|
| App layout / flash | `layouts/app.blade.php` — `status_ar` |
| Auth layout / flash | `layouts/auth.blade.php` — `status_ar` |
| Sidebar IA | `layouts/partials/app-sidebar.blade.php` — enabled + disabled future modules |

### 3.3 Forms, validation, status, empty states, help

Covered via the pages above + all Form Requests under `app/Http/Requests/{Auth,Account,Admin,Customers}`.

### 3.4 Notifications & emails

| Surface | Status |
|---|---|
| In-app notifications UI | Reviewed |
| Credit-limit notification payloads | Reviewed in `CreditLimitWorkflow` (**Verified** strings; UI route `credit-requests.show` not in `web.php` — **Assumption:** link may 404 until that module is routed) |
| Mail configuration test email | `resources/views/mail/configuration-test.blade.php` |
| Password-reset email | Laravel default notification — **Assumption:** English body while `APP_LOCALE=en` |

### 3.5 Generated documents

| Surface | Status |
|---|---|
| Invoices, quotations, delivery notes, PDFs | **Not found** in current routed UI |

### 3.6 Intentionally excluded

| Asset | Reason |
|---|---|
| `welcome.blade.php` | Orphan, not routed |
| Disabled sidebar labels for future modules | Included only for terminology/tone (they are user-visible) |
| Framework `/up`, storage routes | Non-product UI |

---

## 4. Business language to preserve

Do **not** dilute these when rewriting (from Phase 0 docs + current UI intent):

- Customer / مندوب المبيعات / جهات الاتصال  
- Credit exposure = unpaid issued invoice balances; credit limit gate  
- Sales Order reserve / delivery deduct / cancel release  
- POD before delivery confirm and invoice  
- One company, one warehouse (v1)  
- No self-registration; internal users only  
- Security: secrets stay in environment; passwords never shown in audit  
- Honest empty states (no fake demo data)

---

## 5. Prioritized findings

### P0 — Trust & professionalism (fix first)

| ID | Finding | Evidence | Tag |
|---|---|---|---|
| P0-1 | English validation in Arabic UI for password strength | `Password::defaults()` + no Arabic messages for min/mixedCase/numbers; `APP_LOCALE=en` | Verified |
| P0-2 | Customer forms show bilingual Laravel errors | Attributes Arabic, no `messages()`; e.g. `The اسم العميل field is required.` | Verified mechanism |
| P0-3 | Pagination in English on notifications | `$notifications->links()` + EN locale | Verified |
| P0-4 | Forgot-password exposes `.env` keys to any guest | `MAIL_MAILER`, `MAIL_HOST`, … on `forgot-password` view | Verified |
| P0-5 | Roles UI shows raw English `group_key` + permission slugs | `platform`, `users.view`, etc. | Verified |
| P0-6 | Approvals UI shows English `workflow_key` + `permission_slug` | Primary chrome beside Arabic labels | Verified |
| P0-7 | Notification category shown as raw slug | e.g. `credit_limit` | Verified |
| P0-8 | Password-reset email likely English | Default Laravel mail + `APP_LOCALE=en` | Assumption (not captured live) |

### P1 — Consistency & clarity

| ID | Finding | Evidence | Tag |
|---|---|---|---|
| P1-1 | Login CTA: `دخول` vs `تسجيل الدخول` | Auth nav vs page title/home | Verified |
| P1-2 | Mark-as-read: button `تعيين` vs flash `تعليم` | Notifications view vs controller | Verified |
| P1-3 | Nav `صلاحيات الاعتماد` vs page `جهات الاعتماد` | Sidebar vs approvals view | Verified |
| P1-4 | Nav `مهامي وتنبيهاتي` vs page `الإشعارات` | Sidebar vs notifications | Verified |
| P1-5 | Assigned rep shortened inconsistently | `المندوب المُعيَّن` vs `مندوب المبيعات المُعيَّن` | Verified |
| P1-6 | Cancel/back vocabulary varies | `إلغاء` / `رجوع` / `رجوع للإعدادات` | Verified (acceptable if systematized) |
| P1-7 | Contact role `جهة اتصال الحساب` may read as bank account | Enum label; glossary silent on Arabic | Verified string; ambiguity = Assumption |
| P1-8 | Mail / attachment / audit surfaces mix Arabic with English ops jargon | `.env`, `mailer`, `transport`, `KB`, `/storage`, `audit.view`, `php artisan config:clear` | Verified |

### P2 — Tone (de-robot / de-meta)

| ID | Finding | Evidence | Tag |
|---|---|---|---|
| P2-1 | Home is a build-status report, not a product home | `المرحلة ١ · التأسيس`, `وثائق المرحلة ٠`, `هيكل أبيض فاخر` | Verified |
| P2-2 | Profile lead is auth jargon | `جلسة مصادَق عليها` | Verified |
| P2-3 | Sidebar disabled title `لم يُبنَ بعد` | Repeated on future modules | Verified |
| P2-4 | `الستيكرات` transliteration | Warehouse prep nav | Verified |
| P2-5 | `المصفوفة المعتمدة` on users/roles leads | Admin copy | Verified |
| P2-6 | Mail test email mentions `log` / `array` to recipient | `configuration-test.blade.php` | Verified |
| P2-7 | Dense meta-disclaimers on customer leads | Useful honesty, slightly heavy | Verified; tone judgment = Assumption |

### P3 — Gaps / future content discipline

| ID | Finding | Tag |
|---|---|---|
| P3-1 | No app `lang/` Arabic packs — every new rule risks EN leak | Verified |
| P3-2 | Credit limit UI not on customer screens (intentional); notifications already use credit terms | Verified |
| P3-3 | Generated commercial documents not present — establish glossary-aligned templates when built | Not found |
| P3-4 | `docs/ROUTE_INVENTORY.md` outdated vs 46 routes — refresh when implementing | Verified |

---

## 6. Representative before → after

### 6.1 Locale / validation (P0)

| Before (Verified) | After (proposed) |
|---|---|
| `The password field must contain at least one uppercase and one lowercase letter.` | `يجب أن تحتوي كلمة المرور على حرف كبير وحرف صغير على الأقل.` |
| `The اسم العميل field is required.` | `اسم العميل مطلوب.` |
| Pagination `Previous` / `Next` | `السابق` / `التالي` |

### 6.2 Guest-facing mail warning (P0)

| Before (Verified) | After (proposed) |
|---|---|
| `إعداد البريد غير مكتمل للإنتاج. … للإنتاج عيّن في .env: MAIL_MAILER، وMAIL_HOST، …` | User-facing: `تعذر إكمال إرسال البريد حاليًا. إذا استمرت المشكلة، تواصل مع مدير النظام.` Ops detail: move to `/admin/settings/mail` only (keep env guidance there, optionally behind “تفاصيل للمشغّل”). |

### 6.3 Roles / approvals chrome (P0)

| Before (Verified) | After (proposed) |
|---|---|
| Legend `platform` + slug `users.view` | Legend `المنصة` (or Arabic group map); show Arabic `name_ar` only; keep slug in `title` / expandable “معرّف تقني” for admins who need it |
| Columns showing `workflow_key` | Show `label_ar` only; technical key optional/hidden |

### 6.4 Navigation & actions (P1)

| Before (Verified) | After (proposed) |
|---|---|
| Nav `دخول` | `تسجيل الدخول` (match home CTA) |
| `تعيين كمقروء` + flash `تم تعليم…` | Buttons + flashes both use `تعليم` (or both `تعيين` — pick one) |
| Sidebar `صلاحيات الاعتماد` | `جهات الاعتماد` |
| Sidebar `مهامي وتنبيهاتي` | `الإشعارات` (or rename page to match tasks — prefer one product term) |
| Index column `المندوب المُعيَّن` | `مندوب المبيعات المُعيَّن` |

### 6.5 Home & profile tone (P2)

| Before (Verified) | After (proposed) |
|---|---|
| `المرحلة ١ · التأسيس` + cards about RTL shell / Phase 0 docs | `منصة العمليات` + short purpose: access account, customers (if permitted), and admin tools you can use now — without roadmap jargon |
| `جلسة مصادَق عليها. إدارة الأدوار…` | `إدارة بيانات حسابك وكلمة المرور. لإدارة المستخدمين والأدوار استخدم شاشات الإدارة.` |

### 6.6 Mail / attachments (P1–P2)

| Before (Verified) | After (proposed) |
|---|---|
| Pill `مزود جاهز` | `جاهز للإرسال` (or `مزود خارجي جاهز` if precision needed) |
| Size `12.4 KB` | `١٢٫٤ كيلوبايت` (or bilingual unit policy: Arabic unit always) |
| Attachment help citing `/storage` | `الملفات خاصة ولا تُفتح عبر رابط عام.` |
| Test email: `إذا كان المُرسِل log أو array…` | `إذا كانت بيئة الاختبار محلية، قد لا تصل الرسالة إلى صندوق الوارد.` |

### 6.7 Contact role clarity (P1 — needs stakeholder OK)

| Before (Verified) | After (options — do not invent without approval) |
|---|---|
| `جهة اتصال الحساب` | Prefer stakeholder pick: e.g. `جهة الاتصال التجارية` / `مسؤول الحساب لدى العميل` — glossary has no Arabic yet |

---

## 7. Terminology decisions (proposed canon)

Approve or amend before implementation:

| Concept | Preferred Arabic | Avoid / deprecate |
|---|---|---|
| Sign in | تسجيل الدخول | دخول (as sole CTA) |
| Sign out | تسجيل الخروج | — |
| Approvals config | جهات الاعتماد | صلاحيات الاعتماد (nav) |
| In-app alerts | الإشعارات | مهامي وتنبيهاتي (unless tasks module arrives) |
| Mark read | تعليم كمقروء | تعيين كمقروء |
| Assigned sales rep | مندوب المبيعات المُعيَّن | المندوب المُعيَّن (alone) |
| Customer | عميل / العملاء | زبون |
| Future modules | غير متاح حاليًا | لم يُبنَ بعد |
| Labels / stickers | ملصقات | الستيكرات |
| Permission matrix (admin) | صلاحيات الدور المعتمدة | المصفوفة المعتمدة |
| Local mail | اختبار محلي / لا يصل لصندوق الوارد | Overuse of `array`/`log` in primary copy |

English brand name (`Cemi Core` / `APP_NAME`) may remain as product identity.

---

## 8. Implementation plan (after approval)

### Phase A — Foundation (highest leverage)

1. Publish Arabic lang files (or set `APP_LOCALE=ar` + Laravel lang publish) covering `validation`, `passwords`, `pagination`, `auth`.  
2. Add Arabic messages for `Password::defaults()` failures on all password Form Requests.  
3. Add `messages()` (or shared trait) for Customer Form Requests.  
4. Map notification `category` → Arabic label helper.  
5. Map permission `group_key` → Arabic group titles; demote/hide slugs by default.

### Phase B — Critical surfaces

6. Rewrite guest forgot-password warning (no `.env` on guest pages).  
7. Soften home + profile copy (product language, not phase report).  
8. Unify nav labels (approvals, notifications, login).  
9. Unify mark-as-read verb + assigned-rep label.  
10. Roles / approvals / audit: Arabic-first chrome; technical IDs secondary.  
11. Mail settings + test email: keep security truths; move artisan/`.env` to secondary “للمشغّل” block.  
12. Attachments: Arabic units; remove `/storage` from primary help.

### Phase C — Sweep & document

13. Pass all remaining flashes, empty states, confirms, and sidebar disabled titles.  
14. Arabic password-reset email (custom notification / lang).  
15. Update `docs/ROUTE_INVENTORY.md` and add a short `docs/UX_WRITING_GUIDE.md` (tone + glossary AR).  
16. Verify: form validation (AR), pagination (AR), mail warning (guest vs admin), roles/approvals, notifications category, one full admin + customer journey.

### Explicit non-goals (unless you expand scope)

- Inventing copy for unbuilt sales/finance modules beyond sidebar tone fixes  
- Changing business rules, routes, or schema  
- Visual redesign (separate from writing)  
- Translating audit JSON payloads for end users (keep technical detail for auditors; improve action labels only)

---

## 9. Approval checklist

Please confirm:

1. **Locale strategy:** prefer `APP_LOCALE=ar` + Arabic lang packs, or keep `en` locale with full Arabic `messages()` overrides?  
2. **Technical detail policy:** hide slugs/keys by default for admins, or keep visible for power users?  
3. **Contact role label:** keep `جهة اتصال الحساب` or choose a clearer phrase?  
4. **Home page:** product landing for signed-in users vs keep lightweight “foundation status” tone?  
5. **Scope for first implementation pass:** P0+P1 only, or include P2 tone rewrite of home/sidebar?

---

## 10. Setup note

Laravel Boost install was attempted per `AGENTS.md` but failed (`api.github.com` CONNECT 403). Study completed from application source without Boost. Retry Boost before implementation if required by project policy.

---

## 11. Document control

| Version | Change |
|---|---|
| 2026-10-16 | Initial UX writing audit (study only) |
| 2026-10-16 | Implementation approved and completed (P0–P2). See §12. |

---

## 12. Implementation completion (2026-10-16)

**Approved decisions applied:**
1. `APP_LOCALE=ar` + `lang/ar/*`
2. Technical IDs hidden in primary UI
3. Contact role renamed to `جهة الاتصال التجارية`
4. Home rewritten as product landing (not phase status)
5. Full P0–P1–P2 pass

**Completed**
- Arabic validation / auth / passwords / pagination packs
- Arabic password-reset email notification
- Guest forgot-password warning without `.env`
- Home, profile, auth, notifications, sidebar terminology
- Roles / approvals / audit / settings / mail / attachments copy
- Customers + credit screens tone and terminology
- Mail inspector / test sender user-facing labels softened
- Writing guide: `docs/UX_WRITING_GUIDE.md`

**Verification**
- `php artisan test` — **117 passed** (2026-10-16)

**Remaining / deferred**
- Generated commercial documents (not built yet)
- Future module page copy when those routes ship
- `docs/ROUTE_INVENTORY.md` still stale vs current routes (inventory refresh separate from writing)
- Laravel Boost install still blocked by GitHub network 403
