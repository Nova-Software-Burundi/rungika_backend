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
    "user": { "id": 1, "name": "John", "email": "...", "phone": "...", "kyc_status": "verified", "kyc_tier": 2, "trading_enabled": true, "flagged": false },
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

---

## 3. Reference Data (protected)

All three return static lists used to populate dropdowns and filters.

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

## 4. Advertisements (protected)

### Browse active ads (public marketplace)

```
GET /api/mobile/ads?type=sell&asset_id=1&fiat_currency_id=1&per_page=20&page=1
```

Query params:
| Param | Type | Description |
|---|---|---|
| `type` | string | `buy` or `sell` |
| `asset_id` | int | Filter by asset |
| `fiat_currency_id` | int | Filter by fiat currency |
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
            "user": { "id": 2, "name": "Merchant", "kyc_tier": 2 },
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

→ `200` — single ad object (same shape as above)

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

## 5. Trades (protected)

The core trade lifecycle follows this state machine:

```
pending → awaiting_payment → payment_sent → released → completed
   ↓           ↓                  ↓
cancelled  cancelled          disputed → resolved (released or cancelled)
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
            "created_at": "2026-06-14T12:00:00.000000Z",
            "ad": { "id": 1 },
            "asset": { "id": 1, "code": "USDT" },
            "fiat_currency": { "id": 1, "code": "ZMW" },
            "payment_method": { "id": 1, "name": "Bank Transfer" }
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
    "buyer": { "id": 1, "name": "John" },
    "seller": { "id": 2, "name": "Merchant" },
    "asset": { "code": "USDT" },
    "fiat_currency": { "code": "ZMW" },
    "payment_method": { "name": "Bank Transfer" },
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
    "payment_details": "Reference: TRF-123456"
}
```

- `asset_amount`: quantity of the asset to buy/sell
- `payment_method_id`: must be one of the ad's accepted methods
- `payment_details`: optional — buyer's payment reference or account details
- `price`: required only if the ad uses `floating` price type

Backend validates:
- Ad is active
- Buyer is not the ad owner
- Amount within ad's min/max limits
- Sufficient available quantity
- Decrements `available_quantity` on the ad
- Calculates platform fees automatically

→ `201`
```json
{
    "id": 1,
    "reference": "P2P-4A2B9C",
    "status": "pending",
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

→ `200` — updated trade

### Mark as paid (buyer action)

```
POST /api/mobile/trades/{id}/mark-paid
```

Multipart form data (file upload):
| Field | Type | Required |
|---|---|---|
| `proof` | file (jpg/png/pdf, max 5MB) | No |
| `payment_details` | string (max 500) | No |

Moves trade from `awaiting_payment` → `payment_sent`.

→ `200` — updated trade

### Release asset (seller action)

```
POST /api/mobile/trades/{id}/release
```

Seller confirms fiat receipt. Moves `payment_sent` → `released` → `completed` (auto-completes).

→ `200` — updated trade

### Cancel trade (buyer or seller action)

```
POST /api/mobile/trades/{id}/cancel
```

```json
{ "cancelled_by": "buyer" }
```

`cancelled_by`: `"buyer"` or `"seller"`

Only allowed when status is `pending` or `awaiting_payment`. Restores `available_quantity` on the ad.

→ `200` — updated trade

### Open dispute

```
POST /api/mobile/trades/{id}/dispute
```

```json
{ "reason": "Seller not responding after payment" }
```

Only allowed when status is `payment_sent`. Moves trade to `disputed`. Either buyer or seller can open.

→ `200` — updated trade

### Get dispute messages (chat)

```
GET /api/mobile/trades/{id}/messages
```

Returns all chat messages for the dispute, ordered oldest-first.

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

Only allowed when trade is in `disputed` status.

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

## 6. Ratings (protected)

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
    "completion_rate": 92.0,
    "total_volume": 12500.50,
    "average_rating": 4.5,
    "total_ratings": 12,
    "kyc_tier": 2,
    "kyc_status": "verified",
    "trading_enabled": true
}
```

---

## 7. Notifications (protected)

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

## 8. Device Tokens (protected)

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

## 9. Trade State Machine Summary

```
User Action              Endpoint                                  From             → To
──────────────────────────────────────────────────────────────────────────────────────────
Buyer creates trade      POST /trades                             —                pending
Seller confirms          POST /trades/{id}/confirm                pending          awaiting_payment
Buyer marks paid         POST /trades/{id}/mark-paid              awaiting_payment payment_sent
Seller releases          POST /trades/{id}/release                payment_sent     released → completed
Buyer cancels            POST /trades/{id}/cancel                 pending/awaiting cancelled
Seller cancels           POST /trades/{id}/cancel                 pending/awaiting cancelled
Buyer/seller dispute     POST /trades/{id}/dispute                payment_sent     disputed
Admin resolves           (admin portal)                           disputed         resolved
```

---

## 10. Error Handling

All endpoints return consistent errors:

```json
// Validation error (422)
{ "message": "The asset amount field is required.", "errors": { "asset_amount": ["The asset amount field is required."] } }

// Business logic error (422)
{ "message": "Ad is not active." }

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

## 11. Implementation Checklist for Mobile Client

### Screen: Onboarding / Auth
- [ ] Register form (name, phone, email optional)
- [ ] WhatsApp OTP request + verify
- [ ] Firebase phone auth as alternative
- [ ] Token persistence (secure storage)
- [ ] Auto-login on app start (check token validity with `GET /profile`)
- [ ] Logout (delete token from storage + `POST /auth/logout`)

### Screen: Home / Marketplace
- [ ] Fetch `GET /assets` + `GET /fiat-currencies` + `GET /payment-methods` for filter dropdowns
- [ ] Browse ads: `GET /ads?type=sell&asset_id=X&fiat_currency_id=Y` with pull-to-refresh
- [ ] Ad detail sheet with price, limits, payment methods, seller name, seller rating
- [ ] "Buy" / "Sell" button → amount input → trade creation

### Screen: My Ads
- [ ] List: `GET /my-ads`
- [ ] Create ad: form with all fields → `POST /ads`
- [ ] Edit ad: `PUT /ads/{id}`
- [ ] Delete ad: `DELETE /ads/{id}`
- [ ] Status toggle (active/paused/closed)

### Screen: Trades
- [ ] Tabbed list: "As Buyer" and "As Seller"
- [ ] Each item shows: reference, status badge, asset/fiat, amount, counterparty name
- [ ] Status badges with color coding (pending=blue, awaiting_payment=amber, payment_sent=purple, released=teal, completed=green, cancelled=grey, disputed=red, resolved=cyan)

### Screen: Trade Detail
- [ ] Full trade info card (reference, status, amounts, fees, buyer, seller, payment method)
- [ ] Action buttons based on role + current status:
  - **Seller**: Confirm (pending), Release (payment_sent)
  - **Buyer**: Mark as Paid (awaiting_payment), Cancel (pending/awaiting_payment)
  - **Both**: Dispute (payment_sent)
- [ ] Proof upload (camera/gallery picker) for mark-paid
- [ ] Event timeline (list of status transitions)
- [ ] Dispute chat (if disputed): message list + text input + file attachment

### Screen: Notifications
- [ ] List: `GET /notifications` with unread indicator
- [ ] Tap → mark as read → navigate to relevant trade
- [ ] "Mark all as read" button
- [ ] Badge count on tab icon (`GET /notifications/unread-count`)

### Screen: Profile / Settings
- [ ] User info display (name, phone, email, KYC tier, KYC status)
- [ ] Reputation stats: `GET /users/{id}/stats`
- [ ] Ratings received: `GET /users/{id}/ratings`
- [ ] Edit profile: `PUT /profile`

### Screen: User Public Profile
- [ ] View another user's stats: `GET /users/{id}/stats`
- [ ] View ratings: `GET /users/{id}/ratings`

### Push Notifications
- [ ] On app startup, register device token: `POST /device-tokens`
- [ ] On logout, unregister: `DELETE /device-tokens/{token}`
- [ ] Handle incoming push → navigate to trade detail

---

## 12. API Base URL & Headers

```
Base URL: https://your-domain.com/api/mobile

Content-Type: application/json (most requests)
Accept: application/json

Authorization: Bearer <sanctum_token>  (for protected routes)
```

For file uploads (`mark-paid`, `sendMessage`), use `multipart/form-data`.
