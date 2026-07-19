# Rungika Marketplace — Feature Test Plan

> This document describes **manual test scenarios** for validating the real-world features of the Rungika remittance marketplace. Each scenario walks through a realistic end-to-end flow, covering the key business requirements: mobile registration, agent marketplace, remittance lifecycle, debt tracking, support tickets, portal administration, API key auth, and 2FA.

---

## 1. Mobile User Registration & Approval Gate

### 1.1 Register a new user via mobile API

**Precondition:** App is running, database is clean (or you have a fresh test environment).

1. Send `POST /api/mobile/auth/register` with:
   ```json
   {
     "name": "Alice Chanda",
     "phone": "+260970000001",
     "country_id": 1
   }
   ```
2. Verify:
   - Response status **201**
   - Response body contains `user` with `kyc_status: "pending"`
   - A `Contact` record is created (type=`whatsapp`, value=`+260970000001`)
   - User has role `Customer`

### 1.2 Attempt to access protected routes before approval

1. Request an OTP: `POST /api/mobile/auth/request-whatsapp-otp` with `identifier: +260970000001`
2. Verify OTP: `POST /api/mobile/auth/verify-whatsapp-otp` with `identifier` and `code` (check logs for dev code)
3. Use returned `token` as `Authorization: Bearer <token>`
4. Try: `GET /api/mobile/profile`
5. Verify:
   - Response status **403**
   - Message contains `pending approval`

### 1.3 Approve user via portal

1. Login to portal as admin: `POST /api/portal/login` with admin credentials
2. Call `POST /api/portal/users/{user_id}/approve-kyc`
3. Verify:
   - Response status **200**
   - User's `kyc_status` is now `"verified"`
4. Repeat step 1.2 — now `GET /api/mobile/profile` should return **200** with user data

---

## 2. Agent Marketplace (Mobile)

### 2.1 Create an agent user

1. Via portal or seeder, create a user with role `Agent` and `kyc_status: "verified"`
2. Set `is_agent_available: true`, `last_activity_at` to a recent timestamp

### 2.2 Search for agents

1. Call `GET /api/mobile/agents` as an approved mobile user
2. Verify:
   - Response includes the agent from 2.1
   - Fields: `id`, `name`, `photo_url`, `country`, `is_available`, `is_online`, `average_rating`, `total_jobs`, `completion_rate`

### 2.3 Test filters and sorting

1. `GET /api/mobile/agents?available=true` — only available agents
2. `GET /api/mobile/agents?online=true` — only recently active
3. `GET /api/mobile/agents?country_id=1` — filter by country
4. `GET /api/mobile/agents?q=Alice` — search by name
5. `GET /api/mobile/agents?sort_by=total_jobs&sort_dir=asc` — sort

### 2.4 Agent toggles availability

1. Call `PATCH /api/mobile/agent/availability` as the agent
2. Verify `is_agent_available` toggles and `agent_available_since` is set

### 2.5 Agent pings for online status

1. Call `POST /api/mobile/ping` as the agent
2. Verify `last_activity_at` updates

### 2.6 Agent updates profile

1. Call `POST /api/mobile/agent/profile` with `agent_location`, `agent_bio`, and optional photo
2. Verify response contains updated fields

---

## 3. Remittance Lifecycle (Requester + Agent)

### 3.1 Requester creates a remittance

1. As an approved mobile user, call `POST /api/mobile/remittances`:
   ```json
   {
     "agent_id": 2,
     "destinator_name": "Bob Mulenga",
     "destinator_phone": "+260970000002",
     "destinator_address": "Lusaka, Zambia",
     "send_amount": 500.00,
     "send_currency": "USD"
   }
   ```
2. Verify:
   - Response status **201**
   - Status is `"pending"`
   - Agent is set
   - Reference is generated (`MT-YYYYMMDD-XXXXXX`)
   - Event `initiated` is created

### 3.2 Requester views their remittances

1. Call `GET /api/mobile/remittances`
2. Verify the remittance appears with status, agent info, and events

### 3.3 Requester uploads proof (optional — no debt)

1. Call `POST /api/mobile/remittances/{id}/requester-proof` with a file (jpg/png/pdf)
2. Verify:
   - Response status **200**
   - `requester_proof_path` is set
   - `requester_debt` is `false`
   - Event `requester_proof_uploaded` is created

### 3.4 Requester skips proof (creates debt)

1. Do NOT upload proof for a remittance (or test separately)
2. When agent continues without requester proof, the remittance proceeds but `requester_debt` remains `true`

### 3.5 Agent sees the order

1. As the agent, call `GET /api/mobile/agent/orders`
2. Verify the remittance appears with status `"pending"`

### 3.6 Agent accepts the order

1. Call `POST /api/mobile/agent/orders/{id}/accept`
2. Verify:
   - Status changes to `"accepted"`
   - `accepted_at` is set
   - Event `accepted` is created

### 3.7 Agent executes the order (with proof)

1. Call `POST /api/mobile/agent/orders/{id}/execute` with:
   - `proof` file
   - `payout_reference: "CASH-12345"`
   - `agent_notes: "Delivered in person"`
2. Verify:
   - Status changes to `"executed"`
   - `executor_proof_path` is set
   - `executor_debt` is `false`
   - `executed_at` is set
   - Event `executed` is created with `has_proof: true`

### 3.8 Agent executes without proof (creates executor debt)

1. Call `POST /api/mobile/agent/orders/{id}/execute` without a `proof` file
2. Verify:
   - `executor_debt` is `true`
   - Status is `"executed"`

### 3.9 Requester confirms completion

1. Call `POST /api/mobile/remittances/{id}/confirm`
2. Verify:
   - Status changes to `"completed"`
   - `completed_at` is set
   - Event `completed` is created

### 3.10 Requester cancels a pending remittance

1. Create a new remittance (status: `pending`)
2. Call `POST /api/mobile/remittances/{id}/cancel`
3. Verify:
   - Status changes to `"cancelled"`
   - Event `cancelled` is created

---

## 4. Debt Tracking

### 4.1 View debts as requester

1. After creating a remittance where requester did NOT upload proof
2. Call `GET /api/mobile/remittances/debts/list`
3. Verify:
   - The remittance appears
   - `requester_debt` is `true`
   - Can filter by `side=my_debts` or `side=owed_to_me`

### 4.2 Resolve requester debt by uploading proof later

1. Call `POST /api/mobile/remittances/{id}/requester-proof` with a file
2. Verify `requester_debt` becomes `false`

### 4.3 View debts from agent side

1. Call `GET /api/mobile/agent/orders?debt=true`
2. Verify executor debts appear

### 4.4 Resolve executor debt by uploading proof later

1. Call `POST /api/mobile/agent/orders/{id}/proof` with a file
2. Verify `executor_debt` becomes `false`

---

## 5. Support Tickets (Disputes)

### 5.1 Create a support ticket for a remittance

1. As a requester or agent, call `POST /api/mobile/support/remittance-tickets`:
   ```json
   {
     "remittance_id": 1,
     "support_category_id": 1,
     "title": "Funds not delivered",
     "description": "The agent marked as executed but I didn't receive the money.",
     "priority": "urgent"
   }
   ```
2. Verify:
   - Response status **201**
   - Ticket is created with status `"open"`
   - If remittance was not already `"disputed"`, its status changes to `"disputed"`
   - Event `disputed` is created on the remittance

### 5.2 Cannot open a ticket for a closed remittance

1. For a `completed` or `cancelled` remittance, attempt to create a ticket
2. Verify **422** with message `Cannot open a ticket for a closed remittance.`

### 5.3 Portal: View and manage support tickets

1. Login to portal as admin/operator
2. `GET /api/portal/support/tickets` — list all tickets
3. `GET /api/portal/support/tickets/{id}` — view details
4. `PATCH /api/portal/support/tickets/{id}/status` — update status

---

## 6. Portal Administration

### 6.1 Portal login with 2FA

1. Call `POST /api/portal/login` with valid email+password
2. If user has 2FA enabled:
   - Response includes `requires_2fa: true` and `temp_token`
3. Call `POST /api/portal/2fa/verify` with `temp_token` and `code` (from authenticator app)
4. Verify:
   - Response includes a session cookie
   - `GET /api/user` returns authenticated user

### 6.2 Portal 2FA setup flow

1. Login (without 2FA enabled) → login succeeds directly
2. Enable 2FA: `POST /api/portal/2fa/enable` (inside auth group)
3. Call `GET /api/portal/2fa/qr` — returns SVG string
4. Call `GET /api/portal/2fa/recovery-codes` — shows recovery codes
5. Call `POST /api/portal/2fa/recovery-codes/regenerate` — regenerates recovery codes
6. Call `POST /api/portal/2fa/disable` — disables 2FA

### 6.3 Portal 2FA recovery code flow

1. Login as user with 2FA enabled
2. Call `POST /api/portal/2fa/recovery` with `temp_token` and a recovery code
3. Verify login succeeds

### 6.4 Portal: Remittance management

1. `GET /api/portal/transfers` — list all transfers with search/filter
2. `GET /api/portal/transfers/stats` — status counts
3. `PATCH /api/portal/transfers/{id}/status` — manually update status

### 6.5 Portal: Reports

1. `GET /api/portal/reports/remittances` — filtered remittance report
2. `GET /api/portal/reports/remittances/export?format=xlsx` — export
3. `GET /api/portal/reports/debts` — debt report
4. `GET /api/portal/reports/agent-performance` — agent performance
5. `GET /api/portal/reports/platform-summary` — platform overview

### 6.6 Portal: User management

1. `GET /api/portal/users` — list users
2. `GET /api/portal/users/{id}` — view user details
3. `POST /api/portal/users/{id}/approve-kyc` — approve user
4. `POST /api/portal/users/{id}/suspend` — flag/suspend user

### 6.7 Portal: Country and currency management

1. `GET /api/portal/countries` — list countries
2. `POST /api/portal/countries` — create country (requires `manage_countries` permission)
3. `POST /api/portal/countries/seed` — seed from artisan command
4. `GET /api/portal/currencies` — list currencies
5. `POST /api/portal/currencies` — create currency

### 6.8 Portal: Permission gating

1. Login as Operator (no `manage_countries` permission)
2. Attempt `POST /api/portal/countries`
3. Verify **403** response

---

## 7. API Key Authentication

### 7.1 Create an API key

1. `POST /api/v1/keys` with valid `X-API-Key` header (use a pre-seeded API key or create via tinker)
2. Or via portal: hit the key management endpoint
3. Verify:
   - Response includes the **plaintext** key (shown once)
   - Key is stored as SHA-256 hash

### 7.2 List API keys

1. `GET /api/v1/keys` with valid API key
2. Verify list of keys (without plaintext)

### 7.3 Revoke an API key

1. `DELETE /api/v1/keys/{id}` with valid API key
2. Verify `is_active` becomes `false`

### 7.4 API key validation

1. Call v1 endpoint **without** `X-API-Key` header
2. Verify **401** — `API key is missing`
3. Call with an expired/revoked key
4. Verify **401** — `Invalid or inactive API key`

---

## 8. Mobile Reports (Self-Service)

### 8.1 User activity report

1. As an approved mobile user, call `GET /api/mobile/reports/my-activity`
2. Verify:
   - `requester.total_remittances` — count of all remittances
   - `requester.total_volume` — sum of send amounts
   - `requester.completed`, `requester.pending`, `requester.disputed`, `requester.my_debts`
   - If user is also an agent: `agent` stats block

### 8.2 Export user activity as CSV

1. Call `GET /api/mobile/reports/my-activity/export`
2. Verify response is a downloadable CSV file with headers:
   `Reference, Agent, Status, Amount, Currency, Destinator, Payment Method, Requester Debt, Executor Debt, Created At, Completed At`

---

## 9. Edge Cases & Negative Tests

### 9.1 Unauthorized access to remittances

- Requester tries to view another user's remittance → **403**
- Agent tries to view an order not assigned to them → **403**

### 9.2 Invalid state transitions

- Try to accept an already-accepted order → **422**
- Try to confirm a remittance that hasn't been executed → **422**
- Try to cancel a non-pending remittance → **422**
- Try to upload proof for a closed remittance → **422**
- Try to upload proof when no debt exists → **422**

### 9.3 Validation errors

- Create remittance without `agent_id` → **422** validation error
- Register without `phone` → **422**
- Wrong OTP code → **422** `Invalid verification code`
- Expired OTP code → **422** `Verification code expired`

### 9.4 Flagged/suspended users

1. Flag a user via admin: `POST /api/portal/users/{id}/flag`
2. The flagged user tries to access mobile routes → **403** with restriction message

### 9.5 Trading disabled

1. Set `trading_enabled = false` for a user
2. User tries to access mobile routes → **403** `Trading is currently disabled`

---

## 10. Seeder Verification

Run `php artisan db:seed` and verify:

| Seeder | What to check |
|---|---|
| `RolesAndPermissionsSeeder` | Roles: super_admin, Admin, Operator, Agent, Customer exist. Permissions: manage_users, manage_remittances, manage_agents, manage_countries, manage_currencies, manage_support exist |
| `CountrySeeder` | Countries populated with codes, flags, currencies |
| `CurrencySeeder` | Currencies populated |
| `SupportCategorySeeder` | Support categories exist (at least one) |

---

## How to Execute

Run these tests manually using any HTTP client (Postman, cURL, Bruno, HTTPie):

```bash
# Register
curl -X POST http://localhost/api/mobile/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice","phone":"+260970000001","country_id":1}'

# Get OTP code from logs
curl -X POST http://localhost/api/mobile/auth/request-whatsapp-otp \
  -H "Content-Type: application/json" \
  -d '{"identifier":"+260970000001"}'

# Verify OTP (check storage/logs for code)
curl -X POST http://localhost/api/mobile/auth/verify-whatsapp-otp \
  -H "Content-Type: application/json" \
  -d '{"identifier":"+260970000001","code":"123456"}'

# Use the token
TOKEN="your_sanctum_token_here"

# Test approval gate
curl http://localhost/api/mobile/profile -H "Authorization: Bearer $TOKEN"
```

For PHPUnit automated tests, see `tests/Feature/MobileAuthTest.php` and `tests/Feature/MoneyTransferWorkflowTest.php`.
