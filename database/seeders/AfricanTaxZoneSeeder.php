<?php

namespace Database\Seeders;

use App\Models\TaxZone;
use Illuminate\Database\Seeder;

class AfricanTaxZoneSeeder extends Seeder
{
    /**
     * Run the database seeds for African countries tax zones.
     * 
     * This seeder focuses on comprehensive African tax coverage,
     * including VAT rates for all major African countries.
     */
    public function run(): void
    {
        // CEMAC Countries (Economic and Monetary Community of Central Africa)
        $cemacTaxZones = [
            ['CM', null, 'Cameroon', 19.25], // VAT rate
            ['CF', null, 'Central African Republic', 19.00],
            ['TD', null, 'Chad', 18.00],
            ['CG', null, 'Republic of Congo', 18.00],
            ['GQ', null, 'Equatorial Guinea', 15.00],
            ['GA', null, 'Gabon', 18.00],
        ];

        // ECOWAS Countries (Economic Community of West African States)
        $ecowaseTaxZones = [
            ['BJ', null, 'Benin', 18.00],
            ['BF', null, 'Burkina Faso', 18.00],
            ['CV', null, 'Cape Verde', 15.00],
            ['CI', null, 'Ivory Coast', 18.00],
            ['GM', null, 'The Gambia', 15.00],
            ['GH', null, 'Ghana', 12.50], // VAT + NHIL + GETFund levy
            ['GN', null, 'Guinea', 18.00],
            ['GW', null, 'Guinea-Bissau', 17.00],
            ['LR', null, 'Liberia', 10.00],
            ['ML', null, 'Mali', 18.00],
            ['NE', null, 'Niger', 19.00],
            ['NG', null, 'Nigeria', 7.50], // VAT rate
            ['SN', null, 'Senegal', 18.00],
            ['SL', null, 'Sierra Leone', 15.00],
            ['TG', null, 'Togo', 18.00],
        ];

        // EAC Countries (East African Community)
        $eacTaxZones = [
            ['KE', null, 'Kenya', 16.00],
            ['TZ', null, 'Tanzania', 18.00],
            ['UG', null, 'Uganda', 18.00],
            ['RW', null, 'Rwanda', 18.00],
            ['BI', null, 'Burundi', 18.00],
            ['SS', null, 'South Sudan', 18.00],
        ];

        // SADC Countries (Southern African Development Community)
        $sadcTaxZones = [
            ['ZA', null, 'South Africa', 15.00],
            ['BW', null, 'Botswana', 12.00],
            ['LS', null, 'Lesotho', 14.00],
            ['SZ', null, 'Eswatini (Swaziland)', 14.00],
            ['NA', null, 'Namibia', 15.00],
            ['ZW', null, 'Zimbabwe', 14.50],
            ['ZM', null, 'Zambia', 16.00],
            ['MW', null, 'Malawi', 16.50],
            ['MZ', null, 'Mozambique', 17.00],
            ['AO', null, 'Angola', 14.00],
            ['CD', null, 'Democratic Republic of Congo', 16.00],
            ['MG', null, 'Madagascar', 20.00],
            ['MU', null, 'Mauritius', 15.00],
            ['SC', null, 'Seychelles', 15.00],
        ];

        // North African Countries
        $northAfricaTaxZones = [
            ['DZ', null, 'Algeria', 19.00],
            ['EG', null, 'Egypt', 14.00],
            ['LY', null, 'Libya', 0.00], // No VAT currently
            ['MA', null, 'Morocco', 20.00],
            ['SD', null, 'Sudan', 17.00],
            ['TN', null, 'Tunisia', 19.00],
        ];

        // Other African Countries
        $otherAfricanTaxZones = [
            ['DZ', null, 'Algeria', 19.00],
            ['ET', null, 'Ethiopia', 15.00],
            ['DJ', null, 'Djibouti', 10.00],
            ['ER', null, 'Eritrea', 0.00], // No VAT
            ['SO', null, 'Somalia', 0.00], // No VAT
            ['KM', null, 'Comoros', 10.00],
            ['ST', null, 'São Tomé and Príncipe', 0.00], // No VAT
            ['CV', null, 'Cape Verde', 15.00],
        ];

        // Combine all African tax zones (island nations are included in other arrays)
        $africanTaxZones = array_merge(
            $cemacTaxZones,
            $ecowaseTaxZones,
            $eacTaxZones,
            $sadcTaxZones,
            $northAfricaTaxZones,
            $otherAfricanTaxZones
        );

        // Remove duplicates (some countries appear in multiple arrays)
        $uniqueAfricanTaxZones = [];
        $processedCountries = [];

        foreach ($africanTaxZones as $zone) {
            $countryCode = $zone[0];
            if (!in_array($countryCode, $processedCountries)) {
                $uniqueAfricanTaxZones[] = $zone;
                $processedCountries[] = $countryCode;
            }
        }

        // Special Economic Zones and Free Trade Areas
        $specialZones = [
            // CEMAC Special zones
            ['CM', 'DOUALA-FTZ', 'Douala Free Trade Zone', 0.00],
            ['GA', 'NKOK-SEZ', 'Nkok Special Economic Zone', 0.00],
            
            // Nigeria Special zones
            ['NG', 'LAGOS-FTZ', 'Lagos Free Trade Zone', 0.00],
            ['NG', 'CALABAR-FTZ', 'Calabar Free Trade Zone', 0.00],
            
            // Ghana Special zones
            ['GH', 'TEMA-FTZ', 'Tema Free Zone', 0.00],
            
            // Kenya Special zones
            ['KE', 'NAIROBI-FTZ', 'Nairobi Free Zone', 0.00],
            ['KE', 'MOMBASA-FTZ', 'Mombasa Free Port', 0.00],
            
            // South Africa Special zones
            ['ZA', 'COEGA-IDZ', 'Coega Industrial Development Zone', 0.00],
            ['ZA', 'DUBE-SEZ', 'Dube TradePort SEZ', 0.00],
            
            // Egypt Special zones
            ['EG', 'SUEZ-FTZ', 'Suez Canal Economic Zone', 0.00],
            
            // Morocco Special zones
            ['MA', 'TANGIER-FTZ', 'Tangier Free Zone', 0.00],
        ];

        // Seed all unique African tax zones
        foreach ($uniqueAfricanTaxZones as [$countryCode, $stateCode, $zoneName, $taxRate]) {
            TaxZone::firstOrCreate([
                'country_code' => $countryCode,
                'state_code' => $stateCode,
            ], [
                'zone_name' => $zoneName,
                'tax_rate' => $taxRate,
                'description' => "Standard VAT rate for {$zoneName}",
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed special economic zones
        foreach ($specialZones as [$countryCode, $stateCode, $zoneName, $taxRate]) {
            TaxZone::firstOrCreate([
                'country_code' => $countryCode,
                'state_code' => $stateCode,
            ], [
                'zone_name' => $zoneName,
                'tax_rate' => $taxRate,
                'description' => "Special Economic Zone - {$zoneName}",
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('African Tax Zones seeded successfully!');
        $this->command->info('Seeded ' . count($uniqueAfricanTaxZones) . ' standard African tax zones');
        $this->command->info('Seeded ' . count($specialZones) . ' special economic zones');
    }
}