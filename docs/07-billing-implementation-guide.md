# 07 - Mobile App: Storage Plans Implementation Guide

**Yeh guide app (Client ke liye) banane wale frontend developer ke liye hai** - kaunse screens banane hain, kis order mein APIs call karni hain, har state mein UI kaisa dikhna chahiye, aur kya test karna hai launch se pehle. Exact request/response JSON ke liye `06-billing-and-storage.md` dekho - yahan sirf wahi fields hain jo UI decision ke liye zaroori hain.

Yeh feature **sirf Client app** mein hai - Lawyer app mein koi plan/billing screen nahi chahiye.

---

## 1. Kaunse screens banane hain

| # | Screen | Kab khulta hai |
|---|---|---|
| 1 | **Plans / Pricing** | Client ke paas koi plan nahi hai, ya woh naya plan dekhna chahta hai (upgrade/downgrade ke liye bhi yehi screen reuse hoga) |
| 2 | **My Subscription** | Settings/Profile se - current plan, storage usage bar, Upgrade/Downgrade/Cancel buttons |
| 3 | **Downgrade Warning Dialog** | Downgrade confirm karne se pehle - mandatory, skip nahi kar sakte |
| 4 | **Cancel Confirmation** | Cancel karte waqt - 7 din ka grace period clearly dikhana hai |
| 5 | **Account Restricted** | App-wide "locked out" screen - jab bhi koi bhi API `403 subscription_restricted` de |
| 6 | **Storage Full Prompt** | Jab upload ya case-submit `422 storage_full` de - "Upgrade karo" CTA ke saath |
| 7 | **Case Documents** | Ek case ke andar evidence files ki list + upload button |

---

## 2. `GET /subscription` - sabse pehla call

App open hote hi (ya Client login hote hi), yeh call karo aur result ko app-wide state mein rakho (Redux/Provider/whatever) - kai screens isi par depend karte hain.

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

`data: null` ka matlab: client ne kabhi subscribe nahi kiya - seedha **Plans screen** dikhao (koi "My Subscription" screen nahi hai abhi dikhane ke liye).

### State → UI decision table

| `status` | `storage.is_full` | My Subscription screen | Upload / "Submit Case" button | Global banner |
|---|---|---|---|---|
| `active` | `false` | Normal, sab buttons enabled | Enabled | Koi nahi |
| `active` | `true` | Normal, "Storage full" badge dikhao | **Disabled**, tap karne par Storage Full Prompt khulo | "Your storage is full - upgrade to upload more" (dismissible) |
| `cancelled` | - | "Cancelled - access until {grace_ends_at}" banner, sirf "Resubscribe" button | Disabled | "You have until {grace_ends_at} to download your data" (persistent, dismiss nahi hota) |
| `restricted` | - | Sirf Plans screen tak access, baaki app locked | Disabled | Poori app "Account Restricted" screen dikhao (section 5) |
| `null` (no data) | - | Dikhao hi mat - Plans screen seedha | Disabled | "Subscribe to a plan to get started" |

---

## 3. Plans screen - subscribe / upgrade / downgrade

`GET /plans` se list lao (dekho `03-mobile-api.md` section 4.14). Current plan ko highlight karo agar `GET /subscription` se match karta ho.

**Button logic per plan card:**
- Agar yeh client ka current plan hai → "Current Plan" (disabled badge, button nahi)
- Agar isse bada hai current se → "Upgrade" button → `POST /subscription/upgrade { "plan_id" }`
- Agar isse chhota hai current se → "Downgrade" button → pehle Warning Dialog (section 4), phir `POST /subscription/downgrade { "plan_id", "confirmed": true }`
- Agar client ka koi plan hi nahi hai → sirf "Subscribe" button → `POST /subscription/subscribe { "plan_id" }`

**Teeno call ka success response same shape hai** (poora updated `data` object, `GET /subscription` jaisa) - update kar do apna app-wide state isi se, dobara `GET /subscription` call karne ki zaroorat nahi.

**Error handling (teeno par):**
| Response | UI |
|---|---|
| `422` with `message` (card declined, galat plan direction) | Simple error toast/dialog with `message` - yehi text seedha dikhao, backend ne already user-friendly bana ke diya hai |
| `403` (lawyer ka token - app mein yeh case aana hi nahi chahiye agar role-check sahi hai) | Generic error |

---

## 4. Downgrade Warning Dialog - **mandatory**

Backend `confirmed: true` ke bina downgrade reject kar dega (`422`, validation error on `confirmed`). Iska matlab: **yeh dialog skip nahi kar sakte**, chahte bhi toh backend bypass nahi hoga.

Dialog mein kya dikhana hai (copy suggestion):
> "Downgrading to {plan name} ({plan storage}) will make your oldest files inaccessible if they don't fit the new limit. This cannot be undone. Continue?"
> [Cancel]  [Yes, Downgrade]

"Yes, Downgrade" tap karne par hi `confirmed: true` bhejo.

---

## 5. Cancel flow

`POST /subscription/cancel` (body nahi chahiye). Response mein `grace_ends_at` milega.

Confirmation dialog se pehle:
> "Cancelling stops auto-renewal. You'll have **7 days** (until {grace_ends_at}) to download your data before your account is restricted. Continue?"

Cancel hone ke baad: **turant** "My Subscription" screen update karo (cancelled banner), **aur** ek persistent (dismiss na ho sakne wala, ya baar-baar dikhne wala) banner poori app mein lagao jab tak grace period chale - "X days left to download your data".

Mobile OS-level push bhi aata hai jab cancel hota hai (in-app notification bell mein bhi, dekho `05-account-and-notifications.md`), lekin UI ko sirf notification par depend nahi karna chahiye - `GET /subscription` ka `grace_ends_at` hi source of truth hai.

---

## 6. "Account Restricted" screen - app-wide gate

Yeh sabse important integration piece hai: **kisi bhi API call** (cases, documents, notifications, device-tokens) par agar response `403` ho aur body mein `"code": "subscription_restricted"` ho, toh turant poore app ko Restricted screen par redirect kar do - chahe user kisi bhi screen par ho.

Best tareeka: apne HTTP client (Axios interceptor / Dio interceptor / jo bhi use kar rahe ho) mein ek **global response interceptor** lagao:

```
on response error:
  if (response.status == 403 && response.body.code == "subscription_restricted"):
    navigate to "Account Restricted" screen (clear back-stack)
    return  // isi jagah handle ho gaya, individual screen ko dobara handle nahi karna
```

Isse har screen mein alag se yeh check likhne ki zaroorat nahi padegi.

**Restricted screen par kya dikhana hai:**
- "Your account has been restricted because your subscription was cancelled and the grace period ended. Your data is not accessible until you subscribe again."
- Sirf ek button: "View Plans" → Plans screen (jo hamesha accessible hai, chahe restricted ho)
- Bottom nav / drawer bhi hide kar do is screen par - sirf Plans aur Profile tak jaane ka raasta ho (dekho `06-billing-and-storage.md` state table - yehi do cheezein backend bhi allow karta hai)

---

## 7. "Storage Full" prompt

Jab `POST /cases` (naya case) ya `POST /cases/{case}/documents` (upload) `422` de with `"code": "storage_full"`:

```json
{ "message": "You do not have enough storage for this file. Upgrade your plan to continue.", "code": "storage_full" }
```

Dialog:
> "Your storage is full. Upgrade your plan to upload more files or create new cases."
> [Cancel]  [Upgrade Plan] → Plans screen

**Proactive check bhi karo** (sirf error aane ka wait mat karo): "Submit Case" aur "Upload" buttons ko `GET /subscription` ke `storage.is_full` (ya `status !== "active"` jab "cancelled"/"restricted" ho) ke hisab se pehle se hi disable/grey-out kar do, tap karte hi ek chhota tooltip/toast dikha do - behtar UX hai error ka wait karne se.

---

## 8. Case Documents screen

Ek case khulte hi:

1. `GET /cases/{case}/documents` call karo - list milegi, har document mein `accessible: true/false` flag hai.
2. `accessible: false` wali files ko **grey out / strikethrough** karo, badge lagao "No longer accessible" - delete mat karo list se, user ko pata chalna chahiye ki yeh file kabhi thi (downgrade ki wajah se hide hui hogi).
3. Upload button: file picker kholo (PDF/Word/JPG/PNG, max 20MB - isi limit ko client-side bhi validate kar lo, taaki upload shuru hone se pehle hi reject ho jaaye bade file ke liye).
4. Upload `POST /cases/{case}/documents` (`multipart/form-data`, field `file`). Success par list mein naya item add karo, ya poori list refresh kar do.
5. Download: `GET /cases/{case}/documents/{document}/download` - **Bearer token ke saath call karo**, yeh koi public link nahi hai. Response file stream hai - apne platform ke file-save/open mechanism se handle karo (yeh signed URL nahi hai jo browser mein seedha khul jaaye).

---

## 9. Integration order (suggested build sequence)

1. `GET /subscription` call + app-wide state - sabse pehle banao, sab iske upar depend karta hai.
2. Plans screen + Subscribe flow (naye client ke liye zaroori hai, baaki sab iske baad hi test ho sakta hai).
3. My Subscription screen (dikhane ke liye - storage bar, status).
4. Global 403 `subscription_restricted` interceptor + Restricted screen.
5. Upgrade / Downgrade (downgrade warning dialog ke saath) / Cancel.
6. Case Documents screen (upload + list + download) + Storage Full prompt.
7. Proactive button-disabling (polish pass - sab kaam karne ke baad).

---

## 10. QA checklist (launch se pehle test karo)

- [ ] Naya client, koi plan nahi → seedha Plans screen, baaki app locked (403 aata hai cases/documents par).
- [ ] Subscribe karo → My Subscription screen turant update hota hai, app-wide lock hat jaata hai.
- [ ] Storage full hone tak upload karo → next upload `422 storage_full` deta hai → prompt dikhta hai → upgrade karne ke baad upload chal jaata hai.
- [ ] Upgrade karo → naya plan turant reflect hota hai, **purana data accessible rehta hai**.
- [ ] Downgrade karo (bina warning dialog confirm kiye - skip karne ki koshish karo) → backend `confirmed` na bhejne par `422` deta hai, confirm karna hi padta hai.
- [ ] Downgrade confirm karo, jab purani files naye limit se zyada hon → sabse purani files `accessible: false` ho jaati hain list mein.
- [ ] Cancel karo → grace banner dikhta hai, cases/documents **abhi bhi accessible** hain (read-only - naya upload disabled).
- [ ] Grace period khatam hone ke baad (backend test env mein `grace_ends_at` ko past date kar ke `subscriptions:expire-grace-periods` chalao) → agli kisi bhi API call par turant Restricted screen khulti hai, chahe user kahin bhi ho.
- [ ] Restricted state mein Plans screen aur Profile dono accessible rehte hain, baaki sab block.
- [ ] Lawyer app se koi bhi `/subscription/*` ya `/plans` call mat karo - lawyer ke liye yeh UI hi nahi honi chahiye.
