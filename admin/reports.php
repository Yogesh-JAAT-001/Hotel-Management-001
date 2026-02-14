<?php
require_once '../config.php';
require_once '../includes/economics-engine.php';

if (!isAdmin()) {
    redirect('login.php');
}

$formSuccess = '';
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_cost') {
    if (!verifyCsrfToken($_POST['_csrf_token'] ?? '')) {
        $formError = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $costMonth = (string)($_POST['cost_month'] ?? '');
        $category = trim((string)($_POST['category'] ?? ''));
        $amount = (float)($_POST['amount'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));

        try {
            upsertOperatingCost($pdo, $costMonth, $category, $amount, $description);
            $formSuccess = 'Cost entry saved successfully.';
        } catch (InvalidArgumentException $e) {
            $formError = $e->getMessage();
        } catch (Exception $e) {
            error_log('Save operating cost error: ' . $e->getMessage());
            $formError = 'Failed to save cost entry. Please try again.';
        }
    }
}

try {
    $dashboard = getEconomicsDashboardData($pdo, 12);
} catch (Exception $e) {
    error_log('Economics dashboard error: ' . $e->getMessage());
    $dashboard = [
        'months' => [],
        'recent_costs' => [],
        'cost_category_totals' => [
            'Staff' => 0,
            'Electricity' => 0,
            'Maintenance' => 0,
            'Water' => 0
        ],
        'summary' => [
            'total_bookings' => 0,
            'total_revenue' => 0,
            'total_cost' => 0,
            'total_profit' => 0,
            'profit_margin_pct' => 0,
            'avg_revenue_per_booking' => 0,
            'avg_variable_cost_per_booking' => 0,
            'avg_monthly_fixed_cost' => 0,
            'contribution_per_booking' => 0,
            'break_even_bookings' => 0,
            'break_even_revenue' => 0
        ]
    ];
    if ($formError === '') {
        $formError = 'Failed to load engineering economics data.';
    }
}

$summary = $dashboard['summary'];
$months = $dashboard['months'];
$recentCosts = $dashboard['recent_costs'];
$costCategoryTotals = $dashboard['cost_category_totals'];
$analysisMonths = max(1, count($months));

$labels = array_map(fn($m) => $m['month_label'], $months);
$bookingsData = array_map(fn($m) => (int)$m['bookings'], $months);
$revenueData = array_map(fn($m) => (float)$m['revenue'], $months);
$costData = array_map(fn($m) => (float)$m['cost'], $months);
$profitData = array_map(fn($m) => (float)$m['profit'], $months);

$page_title = 'Engineering Economics Dashboard';
$additional_css = '<link rel="stylesheet" href="' . appPath('/assets/css/analytics.css') . '">';
$additional_js = ''
    . '<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>'
    . '<script src="' . appPath('/assets/js/admin-analytics.js') . '"></script>';
include '../includes/header.php';
?>

<!-- Admin Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-hotel me-2"></i><?php echo APP_NAME; ?> - Admin
        </a>

        <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#adminNav">
            <i class="fas fa-bars"></i>
        </button>

        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manager.php"><i class="fas fa-briefcase me-1"></i>Manager Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reservations.php"><i class="fas fa-calendar-check me-1"></i>Reservations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rooms.php"><i class="fas fa-bed me-1"></i>Rooms</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="guests.php"><i class="fas fa-users me-1"></i>Guests</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="staff.php"><i class="fas fa-user-tie me-1"></i>Staff</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="food.php"><i class="fas fa-utensils me-1"></i>Food & Dining</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pricing.php"><i class="fas fa-chart-line me-1"></i>Pricing Engine</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="reports.php"><i class="fas fa-chart-bar me-1"></i>Economics</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php"><i class="fas fa-cog me-1"></i>Settings</a>
                </li>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo $_SESSION['admin_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../user/" target="_blank">View Website</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container-fluid" style="margin-top: 80px;">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Engineering Economics Dashboard</h1>
            <span class="badge bg-dark">Last <?php echo $analysisMonths; ?> Months</span>
        </div>
    </div>

    <?php if ($formSuccess !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($formSuccess); ?></div>
    <?php endif; ?>
    <?php if ($formError !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($formError); ?></div>
    <?php endif; ?>

    <!-- Advanced Financial Intelligence -->
    <section class="analytics-section mb-4">
        <div class="section-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1"><i class="fas fa-briefcase me-2"></i>Financial & Revenue Intelligence</h4>
                    <p class="small mb-0">Revenue breakdown, profit drivers, monthly cost vs profit trends, and 3-month revenue forecast</p>
                </div>
                <div class="export-row">
                    <button class="btn btn-outline-light btn-sm" type="button" onclick="exportFinancialAnalytics('monthly', 'csv')">
                        <i class="fas fa-file-csv me-1"></i>Monthly CSV
                    </button>
                    <button class="btn btn-outline-light btn-sm" type="button" onclick="exportFinancialAnalytics('monthly', 'xls')">
                        <i class="fas fa-file-excel me-1"></i>Monthly Excel
                    </button>
                    <button class="btn btn-outline-light btn-sm" type="button" onclick="exportFinancialAnalytics('quarterly', 'xls')">
                        <i class="fas fa-file-alt me-1"></i>Quarterly Excel
                    </button>
                    <button class="btn btn-outline-light btn-sm" type="button" onclick="downloadFinancialPdf()">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </button>
                </div>
            </div>
        </div>

        <div class="analytics-toolbar">
            <div class="field field-sm">
                <label class="form-label" for="finFromDate">From Date</label>
                <input type="date" id="finFromDate" class="form-control">
            </div>
            <div class="field field-sm">
                <label class="form-label" for="finToDate">To Date</label>
                <input type="date" id="finToDate" class="form-control">
            </div>
            <div class="field field-sm">
                <label class="form-label" for="finMonth">Month</label>
                <select id="finMonth" class="form-select">
                    <option value="">All</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>"><?php echo date('M', mktime(0, 0, 0, $m, 1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="field field-sm">
                <label class="form-label" for="finYear">Year</label>
                <select id="finYear" class="form-select">
                    <option value="">All</option>
                    <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="actions">
                <button class="btn btn-luxury btn-sm" type="button" onclick="loadFinancialAnalytics()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <button class="btn btn-outline-secondary btn-sm" type="button" onclick="resetFinancialFilters()">
                    <i class="fas fa-rotate-left me-1"></i>Reset
                </button>
            </div>
        </div>

        <div class="analytics-grid cols-4">
            <article class="analytics-kpi">
                <div class="label">Rooms Revenue</div>
                <div class="value" id="finRoomsRevenue">₹0</div>
                <div class="meta">Net room earnings</div>
            </article>
            <article class="analytics-kpi">
                <div class="label">Food Revenue</div>
                <div class="value" id="finFoodRevenue">₹0</div>
                <div class="meta">Dining contribution</div>
            </article>
            <article class="analytics-kpi">
                <div class="label">Other Services</div>
                <div class="value" id="finOtherRevenue">₹0</div>
                <div class="meta">Payment surplus/services</div>
            </article>
            <article class="analytics-kpi">
                <div class="label">Net Profit</div>
                <div class="value" id="finNetProfit">₹0</div>
                <div class="meta" id="finProfitMeta">Margin 0%</div>
            </article>
        </div>

        <div class="analytics-grid cols-2 pt-0">
            <article class="analytics-card">
                <div class="card-head">
                    <h6 class="mb-0">Revenue Breakdown</h6>
                    <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('finRevenueBreakdownChart', 'financial-revenue-breakdown.png')">
                        <i class="fas fa-image me-1"></i>PNG
                    </button>
                </div>
                <div class="card-body">
                    <div class="chart-shell chart-sm"><canvas id="finRevenueBreakdownChart"></canvas></div>
                </div>
            </article>
            <article class="analytics-card">
                <div class="card-head">
                    <h6 class="mb-0">Profit Drivers (Room Types)</h6>
                    <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('finDriverChart', 'financial-profit-drivers.png')">
                        <i class="fas fa-image me-1"></i>PNG
                    </button>
                </div>
                <div class="card-body">
                    <div class="chart-shell chart-sm"><canvas id="finProfitDriverChart"></canvas></div>
                </div>
            </article>
        </div>

        <div class="analytics-grid cols-2 pt-0">
            <article class="analytics-card">
                <div class="card-head">
                    <h6 class="mb-0">Cost vs Profit Trend</h6>
                    <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('finTrendChart', 'financial-cost-vs-profit.png')">
                        <i class="fas fa-image me-1"></i>PNG
                    </button>
                </div>
                <div class="card-body">
                    <div class="chart-shell"><canvas id="finCostProfitTrendChart"></canvas></div>
                </div>
            </article>
            <article class="analytics-card">
                <div class="card-head">
                    <h6 class="mb-0">Revenue Forecast (Next 3 Months)</h6>
                    <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('finForecastChart', 'financial-forecast.png')">
                        <i class="fas fa-image me-1"></i>PNG
                    </button>
                </div>
                <div class="card-body">
                    <div class="chart-shell chart-sm"><canvas id="finForecastChart"></canvas></div>
                </div>
            </article>
        </div>

        <div class="analytics-grid pt-0">
            <article class="analytics-card">
                <div class="card-head">
                    <h6 class="mb-0">Quarterly Performance Report</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table analytics-table table-sm" id="financialQuarterlyTable">
                            <thead>
                                <tr>
                                    <th>Quarter</th>
                                    <th class="text-end">Bookings</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Cost</th>
                                    <th class="text-end">Profit</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div id="financialAnalyticsEmpty" class="mt-3" style="display:none;"></div>
                </div>
            </article>
        </div>

        <div class="analytics-grid cols-4 pt-0">
            <article class="analytics-kpi">
                <div class="label">NPV</div>
                <div class="value" id="econNpv">₹0</div>
                <div class="meta">Net present value</div>
            </article>
            <article class="analytics-kpi">
                <div class="label">IRR</div>
                <div class="value" id="econIrr">0%</div>
                <div class="meta">Internal rate of return</div>
            </article>
            <article class="analytics-kpi">
                <div class="label">Cost-Benefit Ratio</div>
                <div class="value" id="econCbr">0.00</div>
                <div class="meta">Revenue / cost</div>
            </article>
            <article class="analytics-kpi">
                <div class="label">6-Month Forecast</div>
                <div class="value" id="econForecast6">₹0</div>
                <div class="meta">Projected cumulative revenue</div>
            </article>
        </div>

        <div class="analytics-grid cols-2 pt-0">
            <article class="analytics-card">
                <div class="card-head">
                    <h6 class="mb-0">Sensitivity Analysis (Profit)</h6>
                    <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('finSensitivityChart', 'financial-sensitivity.png')">
                        <i class="fas fa-image me-1"></i>PNG
                    </button>
                </div>
                <div class="card-body">
                    <div class="chart-shell chart-sm"><canvas id="finSensitivityChart"></canvas></div>
                </div>
            </article>
            <article class="analytics-card">
                <div class="card-head">
                    <h6 class="mb-0">Revenue Forecast (Next 6 Months)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table analytics-table table-sm" id="finForecast6Table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">Predicted Revenue</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-uppercase">Total Revenue</div>
                            <div class="h4 mb-0">₹<?php echo number_format($summary['total_revenue'], 2); ?></div>
                        </div>
                        <i class="fas fa-rupee-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-uppercase">Total Costs</div>
                            <div class="h4 mb-0">₹<?php echo number_format($summary['total_cost'], 2); ?></div>
                        </div>
                        <i class="fas fa-file-invoice-dollar fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card <?php echo $summary['total_profit'] >= 0 ? 'bg-success' : 'bg-warning'; ?> text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-uppercase">Net Profit / Loss</div>
                            <div class="h4 mb-0">₹<?php echo number_format($summary['total_profit'], 2); ?></div>
                            <div class="small">Margin <?php echo number_format($summary['profit_margin_pct'], 2); ?>%</div>
                        </div>
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-uppercase">Break-even Bookings</div>
                            <div class="h4 mb-0"><?php echo number_format($summary['break_even_bookings'], 2); ?></div>
                            <div class="small">Current bookings: <?php echo (int)$summary['total_bookings']; ?></div>
                        </div>
                        <i class="fas fa-balance-scale fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Charts -->
    <div class="row mb-4">
        <div class="col-lg-8 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Profit / Loss Trend (Revenue vs Cost)</h5>
                </div>
                <div class="card-body">
                    <canvas id="profitLossChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Cost Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="costDistributionChart" height="210"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Demand Trend (Bookings)</h5>
                </div>
                <div class="card-body">
                    <canvas id="bookingDemandChart" height="130"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Break-even Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th>Average Revenue per Booking</th>
                                    <td class="text-end">₹<?php echo number_format($summary['avg_revenue_per_booking'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Average Variable Cost per Booking</th>
                                    <td class="text-end">₹<?php echo number_format($summary['avg_variable_cost_per_booking'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Contribution per Booking</th>
                                    <td class="text-end">₹<?php echo number_format($summary['contribution_per_booking'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Average Monthly Fixed Cost</th>
                                    <td class="text-end">₹<?php echo number_format($summary['avg_monthly_fixed_cost'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Break-even Bookings / Month</th>
                                    <td class="text-end fw-bold"><?php echo number_format($summary['break_even_bookings'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Break-even Revenue / Month</th>
                                    <td class="text-end fw-bold">₹<?php echo number_format($summary['break_even_revenue'], 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">ROI Calculator</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Initial Investment (₹)</label>
                            <input type="number" class="form-control" id="roiInvestment" value="2500000" min="1" step="1000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Analysis Months</label>
                            <input type="number" class="form-control" id="roiMonths" value="<?php echo $analysisMonths; ?>" min="1" max="36">
                        </div>
                    </div>

                    <button class="btn btn-primary mt-3" onclick="calculateRoi()">
                        <i class="fas fa-calculator me-1"></i>Calculate ROI
                    </button>

                    <div class="mt-3">
                        <div class="border rounded p-3 bg-light">
                            <div class="mb-2">Annualized Profit: <strong id="annualizedProfit">₹0.00</strong></div>
                            <div class="mb-2">ROI (%): <strong id="roiPercent">0.00%</strong></div>
                            <div>Estimated Payback Period: <strong id="paybackPeriod">N/A</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Add / Update Monthly Cost</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="reports.php">
                        <input type="hidden" name="action" value="save_cost">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>">

                        <div class="mb-3">
                            <label class="form-label">Cost Month</label>
                            <input type="month" name="cost_month" class="form-control" required value="<?php echo date('Y-m'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="Staff">Staff</option>
                                <option value="Electricity">Electricity</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Water">Water</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <input type="text" name="description" class="form-control" maxlength="255" placeholder="Monthly utility or payroll expense">
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-save me-1"></i>Save Cost Entry
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Financial Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Monthly Profit / Loss Report</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($months)): ?>
                        <p class="text-muted mb-0">No monthly data available yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th class="text-end">Bookings</th>
                                        <th class="text-end">Revenue (₹)</th>
                                        <th class="text-end">Cost (₹)</th>
                                        <th class="text-end">Profit/Loss (₹)</th>
                                        <th class="text-end">Margin %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($months as $row): ?>
                                        <?php $margin = $row['revenue'] > 0 ? ($row['profit'] / $row['revenue']) * 100 : 0; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['month_label']); ?></td>
                                            <td class="text-end"><?php echo (int)$row['bookings']; ?></td>
                                            <td class="text-end"><?php echo number_format($row['revenue'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['cost'], 2); ?></td>
                                            <td class="text-end <?php echo $row['profit'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                                <?php echo number_format($row['profit'], 2); ?>
                                            </td>
                                            <td class="text-end <?php echo $margin >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo number_format($margin, 2); ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Cost Tracking -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Cost Tracking Entries</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentCosts)): ?>
                        <p class="text-muted mb-0">No cost entries recorded.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Category</th>
                                        <th class="text-end">Amount (₹)</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentCosts as $cost): ?>
                                        <tr>
                                            <td><?php echo date('M Y', strtotime((string)$cost['cost_month'])); ?></td>
                                            <td><?php echo htmlspecialchars((string)$cost['category']); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$cost['amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars((string)($cost['description'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const economicsLabels = <?php echo json_encode($labels); ?>;
const economicsRevenue = <?php echo json_encode($revenueData); ?>;
const economicsCost = <?php echo json_encode($costData); ?>;
const economicsProfit = <?php echo json_encode($profitData); ?>;
const economicsBookings = <?php echo json_encode($bookingsData); ?>;
const costCategoryTotals = <?php echo json_encode(array_values($costCategoryTotals)); ?>;

const currencyFormatter = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 2
});

// Profit / Loss chart
new Chart(document.getElementById('profitLossChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: economicsLabels,
        datasets: [
            {
                type: 'line',
                label: 'Revenue',
                data: economicsRevenue,
                borderColor: 'rgba(33, 150, 243, 1)',
                backgroundColor: 'rgba(33, 150, 243, 0.15)',
                tension: 0.25,
                yAxisID: 'y'
            },
            {
                type: 'line',
                label: 'Cost',
                data: economicsCost,
                borderColor: 'rgba(255, 99, 132, 1)',
                backgroundColor: 'rgba(255, 99, 132, 0.15)',
                tension: 0.25,
                yAxisID: 'y'
            },
            {
                label: 'Profit/Loss',
                data: economicsProfit,
                backgroundColor: economicsProfit.map(v => v >= 0 ? 'rgba(40, 167, 69, 0.70)' : 'rgba(220, 53, 69, 0.70)'),
                borderColor: economicsProfit.map(v => v >= 0 ? 'rgba(40, 167, 69, 1)' : 'rgba(220, 53, 69, 1)'),
                borderWidth: 1,
                yAxisID: 'y'
            }
        ]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            y: {
                ticks: {
                    callback: function(value) {
                        return '₹' + Number(value).toLocaleString('en-IN');
                    }
                }
            }
        }
    }
});

// Cost distribution chart
new Chart(document.getElementById('costDistributionChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Staff', 'Electricity', 'Maintenance', 'Water'],
        datasets: [{
            data: costCategoryTotals,
            backgroundColor: [
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(111, 66, 193, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + currencyFormatter.format(context.raw || 0);
                    }
                }
            }
        }
    }
});

// Booking demand chart
new Chart(document.getElementById('bookingDemandChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: economicsLabels,
        datasets: [{
            label: 'Bookings',
            data: economicsBookings,
            borderColor: 'rgba(255, 159, 64, 1)',
            backgroundColor: 'rgba(255, 159, 64, 0.2)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

function setFinancialFilterDefaults() {
    const today = new Date();
    const from = new Date(today.getFullYear(), today.getMonth() - 11, 1);
    document.getElementById('finFromDate').value = from.toISOString().split('T')[0];
    document.getElementById('finToDate').value = today.toISOString().split('T')[0];
}

function getFinancialFilters() {
    return AdminAnalytics.parseFilters('fin');
}

function renderQuarterlyTable(rows) {
    const tbody = document.querySelector('#financialQuarterlyTable tbody');
    if (!tbody) return;

    if (!rows || rows.length === 0) {
        tbody.innerHTML = '';
        return;
    }

    tbody.innerHTML = rows.map((row) => `
        <tr>
            <td>${row.quarter}</td>
            <td class="text-end">${Number(row.bookings || 0).toLocaleString('en-IN')}</td>
            <td class="text-end">${AdminAnalytics.toCurrency(row.revenue || 0)}</td>
            <td class="text-end">${AdminAnalytics.toCurrency(row.cost || 0)}</td>
            <td class="text-end">${AdminAnalytics.toCurrency(row.profit || 0)}</td>
        </tr>
    `).join('');
}

function renderForecastSixMonthTable(rows) {
    const tbody = document.querySelector('#finForecast6Table tbody');
    if (!tbody) return;

    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan=\"2\" class=\"text-center text-muted\">No forecast data</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((row) => `
        <tr>
            <td>${row.month_label || ''}</td>
            <td class=\"text-end\">${AdminAnalytics.toCurrency(row.predicted_revenue || 0)}</td>
        </tr>
    `).join('');
}

async function loadFinancialAnalytics() {
    try {
        const filters = getFinancialFilters();
        const [payload, insightsPayload] = await Promise.all([
            AdminAnalytics.fetchAnalytics('financial', filters),
            AdminAnalytics.fetchInsights(filters)
        ]);

        const data = payload.data || {};
        const summary = data.summary || {};
        const chart = data.chart || {};
        const quarterlyRows = data.quarterly || [];
        const econAdvanced = (insightsPayload.data && insightsPayload.data.economics_advanced) || {};
        const forecast6 = econAdvanced.forecast_6_months || [];
        const sensitivity = econAdvanced.sensitivity_analysis || {};

        AdminAnalytics.setText('finRoomsRevenue', AdminAnalytics.toCurrency(summary.room_revenue || 0));
        AdminAnalytics.setText('finFoodRevenue', AdminAnalytics.toCurrency(summary.food_revenue || 0));
        AdminAnalytics.setText('finOtherRevenue', AdminAnalytics.toCurrency(summary.other_revenue || 0));
        AdminAnalytics.setText('finNetProfit', AdminAnalytics.toCurrency(summary.net_profit || 0));
        AdminAnalytics.setText('finProfitMeta', `Margin ${(summary.profit_margin_pct || 0).toFixed(2)}%`);
        AdminAnalytics.setText('econNpv', AdminAnalytics.toCurrency(econAdvanced.npv || 0));
        AdminAnalytics.setText('econIrr', `${Number(econAdvanced.irr_pct || 0).toFixed(2)}%`);
        AdminAnalytics.setText('econCbr', Number(econAdvanced.cost_benefit_ratio || 0).toFixed(3));
        AdminAnalytics.setText(
            'econForecast6',
            AdminAnalytics.toCurrency(
                forecast6.reduce((sum, row) => sum + Number(row.predicted_revenue || 0), 0)
            )
        );

        renderQuarterlyTable(quarterlyRows);
        renderForecastSixMonthTable(forecast6);

        if (data.empty) {
            AdminAnalytics.showEmptyState(
                'financialAnalyticsEmpty',
                'No financial data found for selected range.',
                'Refresh',
                loadFinancialAnalytics
            );
            document.getElementById('financialAnalyticsEmpty').style.display = 'block';
        } else {
            document.getElementById('financialAnalyticsEmpty').style.display = 'none';
        }

        AdminAnalytics.renderChart('finRevenueBreakdownChart', 'finRevenueBreakdownChart', {
            type: 'pie',
            data: {
                labels: chart.revenue_breakdown_labels || [],
                datasets: [{
                    data: chart.revenue_breakdown_values || [],
                    backgroundColor: ['#D4AF37', '#0F1B2D', '#8B6F2F']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: { label: (ctx) => `${ctx.label}: ${AdminAnalytics.toCurrency(ctx.raw || 0)}` }
                    }
                }
            }
        });

        AdminAnalytics.renderChart('finDriverChart', 'finProfitDriverChart', {
            type: 'bar',
            data: {
                labels: chart.driver_room_labels || [],
                datasets: [{
                    label: 'Revenue',
                    data: chart.driver_room_revenue || [],
                    backgroundColor: 'rgba(212, 175, 55, 0.78)',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: {
                    tooltip: {
                        callbacks: { label: (ctx) => `Revenue: ${AdminAnalytics.toCurrency(ctx.raw || 0)}` }
                    }
                }
            }
        });

        AdminAnalytics.renderChart('finTrendChart', 'finCostProfitTrendChart', {
            data: {
                labels: chart.monthly_labels || [],
                datasets: [
                    {
                        type: 'line',
                        label: 'Revenue',
                        data: chart.monthly_revenue || [],
                        borderColor: '#0F1B2D',
                        backgroundColor: 'rgba(15, 27, 45, 0.14)',
                        fill: false,
                        tension: 0.25,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: 'Total Cost',
                        data: chart.monthly_total_cost || [],
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.12)',
                        fill: false,
                        tension: 0.25,
                        yAxisID: 'y'
                    },
                    {
                        type: 'bar',
                        label: 'Net Profit',
                        data: chart.monthly_profit || [],
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderRadius: 8,
                        yAxisID: 'y'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true } }
            }
        });

        AdminAnalytics.renderChart('finForecastChart', 'finForecastChart', {
            type: 'line',
            data: {
                labels: chart.forecast_labels || [],
                datasets: [{
                    label: 'Predicted Revenue',
                    data: chart.forecast_values || [],
                    borderColor: '#8B6F2F',
                    backgroundColor: 'rgba(139, 111, 47, 0.22)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
                plugins: {
                    tooltip: {
                        callbacks: { label: (ctx) => `Predicted: ${AdminAnalytics.toCurrency(ctx.raw || 0)}` }
                    }
                }
            }
        });

        AdminAnalytics.renderChart('finSensitivityChart', 'finSensitivityChart', {
            type: 'bar',
            data: {
                labels: ['-10% Occupancy', 'Base Case', '+10% Occupancy'],
                datasets: [{
                    label: 'Net Profit',
                    data: [
                        Number(sensitivity.occupancy_minus_10 || 0),
                        Number(sensitivity.occupancy_base || 0),
                        Number(sensitivity.occupancy_plus_10 || 0)
                    ],
                    backgroundColor: ['#b55f53', '#0F1B2D', '#2a8b57'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => `Profit: ${AdminAnalytics.toCurrency(ctx.raw || 0)}`
                        }
                    }
                },
                scales: { y: { beginAtZero: true } }
            }
        });
    } catch (error) {
        showToast(error.message || 'Failed to load financial analytics', 'danger');
    }
}

function resetFinancialFilters() {
    document.getElementById('finMonth').value = '';
    document.getElementById('finYear').value = '';
    setFinancialFilterDefaults();
    loadFinancialAnalytics();
}

function exportFinancialAnalytics(type, format) {
    AdminAnalytics.exportAnalytics(type, getFinancialFilters(), format);
}

function downloadFinancialPdf() {
    const lines = [
        `Rooms Revenue: ${document.getElementById('finRoomsRevenue').textContent}`,
        `Food Revenue: ${document.getElementById('finFoodRevenue').textContent}`,
        `Other Services Revenue: ${document.getElementById('finOtherRevenue').textContent}`,
        `Net Profit: ${document.getElementById('finNetProfit').textContent}`,
        `${document.getElementById('finProfitMeta').textContent}`,
        `NPV: ${document.getElementById('econNpv').textContent}`,
        `IRR: ${document.getElementById('econIrr').textContent}`,
        `Cost-Benefit Ratio: ${document.getElementById('econCbr').textContent}`,
        `6-Month Forecast: ${document.getElementById('econForecast6').textContent}`
    ];
    AdminAnalytics.downloadSimplePdf('Financial Intelligence Report', lines, 'financial-intelligence-report.pdf');
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.AdminAnalytics) {
        showToast('Analytics module failed to load. Please refresh the page.', 'danger');
        return;
    }
    setFinancialFilterDefaults();
    loadFinancialAnalytics();

    ['finFromDate', 'finToDate', 'finMonth', 'finYear'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', loadFinancialAnalytics);
    });
});

function calculateRoi() {
    const investment = Number(document.getElementById('roiInvestment').value || 0);
    const months = Number(document.getElementById('roiMonths').value || 0);
    const netProfit = Number(<?php echo json_encode((float)$summary['total_profit']); ?>);

    if (investment <= 0 || months <= 0) {
        showToast('Please enter valid investment and analysis months.', 'warning');
        return;
    }

    const annualizedProfit = netProfit * (12 / months);
    const roiPercent = ((annualizedProfit - investment) / investment) * 100;

    let paybackText = 'N/A';
    if (annualizedProfit > 0) {
        const paybackMonths = investment / (annualizedProfit / 12);
        paybackText = `${paybackMonths.toFixed(1)} months`;
    }

    document.getElementById('annualizedProfit').textContent = currencyFormatter.format(annualizedProfit);
    document.getElementById('roiPercent').textContent = `${roiPercent.toFixed(2)}%`;
    document.getElementById('paybackPeriod').textContent = paybackText;
}

calculateRoi();

// Logout function
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
