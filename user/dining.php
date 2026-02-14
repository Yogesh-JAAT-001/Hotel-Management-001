<?php
require_once '../config.php';
require_once '../includes/media-helper.php';
require_once '../includes/food-image-fixer.php';

$category_order = ['Starters', 'Soups', 'Main Course', 'Breads', 'Rice', 'Desserts', 'Beverages', 'Combos'];
$category_icons = [
    'Starters' => 'fas fa-cheese',
    'Soups' => 'fas fa-mug-hot',
    'Main Course' => 'fas fa-utensils',
    'Breads' => 'fas fa-bread-slice',
    'Rice' => 'fas fa-seedling',
    'Desserts' => 'fas fa-ice-cream',
    'Beverages' => 'fas fa-glass-whiskey',
    'Combos' => 'fas fa-layer-group'
];

$categorized_items = [];
$food_items = [];
$has_menu_category = false;
$food_title_col = 'title';
$food_type_col = 'food_type';
$food_status_col = null;
$food_status_is_numeric = false;
$food_category_col = null;
$autoFixBudget = 2;

foreach ($category_order as $category_name) {
    $categorized_items[$category_name] = [];
}

try {
    $food_title_col = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['title', 'food_name', 'name']) ?? 'title';
    $food_type_col = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['food_type', 'type']) ?? 'food_type';
    $food_status_col = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['is_available', 'status']);
    $food_category_col = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['menu_category', 'category']);

    if ($food_status_col) {
        $statusTypeStmt = $pdo->prepare(
            "SELECT DATA_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'FOOD_DINING'
               AND COLUMN_NAME = ?
             LIMIT 1"
        );
        $statusTypeStmt->execute([$food_status_col]);
        $statusDataType = strtolower((string)($statusTypeStmt->fetchColumn() ?: ''));
        $food_status_is_numeric = in_array($statusDataType, ['tinyint', 'smallint', 'int', 'bigint', 'bit', 'boolean'], true);
    }

    $column_stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'FOOD_DINING' AND COLUMN_NAME = 'menu_category'"
    );
    $column_stmt->execute();
    $has_menu_category = ((int)$column_stmt->fetch()['total']) > 0;

    $sql = "
        SELECT
            food_id,
            {$food_title_col} AS title,
            description,
            price,
            " . ($food_type_col ? "{$food_type_col}" : "'VEG'") . " AS food_type,
            " . ($food_status_col ? "{$food_status_col}" : "1") . " AS status_value,
            image_path,
            " . ($food_category_col ? "COALESCE(NULLIF(TRIM({$food_category_col}), ''), 'Main Course')" : "'Main Course'") . " AS menu_category
        FROM FOOD_DINING
        ORDER BY menu_category ASC, title ASC
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();

    foreach ($rows as $item) {
        $isAvailable = true;
        if ($food_status_col) {
            if ($food_status_is_numeric) {
                $isAvailable = ((int)($item['status_value'] ?? 0)) === 1;
            } else {
                $statusText = strtolower(trim((string)($item['status_value'] ?? '')));
                $isAvailable = in_array($statusText, ['available', 'active', '1', 'yes', 'enabled'], true);
            }
        }
        if (!$isAvailable) {
            continue;
        }

        $raw_category = trim((string)$item['menu_category']);
        $normalized_category = in_array($raw_category, $category_order, true) ? $raw_category : 'Main Course';

        $item['menu_category'] = $normalized_category;
        $item['price'] = (float)$item['price'];
        $item['food_type'] = strtoupper((string)$item['food_type']) === 'NON-VEG' ? 'NON-VEG' : 'VEG';
        $imageUrl = resolveDiningImageUrl($item['image_path'] ?? '', $normalized_category, $item['title'] ?? '');
        $fallbackUrl = appUrl(diningFallbackPath($normalized_category));

        // Auto-heal a few missing dish images per request to avoid any broken cards.
        if ($autoFixBudget > 0 && $imageUrl === $fallbackUrl) {
            try {
                $fixReport = foodImageFixMissing($pdo, [
                    'food_id' => (int)$item['food_id'],
                    'limit' => 1
                ]);
                if (!empty($fixReport['items'][0]['image_path'])) {
                    $fixedPath = '/' . ltrim((string)$fixReport['items'][0]['image_path'], '/');
                    if (mediaPublicAssetExists($fixedPath)) {
                        $imageUrl = appUrl($fixedPath);
                    }
                }
            } catch (Throwable $e) {
                // Keep fallback if remote/local auto-fix fails.
            }
            $autoFixBudget--;
        }

        $item['image_url'] = $imageUrl;
        $item['image_fallback_url'] = appUrl(diningFallbackPath($normalized_category));
        $food_items[] = $item;
        $categorized_items[$normalized_category][] = $item;
    }
} catch (PDOException $e) {
    error_log('Dining page error: ' . $e->getMessage());
}

$stats = [
    'total' => count($food_items),
    'veg' => count(array_filter($food_items, fn($item) => $item['food_type'] === 'VEG')),
    'non_veg' => count(array_filter($food_items, fn($item) => $item['food_type'] === 'NON-VEG')),
    'categories' => count(array_filter($categorized_items, fn($items) => count($items) > 0))
];
$menu_image_fallback = appUrl('/assets/images/dining/mains/main-course.png');
$dining_hero_image = appPath('/assets/images/dining/hero/royal-dining.png');
$additional_css = '<link rel="stylesheet" href="' . appPath('/assets/css/dining.css') . '">';

$page_title = 'Dining Experience';
include '../includes/header.php';
?>

<nav class="navbar navbar-expand-lg navbar-luxury fixed-top">
    <div class="container">
        <a class="navbar-brand" href="../"><?php echo APP_NAME; ?></a>
        <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
            <i class="fas fa-bars text-white"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="../">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="rooms.php">Rooms</a></li>
                <li class="nav-item"><a class="nav-link active" href="dining.php">Dining</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
            </ul>

            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="reservations.php">My Reservations</a></li>
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="#" data-mdb-toggle="modal" data-mdb-target="#loginModal">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<section class="dining-hero-banner" style="--dining-hero-image: url('<?php echo htmlspecialchars($dining_hero_image); ?>');">
    <div class="container">
        <div class="dining-hero-inner">
            <div class="row align-items-center gy-4">
                <div class="col-lg-8">
                    <span class="dining-hero-badge"><i class="fas fa-utensils"></i> Heritage Restaurant</span>
                    <h1 class="dining-hero-title">Royal Dining Experience</h1>
                    <p class="dining-hero-subtitle">Fine Cuisine in a Palace Ambience</p>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <i class="fas fa-concierge-bell fa-5x text-warning opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-4">
    <div class="container">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="menu-kpi p-3">
                    <div class="text-muted small text-uppercase">Total Items</div>
                    <div class="metric"><?php echo (int)$stats['total']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="menu-kpi p-3">
                    <div class="text-muted small text-uppercase">Vegetarian</div>
                    <div class="metric"><?php echo (int)$stats['veg']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="menu-kpi p-3">
                    <div class="text-muted small text-uppercase">Non-Vegetarian</div>
                    <div class="metric"><?php echo (int)$stats['non_veg']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="menu-kpi p-3">
                    <div class="text-muted small text-uppercase">Active Categories</div>
                    <div class="metric"><?php echo (int)$stats['categories']; ?></div>
                </div>
            </div>
        </div>

        <div class="card menu-filters mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label">Search Dish</label>
                        <input type="text" class="form-control" id="menuSearch" placeholder="Type dish name...">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Food Type</label>
                        <select id="foodTypeFilter" class="form-select">
                            <option value="all">All</option>
                            <option value="VEG">Vegetarian</option>
                            <option value="NON-VEG">Non-Vegetarian</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Category</label>
                        <select id="categoryFilter" class="form-select">
                            <option value="all">All Categories</option>
                            <?php foreach ($category_order as $category_name): ?>
                                <option value="<?php echo htmlspecialchars($category_name); ?>"><?php echo htmlspecialchars($category_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button type="button" class="btn btn-outline-secondary" onclick="resetMenuFilters()">
                            <i class="fas fa-rotate-left me-1"></i>Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="category-scroll-nav mb-3" id="categoryNav">
            <?php foreach ($category_order as $category_name): ?>
                <?php
                    $slug = strtolower(str_replace(' ', '-', $category_name));
                    $count = count($categorized_items[$category_name]);
                ?>
                <button type="button" class="category-btn" data-category="<?php echo htmlspecialchars($category_name); ?>" data-target="cat-<?php echo htmlspecialchars($slug); ?>">
                    <i class="<?php echo htmlspecialchars($category_icons[$category_name] ?? 'fas fa-utensils'); ?> me-1"></i>
                    <?php echo htmlspecialchars($category_name); ?>
                    <span class="badge bg-dark ms-1" id="nav-count-<?php echo htmlspecialchars($slug); ?>"><?php echo (int)$count; ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div id="menuSections">
            <?php foreach ($category_order as $category_name): ?>
                <?php
                    $slug = strtolower(str_replace(' ', '-', $category_name));
                    $items = $categorized_items[$category_name];
                ?>
                <section class="menu-category-section" id="cat-<?php echo htmlspecialchars($slug); ?>" data-category-section="<?php echo htmlspecialchars($category_name); ?>">
                    <div class="menu-category-title">
                        <h4>
                            <i class="<?php echo htmlspecialchars($category_icons[$category_name] ?? 'fas fa-utensils'); ?> me-2"></i>
                            <?php echo htmlspecialchars($category_name); ?>
                        </h4>
                        <span class="badge bg-primary" id="section-count-<?php echo htmlspecialchars($slug); ?>"><?php echo count($items); ?> items</span>
                    </div>

                    <?php if (empty($items)): ?>
                        <div class="category-empty">No items added yet in this category.</div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($items as $item): ?>
                                <div class="col-xl-3 col-lg-4 col-md-6 food-menu-wrapper"
                                     data-category="<?php echo htmlspecialchars($category_name); ?>"
                                     data-food-type="<?php echo htmlspecialchars($item['food_type']); ?>"
                                     data-title="<?php echo htmlspecialchars(strtolower($item['title'])); ?>">
                                    <article class="food-menu-card" data-food-id="<?php echo (int)$item['food_id']; ?>">
                                        <img class="thumb"
                                             src="<?php echo htmlspecialchars($item['image_url'] ?? $menu_image_fallback); ?>"
                                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                                             loading="lazy"
                                             decoding="async"
                                             onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($item['image_fallback_url'] ?? $menu_image_fallback); ?>';">

                                        <div class="content">
                                            <span class="menu-category-badge mb-2"><?php echo htmlspecialchars($category_name); ?></span>
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="food-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                                <span class="<?php echo $item['food_type'] === 'VEG' ? 'veg-badge' : 'nonveg-badge'; ?>">
                                                    <?php echo htmlspecialchars($item['food_type']); ?>
                                                </span>
                                            </div>
                                            <div class="food-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <div class="food-price">₹<?php echo number_format($item['price'], 2); ?></div>
                                                <button class="btn btn-luxury btn-sm" onclick="addToCart(<?php echo (int)$item['food_id']; ?>)">
                                                    <i class="fas fa-plus me-1"></i>Add
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>

        <div id="noMenuResults" class="category-empty mt-3" style="display: none;">
            <i class="fas fa-search me-1"></i>No menu items match current filters.
        </div>

        <div id="orderSummary" class="mt-4" style="display: none;">
            <div class="card card-luxury">
                <div class="card-body">
                    <h5 class="luxury-header mb-3">Your Order</h5>
                    <div id="cartItems"></div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h4 class="luxury-header mb-0">Total: <span id="cartTotal" class="text-warning">₹0.00</span></h4>
                        <div>
                            <button class="btn btn-outline-secondary me-2" onclick="clearCart()">Clear Order</button>
                            <button class="btn btn-luxury" onclick="proceedToOrder()">
                                <i class="fas fa-shopping-cart me-1"></i>Place Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Login Required</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Please login to place dining orders.</p>
                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div class="form-outline mb-3">
                        <input type="email" id="loginEmail" name="email" class="form-control" required>
                        <label class="form-label" for="loginEmail">Email Address</label>
                    </div>
                    <div class="form-outline mb-3">
                        <input type="password" id="loginPassword" name="password" class="form-control" required>
                        <label class="form-label" for="loginPassword">Password</label>
                    </div>
                    <button type="submit" class="btn btn-luxury w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const foodItems = <?php echo json_encode($food_items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let cart = [];

function applyMenuFilters() {
    const search = String(document.getElementById('menuSearch').value || '').trim().toLowerCase();
    const foodType = document.getElementById('foodTypeFilter').value;
    const category = document.getElementById('categoryFilter').value;

    const wrappers = Array.from(document.querySelectorAll('.food-menu-wrapper'));
    const sectionCounts = {};
    let visibleCards = 0;

    wrappers.forEach(wrapper => {
        const title = wrapper.dataset.title || '';
        const type = wrapper.dataset.foodType || '';
        const itemCategory = wrapper.dataset.category || '';

        const matchesSearch = search === '' || title.includes(search);
        const matchesType = foodType === 'all' || type === foodType;
        const matchesCategory = category === 'all' || itemCategory === category;
        const isVisible = matchesSearch && matchesType && matchesCategory;

        wrapper.classList.toggle('hidden-item', !isVisible);

        if (isVisible) {
            visibleCards += 1;
            sectionCounts[itemCategory] = (sectionCounts[itemCategory] || 0) + 1;
        }
    });

    document.querySelectorAll('[data-category-section]').forEach(section => {
        const sectionCategory = section.dataset.categorySection;
        const count = sectionCounts[sectionCategory] || 0;
        const slug = section.id.replace('cat-', '');
        const countBadge = document.getElementById(`section-count-${slug}`);
        const navBadge = document.getElementById(`nav-count-${slug}`);

        if (countBadge) countBadge.textContent = `${count} items`;
        if (navBadge) navBadge.textContent = `${count}`;

        section.classList.toggle('hidden-item', count === 0);
    });

    document.getElementById('noMenuResults').style.display = visibleCards === 0 ? 'block' : 'none';
}

function resetMenuFilters() {
    document.getElementById('menuSearch').value = '';
    document.getElementById('foodTypeFilter').value = 'all';
    document.getElementById('categoryFilter').value = 'all';
    applyMenuFilters();
}

function updateActiveCategoryButton(activeCategory) {
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.category === activeCategory);
    });
}

function setupCategoryNav() {
    document.querySelectorAll('.category-btn').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.target;
            const targetEl = document.getElementById(targetId);
            if (!targetEl) return;

            updateActiveCategoryButton(button.dataset.category);
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
}

function addToCart(foodId) {
    <?php if (!isLoggedIn()): ?>
    const loginModal = new mdb.Modal(document.getElementById('loginModal'));
    loginModal.show();
    return;
    <?php endif; ?>

    const item = foodItems.find(food => Number(food.food_id) === Number(foodId));
    if (!item) return;

    const existing = cart.find(entry => Number(entry.food_id) === Number(foodId));
    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({
            food_id: Number(item.food_id),
            title: item.title,
            price: Number(item.price),
            qty: 1
        });
    }

    updateCartDisplay();
    showToast(`${item.title} added to order`, 'success');
}

function updateQuantity(foodId, delta) {
    const item = cart.find(entry => Number(entry.food_id) === Number(foodId));
    if (!item) return;

    item.qty += delta;
    if (item.qty <= 0) {
        cart = cart.filter(entry => Number(entry.food_id) !== Number(foodId));
    }

    updateCartDisplay();
}

function clearCart() {
    cart = [];
    updateCartDisplay();
    showToast('Order cleared', 'info');
}

function updateCartDisplay() {
    const summary = document.getElementById('orderSummary');
    const itemsEl = document.getElementById('cartItems');
    const totalEl = document.getElementById('cartTotal');

    if (cart.length === 0) {
        summary.style.display = 'none';
        itemsEl.innerHTML = '';
        totalEl.textContent = '₹0.00';
        return;
    }

    summary.style.display = 'block';

    let total = 0;
    const html = cart.map(item => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;

        return `
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                    <strong>${item.title}</strong>
                    <small class="text-muted d-block">₹${item.price.toFixed(2)} each</small>
                </div>
                <div class="d-flex align-items-center">
                    <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${item.food_id}, -1)">-</button>
                    <span class="mx-2">${item.qty}</span>
                    <button class="btn btn-sm btn-outline-secondary me-3" onclick="updateQuantity(${item.food_id}, 1)">+</button>
                    <strong class="text-warning">₹${itemTotal.toFixed(2)}</strong>
                </div>
            </div>
        `;
    }).join('');

    itemsEl.innerHTML = html;
    totalEl.textContent = `₹${total.toFixed(2)}`;
}

function proceedToOrder() {
    if (cart.length === 0) {
        showToast('Your cart is empty', 'warning');
        return;
    }

    showToast('Demo mode: dining order can be linked to a room booking during checkout.', 'info');
}

async function handleLogin(event) {
    event.preventDefault();
    const formData = new FormData(event.target);

    try {
        const response = await apiRequest('../api/auth/login.php', {
            method: 'POST',
            body: JSON.stringify({
                email: formData.get('email'),
                password: formData.get('password'),
                user_type: 'guest'
            })
        });

        if (response.success) {
            showToast('Login successful', 'success');
            setTimeout(() => location.reload(), 700);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

async function logout() {
    if (!confirm('Are you sure you want to logout?')) {
        return;
    }

    try {
        await apiRequest('../api/auth/logout.php', { method: 'POST' });
        location.reload();
    } catch (error) {
        showToast('Unable to logout right now. Please try again.', 'danger');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setupCategoryNav();

    document.getElementById('menuSearch').addEventListener('input', () => {
        clearTimeout(window.__menuFilterDebounce);
        window.__menuFilterDebounce = setTimeout(applyMenuFilters, 200);
    });

    document.getElementById('foodTypeFilter').addEventListener('change', applyMenuFilters);
    document.getElementById('categoryFilter').addEventListener('change', applyMenuFilters);

    applyMenuFilters();
});
</script>

<?php include '../includes/footer.php'; ?>
