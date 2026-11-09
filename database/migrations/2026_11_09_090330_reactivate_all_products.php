<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Products can no longer be deactivated (panel suggestion): whether a
 * product is listed on the POS is decided by its stock alone. Products
 * deactivated before then are turned back on so none is left hidden with
 * no button to bring it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('Product')->where('IsActive', false)->update(['IsActive' => true]);
    }

    /**
     * Which products were deactivated isn't kept, so nothing is undone.
     */
    public function down(): void {}
};
