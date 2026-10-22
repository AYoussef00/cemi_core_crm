# Customer credit — implementation notes

**Phase:** 2 Prompt 2 (profile + approval workflow)  
**Confirmed sequence:** Sales Representative requests → Accounts reviews → Sales Manager final approval (`WORKFLOWS` §7, `ROLES_AND_PERMISSIONS` §5.8).

---

## Effective vs requested values

| Value | Storage | When effective |
|---|---|---|
| Requested credit limit / payment term | `credit_limit_requests` | Never — informational until final approval |
| Approved credit limit / payment term | `customers.approved_credit_limit`, `customers.approved_payment_term` | Only after Sales Manager **approve** |

Rejection (Accounts review or SM final) **does not** change approved customer columns.

---

## Statuses (confirmed transitions only)

```
pending_review → (Accounts forward) → pending_final → (SM approve) → approved
pending_review → (Accounts reject)  → rejected
pending_final  → (SM reject)        → rejected
```

**Not implemented (policy open):** return-for-revision, edit-in-place resubmission of the same request row. After rejection, a **new** request may be submitted. Tracked as `CR-CREDIT-RETURN` in `OPEN_QUESTIONS.md`.

Payment term is free-text until calendar rules (`CR5`) are decided.

---

## Exposure and order gate

Confirmed policy:

```
credit_exposure = sum(unpaid balances of customer's issued invoices)
# Approved / undelivered Sales Orders are excluded.
if credit_exposure >= customer.approved_credit_limit:
    block new orders
```

**Phase 7 Prompt 1:** `App\Support\Credit\CreditExposure::forCustomer()` sums unpaid balances of **issued** invoices per `CR7` (confirmed collections + approved notes). Wired on Sales Order approve. Gate: null `approved_credit_limit` → allow; otherwise exposure must be **&lt;** limit. With no issued invoices, exposure is `0.00` (honest empty, not a stub).

**Remaining:**

1. Credit profile UI may still need polish to surface live exposure figures — do not show fake balances when none exist.
2. Payment-term calendar rules (`CR5`) still open.

---

## Notifications

Internal database only (`InternalNotifier`):

| Event | Recipients |
|---|---|
| Request submitted | Accounts role |
| Review forwarded | Sales Manager role |
| Review/final reject or final approve | Requesting Sales Representative |
