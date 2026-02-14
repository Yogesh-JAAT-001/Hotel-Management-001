(function () {
    const chartStore = {};

    function toCurrency(value) {
        const numeric = Number(value || 0);
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 2
        }).format(numeric);
    }

    function destroyChart(key) {
        if (chartStore[key]) {
            chartStore[key].destroy();
            chartStore[key] = null;
        }
    }

    function renderChart(key, canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            return null;
        }

        destroyChart(key);
        const ctx = canvas.getContext('2d');
        chartStore[key] = new Chart(ctx, config);
        return chartStore[key];
    }

    async function fetchAnalytics(module, filters) {
        const params = new URLSearchParams(filters || {});
        if (module) {
            params.set('module', module);
        }

        const response = await fetch(`../api/admin/analytics.php?${params.toString()}`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Failed to load analytics');
        }

        return payload;
    }

    async function fetchJson(path, params) {
        const query = new URLSearchParams(params || {});
        const url = query.toString() ? `${path}?${query.toString()}` : path;
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Failed to load analytics');
        }

        return payload;
    }

    function fetchDashboardStats(params) {
        return fetchJson('../api/dashboard-stats.php', params);
    }

    function fetchCharts(params) {
        return fetchJson('../api/charts.php', params);
    }

    function fetchTopPerformers(params) {
        return fetchJson('../api/top-performers.php', params);
    }

    function fetchInsights(params) {
        return fetchJson('../api/insights.php', params);
    }

    function parseFilters(prefix) {
        const fromInput = document.getElementById(`${prefix}FromDate`);
        const toInput = document.getElementById(`${prefix}ToDate`);
        const monthInput = document.getElementById(`${prefix}Month`);
        const yearInput = document.getElementById(`${prefix}Year`);
        const categoryInput = document.getElementById(`${prefix}Category`);
        const topLimitInput = document.getElementById(`${prefix}TopLimit`);

        const filters = {
            from_date: fromInput ? fromInput.value : '',
            to_date: toInput ? toInput.value : '',
            month: monthInput ? monthInput.value : '',
            year: yearInput ? yearInput.value : ''
        };

        if (categoryInput) {
            filters.category = categoryInput.value;
        }

        if (topLimitInput) {
            filters.top_limit = topLimitInput.value;
            filters.per_page = topLimitInput.value;
        }

        Object.keys(filters).forEach((key) => {
            if (filters[key] === '' || filters[key] === null || typeof filters[key] === 'undefined') {
                delete filters[key];
            }
        });

        return filters;
    }

    function showEmptyState(containerId, message, actionLabel, actionHandler) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-chart-pie fa-3x mb-3"></i>
                <h5 class="mb-2">${message}</h5>
                <p class="mb-3">Try changing the date range or add more operational data.</p>
                ${actionLabel ? `<button class="btn btn-luxury btn-sm" id="${containerId}ActionBtn">${actionLabel}</button>` : ''}
            </div>
        `;

        if (actionLabel && typeof actionHandler === 'function') {
            const btn = document.getElementById(`${containerId}ActionBtn`);
            if (btn) {
                btn.addEventListener('click', actionHandler);
            }
        }
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    }

    function renderPagination(containerId, pagination, onPageChange) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const page = Number((pagination && pagination.page) || 1);
        const totalPages = Number((pagination && pagination.total_pages) || 1);
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        const prevDisabled = page <= 1 ? 'disabled' : '';
        const nextDisabled = page >= totalPages ? 'disabled' : '';
        container.innerHTML = `
            <div class="bi-pagination">
                <button class="btn btn-outline-secondary btn-sm" data-page="${page - 1}" ${prevDisabled}>Prev</button>
                <span>Page ${page} of ${totalPages}</span>
                <button class="btn btn-outline-secondary btn-sm" data-page="${page + 1}" ${nextDisabled}>Next</button>
            </div>
        `;

        if (typeof onPageChange === 'function') {
            container.querySelectorAll('button[data-page]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const target = Number(btn.getAttribute('data-page') || page);
                    if (!Number.isNaN(target) && target >= 1 && target <= totalPages) {
                        onPageChange(target);
                    }
                });
            });
        }
    }

    function downloadChartPng(chartKey, fileName) {
        const chart = chartStore[chartKey];
        if (!chart) {
            if (typeof showToast === 'function') {
                showToast('Chart is not ready for export', 'warning');
            }
            return;
        }

        const link = document.createElement('a');
        link.href = chart.toBase64Image('image/png', 1);
        link.download = fileName;
        link.click();
    }

    function exportAnalytics(type, filters, format) {
        const query = new URLSearchParams(filters || {});
        query.set('type', type || 'monthly');
        query.set('format', format || 'csv');
        window.location.href = `../api/admin/analytics-export.php?${query.toString()}`;
    }

    function downloadSimplePdf(title, lines, fileName) {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            if (typeof showToast === 'function') {
                showToast('PDF library not loaded. Please refresh and retry.', 'warning');
            }
            return;
        }

        const pdf = new window.jspdf.jsPDF();
        let y = 15;

        pdf.setFontSize(16);
        pdf.text(title, 14, y);
        y += 8;

        pdf.setFontSize(10);
        pdf.text(`Generated: ${new Date().toLocaleString()}`, 14, y);
        y += 8;

        (lines || []).forEach((line) => {
            if (y > 280) {
                pdf.addPage();
                y = 15;
            }
            pdf.text(String(line), 14, y);
            y += 6;
        });

        pdf.save(fileName || 'analytics-report.pdf');
    }

    window.AdminAnalytics = {
        toCurrency,
        renderChart,
        destroyChart,
        fetchAnalytics,
        fetchDashboardStats,
        fetchCharts,
        fetchTopPerformers,
        fetchInsights,
        parseFilters,
        showEmptyState,
        setText,
        renderPagination,
        downloadChartPng,
        exportAnalytics,
        downloadSimplePdf
    };
})();
