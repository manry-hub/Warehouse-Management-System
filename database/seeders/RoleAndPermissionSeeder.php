<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Standard roles
        $roles = [
            'Warehouse Manager',
            'Inbound Receiver',
            'Material Handler',
            'QA Inspector',
            'Shipping Clerk',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Create default system administrator
        $admin = User::firstOrCreate(
            ['email' => 'admin@wms.local'],
            [
                'name' => 'WMS Administrator',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole('Warehouse Manager');

        // Create QA Inspector user
        $qa = User::firstOrCreate(
            ['email' => 'qa@wms.local'],
            [
                'name' => 'QA Inspector Lead',
                'password' => Hash::make('password'),
            ]
        );
        $qa->assignRole('QA Inspector');

        // Create Floor Handler user
        $handler = User::firstOrCreate(
            ['email' => 'handler@wms.local'],
            [
                'name' => 'Shop Floor Handler',
                'password' => Hash::make('password'),
            ]
        );
        $handler->assignRole('Material Handler');
    }
}
