<?php
require_once '../config.php';

$tier = isset($_GET['tier']) ? (int)$_GET['tier'] : null;
$room_type = isset($_GET['room_type']) ? sanitize($_GET['room_type']) : '';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$check_in = isset($_GET['check_in']) ? sanitize($_GET['check_in']) : '';
$check_out = isset($_GET['check_out']) ? sanitize($_GET['check_out']) : '';
$availability = isset($_GET['availability']) ? sanitize($_GET['availability']) : 'all';
$room_no_search = isset($_GET['room_no']) ? sanitize($_GET['room_no']) : '';

if (!in_array($availability, ['all', 'available', 'booked'], true)) {
    $availability = 'all';
}

try {
    $stmt = $pdo->query('SELECT room_type_id, name FROM ROOM_TYPE ORDER BY room_type_id');
    $room_types = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT MIN(rent) AS min_price, MAX(rent) AS max_price FROM ROOMS");
    $price_range = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Rooms page bootstrap error: ' . $e->getMessage());
    $room_types = [];
    $price_range = ['min_price' => 0, 'max_price' => 20000];
}

$additional_css = '<link rel="stylesheet" href="' . appPath('/assets/css/rooms.css') . '">';

$page_title = 'Rooms By Floor';
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
                <li class="nav-item"><a class="nav-link active" href="rooms.php">Rooms</a></li>
                <li class="nav-item"><a class="nav-link" href="dining.php">Dining</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
            </ul>

            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-mdb-toggle="dropdown">
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
                    <li class="nav-item"><a class="nav-link" href="#" data-mdb-toggle="modal" data-mdb-target="#registerModal">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<section class="hero-section rooms-hero py-5">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-8">
                <span class="rooms-hero-badge"><i class="fas fa-crown"></i> Palace Wing Collection</span>
                <h1 class="display-5 fw-bold text-white mb-3">Royal Room Wing Explorer</h1>
                <p class="lead text-light mb-0">
                    Discover suites and curated stays by wing, compare dynamic rates in real time, and reserve instantly.
                </p>
            </div>
            <div class="col-lg-4 text-center">
                <i class="fas fa-hotel fa-5x text-warning opacity-75"></i>
            </div>
        </div>
    </div>
</section>

<section class="py-4 bg-light">
    <div class="container">
        <div class="card filter-shell">
            <div class="card-body">
                <form id="filterForm" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Check-in</label>
                        <input type="date" name="check_in" class="form-control" id="checkInFilter" value="<?php echo htmlspecialchars($check_in); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Check-out</label>
                        <input type="date" name="check_out" class="form-control" id="checkOutFilter" value="<?php echo htmlspecialchars($check_out); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Room Type</label>
                        <select name="room_type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach ($room_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['name']); ?>" <?php echo $room_type === $type['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tier</label>
                        <select name="tier" class="form-select">
                            <option value="">All Tiers</option>
                            <option value="1" <?php echo $tier === 1 ? 'selected' : ''; ?>>Tier 1</option>
                            <option value="2" <?php echo $tier === 2 ? 'selected' : ''; ?>>Tier 2</option>
                            <option value="3" <?php echo $tier === 3 ? 'selected' : ''; ?>>Tier 3</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Min Price</label>
                        <input type="number" name="min_price" class="form-control" min="0" value="<?php echo $min_price !== null ? (float)$min_price : ''; ?>" placeholder="₹<?php echo (int)($price_range['min_price'] ?? 0); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Max Price</label>
                        <input type="number" name="max_price" class="form-control" min="0" value="<?php echo $max_price !== null ? (float)$max_price : ''; ?>" placeholder="₹<?php echo (int)($price_range['max_price'] ?? 0); ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Availability</label>
                        <select class="form-select" name="availability" id="availabilityFilter">
                            <option value="all" <?php echo $availability === 'all' ? 'selected' : ''; ?>>All Rooms</option>
                            <option value="available" <?php echo $availability === 'available' ? 'selected' : ''; ?>>Available Only</option>
                            <option value="booked" <?php echo $availability === 'booked' ? 'selected' : ''; ?>>Booked/Occupied</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Room Number</label>
                        <input type="text" class="form-control" id="roomNoFilter" name="room_no" value="<?php echo htmlspecialchars($room_no_search); ?>" placeholder="e.g. 305">
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-luxury">
                            <i class="fas fa-search me-1"></i>Apply Filters
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                            <i class="fas fa-rotate-left me-1"></i>Reset
                        </button>
                    </div>
                </form>
                <div class="muted-help mt-3">
                    Dynamic prices are calculated using current pricing rules and selected stay dates. Unavailable rooms are shown as booked.
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-3">
    <div class="container">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="floor-kpi p-3">
                    <div class="text-muted small text-uppercase">Total Rooms</div>
                    <div class="metric" id="totalRoomsMetric">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="floor-kpi p-3">
                    <div class="text-muted small text-uppercase">Available</div>
                    <div class="metric" id="availableRoomsMetric">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="floor-kpi p-3">
                    <div class="text-muted small text-uppercase">Booked</div>
                    <div class="metric" id="bookedRoomsMetric">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="floor-kpi p-3">
                    <div class="text-muted small text-uppercase">Avg Dynamic Price</div>
                    <div class="metric" id="avgPriceMetric">₹0</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <ul class="nav floor-nav flex-wrap mb-3" id="floorTabs">
            <li class="nav-item">
                <button type="button" class="nav-link active" data-floor="1">
                    <span class="wing-title">
                        <strong><i class="fas fa-crown me-1"></i>Royal Standard Wing</strong>
                        <span class="wing-range">101-120</span>
                    </span>
                    <span class="floor-tag" id="floorTabCount1">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-floor="2">
                    <span class="wing-title">
                        <strong><i class="fas fa-landmark me-1"></i>Deluxe Heritage Wing</strong>
                        <span class="wing-range">201-220</span>
                    </span>
                    <span class="floor-tag" id="floorTabCount2">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-floor="3">
                    <span class="wing-title">
                        <strong><i class="fas fa-gem me-1"></i>Imperial Suite Wing</strong>
                        <span class="wing-range">301-320</span>
                    </span>
                    <span class="floor-tag" id="floorTabCount3">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-floor="4">
                    <span class="wing-title">
                        <strong><i class="fas fa-users me-1"></i>Royal Family Wing</strong>
                        <span class="wing-range">401-430</span>
                    </span>
                    <span class="floor-tag" id="floorTabCount4">0</span>
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" data-floor="5">
                    <span class="wing-title">
                        <strong><i class="fas fa-chess-king me-1"></i>Presidential Wing</strong>
                        <span class="wing-range">501-550</span>
                    </span>
                    <span class="floor-tag" id="floorTabCount5">0</span>
                </button>
            </li>
        </ul>

        <div id="roomsLoading" class="text-center py-5">
            <div class="spinner-border text-warning" role="status"></div>
            <p class="text-muted mt-2">Loading floor inventory...</p>
        </div>

        <div id="roomsBoard" style="display: none;">
            <div class="accordion floor-accordion" id="floorAccordion"></div>
        </div>

        <div id="noRoomsFound" class="empty-floor" style="display: none;">
            <i class="fas fa-bed fa-2x mb-2"></i>
            <h5>No matching rooms</h5>
            <p class="mb-2">Try changing filters or choosing another date range.</p>
            <button class="btn btn-outline-secondary btn-sm" onclick="clearFilters()">Clear Filters</button>
        </div>
    </div>
</section>

<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Login to Continue</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body">
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
                <div class="text-center mt-3">
                    <p class="mb-0">New here? <a href="#" onclick="switchToRegister()">Create account</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Account</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="registerForm" onsubmit="handleRegister(event)">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <input type="text" id="registerName" name="name" class="form-control" required>
                                <label class="form-label" for="registerName">Full Name</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <input type="tel" id="registerPhone" name="phone" class="form-control" required minlength="10" maxlength="15" inputmode="numeric">
                                <label class="form-label" for="registerPhone">Phone Number</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-outline mb-3">
                        <input type="email" id="registerEmail" name="email" class="form-control" required>
                        <label class="form-label" for="registerEmail">Email Address</label>
                    </div>
                    <div class="form-outline mb-3">
                        <input type="password" id="registerPassword" name="password" class="form-control" required>
                        <label class="form-label" for="registerPassword">Password</label>
                    </div>
                    <div class="form-outline mb-3">
                        <input type="password" id="registerConfirmPassword" name="confirm_password" class="form-control" required>
                        <label class="form-label" for="registerConfirmPassword">Confirm Password</label>
                    </div>
                    <button type="submit" class="btn btn-luxury w-100">Register & Continue</button>
                </form>
                <div class="text-center mt-3">
                    <p class="mb-0">Already registered? <a href="#" onclick="switchToLogin()">Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const floorConfig = {
    1: { title: 'Royal Standard Wing', range: '101-120', focus: 'Signature Standard Rooms', icon: 'fa-crown' },
    2: { title: 'Deluxe Heritage Wing', range: '201-220', focus: 'Heritage Deluxe Collection', icon: 'fa-landmark' },
    3: { title: 'Imperial Suite Wing', range: '301-320', focus: 'Imperial Suite Inventory', icon: 'fa-gem' },
    4: { title: 'Royal Family Wing', range: '401-430', focus: 'Family & VIP Comfort', icon: 'fa-users' },
    5: { title: 'Presidential Wing', range: '501-550', focus: 'Premium Palace Suites', icon: 'fa-chess-king' }
};

const roomImageFallback = <?php echo json_encode(appUrl('/assets/images/rooms/standard/standard-room.png')); ?>;

let allRooms = [];
let groupedRooms = {};
let activeFloor = 1;
const pricingCache = new Map();

function inferFeatures(room) {
    const type = String(room.room_type_name || '').toLowerCase();
    if (type.includes('premium')) {
        return ['Butler Service', 'Sky Lounge Access', 'Jacuzzi', 'Panoramic View'];
    }
    if (type.includes('family')) {
        return ['Twin Beds', 'Family Lounge', 'Kids Friendly', 'Complimentary Breakfast'];
    }
    if (type.includes('suite')) {
        return ['Living Area', 'Mini Bar', 'Bathtub', 'Room Service'];
    }
    if (type.includes('deluxe')) {
        return ['Balcony', 'Work Desk', 'Mini Bar', 'High-speed WiFi'];
    }
    return ['Air Conditioning', 'Smart TV', 'WiFi', 'Safe Locker'];
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function parseFloor(roomNo) {
    const match = String(roomNo || '').match(/^(\d)/);
    if (!match) return 0;
    const floor = Number(match[1]);
    return floor >= 1 && floor <= 5 ? floor : 0;
}

function labelRoomStatus(status) {
    return status === 'Available' ? 'Available' : 'Booked';
}

function normalizeRoom(room) {
    const floor = parseFloor(room.room_no);
    const features = Array.isArray(room.features) && room.features.length > 0 ? room.features : inferFeatures(room);

    return {
        ...room,
        floor,
        tier: Number(room.tier || 0),
        rent: Number(room.rent || 0),
        dynamic_rent: Number(room.rent || 0),
        status_label: labelRoomStatus(room.status),
        is_bookable: room.status === 'Available',
        features,
        image_url: room.image_url || roomImageFallback
        ,
        image_fallback_url: room.image_fallback_url || roomImageFallback
    };
}

function initializeDateFilters() {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);

    const checkInInput = document.getElementById('checkInFilter');
    const checkOutInput = document.getElementById('checkOutFilter');

    const todayStr = today.toISOString().split('T')[0];
    const tomorrowStr = tomorrow.toISOString().split('T')[0];

    if (!checkInInput.value) checkInInput.value = todayStr;
    if (!checkOutInput.value) checkOutInput.value = tomorrowStr;

    checkInInput.min = todayStr;
    checkOutInput.min = tomorrowStr;

    checkInInput.addEventListener('change', () => {
        const checkInDate = new Date(checkInInput.value);
        if (Number.isNaN(checkInDate.getTime())) return;
        checkInDate.setDate(checkInDate.getDate() + 1);
        const minCheckout = checkInDate.toISOString().split('T')[0];
        checkOutInput.min = minCheckout;
        if (!checkOutInput.value || checkOutInput.value <= checkInInput.value) {
            checkOutInput.value = minCheckout;
        }
        loadRooms();
    });

    checkOutInput.addEventListener('change', loadRooms);
}

function getFilterParams() {
    const formData = new FormData(document.getElementById('filterForm'));
    const params = new URLSearchParams();

    const availability = formData.get('availability') || 'all';
    const roomNoFilter = String(formData.get('room_no') || '').trim();

    for (const [key, value] of formData.entries()) {
        if (!value || key === 'availability' || key === 'room_no') continue;
        params.set(key, value);
    }

    params.set('available_only', availability === 'available' ? '1' : '0');

    return {
        params,
        availability,
        roomNoFilter
    };
}

async function loadRooms() {
    const loading = document.getElementById('roomsLoading');
    const board = document.getElementById('roomsBoard');
    const empty = document.getElementById('noRoomsFound');

    const { params, availability, roomNoFilter } = getFilterParams();

    loading.style.display = 'block';
    board.style.display = 'none';
    empty.style.display = 'none';

    try {
        const response = await fetch(`../api/rooms.php?${params.toString()}`);
        const payload = await response.json();

        if (!payload.success) {
            throw new Error(payload.error || 'Unable to fetch rooms');
        }

        let rooms = payload.data.map(normalizeRoom).filter(room => room.floor > 0);

        if (availability === 'booked') {
            rooms = rooms.filter(room => !room.is_bookable);
        }

        if (roomNoFilter) {
            rooms = rooms.filter(room => String(room.room_no).includes(roomNoFilter));
        }

        allRooms = rooms;
        groupedRooms = groupRoomsByFloorAndType(rooms);

        renderSummary(rooms);
        renderFloorCounts(rooms);
        renderFloorAccordion(groupedRooms);

        loading.style.display = 'none';

        if (rooms.length === 0) {
            empty.style.display = 'block';
            return;
        }

        board.style.display = 'block';
        openFloor(activeFloor, false);
        await refreshDynamicPricingForFloor(activeFloor);
    } catch (error) {
        showToast(error.message || 'Failed to load rooms', 'danger');
        loading.style.display = 'none';
        empty.style.display = 'block';
    }
}

function groupRoomsByFloorAndType(rooms) {
    const grouped = { 1: {}, 2: {}, 3: {}, 4: {}, 5: {} };

    rooms.forEach(room => {
        if (!grouped[room.floor]) return;
        const typeKey = room.room_type_name || 'Uncategorized';
        if (!grouped[room.floor][typeKey]) {
            grouped[room.floor][typeKey] = [];
        }
        grouped[room.floor][typeKey].push(room);
    });

    Object.values(grouped).forEach(typeMap => {
        Object.values(typeMap).forEach(list => list.sort((a, b) => String(a.room_no).localeCompare(String(b.room_no))));
    });

    return grouped;
}

function renderSummary(rooms) {
    const total = rooms.length;
    const available = rooms.filter(room => room.is_bookable).length;
    const booked = total - available;

    const avgPrice = total > 0
        ? Math.round(rooms.reduce((sum, room) => sum + Number(room.dynamic_rent || room.rent || 0), 0) / total)
        : 0;

    document.getElementById('totalRoomsMetric').textContent = String(total);
    document.getElementById('availableRoomsMetric').textContent = String(available);
    document.getElementById('bookedRoomsMetric').textContent = String(booked);
    document.getElementById('avgPriceMetric').textContent = `₹${avgPrice.toLocaleString()}`;
}

function renderFloorCounts(rooms) {
    for (let floor = 1; floor <= 5; floor++) {
        const count = rooms.filter(room => room.floor === floor).length;
        const tag = document.getElementById(`floorTabCount${floor}`);
        if (tag) tag.textContent = String(count);
    }
}

function buildRoomCard(room) {
    const features = room.features.slice(0, 4)
        .map(feature => `<span class="feature-pill">${escapeHtml(feature)}</span>`)
        .join('');

    const statusClass = room.is_bookable ? 'status-available' : 'status-booked';
    const safeDescription = escapeHtml(room.description || 'Comfort-focused accommodation with curated in-room amenities.');

    return `
        <article class="room-card">
            <img src="${escapeHtml(room.image_url)}" alt="Room ${escapeHtml(room.room_no)}" class="room-image" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='${escapeHtml(room.image_fallback_url || roomImageFallback)}';">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="room-no">Room ${escapeHtml(room.room_no)}</div>
                        <div class="text-muted small">${escapeHtml(room.room_type_name)} • ${escapeHtml((floorConfig[room.floor] || {}).title || ('Wing ' + room.floor))}</div>
                    </div>
                    <span class="status-pill ${statusClass}">${room.status_label}</span>
                </div>

                <div class="small text-muted mb-2">${safeDescription}</div>

                <div class="mb-2">${features}</div>

                <div class="room-price mb-3">
                    <div class="dynamic-price" id="price-${room.room_id}">₹${Number(room.rent).toLocaleString()}</div>
                    <div class="meta" id="price-meta-${room.room_id}">Base rate per night</div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2 small text-muted">
                    <span>Tier ${room.tier}</span>
                    <span>Type: ${escapeHtml(room.room_type_name)}</span>
                </div>

                <div class="d-flex gap-2 room-actions">
                    <a href="room-detail.php?id=${room.room_id}" class="btn btn-outline-info flex-grow-1">View</a>
                    ${room.is_bookable
                        ? `<button class="btn btn-luxury flex-grow-1" onclick="bookRoom(${room.room_id})">Book</button>`
                        : '<button class="btn btn-outline-secondary flex-grow-1" disabled>Booked</button>'}
                </div>
            </div>
        </article>
    `;
}

function renderFloorAccordion(grouped) {
    const accordion = document.getElementById('floorAccordion');
    let html = '';

    for (let floor = 1; floor <= 5; floor++) {
        const floorMeta = floorConfig[floor];
        const typeMap = grouped[floor] || {};
        const floorRoomsCount = Object.values(typeMap).reduce((sum, arr) => sum + arr.length, 0);

        const typeSections = floorRoomsCount === 0
            ? '<div class="empty-floor">No rooms found for this floor with current filters.</div>'
            : Object.entries(typeMap).map(([typeName, rooms]) => `
                <section class="mb-3">
                    <div class="room-type-chip">
                        <i class="fas fa-bed"></i>
                        ${escapeHtml(typeName)}
                        <span class="badge bg-dark">${rooms.length}</span>
                    </div>
                    <div class="room-grid">
                        ${rooms.map(buildRoomCard).join('')}
                    </div>
                </section>
            `).join('');

        html += `
            <div class="accordion-item" data-floor-item="${floor}">
                <h2 class="accordion-header" id="heading-${floor}">
                    <button class="accordion-button ${floor === activeFloor ? '' : 'collapsed'}" type="button"
                        data-mdb-toggle="collapse" data-mdb-target="#collapse-${floor}" aria-expanded="${floor === activeFloor ? 'true' : 'false'}"
                        aria-controls="collapse-${floor}" onclick="setActiveFloor(${floor})">
                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                            <div>
                                <i class="fas ${floorMeta.icon || 'fa-building'} me-2"></i>
                                ${floorMeta.title} (${floorMeta.range})
                                <div class="small text-muted">Primary mix: ${floorMeta.focus}</div>
                            </div>
                            <span class="badge badge-luxury">${floorRoomsCount} Rooms</span>
                        </div>
                    </button>
                </h2>
                <div id="collapse-${floor}" class="accordion-collapse collapse ${floor === activeFloor ? 'show' : ''}"
                    aria-labelledby="heading-${floor}" data-mdb-parent="#floorAccordion">
                    <div class="accordion-body">${typeSections}</div>
                </div>
            </div>
        `;
    }

    accordion.innerHTML = html;
}

function setActiveFloor(floor) {
    activeFloor = floor;
    document.querySelectorAll('#floorTabs .nav-link').forEach(link => {
        link.classList.toggle('active', Number(link.dataset.floor) === floor);
    });
    refreshDynamicPricingForFloor(floor);
}

function openFloor(floor, scrollIntoView = true) {
    activeFloor = floor;
    document.querySelectorAll('#floorTabs .nav-link').forEach(link => {
        link.classList.toggle('active', Number(link.dataset.floor) === floor);
    });

    const target = document.getElementById(`collapse-${floor}`);
    if (target) {
        const collapse = mdb.Collapse.getOrCreateInstance(target, { toggle: false });
        collapse.show();
        if (scrollIntoView) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

async function getDynamicNightlyPrice(roomId, checkIn, checkOut) {
    const cacheKey = `${roomId}:${checkIn}:${checkOut}`;
    if (pricingCache.has(cacheKey)) {
        return pricingCache.get(cacheKey);
    }

    const response = await fetch(`../api/pricing.php?room_id=${encodeURIComponent(roomId)}&check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`);
    const result = await response.json();

    if (!result.success || !result.data) {
        throw new Error(result.error || 'Dynamic pricing unavailable');
    }

    pricingCache.set(cacheKey, result.data);
    return result.data;
}

async function refreshDynamicPricingForFloor(floor) {
    const checkIn = document.getElementById('checkInFilter').value;
    const checkOut = document.getElementById('checkOutFilter').value;

    if (!checkIn || !checkOut || checkOut <= checkIn) {
        return;
    }

    const floorRooms = allRooms.filter(room => room.floor === floor && room.is_bookable);

    for (const room of floorRooms) {
        const priceEl = document.getElementById(`price-${room.room_id}`);
        const metaEl = document.getElementById(`price-meta-${room.room_id}`);
        if (!priceEl || !metaEl) continue;

        try {
            metaEl.textContent = 'Loading dynamic rate...';
            const quote = await getDynamicNightlyPrice(room.room_id, checkIn, checkOut);
            const nightly = Number(quote.average_nightly_rate || room.rent);
            room.dynamic_rent = nightly;

            priceEl.textContent = `₹${Math.round(nightly).toLocaleString()}`;
            metaEl.textContent = `Dynamic (${quote.nights || 1} night${Number(quote.nights) === 1 ? '' : 's'})`;
        } catch (error) {
            room.dynamic_rent = room.rent;
            priceEl.textContent = `₹${Math.round(room.rent).toLocaleString()}`;
            metaEl.textContent = 'Base rate per night';
        }
    }

    renderSummary(allRooms);
}

function clearFilters() {
    const form = document.getElementById('filterForm');
    form.reset();

    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);

    document.getElementById('checkInFilter').value = today.toISOString().split('T')[0];
    document.getElementById('checkOutFilter').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('availabilityFilter').value = 'all';
    document.getElementById('roomNoFilter').value = '';

    loadRooms();
}

function bookRoom(roomId) {
    <?php if (isLoggedIn()): ?>
    const checkIn = document.getElementById('checkInFilter').value;
    const checkOut = document.getElementById('checkOutFilter').value;

    if (!checkIn || !checkOut || checkOut <= checkIn) {
        showToast('Select a valid check-in and check-out date first', 'warning');
        return;
    }

    window.location.href = `booking.php?room_id=${encodeURIComponent(roomId)}&check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`;
    <?php else: ?>
    const loginModal = new mdb.Modal(document.getElementById('loginModal'));
    loginModal.show();
    <?php endif; ?>
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
            setTimeout(() => {
                window.location.href = response.redirect || 'dashboard.php';
            }, 600);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

async function handleRegister(event) {
    event.preventDefault();
    const formData = new FormData(event.target);

    const name = String(formData.get('name') || '').trim();
    const email = String(formData.get('email') || '').trim();
    const phone = String(formData.get('phone') || '').trim().replace(/\D+/g, '');
    const password = String(formData.get('password') || '');
    const confirmPassword = String(formData.get('confirm_password') || '');

    if (!name || !email || !phone || !password || !confirmPassword) {
        showToast('Required fields missing', 'warning');
        return;
    }

    if (password.length < 8) {
        showToast('Use at least 8 characters for password', 'warning');
        return;
    }

    if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password) || !/[^A-Za-z0-9]/.test(password)) {
        showToast('Password must include uppercase, lowercase, number, and special character', 'warning');
        return;
    }

    if (password !== confirmPassword) {
        showToast('Password mismatch', 'warning');
        return;
    }

    try {
        const response = await apiRequest('../api/auth/register.php', {
            method: 'POST',
            body: JSON.stringify({
                name,
                email,
                phone,
                password,
                confirm_password: confirmPassword
            })
        });

        if (response.success) {
            showToast('Registration successful', 'success');
            setTimeout(() => {
                window.location.href = response.redirect || 'dashboard.php';
            }, 700);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

function switchToRegister() {
    const login = mdb.Modal.getInstance(document.getElementById('loginModal'));
    if (login) login.hide();

    setTimeout(() => {
        const register = new mdb.Modal(document.getElementById('registerModal'));
        register.show();
    }, 250);
}

function switchToLogin() {
    const register = mdb.Modal.getInstance(document.getElementById('registerModal'));
    if (register) register.hide();

    setTimeout(() => {
        const login = new mdb.Modal(document.getElementById('loginModal'));
        login.show();
    }, 250);
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
    initializeDateFilters();

    document.getElementById('filterForm').addEventListener('submit', event => {
        event.preventDefault();
        loadRooms();
    });

    document.getElementById('availabilityFilter').addEventListener('change', loadRooms);
    document.getElementById('roomNoFilter').addEventListener('input', () => {
        clearTimeout(window.__roomFilterDebounce);
        window.__roomFilterDebounce = setTimeout(loadRooms, 250);
    });

    document.querySelectorAll('#floorTabs .nav-link').forEach(button => {
        button.addEventListener('click', async () => {
            const floor = Number(button.dataset.floor || 1);
            openFloor(floor);
            await refreshDynamicPricingForFloor(floor);
        });
    });

    loadRooms();
});
</script>

<?php include '../includes/footer.php'; ?>
