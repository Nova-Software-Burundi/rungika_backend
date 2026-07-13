<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'ZMW', 'name' => 'Zambian Kwacha',       'symbol' => 'ZK',  'is_default' => false, 'enabled' => true],
            ['code' => 'NGN', 'name' => 'Nigerian Naira',        'symbol' => '₦',   'is_default' => false, 'enabled' => true],
            ['code' => 'KES', 'name' => 'Kenyan Shilling',       'symbol' => 'KSh', 'is_default' => false, 'enabled' => true],
            ['code' => 'UGX', 'name' => 'Ugandan Shilling',      'symbol' => 'USh', 'is_default' => false, 'enabled' => true],
            ['code' => 'TZS', 'name' => 'Tanzanian Shilling',    'symbol' => 'TSh', 'is_default' => false, 'enabled' => true],
            ['code' => 'RWF', 'name' => 'Rwandan Franc',         'symbol' => 'FRw', 'is_default' => false, 'enabled' => true],
            ['code' => 'ZAR', 'name' => 'South African Rand',    'symbol' => 'R',   'is_default' => false, 'enabled' => true],
            ['code' => 'BWP', 'name' => 'Botswana Pula',         'symbol' => 'P',   'is_default' => false, 'enabled' => true],
            ['code' => 'GHS', 'name' => 'Ghanaian Cedi',         'symbol' => 'GH₵', 'is_default' => false, 'enabled' => true],
            ['code' => 'XOF', 'name' => 'West African CFA Franc','symbol' => 'CFA', 'is_default' => false, 'enabled' => true],
            ['code' => 'XAF', 'name' => 'Central African CFA Franc','symbol' => 'CFA', 'is_default' => false, 'enabled' => true],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
