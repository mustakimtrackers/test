# Remaining Fix Notes — ERP (v3 → Final)
**Date:** 2026-09-05  
**Issues:** 3টি বাকি — F-12-B, F-08, F-14

---

## FIX-1 (🔴 Critical) — Investor Ledger Type Mismatch

**File:** [`reports/ledger.php`](file:///c:/xampp/htdocs/erp/modules/reports/ledger.php)  
**Lines:** 109–110 এবং 131–135  
**কারণ:** Capital module `'injection'`/`'withdrawal'` → `'add'`/`'withdraw'` এ standardize হয়েছে কিন্তু ledger.php আপডেট হয়নি।

---

### পরিবর্তন ১ — Opening Balance Query (Line 107–113)

**এই code খোঁজো:**
```php
$opQuery = "
    SELECT 
        SUM(CASE WHEN type = 'injection' THEN amount ELSE 0 END) as total_in,
        SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_out
    FROM capital_transactions 
    WHERE investor_id = ? AND trans_date < ?
";
```

**এই দিয়ে replace করো:**
```php
$opQuery = "
    SELECT 
        SUM(CASE WHEN type = 'add' THEN amount ELSE 0 END) as total_in,
        SUM(CASE WHEN type = 'withdraw' THEN amount ELSE 0 END) as total_out
    FROM capital_transactions 
    WHERE investor_id = ? AND trans_date < ?
";
```

---

### পরিবর্তন ২ — Transaction Display Mapping (Line 128–136)

**এই code খোঁজো:**
```php
$transactions[] = [
    'date'        => $row['trans_date'],
    'ref'         => '-', 
    'type'        => $row['type'] == 'injection' ? 'Investment' : 'Withdrawal',
    'description' => $row['description'],
    'method'      => $row['method'],
    'debit'       => $row['type'] == 'withdrawal' ? $row['amount'] : 0,
    'credit'      => $row['type'] == 'injection' ? $row['amount'] : 0,
];
```

**এই দিয়ে replace করো:**
```php
$transactions[] = [
    'date'        => $row['trans_date'],
    'ref'         => '-', 
    'type'        => $row['type'] == 'add' ? 'Investment' : 'Withdrawal',
    'description' => $row['description'],
    'method'      => $row['method'],
    'debit'       => $row['type'] == 'withdraw' ? $row['amount'] : 0,
    'credit'      => $row['type'] == 'add' ? $row['amount'] : 0,
];
```

> ✅ শুধু `'injection'` → `'add'` এবং `'withdrawal'` → `'withdraw'` — এই চারটা string replace করলেই হবে।

---

## FIX-2 (🟡 Medium) — Sale Un-Cancel Financial Account Gap

**File:** [`sales/update_status.php`](file:///c:/xampp/htdocs/erp/modules/sales/update_status.php)  
**Lines:** 144–153 (CASE B section)  
**কারণ:** Cancel করার সময় paid amount financial account থেকে বের করা হয়, কিন্তু un-cancel করার সময় সেটা আবার account-এ ঢোকানো হয় না।

> **Design Decision:** এই gap intentional রাখাই সঠিক। Un-cancel করলে sale পুরো due হিসেবে reopen হবে — customer-কে নতুন করে pay করতে হবে। Code-এ comment দিয়ে এটা document করো।

**এই code খোঁজো (Line 144–152):**
```php
// 3. Re-apply Customer Balance (Add Due Amount)
// Since paid amount was zeroed out and refunded on cancel, due is now total amount
$totalAmount = $sale['total_amount'];
```

**এই দিয়ে replace করো:**
```php
// 3. Re-apply Customer Balance (Add Total Amount — all is now due)
// On cancel: paid_amount was zeroed, payments deleted, financial transactions reversed.
// On un-cancel: sale reopens as FULLY DUE. Previous payment history is gone.
// The customer must re-pay via the payment module. This is intentional by design.
$totalAmount = $sale['total_amount'];
```

> শুধু comment বদলাও — code logic-এ কোনো পরিবর্তন নেই।

---

## FIX-3 (🟡 Medium) — Quick Adjustment Customer/Supplier Refund Logic

**File:** [`finance/quick_adjustment.php`](file:///c:/xampp/htdocs/erp/modules/finance/quick_adjustment.php)  

**সমস্যার core:**
```
$amount = receive → +rawAmount
$amount = refund  → -rawAmount (negative)

balance = balance - $amount
→ balance - (-rawAmount) = balance + rawAmount  ← Due বাড়ে! ভুল!
```

**Customer Section (approx. line 69–104) — এই পুরো block খোঁজো:**
```php
$amount = ($paymentType === 'receive') ? $rawAmount : (-1 * $rawAmount);

$stmt = $conn->prepare("INSERT INTO payments ... VALUES ('customer', ?, ?, ?, ?, ?)");
$stmt->bind_param("idsss", $entityId, $amount, ...);

$stmt = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
$stmt->bind_param("di", $amount, $entityId);

$txnType = $amount >= 0 ? 'credit' : 'debit';
$description = $amount >= 0 ? "Quick Payment from Customer" : "Quick Refund to Customer";
```

**এই দিয়ে replace করো:**
```php
// Always work with positive amount
// payment_type determines direction

$stmt = $conn->prepare("INSERT INTO payments (payable_type, payable_id, amount, method, note, paid_at) VALUES ('customer', ?, ?, ?, ?, ?)");
$stmt->bind_param("idsss", $entityId, $rawAmount, $methodLabel, $note, $date);
$stmt->execute();
$payment_id = $conn->insert_id;
$stmt->close();

if ($paymentType === 'receive') {
    // Customer paid us → their due decreases
    $stmt = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
    $txnType = 'credit'; // Money IN to our account
    $description = "Quick Payment from Customer";
} else {
    // We refunded customer → their due decreases (or credit balance increases)
    $stmt = $conn->prepare("UPDATE contacts SET balance = balance + ? WHERE id = ?");
    $txnType = 'debit'; // Money OUT of our account
    $description = "Quick Refund to Customer";
}
$stmt->bind_param("di", $rawAmount, $entityId);
$stmt->execute();
$stmt->close();

$tm = new TransactionManager($conn);

foreach ($accountIds as $index => $accId) {
    $splitRawVal = (float)$paymentAmounts[$index];
    if ($splitRawVal <= 0) continue;
    
    $tm->recordTransaction(
        (int)$accId, $txnType, abs($splitRawVal), 'customer_payment', $payment_id,
        "$description: $contactName" . ($note ? " ($note) [Split]" : " [Split]"), $user_id
    );
}
```

---

**Supplier Section (approx. line 106–133) — একই pattern:**

```php
// আগে:
$amount = ($paymentType === 'pay') ? $rawAmount : (-1 * $rawAmount);
$stmt->bind_param("idsss", $entityId, $amount, ...);
UPDATE contacts SET balance = balance - ?  // with signed $amount
$txnType = $amount >= 0 ? 'debit' : 'credit';
```

```php
// এই দিয়ে replace করো:
$stmt = $conn->prepare("INSERT INTO payments (payable_type, payable_id, amount, method, note, paid_at) VALUES ('supplier', ?, ?, ?, ?, ?)");
$stmt->bind_param("idsss", $entityId, $rawAmount, $methodLabel, $note, $date);
$stmt->execute();
$payment_id = $conn->insert_id;
$stmt->close();

if ($paymentType === 'pay') {
    // We paid supplier → their due decreases
    $stmt = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
    $txnType = 'debit'; // Money OUT of our account
    $description = "Quick Payment to Supplier";
} else {
    // Supplier refunded us → their due increases (we recover money)
    $stmt = $conn->prepare("UPDATE contacts SET balance = balance + ? WHERE id = ?");
    $txnType = 'credit'; // Money IN to our account
    $description = "Quick Refund from Supplier";
}
$stmt->bind_param("di", $rawAmount, $entityId);
$stmt->execute();
$stmt->close();

$tm = new TransactionManager($conn);

foreach ($accountIds as $index => $accId) {
    $splitRawVal = (float)$paymentAmounts[$index];
    if ($splitRawVal <= 0) continue;
    
    $tm->recordTransaction(
        (int)$accId, $txnType, abs($splitRawVal), 'supplier_payment', $payment_id,
        "$description: $contactName" . ($note ? " ($note) [Split]" : " [Split]"), $user_id
    );
}
```

---

## ✅ সংক্ষেপে — কোন file-এ কী করতে হবে

| Priority | Fix | File | কাজের পরিমাণ |
|----------|-----|------|-------------|
| 🔴 আগে করো | FIX-1 | [`reports/ledger.php`](file:///c:/xampp/htdocs/erp/modules/reports/ledger.php) L109–135 | মাত্র 4টি string replace |
| 🟡 পরে করো | FIX-2 | [`sales/update_status.php`](file:///c:/xampp/htdocs/erp/modules/sales/update_status.php) L144 | শুধু comment বদলাও |
| 🟡 পরে করো | FIX-3 | [`finance/quick_adjustment.php`](file:///c:/xampp/htdocs/erp/modules/finance/quick_adjustment.php) L69–133 | Customer + Supplier block rewrite |

> FIX-1 করলেই সবচেয়ে বড় active bug চলে যাবে।  
> FIX-3 করলে accounting আরো accurate হবে।  
> FIX-2 শুধু documentation — optional।
