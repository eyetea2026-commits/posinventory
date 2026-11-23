<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Discount', function (Blueprint $table) {
            $table->dropUnique(['DiscountRate']);
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->unsignedInteger('ProductID')->nullable()->after('DiscountID');
            $table->foreign('ProductID')->references('ProductID')->on('Product')->nullOnDelete();

            $table->string('PromoCode', 30)->nullable()->unique()->after('Name');
            $table->text('Description')->nullable()->after('PromoCode');
            $table->date('StartDate')->nullable()->after('Description');
            $table->date('EndDate')->nullable()->after('StartDate');
            $table->string('Status', 20)->default('active')->after('EndDate');

            $table->unsignedBigInteger('CreatedBy')->nullable()->after('Status');
            $table->foreign('CreatedBy')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->decimal('DiscountRate', 5, 2)->default(0)->change();
        });

        DB::table('Discount')->whereNull('created_at')->update([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('Discount', function (Blueprint $table) {
            $table->string('DiscountRate', 20)->change();
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->dropForeign(['ProductID']);
            $table->dropForeign(['CreatedBy']);
            $table->dropColumn(['ProductID', 'PromoCode', 'Description', 'StartDate', 'EndDate', 'Status', 'CreatedBy', 'created_at', 'updated_at', 'deleted_at']);
        });

        Schema::table('Discount', function (Blueprint $table) {
            $table->unique('DiscountRate');
        });
    }
};
