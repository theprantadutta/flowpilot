import type { EnumOption, PersonSummary } from './operations';

export type NamedRef = { id: string; name: string };

export type InventoryOptions = {
    units: { value: string; label: string }[];
    statuses: EnumOption[];
    categories: NamedRef[];
    suppliers: NamedRef[];
    locations: NamedRef[];
};

export type InventoryItemData = {
    id: string;
    sku: string;
    name: string;
    description: string | null;
    unit: { value: string; label: string };
    current_stock: number;
    stock_label: string;
    minimum_stock: number;
    reorder_point: number;
    reorder_quantity: number;
    status: EnumOption;
    unit_cost: {
        minor: number;
        formatted: string;
        input: string | null;
    } | null;
    stock_value: string | null;
    is_active: boolean;
    category?: NamedRef | null;
    supplier?: NamedRef | null;
    default_location?: NamedRef | null;
    stock_levels?: { location: NamedRef; quantity: number; label: string }[];
    low_stock_at: string | null;
    last_movement_at: string | null;
};

export type MovementData = {
    id: string;
    reference: string;
    type: EnumOption;
    quantity: number;
    change: number;
    change_label: string;
    stock_after: number;
    stock_after_label: string;
    item?: { id: string; sku: string; name: string };
    from?: string | null;
    to?: string | null;
    performer?: PersonSummary | null;
    reference_note: string | null;
    notes: string | null;
    purchase_request_id: string | null;
    occurred_at: string;
};

export type PurchaseRequestData = {
    id: string;
    reference: string;
    summary: string;
    status: EnumOption;
    is_open: boolean;
    item_name: string;
    item?: {
        id: string;
        sku: string;
        name: string;
        stock_label: string;
    } | null;
    supplier?: NamedRef | null;
    deliver_to?: NamedRef | null;
    requester?: PersonSummary | null;
    decider?: PersonSummary | null;
    quantity: number;
    received_quantity: number;
    unit_cost: string;
    total: string;
    needed_by: string | null;
    reason: string | null;
    supplier_reference: string | null;
    decided_at: string | null;
    ordered_at: string | null;
    received_at: string | null;
    cancelled_at: string | null;
    created_at: string | null;
};

export type PurchaseItemOption = {
    id: string;
    label: string;
    unit_cost: string | null;
    supplier_id: string | null;
    reorder_quantity: number;
};

export type MovementKind = 'receipt' | 'issue' | 'transfer' | 'adjustment';

export type PurchaseOptions = {
    items: PurchaseItemOption[];
    suppliers: NamedRef[];
    locations: NamedRef[];
};

export type SupplierData = {
    id: string;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    lead_time_days: number | null;
    notes: string | null;
    is_active: boolean;
    items_count: number;
};

export type LocationData = {
    id: string;
    name: string;
    code: string | null;
    description: string | null;
    is_active: boolean;
    items: number;
    units: number;
};

export type CategoryData = { id: string; name: string; items_count: number };
