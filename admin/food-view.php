<?php
require_once '../config.php';
require_once '../includes/food-admin-helper.php';

if (!isAdmin()) {
    redirect('login.php');
}

$foodId = (int)($_GET['id'] ?? 0);
$item = null;
$error = '';

if ($foodId <= 0) {
    $error = 'Invalid food item ID.';
} else {
    try {
        $item = foodAdminFindById($pdo, $foodId);
        if (!$item) {
            $error = 'Food item not found.';
        }
    } catch (Throwable $e) {
        error_log('Food view error: ' . $e->getMessage());
        $error = 'Unable to load food item details.';
    }
}

$page_title = 'View Food Item';
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
            <h2 class="h4 mb-4">Food Item Details</h2>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <a href="food.php" class="btn btn-outline-secondary">Back</a>
            <?php else: ?>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <img
                            src="<?php echo htmlspecialchars($item['image_url']); ?>"
                            alt="<?php echo htmlspecialchars($item['food_name']); ?>"
                            data-food-id="<?php echo (int)$item['food_id']; ?>"
                            class="img-fluid rounded shadow"
                            style="width: 100%; max-height: 420px; object-fit: cover;"
                            onerror="handleSingleFoodImageError(this);"
                        >
                    </div>

                    <div class="col-lg-6">
                        <h3 class="mb-3"><?php echo htmlspecialchars($item['food_name']); ?></h3>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category']); ?></span>
                            <span class="badge <?php echo $item['type'] === 'VEG' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo htmlspecialchars($item['type']); ?>
                            </span>
                            <span class="badge <?php echo $item['is_available'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo htmlspecialchars($item['status_label']); ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <div class="text-muted small">Price</div>
                            <div class="fs-4 fw-bold text-warning">₹<?php echo number_format((float)$item['price'], 2); ?></div>
                        </div>

                        <div class="mb-3">
                            <div class="text-muted small">Description</div>
                            <p class="mb-0" style="line-height: 1.6;"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <a href="food-edit.php?id=<?php echo (int)$item['food_id']; ?>" class="btn btn-luxury">
                                <i class="fas fa-edit me-1"></i>Edit Item
                            </a>
                            <a href="food-delete.php?id=<?php echo (int)$item['food_id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this item?');">
                                <i class="fas fa-trash me-1"></i>Delete
                            </a>
                            <a href="food.php" class="btn btn-outline-secondary">Back to List</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
async function handleSingleFoodImageError(imageEl) {
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
