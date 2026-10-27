<?php

namespace App\Services;

use App\Http\Controllers\Admin\PurchaseOrderController;
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
    public static function forType(string $type, Collection $rows): array
    {
        return match ($type) {
            'inventory' => self::inventory($rows),
            'reorder' => self::reorder($rows),
            'stock_adjustment' => self::stockAdjustment($rows),
            'orders' => self::orders($rows),
            'returns' => self::returns($rows),
            'damage' => self::damage($rows),
            default => self::sales($rows),
        };
    }

    // Rows are item-level (ReportController::salesItemRows()) — Quantity is
    // summed across every row, but Discount/VatAmount/TotalAmount are
    // only populated on each invoice's first item row (is_first), so
    // summing them still counts each invoice exactly once.
    private static function sales(Collection $rows): array
    {
        return [
            ['label' => 'Total Transactions', 'value' => $rows->where('is_first', true)->count(), 'money' => false],
            ['label' => 'Total Quantity Sold', 'value' => $rows->sum('Quantity'), 'money' => false, 'text' => self::byUnit($rows, 'Quantity')],
            ['label' => 'Subtotal (Gross Sales)', 'value' => round($rows->sum('ItemTotal'), 2), 'money' => true],
            ['label' => 'Discount', 'value' => round($rows->sum('Discount'), 2), 'money' => true],
            ['label' => 'VAT', 'value' => round($rows->sum('VatAmount'), 2), 'money' => true],
            ['label' => 'Net Sales', 'value' => round($rows->sum('TotalAmount'), 2), 'money' => true],
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

    // Rows are Inventory records (ReportController::reorderRows()) already
    // filtered to Quantity <= ReorderThreshold, so every row here IS a
    // product that needs reordering — no further filtering needed.
    private static function reorder(Collection $rows): array
    {
        return [
            ['label' => 'Products Needing Reorder', 'value' => $rows->count(), 'money' => false],
            ['label' => 'Total Suggested Reorder Quantity', 'value' => $rows->sum(
                fn ($r) => PurchaseOrderController::suggestedReorderQuantity($r->Quantity, (int) ($r->ReorderThreshold ?? 50))
            ), 'money' => false, 'text' => UnitOfMeasure::totals($rows->map(fn ($r) => [
                PurchaseOrderController::suggestedReorderQuantity($r->Quantity, (int) ($r->ReorderThreshold ?? 50)),
                $r->product?->baseUnit(),
            ]))],
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
