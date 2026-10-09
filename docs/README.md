# CaseHub - Documentation

Yeh folder batata hai ki ab tak project mein kya-kya bana hai, kaise kaam karta hai, aur APIs ko kaise call karna hai.

| File | Kya hai |
|---|---|
| [01-project-overview.md](01-project-overview.md) | Poora project ek nazar mein: architecture, database tables, roles/permissions, security, setup, tests, aur jo abhi baaki hai |
| [02-admin-panel.md](02-admin-panel.md) | Admin panel: login, forgot password, permissions, Clients/Lawyers management (suspend / activate), unke JSON endpoints |
| [03-mobile-api.md](03-mobile-api.md) | Mobile app ki APIs (Client + Lawyer): register, OTP, login, forgot password. Har endpoint ka dummy request aur response |
| [04-realtime-chat.md](04-realtime-chat.md) | Client-Advocate real-time chat: Reverb setup, WebSocket connect karne ka tareeka, cases + messages ke REST endpoints |
| [05-account-and-notifications.md](05-account-and-notifications.md) | Client + Lawyer dono ke liye: password change (logged-in), profile photo update, in-app notifications; aur sirf Lawyer ke liye practice profile (location/experience/specialization/bio) update |
| [06-billing-and-storage.md](06-billing-and-storage.md) | Client storage plans: monthly auto-recurring billing, upgrade/downgrade/cancel, grace period, storage quota aur evidence document upload/download |
| [07-billing-implementation-guide.md](07-billing-implementation-guide.md) | Storage plans **frontend implementation guide** - mobile app ke liye kaunse screens banane hain, flows, state-based UI, aur QA checklist |
| [08-case-intake-api.md](08-case-intake-api.md) | "Create Case" screen: draft start karna, evidence upload (har file alag), submit karna, aur abandoned drafts ka automatic cleanup |

## Sabse pehle (setup)

```bash
composer install
copy .env.example .env          # phir DB details bharo (DB_DATABASE=case_hub)
php artisan key:generate
php artisan migrate
php artisan db:seed             # Super Admin role + Super Admin + practice areas
php artisan storage:link        # profile photos ke liye
php artisan serve               # http://127.0.0.1:8000
php artisan reverb:start        # real-time chat ke liye (alag terminal mein) - dekho 04-realtime-chat.md
```

- Admin panel: `http://127.0.0.1:8000/` (login page).
- Local Super Admin (sirf local mein seed hota hai): `admin@casehub.test` / `Password@123`.
- Production mein pehla Super Admin banane ke liye: `php artisan admin:create-super`.
- Tests chalane ke liye: `php artisan test` (350+ tests, alag in-memory database use hota hai, tumhara asli database nahi chhuta).

## Zaroori baatein (production se pehle)

1. `.env` mein `APP_DEBUG=false` rakho. `true` hone par error responses mein file paths aur stack trace dikhte hain (API ke 404 / 429 jaise errors mein bhi).
2. HTTPS lagao aur `SESSION_SECURE_COOKIE=true` karo.
3. SMS provider abhi choose nahi hua (details `01-project-overview.md` mein).
4. **Payment gateway bhi abhi choose nahi hua** (Paystack/Flutterwave) - storage-plan billing `BILLING_DRIVER=log` par hai, API kaam karta hai (subscribe/upgrade/downgrade sab chalte hain) lekin **koi real paisa charge nahi hota** jab tak real gateway wire na ho - dekho `06-billing-and-storage.md` section 5.
5. `composer audit` chalao: packages mein security advisories dikh rahi hain, update plan karo.
6. `php artisan reverb:start` ek long-running process hai (supervisor/systemd se chalao, `php artisan serve` ki tarah request-response par nahi chalta). Apne khud ke random `REVERB_APP_ID`/`KEY`/`SECRET` banao - `.env` mein jo hain woh sirf local dev ke liye hain.
