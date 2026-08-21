# SmartPOS Business Service — API Reference

> **Base URL:** `http://localhost:8002/api/v1` (local) · `https://api.smartpos.yourdomain.com/api/v1` (production)  
> **OpenAPI 3.1 Schema:** `GET /docs/business.json`  
> **Service Port:** `:8002`  
> **Version:** `1.0.0`

---

## Table of Contents

1. [Authentication](#1-authentication)
2. [Rate Limiting](#2-rate-limiting)
3. [Error Responses](#3-error-responses)
4. [Resource Identifiers](#4-resource-identifiers)
5. [Health Check](#5-health-check)
6. [Businesses](#6-businesses)
7. [Business Settings](#7-business-settings)
8. [Business Users](#8-business-users)
9. [Cashier Profiles & Outlet Assignments](#9-cashier-profiles--outlet-assignments)
10. [Outlets](#10-outlets)
11. [Registers](#11-registers)
12. [POS Devices](#12-pos-devices)
13. [Device Sessions](#13-device-sessions)
14. [Cashier Sessions](#14-cashier-sessions)
15. [Register Shifts & Cash Drawers](#15-register-shifts--cash-drawers)
16. [Warehouses](#16-warehouses)
17. [Warehouse Locations](#17-warehouse-locations)
18. [Permissions Reference](#18-permissions-reference)
19. [Security Architecture](#19-security-architecture)

---

## 1. Authentication

All endpoints (except Health Check and POS Device Machine Auth) require a **Bearer JWT** issued by the SmartPOS Auth Service (`:8001`).

```http
Authorization: Bearer <jwt_token>
```

### JWT Claims Expected

| Claim | Type | Description |
|:---|:---|:---|
| `sub` | string | User UUID |
| `user_uuid` | string | User UUID (preferred over `sub`) |
| `permissions` | string[] | List of permission strings |
| `roles` | string[] | `["admin"]` for platform-level admins |
| `iss` | string | Must be `smartpos-auth-service` (when `JWT_VERIFY_ISSUER=true`) |
| `aud` | string | Must be `smartpos-api` (when `JWT_VERIFY_AUDIENCE=true`) |
| `exp` | int | Unix timestamp — token rejected after expiry |

> Tokens with invalid signature, expired `exp`, mismatched `iss`/`aud`, or incorrect algorithm are rejected with `401 Unauthorized`.

---

## 2. Rate Limiting

| Rate Limiter | Applied To | Limit |
|:---|:---|:---|
| `throttle:api` | All authenticated routes | 60 req/min (default) |
| `throttle:auth` | `POST /pos-devices/auth` | **5 req/min** (brute-force protection) |
| `throttle:cashier_pin` | `POST /cashier-sessions/{id}/unlock` | Configurable PIN brute-force protection |

Exceeded limits return **HTTP 429 Too Many Requests** with `Retry-After` header.

---

## 3. Error Responses

### 401 Unauthorized
```json
{ "message": "Unauthenticated." }
{ "message": "Invalid or expired token." }
```

### 403 Forbidden
```json
{ "message": "This action is unauthorized." }
```

### 404 Not Found
```json
{ "message": "No query results for model [App\\Models\\Warehouse]." }
```

### 422 Validation Error
```json
{
  "message": "The name field is required.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

### 429 Too Many Requests
```json
{ "message": "Too Many Attempts." }
```
Response headers: `Retry-After: 60`, `X-RateLimit-Limit: 5`, `X-RateLimit-Remaining: 0`

### 413 Payload Too Large
```json
{
  "success": false,
  "error": "PAYLOAD_TOO_LARGE",
  "message": "Request payload exceeds the maximum allowed size of 2MB."
}
```

---

## 4. Resource Identifiers

All URL path parameters use **UUID** (not integer IDs). UUIDs are auto-generated on creation and are immutable.

```
/warehouses/{warehouse}           ← {warehouse} is the warehouse UUID
/warehouse-locations/{location}   ← {location} is the location UUID
```

---

## 5. Health Check

### `GET /api/v1/business/health`

No authentication required. Returns service liveness status.

**Response `200 OK`**
```json
{
  "status": "ok",
  "service": "smartpos-business-service",
  "version": "1.0.0",
  "timestamp": "2026-08-21T07:00:00+00:00"
}
```

---

## 6. Businesses

> Platform admins (`role: admin` in JWT) can view and operate on **all** businesses across the system.

### Resource Shape

```json
{
  "id": 1,
  "uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "name": "My Café",
  "code": "MYCAFE",
  "legal_name": "My Café Sdn Bhd",
  "phone": "+60123456789",
  "email": "owner@mycafe.com",
  "tax_number": "TN-001",
  "registration_number": "REG-001",
  "logo_path": null,
  "website": "https://mycafe.com",
  "description": "A cozy café",
  "address": "123 Jalan Bunga",
  "city": "Kuala Lumpur",
  "province": "Wilayah Persekutuan",
  "postal_code": "50000",
  "country_code": "MY",
  "currency_code": "MYR",
  "default_currency": "MYR",
  "currency_symbol": "RM",
  "receipt_header": "Welcome!",
  "receipt_footer": "Thank you!",
  "tax_rate": "6.00",
  "is_tax_inclusive": false,
  "timezone": "Asia/Kuala_Lumpur",
  "status": "active",
  "outlets_count": 2,
  "registers_count": 4,
  "pos_devices_count": 4,
  "business_users_count": 5,
  "created_at": "2026-08-21T07:00:00.000000Z",
  "updated_at": "2026-08-21T07:00:00.000000Z"
}
```

---

### `GET /api/v1/businesses`

List businesses accessible to the current user.

- **Permission:** `businesses.view`
- **Behavior:** Admin returns all businesses; normal user returns only businesses where user is an active member.

**Response `200 OK`**
```json
{ "data": [ { ...business } ] }
```

---

### `POST /api/v1/businesses`

Create a new business. The authenticated user becomes **owner** automatically. Auto-provisions a **default outlet, register, and POS device**, then emails credentials to the business email.

- **Permission:** `businesses.create`

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `name` | string | ✅ | Business display name (max 255) |
| `code` | string | ✅ | Unique business code (max 50) |
| `legal_name` | string | — | Legal registered name |
| `phone` | string | — | Contact phone |
| `email` | email | — | Business email — receives provisioned credentials |
| `tax_number` | string | — | Tax registration number |
| `registration_number` | string | — | Company registration number |
| `logo_path` | string | — | Path to uploaded logo |
| `website` | string | — | Business website URL |
| `description` | string | — | About the business |
| `address` | string | — | Street address |
| `city` | string | — | City (max 100) |
| `province` | string | — | Province / state (max 100) |
| `postal_code` | string | — | Postal code (max 20) |
| `country_code` | string | — | ISO country code e.g. `MY` (max 10) |
| `currency_code` | string | — | ISO currency code e.g. `MYR` (max 3) |
| `default_currency` | string | — | Currency label (max 10) |
| `currency_symbol` | string | — | Currency symbol e.g. `RM` (max 10) |
| `receipt_header` | string | — | Text to print on receipt header |
| `receipt_footer` | string | — | Text to print on receipt footer |
| `tax_rate` | number | — | Default tax rate percentage (0–100) |
| `is_tax_inclusive` | boolean | — | Whether prices include tax |
| `timezone` | string | — | PHP timezone string (max 100) |
| `status` | string | — | `active` · `inactive` · `suspended` (default: `active`) |

**Response `201 Created`**
```json
{
  "message": "Business created successfully. Default outlet, register, and POS terminal have been provisioned.",
  "data": { ...business },
  "provisioned": {
    "outlet": { ...outlet },
    "register": { ...register },
    "pos_device": { ...pos_device },
    "credentials": { "machine_password": "..." }
  }
}
```

---

### `GET /api/v1/businesses/{business}`

Show a business with its outlets, registers, and POS devices.

- **Permission:** `businesses.view`
- **Middleware:** `business.member`

**Response `200 OK`**
```json
{
  "data": {
    ...business,
    "outlets": [...],
    "registers": [...],
    "pos_devices": [...]
  }
}
```

---

### `PUT /api/v1/businesses/{business}`

Update business details. All fields are optional (same fields as POST).

- **Permission:** `businesses.update`
- **Middleware:** `business.member`, `business.owner`

**Response `200 OK`**
```json
{ "message": "Business updated successfully.", "data": { ...business } }
```

---

### `DELETE /api/v1/businesses/{business}`

Permanently delete a business and all associated data (cascade).

- **Permission:** `businesses.delete`
- **Middleware:** `business.member`, `business.owner`

**Response `200 OK`**
```json
{ "message": "Business deleted successfully." }
```

---

## 7. Business Settings

### `GET /api/v1/businesses/{business}/settings`

Retrieve business configuration settings.

- **Permission:** `businesses.view`
- **Middleware:** `business.member`

**Response `200 OK`**
```json
{ "data": { ...settings } }
```

---

### `PUT /api/v1/businesses/{business}/settings`

Update business settings.

- **Permission:** `businesses.update`
- **Middleware:** `business.member`, `business.owner`

**Response `200 OK`**
```json
{ "message": "...", "data": { ...settings } }
```

---

## 8. Business Users

Manage staff memberships within a business.

### Resource Shape

```json
{
  "id": 1,
  "business_id": 1,
  "user_uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "role": "cashier",
  "is_owner": false,
  "status": "active",
  "pin_code_hash": null,
  "joined_at": "2026-08-21T07:00:00.000000Z",
  "created_at": "2026-08-21T07:00:00.000000Z",
  "updated_at": "2026-08-21T07:00:00.000000Z"
}
```

> `pin_code_hash` is never returned in API responses.

---

### `GET /api/v1/businesses/{business}/users`

List all staff members of a business.

- **Permission:** `business_users.view`
- **Middleware:** `business.member`

**Response `200 OK`**
```json
{ "data": [ { ...businessUser } ] }
```

---

### `POST /api/v1/businesses/{business}/users`

Add a user to the business as a staff member.

- **Permission:** `business_users.manage`
- **Middleware:** `business.member`, `business.owner`

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `user_uuid` | uuid | ✅ | UUID of the user to add |
| `role` | string | — | Role label e.g. `cashier`, `manager` |
| `is_owner` | boolean | — | Elevate as business owner (default: `false`) |
| `status` | string | — | `active` · `inactive` (default: `active`) |
| `pin_code` | string | — | Cashier PIN code (hashed with bcrypt before saving) |

**Response `201 Created`**
```json
{ "message": "User added to business successfully.", "data": { ...businessUser } }
```

---

### `PUT /api/v1/businesses/{business}/users/{businessUser}`

Update a staff member's role, status, or PIN code.

> **Protection:** Cannot demote or suspend the sole owner.

- **Permission:** `business_users.manage`
- **Middleware:** `business.member`, `business.owner`

**Response `200 OK`**
```json
{ "message": "Business user membership updated successfully.", "data": { ...businessUser } }
```

---

### `POST /api/v1/businesses/{business}/users/{businessUser}/suspend`

Suspend a staff member's access.

> **Protection:** Cannot suspend the sole owner.

- **Permission:** `business_users.manage`
- **Middleware:** `business.member`, `business.owner`

**Response `200 OK`**
```json
{ "message": "Business user suspended successfully.", "data": { ...businessUser } }
```

---

### `DELETE /api/v1/businesses/{business}/users/{businessUser}`

Remove a staff member from the business.

> **Protection:** Cannot remove the sole owner.

- **Permission:** `business_users.manage`
- **Middleware:** `business.member`, `business.owner`

**Response `200 OK`**
```json
{ "message": "Business user removed successfully." }
```

---

## 9. Cashier Profiles & Outlet Assignments

### `GET /api/v1/businesses/{business}/users/{businessUser}/cashier-profile`

Retrieve the cashier profile (permissions, POS settings) for a staff member.

- **Permission:** `business_users.view`
- **Middleware:** `business.member`

**Response `200 OK`**
```json
{ "data": { ...cashierProfile } }
```

---

### `PUT /api/v1/businesses/{business}/users/{businessUser}/cashier-profile`

Update cashier permissions and settings for a staff member.

- **Permission:** `business_users.manage`
- **Middleware:** `business.member`, `business.owner`

**Response `200 OK`**
```json
{ "message": "...", "data": { ...cashierProfile } }
```

---

### `GET /api/v1/businesses/{business}/users/{businessUser}/outlets`

List all outlets the staff member is assigned to.

- **Permission:** `business_users.view`
- **Middleware:** `business.member`

**Response `200 OK`**
```json
{ "data": [ { ...outlet } ] }
```

---

### `POST /api/v1/businesses/{business}/users/{businessUser}/outlets`

Assign a staff member to an outlet.

- **Permission:** `business_users.manage`
- **Middleware:** `business.member`, `business.owner`

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `outlet_uuid` | uuid | ✅ | UUID of the outlet to assign |

**Response `201 Created`**
```json
{ "message": "...", "data": { ...assignment } }
```

---

### `DELETE /api/v1/businesses/{business}/users/{businessUser}/outlets/{outlet}`

Remove outlet assignment from a staff member.

- **Permission:** `business_users.manage`
- **Middleware:** `business.member`, `business.owner`

**Response `200 OK`**
```json
{ "message": "..." }
```

---

## 10. Outlets

An outlet is a physical store / branch location belonging to a business.

### Resource Shape

```json
{
  "id": 1,
  "uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "business_id": 1,
  "name": "Main Branch",
  "code": "MAIN",
  "phone": "+60123456789",
  "email": "main@mycafe.com",
  "address": "123 Jalan Bunga",
  "city": "Kuala Lumpur",
  "province": "Wilayah Persekutuan",
  "postal_code": "50000",
  "country_code": "MY",
  "status": "active",
  "created_at": "2026-08-21T07:00:00.000000Z",
  "updated_at": "2026-08-21T07:00:00.000000Z"
}
```

---

### `GET /api/v1/businesses/{business}/outlets`

List all outlets for a business.

- **Permission:** `outlets.view`
- **Middleware:** `business.member`

**Response `200 OK`**
```json
{ "data": [ { ...outlet } ] }
```

---

### `POST /api/v1/businesses/{business}/outlets`

Create a new outlet under a business.

- **Permission:** `outlets.create`
- **Middleware:** `business.member`

**Request Body**

| Field | Type | Required | Notes |
|:---|:---|:---|:---|
| `name` | string | ✅ | Outlet display name |
| `code` | string | ✅ | Unique code within the business |
| `phone` | string | — | |
| `email` | email | — | |
| `address` | string | — | |
| `city` | string | — | |
| `province` | string | — | |
| `postal_code` | string | — | |
| `country_code` | string | — | |
| `status` | string | — | `active` · `inactive` |

**Response `201 Created`**
```json
{ "message": "Outlet created successfully.", "data": { ...outlet } }
```

---

### `GET /api/v1/outlets/{outlet}`

Show a specific outlet.

- **Permission:** `outlets.view`
- **Middleware:** `outlet.access`

**Response `200 OK`**
```json
{ "data": { ...outlet } }
```

---

### `PUT /api/v1/outlets/{outlet}`

Update outlet details.

- **Permission:** `outlets.update`
- **Middleware:** `outlet.access`

**Response `200 OK`**
```json
{ "message": "Outlet updated successfully.", "data": { ...outlet } }
```

---

### `DELETE /api/v1/outlets/{outlet}`

Delete an outlet permanently.

- **Permission:** `outlets.delete`
- **Middleware:** `outlet.access`, `business.owner`

**Response `200 OK`**
```json
{ "message": "Outlet deleted successfully." }
```

---

## 11. Registers

A register (cash register / till) belongs to an outlet.

---

### `GET /api/v1/outlets/{outlet}/registers`

List all registers for an outlet.

- **Permission:** `registers.view`
- **Middleware:** `outlet.access`

**Response `200 OK`**
```json
{ "data": [ { ...register } ] }
```

---

### `POST /api/v1/outlets/{outlet}/registers`

Create a new register in an outlet.

- **Permission:** `registers.create`
- **Middleware:** `outlet.access`

**Request Body**

| Field | Type | Required | Notes |
|:---|:---|:---|:---|
| `name` | string | ✅ | Register display name |
| `code` | string | ✅ | Unique within outlet |
| `status` | string | — | `active` · `inactive` |

**Response `201 Created`**
```json
{ "message": "Register created successfully.", "data": { ...register } }
```

---

### `GET /api/v1/registers/{register}`

Show a specific register.

- **Permission:** `registers.view`
- **Middleware:** `register.access`

**Response `200 OK`**
```json
{ "data": { ...register } }
```

---

### `PUT /api/v1/registers/{register}`

Update register details.

- **Permission:** `registers.update`
- **Middleware:** `register.access`

**Response `200 OK`**
```json
{ "message": "Register updated successfully.", "data": { ...register } }
```

---

### `DELETE /api/v1/registers/{register}`

Delete a register permanently.

- **Permission:** `registers.manage`
- **Middleware:** `register.access`, `business.owner`

**Response `200 OK`**
```json
{ "message": "Register deleted successfully." }
```

---

## 12. POS Devices

### Machine Authentication (No JWT Required)

### `POST /api/v1/pos-devices/auth`

Authenticate a physical POS machine. **Rate limited: 5 req/min.** Failed attempts logged as `[SECURITY_POS_AUTH_FAILED]`.

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `machine_id` | string | ✅ | Hardware ID or `device_code` |
| `machine_password` | string | ✅ | 32-char random password issued at registration |

**Response `200 OK`**
```json
{
  "message": "POS device authenticated successfully.",
  "session_token": "<64-char-token>",
  "device_session_uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "data": {
    "device_uuid": "...",
    "device_code": "POS-01",
    "device_name": "Main Counter",
    "business_uuid": "...",
    "outlet_uuid": "...",
    "register_uuid": "...",
    "device_token": "<64-char-token>"
  },
  "context": {
    "pos_device": { ...device },
    "business": { ...business },
    "outlet": { ...outlet },
    "register": { ...register }
  }
}
```

| Status | Meaning |
|:---|:---|
| `401` | Wrong `machine_id` or `machine_password` |
| `403` | Device is `revoked` or `locked` |
| `403` | Device status is not `active` |
| `429` | Too many auth attempts |

---

### POS Device Management (JWT Required)

### `GET /api/v1/outlets/{outlet}/pos-devices`

List all POS devices under an outlet.

- **Permission:** `pos_devices.view`
- **Middleware:** `outlet.access`

---

### `POST /api/v1/outlets/{outlet}/pos-devices`

Register a new POS device. Generates a `machine_password` shown **once only**.

- **Permission:** `pos_devices.create`
- **Middleware:** `outlet.access`

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `machine_id` | string | ✅ | Unique hardware identifier |
| `device_name` | string | ✅ | Display name e.g. "Counter 1" |
| `device_code` | string | — | Short code (defaults to `machine_id`) |
| `register_uuid` | uuid | — | Assign to a specific register |
| `device_type` | string | — | `pos_terminal` · `mobile_pos` etc. |
| `platform` | string | — | `android` · `ios` · `windows` |
| `os_version` | string | — | |
| `app_version` | string | — | |
| `ip_address` | string | — | |
| `mac_address` | string | — | |

**Response `201 Created`**
```json
{
  "message": "POS device registered successfully. Save the machine password now as it will not be shown again.",
  "machine_password": "<32-char-random-password>",
  "data": { ...device }
}
```

> ⚠️ **Save `machine_password` immediately.** It is hashed with bcrypt — the plaintext cannot be recovered. Use `/rotate-secret` to generate a new one if lost.

---

### `GET /api/v1/pos-devices/{posDevice}`

Show a POS device with full relations.

- **Permission:** `pos_devices.view`
- **Middleware:** `pos_device.access`

**Response `200 OK`**
```json
{
  "data": {
    ...device,
    "business": {...}, "outlet": {...},
    "register": {...}, "credentials": [...],
    "device_sessions": [...]
  }
}
```

---

### `PUT /api/v1/pos-devices/{posDevice}`

Update POS device config, outlet, or register assignment.

- **Permission:** `pos_devices.update`
- **Middleware:** `pos_device.access`

---

### `POST /api/v1/pos-devices/{posDevice}/activate`

Activate a pending or inactive POS device.

- **Permission:** `pos_devices.manage`
- **Middleware:** `pos_device.access`

**Response `200 OK`**
```json
{ "message": "POS device activated successfully.", "data": { ...device } }
```

---

### `POST /api/v1/pos-devices/{posDevice}/revoke`

Revoke a POS device — also revokes all credentials and active sessions.

- **Permission:** `pos_devices.manage`

**Response `200 OK`**
```json
{ "message": "POS device revoked successfully.", "data": { ...device } }
```

---

### `POST /api/v1/pos-devices/{posDevice}/lock`

Temporarily lock a POS device (blocks machine auth).

- **Permission:** `pos_devices.manage`

**Response `200 OK`**
```json
{ "message": "POS device locked successfully.", "data": { ...device } }
```

---

### `POST /api/v1/pos-devices/{posDevice}/rotate-secret`

Generate a new `machine_password`. Revokes all previous credentials. **Business owners or platform admins only.**

- **Permission:** `pos_devices.manage`

**Response `200 OK`**
```json
{
  "message": "POS device credentials rotated successfully. Save the new machine password now as it will not be shown again.",
  "machine_password": "<new-32-char-password>",
  "data": { ...device }
}
```

---

## 13. Device Sessions

A device session is created each time a POS device authenticates. Sessions expire after **24 hours**.

### `GET /api/v1/pos-devices/{posDevice}/sessions`

List all sessions for a POS device.

- **Permission:** `pos_devices.view`
- **Middleware:** `pos_device.access`

**Response `200 OK`**
```json
{ "data": [ { ...deviceSession } ] }
```

### Device Session Shape

```json
{
  "id": 1,
  "uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "pos_device_id": 1,
  "token_hash": "sha256-hashed-session-token",
  "ip_address": "172.22.0.1",
  "user_agent": "SmartPOS/2.0 Android",
  "started_at": "2026-08-21T07:00:00.000000Z",
  "last_activity_at": "2026-08-21T08:30:00.000000Z",
  "expires_at": "2026-08-22T07:00:00.000000Z",
  "revoked_at": null
}
```

---

### `POST /api/v1/pos-devices/{posDevice}/sessions/{deviceSession}/revoke`

Revoke a specific active device session (force sign-out of a terminal).

- **Permission:** `pos_devices.manage`
- **Middleware:** `pos_device.access`

**Response `200 OK`**
```json
{ "message": "Device session revoked successfully." }
```

---

## 14. Cashier Sessions

Tracks which staff member is logged into which POS terminal at any moment.

### `POST /api/v1/outlets/{outlet}/cashier-sessions/start`

Start a new cashier session. Any existing active/locked session on the same POS device is automatically ended.

- **Permission:** `pos_devices.use`
- **Middleware:** `outlet.access`

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `register_uuid` | uuid | ✅ | Must belong to this outlet |
| `pos_device_uuid` | uuid | ✅ | Must belong to this outlet |
| `user_uuid` | uuid | ✅ | UUID of the cashier |

**Response `201 Created`**
```json
{
  "message": "Cashier session started successfully.",
  "data": {
    ...cashierSession,
    "business_user": { ...businessUser, "cashier_profile": {...} },
    "register": { ...register },
    "pos_device": { ...device }
  }
}
```

---

### `GET /api/v1/outlets/{outlet}/cashier-sessions/current`

Get the current active or locked session for a register or device.

- **Permission:** `pos_devices.use`
- **Middleware:** `outlet.access`

**Query Parameters**

| Param | Type | Description |
|:---|:---|:---|
| `register_uuid` | uuid | Filter by register |
| `pos_device_uuid` | uuid | Filter by POS device |

**Response `200 OK`**
```json
{ "data": { ...cashierSession } }
```
No active session:
```json
{ "message": "No active cashier session found.", "data": null }
```

---

### `POST /api/v1/outlets/{outlet}/cashier-sessions/{cashierSession}/lock`

Lock an active cashier session (screen lock).

- **Permission:** `pos_devices.use`
- **Middleware:** `outlet.access`

**Response `200 OK`**
```json
{ "message": "Cashier session locked successfully.", "data": { ...cashierSession } }
```

---

### `POST /api/v1/outlets/{outlet}/cashier-sessions/{cashierSession}/unlock`

Unlock a locked cashier session. Verifies PIN code if the cashier has one set.

> **SEC-02 Protection:** If the cashier has no PIN code configured, unlock attempts by standard users are blocked with **HTTP 403**. Platform administrators (`roles: ["admin"]`) may override and unlock PIN-less sessions.

- **Permission:** `pos_devices.use`
- **Middleware:** `outlet.access`, `throttle:cashier_pin`

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `pin_code` | string | Conditional | Required for standard unlock |

**Responses**

| Status | Body |
|:---|:---|
| `200 OK` | `{ "message": "Cashier session unlocked successfully.", "data": {...} }` |
| `401` | `{ "message": "Invalid cashier PIN code." }` |
| `403` | `{ "message": "This cashier session cannot be unlocked: the cashier has no PIN code set..." }` |
| `422` | `{ "message": "Session is not locked. Current status: active" }` |

---

### `POST /api/v1/outlets/{outlet}/cashier-sessions/{cashierSession}/end`

End a cashier session.

- **Permission:** `pos_devices.use`
- **Middleware:** `outlet.access`

**Response `200 OK`**
```json
{ "message": "Cashier session ended successfully.", "data": { ...cashierSession } }
```

---

## 15. Register Shifts & Cash Drawers

### Register Shifts

### `GET /api/v1/outlets/{outlet}/registers/{register}/shifts`

List all shift sessions for a register.

- **Permission:** `registers.view` | **Middleware:** `outlet.access`, `register.access`

**Response `200 OK`**
```json
{ "data": [ { ...registerSession } ] }
```

---

### `GET /api/v1/outlets/{outlet}/registers/{register}/shifts/current`

Get the currently active shift session.

- **Permission:** `registers.view`

**Response `200 OK`**
```json
{ "data": { ...registerSession } }
```

---

### `POST /api/v1/outlets/{outlet}/registers/{register}/shifts/open`

Open a new shift (start of day). Records opening cash balance.

- **Permission:** `registers.manage` | **Middleware:** `outlet.access`, `register.access`

---

### `POST /api/v1/outlets/{outlet}/registers/{register}/shifts/{registerSession}/close`

Close a shift (end of day). Records closing balance.

- **Permission:** `registers.manage`

---

### Cash Drawers

### `GET /api/v1/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}`

Show a cash drawer session summary with the current computed balance.

- **Permission:** `registers.view`

**Response `200 OK`**
```json
{
  "data": { ...cashDrawerSession, "register_session": {...}, "movements": [...] },
  "current_balance": 250.00
}
```

---

### `GET /api/v1/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}/movements`

List all cash movements in a drawer session (newest first).

- **Permission:** `registers.view`

**Response `200 OK`**
```json
{ "data": [ { ...movement } ] }
```

---

### `POST /api/v1/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}/movements`

Record a cash movement. Uses a DB transaction with row-level locking (`FOR UPDATE`) to prevent race conditions.

- **Permission:** `registers.manage`

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `type` | string | ✅ | Movement type (see table below) |
| `amount` | number | ✅ | Absolute positive amount |
| `reference_type` | string | — | e.g. `order`, `refund` |
| `reference_uuid` | uuid | — | UUID of referenced document |
| `reason` | string | — | Short reason label |
| `notes` | string | — | Free text notes |

**Movement Types**

| Type | Direction | Description |
|:---|:---|:---|
| `cash_in` | ➕ Inbound | Cash added to drawer |
| `cash_out` | ➖ Outbound | Cash removed from drawer |
| `payout` | ➖ Outbound | Expense payout |
| `deposit` | ➕ Inbound | Manual deposit |
| `adjustment` | ➕ Inbound | Balance correction |
| `sale` | ➕ Inbound | Cash collected from sale |
| `cash_refund` | ➖ Outbound | Cash refund to customer |
| `closing` | — | Closing snapshot (excluded from balance) |

> Outbound types are automatically stored as **negative amounts** internally.

**Response `201 Created`**
```json
{
  "message": "Cash movement recorded successfully.",
  "data": { ...movement },
  "current_balance": 275.50
}
```

---

## 16. Warehouses

A warehouse belongs to a business. It may be linked to a specific outlet or serve as a **central warehouse** (`outlet_id = null`).

### Resource Shape

```json
{
  "id": 1,
  "uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "business_id": 1,
  "outlet_id": null,
  "code": "WH-CENTRAL",
  "name": "Central Warehouse",
  "address": "456 Industrial Park, Shah Alam",
  "status": "active",
  "locations_count": 12,
  "outlet": null,
  "created_at": "2026-08-21T07:00:00.000000Z",
  "updated_at": "2026-08-21T07:00:00.000000Z"
}
```

---

### `GET /api/v1/businesses/{business}/warehouses`

List all warehouses for a business with location counts.

- **Permission:** `warehouses.view`
- **Middleware:** `business.member`

**Query Parameters**

| Param | Type | Description |
|:---|:---|:---|
| `status` | string | Filter: `active` or `inactive` |
| `outlet_id` | int\|`null` | Filter by outlet; `null` returns central warehouses only |

**Response `200 OK`**
```json
{ "data": [ { ...warehouse } ] }
```

---

### `POST /api/v1/businesses/{business}/warehouses`

Create a new warehouse.

- **Permission:** `warehouses.create`
- **Middleware:** `business.member`

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `code` | string | ✅ | Unique within business (max 50) |
| `name` | string | ✅ | Warehouse display name (max 150) |
| `outlet_uuid` | uuid | — | Link to outlet (auto-resolved); omit for central warehouse |
| `address` | string | — | Physical address |
| `status` | string | — | `active` · `inactive` (default: `active`) |

**Response `201 Created`**
```json
{
  "message": "Warehouse created successfully.",
  "data": { ...warehouse, "outlet": {...}, "locations": [] }
}
```

---

### `GET /api/v1/warehouses/{warehouse}`

Show a warehouse with its business, outlet, and locations.

- **Permission:** `warehouses.view`
- **Middleware:** `warehouse.access`

**Response `200 OK`**
```json
{ "data": { ...warehouse, "business": {...}, "outlet": {...}, "locations": [...] } }
```

---

### `PUT /api/v1/warehouses/{warehouse}`

Update warehouse details. All fields optional (same as POST).

- **Permission:** `warehouses.update`
- **Middleware:** `warehouse.access`

**Response `200 OK`**
```json
{ "message": "Warehouse updated successfully.", "data": { ...warehouse } }
```

---

### `DELETE /api/v1/warehouses/{warehouse}`

Delete a warehouse.

- **Permission:** `warehouses.delete`
- **Middleware:** `warehouse.access`, `business.owner`

**Response `200 OK`**
```json
{ "message": "Warehouse deleted successfully." }
```

---

## 17. Warehouse Locations

A location is a specific storage slot inside a warehouse, addressed by zone / aisle / rack / shelf / bin.

### Resource Shape

```json
{
  "id": 1,
  "uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
  "warehouse_id": 1,
  "code": "A3-R2-S1-B4",
  "zone": "A",
  "aisle": "3",
  "rack": "2",
  "shelf": "1",
  "bin": "4",
  "description": "Dry goods area",
  "status": "active",
  "created_at": "2026-08-21T07:00:00.000000Z",
  "updated_at": "2026-08-21T07:00:00.000000Z"
}
```

---

### `GET /api/v1/warehouses/{warehouse}/locations`

List all locations inside a warehouse.

- **Permission:** `warehouses.view`
- **Middleware:** `warehouse.access`

**Response `200 OK`**
```json
{ "data": [ { ...warehouseLocation } ] }
```

---

### `POST /api/v1/warehouses/{warehouse}/locations`

Create a new location inside a warehouse.

- **Permission:** `warehouses.create`
- **Middleware:** `warehouse.access`

**Request Body**

| Field | Type | Required | Description |
|:---|:---|:---|:---|
| `code` | string | ✅ | Unique within warehouse (max 50) |
| `zone` | string | — | Zone identifier e.g. `A`, `B` (max 50) |
| `aisle` | string | — | Aisle number or label (max 50) |
| `rack` | string | — | Rack identifier (max 50) |
| `shelf` | string | — | Shelf identifier (max 50) |
| `bin` | string | — | Bin identifier (max 50) |
| `description` | string | — | Human-readable description (max 255) |
| `status` | string | — | `active` · `inactive` (default: `active`) |

**Response `201 Created`**
```json
{
  "message": "Warehouse location created successfully.",
  "data": { ...warehouseLocation }
}
```

---

### `GET /api/v1/warehouse-locations/{warehouseLocation}`

Show a specific warehouse location.

- **Permission:** `warehouses.view`
- **Middleware:** `warehouse_location.access`

**Response `200 OK`**
```json
{ "data": { ...warehouseLocation, "warehouse": {...} } }
```

---

### `PUT /api/v1/warehouse-locations/{warehouseLocation}`

Update a warehouse location. All fields optional.

- **Permission:** `warehouses.update`
- **Middleware:** `warehouse_location.access`

**Response `200 OK`**
```json
{ "message": "Warehouse location updated successfully.", "data": { ...warehouseLocation } }
```

---

### `DELETE /api/v1/warehouse-locations/{warehouseLocation}`

Delete a warehouse location.

- **Permission:** `warehouses.delete`
- **Middleware:** `warehouse_location.access`, `business.owner`

**Response `200 OK`**
```json
{ "message": "Warehouse location deleted successfully." }
```

---

## 18. Permissions Reference

Permission strings are passed in the JWT `permissions` claim array.

| Permission | Grants Access To |
|:---|:---|
| `businesses.view` | List & view businesses |
| `businesses.create` | Create businesses |
| `businesses.update` | Update business details & settings |
| `businesses.delete` | Delete businesses |
| `business_users.view` | View staff & cashier profiles |
| `business_users.manage` | Add / update / suspend / remove staff |
| `outlets.view` | View outlets |
| `outlets.create` | Create outlets |
| `outlets.update` | Update outlets |
| `outlets.delete` | Delete outlets |
| `registers.view` | View registers & shifts |
| `registers.create` | Create registers |
| `registers.update` | Update registers |
| `registers.manage` | Open/close shifts, record cash movements, delete registers |
| `pos_devices.view` | View POS devices & sessions |
| `pos_devices.create` | Register new POS devices |
| `pos_devices.update` | Update POS device configuration |
| `pos_devices.manage` | Activate / revoke / lock / rotate credentials |
| `pos_devices.use` | Start / end / lock / unlock cashier sessions |
| `warehouses.view` | View warehouses & locations |
| `warehouses.create` | Create warehouses & locations |
| `warehouses.update` | Update warehouses & locations |
| `warehouses.delete` | Delete warehouses & locations |

---

## 19. Security Architecture

### Global Middleware Stack

| Middleware | Purpose |
|:---|:---|
| `AttackShieldMiddleware` | Blocks scanner User-Agents (`sqlmap`, `nikto`, `acunetix`, `dirbuster`, `gobuster`, `nmap` …), recon probes (`/.env`, `/.git`, `/phpmyadmin`, `/backup.sql` …), and path traversal (`../`). All blocks are logged with `[SECURITY_ATTACK_SHIELD]`. |
| `SanitizeInputMiddleware` | Strips null bytes (`\0`) from all inputs. Enforces **2 MB** max request body (HTTP 413). |
| `SecurityHeadersMiddleware` | Injects: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy`, `HSTS`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Resource-Policy`. Removes `X-Powered-By`. |

### Authentication & Authorization Middleware

| Middleware Alias | Validates |
|:---|:---|
| `jwt.auth` | JWT signature (HS256), expiry, format, issuer, audience |
| `permission:{perm}` | JWT `permissions` array includes the required permission |
| `business.member` | User is an active `BusinessUser` of the business in the route |
| `business.owner` | User's `BusinessUser` has `is_owner = true` |
| `outlet.access` | Outlet belongs to the user's business |
| `register.access` | Register belongs to the outlet / user's business |
| `pos_device.access` | POS device belongs to the user's business |
| `warehouse.access` | Warehouse belongs to the user's business |
| `warehouse_location.access` | Location's warehouse belongs to the user's business |

### Security Event Log Tags

| Tag | Trigger |
|:---|:---|
| `[SECURITY_ATTACK_SHIELD]` | Scanner User-Agent, recon probe, or path traversal blocked |
| `[SECURITY_POS_AUTH_FAILED]` | Wrong `machine_id` or `machine_password` |
| `[SECURITY_POS_AUTH_BLOCKED]` | Revoked or locked device attempting machine auth |
| `[SECURITY_CASHIER_UNLOCK_BLOCKED]` | Unlock attempted on PIN-less cashier session (SEC-02) |
| `[SECURITY_CASHIER_PIN_FAILED]` | Invalid cashier PIN unlock attempt (SEC-02) |

```bash
# Monitor live security events
docker compose exec business-service tail -f storage/logs/laravel.log | grep SECURITY
```

---

*Generated from source code review — SmartPOS Business Service v1.0.0*
*Last updated: 2026-08-21*
