# 🔴 Security Vulnerability Report — Pass 3
**Project:** `c:\xampp\htdocs\erp` | **Date:** August 7, 2026  
**Analysis Scope:** All 19 modules + includes + config + API endpoints

> [!IMPORTANT]
> **Corrections from previous reports:** `login.php` NOW has rate limiting (5 attempts / 15 min lockout), cookie_secure = true, SameSite=Strict, and 2FA support via `login_2fa.php`. These were previously marked as missing — they are already implemented.

---

## 🔴 CRITICAL

---

### [CRIT-1] `inventory_pos.sql` — Full DB Dump Publicly Accessible
**File:** [inventory_pos.sql](file:///c:/xampp/htdocs/erp/inventory_pos.sql) — 267KB  
**Status:** ⚠️ Still Present

The complete database dump is in the public web root. Anyone who guesses or knows the URL downloads the entire database including all customer data, financial history, hashed passwords, and company information.

**Fix:** `del c:\xampp\htdocs\erp\inventory_pos.sql` — Delete immediately.

---

### [CRIT-2] `backfill_cogs.php` — Unauthenticated Mass Data Modification
**File:** [backfill_cogs.php](file:///c:/xampp/htdocs/erp/backfill_cogs.php)  
**Status:** ⚠️ Still Present

```php
<?php
require_once __DIR__ . '/config/database.php';
// NO AUTH. Runs UPDATE query on entire sale_items table.
$conn->query("UPDATE sale_items si JOIN products p ... SET si.cost_price = p.cost_price ...");
```

No authentication. Any internet user can trigger a mass UPDATE on financial data.

**Fix:** `del c:\xampp\htdocs\erp\backfill_cogs.php` — Delete immediately.

---

### [CRIT-3] 🆕 `sales/returns/create.php` — Fatal Error on Page Load (Missing Class File)
**File:** [sales/returns/create.php Line 5](file:///c:/xampp/htdocs/erp/modules/sales/returns/create.php#L5)  
**Severity:** 🔴 CRITICAL

```php
require_once __DIR__ . '/../../../includes/InventoryManager.php'; // FILE DOES NOT EXIST!
```

**`InventoryManager.php` does not exist anywhere in the codebase.** This causes a **Fatal PHP Error** whenever anyone visits the Sales Return page. The entire Sales Returns module is completely non-functional.

Confirmed: grep search across all `includes/` and the entire project returns zero results for `InventoryManager.php` as a file.

**Fix:** Create `includes/InventoryManager.php` with an `addStock()` method, or replace with direct stock update SQL in the returns module.

---

### [CRIT-4] `capital.php` — Requires Non-Existent File (`db_connect.php`)
**File:** [finance/capital.php Line 7](file:///c:/xampp/htdocs/erp/modules/finance/capital.php#L7)  
**Severity:** 🔴 CRITICAL

```php
require_once __DIR__ . '/../../includes/db_connect.php'; // File does not exist!
```

All other modules use `config/database.php`. This file requires `includes/db_connect.php` which **does not exist**. The entire Capital Management page will throw a **Fatal Error** on every load.

**Fix:** Change to `require_once __DIR__ . '/../../config/database.php';`

---

### [CRIT-5] `Validator.php` — SQL Injection via Table/Column Names (Mass Assignment)
**File:** [includes/Validator.php Lines 93-94](file:///c:/xampp/htdocs/erp/includes/Validator.php#L93-L94)  
**Severity:** 🔴 CRITICAL

```php
private function checkUnique($table, $column, $value, $excludeId = null) {
    $sql = "SELECT id FROM $table WHERE $column = ?";
    // $table and $column are directly interpolated into SQL with NO sanitization!
```

The `$table` and `$column` parameters are interpolated directly into the SQL query. While values are safely parameterized with `?`, the table and column names are not. If user input ever reaches this function as `$table` or `$column`, it is a **SQL injection vulnerability**.

```php
// Usage in products/create.php:
$validator->validate($_POST, ['sku' => 'unique:products,sku']);
// 'products' and 'sku' come from the rules string — currently hardcoded in PHP.
// BUT: if the rule source is ever user-controllable or passed via URL, this is injectable.
```

**Fix:** Whitelist allowed table/column names:
```php
$allowedTables = ['users', 'products', 'contacts'];
$allowedColumns = ['email', 'sku', 'slug', 'name'];
if (!in_array($table, $allowedTables) || !in_array($column, $allowedColumns)) {
    throw new Exception("Invalid table/column for unique check");
}
```

---

### [CRIT-6] All DELETE Actions Are GET Requests — No CSRF Token
**Files:** All `delete.php` files in products, sales, purchases, expenses  
**Status:** ⚠️ Still not fixed

```
GET /modules/products/delete.php?id=15
GET /modules/sales/delete.php?id=42
```

Cross-site request forgery attack via embedded image/link. No confirmation, no CSRF token.

**Fix:** Convert to POST + CSRF token or use a signed confirmation URL.

---

### [CRIT-7] DB Credentials Hardcoded in Source File
**File:** [config/database.php](file:///c:/xampp/htdocs/erp/config/database.php)  
**Status:** ⚠️ Still present

```php
define('DB_PASS', 'FORBIT@at66');
define('SECRET_KEY', '7f8a9d1b2c3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9');
```

**Fix:** Move to `.env` file outside `htdocs/`.

---

## 🟠 HIGH SEVERITY

---

### [HIGH-1] 🆕 `customer_ledger.php` — Raw DB Error Exposed via `die()`
**File:** [reports/customer_ledger.php Line 116](file:///c:/xampp/htdocs/erp/modules/reports/customer_ledger.php#L116)  
**Severity:** 🟠 HIGH

```php
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error); // Exposes full SQL error to user!
}
```

If the SQL query fails (e.g., due to DB error, missing column, or schema change), this line will output the full SQL error message including table names, column names, and the internal query structure directly to the browser. This leaks database schema information.

**Fix:** Replace with a user-friendly error and log to error_log:
```php
if (!$stmt) {
    error_log("Ledger prepare failed: " . $conn->error);
    setFlashMessage('error', 'Failed to load ledger. Please try again.');
    redirect(url('/modules/reports/customer_ledger.php'));
}
```

---

### [HIGH-2] `email_helper.php` — SSL Verification Disabled
**File:** [includes/email_helper.php Line 53](file:///c:/xampp/htdocs/erp/includes/email_helper.php#L53)  
**Severity:** 🟠 HIGH

```php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
```

SSL peer verification is disabled for the OneSignal API call. This opens the app to **man-in-the-middle attacks** — an attacker on the network could intercept the cURL request and steal the OneSignal API key or inject false responses.

**Fix:** Remove this line (default is `true`) or explicitly set to `true`.

---

### [HIGH-3] `expenses/delete.php` — No Admin Role Check
**File:** [expenses/delete.php](file:///c:/xampp/htdocs/erp/modules/expenses/delete.php#L1-L5)  
**Status:** ⚠️ Still not fixed

Any authenticated staff/cashier can delete expenses and reverse their financial account transactions.

**Fix:** Add `requireAdmin();` at line 3.

---

### [HIGH-4] Remember-Me Token — No Expiry Validation, No DB-Backed Revocation
**File:** [includes/auth_check.php Lines 24-56](file:///c:/xampp/htdocs/erp/includes/auth_check.php#L24-L56)  
**Status:** ⚠️ Still not fixed

The cookie timestamp is stored but never compared against a maximum age. A stolen cookie works indefinitely since there's no server-side token table to revoke.

---

### [HIGH-5] Debug Files Still in Web Root
**Status:** ⚠️ Not confirmed removed since last report

| File | Issue |
|---|---|
| `modules/purchases/debug_post.log` | Writes all POST data to disk on every purchase |
| `modules/sales/api/debug_last_sale.php` | Unauthenticated endpoint |
| `modules/reports/debug_ledger_query.php` | Unauthenticated debug output |
| `modules/sales/invoice_debug.php` | Debug invoice data |

The active write in [purchases/create.php Line 15](file:///c:/xampp/htdocs/erp/modules/purchases/create.php#L15) still logs all POST data.

---

## 🟡 MEDIUM SEVERITY

---

### [MED-1] 🆕 `capital.php` Modal Form — No CSRF Token
**File:** [finance/capital.php Line 124](file:///c:/xampp/htdocs/erp/modules/finance/capital.php#L124)  
**Severity:** 🟡 MEDIUM

```html
<form id="capitalForm">
    <input type="hidden" name="type" id="capitalType" value="add">
    <!-- NO csrf_token field! -->
```

The capital add/withdraw form submits via AJAX to `process_capital.php`. There is **no CSRF token in the form**, and `process_capital.php` doesn't validate one. An attacker could trick an admin into triggering unauthorized capital injections or withdrawals.

**Fix:** Add `generateCSRFToken()` to form and validate in `process_capital.php`.

---

### [MED-2] `stock_adjustment.php` — No CSRF Token
**File:** [products/stock_adjustment.php Line 108](file:///c:/xampp/htdocs/erp/modules/products/stock_adjustment.php#L108)  
**Status:** ⚠️ Still not fixed

The stock adjustment POST form has no CSRF token.

---

### [MED-3] `error_reporting(E_ALL)` Left On in Ledger
**File:** [reports/ledger.php Lines 7-8](file:///c:/xampp/htdocs/erp/modules/reports/ledger.php#L7-L8)  
**Status:** ⚠️ Still not fixed

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

---

### [MED-4] No Content Security Policy Header
**File:** [.htaccess](file:///c:/xampp/htdocs/erp/.htaccess)  
**Status:** ⚠️ Still not fixed

---

## 🔵 LOW SEVERITY

---

### [LOW-1] `handleFileUpload()` — Directory Created with `0777` Permissions
**File:** [includes/functions.php Line 429](file:///c:/xampp/htdocs/erp/includes/functions.php#L429)  
**Status:** ⚠️ Still not fixed

```php
mkdir($targetDir, 0777, true); // World-writable
```
**Fix:** Use `0755`.

---

### [LOW-2] Timezone Never Applied from Settings
**Status:** ⚠️ Still not fixed

`date_default_timezone_set()` is never called. All timestamps use server default timezone regardless of the Asia/Dhaka setting configured in the DB.

---

### [LOW-3] `login.php` Cookie Set with `false` for Secure Flag in Cookie
**File:** [modules/auth/login.php Line 84](file:///c:/xampp/htdocs/erp/modules/auth/login.php#L84)  
**Severity:** 🔵 LOW

```php
// Session cookie: cookie_secure = TRUE ✅ (line 9)
session_start(['cookie_secure' => true, ...]);

// BUT the Remember-Me cookie explicitly sets secure=false:
setcookie('remember_token', $cookie_value, time() + (86400 * 30), '/', '', false, true);
//                                                                       ^^^^^ secure=false!
```

The session cookie is secure, but the Remember-Me cookie (`remember_token`) is set with `secure=false`. This means the Remember-Me token is transmitted over plain HTTP.

**Fix:** Change `false` to `true`: `setcookie('remember_token', $cookie_value, time() + (86400 * 30), '/', '', true, true);`

---

## ✅ CORRECTED FROM PREVIOUS REPORTS

| Item | Old Finding | Actual Status |
|---|---|---|
| Login Rate Limiting | ❌ "Missing" | ✅ Implemented (5 attempts / 15 min) |
| 2FA Support | ❌ "Missing Feature" | ✅ `login_2fa.php` exists, DB has `two_factor_secret` column |
| `cookie_secure` on session | ❌ "Set to false" | ✅ Set to `true` in login.php |
| SameSite cookie | ❌ "Not set" | ✅ `'cookie_samesite' => 'Strict'` |
| XSS in `users/add.php` errors | ❌ "Unescaped" | ✅ Uses `escape($e)` |
| Password strength validation | ❌ "Missing" | ✅ Min 8 chars + number + special char enforced |

---

## 📋 Priority Action List

| # | Issue | File | Urgency |
|---|---|---|---|
| 1 | Delete `inventory_pos.sql` from web root | Root | **Now** |
| 2 | Delete `backfill_cogs.php` | Root | **Now** |
| 3 | Fix Fatal Error in `capital.php` (`db_connect.php` → `database.php`) | [capital.php](file:///c:/xampp/htdocs/erp/modules/finance/capital.php#L7) | **Now** |
| 4 | Create `InventoryManager.php` or fix returns module | [returns/create.php](file:///c:/xampp/htdocs/erp/modules/sales/returns/create.php#L5) | **Now** |
| 5 | Remove `die()` with DB error in customer ledger | [customer_ledger.php](file:///c:/xampp/htdocs/erp/modules/reports/customer_ledger.php#L116) | Today |
| 6 | Fix SSL verify peer in email helper | [email_helper.php](file:///c:/xampp/htdocs/erp/includes/email_helper.php#L53) | Today |
| 7 | Add `requireAdmin()` to expenses delete | [expenses/delete.php](file:///c:/xampp/htdocs/erp/modules/expenses/delete.php) | Today |
| 8 | Remove debug log line from purchases create | [create.php](file:///c:/xampp/htdocs/erp/modules/purchases/create.php#L15) | Today |
| 9 | Fix Remember-Me cookie `secure=false` → `true` | [login.php](file:///c:/xampp/htdocs/erp/modules/auth/login.php#L84) | This week |
| 10 | Add CSRF to capital modal form | [capital.php](file:///c:/xampp/htdocs/erp/modules/finance/capital.php) | This week |
| 11 | Add CSRF to stock adjustment | [stock_adjustment.php](file:///c:/xampp/htdocs/erp/modules/products/stock_adjustment.php) | This week |
| 12 | Remove `display_errors` from ledger | [ledger.php](file:///c:/xampp/htdocs/erp/modules/reports/ledger.php#L7-L8) | This week |
| 13 | Whitelist table/column in Validator | [Validator.php](file:///c:/xampp/htdocs/erp/includes/Validator.php#L93) | This week |
| 14 | Move DB credentials to `.env` | [database.php](file:///c:/xampp/htdocs/erp/config/database.php) | This sprint |
| 15 | Fix delete actions: GET → POST + CSRF | All delete.php files | This sprint |
