<?php

namespace App\Support\Reports\Definitions;

use App\Enums\MovementType;
use App\Enums\ReportType;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use App\Support\Reports\Chart;
use App\Support\Reports\Report;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportFilter;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportResult;
use App\Support\Reports\SqlDates;
use App\Support\Reports\Timeline;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;

class InventoryStatusReport extends Report
{
    /**
     * Chart slot per movement type, in the order the types stack, so each
     * type keeps its colour and neighbours stay distinguishable.
     */
    private const array COLORS = [
        'receipt' => 'chart-1',
        'issue' => 'chart-2',
        'adjustment' => 'chart-3',
        'transfer' => 'chart-4',
    ];

    public function __construct(private readonly Tenancy $tenancy) {}

    public function type(): ReportType
    {
        return ReportType::InventoryStatus;
    }

    public function filters(): array
    {
        return [
            new ReportFilter('category', 'Category', 'All categories', array_values(InventoryCategory::query()->orderBy('name')->get(['id', 'organization_id', 'name'])
                ->map(fn (InventoryCategory $category): array => ['value' => $category->id, 'label' => $category->name])->all())),
            new ReportFilter('supplier', 'Supplier', 'All suppliers', array_values(Supplier::query()->orderBy('name')->get(['id', 'organization_id', 'name'])
                ->map(fn (Supplier $supplier): array => ['value' => $supplier->id, 'label' => $supplier->name])->all())),
        ];
    }

    public function groups(): array
    {
        return [
            'item' => 'Each item',
            'category' => 'By category',
            'supplier' => 'By supplier',
        ];
    }

    public function summarize(ReportQuery $query): ReportResult
    {
        $currency = $this->tenancy->currentOrFail()->currency;
        $dates = SqlDates::for($query);

        $totals = $this->items($query)
            ->selectRaw('count(*) as items')
            ->selectRaw('sum(case when current_stock > 0 and current_stock <= reorder_point then 1 else 0 end) as low')
            ->selectRaw('sum(case when current_stock <= 0 then 1 else 0 end) as out_of_stock')
            ->selectRaw('sum(case when unit_cost_amount is not null then current_stock * unit_cost_amount else 0 end) as value')
            ->toBase()
            ->first();

        $byCategory = $this->items($query)
            ->leftJoin('inventory_categories', 'inventory_categories.id', '=', 'inventory_items.category_id')
            ->selectRaw("coalesce(inventory_categories.name, 'Uncategorised') as name")
            ->selectRaw('sum(case when inventory_items.unit_cost_amount is not null then inventory_items.current_stock * inventory_items.unit_cost_amount else 0 end) as value')
            ->groupBy('inventory_categories.name')
            ->orderByDesc('value')
            ->limit(10)
            ->toBase()
            ->get();

        $movements = $this->movements($query)
            ->selectRaw($dates->bucket('occurred_at').' as bucket, type, count(*) as total', $dates->bindings())
            ->groupBy('bucket', 'type')
            ->toBase()
            ->get();

        $low = (int) ($totals->low ?? 0);
        $out = (int) ($totals->out_of_stock ?? 0);
        $labels = Timeline::labels($query);

        $movementChart = Chart::columns('movements', 'Stock movements')
            ->describe('Receipts, issues, moves and counts booked in each period.')
            ->stacked()
            ->labels($labels);

        foreach (MovementType::cases() as $type) {
            $movementChart->series($type->value, $type->label(), self::COLORS[$type->value], Timeline::fill(
                $query,
                $movements->where('type', $type->value)->pluck('total', 'bucket')->map(fn (mixed $total): int => (int) $total)->all(),
            ));
        }

        return (new ReportResult)
            ->tile('items', 'Stocked items', (int) ($totals->items ?? 0))
            ->tile('low', 'Running low', $low, tone: $low > 0 ? 'warning' : 'success')
            ->tile('out', 'Out of stock', $out, tone: $out > 0 ? 'danger' : 'success')
            ->tile('value', 'Stock value', (int) ($totals->value ?? 0), 'money', "In {$currency}, at unit cost")
            ->tile('movements', 'Movements booked', (int) $movements->sum('total'), hint: $query->label())
            ->chart(Chart::bars('value', 'Stock value by category')
                ->describe('What the stock on hand is worth at unit cost.')
                ->format('money')
                ->labels($byCategory->pluck('name')->map(fn (mixed $name): string => (string) $name)->values()->all())
                ->series('value', 'Stock value', 'chart-1', $byCategory->pluck('value')->map(fn (mixed $value): int => (int) $value)->values()->all()))
            ->chart($movementChart);
    }

    public function columns(ReportQuery $query): array
    {
        if ($query->group === 'category' || $query->group === 'supplier') {
            return [
                ReportColumn::make('name', $query->group === 'category' ? 'Category' : 'Supplier'),
                ReportColumn::make('items', 'Items', 'number'),
                ReportColumn::make('low', 'Running low', 'number'),
                ReportColumn::make('out', 'Out of stock', 'number'),
                ReportColumn::make('units', 'Units on hand', 'number'),
                ReportColumn::make('value', 'Stock value', 'money'),
            ];
        }

        return [
            ReportColumn::make('sku', 'SKU'),
            ReportColumn::make('item', 'Item', 'link'),
            ReportColumn::make('category', 'Category'),
            ReportColumn::make('supplier', 'Supplier'),
            ReportColumn::make('status', 'Stock', 'status'),
            ReportColumn::make('on_hand', 'On hand', 'number'),
            ReportColumn::make('unit', 'Unit'),
            ReportColumn::make('reorder_point', 'Reorder point', 'number'),
            ReportColumn::make('unit_cost', 'Unit cost', 'money'),
            ReportColumn::make('value', 'Stock value', 'money'),
            ReportColumn::make('received', 'Received in period', 'number'),
            ReportColumn::make('issued', 'Issued in period', 'number'),
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        if ($query->group === 'category' || $query->group === 'supplier') {
            yield from $this->grouped($query, $this->tenancy->currentOrFail()->currency);

            return;
        }

        $between = $query->between();

        $items = $this->items($query)
            ->with(['category:id,organization_id,name', 'supplier:id,organization_id,name'])
            ->withSum(['movements as received_in_period' => fn (Builder $movements) => $movements->where('type', MovementType::Receipt->value)->whereBetween('occurred_at', $between)], 'quantity')
            ->withSum(['movements as issued_in_period' => fn (Builder $movements) => $movements->where('type', MovementType::Issue->value)->whereBetween('occurred_at', $between)], 'quantity')
            ->orderByRaw('case when current_stock <= 0 then 0 when current_stock <= reorder_point then 1 else 2 end')
            ->orderBy('name')
            ->lazy(200);

        foreach ($items as $item) {
            $money = fn (?int $amount): ?array => $amount !== null && $item->currency !== null ? ['amount' => $amount, 'currency' => $item->currency] : null;

            yield [
                'sku' => $item->sku,
                'item' => ['label' => $item->name, 'url' => $this->url('inventory.items.show', ['item' => $item->id])],
                'category' => $item->category?->name,
                'supplier' => $item->supplier?->name,
                'status' => $item->stockStatus()->toOption(),
                'on_hand' => $item->current_stock,
                'unit' => $item->unit->label(),
                'reorder_point' => $item->reorder_point,
                'unit_cost' => $money($item->unit_cost_amount),
                'value' => $money($item->unit_cost_amount !== null ? $item->unit_cost_amount * $item->current_stock : null),
                'received' => (int) $item->getAttribute('received_in_period'),
                'issued' => (int) $item->getAttribute('issued_in_period'),
            ];
        }
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function grouped(ReportQuery $query, string $currency): iterable
    {
        [$table, $column, $empty] = $query->group === 'category'
            ? ['inventory_categories', 'category_id', 'Uncategorised']
            : ['suppliers', 'supplier_id', 'No supplier'];

        $groups = $this->items($query)
            ->leftJoin($table, "{$table}.id", '=', "inventory_items.{$column}")
            ->selectRaw("{$table}.name as name")
            ->selectRaw('count(*) as items')
            ->selectRaw('sum(case when inventory_items.current_stock > 0 and inventory_items.current_stock <= inventory_items.reorder_point then 1 else 0 end) as low')
            ->selectRaw('sum(case when inventory_items.current_stock <= 0 then 1 else 0 end) as out_of_stock')
            ->selectRaw('sum(inventory_items.current_stock) as units')
            ->selectRaw('sum(case when inventory_items.unit_cost_amount is not null then inventory_items.current_stock * inventory_items.unit_cost_amount else 0 end) as value')
            ->groupBy("{$table}.name")
            ->orderByDesc('value')
            ->toBase()
            ->get();

        foreach ($groups as $row) {
            yield [
                'name' => $row->name ?? $empty,
                'items' => (int) $row->items,
                'low' => (int) $row->low,
                'out' => (int) $row->out_of_stock,
                'units' => (int) $row->units,
                'value' => ['amount' => (int) $row->value, 'currency' => $currency],
            ];
        }
    }

    /**
     * Active items, qualified by table so joins stay unambiguous.
     *
     * @return Builder<InventoryItem>
     */
    private function items(ReportQuery $query): Builder
    {
        return InventoryItem::query()
            ->where('inventory_items.is_active', true)
            ->when($query->filter('category'), fn (Builder $items, string $category) => $items->where('inventory_items.category_id', $category))
            ->when($query->filter('supplier'), fn (Builder $items, string $supplier) => $items->where('inventory_items.supplier_id', $supplier));
    }

    /**
     * @return Builder<InventoryMovement>
     */
    private function movements(ReportQuery $query): Builder
    {
        return InventoryMovement::query()
            ->whereBetween('occurred_at', $query->between())
            ->whereIn('inventory_item_id', $this->items($query)->select('inventory_items.id'));
    }
}
