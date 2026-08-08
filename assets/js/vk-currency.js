/**
 * Global Sri Lankan Rupee display helpers (presentation only).
 * Always: "Rs. 1,250.00"
 */
(function (global) {
    'use strict';

    function toNumber(amount) {
        if (amount === null || amount === undefined || amount === '') {
            return 0;
        }
        if (typeof amount === 'number') {
            return Number.isFinite(amount) ? amount : 0;
        }
        var cleaned = String(amount).replace(/,/g, '').replace(/[^\d.-]/g, '');
        var n = Number(cleaned);
        return Number.isFinite(n) ? n : 0;
    }

    function formatCurrency(amount) {
        var n = toNumber(amount);
        var fixed = n.toFixed(2);
        var parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return 'Rs. ' + parts.join('.');
    }

    function parseCurrencyInput(value) {
        return Math.round(toNumber(value) * 100) / 100;
    }

    function chartCurrencyTick(value) {
        return formatCurrency(value);
    }

    global.formatCurrency = formatCurrency;
    global.vkFormatCurrency = formatCurrency;
    global.vkParseCurrencyInput = parseCurrencyInput;
    global.vkChartCurrencyTick = chartCurrencyTick;
    global.VK_CURRENCY = {
        code: 'LKR',
        symbol: 'Rs.',
        name: 'Sri Lankan Rupee',
        locale: 'en_LK',
        format: formatCurrency,
        parse: parseCurrencyInput,
    };
})(typeof window !== 'undefined' ? window : globalThis);
