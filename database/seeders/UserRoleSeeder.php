<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    

        // สร้าง Owner
        User::updateOrCreate(
            ['email' => 'owner@restaurant.com'],
            [
                'name' => 'เจ้าของร้าน',
                'email' => 'owner@restaurant.com',
                'password' => Hash::make('owner123'),
                'role' => 'owner',
                'employee_id' => 'OWN001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // สร้าง Manager
        User::updateOrCreate(
            ['email' => 'manager@restaurant.com'],
            [
                'name' => 'ผู้จัดการ',
                'email' => 'manager@restaurant.com',
                'password' => Hash::make('manager123'),
                'role' => 'manager',
                'employee_id' => 'MGR001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $cashiers = [
            [
                'name' => 'แคชเชียร์ 1',
                'email' => 'cashier1@restaurant.com',
                'employee_id' => 'CSH001',
            ],
            [
                'name' => 'แคชเชียร์ 2',
                'email' => 'cashier2@restaurant.com',
                'employee_id' => 'CSH002',
            ]
        ];

        foreach ($cashiers as $cashier) {
            User::updateOrCreate(
                ['email' => $cashier['email']],
                [
                    'name' => $cashier['name'],
                    'email' => $cashier['email'],
                    'password' => Hash::make('cashier123'),
                    'role' => 'cashier',
                    'employee_id' => $cashier['employee_id'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        // สร้าง Staff 
        $staffs = [
            [
                'name' => 'พนักงาน 1',
                'email' => 'staff1@restaurant.com',
                'employee_id' => 'STF001',
            ],
            [
                'name' => 'พนักงาน 2',
                'email' => 'staff2@restaurant.com',
                'employee_id' => 'STF002',
            ],
            [
                'name' => 'พนักงาน 3',
                'email' => 'staff3@restaurant.com',
                'employee_id' => 'STF003',
            ]
        ];

        foreach ($staffs as $staff) {
            User::updateOrCreate(
                ['email' => $staff['email']],
                [
                    'name' => $staff['name'],
                    'email' => $staff['email'],
                    'password' => Hash::make('staff123'),
                    'role' => 'staff',
                    'employee_id' => $staff['employee_id'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        $this->command->info('สร้าง User ตัวอย่างสำเร็จ!');
        $this->command->info('');
        $this->command->info('รายละเอียด Login:');
        $this->command->info('Owner: owner@restaurant.com / owner123');
        $this->command->info('Manager: manager@restaurant.com / manager123');
        $this->command->info('Cashier1: cashier1@restaurant.com / cashier123');
        $this->command->info('Cashier2: cashier2@restaurant.com / cashier123');
        $this->command->info('Staff1: staff1@restaurant.com / staff123');
        $this->command->info('Staff2: staff2@restaurant.com / staff123');
        $this->command->info('Staff3: staff3@restaurant.com / staff123');
        $this->command->info('Customer: customer@example.com / customer123');
    }
}