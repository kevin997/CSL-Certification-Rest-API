<?php

namespace Database\Seeders;

use App\Models\TaxZone;
use Illuminate\Database\Seeder;

class TaxZoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing tax zones to avoid duplicates
        TaxZone::truncate();
        
        // European Union VAT rates (as of 2025)
        $euTaxZones = [
            ['AT', null, 'Austria', 20.00],
            ['BE', null, 'Belgium', 21.00],
            ['BG', null, 'Bulgaria', 20.00],
            ['HR', null, 'Croatia', 25.00],
            ['CY', null, 'Cyprus', 19.00],
            ['CZ', null, 'Czech Republic', 21.00],
            ['DK', null, 'Denmark', 25.00],
            ['EE', null, 'Estonia', 22.00],
            ['FI', null, 'Finland', 24.00],
            ['FR', null, 'France', 20.00],
            ['DE', null, 'Germany', 19.00],
            ['GR', null, 'Greece', 24.00],
            ['HU', null, 'Hungary', 27.00],
            ['IE', null, 'Ireland', 23.00],
            ['IT', null, 'Italy', 22.00],
            ['LV', null, 'Latvia', 21.00],
            ['LT', null, 'Lithuania', 21.00],
            ['LU', null, 'Luxembourg', 17.00],
            ['MT', null, 'Malta', 18.00],
            ['NL', null, 'Netherlands', 21.00],
            ['PL', null, 'Poland', 23.00],
            ['PT', null, 'Portugal', 23.00],
            ['RO', null, 'Romania', 19.00],
            ['SK', null, 'Slovakia', 20.00],
            ['SI', null, 'Slovenia', 22.00],
            ['ES', null, 'Spain', 21.00],
            ['SE', null, 'Sweden', 25.00],
        ];
        
        // United States state sales taxes (as of 2025)
        $usTaxZones = [
            ['US', 'CA', 'California', 8.25],
            ['US', 'NY', 'New York', 8.00],
            ['US', 'TX', 'Texas', 6.25],
            ['US', 'FL', 'Florida', 6.00],
            ['US', 'WA', 'Washington', 6.50],
            ['US', 'NV', 'Nevada', 6.85],
            ['US', 'IL', 'Illinois', 6.25],
            ['US', 'PA', 'Pennsylvania', 6.00],
            ['US', 'OH', 'Ohio', 5.75],
            ['US', 'GA', 'Georgia', 4.00],
            ['US', 'OR', 'Oregon', 0.00], // Oregon has no sales tax
            ['US', 'DE', 'Delaware', 0.00], // Delaware has no sales tax
            ['US', 'MT', 'Montana', 0.00], // Montana has no sales tax
            ['US', 'NH', 'New Hampshire', 0.00], // New Hampshire has no sales tax
            ['US', null, 'United States (Default)', 5.00], // Default US rate if state not specified
        ];
        
        // Other countries
        $otherTaxZones = [
            ['CA', null, 'Canada', 5.00], // GST only, provinces have additional taxes
            ['AU', null, 'Australia', 10.00],
            ['NZ', null, 'New Zealand', 15.00],
            ['JP', null, 'Japan', 10.00],
            ['SG', null, 'Singapore', 8.00],
            ['CH', null, 'Switzerland', 7.70],
            ['NO', null, 'Norway', 25.00],
            ['GB', null, 'United Kingdom', 20.00],
            ['RU', null, 'Russia', 20.00],
            ['CN', null, 'China', 13.00],
            ['IN', null, 'India', 18.00], // GST standard rate
            ['BR', null, 'Brazil', 17.00], // ICMS standard rate
            ['ZA', null, 'South Africa', 15.00],
            ['MX', null, 'Mexico', 16.00],
            ['AE', null, 'United Arab Emirates', 5.00],
            ['SA', null, 'Saudi Arabia', 15.00],
            ['CM', null, 'Cameroon', 19.25], // Cameroon VAT rate
        ];
        
        $allTaxZones = array_merge($euTaxZones, $usTaxZones, $otherTaxZones);
        
        foreach ($allTaxZones as [$countryCode, $stateCode, $zoneName, $taxRate]) {
            TaxZone::firstOrCreate([
                'country_code' => $countryCode,
                'state_code' => $stateCode,
                'zone_name' => $zoneName,
                'tax_rate' => $taxRate,
                'description' => "Standard tax rate for {$zoneName}",
                'is_active' => true,
            ]);
        }
    }
}
