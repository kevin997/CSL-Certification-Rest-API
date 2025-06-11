<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class TaxZone extends Model
{
    use SoftDeletes;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'zone_name',
        'country_code',
        'state_code',
        'tax_rate',
        'description',
        'is_active',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'tax_rate' => 'float',
        'is_active' => 'boolean',
    ];
    
    /**
     * Find a tax zone by country code and optionally state code.
     *
     * @param string $countryCode
     * @param string|null $stateCode
     * @return TaxZone|null
     */
    public static function findByLocation(string $countryCode, ?string $stateCode = null): ?TaxZone
    {
        $countryCode = strtoupper($countryCode);
        
        // Debug logging
        Log::info('Searching for tax zone', [
            'country_code' => $countryCode,
            'state_code' => $stateCode,
            'existing_zones' => self::pluck('country_code')->toArray()
        ]);
        
        $query = self::where('country_code', $countryCode)
            ->where('is_active', 1); // Changed from 'true' to 1
            
        if ($stateCode) {
            // First try to find a specific state-level tax zone
            $stateZone = (clone $query)
                ->where('state_code', strtoupper($stateCode))
                ->first();
                
            if ($stateZone) {
                return $stateZone;
            }
        }
        
        // If no state-specific zone or no state provided, find country-level zone
        $result = $query->whereNull('state_code')->first();
        
        // More debug logging
        Log::info('Tax zone query result', [
            'found' => $result ? true : false,
            'country_code' => $countryCode,
            'state_code' => $stateCode
        ]);
        
        return $result;
    }
}
