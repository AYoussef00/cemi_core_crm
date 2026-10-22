# Cemi Core CRM — Design System

**Source references:** White-luxury admin shell (Preclinic-like structure) + dark auth glass  
**Elevation:** Soft white cards, deep-blue active states, iPhone-style white luxury glass buttons  
**Stack:** Blade · Tailwind 4 · Arabic RTL  
**Status:** Active product identity (2026-10-16)

Do not copy reference product names, clinic widgets, or fake KPI data. Extract structure and adapt for Cemi.

---

## 1. Dual surface model

| Surface | Used for | Character |
|---|---|---|
| **Auth (dark)** | Login, forgot password, reset password | Dark canvas, bright blue primary CTA, soft rounded fields, split composition |
| **App (white luxury)** | Home, account, admin modules | Soft gray canvas `#f4f6f9`, solid white sidebar + cards, deep blue `#2e37a4` active/nav |

Future domain pages (customers, sales, warehouse, finance) use the **App** surface exclusively.

---

## 2. Color tokens

| Token | Value | Role |
|---|---|---|
| `--ds-primary` | `#2e37a4` | Primary actions, active nav, focus |
| `--ds-primary-strong` | `#232b86` | Hover / pressed |
| `--ds-ink` | `#1a1d2e` | Primary text |
| `--ds-ink-muted` | `#6b7285` | Secondary text / labels |
| `--ds-surface` | `#f4f6f9` | App page background |
| `--ds-elevated` | `#ffffff` | Cards, sidebar, tables |
| `--ds-border` | `rgb(15 23 42 / 0.08)` | Dividers, card edges |
| `--ds-auth-bg` | `#000000` | Auth canvas |
| `--ds-success` | `#12b76a` | Positive / complete |
| `--ds-danger` | `#f04438` | Errors / inactive |
| `--ds-warning` | `#f79009` | Caution |

Auth primary CTA may use brighter `#0071e3` on dark backgrounds for contrast.

---

## 3. Typography

- **Primary typeface:** Almarai (300, 400, 700, 800)
- Hierarchy via weight and modest size steps
- App page titles ~1.5–1.75rem bold; sidebar labels medium weight

---

## 4. Layout conventions

### Auth
- Full-viewport dark shell (no app sidebar)
- Split: form column + atmospheric panel
- Bright glass primary CTA

### App (RTL)
- **Sidebar on the right** (DOM-first + `dir=rtl`), **always visible**, full labels (not icon-rail)
- Sidebar structure (mandatory for new pages):
  1. Product brand mark + name
  2. Org card (real company singleton — or honest “لم يُعدَّ بعد”)
  3. Section labels + **expandable groups** (Preclinic-like tree: chevron, vertical guide, dots)
  4. **Enabled** = real routed page (+ permission). **Disabled** = future module — muted, `aria-disabled`, not clickable
  5. Active child = blue text + solid blue dot; active group summary = soft blue wash
  6. User chip + white-glass logout
- Top bar: page title + real notify/avatar only — **no fake search / Cmd+K / AI chip**
- Content: title row + glass action buttons; white cards on gray canvas
- Radius: ~14–18px cards; ~12–14px buttons
- Shadow: soft only (`--ds-shadow`)

---

## 5. Buttons (white luxury glass)

| Class | Look |
|---|---|
| `.btn-primary` / `.btn-glass-primary` | Deep blue with white glass highlight + soft shadow |
| `.btn-secondary` / `.btn-glass` | White frosted glass, thin border, soft lift |

Use these on every app surface. Do not invent third accent button styles per page.

---

## 6. Components (reuse)

Inputs / selects: white fields, soft border, blue focus ring.  
Alerts: soft success / warning / danger fills.  
Badges: success / danger / warning / info / process / neutral pills.  
**Data tables (required pattern — elevated card):**
1. Page bar `.ds-page-bar` — page H1 + lead on one side; search / filter / CTA tools on the other (only real tools)
2. Optional `.ds-filter-bar` card for multi-field filters — **no nested title inside the table chrome**
3. Table surface `.ds-table-surface` / `.ds-data-card` — **white elevated card** (soft border, radius, shadow, thin primary top accent); titles stay on the page bar
4. Grid — tinted sticky header; zebra rows; hover + open-menu row highlight; `#` index when paginated; primary cell weight; status badges; kebab `⋮` with a **high-contrast** floating panel (border + deep shadow, surface `overflow: visible` so menus are not clipped)
5. Footer — `.ds-pager` via `vendor/pagination/cemi` (meta + page chips + السابق/التالي) inside the card footer band
Do **not** invent Filter / Add / per-page controls without backend support.  
Empty states: one honest sentence in a matching card — never decorative fake charts or KPI sparklines.

---

## 7. Vision for future pages (design only — not implemented)

When Phase modules are routed, extend this system:

| Future area | Shell | Pattern |
|---|---|---|
| Customers / visits | App | List card + filters + detail form |
| Products / inventory | App | Catalog table; stock badges |
| Quotes / SO | App | Pipeline tabs + dense table; status pills |
| Warehouse / delivery | App | Task cards + checklist |
| Invoices / collections | App | Ledger table; amount column LTR |
| Reports | App | Filter bar + chart card **only when real metrics exist** |

Navigation grows by **real routes only**. Never add “قريبًا” dead links, fake calendars, or invented KPI strips.

---

## 8. Motion

Subtle rise-in for panels; ~200ms ease on hover/focus. Respect `prefers-reduced-motion`.

---

## 9. Fidelity notes vs reference

- **Kept:** white sidebar IA (brand → org card → sections → links), soft blue active item, title-row actions, white cards on gray, rounded luxury controls  
- **Adapted:** Arabic RTL (sidebar right), Cemi branding, real company singleton in org card  
- **Omitted until real:** Cmd+K search, AI assistant, appointment charts, calendar widgets, fake % trend pills
