<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Computes the "Report Summary" aggregate fields shown at the bottom of
 * every report format (Print, PDF, Excel) from the same $rows collection
 * ReportController already built for the table body — no extra queries.
 * Single source of truth so the three output formats can't drift from
 * each other the way the old per-format ad hoc totals used to.
 *
 * Returns raw numeric values (not pre-formatted strings) tagged with
 * `money` so each consumer can render them appropriately: Blade views
 * prefix ₱ + number_format for display, while the Excel export applies a
 * native currency cell format instead of embedding a symbol in a string
 * (which would break SUM() formulas and sorting in the spreadsheet).
 */
class ReportSummaryBuilder
{
    /** Sales and Revenue summary lines (also the Excel export's formula rows). */
    public const GROSS_SALES = 'Gross Sales (VAT included)';

    public const DISCOUNT = 'Less: Discount';

    public const AMOUNT_COLLECTED = 'Amount Collected (VAT included)';

    public const VAT = 'VAT (12%)';

    /** What the sold pieces cost, from the stock batches they came out of. */
    public const COST_OF_GOODS_SOLD = 'Cost of Goods Sold (FIFO)';

    /** Amount Collected - Cost of Goods Sold. */
    public const GROSS_PROFIT = 'Gross Profit';

    public const NET_SALES = 'Net Sales (VAT excluded)';

    public static function forType(string $type, Collection $rows): array
    {
        return match ($type) {
            'inventory' => self::inventory($rows),
            'receiving' => self::receiving($rows),
            'stock_adjustment' => self::stockAdjustment($rows),
            'orders' => self::orders($rows),
            'returns' => self::returns($rows),
            'damage' => self::damage($rows),
            default => self::sales($rows),
        };
    }

    // Rows are item-level (ReportController::salesItemRows()) — Quantity is
    // summed across every row, but Discount/VatAmount/TotalAmount are
    // only populated on each invoice's last item row (is_last), so
    // summing them still counts each invoice exactly once.
    //
    // Accounting order for VAT-inclusive POS prices: Gross Sales − Discount
    // = what customers paid (VAT included); − the VAT inside it (12/112,
    // owed to the BIR) = Net Sales, the store's revenue (VAT excluded).
    private static function sales(Collection $rows): array
    {
        $collected = round($rows->sum('TotalAmount'), 2);
        $vat = round($rows->sum('VatAmount'), 2);
        $costOfGoodsSold = round($rows->sum('CostAmount'), 2);

        return [
            ['label' => 'Total Transactions', 'value' => $rows->where('is_last', true)->count(), 'money' => false],
            ['label' => 'Total Quantity Sold', 'value' => $rows->sum('Quantity'), 'money' => false, 'text' => self::byUnit($rows, 'Quantity')],
            ['label' => self::GROSS_SALES, 'value' => round($rows->sum('ItemTotal'), 2), 'money' => true],
            ['label' => self::DISCOUNT, 'value' => round($rows->sum('Discount'), 2), 'money' => true],
            ['label' => self::AMOUNT_COLLECTED, 'value' => $collected, 'money' => true],
            ['label' => self::VAT, 'value' => $vat, 'money' => true],
            ['label' => self::NET_SALES, 'value' => round($collected - $vat, 2), 'money' => true],
            ['label' => self::COST_OF_GOODS_SOLD, 'value' => $costOfGoodsSold, 'money' => true],
            ['label' => self::GROSS_PROFIT, 'value' => round($collected - $costOfGoodsSold, 2), 'money' => true],
        ];
    }

    private static function inventory(Collection $rows): array
    {
        return [
            ['label' => 'Total Products', 'value' => $rows->count(), 'money' => false],
            ['label' => 'Total Stock Quantity', 'value' => $rows->sum('Quantity'), 'money' => false, 'text' => self::byUnit($rows, 'Quantity')],
            ['label' => 'Total Inventory Value', 'value' => round($rows->sum(fn ($r) => $r->Quantity * (float) ($r->product?->CostPrice ?? 0)), 2), 'money' => true],
            ['label' => 'Low Stock Items', 'value' => $rows->where('Status', 'Low Stock')->count(), 'money' => false],
            ['label' => 'Out of Stock Items', 'value' => $rows->where('Status', 'Out of Stock')->count(), 'money' => false],
        ];
    }

    private static function stockAdjustment(Collection $rows): array
    {
        $increases = $rows->filter(fn ($r) => $r->QuantityAdjust > 0)->sum('QuantityAdjust');
        $decreases = $rows->filter(fn ($r) => $r->QuantityAdjust < 0)->sum('QuantityAdjust');

        return [
            ['label' => 'Total Adjustments', 'value' => $rows->count(), 'money' => false],
            ['label' => 'Total Quantity Increased', 'value' => $increases, 'money' => false, 'text' => self::byUnit($rows->filter(fn ($r) => $r->QuantityAdjust > 0), 'QuantityAdjust')],
            ['label' => 'Total Quantity Decreased', 'value' => abs($decreases), 'money' => false, 'text' => self::byUnit($rows->filter(fn ($r) => $r->QuantityAdjust < 0), 'QuantityAdjust', true)],
        ];
    }

    // Rows are delivery product lines (ReportController::receivingRows()) —
    // Total Deliveries counts distinct deliveries, not rows; what each
    // added to stock is in the product's base unit.
    private static function receiving(Collection $rows): array
    {
        return [
            ['label' => 'Total Deliveries', 'value' => $rows->pluck('DeliveryID')->unique()->count(), 'money' => false],
            ['label' => 'Product Lines Received', 'value' => $rows->count(), 'money' => false],
            ['label' => 'Total Quantity Added to Stock', 'value' => $rows->sum('BaseQuantity'), 'money' => false, 'text' => UnitOfMeasure::totals($rows->map(fn ($r) => [$r->BaseQuantity, $r->BaseUnit]))],
        ];
    }

    // Rows are line-item level (ReportController::orderItemRows()) — Total
    // Purchase Orders counts distinct PO numbers, not rows.
    private static function orders(Collection $rows): array
    {
        return [
            ['label' => 'Total Purchase Orders', 'value' => $rows->pluck('PONumber')->unique()->count(), 'money' => false],
            ['label' => 'Total Quantity Purchased', 'value' => $rows->sum('Quantity'), 'money' => false, 'text' => self::byUnit($rows, 'Quantity')],
            ['label' => 'Total Amount', 'value' => round($rows->sum('Subtotal'), 2), 'money' => true],
        ];
    }

    private static function damage(Collection $rows): array
    {
        return [
            ['label' => 'Total Records', 'value' => $rows->count(), 'money' => false],
            ['label' => 'Total Quantity Damaged', 'value' => $rows->sum('Quantity'), 'money' => false, 'text' => self::byUnit($rows, 'Quantity')],
        ];
    }

    private static function returns(Collection $rows): array
    {
        return [
            ['label' => 'Total Returns', 'value' => $rows->pluck('SalesReturnID')->unique()->count(), 'money' => false],
            ['label' => 'Total Quantity Returned', 'value' => $rows->sum('Quantity'), 'money' => false, 'text' => self::byUnit($rows, 'Quantity')],
        ];
    }

    // Quantities in different units (pieces and meters) are added up per
    // unit ("12 pieces, 5,000 meters"); null when they're all pieces.
    private static function byUnit(Collection $rows, string $field, bool $absolute = false): ?string
    {
        return UnitOfMeasure::totals($rows->map(fn ($r) => [
            $absolute ? abs((float) $r->{$field}) : $r->{$field},
            $r->Unit ?? ($r->product ?? null)?->baseUnit(),
        ]));
    }

    public static function formatValue(array $entry): string
    {
        if (! $entry['money'] && ! empty($entry['text'])) {
            return $entry['text'];
        }

        return $entry['money'] ? '₱'.number_format($entry['value'], 2) : UnitOfMeasure::number($entry['value']);
    }
}
