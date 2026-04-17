<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'customers.create',
            'customers.read',
            'customers.update',
            'customers.delete',
            'customer-requests.create',
            'customer-requests.read',
            'customer-requests.update',
            'customer-requests.delete',
            'properties.read',
            'matches.read',
            'matches.update',
            'shared-links.create',
            'shared-links.read',
            'subscriptions.manage',
            'admin.dashboard',
            'admin.users',
            'admin.roles',
            'admin.permissions',
            'admin.scraping',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Superadmin — Gate::before ilə bypass, permission assign lazım deyil
        Role::firstOrCreate(['name' => 'superadmin']);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        $makler = Role::firstOrCreate(['name' => 'makler']);
        $makler->syncPermissions([
            'customers.create',
            'customers.read',
            'customers.update',
            'customers.delete',
            'customer-requests.create',
            'customer-requests.read',
            'customer-requests.update',
            'customer-requests.delete',
            'properties.read',
            'matches.read',
            'matches.update',
            'shared-links.create',
            'shared-links.read',
        ]);

        $developer = Role::firstOrCreate(['name' => 'developer']);
        $developer->syncPermissions(Permission::all());

        // ID 1,2,3 user-lərə superadmin rolu
        foreach (\App\Models\User::whereIn('id', [1, 2, 3])->get() as $user) {
            if (!$user->hasRole('superadmin')) {
                $user->assignRole('superadmin');
            }
        }
    }
}
