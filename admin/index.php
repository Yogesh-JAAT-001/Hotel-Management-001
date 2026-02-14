<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Admin BI Dashboard';
$additional_css = ''
    . '<link rel="stylesheet" href="' . appPath('/assets/css/dashboard.css') . '">' 
    . '<link rel="stylesheet" href="' . appPath('/assets/css/analytics.css') . '">';
$additional_js = ''
    . '<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>'
    . '<script src="' . appPath('/assets/js/admin-analytics.js') . '"></script>';
include '../includes/header.php';

$totalRooms = 0;
$totalReservations = 0;
$totalRevenue = 0.0;

try {
    $totalRooms = (int)($pdo->query("SELECT COUNT(*) AS c FROM ROOMS")->fetch()['c'] ?? 0);
    $totalReservations = (int)($pdo->query("SELECT COUNT(*) AS c FROM RESERVATION")->fetch()['c'] ?? 0);
    $totalRevenue = (float)($pdo->query("SELECT COALESCE(SUM(total_price), 0) AS total FROM RESERVATION WHERE status IN ('Confirmed','Checked-in','Checked-out')")->fetch()['total'] ?? 0);
} catch (Throwable $e) {
    error_log('Admin index initial stat error: ' . $e->getMessage());
}
?>

<div class="container-fluid dashboard-shell">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar-luxury collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h4 class="luxury-header text-warning"><?php echo APP_NAME; ?></h4>
                    <p class="text-light small">Admin BI Panel</p>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link active" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="manager.php"><i class="fas fa-briefcase me-2"></i>Manager Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="rooms.php"><i class="fas fa-bed me-2"></i>Rooms</a></li>
                    <li class="nav-item"><a class="nav-link" href="reservations.php"><i class="fas fa-calendar-check me-2"></i>Reservations</a></li>
                    <li class="nav-item"><a class="nav-link" href="pricing.php"><i class="fas fa-chart-line me-2"></i>Pricing Engine</a></li>
                    <li class="nav-item"><a class="nav-link" href="guests.php"><i class="fas fa-users me-2"></i>Guests</a></li>
                    <li class="nav-item"><a class="nav-link" href="staff.php"><i class="fas fa-user-tie me-2"></i>Staff</a></li>
                    <li class="nav-item"><a class="nav-link" href="food.php"><i class="fas fa-utensils me-2"></i>Food & Dining</a></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Economics</a></li>
                    <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li class="nav-item mt-3"><a class="nav-link text-danger" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <section class="dashboard-banner mb-4" data-reveal>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h2 class="mb-2">Hotel Business Intelligence Command Center</h2>
                        <p class="mb-0">Real-time revenue, occupancy, dining demand, and operating efficiency analytics for The Heartland Abode.</p>
                    </div>
                    <div class="export-row">
                        <button class="btn btn-outline-light btn-sm" type="button" onclick="exportMonthlyReport('csv')"><i class="fas fa-file-csv me-1"></i>Monthly CSV</button>
                        <button class="btn btn-outline-light btn-sm" type="button" onclick="exportMonthlyReport('xls')"><i class="fas fa-file-excel me-1"></i>Monthly Excel</button>
                        <button class="btn btn-outline-light btn-sm" type="button" onclick="exportChartData('csv')"><i class="fas fa-chart-line me-1"></i>Chart Data</button>
                        <button class="btn btn-outline-light btn-sm" type="button" onclick="downloadDashboardPdf()"><i class="fas fa-file-pdf me-1"></i>BI PDF</button>
                    </div>
                </div>
            </section>

            <section class="analytics-section mb-4" data-reveal>
                <div class="section-header">
                    <h5 class="mb-1">Global Filters</h5>
                    <p class="small mb-0">Refreshes stats, charts, top performers, and AI-style business insights dynamically.</p>
                </div>
                <div class="analytics-toolbar">
                    <div class="field field-sm">
                        <label class="form-label" for="dashFromDate">From Date</label>
                        <input type="date" id="dashFromDate" class="form-control">
                    </div>
                    <div class="field field-sm">
                        <label class="form-label" for="dashToDate">To Date</label>
                        <input type="date" id="dashToDate" class="form-control">
                    </div>
                    <div class="field field-sm">
                        <label class="form-label" for="dashMonth">Month</label>
                        <select id="dashMonth" class="form-select">
                            <option value="">All</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('M', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="field field-sm">
                        <label class="form-label" for="dashYear">Year</label>
                        <select id="dashYear" class="form-select">
                            <option value="">All</option>
                            <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="actions">
                        <button class="btn btn-luxury btn-sm" type="button" onclick="loadDashboard()"><i class="fas fa-sync-alt me-1"></i>Refresh</button>
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="resetDashboardFilters()"><i class="fas fa-rotate-left me-1"></i>Reset</button>
                    </div>
                </div>
            </section>

            <section class="analytics-section mb-4" data-reveal>
                <div class="section-header">
                    <h5 class="mb-0">Hotel Performance Overview</h5>
                </div>
                <div class="analytics-grid">
                    <div class="master-grid">
                        <article class="master-card">
                            <span class="icon"><i class="fas fa-trophy"></i></span>
                            <div class="title">Top Food Item</div>
                            <div class="value" id="statTopFood">N/A</div>
                        </article>
                        <article class="master-card">
                            <span class="icon"><i class="fas fa-hotel"></i></span>
                            <div class="title">Most Booked Room Type</div>
                            <div class="value" id="statTopRoom">N/A</div>
                        </article>
                        <article class="master-card">
                            <span class="icon"><i class="fas fa-chart-line"></i></span>
                            <div class="title">Highest Revenue Month</div>
                            <div class="value" id="statHighMonth">N/A</div>
                        </article>
                        <article class="master-card">
                            <span class="icon"><i class="fas fa-fire"></i></span>
                            <div class="title">Peak Booking Season</div>
                            <div class="value" id="statPeakSeason">N/A</div>
                        </article>
                        <article class="master-card">
                            <span class="icon"><i class="fas fa-layer-group"></i></span>
                            <div class="title">Best Performing Floor</div>
                            <div class="value" id="statBestFloor">N/A</div>
                        </article>
                        <article class="master-card">
                            <span class="icon"><i class="fas fa-coins"></i></span>
                            <div class="title">Most Profitable Floor</div>
                            <div class="value" id="statProfitFloor">N/A</div>
                        </article>
                        <article class="master-card">
                            <span class="icon"><i class="fas fa-coins"></i></span>
                            <div class="title">Highest Profit Category</div>
                            <div class="value" id="statProfitCategory">N/A</div>
                        </article>
                        <article class="master-card">
                            <span class="icon"><i class="fas fa-crown"></i></span>
                            <div class="title">Best Customer</div>
                            <div class="value" id="statBestCustomer">N/A</div>
                        </article>
                        <article class="master-card">
                            <span class="icon"><i class="fas fa-ban"></i></span>
                            <div class="title">Cancellation Rate</div>
                            <div class="value" id="statCancelRate">0%</div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="dashboard-kpi-grid mb-4" data-reveal>
                <article class="kpi-card">
                    <span class="kpi-icon"><i class="fas fa-calendar-check"></i></span>
                    <div class="kpi-label">Bookings</div>
                    <div class="kpi-value" id="kpiBookings"><?php echo number_format($totalReservations); ?></div>
                    <div class="kpi-meta">Total reservations in selected period</div>
                </article>
                <article class="kpi-card">
                    <span class="kpi-icon"><i class="fas fa-rupee-sign"></i></span>
                    <div class="kpi-label">Revenue</div>
                    <div class="kpi-value" id="kpiRevenue"><?php echo '₹' . number_format($totalRevenue, 0); ?></div>
                    <div class="kpi-meta">Room + dining + service revenue</div>
                </article>
                <article class="kpi-card">
                    <span class="kpi-icon"><i class="fas fa-building"></i></span>
                    <div class="kpi-label">Room Inventory</div>
                    <div class="kpi-value" id="kpiRooms"><?php echo number_format($totalRooms); ?></div>
                    <div class="kpi-meta">Total active room inventory</div>
                </article>
                <article class="kpi-card">
                    <span class="kpi-icon"><i class="fas fa-sack-dollar"></i></span>
                    <div class="kpi-label">Net Profit</div>
                    <div class="kpi-value" id="kpiProfit">₹0</div>
                    <div class="kpi-meta" id="kpiProfitMeta">Profit margin 0%</div>
                </article>
            </section>

            <section class="analytics-grid cols-2 mb-4" data-reveal>
                <article class="analytics-card">
                    <div class="card-head">
                        <h6 class="mb-0">Revenue & Booking Trend</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('dashRevenueChart', 'dashboard-revenue-bookings.png')"><i class="fas fa-image me-1"></i>PNG</button>
                    </div>
                    <div class="card-body"><div class="chart-shell"><canvas id="dashRevenueBookingChart"></canvas></div></div>
                </article>
                <article class="analytics-card">
                    <div class="card-head">
                        <h6 class="mb-0">Room Occupancy by Wing</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('dashOccupancyChart', 'dashboard-occupancy.png')"><i class="fas fa-image me-1"></i>PNG</button>
                    </div>
                    <div class="card-body"><div class="chart-shell chart-sm"><canvas id="dashOccupancyChart"></canvas></div></div>
                </article>
            </section>

            <section class="analytics-grid cols-2 mb-4" data-reveal>
                <article class="analytics-card">
                    <div class="card-head">
                        <h6 class="mb-0">Demand Trend (Bookings)</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('dashDemandChart', 'dashboard-demand-trend.png')"><i class="fas fa-image me-1"></i>PNG</button>
                    </div>
                    <div class="card-body"><div class="chart-shell chart-sm"><canvas id="dashDemandChart"></canvas></div></div>
                </article>
                <article class="analytics-card">
                    <div class="card-head">
                        <h6 class="mb-0">Cost Distribution</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('dashCostChart', 'dashboard-cost-distribution.png')"><i class="fas fa-image me-1"></i>PNG</button>
                    </div>
                    <div class="card-body"><div class="chart-shell chart-sm"><canvas id="dashCostChart"></canvas></div></div>
                </article>
            </section>

            <section class="analytics-section mb-4" data-reveal>
                <div class="section-header">
                    <h5 class="mb-0">Hotel Performance Insights</h5>
                </div>
                <div class="analytics-grid">
                    <div id="insightsGrid" class="insights-grid"></div>
                </div>
            </section>

            <section class="analytics-section mb-5" data-reveal>
                <div class="section-header">
                    <h5 class="mb-0">Recent Activity Panels</h5>
                </div>
                <div class="analytics-grid">
                    <div class="activity-grid">
                        <article class="activity-panel">
                            <div class="head"><h6>Recent Reservations</h6><span class="badge badge-luxury">Last 10</span></div>
                            <div class="body">
                                <div class="table-responsive">
                                    <table class="mini-table" id="recentReservationsTable">
                                        <thead>
                                            <tr><th>ID</th><th>Guest</th><th>Status</th><th class="text-end">Amount</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div id="recentReservationsPagination"></div>
                            </div>
                        </article>

                        <article class="activity-panel">
                            <div class="head"><h6>Recent Food Orders</h6><span class="badge badge-luxury">Last 10</span></div>
                            <div class="body">
                                <div class="table-responsive">
                                    <table class="mini-table" id="recentFoodTable">
                                        <thead>
                                            <tr><th>Item</th><th>Category</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div id="recentFoodPagination"></div>
                            </div>
                        </article>

                        <article class="activity-panel">
                            <div class="head"><h6>Recent Payments</h6><span class="badge badge-luxury">Last 10</span></div>
                            <div class="body">
                                <div class="table-responsive">
                                    <table class="mini-table" id="recentPaymentsTable">
                                        <thead>
                                            <tr><th>Txn</th><th>Status</th><th>Method</th><th class="text-end">Amount</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div id="recentPaymentsPagination"></div>
                            </div>
                        </article>

                        <article class="activity-panel">
                            <div class="head"><h6>Recent Staff Activities</h6><span class="badge badge-luxury">Last 10</span></div>
                            <div class="body">
                                <div class="table-responsive">
                                    <table class="mini-table" id="recentStaffTable">
                                        <thead>
                                            <tr><th>Staff</th><th>Role</th><th>Status</th><th>Date</th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div id="recentStaffPagination"></div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
const activityState = {
    reservations_page: 1,
    food_page: 1,
    payments_page: 1,
    staff_page: 1,
    per_page: 10
};

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getDashboardFilters() {
    return AdminAnalytics.parseFilters('dash');
}

function setDashboardDefaults() {
    const today = new Date();
    const from = new Date(today.getFullYear(), today.getMonth() - 11, 1);
    document.getElementById('dashFromDate').value = from.toISOString().split('T')[0];
    document.getElementById('dashToDate').value = today.toISOString().split('T')[0];
}

function resetDashboardFilters() {
    setDashboardDefaults();
    document.getElementById('dashMonth').value = '';
    document.getElementById('dashYear').value = '';
    loadDashboard();
}

function renderTopStats(master) {
    AdminAnalytics.setText('statTopFood', master.top_food_item || 'N/A');
    AdminAnalytics.setText('statTopRoom', master.most_booked_room_type || 'N/A');
    AdminAnalytics.setText('statHighMonth', master.highest_revenue_month || 'N/A');
    AdminAnalytics.setText('statPeakSeason', master.peak_booking_season || 'N/A');
    AdminAnalytics.setText('statBestFloor', master.best_performing_floor || 'N/A');
    AdminAnalytics.setText('statProfitFloor', master.most_profitable_floor || 'N/A');
    AdminAnalytics.setText('statProfitCategory', master.highest_profit_category || 'N/A');
    AdminAnalytics.setText('statBestCustomer', master.best_customer || 'N/A');
    AdminAnalytics.setText('statCancelRate', `${Number(master.cancellation_rate_pct || 0).toFixed(2)}%`);
}

function renderKpis(kpi) {
    AdminAnalytics.setText('kpiBookings', Number(kpi.total_bookings || 0).toLocaleString('en-IN'));
    AdminAnalytics.setText('kpiRevenue', AdminAnalytics.toCurrency(kpi.total_revenue || 0));
    AdminAnalytics.setText('kpiRooms', Number(kpi.total_rooms || 0).toLocaleString('en-IN'));
    AdminAnalytics.setText('kpiProfit', AdminAnalytics.toCurrency(kpi.net_profit || 0));
    AdminAnalytics.setText('kpiProfitMeta', `Profit margin ${Number(kpi.profit_margin_pct || 0).toFixed(2)}%`);
}

function renderBiCharts(chartPayload) {
    const revenue = chartPayload.revenue_booking || {};
    const occupancy = chartPayload.room_occupancy || {};
    const demand = chartPayload.demand_trend || {};
    const cost = chartPayload.cost_distribution || {};
    const occupancyValues = (occupancy.booked_pct && occupancy.booked_pct.length)
        ? occupancy.booked_pct
        : (occupancy.booked || []);

    AdminAnalytics.renderChart('dashRevenueChart', 'dashRevenueBookingChart', {
        data: {
            labels: revenue.labels || [],
            datasets: [
                {
                    type: 'line',
                    label: 'Revenue',
                    data: revenue.revenue || [],
                    borderColor: '#0F1B2D',
                    backgroundColor: 'rgba(15, 27, 45, 0.12)',
                    fill: false,
                    tension: 0.24,
                    yAxisID: 'y'
                },
                {
                    type: 'bar',
                    label: 'Bookings',
                    data: revenue.bookings || [],
                    backgroundColor: 'rgba(212, 175, 55, 0.74)',
                    borderRadius: 8,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: { enabled: true }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Revenue (INR)' }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Bookings' }
                },
                x: {
                    title: { display: true, text: 'Month' }
                }
            }
        }
    });

    AdminAnalytics.renderChart('dashOccupancyChart', 'dashOccupancyChart', {
        type: 'doughnut',
        data: {
            labels: occupancy.labels || [],
            datasets: [{
                data: occupancyValues,
                backgroundColor: ['#0F1B2D', '#D4AF37', '#8B6F2F', '#4F688D', '#B8962E']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '58%',
            plugins: {
                legend: { position: 'top' },
                tooltip: { enabled: true }
            }
        }
    });

    AdminAnalytics.renderChart('dashDemandChart', 'dashDemandChart', {
        type: 'line',
        data: {
            labels: demand.labels || [],
            datasets: [{
                label: 'Bookings',
                data: demand.bookings || [],
                borderColor: '#D4AF37',
                backgroundColor: 'rgba(212, 175, 55, 0.26)',
                fill: true,
                tension: 0.26
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: { enabled: true }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Bookings' }
                },
                x: {
                    title: { display: true, text: 'Month' }
                }
            }
        }
    });

    AdminAnalytics.renderChart('dashCostChart', 'dashCostChart', {
        type: 'pie',
        data: {
            labels: cost.labels || [],
            datasets: [{
                data: cost.values || [],
                backgroundColor: ['#0F1B2D', '#D4AF37', '#8B6F2F', '#6E819D']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: { enabled: true }
            }
        }
    });
}

function renderInsights(insights) {
    const container = document.getElementById('insightsGrid');
    if (!container) return;

    if (!insights || insights.length === 0) {
        container.innerHTML = '<div class="insight-card"><div class="insight-value">No insights available for selected filters.</div></div>';
        return;
    }

    container.innerHTML = insights.map((item) => `
        <article class="insight-card">
            <div class="insight-title">${escapeHtml(item.title || '')}</div>
            <div class="insight-value">${escapeHtml(item.value || 'N/A')}</div>
            <div class="insight-note">${escapeHtml(item.note || '')}</div>
        </article>
    `).join('');
}

function renderRecentReservations(block) {
    const tbody = document.querySelector('#recentReservationsTable tbody');
    if (!tbody) return;

    const rows = (block && block.rows) || [];
    tbody.innerHTML = rows.length
        ? rows.map((r) => `
            <tr>
                <td>#${r.res_id}</td>
                <td>${escapeHtml(r.guest_name || 'Guest')}</td>
                <td>${escapeHtml(r.status || '')}</td>
                <td class="text-end">${AdminAnalytics.toCurrency(r.total_price || 0)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="4" class="text-center text-muted">No reservation activity</td></tr>';

    AdminAnalytics.renderPagination('recentReservationsPagination', block.pagination || {}, (targetPage) => {
        loadActivityPanel('reservations', targetPage);
    });
}

function renderRecentFood(block) {
    const tbody = document.querySelector('#recentFoodTable tbody');
    if (!tbody) return;

    const rows = (block && block.rows) || [];
    tbody.innerHTML = rows.length
        ? rows.map((r) => `
            <tr>
                <td>${escapeHtml(r.item_name || '-')}</td>
                <td>${escapeHtml(r.menu_category || '-')}</td>
                <td class="text-end">${Number(r.quantity || 0)}</td>
                <td class="text-end">${AdminAnalytics.toCurrency(r.line_total || 0)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="4" class="text-center text-muted">No food activity</td></tr>';

    AdminAnalytics.renderPagination('recentFoodPagination', block.pagination || {}, (targetPage) => {
        loadActivityPanel('food_orders', targetPage);
    });
}

function renderRecentPayments(block) {
    const tbody = document.querySelector('#recentPaymentsTable tbody');
    if (!tbody) return;

    const rows = (block && block.rows) || [];
    tbody.innerHTML = rows.length
        ? rows.map((r) => `
            <tr>
                <td>${escapeHtml(r.txn_id || ('PAY-' + r.payment_id))}</td>
                <td>${escapeHtml(r.status || '-')}</td>
                <td>${escapeHtml(r.payment_method || '-')}</td>
                <td class="text-end">${AdminAnalytics.toCurrency(r.amount || 0)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="4" class="text-center text-muted">No payment activity</td></tr>';

    AdminAnalytics.renderPagination('recentPaymentsPagination', block.pagination || {}, (targetPage) => {
        loadActivityPanel('payments', targetPage);
    });
}

function renderRecentStaff(block) {
    const tbody = document.querySelector('#recentStaffTable tbody');
    if (!tbody) return;

    const rows = (block && block.rows) || [];
    tbody.innerHTML = rows.length
        ? rows.map((r) => `
            <tr>
                <td>${escapeHtml(r.staff_name || '-')}</td>
                <td>${escapeHtml(r.role_name || '-')}</td>
                <td>${escapeHtml(r.activity_status || '-')}</td>
                <td>${escapeHtml(r.activity_date ? String(r.activity_date).slice(0, 10) : '-')}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="4" class="text-center text-muted">No staff activity</td></tr>';

    AdminAnalytics.renderPagination('recentStaffPagination', block.pagination || {}, (targetPage) => {
        loadActivityPanel('staff_activities', targetPage);
    });
}

function renderRecentActivityPanels(recent) {
    renderRecentReservations(recent.reservations || {});
    renderRecentFood(recent.food_orders || {});
    renderRecentPayments(recent.payments || {});
    renderRecentStaff(recent.staff_activities || {});
}

async function loadActivityPanel(type, page) {
    try {
        const params = { per_page: activityState.per_page, page };
        const filters = getDashboardFilters();
        Object.assign(params, filters, { activity: type });

        const payload = await AdminAnalytics.fetchDashboardStats(params);
        const data = payload.data || {};

        if (type === 'reservations') {
            activityState.reservations_page = page;
            renderRecentReservations(data);
            return;
        }
        if (type === 'food_orders' || type === 'food') {
            activityState.food_page = page;
            renderRecentFood(data);
            return;
        }
        if (type === 'payments') {
            activityState.payments_page = page;
            renderRecentPayments(data);
            return;
        }
        if (type === 'staff_activities' || type === 'staff') {
            activityState.staff_page = page;
            renderRecentStaff(data);
        }
    } catch (error) {
        showToast(error.message || 'Failed to load recent activity panel', 'danger');
    }
}

async function loadDashboard() {
    try {
        const filters = getDashboardFilters();
        const sharedActivity = {
            reservations_page: activityState.reservations_page,
            food_page: activityState.food_page,
            payments_page: activityState.payments_page,
            staff_page: activityState.staff_page,
            per_page: activityState.per_page
        };

        const [statsPayload, chartPayload, insightPayload] = await Promise.all([
            AdminAnalytics.fetchDashboardStats(Object.assign({}, filters, sharedActivity)),
            AdminAnalytics.fetchCharts(filters),
            AdminAnalytics.fetchInsights(filters)
        ]);

        const stats = statsPayload.data || {};
        const charts = chartPayload.data || {};
        const insights = insightPayload.data || {};

        renderTopStats(stats.master || {});
        renderKpis(stats.kpi || {});
        renderBiCharts(charts);
        renderInsights(insights.insights || []);
        renderRecentActivityPanels(stats.recent_activity || {});
    } catch (error) {
        showToast(error.message || 'Failed to load dashboard analytics', 'danger');
    }
}

function exportMonthlyReport(format) {
    AdminAnalytics.exportAnalytics('monthly', getDashboardFilters(), format || 'csv');
}

function exportChartData(format) {
    AdminAnalytics.exportAnalytics('charts', getDashboardFilters(), format || 'csv');
}

function downloadDashboardPdf() {
    const lines = [
        `Top Food Item: ${document.getElementById('statTopFood').textContent}`,
        `Most Booked Room Type: ${document.getElementById('statTopRoom').textContent}`,
        `Highest Revenue Month: ${document.getElementById('statHighMonth').textContent}`,
        `Peak Booking Season: ${document.getElementById('statPeakSeason').textContent}`,
        `Best Performing Floor: ${document.getElementById('statBestFloor').textContent}`,
        `Most Profitable Floor: ${document.getElementById('statProfitFloor').textContent}`,
        `Highest Profit Category: ${document.getElementById('statProfitCategory').textContent}`,
        `Best Customer: ${document.getElementById('statBestCustomer').textContent}`,
        `Cancellation Rate: ${document.getElementById('statCancelRate').textContent}`,
        `Total Revenue: ${document.getElementById('kpiRevenue').textContent}`,
        `Total Bookings: ${document.getElementById('kpiBookings').textContent}`,
        `Net Profit: ${document.getElementById('kpiProfit').textContent}`
    ];
    AdminAnalytics.downloadSimplePdf('Hotel BI Dashboard Report', lines, 'hotel-bi-dashboard-report.pdf');
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.AdminAnalytics) {
        showToast('Analytics library failed to load.', 'danger');
        return;
    }

    setDashboardDefaults();
    loadDashboard();

    ['dashFromDate', 'dashToDate', 'dashMonth', 'dashYear'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', () => {
            activityState.reservations_page = 1;
            activityState.food_page = 1;
            activityState.payments_page = 1;
            activityState.staff_page = 1;
            loadDashboard();
        });
    });
});

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
