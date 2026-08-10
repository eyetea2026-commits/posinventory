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
    public static function forType(string $type, Collection $rows): array
    {
        return match ($type) {
            'inventory' => self::inventory($rows),
            'orders' => self::orders($rows),
            'returns' => self::returns($rows),
            'damage' => self::damage($rows),
            'supplier' => self::supplier($rows),
            default => self::sales($rows),
        };
    }

    // Rows are item-level (ReportController::salesItemRows()) — Quantity is
    // summed across every row, but Discount/VatAmount/BillingAmount are
    // only populated on each invoice's first item row (is_first), so
    // summing them still counts each invoice exactly once.
    private static function sales(Collection $rows): array
    {
        return [
            ['label' => 'Total Transactions', 'value' => $rows->where('is_first', true)->count(), 'money' => false],
            ['label' => 'Total Quantity Sold', 'value' => $rows->sum('Quantity'), 'money' => false],
            ['label' => 'Subtotal (Gross Sales)', 'value' => round($rows->sum('ItemTotal'), 2), 'money' => true],
            ['label' => 'Discount', 'value' => round($rows->sum('Discount'), 2), 'money' => true],
            ['label' => 'VAT', 'value' => round($rows->sum('VatAmount'), 2), 'money' => true],
            ['label' => 'Net Sales', 'value' => round($rows->sum('BillingAmount'), 2), 'money' => true],
        ];
    }

    private static function inventory(Collection $rows): array
    {
        return [
            ['label' => 'Total Products', 'value' => $rows->count(), 'money' => false],
            ['label' => 'Total Stock Quantity', 'value' => $rows->sum('Quantity'), 'money' => false],
            ['label' => 'Total Inventory Value', 'value' => round($rows->sum(fn ($r) => $r->Quantity * (float) ($r->product?->CostPrice ?? 0)), 2), 'money' => true],
            ['label' => 'Low Stock Items', 'value' => $rows->where('Status', 'Low Stock')->count(), 'money' => false],
            ['label' => 'Out of Stock Items', 'value' => $rows->where('Status', 'Out of Stock')->count(), 'money' => false],
        ];
    }

    // Rows are line-item level (ReportController::orderItemRows()) — Total
    // Purchase Orders counts distinct PO numbers, not rows.
    private static function orders(Collection $rows): array
    {
        return [
            ['label' => 'Total Purchase Orders', 'value' => $rows->pluck('PONumber')->unique()->count(), 'money' => false],
            ['label' => 'Total Quantity Purchased', 'value' => $rows->sum('Quantity'), 'money' => false],
            ['label' => 'Total Amount', 'value' => round($rows->sum('Subtotal'), 2), 'money' => true],
        ];
    }

    private static function damage(Collection $rows): array
    {
        return [
            ['label' => 'Total Records', 'value' => $rows->count(), 'money' => false],
            ['label' => 'Total Quantity Damaged', 'value' => $rows->sum('Quantity'), 'money' => false],
        ];
    }

    private static function returns(Collection $rows): array
    {
        return [
            ['label' => 'Total Returns', 'value' => $rows->pluck('SalesReturnID')->unique()->count(), 'money' => false],
            ['label' => 'Total Quantity Returned', 'value' => $rows->sum('Quantity'), 'money' => false],
        ];
    }

    private static function supplier(Collection $rows): array
    {
        return [
            ['label' => 'Total Suppliers', 'value' => $rows->count(), 'money' => false],
            ['label' => 'Active Suppliers', 'value' => $rows->where('Status', \App\Models\Supplier::STATUS_ACTIVE)->count(), 'money' => false],
            ['label' => 'Inactive Suppliers', 'value' => $rows->where('Status', \App\Models\Supplier::STATUS_INACTIVE)->count(), 'money' => false],
            ['label' => 'Total Orders', 'value' => $rows->sum('TotalOrders'), 'money' => false],
            ['label' => 'Total Amount', 'value' => round($rows->sum('TotalAmount'), 2), 'money' => true],
        ];
    }

    public static function formatValue(array $entry): string
    {
        return $entry['money'] ? '₱' . number_format($entry['value'], 2) : number_format($entry['value']);
    }
}
