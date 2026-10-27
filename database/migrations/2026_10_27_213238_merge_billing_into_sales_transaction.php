<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing was a one-to-one copy of every sale (customers pay at once),
     * so its amounts move onto SalesTransaction and the Payment points at
     * the sale itself: SalesTransaction -> SalesItem, with Payment linked
     * to the transaction. Billing's CustomerName and BillingDate repeated
     * the sale's own and aren't copied.
     */
    public function up(): void
    {
        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->decimal('Subtotal', 10, 2)->nullable();
            $table->decimal('DiscountAmount', 10, 2)->nullable();
            $table->decimal('CustomDiscountPercent', 5, 2)->nullable();
            $table->decimal('CustomDiscountAmount', 10, 2)->nullable();
            $table->unsignedBigInteger('CustomDiscountApprovedBy')->nullable();
            $table->string('PromoCode', 30)->nullable();
            $table->string('VatApplied', 20)->nullable();
            $table->decimal('VatAmount', 10, 2)->nullable();
            $table->decimal('TotalAmount', 10, 2)->default(0);
            $table->unsignedInteger('DiscountID')->nullable();
            $table->foreign('DiscountID')->references('DiscountID')->on('Discount')->onDelete('set null');
        });

        Schema::table('Payment', function (Blueprint $table) {
            $table->unsignedInteger('SalesTransactionID')->nullable();
        });

        foreach (DB::table('Billing')->orderBy('BillingID')->get() as $billing) {
            DB::table('SalesTransaction')->where('SalesTransactionID', $billing->SalesTransactionID)->update([
                'Subtotal' => $billing->Subtotal,
                'DiscountAmount' => $billing->DiscountAmount,
                'CustomDiscountPercent' => $billing->CustomDiscountPercent,
                'CustomDiscountAmount' => $billing->CustomDiscountAmount,
                'CustomDiscountApprovedBy' => $billing->CustomDiscountApprovedBy,
                'PromoCode' => $billing->PromoCode,
                'VatApplied' => $billing->VatApplied,
                'VatAmount' => $billing->VatAmount,
                'TotalAmount' => $billing->BillingAmount,
                'DiscountID' => $billing->DiscountID,
            ]);
            DB::table('Payment')->where('BillingID', $billing->BillingID)->update(['SalesTransactionID' => $billing->SalesTransactionID]);
        }

        Schema::table('Payment', function (Blueprint $table) {
            $table->dropForeign(['BillingID']);
        });
        Schema::table('Payment', function (Blueprint $table) {
            $table->dropColumn('BillingID');
        });
        Schema::table('Payment', function (Blueprint $table) {
            $table->foreign('SalesTransactionID')->references('SalesTransactionID')->on('SalesTransaction')->onDelete('cascade');
        });

        Schema::dropIfExists('Billing');
    }

    public function down(): void
    {
        Schema::create('Billing', function (Blueprint $table) {
            $table->increments('BillingID');
            $table->string('CustomerName', 100);
            $table->string('VatApplied', 20);
            $table->decimal('BillingAmount', 10, 2);
            $table->decimal('Subtotal', 10, 2)->nullable();
            $table->decimal('DiscountAmount', 10, 2)->nullable();
            $table->decimal('CustomDiscountPercent', 5, 2)->nullable();
            $table->decimal('CustomDiscountAmount', 10, 2)->nullable();
            $table->unsignedBigInteger('CustomDiscountApprovedBy')->nullable();
            $table->string('PromoCode', 30)->nullable();
            $table->decimal('VatAmount', 10, 2)->nullable();
            $table->date('BillingDate');
            $table->unsignedInteger('DiscountID')->nullable();
            $table->unsignedInteger('SalesTransactionID');
            $table->foreign('DiscountID')->references('DiscountID')->on('Discount')->onDelete('set null');
            $table->foreign('SalesTransactionID')->references('SalesTransactionID')->on('SalesTransaction')->onDelete('cascade');
        });

        Schema::table('Payment', function (Blueprint $table) {
            $table->unsignedInteger('BillingID')->nullable();
        });

        foreach (DB::table('SalesTransaction')->orderBy('SalesTransactionID')->get() as $sale) {
            $billingId = DB::table('Billing')->insertGetId([
                'CustomerName' => $sale->CustomerName,
                'VatApplied' => $sale->VatApplied ?? '12%',
                'BillingAmount' => $sale->TotalAmount,
                'Subtotal' => $sale->Subtotal,
                'DiscountAmount' => $sale->DiscountAmount,
                'CustomDiscountPercent' => $sale->CustomDiscountPercent,
                'CustomDiscountAmount' => $sale->CustomDiscountAmount,
                'CustomDiscountApprovedBy' => $sale->CustomDiscountApprovedBy,
                'PromoCode' => $sale->PromoCode,
                'VatAmount' => $sale->VatAmount,
                'BillingDate' => substr((string) $sale->SalesTransactionDate, 0, 10),
                'DiscountID' => $sale->DiscountID,
                'SalesTransactionID' => $sale->SalesTransactionID,
            ], 'BillingID');
            DB::table('Payment')->where('SalesTransactionID', $sale->SalesTransactionID)->update(['BillingID' => $billingId]);
        }

        Schema::table('Payment', function (Blueprint $table) {
            $table->dropForeign(['SalesTransactionID']);
        });
        Schema::table('Payment', function (Blueprint $table) {
            $table->dropColumn('SalesTransactionID');
            $table->foreign('BillingID')->references('BillingID')->on('Billing')->onDelete('cascade');
        });

        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->dropForeign(['DiscountID']);
        });
        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->dropColumn([
                'Subtotal', 'DiscountAmount', 'CustomDiscountPercent', 'CustomDiscountAmount', 'CustomDiscountApprovedBy',
                'PromoCode', 'VatApplied', 'VatAmount', 'TotalAmount', 'DiscountID',
            ]);
        });
    }
};
