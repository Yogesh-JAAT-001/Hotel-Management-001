<?php
require_once '../config.php';
require_once '../includes/food-admin-helper.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Food & Dining Management';
include '../includes/header.php';

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'category' => trim((string)($_GET['category'] ?? '')),
    'type' => trim((string)($_GET['type'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? ''))
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$foodList = ['rows' => [], 'pagination' => ['page' => 1, 'total_pages' => 1, 'has_prev' => false, 'has_next' => false]];
$stats = ['total' => 0, 'veg' => 0, 'non_veg' => 0, 'available' => 0];
$categories = ['Starters', 'Soups', 'Main Course', 'Breads', 'Rice', 'Desserts', 'Beverages', 'Combos'];
$errorMessage = '';

try {
    $foodList = foodAdminList($pdo, $filters, $page, $perPage);
    $stats = foodAdminStats($pdo);
    $categories = foodAdminCategoryOptions($pdo);
} catch (Throwable $e) {
    error_log('Food list error: ' . $e->getMessage());
    $errorMessage = 'Unable to load food records right now.';
}

$success = trim((string)($_GET['success'] ?? ''));
$errorBanner = trim((string)($_GET['error'] ?? ''));
$messages = [
    'added' => 'Food item added successfully.',
    'updated' => 'Food item updated successfully.',
    'deleted' => 'Food item deleted successfully.'
];

function foodQueryWithPage($filters, $targetPage) {
    $payload = [
        'q' => $filters['q'] ?? '',
        'category' => $filters['category'] ?? '',
        'type' => $filters['type'] ?? '',
        'status' => $filters['status'] ?? '',
        'page' => $targetPage
    ];

    $payload = array_filter($payload, function ($value) {
        return $value !== '' && $value !== null;
    });

    return http_build_query($payload);
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-hotel me-2"></i><?php echo APP_NAME; ?> - Admin
        </a>

        <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#adminNavFood">
            <i class="fas fa-bars"></i>
        </button>

        <div class="collapse navbar-collapse" id="adminNavFood">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="reservations.php"><i class="fas fa-calendar-check me-1"></i>Reservations</a></li>
                <li class="nav-item"><a class="nav-link" href="rooms.php"><i class="fas fa-bed me-1"></i>Rooms</a></li>
                <li class="nav-item"><a class="nav-link active" href="food.php"><i class="fas fa-utensils me-1"></i>Food & Dining</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-1"></i>Reports</a></li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../" target="_blank">View Website</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout(); return false;">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid" style="margin-top: 90px;">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="h3 mb-0">Food & Dining Management</h1>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-warning" onclick="runFoodImageMapper()">
                    <i class="fas fa-wand-magic-sparkles me-1"></i>Auto Map Images
                </button>
                <button type="button" class="btn btn-outline-info" onclick="fixMissingFoodImages()">
                    <i class="fas fa-image me-1"></i>Fix Missing Images
                </button>
                <a href="food-add.php" class="btn btn-luxury">
                    <i class="fas fa-plus me-1"></i>Add Food Item
                </a>
            </div>
        </div>
    </div>

    <?php if ($success !== '' && isset($messages[$success])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($messages[$success]); ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>
    <?php if ($errorBanner !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorBanner); ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card card-luxury h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Items</h6>
                    <div class="fs-4 fw-bold"><?php echo (int)$stats['total']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card card-luxury h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Veg</h6>
                    <div class="fs-4 fw-bold text-success"><?php echo (int)$stats['veg']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card card-luxury h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Non-Veg</h6>
                    <div class="fs-4 fw-bold text-danger"><?php echo (int)$stats['non_veg']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card card-luxury h-100">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Available</h6>
                    <div class="fs-4 fw-bold text-warning"><?php echo (int)$stats['available']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-luxury mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="q">Search</label>
                    <input type="text" id="q" name="q" class="form-control" value="<?php echo htmlspecialchars($filters['q']); ?>" placeholder="Food name or description">
                </div>
                <div class="col-lg-2">
                    <label class="form-label" for="category">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filters['category'] === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label" for="type">Type</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">All</option>
                        <option value="VEG" <?php echo strtoupper($filters['type']) === 'VEG' ? 'selected' : ''; ?>>VEG</option>
                        <option value="NON-VEG" <?php echo strtoupper($filters['type']) === 'NON-VEG' ? 'selected' : ''; ?>>NON-VEG</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="available" <?php echo strtolower($filters['status']) === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="unavailable" <?php echo strtolower($filters['status']) === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-luxury w-100">Apply</button>
                    <a href="food.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($foodList['rows'])): ?>
        <div class="card card-luxury">
            <div class="card-body text-center py-5">
                <img src="<?php echo htmlspecialchars(appPath('/assets/images/food/default_food.jpg')); ?>" alt="No food" style="width: 120px; border-radius: 12px; opacity: 0.8;">
                <h4 class="mt-3">No food records found</h4>
                <p class="text-muted">Start by adding your first menu item.</p>
                <a href="food-add.php" class="btn btn-luxury">Add Food Item</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($foodList['rows'] as $row): ?>
                <div class="col-xxl-3 col-xl-4 col-md-6 mb-4">
                    <div class="card card-luxury h-100 food-card">
                        <div class="overflow-hidden" style="border-radius: 12px 12px 0 0;">
                            <img
                                src="<?php echo htmlspecialchars($row['image_url']); ?>"
                                alt="<?php echo htmlspecialchars($row['food_name']); ?>"
                                style="width:100%; height:200px; object-fit:cover; transition: transform 0.35s ease;"
                                onerror="handleFoodCardImageError(this, <?php echo (int)$row['food_id']; ?>);"
                            >
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($row['food_name']); ?></h5>
                                <span class="badge <?php echo $row['type'] === 'VEG' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo htmlspecialchars($row['type']); ?>
                                </span>
                            </div>

                            <div class="small text-muted mb-2"><?php echo htmlspecialchars($row['category']); ?></div>
                            <?php $shortDesc = strlen($row['description']) > 120 ? substr($row['description'], 0, 120) . '...' : $row['description']; ?>
                            <p class="small mb-2" style="line-height: 1.55;"><?php echo htmlspecialchars($shortDesc); ?></p>

                            <div class="mt-auto d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold text-warning">₹<?php echo number_format((float)$row['price'], 2); ?></span>
                                <span class="badge <?php echo $row['is_available'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars($row['status_label']); ?>
                                </span>
                            </div>

                            <div class="d-flex gap-2">
                                <a class="btn btn-outline-info btn-sm flex-fill" href="food-view.php?id=<?php echo (int)$row['food_id']; ?>">
                                    <i class="fas fa-eye me-1"></i>VIEW
                                </a>
                                <a class="btn btn-outline-primary btn-sm flex-fill" href="food-edit.php?id=<?php echo (int)$row['food_id']; ?>">
                                    <i class="fas fa-edit me-1"></i>EDIT
                                </a>
                                <a
                                    class="btn btn-outline-danger btn-sm flex-fill"
                                    href="food-delete.php?id=<?php echo (int)$row['food_id']; ?>"
                                    onclick="return confirm('Delete this item?');"
                                >
                                    <i class="fas fa-trash me-1"></i>DELETE
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php $pagination = $foodList['pagination']; ?>
        <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
            <nav aria-label="Food pagination" class="mt-2">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo !$pagination['has_prev'] ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo htmlspecialchars(foodQueryWithPage($filters, max(1, (int)$pagination['page'] - 1))); ?>">Previous</a>
                    </li>

                    <?php for ($p = 1; $p <= (int)$pagination['total_pages']; $p++): ?>
                        <li class="page-item <?php echo $p === (int)$pagination['page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo htmlspecialchars(foodQueryWithPage($filters, $p)); ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo htmlspecialchars(foodQueryWithPage($filters, min((int)$pagination['total_pages'], (int)$pagination['page'] + 1))); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function logout() {
    if (!confirm('Are you sure you want to logout?')) {
        return;
    }

    apiRequest('../api/auth/logout.php', { method: 'POST' })
        .then(() => {
            window.location.href = 'login.php';
        })
        .catch(() => {
            window.location.href = 'login.php';
        });
}

async function runFoodImageMapper() {
    if (!confirm('Scan food image folders and auto-map dish photos now?')) {
        return;
    }

    try {
        const response = await apiRequest('../api/admin/food-image-map.php', {
            method: 'POST',
            body: JSON.stringify({ apply_defaults: true })
        });

        const stats = response.data || {};
        showToast(
            `Image mapping complete. Updated: ${Number(stats.updated || 0)}, mapped: ${Number(stats.mapped || 0)}, defaulted: ${Number(stats.defaulted || 0)}`,
            'success'
        );
        setTimeout(() => window.location.reload(), 900);
    } catch (error) {
        showToast(error.message || 'Failed to map food images', 'danger');
    }
}

async function fixMissingFoodImages() {
    try {
        const response = await apiRequest('../api/admin/fix-missing-food-images.php', {
            method: 'POST',
            body: JSON.stringify({})
        });

        const data = response.data || {};
        showToast(
            `Image fix completed. Fixed: ${Number(data.fixed || 0)}, Failed: ${Number(data.failed || 0)}`,
            Number(data.failed || 0) === 0 ? 'success' : 'warning'
        );
        setTimeout(() => window.location.reload(), 1100);
    } catch (error) {
        showToast(error.message || 'Failed to fix missing images', 'danger');
    }
}

async function handleFoodCardImageError(imageEl, foodId) {
    const fallback = '<?php echo htmlspecialchars(appPath('/assets/images/food/default_food.jpg')); ?>';
    imageEl.onerror = null;
    imageEl.src = fallback;

    if (!foodId || imageEl.dataset.fixAttempted === '1') {
        return;
    }
    imageEl.dataset.fixAttempted = '1';

    try {
        const response = await apiRequest('../api/admin/fix-missing-food-images.php', {
            method: 'POST',
            body: JSON.stringify({ food_id: Number(foodId) })
        });

        const item = (response.data && Array.isArray(response.data.items) && response.data.items[0]) ? response.data.items[0] : null;
        if (item && item.image_path) {
            const newSrc = '<?php echo htmlspecialchars(rtrim(appPath('/'), '/')); ?>/' + String(item.image_path).replace(/^\/+/, '') + '?v=' + Date.now();
            imageEl.src = newSrc;
        }
    } catch (error) {
        // Keep fallback image silently; no hard failure in UI.
    }
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.food-card img').forEach((img) => {
        img.addEventListener('mouseenter', () => {
            img.style.transform = 'scale(1.06)';
        });
        img.addEventListener('mouseleave', () => {
            img.style.transform = 'scale(1)';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
