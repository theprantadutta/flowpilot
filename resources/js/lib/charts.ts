import { formatMoney, formatNumber } from '@/lib/format';
import type { ChartColor, ValueFormat } from '@/types/reports';

/** CSS colour for a series colour role. */
export function seriesColor(color: ChartColor): string {
    return color === 'neutral' ? 'var(--chart-neutral)' : `var(--${color})`;
}

const BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

/**
 * A value as people read it: "1,284", "82.5%", "6.5 h", "3 days",
 * "$4,200.00", "12.4 MB". Money is in minor units.
 */
export function formatValue(
    value: number | string | null | undefined,
    format: ValueFormat,
    currency = 'USD',
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'string') {
        return value;
    }

    switch (format) {
        case 'percent':
            return `${formatNumber(value, { maximumFractionDigits: 1 })}%`;
        case 'hours':
            if (value < 1) {
                return `${Math.round(value * 60)} min`;
            }

            return value < 48
                ? `${formatNumber(value, { maximumFractionDigits: 1 })} h`
                : `${formatNumber(value / 24, { maximumFractionDigits: 1 })} days`;
        case 'days':
            return `${formatNumber(value, { maximumFractionDigits: 1 })} ${value === 1 ? 'day' : 'days'}`;
        case 'duration':
            return formatDuration(value);
        case 'money':
            return formatMoney(value, currency);
        case 'bytes': {
            let size = value;
            let unit = 0;

            while (size >= 1024 && unit < BYTE_UNITS.length - 1) {
                size /= 1024;
                unit++;
            }

            return `${formatNumber(size, { maximumFractionDigits: unit === 0 ? 0 : 1 })} ${BYTE_UNITS[unit]}`;
        }
        default:
            return formatNumber(value, { maximumFractionDigits: 1 });
    }
}

/** Seconds as "45 s", "3 min", "2 h 5 min" or "3 days". */
export function formatDuration(seconds: number): string {
    if (seconds < 60) {
        return `${Math.round(seconds)} s`;
    }

    const minutes = Math.round(seconds / 60);

    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 48) {
        const rest = minutes % 60;

        return rest > 0 ? `${hours} h ${rest} min` : `${hours} h`;
    }

    return `${formatNumber(hours / 24, { maximumFractionDigits: 1 })} days`;
}

/** Short tick labels: "1.2K", "$4K", "12 h". */
export function formatTick(
    value: number,
    format: ValueFormat,
    currency = 'USD',
): string {
    switch (format) {
        case 'percent':
            return `${formatNumber(value)}%`;
        case 'hours':
            return `${formatNumber(value, { maximumFractionDigits: 1 })} h`;
        case 'duration':
            return formatDuration(value);
        case 'money':
            return formatMoney(value, currency, {
                notation: 'compact',
                maximumFractionDigits: 1,
            });
        default:
            return formatNumber(value, {
                notation: 'compact',
                maximumFractionDigits: 1,
            });
    }
}

/**
 * Round axis maximum and evenly spaced ticks from zero: 0, 25, 50, 75, 100.
 */
export function niceScale(
    maximum: number,
    tickCount = 4,
): { max: number; ticks: number[] } {
    if (!Number.isFinite(maximum) || maximum <= 0) {
        return { max: 1, ticks: [0, 1] };
    }

    const rough = maximum / tickCount;
    const magnitude = 10 ** Math.floor(Math.log10(rough));
    const step =
        [1, 2, 2.5, 5, 10]
            .map((factor) => factor * magnitude)
            .find((candidate) => candidate >= rough) ?? 10 * magnitude;
    // Whole steps for counts that are small.
    const roundedStep = maximum <= tickCount ? 1 : step;
    const max = Math.ceil(maximum / roundedStep) * roundedStep;
    const ticks: number[] = [];

    for (let tick = 0; tick <= max + roundedStep / 2; tick += roundedStep) {
        ticks.push(Number(tick.toFixed(6)));
    }

    return { max, ticks };
}

/**
 * Path for a column with rounded top corners and a square base.
 */
export function columnPath(
    x: number,
    y: number,
    width: number,
    height: number,
    radius: number,
): string {
    if (height <= 0 || width <= 0) {
        return '';
    }

    const r = Math.min(radius, width / 2, height);

    return [
        `M${x},${y + height}`,
        `V${y + r}`,
        `Q${x},${y} ${x + r},${y}`,
        `H${x + width - r}`,
        `Q${x + width},${y} ${x + width},${y + r}`,
        `V${y + height}`,
        'Z',
    ].join(' ');
}
