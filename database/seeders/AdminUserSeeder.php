<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultWarehouse = Warehouse::query()->value('code');

        User::updateOrCreate(
            ['email' => 'super@dlms.test'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPER_ADMIN,
                'warehouse_code' => null,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'gudang@dlms.test'],
            [
                'name' => 'Admin Gudang',
                'password' => Hash::make('password'),
                'role' => User::ROLE_WAREHOUSE_ADMIN,
                'warehouse_code' => $defaultWarehouse,
                'is_active' => true,
            ]
        );

        // Pastikan akun lama tetap bisa login sebagai super admin.
        User::where('email', 'test@example.com')->update([
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
    }
}
