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

// ---- Exact on-screen arithmetic. DISPLAY ONLY: the server calculates the real amounts. ----
// We use whole numbers (BigInt) instead of decimals, so there is no floating-point error.

// "12.5" becomes 12500n with scale 3 (thousandths). Returns null if the text is not a valid number
// or has more decimals than the scale allows.
export function toScaled(text, scale) {
    const match = /^(\d+)(?:\.(\d+))?$/.exec(String(text ?? '').trim());
    if (!match) return null;

    const decimals = match[2] ?? '';
    if (decimals.length > scale) return null;

    return BigInt(match[1] + decimals.padEnd(scale, '0'));
}

// quantity x unit price in cents, rounded half-up like the server (DR-03). null if either is invalid.
export function lineCents(quantity, unitPrice) {
    const thousandths = toScaled(quantity, 3);
    const cents = toScaled(unitPrice, 2);

    if (thousandths === null || cents === null) return null;

    return (thousandths * cents + 500n) / 1000n;
}

// 2501n becomes "25.01", and 1234567n becomes "12,345.67".
export function formatCents(cents) {
    const text = cents.toString().padStart(3, '0');
    const whole = text.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `${whole}.${text.slice(-2)}`;
}