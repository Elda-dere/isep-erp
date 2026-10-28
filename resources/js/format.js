// Small helpers shared by pages. Money and quantities arrive from the server as exact strings
// like "1234.50". We only add commas for display and never turn them into floating-point numbers.

// "1234.5" is shown as "ETB 1,234.5"; the server already sends two decimals: "ETB 1,234.50".
export function formatMoney(value, currency = 'ETB') {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const [whole, decimals] = String(value).split('.');
    // Commas only in the whole part, so decimals are never touched.
    const withCommas = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `${currency} ${withCommas}${decimals !== undefined ? `.${decimals}` : ''}`;
}

// The database stores UTC, but people read Addis Ababa time (SRS: UTC stored, Africa/Addis_Ababa shown).
export function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-GB', {
        timeZone: 'Africa/Addis_Ababa',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}