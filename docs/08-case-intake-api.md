# 08 - Case Intake API (Client only)

"Create Case" screen ke peeche ka API - case ki details bharna, evidence upload karna, aur submit karna. Sirf **clients** case khol sakte hain; lawyers is poore flow mein kahin nahi aate (unhe admin baad mein assign karega - abhi wo alag feature hai, isi doc ke §6 mein note hai).

> **Base URL / headers:** `03-mobile-api.md` jaisa hi - `Authorization: Bearer <token>`, `Accept: application/json`.
> **Storage quota** `06-billing-and-storage.md` mein cover hai - yahan reuse hoti hai, naya logic nahi hai.

---

## 1. Flow - 3 calls, is order mein

```
"Create Case" screen khulti hai
   |
   |  user pehli file pick karta hai (ya chahe title type karna shuru kare)
   v
POST /cases                      -> draft case ban gaya, case_id mil gaya
   |
   |  har file ke liye alag call - jitni files utni calls
   v
POST /cases/{id}/documents       -> file upload, "Ready" status yehi response hai
   |
   |  user baaki form bhar ke "Create Case" button tap kare
   v
POST /cases/{id}/submit          -> case finalize, ab admin review mein chala gaya
```

**Kyun 2 steps mein case banta hai, ek call mein nahi?** Taaki files upload hone ke liye case_id pehle se ho - file pick karte hi upload shuru ho sakta hai, poora form bharne ka wait nahi karna padta. Isse bade files ka upload bhi robust rehta hai: agar ek file fail ho, sirf wahi retry karni padti hai, poora form dobara nahi bharna padta.

**Draft case kahin dikhta nahi hai** - `GET /cases` list mein nahi, admin ke paas nahi - jab tak `submit` call na ho jaye. Agar user beech mein hi app band kar de ya screen cancel kar de, draft **48 ghante** baad apne aap delete ho jata hai (uski uploaded files samet) - dekho §5.

---

## 2. `POST /cases` - draft shuru karo

Jab user pehli baar kuch kare (file pick, ya title type karna shuru kare), ye call karo. Body mein jo bhi field us waqt available ho bhej do - sab optional hain.

Body (sab optional):
```json
{
  "title": "Wrongful termination",
  "practice_area_id": 3,
  "description": "Terminated without notice after 3 years of service.",
  "incident_date": "2026-09-01",
  "location": "Mumbai, India"
}
```

Response `201`:
```json
{
  "message": "Draft case started.",
  "data": {
    "id": 42,
    "title": "Wrongful termination",
    "practice_area": { "id": 3, "name": "Labour Law" },
    "description": "Terminated without notice after 3 years of service.",
    "incident_date": "2026-09-01",
    "location": "Mumbai, India",
    "status": "pending",
    "is_draft": true,
    "client": { "id": "448dc022-...", "name": "Rahul Sharma", "...": "..." },
    "advocate": null,
    "unread_count": 0,
    "submitted_at": null,
    "created_at": "2026-10-09T10:00:00+00:00"
  }
}
```

**Response `403`** - lawyer ne call kiya: `{ "message": "Only clients can open a case." }`
**Response `422 storage_full`** - client ke paas active plan nahi hai ya quota full hai: same shape jo `06-billing-and-storage.md` mein hai.

`data.id` ko local state mein save karo - yehi `case_id` hai jo documents aur submit dono ke liye chahiye.

---

## 3. `POST /cases/{id}/documents` - har file ke liye alag

Bilkul wahi endpoint jo pehle se hai - koi change nahi (`06-billing-and-storage.md` §3 dekho). Multipart, field `file`, max 20MB, PDF/DOC/DOCX/JPG/JPEG/PNG. Draft case ho ya submitted - dono par kaam karta hai.

```
POST /cases/42/documents
Content-Type: multipart/form-data
file: Termination_Notice.pdf
```

Response `201`:
```json
{
  "data": {
    "id": 101,
    "case_id": 42,
    "original_name": "Termination_Notice.pdf",
    "mime_type": "application/pdf",
    "size_bytes": 1468006,
    "accessible": true,
    "download_path": "/api/v1/cases/42/documents/101/download",
    "created_at": "2026-10-09T10:01:12+00:00"
  }
}
```

**Response `422 storage_full`** - is file se quota cross ho jata: baaki files bilkul safe rehti hain, sirf yahi ek fail hoti hai. UI mein us file ko "Failed - upgrade plan" dikhao, baaki "Ready" rehte hain.

---

## 4. `POST /cases/{id}/submit` - "Create Case" button

Ab **sab fields required** hain - yahi final validation hai. Documents ka is request mein koi zikr nahi (jo already upload ho chuke hain, wahi attached rehte hain).

Body (sab required):
```json
{
  "title": "Wrongful termination",
  "practice_area_id": 3,
  "description": "Terminated without notice after 3 years of service.",
  "incident_date": "2026-09-01",
  "location": "Mumbai, India"
}
```

Response `200`:
```json
{
  "message": "Case submitted for review.",
  "data": { "...": "...same shape as above, but", "is_draft": false, "submitted_at": "2026-10-09T10:05:00+00:00" }
}
```

Ab case `GET /cases` list mein dikhega aur admin ke paas review ke liye chala jayega.

**Response `422`** - koi required field missing/invalid, ya `incident_date` future mein hai: standard Laravel validation shape (`03-mobile-api.md` §2).
**Response `422`** - case already submit ho chuka hai: `{ "message": "This case has already been submitted." }`
**Response `403`** - apna draft nahi hai.

---

## 5. Draft discard / cleanup

| | |
|---|---|
| **User khud cancel kare** | `DELETE /cases/{id}` - sirf apna draft, aur sirf jab tak submit na hua ho. Case aur uski saari evidence (disk se bhi) delete ho jati hai. Response `422` agar case already submitted hai. |
| **User bas app band kar de / crash ho jaye** | Koi action nahi leni - server khud sambhal lega. Ek daily job (`cases:purge-abandoned-drafts`) 48 ghante se purane unsubmitted drafts ko unki evidence samet delete kar deta hai. |

App agar screen se explicitly back/cancel detect kare, `DELETE` call karna best practice hai (turant cleanup) - lekin zaroori nahi hai, safety net hamesha chalta rahega.

---

## 6. Data model aur abhi kya scope mein nahi hai

| Field | Note |
|---|---|
| `practice_area_id` | Wahi `practice_areas` table jo lawyer specialization ke liye use hoti hai (`03-mobile-api.md` ke registration mein dekha hoga) - case ka "legal issue" usi list se aata hai. |
| `advocate_id` | **Client ye kabhi nahi bhejta.** Submit hone ke baad case admin ke paas Pending baitha rehta hai, `advocate_id = null`. Admin kis lawyer ko assign karega - wo endpoint abhi bana nahi hai, alag se banega. Jab tak assign nahi hota, case kisi lawyer ko dikhta nahi aur chat shuru nahi ho sakta. |
| `status` | Abhi bhi `pending` / `accepted` / `rejected` / `closed` - assign hone ke baad hi `accept`/`reject` (`04-realtime-chat.md`) relevant hote hain. |

---

## 7. Scheduled job

`php artisan cases:purge-abandoned-drafts` daily chalta hai (`routes/console.php`, same cron setup jo baaki sab scheduled jobs use karte hain). TTL `CASE_DRAFT_TTL_HOURS` env se configurable hai (default 48).
