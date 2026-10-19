# 04 - Real-time Chat (Client <-> Advocate)

Client aur Lawyer ke beech per-case real-time chat. **Laravel Reverb** (Laravel ka apna WebSocket server) se chalta hai. Redis **nahi** use ho raha - single server ke liye Reverb ka apna in-memory driver kaafi hai (dekho project overview mein "Redis vs Reverb" wala decision).

> **Case feature abhi minimal hai.** Chat ko attach karne ke liye bas itna bana hai: client kisi lawyer ko case ke liye invite karta hai (`title` + `advocate_id`), lawyer accept/reject karta hai, accept hone par chat khulti hai. Poora case-management (documents, description, location/date, status history) **abhi nahi bana** - woh alag feature hai.

---

## 1. Architecture

```
Mobile App (Client / Lawyer)
   |
   |-- REST (Sanctum bearer token) --> Laravel --> DB (cases, case_messages)
   |                                       |
   |                                       `-- broadcast(MessageSent) --> Reverb server
   |
   `-- WebSocket (ws://.../app/{REVERB_APP_KEY}) --> Reverb server --> real-time message
```

- Har case ka apna **private channel** hai: `case.{caseId}`.
- Sirf us case ke `client` aur `advocate` hi us channel ko subscribe kar sakte hain - authorization `routes/channels.php` mein check hoti hai.
- Message bhejna ek normal REST call hai (`POST /cases/{case}/messages`); us call ke response ke saath-saath, backend **turant** (`ShouldBroadcastNow`, queue mein nahi) Reverb par bhi broadcast kar deta hai, taaki dusra banda WebSocket se turant dekh le.
- Agar receiver app mein nahi hai (WebSocket connected nahi), unhe **push notification** bhi jaati hai (wahi FCM system jo pehle se bana hai - dekho push-notification docs).

---

## 2. Reverb chalana

Dev mein:
```bash
php artisan reverb:start
# ya debug logs ke saath:
php artisan reverb:start --debug
```

Production mein `supervisor`/systemd se background service ki tarah chalao (isi tarah jaise queue worker chalate ho), kyunki ye ek long-running process hai jo normal PHP-FPM/XAMPP request-response cycle se bahar chalta hai.

`.env` (already set for local dev, production mein apne khud ke random `REVERB_APP_ID`/`KEY`/`SECRET` banao):
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```
Production mein `REVERB_HOST` apna domain hoga aur `REVERB_SCHEME=https` (Reverb ke aage Nginx/reverse-proxy laga kar SSL termination karo - Reverb khud HTTPS nahi karta).

`QUEUE_CONNECTION=database` hi rehta hai - push-notification fallback (`NewCaseMessage`) queued hai, isliye `php artisan queue:work` bhi chalna chahiye (yeh pehle se document hai push-notification docs mein).

---

## 3. Mobile app WebSocket se kaise jude

App ko ek Pusher-protocol-compatible client library chahiye (Reverb Pusher protocol hi bolta hai) - jaise Flutter ke liye `pusher_channels_flutter`, ya native Android/iOS ke liye koi Pusher-compatible SDK.

**Connect:**
```
Host: {REVERB_HOST}:{REVERB_PORT}
Key: {REVERB_APP_KEY}
Scheme: {REVERB_SCHEME}  (dev mein http/ws, production mein https/wss)
```

**Private channel subscribe karne ke liye** app ko pehle backend se channel authorize karana hoga:
```
POST {APP_URL}/api/broadcasting/auth
Headers: Authorization: Bearer <token>, Accept: application/json
Body: { "channel_name": "private-case.42", "socket_id": "<socket id jo WS handshake se mila>" }
```
Zyadatar Pusher-compatible client libraries ye call **khud** kar dete hain jab tumhe bas `authEndpoint` aur bearer token config karna hota hai - manually call karne ki zaroorat aam taur par nahi padti.

**Subscribe karo:**
```
channel = "case.{caseId}"   // library "private-" prefix khud laga degi
events  = "message.sent", "messages.delivered", "messages.read"
```

**Event payload jo milega** (`message.sent`):
```json
{
  "id": 101,
  "case_id": 42,
  "sender_id": "448dc022-5370-43e7-bc16-a60c8a77427e",
  "body": "When is the hearing?",
  "created_at": "2026-09-28T12:05:00+00:00"
}
```

**`messages.delivered`** aur **`messages.read`** (WhatsApp-style ticks - grey/grey-grey/blue, poora detail section 4.5-4.6 mein hai):
```json
{ "delivered_by": "448dc022-5370-43e7-bc16-a60c8a77427e", "delivered_at": "2026-10-21T12:05:30+00:00" }
```
```json
{ "read_by": "448dc022-5370-43e7-bc16-a60c8a77427e", "read_at": "2026-10-21T12:06:10+00:00" }
```
In dono events ka payload chhota rakha gaya hai - poori message-id list nahi hai, bas kisne aur kab. Apne khud ke bheje (`is_mine: true`) messages mein se jinka `created_at <= delivered_at`/`read_at` hai, unka tick status wahi UI update kar do - koi dobara REST call karne ki zaroorat nahi.

**Apna hi message wapas na dikhe, iske liye:** WS connect hone par library ek `socket_id` deti hai. Wahi `socket_id` `POST /cases/{case}/messages` call mein header `X-Socket-ID` se bhejo - Reverb tumhare apne connection ko wapas broadcast nahi karega (`toOthers()`). Agar header nahi bhejoge to koi crash nahi hoga, bas apna message tumhe REST response se **aur** WebSocket se dono jagah se milega (duplicate - message id se de-dupe kar lena).

---

## 4. REST Endpoints

Sab `auth:sanctum` + `api.active` ke peeche hain (login ke baad).

| # | Method | URL | Kya karta hai |
|---|---|---|---|
| 1 | GET | `/cases` | Apne saare cases (client ya advocate, dono roles mein) |
| 2 | POST | `/cases` | Naya case kholo (sirf Client) |
| 3 | GET | `/cases/{case}` | Ek case ki detail |
| 4 | POST | `/cases/{case}/accept` | Advocate case accept kare |
| 5 | POST | `/cases/{case}/reject` | Advocate case reject kare |
| 6 | GET | `/cases/{case}/messages` | Message history (paginated, naye pehle) |
| 7 | POST | `/cases/{case}/messages` | Message bhejo |
| 8 | POST | `/cases/{case}/messages/delivered` | "Main online hoon" ack - dusre party ke saare undelivered messages ko deliver mark karo (grey -> double-grey tick) |
| 9 | POST | `/cases/{case}/messages/read` | Chat khuli/focus hui - dusre party ke unread messages ko read mark karo (double-grey -> double-blue tick) |

### 4.1 `POST /cases` - Case kholo

Sirf **Client** call kar sakta hai.

Request:
```json
{ "advocate_id": "d1fdf080-fd81-4b7b-87d6-bfeeb2384858", "title": "Wrongful termination" }
```

Response `201`:
```json
{
  "message": "Case created.",
  "data": {
    "id": 42,
    "title": "Wrongful termination",
    "status": "pending",
    "client": { "id": "...", "name": "Rahul Sharma", "...": "..." },
    "advocate": { "id": "...", "name": "Adv. Sarah Jenkins", "...": "..." },
    "unread_count": 0,
    "created_at": "2026-09-28T12:00:00+00:00"
  }
}
```

`advocate_id` kisi bhi registered lawyer ka `user_id` ho sakta hai (frontend ko lawyer chunne ke liye koi "browse lawyers" list UI khud banani hogi - abhi is document ka scope nahi hai). Lawyer khud case create nahi kar sakta (`403`).

### 4.2 `POST /cases/{case}/accept` aur `/reject`

Sirf us case ka **advocate** call kar sakta hai. Sirf `pending` case par chalta hai - dusri baar chalane par `422`: `{ "message": "This case has already been decided." }`.

Response `200`:
```json
{ "message": "Case accepted.", "data": { "status": "accepted" } }
```

**Chat sirf `accepted` case par khulti hai.** Pending ya rejected case par message bhejne ki koshish `422`: `{ "message": "This case is not open for messages yet." }`.

### 4.3 `GET /cases/{case}/messages` - History

Response `200` (naye message pehle, standard pagination):
```json
{
  "data": [
    { "id": 102, "case_id": 42, "sender_id": "...", "is_mine": true, "body": "Sure, 10am works.", "status": "sent", "created_at": "..." },
    { "id": 101, "case_id": 42, "sender_id": "...", "is_mine": false, "body": "When is the hearing?", "status": "read", "created_at": "..." }
  ]
}
```
`is_mine` current logged-in user ke hisaab se hai (frontend ko khud sender_id compare nahi karna padta).

`status` teen values le sakta hai - `"sent"` (1 grey tick), `"delivered"` (2 grey tick), `"read"` (2 blue tick). **Ticks sirf apne `is_mine: true` messages par dikhao** - doosri party ke message par tick ka koi matlab nahi hai. `GET /messages` khud koi status change nahi karta, sirf current state dikhata hai - delivered/read sirf section 4.5 aur 4.6 wale calls se set hote hain.

### 4.4 `POST /cases/{case}/messages` - Bhejo

Request:
```json
{ "body": "Sure, 10am works." }
```
Header (optional, apna message wapas na dikhe iske liye - upar section 3 dekho): `X-Socket-ID: <socket id>`

Response `201`: wahi shape jo history mein ek message ki hoti hai.

Ye call: (a) DB mein save karta hai, (b) Reverb par turant broadcast karta hai, (c) dusre party ko push notification bhejta hai (queued).

### 4.5 `POST /cases/{case}/messages/delivered` - "Main online hoon" ack

Body nahi chahiye. **Jaise hi WebSocket connect ho** (chat screen khuli ho ya na ho - bas app foreground mein aaye/socket judhe), yeh call karo. Sirf **dusre party ke** undelivered messages ko deliver mark karta hai.

Response `200`: `{ "message": "Marked as delivered." }`

Kam se kam ek message deliver hua ho tabhi `messages.delivered` WebSocket event doosri party ko broadcast hota hai (kuch bhi pending na ho toh koi event nahi jaata, noise nahi hota). **Idempotent hai** - baar baar call karne mein koi harm nahi, reconnect/foreground hone par dobara call karna best practice hai.

### 4.6 `POST /cases/{case}/messages/read`

Body nahi chahiye. Chat screen actually khulne/focus hone par call karo. **Dusre party ke** unread messages ko read mark karta hai (apne khud ke bheje hue messages affect nahi hote) - aur saath mein `delivered_at` bhi backfill kar deta hai (agar kisi wajah se delivered-ack miss ho gaya tha, read hamesha delivered implicitly bhi hota hai).

Response `200`: `{ "message": "Marked as read." }`

### 4.7 Common errors

| Status | Kab |
|---|---|
| `401` | Login nahi hai |
| `403` | Case se related nahi ho (na client, na advocate), ya lawyer ne case create/accept karne ki koshish ki jo unka role allow nahi karta |
| `404` | Case exist nahi karta |
| `422` | Validation, ya case abhi `accepted` nahi hai (messages ke liye), ya case already decided (accept/reject ke liye) |

---

## 5. Kya jaan-bujh kar chhoda gaya (abhi)

- Typing indicators / online-presence (Reverb presence channels se ban sakta hai, abhi nahi banaya) - "delivered" tick isse alag hai (section 4.5), woh ek simple ack call hai, presence channel nahi.
- Multiple devices par alag-alag delivered/read status (ek hi account ke 2 phone) - jo bhi device pehle ack kare wahi mark ho jaata hai.
- Message edit/delete, attachments/files in chat.
- Admin panel se chat dekhna/moderate karna.

Ye sab agle iteration mein add ho sakte hain jab case-management feature ka poora scope decide ho.
