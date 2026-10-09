import type { EnumOption } from './operations';

/** How a number reads: counts, shares, durations, money (minor units), sizes. */
export type ValueFormat =
    | 'number'
    | 'percent'
    | 'hours'
    | 'days'
    | 'duration'
    | 'money'
    | 'bytes'
    | 'text';

export type ChartColor =
    | 'chart-1'
    | 'chart-2'
    | 'chart-3'
    | 'chart-4'
    | 'chart-5'
    | 'success'
    | 'danger'
    | 'warning'
    | 'flow'
    | 'neutral';

export type ChartSeries = {
    key: string;
    label: string;
    color: ChartColor;
    values: (number | null)[];
};

export type ChartData = {
    key: string;
    title: string;
    description: string | null;
    kind: 'columns' | 'line' | 'bars';
    format: ValueFormat;
    stacked: boolean;
    labels: string[];
    series: ChartSeries[];
    empty: boolean;
};

export type ReportTile = {
    key: string;
    label: string;
    value: number | string | null;
    format: ValueFormat;
    hint: string | null;
    tone: 'success' | 'warning' | 'danger' | null;
};

export type ReportColumn = {
    key: string;
    label: string;
    format: ValueFormat | 'link' | 'status' | 'date' | 'datetime';
    numeric: boolean;
};

export type LinkCell = { label: string; url: string | null };
export type MoneyCell = { amount: number; currency: string };
export type ReportCell =
    | string
    | number
    | null
    | LinkCell
    | MoneyCell
    | EnumOption;

export type ReportTable = {
    columns: ReportColumn[];
    rows: Record<string, ReportCell>[];
    truncated: boolean;
    limit: number;
};

export type ReportResult = {
    tiles: ReportTile[];
    charts: ChartData[];
};

export type ReportSummary = {
    value: string;
    label: string;
    description: string;
    icon: string;
    group: string;
    /** Not in the organization's plan; `plan` names the one that has it. */
    locked: boolean;
    plan: string | null;
};

export type ReportFilterOption = {
    key: string;
    label: string;
    any_label: string;
    options: { value: string; label: string }[];
};

export type ReportParameters = {
    range: string;
    from: string;
    to: string;
    bucket: string;
    group: string | null;
    filters: Record<string, string>;
    label: string;
};

export type ReportOptions = {
    ranges: { value: string; label: string }[];
    buckets: { value: string; label: string }[];
    groups: { value: string; label: string }[];
    filters: ReportFilterOption[];
};

export type ReportExportItem = {
    id: string;
    report: { value: string; label: string };
    status: EnumOption;
    is_finished: boolean;
    range: string | null;
    row_count: number | null;
    size: number | null;
    filename: string | null;
    download_url: string | null;
    is_expired: boolean;
    created_at: string | null;
    completed_at: string | null;
    expires_at: string | null;
};
