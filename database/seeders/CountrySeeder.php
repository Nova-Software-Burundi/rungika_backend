<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('countries:seed');
        $this->command->info('Countries seeded via countries:seed command.');
    }
}
