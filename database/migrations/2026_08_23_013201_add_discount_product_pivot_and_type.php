<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('DiscountProduct', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('DiscountID');
            $table->unsignedInteger('ProductID');
            $table->timestamps();

            $table->foreign('DiscountID')->references('DiscountID')->on('Discount')->cascadeOnDelete();
            $table->foreign('ProductID')->references('ProductID')->on('Product')->cascadeOnDelete();
            $table->unique(['DiscountID', 'ProductID']);
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->string('DiscountType', 20)->default('percentage')->after('DiscountRate');
        });

        $existingAssignments = DB::table('Discount')
            ->whereNotNull('ProductID')
            ->select('DiscountID', 'ProductID')
            ->get();

        $now = now();
        foreach ($existingAssignments as $row) {
            DB::table('DiscountProduct')->insert([
                'DiscountID' => $row->DiscountID,
                'ProductID' => $row->ProductID,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('Discount', function (Blueprint $table) {
            $table->dropColumn('DiscountType');
        });

        Schema::dropIfExists('DiscountProduct');
    }
};
