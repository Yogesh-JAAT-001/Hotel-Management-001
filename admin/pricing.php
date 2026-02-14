<?php
require_once '../config.php';
require_once '../includes/pricing-engine.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Dynamic Pricing Engine';
include '../includes/header.php';

try {
    $settings = getDynamicPricingSettings($pdo);
    $seasons = getDynamicPricingSeasons($pdo);
    $diagnostics = getPricingDiagnostics($pdo);
    $roomsStmt = $pdo->query("SELECT room_id, room_no, rent, status FROM ROOMS ORDER BY room_no ASC");
    $rooms = $roomsStmt->fetchAll();
} catch (Exception $e) {
    error_log('Pricing admin page error: ' . $e->getMessage());
    $settings = [];
    $seasons = [];
    $diagnostics = [
        'sellable_rooms' => 0,
        'booked_today' => 0,
        'today_occupancy_rate' => 0,
        'today_demand_score' => 0
    ];
    $rooms = [];
}
?>

<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar-luxury collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h4 class="luxury-header text-warning"><?php echo APP_NAME; ?></h4>
                    <p class="text-light small">Admin Panel</p>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="rooms.php">
                            <i class="fas fa-bed me-2"></i>Rooms
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reservations.php">
                            <i class="fas fa-calendar-check me-2"></i>Reservations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="guests.php">
                            <i class="fas fa-users me-2"></i>Guests
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="staff.php">
                            <i class="fas fa-user-tie me-2"></i>Staff
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="food.php">
                            <i class="fas fa-utensils me-2"></i>Food & Dining
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="pricing.php">
                            <i class="fas fa-chart-line me-2"></i>Pricing Engine
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link text-danger" href="#" onclick="logout()">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="luxury-header">Dynamic Pricing Engine</h1>
                <div class="btn-toolbar">
                    <button class="btn btn-luxury btn-sm" onclick="savePricingSettings()">
                        <i class="fas fa-save me-1"></i>Save Settings
                    </button>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stats-card card-luxury h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Engine Status</p>
                            <h5 class="mb-0 <?php echo ((int)($settings['is_enabled'] ?? 0) === 1) ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ((int)($settings['is_enabled'] ?? 0) === 1) ? 'Enabled' : 'Disabled'; ?>
                            </h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card card-luxury h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Today Occupancy</p>
                            <h5 class="mb-0 text-warning"><?php echo number_format(($diagnostics['today_occupancy_rate'] ?? 0) * 100, 2); ?>%</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card card-luxury h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Today Demand Score</p>
                            <h5 class="mb-0 text-info"><?php echo (int)($diagnostics['today_demand_score'] ?? 0); ?></h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card card-luxury h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1">Configured Seasons</p>
                            <h5 class="mb-0 text-primary"><?php echo count($seasons); ?></h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-7 mb-4">
                    <div class="card card-luxury">
                        <div class="card-header">
                            <h5 class="mb-0">Pricing Configuration</h5>
                        </div>
                        <div class="card-body">
                            <form id="pricingSettingsForm">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Engine Enabled</label>
                                        <select class="form-select" id="is_enabled">
                                            <option value="1" <?php echo ((int)($settings['is_enabled'] ?? 1) === 1) ? 'selected' : ''; ?>>Yes</option>
                                            <option value="0" <?php echo ((int)($settings['is_enabled'] ?? 1) === 0) ? 'selected' : ''; ?>>No</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Min Multiplier</label>
                                        <input type="number" class="form-control" id="min_multiplier" step="0.01" value="<?php echo htmlspecialchars($settings['min_multiplier'] ?? '0.70'); ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Max Multiplier</label>
                                        <input type="number" class="form-control" id="max_multiplier" step="0.01" value="<?php echo htmlspecialchars($settings['max_multiplier'] ?? '1.80'); ?>">
                                    </div>
                                </div>

                                <h6 class="mt-3 mb-2">Occupancy Sensitivity</h6>
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Low Threshold</label>
                                        <input type="number" class="form-control" id="occupancy_low_threshold" step="0.01" value="<?php echo htmlspecialchars($settings['occupancy_low_threshold'] ?? '0.40'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">High Threshold</label>
                                        <input type="number" class="form-control" id="occupancy_high_threshold" step="0.01" value="<?php echo htmlspecialchars($settings['occupancy_high_threshold'] ?? '0.75'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Low Adj.</label>
                                        <input type="number" class="form-control" id="occupancy_low_adjustment" step="0.01" value="<?php echo htmlspecialchars($settings['occupancy_low_adjustment'] ?? '-0.10'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">High Adj.</label>
                                        <input type="number" class="form-control" id="occupancy_high_adjustment" step="0.01" value="<?php echo htmlspecialchars($settings['occupancy_high_adjustment'] ?? '0.15'); ?>">
                                    </div>
                                </div>

                                <h6 class="mt-3 mb-2">Demand Sensitivity</h6>
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Window Days</label>
                                        <input type="number" class="form-control" id="demand_window_days" value="<?php echo htmlspecialchars($settings['demand_window_days'] ?? '7'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Low Demand</label>
                                        <input type="number" class="form-control" id="demand_low_threshold" value="<?php echo htmlspecialchars($settings['demand_low_threshold'] ?? '2'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">High Demand</label>
                                        <input type="number" class="form-control" id="demand_high_threshold" value="<?php echo htmlspecialchars($settings['demand_high_threshold'] ?? '8'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Low Adj.</label>
                                        <input type="number" class="form-control" id="demand_low_adjustment" step="0.01" value="<?php echo htmlspecialchars($settings['demand_low_adjustment'] ?? '-0.05'); ?>">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">High Adj.</label>
                                        <input type="number" class="form-control" id="demand_high_adjustment" step="0.01" value="<?php echo htmlspecialchars($settings['demand_high_adjustment'] ?? '0.10'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Last Minute Days</label>
                                        <input type="number" class="form-control" id="lead_time_last_minute_days" value="<?php echo htmlspecialchars($settings['lead_time_last_minute_days'] ?? '3'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Early Bird Days</label>
                                        <input type="number" class="form-control" id="lead_time_early_bird_days" value="<?php echo htmlspecialchars($settings['lead_time_early_bird_days'] ?? '30'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Last Minute Adj.</label>
                                        <input type="number" class="form-control" id="lead_time_last_minute_adjustment" step="0.01" value="<?php echo htmlspecialchars($settings['lead_time_last_minute_adjustment'] ?? '0.12'); ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Early Bird Adj.</label>
                                        <input type="number" class="form-control" id="lead_time_early_bird_adjustment" step="0.01" value="<?php echo htmlspecialchars($settings['lead_time_early_bird_adjustment'] ?? '-0.08'); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Global Adj.</label>
                                        <input type="number" class="form-control" id="manual_global_adjustment" step="0.01" value="<?php echo htmlspecialchars($settings['manual_global_adjustment'] ?? '0.00'); ?>">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 mb-4">
                    <div class="card card-luxury mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Season Multipliers</h5>
                            <button class="btn btn-sm btn-luxury" onclick="openSeasonModal()">
                                <i class="fas fa-plus me-1"></i>Add
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Window</th>
                                            <th>Mult.</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="seasonTableBody">
                                        <?php foreach ($seasons as $season): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($season['name']); ?></td>
                                            <td><?php echo htmlspecialchars($season['start_mmdd']); ?> to <?php echo htmlspecialchars($season['end_mmdd']); ?></td>
                                            <td><?php echo number_format((float)$season['multiplier'], 2); ?>x</td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" onclick='openSeasonModal(<?php echo json_encode($season); ?>)'>
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteSeason(<?php echo (int)$season['season_id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card card-luxury">
                        <div class="card-header">
                            <h5 class="mb-0">Demo Scenario Simulator</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <label class="form-label">Room</label>
                                <select id="scenarioRoomId" class="form-select">
                                    <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo (int)$room['room_id']; ?>">Room <?php echo htmlspecialchars($room['room_no']); ?> (Base ₹<?php echo number_format((float)$room['rent'], 2); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row mb-2">
                                <div class="col">
                                    <label class="form-label">Check-in</label>
                                    <input type="date" id="scenarioCheckIn" class="form-control">
                                </div>
                                <div class="col">
                                    <label class="form-label">Check-out</label>
                                    <input type="date" id="scenarioCheckOut" class="form-control">
                                </div>
                            </div>
                            <button class="btn btn-luxury w-100" onclick="runScenario()">
                                <i class="fas fa-play me-1"></i>Run Scenario
                            </button>
                            <div id="scenarioResult" class="mt-3 small text-muted"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<div class="modal fade" id="seasonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="seasonModalTitle">Add Season</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="season_id">
                <div class="mb-2">
                    <label class="form-label">Season Name</label>
                    <input type="text" id="season_name" class="form-control">
                </div>
                <div class="row mb-2">
                    <div class="col">
                        <label class="form-label">Start (MM-DD)</label>
                        <input type="text" id="season_start" class="form-control" placeholder="04-01">
                    </div>
                    <div class="col">
                        <label class="form-label">End (MM-DD)</label>
                        <input type="text" id="season_end" class="form-control" placeholder="06-30">
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col">
                        <label class="form-label">Multiplier</label>
                        <input type="number" id="season_multiplier" class="form-control" step="0.01" value="1.00">
                    </div>
                    <div class="col">
                        <label class="form-label">Priority</label>
                        <input type="number" id="season_priority" class="form-control" value="1">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Description</label>
                    <input type="text" id="season_description" class="form-control">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="season_active" checked>
                    <label class="form-check-label" for="season_active">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-luxury" onclick="saveSeason()">Save Season</button>
            </div>
        </div>
    </div>
</div>

<script>
const seasonModalElement = document.getElementById('seasonModal');
const seasonModal = new mdb.Modal(seasonModalElement);

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    const checkIn = today.toISOString().split('T')[0];
    const checkOutDate = new Date(today);
    checkOutDate.setDate(checkOutDate.getDate() + 2);
    const checkOut = checkOutDate.toISOString().split('T')[0];
    document.getElementById('scenarioCheckIn').value = checkIn;
    document.getElementById('scenarioCheckOut').value = checkOut;
});

function readSettingsForm() {
    return {
        type: 'settings',
        is_enabled: Number(document.getElementById('is_enabled').value),
        min_multiplier: Number(document.getElementById('min_multiplier').value),
        max_multiplier: Number(document.getElementById('max_multiplier').value),
        occupancy_low_threshold: Number(document.getElementById('occupancy_low_threshold').value),
        occupancy_high_threshold: Number(document.getElementById('occupancy_high_threshold').value),
        occupancy_low_adjustment: Number(document.getElementById('occupancy_low_adjustment').value),
        occupancy_high_adjustment: Number(document.getElementById('occupancy_high_adjustment').value),
        demand_window_days: Number(document.getElementById('demand_window_days').value),
        demand_low_threshold: Number(document.getElementById('demand_low_threshold').value),
        demand_high_threshold: Number(document.getElementById('demand_high_threshold').value),
        demand_low_adjustment: Number(document.getElementById('demand_low_adjustment').value),
        demand_high_adjustment: Number(document.getElementById('demand_high_adjustment').value),
        lead_time_last_minute_days: Number(document.getElementById('lead_time_last_minute_days').value),
        lead_time_early_bird_days: Number(document.getElementById('lead_time_early_bird_days').value),
        lead_time_last_minute_adjustment: Number(document.getElementById('lead_time_last_minute_adjustment').value),
        lead_time_early_bird_adjustment: Number(document.getElementById('lead_time_early_bird_adjustment').value),
        manual_global_adjustment: Number(document.getElementById('manual_global_adjustment').value)
    };
}

async function savePricingSettings() {
    try {
        const payload = readSettingsForm();
        const response = await apiRequest('../api/admin/pricing.php', {
            method: 'PUT',
            body: JSON.stringify(payload)
        });
        if (response.success) {
            showToast('Pricing settings saved successfully', 'success');
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

function openSeasonModal(season = null) {
    document.getElementById('season_id').value = season ? season.season_id : '';
    document.getElementById('season_name').value = season ? season.name : '';
    document.getElementById('season_start').value = season ? season.start_mmdd : '';
    document.getElementById('season_end').value = season ? season.end_mmdd : '';
    document.getElementById('season_multiplier').value = season ? season.multiplier : '1.00';
    document.getElementById('season_priority').value = season ? season.priority : '1';
    document.getElementById('season_description').value = season ? (season.description || '') : '';
    document.getElementById('season_active').checked = season ? Number(season.is_active) === 1 : true;
    document.getElementById('seasonModalTitle').textContent = season ? 'Edit Season' : 'Add Season';
    seasonModal.show();
}

async function saveSeason() {
    const seasonId = Number(document.getElementById('season_id').value || 0);
    const payload = {
        season_id: seasonId,
        name: document.getElementById('season_name').value.trim(),
        start_mmdd: document.getElementById('season_start').value.trim(),
        end_mmdd: document.getElementById('season_end').value.trim(),
        multiplier: Number(document.getElementById('season_multiplier').value),
        priority: Number(document.getElementById('season_priority').value),
        description: document.getElementById('season_description').value.trim(),
        is_active: document.getElementById('season_active').checked ? 1 : 0
    };

    try {
        let response;
        if (seasonId > 0) {
            payload.type = 'season';
            response = await apiRequest('../api/admin/pricing.php', {
                method: 'PUT',
                body: JSON.stringify(payload)
            });
        } else {
            response = await apiRequest('../api/admin/pricing.php', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
        }

        if (response.success) {
            showToast('Season saved successfully', 'success');
            seasonModal.hide();
            setTimeout(() => location.reload(), 600);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

async function deleteSeason(seasonId) {
    if (!confirm('Delete this season rule?')) return;

    try {
        const response = await apiRequest('../api/admin/pricing.php', {
            method: 'DELETE',
            body: JSON.stringify({ season_id: seasonId })
        });
        if (response.success) {
            showToast('Season deleted successfully', 'success');
            setTimeout(() => location.reload(), 600);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

async function runScenario() {
    const roomId = document.getElementById('scenarioRoomId').value;
    const checkIn = document.getElementById('scenarioCheckIn').value;
    const checkOut = document.getElementById('scenarioCheckOut').value;
    const resultDiv = document.getElementById('scenarioResult');

    if (!roomId || !checkIn || !checkOut) {
        resultDiv.textContent = 'Select room and valid dates.';
        return;
    }

    try {
        const response = await fetch(`../api/pricing.php?room_id=${encodeURIComponent(roomId)}&check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`);
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Failed to simulate scenario');
        }

        const quote = result.data;
        resultDiv.innerHTML = `
            <div class="alert alert-info mb-0">
                <div><strong>Base Total:</strong> ₹${Number(quote.base_total).toFixed(2)}</div>
                <div><strong>Dynamic Total:</strong> ₹${Number(quote.dynamic_total).toFixed(2)}</div>
                <div><strong>Average Multiplier:</strong> ${Number(quote.average_multiplier).toFixed(4)}x</div>
                <div><strong>Demand Score:</strong> ${Number(quote.demand_score)}</div>
            </div>
        `;
    } catch (error) {
        resultDiv.innerHTML = `<div class="alert alert-danger mb-0">${error.message}</div>`;
    }
}

async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            await apiRequest('../api/auth/logout.php', { method: 'POST' });
            window.location.href = 'login.php';
        } catch (error) {
            showToast('Unable to logout right now. Please try again.', 'danger');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
