import { formatNumber, localDateTimeValue } from './number';

const millisecondsPerDay = 86_400_000;
const jalaliFormatter = new Intl.DateTimeFormat('en', {
    calendar: 'persian',
    numberingSystem: 'latn',
    timeZone: 'UTC',
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
});

export const jalaliMonths = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
];

function jalaliDateParts(date) {
    const parts = Object.fromEntries(jalaliFormatter.formatToParts(date).map(({ type, value }) => [type, value]));
    return { year: Number(parts.year), month: Number(parts.month), day: Number(parts.day) };
}

function jalaliDayOffset(month, day) {
    const completedMonths = month - 1;
    return completedMonths * 30 + Math.min(completedMonths, 6) + day - 1;
}

function startOfJalaliYear(year) {
    // July 1 falls within the corresponding Jalali year. Its day offset leads back to Nowruz.
    const july = Date.UTC(year + 621, 6, 1);
    const { month, day } = jalaliDateParts(july);
    return july - jalaliDayOffset(month, day) * millisecondsPerDay;
}

export function jalaliMonthLength(year, month) {
    if (!Number.isInteger(year) || year < 1 || year > 9999) return 0;
    if (!Number.isInteger(month) || month < 1 || month > 12) return 0;
    if (month < 12) return month <= 6 ? 31 : 30;

    const daysInYear = (startOfJalaliYear(year + 1) - startOfJalaliYear(year)) / millisecondsPerDay;
    return daysInYear - jalaliDayOffset(12, 1);
}

function gregorianDate(year, month, day) {
    return new Date(startOfJalaliYear(year) + jalaliDayOffset(month, day) * millisecondsPerDay);
}

export function jalaliMonthStartDay(year, month) {
    return (gregorianDate(year, month, 1).getUTCDay() + 1) % 7;
}

export function localJalaliDate(date = new Date()) {
    return jalaliDateParts(new Date(localDateTimeValue(date).slice(0, 10)));
}

export function jalaliDateToIso({ year, month, day }) {
    if (!Number.isInteger(day) || day < 1 || day > jalaliMonthLength(year, month)) return null;

    const date = gregorianDate(year, month, day);
    if (date.getUTCFullYear() < 1000 || date.getUTCFullYear() > 9999) return null;

    return date.toISOString().slice(0, 10);
}

export function formatCalendarDate({ year, month, day }) {
    return `${formatNumber(day)} ${jalaliMonths[month - 1]} ${year.toLocaleString('fa-IR', { useGrouping: false })}`;
}

export function formatJalaliDay(value) {
    return value ? formatCalendarDate(jalaliDateParts(new Date(value.slice(0, 10)))) : '—';
}
