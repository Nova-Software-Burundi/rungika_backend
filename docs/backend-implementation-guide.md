# Backend Implementation Guide

This document covers the 4 remaining backend-dependent items (§10.1–10.4) from the incremental improvements checklist. Each section specifies exactly what the Android client expects so the Laravel backend can be built to match.

---

## §10.1 Countries Endpoint

### Problem

The app currently falls back to a hardcoded list of 196 countries when the API fails. This fallback uses sequential IDs 1–196 (alphabetical order matching the `countries:seed` artisan command). If the backend returns different IDs or ordering, the country picker will be wrong.

### Endpoint

```
GET /api/mobile/countries
Authorization: Bearer <token>
Accept: application/json
```

### Response Format

Return a **JSON array** (not paginated) of country objects. Example:

```json
[
    { "id": 1, "code": "AF", "name": "Afghanistan", "flag_url": null, "phone_code": "+93" },
    { "id": 2, "code": "AL", "name": "Albania", "flag_url": null, "phone_code": "+355" },
    { "id": 3, "code": "DZ", "name": "Algeria", "flag_url": null, "phone_code": "+213" }
]
```

### Field Requirements

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | int | Yes | Primary key. Must match the `country_id` sent during registration. |
| `code` | string | Yes | ISO 3166-1 alpha-2 code (e.g. `"ZM"`, `"KE"`, `"NG"`). Case-sensitive. |
| `name` | string | Yes | Full country name. Must match exactly what appears in the seeder. |
| `flag_url` | string or null | No | URL to flag image. If null, client ignores it. |
| `phone_code` | string | Yes | International dialing code with `+` prefix (e.g. `"+260"`, `"+254"`, `"+234"`). |

### Validation Rules

- The array **must** be sorted alphabetically by `name` and contain all 196 countries.
- The `id` values **must** be sequential starting from 1, matching the insertion order in the `countries:seed` seeder.
- `code` must be unique per country.
- `phone_code` must include the `+` prefix.

### Where It Is Used

1. **Registration** (`RegisterFragment`): User picks a country → `country_id` is sent in `POST /mobile/auth/register`.
2. **Destinator phone country picker** (`CreateRemittanceFragment`): User picks a country code prefix for the destinator's phone number.
3. **Agent list filter** (`HomeFragment`): Country dropdown to filter agents by country.

### Error Handling

If the endpoint returns a non-200 status or malformed JSON, the client silently falls back to the hardcoded list. No toast or error shown to user.

### Quick Implementation (if `countries` table exists)

```php
// routes/api.php
Route::get('/mobile/countries', function () {
    return \App\Models\Country::orderBy('name')->get(['id', 'code', 'name', 'flag_url', 'phone_code']);
});
```

If no `countries` table exists, create a migration and seeder. The seeder must insert countries in **alphabetical order** so that the `id` values match the hardcoded fallback (Afghanistan=1, Albania=2, …, Zimbabwe=196).

---

## §10.2 Remittance Events Array

### Problem

The `GET /mobile/remittances/{id}` endpoint currently returns a remittance object without an `events` array. The client has a hardcoded 4-step status timeline (Pending → Accepted → Executed → Completed) that works but doesn't show real audit trail data. Adding an `events` array enables a proper event-driven timeline.

### Endpoint (already exists)

```
GET /api/mobile/remittances/{id}
Authorization: Bearer <token>
```

### Required Change

Add an `events` array to the existing remittance detail response. Example:

```json
{
    "id": 42,
    "reference": "MT-20260713-ABC123",
    "status": "executed",
    "send_amount": 500.00,
    "send_currency": "USD",
    "destinator_name": "Jane Doe",
    "destinator_phone": "+260970000001",
    "destinator_address": "Lusaka, Zambia",
    "requester_proof_path": "/storage/proofs/abc.jpg",
    "agent_proof_path": null,
    "requester_debt": false,
    "agent_debt": true,
    "created_at": "2026-07-13T10:30:00.000000Z",
    "agent": { "id": 5, "name": "John Agent" },
    "requester": { "id": 1, "name": "Mary Requester" },
    "payment_method": { "id": 1, "name": "Bank Transfer" },
    "events": [
        {
            "id": 1,
            "actor_type": "requester",
            "actor_name": "Mary Requester",
            "from_status": null,
            "to_status": "pending",
            "description": "Remittance created",
            "created_at": "2026-07-13T10:30:00.000000Z"
        },
        {
            "id": 2,
            "actor_type": "agent",
            "actor_name": "John Agent",
            "from_status": "pending",
            "to_status": "accepted",
            "description": "Order accepted by agent",
            "created_at": "2026-07-13T10:35:00.000000Z"
        },
        {
            "id": 3,
            "actor_type": "requester",
            "actor_name": "Mary Requester",
            "from_status": null,
            "to_status": null,
            "description": "Proof of payment uploaded",
            "created_at": "2026-07-13T10:40:00.000000Z"
        },
        {
            "id": 4,
            "actor_type": "agent",
            "actor_name": "John Agent",
            "from_status": "accepted",
            "to_status": "executed",
            "description": "Payment executed, proof uploaded",
            "created_at": "2026-07-13T11:00:00.000000Z"
        }
    ]
}
```

### Event Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | int | Yes | Unique event ID |
| `actor_type` | string | Yes | One of: `"requester"`, `"agent"`, `"system"` |
| `actor_name` | string | Yes | Display name of who triggered the event |
| `from_status` | string or null | Yes | Status before transition. Null for non-transition events (e.g. proof upload). |
| `to_status` | string or null | Yes | Status after transition. Null for non-transition events. |
| `description` | string | Yes | Human-readable description shown in the timeline |
| `created_at` | string | Yes | ISO 8601 timestamp |

### Events to Record

| Action | actor_type | from_status | to_status | description |
|---|---|---|---|---|
| Remittance created | `"requester"` | null | `"pending"` | `"Remittance created"` |
| Agent accepts | `"agent"` | `"pending"` | `"accepted"` | `"Order accepted by agent"` |
| Requester uploads proof | `"requester"` | null | null | `"Proof of payment uploaded"` |
| Agent executes | `"agent"` | `"accepted"` | `"executed"` | `"Payment executed, proof uploaded"` |
| Agent executes (no proof) | `"agent"` | `"accepted"` | `"executed"` | `"Payment executed (proof pending)"` |
| Requester confirms | `"requester"` | `"executed"` | `"completed"` | `"Remittance confirmed completed"` |
| Requester cancels | `"requester"` | `"pending"` | `"cancelled"` | `"Remittance cancelled"` |
| Support ticket opened | `"system"` | null | null | `"Support ticket #N opened"` |

### Implementation Notes

- Events should be created via an `observe()` on the Remittance model, or manually in each action controller method.
- Sort events by `id` ascending (chronological order).
- The client currently renders the hardcoded stepper regardless of events. Once the backend provides events, a future client update can render the actual event list. **This is backwards-compatible** — if `events` is missing or empty, the client falls back to the hardcoded stepper.

### Optional: Add `events` to Trade Detail Too

The `Trade` model in the client (`ApiModels.java` line 255) already parses `events` as `List<TradeEvent>`. If you want to display events on trade detail screens too, add the same `events` array to `GET /mobile/trades/{id}`.

---

## §10.3 Proof Upload via Multipart

### Problem

The client sends proof files via `multipart/form-data` to two endpoints. The backend must accept these multipart requests correctly.

### Endpoint 1: Requester Proof Upload

```
POST /api/mobile/remittances/{id}/requester-proof
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `proof` | file | Yes | Image or PDF. MIME types: `image/jpeg`, `image/png`, `application/pdf`, `image/gif`, `image/webp`. Max 5MB recommended. |

No text fields are sent — just the file.

**Success response:** Any 2xx with a JSON body. The client discards the response body and just checks for non-error status.

```json
{ "message": "Proof uploaded successfully", "path": "/storage/proofs/abc.jpg" }
```

**Error response:**

```json
{ "message": "Validation failed" }
```

Client shows the `message` field as a toast.

### Endpoint 2: Agent Order Execute (with optional proof)

```
POST /api/mobile/agent/orders/{id}/execute
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `notes` | text | No | Execution notes (e.g. "Paid via bank transfer ref #12345") |
| `payout_reference` | text | No | Payout reference number |
| `proof` | file | No | Proof of execution image/PDF |

The client sends this as multipart **only when a proof file is selected**. When no file is selected, it sends a plain JSON body instead:

```json
POST /api/mobile/agent/orders/{id}/execute
Content-Type: application/json

{ "notes": "Paid via bank transfer", "payout_reference": "REF-12345" }
```

**Backend must accept BOTH content types** on this endpoint — `application/json` (no proof) and `multipart/form-data` (with proof).

**Success response:** Any 2xx with JSON body. Client discards the body.

```json
{ "message": "Order executed successfully" }
```

### Endpoint 3: Ticket Message with Attachment (new)

```
POST /api/mobile/support/tickets/{id}/messages
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

**Request body:**

| Field | Type | Required | Notes |
|---|---|---|---|
| `message` | text | Yes | Message text |
| `attachment` | file | No | Image attachment. Client sends `Content-Disposition: form-data; name="attachment"; filename="<actual_filename>"` |

**Success response:**

```json
{
    "id": 5,
    "message": "Here is the receipt",
    "attachment_path": "/storage/ticket-attachments/xyz.jpg",
    "created_at": "2026-07-26T12:00:00.000000Z",
    "user": { "id": 1, "name": "John Doe" }
}
```

When no attachment is selected, the client sends the same endpoint as plain JSON:

```json
{ "message": "Thanks for your help" }
```

**Backend must accept both** `application/json` and `multipart/form-data` on this endpoint.

### Laravel Implementation Hint

Use `$request->file('proof')` for multipart, `$request->input()` for JSON. Detect content type:

```php
public function storeProof(Request $request, $id)
{
    $remittance = Remittance::findOrFail($id);

    if ($request->hasFile('proof')) {
        $path = $request->file('proof')->store('remittance-proofs', 'public');
        $remittance->requester_proof_path = '/storage/' . $path;
        $remittance->requester_debt = false;
        $remittance->save();
    }

    return response()->json(['message' => 'Proof uploaded successfully']);
}
```

### Content-Type Detection

The client's `MultipartRequest.java` generates its own boundary string and sets `Content-Type: multipart/form-data; boundary=boundary<timestamp>`. It explicitly removes any existing `Content-Type` header before sending. The backend must handle this correctly (Laravel does by default).

---

## §10.4 Push Notification Payload Format

### Problem

The client extracts specific data fields from FCM push notifications to navigate to the correct screen. If the backend sends only a `notification` payload (title/body) without a `data` payload, the client won't know which screen to open.

### Required Data Payload

Every push notification must include a `data` payload (not just `notification`). FCM allows sending both simultaneously.

**FCM HTTP v1 format:**

```json
{
    "token": "<device_fcm_token>",
    "notification": {
        "title": "Order Accepted",
        "body": "John Agent accepted your remittance MT-20260713-ABC123"
    },
    "data": {
        "title": "Order Accepted",
        "body": "John Agent accepted your remittance MT-20260713-ABC123",
        "remittance_id": "42",
        "navigate_to_tab": "agent"
    }
}
```

### Data Fields

| Key | Type | Required | Values | Notes |
|---|---|---|---|---|
| `title` | string | Yes | Any | Used as fallback if `notification.title` is null |
| `body` | string | Yes | Any | Used as fallback if `notification.body` is null |
| `remittance_id` | string | Conditional | Integer as string | Set when notification relates to a remittance. Must be a valid integer string (e.g. `"42"` not `42`). |
| `ticket_id` | string | Conditional | Integer as string | Set when notification relates to a support ticket. |
| `navigate_to_tab` | string | Conditional | `"agent"` | Only needed when the recipient is the agent (routes to agent dashboard tab instead of orders tab). Omit or set to `null`/`"orders"` for requester. |

### Navigation Logic

The client synthesizes navigation based on which IDs are present:

| remittance_id | ticket_id | navigate_to_tab | Client navigates to |
|---|---|---|---|
| `"42"` | absent | absent | Orders tab → Order Detail (remittance 42) |
| `"42"` | absent | `"agent"` | Agent Dashboard tab → Agent Order Detail (remittance 42) |
| absent | `"7"` | absent | Support tab → Ticket Detail (ticket 7) |

**Priority:** `remittance_id` takes precedence over `ticket_id`. If both are set, the client navigates to the remittance.

### Notification Types to Implement

| Event | Recipient | remittance_id | ticket_id | navigate_to_tab |
|---|---|---|---|---|
| New remittance created | Agent | `"id"` | — | `"agent"` |
| Agent accepted order | Requester | `"id"` | — | — |
| Agent executed order | Requester | `"id"` | — | — |
| Requester confirmed order | Agent | `"id"` | — | `"agent"` |
| Requester cancelled order | Agent | `"id"` | — | `"agent"` |
| Proof uploaded | Other party | `"id"` | — | — |
| New support ticket message | Other party | — | `"ticket_id"` | — |
| Support ticket status changed | Both | — | `"ticket_id"` | — |
| Account approved | User | — | — | — |

### FCM Legacy vs HTTP v1

**If using FCM legacy API (`/v1/{project}/messages:send`):**

```json
{
    "message": {
        "token": "<device_token>",
        "notification": {
            "title": "Order Accepted",
            "body": "John Agent accepted your remittance"
        },
        "data": {
            "title": "Order Accepted",
            "body": "John Agent accepted your remittance",
            "remittance_id": "42",
            "navigate_to_tab": "agent"
        }
    }
}
```

**If using Laravel Notification channels:** Make sure to pass data alongside the notification:

```php
$notification = new Notification([
    'title' => 'Order Accepted',
    'body' => 'John Agent accepted your remittance',
]);

// For FCM, include data payload
$fcmPayload = [
    'data' => [
        'title' => 'Order Accepted',
        'body' => 'John Agent accepted your remittance',
        'remittance_id' => (string) $remittance->id,
        'navigate_to_tab' => 'agent',
    ]
];
```

### Common Mistakes to Avoid

1. **Sending only `notification` payload without `data`:** The client's `onMessageReceived` will show the notification but won't know which screen to open. The `data` payload is required for navigation.

2. **Sending `remittance_id` as an integer instead of string:** FCM data payloads are all strings. The client calls `Integer.parseInt()` on the value. Sending `"42"` works; sending `42` may cause issues.

3. **Missing `navigate_to_tab: "agent"` for agent-targeted notifications:** Without this, tapping the notification opens the Orders tab instead of the Agent Dashboard tab.

4. **Sending `navigate_to` as a data field:** The client does NOT read `navigate_to` from the FCM data payload. It synthesizes `navigate_to` internally based on which IDs are present. Including it in the data payload is harmless but unused.

---

## Ticket Message Attachment Response Format

This is an additional item related to §10.3 — the ticket message endpoint (§7.2) now accepts attachments.

### Existing endpoint

```
POST /api/mobile/support/tickets/{id}/messages
```

### When sending as plain JSON (no attachment)

```json
{ "message": "Thanks" }
```

### When sending as multipart (with attachment)

```
Content-Type: multipart/form-data; boundary=boundary<timestamp>

--boundary<timestamp>
Content-Disposition: form-data; name="message"

Thanks for your help
--boundary<timestamp>
Content-Disposition: form-data; name="attachment"; filename="receipt.jpg"
Content-Type: image/jpeg

<binary file data>
--boundary<timestamp>--
```

### Response must include

```json
{
    "id": 5,
    "message": "Thanks for your help",
    "attachment_path": "storage/ticket-attachments/abc.jpg",
    "created_at": "2026-07-26T12:00:00.000000Z",
    "user": { "id": 1, "name": "John Doe" }
}
```

| Field | Type | Notes |
|---|---|---|
| `id` | int | Message ID |
| `message` | string | Message text |
| `attachment_path` | string or null | Path relative to storage root (e.g. `"storage/ticket-attachments/abc.jpg"`). Client constructs full URL as `BASE_URL.replace("/api", "") + "/" + attachment_path`. |
| `created_at` | string | ISO 8601 timestamp |
| `user` | object | `{ "id": int, "name": string }` — the message sender |

---

## Testing Checklist

After implementing each endpoint, verify:

- [ ] **§10.1** Countries: `GET /mobile/countries` returns 196 objects with `id`, `code`, `name`, `flag_url`, `phone_code`. Country picker in registration works.
- [ ] **§10.2** Events: `GET /mobile/remittances/{id}` includes `events` array. Each event has `id`, `actor_type`, `actor_name`, `from_status`, `to_status`, `description`, `created_at`.
- [ ] **§10.3a** Requester proof: `POST /mobile/remittances/{id}/requester-proof` accepts `multipart/form-data` with `proof` file field. Verify file is stored and path saved.
- [ ] **§10.3b** Agent execute: `POST /mobile/agent/orders/{id}/execute` accepts both `application/json` and `multipart/form-data`. Verify notes, payout_reference, and optional proof are handled.
- [ ] **§10.3c** Ticket message: `POST /mobile/support/tickets/{id}/messages` accepts both `application/json` and `multipart/form-data` with optional `attachment` file field. Response includes `attachment_path`.
- [ ] **§10.4** Push notifications: Send a test notification with `data` payload containing `remittance_id`. Verify the client navigates to the correct screen on tap.

---

## Summary of Endpoints

| Item | Method | URL | Content-Type | Key Fields |
|---|---|---|---|---|
| §10.1 | GET | `/api/mobile/countries` | JSON | Array of `{id, code, name, flag_url, phone_code}` |
| §10.2 | GET | `/api/mobile/remittances/{id}` | JSON | Add `events` array to existing response |
| §10.3a | POST | `/api/mobile/remittances/{id}/requester-proof` | multipart | `proof` (file) |
| §10.3b | POST | `/api/mobile/agent/orders/{id}/execute` | JSON or multipart | `notes`, `payout_reference`, `proof` (file, optional) |
| §10.3c | POST | `/api/mobile/support/tickets/{id}/messages` | JSON or multipart | `message`, `attachment` (file, optional) |
| §10.4 | POST | FCM API | — | Data payload with `title`, `body`, `remittance_id`, `ticket_id`, `navigate_to_tab` |

---

## §10.5 Agent Order Status Counts (Home Screen Badges)

### Problem

The agent home screen shows 4 actionable badge cards: **New Orders**, **In Progress**, **Completed**, and **Debts**. Each badge displays the count of orders in that state. Currently the client makes 4 separate `GET /mobile/agent/orders?tab=X&page=1` calls (one per tab) and counts the first-page results. This works but is inefficient — 4 HTTP requests on every home screen load.

### Recommended Endpoint

```
GET /api/mobile/agent/orders/stats
Authorization: Bearer <token>
Accept: application/json
```

### Response Format

```json
{
    "new": 3,
    "in_progress": 5,
    "completed": 12,
    "debts": 1
}
```

| Field | Type | Notes |
|---|---|---|
| `new` | int | Orders with status `pending` assigned to this agent (not yet accepted) |
| `in_progress` | int | Orders with status `accepted` by this agent (awaiting execution) |
| `completed` | int | Orders with status `completed` by this agent |
| `debts` | int | Orders where this agent has outstanding debt |

### Implementation Notes

- These counts should be exact, not approximated from first-page results.
- If the endpoint is not implemented, the client falls back to 4 paginated calls with `tab` parameter.
- The `GET /mobile/agent/orders?tab=` parameter values are: `new`, `active`, `history`, `debts`.

### Laravel Implementation Hint

```php
// routes/api.php
Route::get('/mobile/agent/orders/stats', function () {
    $agentId = auth()->id();

    return response()->json([
        'new' => \App\Models\Remittance::where('agent_id', $agentId)
            ->where('status', 'pending')->count(),
        'in_progress' => \App\Models\Remittance::where('agent_id', $agentId)
            ->where('status', 'accepted')->count(),
        'completed' => \App\Models\Remittance::where('agent_id', $agentId)
            ->where('status', 'completed')->count(),
        'debts' => \App\Models\Remittance::where('agent_id', $agentId)
            ->where('agent_debt', true)
            ->whereNotIn('status', ['completed', 'cancelled'])->count(),
    ]);
});
```

---

## §10.6 MyOrders Status Filter via Navigation Argument

### Problem

The home screen badge cards for users navigate to `MyOrdersFragment` with a `status_filter` argument (e.g. `"pending"`, `"completed"`, `"all"`). The fragment should pre-select the matching filter tab on load.

### Current Behavior

`MyOrdersFragment` already has internal filter chips: All, Pending, Accepted, Executed, Completed, Debts. The `status_filter` navigation argument is passed from the home screen and should pre-select the matching chip.

### Client Implementation

The `status_filter` argument is read in `MyOrdersFragment.onViewCreated()`:

```java
String initialFilter = "all";
if (getArguments() != null) {
    initialFilter = getArguments().getString("status_filter", "all");
}
```

No backend changes needed — this is purely client-side navigation behavior using the existing `GET /mobile/remittances?status=` endpoint.

### Status Filter Values

| status_filter | API `status` param | Description |
|---|---|---|
| `"all"` | `null` | All remittances |
| `"pending"` | `"pending"` | Pending remittances |
| `"completed"` | `"completed"` | Completed remittances |
| `"debts"` | — | Uses separate `GET /mobile/remittances/debts` endpoint |

---

## Updated Testing Checklist

After implementing each endpoint, verify:

- [ ] **§10.1** Countries: `GET /mobile/countries` returns 196 objects with `id`, `code`, `name`, `flag_url`, `phone_code`.
- [ ] **§10.2** Events: `GET /mobile/remittances/{id}` includes `events` array.
- [ ] **§10.3a** Requester proof: `POST /mobile/remittances/{id}/requester-proof` accepts multipart with `proof`.
- [ ] **§10.3b** Agent execute: `POST /mobile/agent/orders/{id}/execute` accepts JSON and multipart.
- [ ] **§10.3c** Ticket message: `POST /mobile/support/tickets/{id}/messages` accepts JSON and multipart with `attachment`.
- [ ] **§10.4** Push notifications: Data payload with `remittance_id` navigates correctly.
- [ ] **§10.5** Agent stats: `GET /mobile/agent/orders/stats` returns counts per status. If not implemented, client uses fallback (4 paginated calls).
- [ ] **§10.6** MyOrders filter: Home badge "Pending" navigates to MyOrders with pre-selected "Pending" tab.
