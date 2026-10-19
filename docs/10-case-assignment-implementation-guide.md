# 10 - Mobile App: Case-to-Lawyer Assignment Implementation Guide

**Yeh guide Client aur Lawyer, dono app banane wale frontend developers ke liye hai** - kaunse screens banane hain, kis order mein APIs call karni hain, har state mein UI kaisa dikhna chahiye, aur kya test karna hai launch se pehle. Exact request/response JSON ke liye `09-case-assignment-api.md` dekho - yahan sirf wahi fields hain jo UI decision ke liye zaroori hain.

Case draft/submit flow (`08-case-intake-api.md`) aur chat (`04-realtime-chat.md`) pehle se document hain - yeh guide un dono ke **beech** mein aata hai: submit hone ke baad lawyer assign karna, aur lawyer side se accept/reject karna.

> **Case ID dikhane ka rule:** har jagah (list cards, detail screen, headers) `case_number` field use karo (jaise `CASE-1042`) - raw `id` kabhi UI mein mat dikhana, woh sirf API calls ke URL mein jaata hai.

---

## 1. Kaunse screens banane hain

| # | App | Screen | Kab khulta hai |
|---|---|---|---|
| 1 | Client | **Lawyers tab** (general directory, bottom nav) | App ke bottom nav se, kisi bhi waqt - sab verified lawyers browse/search karne ke liye, koi case select kiye bina |
| 2 | Client | **Assign a Lawyer** (case-scoped list) | Case submit hone ke baad, case detail se "Assign a Lawyer" button tap karne par (sirf jab tak `advocate_id` set nahi hua) |
| 3 | Client | **Case Detail - awaiting / assigned states** | Har case detail screen mein hi - naya state handling, alag screen nahi |
| 4 | Lawyer | **My Cases - New / In Progress tabs** | Lawyer app ka home/cases tab |
| 5 | Lawyer | **Case Detail (New)** | "New" tab se case tap karne par - Accept / Decline buttons |
| 6 | Lawyer | **Accept / Decline confirmation** | Accept ya Decline tap karne par |

---

## 2. Client side - "Assign a Lawyer" flow

### 2.0 Lawyers tab (general directory - koi case involved nahi)

Bottom nav ka "Lawyers" tab `GET /lawyers` call karta hai (`09-case-assignment-api.md` section 2) - **isme koi case ID nahi lagta**, bas sab verified lawyers aate hain. Search box top par ho toh `?q=` query param use karo; specialization filter chips/dropdown ho toh `?practice_area_id=`.

Yeh screen sirf **browse/discover** ke liye hai - lekin yahan se seedha "assign" mat karne do, kyunki assign karne ke liye ek case chahiye hota hai. Agar iss screen se bhi assign karne ka button chahiye, pehle "Select a case" picker dikhao (client ke pending cases mein se), phir section 2.2 wala hi `POST /cases/{id}/assignment-requests` call karo.

**"View Details" tap karne par** `GET /lawyers/{id}` call karo (`09-case-assignment-api.md` section 2.1) - full profile screen: bio, experience, specialization chips, `completed_cases` count. **Koi rating/reviews ya firm-name mat dikhana** - field hi nahi hai (section 7 dekho). `404` aaye toh "This lawyer is no longer available" dikha ke list par wapas bhej do (suspended/unverified ho sakta hai).

**`rating` / "match %" mat dikhana** - yeh field backend se aata hi nahi (mockup mein tha, API mein nahi).

### 2.1 Case detail screen - button kab dikhana hai

`GET /cases/{id}` call karo, phir `status` + `assignment_request` dekh kar decide karo:

| `case.status` | `assignment_request` | Case detail screen par kya dikhao |
|---|---|---|
| `pending` | `null` | **"Assign a Lawyer"** button - tap karne par list screen khulti hai |
| `pending` | `{ status: "pending" }` | "Awaiting admin review" badge, lawyer ka naam dikhao, button disabled/hidden - dusra pick nahi kar sakte jab tak decide na ho |
| `pending` | `{ status: "rejected", reason }` | "Request rejected" banner with `reason`, **"Pick another Lawyer"** button phir se list screen khol de |
| `assigned` | `{ status: "approved" }` | "Waiting for {lawyer.name} to accept" - koi button nahi, bas status |
| `accepted` | `{ status: "approved" }` | Normal case screen + **Chat** tab/button khul jaata hai (`04-realtime-chat.md`) |
| `rejected` | `{ status: "approved" }` | Lawyer ne khud decline kar diya - "This lawyer declined your case" banner. **Koi dobara-assign API nahi hai** is state ke liye abhi (section 7 dekho) |

### 2.2 Lawyer list screen

1. `GET /cases/{id}/lawyers` call karo - card per lawyer: naam, photo (`image_url`), specialization chips (`lawyer.practice_areas`), experience (`lawyer.years_of_experience`), "Verified" badge (hamesha true hoga, list khud sirf verified lawyers deti hai).
2. **Koi rating ya "match %" mat dikhana** - woh field backend se aata hi nahi (mockup mein tha, API mein nahi - section 7 dekho).
3. Khali list: "No lawyers available for this practice area yet" empty state.
4. Card tap / "Select" button -> confirmation dialog ("Assign Adv. {name} to this case? This will be sent to our team for review.") -> confirm par `POST /cases/{id}/assignment-requests { lawyer_id }`.
5. Success (`201`) -> list screen band karo, case detail screen par wapas jao, "Awaiting admin review" state dikhao (upar table row 2).
6. `422` aaye toh seedha `message` ek toast/dialog mein dikha do - backend ka message already user-friendly hai (`09-case-assignment-api.md` section 4 ki table dekho).

---

## 3. Lawyer side - "My Cases" flow

### 3.1 New vs In Progress tabs

`GET /cases` ek hi call hai - **server status se filter nahi karta**, app ko khud bucket karna hai:

```
New         = cases jinka status === "assigned"   (admin ne approve kiya, tumhara decision baaki hai)
In Progress = cases jinka status === "accepted"    (tumne accept kar diya, chat chal rahi hai)
```

(`rejected`/`closed` wale cases "My Cases" mein dikhane ki zaroorat nahi - woh history hai, agar chahiye toh ek teesra "Past" tab bana sakte ho, mandatory nahi hai.)

Dono tabs ka data **ek hi API call** se aata hai - do alag calls mat karo, local state mein split kar lo.

### 3.2 Case detail (New tab se)

`GET /cases/{id}` se poori detail milti hai (client ka naam, practice area, description, incident date, location) - mockup jaisi "Case Information" + "Client Information" sections isi se bharo. Documents ke liye `GET /cases/{id}/documents` alag call hai (`06-billing-and-storage.md` section 3).

Do buttons: **Accept** aur **Decline**.

- Accept -> confirmation dialog -> `POST /cases/{id}/accept` -> success par case "In Progress" tab mein chala jaata hai, **Chat** screen khul jaati hai (seedha navigate kar do, lawyer ab turant client se baat kar sakta hai).
- Decline -> confirmation dialog (reason optional - backend reason leta hi nahi is call mein) -> `POST /cases/{id}/reject` -> success par case list se hat jaata hai ("Case declined successfully" jaisa confirmation dikhao, phir list par wapas).

`422` ("This case has already been decided.") - matlab doosra device/tab se pehle hi decide ho chuka, list refresh karke latest state dikhao.

---

## 4. Error handling (dono apps)

| Response | UI |
|---|---|
| `403` | Generic "You don't have access to this" - normal app flow mein aana hi nahi chahiye |
| `404` | Case/lawyer nahi mila - list refresh karo, "This case is no longer available" |
| `422` with `message` | Seedha `message` dikhao (toast/dialog) - har jagah user-friendly text hai |
| `401` | App-wide session-expired handling (jo pehle se hai) |

---

## 5. Integration order (suggested build sequence)

1. Case detail screen mein naya `assignment_request`-based state table wire karo (section 2.1) - isse pehle poora feature test hi nahi ho sakta.
2. Lawyer list screen + assign call (section 2.2). "Lawyers" tab (section 2.0) iske baad ya pehle, koi dependency nahi hai - standalone hai.
3. Lawyer app: New/In Progress tab split (section 3.1) - purane "single list" UI se yeh chhota change hai agar `GET /cases` pehle se use ho raha hai.
4. Lawyer app: Case detail + Accept/Decline (section 3.2).
5. Accept ke baad Chat screen ka naya entry point (agar pehle se chat ban chuki hai, bas navigation wire karo).
6. Reject/re-pick loop (client side "Pick another Lawyer" button) - polish pass.

---

## 6. QA checklist (launch se pehle test karo)

- [ ] "Lawyers" tab sab verified lawyers dikhata hai (koi case select kiye bina), search aur specialization filter dono kaam karte hain.
- [ ] Case submit karo, koi assignment request na banao -> "Assign a Lawyer" button dikhta hai.
- [ ] Lawyer list sirf us practice area ke verified lawyers dikhati hai - koi unverified/suspended lawyer list mein nahi aana chahiye.
- [ ] Lawyer select karo -> "Awaiting admin review" state dikhta hai, dobara assign karne ka button nahi dikhna chahiye.
- [ ] (Admin panel se) request approve karo -> app mein (refresh/pull-to-refresh par) case `status: "assigned"` ho jaata hai, client ko "Waiting for {lawyer} to accept" dikhta hai.
- [ ] Lawyer app mein "New" tab mein wahi case dikhta hai - puri detail sahi dikhti hai.
- [ ] Lawyer Accept kare -> case "In Progress" tab mein chala jaata hai, chat turant khul jaati hai dono taraf se.
- [ ] (Doosre case ke liye) Lawyer Decline kare -> case lawyer ki list se hat jaata hai, client ko "declined" state dikhta hai.
- [ ] (Admin panel se) ek request reject karo (reason ke saath) -> client ko reason ke saath "rejected" banner dikhta hai, aur "Pick another Lawyer" se dobara list khulti hai aur naya pick submit ho jaata hai.
- [ ] Ek hi case par do baar assignment request bhejne ki koshish karo (pehli abhi pending ho) -> `422` aata hai, UI crash nahi hoti, samajhdar error dikhta hai.
- [ ] Client app se koi `advocate_id` seedha kahin bhi nahi bheja ja raha - sirf `lawyer_id` assignment-request call mein.

---

## 7. Kya jaan-bujh kar chhoda gaya (abhi)

Yeh poora `09-case-assignment-api.md` section 8 wahi hai jo backend mein nahi hai - sabse zaroori UI-impacting points yahan dobara:

- **`rating` / "match %"** cards par mat dikhana - field hi nahi hai.
- **Lawyer jab khud reject kar de** (`case.status === "rejected"` lawyer-accept ke baad), client ke liye koi "dobara assign karo" flow abhi nahi bana - agar product chahta hai, naya backend endpoint lagega (abhi sirf admin-rejection ke baad hi re-pick allowed hai).
- Koi real-time push nahi hai jab admin approve/reject kare - app ko **pull-to-refresh / polling** se `GET /cases/{id}` dobara call karna hoga, ya in-app notification tap hone par (`05-account-and-notifications.md` - agar is event ke liye notification bhi bhejni ho, abhi database + FCM dono jaate hain jab admin approve/reject karta hai, toh notification tap se bhi refresh trigger kar sakte ho).
