<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::create(['name' => 'user', 'guard_name' => 'web']);

        // Create permissions
        $permissions = [
            'view_documents',
            'create_documents',
            'edit_documents',
            'delete_documents',
            'approve_documents',
            'reject_documents',
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'manage_roles',
            'view_tracking',
            'export_documents',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $adminRole->givePermissionTo(Permission::all());
        $userRole->givePermissionTo([
            'view_documents',
            'create_documents',
            'edit_documents',
        ]);

        // Create admin user
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@gerona.gov.ph',
            'password' => Hash::make('Password123'),
        ]);
        $admin->assignRole('admin');
    }
}