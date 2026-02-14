<?php
require_once '../config.php';
require_once '../includes/food-admin-helper.php';

if (!isAdmin()) {
    redirect('login.php');
}

$schema = foodAdminSchema($pdo);
$foodId = (int)($_REQUEST['id'] ?? $_POST['food_id'] ?? 0);

if ($foodId <= 0) {
    redirect('food.php');
}

$item = null;
try {
    $item = foodAdminFindById($pdo, $foodId);
} catch (Throwable $e) {
    error_log('Food delete load error: ' . $e->getMessage());
}

if (!$item) {
    redirect('food.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireCsrfToken();

    try {
        $stmt = $pdo->prepare("DELETE FROM {$schema['table']} WHERE {$schema['id']} = :food_id LIMIT 1");
        $stmt->execute([':food_id' => $foodId]);

        if ((int)$stmt->rowCount() > 0) {
            foodAdminDeleteImageFile($item['image_path'] ?? '');
            logAdminAction('food_delete', 'FOOD_DINING', (string)$foodId, 'name=' . ($item['food_name'] ?? ''));
        }

        redirect('food.php?success=deleted');
    } catch (Throwable $e) {
        logSystemError('admin_food_delete', (string)$e->getMessage(), 'food_id=' . $foodId);
        redirect('food.php?error=' . urlencode('Unable to delete food item right now.'));
    }
}

$page_title = 'Delete Food Item';
include '../includes/header.php';
?>

<nav class="navbar navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="food.php"><i class="fas fa-arrow-left me-2"></i>Back to Food Management</a>
    </div>
</nav>

<div class="container" style="margin-top: 90px; max-width: 840px;">
    <div class="card card-luxury border border-danger">
        <div class="card-body p-4">
            <h2 class="h4 text-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h2>
            <p class="mb-3">This action will permanently remove the food item and its uploaded image file.</p>

            <div class="d-flex gap-3 align-items-center mb-3">
                <img
                    src="<?php echo htmlspecialchars($item['image_url']); ?>"
                    alt="<?php echo htmlspecialchars($item['food_name']); ?>"
                    style="width: 140px; height: 110px; object-fit: cover; border-radius: 10px;"
                    onerror="this.onerror=null; this.src='<?php echo htmlspecialchars(appPath('/assets/images/food/default_food.jpg')); ?>';"
                >
                <div>
                    <div class="fw-bold"><?php echo htmlspecialchars($item['food_name']); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($item['category']); ?> • <?php echo htmlspecialchars($item['type']); ?></div>
                    <div class="text-warning fw-bold">₹<?php echo number_format((float)$item['price'], 2); ?></div>
                </div>
            </div>

            <form method="post" class="d-flex gap-2">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>">
                <input type="hidden" name="food_id" value="<?php echo (int)$item['food_id']; ?>">
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Yes, Delete</button>
                <a href="food.php" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
