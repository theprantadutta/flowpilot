/**
 * Display helpers. Values arrive from the server as ISO-8601 UTC strings or
 * integer minor units; these turn them into text for the viewer's locale and
 * the organization's timezone and currency.
 */

const relativeFormatter = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

const RELATIVE_STEPS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['second', 60],
    ['minute', 60],
    ['hour', 24],
    ['day', 7],
    ['week', 4.34524],
    ['month', 12],
    ['year', Number.POSITIVE_INFINITY],
];

/** "3 hours ago", "in 2 days", "yesterday". */
export function timeAgo(
    iso: string | null | undefined,
    now = Date.now(),
): string {
    if (!iso) {
        return '';
    }

    let value = (new Date(iso).getTime() - now) / 1000;

    for (const [unit, size] of RELATIVE_STEPS) {
        if (Math.abs(value) < size) {
            return relativeFormatter.format(Math.round(value), unit);
        }

        value /= size;
    }

    return '';
}

/** A calendar date in the organization's timezone, e.g. "Oct 6, 2026". */
export function formatDate(
    iso: string | null | undefined,
    timeZone?: string,
    options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' },
): string {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, { ...options, timeZone }).format(
        new Date(iso),
    );
}

/** Date and time in the organization's timezone, e.g. "Oct 6, 2026, 9:12 AM". */
export function formatDateTime(
    iso: string | null | undefined,
    timeZone?: string,
): string {
    return formatDate(iso, timeZone, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

const ZERO_DECIMAL_CURRENCIES = new Set(['JPY', 'KRW', 'CLP', 'VND']);

/** Integer minor units to a currency string: 842000 USD → "$8,420.00". */
export function formatMoney(
    minorUnits: number | null | undefined,
    currency = 'USD',
    options: Intl.NumberFormatOptions = {},
): string {
    if (minorUnits === null || minorUnits === undefined) {
        return '';
    }

    const divisor = ZERO_DECIMAL_CURRENCIES.has(currency) ? 1 : 100;

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        ...options,
    }).format(minorUnits / divisor);
}

export function formatNumber(
    value: number | null | undefined,
    options: Intl.NumberFormatOptions = {},
): string {
    if (value === null || value === undefined) {
        return '';
    }

    return new Intl.NumberFormat(undefined, options).format(value);
}

/** "1 member", "3 members". */
export function plural(
    count: number,
    singular: string,
    pluralForm?: string,
): string {
    return `${formatNumber(count)} ${count === 1 ? singular : (pluralForm ?? `${singular}s`)}`;
}
