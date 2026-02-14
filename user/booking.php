<?php
require_once '../config.php';
require_once '../includes/media-helper.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../index.php?login_required=1');
}

$page_title = 'Book Room';
include '../includes/header.php';

// Get parameters
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : '';
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : '';

if (!$room_id) {
    redirect('rooms.php');
}

// Get room details
try {
    $hasFeatureIcons = dbHasColumn($pdo, 'ROOM_FEATURES', 'icon');
    $foodTitleCol = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['title', 'food_name', 'name']) ?? 'title';
    $foodTypeCol = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['food_type', 'type']) ?? 'food_type';
    $foodStatusCol = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['is_available', 'status']);
    $foodStatusIsNumeric = false;
    if ($foodStatusCol) {
        $statusTypeStmt = $pdo->prepare(
            "SELECT DATA_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'FOOD_DINING'
               AND COLUMN_NAME = ?
             LIMIT 1"
        );
        $statusTypeStmt->execute([$foodStatusCol]);
        $statusDataType = strtolower((string)($statusTypeStmt->fetchColumn() ?: ''));
        $foodStatusIsNumeric = in_array($statusDataType, ['tinyint', 'smallint', 'int', 'bigint', 'bit', 'boolean'], true);
    }

    $sql = "
        SELECT 
            r.*,
            rt.name as room_type_name,
            rt.description as room_type_description,
            GROUP_CONCAT(DISTINCT rf.name) as features,
            " . ($hasFeatureIcons ? "GROUP_CONCAT(DISTINCT rf.icon)" : "''") . " as feature_icons
        FROM ROOMS r
        JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
        LEFT JOIN ROOM_FEATURES_MAP rfm ON r.room_id = rfm.room_id
        LEFT JOIN ROOM_FEATURES rf ON rfm.feature_id = rf.room_feature_id
        WHERE r.room_id = ?
        GROUP BY r.room_id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if (!$room) {
        redirect('rooms.php');
    }
    
    // Get reservation types
    $stmt = $pdo->query("SELECT * FROM RESERVATION_TYPE ORDER BY reservation_type_id");
    $reservation_types = $stmt->fetchAll();
    
    // Get available food items
    $foodSql = "
        SELECT
            food_id,
            {$foodTitleCol} AS title,
            " . ($foodTypeCol ? "{$foodTypeCol}" : "'VEG'") . " AS food_type,
            price,
            " . ($foodStatusCol ? "{$foodStatusCol}" : "1") . " AS status_value
        FROM FOOD_DINING
        ORDER BY food_type ASC, title ASC
    ";
    $stmt = $pdo->query($foodSql);
    $rawFoodItems = $stmt->fetchAll();
    $food_items = [];
    foreach ($rawFoodItems as $foodRow) {
        $isAvailable = true;
        if ($foodStatusCol) {
            if ($foodStatusIsNumeric) {
                $isAvailable = ((int)($foodRow['status_value'] ?? 0)) === 1;
            } else {
                $statusText = strtolower(trim((string)($foodRow['status_value'] ?? '')));
                $isAvailable = in_array($statusText, ['available', 'active', '1', 'yes', 'enabled'], true);
            }
        }
        if (!$isAvailable) {
            continue;
        }
        $food_items[] = $foodRow;
    }
    
} catch (PDOException $e) {
    error_log("Booking page error: " . $e->getMessage());
    redirect('rooms.php');
}

// Set default dates if not provided
if (!$check_in) {
    $check_in = date('Y-m-d');
}
if (!$check_out) {
    $check_out = date('Y-m-d', strtotime('+1 day'));
}
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-luxury fixed-top">
    <div class="container">
        <a class="navbar-brand" href="../"><?php echo APP_NAME; ?></a>
        
        <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
            <i class="fas fa-bars text-white"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rooms.php">Rooms</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dining.php">Dining</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo $_SESSION['user_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="reservations.php">My Reservations</a></li>
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Page Header -->
<section class="hero-section py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-3 luxury-header text-white">
                    Book Your Stay
                </h1>
                <p class="lead text-light mb-4">
                    Complete your reservation for Room <?php echo $room['room_no']; ?> - <?php echo $room['room_type_name']; ?>
                </p>
            </div>
            <div class="col-lg-4">
                <div class="text-center">
                    <i class="fas fa-calendar-check fa-5x text-warning opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Booking Form -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Room Details -->
            <div class="col-lg-4 mb-4">
                <div class="card card-luxury sticky-top" style="top: 100px;">
                    <img src="<?php echo htmlspecialchars(resolveRoomImageUrl($room['image_path'] ?? '', $room['room_type_name'] ?? '', $room['room_no'] ?? null)); ?>" 
                         class="card-img-top" alt="Room <?php echo $room['room_no']; ?>" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="luxury-header">Room <?php echo $room['room_no']; ?></h5>
                        <p class="text-muted"><?php echo $room['room_type_name']; ?> - <?php echo $room['tier']; ?> TIER</p>
                        <p class="card-text"><?php echo $room['description'] ?: 'Comfortable and well-appointed room with modern amenities.'; ?></p>
                        
                        <?php if ($room['features']): ?>
                        <div class="mb-3">
                            <small class="text-muted">Features:</small><br>
                            <?php 
                            $features = explode(',', $room['features']);
                            foreach ($features as $feature): 
                            ?>
                                <span class="badge bg-light text-dark me-1"><?php echo trim($feature); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <h3 class="text-warning">₹<?php echo number_format($room['rent']); ?></h3>
                            <small class="text-muted">base rate per night</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Booking Form -->
            <div class="col-lg-8">
                <div class="card card-luxury">
                    <div class="card-body">
                        <h4 class="luxury-header mb-4">Reservation Details</h4>
                        
                        <form id="bookingForm" onsubmit="handleBooking(event)">
                            <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                            
                            <!-- Dates -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <input type="date" id="checkIn" name="check_in" class="form-control" 
                                               value="<?php echo $check_in; ?>" required>
                                        <label class="form-label" for="checkIn">Check-in Date</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <input type="date" id="checkOut" name="check_out" class="form-control" 
                                               value="<?php echo $check_out; ?>" required>
                                        <label class="form-label" for="checkOut">Check-out Date</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Reservation Type -->
                            <div class="mb-4">
                                <label class="form-label">Reservation Type</label>
                                <div class="row">
                                    <?php foreach ($reservation_types as $type): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="reservation_type_id" 
                                                   value="<?php echo $type['reservation_type_id']; ?>" 
                                                   id="resType<?php echo $type['reservation_type_id']; ?>" 
                                                   <?php echo $type['reservation_type_id'] == 2 ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="resType<?php echo $type['reservation_type_id']; ?>">
                                                <strong><?php echo $type['name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $type['payment_rule']; ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Special Requests -->
                            <div class="form-outline mb-4">
                                <textarea id="specialRequests" name="special_requests" class="form-control" rows="3"></textarea>
                                <label class="form-label" for="specialRequests">Special Requests (Optional)</label>
                            </div>
                            
                            <!-- Coupon Code -->
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <div class="form-outline">
                                        <input type="text" id="couponCode" name="coupon_code" class="form-control">
                                        <label class="form-label" for="couponCode">Coupon Code (Optional)</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-warning w-100" onclick="applyCoupon()">
                                        Apply Coupon
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Food Orders -->
                            <div class="mb-4">
                                <h5 class="luxury-header mb-3">Add Food Orders (Optional)</h5>
                                <div class="row" id="foodItems">
                                    <?php foreach ($food_items as $food): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo $food['title']; ?></h6>
                                                        <small class="text-muted"><?php echo $food['food_type']; ?></small>
                                                        <p class="text-warning mb-2">₹<?php echo number_format($food['price'], 2); ?></p>
                                                    </div>
                                                    <div class="d-flex align-items-center">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                onclick="updateFoodQty(<?php echo $food['food_id']; ?>, -1)">-</button>
                                                        <span class="mx-2 food-qty" data-food-id="<?php echo $food['food_id']; ?>">0</span>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                onclick="updateFoodQty(<?php echo $food['food_id']; ?>, 1)">+</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Price Summary -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h5 class="luxury-header mb-3">Price Summary</h5>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Room Rate (<span id="nightsCount">1</span> night(s))</span>
                                        <span id="roomTotal">₹<?php echo number_format($room['rent']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 small text-muted">
                                        <span>Dynamic Multiplier</span>
                                        <span id="pricingMultiplier">1.0000x</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2 small text-muted">
                                        <span>Season / Demand / Lead</span>
                                        <span id="pricingFactors">Base pricing</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2" id="foodTotalRow" style="display: none;">
                                        <span>Food Orders</span>
                                        <span id="foodTotal">₹0</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2" id="discountRow" style="display: none;">
                                        <span class="text-success">Discount</span>
                                        <span class="text-success" id="discountAmount">-₹0</span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total Amount</strong>
                                        <strong class="text-warning" id="totalAmount">₹<?php echo number_format($room['rent']); ?></strong>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-luxury btn-lg w-100">
                                <i class="fas fa-credit-card me-2"></i>Confirm Booking
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
const roomId = <?php echo (int)$room['room_id']; ?>;
const roomRate = <?php echo $room['rent']; ?>;
const foodItems = <?php echo json_encode($food_items); ?>;
let selectedFood = {};
let appliedDiscount = 0;
let pricingCache = {};
let currentPricingQuote = null;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    const checkInInput = document.getElementById('checkIn');
    const checkOutInput = document.getElementById('checkOut');
    
    checkInInput.min = today;
    checkOutInput.min = tomorrow.toISOString().split('T')[0];
    
    // Update checkout min date when checkin changes
    checkInInput.addEventListener('change', function() {
        const checkInDate = new Date(this.value);
        checkInDate.setDate(checkInDate.getDate() + 1);
        checkOutInput.min = checkInDate.toISOString().split('T')[0];
        updatePriceSummary();
    });
    
    checkOutInput.addEventListener('change', updatePriceSummary);
    
    updatePriceSummary();
});

// Update food quantity
function updateFoodQty(foodId, change) {
    if (!selectedFood[foodId]) {
        selectedFood[foodId] = 0;
    }
    
    selectedFood[foodId] += change;
    
    if (selectedFood[foodId] < 0) {
        selectedFood[foodId] = 0;
    }
    
    // Update display
    const qtyElement = document.querySelector(`[data-food-id="${foodId}"]`);
    qtyElement.textContent = selectedFood[foodId];
    
    updatePriceSummary();
}

// Update price summary
async function fetchPricingQuote(checkIn, checkOut) {
    const key = `${checkIn}|${checkOut}`;
    if (pricingCache[key]) {
        return pricingCache[key];
    }

    const response = await fetch(`../api/pricing.php?room_id=${encodeURIComponent(roomId)}&check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`);
    const result = await response.json();
    if (!response.ok || !result.success) {
        throw new Error(result.error || 'Failed to fetch dynamic price');
    }

    pricingCache[key] = result.data;
    return result.data;
}

async function updatePriceSummary() {
    const checkIn = document.getElementById('checkIn').value;
    const checkOut = document.getElementById('checkOut').value;
    
    if (!checkIn || !checkOut) return;
    
    // Calculate nights
    const checkInDate = new Date(checkIn);
    const checkOutDate = new Date(checkOut);
    const nights = Math.max(1, Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24)));
    
    // Calculate room total from dynamic pricing
    let roomTotal = roomRate * nights;
    let averageMultiplier = 1;
    let factorSummary = 'Base pricing';
    currentPricingQuote = null;

    try {
        const quote = await fetchPricingQuote(checkIn, checkOut);
        currentPricingQuote = quote;
        roomTotal = Number(quote.dynamic_total || roomTotal);
        averageMultiplier = Number(quote.average_multiplier || 1);

        const factorParts = [];
        if (quote.factors) {
            factorParts.push(`Season ${Number(quote.factors.season_multiplier_avg || 1).toFixed(2)}x`);
            factorParts.push(`Demand ${(Number(quote.factors.demand_adjustment_avg || 0) * 100).toFixed(1)}%`);
            factorParts.push(`Lead ${(Number(quote.factors.lead_time_adjustment_avg || 0) * 100).toFixed(1)}%`);
        }
        factorSummary = factorParts.join(' | ') || 'Base pricing';
    } catch (error) {
        factorSummary = 'Base pricing fallback';
    }
    
    // Calculate food total
    let foodTotal = 0;
    for (const foodId in selectedFood) {
        const qty = selectedFood[foodId];
        if (qty > 0) {
            const food = foodItems.find(f => f.food_id == foodId);
            if (food) {
                foodTotal += food.price * qty;
            }
        }
    }
    
    // Calculate total
    const subtotal = roomTotal + foodTotal;
    const total = subtotal - appliedDiscount;
    
    // Update display
    document.getElementById('nightsCount').textContent = nights;
    document.getElementById('roomTotal').textContent = `₹${roomTotal.toLocaleString()}`;
    document.getElementById('pricingMultiplier').textContent = `${averageMultiplier.toFixed(4)}x`;
    document.getElementById('pricingFactors').textContent = factorSummary;
    
    const foodTotalRow = document.getElementById('foodTotalRow');
    if (foodTotal > 0) {
        document.getElementById('foodTotal').textContent = `₹${foodTotal.toLocaleString()}`;
        foodTotalRow.style.display = 'flex';
    } else {
        foodTotalRow.style.display = 'none';
    }
    
    const discountRow = document.getElementById('discountRow');
    if (appliedDiscount > 0) {
        document.getElementById('discountAmount').textContent = `-₹${appliedDiscount.toLocaleString()}`;
        discountRow.style.display = 'flex';
    } else {
        discountRow.style.display = 'none';
    }
    
    document.getElementById('totalAmount').textContent = `₹${total.toLocaleString()}`;
}

// Apply coupon
async function applyCoupon() {
    const couponCode = document.getElementById('couponCode').value.trim();
    if (!couponCode) {
        showToast('Please enter a coupon code', 'warning');
        return;
    }
    
    // For now, just show a message - in real implementation, this would validate the coupon
    showToast('Coupon validation will be handled during booking confirmation', 'info');
}

// Handle booking submission
async function handleBooking(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const bookingData = {
        room_id: parseInt(formData.get('room_id')),
        check_in: formData.get('check_in'),
        check_out: formData.get('check_out'),
        reservation_type_id: parseInt(formData.get('reservation_type_id')),
        special_requests: formData.get('special_requests'),
        coupon_code: formData.get('coupon_code'),
        food_items: []
    };
    
    // Add food items
    for (const foodId in selectedFood) {
        if (selectedFood[foodId] > 0) {
            bookingData.food_items.push({
                food_id: parseInt(foodId),
                qty: selectedFood[foodId]
            });
        }
    }
    
    try {
        const response = await apiRequest('../api/reservations.php', {
            method: 'POST',
            body: JSON.stringify(bookingData)
        });
        
        if (response.success) {
            showToast('Booking confirmed successfully!', 'success');
            setTimeout(() => {
                window.location.href = `booking-success.php?id=${response.data.reservation_id}`;
            }, 1500);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

// Logout function
async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            await apiRequest('../api/auth/logout.php', { method: 'POST' });
            location.reload();
        } catch (error) {
            showToast('Unable to logout right now. Please try again.', 'danger');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
