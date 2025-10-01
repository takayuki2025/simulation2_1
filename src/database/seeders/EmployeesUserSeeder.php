<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Carbon\Carbon;

class EmployeesUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => '山田　太郎',
            'email' => 'taro.y@coachtech.com',
            'password' => Hash::make('testtest1'),
            'role' => 'employee',
            'email_verified_at' => Carbon::now(),
        ]);
        User::create([
            'name' => '西　怜奈',
            'email' => 'reina.n@coachtech.com',
            'password' => Hash::make('testtest2'),
            'role' => 'employee',
            'email_verified_at' => Carbon::now(),
        ]);
        User::create([
            'name' => '秋田　朋美',
            'email' => 'tomomi.a@coachtech.com',
            'password' => Hash::make('testtest3'),
            'role' => 'employee',
            'email_verified_at' => Carbon::now(),
        ]);
        User::create([
            'name' => '中西　教夫',
            'email' => 'norio.n@coachtech.com',
            'password' => Hash::make('testtest4'),
            'role' => 'employee',
            'email_verified_at' => Carbon::now(),
        ]);
    }
}
