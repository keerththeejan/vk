(function () {
    'use strict';
    const d = window.QTN_DASH;
    if (!d || typeof Chart === 'undefined') return;

    const brand = '#0B5ED7';
    const teal = '#0d9488';
    const colors = ['#0B5ED7', '#0A2F6B', '#0d9488', '#d97706', '#dc2626', '#7c3aed', '#0284c7', '#059669', '#64748b'];

    function formatMoney(amount) {
        if (typeof formatCurrency === 'function') {
            return formatCurrency(amount);
        }
        let n = Number(amount);
        if (!Number.isFinite(n)) n = 0;
        const fixed = n.toFixed(2);
        const parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return 'Rs. ' + parts.join('.');
    }

    function chartMoneyTick(value) {
        if (typeof vkChartCurrencyTick === 'function') {
            return vkChartCurrencyTick(value);
        }
        return formatMoney(value);
    }

    const monthly = document.getElementById('qtnMonthlyChart');
    if (monthly) {
        new Chart(monthly, {
            type: 'bar',
            data: {
                labels: d.monthlyLabels,
                datasets: [
                    { label: 'Count', data: d.monthlyCounts, backgroundColor: brand, borderRadius: 6, yAxisID: 'y' },
                    { label: 'Value', data: d.monthlyValues, type: 'line', borderColor: teal, tension: 0.35, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, position: 'left' },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            callback: function (value) { return chartMoneyTick(value); }
                        }
                    }
                },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const v = ctx.parsed.y;
                                if (ctx.dataset.yAxisID === 'y1') {
                                    return (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + formatMoney(v);
                                }
                                return (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + v;
                            }
                        }
                    }
                }
            }
        });
    }

    const status = document.getElementById('qtnStatusChart');
    if (status) {
        new Chart(status, {
            type: 'doughnut',
            data: {
                labels: d.statusLabels,
                datasets: [{ data: d.statusCounts, backgroundColor: colors, borderWidth: 0 }]
            },
            options: { plugins: { legend: { position: 'bottom' } }, cutout: '58%' }
        });
    }

    const forecast = document.getElementById('qtnForecastChart');
    if (forecast) {
        new Chart(forecast, {
            type: 'line',
            data: {
                labels: d.monthlyLabels,
                datasets: [{
                    label: 'Pipeline value',
                    data: d.monthlyValues,
                    fill: true,
                    backgroundColor: 'rgba(11,77,186,.12)',
                    borderColor: brand,
                    tension: 0.4
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + formatMoney(ctx.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) { return chartMoneyTick(value); }
                        }
                    }
                }
            }
        });
    }
})();
