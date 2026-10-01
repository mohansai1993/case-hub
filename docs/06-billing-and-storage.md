# 06 - Storage Plans & Billing (Client only)

Monthly, auto-recurring storage subscriptions for clients (lawyers don't have plans - the vault belongs to the client's cases). Replaces the old one-time-payment design entirely. Currency is **NGN (₦)**, configurable via `config/billing.php`.

> Frontend/mobile dev ho aur app mein yeh feature banana hai? `07-billing-implementation-guide.md` seedha usi ke liye hai - screens, flows, aur har state mein UI kaisa dikhe.

> **No payment gateway is wired yet.** Everything below (state machine, quota tracking, grace period, admin UI) is real and tested. The actual charge call goes through `App\Contracts\BillingGateway`, currently bound to `LogBillingGateway` - it logs every charge as "successful" instead of actually charging a card, in every environment including production. **No real money moves until a real gateway is wired** - see [§5](#5-wiring-a-real-payment-gateway) before relying on this for real payments.

---

## 1. The state machine

| State | How you get here | Can read existing cases/docs/chat | Can upload / submit new cases | Can manage billing / profile |
|---|---|---|---|---|
| **Active, storage available** | Normal | Yes | Yes | Yes |
| **Active, storage full** | Usage hits the plan's limit | Yes | **No** | Yes |
| **Cancelled (grace)** | Client cancels | Yes | No | Yes |
| **Restricted** | Grace period (7 days, `BILLING_GRACE_DAYS`) expires | **No** | No | Yes (only way back in) |

- A client never holds more than one plan at a time - one row in `client_subscriptions` per client. Upgrading/downgrading replaces it; it is never "stacked".
- **Upgrade**: must be to a strictly bigger plan. Full new price charged immediately, no proration.
- **Downgrade**: must be to a strictly smaller plan. Requires the caller to send `"confirmed": true` (the app must show a warning first - the server never trusts that the warning was shown and rejects the call otherwise). Oldest files are hidden first (not deleted) until usage fits the new limit.
- **Cancel**: stops the gateway's auto-renewal, starts the 7-day grace countdown, sends an in-app notification.
- **Restricted -> Active**: only by subscribing again (`POST /subscription/subscribe` or `/upgrade`).
- Nothing here needs admin approval. A successful charge grants access immediately - see `app/Services/Billing/SubscriptionService.php`.

---

## 2. Data model

| Table | What it is |
|---|---|
| `plans` | Same table the admin panel manages (`/admin/subscriptions`) - name, storage (MB/GB), monthly price, popular/active flags |
| `client_subscriptions` | One row per client: current plan, status, `current_period_ends_at`, `cancelled_at`, `grace_ends_at`, `restricted_at`, gateway references |
| `subscription_charges` | Append-only billing audit trail - every charge attempt, success or failure, with `reason` (subscribe/upgrade/downgrade/renewal) |
| `case_documents` | Evidence files on a case. Count toward the **case's client's** quota regardless of who uploaded them. `inaccessible_at` hides a file (downgrade) without ever auto-deleting it |

`App\Services\Billing\StorageQuotaService` computes usage (`sum(size_bytes)` of accessible documents across all of a client's cases) and the plan's limit (`storage_amount` converted to bytes). Nothing is cached - always computed fresh.

---

## 3. Mobile API endpoints

All require `Authorization: Bearer <token>`. The `subscription.active` middleware (gate 2 below) does **not** apply to the subscription/plans/profile routes - a Restricted client must still be able to pay to get back in.

| Method | URL | Notes |
|---|---|---|
| GET | `/subscription` | Current plan, status, storage used/limit. `data: null` if never subscribed |
| POST | `/subscription/subscribe` | Body `{ "plan_id" }`. 403 for lawyers, 422 if already subscribed or card declined |
| POST | `/subscription/upgrade` | Body `{ "plan_id" }`. 422 if the plan isn't actually bigger |
| POST | `/subscription/downgrade` | Body `{ "plan_id", "confirmed": true }`. 422 if the plan isn't actually smaller, or `confirmed` is missing |
| POST | `/subscription/cancel` | No body. Starts the 7-day grace period |
| GET | `/cases/{case}/documents` | List evidence (accessible + hidden, each flagged) |
| POST | `/cases/{case}/documents` | Body `multipart/form-data`, field `file` (PDF/Word/JPG/PNG, max 20MB). `422 storage_full` if the client's quota is full or they aren't paying |
| GET | `/cases/{case}/documents/{document}/download` | Streams the file. Requires the same case-participant check as everything else (not a signed/public URL - this is legal evidence) |

`POST /cases` (opening a case) now also 422s with `code: storage_full` if the client has no active plan or their storage is full - "without evidence the advocate won't know what the case entails."

### Example: `GET /subscription`

```json
{
  "data": {
    "status": "active",
    "plan": { "id": 2, "name": "Standard", "storage": "5 GB", "price": 2999 },
    "storage": { "used_bytes": 1048576, "limit_bytes": 5368709120, "is_full": false },
    "current_period_ends_at": "2026-11-01T10:00:00+00:00",
    "cancelled_at": null,
    "grace_ends_at": null,
    "restricted_at": null
  }
}
```

### Common errors

| Status | Code | Kab |
|---|---|---|
| `403` | - | Lawyer called a client-only endpoint |
| `403` | `subscription_restricted` | Grace period expired - every route except subscription/plans/profile |
| `422` | `storage_full` | No active plan, or quota full, on upload / case creation |
| `422` | - | Declined card, wrong plan direction (upgrade to smaller / downgrade to bigger), already subscribed, missing `confirmed` on downgrade |

---

## 4. Scheduled job

`php artisan subscriptions:expire-grace-periods` runs daily (`routes/console.php`, needs `php artisan schedule:run` every minute via cron, already required for OTP/token pruning). Flips any `Cancelled` subscription whose `grace_ends_at` has passed to `Restricted` and sends a notification. Nothing is ever auto-deleted by this job - see §6.

---

## 5. Wiring a real payment gateway

No gateway is chosen yet (Paystack and Flutterwave are the common Nigerian options). To go live:

1. Implement `App\Contracts\BillingGateway` (`charge()`, `cancelRecurring()`) for your provider.
2. Bind it in `AppServiceProvider::register()` based on `config('billing.driver')`, same pattern as `SmsGateway`/`PushGateway`.
3. Set `BILLING_DRIVER` in `.env` to your new driver name.

Until that's done, `LogBillingGateway` stays bound everywhere (including production) and every subscribe/upgrade/downgrade "succeeds" without charging a real card - it logs a `warning`-level line in production (`[billing:log] ... NO REAL GATEWAY CONFIGURED, no money moved.`) on every charge so that stays visible in the logs. No `.env` change is needed for the API to work; the gap is simply that no real money is collected until a real gateway is wired.

`SubscriptionService` always charges **before** touching any DB state, and never inside a DB transaction - a successful charge must never be undone by an unrelated DB failure, and a DB failure must never leave a subscription row changed without a matching successful charge.

---

## 6. Deliberately left out (for now)

- **Automatic permanent deletion.** "Restricted" data is flagged/inaccessible, never actually deleted by any code here. Decide a real second deadline (e.g. delete 30 days after restriction) before building that - it's irreversible, so it wasn't guessed at.
- **Proration.** Upgrading mid-cycle charges the full new price immediately; no credit for unused time on the old plan.
- **Renewal retries.** The monthly renewal charge itself depends entirely on whichever gateway is chosen (most, like Paystack/Flutterwave, auto-retry failed renewals and call your webhook) - no webhook endpoint exists yet since there's no gateway to receive from.
