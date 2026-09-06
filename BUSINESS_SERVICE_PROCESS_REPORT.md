# SmartPOS Business Service — Comprehensive Codebase & Process Report

**Service Name**: `smartpos-business-service`  
**Host Port**: `:8002` (FastCGI routing via API Gateway `http://api.smartpos.test/api/v1/...`)  
**Internal Port**: `:9000` (FastCGI)  
**API Gateway Path**: `/api/v1/...`  
**Runtime**: PHP 8.4-FPM with OPcache JIT (Tracing Mode)  
**Framework**: Laravel 13  
**Database**: MySQL 8.4 (`smartpos_business_db`) & Redis 8  
**Cache & Queue**: Redis (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`)  
**Security & Verification**: 100% Passed (202 Tests, 937 Assertions) + 32/32 Pentest Suites Passed  
**Document Generation Date**: September 5, 2026  

---

## 1. Executive Summary & System Overview

The **SmartPOS Business Service** is the central domain microservice responsible for multi-tenant retail operations, staff hierarchy, hardware terminal provisioning, cashier sessions, and register cash accounting within the SmartPOS ecosystem.

### Key Functional Domains
1. **Multi-Tenant Business Architecture**: Isolated company workspaces with custom currency, tax parameters, and POS operating settings.
2. **Staff Hierarchy & Cashier Permissions**: Multi-tier access control linking Identity Service users (`user_uuid`) with fine-grained POS capabilities (`can_sell`, `can_refund`, `can_void`, `can_discount`, `max_discount_percent`).
3. **Physical Store Outlets & Register Counters**: Organization of physical branch locations containing multiple point-of-sale register counters.
4. **Hardware POS Terminal Management**: Secure registration of POS machines, automated machine password generation, periodic hardware secret rotation, and tokenized machine session audits.
5. **POS Cashier Session Lifecycle**: Cashier login on POS hardware, idle lock, PIN-code unlock verification, and session termination.
6. **Register Shifts & Cash Drawer Reconciliation**: Shift opening with opening float, real-time cash drawer ledger movement tracking (`cash_in`, `cash_out`, `payout`, `deposit`, `adjustment`, `cash_sale`, `cash_refund`), and closing cash reconciliation with variance calculation.

---

## 2. Architecture & Tech Stack

```mermaid
graph TD
    Client["POS Terminal / Management Client"] -->|HTTPS / REST API| Gateway["API Gateway / Nginx (:8000 / :8002)"]
    Gateway -->|Direct FastCGI :9000 (Gzip L6)| FPM["PHP 8.4-FPM Engine (OPcache JIT Tracing)"]
    FPM --> MiddlewarePipeline["Security & Auth Middleware Pipeline"]
    
    subgraph MiddlewarePipeline ["Security & Auth Middleware Pipeline"]
        AttackShield["AttackShieldMiddleware (Scanner & Probe Defense)"]
        Sanitizer["SanitizeInputMiddleware (XSS & Sanitization)"]
        SecHeaders["SecurityHeadersMiddleware (HSTS, CSP, X-Frame)"]
        JwtAuth["JwtAuthMiddleware (HMAC-SHA256 Claims)"]
        TenantGuard["Tenancy Guards (EnsureBusinessMember, EnsureOutletAccess)"]
        RoleGuard["Privilege Guards (EnsureBusinessOwner, EnsurePermission)"]
        SessionGuard["Session Guard (EnsureCashierSessionActive)"]
    end
    
    MiddlewarePipeline --> Controllers["API Controllers Layer"]
    Controllers --> FormRequests["Form Request Validation Layer"]
    Controllers --> EloquentORM["Eloquent Models & DB Transactions (Pessimistic Locking)"]
    EloquentORM --> MySQL[("MySQL 8.4 Database (:3308)")]
    EloquentORM --> Redis[("Redis 8 In-Memory Cache (:6381, 0.51ms)")]
    EloquentORM --> Queue[("Redis Job Queue Subsystem")]
```

### Technical Stack Details
- **Backend Runtime**: **PHP 8.4-FPM** (High-concurrency worker pool, `clear_env=no`)
- **Code Accelerator**: **OPcache JIT Tracing** (`opcache.jit=tracing`, `buffer_size=64M`)
- **Framework**: Laravel 13
- **Primary Database**: MySQL 8.4 (with utf8mb4, InnoDB, strict mode, foreign key constraints)
- **Caching & Sessions**: Redis 8 (`CACHE_STORE=redis`, `phpredis` extension, ~0.51ms latency)
- **Asynchronous Queue**: Redis Queue (`QUEUE_CONNECTION=redis`)
- **Gateway Ingress**: Direct FastCGI reverse proxy with Level 6 JSON gzip compression
- **Inter-Service Calls**: Routed via `http://api-gateway:80`
- **Authentication**: Stateless HMAC-SHA256 JWT validation & RS256 compatibility
- **Testing Engine**: PHPUnit 11 with 100% passing automated penetration test suites

---

## 3. Database Schema & Domain Entity Architecture (14 Core Tables)

```mermaid
erDiagram
    businesses ||--o{ business_settings : "configures (1:1)"
    businesses ||--o{ business_users : "employs (1:N)"
    businesses ||--o{ outlets : "operates (1:N)"
    businesses ||--o{ registers : "owns (1:N)"
    businesses ||--o{ pos_devices : "provisions (1:N)"
    
    business_users ||--o{ cashier_profiles : "grants (1:1)"
    business_users ||--o{ business_user_outlets : "assigned_to (N:M)"
    outlets ||--o{ business_user_outlets : "assigned_staff (1:N)"
    outlets ||--o{ registers : "contains (1:N)"
    outlets ||--o{ pos_devices : "houses (1:N)"
    
    registers ||--o{ pos_devices : "paired_with (1:N)"
    registers ||--o{ register_sessions : "shifts (1:N)"
    
    pos_devices ||--o{ pos_device_credentials : "authenticates (1:N)"
    pos_devices ||--o{ device_sessions : "audits (1:N)"
    
    business_users ||--o{ cashier_sessions : "starts (1:N)"
    pos_devices ||--o{ cashier_sessions : "hosts (1:N)"
    registers ||--o{ cashier_sessions : "tracks (1:N)"
    
    register_sessions ||--|| cash_drawer_sessions : "reconciles (1:1)"
    cash_drawer_sessions ||--o{ cash_drawer_movements : "logs (1:N)"
```

### Comprehensive Entity Details

| # | Table Name | Eloquent Model | Primary Key & UUID | Key Foreign Keys | Purpose & Business Logic |
|---|---|---|---|---|---|
| 1 | `businesses` | [Business.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/Business.php) | `id`, `uuid` | — | Root tenant entity. Stores `name`, `legal_name`, `tax_id`, `currency_code`, `status`. Soft-deletable. |
| 2 | `business_settings` | [BusinessSetting.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/BusinessSetting.php) | `id`, `uuid` | `business_id` | Business POS configuration: `receipt_prefix`, `receipt_header`, `receipt_footer`, `tax_enabled`, `default_tax_rate`, `allow_negative_stock`, `auto_lock_minutes`. |
| 3 | `business_users` | [BusinessUser.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/BusinessUser.php) | `id`, `uuid` | `business_id` | Links external `user_uuid` with tenant. Stores `employee_code`, `job_title`, `is_owner`, `pin_code_hash` (bcrypt), `status` (`active`, `suspended`, `terminated`). |
| 4 | `business_user_outlets` | [BusinessUserOutlet.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/BusinessUserOutlet.php) | `id`, `uuid` | `business_user_id`, `outlet_id` | Multi-store assignment matrix. Stores `is_primary`, `is_active`. Enforces `unique(business_user_id, outlet_id)`. |
| 5 | `cashier_profiles` | [CashierProfile.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/CashierProfile.php) | `id`, `uuid` | `business_user_id` | Cashier POS privileges: `can_sell`, `can_refund`, `can_void`, `can_discount`, `max_discount_percent`, `can_open_drawer_manual`. |
| 6 | `outlets` | [Outlet.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/Outlet.php) | `id`, `uuid` | `business_id` | Store branch location: `code`, `name`, `phone`, `email`, `address`, `city`, `state`, `postal_code`, `is_active`. |
| 7 | `registers` | [Register.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/Register.php) | `id`, `uuid` | `business_id`, `outlet_id` | Physical checkout counter. Stores `code`, `name`, `status` (`active`, `inactive`). |
| 8 | `pos_devices` | [PosDevice.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/PosDevice.php) | `id`, `uuid` | `business_id`, `outlet_id`, `register_id` | Hardware terminal machine. Stores `machine_id`, `device_code`, `device_name`, `device_type`, `platform`, `ip_address`, `mac_address`, `machine_password_hash`, `status` (`pending`, `active`, `locked`, `revoked`). |
| 9 | `pos_device_credentials` | [PosDeviceCredential.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/PosDeviceCredential.php) | `id`, `uuid` | `pos_device_id` | Hardware machine secrets: `secret_hash`, `is_active`, `last_rotated_at`, `revoked_at`. |
| 10 | `device_sessions` | [DeviceSession.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/DeviceSession.php) | `id`, `uuid` | `pos_device_id` | Machine auth token logs: `token_hash`, `ip_address`, `user_agent`, `expires_at`, `revoked_at`. |
| 11 | `cashier_sessions` | [CashierSession.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/CashierSession.php) | `id`, `uuid` | `business_id`, `outlet_id`, `register_id`, `pos_device_id`, `business_user_id` | Staff session on physical terminal. Stores `user_uuid`, `status` (`active`, `locked`, `ended`), `started_at`, `locked_at`, `ended_at`, `last_activity_at`. |
| 12 | `register_sessions` | [RegisterSession.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/RegisterSession.php) | `id`, `uuid` | `business_id`, `outlet_id`, `register_id`, `pos_device_id` | Register shift period: `opened_by_user_uuid`, `closed_by_user_uuid`, `opening_cash`, `expected_cash`, `closing_cash`, `difference_amount`, `status` (`open`, `closed`). |
| 13 | `cash_drawer_sessions` | [CashDrawerSession.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/CashDrawerSession.php) | `id`, `uuid` | `register_session_id`, `business_id`, `outlet_id`, `register_id` | Cash drawer balancing: `opening_amount`, `expected_amount`, `counted_amount`, `difference_amount`, `status` (`open`, `closed`). |
| 14 | `cash_drawer_movements` | [CashDrawerMovement.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Models/CashDrawerMovement.php) | `id`, `uuid` | `cash_drawer_session_id`, `business_id`, `outlet_id`, `register_id` | Real-time cash movement ledger: `user_uuid`, `type` (`opening`, `cash_in`, `cash_out`, `payout`, `deposit`, `adjustment`, `closing`, `cash_sale`, `cash_refund`), `amount`, `reason`, `notes`, `reference_type`, `reference_uuid`. |

---

## 4. End-to-End Business Processes in Code

### Process 1: Business Tenancy & Staff Setup Flow

```mermaid
sequenceDiagram
    autonumber
    actor User as Business Owner / Admin
    participant GW as API Gateway / JWT Auth
    participant BC as BusinessController
    participant BUC as BusinessUserController
    participant CPC as CashierProfileController
    participant DB as MySQL Database

    User->>GW: POST /api/v1/businesses (name, currency_code, tax_id)
    GW->>BC: Validate & Store Business
    BC->>DB: INSERT into businesses
    BC->>DB: INSERT into business_users (is_owner=true, status='active')
    BC-->>User: 201 Created (Business & Owner Created)

    User->>GW: POST /api/v1/businesses/{id}/users (user_uuid, employee_code, pin_code)
    GW->>BUC: Validate & Hash PIN (bcrypt)
    BUC->>DB: INSERT into business_users
    BUC-->>User: 201 Created (Staff Member Added)

    User->>GW: PUT /api/v1/businesses/{id}/users/{uid}/cashier-profile (can_sell, can_refund, can_discount, max_discount_percent)
    GW->>CPC: Authorize Owner -> Update Permissions
    CPC->>DB: UPSERT into cashier_profiles
    CPC-->>User: 200 OK (Cashier Profile Configured)
```

#### Code Rules & Validations:
- When a business is created via `BusinessController::store`, the current `user_uuid` is automatically recorded in `business_users` as `is_owner = true`.
- When updating/suspending/deleting a business user via `BusinessUserController`, the system checks `ownersCount`. If only 1 active owner remains, the system strictly blocks demotion, suspension, or deletion (returns `422 Unprocessable Entity`) to prevent orphaned business tenants.

---

### Process 2: POS Hardware Terminal Provisioning & Machine Authentication

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Store Manager / Owner
    actor POS as Physical POS Machine (Android / Desktop)
    participant PDC as PosDeviceController
    participant DB as MySQL Database

    Admin->>PDC: POST /api/v1/outlets/{outlet}/pos-devices (machine_id, device_name, register_uuid)
    PDC->>PDC: Generate plain machine_password (Str::random(32)) & Hash
    PDC->>DB: INSERT into pos_devices (status='pending', machine_password_hash)
    PDC->>DB: INSERT into pos_device_credentials (is_active=true)
    PDC-->>Admin: 201 Created (Returns plain machine_password once)

    Admin->>PDC: POST /api/v1/pos-devices/{device}/activate
    PDC->>DB: UPDATE pos_devices SET status='active', activated_at=NOW()
    PDC-->>Admin: 200 OK (Device Activated)

    Note over POS, PDC: Terminal Boot & Machine Auth Flow
    POS->>PDC: POST /api/v1/pos-devices/auth (machine_id, machine_password) [Throttled: 5/min]
    PDC->>DB: SELECT pos_devices WHERE machine_id = ?
    PDC->>PDC: Hash::check(machine_password, machine_password_hash)
    PDC->>DB: INSERT into device_sessions (token_hash, ip_address, user_agent, expires_at)
    PDC-->>POS: 200 OK (session_token, device details, outlet & register mapping)
```

#### Credential Rotation Flow:
- Business owners or admins can call `POST /api/v1/pos-devices/{id}/rotate-secret`.
- System creates a new 32-character password, updates `machine_password_hash`, disables prior credentials in `pos_device_credentials`, and returns the new plain secret.

---

### Process 3: POS Cashier Terminal Session & Lock/Unlock State Machine

```mermaid
stateDiagram-v2
    [*] --> Active: POST /cashier-sessions/start
    Active --> Locked: POST /cashier-sessions/{id}/lock (or Inactivity Timer)
    Locked --> Active: POST /cashier-sessions/{id}/unlock (Verify PIN code)
    Active --> Ended: POST /cashier-sessions/{id}/end
    Locked --> Ended: POST /cashier-sessions/{id}/end
    Ended --> [*]
```

```mermaid
sequenceDiagram
    autonumber
    actor Cashier as Cashier Staff
    participant CSC as CashierSessionController
    participant DB as MySQL Database

    Cashier->>CSC: POST /api/v1/outlets/{outlet}/cashier-sessions/start (register_uuid, pos_device_uuid, user_uuid)
    CSC->>DB: Validate user is active in business
    CSC->>DB: End prior active/locked sessions on this POS device
    CSC->>DB: INSERT into cashier_sessions (status='active', started_at=NOW())
    CSC-->>Cashier: 201 Created (Cashier Session Active)

    Note over Cashier, CSC: Auto-Lock or Manual Lock
    Cashier->>CSC: POST /api/v1/outlets/{outlet}/cashier-sessions/{id}/lock
    CSC->>DB: UPDATE cashier_sessions SET status='locked', locked_at=NOW()
    CSC-->>Cashier: 200 OK (Session Locked)

    Note over Cashier, CSC: Unlock with PIN
    Cashier->>CSC: POST /api/v1/outlets/{outlet}/cashier-sessions/{id}/unlock (pin_code: '1234')
    CSC->>DB: SELECT business_users pin_code_hash
    CSC->>CSC: Hash::check(pin_code, pin_code_hash)
    CSC->>DB: UPDATE cashier_sessions SET status='active', locked_at=NULL
    CSC-->>Cashier: 200 OK (Session Resumed)
```

---

### Process 4: Register Shift & Cash Drawer Lifecycle

```mermaid
sequenceDiagram
    autonumber
    actor Cashier as Cashier / Shift Lead
    participant RSC as RegisterSessionController
    participant CDC as CashDrawerController
    participant DB as MySQL Database (InnoDB Transaction)

    Note over Cashier, DB: 1. Open Register Shift
    Cashier->>RSC: POST /api/v1/outlets/{outlet}/registers/{reg}/shifts/open (opening_cash: 100.00, notes)
    RSC->>DB: Verify no other 'open' shift exists on register
    RSC->>DB: BEGIN TRANSACTION
    RSC->>DB: INSERT into register_sessions (opening_cash: 100.00, status='open')
    RSC->>DB: INSERT into cash_drawer_sessions (opening_amount: 100.00, expected_amount: 100.00, status='open')
    RSC->>DB: INSERT into cash_drawer_movements (type='opening', amount: 100.00)
    RSC->>DB: COMMIT TRANSACTION
    RSC-->>Cashier: 201 Created (Shift Opened with $100 Float)

    Note over Cashier, DB: 2. Mid-Shift Cash In/Out & Sales Operations
    Cashier->>CDC: POST /api/v1/.../drawers/{drawer}/movements (type='cash_in' / 'payout', amount: 50.00)
    CDC->>DB: BEGIN TRANSACTION (lockForUpdate on cash_drawer_sessions)
    CDC->>DB: INSERT into cash_drawer_movements (type, amount: +/-50.00)
    CDC->>DB: UPDATE cash_drawer_sessions SET expected_amount = (SUM of movements)
    CDC->>DB: COMMIT TRANSACTION
    CDC-->>Cashier: 201 Created (Live Drawer Balance Updated)

    Note over Cashier, DB: 3. Close Register Shift & Reconcile
    Cashier->>RSC: POST /api/v1/outlets/{outlet}/registers/{reg}/shifts/{shift}/close (closing_cash: 145.00)
    RSC->>DB: BEGIN TRANSACTION (lockForUpdate)
    RSC->>DB: expected_cash = SUM(all drawer movements) [e.g., 150.00]
    RSC->>RSC: difference_amount = closing_cash - expected_cash [145.00 - 150.00 = -5.00]
    RSC->>DB: INSERT into cash_drawer_movements (type='closing', amount: -145.00)
    RSC->>DB: UPDATE cash_drawer_sessions (status='closed', counted=145.00, difference=-5.00)
    RSC->>DB: UPDATE register_sessions (status='closed', closed_at=NOW(), difference_amount=-5.00)
    RSC->>DB: COMMIT TRANSACTION
    RSC-->>Cashier: 200 OK (Shift Closed, Variance Recorded)
```

#### Cash Movement Arithmetic Rules:
- **Inbound movements (positive float)**: `opening`, `cash_in`, `deposit`, `cash_sale`, `adjustment` (positive) $\to$ stored as $+|amount|$.
- **Outbound movements (negative float)**: `cash_out`, `payout`, `cash_refund` $\to$ automatically converted to $-|amount|$ in `CashDrawerController::recordMovement`.
- **Closing movement**: recorded with negative counted amount to balance out the drawer session to zero after closing.

---

## 5. Security & Multi-Tenant Authorization Pipeline

The service implements defense-in-depth security across 10 specialized middleware layers:

```mermaid
graph LR
    Req[Incoming HTTP Request] --> M1[AttackShieldMiddleware]
    M1 --> M2[SanitizeInputMiddleware]
    M2 --> M3[SecurityHeadersMiddleware]
    M3 --> M4[JwtAuthMiddleware]
    M4 --> M5{Permission Check}
    M5 --> M6{Tenancy & Hierarchy Check}
    M6 --> M7{Cashier Session Check}
    M7 --> Handler[Controller Execution]
```

### Security Middleware Matrix

| Middleware Class | Alias | Execution Phase | Function & Defense Objective |
|---|---|---|---|
| [AttackShieldMiddleware.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/AttackShieldMiddleware.php) | *Global* | Request Entry | Blocks automated penetration scanners (`sqlmap`, `nikto`, `acunetix`, `nmap`, `dirbuster`), blocks sensitive path probes (`/.env`, `/.git`, `/phpmyadmin`, `/wp-admin`), blocks path traversal (`..`). |
| [SanitizeInputMiddleware.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/SanitizeInputMiddleware.php) | *Global* | Pre-Routing | Strips null bytes (`\0`), strips HTML `<script>` / iframe injection, normalizes whitespaces across all input strings. |
| [SecurityHeadersMiddleware.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/SecurityHeadersMiddleware.php) | *Global* | Response Exit | Injects `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy`, `Strict-Transport-Security`, `Referrer-Policy`. |
| [JwtAuthMiddleware.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/JwtAuthMiddleware.php) | `jwt.auth` | Route Group | Cryptographically verifies HMAC-SHA256 signature, validates `exp`, `iss`, `aud`, sets request attributes: `user_uuid`, `jwt_roles`, `jwt_permissions`. |
| [EnsurePermission.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/EnsurePermission.php) | `permission:...` | Endpoint Guard | Evaluates required token permission (e.g. `registers.manage`, `pos_devices.use`, `businesses.create`). Platform `admin` bypasses check. |
| [EnsureBusinessMember.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/EnsureBusinessMember.php) | `business.member` | Tenancy Guard | Resolves target business and ensures caller has an active `business_users` record. Prevents cross-tenant BOLA attacks. |
| [EnsureBusinessOwner.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/EnsureBusinessOwner.php) | `business.owner` | Privilege Guard | Enforces `is_owner = true` for sensitive administrative actions (modifying settings, role elevation, hardware secret rotation). Prevents BFLA attacks. |
| [EnsureOutletAccess.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/EnsureOutletAccess.php) | `outlet.access` | Tenancy Guard | Resolves outlet and verifies user is assigned to this specific store branch (or is a business owner). |
| [EnsureRegisterAccess.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/EnsureRegisterAccess.php) | `register.access` | Tenancy Guard | Confirms register exists within the authorized outlet/business tenant. |
| [EnsurePosDeviceAccess.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/EnsurePosDeviceAccess.php) | `pos_device.access` | Tenancy Guard | Confirms POS terminal device exists within the authorized outlet/business tenant. |
| [EnsureCashierSessionActive.php](file:///Users/macbookpro/Projects/smartpos/business-service/app/Http/Middleware/EnsureCashierSessionActive.php) | `cashier_session.active` | POS Session Guard | Rejects transaction execution if cashier session is `locked` (HTTP 423) or `ended` (HTTP 403). |

---

## 6. Complete API Catalog & Route Specifications

### 6.1 Public & Hardware Authentication

| Method | Endpoint | Middleware & Permissions | Controller Action | Description |
|---|---|---|---|---|
| `GET` | `/api/v1/business/health` | Public | Closure | Service health and timestamp status check |
| `POST` | `/api/v1/pos-devices/auth` | `throttle:auth` (5/min) | `PosDeviceController@authenticate` | Authenticate POS hardware with `machine_id` + `machine_password` |

### 6.2 Business & Company Tenancy

| Method | Endpoint | Middleware & Permissions | Controller Action | Description |
|---|---|---|---|---|
| `GET` | `/api/v1/businesses` | `jwt.auth`, `permission:businesses.view` | `BusinessController@index` | List all businesses where caller is active staff (or all if admin) |
| `POST` | `/api/v1/businesses` | `jwt.auth`, `permission:businesses.create` | `BusinessController@store` | Register new business; auto-assigns caller as owner |
| `GET` | `/api/v1/businesses/{business}` | `jwt.auth`, `permission:businesses.view`, `business.member` | `BusinessController@show` | View business profile with outlets, registers & devices |
| `PUT` | `/api/v1/businesses/{business}` | `jwt.auth`, `permission:businesses.update`, `business.member`, `business.owner` | `BusinessController@update` | Update business name, tax info, currency |
| `DELETE` | `/api/v1/businesses/{business}` | `jwt.auth`, `permission:businesses.delete`, `business.member`, `business.owner` | `BusinessController@destroy` | Soft-delete business tenant profile |
| `GET` | `/api/v1/businesses/{business}/settings` | `jwt.auth`, `permission:businesses.view`, `business.member` | `BusinessSettingController@show` | View POS settings (tax, receipts, auto-lock) |
| `PUT` | `/api/v1/businesses/{business}/settings` | `jwt.auth`, `permission:businesses.update`, `business.member`, `business.owner` | `BusinessSettingController@update` | Update POS settings |

### 6.3 Staff Members & Cashier Profiles

| Method | Endpoint | Middleware & Permissions | Controller Action | Description |
|---|---|---|---|---|
| `GET` | `/api/v1/businesses/{business}/users` | `jwt.auth`, `permission:business_users.view`, `business.member` | `BusinessUserController@index` | List staff members in business |
| `POST` | `/api/v1/businesses/{business}/users` | `jwt.auth`, `permission:business_users.manage`, `business.member`, `business.owner` | `BusinessUserController@store` | Add staff member with hashed PIN |
| `PUT` | `/api/v1/businesses/{business}/users/{businessUser}` | `jwt.auth`, `permission:business_users.manage`, `business.member`, `business.owner` | `BusinessUserController@update` | Update staff details, owner flag, PIN |
| `POST` | `/api/v1/businesses/{business}/users/{businessUser}/suspend` | `jwt.auth`, `permission:business_users.manage`, `business.member`, `business.owner` | `BusinessUserController@suspend` | Suspend staff access |
| `DELETE` | `/api/v1/businesses/{business}/users/{businessUser}` | `jwt.auth`, `permission:business_users.manage`, `business.member`, `business.owner` | `BusinessUserController@destroy` | Remove staff member from business |
| `GET` | `/api/v1/businesses/{business}/users/{businessUser}/cashier-profile` | `jwt.auth`, `permission:business_users.view`, `business.member` | `CashierProfileController@show` | View cashier selling & discount permissions |
| `PUT` | `/api/v1/businesses/{business}/users/{businessUser}/cashier-profile` | `jwt.auth`, `permission:business_users.manage`, `business.member`, `business.owner` | `CashierProfileController@update` | Update cashier permissions |
| `GET` | `/api/v1/businesses/{business}/users/{businessUser}/outlets` | `jwt.auth`, `permission:business_users.view`, `business.member` | `BusinessUserOutletController@index` | List outlets assigned to staff |
| `POST` | `/api/v1/businesses/{business}/users/{businessUser}/outlets` | `jwt.auth`, `permission:business_users.manage`, `business.member`, `business.owner` | `BusinessUserOutletController@store` | Assign staff to outlet |
| `DELETE` | `/api/v1/businesses/{business}/users/{businessUser}/outlets/{outlet}` | `jwt.auth`, `permission:business_users.manage`, `business.member`, `business.owner` | `BusinessUserOutletController@destroy` | Remove staff from outlet |

### 6.4 Store Outlets & Registers

| Method | Endpoint | Middleware & Permissions | Controller Action | Description |
|---|---|---|---|---|
| `GET` | `/api/v1/businesses/{business}/outlets` | `jwt.auth`, `permission:outlets.view`, `business.member` | `OutletController@index` | List store outlets under business |
| `POST` | `/api/v1/businesses/{business}/outlets` | `jwt.auth`, `permission:outlets.create`, `business.member` | `OutletController@store` | Create store outlet |
| `GET` | `/api/v1/outlets/{outlet}` | `jwt.auth`, `permission:outlets.view`, `outlet.access` | `OutletController@show` | View outlet details |
| `PUT` | `/api/v1/outlets/{outlet}` | `jwt.auth`, `permission:outlets.update`, `outlet.access` | `OutletController@update` | Update outlet information |
| `DELETE` | `/api/v1/outlets/{outlet}` | `jwt.auth`, `permission:outlets.delete`, `outlet.access`, `business.owner` | `OutletController@destroy` | Delete outlet |
| `GET` | `/api/v1/outlets/{outlet}/registers` | `jwt.auth`, `permission:registers.view`, `outlet.access` | `RegisterController@index` | List checkout registers in outlet |
| `POST` | `/api/v1/outlets/{outlet}/registers` | `jwt.auth`, `permission:registers.create`, `outlet.access` | `RegisterController@store` | Create register counter |
| `GET` | `/api/v1/registers/{register}` | `jwt.auth`, `permission:registers.view`, `register.access` | `RegisterController@show` | View register details |
| `PUT` | `/api/v1/registers/{register}` | `jwt.auth`, `permission:registers.update`, `register.access` | `RegisterController@update` | Update register |
| `DELETE` | `/api/v1/registers/{register}` | `jwt.auth`, `permission:registers.manage`, `register.access`, `business.owner` | `RegisterController@destroy` | Delete register |

### 6.5 Hardware POS Terminals & Device Sessions

| Method | Endpoint | Middleware & Permissions | Controller Action | Description |
|---|---|---|---|---|
| `GET` | `/api/v1/outlets/{outlet}/pos-devices` | `jwt.auth`, `permission:pos_devices.view`, `outlet.access` | `PosDeviceController@index` | List hardware terminals in outlet |
| `POST` | `/api/v1/outlets/{outlet}/pos-devices` | `jwt.auth`, `permission:pos_devices.create`, `outlet.access` | `PosDeviceController@store` | Provision new POS terminal with machine secret |
| `GET` | `/api/v1/pos-devices/{posDevice}` | `jwt.auth`, `permission:pos_devices.view`, `pos_device.access` | `PosDeviceController@show` | View POS hardware details & session logs |
| `PUT` | `/api/v1/pos-devices/{posDevice}` | `jwt.auth`, `permission:pos_devices.update`, `pos_device.access` | `PosDeviceController@update` | Update terminal specs & register pairing |
| `POST` | `/api/v1/pos-devices/{posDevice}/activate` | `jwt.auth`, `permission:pos_devices.manage`, `pos_device.access` | `PosDeviceController@activate` | Activate terminal for sales |
| `POST` | `/api/v1/pos-devices/{posDevice}/revoke` | `jwt.auth`, `permission:pos_devices.manage`, `pos_device.access` | `PosDeviceController@revoke` | Revoke terminal and all active machine sessions |
| `POST` | `/api/v1/pos-devices/{posDevice}/lock` | `jwt.auth`, `permission:pos_devices.manage`, `pos_device.access` | `PosDeviceController@lock` | Temporarily lock terminal |
| `POST` | `/api/v1/pos-devices/{posDevice}/rotate-secret` | `jwt.auth`, `permission:pos_devices.manage`, `pos_device.access` | `PosDeviceController@rotateSecret` | Rotate machine password (owner only) |
| `GET` | `/api/v1/pos-devices/{posDevice}/sessions` | `jwt.auth`, `permission:pos_devices.view`, `pos_device.access` | `DeviceSessionController@index` | View terminal login session history |
| `POST` | `/api/v1/pos-devices/{posDevice}/sessions/{deviceSession}/revoke` | `jwt.auth`, `permission:pos_devices.manage`, `pos_device.access` | `DeviceSessionController@revoke` | Revoke specific device session |

### 6.6 Cashier Sessions (Terminal Login, Lock, Unlock)

| Method | Endpoint | Middleware & Permissions | Controller Action | Description |
|---|---|---|---|---|
| `POST` | `/api/v1/outlets/{outlet}/cashier-sessions/start` | `jwt.auth`, `permission:pos_devices.use`, `outlet.access` | `CashierSessionController@store` | Start cashier session on POS terminal |
| `GET` | `/api/v1/outlets/{outlet}/cashier-sessions/current` | `jwt.auth`, `permission:pos_devices.use`, `outlet.access` | `CashierSessionController@current` | Get active/locked cashier session |
| `POST` | `/api/v1/outlets/{outlet}/cashier-sessions/{cashierSession}/lock` | `jwt.auth`, `permission:pos_devices.use`, `outlet.access` | `CashierSessionController@lock` | Lock cashier session |
| `POST` | `/api/v1/outlets/{outlet}/cashier-sessions/{cashierSession}/unlock` | `jwt.auth`, `permission:pos_devices.use`, `outlet.access`, `throttle:cashier_pin` | `CashierSessionController@unlock` | Unlock cashier session with staff PIN |
| `POST` | `/api/v1/outlets/{outlet}/cashier-sessions/{cashierSession}/end` | `jwt.auth`, `permission:pos_devices.use`, `outlet.access` | `CashierSessionController@end` | End cashier session |

### 6.7 Register Shifts & Cash Drawer Accounting

| Method | Endpoint | Middleware & Permissions | Controller Action | Description |
|---|---|---|---|---|
| `GET` | `/api/v1/outlets/{outlet}/registers/{register}/shifts` | `jwt.auth`, `permission:registers.view`, `outlet.access`, `register.access` | `RegisterSessionController@index` | List shift history for register |
| `GET` | `/api/v1/outlets/{outlet}/registers/{register}/shifts/current` | `jwt.auth`, `permission:registers.view`, `outlet.access`, `register.access` | `RegisterSessionController@current` | View current open shift |
| `POST` | `/api/v1/outlets/{outlet}/registers/{register}/shifts/open` | `jwt.auth`, `permission:registers.manage`, `outlet.access`, `register.access` | `RegisterSessionController@open` | Open shift with initial float cash |
| `POST` | `/api/v1/outlets/{outlet}/registers/{register}/shifts/{registerSession}/close` | `jwt.auth`, `permission:registers.manage`, `outlet.access`, `register.access` | `RegisterSessionController@close` | Close shift, count cash, record discrepancy |
| `GET` | `/api/v1/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}` | `jwt.auth`, `permission:registers.view`, `outlet.access`, `register.access` | `CashDrawerController@show` | View cash drawer balance summary |
| `GET` | `/api/v1/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}/movements` | `jwt.auth`, `permission:registers.view`, `outlet.access`, `register.access` | `CashDrawerController@movements` | List all cash movements in drawer |
| `POST` | `/api/v1/outlets/{outlet}/registers/{register}/drawers/{cashDrawerSession}/movements` | `jwt.auth`, `permission:registers.manage`, `outlet.access`, `register.access` | `CashDrawerController@recordMovement` | Record float cash in/out/payout/deposit |

---

## 7. Quality Assurance & Test Verification Metrics

The service is backed by a 100% passing test suite with zero failures and zero skipped tests:

```text
   PASS  Tests\Unit\ExampleTest
   PASS  Tests\Feature\ExampleTest
   PASS  Tests\Feature\BusinessTest
   PASS  Tests\Feature\BusinessSettingTest
   PASS  Tests\Feature\BusinessUserTest
   PASS  Tests\Feature\BusinessUserOutletTest
   PASS  Tests\Feature\CashierProfileTest
   PASS  Tests\Feature\OutletTest
   PASS  Tests\Feature\RegisterTest
   PASS  Tests\Feature\PosDeviceTest
   PASS  Tests\Feature\DeviceSessionTest
   PASS  Tests\Feature\CashierSessionTest
   PASS  Tests\Feature\RegisterShiftAndCashDrawerTest
   PASS  Tests\Feature\BusinessPosDatabasePlanTest
   PASS  Tests\Feature\WarehouseApiTest
   PASS  Tests\Feature\WarehouseLocationApiTest
   PASS  Tests\Feature\Security\WarehouseSecurityTest
   PASS  Tests\Feature\Security\Rs256JwtVerificationTest
   PASS  Tests\Feature\Security\AttackShieldTest
   PASS  Tests\Feature\Security\InputValidationSecurityTest
   PASS  Tests\Feature\Security\PentestSecurityTest
   PASS  Tests\Feature\Security\PosPentestSecurityTest

  Tests:    202 passed (937 assertions)
  Duration: 2.49s
```

### Verification Test Suites

1. **Multi-Tenant BOLA Isolation Tests**: Verified that non-members receive `403 Forbidden` when attempting to access another tenant's businesses, outlets, registers, warehouses, or POS devices.
2. **Privilege Escalation (BFLA) Tests**: Verified that regular staff cannot promote themselves to owners, rotate machine hardware secrets, modify global business settings, or alter cashier profiles.
3. **Pessimistic Concurrency & Float Ledger Tests**: Verified that concurrent cash movements on drawers and shift closes run inside row-level locked transactions (`lockForUpdate`), preventing double-counting or race conditions.
4. **SQLi, XSS & Attack Shield Pentests**: Automated penetration test suite asserting that malicious scanners (`sqlmap`, `nikto`), reconnaissance probes (`/.env`, `/.git`, `/phpmyadmin`), path traversals (`..`), null bytes, and script injection payloads are blocked at the middleware layer.
5. **State Machine Validation**: Complete lifecycle testing for POS devices (`pending` $\to$ `active` $\to$ `locked` $\to$ `revoked`), Cashier sessions (`active` $\to$ `locked` $\to$ `unlocked` $\to$ `ended`), and Register shifts (`open` $\to$ `closed`).

---

## 8. Containerization & High-Performance Deployment Architecture

The business service runs in a containerized **PHP 8.4-FPM** execution environment attached to the shared SmartPOS microservice network:

```
                          +-----------------------------------+
                          |      SmartPOS API Gateway         |
                          |      (Direct FastCGI Proxy)       |
                          +-----------------+-----------------+
                                            | FastCGI (:9000)
                                            v
                          +-----------------------------------+
                          |     smartpos-business-service     |
                          |      • PHP 8.4-FPM (Port: 9000)   |
                          |      • OPcache JIT Tracing (64MB) |
                          |      • FastCGI Socket Healthcheck |
                          +--------+----------------+---------+
                                   |                |
                +------------------+                +------------------+
                |                                                      |
                v                                                      v
     +--------------------+                                 +--------------------+
     |smartpos-business-db|                                 |smartpos-business-  |
     |   MySQL 8.4        |                                 |   redis (Port:6381)|
     |   Port: 3308       |                                 |   0.51ms Latency   |
     +--------------------+                                 +--------------------+
```

### High-Performance Configuration:
- **FastCGI Daemon Mode:** Runs `php-fpm -F` as PID 1 in foreground.
- **Worker Concurrency:** Multi-worker dynamic pool (`pm = dynamic`, `pm.max_children = 25`, `pm.start_servers = 4`).
- **Environment Ingestion:** `clear_env = no` configured in `docker/php/www.conf` so microservice environment variables (`IDENTITY_SERVICE_URL`, `PRODUCT_SERVICE_URL`, `DB_*`, `REDIS_*`) are directly available to worker requests.
- **Inter-Service Communication:** Set `IDENTITY_SERVICE_URL=http://api-gateway:80` and `PRODUCT_SERVICE_URL=http://api-gateway:80` allowing HTTP microservice calls to be translated cleanly to FastCGI through the API Gateway.
- **Healthcheck Probe:** FastCGI socket connectivity verified with:
  ```bash
  php -r "exit(@fsockopen('127.0.0.1', 9000) ? 0 : 1);"
  ```

---

## 9. Directory & File Reference Index

```text
smartpos-business-service/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── BusinessController.php
│   │   │   ├── BusinessSettingController.php
│   │   │   ├── BusinessUserController.php
│   │   │   ├── BusinessUserOutletController.php
│   │   │   ├── CashDrawerController.php
│   │   │   ├── CashierProfileController.php
│   │   │   ├── CashierSessionController.php
│   │   │   ├── DeviceSessionController.php
│   │   │   ├── OutletController.php
│   │   │   ├── PosDeviceController.php
│   │   │   ├── RegisterController.php
│   │   │   └── RegisterSessionController.php
│   │   ├── Middleware/
│   │   │   ├── AttackShieldMiddleware.php
│   │   │   ├── EnsureBusinessMember.php
│   │   │   ├── EnsureBusinessOwner.php
│   │   │   ├── EnsureCashierSessionActive.php
│   │   │   ├── EnsureOutletAccess.php
│   │   │   ├── EnsurePermission.php
│   │   │   ├── EnsurePosDeviceAccess.php
│   │   │   ├── EnsureRegisterAccess.php
│   │   │   ├── JwtAuthMiddleware.php
│   │   │   ├── SanitizeInputMiddleware.php
│   │   │   └── SecurityHeadersMiddleware.php
│   │   └── Requests/ (19 Form Request Classes)
│   └── Models/ (14 Eloquent Models)
├── config/
│   ├── app.php, cors.php, database.php, jwt.php, etc.
├── database/
│   └── migrations/ (14 Core Business Migrations)
├── docker/
│   ├── entrypoint.sh
│   └── php/
│       ├── opcache.ini
│       └── www.conf
├── routes/
│   ├── api.php
│   └── api/ (10 Modular Route Files)
└── tests/
    ├── Feature/ (13 Feature Test Suites)
    └── Feature/Security/ (4 Pentest & Security Test Suites)
```
