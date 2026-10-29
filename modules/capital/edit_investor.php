<?php
/**
 * Edit Investor
 */

// Enable Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Edit Investor';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/Validator.php';

$id = $_GET['id'] ?? 0;
$errors = [];

// Fetch Investor
$stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ? AND type = 'investor'");
$stmt->bind_param("i", $id);
$stmt->execute();
$investor = $stmt->get_result()->fetch_assoc();

if (!$investor) {
    setFlashMessage('error', 'Investor not found.');
    redirect('investors.php');
}

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
                'phone' => "nullable|phone|unique:contacts,phone,$id",
                'email' => "nullable|email|unique:contacts,email,$id"
            ];

            $validator = new Validator($conn);

            if ($validator->validate($data, $rules)) {
                 $name = cleanInput($data['name']);
                 $phone = cleanInput($data['phone']);
                 $email = cleanInput($data['email']);

                $stmt = $conn->prepare("UPDATE contacts SET name = ?, phone = ?, email = ? WHERE id = ?");
                
                if ($stmt === false) {
                    throw new Exception("Database Prepare Error: " . $conn->error);
                }
                
                $stmt->bind_param("sssi", $name, $phone, $email, $id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Database Execute Error: " . $stmt->error);
                }

                // Success
                logActivity('update_investor', 'capital', $id, $investor, $data, "Updated investor: $name");
                setFlashMessage('success', 'Investor updated successfully!');
                redirect('investors.php');

            } else {
                $errors = array_values($validator->getErrors());
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "System Error: " . $e->getMessage();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Edit Investor</h5>
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
                            <input type="text" class="form-control" name="name" required value="<?= escape($investor['name']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" class="form-control" name="phone" value="<?= escape($investor['phone']) ?>" placeholder="Optional">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" value="<?= escape($investor['email']) ?>" placeholder="Optional">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Investor
                            </button>
                            <a href="investors.php" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
