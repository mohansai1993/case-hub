# 04 - Real-time Chat (Client <-> Advocate)

Client aur Lawyer ke beech per-case real-time chat. **Laravel Reverb** (Laravel ka apna WebSocket server) se chalta hai. Redis **nahi** use ho raha - single server ke liye Reverb ka apna in-memory driver kaafi hai (dekho project overview mein "Redis vs Reverb" wala decision).

> Case kaise banta hai, evidence upload, aur lawyer kaise assign hota hai (client browse karke pick karta hai, admin approve karta hai) - yeh sab alag docs mein hai (`08-case-intake-api.md`, `09-case-assignment-api.md`). Yahan sirf us poore flow ke **baad** ka hissa hai: lawyer ka accept/reject, aur chat (messages, ticks, presence).

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

**`messages.delivered`** aur **`messages.read`** (WhatsApp-style ticks - grey/grey-grey/blue, poora detail section 4.4-4.5 mein hai):
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
| 2 | GET | `/cases/{case}` | Ek case ki detail |
| 3 | POST | `/cases/{case}/accept` | Advocate case accept kare |
| 4 | POST | `/cases/{case}/reject` | Advocate case reject kare |
| 5 | GET | `/cases/{case}/messages` | Message history (paginated - page 1 sabse naya conversation, lekin har page ke andar oldest-first) |
| 6 | POST | `/cases/{case}/messages` | Message bhejo |
| 7 | POST | `/cases/{case}/messages/delivered` | "Main online hoon" ack - dusre party ke saare undelivered messages ko deliver mark karo (grey -> double-grey tick) |
| 8 | POST | `/cases/{case}/messages/read` | Chat khuli/focus hui - dusre party ke unread messages ko read mark karo (double-grey -> double-blue tick) |

> Case **banane** (`POST /cases`, draft + submit) ka pura flow `08-case-intake-api.md` mein hai, aur **lawyer assign karne** (browse + pick + admin-approval) ka flow `09-case-assignment-api.md` mein hai - dono is doc ka scope nahi hain. Yahan se shuru hota hai jahan case pehle se `status: "assigned"` hai (admin ne lawyer approve kar diya) - ab lawyer ka accept/reject aur chat.

### 4.1 `POST /cases/{case}/accept` aur `/reject`

Sirf us case ka **advocate** call kar sakta hai. Sirf `assigned` case par chalta hai - dusri baar chalane par `422`: `{ "message": "This case has already been decided." }`.

Response `200`:
```json
{ "message": "Case accepted.", "data": { "status": "accepted" } }
```

**Chat sirf `accepted` case par khulti hai.** Pending ya rejected case par message bhejne ki koshish `422`: `{ "message": "This case is not open for messages yet." }`.

### 4.2 `GET /cases/{case}/messages` - History

Response `200`:
```json
{
  "data": [
    { "id": 101, "case_id": 42, "sender_id": "...", "is_mine": false, "body": "When is the hearing?", "status": "read", "created_at": "..." },
    { "id": 102, "case_id": 42, "sender_id": "...", "is_mine": true, "body": "Sure, 10am works.", "status": "sent", "created_at": "..." }
  ]
}
```

**Har page ke andar purana message pehle, naya message aakhir mein** (`data` array seedha render karo top-to-bottom - naya message neeche aayega, koi reverse() karne ki zaroorat nahi). Page selection khud **sabse latest** conversation se hoti hai - `?page=1` (default) hamesha sabse naye N messages dega (bas unhi N ke andar oldest-first order mein), `?page=2` usse purane N dega, waisa hi "scroll up to load older" pattern ke liye.

`is_mine` current logged-in user ke hisaab se hai (frontend ko khud sender_id compare nahi karna padta).

`status` teen values le sakta hai - `"sent"` (1 grey tick), `"delivered"` (2 grey tick), `"read"` (2 blue tick). **Ticks sirf apne `is_mine: true` messages par dikhao** - doosri party ke message par tick ka koi matlab nahi hai. `GET /messages` khud koi status change nahi karta, sirf current state dikhata hai - delivered/read sirf section 4.4 aur 4.5 wale calls se set hote hain.

### 4.3 `POST /cases/{case}/messages` - Bhejo

Request:
```json
{ "body": "Sure, 10am works." }
```
Header (optional, apna message wapas na dikhe iske liye - upar section 3 dekho): `X-Socket-ID: <socket id>`

Response `201`: wahi shape jo history mein ek message ki hoti hai.

Ye call: (a) DB mein save karta hai, (b) Reverb par turant broadcast karta hai, (c) dusre party ko push notification bhejta hai (queued).

### 4.4 `POST /cases/{case}/messages/delivered` - "Main online hoon" ack

Body nahi chahiye. **Jaise hi WebSocket connect ho** (chat screen khuli ho ya na ho - bas app foreground mein aaye/socket judhe), yeh call karo. Sirf **dusre party ke** undelivered messages ko deliver mark karta hai.

Response `200`: `{ "message": "Marked as delivered." }`

Kam se kam ek message deliver hua ho tabhi `messages.delivered` WebSocket event doosri party ko broadcast hota hai (kuch bhi pending na ho toh koi event nahi jaata, noise nahi hota). **Idempotent hai** - baar baar call karne mein koi harm nahi, reconnect/foreground hone par dobara call karna best practice hai.

### 4.5 `POST /cases/{case}/messages/read`

Body nahi chahiye. Chat screen actually khulne/focus hone par call karo. **Dusre party ke** unread messages ko read mark karta hai (apne khud ke bheje hue messages affect nahi hote) - aur saath mein `delivered_at` bhi backfill kar deta hai (agar kisi wajah se delivered-ack miss ho gaya tha, read hamesha delivered implicitly bhi hota hai).

Response `200`: `{ "message": "Marked as read." }`

### 4.6 Common errors

| Status | Kab |
|---|---|
| `401` | Login nahi hai |
| `403` | Case se related nahi ho (na client, na advocate), ya client ne khud apna case accept/reject karne ki koshish ki (yeh sirf advocate kar sakta hai) |
| `404` | Case exist nahi karta |
| `422` | Validation, ya case abhi `accepted` nahi hai (messages ke liye), ya case already decided (accept/reject ke liye) |

---

## 5. Profile picture & Presence (online/offline, last seen)

Chat screen ke header mein doosri party ki photo aur online status dikhane ke liye.

### 5.1 Profile picture - already available hai, koi alag call nahi chahiye

`GET /cases/{case}` (section 4) ka `client` aur `advocate`, dono objects mein pehle se `image_url` hota hai - chat header isi se bhar do, koi naya endpoint nahi hai.

### 5.2 Online / Offline + "Last seen"

`client` aur `advocate` dono objects mein (sirf case ke andar - general app mein kahin aur nahi, privacy ki wajah se, neeche dekho) do naye fields hain:

```json
{
  "id": "...",
  "name": "Adv. Sarah Jenkins",
  "image_url": "...",
  "is_online": false,
  "last_active_at": "2026-10-22T11:58:00+00:00",
  "...": "..."
}
```

- `is_online: true` - iska matlab woh pichle **2 minute** ke andar app use kar rahe the (koi bhi API call - sirf chat screen khuli hona zaroori nahi). Isse zyada purana ho toh `false`.
- `last_active_at` - raw ISO8601 timestamp hai, **"2 minutes ago" jaisa text backend nahi bhejta** - frontend khud relative-time format kare (jaisa `created_at` har jagah hota hai).
- `last_active_at: null` - matlab yeh user kabhi kisi authenticated API call mein nahi aaya (bahut purana/test account) - is case mein sirf "offline" dikhao, "last seen" mat dikhao.
- **Kaise update hota hai:** server-side, har authenticated request par ek throttled touch hota hai (1 minute mein ek baar se zyada DB write nahi) - **frontend ko koi alag "heartbeat" call bhejne ki zaroorat nahi**, normal app usage (cases list refresh, messages fetch, waghera) khud hi presence ko zinda rakhta hai.
- **Live update:** real-time WebSocket push nahi hai is field ke liye abhi (presence channel use nahi kiya gaya, jaan-bujh kar simple rakha hai - section 6 dekho). Jab bhi `GET /cases/{case}` dobara call ho (chat screen open/resume, ya periodic refresh), tazi values aa jaayengi.
- **Privacy:** yeh fields sirf `GET /cases/{case}` (aur list) ke `client`/`advocate` mein hain - `GET /lawyers` / `GET /lawyers/{id}` (general directory, `09-case-assignment-api.md`) mein **nahi** hain. Matlab koi lawyer ka online-status sirf unhi clients ko pata chalega jinke saath unki actual case hai - poori directory browse karke "kaun abhi online hai" track nahi kiya ja sakta.

---

## 6. Kya jaan-bujh kar chhoda gaya (abhi)

- Typing indicators, aur presence ka **turant/instant** (WebSocket push) version - abhi jo hai woh poll-based hai (section 5.2), Reverb presence channels se "live" bhi ban sakta hai, jaan-bujh kar simple rakha gaya hai.
- Multiple devices par alag-alag delivered/read/presence status (ek hi account ke 2 phone) - jo bhi device pehle ack kare wahi mark ho jaata hai.
- Message edit/delete, attachments/files in chat.
- Admin panel se chat dekhna/moderate karna.

Ye sab agle iteration mein add ho sakte hain jab case-management feature ka poora scope decide ho.
