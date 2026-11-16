<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed one owner login and one staff login for local use. The details are in the README.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'owner@africanhairways.test'], [
            'name' => 'Shop Owner',
            'password' => 'password',
            'role' => UserRole::Owner,
        ]);

        User::updateOrCreate(['email' => 'dami@africanhairways.test'], [
            'name' => 'Dami',
            'password' => 'password',
            'role' => UserRole::Staff,
            'staff_member_id' => StaffMember::where('name', 'Dami')->value('id'),
        ]);
    }
}
