<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['SalesTransaction', 'Billing', 'SalesReturn'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->where('CustomerName', 'Walk-in Customer')->update(['CustomerName' => 'N/A']);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            DB::table($table)->where('CustomerName', 'N/A')->update(['CustomerName' => 'Walk-in Customer']);
        }
    }
};
