<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('role_name', 50);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->unsignedBigInteger('role_id')->nullable()->index();
                $table->timestamps();
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('Staff')) {
            Schema::create('Staff', function (Blueprint $table) {
                $table->increments('StaffID');
                $table->string('FirstName', 50);
                $table->string('MiddleName', 50);
                $table->string('LastName', 50);
                $table->string('ContactNumber', 20);
                $table->string('Email', 100);
                $table->string('Age', 10);
                $table->string('Gender', 20);
                $table->unsignedBigInteger('UserID');
                $table->foreign('UserID')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('Staff')) {
            Schema::table('Staff', function (Blueprint $table) {
                $table->dropForeign(['UserID']);
            });
            Schema::dropIfExists('Staff');
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
            });
            Schema::dropIfExists('users');
        }

        Schema::dropIfExists('roles');
    }
};
