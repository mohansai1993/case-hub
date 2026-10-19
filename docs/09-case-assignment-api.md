# 09 - Case-to-Lawyer Assignment API (Client + Lawyer)

`08-case-intake-api.md` mein case **submit** tak cover hota hai - uske baad case `status: "pending"`, `advocate_id: null` ke saath admin review mein baitha rehta hai. Yeh doc wahan se aage ka flow hai: **client khud lawyer chunta hai**, lekin seedha us lawyer ko nahi jaata - pehle **admin approve/reject** karta hai, tabhi lawyer ko dikhta hai. Admin-side screens `02-admin-panel.md` mein hain; yahan sirf **mobile app (Client + Lawyer)** ki APIs hain.

Lawyer ka **accept/reject** aur uske baad ki **chat** already document hai `04-realtime-chat.md` mein - yahan sirf wahi one-line change hai jo is feature se aaya (status guard).

---

## 1. Poora flow - ek nazar mein

```
Case submit ho gaya (08-case-intake-api.md)
   |
   v
status: "pending", advocate_id: null, assignment_request: null
   |
   |  Client: "Assign a Lawyer" button tap kare
   v
GET /cases/{id}/lawyers            -> practice-area-matched, verified lawyers ki list
   |
   |  Client: ek lawyer select kare
   v
POST /cases/{id}/assignment-requests   -> request ban gayi, admin ke paas chali gayi
   |
   v
status: "pending" (abhi bhi), assignment_request: { status: "pending", lawyer: {...} }
   |
   |-- Admin REJECT kare (02-admin-panel.md) -------> assignment_request.status: "rejected"
   |                                                   case wapas "pending" + advocate_id null
   |                                                   -> Client phir se GET /cases/{id}/lawyers karke
   |                                                      doosra (ya wahi) lawyer try kar sakta hai
   |
   `-- Admin APPROVE kare ---------------------------> status: "assigned", advocate_id set
                                                         assignment_request.status: "approved"
                                                         |
                                                         |  Lawyer ab is case ko dekh sakta hai
                                                         v
                                              POST /cases/{id}/accept  ya  /reject   (04-realtime-chat.md)
                                                         |
                                              accept  -> status: "accepted" -> chat khul jaati hai
                                              reject  -> status: "rejected" (terminal)
```

**Zaroori baat:** jab tak admin approve nahi karta, `advocate_id` null hi rehta hai aur case us lawyer ko **bilkul nahi dikhta** (`GET /cases` lawyer ke liye sirf unhi cases ko list karta hai jahan `advocate_id` unka apna ho) - isliye lawyer app ko is poore pending-review state ke baare mein kuch alag se handle nahi karna padta, bas wait karna hai.

---

## 2. `GET /lawyers` - General Lawyer directory (sirf Client)

Yeh **case se bilkul alag/independent** endpoint hai - app ke "Lawyers" tab (bottom nav) ke liye, jahan client bina kisi case khole bhi sab lawyers browse kar sake. Kisi case ke liye specifically assign karna ho toh section 3 (`GET /cases/{case}/lawyers`) use karo - woh case ke practice area se match karta hai, yeh nahi karta.

Query params (dono optional):
| Param | Kya karta hai |
|---|---|
| `q` | Naam se search (partial match) |
| `practice_area_id` | Sirf us specialization wale lawyers |

```
GET /api/v1/lawyers?q=sarah&practice_area_id=2
```

Response `200`: same shape jo section 3 mein hai (neeche dekho) - `UserResource` array, paginated, 20 per page.

- List sirf un lawyers ki hai jo account se `active` aur admin se `verified` hain - koi filter diye bina **sab verified lawyers** aa jaate hain (case-scoped wale endpoint ke ulat, jahan practice area match zaroori hai).
- **`rating` / "match %" koi field nahi hai** - mockup mein dikhte hain, backend schema mein nahi (dekho section 8).
- **Response `403`** - lawyer ka token (yeh tab sirf Client app mein hai).

### 2.1 `GET /lawyers/{id}` - Lawyer Profile / "View Details" (sirf Client)

List card ya case-assignment ke "View Details" tap karne par yeh call karo.

```
GET /api/v1/lawyers/d1fdf080-fd81-4b7b-87d6-bfeeb2384858
```

Response `200`:
```json
{
  "data": {
    "id": "d1fdf080-fd81-4b7b-87d6-bfeeb2384858",
    "type": "lawyer",
    "name": "Adv. John Smith",
    "email": "john@lawfirm.com",
    "mobile": "9876543210",
    "image_url": null,
    "email_verified": true,
    "status": "active",
    "lawyer": {
      "location": "Pune, India",
      "years_of_experience": 12,
      "bio": "Senior legal counsel specializing in corporate governance, employment litigation, and contract enforcement.",
      "verification_status": "verified",
      "practice_areas": [
        { "id": 2, "name": "Employment Contracts" },
        { "id": 5, "name": "Workplace Grievance" }
      ],
      "completed_cases": 200
    }
  }
}
```

- `lawyer.completed_cases` = is lawyer ke naam kitne cases `closed` status tak pahunche hain - list endpoint (section 2) mein yeh field **nahi** hai, sirf detail/profile screen ke liye hai (har list-item ke liye alag count query chalana costly hota).
- **`rating` / "4.8 (124 reviews)" aur firm/company ka naam** - dono mockup mein dikhte hain, **koi bhi field schema mein hai hi nahi** - koi reviews/ratings table nahi hai, `LawyerProfile` mein firm-name wala column nahi hai. Yeh fabricate nahi kiya gaya - jab tak yeh feature real mein banaye (naya table + review-collection flow), UI mein mat dikhana.
- "ACTIVE COUNSEL" jaisa badge koi alag field nahi hai - top-level `status: "active"` se hi derive karo.
- **Response `404`** - lawyer exist nahi karta, lawyer ka account type nahi hai (client ka id diya), ya lawyer abhi `active`+`verified` nahi hai (list mein bhi nahi dikhega - same visibility rule, taaki suspended/unverified lawyer ka profile URL guess karke na khul jaaye).
- **Response `403`** - caller khud lawyer hai (yeh endpoint sirf Client app ke liye hai).

---

## 3. `GET /cases/{case}/lawyers` - Ek case ke liye matched lawyers (sirf Client, case ka owner)

"Assign a Lawyer" screen khulte hi (ek case ke andar se) yeh call karo - section 2 wale general directory se farak: yahan sirf us case ke practice area wale lawyers aate hain.

Response `200`:
```json
{
  "data": [
    {
      "id": "d1fdf080-fd81-4b7b-87d6-bfeeb2384858",
      "type": "lawyer",
      "name": "Adv. Sarah Jenkins",
      "email": "sarah@lawfirm.com",
      "mobile": "9876543210",
      "image_url": null,
      "email_verified": true,
      "status": "active",
      "lawyer": {
        "location": "Pune, India",
        "years_of_experience": 10,
        "bio": "Employment & labour law.",
        "verification_status": "verified",
        "practice_areas": [
          { "id": 2, "name": "Employment Law" }
        ]
      }
    }
  ]
}
```

- List sirf un lawyers ki hai jo: account `active` hain, admin se `verified` hain, aur case ke `practice_area_id` wali specialization rakhte hain. Koi `rating` ya "match %" field nahi hai - woh abhi backend mein exist hi nahi karta.
- Paginated hai (`?page=2`), standard Laravel pagination shape (`03-mobile-api.md` jaisa).
- **Response `403`** - case tumhara khud ka nahi: `{ "message": "..." }`
- **Response `422`** - case abhi draft hai (submit nahi hua): `{ "message": "Submit the case before browsing lawyers." }`

---

## 4. `POST /cases/{case}/assignment-requests` - Lawyer assign karo

Body:
```json
{ "lawyer_id": "d1fdf080-fd81-4b7b-87d6-bfeeb2384858" }
```
(`lawyer_id` upar wali list ke kisi bhi item ka `id`.)

Response `201`:
```json
{
  "message": "Lawyer assignment submitted for admin review.",
  "data": {
    "id": 7,
    "lawyer": { "id": "d1fdf080-...", "name": "Adv. Sarah Jenkins", "...": "..." },
    "status": "pending",
    "reason": null,
    "decided_at": null,
    "created_at": "2026-10-19T10:00:00+00:00"
  }
}
```

**Yeh call case ko turant lawyer ko assign *nahi* karta** - sirf ek request banata hai jo admin ke dashboard mein "Pending Review" mein dikhegi. Isi response ka `data` object hi tumhe `GET /cases/{id}` ke `assignment_request` field mein bhi milega (section 5 dekho) - UI isi se current state dikha sakti hai.

**Response `422`** (`message` seedha dikhane layak hai):
| Message | Kab |
|---|---|
| `"Submit the case before assigning a lawyer."` | Case abhi draft hai |
| `"This case already has a lawyer assigned."` | Pehle se admin-approved advocate hai is case par |
| `"A request for this case is already awaiting admin review."` | Pehle se ek pending request hai - naya banane se pehle usska decide hona zaroori hai |
| `"Only verified lawyers can be assigned."` | Chuna gaya lawyer verified nahi hai (ho sakta list fetch aur is call ke beech status badal gaya ho) |
| `"This lawyer does not handle the case's practice area."` | Specialization match nahi karti |

**Response `403`** - case tumhara khud ka nahi.

**Ek hi waqt mein sirf ek pending request allowed hai per case.** Agar admin reject kar de, tabhi dobara koi bhi lawyer (wahi ya naya) try kar sakte ho - koi limit/cap nahi hai kitni baar try karein.

---

## 5. `GET /cases/{case}` aur `GET /cases` - naye `assignment_request` aur `case_number` fields

`CaseResource` (dono list aur detail endpoint) ab do naye fields deta hai - `case_number` (har case ka human-readable unique ID, jaise `CASE-1042` - UI mein raw `id` ke bajaye hamesha yehi dikhao) aur `assignment_request`:

```json
{
  "id": 42,
  "case_number": "CASE-1042",
  "title": "Wrongful termination",
  "practice_area": { "id": 3, "name": "Employment Law" },
  "status": "assigned",
  "is_draft": false,
  "client": { "...": "..." },
  "advocate": { "...": "..." },
  "assignment_request": {
    "id": 7,
    "lawyer": { "id": "...", "name": "Adv. Sarah Jenkins", "...": "..." },
    "status": "approved",
    "reason": null,
    "decided_at": "2026-10-19T11:00:00+00:00",
    "created_at": "2026-10-19T10:00:00+00:00"
  },
  "unread_count": 0,
  "submitted_at": "2026-10-09T10:05:00+00:00",
  "created_at": "2026-10-09T10:00:00+00:00"
}
```

- `assignment_request` **hamesha sabse latest** request hai (jo bhi abhi "active" hai - pending, approved, ya sabse recent rejected agar koi naya pick nahi kiya abhi tak).
- `assignment_request: null` ka matlab: client ne abhi tak koi lawyer pick hi nahi kiya.
- `assignment_request.status` ke 3 values: `"pending"` (admin review mein), `"approved"` (ab lawyer ke paas hai), `"rejected"` (client dobara try kar sakta hai, `reason` field mein wajah hai).
- `case.status` **khud se** bhi state batata hai, is poore feature ke baad ab yeh values ho sakti hain: `"pending"` (kisi ko assign nahi, ya abhi admin review mein) -> `"assigned"` (admin approved, lawyer ke decision ka wait) -> `"accepted"` / `"rejected"` (lawyer ka decision) -> `"closed"`.

---

## 6. Lawyer ka accept/reject - ek guard change

`04-realtime-chat.md` mein documented `POST /cases/{case}/accept` aur `/reject` **bilkul same tarah kaam karte hain**, bas ab yeh `case.status === "assigned"` par chalte hain (pehle `"pending"` par chalte the, jab `advocate_id` seedha case-creation call mein bhejte the - woh purana flow ab exist nahi karta, upar section 1 ka flow hi sahi hai).

Agar koi case abhi bhi plain `"pending"` hai (koi admin-approved advocate nahi), lawyer ke paas woh dikhega hi nahi (`GET /cases` unke liye empty rehta hai us case ke liye), isliye normal app flow mein yeh guard kabhi hit hi nahi hoga - bas safety ke liye hai.

---

## 7. Common errors

| Status | Kab |
|---|---|
| `401` | Login nahi hai |
| `403` | Case tumhara nahi (lawyers list / assignment-request), lawyer ne `/lawyers` directory kholne ki koshish ki, ya role allow nahi karta |
| `404` | Case ya lawyer exist nahi karta |
| `422` | Upar section 4 ki table, ya case draft hai |

---

## 8. Kya jaan-bujh kar chhoda gaya (abhi)

- **`rating` / "match %"** - koi aisa field schema mein hai hi nahi, UI mockup mein dikha tha lekin backend se nahi aata.
- **Timeout/auto-expire** - admin agar lambe time tak pending request decide na kare, koi automatic expiry/escalation nahi hai.
- **Multi-lawyer fan-out** - ek waqt mein sirf ek hi lawyer ko request bhej sakte ho, "5 lawyers ko ek saath bhejo, jo pehle accept kare" wala flow nahi hai.
- **Client-side cancel** - pending request ko client khud cancel nahi kar sakta (admin ke decide hone ka wait karna padega).
- Poora admin-approval UI (list / approve / reject) is doc ka scope nahi hai - dekho `02-admin-panel.md`.
