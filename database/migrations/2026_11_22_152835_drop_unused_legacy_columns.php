<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'SalesReturn' => ['ProductID', 'Quantity', 'Reason'],
        'Discount' => ['ProductID'],
        'PurchaseOrderItem' => ['ReceiptNumber'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column) && DB::table($table)->whereNotNull($column)->exists()) {
                    throw new RuntimeException("{$table}.{$column} still has data; nothing was dropped.");
                }
            }
        }

        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropForeign(['ProductID']);
        });
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropColumn(['ProductID', 'Quantity', 'Reason']);
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->dropForeign(['ProductID']);
        });
        Schema::table('Discount', function (Blueprint $table) {
            $table->dropColumn('ProductID');
        });

        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->dropUnique(['ReceiptNumber']);
        });
        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->dropColumn('ReceiptNumber');
        });
    }

    public function down(): void
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->unsignedInteger('ProductID')->nullable()->after('SalesTransactionID');
            $table->integer('Quantity')->nullable()->after('ProductID');
            $table->string('Reason')->nullable()->after('Quantity');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->cascadeOnDelete();
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->unsignedInteger('ProductID')->nullable();
            $table->foreign('ProductID')->references('ProductID')->on('Product')->nullOnDelete();
        });

        Schema::table('PurchaseOrderItem', function (Blueprint $table) {
            $table->string('ReceiptNumber', 50)->nullable()->unique();
        });
    }
};
