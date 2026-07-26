# Incremental Improvements

Living checklist of gaps, stubs, and polish items. Each item references the feature test plan section it unblocks. Update this file as items are completed — delete done items, add new ones as they surface during testing.

---

## Status Legend

| Symbol | Meaning |
|--------|---------|
| [ ] | Not started |
| [~] | In progress |
| [x] | Done |
| [!] | Blocked (backend / external dependency) |

---

## 1. Remittance Lifecycle — Missing Actions

These block test plan sections **3.3**, **3.9**, **3.10**.

### 1.1 Cancel button on OrderDetailFragment (Requester)

- **Unblocks:** §3.10 — Requester cancels a pending remittance
- **Backend:** `POST /mobile/remittances/{id}/cancel` — already wired in `UserRepository.cancelRemittance()` and `ApiService.cancelRemittance()`
- **What to do:**
  - Add a `btn_cancel` button to `fragment_order_detail.xml`, visible only when `status == "pending"`
  - On tap: confirm dialog → call `viewModel.cancelRemittance(id)` → pop back on success
  - Wire `cancelRemittance()` in `OrderDetailViewModel` (or `MyOrdersViewModel`) if not already exposed
- **Files:** `OrderDetailFragment.java`, `fragment_order_detail.xml`, `MyOrdersViewModel.java`

### 1.2 Confirm button on OrderDetailFragment (Requester)

- **Unblocks:** §3.9 — Requester confirms completion
- **Backend:** `POST /mobile/remittances/{id}/confirm` — already wired in `UserRepository.confirmRemittance()` and `ApiService.confirmRemittance()`
- **What to do:**
  - Add a `btn_confirm` button to `fragment_order_detail.xml`, visible only when `status == "executed"`
  - On tap: confirm dialog → call `viewModel.confirmRemittance(id)` → pop back on success
  - Wire `confirmRemittance()` in `OrderDetailViewModel` or `MyOrdersViewModel`
- **Files:** `OrderDetailFragment.java`, `fragment_order_detail.xml`, `MyOrdersViewModel.java`

### 1.3 Proof upload — file picker (Requester side)

- **Unblocks:** §3.3 — Requester uploads proof (no debt)
- **Backend:** `POST /mobile/remittances/{id}/requester-proof` — endpoint defined in `ApiConfig.remittanceRequesterProof(id)` but no `ApiService` method exists
- **What to do:**
  - Replace stub in `CreateRemittanceFragment.btn_upload_proof` with `ActivityResultContracts.GetContent` or `PickVisualMedia`
  - Set `selectedProofPath` on file selection; show filename in `text_proof_name`
  - Add `uploadRequesterProof(int id, String filePath, ApiCallback)` to `ApiService` using Volley `MultipartRequest` or equivalent
  - Call after remittance is created if proof was selected
- **Files:** `CreateRemittanceFragment.java`, `ApiService.java`, `ApiConfig.java`
- **Note:** `ApiService` currently has no multipart capability — a `MultipartRequest` helper class may be needed

### 1.4 Proof upload — file picker (Agent side)

- **Unblocks:** §3.7 — Agent executes with proof
- **Backend:** `POST /mobile/agent/orders/{id}/execute` — current implementation sends only `{ "notes": "..." }`. The backend may accept an optional `proof` file via multipart
- **What to do:**
  - Add a file picker to `AgentOrderDetailFragment` (above the execute button)
  - Send proof file alongside notes in multipart request
  - Update `executeAgentOrder()` in `ApiService` / `UserRepository` to accept a file path parameter
- **Files:** `AgentOrderDetailFragment.java`, `fragment_agent_order_detail.xml`, `ApiService.java`

### 1.5 Payout reference field on Agent execute

- **Unblocks:** §3.7 — Agent provides payout reference (e.g. `CASH-12345`)
- **Backend:** `POST /mobile/agent/orders/{id}/execute` — body may accept `payout_reference`
- **What to do:**
  - Add `et_payout_reference` EditText to `fragment_agent_order_detail.xml` inside the execute card
  - Include in request body: `{ "notes": "...", "payout_reference": "...", "proof": <file> }`
- **Files:** `AgentOrderDetailFragment.java`, `fragment_agent_order_detail.xml`

---

## 2. Debt Management — Action Gaps

### 2.1 Debt "Upload Proof Now" — needs real upload

- **Current:** Navigates to `OrderDetailFragment` which is read-only — no upload UI
- **What to do:** Either:
  - (a) Make `OrderDetailFragment` show the requester proof upload UI when `requesterDebt == true` and `status != "completed" && status != "cancelled"`, or
  - (b) Create a dedicated debt-resolve screen with file picker + API call
- **Files:** `DebtListFragment.java`, `DebtAdapter.java`, `OrderDetailFragment.java`

### 2.2 Debt "Remind" — currently a no-op toast

- **Current:** Shows "Reminder sent to {name}" but no API call is made
- **What to do:** Either:
  - (a) Hit `POST /mobile/support/remittance-tickets` to auto-create a reminder ticket, or
  - (b) Hit `POST /mobile/notifications` with a custom message, or
  - (c) Remove the button and add a note explaining it's not yet available
- **Files:** `DebtAdapter.java`, `DebtListFragment.java`

---

## 3. Order Detail — Missing Info

### 3.1 Status timeline / progress indicator

- **Current:** Shows status as a colored badge only
- **What to do:** Add a vertical stepper (Pending → Accepted → Executed → Completed) to `fragment_order_detail.xml` showing completed steps with checkmarks and the current step highlighted
- **Files:** `OrderDetailFragment.java`, `fragment_order_detail.xml`
- **Note:** The implementation plan (§Screen 5) describes this as a core UX element

### 3.2 Event timeline on order detail

- **Current:** No event history shown
- **What to do:** Fetch and display the `events` array from `GET /mobile/remittances/{id}`. Show each event as a timeline entry: actor name, action, timestamp, notes
- **Files:** `OrderDetailFragment.java`, `ApiModels.Remittance` (may need `events` field parsed), `fragment_order_detail.xml`

### 3.3 Support ticket link on order detail

- **Current:** No way to open a support ticket from an order
- **What to do:** Add "Open Support Ticket" button visible when `status != "completed" && status != "cancelled"`. Navigate to `CreateTicketFragment` with `remittance_id` pre-filled
- **Files:** `OrderDetailFragment.java`, `fragment_order_detail.xml`, `CreateTicketFragment.java` (verify `remittance_id` arg handling)

---

## 4. Agent Dashboard — Polish

### 4.1 Agent order detail — show requester info

- **Current:** Agent order detail shows destinator info but no requester contact
- **What to do:** Add a "Requester" card at the top of `fragment_agent_order_detail.xml` showing requester name, phone (tap-to-call), and country
- **Files:** `AgentOrderDetailFragment.java`, `fragment_agent_order_detail.xml`

### 4.2 Agent order detail — show requester proof if uploaded

- **Current:** No section for viewing requester's uploaded proof
- **What to do:** If `requesterProofPath` is non-null, show a "Requester Proof" card with a thumbnail that opens full-screen on tap
- **Files:** `AgentOrderDetailFragment.java`, `fragment_agent_order_detail.xml`

### 4.3 Agent order detail — support ticket link

- **Current:** No way for agent to open a dispute from order detail
- **What to do:** Add "Open Support Ticket" button visible when `status != "completed" && status != "cancelled"`. Navigate to `CreateTicketFragment` with `remittance_id` pre-filled
- **Files:** `AgentOrderDetailFragment.java`, `fragment_agent_order_detail.xml`

### 4.4 Agent dashboard — sort/filter by status within tabs

- **Current:** Tab-level filter only (new / active / history / debts)
- **What to do:** Add sub-filters or sort options (date, amount) within each tab
- **Files:** `AgentDashboardFragment.java`, `fragment_agent_dashboard.xml`

---

## 5. Create Remittance — Polish

### 5.1 Summary step — show payment method name, not just ID

- **Current:** Step 3 summary may show the raw payment method ID or index
- **What to do:** Resolve `destinator_payment_method_id` to the human-readable name from the loaded `paymentMethods` list before displaying in summary
- **Files:** `CreateRemittanceFragment.java`

### 5.2 Country picker integration in destinator phone

- **Current:** Destinator phone is a plain text field
- **What to do:** Optionally add a country code prefix picker (like the registration screen) so the destinator phone is consistently formatted
- **Files:** `fragment_create_remittance.xml`, `CreateRemittanceFragment.java`

### 5.3 Currency auto-fill from user profile

- **Current:** Currency spinner starts at index 0 (unselected)
- **What to do:** Pre-select the user's preferred currency if available from `GET /mobile/profile`
- **Files:** `CreateRemittanceFragment.java`

---

## 6. My Orders List — Polish

### 6.1 Empty state

- **Current:** Blank screen when no orders exist
- **What to do:** Show an illustration + "No orders yet. Find an agent to start." text + a CTA button to navigate to marketplace
- **Files:** `MyOrdersFragment.java`, `fragment_transactions.xml`

### 6.2 Date display formatting

- **Current:** Raw ISO 8601 timestamps displayed on cards
- **What to do:** Format dates as `dd MMM yyyy, HH:mm` (local time) in `RemittanceAdapter`
- **Files:** `RemittanceAdapter.java` (add `formatDate()` method as done in `SupportTicketAdapter`)

### 6.3 Pull-to-refresh indicator styling

- **Current:** Standard SwipeRefreshLayout
- **What to do:** Ensure the refresh indicator uses the app's primary blue color for consistency
- **Files:** `fragment_transactions.xml`

---

## 7. Support Tickets — Gaps

### 7.1 Ticket detail — typing indicator / auto-refresh

- **Current:** Messages loaded once; no auto-refresh or polling
- **What to do:** Add a periodic poll (every 10–15 seconds) or a manual refresh button on `TicketDetailFragment`
- **Files:** `TicketDetailFragment.java`

### 7.2 Ticket detail — attachment support on send

- **Current:** Send button sends text only
- **What to do:** Add an attachment button (camera/gallery) next to the send button. Send via multipart if a file is attached
- **Files:** `TicketDetailFragment.java`, `fragment_ticket_detail.xml`, `ApiService.java`

### 7.3 Support ticket list — pull-to-refresh

- **Current:** Loads on creation only; no refresh mechanism
- **What to do:** Add SwipeRefreshLayout to `fragment_support_tickets.xml`
- **Files:** `SupportTicketsFragment.java`, `fragment_support_tickets.xml`

---

## 8. Profile — Gaps

### 8.1 Edit profile (name, email)

- **Current:** Profile is read-only
- **What to do:** Add an "Edit" button that opens a form dialog or bottom sheet. Call `PUT /mobile/profile` on save
- **Files:** `ProfileFragment.java`, `fragment_profile.xml`

### 8.2 Preferred currency selector

- **Current:** Not settable from the app
- **What to do:** Add a "Preferred currency" dropdown in profile that calls `PUT /mobile/profile` with `preferred_currency_id`
- **Files:** `ProfileFragment.java`, `fragment_profile.xml`

---

## 9. Navigation & UX Polish

### 9.1 Loading states — skeleton screens

- **Current:** Most lists show a spinner on load
- **What to do:** Replace spinners with skeleton/placeholder cards for: agent list, remittance list, agent dashboard, ticket list
- **Files:** Various layout XMLs and fragment Java files

### 9.2 Error states — retry buttons

- **Current:** Some error toasts shown; retry requires pull-to-refresh
- **What to do:** On API failure, show a centered error card with message + "Retry" button on: agent list, remittance list, agent dashboard, debt list, ticket list
- **Files:** Various fragments

### 9.3 Offline detection

- **Current:** No offline handling
- **What to do:** Add a network connectivity check. Show a persistent banner ("You're offline") when `ConnectivityManager` reports no network. Disable submit buttons when offline
- **Files:** New utility class + `MainActivity.java`

### 9.4 Back navigation consistency

- **Current:** Most fragments use `popBackStack()` on back
- **What to do:** Audit all fragment back-stack behavior. Ensure consistent: top-level tabs don't pop, detail screens pop, wizard steps use back arrow not system back
- **Files:** All fragments with navigation

---

## 10. Backend-Dependent Items

These require backend changes before the app can implement them.

### 10.1 `GET /mobile/countries` endpoint

- **Current:** App falls back to 196 hardcoded countries
- **Backend action:** Create `GET /api/mobile/countries` endpoint returning `[{id, name, code, phone_code, flag_url}]`
- **App change:** Remove `ApiModels.Country.getDefaults()` fallback once endpoint exists

### 10.2 Remittance events array in detail response

- **Current:** `GET /mobile/remittances/{id}` may not return `events`
- **Backend action:** Include `events` array in remittance detail response
- **App change:** Parse and display event timeline (see §3.2)

### 10.3 Proof upload via multipart

- **Current:** `POST /mobile/remittances/{id}/requester-proof` and `POST /mobile/agent/orders/{id}/execute` (with file) — not tested
- **Backend action:** Confirm these endpoints accept `multipart/form-data` with a `proof` field
- **App change:** Implement multipart upload (see §1.3, §1.4)

### 10.4 Push notification payload format

- **Current:** `onMessageReceived()` extracts `remittance_id` or `ticket_id` from data payload
- **Backend action:** Confirm push notifications send data payload (not just notification payload) with `type`, `remittance_id`, `ticket_id` fields
- **App change:** Test deep linking from push → correct screen

---

## 11. Testing Checklist

After each item above is implemented, run the corresponding test case from `feature-test-plan.md` §3 and mark it here.

| Section | Test Case | Status |
|---------|-----------|--------|
| §3.1 | Create remittance | Ready to test |
| §3.2 | View remittances list | Ready to test |
| §3.3 | Upload proof (requester) | Blocked (§1.3) |
| §3.4 | Skip proof → debt | Ready to test |
| §3.5 | Agent sees order | Ready to test |
| §3.6 | Agent accepts | Ready to test |
| §3.7 | Agent executes with proof | Blocked (§1.4) |
| §3.8 | Agent executes without proof | Ready to test |
| §3.9 | Requester confirms | Blocked (§1.2) |
| §3.10 | Requester cancels | Blocked (§1.1) |

---

## 12. Priority Order

Recommended implementation sequence (each builds on the previous):

| Priority | Item | Effort | Unblocks |
|----------|------|--------|----------|
| **P0** | §1.1 Cancel button | Small | §3.10 testable |
| **P0** | §1.2 Confirm button | Small | §3.9 testable |
| **P1** | §1.3 Proof upload (requester) | Medium | §3.3 testable |
| **P1** | §1.4 Proof upload (agent) | Medium | §3.7 testable |
| **P1** | §1.5 Payout reference field | Small | §3.7 full |
| **P2** | §3.1 Status timeline | Medium | UX polish |
| **P2** | §3.2 Event timeline | Medium | UX polish |
| **P2** | §3.3 Support link on detail | Small | §5 cross-link |
| **P2** | §6.2 Date formatting | Small | Readability |
| **P2** | §6.1 Empty state | Small | UX polish |
| **P3** | §4.1–4.3 Agent detail polish | Medium | Agent UX |
| **P3** | §7.1–7.3 Ticket polish | Medium | Support UX |
| **P3** | §8.1–8.2 Profile edit | Medium | Profile UX |
| **P3** | §9.1–9.4 Navigation polish | Medium | Overall polish |
| **P4** | §10.1–10.4 Backend deps | Varies | Full feature parity |
