<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ActivityLog', function (Blueprint $table) {
            $table->id('ActivityLogID');
            $table->unsignedBigInteger('UserID')->nullable();
            $table->string('Action', 50);
            $table->string('Description', 255);
            $table->dateTime('DateRecorded')->useCurrent();

            $table->foreign('UserID')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ActivityLog');
    }
};
