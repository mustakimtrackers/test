# 🟡 Features & Bugs Report — Pass 3
**Project:** `c:\xampp\htdocs\erp` | **Date:** August 7, 2026  
**Analysis Scope:** All 19 modules + includes + reports + APIs

> [!IMPORTANT]
> **Corrections from previous reports:**
> - Sales Returns module **IS implemented** (`modules/sales/returns/create.php` + `index.php`) — it just crashes due to a missing `InventoryManager.php` dependency
> - Password validation **IS implemented** in `users/add.php` (min 8 chars, number, special char)
> - 2FA **IS implemented** — `GoogleAuthenticator.php` exists in `includes/`, `login_2fa.php` handles the flow
> - Login rate limiting **IS implemented** — 5 attempts → 15-minute lockout

---

## Part 1 — 🐛 BUGS

---

### [BUG-1] 🆕 FATAL: `sales/returns/create.php` Requires Missing `InventoryManager.php`
**File:** [sales/returns/create.php Line 5](file:///c:/xampp/htdocs/erp/modules/sales/returns/create.php#L5)  
**Impact:** Sales Returns module is 100% broken — every visit throws Fatal Error

```php
require_once __DIR__ . '/../../../includes/InventoryManager.php';
// File does not exist anywhere in the project!
```

The class `InventoryManager` is used at line 97:
```php
$invManager = new InventoryManager($conn);
$invManager->addStock($rtItem['product_id'], $rtItem['quantity'], "...");
```

The entire sales return processing flow is dead. No customer can be refunded and no stock can be restocked via this module.

**Fix:** Create `includes/InventoryManager.php`:
```php
class InventoryManager {
    private $conn;
    public function __construct($conn) { $this->conn = $conn; }
    public function addStock($productId, $qty, $description) {
        $stmt = $this->conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $stmt->bind_param("ii", $qty, $productId);
        $stmt->execute();
        // Log stock movement
        $logStmt = $this->conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, description) VALUES (?, 'return', ?, ?)");
        $logStmt->bind_param("iis", $productId, $qty, $description);
        $logStmt->execute();
    }
}
```

---

### [BUG-2] 🆕 FATAL: `finance/capital.php` Requires Non-Existent `db_connect.php`
**File:** [finance/capital.php Line 7](file:///c:/xampp/htdocs/erp/modules/finance/capital.php#L7)  
**Impact:** Entire Capital Management page is broken

```php
require_once __DIR__ . '/../../includes/db_connect.php'; // Does not exist!
// All other files use:
require_once __DIR__ . '/../../config/database.php';
```

The Capital Management module throws a Fatal Error on every page load.

**Fix:** Change line 7 to: `require_once __DIR__ . '/../../config/database.php';`

---

### [BUG-3] 🆕 Sales Report — Status Filter Uses `'cancelled'` but DB Stores `'canceled'`
**Files:** [reports/sales.php Lines 59, 98, 180](file:///c:/xampp/htdocs/erp/modules/reports/sales.php#L59), [sales/list.php Line 144](file:///c:/xampp/htdocs/erp/modules/sales/list.php#L144)  
**Impact:** "Cancelled" filter shows zero results; totals include cancelled sales

```php
// In sales.php:
if ($sale['status'] !== 'cancelled') { // Double-L ❌
    $totalRevenue += $sale['total_amount']; // Cancelled sales counted in revenue!
}
// Filter dropdown option:
<option value="cancelled">Cancelled</option> // Will return 0 rows - DB has 'canceled'
```

The database `sales.status` ENUM uses `'canceled'` (American English, single L). The report files use `'cancelled'` (British English, double L). This means:
1. The "Cancelled" filter option returns **zero results** always
2. The revenue total incorrectly **includes** cancelled sales because the `!== 'cancelled'` check never matches

**Fix:** Global search-replace `'cancelled'` → `'canceled'` in all report and list files.

---

### [BUG-4] 🆕 `sales/returns/create.php` Queries Non-Existent Column `sales.customer_name`
**File:** [sales/returns/create.php Line 14](file:///c:/xampp/htdocs/erp/modules/sales/returns/create.php#L14)  
**Impact:** Even if `InventoryManager.php` is fixed, the returns page will still fail

```php
$salesQuery = "SELECT id, reference_no, sale_date, customer_name, total_amount FROM sales";
// 'customer_name' is NOT a column in the sales table!
// The sales table stores customer_id (FK), not customer_name directly.
```

The `sales` table has `customer_id` pointing to `contacts`. The `customer_name` column doesn't exist in `sales`. This query will fail with a MySQL error.

**Fix:**
```sql
SELECT s.id, s.reference_no, s.sale_date, s.total_amount, c.name as customer_name
FROM sales s
LEFT JOIN contacts c ON s.customer_id = c.id
WHERE s.status = 'completed' ORDER BY s.id DESC LIMIT 500
```

---

### [BUG-5] 🆕 `getCurrencySymbol()` Called in Returns Module — Function Does Not Exist
**File:** [sales/returns/create.php Line 236](file:///c:/xampp/htdocs/erp/modules/sales/returns/create.php#L236)  
**Impact:** Third reason the Returns module crashes (even after fixing BUG-1 and BUG-4)

```php
<span class="input-group-text"><?= getCurrencySymbol() ?></span>
```

`getCurrencySymbol()` is not defined anywhere in the codebase. `functions.php` has `formatCurrency()` which hardcodes `৳`, and `getSetting('currency_symbol')` is available, but `getCurrencySymbol()` as a standalone function does not exist.

**Fix:** Add to `includes/functions.php`:
```php
function getCurrencySymbol() {
    return getSetting('currency_symbol', '৳');
}
```

---

### [BUG-6] P&L Report — COGS Uses Current Cost Price, Not Historical
**File:** [reports/profit_loss.php Lines 26-36](file:///c:/xampp/htdocs/erp/modules/reports/profit_loss.php#L26-L36)  
**Status:** ⚠️ Still not fixed

```sql
SELECT SUM(si.quantity * p.cost_price) as cogs  -- p = current product, not historical!
```

P&L figures are historically inaccurate whenever product cost prices are updated.

---

### [BUG-7] `formatCurrency()` Hardcodes `৳` Symbol — Ignores Currency Setting
**File:** [includes/functions.php](file:///c:/xampp/htdocs/erp/includes/functions.php#L118)  
**Status:** ⚠️ Still not fixed

```php
function formatCurrency($amount) {
    return '৳' . number_format($amount, 2); // Ignores currency_symbol setting
}
```

The currency symbol in Settings page has no effect.

---

### [BUG-8] `getCompanyInfo()` Uses Wrong Key for Tax Number
**File:** [includes/settings_helper.php Line 90](file:///c:/xampp/htdocs/erp/includes/settings_helper.php#L90)  
**Status:** ⚠️ Still not fixed

```php
'tax_number' => getSetting('company_tax_number', ''), // Key doesn't exist
// Settings saves it as 'tax_id'
```

Tax number always shows blank on invoices and company info.

---

### [BUG-9] `TransactionManager` — Backdating Not Supported
**File:** [includes/TransactionManager.php Line 33](file:///c:/xampp/htdocs/erp/includes/TransactionManager.php#L33)  
**Status:** ⚠️ Still not fixed

All financial transactions are recorded with today's date regardless of the actual sale/purchase date. Backdated sales create ledger date mismatches.

---

### [BUG-10] `capital.php` — Displays Amount Column Label as `৳` Hardcoded
**File:** [finance/capital.php Line 81](file:///c:/xampp/htdocs/erp/modules/finance/capital.php#L81)  
**Impact:** Minor — currency symbol hardcoded instead of from settings

```php
৳<?= number_format($ctx['amount'], 2) ?>
```

Same issue as `formatCurrency()` — should use `getSetting('currency_symbol', '৳')`.

---

### [BUG-11] Customer Ledger — Date Filter Applied in PHP, Not SQL
**File:** [reports/customer_ledger.php Lines 136-169](file:///c:/xampp/htdocs/erp/modules/reports/customer_ledger.php#L136-L169)  
**Impact:** Performance — loads ALL transactions then filters in PHP

```php
$allTransactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// Then filters by date in a PHP foreach loop
foreach ($allTransactions as $t) {
    $txnDate = date('Y-m-d', strtotime($t['date']));
    if ($txnDate >= $startDate && $txnDate <= $endDate) { ... }
}
```

The SQL query fetches **all transactions for a customer since year 2000** (`'2000-01-01'` hardcoded), then filters by date in PHP. For a customer with years of transaction history, this will be extremely slow.

**Fix:** Add `WHERE date BETWEEN ? AND ?` to the SQL `UNION ALL` query and remove the PHP filtering loop.

---

### [BUG-12] Sale Delete — Customer Balance Not Reversed on Overpayment
**File:** [sales/delete.php Lines 59-65](file:///c:/xampp/htdocs/erp/modules/sales/delete.php#L59-L65)  
**Status:** ⚠️ Still not fixed

When `paid_amount > total_amount` (change given), `dueAmount` is negative and the balance reversal is skipped.

---

### [BUG-13] `activity_logger.php` — Wrong `logActivity()` Call Signature in Returns
**File:** [sales/returns/create.php Line 128](file:///c:/xampp/htdocs/erp/modules/sales/returns/create.php#L128)  
**Impact:** Activity logging for returns uses wrong function signature

```php
logActivity($conn, 'sale_return_created', 'Created sales return', $return_id);
// Actual signature: logActivity($action, $module, $record_id, $old_values, $new_values, $description)
// $conn is being passed as $action — completely wrong!
```

The `logActivity()` function in `activity_logger.php` takes `($action, $module, $record_id, ...)` as first params — NOT `$conn`. This call will either silently fail or cause unexpected behavior.

**Fix:** `logActivity('sale_return_created', 'sale_returns', $return_id, null, null, 'Created sales return');`

---

### [BUG-14] `capital.php` Query — Joins on Wrong Column (`ct.payment_method` vs `fa.id`)
**File:** [finance/capital.php Lines 16-24](file:///c:/xampp/htdocs/erp/modules/finance/capital.php#L16-L24)  
**Impact:** "Target Account" column always shows NULL/Unknown Account

```php
$query = "
    SELECT ct.*, u.name as creator_name,
           fa.account_name as bank_name
    FROM capital_transactions ct
    LEFT JOIN financial_accounts fa ON ct.payment_method = fa.id
    // ct.payment_method is an ENUM ('cash', 'bank', 'mobile') — NOT a foreign key ID!
```

`capital_transactions.method` is an ENUM of text values (`'cash'`, `'bank'`, `'mobile'`), not a numeric foreign key. Joining it with `financial_accounts.id` (integer) will always return NULL. The "Target Account" column in the table will always display "Unknown Account".

**Fix:** Remove the JOIN or restructure to use a proper `account_id` integer FK column in `capital_transactions`.

---

## Part 2 — 🚧 MISSING FEATURES

---

### [FEAT-1] Sales Returns Module Exists but Has 3 Fatal Bugs Blocking It
**Files:** [returns/create.php](file:///c:/xampp/htdocs/erp/modules/sales/returns/create.php), [returns/index.php](file:///c:/xampp/htdocs/erp/modules/sales/returns/index.php)

The Sales Returns module was built but has three blocking issues:
1. BUG-1: `InventoryManager.php` missing → Fatal Error
2. BUG-4: `customer_name` column doesn't exist in `sales` table → SQL Error
3. BUG-5: `getCurrencySymbol()` function doesn't exist → Call to undefined function

The module logic itself is well-designed. Fix all 3 bugs and it should work.

---

### [FEAT-2] No Dedicated Supplier Payment Module
**Status:** ⚠️ Still missing

`customers/payment.php` exists. No equivalent `suppliers/payment.php` exists. Staff have no way to record payments to suppliers against their dues except via "Quick Adjustment".

---

### [FEAT-3] No CSV/Excel Export for Any Report
**Status:** ⚠️ Still missing

All reports have print views only. No data export capability.

---

### [FEAT-4] No Pagination on Customer/Supplier Ledger
**File:** [reports/customer_ledger.php](file:///c:/xampp/htdocs/erp/modules/reports/customer_ledger.php)  
**Status:** ⚠️ Still a performance issue + BUG-11 makes it worse

Loads all-time transactions in PHP memory. Confirmed: the system has been running since Feb 2026 with daily transactions.

---

### [FEAT-5] 2FA Setup Page Missing for Users
While `GoogleAuthenticator.php` exists and `login_2fa.php` handles TOTP verification, there is **no user-facing setup page** to:
- Enable 2FA for their account
- See the QR code to scan in Google Authenticator
- Save the secret to their profile

The `two_factor_secret` column exists in `users` table but there is no module at `modules/users/enable_2fa.php` or similar. 2FA is technically stubbed but never activatable by actual users.

---

### [FEAT-6] No Forgot Password Flow
**Status:** ⚠️ Still missing

No email-based password reset mechanism exists.

---

### [FEAT-7] Supplier Ledger Report Has No Balance Summary
**File:** [reports/supplier_ledger.php](file:///c:/xampp/htdocs/erp/modules/reports/supplier_ledger.php)

The supplier ledger shows transaction history but has no "Current Balance Due" summary at the top like the customer ledger has.

---

### [FEAT-8] No Multi-Unit Support for Products in POS
Products have a `unit_id` field, but the POS only sells in whole units. There is no support for selling in fractions (e.g., 0.5 kg of feed), which is important for the poultry feed business this system is being used for.

---

## 📊 Summary

| Category | Count |
|---|---|
| 🐛 Bugs (including 5 new Fatal/Critical ones) | 14 |
| 🚧 Missing Features | 8 |
| **Total** | **22** |

### Top 5 Immediate Fixes

| # | Issue | Effect |
|---|---|---|
| 1 | Create `InventoryManager.php` | Sales Returns module becomes functional |
| 2 | Fix `capital.php` db_connect path | Capital Management page loads |
| 3 | Fix `'cancelled'` → `'canceled'` spelling | Sales report filter and totals corrected |
| 4 | Fix `customer_name` column in returns query | Returns dropdown populated correctly |
| 5 | Add `getCurrencySymbol()` function | Returns module UI renders correctly |
