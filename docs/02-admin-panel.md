# 02 - Admin Panel

Admin panel browser mein chalta hai (session cookie se). Yeh file batati hai: pages kaun kaun se hain, kaun kya dekh sakta hai, login/forgot password kaise kaam karta hai, aur Clients/Lawyers ko suspend/approve karne wale JSON endpoints ke request/response.

> Neeche ke JSON endpoints **browser session** se chalte hain (admin login ke baad), Postman/mobile se nahi. Har POST par `X-CSRF-TOKEN` header aur session cookie chahiye. Admin panel ka page khud yeh sab handle karta hai (`moderation.js`).

---

## 1. Pages aur permissions

| URL | Page | Permission |
|---|---|---|
| `/` | Login | (guest) |
| `/admin` | Login ke baad ka home. Pehle allowed section par bhej deta hai | login zaroori |
| `/admin/dashboard` | Dashboard (abhi static) | `dashboard.view` |
| `/admin/clients` | Clients list | `clients.view` |
| `/admin/clients/{id}` | Client details + suspend/activate | `clients.view` (dekhne ke liye), `clients.update` (buttons ke liye) |
| `/admin/lawyers` | Lawyers list | `lawyers.view` |
| `/admin/lawyers/{id}` | Lawyer details + suspend/activate + approve/reject | `lawyers.view`; `lawyers.update`; `lawyers.verify` |
| `/admin/subscriptions`, `/admin/subscription-details` | Subscriptions (abhi static) | `subscriptions.view` |
| `/admin/create-plan` | Plan banana (abhi static) | `subscriptions.manage` |
| `/admin/notifications` | Notifications (abhi static) | `notifications.view` |
| `/admin/settings` | Settings (abhi static) | `settings.view` |
| `/admin/roles`, `/admin/create-role`, `/admin/role-details` | Roles (abhi static) | Sirf Super Admin |
| `/admin/staff`, `/admin/create-staff`, `/admin/staff-details` | Staff (abhi static) | Sirf Super Admin |
| `/admin/no-access` | "Koi section assign nahi hua" page | login zaroori |

Bina permission ke page kholne par **403**. Login nahi hai toh login page par redirect.

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

Galat filter value ignore ho jaati hai (page toot-ta nahi). Example: `/admin/lawyers?verification=pending&q=marcus`.

List mein sirf app ke accounts dikhte hain: Clients page par sirf clients, Lawyers page par sirf lawyers. Admins kabhi nahi.

---

## 5. Suspend / Activate / Approve / Reject (JSON)

Yeh Details page ke buttons ke peeche ke endpoints hain. Button dabane par **SweetAlert** dialog khulta hai (confirm, aur suspend/reject par reason likhna zaroori), phir request jaati hai. Kabhi browser ka `confirm()`/`alert()` nahi.

Common: session cookie + `X-CSRF-TOKEN`, `Accept: application/json`. `{id}` = user ka UUID.

| Endpoint | Kya karta hai | Permission | Reason |
|---|---|---|---|
| `POST /admin/clients/{id}/suspend` | Client suspend | `clients.update` | zaroori |
| `POST /admin/clients/{id}/activate` | Client dobara active | `clients.update` | nahi |
| `POST /admin/lawyers/{id}/suspend` | Lawyer suspend | `lawyers.update` | zaroori |
| `POST /admin/lawyers/{id}/activate` | Lawyer dobara active | `lawyers.update` | nahi |
| `POST /admin/lawyers/{id}/approve` | Lawyer verify | `lawyers.verify` | nahi |
| `POST /admin/lawyers/{id}/reject` | Lawyer ka verification reject | `lawyers.verify` | zaroori |

Ek URL se sirf usi type ka account milta hai: client ke URL par lawyer ka id dene par `404`, aur ulta bhi. Admin accounts in URLs se kabhi chhue nahi ja sakte.

### 5.1 Suspend - `POST /admin/clients/{id}/suspend`

Request:
```json
{ "reason": "Fraudulent documents" }
```

Response `200`:
```json
{
  "message": "Account suspended.",
  "data": { "status": "suspended", "verification_status": null }
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
  "data": { "status": "active", "verification_status": null }
}
```

Pehle se active account par `422`: `{ "message": "This account is already active." }`. Inactive ya suspended dono ko activate kar sakte hain.

### 5.3 Lawyer approve - `POST /admin/lawyers/{id}/approve`

Response `200`:
```json
{
  "message": "Lawyer approved.",
  "data": { "status": "active", "verification_status": "verified" }
}
```
`verified_at` set ho jaata hai. Pehle se verified par `422`: `{ "message": "This lawyer is already verified." }`. Rejected lawyer ko baad mein approve kiya ja sakta hai.

### 5.4 Lawyer reject - `POST /admin/lawyers/{id}/reject`

Request:
```json
{ "reason": "Bar number could not be verified" }
```

Response `200`:
```json
{
  "message": "Lawyer rejected.",
  "data": { "status": "active", "verification_status": "rejected" }
}
```
Verified lawyer ko reject karne par `verified_at` hat jaata hai. Pehle se rejected par `422`: `{ "message": "This lawyer is already rejected." }`.

Lawyer ko **suspend** karne se uska verification status nahi badalta (dono alag cheezein hain).

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
| `lawyer_approved` | Lawyer verified |
| `lawyer_rejected` | Lawyer ka verification reject |

---

## 7. Design ka kaam (front-end)

- Admin ka design `design/` folder ke HTML se Blade layout mein convert kiya gaya: `layouts/admin.blade.php` (sidebar + topbar + content), `layouts/auth.blade.php` (login).
- Login page Figma ke hisab se banaya (`public/assets/admin/login.css`), font **Inter**.
- Baaki admin pages ka text/spacing Figma ke hisab se bada kiya gaya (pehle sab bahut chhota tha). Yeh ek scale factor se hua hai kyunki baaki pages ka Figma nahi mila tha; koi page alag dikhe toh screenshot bhejo.
- Clients aur Lawyers ke pages se Cases / Subscription / Storage ke columns abhi hata diye gaye hain kyunki unki tables abhi nahi bani (nakli sankhya nahi dikhani).
