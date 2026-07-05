# Mobile App API Integration Guide

Base URL: `https://your-domain.com/api/mobile`

All endpoints return JSON. Protected endpoints require `Authorization: Bearer <token>` header.

---

## 1. Authentication

All auth endpoints are **public** (no token required), except `logout`.

### Register

```
POST /api/mobile/auth/register
```

```json
{ "name": "John Doe", "phone": "+260970000000", "email": "john@example.com" }
```

→ `201`
```json
{ "message": "User registered successfully", "user": { "id": 1, "name": "John Doe", ... } }
```

### Login (Email/Password)

```
POST /api/mobile/auth/login
```

```json
{ "email": "john@example.com", "password": "secret" }
```

→ `200`
```json
{
    "token": "1|abc123def456...",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "phone": "+250781234567"
    }
}
```

On 401:
```json
{ "message": "Invalid email or password" }
```

### Login (WhatsApp OTP flow)

```
POST /api/mobile/auth/request-whatsapp-otp
{ "identifier": "+260970000000" }
```
→ `200`
```json
{ "message": "OTP sent via WhatsApp" }
```

```
POST /api/mobile/auth/verify-whatsapp-otp
{ "identifier": "+260970000000", "code": "123456" }
```
→ `200`
```json
{ "message": "Login successful", "token": "1|abc123...", "user": { ... } }
```

### Login (Firebase Phone Auth)

```
POST /api/mobile/auth/verify-firebase-phone
{ "idToken": "firebase-id-token", "identifier": "+260970000000" }
```
→ `200`
```json
{ "message": "Login successful", "token": "1|abc123...", "user": { ... } }
```

### Logout (protected)

```
POST /api/mobile/auth/logout
Authorization: Bearer 1|abc123...
```
→ `200`
```json
{ "message": "Logged out" }
```

**Important**: Save the `token` from login responses. The token is a Sanctum Bearer token. Send it on every subsequent request as:

```
Authorization: Bearer 1|abc123...
```

---

## 2. Profile (protected)

### Get current user

```
GET /api/mobile/profile
```

→ `200`
```json
{
    "user": {
        "id": 1,
        "name": "John",
        "email": "...",
        "phone": "...",
        "kyc_status": "verified",
        "kyc_tier": 2,
        "trading_enabled": true,
        "flagged": false,
        "is_merchant": false,
        "merchant_verified_at": null,
        "last_activity_at": "2026-07-05T10:30:00.000000Z"
    },
    "kyc_approved": true,
    "is_agent": false
}
```

### Update profile

```
PUT /api/mobile/profile
{ "name": "New Name", "email": "new@example.com" }
```

→ `200` `{ "user": { ... } }`

### Online heartbeat

Mobile apps should call this periodically (every 60s) to keep `last_activity_at` current, which drives the online/offline indicator on ad cards.

```
POST /api/mobile/ping
```

→ `200`
```json
{ "last_activity_at": "2026-07-05T10:30:00.000000Z" }
```

---

## 3. Reference Data (protected)

### List enabled assets

```
GET /api/mobile/assets
```

→ `200`
```json
[
    { "id": 1, "code": "USDT", "name": "Tether USD", "decimals": 6, "enabled": true },
    { "id": 2, "code": "USDC", "name": "USD Coin", "decimals": 6, "enabled": true }
]
```

### List enabled fiat currencies

```
GET /api/mobile/fiat-currencies
```

→ `200`
```json
[
    { "id": 1, "code": "ZMW", "name": "Zambian Kwacha", "enabled": true },
    { "id": 2, "code": "NGN", "name": "Nigerian Naira", "enabled": true }
]
```

### List enabled payment methods

```
GET /api/mobile/payment-methods
```

→ `200`
```json
[
    { "id": 1, "name": "Bank Transfer", "code": "bank_transfer", "enabled": true },
    { "id": 2, "name": "Mobile Money", "code": "mobile_money", "enabled": true }
]
```

### Latest reference prices

```
GET /api/mobile/reference-prices
```

→ `200`
```json
[
    {
        "id": 1,
        "price": "24.50000000",
        "source": "manual",
        "valid_at": "2026-06-14T12:00:00.000000Z",
        "asset": { "id": 1, "code": "USDT" },
        "fiat_currency": { "id": 1, "code": "ZMW" }
    }
]
```

---

## 4. User Payment Accounts (protected)

Users register their own payment details (bank accounts, mobile money numbers) before trading. These are shown to the counterparty during a trade.

### List my payment accounts

```
GET /api/mobile/payment-accounts
```

→ `200`
```json
[
    {
        "id": 1,
        "payment_method_id": 1,
        "payment_method": { "id": 1, "name": "Bank Transfer" },
        "account_label": "My GTBank Account",
        "account_holder": "John Doe",
        "account_number": "0123456789",
        "bank_name": "GTBank",
        "mobile_network": null,
        "mobile_number": null,
        "is_default": true,
        "is_verified": false
    }
]
```

### Create payment account

```
POST /api/mobile/payment-accounts
```

```json
{
    "payment_method_id": 1,
    "account_label": "My GTBank Account",
    "account_holder": "John Doe",
    "account_number": "0123456789",
    "bank_name": "GTBank",
    "is_default": true
}
```

For mobile money:
```json
{
    "payment_method_id": 2,
    "account_label": "My Airtel Money",
    "account_holder": "John Doe",
    "mobile_network": "Airtel",
    "mobile_number": "+260970000000",
    "is_default": false
}
```

→ `201` — created account

### Update payment account

```
PUT /api/mobile/payment-accounts/{id}
```

Send any subset of the creation fields.

→ `200` — updated account

### Delete payment account

```
DELETE /api/mobile/payment-accounts/{id}
```

→ `204 No Content`

### Set as default

```
PATCH /api/mobile/payment-accounts/{id}/default
```

→ `200`
```json
{ "message": "Default payment account updated." }
```

---

## 5. Advertisements (protected)

### Browse active ads

```
GET /api/mobile/ads?type=sell&asset_id=1&fiat_currency_id=1&per_page=20&page=1
```

Query params:

| Param | Type | Description |
|---|---|---|
| `type` | string | `buy` or `sell` |
| `asset_id` | int | Filter by asset |
| `fiat_currency_id` | int | Filter by fiat currency |
| `payment_method_id` | int | Filter by accepted payment method |
| `min_price` | decimal | Minimum fiat price per unit |
| `max_price` | decimal | Maximum fiat price per unit |
| `online` | bool | Only show traders who are currently online |
| `merchant` | bool | Only show verified merchants |
| `sort_by` | string | `price`, `user_rating`, `completion_rate` |
| `per_page` | int | Pagination size (default 20) |
| `page` | int | Page number |

→ `200` — paginated response
```json
{
    "data": [
        {
            "id": 1,
            "type": "sell",
            "price_type": "fixed",
            "price": "25.00000000",
            "margin": null,
            "min_order": "100.00000000",
            "max_order": "5000.00000000",
            "available_quantity": "200.00000000",
            "payment_methods": [1, 2],
            "terms": "Payment within 15 minutes",
            "status": "active",
            "user": {
                "id": 2,
                "name": "Merchant",
                "kyc_tier": 2,
                "is_merchant": true,
                "is_online": true,
                "last_activity_at": "2026-07-05T10:29:00.000000Z",
                "completion_rate": 98.5,
                "total_trades": 150,
                "average_rating": 4.8
            },
            "asset": { "id": 1, "code": "USDT" },
            "fiat_currency": { "id": 1, "code": "ZMW" }
        }
    ],
    "current_page": 1,
    "last_page": 5,
    "total": 100
}
```

### Get ad detail

```
GET /api/mobile/ads/{id}
```

→ `200` — single ad object with full seller stats

### Create ad

```
POST /api/mobile/ads
```

```json
{
    "type": "sell",
    "asset_id": 1,
    "fiat_currency_id": 1,
    "price_type": "fixed",
    "price": 25.00,
    "min_order": 100,
    "max_order": 5000,
    "available_quantity": 200,
    "payment_methods": [1, 2],
    "terms": "Bank transfer only",
    "auto_reply": "Thanks for your order!"
}
```

If `price_type` is `floating`, send `margin` (percentage) instead of `price`.

**Validation**: For sell ads, the backend checks that the seller's wallet balance ≥ `available_quantity`. If not enough balance, the ad creation is rejected.

→ `201` — created ad

### Update ad

```
PUT /api/mobile/ads/{id}
```

Send any subset of the creation fields. Can also update `status` to `paused` or `closed`.

→ `200` — updated ad

### Delete ad

```
DELETE /api/mobile/ads/{id}
```

Only if no active trades. → `204 No Content`

### My ads

```
GET /api/mobile/my-ads?page=1
```

→ `200` — paginated list of current user's ads

---

## 6. Trades (protected)

### Trade State Machine

```
pending → awaiting_payment → payment_sent → released → completed
   ↓           ↓                  ↓
cancelled  cancelled          disputed → resolved (released or cancelled)

Also:
- payment timer expiry → auto-cancel (system)
- 24h in dispute without response → auto-escalate
```

### List my trades

```
GET /api/mobile/trades?page=1&per_page=20
```

Returns trades where the authenticated user is either buyer or seller.

→ `200` — paginated
```json
{
    "data": [
        {
            "id": 1,
            "reference": "P2P-4A2B9C",
            "status": "awaiting_payment",
            "asset_amount": "10.00000000",
            "fiat_amount": "250.00000000",
            "price": "25.00000000",
            "fee_buyer": "1.25000000",
            "fee_seller": "0.00000000",
            "payment_details": null,
            "payment_remaining_seconds": 845,
            "payment_expires_at": "2026-07-05T11:00:00.000000Z",
            "created_at": "2026-06-14T12:00:00.000000Z",
            "ad": { "id": 1 },
            "asset": { "id": 1, "code": "USDT" },
            "fiat_currency": { "id": 1, "code": "ZMW" },
            "payment_method": { "id": 1, "name": "Bank Transfer" },
            "buyer": { "id": 1, "name": "John" },
            "seller": { "id": 2, "name": "Merchant" },
            "unread_chat_count": 2
        }
    ],
    "current_page": 1,
    "last_page": 3
}
```

### Get trade detail (with event timeline)

```
GET /api/mobile/trades/{id}
```

→ `200`
```json
{
    "id": 1,
    "reference": "P2P-4A2B9C",
    "status": "payment_sent",
    "asset_amount": "10.00000000",
    "fiat_amount": "250.00000000",
    "price": "25.00000000",
    "fee_buyer": "1.25000000",
    "fee_seller": "0.50000000",
    "proof_path": "/storage/trade-proofs/abc.jpg",
    "payment_remaining_seconds": null,
    "payment_expires_at": null,
    "buyer": { "id": 1, "name": "John" },
    "seller": { "id": 2, "name": "Merchant" },
    "asset": { "code": "USDT" },
    "fiat_currency": { "code": "ZMW" },
    "payment_method": { "name": "Bank Transfer" },
    "buyer_payment_account": {
        "id": 1,
        "account_label": "My GTBank Account",
        "account_holder": "John Doe",
        "account_number": "0123456789",
        "bank_name": "GTBank"
    },
    "seller_payment_account": null,
    "events": [
        { "id": 1, "actor_type": "system", "from_status": null, "to_status": "pending", "notes": "Trade created", "actor": { "name": "System" }, "created_at": "..." },
        { "id": 2, "actor_type": "seller", "from_status": "pending", "to_status": "awaiting_payment", "notes": "Seller confirmed", "actor": { "name": "Merchant" }, "created_at": "..." },
        { "id": 3, "actor_type": "buyer", "from_status": "awaiting_payment", "to_status": "payment_sent", "notes": "Buyer marked as paid", "actor": { "name": "John" }, "created_at": "..." }
    ]
}
```

### Create trade (buyer initiates)

```
POST /api/mobile/trades
```

```json
{
    "ad_id": 1,
    "asset_amount": 10,
    "payment_method_id": 1,
    "buyer_payment_account_id": 1,
    "payment_details": "Reference: TRF-123456"
}
```

- `asset_amount`: quantity of the asset to buy
- `payment_method_id`: must be one of the ad's accepted methods
- `buyer_payment_account_id`: (optional) the buyer's registered payment account to use for sending payment
- `payment_details`: optional — buyer's payment reference or notes
- `price`: required only if the ad uses `floating` price type

Backend validates:
- Ad is active
- Buyer is not the ad owner
- Amount within ad's min/max limits
- Sufficient available quantity
- **Sufficient seller wallet balance** (escrow hold)
- **Buyer KYC tier limits** (max trade size, daily volume, active trades)
- **Buyer has not blocked seller** and **seller has not blocked buyer**
- Price deviation is within ad's allowed range (for floating price ads)
- Decrements `available_quantity` on the ad
- Places escrow hold on seller's wallet
- Calculates platform fees (with volume-based tier discounts)
- Sets `payment_expires_at = now + 30 minutes`

→ `201`
```json
{
    "id": 1,
    "reference": "P2P-4A2B9C",
    "status": "pending",
    "payment_remaining_seconds": null,
    "payment_expires_at": null,
    "fee_buyer": "1.25000000",
    "fee_seller": "0.00000000",
    ...
}
```

### Confirm trade (seller action)

```
POST /api/mobile/trades/{id}/confirm
```

Moves trade from `pending` → `awaiting_payment`. Only the seller can confirm.

Sets `payment_expires_at = now + 30 minutes`.

→ `200` — updated trade with `payment_remaining_seconds`

### Mark as paid (buyer action)

```
POST /api/mobile/trades/{id}/mark-paid
```

Multipart form data:

| Field | Type | Required |
|---|---|---|
| `proof` | file (jpg/png/pdf, max 5MB) | No |
| `payment_details` | string (max 500) | No |

Moves trade from `awaiting_payment` → `payment_sent` (before expiry). If the payment timer has expired, the backend rejects with `422` — the trade has already been auto-cancelled.

→ `200` — updated trade

### Release asset (seller action)

```
POST /api/mobile/trades/{id}/release
```

Seller confirms fiat receipt. Backend:
- Releases escrowed asset to buyer's wallet
- Deducts platform fee from seller (or buyer, per config)
- Moves `payment_sent` → `released` → `completed`

If the seller has 2FA enabled and the trade value exceeds the 2FA threshold, the request requires a valid 2FA code:
```json
{ "two_factor_code": "123456" }
```

→ `200` — updated trade

### Cancel trade (buyer or seller action)

```
POST /api/mobile/trades/{id}/cancel
```

```json
{ "cancelled_by": "buyer" }
```

`cancelled_by`: `"buyer"` or `"seller"`

Only allowed when status is `pending` or `awaiting_payment`. Backend:
- Refunds escrowed asset to seller's wallet
- Restores `available_quantity` on the ad
- Records a cancellation penalty event

If the user exceeds the cancellation limit (10 per rolling 7 days), their trading is restricted.

→ `200` — updated trade

### Open dispute

```
POST /api/mobile/trades/{id}/dispute
```

```json
{ "reason": "Seller not responding after payment" }
```

Only allowed when status is `payment_sent`. Either buyer or seller can open.

→ `200` — updated trade

### Get dispute messages

```
GET /api/mobile/trades/{id}/messages
```

Returns all dispute chat messages, ordered oldest-first.

→ `200`
```json
[
    {
        "id": 1,
        "message": "I have sent the payment, please release",
        "attachment_path": null,
        "created_at": "2026-06-14T12:05:00.000000Z",
        "user": { "id": 1, "name": "John" }
    }
]
```

### Send dispute message

```
POST /api/mobile/trades/{id}/messages
```

Multipart form data:

| Field | Type | Required |
|---|---|---|
| `message` | string (max 2000) | Yes |
| `attachment` | file (jpg/png/pdf, max 5MB) | No |

→ `201`
```json
{
    "id": 2,
    "message": "Here is my bank statement",
    "attachment_path": "/storage/dispute-attachments/xyz.pdf",
    "user": { "id": 1, "name": "John" }
}
```

---

## 7. Trade Chat (Pre-Dispute) (protected)

Trade chat is available from `pending` through `completed` (all states except `cancelled`). Once a dispute is opened, use the `/messages` endpoint instead.

### List chat messages

```
GET /api/mobile/trades/{id}/chat
```

Returns all pre-dispute chat messages, ordered oldest-first.

→ `200`
```json
[
    {
        "id": 1,
        "message": "Hi, I can make the payment now",
        "attachment_path": null,
        "created_at": "2026-06-14T11:55:00.000000Z",
        "user": { "id": 1, "name": "John" }
    },
    {
        "id": 2,
        "message": "Sure, send to the same account",
        "attachment_path": null,
        "created_at": "2026-06-14T11:56:00.000000Z",
        "user": { "id": 2, "name": "Merchant" }
    }
]
```

### Send chat message

```
POST /api/mobile/trades/{id}/chat
```

Multipart form data:

| Field | Type | Required |
|---|---|---|
| `message` | string (max 2000) | Yes |
| `attachment` | file (jpg/png/pdf, max 5MB) | No |

→ `201`
```json
{
    "id": 3,
    "message": "Payment sent, please confirm",
    "attachment_path": null,
    "user": { "id": 1, "name": "John" }
}
```

---

## 8. Ratings (protected)

### Rate a counterparty

```
POST /api/mobile/trades/{id}/rate
```

```json
{ "rating": 5, "comment": "Fast and reliable!" }
```

- `rating`: integer 1–5 (required)
- `comment`: string max 1000 (optional)

Only allowed when trade status is `completed`. Cannot rate the same trade twice. Rates the other party (buyer rates seller, seller rates buyer).

→ `201`
```json
{
    "id": 1,
    "rating": 5,
    "comment": "Fast and reliable!",
    "rater": { "id": 1, "name": "John" }
}
```

### View user ratings

```
GET /api/mobile/users/{user_id}/ratings?page=1
```

→ `200`
```json
{
    "ratings": {
        "data": [
            {
                "id": 1,
                "rating": 5,
                "comment": "Great trader",
                "created_at": "...",
                "rater": { "id": 2, "name": "Merchant" }
            }
        ]
    },
    "average_rating": 4.5,
    "total_ratings": 12
}
```

### View user stats (reputation profile)

```
GET /api/mobile/users/{user_id}/stats
```

→ `200`
```json
{
    "total_trades": 25,
    "completed_trades": 23,
    "cancelled_trades": 2,
    "cancellation_rate": 8.0,
    "completion_rate": 92.0,
    "total_volume": 12500.50,
    "average_rating": 4.5,
    "total_ratings": 12,
    "kyc_tier": 2,
    "kyc_status": "verified",
    "trading_enabled": true,
    "is_merchant": true,
    "is_online": true,
    "last_activity_at": "2026-07-05T10:29:00.000000Z"
}
```

---

## 9. Notifications (protected)

### List notifications

```
GET /api/mobile/notifications?page=1
```

→ `200`
```json
{
    "data": [
        {
            "id": "uuid-string",
            "type": "App\\Notifications\\TradeNotification",
            "data": { "title": "Trade updated", "body": "Merchant confirmed your order P2P-4A2B9C" },
            "read_at": null,
            "created_at": "2026-06-14T12:01:00.000000Z"
        }
    ],
    "unread_count": 3
}
```

### Mark single as read

```
POST /api/mobile/notifications/{id}/read
```

→ `200` `{ "message": "Marked as read." }`

### Mark all as read

```
POST /api/mobile/notifications/read-all
```

→ `200` `{ "message": "All marked as read." }`

### Unread count

```
GET /api/mobile/notifications/unread-count
```

→ `200` `{ "unread_count": 3 }`

---

## 10. Device Tokens (protected)

Used for push notification registration.

### Register token

```
POST /api/mobile/device-tokens
```

```json
{ "token": "expo-or-fcm-token", "platform": "ios" }
```

`platform`: `"ios"`, `"android"`, or `"web"`.

→ `201` — created device token (upserts on user_id + token unique pair)

### Unregister token

```
DELETE /api/mobile/device-tokens/{token}
```

→ `204 No Content`

---

## 11. Blocked Users (protected)

### List blocked users

```
GET /api/mobile/blocked-users
```

→ `200`
```json
[
    {
        "id": 1,
        "blocked_user": { "id": 3, "name": "Bad Trader" },
        "reason": "Scam attempt",
        "created_at": "2026-07-04T12:00:00.000000Z"
    }
]
```

### Block a user

```
POST /api/mobile/blocked-users
```

```json
{ "blocked_user_id": 3, "reason": "Scam attempt" }
```

→ `201`
```json
{ "message": "User blocked." }
```

### Unblock a user

```
DELETE /api/mobile/blocked-users/{blockedUserId}
```

→ `204 No Content`

---

## 12. 2FA Management (protected)

### Enable 2FA

```
POST /api/mobile/2fa/enable
```

```json
{ "password": "current-password" }
```

→ `200`
```json
{ "secret": "BASE32SECRET...", "qr_code_url": "data:image/png;base64,...", "recovery_codes": ["code1", "code2", ...] }
```

### Disable 2FA

```
POST /api/mobile/2fa/disable
```

```json
{ "password": "current-password" }
```

→ `200`
```json
{ "message": "2FA disabled." }
```

### Get recovery codes

```
GET /api/mobile/2fa/recovery-codes
```

Requires password confirmation:
```json
{ "password": "current-password" }
```

→ `200`
```json
{ "recovery_codes": ["code1", "code2", ...] }
```

---

## 13. Trade State Machine Summary

```
User Action              Endpoint                                  From             → To
────────────────────────────────────────────────────────────────────────────────────────────
Buyer creates trade      POST /trades                             —                pending
Seller confirms          POST /trades/{id}/confirm                pending          awaiting_payment
Buyer marks paid         POST /trades/{id}/mark-paid              awaiting_payment payment_sent
Seller releases          POST /trades/{id}/release                payment_sent     released → completed
Buyer cancels            POST /trades/{id}/cancel                 pending/awaiting cancelled
Seller cancels           POST /trades/{id}/cancel                 pending/awaiting cancelled
System auto-cancel       (scheduled job)                          awaiting_payment cancelled
Buyer/seller dispute     POST /trades/{id}/dispute                payment_sent     disputed
Admin resolves           (admin portal)                           disputed         resolved
```

---

## 14. Error Handling

All endpoints return consistent errors:

```json
// Validation error (422)
{ "message": "The asset amount field is required.", "errors": { "asset_amount": ["The asset amount field is required."] } }

// Business logic error (422)
{ "message": "Ad is not active." }
{ "message": "Insufficient wallet balance." }
{ "message": "Payment window has expired. This trade has been auto-cancelled." }
{ "message": "KYC tier limit exceeded. Maximum single trade: 5000 ZMW." }
{ "message": "Daily trade volume limit reached." }
{ "message": "Cancellation rate too high. Trading temporarily restricted." }
{ "message": "You have blocked this user." }
{ "message": "This user has blocked you." }

// Authorization error (403)
{ "message": "This action is unauthorized." }

// Not found (404)
{ "message": "No query results for model [App\\Models\\Trade] 99" }
```

**401 Unauthenticated** — no token or expired token:
```json
{ "message": "Unauthenticated." }
```

---

## 15. Implementation Checklist for Mobile Client

### Screen: Onboarding / Auth
- [ ] Register form (name, phone, email optional)
- [ ] Email/password login
- [ ] WhatsApp OTP request + verify
- [ ] Firebase phone auth as alternative
- [ ] Token persistence (secure storage)
- [ ] Auto-login on app start (check token validity with `GET /profile`)
- [ ] Logout (delete token from storage + `POST /auth/logout`)

### Screen: Payment Accounts
- [ ] List saved accounts: `GET /payment-accounts`
- [ ] Add bank account form (account_holder, account_number, bank_name)
- [ ] Add mobile money form (mobile_network, mobile_number)
- [ ] Set default account: `PATCH /payment-accounts/{id}/default`
- [ ] Edit account: `PUT /payment-accounts/{id}`
- [ ] Delete account: `DELETE /payment-accounts/{id}`

### Screen: Home / Marketplace
- [ ] Fetch `GET /assets` + `GET /fiat-currencies` + `GET /payment-methods` for filter dropdowns
- [ ] Browse ads: `GET /ads?type=sell&asset_id=X&fiat_currency_id=Y` with pull-to-refresh
- [ ] Filter by online traders, merchants, price range
- [ ] Ad detail sheet with price, limits, payment methods, seller stats (rating, trades, completion rate, online status, merchant badge)
- [ ] "Buy" / "Sell" button → amount input → select payment account → trade creation

### Screen: My Ads
- [ ] List: `GET /my-ads`
- [ ] Create ad: form with all fields → `POST /ads`
- [ ] Edit ad: `PUT /ads/{id}`
- [ ] Delete ad: `DELETE /ads/{id}`
- [ ] Status toggle (active/paused/closed)

### Screen: Trades
- [ ] Tabbed list: "As Buyer" and "As Seller"
- [ ] Each item shows: reference, status badge, asset/fiat, amount, counterparty name, payment countdown timer (if `payment_remaining_seconds > 0`)
- [ ] Unread chat badge
- [ ] Status badges with color coding

### Screen: Trade Detail
- [ ] Full trade info card with payment countdown timer
- [ ] Action buttons based on role + current status:
  - **Seller**: Confirm (pending), Release (payment_sent)
  - **Buyer**: Mark as Paid (awaiting_payment), Cancel (pending/awaiting_payment)
  - **Both**: Dispute (payment_sent)
- [ ] 2FA code input when releasing high-value trades
- [ ] Proof upload (camera/gallery picker) for mark-paid
- [ ] Payment account details of counterparty
- [ ] Event timeline (list of status transitions)
- [ ] Pre-dispute chat tab (text + file attachment)
- [ ] Dispute tab (if disputed): message list + text input + file attachment

### Screen: Blocked Users
- [ ] List: `GET /blocked-users`
- [ ] Block user: `POST /blocked-users`
- [ ] Unblock: `DELETE /blocked-users/{id}`

### Screen: 2FA Settings
- [ ] Enable 2FA (scan QR code, enter code to verify)
- [ ] Disable 2FA (with password confirmation)
- [ ] View recovery codes

### Screen: Notifications
- [ ] List: `GET /notifications` with unread indicator
- [ ] Tap → mark as read → navigate to relevant trade
- [ ] "Mark all as read" button
- [ ] Badge count on tab icon (`GET /notifications/unread-count`)

### Screen: Profile / Settings
- [ ] User info display (name, phone, email, KYC tier, KYC status, merchant badge)
- [ ] Reputation stats: `GET /users/{id}/stats`
- [ ] Ratings received: `GET /users/{id}/ratings`
- [ ] Edit profile: `PUT /profile`
- [ ] KYC document upload (self-service)
- [ ] Online heartbeat: `POST /ping` every 60s

### Screen: User Public Profile
- [ ] View another user's stats, ratings, online status, merchant badge
- [ ] Block user button from public profile

### Push Notifications
- [ ] On app startup, register device token: `POST /device-tokens`
- [ ] On logout, unregister: `DELETE /device-tokens/{token}`
- [ ] Handle incoming push → navigate to trade detail

---

## 16. API Base URL & Headers

```
Base URL: https://your-domain.com/api/mobile

Content-Type: application/json (most requests)
Accept: application/json

Authorization: Bearer <sanctum_token>  (for protected routes)
```

For file uploads (`mark-paid`, `sendMessage`, Trade Chat), use `multipart/form-data`.
