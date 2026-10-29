<?php
/**
 * Capital Management Dashboard
 * Shows total investment, withdrawals, and net capital
 */

$pageTitle = 'Capital Management';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Get Summary Data
$injectResult = $conn->query("SELECT SUM(amount) as total FROM capital_transactions WHERE type IN ('injection', 'add')");
$totalInjection = $injectResult->fetch_assoc()['total'] ?? 0;

// 2. Total Withdrawals
$withdrawResult = $conn->query("SELECT SUM(amount) as total FROM capital_transactions WHERE type IN ('withdrawal', 'withdraw')");
$totalWithdrawal = $withdrawResult->fetch_assoc()['total'] ?? 0;

// 3. Net Capital
$netCapital = $totalInjection - $totalWithdrawal;

// Prepare Chart Data
// Month-wise trend
$trendQuery = "
    SELECT 
        DATE_FORMAT(trans_date, '%Y-%m') as month,
        SUM(CASE WHEN type IN ('injection', 'add') THEN amount ELSE 0 END) as injection,
        SUM(CASE WHEN type IN ('withdrawal', 'withdraw') THEN amount ELSE 0 END) as withdrawal
    FROM capital_transactions 
    GROUP BY month 
    ORDER BY month ASC 
    LIMIT 12
";
$trendResult = $conn->query($trendQuery);
$months = [];
$injections = [];
$withdrawals = [];
while ($row = $trendResult->fetch_assoc()) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $injections[] = $row['injection'];
    $withdrawals[] = $row['withdrawal'];
}

// Investor Distribution (Net Balance)
$investorQuery = "
    SELECT 
        c.name, 
        (
            COALESCE(SUM(CASE WHEN ct.type IN ('injection', 'add') THEN ct.amount ELSE 0 END), 0) - 
            COALESCE(SUM(CASE WHEN ct.type IN ('withdrawal', 'withdraw') THEN ct.amount ELSE 0 END), 0)
        ) as total 
    FROM contacts c
    JOIN capital_transactions ct ON ct.investor_id = c.id 
    GROUP BY c.id 
    HAVING total > 0
    ORDER BY total DESC
";
$invResult = $conn->query($investorQuery);
$investorNames = [];
$investorAmounts = [];
while ($row = $invResult->fetch_assoc()) {
    $investorNames[] = $row['name'];
    $investorAmounts[] = $row['total'];
}

// Fetch Financial Accounts for Modal
$accountsStmt = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active = 1 ORDER BY account_name");
$accounts = $accountsStmt->fetch_all(MYSQLI_ASSOC);

?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title">
            <i class="bi bi-bank"></i> Capital Management
        </h1>
        <div class="d-flex gap-2 flex-wrap mt-3 mt-md-0">
            <a href="investors.php" class="btn btn-outline-primary btn-lg rounded-pill shadow-sm d-none d-md-inline-flex">
                <i class="bi bi-people"></i> Manage Investors
            </a>
            <button type="button" class="btn btn-success btn-lg rounded-pill shadow-sm d-none d-md-inline-flex" data-bs-toggle="modal" data-bs-target="#capitalModal" onclick="setCapitalType('add')">
                <i class="bi bi-plus-lg"></i> Add Capital
            </button>
            <button type="button" class="btn btn-danger btn-lg rounded-pill shadow-sm d-none d-md-inline-flex" data-bs-toggle="modal" data-bs-target="#capitalModal" onclick="setCapitalType('withdraw')">
                <i class="bi bi-dash-lg"></i> Withdraw
            </button>
            <!-- Mobile alternate buttons that stay top -->
            <a href="investors.php" class="btn btn-outline-primary rounded-pill shadow-sm d-md-none flex-grow-1">
                <i class="bi bi-people"></i> Investors
            </a>
        </div>
    </div>
    
    <!-- Mobile Floating Action Button (Add Capital) -->
    <button type="button" id="mobileFabBtn" class="btn btn-success d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" data-bs-toggle="modal" data-bs-target="#capitalModal" onclick="setCapitalType('add')" style="bottom: 30px; right: 24px; width: 64px; height: 64px; z-index: 1050;">
        <i class="bi bi-plus-lg" style="font-size: 2rem;"></i>
    </button>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title opacity-75">Total Capital In</h6>
                    <h3 class="mb-0"><?= formatCurrency($totalInjection) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title opacity-75">Total Withdrawn</h6>
                    <h3 class="mb-0"><?= formatCurrency($totalWithdrawal) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title opacity-75">Net Business Capital</h6>
                    <h3 class="mb-0"><?= formatCurrency($netCapital) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Capital Flow Trend (Last 12 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="capitalTrendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Investor Share (Current Net)</h5>
                </div>
                <div class="card-body">
                    <canvas id="investorChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Transactions</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Investor / Source</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Get Transactions List with Investor and Financial Account joined
                        $transResult = $conn->query("
                            SELECT ct.*, 
                                   c.name as investor_name,
                                   (SELECT GROUP_CONCAT(fa.account_name SEPARATOR ', ') 
                                    FROM financial_transactions ft 
                                    JOIN financial_accounts fa ON ft.financial_account_id = fa.id 
                                    WHERE ft.reference_id = ct.id 
                                      AND ft.reference_type IN ('capital_injection', 'capital_withdrawal')
                                   ) as bank_name 
                            FROM capital_transactions ct 
                            LEFT JOIN contacts c ON ct.investor_id = c.id 
                            ORDER BY ct.trans_date DESC, ct.created_at DESC 
                            LIMIT 20
                        ");
                        
                        while ($row = $transResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('d M, Y', strtotime($row['trans_date'])) ?></td>
                            <td>
                                <strong><?= escape($row['investor_name'] ?? 'Main Business') ?></strong>
                            </td>
                            <td>
                                <?php if ($row['type'] == 'add'): ?>
                                    <span class="badge bg-success">Invest/Add</span>
                                <?php elseif ($row['type'] == 'injection'): ?>
                                    <span class="badge bg-success">Invest/Add</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Withdraw</span>
                                <?php endif; ?>
                            </td>
                            <td><?= escape($row['bank_name'] ?: ucfirst($row['method'] ?? 'Unknown Account')) ?></td>
                            <td>
                                <small class="text-muted"><?= escape($row['description']) ?></small>
                            </td>
                            <td class="text-end fw-bold <?= in_array($row['type'], ['add', 'injection']) ? 'text-success' : 'text-danger' ?>">
                                <?= in_array($row['type'], ['add', 'injection']) ? '+' : '-' ?> 
                                <?= formatCurrency($row['amount']) ?>
                            </td>
                            <td class="text-center">
                                <a href="delete_transaction.php?id=<?= $row['id'] ?>&csrf_token=<?= generateCSRFToken() ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('WARNING: Deleting this does NOT reverse financial balances automatically yet. Are you sure?')"
                                   title="Delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($transResult->num_rows == 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No transactions found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Manage Capital -->
<div class="modal fade" id="capitalModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-wallet2 text-primary"></i> <span id="modalTitle">Manage Capital</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <ul class="nav nav-tabs nav-justified px-3 pt-3 mb-3 border-bottom" id="capitalTypeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold text-success" id="tab-add" data-bs-toggle="tab" data-bs-target="#panel-form" type="button" role="tab" onclick="setCapitalType('add')">
                        <i class="bi bi-plus-circle"></i> Add Capital (Invest)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-danger" id="tab-withdraw" data-bs-toggle="tab" data-bs-target="#panel-form" type="button" role="tab" onclick="setCapitalType('withdraw')">
                        <i class="bi bi-dash-circle"></i> Withdraw Capital
                    </button>
                </li>
            </ul>

            <form id="capitalForm">
                <div class="modal-body pt-0">
                    <input type="hidden" name="type" id="capitalType" value="add">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="trans_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <!-- 4. Total Amount -->
                        <div class="col-12">
                            <label class="form-label fw-bold">Total Capital Amount (মোট টাকার পরিমাণ) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light text-success fw-bold" id="amountAddon">৳</span>
                                <input type="number" id="totalAmount" name="amount" class="form-control fw-bold fs-4" step="0.01" min="1" required placeholder="0.00" oninput="checkSplitValidation()">
                            </div>
                        </div>

                        <!-- 5. Split Account Sections -->
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold mb-0">Financial Accounts (অ্যাকাউন্ট) <span id="payment_action_badge" class="badge bg-success ms-2">MONEY IN/OUT</span></label>
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="addPaymentSource()">
                                    <i class="bi bi-plus-circle"></i> Payment Sources
                                </button>
                            </div>
                            
                            <div id="paymentSourcesContainer">
                                <!-- Initial Row -->
                                <div class="payment-source-row row g-2 mb-2 align-items-end rounded bg-light p-2 border">
                                    <div class="col-md-5">
                                        <label class="form-label small">Account</label>
                                        <select name="payment_accounts[]" class="form-select source-account" required>
                                            <option value="">Select Account</option>
                                            <?php foreach($accounts as $ac): ?>
                                                <option value="<?= $ac['id'] ?>"><?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Method</label>
                                        <select name="method[]" class="form-select">
                                            <option value="cash">Cash</option>
                                            <option value="mobile">Mobile</option>
                                            <option value="bank">Bank</option>
                                            <option value="card">Card</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text px-2">৳</span>
                                            <input type="number" name="payment_amounts[]" class="form-control source-amount" step="0.01" min="0.01" placeholder="0" required oninput="checkSplitValidation()">
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button type="button" class="btn btn-outline-danger remove-source-btn" onclick="removePaymentSource(this)" style="display:none;" title="Remove this split">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-2 text-end">
                                <span id="splitValidationMsg" class="text-danger fw-bold small" style="display: none;"><i class="bi bi-exclamation-triangle"></i> Amounts do not match Total</span>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Investor ID/Name</label>
                            <select class="form-select select2" name="investor_id">
                                <option value="">-- Main Business / Self --</option>
                                <?php 
                                $invResult = $conn->query("SELECT id, name FROM contacts WHERE type = 'investor' ORDER BY name");
                                while($inv = $invResult->fetch_assoc()): 
                                ?>
                                    <option value="<?= $inv['id'] ?>"><?= escape($inv['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-text">Optional. Requires pre-registered investor.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Reference</label>
                            <input type="text" class="form-control" name="reference" placeholder="Check #, Transaction ID, etc.">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description / Note</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Write any notes here..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4" id="submitBtn">
                        <i class="bi bi-save"></i> Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template for cloning rows safely -->
<template id="paymentRowTemplate">
    <div class="payment-source-row row g-2 mb-2 align-items-end rounded bg-light p-2 border">
        <div class="col-md-5">
            <label class="form-label small">Account</label>
            <select name="payment_accounts[]" class="form-select source-account" required>
                <option value="">Select Account</option>
                <?php foreach($accounts as $ac): ?>
                    <option value="<?= $ac['id'] ?>"><?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Method</label>
            <select name="method[]" class="form-select">
                <option value="cash">Cash</option>
                <option value="mobile">Mobile</option>
                <option value="bank">Bank</option>
                <option value="card">Card</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Amount</label>
            <div class="input-group">
                <span class="input-group-text px-2">৳</span>
                <input type="number" name="payment_amounts[]" class="form-control source-amount" step="0.01" min="0.01" placeholder="0" required oninput="checkSplitValidation()">
            </div>
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-outline-danger remove-source-btn" onclick="removePaymentSource(this)" title="Remove this split">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</template>

<script>
// Modal Logic
function setCapitalType(type) {
    document.getElementById('capitalType').value = type;
    const btn = document.getElementById('submitBtn');
    const badge = document.getElementById('payment_action_badge');
    const addon = document.getElementById('amountAddon');
    
    // Toggle active tab visually
    if (type === 'add') {
        document.getElementById('tab-add').classList.add('active');
        document.getElementById('tab-withdraw').classList.remove('active');
        
        btn.className = 'btn btn-success px-4';
        btn.innerHTML = '<i class="bi bi-plus-circle"></i> Add Capital';
        badge.textContent = 'Account Receiving Investment';
        addon.className = 'input-group-text bg-light text-success fw-bold';
    } else {
        document.getElementById('tab-withdraw').classList.add('active');
        document.getElementById('tab-add').classList.remove('active');
        
        btn.className = 'btn btn-danger px-4';
        btn.innerHTML = '<i class="bi bi-dash-circle"></i> Withdraw Capital';
        badge.textContent = 'Account Sending Withdrawal';
        addon.className = 'input-group-text bg-light text-danger fw-bold';
    }
}

// Split Validation Logic
function addPaymentSource() {
    const container = document.getElementById('paymentSourcesContainer');
    const template = document.getElementById('paymentRowTemplate');
    const clone = template.content.cloneNode(true);
    
    // Auto-fill logic
    const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
    const splitInputs = document.querySelectorAll('.source-amount');
    let sumSplit = 0;
    splitInputs.forEach(input => {
        sumSplit += parseFloat(input.value) || 0;
    });
    
    const newAmountInput = clone.querySelector('.source-amount');
    
    if (totalAmount > sumSplit) {
        newAmountInput.value = (totalAmount - sumSplit).toFixed(2);
    } else {
        newAmountInput.value = ""; 
    }
    
    container.appendChild(clone);
    updateRemoveButtons();
    checkSplitValidation();
}

function removePaymentSource(btn) {
    const row = btn.closest('.payment-source-row');
    if (row && document.querySelectorAll('.payment-source-row').length > 1) {
        row.remove();
        updateRemoveButtons();
        checkSplitValidation();
    }
}

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.payment-source-row');
    rows.forEach((row, index) => {
        const btn = row.querySelector('.remove-source-btn');
        if (btn) {
            btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
        }
    });
}

function checkSplitValidation() {
    const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
    const splitInputs = document.querySelectorAll('.source-amount');
    
    let sumSplit = 0;
    splitInputs.forEach(input => {
        sumSplit += parseFloat(input.value) || 0;
    });
    
    const diff = Math.abs(totalAmount - sumSplit);
    const msg = document.getElementById('splitValidationMsg');
    const btn = document.getElementById('submitBtn');
    
    // Allow small JS floating point differences (0.01)
    if (totalAmount > 0 && diff > 0.05) {
        msg.style.display = 'inline-block';
        msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Splits = ' + sumSplit.toFixed(2) + ' (Must equal ' + totalAmount.toFixed(2) + ')';
        btn.disabled = true;
        return false;
    } else {
        msg.style.display = 'none';
        btn.disabled = false;
        return true;
    }
}

document.getElementById('capitalForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
    btn.disabled = true;

    try {
        const formData = new FormData(this);
        const response = await fetch('<?= url("/modules/finance/api/process_capital.php") ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'An error occurred.');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Network error or server failed to respond.');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
if (typeof Chart !== 'undefined') {
    // Trend Chart
    const ctxTrend = document.getElementById('capitalTrendChart').getContext('2d');
    new Chart(ctxTrend, {
    type: 'bar',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [
            {
                label: 'Injected',
                data: <?= json_encode($injections) ?>,
                backgroundColor: 'rgba(25, 135, 84, 0.7)',
                borderColor: 'rgba(25, 135, 84, 1)',
                borderWidth: 1
            },
            {
                label: 'Withdrawn',
                data: <?= json_encode($withdrawals) ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

    // Investor Pie Chart
    const ctxInv = document.getElementById('investorChart').getContext('2d');
    new Chart(ctxInv, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($investorNames) ?>,
            datasets: [{
                data: <?= json_encode($investorAmounts) ?>,
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'
                ],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
