# DEVREADME.md

Developer-facing API reference for the current codebase in `routes/api.php` + `app/Http/Controllers/Api/*` + models/migrations.

Last updated: 2026-04-09

## 0) Current Backend Status

- PHP syntax checks: pass
- API route listing: pass
- Route cache: pass
- Basic tests: pass (`php artisan test`)
- Route/controller mapping in `routes/api.php`: clean

## 1) Base URL, Auth, and Security

- Base API prefix: `/api`
- Auth for protected routes: `Authorization: Bearer <sanctum_token>`
- Common content type: `application/json`
- API security headers are globally applied by [`SecurityHeaders.php`](app/Http/Middleware/SecurityHeaders.php) via [`bootstrap/app.php`](bootstrap/app.php)
- Role gate:
  - `admin` middleware returns `401 {"message":"Unauthenticated"}` if no user
  - `admin` middleware returns `403 {"message":"Forbidden"}` for non-admin users

## 1.1 Required ENV Keys (Project-specific)

- `CASHFREE_MODE` (`sandbox` or `production`)
- `CASHFREE_VERIFICATION_CLIENT_ID`
- `CASHFREE_VERIFICATION_CLIENT_SECRET`
- `CASHFREE_PAYMENT_CLIENT_ID`
- `CASHFREE_PAYMENT_CLIENT_SECRET`
- `CASHFREE_WEBHOOK_SECRET`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI` (recommended: `${APP_URL}/api/auth/google/callback`)
- `SANCTUM_STATEFUL_DOMAINS`
- Mail transport compatibility:
  - Preferred: `MAIL_SCHEME`
  - Backward-compatible fallback supported in config: `MAIL_ENCRYPTION`

## 2) Quick Auth Flow

1. `POST /api/register`
2. `POST /api/login`
3. Use returned `token` as Bearer token
4. Call protected endpoints

Example:

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"login":"9999999999","password":"Pass@123"}'
```

## 3) Common Output Patterns (All Possible Response Shapes)

### 3.1 Success JSON

```json
{
  "success": true,
  "message": "...",
  "data": {}
}
```

### 3.2 Validation Error (`422`)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": ["error text"]
  }
}
```

### 3.3 Unauthenticated (`401`)

Pattern A (most controllers):

```json
{ "success": false, "message": "User not authenticated" }
```

Pattern B (`admin` middleware):

```json
{ "message": "Unauthenticated" }
```

### 3.4 Forbidden (`403`)

Pattern A:

```json
{ "success": false, "message": "Access denied" }
```

Pattern B (`admin` middleware):

```json
{ "message": "Forbidden" }
```

### 3.5 Not Found (`404`)

```json
{ "success": false, "message": "... not found" }
```

### 3.6 Conflict / Business Rule

- `409` conflict (e.g., vehicle unavailable)
- `402` (used in rental phase-1 for insufficient wallet balance)
- `429` rate-limited requests

### 3.7 File Responses

Some endpoints return file streams instead of JSON:
- Document download
- CSV exports
- Agreement/receipt downloads

### 3.8 Fallback (`404` unknown route)

```json
{
  "success": false,
  "message": "API endpoint not found",
  "error": "The requested endpoint does not exist",
  "path": "...",
  "method": "..."
}
```

## 4) Complete Endpoint Catalog

All paths below are full `/api/...` paths.

## 4.1 Public Auth + Recovery

- `POST /api/register` -> `AuthController@register`
- `POST /api/login` -> `AuthController@login`
- `POST /api/email/verify/send` -> `AuthController@sendEmailVerification`
- `POST /api/email/verify/otp` -> `AuthController@verifyEmailWithOtp`
- `POST /api/email/verify/resend` -> `AuthController@resendEmailVerification`
- `POST /api/password/forgot` -> `AuthController@sendPasswordResetOtp`
- `POST /api/password/reset` -> `AuthController@resetPasswordWithOtp`
- `POST /api/password/resend-otp` -> `AuthController@resendPasswordResetOtp`
- `GET /api/email/verify/token/{token}` -> `AuthController@verifyEmailWithToken`

Example request (`register`):

```json
{
  "name": "Shakti Dev",
  "phone": "9999999999",
  "email": "dev@example.com",
  "password": "Strong@123",
  "password_confirmation": "Strong@123"
}
```

Example success (`201`):

```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {"id": 1, "name": "Shakti Dev", "phone": "9999999999", "role": "user"},
    "token": "1|...",
    "requires_verification": true
  }
}
```

## 4.2 Google Auth

- `GET /api/auth/google/auth-url` -> `AuthController@getGoogleAuthUrl`
- `POST /api/auth/google/callback` -> `AuthController@handleGoogleCallback`
- `POST /api/auth/google/set-password` -> `AuthController@setPasswordForGoogleUser` (auth)
- `POST /api/auth/google/link` -> `AuthController@linkGoogleAccount` (auth)
- `POST /api/auth/google/unlink` -> `AuthController@unlinkGoogleAccount` (auth)

## 4.3 Protected Auth/Profile

- `POST /api/auth/logout`
- `GET /api/auth/me`
- `POST /api/auth/change-password`
- `POST /api/auth/refresh-token`

## 4.4 Vehicles

- `GET /api/vehicles`
- `POST /api/vehicles`
- `GET /api/vehicles/available`
- `GET /api/vehicles/{id}`
- `PUT /api/vehicles/{id}`
- `PATCH /api/vehicles/{id}/status`
- `DELETE /api/vehicles/{id}`
- `GET /api/vehicles/{id}/statistics`

Create vehicle payload example:

```json
{
  "name": "Activa 6G",
  "number_plate": "OD05AB1234",
  "type": "bike",
  "hourly_rate": 80,
  "daily_rate": 600,
  "weekly_rate": 3500,
  "status": "available"
}
```

## 4.5 Customers

Admin-only:
- `GET /api/customers`
- `GET /api/customers/search`
- `GET /api/customers/incomplete-documentation`
- `GET /api/customers/verified`
- `GET /api/customers/{id}`
- `GET /api/customers/{id}/statistics`
- `GET /api/customers/{id}/rental-history`
- `PUT /api/customers/{id}`
- `GET /api/customers/export`
- `GET /api/customers/analytics`

All authenticated users:
- `POST /api/customers/check-by-license`

`check-by-license` payload:

```json
{ "license_number": "OD0120240001234" }
```

Possible outputs:
- `200` with `exists: true` and masked customer/license
- `200` with `exists: false`
- `422` validation error
- `429` too many checks

## 4.6 Rentals (3-phase flow)

- `POST /api/rentals/phase1/verify`
- `POST /api/rentals/phase2/documents`
- `POST /api/rentals/{id}/phase3/sign`
- `POST /api/rentals/{id}/return`
- `GET /api/rentals/{id}/phase-status`
- `POST /api/rentals/{id}/cancel`

Phase-1 payload:

```json
{
  "vehicle_id": 12,
  "customer_phone": "9999999999",
  "dl_number": "OD0120240001234",
  "dob": "1996-04-11"
}
```

Phase-1 possible outputs from code:
- `200` success + `verification_token`, `rental_id`, customer/vehicle data
- `402` insufficient wallet
- `409` vehicle not available
- `422` invalid DL/validation
- `500` verification/config failure

Phase-2 payload (`multipart/form-data`):
- `verification_token` (required)
- `license_image` (optional image)
- `aadhaar_image` (optional image)

Phase-3 payload (`multipart/form-data`):
- `signed_agreement_image` (required)
- `customer_with_vehicle_image` (optional)
- `vehicle_condition_video` (optional)

Return payload:

```json
{
  "vehicle_in_good_condition": false,
  "damage_amount": 500,
  "damage_description": "Left mirror broken"
}
```

Additional rental endpoints:
- `GET /api/rentals/active`
- `GET /api/rentals/history`
- `GET /api/rentals/{id}`
- `GET /api/rentals/statistics`
- `GET /api/rentals/{id}/agreement`
- `GET /api/rentals/{id}/receipt`

## 4.7 Documents

- `GET /api/documents`
- `GET /api/documents/{id}`
- `GET /api/documents/{id}/download/{type}` (`type`: `aadhaar|license`)
- `DELETE /api/documents/{id}`
- `GET /api/documents/unverified` (admin)
- `POST /api/documents/bulk-verify` (admin)
- `POST /api/documents/{id}/verify` (admin)

Additional document admin endpoints:
- `POST /api/documents/{id}/reject`
- `GET /api/documents/analytics`

## 4.8 Dashboard

- `GET /api/dashboard`
- `GET /api/dashboard/recent`
- `GET /api/dashboard/vehicles`
- `GET /api/dashboard/rentals`
- `GET /api/dashboard/summary`
- `GET /api/dashboard/top-vehicles`

## 4.9 Wallet

- `GET /api/wallet`
- `GET /api/wallet/transactions`
- `GET /api/wallet/transactions/{id}`
- `POST /api/wallet/add`
- `POST /api/wallet/deduct`
- `POST /api/wallet/transfer`
- `GET /api/wallet/statement`
- `POST /api/wallet/recharge/initiate`
- `GET /api/wallet/payment-status`

Examples:

```json
{ "amount": 1000, "payment_method": "upi" }
```

```json
{ "recipient_phone": "8888888888", "amount": 200, "reason": "test" }
```

## 4.10 Reports

- `GET /api/reports/earnings`
- `GET /api/reports/rentals`
- `GET /api/reports/summary`
- `GET /api/reports/top-vehicles`
- `GET /api/reports/top-customers`
- `GET /api/reports/documents`
- `GET /api/reports/export/{type}` (`rentals|earnings|vehicles|customers`)

Admin-only:
- `GET /api/reports/verification-metrics`
- `GET /api/reports/fraud-detection`
- `GET /api/reports/customer-analytics`
- `GET /api/reports/access-logs`

## 4.11 Settings

- `GET /api/settings`
- `PUT /api/settings`
- `GET /api/settings/defaults`
- `GET /api/settings/type/{type}`
- `POST /api/settings/reset`
- `GET /api/settings/{key}`
- `PUT /api/settings/{key}`
- `DELETE /api/settings/{key}`
- `DELETE /api/settings`

Bulk update payload:

```json
{
  "settings": [
    {"key": "verification_price", "value": 49, "type": "integer"},
    {"key": "notifications.enabled", "value": true, "type": "boolean"}
  ]
}
```

## 4.12 Notifications

- `GET /api/notifications`
- `GET /api/notifications/unread-count`
- `GET /api/notifications/statistics`
- `GET /api/notifications/type/{type}`
- `PUT /api/notifications/{id}/read`
- `PUT /api/notifications/mark-all-read`
- `DELETE /api/notifications/read`
- `DELETE /api/notifications/{id}`
- `DELETE /api/notifications`
- `GET /api/notifications/{id}`

## 4.13 Admin Routes

User management:
- `GET /api/admin/users`
- `PUT /api/admin/users/{id}/role`
- `DELETE /api/admin/users/{id}`
- `GET /api/admin/users/{id}/details`

Rental management:
- `GET /api/admin/rentals`
- `GET /api/admin/rentals/stats`
- `GET /api/admin/rentals/fraud-alerts`
- `POST /api/admin/rentals/{id}/force-end`
- `GET /api/admin/rentals/analytics`

Settings management:
- `GET /api/admin/settings`
- `POST /api/admin/settings/verification-price`
- `POST /api/admin/settings/lease-threshold`

Stats/system/audit:
- `GET /api/admin/stats/users`
- `GET /api/admin/stats/vehicles`
- `GET /api/admin/stats/earnings`
- `GET /api/admin/stats/dashboard`
- `GET /api/admin/stats/verification`
- `GET /api/admin/stats/fraud`
- `GET /api/admin/system/health`
- `GET /api/admin/system/logs`
- `POST /api/admin/system/clear-cache`
- `GET /api/admin/system/cashfree-status`
- `GET /api/admin/audit/customer-access`
- `GET /api/admin/audit/rental-activity`
- `GET /api/admin/audit/user-activity`
- `GET /api/admin/audit/export`

## 4.14 Cashfree Webhooks (No Auth)

- `POST /api/webhooks/cashfree/payment`
- `POST /api/webhooks/cashfree/refund`
- `GET /api/webhooks/cashfree/health`

Webhook security behavior from code:
- IP whitelist check
- `x-webhook-signature` required
- signature verification via `CashfreeService::verifyWebhookSignature`
- idempotency by `event_id` cache

Possible webhook outputs:
- `200` processed/ignored/already processed
- `400` invalid payload/JSON
- `401` missing or invalid signature
- `403` unauthorized IP
- `404` transaction not found
- `500` processing failure

## 5) Route Health

Route/controller method mapping is now clean for `routes/api.php` (no missing controller methods detected in the current project state).

Additional framework health checks now pass after fixes:
- `php artisan route:list --path=api`
- `php artisan route:cache`

## 6) Data Model Snapshot (API-relevant)

- `users`: auth identity, roles, wallet, email/google verification flags
- `vehicles`: owner vehicle inventory + pricing + status
- `customers`: verified identity profile + encrypted `license_number`/`aadhaar_number` in model mutators
- `rentals`: multi-phase lifecycle (`verification -> document_upload -> active -> completed/cancelled`)
- `documents`: uploaded KYC assets + verification flags/metadata
- `wallet_transactions`: credit/debit/payment order lifecycle
- `user_settings`, `notifications`, `customer_access_logs`, `password_resets`, `email_verifications`

## 7) Useful cURL Templates

Protected request template:

```bash
curl -X GET http://localhost:8000/api/vehicles \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Accept: application/json"
```

Multipart upload template:

```bash
curl -X POST http://localhost:8000/api/rentals/phase2/documents \
  -H "Authorization: Bearer <TOKEN>" \
  -F "verification_token=<TOKEN_64>" \
  -F "license_image=@/path/license.jpg" \
  -F "aadhaar_image=@/path/aadhaar.jpg"
```
