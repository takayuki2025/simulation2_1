<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Carbon\Carbon;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        User::create([
            'name' => '川田　隆之',
            'email' => 't.principle.k2024@gmail.com',
            'password' => Hash::make('takayuki'),
            'role' => 'admin',
            'email_verified_at' => Carbon::now(),
        ]);
    }
}