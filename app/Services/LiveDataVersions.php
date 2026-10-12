<?php

namespace App\Services;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Change tracking for real-time pages (see LiveUpdatesController).
 *
 * Most tables have no updated_at column, so an edit can't be seen by
 * looking at the data. Instead every insert/update/delete the app runs is
 * noticed as it happens (AppServiceProvider registers recordWrite() as a
 * DB listener) and bumps a version number for that table. Pages name the
 * kind of data they show ("topics" below); the "anything new?" check
 * returns a fingerprint per topic, so a page re-fetches only when its own
 * data changed.
 */
class LiveDataVersions
{
    /**
     * Topic => the tables whose changes it covers (lowercase).
     *
     * @var array<string, list<string>>
     */
    public const TOPICS = [
        'products' => ['product', 'category', 'brand', 'inventory', 'productsupplier', 'supplier', 'discount', 'discountproduct'],
        'categories' => ['category', 'brand', 'product'],
        'suppliers' => ['supplier', 'productsupplier', 'product', 'purchaseorder', 'purchaseorderitem'],
        'purchase_orders' => ['purchaseorder', 'purchaseorderitem', 'stockreceiving', 'stockreceivingbatch', 'supplier', 'product'],
        'stock_receivings' => ['stockreceiving', 'stockreceivingbatch', 'purchaseorder', 'purchaseorderitem', 'supplier', 'product'],
        'stock_adjustments' => ['stockadjustment', 'inventory', 'product'],
        'damages' => ['damagedproduct', 'salesreturn', 'salesreturnitem', 'replacement', 'product'],
        'inventory' => ['inventory', 'product', 'category', 'brand'],
        'sales' => ['salestransaction', 'salesitem', 'payment', 'billing', 'salesreturn', 'salesreturnitem', 'replacement'],
        'discounts' => ['discount', 'discountproduct', 'product'],
        'activity' => ['activitylog'],
    ];

    /**
     * Tables written on (almost) every request, or by this tracker itself,
     * which no page shows.
     */
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

    /**
     * One fingerprint per topic.
     *
     * @return array<string, string>
     */
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
