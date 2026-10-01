# 02 - Admin Panel

Admin panel browser mein chalta hai (session cookie se). Yeh file batati hai: pages kaun kaun se hain, kaun kya dekh sakta hai, login/forgot password kaise kaam karta hai, aur Clients/Lawyers ko suspend/activate/approve/reject karne wale JSON endpoints ke request/response.

> Neeche ke JSON endpoints **browser session** se chalte hain (admin login ke baad), Postman/mobile se nahi. Har POST par `X-CSRF-TOKEN` header aur session cookie chahiye. Admin panel ka page khud yeh sab handle karta hai (`moderation.js`).

---

## 1. Pages aur permissions

| URL | Page | Permission |
|---|---|---|
| `/` | Login | (guest) |
| `/admin` | Login ke baad ka home. Pehle allowed section par bhej deta hai | login zaroori |
| `/admin/dashboard` | Dashboard: real client/lawyer/case/subscription counts, recent failed payments, recent platform activity | `dashboard.view` |
| `/admin/clients` | Clients list | `clients.view` |
| `/admin/clients/{id}` | Client details + suspend/activate + unka Subscription card (plan, status, storage usage, recent billing) | `clients.view` (dekhne ke liye), `clients.update` (buttons ke liye) |
| `/admin/lawyers` | Lawyers list | `lawyers.view` |
| `/admin/lawyers/{id}` | Lawyer details + suspend/activate + **approve/reject** verification (jab tak approve nahi, lawyer app mein login nahi kar sakta) | `lawyers.view`; `lawyers.update` |
| `/admin/lawyers/practice-areas` | Specialization chips add/rename/activate-deactivate/delete | `lawyers.practice_areas` |
| `/admin/subscriptions` | Subscription Plans grid + real Client Subscriptions list (search/filter by plan/status, dono dynamic/DB-backed) | `subscriptions.view` |
| `/admin/plans/create`, `/admin/plans/{plan}/edit` | Plan banao/edit karo: naam, storage (MB ya GB), price, description, popular tag | `subscriptions.manage` |
| `/admin/notifications` | Notifications: draft banao, clients/lawyers select karke bhejo (in-app + push), sent history | `notifications.view`; `notifications.create` |
| `/admin/settings` | Apna profile, password change, plan activate/deactivate + bulk deactivate (agar `subscriptions.manage` hai), account delete (Super Admin ke liye nahi dikhta), logout | `settings.view` |
| `/admin/roles`, `/admin/roles/create`, `/admin/roles/{role}`, `/admin/roles/{role}/edit` | Roles: banao, dekho, edit karo, enable/disable karo | Sirf Super Admin |
| `/admin/staff`, `/admin/staff/create`, `/admin/staff/{admin}`, `/admin/staff/{admin}/edit` | Staff: banao, dekho, edit karo, activate/deactivate karo, role badlo, password reset bhejo | Sirf Super Admin |
| `/admin/no-access` | "Koi section assign nahi hua" page | login zaroori |

Bina permission ke page kholne par **403**. Login nahi hai toh login page par redirect.

**Super Admin, Roles & Staff mein kabhi nahi dikhta** - Super Admin role khud Roles list mein nahi aata, uska details/edit page `404` hai, aur koi bhi admin jiska role Super Admin ho, woh Staff list ya Staff details mein kabhi nahi aata (URL se direct access karne par bhi `404`). Apna khud ka account Settings page se manage hota hai, Staff se nahi. Staff/Role forms mein Super Admin role assign bhi nahi ho sakta.

---

## 1.1 Topbar bell icon (system alerts)

Har admin/staff ke apne alerts hain - Laravel ke standard `notifications` table mein stored (wahi table jo `/admin/notifications` ke in-app sends ke liye bhi use hoti hai, bas `notifiable` yahan Admin hota hai, User nahi).

**Abhi sirf ek trigger wired hai:** naya client ya lawyer register karta hai (`RegistrationService`) -> jin admins ke paas `clients.view` (client ke liye) ya `lawyers.view` (lawyer ke liye) permission hai, unko ek alert milta hai. Super Admin ko hamesha milta hai (uske paas har permission implicitly hoti hai).

**"Plan leta hai" abhi admin-bell mein wired nahi hai** - client-to-plan subscription feature khud ab ban chuka hai (dekho `docs/06-billing-and-storage.md`), bas `AdminAlertDispatcher` mein ek naya method add karke admin ko "naya subscription" alert bhejna abhi nahi joda gaya.

Delivery **real-time websocket push nahi hai** - admin panel ka koi browser-side Echo/Pusher client nahi hai (Reverb sirf mobile app ke chat ke liye use hota hai). Bell icon har 20 second mein `GET /admin/alerts` poll karta hai, isliye naya alert ~20 second ke andar dikh jaata hai bina page refresh ke - zyada tar kaam ke liye yeh kaafi hai. Agar sach much instant (sub-second) websocket push chahiye, woh alag se banana padega (Reverb par ek naya private admin channel + browser mein Echo load karna).

| Endpoint | Kya karta hai |
|---|---|
| `GET /admin/alerts` | `unread_count` + last 10 notifications (title, body, action_url, read, created_at) |
| `POST /admin/alerts/{id}/read` | Ek notification ko read mark karo (sirf apna, doosre admin ka `404`) |
| `POST /admin/alerts/read-all` | Sab apne unread notifications ek saath read mark karo |

---

## 2. Login

**Page:** `GET /` (Figma design ke hisab se). Email **ya** 10 digit mobile se login hota hai.

**Form submit:** `POST /login` (normal HTML form, CSRF token ke saath)

| Field | Detail |
|---|---|
| `identifier` | Email ya mobile (`9876543210`, `+91 98765 43210` sab chalte hain) |
| `password` | Password |
| `remember` | `1` = "Remember me" (30 din). Optional |

Nateeja:
- **Sahi:** redirect `/admin` -> phir us admin ke pehle allowed section par.
- **Galat password / unknown account:** login page par wapas, ek hi message: *"These credentials do not match our records."* (dono case mein bilkul same, taaki koi pata na laga sake ki account hai ya nahi).
- **5 galat attempts:** *"Too many login attempts. Please try again in 60 seconds."* (sahi password bhi 1 minute ke liye block).
- **Deactivated admin (password sahi):** *"Your account has been deactivated. Please contact the Super Admin."*
- **Logout:** `POST /logout` (topbar ka logout button).

Har login/failure/lockout/logout `admin_auth_logs` mein likha jaata hai (IP aur browser ke saath, password kabhi nahi).

---

## 3. Forgot password (admin) - OTP flow

Login page par "Forgot password?" -> 3 steps. Teeno JSON endpoints hain aur login page ka JavaScript unhe `fetch` se bulata hai. OTP **6 digit** ka hota hai; email se ya mobile se (jo identifier diya us par).

Guest ke liye hain (login ke baad nahi). Header: `Accept: application/json`, `X-CSRF-TOKEN`, `Content-Type: application/json`.

### 3.1 OTP bhejo - `POST /forgot-password`

Request:
```json
{ "identifier": "admin@casehub.test" }
```

Response `200` (account ho ya na ho, **hamesha yahi**, taaki kisi ko pata na chale kaun registered hai):
```json
{
  "message": "If the account exists, an OTP has been sent.",
  "resend_in": 60
}
```

Response `422` (identifier na email jaisa, na mobile jaisa):
```json
{
  "message": "Please enter a valid email or 10 digit mobile number.",
  "errors": { "identifier": ["Please enter a valid email or 10 digit mobile number."] }
}
```

Baaton par dhyan: 60 second ke andar dobara bhejne par naya OTP nahi jaata (cooldown). Naya OTP purane ko replace karta hai. Development mein OTP `storage/logs/laravel.log` mein milta hai.

### 3.2 OTP verify karo - `POST /forgot-password/verify`

Request:
```json
{ "identifier": "admin@casehub.test", "otp": "482913" }
```

Response `200`:
```json
{ "reset_token": "OR2qoE1EYFFtkszK7zDYoACw4JCQL0xjUgVkgrRTBoXgwXcwrEEDctKeM4nP1noU" }
```

Response `422` (galat / expire / pehle hi use ho chuka / 5 galat guesses ke baad; kaaran nahi batata):
```json
{ "message": "Invalid or expired OTP. Please try again or request a new one." }
```

### 3.3 Naya password - `POST /reset-password`

Request:
```json
{
  "identifier": "admin@casehub.test",
  "reset_token": "OR2qoE1EYFFtkszK7zDYoACw4JCQL0xjUgVkgrRTBoXgwXcwrEEDctKeM4nP1noU",
  "password": "NewAdmin@789",
  "password_confirmation": "NewAdmin@789"
}
```

Response `200`:
```json
{ "message": "Password updated. Please login with your new password." }
```

Response `422` (kamzor password):
```json
{
  "message": "The password field must be at least 8 characters. (and 2 more errors)",
  "errors": {
    "password": [
      "The password field must be at least 8 characters.",
      "The password field must contain at least one uppercase and one lowercase letter.",
      "The password field must contain at least one number."
    ]
  }
}
```

Response `422` (token galat / expire / pehle use ho gaya):
```json
{ "message": "This reset session has expired. Please start again." }
```

Password badalte hi us admin ke **saare purane sessions aur "remember me" cookies band** ho jaate hain. Reset token sirf ek baar chalta hai (15 minute tak).

Limits: OTP bhejne par 5/minute per IP; verify par 10/minute; dono par per-account hourly limit bhi. Limit paar hone par `429`.

---

## 4. Clients aur Lawyers list

`GET /admin/clients` aur `GET /admin/lawyers` (HTML pages, server par filter hote hain).

| Query parameter | Kahan | Matlab |
|---|---|---|
| `q` | Dono | Name, email ya mobile mein search (`%` `_` jaise akshar literally search hote hain) |
| `status` | Dono | `active`, `inactive`, `suspended` |
| `verification` | Lawyers | `pending`, `verified`, `rejected` |
| `practice_area` | Lawyers | Practice area ka id |
| `page` | Dono | Page number (15 per page) |

Galat filter value ignore ho jaati hai (page toot-ta nahi). Example: `/admin/lawyers?practice_area=3&q=marcus`.

List mein sirf app ke accounts dikhte hain: Clients page par sirf clients, Lawyers page par sirf lawyers. Admins kabhi nahi.

---

## 5. Suspend / Activate / Approve / Reject (JSON)

Yeh Details page ke buttons ke peeche ke endpoints hain. Client aur Lawyer dono ke liye suspend/activate same hain. Lawyer ke liye ek extra pair hai - **Approve / Reject** - kyunki lawyer tab tak app mein login hi nahi kar sakta jab tak admin usko approve na kare (`verification_status`, `lawyer_profiles` table ka field, `users.status` se alag). Button dabane par **SweetAlert** dialog khulta hai (confirm, aur suspend/reject par reason likhna zaroori), phir request jaati hai. Kabhi browser ka `confirm()`/`alert()` nahi.

Common: session cookie + `X-CSRF-TOKEN`, `Accept: application/json`. `{id}` = user ka UUID.

| Endpoint | Kya karta hai | Permission | Reason |
|---|---|---|---|
| `POST /admin/clients/{id}/suspend` | Client suspend | `clients.update` | zaroori |
| `POST /admin/clients/{id}/activate` | Client dobara active | `clients.update` | nahi |
| `POST /admin/lawyers/{id}/suspend` | Lawyer suspend | `lawyers.update` | zaroori |
| `POST /admin/lawyers/{id}/activate` | Lawyer dobara active | `lawyers.update` | nahi |
| `POST /admin/lawyers/{id}/approve` | Lawyer verify karo - tabhi woh app mein login kar sakta hai | `lawyers.update` | nahi |
| `POST /admin/lawyers/{id}/reject` | Lawyer ki verification request reject karo | `lawyers.update` | zaroori |

Ek URL se sirf usi type ka account milta hai: client ke URL par lawyer ka id dene par `404`, aur ulta bhi. Admin accounts in URLs se kabhi chhue nahi ja sakte. `approve`/`reject` sirf Lawyer ke URL se kaam karte hain (client ke URL par `404`).

### 5.1 Suspend - `POST /admin/clients/{id}/suspend`

Request:
```json
{ "reason": "Fraudulent documents" }
```

Response `200`:
```json
{
  "message": "Account suspended.",
  "data": { "status": "suspended" }
}
```

Suspend hone par:
- Account `suspended` ho jaata hai aur **uske saare app tokens turant delete** ho jaate hain (woh app se bahar).
- Woh dobara login nahi kar sakta (`403 account_inactive`, dekho `03-mobile-api.md`).
- `account_actions` mein entry banti hai: kis admin ne, kab, pehle kya status tha, reason kya tha.

Response `422` (reason nahi diya / 3 akshar se kam / 500 se zyada):
```json
{
  "message": "Please give a reason.",
  "errors": { "reason": ["Please give a reason."] }
}
```

Response `422` (pehle se suspended):
```json
{ "message": "This account is already suspended." }
```

### 5.2 Activate - `POST /admin/clients/{id}/activate`

Request: body nahi chahiye.

Response `200`:
```json
{
  "message": "Account activated.",
  "data": { "status": "active" }
}
```

Pehle se active account par `422`: `{ "message": "This account is already active." }`. Inactive ya suspended dono ko activate kar sakte hain.

### 5.3 Approve - `POST /admin/lawyers/{id}/approve`

Request: body nahi chahiye.

Response `200`:
```json
{
  "message": "Advocate approved.",
  "data": { "status": "active", "verification_status": "verified" }
}
```

Approve hone par:
- `lawyer_profiles.verification_status` `verified` ho jaata hai, `verified_at` set hota hai.
- Lawyer ab login kar sakta hai (`03-mobile-api.md` ka `lawyer_not_verified` ab nahi aayega).
- Lawyer ko **email** jaata hai ("Your CaseHub advocate account has been approved") aur app ke andar ek in-app notification bhi milti hai (jo login karte hi dikhegi).
- `account_actions` mein `lawyer_approved` entry banti hai.

Response `422` (pehle se verified): `{ "message": "This advocate is already verified." }`

### 5.4 Reject - `POST /admin/lawyers/{id}/reject`

Request:
```json
{ "reason": "Bar council ID could not be verified" }
```

Response `200`:
```json
{
  "message": "Advocate rejected.",
  "data": { "status": "active", "verification_status": "rejected" }
}
```

Reject hone par:
- `lawyer_profiles.verification_status` `rejected` ho jaata hai. Account suspend nahi hota (`users.status` nahi badalta) - bas login block ho jaata hai verification ki wajah se.
- Lawyer ko **email** jaata hai reason ke saath (koi in-app notification nahi, kyunki reject hone ke baad woh kabhi login hi nahi kar sakta app mein - database notification kabhi dikhti hi nahi).
- `account_actions` mein `lawyer_rejected` entry banti hai, reason ke saath.

Response `422` (reason nahi diya, ya pehle se rejected) - suspend jaisa hi format.

Ek rejected lawyer ko baad mein **approve** kiya ja sakta hai (koi permanent lock nahi hai).

### 5.5 Common errors (sab 6 endpoints par)

| Status | Kab | Response |
|---|---|---|
| `401` | Admin login nahi hai | `{ "message": "Unauthenticated." }` |
| `403` | Permission nahi (jaise sirf `clients.view` wala suspend kare, ya `clients.update` wala lawyer par) | `{ "message": "You do not have permission to access this page." }` |
| `404` | Account nahi mila / galat type / galat UUID | `{ "message": "..." }` |
| `419` | CSRF token / session expire | page refresh karo |
| `422` | Reason nahi / state galat | upar dekho |

Do admins ek saath click karein toh ek hi kaam jeetega: dusre ko `422` ("already ...") milta hai (row lock ke saath check hota hai).

---

## 6. Account History

Har client/lawyer ke details page par **"Account History"** card hai: kaun sa action, kaunse admin ne, kab, aur reason. Yeh `account_actions` table se aata hai aur badla nahi jaata (sirf jodta hai).

| `action` | Matlab |
|---|---|
| `suspended` | Account suspend |
| `activated` | Account activate |
| `lawyer_approved` | Advocate verification approve |
| `lawyer_rejected` | Advocate verification reject |

---

## 7. Design ka kaam (front-end)

- Admin ka design `design/` folder ke HTML se Blade layout mein convert kiya gaya: `layouts/admin.blade.php` (sidebar + topbar + content), `layouts/auth.blade.php` (login).
- Login page Figma ke hisab se banaya (`public/assets/admin/login.css`), font **Inter**.
- Baaki admin pages ka text/spacing Figma ke hisab se bada kiya gaya (pehle sab bahut chhota tha). Yeh ek scale factor se hua hai kyunki baaki pages ka Figma nahi mila tha; koi page alag dikhe toh screenshot bhejo.
- Clients aur Lawyers ke pages se Cases / Subscription / Storage ke columns abhi hata diye gaye hain kyunki unki tables abhi nahi bani (nakli sankhya nahi dikhani).
