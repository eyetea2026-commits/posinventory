<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

        // Create admin user if not exists
        $adminEmail = 'admin@cctvexpress.local';
        $adminExists = DB::table('users')->where('email', $adminEmail)->exists();
        if (!$adminExists) {
            DB::table('users')->insert([
                'name' => 'admin',
                'email' => $adminEmail,
                'password' => Hash::make('Admin@123'),
                'role_id' => $adminRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "Admin user created successfully!\n";
            echo "Username: admin\n";
            echo "Email: admin@cctvexpress.local\n";
            echo "Password: Admin@123\n";
        } else {
            echo "Admin user already exists.\n";
        }

        // Create cashier user if not exists
        $cashierEmail = 'cashier@cctvexpress.local';
        $cashierExists = DB::table('users')->where('email', $cashierEmail)->exists();
        if (!$cashierExists) {
            DB::table('users')->insert([
                'name' => 'cashier',
                'email' => $cashierEmail,
                'password' => Hash::make('Cashier@123'),
                'role_id' => $cashierRoleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "Cashier user created successfully!\n";
            echo "Username: cashier\n";
            echo "Email: cashier@cctvexpress.local\n";
            echo "Password: Cashier@123\n";
        } else {
            echo "Cashier user already exists.\n";
        }
    }
}
