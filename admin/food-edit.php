<?php
require_once '../config.php';
require_once '../includes/food-admin-helper.php';

if (!isAdmin()) {
    redirect('login.php');
}

$foodId = (int)($_GET['id'] ?? 0);
$item = null;
$error = '';
$flashError = trim((string)($_GET['error'] ?? ''));
$categories = [];

if ($foodId <= 0) {
    $error = 'Invalid food item ID.';
} else {
    try {
        $item = foodAdminFindById($pdo, $foodId);
        if (!$item) {
            $error = 'Food item not found.';
        }
        $categories = foodAdminCategoryOptions($pdo);
    } catch (Throwable $e) {
        error_log('Food edit load error: ' . $e->getMessage());
        $error = 'Unable to load food item for editing.';
    }
}

$page_title = 'Edit Food Item';
include '../includes/header.php';
?>

<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="food.php"><i class="fas fa-arrow-left me-2"></i>Back to Food Management</a>
    </div>
</nav>

<div class="container" style="margin-top: 90px; max-width: 980px;">
    <div class="card card-luxury">
        <div class="card-body p-4">
            <h2 class="h4 mb-4">Edit Food Item</h2>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <a href="food.php" class="btn btn-outline-secondary">Back</a>
            <?php else: ?>
                <?php if ($flashError !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($flashError); ?></div>
                <?php endif; ?>
                <form action="food-update.php" method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>">
                    <input type="hidden" name="food_id" value="<?php echo (int)$item['food_id']; ?>">

                    <div class="col-md-8">
                        <label class="form-label" for="food_name">Name</label>
                        <input type="text" id="food_name" name="food_name" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($item['food_name']); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="type">Type</label>
                        <select id="type" name="type" class="form-select" required>
                            <option value="VEG" <?php echo $item['type'] === 'VEG' ? 'selected' : ''; ?>>VEG</option>
                            <option value="NON-VEG" <?php echo $item['type'] === 'NON-VEG' ? 'selected' : ''; ?>>NON-VEG</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="category">Category</label>
                        <select id="category" name="category" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $item['category'] === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="price">Price (INR)</label>
                        <input type="number" id="price" name="price" class="form-control" min="0" step="0.01" required value="<?php echo htmlspecialchars((string)$item['price']); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="available" <?php echo $item['is_available'] ? 'selected' : ''; ?>>Available</option>
                            <option value="unavailable" <?php echo !$item['is_available'] ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="image">Replace Image</label>
                        <input type="file" id="image" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="form-text">Allowed: JPG, JPEG, PNG, WEBP. Max size: 2MB.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Current / New Preview</label>
                        <div class="border rounded p-2 bg-light" style="min-height: 220px;">
                            <img
                                id="imagePreview"
                                src="<?php echo htmlspecialchars($item['image_url']); ?>"
                                alt="Image Preview"
                                data-food-id="<?php echo (int)$item['food_id']; ?>"
                                style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 10px;"
                                onerror="handleEditFoodImageError(this);"
                            >
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <a href="food.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-luxury"><i class="fas fa-save me-1"></i>Save Changes</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const input = document.getElementById('image');
const preview = document.getElementById('imagePreview');

if (input && preview) {
    input.onchange = function () {
        if (!this.files || !this.files[0]) {
            return;
        }
        preview.src = URL.createObjectURL(this.files[0]);
    };
}

async function handleEditFoodImageError(imageEl) {
    const fallback = '<?php echo htmlspecialchars(appPath('/assets/images/food/default_food.jpg')); ?>';
    imageEl.onerror = null;
    imageEl.src = fallback;

    const foodId = Number(imageEl.dataset.foodId || 0);
    if (!foodId || imageEl.dataset.fixAttempted === '1') {
        return;
    }
    imageEl.dataset.fixAttempted = '1';

    try {
        const response = await apiRequest('../api/admin/fix-missing-food-images.php', {
            method: 'POST',
            body: JSON.stringify({ food_id: foodId })
        });
        const item = (response.data && Array.isArray(response.data.items) && response.data.items[0]) ? response.data.items[0] : null;
        if (item && item.image_path) {
            imageEl.src = '<?php echo htmlspecialchars(rtrim(appPath('/'), '/')); ?>/' + String(item.image_path).replace(/^\/+/, '') + '?v=' + Date.now();
        }
    } catch (error) {
        // Keep fallback image silently.
    }
}
</script>

<?php include '../includes/footer.php'; ?>
