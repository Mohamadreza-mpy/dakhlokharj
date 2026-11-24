const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
const arabicDigits = '٠١٢٣٤٥٦٧٨٩';

export function normalizeDigits(value) {
    return String(value ?? '')
        .replace(/[۰-۹]/g, (digit) => String(persianDigits.indexOf(digit)))
        .replace(/[٠-٩]/g, (digit) => String(arabicDigits.indexOf(digit)))
        .replace(/[٬,\s]/g, '')
        .replace(/[٫،]/g, '.');
}

export function parseInteger(value) {
    const normalized = normalizeDigits(value);
    if (!/^\d+$/.test(normalized)) return null;
    const number = Number(normalized);
    return Number.isSafeInteger(number) ? number : null;
}

export function toMilliUnits(value) {
    const normalized = normalizeDigits(value);
    if (!/^\d+(?:\.\d{1,3})?$/.test(normalized)) return null;
    const [whole, fraction = ''] = normalized.split('.');
    const milliUnits = Number(whole) * 1000 + Number(fraction.padEnd(3, '0'));
    return Number.isSafeInteger(milliUnits) ? milliUnits : null;
}

export function fromMilliUnits(value) {
    const milliUnits = Number(value ?? 0);
    const whole = Math.trunc(milliUnits / 1000);
    const fraction = String(Math.abs(milliUnits % 1000)).padStart(3, '0').replace(/0+$/, '');
    return fraction ? `${whole}.${fraction}` : String(whole);
}

export function formatNumber(value) {
    return new Intl.NumberFormat('fa-IR').format(Number(value ?? 0));
}

export function formatToman(value) {
    return `${formatNumber(value)} تومان`;
}

export function formatQuantity(milliUnits, unitLabel) {
    return `${new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 3 }).format(Number(milliUnits ?? 0) / 1000)} ${unitLabel ?? ''}`.trim();
}

export function formatJalaliDate(value, includeTime = false) {
    if (!value) return '—';
    const options = includeTime
        ? { dateStyle: 'medium', timeStyle: 'short', calendar: 'persian' }
        : { dateStyle: 'medium', calendar: 'persian' };
    return new Intl.DateTimeFormat('fa-IR', options).format(new Date(value));
}

export function localDateTimeValue(date = new Date()) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
}

export function requestId() {
    return crypto.randomUUID();
}
