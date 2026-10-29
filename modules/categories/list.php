<?php
/**
 * Category Management - Simple CRUD
 */

$pageTitle = 'Categories';
require_once __DIR__ . '/../../includes/auth_check.php';

$errors = [];
$success = false;

// Handle Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $name = cleanInput($_POST['name'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        
        if (empty($name)) {
            $errors[] = 'Category name is required';
        } else {
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Category created successfully');
                header("Location: " . url("/modules/categories/list.php"));
                exit;
            } else {
                $errors[] = 'Failed to create category';
            }
            $stmt->close();
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    // Strict Rule: Only Admin can Delete
    if (!isAdmin()) {
        setFlashMessage('error', 'Access Denied: Admin privileges required to delete categories.');
        header("Location: " . url("/modules/categories/list.php"));
        exit;
    }

    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        setFlashMessage('success', 'Category deleted successfully');
    } else {
        setFlashMessage('error', 'Failed to delete category. It may be in use.');
    }
    $stmt->close();
    header("Location: " . url("/modules/categories/list.php"));
    exit;
}

// Get all categories
$result = $conn->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
    GROUP BY c.id
    ORDER BY c.name ASC
");
$categories = $result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title">
            <i class="bi bi-tags"></i> Categories
        </h1>
        <button class="btn btn-primary d-none d-md-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg"></i> Add Category
        </button>
    </div>
    
    <!-- Mobile Floating Action Button -->
    <button id="mobileFabBtn" class="btn btn-primary d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-lg" style="font-size: 2rem;"></i>
    </button>
    
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
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Products</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><strong><?= escape($category['name']) ?></strong></td>
                                    <td><?= escape($category['description'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= $category['product_count'] ?> products</span>
                                    </td>
                                    <td><?= formatDate($category['created_at']) ?></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <?php if (isAdmin()): ?>
                                            <a href="<?= url('/modules/categories/edit.php?id=' . $category['id']) ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteCategory(<?= $category['id'] ?>, '<?= escape($category['name']) ?>')"
                                                    <?= $category['product_count'] > 0 ? 'disabled title="Cannot delete category with products"' : 'title="Delete"' ?>>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <span class="text-muted small">View Only</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="bi bi-inbox text-muted" style="font-size: 48px;"></i>
                                        <p class="text-muted mt-3">No categories found</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Create Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteCategory(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?')) {
        window.location.href = '?delete=' + id;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
