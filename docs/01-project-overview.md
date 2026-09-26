# 01 - Project Overview

CaseHub ek legal case management platform hai: Clients aur Lawyers app se judte hain, aur admin panel se Super Admin / staff sab kuch manage karte hain.

Ab tak jo **bana hua hai**:

1. Admin panel ka design (Figma ke hisab se) Blade layout mein.
2. Admin panel ka **authentication** (login, forgot password OTP, logout) + **roles/permissions**.
3. Mobile app ki **APIs**: Client/Lawyer register, OTP verify, login, forgot password.
4. Admin panel mein **Clients aur Lawyers ka management**: list, details, suspend / activate, lawyer approve / reject.

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
| Lawyers | `lawyers.view`, `lawyers.create`, `lawyers.update`, `lawyers.delete`, `lawyers.verify` |
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
| `users` | App ke accounts: Client + Lawyer. Extra columns: `type` (client/lawyer), `status` (active/inactive/suspended), `mobile_verified_at`, `terms_accepted_at` |
| `lawyer_profiles` | Lawyer ki extra details: `location`, `years_of_experience`, `bio`, `verification_status` (pending/verified/rejected), `verified_at` |
| `practice_areas` | Specialization list (Severance, Compliance, ...) |
| `lawyer_practice_area` | Lawyer <-> practice area link |
| `user_otps` | App users ke OTP (mobile verify + forgot password), sirf hash |
| `account_actions` | Admin ne kis client/lawyer par kya action kiya (suspend, activate, approve, reject) aur reason |
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
  Enums/                    UserType, UserStatus, VerificationStatus, AdminStatus
  Models/                   Admin, Role, User, LawyerProfile, PracticeArea, AccountAction, UserOtp ...
  Http/
    Controllers/Auth/       Admin login + forgot password
    Controllers/Admin/      Clients, Lawyers, moderation actions
    Controllers/Api/V1/     Mobile API controllers
    Middleware/             permission check, admin active check, no-store ...
    Requests/               Har form/API ki validation
    Resources/UserResource  User ka JSON shape
  Services/
    Auth/                   Admin login + admin forgot password
    AppAuth/                Registration, OTP, API login, app forgot password
    Admin/                  AccountModerationService (suspend/approve...)
  Notifications/            OTP SMS / email
config/                     permissions.php, otp.php, casehub.php, auth.php ...
resources/views/            layouts/, auth/login, admin/*
public/assets/admin/        style.css, login.css, moderation.js, images
routes/                     web.php (admin panel), api.php (mobile)
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

Poori list `.env.example` mein hai.

---

## 8. Tests

`php vendor/bin/phpunit` -> **170 tests, sab pass**.

| Folder | Kya check hota hai |
|---|---|
| `tests/Unit` | Email/mobile ko normalise karna |
| `tests/Feature/Auth` | Admin login, lockout, remember me, logout, forgot password OTP flow, permissions, deactivation |
| `tests/Feature/Api` | Registration (validation, photo rules, duplicates), OTP (wrong/expired/attempt limit), login (wrong tab, suspended, lockout, tokens), forgot password |
| `tests/Feature/Admin` | Lists, search, filters, pagination, suspend/activate/approve/reject, permissions, audit history |

Tests apne alag in-memory database par chalte hain, tumhare `case_hub` database ko nahi chhute.

---

## 9. Kya baaki hai / jo maan ke chala gaya hoon

**Abhi static (design ka nakli data) wale pages:** Dashboard, Subscriptions, Settings, Notifications, Roles, Staff. Inka backend abhi nahi bana. Inhe database se jodna agla kaam hai.

**Business rules jo tumne define nahi kiye (maine ye maana hai, badalna ho toh bata dena):**

1. Pending (unverified) lawyer bhi login kar sakta hai; response mein `verification_status` aata hai. Unhe kya karne dena hai, yeh rule tum batao.
2. Client ke liye "Approve" ka matlab "Activate Account" rakha hai. Lawyer ke liye "Approve" = verification.
3. Registration ke baad account tab tak login nahi kar sakta jab tak mobile OTP verify na ho.
4. Mobile number Indian format (10 digit, 6-9 se shuru; `+91` chalta hai).
5. Password kam se kam 8 akshar (design mein 6 tha; legal data ke hisab se sakht rakha).

**Abhi baaki:**

- SMS provider abhi choose nahi hua. Development mein OTP `storage/logs/laravel.log` mein `[sms:log]` ke saath dikhta hai. Production mein log gateway jaan-boojhkar error deta hai. Provider chunne par `App\Contracts\SmsGateway` implement karke `AppServiceProvider` mein bind karo.
- Cases, documents, storage quota, plans, chat, notifications, push notifications.
- Scheduler: `php artisan schedule:run` har minute chalao (purane tokens/OTP saaf hote hain).
- SweetAlert abhi CDN se aata hai (internet chahiye). Chaho toh project mein hi rakh sakte hain.
