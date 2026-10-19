# 11 - Chat Delivery & Read Receipts (Sent / Delivered / Seen) - Implementation Guide

> **Status: SHIPPED (backend ban chuka hai).** Yeh doc jis spec ke saath likha gaya tha, usi shape mein implement ho chuka hai - migration, dono naye events, dono naye/updated endpoints, resource field sab live hain. **Live/authoritative API contract ab `04-realtime-chat.md` (section 3-4) mein hai** - yeh doc ab sirf build-notes/reference ke taur par reh gaya hai (edge cases, test list, build order ka history). Frontend integration ke liye bhi `04-realtime-chat.md` hi follow karo.

Lawyer case accept karne ke baad jo chat khulti hai (`04-realtime-chat.md`), usme **WhatsApp jaisa 3-state tick system** chahiye:

| UI | State | Matlab |
|---|---|---|
| ✓ (1 grey tick) | **Sent** | Message server pe save ho gaya |
| ✓✓ (2 grey tick) | **Delivered** | Doosri party ke device tak pahunch gaya (chat khula ho ya na ho) |
| ✓✓ (2 blue tick) | **Read / Seen** | Doosri party ne chat khol ke dekh liya |

Yeh guide **backend banane wale** aur **frontend banane wale**, dono ke liye hai - pehle data model + backend endpoints (jo banane hain), phir client integration flow.

---

## 1. Abhi kya hai, kya badlega

| | Abhi (`04-realtime-chat.md`) | Iske baad |
|---|---|---|
| Column | `case_messages.read_at` (nullable timestamp) | `+ delivered_at` (nayi column) |
| Resource field | `"read": true` / `false` | `"status": "sent" \| "delivered" \| "read"` (boolean `read` field **hat jaayega** - breaking change, ek hi jagah use ho raha hai abhi isliye safe hai) |
| Endpoints | `POST /messages/read` | `+ POST /messages/delivered` (naya), `POST /messages/read` same URL lekin naya behaviour |
| Broadcast events | sirf `MessageSent` | `+ MessagesDelivered`, `+ MessagesRead` |

---

## 2. Data model

### 2.1 Migration

```php
Schema::table('case_messages', function (Blueprint $table) {
    $table->timestamp('delivered_at')->nullable()->after('sender_id');
    $table->index('delivered_at');
});
```

### 2.2 `App\Models\CaseMessage` - changes

```php
protected function casts(): array
{
    return [
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];
}

public function scopeUndelivered(Builder $query): Builder
{
    return $query->whereNull('delivered_at');
}

// scopeUnread() jaisa hi hai, waise hi rahega
```

### 2.3 `App\Http\Resources\CaseMessageResource` - `status` field

```php
'status' => match (true) {
    $this->read_at !== null => 'read',
    $this->delivered_at !== null => 'delivered',
    default => 'sent',
},
```

`is_mine` field waisa hi rehta hai - **frontend ko ticks sirf `is_mine: true` wale messages par dikhane hain**, doosri party ke bheje messages par ticks ka koi matlab nahi hai (woh unka apna sent-status hai, tumhara nahi).

---

## 3. Delivery kaise detect hogi

"Delivered" ka matlab hai message doosri party ke device tak pahuncha - **chat screen khuli ho, zaroori nahi**. Do trigger:

1. **Client online/connected ho jaaye** - jaise hi B ka app `case.{id}` WebSocket channel se connect hota hai (ya app foreground mein aake cases list refresh karta hai), client turant ek ack bhejta hai: `POST /cases/{case}/messages/delivered`. Server us case ke saare "B ko bheje gaye, abhi tak undelivered" messages (`scopeUndelivered()` + `sender_id != B`) ko `delivered_at = now()` kar deta hai.
2. **B offline tha** - jab bhi wapas online aake upar wala ack call chale, tabhi delivered hoga. Isliye A ke ticks kabhi kabhi 1 grey se seedha 2 grey "jump" karte dikhenge jab B online aaye - **yeh bug nahi hai, WhatsApp bhi aisa hi karta hai**.

---

## 4. Naye Broadcast events

Dono `App\Events\MessageSent` jaisi hi shape ke hain - `ShouldBroadcastNow` (turant, queue mein nahi - taaki ticks turant update hon), `case.{caseId}` private channel par.

### 4.1 `App\Events\MessagesDelivered`

```php
class MessagesDelivered implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $caseId,
        public readonly string $deliveredBy,   // jisne ack bheja (receiver ka user_id)
        public readonly string $deliveredAt,   // ISO8601
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("case.{$this->caseId}");
    }

    public function broadcastAs(): string
    {
        return 'messages.delivered';
    }

    public function broadcastWith(): array
    {
        return ['delivered_by' => $this->deliveredBy, 'delivered_at' => $this->deliveredAt];
    }
}
```

### 4.2 `App\Events\MessagesRead` - bilkul same shape, bas:
- `broadcastAs()` → `'messages.read'`
- payload: `read_by`, `read_at`

**Payload deliberately chhota rakha hai** - poori message-id list nahi bheji, bas "is time tak (`delivered_at`/`read_at`) ke saare" - frontend apne `is_mine: true` messages mein se jin ka `created_at <= event.delivered_at` (ya `read_at`) hai, unka status bump kar dega. Isse payload size chahe jitne messages ek saath delivered/read hon, hamesha chhota rehta hai.

---

## 5. Controller / Endpoint changes

### 5.1 `POST /cases/{case}/messages/delivered` - **naya**

`app/Http/Controllers/Api/V1/CaseMessageController.php` mein naya method:

```php
public function delivered(Request $request, LegalCase $case): JsonResponse
{
    $user = $request->user('sanctum');
    $this->authorizeParticipant($request, $case);

    $now = now();
    $updated = $case->messages()->undelivered()->where('sender_id', '!=', $user->user_id)
        ->update(['delivered_at' => $now]);

    if ($updated > 0) {
        broadcast(new MessagesDelivered($case->id, $user->user_id, $now->toIso8601String()));
    }

    return response()->json(['message' => 'Marked as delivered.']);
}
```

Route (`routes/api.php`, `cases` prefix ke andar):
```php
Route::post('{case}/messages/delivered', [CaseMessageController::class, 'delivered'])->name('messages.delivered');
```

### 5.2 `POST /cases/{case}/messages/read` - update

Ab `delivered_at` bhi backfill karega (read implies delivered - agar kisi wajah se delivered-ack miss ho gaya tha) aur naya event broadcast karega:

```php
public function markRead(Request $request, LegalCase $case): JsonResponse
{
    $user = $request->user('sanctum');
    $this->authorizeParticipant($request, $case);

    $now = now();
    $updated = $case->messages()->unread()->where('sender_id', '!=', $user->user_id)
        ->update(['read_at' => $now, 'delivered_at' => $now]); // delivered_at already set rahe toh overwrite se koi farak nahi padta - COALESCE bhi use kar sakte ho agar original delivered_at preserve karna ho

    if ($updated > 0) {
        broadcast(new MessagesRead($case->id, $user->user_id, $now->toIso8601String()));
    }

    return response()->json(['message' => 'Marked as read.']);
}
```

### 5.3 `POST /cases/{case}/messages` (naya message bhejna) - koi change nahi

Naya message hamesha `delivered_at: null, read_at: null` (`status: "sent"`) se start hota hai - `store()` method waisa hi rahega.

---

## 6. Response examples (planned shape)

`GET /cases/{case}/messages`:
```json
{
  "data": [
    { "id": 102, "case_id": 42, "sender_id": "...", "is_mine": true, "body": "Sure, 10am works.", "status": "read", "created_at": "..." },
    { "id": 101, "case_id": 42, "sender_id": "...", "is_mine": false, "body": "When is the hearing?", "status": "read", "created_at": "..." }
  ]
}
```

WebSocket event `messages.delivered`:
```json
{ "delivered_by": "448dc022-5370-43e7-bc16-a60c8a77427e", "delivered_at": "2026-10-19T12:05:30+00:00" }
```

WebSocket event `messages.read`:
```json
{ "read_by": "448dc022-5370-43e7-bc16-a60c8a77427e", "read_at": "2026-10-19T12:06:10+00:00" }
```

---

## 7. Client/Frontend integration flow

```
App launch / case list screen par -> WebSocket connect ho jaata hai case.{id} channel(s) par
   |
   v
Connect hote hi: POST /cases/{id}/messages/delivered   [turant ack - chat screen khula ho na ho]
   |
   |  Doosri taraf wala A yeh "messages.delivered" event WebSocket se sunta hai
   v
A apne is_mine messages jinka created_at <= delivered_at hai, unke ticks 1 grey -> 2 grey kar deta hai
   |
   |  B actually chat screen open/focus kare
   v
POST /cases/{id}/messages/read
   |
   v
A "messages.read" event sunta hai -> ticks 2 grey -> 2 blue
```

**Ticks sirf `is_mine: true` messages par dikhao.** Doosri party ke message par tumhe kabhi tick nahi dikhani (woh unka status hai).

**Reconnect/foreground handling:** app background se foreground mein aaye, ya WebSocket reconnect ho (dropped connection ke baad), tab bhi `POST /messages/delivered` dobara call karo - idempotent hai (already-delivered messages par koi asar nahi, sirf naye jo iss beech aaye unhe deliver mark karega).

**`GET /messages` call khud delivery/read status change nahi karta** - sirf current status dikhata hai (poll/refresh ke liye). Delivered/read sirf upar wale do explicit calls se set hote hain.

---

## 8. Edge cases / decisions

- **Multiple devices (ek hi account, 2 phones)** - v1 scope mein nahi hai; jo bhi device pehle ack kare wahi delivered/read mark kar dega, doosre device ka alag se track nahi hoga (abhi `DeviceToken` per-device hai FCM ke liye, lekin per-device read-status nahi banaya ja raha).
- **Race: read se pehle delivered ack na aaya ho** - `markRead()` khud `delivered_at` bhi set kar deta hai (section 5.2), isliye koi inconsistent state nahi ban sakta (read hamesha delivered implicitly bhi hota hai).
- **Group/broadcast nahi hai** - yeh sirf 1:1 (client-advocate) case chat hai, jaisa pehle se hai.

---

## 9. Tests (jab implement ho, yeh likhne hain)

- `delivered()`: doosri party ke undelivered messages ko deliver karta hai; apne khud ke bheje messages affect nahi hote; event sirf tab broadcast hota hai jab kam se kam 1 message update hua ho (0 updates par koi event noise nahi).
- `markRead()`: `read_at` + `delivered_at` dono set hote hain ek saath; `MessagesRead` event sahi payload ke saath fire hota hai.
- `CaseMessageResource`: `status` teeno values sahi derive karta hai (`sent`/`delivered`/`read`) from `read_at`/`delivered_at` combinations.
- Broadcast: `Event::fake([MessagesDelivered::class, MessagesRead::class])` se assert karo ki sahi channel/payload par fire hua.

---

## 10. Build order (jab shuru karna ho)

1. Migration (`delivered_at` column).
2. `CaseMessage` model + `CaseMessageResource` (`status` field).
3. Do naye Event classes.
4. `CaseMessageController::delivered()` + route + `markRead()` update.
5. Tests (section 9).
6. `04-realtime-chat.md` update karo - naya `status` field, naya endpoint, naye events - taaki woh doc bhi real code match kare (abhi woh `read: true/false` wala purana shape hi dikhayega jab tak yeh ship na ho).
7. Frontend: ticks UI + connect/foreground par delivered-ack + chat-open par read-ack (section 7).
