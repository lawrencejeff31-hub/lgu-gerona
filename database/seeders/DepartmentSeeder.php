<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'BAC Secretariat', 'code' => 'BAC', 'description' => 'Bids and Awards Committee Secretariat'],
            ['name' => 'General Services Department', 'code' => 'GSO', 'description' => 'General Services Department'],
            ['name' => 'Budget Department', 'code' => 'BUDGET', 'description' => 'Municipal Budget Department'],
            ['name' => 'Accounting Department', 'code' => 'ACCTG', 'description' => 'Municipal Accounting Department'],
            ['name' => 'Treasury Department', 'code' => 'TREAS', 'description' => 'Municipal Treasury Department'],
            ['name' => 'Mayor\'s Department', 'code' => 'MAYOR', 'description' => 'Department of the Mayor'],
            ['name' => 'Municipal Planning Department', 'code' => 'MPO', 'description' => 'Municipal Planning and Development Department'],
            ['name' => 'Engineering Department', 'code' => 'ENG', 'description' => 'Municipal Engineering Department'],
            ['name' => 'Health Department', 'code' => 'HEALTH', 'description' => 'Municipal Health Department'],
            ['name' => 'Social Welfare Department', 'code' => 'SWO', 'description' => 'Municipal Social Welfare and Development Department'],
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }
    }
}