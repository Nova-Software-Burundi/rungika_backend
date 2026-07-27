# Back Office Portal Revamp Plan

## Context

The platform has shifted from a P2P USDT transfer platform to an **agent-centric remittance marketplace**. The back office portal must reflect this new identity. The current portal was built for the old model and carries legacy P2P/crypto terminology, unused features, and a default view that no longer serves the primary use case.

**Key business rule:** Debt is calculated by proof-of-upload — when an agent executes an order and uploads proof, the client owes the agent. When a client uploads proof of payment and the agent hasn't executed yet, the agent owes the client. This is the most critical operational metric.

---

## Current State Summary

| Area | Current State | Problem |
|---|---|---|
| **Default page** | `/transfers` — a create-transfer form + transaction list | Admins should NOT create remittances; only mobile users and agents do |
| **Sidebar** | 16 flat items including Ads, Trades, Fees, Prices, Revenue | Many are legacy P2P/crypto features irrelevant to remittance ops |
| **Topbar subtitle** | "USDT receipt and payout desk" | Wrong identity |
| **Sidebar brand** | "Transfer Desk" with ArrowLeftRight icon | Should reflect remittance marketplace |
| **Dashboard** | None — root `/` redirects to `/transfers` | No at-a-glance operational overview |
| **Debt visibility** | Scattered across Reports tab and Transfer detail | Not front-and-center where ops team needs it |
| **Create remittance** | Admins can create transfers via the portal | Should be removed — remittances are created by mobile users |
| **Terminology** | "Transfer", "USDT proof", "Payout proof" | Should be "Remittance", "Requester proof", "Agent proof" |

---

## Revamp Plan

### 1. New Dashboard Page (`/dashboard`) — NEW DEFAULT

**Route:** `/dashboard` (becomes the new root redirect from `/`)

**Purpose:** At-a-glance operational overview for the admin/operator.

**Layout:** Grid of stat cards + summary tables.

#### Top Row — Key Metrics (4 cards)

| Card | Value | Source |
|---|---|---|
| **Active Remittances** | Count of remittances in `pending` + `accepted` status | `GET /portal/transfers/stats` (extend) |
| **Client Debt Owed to Agents** | Sum of `send_amount` where `executor_debt = true` (agent executed without proof, client owes agent) | New endpoint or extend stats |
| **Agent Debt Owed to Clients** | Sum of `send_amount` where `requester_debt = true` (client uploaded proof, agent hasn't executed) | New endpoint or extend stats |
| **Completed This Month** | Count of `completed` remittances in current month | `GET /portal/transfers/stats` |

#### Second Row — Debt Ledger (the critical view)

**"Outstanding Debts" table** showing every remittance with an active debt:

| Column | Description |
|---|---|
| Reference | Remittance reference |
| Client | Remittance requester name |
| Agent | Assigned agent name |
| Amount | `send_amount` + `send_currency` |
| Who Owes | "Client owes Agent" or "Agent owes Client" |
| Reason | "Agent executed (no proof)" or "Client paid (agent pending)" |
| Status | Current remittance status |
| Created | Date |

**Default sort:** By amount descending (highest debt first).

#### Third Row — Quick Stats

- **Agent Performance** — top 5 agents by completion rate (reuse data from Reports)
- **Recent Activity** — last 10 status changes across all remittances

---

### 2. Sidebar Revamp

#### Menu Reorganization — Grouped with sections

**Before (16 flat items):**

Transfers, Users, Countries, Currencies, Assets, Payment Methods, Trades, Ads, Fees, Prices, Revenue, Reports, Ratings, Announcements, Support, Settings

**After (grouped, remittance-focused):**

| Group | Items | Notes |
|---|---|---|
| **Operations** | Dashboard, Remittances, Support | Core daily-use pages |
| **People** | Users, Agents, Ratings | User and agent management |
| **Finance** | Debts, Reports, Revenue | Financial oversight |
| **Configuration** | Countries, Currencies, Payment Methods, Announcements, Settings | Setup and config |
| **Legacy** (collapsed by default) | Ads, Trades, Assets, Fees, Prices | Old P2P features — kept but deprioritized |

#### Brand Update

| Element | Before | After |
|---|---|---|
| Sidebar brand | "Transfer Desk" | "Remittance Portal" |
| Sidebar icon | ArrowLeftRight | Banknote (or Send) |
| Topbar subtitle | "USDT receipt and payout desk" | "Agent Remittance Operations" |
| App title (constants.js) | "Martin Logistics Portal" | "Rungika Remittance Portal" |

---

### 3. Remittances Page Revamp (`/remittances`)

**Route rename:** `/transfers` → `/remittances` (keep `/transfers` as redirect for backward compat)

#### Remove: Create Transfer Form

The entire left panel "New Transfer" form is removed. Admins do not create remittances — mobile users do.

#### Keep: Transaction List + Detail View

The right panel table and detail view stay, but with updates:

**Table column changes:**

| Before | After |
|---|---|
| Reference | Reference |
| Parties (sender + recipient) | Client → Agent |
| Amount | Amount |
| Status | Status (color-coded) |
| Debt | Debt (enhanced: "Client owes" / "Agent owes" / "Clean") |
| Created | Date |

**Detail view changes:**

- Rename "Requester Proof" → "Client Proof of Payment"
- Rename "Executor Proof" → "Agent Proof of Execution"
- Rename "Mark Accepted" → "Agent Accepted"
- Rename "Mark Executed" → "Agent Executed"
- Rename "Mark Completed" → "Client Confirmed"
- Remove USDT-specific terminology from labels and warnings
- Remove "USDT amount", "Exchange rate", "Payout currency" fields (these are legacy P2P fields)
- Debt status card shows clear directional debt: "Client owes Agent $X" or "Agent owes Client $Y"

**Filter changes:**

- Add "Has Debt" quick filter toggle
- Add "My Agents" filter (filter by assigned agent)
- Keep existing status filter

---

### 4. Reports Page Revamp

#### Debts Tab Enhancement

Current: basic table with side filter.

After: **Debt Ledger** — the same data as the dashboard but full-page with more detail and export.

Add columns:
- Agent phone number
- Client phone number
- Days outstanding (calculated from created_at)
- Amount in default currency (with conversion)

Add filters:
- Date range
- Amount range
- Specific agent / specific client search
- Days outstanding threshold (e.g. "debts older than 7 days")

#### Agent Performance Tab Enhancement

Add:
- Total debt outstanding per agent
- Average days to complete a remittance
- Debt-to-completion ratio

#### Remove: USDT-specific filters

Remove "USDT received" status filter. Replace with remittance-native statuses.

---

### 5. Topbar Update

| Element | Before | After |
|---|---|---|
| Subtitle left | "Money Transfer Operations" | "Remittance Operations" |
| Subtitle right | "USDT receipt and payout desk" | "Agent Marketplace" |
| Notification bell | Non-functional | Wire to actual notifications (stretch goal) |

---

### 6. Backend API Changes Needed

#### New/Modified Endpoints

| Endpoint | Change | Purpose |
|---|---|---|
| `GET /portal/transfers/stats` | **Extend** | Add `client_debt_total`, `agent_debt_total`, `active_count`, `completed_this_month` |
| `GET /portal/debts/ledger` | **New** | Dedicated debt ledger with sorting, filtering, pagination |
| `POST /portal/transfers` | **Remove** (or gate behind role) | Admins should not create remittances |
| `GET /portal/transfers` | **Modify** | Accept `has_debt`, `agent_id`, `client_id` filters |

#### Debt Ledger Response Format

```json
{
  "data": [
    {
      "id": 42,
      "reference": "MT-20260725-ABC123",
      "send_amount": 500.00,
      "send_currency": "USD",
      "client": { "id": 10, "name": "Mary Requester", "phone": "+260..." },
      "agent": { "id": 5, "name": "John Agent", "phone": "+260..." },
      "debt_side": "agent_owes_client",
      "debt_reason": "Client uploaded proof, agent has not executed",
      "status": "accepted",
      "days_outstanding": 5,
      "created_at": "2026-07-20T10:30:00.000000Z"
    }
  ],
  "summary": {
    "total_client_debt": 12500.00,
    "total_agent_debt": 8300.00,
    "total_debt_count": 23
  },
  "current_page": 1,
  "last_page": 3
}
```

---

### 7. Files to Modify

| File | Change |
|---|---|
| `resources/js/config/menu.js` | Reorganize into grouped menu with sections |
| `resources/js/portal/router/index.js` | Add `/dashboard` route, rename `/transfers` to `/remittances`, add redirects |
| `resources/js/portal/pages/Dashboard/Index.vue` | **NEW** — Dashboard with metrics + debt ledger |
| `resources/js/portal/pages/Remittances/Index.vue` | **Rename from Transfers** — Remove create form, update terminology, enhance debt display |
| `resources/js/portal/pages/Reports/Index.vue` | Enhance Debts tab, update Agent Performance tab |
| `resources/js/portal/components/navigation/SidebarMenu.vue` | Support grouped menu sections |
| `resources/js/portal/components/navigation/Topbar.vue` | Update subtitle text |
| `resources/js/portal/utils/constants.js` | Update app name |
| `resources/js/portal/components/navigation/SidebarItem.vue` | Support section headers/dividers |
| `app/Http/Controllers/Api/MoneyTransferController.php` | Extend stats, add debt ledger endpoint, gate create behind role |
| `routes/api.php` | Add debt ledger route |

### 8. Files to Clean Up / Remove

| File | Reason |
|---|---|
| `resources/js/portal/pages/Settings.vue` | Placeholder — either implement or remove |
| `resources/js/portal/components/charts/*.vue` | Unused legacy chart components — remove or integrate into dashboard |
| `resources/js/portal/api/trips.js`, `drivers.js`, `invoices.js` | Legacy logistics API wrappers — remove |
| `resources/js/portal/components/search/GlobalSearch.vue` | Legacy logistics search — remove |

---

## Implementation Order

| Phase | Scope | Files |
|---|---|---|
| **Phase 1** | Dashboard + sidebar restructure + brand update | New Dashboard page, menu.js, SidebarMenu, SidebarItem, Topbar, constants.js, router |
| **Phase 2** | Remittances page revamp | Remittances/Index.vue (rename + rewrite), backend stats extension |
| **Phase 3** | Debt ledger (backend + dashboard integration) | New backend endpoint, Dashboard page debt table |
| **Phase 4** | Reports enhancement | Reports/Index.vue updates |
| **Phase 5** | Cleanup | Remove legacy files, clean up unused components |

---

## UI Mockup — Dashboard Layout

```
┌──────────────────────────────────────────────────────┐
│  REMITTANCE PORTAL           [🔔]  John Admin [▼]   │
│  Agent Remittance Operations                         │
├──────────┬───────────────────────────────────────────┤
│          │                                           │
│ Dashboard│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐    │
│ ─────────│  │Active│ │Client│ │Agent │ │Done  │    │
│ Remit-   │  │  23  │ │Owes  │ │Owes  │ │ 142  │    │
│ tances   │  │      │ │$12.5K│ │$8.3K │ │this  │    │
│          │  └──────┘ └──────┘ └──────┘ │month │    │
│ Support  │                              └──────┘    │
│ ─────────│                                           │
│ People   │  Outstanding Debts                       │
│ ─────────│  ┌─────────────────────────────────────┐ │
│ Users    │  │ Ref      │ Client │ Agent │ Debt     │ │
│ Agents   │  │ MT-0725  │ Mary   │ John  │ Client   │ │
│ Ratings  │  │          │        │       │ owes $500│ │
│ ─────────│  │ MT-0723  │ Peter  │ Jane  │ Agent    │ │
│ Finance  │  │          │        │       │ owes $200│ │
│ ─────────│  └─────────────────────────────────────┘ │
│ Debts    │                                           │
│ Reports  │  Recent Activity                         │
│ Revenue  │  ┌─────────────────────────────────────┐ │
│ ─────────│  │ • MT-0725 executed by John Agent     │ │
│ Config   │  │ • MT-0723 confirmed by Peter Client  │ │
│ ─────────│  │ • MT-0720 dispute opened             │ │
│ Countries│  └─────────────────────────────────────┘ │
│ Currency │                                           │
│ Pay Mthd │                                           │
│ Settings │                                           │
│ ─────────│                                           │
│ Legacy ▾ │                                           │
│  Ads     │                                           │
│  Trades  │                                           │
└──────────┴───────────────────────────────────────────┘
```
