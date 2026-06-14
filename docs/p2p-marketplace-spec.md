# P2P Marketplace Specification

## Purpose

Build a peer-to-peer digital asset marketplace where users trade USDT for local fiat currencies directly with each other. The platform acts as a trusted intermediary — it provides the marketplace, escrow, dispute resolution, and reputation system.

Unlike the current money transfer workflow (company-operated), this is a true P2P marketplace where independent buyers and sellers transact with each other.

## Target Users

### Buyer

A person who wants to buy USDT with local fiat currency. This user:

- Browses active sell ads.
- Selects a seller based on price, limits, payment methods, and reputation.
- Initiates a trade.
- Pays the seller via the agreed payment method (outside the platform).
- Marks the order as paid (uploads proof if required by the seller's terms).
- Receives USDT from escrow once the seller confirms receipt.
- Rates the seller after completion.

### Seller

A person who wants to sell USDT for local fiat currency. This user:

- Creates sell ads with price, limits, and payment methods.
- Manages active and archived ads.
- Receives trade requests from buyers.
- Confirms fiat receipt from the buyer.
- Releases USDT from escrow to the buyer.
- Rates the buyer after completion.

### Platform Operator / Admin

Manages the marketplace through the backoffice:

- Manages users, roles, and permissions.
- Handles KYC verification and user tiers.
- Configures currencies, assets, and exchange rates.
- Sets platform fees and margin.
- Resolves disputes.
- Monitors revenue, volume, and platform health.

## Product Name

Working name: **P2P Desk**

The UI should feel professional and trustworthy. It is a marketplace, not a banking app.

## Platforms

- **Backoffice**: Laravel + Filament admin panel (existing `/admin`).
- **Portal**: Vue SPA (existing `/portal`) — for power users to manage ads, trades, settings.
- **Mobile App**: React Native or Flutter (future) — primary interface for buyers and sellers.

## Core Data Model

### Assets

Digital assets available for trading (e.g., USDT, USDC, BTC).

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| code | string(10) | e.g. "USDT", "BTC" |
| name | string | e.g. "Tether USD" |
| decimals | tinyint | e.g. 6 for USDT |
| enabled | bool | Can be traded |
| created_at | timestamp | |

### Fiat Currencies

Local currencies for the fiat side of trades (e.g., USD, ZMW, NGN, KES).

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| code | string(10) | e.g. "USD", "ZMW" |
| name | string | |
| enabled | bool | |

### Payment Methods

How buyers pay sellers (e.g., Bank Transfer, Mobile Money, Cash Pickup).

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| name | string | e.g. "Bank Transfer", "Airtel Money" |
| code | string(50) | machine identifier |
| enabled | bool | |

### Advertisements

The core marketplace primitive. A user posts an ad to buy or sell.

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| user_id | bigint FK | advertiser |
| type | enum | "buy" or "sell" |
| asset_id | bigint FK | e.g. USDT |
| fiat_currency_id | bigint FK | e.g. ZMW |
| price_type | enum | "fixed" or "floating" |
| price | decimal(18,8) | fixed price in fiat per unit of asset |
| margin | decimal(5,2) | % above/below reference price when price_type=floating |
| min_order | decimal(18,8) | minimum fiat amount per trade |
| max_order | decimal(18,8) | maximum fiat amount per trade |
| available_quantity | decimal(18,8) | total asset quantity available for this ad |
| payment_methods | json | list of payment method IDs accepted |
| terms | text | seller's terms and conditions |
| status | enum | "active", "paused", "closed" |
| auto_reply | text | auto-reply when a trade is created |
| created_at | timestamp | |
| updated_at | timestamp | |

### Trades / Orders

A trade created when a buyer clicks "Buy" on a sell ad (or vice versa).

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| reference | string(20) | unique trade reference (e.g., "P2P-4A2B9C") |
| ad_id | bigint FK | the ad this trade was created from |
| asset_id | bigint FK | |
| fiat_currency_id | bigint FK | |
| buyer_id | bigint FK | |
| seller_id | bigint FK | |
| status | enum | see state machine below |
| asset_amount | decimal(18,8) | quantity of asset being traded |
| fiat_amount | decimal(18,8) | equivalent fiat amount |
| price | decimal(18,8) | execution price |
| payment_method_id | bigint FK | selected payment method |
| payment_details | text | buyer's payment reference or account details |
| proof_path | string | buyer's payment proof file |
| proof_uploaded_at | timestamp | |
| seller_confirmed_at | timestamp | |
| completed_at | timestamp | |
| cancelled_at | timestamp | |
| cancelled_by | enum | "buyer", "seller", "system" |
| dispute_reason | text | |
| dispute_opened_at | timestamp | |
| dispute_resolved_at | timestamp | |
| dispute_resolved_by | bigint FK | admin user |
| dispute_resolution | text | admin's decision notes |
| fee_buyer | decimal(18,8) | platform fee charged to buyer |
| fee_seller | decimal(18,8) | platform fee charged to seller |
| created_at | timestamp | |
| updated_at | timestamp | |

### Trade Events

Audit log for every state change in a trade.

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| trade_id | bigint FK | |
| actor_id | bigint FK | user who performed action |
| actor_type | enum | "buyer", "seller", "system", "admin" |
| from_status | string | previous status |
| to_status | string | new status |
| notes | text | optional |
| created_at | timestamp | |

### User Ratings

Feedback after a completed trade.

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| trade_id | bigint FK | |
| rater_id | bigint FK | user who left feedback |
| rated_user_id | bigint FK | user being rated |
| rating | tinyint | 1 to 5 |
| comment | text | optional |
| created_at | timestamp | |

### Dispute Messages

Chat between buyer, seller, and admin during a dispute.

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| trade_id | bigint FK | |
| user_id | bigint FK | |
| message | text | |
| attachment_path | string | optional file evidence |
| created_at | timestamp | |

### Platform Fees / Margins

Configurable fee structures per asset/fiat pair.

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| asset_id | bigint FK | |
| fiat_currency_id | bigint FK | |
| buyer_fee_type | enum | "percentage" or "fixed" |
| buyer_fee_value | decimal(18,8) | |
| seller_fee_type | enum | "percentage" or "fixed" |
| seller_fee_value | decimal(18,8) | |
| min_fee | decimal(18,8) | minimum fee per trade |
| max_fee | decimal(18,8) | maximum fee per trade |
| enabled | bool | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Reference Prices

External or manual reference prices for floating-rate ads.

| Field | Type | Notes |
|---|---|---|
| id | bigint | |
| asset_id | bigint FK | |
| fiat_currency_id | bigint FK | |
| price | decimal(18,8) | reference price |
| source | string | e.g. "manual", "coingecko" |
| valid_at | timestamp | when this price was captured |
| created_at | timestamp | |

## Trade State Machine

```
                    ┌────────────────────────────────────────────┐
                    │                                            │
                    v                                            │
              ┌──────────┐                                       │
              │ pending  │── buyer cancels ──→ ┌──────────┐     │
              └────┬─────┘                     │ cancelled│     │
                   │                           └──────────┘     │
        seller confirms order                                    │
                   │                                             │
                   v                                             │
              ┌──────────┐                                       │
              │ awaiting │── buyer cancels ──→ ┌──────────┐     │
              │ payment  │                     │ cancelled│     │
              └────┬─────┘                     └──────────┘     │
                   │                                             │
     buyer marks as paid (uploads proof)                         │
                   │                                             │
                   v                                             │
              ┌──────────┐                                       │
              │ payment  │── dispute opened ──→ ┌──────────┐    │
              │ sent     │                      │ disputed │    │
              └────┬─────┘                      └────┬─────┘    │
                   │                                 │          │
      seller confirms fiat received                  │          │
                   │                      admin resolves         │
                   v                           │                 │
              ┌──────────┐                     v                 │
              │ released │              ┌────────────┐           │
              └────┬─────┘              │ resolved   │           │
                   │                    ├────────────┤           │
                   │                    │ released   │           │
                   │                    │ cancelled  │           │
                   v                    └────────────┘           │
              ┌──────────┐                                       │
              │completed │                                       │
              └──────────┘                                       │
                                              Timer expiry also
                                              leads to dispute
```

### Status Descriptions

| Status | Meaning |
|---|---|
| `pending` | Trade created, awaiting seller's confirmation to proceed |
| `awaiting_payment` | Seller confirmed, buyer must pay and upload proof |
| `payment_sent` | Buyer uploaded proof, seller must confirm fiat received |
| `released` | Seller released asset from escrow |
| `completed` | Both sides confirmed, trade finished |
| `cancelled` | Trade was cancelled (by buyer before payment, or by admin) |
| `disputed` | Either party opened a dispute |
| `resolved` | Admin resolved the dispute (trade ends as released or cancelled) |

### Timer-Based Auto-Cancellation

| Timer | Duration | Action |
|---|---|---|
| Payment window | 15 minutes (configurable) | If buyer doesn't mark as paid, trade auto-cancels |
| Release window | 15 minutes (configurable) | If seller doesn't release after payment, buyer can dispute |
| Dispute response | 24 hours (configurable) | If admin doesn't respond to dispute, auto-escalate |

## Feature List by Phase

### Phase 1 — Advertisements & Marketplace

**Backend**
- [ ] `assets` table + model + Filament resource (admin CRUD)
- [ ] `payment_methods` table + model + Filament resource (admin CRUD)
- [ ] `advertisements` table + model
- [ ] Ad CRUD API (`POST /api/portal/ads`, `GET /api/portal/ads`, `PUT/DELETE`)
- [ ] Public ad listing with filters (asset, fiat, payment method, price range)
- [ ] My Ads dashboard (portal Vue page)
- [ ] Ad status toggling (active/paused/closed)
- [ ] Mobile API endpoints for ads

**Portal (Vue)**
- [ ] Ad listing page with search/filter
- [ ] Create Ad form (sell + buy)
- [ ] My Ads management page

**Admin (Filament)**
- [ ] Assets resource
- [ ] Payment methods resource
- [ ] Ads management (view, suspend, delete)

### Phase 2 — Trade Execution

**Backend**
- [ ] `trades` table + model
- [ ] `trade_events` table + model
- [ ] Trade creation endpoint (`POST /api/portal/trades`)
- [ ] Trade listing endpoint (buyer view + seller view)
- [ ] Trade detail endpoint (with events)
- [ ] Confirm trade (seller: `POST /api/portal/trades/{id}/confirm`)
- [ ] Mark as paid with proof upload (`POST /api/portal/trades/{id}/mark-paid`)
- [ ] Release asset (`POST /api/portal/trades/{id}/release`)
- [ ] Cancel trade (`POST /api/portal/trades/{id}/cancel`)
- [ ] Trade state machine validation
- [ ] Timer-based auto-cancellation (queued job)

**Portal (Vue)**
- [ ] Trade detail page with timeline
- [ ] Action buttons (confirm, mark paid, release, cancel)
- [ ] Proof upload component
- [ ] Trade list with filters

**Mobile API**
- [ ] All trade endpoints exposed under `/api/mobile/trades`
- [ ] Sanctum-based auth

### Phase 3 — Dispute Resolution

**Backend**
- [ ] `dispute_messages` table + model
- [ ] Open dispute endpoint (`POST /api/portal/trades/{id}/dispute`)
- [ ] Dispute chat endpoint (list + send messages)
- [ ] Resolve dispute endpoint (admin: `POST /api/admin/disputes/{id}/resolve`)
- [ ] Admin dispute listing

**Admin (Filament)**
- [ ] Disputes resource with full trade context
- [ ] Dispute chat widget (buyer, seller, admin can see messages)
- [ ] Resolve dispute form (release to buyer / release to seller / cancel)

**Portal (Vue)**
- [ ] Dispute chat UI within trade detail
- [ ] Open dispute button (when eligible)

### Phase 4 — Platform Economics

**Backend**
- [ ] `platform_fees` table + model + Filament resource
- [ ] `reference_prices` table + model
- [ ] Fee calculation service (applied on trade creation and completion)
- [ ] Revenue tracking (aggregate fees by day/asset/fiat)
- [ ] Revenue dashboard API

**Admin (Filament)**
- [ ] Fee configuration per asset/fiat pair
- [ ] Reference price management (manual or auto-fetch)
- [ ] Revenue dashboard with charts
- [ ] Trade volume reports

### Phase 5 — Reputation & Safety

**Backend**
- [ ] `user_ratings` table + model
- [ ] Rating endpoint (`POST /api/portal/trades/{id}/rate`)
- [ ] User stats (completion rate, avg response time, total volume, total trades)
- [ ] Rating aggregation per user

**Portal / Mobile**
- [ ] Rate counterparty after completed trade
- [ ] User profile with reputation stats
- [ ] Trust score display on ads

**Admin (Filament)**
- [ ] User detail with trade history and ratings
- [ ] Flag user / restrict trading
- [ ] User verification tiers management

### Phase 6 — Operational Polish

**Backend**
- [ ] Notifications service (in-app + push)
- [ ] Device token registration
- [ ] Announcements CRUD
- [ ] Trade export (CSV)
- [ ] Full audit trail (admin action logging)

**Portal / Mobile**
- [ ] Notification list
- [ ] Push notification integration
- [ ] In-app notification banner

**Admin (Filament)**
- [ ] Announcements resource
- [ ] Export tool for trades and revenue
- [ ] Audit log viewer

## Mobile API Contract

All mobile endpoints are prefixed with `/api/mobile` and protected by `auth:sanctum`.

### Authentication

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/mobile/auth/register` | Register new user |
| POST | `/api/mobile/auth/login` | Login, returns Bearer token |
| POST | `/api/mobile/auth/logout` | Revoke token |
| GET | `/api/mobile/profile` | Current user profile |
| PUT | `/api/mobile/profile` | Update profile |

### Advertisements

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/mobile/ads` | List active sell/buy ads |
| GET | `/api/mobile/ads/{id}` | Ad detail |
| POST | `/api/mobile/ads` | Create ad |
| PUT | `/api/mobile/ads/{id}` | Update ad |
| DELETE | `/api/mobile/ads/{id}` | Delete ad (only if no active trades) |
| GET | `/api/mobile/my-ads` | Current user's ads |

Query params for listing: `type` (buy/sell), `asset`, `fiat`, `payment_method`, `min_price`, `max_price`, `sort_by` (price, user_rating), `page`.

### Trades

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/mobile/trades` | List user's trades |
| GET | `/api/mobile/trades/{id}` | Trade detail with events |
| POST | `/api/mobile/trades` | Create trade from ad |
| POST | `/api/mobile/trades/{id}/mark-paid` | Buyer marks as paid (with proof file) |
| POST | `/api/mobile/trades/{id}/release` | Seller releases asset |
| POST | `/api/mobile/trades/{id}/cancel` | Cancel trade (before payment) |
| POST | `/api/mobile/trades/{id}/dispute` | Open dispute |
| GET | `/api/mobile/trades/{id}/messages` | Dispute messages |
| POST | `/api/mobile/trades/{id}/messages` | Send dispute message |

### Ratings

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/mobile/trades/{id}/rate` | Rate counterparty |
| GET | `/api/mobile/users/{id}/ratings` | View user's ratings |

### Reference Data

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/mobile/assets` | List enabled assets |
| GET | `/api/mobile/fiat-currencies` | List enabled fiat currencies |
| GET | `/api/mobile/payment-methods` | List enabled payment methods |
| GET | `/api/mobile/reference-prices` | Current reference prices |

### Notifications

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/mobile/notifications` | List notifications |
| POST | `/api/mobile/device-tokens` | Register push token |
| DELETE | `/api/mobile/device-tokens/{token}` | Unregister push token |

## Portal (Vue) Routes

```
/login
/trades          → Trade list (buyer view + seller view)
/trades/:id      → Trade detail with timeline + actions
/ads             → Browse marketplace ads
/ads/create      → Create advertisement (buy or sell)
/ads/:id/edit    → Edit advertisement
/my-ads          → Current user's ads
/my-profile      → Profile with reputation stats
/settings        → Account settings
/support         → Support tickets (existing)
```

## Admin (Filament) Resources

- Users (existing) — add KYC tier, trade limits, flag status
- Roles & Permissions (existing)
- Assets (new)
- Fiat Currencies (existing)
- Payment Methods (new)
- Advertisements (new) — approve, suspend, delete
- Trades (new) — full detail, status override, cancel
- Disputes (new) — chat view, resolution form
- Platform Fees (new)
- Reference Prices (new)
- Revenue Dashboard (new) — charts + export
- Audit Logs
- Announcements (new)

## Security & Trust

- All trades require verified KYC (configurable minimum tier).
- Ads from unverified users are hidden by default.
- Dispute resolution favors the party with stronger evidence.
- Users with high cancellation rates are flagged for review.
- Platform never holds user funds except during active trade escrow.

## Acceptance Criteria

The platform is usable when:

1. Admin can create assets, fiat currencies, and payment methods.
2. A verified user can post a sell ad.
3. Another verified user can browse ads and create a trade.
4. The buyer pays outside the platform and uploads proof.
5. The seller confirms receipt and releases the asset.
6. Both users can rate each other.
7. If something goes wrong, either party can open a dispute.
8. Admin can see the dispute, review evidence, and resolve it.
9. Platform fees are calculated and recorded on every completed trade.
10. Admin can see revenue and volume in the dashboard.
