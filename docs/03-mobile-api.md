# 03 - Mobile API (Client + Lawyer)

Mobile app ke liye REST API. Register, OTP verify, login aur forgot password.

- **Base URL:** `{APP_URL}/api/v1` (XAMPP par jaise `http://localhost/case-hub/public/api/v1`, `artisan serve` par `http://127.0.0.1:8000/api/v1`)
- **Format:** JSON. Har request par header bhejo: `Accept: application/json`
- **POST body:** `Content-Type: application/json` (sirf lawyer registration `multipart/form-data` hai kyunki photo jaati hai)
- **Login ke baad ki APIs:** `Authorization: Bearer <token>`
- Saare tokens/IDs neeche ke examples mein dummy hain.

---

## 1. Flow (app mein screens ka order)

```
REGISTER (Client ya Lawyer)
   |  201: account bana, OTP SMS gaya (abhi token nahi)
   v
VERIFY YOUR ACCOUNT  (4 digit OTP)
   |  200: account verify + token mil gaya  -> app mein login ho gaya
   v
Home

LOGIN (Client/Lawyer tab)
   |-- 200 -> token
   |-- 403 mobile_not_verified -> OTP screen kholo (naya OTP apne aap chala gaya)
   |-- 403 account_inactive    -> "account suspend hai" dikhao
   `-- 401 invalid_credentials -> "galat email/mobile ya password"

FORGOT PASSWORD:  forgot-password -> forgot-password/verify -> reset-password -> LOGIN
```

---

## 2. Response ka format aur error codes

**Success:** `{ "message": "...", "data": { ... } }` (kuch endpoints mein sirf `data` ya sirf `message`).

**Validation error (`422`):** Laravel ka standard format:
```json
{
  "message": "The name field must be at least 2 characters. (and 7 more errors)",
  "errors": {
    "name": ["The name field must be at least 2 characters."],
    "email": ["The email field must be a valid email address."]
  }
}
```

**Domain errors:** ek `code` field hota hai jise app if/else mein use kare:

| HTTP | `code` | Matlab | App kya kare |
|---|---|---|---|
| 401 | `invalid_credentials` | Galat email/mobile, password, ya galat tab (client ki jagah lawyer tab) | Error dikhao |
| 401 | (no code) `Unauthenticated.` | Token nahi / galat / expire | Login screen par bhejo |
| 403 | `mobile_not_verified` | Registration poori nahi hui | OTP screen kholo |
| 403 | `account_inactive` | Account suspend / inactive | "Contact support" dikhao |
| 422 | `invalid_otp` | OTP galat / expire / bahut galat guesses | Dobara try ya Resend |
| 422 | `invalid_reset_token` | Password reset ka session expire | Forgot password dobara shuru karo |
| 429 | - | Bahut zyada requests | `Retry-After` header (seconds) ke baad try karo |

**Sabhi errors JSON mein aate hain**, `Accept` header na bhi ho tab bhi.

**Rate limits (approx):** login 5 galat/minute per account; register 5/minute; OTP bhejna 5/minute; OTP verify 10/minute. Limit paar hone par `429`.

---

## 3. User object

Har jagah user aise dikhta hai:

```json
{
  "id": "448dc022-5370-43e7-bc16-a60c8a77427e",
  "type": "client",
  "name": "Rahul Sharma",
  "email": "rahul@example.com",
  "mobile": "9876543210",
  "image_url": null,
  "mobile_verified": true,
  "status": "active"
}
```

Lawyer ke liye extra `lawyer` object bhi aata hai (client mein nahi):

```json
{
  "id": "afc82c9d-f4bf-4ece-9608-fe8aa3c904dc",
  "type": "lawyer",
  "name": "Adv. Sarah Jenkins",
  "email": "sarah@lawfirm.com",
  "mobile": "9123456780",
  "image_url": "http://localhost/case-hub/public/storage/profile-photos/7UjjjSs0rwRIuyfApAuvs7Zzhx8hONs6rz2yJS6l.jpg",
  "mobile_verified": true,
  "status": "active",
  "lawyer": {
    "location": "New Delhi, India",
    "years_of_experience": 8,
    "bio": "Corporate governance and labour disputes.",
    "verification_status": "pending",
    "practice_areas": [
      { "id": 1, "name": "Severance" },
      { "id": 3, "name": "Compliance" }
    ]
  }
}
```

| Field | Values |
|---|---|
| `type` | `client`, `lawyer` |
| `status` | `active`, `inactive`, `suspended` |
| `lawyer.verification_status` | `pending` (admin review baaki), `verified`, `rejected` |

---

## 4. Endpoints

| # | Method | URL | Auth | Screen |
|---|---|---|---|---|
| 1 | GET | `/practice-areas` | Nahi | Lawyer register (specialization chips) |
| 2 | POST | `/auth/register/client` | Nahi | Register (Client tab) |
| 3 | POST | `/auth/register/lawyer` | Nahi | Register (Lawyer tab) |
| 4 | POST | `/auth/otp/verify` | Nahi | Verify Your Account |
| 5 | POST | `/auth/otp/resend` | Nahi | Resend OTP |
| 6 | POST | `/auth/login` | Nahi | Login |
| 7 | GET | `/auth/me` | Bearer | Current user |
| 8 | POST | `/auth/logout` | Bearer | Is device se logout |
| 9 | POST | `/auth/logout-all` | Bearer | Saare devices se logout |
| 10 | POST | `/auth/forgot-password` | Nahi | Forgot password: OTP bhejo |
| 11 | POST | `/auth/forgot-password/verify` | Nahi | Forgot password: OTP verify |
| 12 | POST | `/auth/reset-password` | Nahi | Naya password |

---

### 4.1 `GET /practice-areas`

Lawyer registration ke chips ke liye list. Sirf active areas, naam ke hisab se sorted. IDs `practice_areas[]` mein bhejne hain.

Response `200`:
```json
{
  "data": [
    { "id": 5, "name": "Arbitration" },
    { "id": 3, "name": "Compliance" },
    { "id": 6, "name": "Contracts" },
    { "id": 4, "name": "Employee Rights" },
    { "id": 1, "name": "Severance" },
    { "id": 2, "name": "Wrongful Termination" }
  ]
}
```

---

### 4.2 `POST /auth/register/client`

Request:
```json
{
  "name": "Rahul Sharma",
  "email": "rahul@example.com",
  "mobile": "9876543210",
  "password": "Password@123",
  "password_confirmation": "Password@123",
  "terms_accepted": true
}
```

| Field | Rule |
|---|---|
| `name` | zaroori, 2 se 100 akshar |
| `email` | zaroori, valid email, max 191 (bade-chhote akshar same maane jaate hain) |
| `mobile` | zaroori, 10 digit Indian number (6-9 se shuru). `+91`, space, dash chalte hain (`+91 98765-43210`) |
| `password` | zaroori, kam se kam 8 akshar, upper + lower case + ek number |
| `password_confirmation` | `password` ke barabar |
| `terms_accepted` | zaroori aur `true` ("Terms & Conditions and Privacy Policy" checkbox). Sahmati ka time save hota hai |

Response `201`:
```json
{
  "message": "Account created. We have sent an OTP to your mobile number.",
  "data": {
    "user": {
      "id": "448dc022-5370-43e7-bc16-a60c8a77427e",
      "type": "client",
      "name": "Rahul Sharma",
      "email": "rahul@example.com",
      "mobile": "9876543210",
      "image_url": null,
      "mobile_verified": false,
      "status": "active"
    },
    "otp": {
      "sent": true,
      "length": 4,
      "resend_in": 59,
      "expires_in": 600
    }
  }
}
```

- **Token nahi milta.** Account tab tak login nahi kar sakta jab tak OTP verify na ho.
- `otp.resend_in`: kitne second baad "Resend OTP" chalega (screen ka 00:59 timer). `otp.expires_in`: OTP kitne second valid.
- Agar SMS bhejna fail ho jaye toh bhi account bana rehta hai, `otp.sent` `false` aata hai. Tab bhi OTP screen dikhao aur user "Resend OTP" dabaye.

Response `422` (galat data, sab galtiyan ek saath):
```json
{
  "message": "The name field must be at least 2 characters. (and 7 more errors)",
  "errors": {
    "name": ["The name field must be at least 2 characters."],
    "email": ["The email field must be a valid email address."],
    "mobile": ["Please enter a valid 10 digit mobile number."],
    "password": [
      "The password field confirmation does not match.",
      "The password field must be at least 8 characters.",
      "The password field must contain at least one uppercase and one lowercase letter.",
      "The password field must contain at least one number."
    ],
    "terms_accepted": ["You must agree to the Terms & Conditions and Privacy Policy."]
  }
}
```

Dusri baaton:
- **Pehle se registered** (verified) email/mobile: `422` mein `errors.email` = `"This email is already registered."` ya `errors.mobile` = `"This mobile number is already registered."`.
- Agar kisi ne kisi ka email/number **bina verify kiye** register kar diya ho, toh asli maalik ka naya registration us adhoore account ko replace kar deta hai (koi kisi ka number/email block nahi kar sakta).
- `type`, `status`, `mobile_verified_at` request se set nahi ho sakte, bhejne par ignore hote hain.

---

### 4.3 `POST /auth/register/lawyer`

`multipart/form-data`. Client ke saare fields + neeche wale.

| Field | Rule |
|---|---|
| `photo` | optional, JPG/PNG, max 5 MB |
| `location` | zaroori, max 191 ("New Delhi, India") |
| `years_of_experience` | zaroori, number 0 se 70 |
| `practice_areas[]` | zaroori, 1 se 10 IDs (`GET /practice-areas` se), duplicate nahi |
| `bio` | optional, max 500 akshar |

curl example:
```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/register/lawyer \
  -H "Accept: application/json" \
  -F name="Adv. Sarah Jenkins" \
  -F email="sarah@lawfirm.com" \
  -F mobile="9123456780" \
  -F password="Password@123" \
  -F password_confirmation="Password@123" \
  -F terms_accepted=1 \
  -F location="New Delhi, India" \
  -F years_of_experience=8 \
  -F "practice_areas[]=1" -F "practice_areas[]=3" \
  -F bio="Corporate governance and labour disputes." \
  -F photo=@headshot.jpg
```

Response `201`:
```json
{
  "message": "Account created. We have sent an OTP to your mobile number.",
  "data": {
    "user": {
      "id": "afc82c9d-f4bf-4ece-9608-fe8aa3c904dc",
      "type": "lawyer",
      "name": "Adv. Sarah Jenkins",
      "email": "sarah@lawfirm.com",
      "mobile": "9123456780",
      "image_url": "http://localhost/case-hub/public/storage/profile-photos/7UjjjSs0rwRIuyfApAuvs7Zzhx8hONs6rz2yJS6l.jpg",
      "mobile_verified": false,
      "status": "active",
      "lawyer": {
        "location": "New Delhi, India",
        "years_of_experience": 8,
        "bio": "Corporate governance and labour disputes.",
        "verification_status": "pending",
        "practice_areas": [
          { "id": 1, "name": "Severance" },
          { "id": 3, "name": "Compliance" }
        ]
      }
    },
    "otp": { "sent": true, "length": 4, "resend_in": 59, "expires_in": 600 }
  }
}
```

- Lawyer hamesha `verification_status: "pending"` se shuru hota hai. Admin approve karta hai. Lawyer khud verify nahi kar sakta (request mein `verification_status` bhejne par ignore).
- Photo galat ho toh `422`: `errors.photo` = `"The profile photo must be a JPG or PNG image."` ya `"The profile photo must not be larger than 5MB."`.
- Practice area galat ho toh `errors.practice_areas.0` (galat index ke saath); khaali ho toh `"Please select at least one practice area."`.

---

### 4.4 `POST /auth/otp/verify`

"Verify Your Account" screen ka Verify button.

Request:
```json
{
  "mobile": "9876543210",
  "otp": "4821",
  "device_name": "Pixel 8"
}
```

| Field | Rule |
|---|---|
| `mobile` | registration wala number (koi bhi format) |
| `otp` | exactly 4 digit |
| `device_name` | optional (default `mobile`), token ko pehchaanne ke liye |

Response `200` (account verify hua **aur user login bhi ho gaya**):
```json
{
  "message": "Account verified.",
  "data": {
    "user": {
      "id": "448dc022-5370-43e7-bc16-a60c8a77427e",
      "type": "client",
      "name": "Rahul Sharma",
      "email": "rahul@example.com",
      "mobile": "9876543210",
      "image_url": null,
      "mobile_verified": true,
      "status": "active"
    },
    "token": "1|f14YhYkh0YG5xtG66nbUV6VRdYEtrmJMA8lXMOpQ26910579",
    "token_type": "Bearer",
    "expires_at": "2026-10-03T13:31:35+00:00"
  }
}
```
`token` ko app mein safe (secure storage) rakho aur aage `Authorization: Bearer <token>` mein bhejo.

Response `422` (galat / expire / pehle hi use ho chuka / number registered nahi, sab ka jawab **ek jaisa**):
```json
{
  "message": "Invalid or expired OTP. Please try again or request a new one.",
  "code": "invalid_otp"
}
```
Response `422` (OTP 4 digit nahi): `errors.otp` = `["Please enter the 4 digit OTP."]`.

Rules: OTP 10 minute valid, sirf ek baar chalta hai, **5 galat guesses ke baad kharab** (tab sahi OTP bhi nahi chalega, "Resend OTP" karo).

---

### 4.5 `POST /auth/otp/resend`

Request:
```json
{ "mobile": "9876543210" }
```

Response `200` (**hamesha yahi**, number registered ho ya na ho, taaki koi pata na laga sake):
```json
{
  "message": "If a pending account exists for this number, an OTP has been sent.",
  "data": { "resend_in": 59 }
}
```
59 second ke andar dobara call karne par naya SMS nahi jaata (timer wahi rehta hai). Naya OTP purane ko replace karta hai.

---

### 4.6 `POST /auth/login`

Request:
```json
{
  "type": "client",
  "identifier": "rahul@example.com",
  "password": "Password@123",
  "device_name": "iPhone 15",
  "remember": true
}
```

| Field | Rule |
|---|---|
| `type` | zaroori: `client` ya `lawyer` (login screen ka toggle) |
| `identifier` | email **ya** mobile (`9876543210`, `+91 98765 43210`) |
| `password` | zaroori |
| `device_name` | optional (default `mobile`) |
| `remember` | optional `true/false`. Token 7 din ki jagah 30 din chalta hai |

Response `200`:
```json
{
  "message": "Login successful.",
  "data": {
    "user": {
      "id": "448dc022-5370-43e7-bc16-a60c8a77427e",
      "type": "client",
      "name": "Rahul Sharma",
      "email": "rahul@example.com",
      "mobile": "9876543210",
      "image_url": null,
      "mobile_verified": true,
      "status": "active"
    },
    "token": "2|HO12HJDqjXkyQZbHmC7yC3giuyT6dUGT4ndJdOPJ77d4ea9f",
    "token_type": "Bearer",
    "expires_at": "2026-10-26T13:31:35+00:00"
  }
}
```

Response `401` (galat email/mobile, galat password, **ya galat tab**: lawyer ne Client tab par login kiya. Teeno ka jawab bilkul same):
```json
{
  "message": "These credentials do not match our records.",
  "code": "invalid_credentials"
}
```

Response `403` (password sahi hai par mobile verify nahi hua; server ne apne aap naya OTP bhej diya hai, app OTP screen kholo):
```json
{
  "message": "Please verify your mobile number to continue.",
  "code": "mobile_not_verified",
  "data": { "mobile": "9876543210", "resend_in": 59 }
}
```

Response `403` (account suspend / inactive):
```json
{
  "message": "Your account is not active. Please contact support.",
  "code": "account_inactive"
}
```

Response `422` (validation, jaise `type` galat):
```json
{
  "message": "The selected type is invalid. (and 2 more errors)",
  "errors": {
    "type": ["The selected type is invalid."],
    "identifier": ["The identifier field is required."],
    "password": ["The password field is required."]
  }
}
```

Response `429` (5 galat attempts ke baad; sahi password bhi 60 second ke liye block; header `Retry-After: 60`):
```json
{ "message": "Too many login attempts. Please try again in 60 seconds." }
```

Ek user ke max **10 devices** logged in reh sakte hain, 11ve login par sabse purana device logout ho jaata hai.

---

### 4.7 `GET /auth/me`

Header: `Authorization: Bearer <token>`

Response `200`:
```json
{
  "data": {
    "user": {
      "id": "448dc022-5370-43e7-bc16-a60c8a77427e",
      "type": "client",
      "name": "Rahul Sharma",
      "email": "rahul@example.com",
      "mobile": "9876543210",
      "image_url": null,
      "mobile_verified": true,
      "status": "active"
    }
  }
}
```

Response `401` (token nahi / galat / expire): `{ "message": "Unauthenticated." }`

Response `403` (token sahi hai par account ab suspend ho chuka hai; login ke baad bhi har request par check hota hai):
```json
{
  "message": "Your account is not active. Please contact support.",
  "code": "account_inactive"
}
```

Login ke baad ki **har** API par yeh `401`/`403` aa sakte hain, unhe app mein ek jagah handle karo.

---

### 4.8 `POST /auth/logout`

Header: `Authorization: Bearer <token>`. Sirf **isi device** ka token hatata hai.

Response `200`:
```json
{ "message": "Logged out." }
```

### 4.9 `POST /auth/logout-all`

Saare devices ke tokens hata deta hai.

Response `200`:
```json
{ "message": "Logged out from all devices." }
```

---

### 4.10 `POST /auth/forgot-password`

Login screen ka "Forgot Password?". Email **ya** mobile de sakte ho, par OTP hamesha **us account ke registered mobile** par jaata hai.

Request:
```json
{ "identifier": "rahul@example.com" }
```

Response `200` (**hamesha yahi**, account ho ya na ho; suspended/unverified account ko OTP nahi jaata par jawab wahi rehta hai):
```json
{
  "message": "If the account exists, an OTP has been sent to its registered mobile number.",
  "data": { "resend_in": 59 }
}
```

### 4.11 `POST /auth/forgot-password/verify`

Request:
```json
{ "identifier": "rahul@example.com", "otp": "7305" }
```

Response `200`:
```json
{
  "data": {
    "reset_token": "se8cIZ7UTiAzmHT3yYl43PylMDvN1CBSJNv1P2wTpAddJOdcmWOG2lVWBeGEs2UL"
  }
}
```
`reset_token` sirf 15 minute valid hai aur ek baar chalta hai. Ise app ki memory mein rakho (URL / storage mein nahi).

Response `422`:
```json
{
  "message": "Invalid or expired OTP. Please try again or request a new one.",
  "code": "invalid_otp"
}
```

### 4.12 `POST /auth/reset-password`

Request:
```json
{
  "identifier": "rahul@example.com",
  "reset_token": "se8cIZ7UTiAzmHT3yYl43PylMDvN1CBSJNv1P2wTpAddJOdcmWOG2lVWBeGEs2UL",
  "password": "NewPass@456",
  "password_confirmation": "NewPass@456"
}
```

Response `200`:
```json
{ "message": "Password updated. Please login with your new password." }
```
Password badalte hi user **saare devices se logout** ho jaata hai (purane tokens hat jaate hain). Ab `POST /auth/login` se login karo.

Response `422` (kamzor password, token sahi rehta hai, phir se try kar sakte ho):
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
{
  "message": "This reset session has expired. Please start again.",
  "code": "invalid_reset_token"
}
```

---

## 5. Kuch aur zaroori baatein

- **OTP SMS:** abhi SMS provider nahi laga. Development mein OTP `storage/logs/laravel.log` mein is tarah dikhta hai: `[sms:log] to 9876543210: CaseHub: 4821 is your verification code. ...`. Production mein log gateway error deta hai jab tak asli provider na jude.
- **Photo URL:** `image_url` ek poora URL hota hai (`APP_URL` par based). Photo dikhne ke liye `php artisan storage:link` ek baar chalana zaroori hai.
- **Admin ka asar:** admin panel se client/lawyer suspend hote hi uske saare tokens delete ho jaate hain, aur woh dobara login nahi kar sakta (`403 account_inactive`) jab tak admin activate na kare. Lawyer ka `verification_status` admin badalta hai; app ko bas `GET /auth/me` se naya status milta hai.
- **`APP_DEBUG=false` production mein zaroori:** `true` hone par error responses mein file path aur stack trace bhi aa jaate hain. Upar ke examples `false` waali (safe) shape dikhate hain.
- **Token kitne der:** normal 7 din, `remember: true` par 30 din (`.env` se badal sakte ho).

## 6. Jaldi test karne ke liye (curl)

```bash
# 1) Register
curl -X POST http://127.0.0.1:8000/api/v1/auth/register/client \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Rahul Sharma","email":"rahul@example.com","mobile":"9876543210","password":"Password@123","password_confirmation":"Password@123","terms_accepted":true}'

# 2) storage/logs/laravel.log mein OTP dekho, phir verify
curl -X POST http://127.0.0.1:8000/api/v1/auth/otp/verify \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"mobile":"9876543210","otp":"4821"}'

# 3) Token se profile
curl http://127.0.0.1:8000/api/v1/auth/me \
  -H "Accept: application/json" -H "Authorization: Bearer <token>"
```
