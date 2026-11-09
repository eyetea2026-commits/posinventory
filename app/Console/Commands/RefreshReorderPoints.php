<?php

namespace App\Console\Commands;

use App\Services\ReorderPoint;
use Illuminate\Console\Command;

class RefreshReorderPoints extends Command
{
    protected $signature = 'reorder-points:refresh';

    protected $description = 'Recompute every product\'s Re-Order Point from its sales and supplier lead time';

    public function handle(): int
    {
        ReorderPoint::refreshAll();
        $this->info('Re-Order Points refreshed.');

        return self::SUCCESS;
    }
}
