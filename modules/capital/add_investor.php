<?php
/**
 * Add New Investor
 * Simplified interface for adding investors/lenders
 */


// Enable Error Reporting for Debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Add Investor';
require_once __DIR__ . '/../../includes/auth_check.php';
// We need functions.php. auth_check.php includes it.
require_once __DIR__ . '/../../includes/Validator.php';

$errors = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token';
        } else {
            $data = [
                'name' => $_POST['name'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'email' => $_POST['email'] ?? '',
            ];

            $rules = [
                'name' => 'required|min:3',
                'phone' => 'nullable|phone|unique:contacts,phone',
                'email' => 'nullable|email|unique:contacts,email'
            ];

            $validator = new Validator($conn);

            if ($validator->validate($data, $rules)) {
                 // ... rest of logic is inside try block
                 $name = cleanInput($data['name']);
                 $phone = cleanInput($data['phone']);
                 $email = cleanInput($data['email']);

                $stmt = $conn->prepare("INSERT INTO contacts (type, name, phone, email, credit_limit) VALUES ('investor', ?, ?, ?, 0)");
                
                if ($stmt === false) {
                    // Fallback
                    $stmt = $conn->prepare("INSERT INTO contacts (type, name, phone, email) VALUES ('investor', ?, ?, ?)");
                    if ($stmt === false) {
                        throw new Exception("Database Prepare Error: " . $conn->error);
                    } else {
                        $stmt->bind_param("sss", $name, $phone, $email);
                        if (!$stmt->execute()) {
                            throw new Exception("Database Execute Error: " . $stmt->error);
                        }
                        // Success
                        logActivity('create_investor', 'capital', $stmt->insert_id, null, $data, "Added new investor: $name");
                        setFlashMessage('success', 'Investor added successfully!');
                        redirect('form.php?type=injection');
                    }
                } else {
                    $stmt->bind_param("sss", $name, $phone, $email);
                    if (!$stmt->execute()) {
                        throw new Exception("Database Execute Error: " . $stmt->error);
                    }
                     // Success
                    logActivity('create_investor', 'capital', $stmt->insert_id, null, $data, "Added new investor: $name");
                    setFlashMessage('success', 'Investor added successfully!');
                    redirect('form.php?type=injection');
                }
            } else {
                $errors = array_values($validator->getErrors());
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "System Error: " . $e->getMessage();
} catch (Error $e) {
    $errors[] = "Fatal Error: " . $e->getMessage();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add New Investor / Lender</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= escape($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Investor Name *</label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g. Brother, Bank Name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="phone" placeholder="Optional">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" placeholder="Optional">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save & Continue
                            </button>
                            <a href="form.php?type=injection" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
