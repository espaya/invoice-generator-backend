<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanySetting;
use App\Models\User;

class CompanySettingSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (!$user) {
            return;
        }

        CompanySetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_name' => 'Beehive Technologies Ltd',
                'company_email' => 'business@beehivetech.com',
                'company_phone' => '+233 000 000 000',
                'company_address' => 'Accra, Ghana',
                'primary_color' => '#0d6efd',
                'secondary_color' => '#6c757d',
                'invoice_prefix' => 'INV',
                'invoice_footer' => 'Thank you for your business!',
                'tin' => 'C0001234567',
                'currency' => 'GHS',
                'currency_symbol' => '₵',

            ]
        );
    }
}
