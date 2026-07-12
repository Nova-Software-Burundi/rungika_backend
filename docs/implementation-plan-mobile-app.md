# Implementation Plan: Mobile App (Flutter/React Native)

## App Identity

**Name:** Rungika Marketplace
**Tagline:** Send money with confidence
**Style:** Clean, trust-oriented, operations-focused
**Primary users:** Remittance requesters + agents (both use the same app, different flows)

---

## How Existing API Maps to Your Requirements

| Your Requirement | Existing API Endpoint(s) | Gaps / Needed Changes |
|---|---|---|
| Auth: Register with country | `POST /auth/register` | Need to add `country_id`, `preferred_currency_id` |
| Auth: Login | `POST /auth/login`, `POST /auth/request-whatsapp-otp`, etc | Already works |
| Auth: Approval gate | No endpoint (it's middleware) | New `CheckApproved` middleware will return 403 if not approved |
| See agents with flags/ratings | None | New `GET /agents` |
| Create remittance & pick agent | `POST /transfers` (legacy) | New `POST /remittances` |
| Upload payment proof (optional) | `POST /transfers/{id}/usdt-proof` | New `POST /remittances/{id}/requester-proof` |
| Agent dashboard/orders | `GET /transfers` (scoped) | New `GET /agent/orders` |
| Agent: accept order | None | New `POST /agent/orders/{id}/accept` |
| Agent: execute + proof | `POST /transfers/{id}/payout-proof` | New `POST /agent/orders/{id}/execute` |
| Support tickets for remittances | `POST /support/tickets` (not wired to transfers) | New `POST /support/remittance-tickets` |
| Debt tracking | None | New `GET /remittances/debts` |
| User self-reports | None | New `GET /reports/my-activity` |
| Profile | `GET /profile`, `PUT /profile` | Add country + currency fields |
| Device tokens | `POST /device-tokens` | Already works |
| Notifications | `GET /notifications` | Already works |

---

## Screens & Data Flow

### Screen 1: Onboarding / Auth

**Flow:** Register → wait for admin approval → login → use app

**Register screen:**
- Full name (required)
- Email (optional, can be auto-generated)
- Phone number (required) — with country code selector (+260, +243, etc.)
- Country selector (dropdown, sourced from `GET /mobile/countries`) — determines default currency
- Accept terms checkbox

**On submit: `POST /auth/register`** with new `country_id` field

**Post-registration:** Show "Your account is under review. We'll notify you when approved."

**Login screen:**
- Phone number or email
- Password (if email/password login) **or** OTP button (phone login)
- For phone login: OTP input screen (via WhatsApp or Firebase)

**Session persistence:**
- Store Sanctum token in secure storage
- On app launch, call `GET /profile` to check token validity
- If 401, redirect to login
- If 403 (not approved), show pending approval screen

**Logout:** `POST /auth/logout` + clear local storage

### Screen 2: Home / Marketplace (Requester view)

**Top section:** Account summary cards
- My total remittances (count)
- Pending (count)
- In debt (count) — red accent if > 0
- Completed (count)

**Agent search bar:**
- Text input: search by agent name
- Country filter dropdown (pre-filled with user's country)
- Available toggle (only show available agents)
- Online toggle (only show recently active)
- Sort by: rating, completion rate, total jobs

**Agent results list:**
```json
{
    "id": 1,
    "name": "John",
    "photo_url": "...",
    "country": { "code": "ZM", "flag_url": "..." },
    "is_available": true,
    "is_online": true,
    "last_activity_at": "...",
    "average_rating": 4.8,
    "total_jobs": 150
}
```

**Agent card layout:**
- [Flag] [Photo] [Name] [Online dot]
- Rating stars + total jobs count
- Completion rate bar
- "Select" button (CTA)

**Bottom tab:**
- `Home` (agents marketplace)
- `My Orders` (my remittances)
- `Agent Mode` (only visible if user has Agent role)
- `Notifications` (with badge count)
- `Profile`

### Screen 3: Create Remittance (after selecting an agent)

**Agent summary bar at top** (selected agent's name, flag, photo, rating — read-only)

**Step 1: Destinator Details**
| Field | Required | Notes |
|---|---|---|
| Full name | Yes | |
| Phone number | Yes | With country code |
| Address | No | Free text |
| Payment method | Yes | Dropdown from `GET /payment-methods` |
| Account number | Yes | Bank account / mobile money number |
| Additional notes | No | Free text |

**Step 2: Amount & Currency**
| Field | Required | Notes |
|---|---|---|
| Send amount | Yes | Number input |
| Currency | Yes | Dropdown from `GET /currencies` (pre-filled with user's preferred) |

**Step 3: Payment Proof (Optional)**
- "Upload proof of payment" button → camera/gallery picker
- Can skip this step → checked as "debt" on requester side
- If skipped, show warning: "You can skip this, but it will be recorded as an outstanding debt."

**Confirm & Submit: `POST /remittances`**
```json
{
    "agent_id": 1,
    "destinator_name": "Jane",
    "destinator_phone": "+260970000001",
    "destinator_address": "Lusaka, Zambia",
    "destinator_payment_method_id": 1,
    "destinator_account_number": "0123456789",
    "send_amount": 500.00,
    "send_currency": "USD",
    "notes": "Family support"
}
```

If proof was chosen, upload immediately after creation via `POST /remittances/{id}/requester-proof`

### Screen 4: My Orders (Requester)

**Tab bar:** All | Pending | Accepted | Executed | Completed | Disputed | Debts

**List cards:**
- Reference (e.g. `MT-20260713-ABC123`)
- Status badge (color-coded)
- Agent name + flag
- Amount + currency
- Destinator name (first line)
- Date created
- **Debt badge** if requester owes proof (red dot/icon)

**Empty state:** "No orders yet. Find an agent to start."

**Pull to refresh.**

### Screen 5: Order Detail (Requester)

**Header:** Reference, status badge, created date

**Destinator info card:**
- Name, phone, address
- Payment method, account number

**Amount card:** Send amount + currency

**Status Timeline (vertical progress):**
```
Pending → Accepted → Executed → Completed
```
- Optional steps in between:
  - "Proof uploaded" step (if requester uploaded)
  - "Execution proof uploaded" (if agent uploaded)

**Action buttons (based on status):**
| Status | Requester Action |
|---|---|
| pending | Cancel |
| accepted | (waiting for agent to execute) |
| executed | Confirm remittance completed (if agent provided proof) — or wait for admin |
| completed | (done) |
| disputed | Support ticket linked |
| cancelled | (done) |

- Upload proof button (if not yet uploaded and status != cancelled/completed)
- "Open Support Ticket" button (any status except completed/cancelled)

### Screen 6: Agent Mode / Dashboard

**Access:** Only visible in bottom tab if user has `Agent` role

**Tab bar:** New Orders | Active | History | Debts

**New Orders tab** (status: pending — not yet accepted):
- List of remittances where no agent has accepted yet
- Each card: Requester name, amount, destinator name, payment method, date
- "Accept" button → `POST /agent/orders/{id}/accept`

**Active tab** (status: accepted):
- List of orders I've accepted
- Tap → Order detail with full destinator info:
  - Name, phone, address, payment method, account number
  - "Execute" button → `POST /agent/orders/{id}/execute`
    - Opens camera/gallery to upload execution proof (can be skipped = debt)
    - Notes field for "paid via..."
- Shows requester proof if uploaded

**History tab** (status: executed, completed, cancelled, disputed):
- Past orders with status badges
- Debt filter: only show where agent owes proof

**Debts tab:**
- Orders where I (agent) have not uploaded execution proof
- "Upload proof now" action button

### Screen 7: Agent Order Detail

**Requester info card:**
- Name, phone (tap to call), registered country/flag
- Photo if available

**Destinator info card:**
- Full details: name, phone, address, payment method, account number
- "Copy account number" button

**Amount card:** Send amount + currency

**Proof section:**
- Requester proof (if uploaded) — view in full screen, zoom
- "Upload execution proof" button

**Action:**
- "Mark as executed" → file picker for proof + optional notes → `POST /agent/orders/{id}/execute`
  - If proof skipped → order marked executed but with debt flag
- Support ticket link

### Screen 8: Support Tickets

**List:** All tickets user has created (or assigned to, if agent/admin)

**Create ticket from order:**
- Pre-filled: subject_type = "money-transfer", subject_id = order id
- Category dropdown (from `GET /support/categories`)
- Title, description, priority

**Ticket detail:**
- Status badge
- Category
- Messages (chat-style, with user name + timestamp)
- Reply input
- Attachments

### Screen 9: Debt Management

**List view:**
- All orders where I owe proof (my_debts)
- All orders where the other party owes proof (owed_to_me)
- Filter: date range, counterparty, payment method, amount range

**Each item:**
- Reference + status
- Amount + currency
- My outstanding: "You need to upload proof"
- Their outstanding: "Awaiting proof from [name]"
- "Upload Now" action (if I owe) or "Remind" (if they owe)

### Screen 10: Profile & Settings

**Profile card:**
- Name, email, phone
- Country, preferred currency
- KYC status badge
- Agent badge (if Agent role)
- Account type: Requester / Agent / Both

**Settings:**
- Preferred currency (dropdown) → `PUT /profile`
- Notification preferences
- App version
- Logout button

**Agent section (if role = Agent):**
- Availability toggle
- Online/offline status
- Agent photo upload
- Bio/location edit

**Reputation section:**
- Total jobs: X
- Completion rate: X%
- Average rating: X
- Total ratings: X

### Screen 11: Reports (User Self-Reports)

**Accessed from profile screen**

**Report type selector:**
- My Activity (remittances I created)
- My Debt Summary

**Date range filter:** Last 7 days, 30 days, 90 days, custom

**Data shown:**
- Count by status (total, pending, accepted, executed, completed)
- Total volume (sum of send_amount)
- Debt count + total debt amount

**Export:** Share as CSV (download + system share sheet)

---

## Navigation & Architecture

### Bottom Tab Bar

| Tab | Icon | Screens |
|---|---|---|
| Home | 🏠 | Agent marketplace / search |
| Orders | 📋 | My Orders list, Order detail, Create order flow |
| Agent Mode (if agent) | ⚡ | Agent dashboard tabs |
| Support | 🎫 | Ticket list, Ticket detail, Create ticket |
| Profile | 👤 | Profile, Settings, Reports, Agent settings |

### Stack Navigation within each tab

**Home tab:**
- AgentsList → AgentDetail → CreateRemittanceFlow (steps)

**Orders tab:**
- OrdersList → OrderDetail → (actions)

**Agent tab:**
- AgentDashboard (tabbed) → AgentOrderDetail → (actions)

**Support tab:**
- TicketList → TicketDetail

**Profile tab:**
- Profile → Settings, AgentSettings, Reports, About

### Deep Linking

When notification is tapped:
- If "new order": open Agent tab → order detail
- If "order updated": open Orders tab → order detail
- If "ticket updated": open Support tab → ticket detail

---

## State Management

| State | Source | Where |
|---|---|---|
| Auth token + user | Secure storage + API | Global provider |
| Countries | API (GET /mobile/countries) — cached | Global provider |
| Currencies | API (GET /currencies) — cached | Global provider |
| Payment methods | API (GET /payment-methods) — cached | Global provider |
| Agent list | API (GET /agents) — fresh each time | Home screen |
| My remittances | API (GET /remittances) — paginated | Orders list |
| Agent orders | API (GET /agent/orders) — paginated | Agent tab |
| Notifications | API (GET /notifications) — combined with push | Global badge |

**Caching strategy:** Countries, currencies, and payment methods are static and rarely change. Cache them indefinitely with a forced refresh button in settings.

---

## Error Handling

| Scenario | Behaviour |
|---|---|
| 401 Unauthenticated | Clear token, redirect to login |
| 403 Not approved | Show "pending approval" screen with contact admin message |
| 403 Forbidden (not role) | Show message + disable action |
| 422 Validation | Show field-level error messages |
| Network error | Show offline banner + retry button |
| Upload interrupted | Show retry option, resume from last byte if possible |

---

## Push Notification Flow

1. On login: register Expo/FCM/APNs token: `POST /device-tokens`
2. On logout: unregister: `DELETE /device-tokens/{token}`
3. Handle incoming push:
   - Extract `remittance_id` or `ticket_id` from notification payload
   - Navigate to relevant screen

**Notification types to handle:**
- "Your account has been approved" → refresh auth
- "Agent accepted your order" → open order detail
- "Agent executed your order" → open order detail
- "Requester confirmed your order" → update agent dashboard
- "New remittance assigned to you" → open agent order detail
- "Support ticket updated" → open ticket detail

---

## File Structure (Flutter example)

```
lib/
  main.dart
  app.dart
  config/
    api_config.dart        — base URL, timeouts
    theme.dart             — colors, text styles
  models/
    user.dart
    country.dart
    currency.dart
    payment_method.dart
    remittance.dart
    agent.dart
    support_ticket.dart
  services/
    api_client.dart        — HTTP client with auth header
    auth_service.dart      — login, register, token storage
    remittance_service.dart — CRUD calls
    agent_service.dart     — agent list, accept, execute
    support_service.dart   — tickets, messages
    notification_service.dart — push handling
    storage_service.dart   — secure token storage
  providers/
    auth_provider.dart
    country_provider.dart
    currency_provider.dart
    remittance_provider.dart
    agent_provider.dart
    notification_provider.dart
  screens/
    auth/
      register_screen.dart
      login_screen.dart
      otp_verification_screen.dart
      pending_approval_screen.dart
    home/
      agent_list_screen.dart
      agent_detail_screen.dart
    remittance/
      create_remittance_screen.dart (details → amount → proof)
      remittance_list_screen.dart
      remittance_detail_screen.dart
    agent/
      agent_dashboard_screen.dart
      agent_order_detail_screen.dart
    support/
      ticket_list_screen.dart
      ticket_detail_screen.dart
      create_ticket_screen.dart
    debt/
      debt_list_screen.dart
    profile/
      profile_screen.dart
      settings_screen.dart
      agent_settings_screen.dart
      reports_screen.dart
  widgets/
    agent_card.dart
    remittance_card.dart
    status_badge.dart
    proof_picker.dart
    loading_indicator.dart
    error_banner.dart
    empty_state.dart
    timeline.dart
    country_flag_with_name.dart
```

---

## Implementation Order

| Step | Screen/Feature | Why this order |
|---|---|---|
| 1 | **Project setup** (Flutter init, API client, auth service, secure storage) | Foundation for everything |
| 2 | **Auth screens** (register, login, OTP, pending approval) | Users need to log in first |
| 3 | **Countries, currencies, payment methods** (models + providers + caching) | Reference data needed everywhere |
| 4 | **Agent list + agent card widget** (Home tab) | Core marketplace view |
| 5 | **Create remittance flow** (3 steps + proof upload) | Primary user action |
| 6 | **Orders list + detail** (My Orders tab) | User needs to see what they created |
| 7 | **Agent dashboard** (New Orders, Accept, Execute) | Core agent flow |
| 8 | **Support tickets list + create** (Support tab) | Dispute handling |
| 9 | **Debt management screen + filters** | Key differentiator |
| 10 | **Profile, settings, agent settings** | User management |
| 11 | **Self-reporting (activity + debt)** | User visibility |
| 12 | **Push notifications** | Engagement |
| 13 | **Deep linking** | UX polish |

---

## Platform-Specific Notes

### Flutter (recommended)
- State: Riverpod or Bloc
- HTTP: `Dio` with auth interceptor (auto-refresh on 401)
- Secure storage: `flutter_secure_storage`
- Notifications: `firebase_messaging` + `flutter_local_notifications`
- Image: `image_picker` (camera + gallery)
- Maps: not needed (agents are by country, not GPS)
- Caching: `hive` or `sqflite`

### React Native / Expo
- State: Zustand or Redux Toolkit
- HTTP: `axios` with auth interceptor
- Secure storage: `expo-secure-store`
- Notifications: `expo-notifications` + FCM
- Image: `expo-image-picker`
- Caching: `react-native-mmkv` or `AsyncStorage`

### Both platforms share the same backend API
All business logic is on the Laravel side. The mobile apps are thin clients that render data and submit forms. No offline transaction creation (v1). Simple offline detection with cached reference data is sufficient.
