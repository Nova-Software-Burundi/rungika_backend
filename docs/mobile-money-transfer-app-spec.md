# Mobile Money Transfer App Specification

## Purpose

Build a mobile app that connects to the Laravel backend and lets operators manage a USDT-backed money transfer workflow from transaction initiation to final payout confirmation.

The app supports this real-world flow:

1. A field operator or initiator receives cash or a transfer request from a client in Lusaka.
2. The initiator creates a transaction in the app with sender, recipient, amount, and optional notes.
3. The assigned agent receives a notification that a new transaction has been initiated.
4. The initiator uploads a screenshot proving the USDT transfer was sent.
5. The agent reviews the screenshot and confirms that the USDT arrived.
6. The agent performs the local payout outside the app.
7. The agent uploads payout proof and marks the transaction completed.
8. The process repeats for each transaction.

## Product Name

Working name: Transfer Desk Mobile

The UI should feel operational, fast, and trustworthy. This is not a consumer banking app with marketing screens. It is a transaction operations tool for repeated daily use.

## Target Users

### Initiator

The person who starts a transfer request. In the original scenario, this is the friend in Lusaka. This user:

- Creates transfer records.
- Captures sender details.
- Captures recipient details.
- Enters the amount to be sent.
- Uploads USDT transfer proof.
- Tracks transaction status.
- Can see whether the agent has confirmed receipt and payout.

### Agent

The person who monitors and completes transfer operations. This user:

- Receives notification for newly initiated transfers.
- Reviews transfer details.
- Reviews USDT proof screenshot.
- Confirms USDT receipt.
- Performs the payout outside the app.
- Uploads payout proof.
- Marks the transaction successful.
- Can filter and search transactions.

### Admin or Supervisor

Optional for the first mobile version. This user:

- Can view all transactions.
- Can audit event history.
- Can cancel or investigate transactions.
- Can review daily volume and pending work.

## Platforms

Build for:

- Android first.
- iOS second if the framework supports it easily.

Recommended implementation options:

- React Native with Expo.
- Flutter.

The app must support camera and gallery access for proof uploads.

## Backend Contract

The backend currently exposes transfer workflow routes under:

`/api/portal/transfers`

Current transfer endpoints:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/portal/transfers` | List transactions |
| POST | `/api/portal/transfers` | Create transaction |
| GET | `/api/portal/transfers/stats` | Get dashboard stats |
| GET | `/api/portal/transfers/{id}` | Get one transaction with event history |
| POST | `/api/portal/transfers/{id}/usdt-proof` | Upload USDT proof screenshot |
| POST | `/api/portal/transfers/{id}/confirm-usdt` | Agent confirms USDT receipt |
| POST | `/api/portal/transfers/{id}/payout-proof` | Upload payout proof and complete transaction |
| PATCH | `/api/portal/transfers/{id}/status` | Cancel or update supported status |

Important mobile integration note:

The mobile app should use Bearer token authentication via Laravel Sanctum. If the backend still protects these transfer routes with session `auth`, update the backend to allow authenticated Sanctum API users as well. The cleanest approach is to expose the same controller under a mobile API group protected by `auth:sanctum`, for example:

`/api/mobile/transfers`

The mobile app should not depend on browser cookies unless the backend team explicitly chooses cookie-based mobile sessions.

## Authentication

### Required Features

- Login screen.
- Logout.
- Persist auth token securely.
- Restore session when the app opens.
- Reject unauthenticated users and return to login.

### Suggested Auth API

Use one of these:

1. Existing token login:
   - `POST /api/login`
   - Body: `{ "identifier": "email_or_phone", "password": "password" }`
   - Returns: `{ "token": "...", "user": {...} }`

2. New mobile login endpoint if needed:
   - `POST /api/mobile/auth/login`

### Secure Storage

Store the token using:

- Expo SecureStore for React Native Expo.
- Keychain or Keystore equivalent for native mobile.
- Flutter secure storage for Flutter.

Never store tokens in plain AsyncStorage unless there is no other option.

## Transaction Statuses

The app must display statuses using these backend values:

| Backend Status | User Label | Meaning |
| --- | --- | --- |
| `initiated` | Initiated | Transfer was created, no USDT proof uploaded yet |
| `usdt_proof_submitted` | Needs Agent Review | Screenshot uploaded, agent must confirm receipt |
| `usdt_received` | Ready for Payout | Agent confirmed USDT, payout must be done |
| `completed` | Completed | Payout proof uploaded, transaction finished |
| `cancelled` | Cancelled | Transaction stopped |

## Main Navigation

Use bottom tabs or a simple drawer.

Required tabs:

- Transfers
- New Transfer
- Notifications
- Account

Optional tabs:

- Dashboard
- Reports

## Screen 1: Login

### Fields

- Email or phone
- Password

### Actions

- Sign in
- Show loading state
- Show validation errors

### Success

After login:

- Store token.
- Fetch current user.
- Navigate to Transfers screen.

## Screen 2: Transfers List

This is the main operational queue.

### Required Features

- Show list of transactions.
- Pull to refresh.
- Search by reference, sender, recipient, or phone.
- Filter by status.
- Show compact stats at top:
  - Total
  - New
  - Needs Agent
  - Ready Payout
  - Completed

### Each List Item Shows

- Reference, for example `MT-20260517-ABC123`
- Sender name
- Recipient name
- Amount and currency
- USDT amount if available
- Status badge
- Created time
- Assigned agent if available

### Empty State

If no transfers exist, show:

`No transfers yet`

Include a button:

`Create Transfer`

## Screen 3: Create Transfer

The initiator uses this screen to create a transfer.

### Required Fields

- Sender name
- Recipient name
- Amount sent

### Optional Fields

- Sender phone
- Recipient phone
- Recipient location
- Send currency, default `USD`
- USDT amount
- Exchange rate
- Payout currency, default `ZMW`
- Estimated payout amount
- Notes

### Validation

- Sender name is required.
- Recipient name is required.
- Amount must be greater than zero.
- Currency codes should be uppercase.
- Phone numbers should allow international format.

### API Call

`POST /api/portal/transfers`

Request body:

```json
{
  "sender_name": "Client in Lusaka",
  "sender_phone": "+260970000000",
  "recipient_name": "Recipient Name",
  "recipient_phone": "+260960000000",
  "recipient_location": "Lusaka",
  "send_amount": 150,
  "send_currency": "USD",
  "usdt_amount": 150,
  "payout_currency": "ZMW",
  "payout_amount": 3800,
  "notes": "Client waiting at booth"
}
```

### Success Behavior

- Show success message.
- Navigate to Transaction Detail.
- Trigger refresh of Transfers List.

## Screen 4: Transaction Detail

This screen is the center of the workflow.

### Header

Show:

- Reference
- Current status
- Created time
- Sender to recipient summary

### Transfer Details Section

Show:

- Sender name and phone
- Recipient name and phone
- Recipient location
- Amount sent
- Send currency
- USDT amount
- Payout amount
- Payout currency
- Notes

### Workflow Section

Display a vertical progress tracker:

1. Transfer initiated
2. USDT proof uploaded
3. Agent confirmed USDT receipt
4. Payout proof uploaded
5. Completed

Each step should show:

- Done, current, or pending state.
- Timestamp if available.
- User who performed the step if available.

### Activity History

Use backend `events` array.

Display:

- Event type
- User name
- Timestamp
- Status transition if available

## Screen 5: Upload USDT Proof

This is used after the client or sender has sent USDT.

### Entry Points

- From Transaction Detail.
- From a transaction list action if status is `initiated`.

### Required Features

- Pick image from gallery.
- Take photo with camera.
- Preview selected proof.
- Optional notes field.
- Upload button.

### Allowed File Types

- JPG
- JPEG
- PNG
- WEBP
- PDF if supported by picker

### Max Size

Backend currently allows up to 5 MB.

### API Call

`POST /api/portal/transfers/{id}/usdt-proof`

Send as multipart form data:

- `usdt_proof`: file
- `notes`: optional string

### Success Behavior

- Status becomes `usdt_proof_submitted`.
- Show message:
  `USDT proof uploaded. Waiting for agent confirmation.`
- Refresh transaction detail.

## Screen 6: Agent Confirm USDT

This action is for agents.

### Availability

Only show the button when:

- Status is `usdt_proof_submitted`.
- A USDT proof exists.

### Required Features

- Display USDT screenshot clearly.
- Allow pinch zoom on image.
- Show transfer amount and USDT amount near the screenshot.
- Optional agent notes.
- Confirm button.

### API Call

`POST /api/portal/transfers/{id}/confirm-usdt`

Request body:

```json
{
  "agent_notes": "Confirmed in wallet."
}
```

### Success Behavior

- Status becomes `usdt_received`.
- Show message:
  `USDT receipt confirmed. Payout can now be completed.`
- Notify initiator if push notifications are enabled.

## Screen 7: Upload Payout Proof

This is used by the agent after the local payout is performed outside the app.

### Availability

Only show this action when status is:

`usdt_received`

### Required Fields

- Payout proof file

### Optional Fields

- Payout reference
- Payout amount
- Agent notes

### API Call

`POST /api/portal/transfers/{id}/payout-proof`

Send as multipart form data:

- `payout_proof`: file
- `payout_reference`: optional string
- `payout_amount`: optional number
- `agent_notes`: optional string

### Success Behavior

- Status becomes `completed`.
- Show message:
  `Transaction completed successfully.`
- Notify initiator.

## Screen 8: Notifications

### Required Notifications

The app should support push notifications for:

- New transaction initiated.
- USDT proof uploaded.
- Agent confirmed USDT receipt.
- Payout completed.
- Transaction cancelled.

### Notification Behavior

Tapping a notification opens the Transaction Detail screen.

### Backend Requirement

The backend needs device token registration if not already present.

Recommended endpoints:

- `POST /api/mobile/device-tokens`
- `DELETE /api/mobile/device-tokens/{token}`

Device token payload:

```json
{
  "token": "push_token_here",
  "platform": "android"
}
```

## Screen 9: Account

### Show

- User name
- Email
- Role
- App version

### Actions

- Logout
- Refresh session

## Role Based UI

The first version can keep role logic simple:

- Initiators can create transfers and upload USDT proof.
- Agents can confirm USDT and upload payout proof.
- Admins can do everything.

If the backend does not yet return roles, the mobile app should still render the full workflow for authenticated users during the prototype phase, but keep role checks isolated in one helper so they can be enforced later.

Suggested helper:

```ts
canCreateTransfer(user)
canUploadUsdtProof(user, transfer)
canConfirmUsdt(user, transfer)
canCompletePayout(user, transfer)
canCancelTransfer(user, transfer)
```

## Data Model Expected By App

Transfer object:

```json
{
  "id": 1,
  "reference": "MT-20260517-ABC123",
  "initiated_by": 1,
  "assigned_agent_id": 2,
  "sender_name": "Client in Lusaka",
  "sender_phone": "+260970000000",
  "recipient_name": "Recipient Name",
  "recipient_phone": "+260960000000",
  "recipient_location": "Lusaka",
  "send_amount": "150.00",
  "send_currency": "USD",
  "usdt_amount": "150.000000",
  "exchange_rate": null,
  "payout_currency": "ZMW",
  "payout_amount": "3800.00",
  "status": "completed",
  "usdt_proof_path": "money-transfers/usdt-proofs/file.png",
  "usdt_proof_uploaded_at": "2026-05-17T18:00:00.000000Z",
  "usdt_confirmed_at": "2026-05-17T18:05:00.000000Z",
  "payout_reference": "CASH-001",
  "payout_proof_path": "money-transfers/payout-proofs/file.png",
  "payout_proof_uploaded_at": "2026-05-17T18:15:00.000000Z",
  "payout_confirmed_at": "2026-05-17T18:15:00.000000Z",
  "notes": "Client waiting",
  "agent_notes": "Paid successfully",
  "initiator": {
    "id": 1,
    "name": "Initiator"
  },
  "agent": {
    "id": 2,
    "name": "Agent"
  },
  "events": []
}
```

Proof URLs:

If the backend returns `usdt_proof_path`, construct a file URL as:

`{API_BASE_URL_WITHOUT_/api}/storage/{usdt_proof_path}`

Example:

`https://example.com/storage/money-transfers/usdt-proofs/file.png`

## Error Handling

Handle these cases gracefully:

- No internet connection.
- Token expired.
- Validation error from backend.
- File too large.
- Unsupported file type.
- Upload interrupted.
- Transfer already closed.
- USDT proof missing when agent tries to confirm.
- Payout attempted before USDT confirmation.

For validation errors, show field-specific messages when possible.

## Offline Behavior

Version 1 does not need full offline transaction creation.

Required:

- Show offline banner when network is unavailable.
- Disable create and upload actions while offline.
- Keep last loaded transfers cached for read-only viewing.

Optional:

- Queue draft transfer creation locally and submit later.

## Security Requirements

- Use HTTPS in production.
- Store auth tokens securely.
- Do not log auth tokens.
- Do not log proof file contents.
- Require authentication for every transfer endpoint.
- Hide sensitive proof previews when app is backgrounded if the framework supports it.
- Add a short timeout lock in future versions if the app handles high volume or high value transfers.

## Audit Requirements

Every important action should be traceable:

- Created transfer.
- Uploaded USDT proof.
- Confirmed USDT receipt.
- Uploaded payout proof.
- Completed transfer.
- Cancelled transfer.

The mobile app should display audit history but should not allow users to edit audit events.

## UI Guidelines

- Use a clean operations-focused design.
- Avoid marketing hero screens.
- Use status badges consistently.
- Use clear action buttons:
  - Create Transfer
  - Upload USDT Proof
  - Confirm USDT Received
  - Upload Payout Proof
  - Complete Transaction
- Use green for completed states.
- Use amber for pending agent action.
- Use blue for initiated states.
- Use red only for cancelled or errors.

## Suggested Mobile App Architecture

### Folders

```txt
src/
  api/
    client.ts
    auth.ts
    transfers.ts
  components/
    StatusBadge.tsx
    ProofPicker.tsx
    TransferCard.tsx
    Timeline.tsx
  screens/
    LoginScreen.tsx
    TransfersScreen.tsx
    CreateTransferScreen.tsx
    TransferDetailScreen.tsx
    NotificationsScreen.tsx
    AccountScreen.tsx
  store/
    authStore.ts
    transferStore.ts
  utils/
    money.ts
    dates.ts
    permissions.ts
```

### API Client Behavior

- Base URL configurable through environment settings.
- Attach `Authorization: Bearer {token}` when logged in.
- If API returns 401, clear token and go to login.
- Use multipart form data for proof uploads.

## Acceptance Criteria

The mobile app is complete when:

1. A user can log in.
2. A user can create a transfer.
3. The transfer appears in the transfer list.
4. A user can upload USDT proof.
5. The status changes to `usdt_proof_submitted`.
6. An agent can confirm USDT receipt.
7. The status changes to `usdt_received`.
8. An agent can upload payout proof.
9. The status changes to `completed`.
10. The detail screen shows the full event history.
11. The app handles validation errors and upload errors without crashing.
12. The UI works on small Android screens.
13. Auth token persists after app restart.
14. Logout clears local auth state.

## Test Scenarios

### Happy Path

1. Login as agent.
2. Create transfer.
3. Upload USDT screenshot.
4. Confirm USDT receipt.
5. Upload payout proof.
6. Verify completed status and event history.

### Validation

1. Try creating transfer without sender.
2. Try creating transfer without recipient.
3. Try creating transfer with zero amount.
4. Try uploading unsupported file type.
5. Try confirming USDT before proof upload.
6. Try payout before USDT confirmation.

### Session

1. Login.
2. Close app.
3. Reopen app.
4. Verify user remains logged in.
5. Logout.
6. Verify protected screens are inaccessible.

## Implementation Notes For AI Builder

Build the app as a real mobile operations tool, not a mockup. Prioritize the transaction lifecycle over decorative screens.

Start by implementing:

1. Auth client and secure token storage.
2. Transfer API client.
3. Transfers list.
4. Create transfer form.
5. Transaction detail screen.
6. USDT proof upload.
7. Agent confirmation.
8. Payout proof upload.
9. Error handling.
10. Push notifications if backend device-token support exists.

If the backend returns a different field shape, adapt the API mapping layer, not the UI components.

Keep all status labels and action availability driven by backend `status`.

