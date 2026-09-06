# SmartPOS Business / POS Service — Complete Project Report

**Service**: `smartpos-business-service`  
**Port**: `:8002` (FastCGI routing via API Gateway `:9000`)  
**Runtime**: PHP 8.4-FPM with OPcache JIT (Tracing Mode)  
**Framework**: Laravel 13  
**Database**: MySQL 8.4 (`smartpos_business_db`) & Redis 8  
**Verification**: ✅ **202 Tests Passed (100%) + 32/32 Pentest Passed**  
**Interactive API Docs**: 🔗 [http://api.smartpos.test/docs/business](http://api.smartpos.test/docs/business)  
**Updated**: September 5, 2026

---

## 1. Project Overview & Scope

The **SmartPOS Business Service** provides multi-tenant store management, hardware authorization, and cashier/shift workflows for the SmartPOS platform.

### Core Capabilities:
1. **Business Management**: Multi-tenant company configuration, currency, tax rates, auto-lock timeouts.
2. **Staff & Outlets**: Multi-store assignment, cashier profile permissions (`can_sell`, `can_refund`, `can_void`, `can_discount`).
3. **Hardware Terminals**: Machine credential generation, rotation, authentication, and session auditing (`device_sessions`).
4. **POS Cashier Sessions**: Cashier PIN sessions, auto-lock, unlock, cashier switching, and logout.
5. **Register Shifts**: Opening float, current balance, and closing reconciliation.
6. **Cash Drawer Accounting**: Real-time cash tracking and movement logging (`cash_in`, `cash_out`, `payout`, `deposit`, `adjustment`, `cash_sale`, `cash_refund`).

---

## 2. 14 Core Tables & Model Architecture

```text
businesses
├── business_settings
├── business_users
│   ├── cashier_profiles
│   └── business_user_outlets ───┐
│                                 v
└── outlets ──────────────────────┘
    └── registers
        ├── pos_devices
        │   ├── pos_device_credentials
        │   └── device_sessions
        ├── cashier_sessions
        └── register_sessions
            └── cash_drawer_sessions
                └── cash_drawer_movements
```

---

## 3. Master Endpoint Index

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/business/health` | Service health check |
| `POST` | `/api/v1/pos-devices/auth` | Machine authentication (credentials & session token) |
| `GET` / `POST` | `/api/v1/businesses` | List / Create businesses |
| `GET` / `PUT` / `DELETE` | `/api/v1/businesses/{business}` | Business profile operations |
| `GET` / `PUT` | `/api/v1/businesses/{business}/settings` | Business-level POS settings |
| `GET` / `POST` | `/api/v1/businesses/{business}/users` | Staff membership |
| `PUT` / `DELETE` | `/api/v1/businesses/{business}/users/{businessUser}` | Update / Remove staff |
| `GET` / `PUT` | `/api/v1/businesses/{business}/users/{businessUser}/cashier-profile` | Cashier permissions |
| `GET` / `POST` / `DELETE` | `/api/v1/businesses/{business}/users/{businessUser}/outlets` | Staff outlet assignments |
| `GET` / `POST` | `/api/v1/businesses/{business}/outlets` | List / Create outlets |
| `GET` / `PUT` / `DELETE` | `/api/v1/outlets/{outlet}` | Outlet management |
| `GET` / `POST` | `/api/v1/outlets/{outlet}/registers` | List / Create registers |
| `GET` / `PUT` / `DELETE` | `/api/v1/registers/{register}` | Register management |
| `GET` / `POST` | `/api/v1/outlets/{outlet}/pos-devices` | List / Register POS machines |
| `GET` / `PUT` | `/api/v1/pos-devices/{posDevice}` | POS hardware configuration |
| `POST` | `/api/v1/pos-devices/{posDevice}/activate` | Activate terminal |
| `POST` | `/api/v1/pos-devices/{posDevice}/revoke` | Revoke terminal |
| `POST` | `/api/v1/pos-devices/{posDevice}/lock` | Lock terminal |
| `POST` | `/api/v1/pos-devices/{posDevice}/rotate-secret` | Rotate machine secret |
| `GET` | `/api/v1/pos-devices/{posDevice}/sessions` | Machine session logs |
| `POST` | `/api/v1/pos-devices/{posDevice}/sessions/{deviceSession}/revoke` | Revoke machine session |
| `POST` | `/api/v1/outlets/{outlet}/cashier-sessions/start` | Start cashier session |
| `GET` | `/api/v1/outlets/{outlet}/cashier-sessions/current` | Active cashier session |
| `POST` | `/api/v1/outlets/{outlet}/cashier-sessions/{cashierSession}/lock` | Lock cashier session |
| `POST` | `/api/v1/outlets/{outlet}/cashier-sessions/{cashierSession}/unlock` | Unlock cashier session |
| `POST` | `/api/v1/outlets/{outlet}/cashier-sessions/{cashierSession}/end` | End cashier session |
| `GET` | `/api/v1/outlets/{outlet}/registers/{register}/shifts` | Shift history |
| `GET` | `/api/v1/outlets/{outlet}/registers/{register}/shifts/current` | Current open shift |
| `POST` | `/api/v1/outlets/{outlet}/registers/{register}/shifts/open` | Open shift with float |
| `POST` | `/api/v1/outlets/{outlet}/registers/{register}/shifts/{registerSession}/close` | Close shift & reconcile |
| `GET` | `/api/v1/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}` | Drawer balance |
| `GET` | `/api/v1/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}/movements` | List cash movements |
| `POST` | `/api/v1/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}/movements` | Log cash movement |

---

## 4. Security & Hardening Architecture

1. **JWT Authentication & Permission Control**: HMAC-SHA256 token verification with fine-grained permission enforcement (`permissions.view`, `registers.manage`, `pos_devices.use`).
2. **Multi-Tenant Isolation (BOLA Defense)**: Strict tenancy middleware (`EnsureBusinessMember`, `EnsureOutletAccess`, `EnsureRegisterAccess`, `EnsurePosDeviceAccess`).
3. **Privilege Escalation Protection (BFLA Defense)**: `EnsureBusinessOwner` prevents non-owner staff from modifying business settings, cashier permissions, or rotating hardware credentials.
4. **Active Cashier Session Guard**: `EnsureCashierSessionActive` (`cashier_session.active`) rejects operations on locked or ended cashier sessions.
5. **AttackShield & Input Sanitization**: Defends against SQLi, XSS, and parameter pollution.
6. **Data Masking**: `secret_hash`, `token_hash`, `pin_code_hash`, and `machine_password_hash` are hidden from all API outputs.

---

## 5. Verification & Test Metrics

- **Test Framework**: PHPUnit via `php artisan test`
- **Total Tests**: **202 tests** (0 failed, 100% pass rate)
- **Total Assertions**: **937 assertions**
- **Test Categories**: Unit, Feature, Validation, Multi-Tenant BOLA, SQLi Pentest, Privilege Escalation, POS Hardware Lifecycle, Warehouse & Storage Locations, and RS256 Asymmetric JWT Verification.

---

## 6. Interactive API Documentation

You can open and test the interactive OpenAPI documentation for Business Service directly:

- **Local Domain:** 🔗 [http://api.smartpos.test/docs/business](http://api.smartpos.test/docs/business)
- **API Gateway (Port 8000):** 🔗 [http://localhost:8000/docs/business](http://localhost:8000/docs/business)
- **Direct Service Port:** 🔗 [http://localhost:8002/docs/business](http://localhost:8002/docs/business)
- **OpenAPI Schema (JSON):** 🔗 [http://api.smartpos.test/docs/business.json](http://api.smartpos.test/docs/business.json)

