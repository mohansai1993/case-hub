# 01 - Project Overview

CaseHub ek legal case management platform hai: Clients aur Lawyers app se judte hain, aur admin panel se Super Admin / staff sab kuch manage karte hain.

Ab tak jo **bana hua hai**:

1. Admin panel ka design (Figma ke hisab se) Blade layout mein.
2. Admin panel ka **authentication** (login, forgot password OTP, logout) + **roles/permissions**.
3. Mobile app ki **APIs**: Client/Lawyer register, OTP verify, login, forgot password.
4. Admin panel mein **Clients aur Lawyers ka management**: list, details, suspend / activate (dono ke liye same, ek hi type ka control - lawyer ke liye alag se "approve/reject verification" nahi hai).
5. Admin panel mein **Specializations (practice areas) ka management**: add/rename/activate-deactivate/delete - wahi list jo lawyer registration ke chips mein dikhti hai.
6. **Push notifications** (Firebase Cloud Messaging): registration OTP ke alawa, admin panel se clients/lawyers ko chun kar in-app + push notification bhej sakte ho.
7. **Real-time chat** (Laravel Reverb) Client aur Lawyer ke beech, ek minimal `cases` model (client + advocate + title + status) ke upar.

Jo **abhi baaki** hai woh sabse neeche "Kya baaki hai" mein hai.

---

## 1. Tech stack

| Cheez | Kya use hua |
|---|---|
| Backend | Laravel 11, PHP 8.2 |
| Database | MySQL / MariaDB (`case_hub`) |
| Admin UI | Blade + custom CSS (`public/assets/admin/style.css`), font Inter |
| Confirm dialogs | SweetAlert2 (CDN se) |
| Mobile API auth | Laravel Sanctum (Bearer tokens) |
| Tests | PHPUnit 11 (in-memory SQLite) |

---

## 2. Do alag login system (yeh sabse zaroori design decision hai)

| | Admin panel | Mobile app |
|---|---|---|
| Kaun | Super Admin + staff | Client + Lawyer |
| Table | `admins` | `users` |
| Auth | Session cookie (guard `admin`) | Bearer token (Sanctum, guard `sanctum`) |
| URL | `/` aur `/admin/...` | `/api/v1/...` |

**Kyun alag?** Taaki koi app user (client/lawyer) kisi galti ya flag ki wajah se kabhi admin panel mein login na kar sake. Dono ke credentials, sessions aur permissions bilkul alag hain.

---

## 3. Roles aur permissions (admin panel)

- **Super Admin**: sab kuch kar sakta hai. Role ka slug `super-admin`, `is_system = true` (delete / deactivate nahi ho sakta).
- **Staff (Admin)**: sirf wahi kar sakta hai jo uske role mein permissions diye gaye hain. Ek admin ka ek role hota hai.
- **Roles & Permissions** aur **Staff** pages sirf Super Admin ke liye hain, inhe kisi ko delegate nahi kiya ja sakta.

Permissions ki list ek jagah hai: [`config/permissions.php`](../config/permissions.php). Naya permission jodna ho toh wahin ek line badhao.

| Module | Permissions |
|---|---|
| Dashboard | `dashboard.view` |
| Clients | `clients.view`, `clients.create`, `clients.update`, `clients.delete` |
| Lawyers | `lawyers.view`, `lawyers.create`, `lawyers.update`, `lawyers.delete`, `lawyers.practice_areas` |
| Subscriptions | `subscriptions.view`, `subscriptions.manage` |
| Notifications | `notifications.view`, `notifications.create` |
| Settings | `settings.view`, `settings.manage` |

- Login ke baad admin us pehle section par jaata hai jo uski permissions allow karti hain (dashboard par nahi bhi ja sakta). Agar role mein kuch bhi nahi hai toh "No sections assigned" page dikhta hai.
- Har route par permission **server par** check hoti hai (`permission:xyz` middleware). Sidebar mein link chhupana sirf dikhawe ke liye hai.
- Admin ko deactivate karne par uski **agli request par hi** woh logout ho jaata hai.

---

## 3.1 Zaroori security features

| Feature | Kaise kaam karta hai |
|---|---|
| Brute-force se bachav | Ek account par 5 galat login ke baad 1 minute ka lockout. Email ke bade-chhote akshar badalkar bachna possible nahi. |
| Account enumeration se bachav | Galat password aur unknown account ka jawab bilkul same hota hai (message aur timing dono). |
| Password policy | Kam se kam 8 akshar, upper + lower case, ek number. Production mein leaked-password check bhi. Ek jagah se badal sakte ho: `AppServiceProvider`. |
| OTP security | OTP aur reset token sirf hash ke roop mein save hote hain. Galat guesses gine jaate hain, limit ke baad OTP kharab. OTP ek baar hi chalta hai. |
| Session | Login par naya session id (session fixation se bachav). Logout par session invalidate. Panel ke pages browser mein cache nahi hote (Back button se purana data nahi dikhta). |
| Audit | Admin ke saare auth events `admin_auth_logs` mein. Client/Lawyer par admin ke decisions `account_actions` mein (kisne, kya, kyun). |
| Rate limiting | Login, OTP send/verify, register, reset sab par per-IP aur per-target limits. |
| Mobile tokens | Token expire hote hain. Har user ke max 10 devices. Account suspend hote hi uske saare tokens delete. |

---

## 4. Database tables

Migrations `database/migrations/` mein hain.

| Table | Kaam |
|---|---|
| `admins` | Admin panel ke accounts (uuid id, name, email, mobile, password, `role_id`, `status`, `last_login_at`) |
| `roles` | Roles (`name`, `slug`, `is_system`, `is_active`) |
| `role_permissions` | Role ko diye gaye permission keys (`role_id`, `permission`) |
| `admin_password_resets` | Admin ke forgot-password OTP + reset token (sirf hash) |
| `admin_auth_logs` | Admin login/logout/OTP events ka audit trail |
| `users` | App ke accounts: Client + Lawyer. Extra columns: `type` (client/lawyer), `status` (active/inactive/suspended), `email_verified_at` (registration OTP se), `mobile_verified_at` (abhi unused), `terms_accepted_at` |
| `lawyer_profiles` | Lawyer ki extra details: `location`, `years_of_experience`, `bio`, `verification_status` (registration se hamesha `pending`, admin ke paas ab isko badalne ka koi action nahi hai), `verified_at` |
| `practice_areas` | Specialization list (Severance, Compliance, ...) |
| `lawyer_practice_area` | Lawyer <-> practice area link |
| `user_otps` | App users ke OTP (registration ka email verify + forgot password ka mobile SMS), sirf hash |
| `account_actions` | Admin ne kis client/lawyer par kya action kiya (suspend, activate) aur reason |
| `device_tokens` | Har device ka FCM push token (multi-device support) |
| `notifications` | Laravel ka standard database-notification table: in-app notification history (read/unread) |
| `notification_drafts` | Admin ke banaye notification drafts (title + message), baad mein bhejne ke liye |
| `notification_broadcasts` | Audit log: admin ne kisko (bulk ya specific), kab, kaunsa notification bheja |
| `cases` | Minimal case: `client_id`, `advocate_id`, `title`, `status` (pending/accepted/rejected/closed) - chat isi se attach hai |
| `case_messages` | Chat messages: `case_id`, `sender_id`, `body`, `read_at` |
| `personal_access_tokens` | Sanctum ke mobile tokens |
| `sessions`, `cache`, `jobs` ... | Laravel ke standard tables. `sessions.user_id` ko string kiya gaya (UUID ke liye). |

Purana `users.role` column (0 = user, 1 = admin) jaisa tha waisa hi hai, use nahi chhua.

---

## 5. Seeders aur commands

| Command | Kya karta hai |
|---|---|
| `php artisan db:seed` | `RoleSeeder` (Super Admin role) + `AdminSeeder` + `PracticeAreaSeeder`. **Kitni bhi baar chalao, duplicate nahi banta.** |
| `php artisan admin:create-super` | Pehla Super Admin banata hai (password sirf prompt se, command line mein nahi). Production ke liye. |
| `php artisan db:seed --class=DemoDataSeeder` | (Optional, sirf local) test ke liye dummy clients/lawyers. Normal `db:seed` mein shamil nahi hai. |

`AdminSeeder`: agar wohi email ya koi bhi Super Admin pehle se hai toh kuch nahi karta. Local mein `admin@casehub.test` / `Password@123` banata hai. Baaki environments mein `.env` ka `ADMIN_SEED_PASSWORD` set hona zaroori hai warna admin nahi banta.

---

## 6. Folder guide (kahan kya hai)

```
app/
  Enums/                    UserType, UserStatus, VerificationStatus, AdminStatus, DevicePlatform, CaseStatus
  Models/                   Admin, Role, User, LawyerProfile, PracticeArea, AccountAction, UserOtp,
                             DeviceToken, NotificationDraft, NotificationBroadcast, LegalCase, CaseMessage ...
  Events/                   MessageSent (chat, Reverb broadcast)
  Http/
    Controllers/Auth/       Admin login + forgot password
    Controllers/Admin/      Clients, Lawyers, PracticeAreas, Notifications, moderation actions
    Controllers/Api/V1/     Mobile API controllers (auth, notifications, device tokens, cases, chat)
    Middleware/             permission check, admin active check, no-store ...
    Requests/               Har form/API ki validation
    Resources/              User/Notification/Case/CaseMessage ka JSON shape
  Services/
    Auth/                   Admin login + admin forgot password
    AppAuth/                Registration, OTP, API login, app forgot password
    Admin/                  AccountModerationService (suspend/activate), NotificationBroadcastService
    Push/                   FCM (Firebase) push notification gateway
  Notifications/            OTP SMS/email, push notification (FCM), chat message notification
config/                     permissions.php, otp.php, casehub.php, auth.php, firebase.php, broadcasting.php, reverb.php ...
resources/views/            layouts/, auth/login, admin/*
public/assets/admin/        style.css, login.css, moderation.js, images
routes/                     web.php (admin panel), api.php (mobile), channels.php (Reverb private channels)
database/                   migrations, seeders, factories
tests/                      Unit, Feature/Auth, Feature/Api, Feature/Admin
docs/                       yeh documentation
```

Har feature ka logic **Service class** mein hai, controller sirf request lekar service ko bulata hai. Isse code test karna aur badalna aasaan rehta hai.

---

## 7. Configuration (`.env`)

| Key | Matlab |
|---|---|
| `DB_*` | Database connection |
| `MAIL_MAILER` | `log` = email `storage/logs/laravel.log` mein likhi jaati hai (dev). Production mein `smtp` |
| `SMS_DRIVER` | Abhi sirf `log` (dev). Production mein SMS provider lagana padega |
| `OTP_*` | Admin OTP settings (6 digit, 10 min expiry, 60 sec resend, 5 attempts) |
| `APP_OTP_*` | App OTP settings (4 digit, 10 min expiry, 59 sec resend, 5 attempts) |
| `API_TOKEN_TTL_MINUTES` / `API_TOKEN_REMEMBER_TTL_MINUTES` | Token ki umar: normal 7 din, "remember me" par 30 din |
| `ADMIN_SEED_*` | `db:seed` ke Super Admin ki details |
| `SESSION_SECURE_COOKIE` | HTTPS par `true` |
| `PUSH_DRIVER`, `FIREBASE_*` | Push notifications (FCM). `log` = dev, `firebase` = asli device par jaata hai |
| `BROADCAST_CONNECTION`, `REVERB_*` | Real-time chat (Laravel Reverb) - dekho `04-realtime-chat.md` |

Poori list `.env.example` mein hai.

---

## 8. Tests

`php vendor/bin/phpunit` -> **178 tests, sab pass**.

| Folder | Kya check hota hai |
|---|---|
| `tests/Unit` | Email/mobile ko normalise karna |
| `tests/Feature/Auth` | Admin login, lockout, remember me, logout, forgot password OTP flow, permissions, deactivation |
| `tests/Feature/Api` | Registration (validation, photo rules, duplicates), OTP (wrong/expired/attempt limit), login (wrong tab, suspended, lockout, tokens), forgot password, cases + real-time chat (open/accept/reject, messages, unread, channel auth) |
| `tests/Feature/Admin` | Lists, search, filters, pagination, suspend/activate, permissions, audit history |

Tests apne alag in-memory database par chalte hain, tumhare `case_hub` database ko nahi chhute.

---

## 9. Kya baaki hai / jo maan ke chala gaya hoon

**Abhi static (design ka nakli data) wale pages:** Dashboard, Subscriptions, Settings, Roles, Staff. Inka backend abhi nahi bana. Inhe database se jodna agla kaam hai.

**Business rules jo tumne define nahi kiye (maine ye maana hai, badalna ho toh bata dena):**

1. Pending (unverified) lawyer bhi login kar sakta hai; response mein `verification_status` aata hai (hamesha `pending`, admin isko badal nahi sakta - sirf mobile API ke liye field maujood hai).
2. Client aur Lawyer dono ke liye admin ke paas sirf ek hi type ka account control hai: Suspend / Activate. Lawyer ke liye alag se "verification approve/reject" nahi hai.
3. Registration ke baad account tab tak login nahi kar sakta jab tak email OTP verify na ho (pehle mobile SMS se tha, ab email se hota hai).
4. Mobile number Indian format (10 digit, 6-9 se shuru; `+91` chalta hai).
5. Password kam se kam 8 akshar (design mein 6 tha; legal data ke hisab se sakht rakha).
6. **Case feature abhi minimal hai** - sirf `client_id`, `advocate_id`, `title`, `status` (pending/accepted/rejected/closed), taaki real-time chat attach ho sake. Poora case-management (documents, description, location/date, status-history table) alag feature hai, abhi nahi bana - dekho `04-realtime-chat.md`.

**Abhi baaki:**

- SMS provider abhi choose nahi hua. Development mein OTP `storage/logs/laravel.log` mein `[sms:log]` ke saath dikhta hai. Production mein log gateway jaan-boojhkar error deta hai. Provider chunne par `App\Contracts\SmsGateway` implement karke `AppServiceProvider` mein bind karo.
- Cases, documents, storage quota, plans, chat, notifications, push notifications.
- Scheduler: `php artisan schedule:run` har minute chalao (purane tokens/OTP saaf hote hain).
- SweetAlert abhi CDN se aata hai (internet chahiye). Chaho toh project mein hi rakh sakte hain.
