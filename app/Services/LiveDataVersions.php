<?php

namespace App\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LiveDataVersions
{
    public const TOPICS = [
        'products' => ['product', 'category', 'brand', 'inventory', 'productsupplier', 'supplier', 'discount', 'discountproduct'],
        'categories' => ['category', 'brand', 'product'],
        'suppliers' => ['supplier', 'productsupplier', 'product', 'purchaseorder', 'purchaseorderitem'],
        'purchase_orders' => ['purchaseorder', 'purchaseorderitem', 'stockreceiving', 'stockreceivingbatch', 'supplier', 'product'],
        'stock_receivings' => ['stockreceiving', 'stockreceivingbatch', 'purchaseorder', 'purchaseorderitem', 'supplier', 'product'],
        'stock_adjustments' => ['stockadjustment', 'inventory', 'product'],
        'damages' => ['damagedproduct', 'salesreturn', 'salesreturnitem', 'product'],
        'inventory' => ['inventory', 'product', 'category', 'brand'],
        'sales' => ['salestransaction', 'salesitem', 'payment', 'salesreturn', 'salesreturnitem'],
        'discounts' => ['discount', 'discountproduct', 'product'],
        'users' => ['users', 'roles'],
        'activity' => ['activitylog'],
    ];

    private const IGNORED = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations'];

    public static function recordWrite(QueryExecuted $query): void
    {
        if (! preg_match('/^\s*(?:insert\s+(?:ignore\s+)?into|update|delete\s+from|replace\s+into)\s+[`"\[]?([A-Za-z0-9_]+)/i', $query->sql, $match)) {
            return;
        }

        $table = strtolower($match[1]);
        if (in_array($table, self::IGNORED, true)) {
            return;
        }

        self::touch($table);
    }

    public static function touch(string $table): void
    {
        Cache::forever(self::key($table), Str::random(12));
    }

    public static function signatures(): array
    {
        $tables = array_values(array_unique(array_merge(...array_values(self::TOPICS))));
        $versions = Cache::many(array_map(fn (string $table): string => self::key($table), $tables));

        $signatures = [];
        foreach (self::TOPICS as $topic => $topicTables) {
            $signatures[$topic] = md5(implode('|', array_map(fn (string $table): string => (string) ($versions[self::key($table)] ?? ''), $topicTables)));
        }

        return $signatures;
    }

    private static function key(string $table): string
    {
        return 'live-version:'.$table;
    }
}
