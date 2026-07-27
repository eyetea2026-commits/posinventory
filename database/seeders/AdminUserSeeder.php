<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist
        $adminRoleId = DB::table('roles')->where('role_name', 'admin')->value('id');
        if (!$adminRoleId) {
            $adminRoleId = DB::table('roles')->insertGetId(['role_name' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        }

        $cashierRoleId = DB::table('roles')->where('role_name', 'cashier')->value('id');
        if (!$cashierRoleId) {
            $cashierRoleId = DB::table('roles')->insertGetId(['role_name' => 'cashier', 'created_at' => now(), 'updated_at' => now()]);
        }

        // No hardcoded default password: read one from the environment for a
        // scripted/CI setup, or generate a random one and print it once so
        // whoever ran the seeder can retrieve it. Either way, re-running this
        // seeder (or a future fresh install) never restores a known,
        // committed-to-source password.
        $adminEmail = 'admin@cctvexpress.local';
        $adminExists = DB::table('users')->where('email', $adminEmail)->exists();
        if (!$adminExists) {
            $adminPassword = env('DEFAULT_ADMIN_PASSWORD') ?: Str::password(16);

            DB::table('users')->insert([
                'name' => 'admin',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role_id' => $adminRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "Admin user created successfully!\n";
            echo "Username: admin\n";
            echo "Email: admin@cctvexpress.local\n";
            echo "Password: {$adminPassword}\n";
            echo "IMPORTANT: record this password now and change it after first login — it will not be shown again.\n";
        } else {
            echo "Admin user already exists.\n";
        }

        // Create cashier user if not exists
        $cashierEmail = 'cashier@cctvexpress.local';
        $cashierExists = DB::table('users')->where('email', $cashierEmail)->exists();
        if (!$cashierExists) {
            $cashierPassword = env('DEFAULT_CASHIER_PASSWORD') ?: Str::password(16);

            DB::table('users')->insert([
                'name' => 'cashier',
                'email' => $cashierEmail,
                'password' => Hash::make($cashierPassword),
                'role_id' => $cashierRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "Cashier user created successfully!\n";
            echo "Username: cashier\n";
            echo "Email: cashier@cctvexpress.local\n";
            echo "Password: {$cashierPassword}\n";
            echo "IMPORTANT: record this password now and change it after first login — it will not be shown again.\n";
        } else {
            echo "Cashier user already exists.\n";
        }
    }
}
