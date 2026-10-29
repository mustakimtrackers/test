<?php
$pageTitle = 'Manual Journal Entry';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/TransactionManager.php';

if (!isAdmin()) {
    die("Access Denied");
}

$message = '';
$tm = new TransactionManager($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Error: Invalid security token.</div>";
    } else {
        $date = cleanInput($_POST['date']);
        $description = cleanInput($_POST['description']);
        $branch_id = $_SESSION['branch_id'] ?? 1;
    
    $account_ids = $_POST['account_id'];
    $debits = $_POST['debit'];
    $credits = $_POST['credit'];
    
    $lines = [];
    $total_debit = 0;
    $total_credit = 0;
    
    for ($i = 0; $i < count($account_ids); $i++) {
        $acc = (int)$account_ids[$i];
        $dr = (float)($debits[$i] ?? 0);
        $cr = (float)($credits[$i] ?? 0);
        
        if ($acc > 0 && ($dr > 0 || $cr > 0)) {
            $lines[] = [
                'account_id' => $acc,
                'debit' => $dr,
                'credit' => $cr
            ];
            $total_debit += $dr;
            $total_credit += $cr;
        }
    }
    
    if (abs($total_debit - $total_credit) > 0.01) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Error: Total Debits must equal Total Credits.</div>";
    } elseif (empty($lines)) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Error: Please add at least one valid journal line.</div>";
    } else {
        try {
            $journal_id = $tm->createJournalEntry($date, 'manual', null, $description, $lines, $branch_id);
            $message = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Journal Entry #$journal_id recorded successfully.</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Error: " . $e->getMessage() . "</div>";
        }
    }
}
}

$accounts = $conn->query("SELECT id, code, name FROM chart_of_accounts ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);
$accountOptions = "<option value=''>Select Account...</option>";
foreach ($accounts as $acc) {
    $accountOptions .= "<option value='{$acc['id']}'>{$acc['code']} - {$acc['name']}</option>";
}
?>
<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title"><i class="bi bi-journal-plus"></i> Manual Journal Entry</h1>
    </div>
    
    <?= $message ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" id="journalForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label fw-bold">Description / Reference</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Adjusting entry for depreciation" required>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="journalTable">
                        <thead class="table-light">
                            <tr>
                                <th width="45%">Account</th>
                                <th width="25%">Debit (Dr)</th>
                                <th width="25%">Credit (Cr)</th>
                                <th width="5%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Two default rows -->
                            <tr>
                                <td>
                                    <select name="account_id[]" class="form-select account-select" required>
                                        <?= $accountOptions ?>
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" min="0" name="debit[]" class="form-control debit-input" value="0"></td>
                                <td><input type="number" step="0.01" min="0" name="credit[]" class="form-control credit-input" value="0"></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" disabled><i class="bi bi-trash"></i></button></td>
                            </tr>
                            <tr>
                                <td>
                                    <select name="account_id[]" class="form-select account-select" required>
                                        <?= $accountOptions ?>
                                    </select>
                                </td>
                                <td><input type="number" step="0.01" min="0" name="debit[]" class="form-control debit-input" value="0"></td>
                                <td><input type="number" step="0.01" min="0" name="credit[]" class="form-control credit-input" value="0"></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row" disabled><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td class="text-end">Total:</td>
                                <td id="totalDebit" class="text-primary fs-5">0.00</td>
                                <td id="totalCredit" class="text-primary fs-5">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-outline-secondary" id="addRowBtn"><i class="bi bi-plus-lg"></i> Add Line</button>
                    <button type="submit" class="btn btn-primary px-5" id="submitBtn"><i class="bi bi-check-lg"></i> Post Journal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('#journalTable tbody');
    const addRowBtn = document.getElementById('addRowBtn');
    const totalDebitEl = document.getElementById('totalDebit');
    const totalCreditEl = document.getElementById('totalCredit');
    const submitBtn = document.getElementById('submitBtn');

    // Add new row
    addRowBtn.addEventListener('click', function() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="account_id[]" class="form-select account-select" required>
                    <?= $accountOptions ?>
                </select>
            </td>
            <td><input type="number" step="0.01" min="0" name="debit[]" class="form-control debit-input" value="0"></td>
            <td><input type="number" step="0.01" min="0" name="credit[]" class="form-control credit-input" value="0"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-trash"></i></button></td>
        `;
        tableBody.appendChild(tr);
        calculateTotals();
    });

    // Remove row and Event delegation for inputs
    tableBody.addEventListener('click', function(e) {
        if (e.target.closest('.remove-row')) {
            const rowCount = tableBody.querySelectorAll('tr').length;
            if (rowCount > 2) {
                e.target.closest('tr').remove();
                calculateTotals();
            } else {
                alert("A journal entry must have at least two lines.");
            }
        }
    });

    tableBody.addEventListener('input', function(e) {
        if (e.target.classList.contains('debit-input') || e.target.classList.contains('credit-input')) {
            // Prevent entering both debit and credit in the same line (optional UX improvement)
            if (e.target.classList.contains('debit-input') && parseFloat(e.target.value) > 0) {
                e.target.closest('tr').querySelector('.credit-input').value = 0;
            } else if (e.target.classList.contains('credit-input') && parseFloat(e.target.value) > 0) {
                e.target.closest('tr').querySelector('.debit-input').value = 0;
            }
            calculateTotals();
        }
    });

    function calculateTotals() {
        let totalDr = 0;
        let totalCr = 0;
        
        document.querySelectorAll('.debit-input').forEach(input => {
            totalDr += parseFloat(input.value) || 0;
        });
        document.querySelectorAll('.credit-input').forEach(input => {
            totalCr += parseFloat(input.value) || 0;
        });
        
        totalDebitEl.textContent = totalDr.toFixed(2);
        totalCreditEl.textContent = totalCr.toFixed(2);
        
        if (totalDr > 0 && totalDr === totalCr) {
            totalDebitEl.classList.replace('text-danger', 'text-success');
            totalCreditEl.classList.replace('text-danger', 'text-success');
            totalDebitEl.classList.replace('text-primary', 'text-success');
            totalCreditEl.classList.replace('text-primary', 'text-success');
            submitBtn.disabled = false;
        } else {
            totalDebitEl.classList.replace('text-success', 'text-danger');
            totalCreditEl.classList.replace('text-success', 'text-danger');
            totalDebitEl.classList.replace('text-primary', 'text-danger');
            totalCreditEl.classList.replace('text-primary', 'text-danger');
            submitBtn.disabled = true;
        }
    }
    
    // Initial calculation
    calculateTotals();
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
