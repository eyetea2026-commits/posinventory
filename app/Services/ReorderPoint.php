<?php

namespace App\Services;

use App\Models\Inventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReorderPoint
{
    public const MINIMUM = 10;

    public const SALES_WINDOW_DAYS = 30;

    public const DEFAULT_LEAD_TIME_DAYS = 7;

    public const LEAD_TIME_ORDERS = 10;

    public static function forProduct(int $productId): array
    {
        return self::calculate([$productId])[$productId];
    }

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

    public static function refreshAllOncePerDay(): void
    {
        $today = now()->toDateString();
        if (Cache::get('reorder-points-refreshed-on') === $today) {
            return;
        }

        self::refreshAll();
        Cache::forever('reorder-points-refreshed-on', $today);
    }

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

    private static function leadTimes(array $productIds): array
    {
        $deliveries = DB::table('StockReceivingDeliveryItem')
            ->join('StockReceivingDelivery', 'StockReceivingDelivery.StockReceivingDeliveryID', '=', 'StockReceivingDeliveryItem.StockReceivingDeliveryID')
            ->join('PurchaseOrder', 'PurchaseOrder.PurchaseOrderID', '=', 'StockReceivingDelivery.PurchaseOrderID')
            ->whereIn('StockReceivingDeliveryItem.ProductID', $productIds)
            ->groupBy('StockReceivingDeliveryItem.ProductID', 'PurchaseOrder.PurchaseOrderID', 'PurchaseOrder.PurchaseDate')
            ->select('StockReceivingDeliveryItem.ProductID', 'PurchaseOrder.PurchaseOrderID', 'PurchaseOrder.PurchaseDate', DB::raw('MIN(StockReceivingDelivery.ReceivedAt) as received_at'))
            ->get();

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
