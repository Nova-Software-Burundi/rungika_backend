# P2P Marketplace Specification

## Purpose

Build a peer-to-peer digital asset marketplace where users trade USDT, USDC, and other stablecoins for local fiat currencies directly with each other. The platform acts as a trusted intermediary — it provides the marketplace, escrow (off-chain ledger), dispute resolution, reputation system, and all operational infrastructure.

This is a **full Binance P2P clone** in terms of architecture, trade lifecycle, and user experience. The backend serves both a Vue-based backoffice portal and mobile clients (Flutter / React Native).

## Target Users

### Buyer
- Browses active sell ads with rich filtering (price, payment method, verification tier, completion rate).
- Selects a seller based on price, limits, payment methods, online status, and reputation.
- Uses stored payment accounts (bank, mobile money) to send payment.
- Marks the order as paid within the payment window.
- Receives USDT from escrow once the seller confirms fiat receipt.
- Can pre-chat with the seller before and during the trade.
- Rates the seller after completion.

### Seller
- Creates sell ads with price, limits, and preferred payment methods.
- Manages inventory (must have sufficient wallet balance to cover available_quantity).
- Receives trade requests with buyer's selected payment account details.
- Confirms fiat receipt in the trade chat or via the confirm action.
- Fails to release = dispute by buyer.
- Rates the buyer after completion.
- Can block problematic buyers.

### Platform Operator / Admin
- Manages users, roles, KYC tiers, and merchant verification.
- Configures assets, fiat currencies, payment methods, and exchange rates.
- Sets platform fees per pair (with volume-based tiers).
- Resolves disputes and handles appeals.
- Monitors revenue, volume, active trades, cancellation rates, and platform health via the Vue backoffice.

## Core Data Model

### Assets
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| code | string(10) | e.g. "USDT", "BTC" |
| name | string | |
| decimals | tinyint | e.g. 6 for USDT |
| enabled | bool | Can be traded |
| created_at | timestamp | |

### Fiat Currencies
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| code | string(10) | e.g. "USD", "ZMW" |
| name | string | |
| enabled | bool | |

### Payment Methods
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| name | string | e.g. "Bank Transfer", "Airtel Money" |
| code | string(50) | machine identifier |
| enabled | bool | |

### User Payment Accounts
Users register their own payment accounts before trading. These are selected during trade creation.
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| user_id | bigint FK | |
| payment_method_id | bigint FK | FK to payment_methods |
| account_label | string | e.g. "My GTBank Account" |
| account_holder | string | |
| account_number | string | |
| bank_name | string | nullable, for bank transfers |
| mobile_network | string | nullable, for mobile money |
| mobile_number | string | nullable, for mobile money |
| is_default | bool | default selection for this method |
| is_verified | bool | admin-verified account |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique constraint: `(user_id, payment_method_id, account_number)` — prevents duplicate accounts.

### Wallets (Off-Chain Ledger)
Internal wallet representing each user's balance per asset. This is the escrow system.
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| user_id | bigint FK | |
| asset_id | bigint FK | |
| balance | decimal(18,8) | current spendable balance |
| escrow_balance | decimal(18,8) | frozen in active trades |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique constraint: `(user_id, asset_id)`.

Balances change via `ledger_transactions` only — never updated directly.

### Ledger Transactions
Immutable audit trail for every balance change.
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| wallet_id | bigint FK | |
| trade_id | bigint FK | nullable, null for deposits/withdrawals |
| type | string | "deposit", "withdrawal", "escrow_hold", "escrow_release", "escrow_refund", "fee" |
| asset_id | bigint FK | |
| amount | decimal(18,8) | positive for credit, negative for debit |
| balance_before | decimal(18,8) | |
| balance_after | decimal(18,8) | |
| reference | string(36) | UUID for external traceability |
| created_at | timestamp | |

Trade lifecycle and wallet interactions:
1. **Ad creation**: No wallet action (just sets available_quantity — must be ≤ wallet balance).
2. **Trade created**: `available_quantity` decremented on ad. `escrow_hold` ledger entry moves `asset_amount` from wallet.balance to wallet.escrow_balance.
3. **Trade cancelled** (before release): `escrow_refund` restores balance from escrow_balance. `available_quantity` restored on ad.
4. **Trade released**: `escrow_release` deducts from seller's escrow_balance and credits buyer's wallet balance. Platform fee ledger entry.
5. **Dispute resolved — release to buyer**: Same as #4.
6. **Dispute resolved — cancel**: Same as #3.

### Advertisements
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| user_id | bigint FK | advertiser |
| type | enum | "buy" or "sell" |
| asset_id | bigint FK | |
| fiat_currency_id | bigint FK | |
| price_type | enum | "fixed" or "floating" |
| price | decimal(18,8) | fixed price in fiat per unit of asset |
| margin | decimal(5,2) | % above/below reference price when price_type=floating |
| min_order | decimal(18,8) | minimum fiat amount per trade |
| max_order | decimal(18,8) | maximum fiat amount per trade |
| available_quantity | decimal(18,8) | total asset quantity available |
| payment_methods | json | list of payment method IDs accepted |
| terms | text | seller's terms and conditions |
| status | enum | "active", "paused", "closed" |
| auto_reply | text | auto-reply when a trade is created |
| fiat_min_price | decimal(18,8) | nullable; minimum price deviation from reference |
| fiat_max_price | decimal(18,8) | nullable; maximum price deviation from reference |
| created_at | timestamp | |
| updated_at | timestamp | |

### Trades / Orders
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| reference | string(20) | unique trade reference |
| ad_id | bigint FK | |
| asset_id | bigint FK | |
| fiat_currency_id | bigint FK | |
| buyer_id | bigint FK | |
| seller_id | bigint FK | |
| status | enum | see state machine |
| asset_amount | decimal(18,8) | |
| fiat_amount | decimal(18,8) | |
| price | decimal(18,8) | execution price |
| payment_method_id | bigint FK | selected payment method |
| buyer_payment_account_id | bigint FK | nullable; buyer's account for sending payment |
| seller_payment_account_id | bigint FK | nullable; seller's account for receiving payment |
| payment_details | text | buyer's payment reference or notes |
| proof_path | string | buyer's payment proof file |
| proof_uploaded_at | timestamp | |
| payment_expires_at | timestamp | auto-cancel deadline |
| seller_confirmed_at | timestamp | |
| completed_at | timestamp | |
| cancelled_at | timestamp | |
| cancelled_by | enum | "buyer", "seller", "system" |
| dispute_reason | text | |
| dispute_opened_at | timestamp | |
| dispute_resolved_at | timestamp | |
| dispute_resolved_by | bigint FK | admin user |
| dispute_resolution | text | |
| fee_buyer | decimal(18,8) | |
| fee_seller | decimal(18,8) | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Trade Messages (Pre-Dispute Chat)
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| trade_id | bigint FK | |
| user_id | bigint FK | |
| message | text | |
| attachment_path | string | nullable |
| created_at | timestamp | |

### Trade Events (Audit Log)
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| trade_id | bigint FK | |
| actor_id | bigint FK | |
| actor_type | enum | "buyer", "seller", "system", "admin" |
| from_status | string | |
| to_status | string | |
| notes | text | |
| created_at | timestamp | |

### User Ratings
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| trade_id | bigint FK | |
| rater_id | bigint FK | |
| rated_user_id | bigint FK | |
| rating | tinyint | 1 to 5 |
| comment | text | |
| created_at | timestamp | |

### Dispute Messages
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| trade_id | bigint FK | |
| user_id | bigint FK | |
| message | text | |
| attachment_path | string | |
| created_at | timestamp | |

### Platform Fees
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| asset_id | bigint FK | |
| fiat_currency_id | bigint FK | |
| buyer_fee_type | enum | "percentage" or "fixed" |
| buyer_fee_value | decimal(18,8) | |
| seller_fee_type | enum | "percentage" or "fixed" |
| seller_fee_value | decimal(18,8) | |
| min_fee | decimal(18,8) | |
| max_fee | decimal(18,8) | |
| enabled | bool | |
| created_at | timestamp | |

### Fee Tiers (Volume-Based Discounts)
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| tier_name | string | e.g. "Bronze", "Silver", "Gold", "Platinum" |
| min_30d_volume | decimal(18,8) | minimum 30-day trading volume in USD |
| buyer_fee_discount_pct | decimal(5,2) | e.g. 25.00 = 25% off buyer fee |
| seller_fee_discount_pct | decimal(5,2) | e.g. 50.00 = 50% off seller fee |
| created_at | timestamp | |

### Reference Prices
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| asset_id | bigint FK | |
| fiat_currency_id | bigint FK | |
| price | decimal(18,8) | |
| source | string | e.g. "manual", "coingecko" |
| valid_at | timestamp | |
| created_at | timestamp | |

### Blocked Users
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| blocker_id | bigint FK | user who blocked |
| blocked_id | bigint FK | user being blocked |
| reason | string | optional |
| created_at | timestamp | |

Unique constraint: `(blocker_id, blocked_id)`.

### Cancellation Penalty Tracking
On every cancellation, the system records a penalty event. After N cancellations in a rolling window, the user is automatically restricted.
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| user_id | bigint FK | |
| trade_id | bigint FK | |
| cancelled_at | timestamp | |
| role_at_time | enum | "buyer" or "seller" |

### User KYC / Verification
| Existing fields on users table | Type | Notes |
|---|---|---|
| kyc_status | enum | "pending", "verified", "suspended" |
| kyc_verified_at | timestamp | |
| kyc_tier | integer | 0 = unverified, 1 = basic, 2 = advanced, 3 = corporate |
| kyc_document_front | string | nullable; ID document front |
| kyc_document_back | string | nullable; ID document back |
| kyc_selfie | string | nullable; selfie for liveness |
| flagged | boolean | |
| flagged_reason | string | |
| trading_enabled | boolean | |
| is_merchant | boolean | verified merchant badge |
| merchant_verified_at | timestamp | |
| last_activity_at | timestamp | online status tracking |

### KYC Tier Limits
| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| kyc_tier | integer | 0-3 |
| max_single_trade_fiat | decimal(18,8) | max fiat per trade |
| max_daily_trade_volume | decimal(18,8) | max fiat volume per day |
| max_active_trades | integer | max concurrent active trades |
| can_post_ads | boolean | can create ads |
| created_at | timestamp | |

### User Payment Accounts (see above)
### Device Tokens (existing)
### Announcements (existing)
### Support Tickets (existing)

## Trade State Machine

```
                          ┌──────────────────────────────────────────────┐
                          │                                              │
                          v                                              │
                    ┌──────────┐                                         │
                    │ pending  │── buyer/seller cancel ──→ ┌──────────┐  │
                    └────┬─────┘                           │ cancelled│  │
                         │                                 └──────────┘  │
              seller confirms order                                      │
                         │                                               │
                         v                                               │
                    ┌──────────┐       payment timer expires             │
                    │ awaiting │── auto-cancel (system) ──→ ┌──────────┐  │
                    │ payment  │                             │ cancelled│  │
                    └────┬─────┘                             └──────────┘  │
                         │                                                 │
          buyer marks as paid (uploads proof)                              │
                         │                                                 │
                         v                                                 │
                    ┌──────────┐                                           │
                    │ payment  │── dispute opened ──→ ┌──────────┐        │
                    │ sent     │                       │ disputed │        │
                    └────┬─────┘                       └────┬─────┘        │
                         │                                   │            │
            seller releases                                   │            │
                         │                       ┌──────────────────────┐ │
                         v                       │  admin resolves:     │ │
                    ┌──────────┐                  │  - release to buyer  │ │
                    │ released │                  │  - cancel to seller  │ │
                    └────┬─────┘                  └──────────────────────┘ │
                         │                                   │            │
                         v                                   v            │
                    ┌──────────┐                       ┌──────────┐       │
                    │completed │                       │ resolved │       │
                    └──────────┘                       │(see type) │       │
                                                       └──────────┘       │
                                                                          │
          Timer events (all trigger dispute or auto-action):              │
          - payment_expires_at exceeded → buyer auto-cancelled            │
          - 24h in dispute without admin response → auto-escalate         │
          - seller does not release within N min → dispute eligible       │
```

### Status Descriptions
| Status | Meaning |
|---|---|
| `pending` | Trade created, awaiting seller confirmation |
| `awaiting_payment` | Seller confirmed, buyer must pay before `payment_expires_at` |
| `payment_sent` | Buyer uploaded proof, seller must confirm |
| `released` | Seller released asset from escrow |
| `completed` | Both sides confirmed, trade finished |
| `cancelled` | Cancelled by buyer, seller, or system (auto-cancel) |
| `disputed` | Either party opened a dispute |
| `resolved` | Admin resolved the dispute |

### Timer Configurations
| Timer | Default | Action |
|---|---|---|
| Payment window | 30 minutes | Auto-cancel if buyer doesn't mark paid |
| Release reminder | 15 minutes | Buyer can dispute if seller doesn't release |
| Dispute auto-escalate | 24 hours | Escalate to senior admin if no response |
| Cancellation limit | 10 per 7 days | Trading restricted if exceeded |

## Escrow Wallet System — Lifecycle

```
1. Seller creates sell ad with available_quantity = 500 USDT
   → Backend checks: seller.wallet(USDT).balance >= 500
   → If not enough balance, ad creation is rejected.

2. Trade created (buyer buys 50 USDT)
   → Ledger: escrow_hold, seller.wallet.balance -= 50, .escrow_balance += 50
   → Ad: available_quantity -= 50

3. Trade cancelled (before release)
   → Ledger: escrow_refund, seller.wallet.balance += 50, .escrow_balance -= 50
   → Ad: available_quantity += 50

4. Trade released (seller confirms)
   → Ledger: escrow_release, seller.wallet.escrow_balance -= 50
   → Ledger: buyer.wallet.balance += 50
   → Ledger: fee (percentage from seller if configured)

5. Dispute resolved — release to buyer
   → Same as #4

6. Dispute resolved — cancel
   → Same as #3
```

## Feature List

### Phase 1 — Asset & User Infrastructure
- [x] Users with roles (Spatie Permission): super_admin, Admin, Operator, Agent, Driver, Customer
- [x] Assets CRUD (admin)
- [x] Fiat Currencies CRUD (admin)
- [x] Payment Methods CRUD (admin)
- [x] Users management (KYC status, tier, flag, trading toggle)
- [ ] **Wallets table + model + migration**
- [ ] **Ledger transactions table + model + migration**
- [ ] **User payment accounts table + model + migration**
- [ ] **KYC tier limits table + migration + seeder**
- [ ] **Fee tiers table + migration + seeder**
- [ ] **Blocked users table + migration + model**
- [ ] **Cancellation penalties table + migration + model**
- [ ] **Trade messages table + migration + model**

### Phase 2 — Wallet & Balance System
- [ ] Create wallet for every user on every enabled asset (via observer or command)
- [ ] `WalletService` — credit, debit, hold, release, refund methods
- [ ] Balance check middleware: ad creation requires sufficient wallet balance
- [ ] Admin deposit/withdrawal endpoint for manual wallet operations
- [ ] Ledger transaction listing (audit trail per wallet)

### Phase 3 — Advertisements & Marketplace
- [x] Ad CRUD (create, read, update, delete)
- [x] Ad listing with filters (asset, fiat, payment method, type, price range)
- [x] My Ads dashboard
- [x] Ad status toggling (active/paused/closed)
- [x] Floating and fixed price types
- [ ] **Balance validation on ad creation** (sell ad: wallet balance >= available_quantity)
- [ ] **Price deviation validation** (`fiat_min_price` / `fiat_max_price` on ad)
- [ ] **Online status on ad cards** (last_activity_at, online indicator)
- [ ] **Merchant badge on ad cards**
- [ ] **Ad scheduling** (publish_at, expire_at dates)

### Phase 4 — Trade Execution
- [x] Trade creation from ad
- [x] Trade listing (buyer view + seller view separate)
- [x] Trade detail with event timeline
- [x] Seller confirm
- [x] Buyer mark as paid with proof upload
- [x] Seller release asset
- [x] Buyer or seller cancel
- [x] Trade state machine validation in TradeService
- [ ] **Escrow hold on trade creation** (call WalletService)
- [ ] **Escrow release on seller release** (call WalletService)
- [ ] **Escrow refund on cancel** (call WalletService)
- [ ] **Auto-cancel job** (command scheduled every minute, cancels expired `awaiting_payment` trades)
- [ ] **Release reminder notification** (auto-message to seller after N minutes)
- [ ] **Payment timer countdown** exposed in API response as `payment_remaining_seconds`

### Phase 5 — Trade Chat (Pre-Dispute)
- [ ] Messages table + model + migration
- [ ] `GET /api/mobile/trades/{id}/chat` — list messages
- [ ] `POST /api/mobile/trades/{id}/chat` — send message (text + optional attachment)
- [ ] Chat available from `pending` through `completed` (all states except `cancelled`)
- [ ] Auto-reply message from seller shown on trade creation
- [ ] Vue portal trade detail shows chat tab

### Phase 6 — Dispute Resolution
- [x] Open dispute from `payment_sent`
- [x] Dispute messages (chat between buyer, seller, admin)
- [x] Admin resolve dispute (release to buyer / cancel)
- [x] Dispute message attachments
- [ ] **Pre-dispute chat visible inside dispute panel** (context preserved)
- [ ] **Dispute auto-escalation** (24h timer, notify senior admin)
- [ ] **Dispute appeal** (after resolution, user can request review by senior admin)
- [ ] **Evidence submission form** (structured: screenshots, transaction reference, text)
- [ ] **Dispute fee** (penalty for losing party, configurable)

### Phase 7 — Platform Economics
- [x] Platform fee configuration per asset/fiat pair
- [x] Fee calculation service (percentage or fixed, min/max caps)
- [x] Reference prices
- [x] Revenue dashboard (summary, by-pair, daily)
- [ ] **Volume-based fee tiers** (FeeTier model, applied per user based on 30d volume)
- [ ] **Fee discount calculation** in FeeService
- [ ] **Revenue export by fee tier**
- [ ] **Revenue with fee discount breakdown**

### Phase 8 — Reputation & Safety
- [x] User ratings (1-5, comment, one per trade per rater)
- [x] User stats endpoint (completion rate, total trades, volume, avg rating)
- [ ] **Cancellation rate tracking** (record every cancel, enforce rolling limits)
- [ ] **Block user feature** (can't trade with blocked user, ads hidden from each other)
- [ ] **Cancellation penalty auto-restriction** (trading_disabled_until timestamp)
- [ ] **Rating breakdown by role** (ratings received as buyer vs seller)
- [ ] **Recent review period** (only trades in last 90 days affect rating)

### Phase 9 — KYC & Merchant Verification
- [x] KYC status and tier on users
- [x] Admin set KYC tier
- [x] Admin flagging
- [ ] **KYC tier limits enforcement** (TradeService checks max_single_trade_fiat, max_daily_trade_volume, max_active_trades)
- [ ] **KYC document upload** (user self-service: front, back, selfie)
- [ ] **KYC tier upgrade request** (user requests upgrade, admin reviews)
- [ ] **Merchant badge toggle** (admin sets `is_merchant`, shows badge on ad cards)
- [ ] **Merchant requirements** (minimum KYC tier 2, 30+ completed trades, 95%+ completion, no recent disputes)

### Phase 10 — Online Presence & User Controls
- [ ] **`last_activity_at` heartbeat** (update on every API request, or explicit `POST /mobile/ping` every 60s)
- [ ] **Online/offline indicator** on ad cards and trade detail (green dot if last_activity_at < 5 min)
- [ ] **2FA management** (enable/disable via portal and mobile API, enforce for trades above threshold)
- [ ] **Login history** (list recent sessions, revoke tokens)

### Phase 11 — Notifications & Real-Time
- [x] In-app notifications (database table)
- [x] Device token registration (store/delete)
- [x] Notification read/unread management
- [ ] **Push notification delivery** (FCM/APNs integration in NotificationService, send on trade events)
- [ ] **Laravel Broadcasting / WebSockets** (Echo + Pusher/reverb for real-time trade status updates)
- [ ] **Real-time chat** (WebSocket channel per trade for messages)
- [ ] **Background job for auto-cancel** (`php artisan p2p:expire-payments`)
- [ ] **Background job for dispute escalation** (`php artisan p2p:escalate-disputes`)

### Phase 12 — Operational Polish
- [x] Announcements CRUD
- [x] Support tickets with categories and SLA
- [x] Trade CSV export
- [x] Revenue dashboard
- [ ] **P2P dashboard** (admin home page: active trades, pending disputes, volume today, active ads, signups today)
- [ ] **Admin trade filters** (by user, by asset, by date range, by dispute status)
- [ ] **Test data seeders** (P2pDemoDataSeeder with sample users, ads, trades, ratings, disputes)
- [ ] **API rate limiting** per endpoint group (trade creation: 10/min, ad creation: 5/min, cancellation: 3/min)
- [ ] **Audit log for admin actions** (P2pAdminAudit model for all admin writes)

### Phase 13 — Mobile Client
- [x] All trade lifecycle endpoints exposed for mobile
- [x] Reference data endpoints
- [x] Authentication (email/password, WhatsApp OTP, Firebase)
- [x] Ratings, notifications, device tokens
- [ ] **User payment accounts CRUD** (mobile endpoints)
- [ ] **Block user endpoints**
- [ ] **Trade chat endpoints**
- [ ] **Online status heartbeat endpoint**
- [ ] **2FA management endpoints**
- [ ] **KYC document upload endpoints**
- [ ] **Cancellation rate / penalties display**

## Mobile API Contract

See [mobile-api-integration.md](./mobile-api-integration.md) for the full mobile API reference.

Base path: `/api/mobile`
Auth: Sanctum Bearer tokens (except auth endpoints)

### Auth
- `POST /auth/register` — create account
- `POST /auth/request-whatsapp-otp` — request WhatsApp OTP
- `POST /auth/verify-whatsapp-otp` — verify WhatsApp OTP + login
- `POST /auth/verify-firebase-phone` — verify Firebase phone auth
- `POST /auth/login` — email/password login
- `POST /auth/logout` — revoke token (protected)

### Profile
- `GET /profile` — current user with KYC/merchant/online info
- `PUT /profile` — update name/email
- `GET /users/{id}/ratings` — user's received ratings
- `GET /users/{id}/stats` — user's reputation stats
- `POST /ping` — heartbeat (updates last_activity_at)

### Reference Data
- `GET /assets`
- `GET /fiat-currencies`
- `GET /payment-methods`
- `GET /reference-prices`

### Payment Accounts
- `GET /payment-accounts` — list user's registered accounts
- `POST /payment-accounts` — register new account
- `PUT /payment-accounts/{id}` — update account
- `DELETE /payment-accounts/{id}` — delete account
- `PATCH /payment-accounts/{id}/default` — set as default

### Ads
- `GET /ads` — browse marketplace (paginated, filterable)
- `GET /ads/{id}` — ad detail with seller stats
- `POST /ads` — create ad (checks wallet balance for sell ads)
- `PUT /ads/{id}` — update ad
- `DELETE /ads/{id}` — delete ad
- `GET /my-ads` — current user's ads

### Trades
- `GET /trades` — list user's trades (paginated)
- `POST /trades` — create trade from ad (locks escrow)
- `GET /trades/{id}` — detail with events + payment_remaining_seconds
- `POST /trades/{id}/confirm` — seller confirms
- `POST /trades/{id}/mark-paid` — buyer marks as paid (multipart)
- `POST /trades/{id}/release` — seller releases (releases escrow)
- `POST /trades/{id}/cancel` — cancel (refunds escrow)
- `POST /trades/{id}/dispute` — open dispute
- `GET /trades/{id}/messages` — dispute messages
- `POST /trades/{id}/messages` — send dispute message

### Trade Chat
- `GET /trades/{id}/chat` — list pre-dispute chat messages
- `POST /trades/{id}/chat` — send message (text + optional attachment)

### Blocked Users
- `GET /blocked-users` — list blocked users
- `POST /blocked-users` — block a user
- `DELETE /blocked-users/{blockedUserId}` — unblock

### 2FA
- `POST /2fa/enable` — enable 2FA
- `POST /2fa/disable` — disable 2FA (with current password)
- `GET /2fa/recovery-codes` — view recovery codes

### Ratings
- `POST /trades/{id}/rate` — rate counterparty after completion
- `GET /users/{id}/ratings` — view user ratings

### Notifications
- `GET /notifications` — list (paginated, with unread_count)
- `POST /notifications/{id}/read` — mark one as read
- `POST /notifications/read-all` — mark all as read
- `GET /notifications/unread-count`
- `POST /device-tokens` — register push token
- `DELETE /device-tokens/{token}` — unregister

## Vue Portal (Backoffice) Routes

Current routes:
| Path | Page | Status |
|---|---|---|
| `/portal/login` | Login | ✅ |
| `/portal/transfers` | Money Transfers (legacy) | ✅ |
| `/portal/users` | User Management | ✅ |
| `/portal/currencies` | Fiat Currencies | ✅ |
| `/portal/assets` | Digital Assets | ✅ |
| `/portal/payment-methods` | Payment Methods | ✅ |
| `/portal/ads` | Advertisements | ✅ |
| `/portal/trades` | Trades + Disputes | ✅ |
| `/portal/fees` | Platform Fees | ✅ |
| `/portal/prices` | Reference Prices | ✅ |
| `/portal/revenue` | Revenue Dashboard | ✅ |
| `/portal/ratings` | Ratings | ✅ |
| `/portal/announcements` | Announcements | ✅ |
| `/portal/support` | Support Tickets + Categories | ✅ |
| `/portal/settings` | Settings | ✅ |

Additional pages needed:
| Path | Page | Priority |
|---|---|---|
| `/portal/wallets` | Wallet management (balances, manual deposit/withdrawal) | High |
| `/portal/ledger` | Ledger transactions (immutable audit log) | High |
| `/portal/fee-tiers` | Volume-based fee tier configuration | Medium |
| `/portal/kyc` | KYC document review queue | Medium |
| `/portal/dashboard` | P2P-specific dashboard (active trades, disputes, volume, ads) | Medium |
| `/portal/disputes` | Dedicated dispute list with escalation status | Medium |

## Acceptance Criteria

The platform is production-ready when:

1. Admin can create assets, fiat currencies, payment methods, and fee tiers.
2. Users can register payment accounts (bank, mobile money) on their profile.
3. A verified user can post a sell ad (requires sufficient wallet balance).
4. Another verified user can browse ads (seeing online status, merchant badge, completion rate).
5. Trade creation places an escrow hold on the seller's wallet.
6. The buyer can chat with the seller before and during the trade.
7. The buyer pays outside the platform and uploads proof within the payment window.
8. If the timer expires, the trade auto-cancels and escrow is refunded.
9. The seller confirms receipt and releases the asset (escrow released to buyer).
10. Both users can rate each other.
11. Either party can open a dispute (with pre-chat context preserved).
12. Admin resolves the dispute, and escrow is settled accordingly.
13. Platform fees are calculated with volume-based discounts and recorded on every trade.
14. Users with high cancellation rates are automatically restricted.
15. KYC tier limits are enforced (max trade size, daily volume, active trades).
16. Admin can see real-time revenue, volume, and active trade metrics.
17. Push notifications are delivered on key trade events.
18. Real-time updates via WebSockets (or polling fallback).
19. Mobile clients can execute the full lifecycle without any web dependency.
20. All state changes are immutable and auditable via ledger + trade events.

## Implementation Order

For AI or human implementers, follow this order:

1. **Wallet & Ledger** (tables, WalletService, balance checks) — everything depends on this.
2. **User Payment Accounts** (table, CRUD, mobile endpoints) — needed for trade creation UX.
3. **Trade Chat** (table, endpoints) — simple, no dependencies on other new features.
4. **Auto-Cancel Timer** (scheduled job) — essential for production safety.
5. **KYC Tier Enforcement** (limits table, TradeService checks) — critical for compliance.
6. **Block User + Cancellation Penalties** (tables, service, middleware).
7. **Online Status** (heartbeat endpoint, indicator in responses).
8. **Fee Tiers + Discounts** (table, FeeService update).
9. **Dispute Escalation + Appeal** (timer, senior admin notifications).
10. **Merchant Badge** (field, requirements check, display).
11. **2FA Enforcement** (manage endpoints, trade threshold check).
12. **Push Notification Delivery** (FCM/APNs integration).
13. **Real-Time WebSockets** (Laravel Broadcasting + Echo).
14. **Vue Portal Additions** (dashboard, wallets, ledger, fee tiers, KYC queue).
15. **Mobile API Endpoints** (payment accounts, block, chat, 2FA, KYC, ping).
16. **Rate Limiting + Security Hardening**.
17. **Seeders + Test Data**.
