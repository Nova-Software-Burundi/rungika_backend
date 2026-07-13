<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            RolesAndPermissionsSeeder::class,
            CountrySeeder::class,
            CurrencySeeder::class,
            SupportCategorySeeder::class,
        ]);

        // Run Wialon sync after DB created (only when you want it)
        if (app()->environment(['local','staging','production'])) {
            $this->call(WialonSyncSeeder::class);
        }
    }
}
