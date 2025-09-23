<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeesUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => '山田　太郎',
            'email' => 'test@22',
            'password' => Hash::make('testtest2'),
            'role' => 'employee',
            'email_verified_at' => Carbon::now(),
        ]);
        User::create([
            'name' => '西　怜奈',
            'email' => 'test@33',
            'password' => Hash::make('testtest3'),
            'role' => 'employee',
            'email_verified_at' => Carbon::now(),
        ]);
        User::create([
            'name' => '中西　教夫',
            'email' => 'test@44',
            'password' => Hash::make('testtest4'),
            'role' => 'employee',
            'email_verified_at' => Carbon::now(),
        ]);
    }
}
