# 05 - Account & Notifications (Client + Lawyer)

Self-service account features. Password change, photo update aur notifications **Client aur Lawyer dono ke liye same endpoint** hain (jo bhi signed-in `User` ho, wahi call kar sakta hai). Lawyer practice profile (location/experience/specialization/bio) sirf **Lawyer** ke liye hai - client ke paas yeh fields hain hi nahi.

Sab endpoints `auth:sanctum` + `api.active` ke peeche hain (login ke baad). Header sabme chahiye: `Authorization: Bearer <token>`. Response format aur error codes ka general rule `03-mobile-api.md` section 2 mein hai.

---

## 1. Password change (logged in)

`PUT /api/v1/auth/password`

Forgot-password wale OTP flow se alag hai - yeh login ke **baad** apna password badalne ke liye hai, aur current password pata hona zaroori hai.

Request:
```json
{
  "current_password": "OldPass@123",
  "password": "NewPass@456",
  "password_confirmation": "NewPass@456"
}
```

Response `200`:
```json
{ "message": "Password updated successfully." }
```

**Is device ka token zinda rehta hai** (app logout nahi hota), lekin **baaki saare devices ke tokens turant delete** ho jaate hain - unhe dobara login karna padega. Yeh forgot-password reset jaisa hi security rule hai, bas current device ke liye kam disruptive.

Response `422` (current password galat):
```json
{
  "message": "The current password field is invalid.",
  "errors": { "current_password": ["The current password field is invalid."] }
}
```

Response `422` (naya password kamzor - kam se kam 8 characters, upper+lowercase, number):
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

Response `422` (confirmation match nahi karta): `errors.password` mein "The password field confirmation does not match."

---

## 2. Profile photo update

`POST /api/v1/profile/photo`

Body: `multipart/form-data`.

| Field | Detail |
|---|---|
| `photo` | Required. JPG ya PNG, max 5MB (lawyer-registration ke photo wala hi rule) |

Response `200`:
```json
{
  "message": "Profile photo updated.",
  "data": { "image_url": "http://127.0.0.1:8000/storage/profile-photos/abc123.jpg" }
}
```

- Pehli baar set karo ya purana replace karo - dono isi endpoint se hote hain.
- Purana photo (agar tha) disk se **turant delete** ho jaata hai jab naya upload ho jaaye.
- `image_url` wahi field hai jo `GET /auth/me` aur login response ke `user` object mein bhi aata hai - isliye update ke baad local UI turant naya `image_url` use kar sakta hai, dobara `/auth/me` call karne ki zaroorat nahi.

Response `422` (missing / galat type / size se bada):
```json
{
  "message": "The photo field is required.",
  "errors": { "photo": ["The photo field is required."] }
}
```
```json
{
  "message": "The profile photo must be a JPG or PNG image.",
  "errors": { "photo": ["The profile photo must be a JPG or PNG image."] }
}
```
```json
{
  "message": "The profile photo must not be larger than 5MB.",
  "errors": { "photo": ["The profile photo must not be larger than 5MB."] }
}
```

---

## 3. Lawyer practice profile - get aur update (sirf Lawyer)

Registration ke time diye gaye location / years of experience / practice areas (specialization) / bio - yahan se dekho aur badlo. **Client ke liye nahi hai** (`403` dono endpoints par).

### 3.1 `GET /api/v1/profile/lawyer` - Dekho

Edit screen kholte hi current values se form pre-fill karne ke liye.

Response `200`:
```json
{
  "data": {
    "user": {
      "id": "448dc022-5370-43e7-bc16-a60c8a77427e",
      "type": "lawyer",
      "name": "Adv. Sarah Jenkins",
      "email": "sarah@lawfirm.com",
      "mobile": "9876543210",
      "image_url": null,
      "email_verified": true,
      "status": "active",
      "lawyer": {
        "location": "Pune, India",
        "years_of_experience": 5,
        "bio": "Family law.",
        "verification_status": "verified",
        "practice_areas": [
          { "id": 2, "name": "Severance" }
        ]
      }
    }
  }
}
```

Response `403` (client ka token): `{ "message": "Only lawyers have a practice profile." }`

> Note: yehi data `GET /auth/me` se bhi milta hai (`user.lawyer` key). Yeh endpoint sirf alag se dedicated hai taaki edit-profile screen `PUT` wale isi path se GET bhi kar sake - dono use karne mein koi fark nahi hai, jo tumhare frontend code ke liye saaf ho wo use karo.

### 3.2 `PUT /api/v1/profile/lawyer` - Update karo

Request (saare fields `bio` chhodke required - jaisa registration mein tha):
```json
{
  "location": "Mumbai, India",
  "years_of_experience": 12,
  "practice_areas": [3, 7],
  "bio": "Specializing in corporate governance and compliance."
}
```

| Field | Detail |
|---|---|
| `location` | Required, text |
| `years_of_experience` | Required, 0-70 ke beech |
| `practice_areas` | Required, kam se kam 1, max 10 - `GET /practice-areas` se mile active id's mein se |
| `bio` | Optional (`null` bhej sakte ho hatane ke liye) |

Har call **pura set replace karta hai** (jaisa form resubmit ho raha ho) - purane `practice_areas` hat jaate hain aur naye lag jaate hain, partial/patch update nahi hai.

Response `200` - poora updated `user` object (wahi shape jo `GET /auth/me` deta hai):
```json
{
  "message": "Profile updated.",
  "data": {
    "user": {
      "id": "448dc022-5370-43e7-bc16-a60c8a77427e",
      "type": "lawyer",
      "name": "Adv. Sarah Jenkins",
      "email": "sarah@lawfirm.com",
      "mobile": "9876543210",
      "image_url": null,
      "email_verified": true,
      "status": "active",
      "lawyer": {
        "location": "Mumbai, India",
        "years_of_experience": 12,
        "bio": "Specializing in corporate governance and compliance.",
        "verification_status": "verified",
        "practice_areas": [
          { "id": 3, "name": "Compliance" },
          { "id": 7, "name": "Litigation" }
        ]
      }
    }
  }
}
```

**`verification_status` is call se kabhi nahi badalta** - profile update karne se admin ki verification wapas reset nahi hoti.

Response `403` (client ka token):
```json
{ "message": "Only lawyers have a practice profile to update." }
```

Response `422` (koi practice area nahi chuna, ya inactive/galat id di):
```json
{
  "message": "Please select at least one practice area.",
  "errors": { "practice_areas": ["Please select at least one practice area."] }
}
```

---

## 4. Notifications

In-app notification feed - har account (client ya lawyer) ki apni list hai, kisi aur ki kabhi nahi dikhti. **Abhi sirf ek event isme likhta hai: naya case-chat message** (`docs/04-realtime-chat.md` dekho) - jab doosri party message bhejti hai aur tum WebSocket se connected nahi ho, yeh list hi batati hai ki kuch naya aaya.

### 4.1 `GET /notifications` - List

Response `200`:
```json
{
  "data": [
    {
      "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
      "title": "Adv. Rao",
      "body": "Please share the documents",
      "data": { "case_id": 14, "message_id": 102 },
      "read": false,
      "created_at": "2026-10-01T09:37:33+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "unread_count": 1
  }
}
```

- 20 per page, naye pehle (`?page=2` se aage).
- `meta.unread_count` **poore list** ka hai, sirf current page ka nahi - bell/badge dikhane ke liye yehi number use karo, response ke `data` array ki length nahi.
- `title`/`body` seedha dikhane layak text hai. `data.case_id` se tum seedha us case/chat screen par navigate kar sakte ho jab notification tap ho.

### 4.2 `POST /notifications/{id}/read` - Ek ko read mark karo

Response `200`: `{ "message": "Marked as read." }`

Doosre ke account ki notification `id` doge toh `404` - sirf apni hi dikhti/badalti hai.

### 4.3 `POST /notifications/read-all` - Sab ek saath

Body nahi chahiye.

Response `200`: `{ "message": "All notifications marked as read." }`

---

## 5. Common errors (sab features par)

| Status | Kab | Response |
|---|---|---|
| `401` | Token nahi hai / galat / expire | `{ "message": "Unauthenticated." }` |
| `403` | Account ab suspend/inactive ho chuka hai (`api.active` middleware), ya client ne lawyer-only endpoint use kiya | `{ "message": "Your account is not active. Please contact support.", "code": "account_inactive" }` |
| `422` | Validation (upar har feature ke examples dekho) | - |

---

## 6. Kya jaan-bujh kar chhoda gaya (abhi)

- **Push notification settings / preferences** - koi "mute notifications" ya per-type toggle nahi hai, sab events (abhi sirf chat) hamesha in-app + push (FCM) dono jaate hain.
- **Notification delete** - sirf read/unread hai, kisi notification ko list se hata nahi sakte.
- **Naam, email, mobile** update karne ka koi endpoint abhi nahi hai - na app se, na admin panel se (admin sirf dekh/suspend-activate kar sakta hai, edit nahi). Photo, password aur (lawyer ke liye) practice profile hi self-service hai abhi.
- **"2FA" / extra verification** password change par nahi hai - sirf current password check hota hai.
