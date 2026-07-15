<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->string('ReturnType', 20)->default('refund')->after('Reason');
            $table->string('DeclineReason', 255)->nullable()->after('Status');
            $table->unsignedBigInteger('ProcessedBy')->nullable()->after('ApprovedBy');
        });

        DB::table('SalesReturn')->where('Status', 'rejected')->update(['Status' => 'declined']);
    }

    public function down()
    {
        Schema::table('SalesReturn', function (Blueprint $table) {
            $table->dropColumn(['ReturnType', 'DeclineReason', 'ProcessedBy']);
        });
    }
};
