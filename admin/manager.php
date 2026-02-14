<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Manager BI Dashboard';
$additional_css = ''
    . '<link rel="stylesheet" href="' . appPath('/assets/css/dashboard.css') . '">' 
    . '<link rel="stylesheet" href="' . appPath('/assets/css/analytics.css') . '">';
$additional_js = ''
    . '<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>'
    . '<script src="' . appPath('/assets/js/admin-analytics.js') . '"></script>';
include '../includes/header.php';
?>

<div class="container-fluid dashboard-shell">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar-luxury collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h4 class="luxury-header text-warning"><?php echo APP_NAME; ?></h4>
                    <p class="text-light small">Manager Panel</p>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="manager.php"><i class="fas fa-briefcase me-2"></i>Manager Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="food.php"><i class="fas fa-utensils me-2"></i>Food & Dining</a></li>
                    <li class="nav-item"><a class="nav-link" href="rooms.php"><i class="fas fa-bed me-2"></i>Rooms</a></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Economics</a></li>
                    <li class="nav-item"><a class="nav-link" href="staff.php"><i class="fas fa-user-tie me-2"></i>Staff</a></li>
                    <li class="nav-item mt-3"><a class="nav-link text-danger" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <section class="dashboard-banner mb-4" data-reveal>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h2 class="mb-2">Manager Intelligence Dashboard</h2>
                        <p class="mb-0">Operational demand, revenue trends, food performance, and weekly business signals in one view.</p>
                    </div>
                    <div class="export-row">
                        <button class="btn btn-outline-light btn-sm" type="button" onclick="exportManager('monthly', 'csv')"><i class="fas fa-file-csv me-1"></i>Monthly CSV</button>
                        <button class="btn btn-outline-light btn-sm" type="button" onclick="exportManager('quarterly', 'xls')"><i class="fas fa-file-excel me-1"></i>Quarterly Excel</button>
                        <button class="btn btn-outline-light btn-sm" type="button" onclick="downloadManagerPdf()"><i class="fas fa-file-pdf me-1"></i>PDF</button>
                    </div>
                </div>
            </section>

            <section class="analytics-section mb-4" data-reveal>
                <div class="section-header">
                    <h5 class="mb-1">Global Date Filters</h5>
                    <p class="small mb-0">All KPIs, charts, and insights refresh dynamically.</p>
                </div>
                <div class="analytics-toolbar">
                    <div class="field field-sm">
                        <label class="form-label" for="mgrFromDate">From Date</label>
                        <input type="date" id="mgrFromDate" class="form-control">
                    </div>
                    <div class="field field-sm">
                        <label class="form-label" for="mgrToDate">To Date</label>
                        <input type="date" id="mgrToDate" class="form-control">
                    </div>
                    <div class="field field-sm">
                        <label class="form-label" for="mgrMonth">Month</label>
                        <select id="mgrMonth" class="form-select">
                            <option value="">All</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('M', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="field field-sm">
                        <label class="form-label" for="mgrYear">Year</label>
                        <select id="mgrYear" class="form-select">
                            <option value="">All</option>
                            <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="actions">
                        <button class="btn btn-luxury btn-sm" type="button" onclick="loadManagerDashboard()"><i class="fas fa-sync-alt me-1"></i>Refresh</button>
                    </div>
                </div>
            </section>

            <section class="analytics-section mb-4" data-reveal>
                <div class="section-header"><h5 class="mb-0">Hotel Performance Overview</h5></div>
                <div class="analytics-grid">
                    <div class="master-grid">
                        <article class="master-card"><span class="icon"><i class="fas fa-trophy"></i></span><div class="title">Top Food Item</div><div class="value" id="mgrTopFood">N/A</div></article>
                        <article class="master-card"><span class="icon"><i class="fas fa-hotel"></i></span><div class="title">Most Booked Room Type</div><div class="value" id="mgrTopRoom">N/A</div></article>
                        <article class="master-card"><span class="icon"><i class="fas fa-chart-line"></i></span><div class="title">Highest Revenue Month</div><div class="value" id="mgrTopMonth">N/A</div></article>
                        <article class="master-card"><span class="icon"><i class="fas fa-fire"></i></span><div class="title">Peak Booking Season</div><div class="value" id="mgrPeakSeason">N/A</div></article>
                        <article class="master-card"><span class="icon"><i class="fas fa-layer-group"></i></span><div class="title">Best Performing Floor</div><div class="value" id="mgrBestFloor">N/A</div></article>
                        <article class="master-card"><span class="icon"><i class="fas fa-coins"></i></span><div class="title">Most Profitable Floor</div><div class="value" id="mgrProfitFloor">N/A</div></article>
                        <article class="master-card"><span class="icon"><i class="fas fa-coins"></i></span><div class="title">Highest Profit Category</div><div class="value" id="mgrProfitCategory">N/A</div></article>
                        <article class="master-card"><span class="icon"><i class="fas fa-crown"></i></span><div class="title">Best Customer</div><div class="value" id="mgrBestCustomer">N/A</div></article>
                        <article class="master-card"><span class="icon"><i class="fas fa-ban"></i></span><div class="title">Cancellation Rate</div><div class="value" id="mgrCancelRate">0%</div></article>
                    </div>
                </div>
            </section>

            <section class="analytics-grid cols-2 mb-4" data-reveal>
                <article class="analytics-card">
                    <div class="card-head">
                        <h6 class="mb-0">Revenue vs Profit Trend</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('mgrRevenueChart', 'manager-revenue-profit.png')"><i class="fas fa-image me-1"></i>PNG</button>
                    </div>
                    <div class="card-body"><div class="chart-shell"><canvas id="mgrRevenueChartCanvas"></canvas></div></div>
                </article>
                <article class="analytics-card">
                    <div class="card-head">
                        <h6 class="mb-0">Occupancy Trend</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('mgrOccupancyChart', 'manager-occupancy-trend.png')"><i class="fas fa-image me-1"></i>PNG</button>
                    </div>
                    <div class="card-body"><div class="chart-shell"><canvas id="mgrOccupancyChartCanvas"></canvas></div></div>
                </article>
            </section>

            <section class="analytics-grid cols-2 mb-4" data-reveal>
                <article class="analytics-card">
                    <div class="card-head">
                        <h6 class="mb-0">Revenue Split</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('mgrRevenueSplitChart', 'manager-revenue-split.png')"><i class="fas fa-image me-1"></i>PNG</button>
                    </div>
                    <div class="card-body"><div class="chart-shell chart-sm"><canvas id="mgrRevenueSplitCanvas"></canvas></div></div>
                </article>
                <article class="analytics-card">
                    <div class="card-head">
                        <h6 class="mb-0">Top Selling Dishes</h6>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('mgrTopDishChart', 'manager-top-dishes.png')"><i class="fas fa-image me-1"></i>PNG</button>
                    </div>
                    <div class="card-body"><div class="chart-shell chart-sm"><canvas id="mgrTopDishCanvas"></canvas></div></div>
                </article>
            </section>

            <section class="analytics-section mb-4" data-reveal>
                <div class="section-header"><h5 class="mb-0">Hotel Performance Insights</h5></div>
                <div class="analytics-grid">
                    <div id="mgrInsightsGrid" class="insights-grid"></div>
                </div>
            </section>

            <section class="analytics-section mb-5" data-reveal>
                <div class="section-header"><h5 class="mb-0">Recent Activity Snapshot</h5></div>
                <div class="analytics-grid">
                    <div class="activity-grid">
                        <article class="activity-panel">
                            <div class="head"><h6>Reservations</h6></div>
                            <div class="body">
                                <table class="mini-table" id="mgrRecentReservations"><thead><tr><th>ID</th><th>Guest</th><th>Status</th><th class="text-end">Amount</th></tr></thead><tbody></tbody></table>
                            </div>
                        </article>
                        <article class="activity-panel">
                            <div class="head"><h6>Food Orders</h6></div>
                            <div class="body">
                                <table class="mini-table" id="mgrRecentFood"><thead><tr><th>Item</th><th>Category</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead><tbody></tbody></table>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
function managerFilters() {
    return AdminAnalytics.parseFilters('mgr');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function setManagerDefaults() {
    const today = new Date();
    const from = new Date(today.getFullYear(), today.getMonth() - 11, 1);
    document.getElementById('mgrFromDate').value = from.toISOString().split('T')[0];
    document.getElementById('mgrToDate').value = today.toISOString().split('T')[0];
}

function renderManagerInsights(insights) {
    const container = document.getElementById('mgrInsightsGrid');
    if (!container) return;

    if (!insights || insights.length === 0) {
        container.innerHTML = '<div class="insight-card"><div class="insight-value">No insights available.</div></div>';
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

function renderManagerRecent(recent) {
    const rBody = document.querySelector('#mgrRecentReservations tbody');
    const fBody = document.querySelector('#mgrRecentFood tbody');
    if (!rBody || !fBody) return;

    const reservations = (recent.reservations && recent.reservations.rows) || [];
    const foodRows = (recent.food_orders && recent.food_orders.rows) || [];

    rBody.innerHTML = reservations.length
        ? reservations.slice(0, 10).map((r) => `
            <tr>
                <td>#${r.res_id}</td>
                <td>${escapeHtml(r.guest_name || 'Guest')}</td>
                <td>${escapeHtml(r.status || '')}</td>
                <td class="text-end">${AdminAnalytics.toCurrency(r.total_price || 0)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="4" class="text-center text-muted">No recent reservations</td></tr>';

    fBody.innerHTML = foodRows.length
        ? foodRows.slice(0, 10).map((r) => `
            <tr>
                <td>${escapeHtml(r.item_name || '-')}</td>
                <td>${escapeHtml(r.menu_category || '-')}</td>
                <td class="text-end">${Number(r.quantity || 0)}</td>
                <td class="text-end">${AdminAnalytics.toCurrency(r.line_total || 0)}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="4" class="text-center text-muted">No recent food orders</td></tr>';
}

async function loadManagerDashboard() {
    try {
        const filters = managerFilters();
        const [allPayload, dashPayload, insightPayload] = await Promise.all([
            AdminAnalytics.fetchAnalytics('all', filters),
            AdminAnalytics.fetchDashboardStats(Object.assign({}, filters, { per_page: 10 })),
            AdminAnalytics.fetchInsights(filters)
        ]);

        const allData = allPayload.data || {};
        const master = allData.master || {};
        const financial = allData.financial || {};
        const rooms = allData.rooms || {};
        const food = allData.food || {};

        AdminAnalytics.setText('mgrTopFood', master.top_food_item || 'N/A');
        AdminAnalytics.setText('mgrTopRoom', master.most_booked_room_type || 'N/A');
        AdminAnalytics.setText('mgrTopMonth', master.highest_revenue_month || 'N/A');
        AdminAnalytics.setText('mgrPeakSeason', master.peak_booking_season || 'N/A');
        AdminAnalytics.setText('mgrBestFloor', master.best_performing_floor || 'N/A');
        AdminAnalytics.setText('mgrProfitFloor', master.most_profitable_floor || 'N/A');
        AdminAnalytics.setText('mgrProfitCategory', master.highest_profit_category || 'N/A');
        AdminAnalytics.setText('mgrBestCustomer', master.best_customer || 'N/A');
        AdminAnalytics.setText('mgrCancelRate', `${Number(master.cancellation_rate_pct || 0).toFixed(2)}%`);

        const finChart = financial.chart || {};
        const roomChart = rooms.chart || {};
        const foodChart = food.chart || {};

        AdminAnalytics.renderChart('mgrRevenueChart', 'mgrRevenueChartCanvas', {
            data: {
                labels: finChart.monthly_labels || [],
                datasets: [
                    {
                        type: 'line',
                        label: 'Revenue',
                        data: finChart.monthly_revenue || [],
                        borderColor: '#0F1B2D',
                        backgroundColor: 'rgba(15, 27, 45, 0.14)',
                        fill: false,
                        tension: 0.22
                    },
                    {
                        type: 'bar',
                        label: 'Profit',
                        data: finChart.monthly_profit || [],
                        backgroundColor: 'rgba(212, 175, 55, 0.74)',
                        borderRadius: 8
                    }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        AdminAnalytics.renderChart('mgrOccupancyChart', 'mgrOccupancyChartCanvas', {
            type: 'line',
            data: {
                labels: roomChart.occupancy_month_labels || [],
                datasets: [{
                    label: 'Occupancy %',
                    data: roomChart.occupancy_month_values || [],
                    borderColor: '#D4AF37',
                    backgroundColor: 'rgba(212, 175, 55, 0.2)',
                    fill: true,
                    tension: 0.25
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        AdminAnalytics.renderChart('mgrRevenueSplitChart', 'mgrRevenueSplitCanvas', {
            type: 'doughnut',
            data: {
                labels: finChart.revenue_breakdown_labels || [],
                datasets: [{
                    data: finChart.revenue_breakdown_values || [],
                    backgroundColor: ['#D4AF37', '#0F1B2D', '#8B6F2F']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%' }
        });

        AdminAnalytics.renderChart('mgrTopDishChart', 'mgrTopDishCanvas', {
            type: 'bar',
            data: {
                labels: foodChart.top_labels || [],
                datasets: [{
                    label: 'Qty Sold',
                    data: foodChart.top_qty || [],
                    backgroundColor: 'rgba(15, 27, 45, 0.82)',
                    borderRadius: 7
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });

        renderManagerInsights((insightPayload.data && insightPayload.data.insights) || []);
        renderManagerRecent((dashPayload.data && dashPayload.data.recent_activity) || {});
    } catch (error) {
        showToast(error.message || 'Failed to load manager dashboard', 'danger');
    }
}

function exportManager(type, format) {
    AdminAnalytics.exportAnalytics(type, managerFilters(), format);
}

function downloadManagerPdf() {
    const lines = [
        `Top Food Item: ${document.getElementById('mgrTopFood').textContent}`,
        `Most Booked Room Type: ${document.getElementById('mgrTopRoom').textContent}`,
        `Highest Revenue Month: ${document.getElementById('mgrTopMonth').textContent}`,
        `Peak Booking Season: ${document.getElementById('mgrPeakSeason').textContent}`,
        `Best Performing Floor: ${document.getElementById('mgrBestFloor').textContent}`,
        `Highest Profit Category: ${document.getElementById('mgrProfitCategory').textContent}`
    ];
    AdminAnalytics.downloadSimplePdf('Manager Dashboard Report', lines, 'manager-dashboard-report.pdf');
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.AdminAnalytics) {
        showToast('Analytics module failed to load. Please refresh the page.', 'danger');
        return;
    }

    setManagerDefaults();
    loadManagerDashboard();

    ['mgrFromDate', 'mgrToDate', 'mgrMonth', 'mgrYear'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', loadManagerDashboard);
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
