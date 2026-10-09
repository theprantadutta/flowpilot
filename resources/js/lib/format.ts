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
        // $ rather than US$; the organization's currency is already known.
        currencyDisplay: 'narrowSymbol',
        ...options,
    }).format(minorUnits / divisor);
}

/**
 * Typed money text to integer minor units: "1,250.5" USD → 125050. Returns
 * null for anything that is not a plain amount. Only used for previews; the
 * server parses the text it is sent.
 */
export function parseMoney(input: string, currency = 'USD'): number | null {
    const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(
        input.trim().replace(/,/g, ''),
    );

    if (!match) {
        return null;
    }

    if (ZERO_DECIMAL_CURRENCIES.has(currency)) {
        return match[2] ? null : Number(match[1]);
    }

    return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
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
