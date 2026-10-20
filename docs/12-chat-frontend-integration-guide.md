# 12 - Chat Frontend Integration Guide (Tick Status + Presence + Push)

> Yeh doc **sirf frontend/mobile-app developer** ke liye hai - poora chat module (`04-realtime-chat.md` aur `11-chat-read-receipts-implementation-guide.md`) backend mein already ban chuka hai aur tested hai. Yahan point-wise bataya gaya hai **kya call karna hai, kab karna hai, aur kyun** - taaki koi step miss na ho.

---

## 0. Pehle: jo bug report hua, uska root cause

Do symptoms report hue the:

1. Sender ko message bheja hua **hamesha 1 grey tick** dikhta hai, kabhi double-tick (delivered/read) nahi aata.
2. Receiver **online/network pe** hone ke bawajood, har message par **push notification popup** aata hai.

**Root cause (ek hi): frontend `POST /cases/{case}/messages/delivered` call nahi kar raha.**

Yeh call **automatic nahi hai** - WebSocket connect hone ya message receive hone se khud-ba-khud nahi hota. Isko app code se **explicitly trigger karna padta hai**. Agar yeh call kabhi nahi hota:

- `delivered_at` DB mein hamesha `null` rehta hai -> tick kabhi `sent` se aage nahi badhta (Symptom 1).
- Backend ka push-notification-skip-logic (jo check karta hai "agar `delivered_at` set hai to FCM push mat bhejo, user already socket se dekh chuka hai") kabhi trigger nahi hota, kyunki `delivered_at` hi set nahi ho raha -> push **har baar** jaata hai (Symptom 2).

**Fix is purely frontend-side.** Backend mein kuch nahi badla/badalna - endpoint, event, sab already bana hua hai aur tested hai (389 backend tests pass). Neeche section 3 mein exact jagah batayi hai ke yeh call kahan lagana hai.

---

## 1. Architecture - 30 second recap

```
App
 |-- REST (Sanctum bearer token) --> Laravel --> DB
 |                                      |
 |                                      `-- broadcast --> Reverb (WebSocket server)
 |
 `-- WebSocket (Pusher-protocol client, e.g. pusher_channels_flutter) --> Reverb
```

- Har case ka apna private channel: `case.{caseId}`.
- Teen WebSocket events subscribe karne hain: `message.sent`, `messages.delivered`, `messages.read`.
- Teen REST calls hain jo tick/presence drive karte hain: message send, delivered-ack, read-ack.

Poora API contract (request/response shapes) `04-realtime-chat.md` mein hai - yeh doc sirf "kab kya call karo" wali sequencing aur UI-binding cover karta hai.

---

## 2. WebSocket connect - ek baar, app session ke shuru mein

1. Pusher-compatible client library init karo:
   ```
   Host: {REVERB_HOST}:{REVERB_PORT}
   Key: {REVERB_APP_KEY}
   Scheme: dev=ws/http, prod=wss/https
   authEndpoint: {APP_URL}/api/broadcasting/auth
   auth.headers.Authorization: Bearer <token>
   ```
2. Jis case ki chat screen khulni hai, uska channel subscribe karo: `case.{caseId}` (library khud `private-` prefix lagati hai).
3. **Connection-state listener lagao** (library har SDK mein `connection.bind('connected', ...)` jaisa kuch deta hai) - yeh agle section ka trigger point hai.

---

## 3. ⚠️ Delivered-ack - yahi wo missing step hai

**Jaise hi socket `case.{caseId}` channel se connect ho** (chat screen khuli ho ya na ho - bas app foreground mein aaye ya socket (re)connect ho), **turant** yeh call karo:

```
POST /api/v1/cases/{caseId}/messages/delivered
Authorization: Bearer <token>
Accept: application/json
(no body)
```

Response `200`: `{ "message": "Marked as delivered." }`

### Kahan-kahan se yeh call trigger hona chahiye (sab jagah lagao, miss mat karo):

- [ ] App launch pe, agar koi open case hai aur socket connect ho gaya.
- [ ] Socket ka `connected` event (pehli baar connect).
- [ ] Socket ka `reconnected` event (network drop ke baad wapas connect) - **yeh sabse zyada miss hone wali jagah hai**.
- [ ] App background se foreground mein aaye (`AppLifecycleState.resumed` / iOS `applicationDidBecomeActive`).
- [ ] Chat screen khulte waqt bhi ek baar call kar do (safety net - idempotent hai, dobara call karne mein koi harm nahi).

**Yeh call idempotent hai** - jitni baar chalao, sirf **naye** undelivered messages ko mark karega; already-delivered messages touch nahi honge, aur agar kuch bhi pending nahi hai to koi WebSocket event bhi broadcast nahi hota (noise nahi hoga).

**Isko miss karne ka result bilkul wahi tha jo bug report mein mila** - tick stuck, aur push-skip logic kabhi kaam nahi karta.

---

## 4. Read-ack - chat screen actually khulne/focus hone par

```
POST /api/v1/cases/{caseId}/messages/read
Authorization: Bearer <token>
Accept: application/json
(no body)
```

Response `200`: `{ "message": "Marked as read." }`

Yeh call **delivered-ack se alag hai** - yeh sirf tab chalao jab user ne **chat screen khol ke dekha** ho (screen focus/visible hona), na ke sirf app foreground hone par. Trigger points:

- [ ] Chat screen pe navigate hone par (screen mount/focus).
- [ ] Agar app already chat screen pe khula hai aur naya message aaya (socket se `message.sent` event mila) - turant `read` call bhi kar do (user abhi dekh raha hai).

Yeh `delivered_at` ko bhi backfill kar deta hai (agar kisi wajah se section 3 wala call miss ho gaya tha) - but **isko section 3 ke replacement mat samjho**, delivered-ack har connection pe chahiye, read-ack sirf screen-open pe.

---

## 5. WebSocket events sunna - tick UI live update

Subscribe karne ke baad teen events handle karo:

### 5.1 `message.sent`
```json
{ "id": 101, "case_id": 42, "sender_id": "...", "body": "...", "created_at": "..." }
```
Naya message list mein append karo. Agar yeh message **doosri party ne** bheja hai aur chat screen abhi focus mein hai, turant section 4 wala `read` call bhi chalao.

### 5.2 `messages.delivered`
```json
{ "delivered_by": "<user_id>", "delivered_at": "2026-10-21T12:05:30+00:00" }
```
Apne **`is_mine: true`** messages mein se jin ka `created_at <= delivered_at` hai, unka tick `sent -> delivered` (1 grey -> 2 grey) kar do.

### 5.3 `messages.read`
```json
{ "read_by": "<user_id>", "read_at": "2026-10-21T12:06:10+00:00" }
```
Apne `is_mine: true` messages mein se jin ka `created_at <= read_at` hai, unka tick `delivered -> read` (2 grey -> 2 blue) kar do.

**Important:** ticks **sirf `is_mine: true`** messages par dikhao - doosri party ke message par tick ka koi matlab nahi (woh unka sent-status hai, tumhara nahi).

---

## 6. Tick rendering - history load hone par (`GET /messages`)

```json
{ "id": 102, "is_mine": true, "status": "sent" | "delivered" | "read", "...": "..." }
```

`status` field seedha map karo:

| `status` | UI |
|---|---|
| `"sent"` | ✓ (1 grey tick) |
| `"delivered"` | ✓✓ (2 grey tick) |
| `"read"` | ✓✓ (2 blue tick) |

`GET /messages` khud koi status change **nahi** karta - sirf current snapshot dikhata hai. Status sirf section 3/4 ke calls se aur unke resulting WebSocket events se change hota hai.

---

## 7. Online / Offline + Last seen

`GET /cases/{case}` (aur list) ke `client`/`advocate` objects mein:

```json
{ "is_online": true, "last_active_at": "2026-10-22T11:58:00+00:00" }
```

- `is_online: true` = pichle 2 minute ke andar koi API activity thi.
- Yeh **live-push nahi hota** - har baar `GET /cases/{case}` refresh karo (screen open/resume, ya periodic poll) to fresh value milegi.
- `last_active_at: null` ho to sirf "offline" dikhao, "last seen X ago" mat dikhao.
- Yeh field sirf case ke andar milta hai (privacy) - general lawyer directory mein nahi.

---

## 8. Push notification - foreground duplicate avoid karna

Backend ab smart hai: agar message `delivered_at` set ho jaata hai (section 3 ka call time pe ho jaye), to FCM push **skip** ho jata hai - sirf in-app/database notification banti hai. Isliye:

- **Section 3 ka call sahi se lagao** - isi se duplicate push khud-ba-khud kam ho jayenge.
- Phir bhi, agar FCM data-message aa jaaye jab app **foreground + us exact chat screen pe** ho, client-side bhi suppress karo (system notification mat dikhao, kyunki WebSocket se UI already update ho chuki hogi). Yeh purely client-side check hai: `data.case_id === currently_open_case_id` ho to local notification skip karo.

---

## 9. Error handling checklist

| Status | Kab | Frontend action |
|---|---|---|
| `401` | Token invalid/expired | Login screen pe redirect |
| `403` | Case ka participant nahi ho | Error screen / back |
| `404` | Case exist nahi karta | Error screen / back |
| `422` | Case abhi `accepted` nahi, ya storage full (`code: storage_full`) | Inline error message, `message` field directly dikhao |
| `429` | Rate limit (document upload: 20/min, general API: 60/min) | "Please slow down" toast, thodi der baad retry |

---

## 10. Testing checklist (fix verify karne ke liye)

1. Device A se message bhejo, Device B socket se connected ho (app foreground) - **connect hote hi** Device B automatically `delivered` call kare, bina chat screen khole bhi.
2. Device A par tick turant `sent -> delivered` banna chahiye (WebSocket event se, REST poll ki zaroorat nahi).
3. Device B chat screen kholo - Device A ka tick `delivered -> read` banna chahiye.
4. Device B ko **push notification nahi** aana chahiye agar woh already socket-connected tha jab message aaya (delivered turant ho gaya tha).
5. Device B ko **push aana chahiye** agar app background/socket-disconnected tha.
6. Network off/on karke reconnect simulate karo - reconnect hone par bhi `delivered` ack dobara jaana chahiye (idempotent, crash nahi hona chahiye).

---

## 11. Summary - kahan kya karna hai

| Symptom jo report hua | Root cause | Fix kahan |
|---|---|---|
| Sender ko hamesha 1 grey tick | `delivered`-ack kabhi call nahi hota tha | **Frontend** - section 3 implement karo |
| Online hone par bhi push notification | Same root cause - backend ka skip-logic `delivered_at` pe depend karta hai | **Frontend** - section 3 implement karo (backend already sahi hai) |

Backend side kuch change nahi karna - endpoints, events, skip-logic sab already shipped aur tested hain.
