# Architecture — Modular Monolith Convention

**Status:** Phase 1 foundation (Prompt 1)  
**Stack:** Laravel 13 · PHP 8.4 · Blade · Vite 8 · Tailwind CSS 4  
**UI:** Arabic primary, `dir="rtl"`; responsive desktop/mobile

This document defines where code belongs as business modules are added. It does **not** invent unresolved Phase 0 business rules.

---

## 1. Principles

- **One Laravel application** (modular monolith), not microservices.
- **Laravel conventions first** — controllers, Form Requests, policies, Eloquent, jobs, events.
- **Module boundaries** follow Phase 0 capabilities (customers, inventory, sales, warehouse, finance, etc.).
- **No decorative UI** — every control must map to a real backend workflow.
- **Do not encode open Phase 0 questions** into schema or “fake” defaults.

---

## 2. Directory convention

```
app/
  Http/
    Controllers/
      Foundation/          # App shell / non-domain pages
      Domains/             # Future: Domains/{Module}/*Controller.php
    Middleware/
    Requests/
      Domains/             # Future Form Requests per module
  Models/                  # Shared/core models (e.g. User); domain models may move under Domains later if needed
  Policies/                # Future authorization policies
  Domains/                 # Optional module namespaces for Actions/Services
    Customers/
    Inventory/
    Sales/
    Warehouse/
    Finance/
    Communications/
resources/
  views/
    layouts/               # Shared shells (RTL app layout)
    components/            # Reusable Blade components
    foundation/            # Foundation / empty states
    domains/               # Future: domains/{module}/*.blade.php
  css/app.css              # Design tokens + Tailwind
  js/app.js
tests/
  Feature/
    Foundation/            # Shell / foundation tests
    Domains/               # Future module feature tests
  Unit/
docs/                      # Phase 0+ project documentation
```

### Placement rules

| Concern | Location |
|---|---|
| HTTP entry | `app/Http/Controllers/...` |
| Validation | `app/Http/Requests/...` |
| Authorization | `app/Policies/...` + Gates from `config/authorization.php`; `app/Support/Authorization/*`; enforce server-side |
| Domain workflows | `app/Domains/{Module}/` Actions or Services when logic outgrows controllers |
| API Resources (if/when) | `app/Http/Resources/Domains/{Module}/` |
| Views | `resources/views/domains/{module}/` |
| Feature tests | `tests/Feature/Domains/{Module}/` |
| Shared UI shell | `resources/views/layouts/`, `resources/views/components/` |

Keep controllers thin. Prefer the simplest structure that stays clear; do not add repositories/DTOs without justification.

---

## 3. Shared UI shell

- Layout: `resources/views/layouts/app.blade.php`
- Language: Arabic (`lang="ar"`), direction RTL (`dir="rtl"`)
- Tokens: CSS variables in `resources/css/app.css` (`@theme` / `:root`)
- Navigation: only real, working destinations — never placeholder module links

---

## 4. Related documents

- `docs/PROJECT_OVERVIEW.md` — modules and goals  
- `docs/ROLES_AND_PERMISSIONS.md` — approved matrix  
- `docs/PHASE_ACCEPTANCE.md` — phase gates  
- `docs/OPEN_QUESTIONS.md` — unresolved domain decisions  

---

## 5. Phase 1 Prompt 1 boundary

Implemented now: convention doc + RTL shared shell + honest foundation home page + tests.  
**Not** in this step: authentication, roles UI, business modules, domain migrations, dashboards with metrics.
