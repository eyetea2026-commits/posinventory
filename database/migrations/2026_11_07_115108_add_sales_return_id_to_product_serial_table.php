<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The return request a sold piece came back on, so the exact serial of a
     * Damaged / Factory Defect return is known and can never be sold again.
     *
     * Returns made before this was tracked are linked to their sale's
     * serials (latest sold first), and the approved ones marked Damaged:
     * every return reason now is unsalable.
     */
    public function up(): void
    {
        Schema::table('ProductSerial', function (Blueprint $table) {
            $table->unsignedInteger('SalesReturnID')->nullable()->after('StockAdjustmentID');
            $table->index('SalesReturnID');
        });

        $items = DB::table('SalesReturnItem')
            ->join('SalesReturn', 'SalesReturn.SalesReturnID', '=', 'SalesReturnItem.SalesReturnID')
            ->whereIn('SalesReturn.Status', ['pending', 'approved', 'processed'])
            ->whereIn('SalesReturnItem.Reason', ['Factory Defect', 'Damaged Product'])
            ->orderBy('SalesReturn.SalesReturnID')
            ->get(['SalesReturn.SalesReturnID', 'SalesReturn.SalesTransactionID', 'SalesReturn.Status', 'SalesReturnItem.ProductID', 'SalesReturnItem.BaseQuantity']);

        foreach ($items as $item) {
            $serialIds = DB::table('ProductSerial')
                ->where('SalesTransactionID', $item->SalesTransactionID)
                ->where('ProductID', $item->ProductID)
                ->where('Status', 'Sold')
                ->whereNull('SalesReturnID')
                ->orderByDesc('ProductSerialID')
                ->limit((int) $item->BaseQuantity)
                ->pluck('ProductSerialID');

            if ($serialIds->isEmpty()) {
                continue;
            }

            DB::table('ProductSerial')->whereIn('ProductSerialID', $serialIds)->update(array_merge(
                ['SalesReturnID' => $item->SalesReturnID],
                $item->Status === 'pending' ? [] : ['Status' => 'Damaged', 'StatusDate' => now()],
            ));
        }
    }

    /**
     * Pieces marked Damaged by a return go back to Sold.
     */
    public function down(): void
    {
        DB::table('ProductSerial')->whereNotNull('SalesReturnID')->where('Status', 'Damaged')
            ->update(['Status' => 'Sold']);

        Schema::table('ProductSerial', function (Blueprint $table) {
            $table->dropIndex(['SalesReturnID']);
            $table->dropColumn('SalesReturnID');
        });
    }
};
