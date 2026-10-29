<?php
$pageTitle = 'Edit Category';
require_once __DIR__ . '/../../includes/auth_check.php';

// Strict Rule: Only Admin can Edit
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

if ($id <= 0) {
    setFlashMessage('error', 'Invalid category ID');
    redirect(url('/modules/categories/list.php'));
}

$stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();
$stmt->close();

if (!$category) {
    setFlashMessage('error', 'Category not found');
    redirect(url('/modules/categories/list.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $name = cleanInput($_POST['name'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name)) {
            $errors[] = 'Category name is required';
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param("ssii", $name, $description, $isActive, $id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Category updated successfully!');
                redirect(url('/modules/categories/list.php'));
            } else {
                $errors[] = 'Failed to update category';
            }
            $stmt->close();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-pencil-square"></i> Edit Category</h1>
        <a href="<?= url('/modules/categories/list.php') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= escape($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= escape($category['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= escape($category['description']) ?></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                   <?= ($category['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Category
                        </button>
                        <a href="<?= url('/modules/categories/list.php') ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
