# Agent Order Processing Workflow — Client→Backend Reference

This document describes exactly how the Android client processes agent orders, so the backend developer can verify that each endpoint matches what the app expects.

---

## Authentication

Every request includes:
```
Authorization: Bearer <token>
Accept: application/json
```

The token is a Sanctum Bearer token stored locally after login/register.

---

## Step 1: Agent Sees Pending Orders

### Endpoint
```
GET /api/mobile/agent/orders?tab=new&page=1
```

### Query Parameters

| Param | Type | Values | Notes |
|-------|------|--------|-------|
| `tab` | string | `new`, `active`, `history`, `debts` | Filters orders by status |
| `page` | int | 1, 2, 3... | Pagination, starts at 1 |

### Tab→Status Mapping

| `tab` value | Expected statuses | Used for |
|---|---|---|
| `new` | `pending` | Orders assigned to agent, not yet accepted |
| `active` | `accepted` | Agent accepted, awaiting execution |
| `history` | `executed`, `completed` | Past completed orders |
| `debts` | Orders where `agent_debt = true` | Outstanding debts |

### Expected Response
```json
{
  "data": [
    {
      "id": 42,
      "reference": "REM-20260725-ABC123",
      "status": "pending",
      "send_amount": 500.00,
      "send_currency": "USD",
      "destinator_name": "Jane Doe",
      "destinator_phone": "+260971234567",
      "destinator_address": "Lusaka, Zambia",
      "notes": null,
      "requester_proof_path": null,
      "agent_proof_path": null,
      "requester_debt": false,
      "agent_debt": false,
      "destinator_account_number": "1234567890",
      "destinator_payment_method_name": "Mobile Money",
      "created_at": "2026-07-25T10:30:00.000000Z",
      "agent": {
        "id": 5,
        "name": "John Agent"
      },
      "requester": {
        "id": 10,
        "name": "Mary Requester"
      },
      "payment_method": {
        "id": 1,
        "name": "Mobile Money"
      }
    }
  ],
  "current_page": 1,
  "last_page": 3
}
```

### Field Requirements

| Field | Type | Required | Notes |
|---|---|---|---|
| `id` | int | Yes | |
| `reference` | string | Yes | |
| `status` | string | Yes | One of: `pending`, `accepted`, `executed`, `completed`, `disputed`, `cancelled` |
| `send_amount` | float | Yes | |
| `send_currency` | string | Yes | |
| `destinator_name` | string | Yes | |
| `destinator_phone` | string | Yes | |
| `created_at` | string | Yes | ISO 8601 |
| `requester` | object | Yes | `{ "id": int, "name": string }`. May also be keyed as `"user"` in JSON — the client checks both. |
| `current_page` | int | Yes | Pagination |
| `last_page` | int | Yes | Pagination |

---

## Step 2: Agent Accepts an Order

### Endpoint
```
POST /api/mobile/agent/orders/{id}/accept
```

### Request
```
Content-Type: application/json
Authorization: Bearer <token>

(no body — empty POST)
```

The client sends **no request body** for this endpoint.

### Expected Response

Any 2xx with a JSON body. The client discards the response body.

```json
{ "message": "Order accepted successfully" }
```

### What Happens After Success

- The order's status should change from `pending` → `accepted` on the backend.
- The client re-fetches the pending orders list and badge counts.
- On the next `GET /mobile/agent/orders?tab=new`, this order should NOT appear.
- On `GET /mobile/agent/orders?tab=active`, this order SHOULD appear.

---

## Step 3: Agent Views Order Detail

### Primary Endpoint
```
GET /api/mobile/remittances/{id}
```

### Fallback Endpoint (if primary returns error)
```
GET /api/mobile/agent/orders/{id}
```

The client tries the primary endpoint first. If it returns any error (including 403/404), it falls back to the agent endpoint.

### Expected Response (same format for both)

A single JSON object (NOT wrapped in `"data"`):

```json
{
  "id": 42,
  "reference": "REM-20260725-ABC123",
  "status": "accepted",
  "send_amount": 500.00,
  "send_currency": "USD",
  "destinator_name": "Jane Doe",
  "destinator_phone": "+260971234567",
  "destinator_address": "Lusaka, Zambia",
  "notes": "Urgent delivery",
  "requester_proof_path": "storage/proofs/req_proof.jpg",
  "agent_proof_path": null,
  "requester_debt": false,
  "agent_debt": false,
  "destinator_account_number": "1234567890",
  "destinator_payment_method_name": "Mobile Money",
  "created_at": "2026-07-25T10:30:00.000000Z",
  "agent": { "id": 5, "name": "John Agent" },
  "requester": { "id": 10, "name": "Mary Requester" },
  "payment_method": { "id": 1, "name": "Mobile Money" }
}
```

### UI Behavior

- The **Execute card** (form with notes, payout ref, proof upload, submit button) is shown **only when `status == "accepted"`**.
- If status is anything else, the Execute card is hidden.

---

## Step 4: Agent Executes (Marks as Processed) an Order

This is the critical step. The client supports **two content types** for the same endpoint. The backend **must accept both**.

### Endpoint
```
POST /api/mobile/agent/orders/{id}/execute
```

---

### Path A: Without Proof (JSON)

**When**: No proof image is attached.

**Request**:
```
Content-Type: application/json
Accept: application/json
Authorization: Bearer <token>
```

**Body**:
```json
{
  "notes": "Paid via bank transfer, ref #12345",
  "payout_reference": "CASH-001"
}
```

**Field rules**:
| Field | Type | Required | Notes |
|---|---|---|---|
| `notes` | string | No | Execution notes. Only included if non-empty. |
| `payout_reference` | string | No | Payout reference. Only included if non-empty. |

**Important**: The client validates that at least one of `notes`, `payout_reference`, or `proof` is non-empty before sending. If all are empty, a client-side validation message is shown and no request is made. However, the backend should still validate and return a clear 422 error if required fields are missing.

**Client validation rule**: At least one of `notes`, `payout_reference`, or `proof` must be provided.

---

### Path B: With Proof (Multipart)

**When**: Agent attaches a proof image (receipt, screenshot, etc.).

**Request**:
```
Content-Type: multipart/form-data; boundary=boundary1690000000000
Accept: application/json
Authorization: Bearer <token>
```

**Body parts**:

```
--boundary1690000000000
Content-Disposition: form-data; name="notes"

Paid via bank transfer
--boundary1690000000000
Content-Disposition: form-data; name="payout_reference"

CASH-001
--boundary1690000000000
Content-Disposition: form-data; name="proof"; filename="agent_proof_1690000000000.jpg"
Content-Type: image/jpeg

<binary file data>
--boundary1690000000000--
```

**Field rules**:
| Field name | Type | Required | Notes |
|---|---|---|---|
| `notes` | text | No | Only included if non-empty |
| `payout_reference` | text | No | Only included if non-empty |
| `proof` | file | No | Image file. MIME types: `image/jpeg`, `image/png`, `application/pdf`, `image/gif`, `image/webp`. Max ~5MB. |

**The proof field name in the multipart body is literally `proof`**.

---

### Expected Response (both paths)

Any 2xx with JSON body:
```json
{ "message": "Order executed successfully" }
```

The client discards the response body. It just checks for non-error status.

### What Happens After Success

- The order's status should change from `accepted` → `executed` on the backend.
- The client shows a Toast "Order executed successfully!" and navigates back.
- The order should now appear in `GET /mobile/agent/orders?tab=history`.

---

## Complete Status Lifecycle

```
pending → accepted → executed → completed
                     ↓
                  disputed
                     ↓
                  resolved

pending → cancelled (by requester)
```

| Status | Who triggers | API endpoint |
|---|---|---|
| `pending` | Initial state | — |
| `accepted` | Agent | `POST /mobile/agent/orders/{id}/accept` |
| `executed` | Agent | `POST /mobile/agent/orders/{id}/execute` |
| `completed` | Requester | `POST /mobile/remittances/{id}/confirm` |
| `cancelled` | Requester | `POST /mobile/remittances/{id}/cancel` |
| `disputed` | Either party | Via support ticket |

---

## All Agent-Related Endpoints Summary

| # | Method | URL | Body | Content-Type | Purpose |
|---|---|---|---|---|---|
| 1 | GET | `/api/mobile/agent/orders?tab=&page=` | none | — | List orders |
| 2 | GET | `/api/mobile/agent/orders/{id}` | none | — | Single order detail (fallback) |
| 3 | POST | `/api/mobile/agent/orders/{id}/accept` | none | — | Accept order |
| 4 | POST | `/api/mobile/agent/orders/{id}/execute` | JSON or multipart | `application/json` OR `multipart/form-data` | Execute order |
| 5 | GET | `/api/mobile/remittances/{id}` | none | — | Single order detail (primary) |

---

## Debugging the 422 Error

If the backend returns 422 on `POST /mobile/agent/orders/{id}/execute`, check:

1. **Is the order status actually `accepted`?** The client only shows the Execute button for accepted orders, but verify on the backend side.

2. **Does the backend require `proof`?** The client treats proof as optional. If the backend requires it, the agent can't mark as executed without attaching an image — this would cause 422.

3. **Does the backend require `payout_reference`?** The client treats it as optional. If the backend requires it, an empty value would cause 422.

4. **Does the backend handle both content types?** When proof is attached, the Content-Type is `multipart/form-data`. When no proof, it's `application/json`. The backend must accept **both** on the same endpoint.

5. **Check the validation errors in the response body.** The client now parses Laravel's `errors` object and shows field-level error messages. Example 422 response:
   ```json
   {
     "message": "The given data was invalid.",
     "errors": {
       "proof": ["The proof field is required when status is accepted."]
     }
   }
   ```

6. **Is the order already executed?** If the agent clicks Execute twice (e.g., network delay), the second request would fail because the order is no longer in `accepted` status.
