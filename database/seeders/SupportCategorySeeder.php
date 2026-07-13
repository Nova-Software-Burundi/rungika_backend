<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SupportCategory;

class SupportCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Fuel', 'description' => 'Fuel requests and shortages'],
            ['name' => 'Breakdown', 'description' => 'Vehicle breakdown or mechanical issue'],
            ['name' => 'Documents', 'description' => 'Missing or incorrect documents'],
            ['name' => 'Accident', 'description' => 'Accident or incident reporting'],
            ['name' => 'General', 'description' => 'Other support requests'],
            // Remittance dispute categories
            ['name' => 'Payment Dispute', 'description' => 'Disputes related to remittance payments'],
            ['name' => 'Agent Not Responding', 'description' => 'Agent is not responding or unreachable'],
            ['name' => 'Incorrect Amount', 'description' => 'Amount received differs from expected'],
            ['name' => 'Other Remittance Issue', 'description' => 'Other issues related to remittances'],
        ];

        foreach ($categories as $category) {
            SupportCategory::firstOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
