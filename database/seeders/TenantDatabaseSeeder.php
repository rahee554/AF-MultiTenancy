<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Add classes of the seeders to be run for each tenant database here, for example:
        // $this->call([
        //     RolesTableSeeder::class,
        //     TenantUsersSeeder::class,
        // ]);
        //
        // After adding the seeder classes, run the tenant database command and seed tenants:
        // php artisan tenant:db --seed
        //
        // User::factory(10)->create();
    }
}

