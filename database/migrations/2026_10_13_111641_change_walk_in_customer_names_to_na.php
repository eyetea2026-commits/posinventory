<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables that store the customer name of a sale or return.
     *
     * @var list<string>
     */
    private array $tables = ['SalesTransaction', 'Billing', 'SalesReturn'];

    /**
     * A customer who gave no name used to be saved as "Walk-in Customer";
     * it is now saved as "N/A".
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::table($table)->where('CustomerName', 'Walk-in Customer')->update(['CustomerName' => 'N/A']);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::table($table)->where('CustomerName', 'N/A')->update(['CustomerName' => 'Walk-in Customer']);
        }
    }
};
