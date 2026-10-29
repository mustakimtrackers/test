<?php
$pageTitle = 'Trial Balance';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

if (!isAdmin()) {
    die("Access Denied");
}

// Generate Trial Balance from journal_entry_lines
$query = "SELECT c.code, c.name, c.type, SUM(l.debit) as total_debit, SUM(l.credit) as total_credit 
          FROM chart_of_accounts c 
          LEFT JOIN journal_entry_lines l ON c.id = l.account_id 
          GROUP BY c.id ORDER BY c.code ASC";
$result = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

$grand_debit = 0;
$grand_credit = 0;
?>
<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title"><i class="bi bi-file-earmark-spreadsheet"></i> Trial Balance</h1>
        <button class="btn btn-outline-secondary d-none d-md-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
    
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th>Type</th>
                            <th class="text-end">Debit (Dr)</th>
                            <th class="text-end">Credit (Cr)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result as $row): 
                            $debit = $row['total_debit'] ?? 0;
                            $credit = $row['total_credit'] ?? 0;
                            // Calculate net balance
                            $net_debit = 0;
                            $net_credit = 0;
                            $type = strtolower($row['type']);
                            if (in_array($type, ['asset', 'expense'])) {
                                $net = $debit - $credit;
                                if ($net >= 0) $net_debit = $net; else $net_credit = abs($net);
                            } else {
                                $net = $credit - $debit;
                                if ($net >= 0) $net_credit = $net; else $net_debit = abs($net);
                            }
                            
                            if ($net_debit == 0 && $net_credit == 0) continue; // Skip zero balance accounts
                            
                            $grand_debit += $net_debit;
                            $grand_credit += $net_credit;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['code']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['type']) ?></span></td>
                            <td class="text-end fw-bold"><?= $net_debit > 0 ? number_format($net_debit, 2) : '-' ?></td>
                            <td class="text-end fw-bold"><?= $net_credit > 0 ? number_format($net_credit, 2) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold fs-5"><?= number_format($grand_debit, 2) ?></td>
                            <td class="text-end fw-bold fs-5"><?= number_format($grand_credit, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
