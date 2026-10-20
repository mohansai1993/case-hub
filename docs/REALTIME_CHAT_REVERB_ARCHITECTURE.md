# Real-Time Chat (Laravel Reverb) - Verified Architecture

> **Document purpose:** a single, source-of-truth trace of how CaseHub's real-time chat is implemented in code and deployed in production, for engineers who need to debug or extend it. Every claim below is labeled as one of:
> - **[SERVER FACT]** - given to this analysis as already-verified production state (not re-derived from code).
> - **[CODE]** - verified directly in this repository, with file:line.
> - **[OBSERVED LOG]** - seen in production application logs earlier in this project's working session (not server facts, not source code - included because they corroborate behavior).
> - **[ASSUMPTION / UNVERIFIED]** - not confirmed by any of the above; flagged explicitly rather than silently treated as true.

No code, configuration, or infrastructure was changed while producing this document. See the "Files touched" note at the end.

---

## 1. Executive Summary

CaseHub's per-case chat (`04-realtime-chat.md`, `11-chat-read-receipts-implementation-guide.md`) is built on **Laravel Reverb** as the WebSocket server, with a **private** (not presence) channel per case. Message send, delivered-ack, and read-ack are plain Sanctum-authenticated REST endpoints; each one additionally broadcasts a small event over Reverb so both participants' clients update instantly. Redis is configured in production, but **[CODE]** confirms it backs the **cache store / queue "should-I-restart" check** - not Reverb's transport and not the broadcast connection. Reverb itself runs single-instance, in-memory, with Redis-based horizontal scaling explicitly off by default. **[SERVER FACT]** production fronts the internal Reverb process (`127.0.0.1:8090`) with Apache as a TLS-terminating WebSocket + HTTP reverse proxy on `rev.beanrev.org:443`, and both a local and a public WebSocket handshake have been confirmed to succeed (HTTP 101).

What is **not** established by anything available to this analysis: whether the mobile app actually keeps that public WebSocket connection open and listens to the three chat events live, versus falling back to polling. That is outside this repository (no mobile client code here) and is called out in section 9 and 15.

---

## 2. Verified Production Deployment Architecture **[SERVER FACT]**

As supplied for this task (not re-verified here beyond cross-referencing against code in later sections):

| Item | Value |
|---|---|
| App directory | `/var/www/html/case-hub` |
| Public Reverb domain | `rev.beanrev.org`, HTTPS/WSS, port 443 |
| Internal Reverb listener | `127.0.0.1:8090` |
| Reverb startup log | `INFO Starting server on 127.0.0.1:8090 (rev.beanrev.org).` |
| Supervisor processes | `casehub-reverb` (RUNNING, ~2 days uptime), `casehub-worker:casehub-worker_00` (RUNNING) |
| Reverse proxy | Apache 2 (not Nginx) |
| TLS cert | Let's Encrypt, `/etc/letsencrypt/live/rev.beanrev.org/` |
| Redis | `predis` client, `127.0.0.1:6379` |

---

## 3. Text-Based Architecture Diagram

```
                                   Mobile App (Client / Lawyer)
                                             |
                    -------------------------+-------------------------
                    |                                                 |
             REST (Sanctum bearer)                              WebSocket (wss)
                    |                                                 |
                    v                                                 v
      Apache :443 -> PHP-FPM (routes/api.php)            Apache :443 (rev.beanrev.org)
                    |                                      ProxyPass /app/ -> ws://127.0.0.1:8090/app/
      CaseMessageController                                           |
         - store()      -> DB insert + broadcast()->toOthers()        v
         - delivered()  -> DB update + broadcast()              Reverb process (casehub-reverb,
         - markRead()   -> DB update + broadcast()               Supervisor-managed)
                    |                                            listening on 127.0.0.1:8090
                    |  (same request, ShouldBroadcastNow,              |
                    |   synchronous HTTP publish call)                 |
                    `-------------------------------------------------'
                         Laravel's Reverb broadcaster publishes to
                         the SAME public host:port as the clients use
                         (config/broadcasting.php - see section 6.3)

      Queued side-channel (not the live-update path):
      CaseMessageController::store() -> NewCaseMessage notification
         -> queued (casehub-worker, QUEUE_CONNECTION=database)
         -> delayed 15s -> checks message.delivered_at -> FCM push (if still null)
```

---

## 4. Reverb Startup and Supervisor Process Lifecycle **[SERVER FACT - confirmed by running the verification commands]**

Both Supervisor program configs were read directly:

```ini
[program:casehub-reverb]
command=php /var/www/html/case-hub/artisan reverb:start --host=127.0.0.1 --port=8090
directory=/var/www/html/case-hub
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/case-hub/storage/logs/reverb.log
stopwaitsecs=3600
```

```ini
[program:casehub-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/case-hub/artisan queue:work redis --sleep=3 --tries=3 --timeout=120
directory=/var/www/html/case-hub
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/case-hub/storage/logs/worker.log
stopwaitsecs=3600
```

- **Reverb's bind address comes from explicit CLI flags** (`--host=127.0.0.1 --port=8090`), **not** from `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` env vars. This resolves the discrepancy raised earlier in this document (section 6.2) - the `.env` excerpt wasn't incomplete, the bind address is simply set on the command line instead of via env.
- Both processes: `autostart=true`, `autorestart=true`, run as `user=www-data`, logs go to `storage/logs/reverb.log` / `storage/logs/worker.log`. **A crash of either process will auto-restart.** A full server reboot will bring them back *if* Supervisor itself is registered as a boot service (not re-verified here, but this is Supervisor's standard behavior once installed via its own init/systemd unit).
- **Worker queue connection is explicitly `redis`**, not `database` - this corrects an assumption made earlier in this document based only on `config/queue.php`'s *default* (`database`); production overrides it. See section 10 for what this means for the Redis-crash incident.
- `casehub-worker`'s command has **no `--max-time` and no `--memory` flag**. For comparison, another app on the same box (`fintech-saas`, confirmed unrelated to CaseHub - seen only via `ps aux`, not inspected further) runs its workers with `--max-time=3600 --memory=256`. `--max-time` makes the worker process voluntarily exit and let Supervisor restart it periodically - this is the standard mitigation for exactly the kind of long-lived-connection crash seen in section 12 (`Predis\Connection\ConnectionException: Stream is already at the end`, which only happens because the worker is a days-old long-running process holding a stale Redis socket). **Recommended (not applied - this is server config, outside this analysis's scope): add `--max-time=3600` to `casehub-worker`'s command.**
- **`supervisorctl status casehub-worker` (bare name) returns `ERROR (no such process)`** - this is a naming gotcha, not a real outage: the actual registered process name is `casehub-worker:casehub-worker_00` (from `process_name=%(program_name)s_%(process_num)02d`), confirmed separately both by the originally-supplied server facts (which listed it with that exact compound name) and by `ps aux` showing the process genuinely running (`pid 825`, started `Oct17`). Use `sudo supervisorctl status casehub-worker:casehub-worker_00` (or no argument, to list everything) to query it correctly.

---

## 5. Apache HTTPS/WSS Reverse Proxy Flow **[SERVER FACT provided, explained here]**

Two vhost files were supplied: `/etc/apache2/sites-enabled/rev.beanrev.org.conf` (HTTP) and `...-le-ssl.conf` (HTTPS, Certbot-managed). Only the HTTPS vhost's proxy directives were given:

```apache
ProxyPreserveHost On
ProxyPass /app/ ws://127.0.0.1:8090/app/
ProxyPassReverse /app/ ws://127.0.0.1:8090/app/
ProxyPass / http://127.0.0.1:8090/
ProxyPassReverse / http://127.0.0.1:8090/
```

What this does, based on standard Apache `mod_proxy_wstunnel`/`mod_proxy_http` semantics (the enabled modules list - `proxy`, `proxy_http`, `proxy_wstunnel`, `ssl`, `headers` - supports this reading):

- Requests to `/app/...` (Reverb/Pusher-protocol's WebSocket upgrade path, e.g. `/app/{key}?protocol=7&...`) are proxied with the **wstunnel** handler to the internal listener over plain `ws://` - Apache itself terminates the client-facing TLS (hence `wss://` externally, `ws://` internally over loopback, which is safe since it never leaves the box).
- Everything else (`/`) is proxied as plain HTTP to the same internal address - this is Reverb's **non-WebSocket HTTP surface** (its internal Pusher-compatible REST API that Laravel's own broadcaster calls to *publish* events - see section 6.3 - plus whatever health/info endpoints Reverb exposes at `/`).
- `ProxyPreserveHost On` forwards the original `Host: rev.beanrev.org` header through to Reverb, which matters because Reverb's app config (`config/reverb.php`) resolves the configured app by matching on host/key, not purely by key.
- `ProxyPassReverse` rewrites any `Location`/redirect headers Reverb might send back so they still point at the public hostname rather than `127.0.0.1:8090`.
- **[SERVER FACT]** the public root `https://rev.beanrev.org/` returns `404` - consistent with the proxy correctly reaching Reverb (which has no handler for `/`) rather than a proxy misconfiguration; the task explicitly notes this should not be read as a failure.
- **HTTP-to-HTTPS redirection - confirmed.** The plain-HTTP vhost (`rev.beanrev.org.conf`, read directly) contains:
  ```apache
  RewriteEngine on
  RewriteCond %{SERVER_NAME} =rev.beanrev.org
  RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
  ```
  This is a standard Certbot-inserted permanent (301) redirect to HTTPS, gated on the exact `ServerName` match - any plain-HTTP request to this host is redirected before Apache's `ProxyPass` rules in that vhost would even run (the `ProxyPass`/`ProxyPassReverse` lines present in the `:80` vhost are themselves dead code once the rewrite fires unconditionally for this `ServerName`, since the rewrite has no further conditions excluding `/app/`). No plaintext WebSocket or API traffic reaches this host.

---

## 6. Laravel Broadcasting Configuration **[CODE]**

### 6.1 `.env` -> `config/broadcasting.php`

`config/broadcasting.php:33-47` (file is the unmodified Laravel/Reverb package stub - confirmed by diffing its shape against the framework default):

```php
'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    'options' => [
        'host' => env('REVERB_HOST'),
        'port' => env('REVERB_PORT', 443),
        'scheme' => env('REVERB_SCHEME', 'https'),
        'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
    ],
],
```

`.env`: `BROADCAST_CONNECTION=reverb` -> `config('broadcasting.default')` is `reverb` -> every `broadcast()` call in the app uses this connection.

### 6.2 `.env` -> `config/reverb.php` (the server process itself)

`config/reverb.php:30-33` - what the Reverb **process** actually binds to:
```php
'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
'port' => env('REVERB_SERVER_PORT', 8080),
```

This is a **different pair of env vars** (`REVERB_SERVER_HOST`/`REVERB_SERVER_PORT`) from the ones listed in this task's "Verified Production Server Configuration" section (`REVERB_HOST`/`REVERB_PORT`, which default this file's **public-facing `apps.apps[0].options`**, at `config/reverb.php:68-73`, to `env('REVERB_PORT', 443)` / `env('REVERB_SCHEME', 'https')`).

**Resolved (section 4): neither env var is set.** The actual bind address comes from explicit CLI flags on the Supervisor command - `php artisan reverb:start --host=127.0.0.1 --port=8090` - which override `config/reverb.php`'s `servers.reverb.host`/`port` defaults directly, bypassing `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` entirely. The `.env` excerpt wasn't incomplete; this particular setting simply isn't env-driven in this deployment.

### 6.3 A noteworthy (not necessarily wrong) architectural detail: self-referential publish path

Because `config/broadcasting.php`'s `reverb.options` reuses `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` (`rev.beanrev.org:443:https`), **every `broadcast()` call made by PHP itself** (i.e., the backend's own outbound publish-to-Reverb request, not the client's connection) goes out to the **public HTTPS domain**, not directly to `127.0.0.1:8090`. Concretely: `CaseMessageController::store()` calling `broadcast(new MessageSent($message))->toOthers()` makes Laravel's Reverb broadcaster issue an HTTP request to `https://rev.beanrev.org/...`, which round-trips out through DNS resolution + TLS handshake + the same Apache proxy, back down to the same local Reverb process.

This is the standard way the Laravel/Reverb package is documented to be configured (one `REVERB_HOST`/`PORT`/`SCHEME` triplet for both client-connect and server-publish), so it is **not flagged as a bug**. It is flagged because it is a plausible (not confirmed) contributor to any measured "message arrives late" symptom raised earlier in this project: the PHP backend's own broadcast call is not a pure-localhost round trip, it depends on the box's own DNS + outbound network path + Apache + TLS being fast, on every single message send. **[ASSUMPTION / UNVERIFIED]** whether this adds meaningful latency in practice - it was not measured; if message delivery is being investigated for slowness, timing this specific `broadcast()->toOthers()` call server-side (e.g. temporarily wrapping it with a `hrtime()` before/after and logging the delta) would directly confirm or rule this out, separately from the client-side WebSocket-connectivity theories already documented in `docs/12-chat-frontend-integration-guide.md` section 11.

---

## 7. Chat Message Creation and Event Dispatch Flow **[CODE]**

### 7.1 Message creation

`app/Http/Controllers/Api/V1/CaseMessageController.php:36-63` (`store()`):
1. `authorizeParticipant()` (line 39, delegates to `LegalCase::isParticipant()`, `app/Models/LegalCase.php:86-89`) - caller must be the case's `client_id` or `advocate_id`.
2. Case must be `CaseStatus::Accepted` (line 41-43) or `422`.
3. `CaseMessage::create(['case_id', 'sender_id', 'body'])` (line 45-49) - persisted immediately, synchronously, in the request.
4. `broadcast(new MessageSent($message))->toOthers()` (line 52) - see 7.2.
5. `Log::info('[chat:broadcast] message.sent dispatched...')` (line 54).
6. `NewCaseMessage` notification queued with a 15-second delay (line 58-60) - this is the FCM-push fallback path, not the live-update path; see section 11.

### 7.2 Events

All three events (`app/Events/MessageSent.php`, `MessagesDelivered.php`, `MessagesRead.php`) implement **`ShouldBroadcastNow`** (not plain `ShouldBroadcast`) - confirmed by `use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;` and `implements ShouldBroadcastNow` in each file. This means **broadcasting is synchronous, inline in the HTTP request** - it is never queued, never picked up by `casehub-worker`. (`ShouldBroadcast` would be queued via the `BroadcastEvent` job; `ShouldBroadcastNow` explicitly skips that.)

| Event | File | Channel | `broadcastAs()` | Payload (`broadcastWith()`) |
|---|---|---|---|---|
| `MessageSent` | `app/Events/MessageSent.php:25-45` | `PrivateChannel('case.' . $message->case_id)` | `message.sent` | `id`, `case_id`, `sender_id`, `body`, `created_at` |
| `MessagesDelivered` | `app/Events/MessagesDelivered.php:27-45` | `PrivateChannel('case.' . $caseId)` | `messages.delivered` | `delivered_by`, `delivered_at` |
| `MessagesRead` | `app/Events/MessagesRead.php:26-44` | `PrivateChannel('case.' . $caseId)` | `messages.read` | `read_by`, `read_at` |

`MessageSent::broadcastWith()` includes the message **body** in plaintext in the broadcast payload (`app/Events/MessageSent.php:38-44`) - see section 8 for the sensitivity note.

`->toOthers()` on the `MessageSent` broadcast (line 52 above) means the sender's own currently-open socket does not receive its own just-sent message back (standard Laravel/Pusher "exclude the originating socket" behavior, keyed off the `X-Socket-ID` header - documented for the frontend in `docs/04-realtime-chat.md` section 3). `MessagesDelivered`/`MessagesRead` broadcasts (`CaseMessageController.php:85`, `106`) do **not** call `->toOthers()` - both participants (including the one who just sent the ack) receive those.

---

## 8. Channel Authorization and Security **[CODE]**

`routes/channels.php:11-15`:
```php
Broadcast::channel('case.{caseId}', function (User $user, int $caseId) {
    $case = LegalCase::find($caseId);
    return $case !== null && $case->isParticipant($user);
});
```

- **Channel type: private**, not presence. `grep -r "PresenceChannel"` across `app/` returns no matches - confirmed no presence channel is used anywhere in this codebase (so there is no live "who's currently connected" roster; the `is_online`/`last_active_at` presence shown in `GET /cases/{case}` is a separate, poll-based mechanism unrelated to Reverb - see `app/Http/Middleware/TouchLastActive.php` and `app/Models/User.php:168-172`, already documented in `docs/04-realtime-chat.md` section 5).
- Authorization runs through Laravel's standard `/broadcasting/auth` endpoint (Sanctum-authenticated), which calls this closure. A user who is not `client_id` or `advocate_id` on that case gets denied the channel subscription outright - they cannot eavesdrop on another case's messages via WebSocket even if they guessed the case ID.
- Sender/recipient/case/message ID validation for the REST side happens in the controller, not the broadcast layer: `authorizeParticipant()` (`CaseMessageController.php:113-116`) on every one of `index`/`store`/`delivered`/`markRead`; `delivered()`/`markRead()` additionally scope their `UPDATE` to `->where('sender_id', '!=', $user->user_id)` (lines 81, 102) so a participant can only ack the *other* party's messages, never their own.
- **Sensitive data in payloads**: `MessageSent`'s payload carries the message body in plaintext (section 7.2). This travels Laravel -> Reverb -> Apache (TLS) -> client, and Laravel -> Reverb is the self-referential HTTPS hop described in section 6.3 - also TLS-covered given `REVERB_SCHEME=https`. No payload travels over plaintext HTTP in the described topology, assuming the HTTP vhost genuinely redirects to HTTPS (unconfirmed, section 5). `MessagesDelivered`/`MessagesRead` payloads are deliberately minimal (just a user ID and a timestamp, no message content or ID list - by design, per `docs/11-chat-read-receipts-implementation-guide.md` section 4).

---

## 9. Mobile Application Connection Expectations - evidence-based only

This repository contains **no mobile/frontend client code** - everything in this section is inferred from (a) what the backend's documentation tells the frontend to do (`docs/04-realtime-chat.md`, `docs/12-chat-frontend-integration-guide.md`) and (b) what the channel-auth/broadcast code requires, **not** from inspecting an actual client implementation:

- The client needs a Pusher-protocol-compatible WebSocket library pointed at `wss://rev.beanrev.org:443`, key = `REVERB_APP_KEY`.
- It must call `POST /api/broadcasting/auth` (Sanctum bearer token) to subscribe to the private `case.{id}` channel - most Pusher-compatible SDKs do this automatically given an `authEndpoint`.
- It must listen for `message.sent`, `messages.delivered`, `messages.read` on that channel.
- Per `docs/12-chat-frontend-integration-guide.md` section 3, it must **explicitly** call `POST /cases/{case}/messages/delivered` on socket connect/reconnect - this is not automatic, and earlier production logs in this project's session showed `[chat:ack]` lines proving this call *is* reaching the backend (so, as of those logs, at least one client build does implement this).
- **[ASSUMPTION / UNVERIFIED]**: whether the mobile app keeps a persistent WebSocket connection open while merely "on the chat screen" versus reconnecting per-screen-visit, and whether it falls back to REST polling of `GET /messages` at any point. Nothing in this repository can confirm or rule this out - it requires inspecting the mobile app's own source or network logs.

---

## 10. Redis and Queue Worker Responsibilities **[CONFIRMED - .env values read directly]**

```
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

Both confirmed by `grep` on production's real `.env` (not just inferred from code defaults as earlier in this analysis). This updates what was previously flagged as unverified:

- **Redis backs two things in this app: the cache store AND the actual queue backend** - not just a cache-flag check as this document first (incorrectly, for lack of the real `.env`) hedged. `QUEUE_CONNECTION=redis` means `config/queue.php`'s `'redis'` connection (`connection: 'default'`, i.e. `REDIS_DB=0` per `config/database.php:153-160`) is where `casehub-worker` actually pulls and pushes jobs - confirmed independently by the Supervisor command itself: `php artisan queue:work redis ...` (section 4) explicitly names the `redis` connection.
- **Reverb's own scaling remains off**: `REVERB_SCALING_ENABLED` did not appear in the `.env` grep (still defaults to `config/reverb.php:38`'s `false`). So even though Redis is now confirmed heavily used, it is still **not** Reverb's transport and still **not** involved in broadcasting message fan-out - `BROADCAST_CONNECTION=reverb` (section 6.1) sends broadcasts straight to the Reverb driver, never through Redis.
- **The section-12 crash, re-examined with this confirmation**: `Illuminate\Queue\Worker->getTimestampOfLastQueueRestart()` reads the restart-flag via the **cache** store (confirmed `redis`, `REDIS_CACHE_DB=1` by default - a different logical DB from the queue's `REDIS_DB=0`). The worker that crashed was reading its *own* queue-restart cache flag over a stale Redis connection - consistent with `casehub-worker` being a days-old long-running process (`ps aux` showed it started `Oct17`, no `--max-time` to force periodic recycling - see section 4's recommendation).
- **What `casehub-worker` actually processes**: the only `ShouldQueue` notification in the chat path is `NewCaseMessage` (`app/Notifications/NewCaseMessage.php:23`). This is the **FCM push + in-app database notification** fallback, dispatched with a 15-second delay from `CaseMessageController::store()` (line 58-60) - **not** the live chat-update path (synchronous `ShouldBroadcastNow`, section 7.2). If `casehub-worker` is down, chat messages still broadcast live and still save to DB; only the delayed push/in-app notification is affected. `php artisan queue:failed` was run and returned **"No failed jobs found"** - no stuck/lost jobs from the earlier crash at the time of checking.
- **Shared server note**: `ps aux` incidentally showed a second, unrelated application (`fintech-saas`) running its own `queue:work redis` workers on the same box. Laravel's `config/database.php:150` auto-prefixes Redis keys by `APP_NAME` (`Str::slug(env('APP_NAME', 'laravel'), '_') . '_database_'`), which should keep the two apps' keys from colliding on the same Redis instance - this prefixing was not independently re-verified against each app's actual `APP_NAME`, but is the mechanism that would prevent collision if both truly point at the same Redis server/DB indices.
- **Push notifications**: `PUSH_DRIVER` did not appear in the `.env` grep, so it defaults to `config/firebase.php:7`'s `'firebase'` (not the `'log'` stand-in) - production is wired to send **real FCM pushes**, assuming its Firebase service-account credentials file and project ID are valid (not checked here - that's a separate, unverified detail: file existence/validity of `storage/app/firebase/service-account.json` or whatever `GOOGLE_CREDENTIALS_PATH` points to).

---

## 11. Read/Delivered Acknowledgement Flow **[CODE]**

`app/Http/Controllers/Api/V1/CaseMessageController.php`:

- **`delivered()` (lines 70-90)**: `UPDATE case_messages SET delivered_at = now() WHERE case_id = ? AND delivered_at IS NULL AND sender_id != :caller` (via `scopeUndelivered()`, `app/Models/CaseMessage.php:33-36`). If `$updated > 0`, broadcasts `MessagesDelivered`. Always logs `[chat:ack] delivered-ack received...` first (line 78), regardless of whether anything was pending - this was added specifically so production logs can prove the client is calling this endpoint at all.
- **`markRead()` (lines 93-111)**: same shape, but sets **both** `read_at` and `delivered_at` together (line 103) - "read implies delivered", so a missed delivered-ack doesn't leave a message stuck. Broadcasts `MessagesRead` if any row changed.
- **Status derivation for history**: `CaseMessageResource::toArray()` (`app/Http/Resources/CaseMessageResource.php:20-24`) - `read_at` set -> `"read"`; else `delivered_at` set -> `"delivered"`; else `"sent"`. This is a pure read of current DB state - `GET /messages` never itself changes status.
- Both endpoints scope their update to the *other* party's messages (`sender_id != caller`) - a user cannot mark their own sent messages as delivered/read via these endpoints.

---

## 12. Existing Logs and Troubleshooting Steps

**[OBSERVED LOG]** (from this project's working session, not from this task's supplied server facts) - two categories of log line exist in `storage/logs/laravel.log`, both added deliberately for this exact kind of debugging:

| Log line prefix | Where (file:line) | What it proves when present |
|---|---|---|
| `[chat:broadcast] message.sent dispatched...` | `CaseMessageController.php:54` | Backend saved the message and attempted the broadcast - always fires, near-instantly after save. |
| `[chat:ack] delivered-ack received...` | `CaseMessageController.php:78` | **The client actually called** `POST /messages/delivered` - fires unconditionally, even if nothing was pending. The single most useful line for proving/disproving "frontend isn't calling the ack endpoint." |
| `[chat:broadcast] messages.delivered dispatched...` | `CaseMessageController.php:86` | At least one message's delivered-ack resulted in a broadcast (i.e. something was actually pending). |
| `[chat:ack] read-ack received...` | `CaseMessageController.php:98` | The client called `POST /messages/read`. |
| `[chat:broadcast] messages.read dispatched...` | `CaseMessageController.php:107` | Read-ack resulted in a broadcast. |

A separate, unrelated **[OBSERVED LOG]** earlier in this session showed a `Predis\Connection\ConnectionException: Stream is already at the end` crash inside the queue worker's restart-check (section 10) - this killed that run of `casehub-worker` until Supervisor (if `autorestart=true`, unconfirmed per section 4) restarted it. This is a queue-worker/Redis resilience issue, not a chat/broadcast bug.

**Troubleshooting steps implied by the above** (none executed as part of this analysis):
1. `tail -f storage/logs/laravel.log | grep chat:` while reproducing a slow/missing message - if `[chat:ack]` lines never appear for the receiving user's session, the frontend is not calling the ack endpoints (client-side gap, documented in `docs/12-chat-frontend-integration-guide.md`).
2. `sudo supervisorctl status casehub-reverb casehub-worker` to confirm both are actually up at the moment of a reported issue.
3. Confirm `REVERB_SCALING_ENABLED`, `CACHE_STORE`, and `QUEUE_CONNECTION`'s real production values (none were in the supplied `.env` excerpt) - needed to close the gaps noted in sections 6.2 and 10.

---

## 13. Production Verification Commands - run, results folded into sections above

The commands below were actually executed against production (output captured and incorporated into sections 4, 5, 6.2, 10 above) - kept here as a record of what was checked and how, for next time:

```bash
sudo supervisorctl status casehub-reverb casehub-worker   # note: query casehub-worker:casehub-worker_00 for the worker specifically
sudo cat /etc/supervisor/conf.d/casehub-reverb.conf
sudo cat /etc/supervisor/conf.d/casehub-worker.conf
grep -E '^REVERB_SERVER_HOST|^REVERB_SERVER_PORT|^REVERB_SCALING_ENABLED|^CACHE_STORE|^QUEUE_CONNECTION|^PUSH_DRIVER' /var/www/html/case-hub/.env
sudo cat /etc/apache2/sites-enabled/rev.beanrev.org.conf
sudo ss -tlnp | grep 8090
ps aux | grep "queue:work"
php artisan queue:failed
```

**Still not run / not applicable without further access:**
```bash
# Confirm Firebase push credentials are actually a valid, present file (not just the driver choice)
ls -la /var/www/html/case-hub/storage/app/firebase/   # or wherever GOOGLE_CREDENTIALS_PATH points

# Tail chat-specific log lines live while reproducing a real slow-message report
tail -f /var/www/html/case-hub/storage/logs/laravel.log | grep --line-buffered 'chat:'

# Time the self-referential broadcast()->toOthers() publish call itself (section 6.3) -
# requires a temporary code change (not applied here) wrapping the call with hrtime() timing
```

---

## 14. Security Considerations and Operational Risks

- **Message body travels in the broadcast payload** (section 7.2, 8) - acceptable only as long as every hop is actually TLS (public leg confirmed via the 101 handshake; the Laravel-to-Reverb self-referential leg in section 6.3 is TLS by config but its HTTP-vhost-redirect behavior is unconfirmed per section 5).
- **No presence channel** means there is no cryptographic/channel-level proof of "who is currently connected" to show a live roster - the existing `is_online` field is a coarse, poll-based proxy (last API activity within 2 minutes), not a WebSocket fact. This is a known, documented simplification (`docs/04-realtime-chat.md` section 6), not a gap introduced by this analysis.
- **Synchronous broadcast on the request path** (`ShouldBroadcastNow`, section 7.2): if Reverb (or the self-referential HTTPS hop to it) is slow or unreachable, `POST /cases/{case}/messages` could be slow or throw, since the publish call is not wrapped in its own try/catch anywhere in `CaseMessageController::store()`. This was not reproduced or confirmed as happening in production - flagged as a design-level risk worth a deliberate decision (swallow-and-log vs. let it fail), not as an observed incident.
- **Queue worker fragility** (section 10, 12): a crashed `casehub-worker` silently stops only the FCM-push/in-app-notification path - chat itself keeps working. `autorestart=true` is confirmed (section 4), so a crash recovers within seconds either way; the real risk is the missing `--max-time`/`--memory` flags letting the same stale-Redis-connection crash recur periodically rather than being pre-empted by a scheduled recycle.

---

## 15. Known Limitations and Items Requiring Live End-to-End Testing

An HTTP 101 handshake (local and public, both confirmed) proves the TCP/TLS/HTTP-upgrade path works. Sections 4, 5, 6.2, and 10's open questions have since been resolved by running the verification commands (section 13). What remains genuinely unproven by anything available to this analysis:

1. That a real mobile client successfully authorizes the **private channel** (`/broadcasting/auth` with a real Sanctum token) and receives `message.sent`/`messages.delivered`/`messages.read` events live, end to end, under real-world mobile network conditions (not just a raw WebSocket handshake test).
2. That the previously-reported "message takes a long time to arrive" symptom is specifically caused by the self-referential publish hop (section 6.3) versus a client-side connectivity/polling gap (`docs/12-chat-frontend-integration-guide.md` section 11) versus something else - neither was measured; the self-referential hop is now a cheap thing to test (time the `broadcast()->toOthers()` call itself) given `casehub-reverb` is confirmed healthy and directly reachable.
3. Whether the Firebase service-account credentials file actually exists and is valid on disk (the *driver choice* is now confirmed as `'firebase'`, i.e. real pushes are attempted - not the `'log'` stand-in - but file presence/validity wasn't checked, see section 13).
4. Whether `casehub-worker`'s missing `--max-time`/`--memory` flags (section 4) will cause a recurrence of the section-12 Redis-connection crash - this is a risk assessment, not something that can be proven without waiting for (or forcing) another long-idle period.

---

## Files touched while producing this document

**Only this file was created: `docs/REALTIME_CHAT_REVERB_ARCHITECTURE.md`.**

No application code, configuration, environment files, dependencies, or Git state were modified, consistent with this task's safety constraints. All file reads were read-only inspection via this session's file-reading tools; no server, Apache, Supervisor, Redis, or TLS configuration was touched, and no services were restarted.
