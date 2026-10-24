<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Super Admin: an Administrator who can also add Administrators and
     * activate/deactivate them. Every account that is an Administrator
     * today becomes the Super Admin.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_active');
        });

        $adminRoleIds = DB::table('roles')->whereRaw('LOWER(role_name) = ?', ['admin'])->pluck('id');

        DB::table('users')->whereIn('role_id', $adminRoleIds)->update(['is_super_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
