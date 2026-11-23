<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('Product')->where('IsActive', false)->update(['IsActive' => true]);
    }

    public function down(): void {}
};
