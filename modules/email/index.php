<?php
$pageTitle = 'Manual Email Station';
require_once __DIR__ . '/../../includes/auth_check.php';
requireAdmin();

require_once __DIR__ . '/../../includes/header.php';

// Fetch all customers and suppliers who have email addresses
$contactsStmt = $conn->query("SELECT id, type, name, email FROM contacts WHERE email IS NOT NULL AND email != '' ORDER BY type, name");
$contacts = [];
if ($contactsStmt) {
    while ($row = $contactsStmt->fetch_assoc()) {
        $contacts[] = $row;
    }
}
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title"><i class="bi bi-envelope-paper"></i> Email Station</h1>
            <p class="text-muted mb-0">Send targeted, branded emails directly to your clients and suppliers.</p>
        </div>
        <div>
            <button class="btn btn-outline-primary rounded-pill shadow-sm" onclick="triggerDailyReport(this)">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> Send Auto Daily Report
            </button>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                    <h5 class="mb-0 text-primary"><i class="bi bi-pen me-2"></i> Compose Email</h5>
                </div>
                <div class="card-body p-4">
                    <form id="emailStationForm">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Recipient</label>
                            <select class="form-select form-select-lg" id="recipientType" onchange="toggleRecipientInput()">
                                <option value="contact">Select Existing Customer/Supplier</option>
                                <option value="custom">Type Custom Email Address</option>
                            </select>
                        </div>
                        
                        <div class="mb-4" id="contactSelectBlock">
                            <label class="form-label text-muted small">Select Contact</label>
                            <select class="form-select select2-init" id="contactId">
                                <option value="">-- Choose Contact --</option>
                                <?php 
                                $currentType = '';
                                foreach ($contacts as $c): 
                                    if ($currentType !== $c['type']):
                                        if ($currentType !== '') echo '</optgroup>';
                                        $currentType = $c['type'];
                                        echo '<optgroup label="' . ucfirst($currentType) . 's">';
                                    endif;
                                ?>
                                    <option value="<?= escape($c['email']) ?>"><?= escape($c['name']) ?> (<?= escape($c['email']) ?>)</option>
                                <?php endforeach; if ($currentType !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4 d-none" id="customEmailBlock">
                            <label class="form-label text-muted small">Custom Email Address</label>
                            <input type="email" class="form-control" id="customEmail" placeholder="e.g. client@example.com">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Subject</label>
                            <input type="text" class="form-control" id="emailSubject" required placeholder="Enter email subject">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Message Layout</label>
                            <textarea class="form-control" id="emailMessage" rows="8" required placeholder="Type your message here..."></textarea>
                            <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i> Your message will be automatically wrapped in your company's official email branding.</div>
                        </div>
                        
                        <div class="d-grid mt-5">
                            <button type="button" class="btn btn-primary btn-lg rounded-pill shadow-sm" id="sendBtn" onclick="sendCustomEmail()">
                                <i class="bi bi-send-fill me-2"></i> Send securely via OneSignal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if ($.fn.select2) {
        $('.select2-init').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    }
});

function toggleRecipientInput() {
    const type = document.getElementById('recipientType').value;
    if (type === 'custom') {
        document.getElementById('contactSelectBlock').classList.add('d-none');
        document.getElementById('customEmailBlock').classList.remove('d-none');
    } else {
        document.getElementById('contactSelectBlock').classList.remove('d-none');
        document.getElementById('customEmailBlock').classList.add('d-none');
    }
}

function sendCustomEmail() {
    const type = document.getElementById('recipientType').value;
    let targetEmail = '';
    
    if (type === 'contact') {
        targetEmail = document.getElementById('contactId').value;
    } else {
        targetEmail = document.getElementById('customEmail').value;
    }
    
    const subject = document.getElementById('emailSubject').value.trim();
    const message = document.getElementById('emailMessage').value.trim();
    
    if (!targetEmail) { alert('Please select or enter a recipient email.'); return; }
    if (!subject) { alert('Please enter a subject.'); return; }
    if (!message) { alert('Please enter a message.'); return; }

    const btn = document.getElementById('sendBtn');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Dispatching...';
    btn.disabled = true;
    
    fetch('<?= url('/modules/email/api/send_custom.php') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ email: targetEmail, subject: subject, message: message })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Email dispatched perfectly!');
            document.getElementById('emailSubject').value = '';
            document.getElementById('emailMessage').value = '';
        } else {
            alert('Error: ' + (data.message || 'Failed to send email.'));
        }
    })
    .catch(err => {
        alert('Network or server error occurred.');
    })
    .finally(() => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    });
}

function triggerDailyReport(btn) {
    if(!confirm('Are you sure you want to generate and send the Daily Report now?')) return;
    
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Generating...';
    btn.disabled = true;
    
    fetch('<?= url('/modules/email/api/send_daily_report.php') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('Daily Report dispatched successfully!');
        } else {
            alert('Error: ' + (data.message || 'Failed to dispatch report.'));
        }
    })
    .catch(err => {
        alert('Network or server error occurred.');
    })
    .finally(() => {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
