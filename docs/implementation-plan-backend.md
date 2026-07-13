# Implementation Plan: Laravel/Vue Backend & API

## How Current Code Maps to Your Requirements

| Your Requirement | Current Status | What's Missing / Needs Change |
|---|---|---|
| 1. User creation, auth, roles | ✅ Done. Mobile register (WhatsApp OTP), email/password login, Spatie roles | Nothing — already solid |
| 2. All users need authorization before using the platform | ⚠️ Partially. KYC status flow (pending→verified→suspended) exists but is not enforced on portal routes. Mobile checks KYC on transfer creation. | Need middleware/guard to block unapproved users from ANY platform access |
| 3. Ban users | ✅ Done. `flagged` boolean + `suspended` KYC status | Already works |
| 4. Country at registration + main currency in settings | ❌ Not present | New feature needed |
| 5. Full currency management | ✅ Done. CRUD + exchange rates | Already works |
| 6. Country management with flags + artisan seeder | ❌ Not present | New feature needed |
| 7. Agent availability for remittances | ❌ Not present. Agents are just a Spatie role | New feature needed |
| 8. Users see available agents with flags/ratings | ❌ Not present | New feature + mobile endpoint |
| 9. User picks agent, creates order with destinator details, proof optional | ⚠️ Partially. Current MoneyTransfer has `sender_name/phone/name/phone/location` and `usdt_proof` but no `assigned_agent_id` at creation | Need to adapt MoneyTransfer for agent-pick flow, make proof optional |
| 10. Agent dashboard with orders, destinator info, mark executed + proof | ⚠️ Partially. Mobile transfer controller exists but is agent-only (not "any approved user can pick an agent") | Need to refactor flow |
| 11. Support tickets linked to remittances | ⚠️ Partial. SupportTicket has polymorphic `subject` but no wire to MoneyTransfer | Need to connect them |
| 12. Filter transactions by payment proof missing (debt tracking) | ❌ Not present | New feature needed |
| 13. Walled garden trust model | ⚠️ Partially. KYC exists but isn't enforced as gate | Need approval gate middleware |
| 14. Admin reports: filterable, Excel/PDF export | ❌ Not present. Only P2P trade CSV export exists | New feature needed |
| 15. Users self-report for their own operations | ❌ Not present | New feature needed |

---

## Phase 1: Foundation — Country & Onboarding

### 1.1 Create Country Model, Migration, and Seeder

**Migration `create_countries_table`**
| Column | Type |
|---|---|
| id | bigIncrements |
| name | string(100) |
| code | string(2), unique (ISO 3166-1 alpha-2) |
| phone_code | string(10) (e.g. "+260") |
| flag_url | string(255), nullable (free hosted flag URL) |
| is_active | boolean, default true |
| timestamps | |

**Seed artisan command: `php artisan countries:seed`**
- Populates ~195 countries from a hardcoded dataset in the command class (name, code, phone_code)
- flag_url: use a free CDN like `https://flagcdn.com/w80/{code}.png` or `https://hatscripts.github.io/circle-flags/flags/{code}.svg`
- Can be re-run safely (upserts by `code`)

**Vue Portal Page: Countries/Index.vue**
- Route: `/portal/countries`
- Table: name, code, phone_code, flag thumbnail, active toggle
- Create/edit modal (name, code, phone_code, flag_url, is_active)
- Sidebar item in portal nav

### 1.2 Add Country to User Registration

**User migration: add columns**
| Column | Type |
|---|---|
| country_id | FK->countries, nullable |
| preferred_currency_id | FK->currencies, nullable |

**Update User model:**
- `belongsTo(Country::class)`
- `belongsTo(Currency::class, 'preferred_currency_id')`
- Make these fillable

**MobileAuthController::register()** — accept `country_id` and `preferred_currency_id` (optional, default to country's currency)

**Mobile Profile controller** — allow updating `preferred_currency_id` in settings

**Portal user detail (Vue):** show country + preferred currency

### 1.3 Enforce Approval Gate

**Create middleware: `CheckApproved`**
```php
// Check two things:
// 1. kyc_status === 'verified' — user is approved
// 2. flagged === false — user is not banned
// 3. trading_enabled === true — user is not restricted
// Return 403 with message if any fail.
```

**Apply middleware:**
- Portal routes (`/api/portal/*`): already session-auth'd but no role/KYC gate → add middleware on the group or on sensitive routes
- Mobile routes (`/api/mobile/*`): add to the `auth:sanctum` group so ALL protected endpoints enforce it

**Exception for admins:** Users with `Admin`, `Operator`, or `super_admin` role bypass the KYC check (admins don't need approval to use the backoffice).

## Phase 2: Agent System

### 2.1 Agent Availability & Profile

**Migration: add columns to `users` (or create `agent_profiles` table)**

Option A (simpler — add to users table):
| Column | Type |
|---|---|
| is_agent_available | boolean, default false |
| agent_available_since | timestamp nullable |
| agent_location | string(255), nullable |
| agent_bio | text, nullable |
| agent_photo_path | string, nullable |
| country_id (already added) | FK->countries |

Set `is_agent_available` when a user with role `Agent` marks themselves available.

**Agent Profile Model** (if we go with separate table):
- `user_id`, `bio`, `photo_path`, `is_available`, `available_since`, `location`, `country_id`

**API endpoints:**
- `PATCH /api/mobile/agent/availability` — toggle `is_agent_available`
- `PUT /api/mobile/agent/profile` — update bio, location, photo
- `GET /api/mobile/agents` — list available agents (open to all approved users)

### 2.2 Agent Badge & Online Status

**User model already has:**
- `is_merchant`, `merchant_verified_at` exist but are for P2P
- We can repurpose `is_merchant` to `is_agent` or keep separate

**Add:**
- `agent_verified_at` timestamp (admin sets it when approving an Agent user)
- `last_activity_at` (already in P2P spec but not yet on User model — add it now)

**Agent list response:**
```json
{
    "id": 1,
    "name": "John",
    "photo_url": "...",
    "country_id": 1,
    "country": { "code": "ZM", "flag_url": "..." },
    "is_available": true,
    "is_online": true,
    "last_activity_at": "...",
    "average_rating": 4.8,
    "total_jobs": 150,
    "completion_rate": 98.5
}
```

### 2.3 Market Intelligence (order book)

**Agent filter/search endpoint: `GET /api/mobile/agents`**

Query params:
| Param | Type | Description |
|---|---|---|
| `country_id` | int | Filter by agent's country |
| `available` | bool | Only available agents |
| `online` | bool | Only online (last_activity_at within 5 min) |
| `q` | string | Search by name |
| `sort_by` | string | `rating`, `completion_rate`, `total_jobs` |

Response: paginated list of agent profiles with all reputation data.

---

## Phase 3: Remittance Flow (Adapted)

### 3.1 Remittance Model Changes

**Migration: add/alter columns on `money_transfers`**

The current model uses a USDT-focused workflow. We need to adapt it to the two-role (requester + agent) model:

| Column | Change |
|---|---|
| `assigned_agent_id` | Keep — agent is chosen at creation by the requester |
| `agent_notes` | Keep |
| `usdt_amount` | Remove or make nullable (we now support optional proof) |
| `usdt_proof_path` | Remove — replaced by `requester_proof_path` |
| `usdt_proof_uploaded_at` | Remove |
| `usdt_confirmed_by` | Remove |
| `usdt_confirmed_at` | Remove |
| `send_amount` | Keep — the value being sent |
| `send_currency` | Keep |
| `payout_amount` | Keep (eventual payout amount, can be null until agent completes) |
| `payout_currency` | Keep |
| `payout_reference` | Keep |
| `payout_proof_path` | Keep |
| `status` | NEW STATUSES (see below) |

**NEW columns:**
| Column | Type | Purpose |
|---|---|---|
| `requester_proof_path` | string nullable | Proof uploaded by requester (optional) |
| `requester_debt` | boolean, default false | true if requester skipped proof → this is a debt |
| `executor_proof_path` | string nullable | Proof uploaded by executor after completing remittance |
| `executor_debt` | boolean, default false | true if executor skipped proof → this is a debt |
| `destinator_name` | string | Person who receives the money |
| `destinator_phone` | string nullable | |
| `destinator_address` | string nullable | |
| `destinator_payment_method_id` | FK->payment_methods nullable | |
| `destinator_account_number` | string nullable | |
| `destinator_notes` | text nullable | |

**NEW status flow:**
| Status | Meaning |
|---|---|
| `pending` | Created by requester, waiting for agent to accept |
| `accepted` | Agent accepted the order |
| `executed` | Agent completed the remittance, uploaded proof (or marked as debt) |
| `completed` | Requester confirmed receipt (or proof uploaded) |
| `disputed` | Either party opened a dispute |
| `cancelled` | Cancelled (by requester before acceptance, or by admin) |

**Track debt:**
A remittance is in debt on a side if:
- `requester_debt = true` (requester didn't upload proof of payment)
- `executor_debt = true` (executor didn't upload proof of execution)

**Actual vs simplified approach:**
Since you want a simpler model (no USDT), we can either:
- **A)** Adapt the existing MoneyTransfer with the new columns and statuses above (keeps the table, changes the workflow)
- **B)** Create a new `Remittances` table from scratch and move MoneyTransfer to legacy

**Recommendation: A** — rename `usdt_*` to `requester_*`/`executor_*` in a new migration, add new columns, change status constants.

### 3.2 API Endpoints — Remittance Flow

#### For Requesters (any approved user)

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/mobile/agents` | List agents (Phase 2) |
| GET | `/api/mobile/agents/{id}` | Agent detail with reputation |
| POST | `/api/mobile/remittances` | Create remittance, choose agent, fill destinator details |
| GET | `/api/mobile/remittances` | My remittances (as requester) |
| GET | `/api/mobile/remittances/{id}` | Detail with events |
| POST | `/api/mobile/remittances/{id}/requester-proof` | Upload proof of payment (optional — skip = debt) |
| POST | `/api/mobile/remittances/{id}/confirm` | Confirm remittance completed by agent (moves to completed) |
| POST | `/api/mobile/remittances/{id}/cancel` | Cancel before agent accepts |
| POST | `/api/mobile/support/remittance-tickets` | Open support ticket linked to remittance |

#### For Agents (users with Agent role)

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/mobile/agent/orders` | List orders assigned to me |
| GET | `/api/mobile/agent/orders/{id}` | Order detail with all destinator info |
| POST | `/api/mobile/agent/orders/{id}/accept` | Accept an order |
| POST | `/api/mobile/agent/orders/{id}/execute` | Mark as executed + upload payout proof |
| POST | `/api/mobile/agent/orders/history` | My execution history |

**Key:** The mobile TransferController already exists at `/api/mobile/transfers`. We should either:
- Rename it to `/api/mobile/remittances` and create a new controller (`RemittanceController`), or
- Reuse `/api/mobile/transfers` as the remittance endpoint path

**Recommendation:** Create a new `RemittanceController` at `/api/mobile/remittances` to keep it clean. Keep MoneyTransfer/MoneyTransferController for admin portal only (legacy), or refactor it.

### 3.3 Debt Tracking

**Endpoint: `GET /api/mobile/remittances/debts`**

Query params:
| Param | Type | Description |
|---|---|---|
| `side` | string | `my_debts` (I owe proof), `owed_to_me` (they owe proof), `all` |
| `agent_id` | int | Filter by agent |
| `date_from` / `date_to` | date | Filter by date |
| `payment_method` | int | Filter by payment method |

Response: list of remittances where `requester_debt = true` or `executor_debt = true`, with outstanding amount, counterparty, days since completion, etc.

---

## Phase 4: Support Tickets for Remittances

### 4.1 Link SupportTickets to Remittances

The SupportTicket model already has a polymorphic `subject` field. We just need to:

1. Add `MoneyTransfer` to the morph map (in `AppServiceProvider::boot()` or `SupportTicket` model)
2. When creating a ticket from a remittance, pass `subject_type: 'money-transfer'` and `subject_id: {remittance_id}`
3. In the admin portal, show the linked remittance detail in the ticket detail panel

**Mobile endpoint:**
```
POST /api/mobile/support/remittance-tickets
{
    "remittance_id": 1,
    "category_id": 2,
    "title": "Agent not responding",
    "description": "My agent hasn't accepted my order in 24 hours",
    "priority": "high"
}
```

**Portal changes:** in the existing Support/Index.vue, when viewing a ticket with `subject_type = money-transfer`, show a "View Transfer" button that opens the transfer detail panel alongside the ticket.

### 4.2 Add "Disputed" Status to Remittances

When a support ticket is created for a remittance, update the remittance status to `disputed`. When the ticket is resolved/closed, revert to the previous status or mark as resolved.

---

## Phase 5: Reporting & Exports

### 5.1 Admin Reports Page

**Vue page: Reports/Index.vue**
- Route: `/portal/reports`
- Sidebar item: "Reports" after "Revenue"

**Report types:**
1. **Remittance Report** — all remittances with filters
2. **Debt Report** — all remittances with outstanding proofs
3. **Agent Performance** — per-agent metrics
4. **Platform Summary** — total volume, fees, completions, debts

**Filters (shared across reports):**
- Date range (from/to)
- Status (multi-select)
- Agent (searchable dropdown)
- Requester (searchable dropdown)
- Country
- Payment method
- Has debt (yes/no)

**View modes:**
- Table with columns: reference, date, requester, agent, amount, status, debt side, created_at
- Summary cards: total count, total volume, debt volume, completion rate

### 5.2 Excel Export

Use the existing but unused `maatwebsite/laravel-excel` package.

**Create export classes:**
- `RemittancesExport` — all remittances with current filters
- `DebtsExport` — remittances with outstanding proofs
- `AgentPerformanceExport` — per-agent stats

**Admin endpoint:**
```
GET /api/portal/reports/remittances/export?format=xlsx
GET /api/portal/reports/remittances/export?format=csv
GET /api/portal/reports/debts/export?format=xlsx
```

**Mobile user endpoint:**
```
GET /api/mobile/reports/my-remittances/export?format=csv
```

### 5.3 PDF Export

Use the existing but unused `barryvdh/laravel-dompdf`.

**Pdf Report Generation:**
- Dashboard summary PDF (stats, charts as tables)
- Per-remittance detail PDF (full event history, proofs)
- Agent activity report PDF

```
GET /api/portal/reports/remittances/export?format=pdf
GET /api/portal/reports/remittances/{id}/download
```

### 5.4 User Self-Reports

**Mobile endpoint:**
```
GET /api/mobile/reports/my-activity?from=...&to=...&format=json
GET /api/mobile/reports/my-activity/export?format=csv
```

Returns: remittances created, accepted, executed, completed; debt summary; volume totals.

---

## Phase 6: Portal Changes

### 6.1 New Sidebar Items

| Item | Route | Component |
|---|---|---|
| Countries | `/portal/countries` | `pages/Countries/Index.vue` (new) |
| Reports | `/portal/reports` | `pages/Reports/Index.vue` (new) |

### 6.2 Reorganize Remittances Page

The existing `/portal/transfers` page already has:
- Stats cards
- Transfer list with search/filter
- Detail panel with actions
- Proof uploads

**Changes needed:**
- Adapt columns/view for the new status flow (pending, accepted, executed, completed, disputed)
- Add debt column (shows which side is in debt)
- Add agent picker for admin to assign in portal
- Add export button on the page

### 6.3 Users Page Enhancements

- Show country + preferred currency
- Show `is_agent_available` status
- Show `last_activity_at` timestamp
- Add filter: "Available Agents" quick-filter

---

## Phase 7: Permissions & Role Gating

### 7.1 Portal Route Guard

Currently, all portal routes are `auth`-protected but not role-checked. Add:
- `role:super_admin,Admin,Operator` middleware to sensitive routes
- Or a custom permission-based approach: create permissions like `view_reports`, `manage_users`, etc.

### 7.2 Agent Role Enforcement

- `PATCH /api/mobile/agent/*` → require `role:Agent`
- Remittance agent actions (accept, execute) → check `hasRole('Agent')`
- `GET /api/mobile/agent/orders` → only return orders assigned to this agent

---

## Phase 8: Seeders

### 8.1 CountrySeeder

Create a comprehensive seeder (or artisan command) with all ~195 countries. Each record:
- `name` e.g. "Zambia"
- `code` e.g. "ZM"
- `phone_code` e.g. "+260"
- `flag_url` e.g. `https://flagcdn.com/w80/zm.png`
- `is_active` → true for common corridor countries, false for others by default

**Arrange corridors:**
Since the platform focuses on specific corridors, set `is_active = true` for:
- Zambia (ZM), DR Congo (CD), South Africa (ZA), Kenya (KE), Nigeria (NG), Ghana (GH), Tanzania (TZ), Uganda (UG), Rwanda (RW), Burundi (BI), Botswana (BW), Zimbabwe (ZW)

### 8.2 Currency Seeder (update existing)

Add common African currencies with `is_default` and `enabled`:
- ZMW, NGN, KES, UGX, TZS, RWF, BIF, ZAR, BWP, GHS, XOF, XAF, USD (as neutral), EUR

### 8.3 SupportCategorySeeder Enhancement

Add a default category for remittance disputes:
- "Payment Dispute"
- "Agent Not Responding"
- "Incorrect Amount"
- "Other Remittance Issue"

---

## Phase 9: Recommended File Changes

### New Files to Create

```
app/Models/Country.php
app/Models/AgentProfile.php (optional)
app/Http/Middleware/CheckApproved.php
app/Http/Controllers/Api/RemittanceController.php (for /api/mobile/remittances)
app/Http/Controllers/Api/Mobile/AgentController.php
app/Http/Controllers/Api/Admin/CountryController.php
app/Http/Controllers/Api/Admin/ReportController.php
app/Http/Controllers/Api/Mobile/ReportController.php (user self-reports)
app/Exports/RemittancesExport.php
app/Exports/DebtsExport.php
app/Exports/AgentPerformanceExport.php
database/migrations/XXXX_XX_XX_create_countries_table.php
database/migrations/XXXX_XX_XX_add_country_and_currency_to_users.php
database/migrations/XXXX_XX_XX_add_agent_fields_to_users.php
database/migrations/XXXX_XX_XX_add_remittance_fields_to_money_transfers.php
database/migrations/XXXX_XX_XX_add_last_activity_to_users.php
database/seeders/CountrySeeder.php
database/seeders/CurrencySeeder.php (update existing)
resources/js/portal/pages/Countries/Index.vue
resources/js/portal/pages/Reports/Index.vue
```

### Files to Modify

```
app/Models/User.php                    — add country, currency, agent, last_activity
app/Models/MoneyTransfer.php           — new statuses, new columns
app/Http/Controllers/Api/MoneyTransferController.php  — adapt to new flow
app/Http/Controllers/Api/UserController.php           — add agent fields handling
app/Http/Controllers/Api/Mobile/TransferController.php — keep as legacy or redirect
config/permission.php                  — any permission changes
routes/api.php                         — new route groups
resources/js/portal/router/index.js    — add new routes
resources/js/portal/components/Sidebar.vue — add new items
resources/js/portal/pages/Transfers/Index.vue — adapt to new statuses
resources/js/portal/pages/Users/Index.vue  — show country/agent status
```

---

## Implementation Order (Recommended)

| Step | What | Why First |
|---|---|---|
| 1 | **Country model + migration + seeder** | Foundation for everything |
| 2 | **User: add country, currency, agent fields** | Allows registration flow to use them |
| 3 | **CheckApproved middleware** | Security gate before any feature |
| 4 | **Agent availability + agents list** | Core feature needed by remittance flow |
| 5 | **Remittance migration (new columns/statuses)** | The product itself |
| 6 | **Remittance API (mobile)** | Mobile-first approach |
| 7 | **Support ticket → remittance linking** | Dispute handling |
| 8 | **Debt tracking** | Key differentiator |
| 9 | **Portal: Countries page** | Admin needs to manage countries |
| 10 | **Portal: Reports + Exports** | Admin visibility |
| 11 | **Portal: Transfers page adaptation** | Reflect new workflow |
| 12 | **User self-reports** | User visibility |
| 13 | **Role gating** | Production hardening |

---

## Phase 10: Double Auth — Session + API Key

### Concept
A single middleware (`auth.api_key`) that bridges API key auth into the same `web` guard that session auth uses, so downstream code (`$request->user()`, Spatie role checks, etc.) works identically regardless of how authentication was established.

### 10.1 Create `api_keys` Migration

| Column | Type | Purpose |
|--------|------|---------|
| id | bigIncrements | |
| user_id | FK->users, nullable, nullOnDelete | Owner of key |
| name | string | Human label (e.g. "Production App") |
| key | string(64), unique | SHA-256 hash of the plaintext key |
| permissions | json, nullable | Granular permission overrides |
| allowed_ips | json, nullable | IP allowlist |
| last_used_at | timestamp, nullable | Track usage |
| expires_at | timestamp, nullable | Auto-expiry |
| is_active | boolean, default true | Soft revoke |
| timestamps | | |

### 10.2 `ApiKey` Model

- `$casts`: `permissions` (array), `allowed_ips` (array), `is_active` (boolean), `last_used_at` (datetime), `expires_at` (datetime)
- `belongsTo(User::class)`

### 10.3 `ApiKeyMiddleware`

```php
handle(Request):
  key = X-API-Key header OR api_key query param
  if !key → 401 "API key is missing"

  apiKey = ApiKey where key = sha256(incoming_key), is_active = true, (expires_at null or > now)
  if !apiKey → 401 "Invalid or inactive API key"

  if apiKey.allowed_ips is set:
    if request.ip not in allowed_ips → 403 "IP not allowed"

  apiKey.update last_used_at = now()

  Auth::shouldUse('web')
  if apiKey.user_id:
      Auth::loginUsingId(apiKey.user_id)

  request->attributes->set('auth.api_key', true)
  request->attributes->set('api_key', apiKey)

  return next(request)
```

### 10.4 Route Groups

| Routes | Middleware | Auth method |
|--------|-----------|-------------|
| `/api/portal/*` | `auth` (default `web` guard) | Session cookie (SPA) |
| `/api/v1/*` | `auth.api_key` | `X-API-Key` header or `?api_key=` |

### 10.5 `ApiKeyController` (CRUD)

- `index`: list keys for current user (never expose key value)
- `store`: generate key with `ml_` prefix + random chars, store SHA-256 hash, return plaintext once
- `destroy` / revoke: set `is_active = false`

### Key Design Decisions
- **No Bearer token conflict** — API keys use `X-API-Key` header, not `Authorization`, so Sanctum tokens and API keys coexist
- **Session bridging** — `Auth::shouldUse('web')` + `Auth::loginUsingId()` makes Spatie middleware, `$request->user()`, and `Auth::user()` work identically
- **Plaintext returned once** — database stores only SHA-256 hash
- **IP allowlisting** — optional per-key restriction

---

## Phase 11: Enforce Two-Factor Authentication on Portal

### Problem
Portal login (`POST /api/portal/login`) bypasses Fortify's 2FA pipeline by calling `Auth::guard('web')->attempt()` directly, so users never see a TOTP challenge. No middleware enforces 2FA setup.

### 11.1 Fix Fortify & Filament Config

- Remove duplicate `Features::twoFactorAuthentication()` and `Features::resetPasswords()` entries in `config/fortify.php`
- Replace non-existent `'2fa'` middleware alias in `config/filament.php` with actual middleware class

### 11.2 `EnsureTwoFactorAuth` Middleware

Applied to all `api/portal/*` routes. Logic:

```
if user has two_factor_confirmed_at:
    if session 'two_factor_passed' flag is false:
        → 403 { requires_2fa_challenge: true }
    else:
        → allow request
else:
    → 403 { requires_2fa_setup: true }
```

### 11.3 `TwoFactorController`

Endpoints (all under `api/portal/2fa/*`, behind auth but not 2fa middleware):

| Method | Path | Purpose |
|--------|------|---------|
| GET | `status` | Returns whether 2FA is enabled/confirmed |
| POST | `setup` | Generates TOTP secret via `pragmarx/google2fa`, returns QR URL + recovery codes |
| POST | `confirm` | Verifies TOTP code, sets `two_factor_confirmed_at` |
| POST | `challenge` | Verifies TOTP code, sets session `two_factor_passed` flag |
| POST | `disable` | Requires current TOTP code to disable 2FA |

### 11.4 Route Structure

```
POST /api/portal/login                          → public (no middleware)
GET  /api/user, POST /api/logout                → auth + approved + role

api/portal/2fa/*                                → auth + approved + role (NO 2fa middleware)
  GET  /status
  POST /setup
  POST /confirm
  POST /challenge
  POST /disable

api/portal/* (transfers, users, countries, etc.)  → auth + approved + role + 2fa
```

### 11.5 Security Fixes

- Added `two_factor_secret` and `two_factor_recovery_codes` to User model's `$hidden` array to prevent leaking encrypted secrets in JSON responses.
- `portalLogin()` response now includes `two_factor` status flags so the Vue frontend can conditionally redirect to 2FA setup/challenge pages.
