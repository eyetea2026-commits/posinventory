<?php

namespace App\Services;

use App\Models\Inventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The Re-Order Point of each product, forecast from its own sales and its
 * supplier's delivery time instead of a fixed number typed in by hand:
 *
 *   Re-Order Point = (Average Daily Sales x Average Lead Time) + Safety Stock
 *   Safety Stock   = (Max Daily Sales x Max Lead Time) - (Average Daily Sales x Average Lead Time)
 *
 * It is never below MINIMUM. The result is stored in
 * Inventory.ReorderThreshold, which Low Stock, the alerts and the
 * automatic purchase orders already compare stock against. It is
 * recomputed after every sale and delivery, and once a day for all
 * products (so a product that stopped selling comes back down).
 */
class ReorderPoint
{
    /** No product's Re-Order Point is ever below this. */
    public const MINIMUM = 10;

    /** Sales from the last 30 days (today included) give the daily sales. */
    public const SALES_WINDOW_DAYS = 30;

    /** Days a delivery is assumed to take until a product has been received on a purchase order. */
    public const DEFAULT_LEAD_TIME_DAYS = 7;

    /** The lead time comes from the product's latest 10 received purchase orders. */
    public const LEAD_TIME_ORDERS = 10;

    /**
     * @return array{average_daily_sales: float, max_daily_sales: float, units_sold: float, average_lead_time: float, max_lead_time: int, lead_time_orders: int, lead_time_is_default: bool, demand_during_lead_time: float, safety_stock: float, computed: int, reorder_point: int}
     */
    public static function forProduct(int $productId): array
    {
        return self::calculate([$productId])[$productId];
    }

    /**
     * The Re-Order Point of each product, with every number that went into it.
     *
     * @param  iterable<int>  $productIds
     * @return array<int, array{average_daily_sales: float, max_daily_sales: float, units_sold: float, average_lead_time: float, max_lead_time: int, lead_time_orders: int, lead_time_is_default: bool, demand_during_lead_time: float, safety_stock: float, computed: int, reorder_point: int}>
     */
    public static function calculate(iterable $productIds): array
    {
        $productIds = collect($productIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($productIds === []) {
            return [];
        }

        $dailySales = self::dailySales($productIds);
        $leadTimes = self::leadTimes($productIds);

        $results = [];
        foreach ($productIds as $productId) {
            $days = $dailySales[$productId] ?? [];
            $orders = $leadTimes[$productId] ?? [];
            $leadTimeIsDefault = $orders === [];
            if ($leadTimeIsDefault) {
                $orders = [self::DEFAULT_LEAD_TIME_DAYS];
            }

            $unitsSold = array_sum($days);
            $averageDailySales = $unitsSold / self::SALES_WINDOW_DAYS;
            $maxDailySales = $days === [] ? 0.0 : (float) max($days);
            $averageLeadTime = array_sum($orders) / count($orders);
            $maxLeadTime = (int) max($orders);

            $demandDuringLeadTime = $averageDailySales * $averageLeadTime;
            $safetyStock = max(0, ($maxDailySales * $maxLeadTime) - $demandDuringLeadTime);
            // Rounded first so 13.9999999 (floating point) isn't taken up to 14.
            $computed = (int) ceil(round($demandDuringLeadTime + $safetyStock, 4));

            $results[$productId] = [
                'average_daily_sales' => round($averageDailySales, 2),
                'max_daily_sales' => round($maxDailySales, 2),
                'units_sold' => round($unitsSold, 2),
                'average_lead_time' => round($averageLeadTime, 1),
                'max_lead_time' => $maxLeadTime,
                'lead_time_orders' => $leadTimeIsDefault ? 0 : count($orders),
                'lead_time_is_default' => $leadTimeIsDefault,
                'demand_during_lead_time' => round($demandDuringLeadTime, 2),
                'safety_stock' => round($safetyStock, 2),
                'computed' => $computed,
                'reorder_point' => max(self::MINIMUM, $computed),
            ];
        }

        return $results;
    }

    /**
     * Saves each product's new Re-Order Point and the stock status it gives.
     *
     * @param  iterable<int>  $productIds
     */
    public static function refresh(iterable $productIds): void
    {
        foreach (self::calculate($productIds) as $productId => $result) {
            DB::transaction(function () use ($productId, $result) {
                $inventory = Inventory::where('ProductID', $productId)->lockForUpdate()->first();
                if (! $inventory || (int) $inventory->ReorderThreshold === $result['reorder_point']) {
                    return;
                }

                $inventory->ReorderThreshold = $result['reorder_point'];
                $inventory->Status = Inventory::resolveStatus($inventory->Quantity, $inventory->ReorderThreshold);
                $inventory->save();
            });
        }
    }

    public static function refreshAll(): void
    {
        Inventory::query()->pluck('ProductID')->chunk(200)->each(fn ($productIds) => self::refresh($productIds));
    }

    /**
     * Refreshes every product the first time it's called on a day: the
     * 30-day window moves even for products nothing happened to.
     */
    public static function refreshAllOncePerDay(): void
    {
        $today = now()->toDateString();
        if (Cache::get('reorder-points-refreshed-on') === $today) {
            return;
        }

        self::refreshAll();
        Cache::forever('reorder-points-refreshed-on', $today);
    }

    /**
     * Units sold (in the base unit) on each day of the window, by product.
     *
     * @param  list<int>  $productIds
     * @return array<int, list<float>>
     */
    private static function dailySales(array $productIds): array
    {
        $rows = DB::table('SalesItem')
            ->join('SalesTransaction', 'SalesTransaction.SalesTransactionID', '=', 'SalesItem.SalesTransactionID')
            ->whereIn('SalesItem.ProductID', $productIds)
            ->where('SalesTransaction.SalesTransactionDate', '>=', now()->subDays(self::SALES_WINDOW_DAYS - 1)->startOfDay())
            ->groupBy('SalesItem.ProductID', DB::raw('DATE(SalesTransaction.SalesTransactionDate)'))
            ->select('SalesItem.ProductID', DB::raw('SUM(SalesItem.BaseQuantity) as sold'))
            ->get();

        $byProduct = [];
        foreach ($rows as $row) {
            $byProduct[(int) $row->ProductID][] = (float) $row->sold;
        }

        return $byProduct;
    }

    /**
     * Days from ordering to the first delivery of the product, for its
     * latest received purchase orders, by product.
     *
     * @param  list<int>  $productIds
     * @return array<int, list<int>>
     */
    private static function leadTimes(array $productIds): array
    {
        $deliveries = DB::table('StockReceivingDeliveryItem')
            ->join('StockReceivingDelivery', 'StockReceivingDelivery.StockReceivingDeliveryID', '=', 'StockReceivingDeliveryItem.StockReceivingDeliveryID')
            ->join('PurchaseOrder', 'PurchaseOrder.PurchaseOrderID', '=', 'StockReceivingDelivery.PurchaseOrderID')
            ->whereIn('StockReceivingDeliveryItem.ProductID', $productIds)
            ->groupBy('StockReceivingDeliveryItem.ProductID', 'PurchaseOrder.PurchaseOrderID', 'PurchaseOrder.PurchaseDate')
            ->select('StockReceivingDeliveryItem.ProductID', 'PurchaseOrder.PurchaseOrderID', 'PurchaseOrder.PurchaseDate', DB::raw('MIN(StockReceivingDelivery.ReceivedAt) as received_at'))
            ->get();

        // Received one line at a time (the older Stock Receiving form).
        $singleReceipts = DB::table('StockReceiving')
            ->join('PurchaseOrder', 'PurchaseOrder.PurchaseOrderID', '=', 'StockReceiving.PurchaseOrderID')
            ->whereIn('StockReceiving.ProductID', $productIds)
            ->groupBy('StockReceiving.ProductID', 'PurchaseOrder.PurchaseOrderID', 'PurchaseOrder.PurchaseDate')
            ->select('StockReceiving.ProductID', 'PurchaseOrder.PurchaseOrderID', 'PurchaseOrder.PurchaseDate', DB::raw('MIN(StockReceiving.DateReceived) as received_at'))
            ->get();

        $orders = [];
        foreach ($deliveries->concat($singleReceipts) as $row) {
            if (! $row->PurchaseDate || ! $row->received_at) {
                continue;
            }

            $days = (int) Carbon::parse($row->PurchaseDate)->startOfDay()->diffInDays(Carbon::parse($row->received_at)->startOfDay(), false);
            $key = (int) $row->PurchaseOrderID;
            // A delivery the same day still counts as a day.
            $days = max(1, $days);
            $previous = $orders[(int) $row->ProductID][$key] ?? null;
            $orders[(int) $row->ProductID][$key] = $previous === null ? $days : min($previous, $days);
        }

        $leadTimes = [];
        foreach ($orders as $productId => $byOrder) {
            krsort($byOrder);
            $leadTimes[$productId] = array_values(array_slice($byOrder, 0, self::LEAD_TIME_ORDERS, true));
        }

        return $leadTimes;
    }
}
