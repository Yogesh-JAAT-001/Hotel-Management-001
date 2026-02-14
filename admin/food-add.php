<?php
require_once '../config.php';
require_once '../includes/food-admin-helper.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Add Food Item';
$schema = foodAdminSchema($pdo);
$categories = foodAdminCategoryOptions($pdo);

$form = [
    'food_name' => '',
    'category' => 'Main Course',
    'type' => 'VEG',
    'price' => '',
    'description' => '',
    'status' => 'available'
];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrfToken();

    $form['food_name'] = trim((string)($_POST['food_name'] ?? ''));
    $form['category'] = trim((string)($_POST['category'] ?? 'Main Course'));
    $form['type'] = foodAdminNormalizeType($_POST['type'] ?? 'VEG');
    $form['price'] = trim((string)($_POST['price'] ?? ''));
    $form['description'] = trim((string)($_POST['description'] ?? ''));
    $form['status'] = strtolower(trim((string)($_POST['status'] ?? 'available')));

    $errors = foodAdminValidationErrors($form);

    $upload = foodAdminHandleImageUpload('image');
    if (!$upload['ok']) {
        $errors[] = $upload['message'];
    }

    if (empty($errors)) {
        try {
            $columns = [];
            $placeholders = [];
            $params = [];

            $columns[] = $schema['name'];
            $placeholders[] = ':food_name';
            $params[':food_name'] = $form['food_name'];

            if ($schema['category']) {
                $columns[] = $schema['category'];
                $placeholders[] = ':category';
                $params[':category'] = $form['category'];
            }

            if ($schema['type']) {
                $columns[] = $schema['type'];
                $placeholders[] = ':type';
                $params[':type'] = $form['type'];
            }

            $columns[] = $schema['price'];
            $placeholders[] = ':price';
            $params[':price'] = (float)$form['price'];

            if ($schema['description']) {
                $columns[] = $schema['description'];
                $placeholders[] = ':description';
                $params[':description'] = $form['description'];
            }

            if ($schema['image']) {
                $columns[] = $schema['image'];
                $placeholders[] = ':image_path';
                $params[':image_path'] = $upload['path'] ?? '';
            }

            if ($schema['status']) {
                $columns[] = $schema['status'];
                $placeholders[] = ':status';
                $params[':status'] = foodAdminNormalizeStatus($form['status'], $schema['status_numeric']);
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $schema['table'],
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $newFoodId = (int)$pdo->lastInsertId();
            logAdminAction('food_create', 'FOOD_DINING', (string)$newFoodId, 'name=' . $form['food_name']);

            redirect('food.php?success=added');
        } catch (Throwable $e) {
            logSystemError('admin_food_add', (string)$e->getMessage(), 'name=' . $form['food_name']);
            $errors[] = 'Unable to add food item right now.';
        }
    }
}

include '../includes/header.php';
?>

<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="food.php"><i class="fas fa-arrow-left me-2"></i>Back to Food Management</a>
    </div>
</nav>

<div class="container" style="margin-top: 90px; max-width: 900px;">
    <div class="card card-luxury">
        <div class="card-body p-4">
            <h2 class="h4 mb-4">Add New Food Item</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>">

                <div class="col-md-8">
                    <label class="form-label" for="food_name">Name</label>
                    <input type="text" id="food_name" name="food_name" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($form['food_name']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="type">Type</label>
                    <select id="type" name="type" class="form-select" required>
                        <option value="VEG" <?php echo $form['type'] === 'VEG' ? 'selected' : ''; ?>>VEG</option>
                        <option value="NON-VEG" <?php echo $form['type'] === 'NON-VEG' ? 'selected' : ''; ?>>NON-VEG</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="category">Category</label>
                    <select id="category" name="category" class="form-select" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $form['category'] === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="price">Price (INR)</label>
                    <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" required value="<?php echo htmlspecialchars($form['price']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="available" <?php echo $form['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="unavailable" <?php echo $form['status'] === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($form['description']); ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="image">Upload Image</label>
                    <input type="file" id="image" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <div class="form-text">Allowed: JPG, JPEG, PNG, WEBP. Max size: 2MB.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Preview</label>
                    <div class="border rounded p-2 bg-light" style="min-height: 220px;">
                        <img
                            id="imagePreview"
                            src="<?php echo htmlspecialchars(appPath('/assets/images/food/default_food.jpg')); ?>"
                            alt="Image Preview"
                            style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 10px;"
                        >
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    <a href="food.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-luxury"><i class="fas fa-save me-1"></i>Add Food</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const input = document.getElementById('image');
const preview = document.getElementById('imagePreview');

input.onchange = function () {
    if (!this.files || !this.files[0]) {
        preview.src = '<?php echo htmlspecialchars(appPath('/assets/images/food/default_food.jpg')); ?>';
        return;
    }
    preview.src = URL.createObjectURL(this.files[0]);
};
</script>

<?php include '../includes/footer.php'; ?>
