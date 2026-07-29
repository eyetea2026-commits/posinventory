<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('Brand')->updateOrInsert(['BrandName' => 'Dahua'], []);
    }

    public function down(): void
    {
        DB::table('Brand')->where('BrandName', 'Dahua')->delete();
    }
};
