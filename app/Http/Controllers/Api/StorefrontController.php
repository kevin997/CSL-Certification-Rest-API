<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGatewaySetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductReview;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CourseSectionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class StorefrontController extends Controller
{
    /**
     * Get the environment by ID
     *
     * @param string $environmentId
     * @return Environment|null
     */
    protected function getEnvironmentById(string $environmentId)
    {
        return Environment::find($environmentId);
    }

    /**
     * Get the order by ID
     *
     * @param string $environmentId
     * @param string $orderId
     * @return Order|null
     */
    protected function getOrderById(string $environmentId, string $orderId)
    {
        return Order::find($orderId);
    }


    /**
     * Get the order by ID
     *
     * @param string $environmentId
     * @param string $orderId
     * @return Order|null
     */
    public function getOrder(string $environmentId, string $orderId) {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $order = $this->getOrderById($environmentId, $orderId);
        
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }
    
    /**
     * Get a list of countries
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCountries(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        // List of countries with their codes
        $countries = [
            // Common countries
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'IT', 'name' => 'Italy'],
            ['code' => 'ES', 'name' => 'Spain'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'CN', 'name' => 'China'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'BR', 'name' => 'Brazil'],
            ['code' => 'MX', 'name' => 'Mexico'],
            ['code' => 'ZA', 'name' => 'South Africa'],
            ['code' => 'AE', 'name' => 'United Arab Emirates'],
            ['code' => 'AR', 'name' => 'Argentina'],
            ['code' => 'AT', 'name' => 'Austria'],
            ['code' => 'BE', 'name' => 'Belgium'],
            ['code' => 'CL', 'name' => 'Chile'],
            ['code' => 'CO', 'name' => 'Colombia'],
            ['code' => 'CZ', 'name' => 'Czech Republic'],
            ['code' => 'DK', 'name' => 'Denmark'],
            ['code' => 'EG', 'name' => 'Egypt'],
            ['code' => 'FI', 'name' => 'Finland'],
            ['code' => 'GR', 'name' => 'Greece'],
            ['code' => 'HK', 'name' => 'Hong Kong'],
            ['code' => 'HU', 'name' => 'Hungary'],
            ['code' => 'ID', 'name' => 'Indonesia'],
            ['code' => 'IE', 'name' => 'Ireland'],
            ['code' => 'IL', 'name' => 'Israel'],
            ['code' => 'KR', 'name' => 'South Korea'],
            ['code' => 'MY', 'name' => 'Malaysia'],
            ['code' => 'NL', 'name' => 'Netherlands'],
            ['code' => 'NO', 'name' => 'Norway'],
            ['code' => 'NZ', 'name' => 'New Zealand'],
            ['code' => 'PE', 'name' => 'Peru'],
            ['code' => 'PH', 'name' => 'Philippines'],
            ['code' => 'PL', 'name' => 'Poland'],
            ['code' => 'PT', 'name' => 'Portugal'],
            ['code' => 'RO', 'name' => 'Romania'],
            ['code' => 'RU', 'name' => 'Russia'],
            ['code' => 'SE', 'name' => 'Sweden'],
            ['code' => 'SG', 'name' => 'Singapore'],
            ['code' => 'TH', 'name' => 'Thailand'],
            ['code' => 'TR', 'name' => 'Turkey'],
            ['code' => 'UA', 'name' => 'Ukraine'],
            ['code' => 'VN', 'name' => 'Vietnam'],
            
            // CEMAC Countries (Economic and Monetary Community of Central Africa)
            ['code' => 'CM', 'name' => 'Cameroon'],
            ['code' => 'CF', 'name' => 'Central African Republic'],
            ['code' => 'TD', 'name' => 'Chad'],
            ['code' => 'CG', 'name' => 'Republic of Congo'],
            ['code' => 'GQ', 'name' => 'Equatorial Guinea'],
            ['code' => 'GA', 'name' => 'Gabon'],
            
            // ECOWAS/CEDEAO Countries (Economic Community of West African States)
            ['code' => 'BJ', 'name' => 'Benin'],
            ['code' => 'BF', 'name' => 'Burkina Faso'],
            ['code' => 'CV', 'name' => 'Cape Verde'],
            ['code' => 'GM', 'name' => 'The Gambia'],
            ['code' => 'GH', 'name' => 'Ghana'],
            ['code' => 'GN', 'name' => 'Guinea'],
            ['code' => 'GW', 'name' => 'Guinea-Bissau'],
            ['code' => 'CI', 'name' => 'Ivory Coast'],
            ['code' => 'LR', 'name' => 'Liberia'],
            ['code' => 'ML', 'name' => 'Mali'],
            ['code' => 'NE', 'name' => 'Niger'],
            ['code' => 'NG', 'name' => 'Nigeria'],
            ['code' => 'SN', 'name' => 'Senegal'],
            ['code' => 'SL', 'name' => 'Sierra Leone'],
            ['code' => 'TG', 'name' => 'Togo'],
        ];
        
        return response()->json([
            'success' => true,
            'data' => $countries,
            'environment' => $environment
        ]);
    }
    
    /**
     * Get a list of states/provinces for a country
     *
     * @param Request $request
     * @param string $environmentId
     * @param string $countryCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStates(Request $request, string $environmentId, string $countryCode)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $states = [];
        
        // Return states based on country code
        if ($countryCode === 'US') {
            $states = [
                ['code' => 'AL', 'name' => 'Alabama'],
                ['code' => 'AK', 'name' => 'Alaska'],
                ['code' => 'AZ', 'name' => 'Arizona'],
                ['code' => 'AR', 'name' => 'Arkansas'],
                ['code' => 'CA', 'name' => 'California'],
                ['code' => 'CO', 'name' => 'Colorado'],
                ['code' => 'CT', 'name' => 'Connecticut'],
                ['code' => 'DE', 'name' => 'Delaware'],
                ['code' => 'FL', 'name' => 'Florida'],
                ['code' => 'GA', 'name' => 'Georgia'],
                ['code' => 'HI', 'name' => 'Hawaii'],
                ['code' => 'ID', 'name' => 'Idaho'],
                ['code' => 'IL', 'name' => 'Illinois'],
                ['code' => 'IN', 'name' => 'Indiana'],
                ['code' => 'IA', 'name' => 'Iowa'],
                ['code' => 'KS', 'name' => 'Kansas'],
                ['code' => 'KY', 'name' => 'Kentucky'],
                ['code' => 'LA', 'name' => 'Louisiana'],
                ['code' => 'ME', 'name' => 'Maine'],
                ['code' => 'MD', 'name' => 'Maryland'],
                ['code' => 'MA', 'name' => 'Massachusetts'],
                ['code' => 'MI', 'name' => 'Michigan'],
                ['code' => 'MN', 'name' => 'Minnesota'],
                ['code' => 'MS', 'name' => 'Mississippi'],
                ['code' => 'MO', 'name' => 'Missouri'],
                ['code' => 'MT', 'name' => 'Montana'],
                ['code' => 'NE', 'name' => 'Nebraska'],
                ['code' => 'NV', 'name' => 'Nevada'],
                ['code' => 'NH', 'name' => 'New Hampshire'],
                ['code' => 'NJ', 'name' => 'New Jersey'],
                ['code' => 'NM', 'name' => 'New Mexico'],
                ['code' => 'NY', 'name' => 'New York'],
                ['code' => 'NC', 'name' => 'North Carolina'],
                ['code' => 'ND', 'name' => 'North Dakota'],
                ['code' => 'OH', 'name' => 'Ohio'],
                ['code' => 'OK', 'name' => 'Oklahoma'],
                ['code' => 'OR', 'name' => 'Oregon'],
                ['code' => 'PA', 'name' => 'Pennsylvania'],
                ['code' => 'RI', 'name' => 'Rhode Island'],
                ['code' => 'SC', 'name' => 'South Carolina'],
                ['code' => 'SD', 'name' => 'South Dakota'],
                ['code' => 'TN', 'name' => 'Tennessee'],
                ['code' => 'TX', 'name' => 'Texas'],
                ['code' => 'UT', 'name' => 'Utah'],
                ['code' => 'VT', 'name' => 'Vermont'],
                ['code' => 'VA', 'name' => 'Virginia'],
                ['code' => 'WA', 'name' => 'Washington'],
                ['code' => 'WV', 'name' => 'West Virginia'],
                ['code' => 'WI', 'name' => 'Wisconsin'],
                ['code' => 'WY', 'name' => 'Wyoming'],
                ['code' => 'DC', 'name' => 'District of Columbia']
            ];
        } else if ($countryCode === 'CA') {
            $states = [
                ['code' => 'AB', 'name' => 'Alberta'],
                ['code' => 'BC', 'name' => 'British Columbia'],
                ['code' => 'MB', 'name' => 'Manitoba'],
                ['code' => 'NB', 'name' => 'New Brunswick'],
                ['code' => 'NL', 'name' => 'Newfoundland and Labrador'],
                ['code' => 'NS', 'name' => 'Nova Scotia'],
                ['code' => 'NT', 'name' => 'Northwest Territories'],
                ['code' => 'NU', 'name' => 'Nunavut'],
                ['code' => 'ON', 'name' => 'Ontario'],
                ['code' => 'PE', 'name' => 'Prince Edward Island'],
                ['code' => 'QC', 'name' => 'Quebec'],
                ['code' => 'SK', 'name' => 'Saskatchewan'],
                ['code' => 'YT', 'name' => 'Yukon']
            ];
        } else if ($countryCode === 'GB') {
            $states = [
                ['code' => 'ENG', 'name' => 'England'],
                ['code' => 'SCT', 'name' => 'Scotland'],
                ['code' => 'WLS', 'name' => 'Wales'],
                ['code' => 'NIR', 'name' => 'Northern Ireland']
            ];
        } else if ($countryCode === 'AU') {
            $states = [
                ['code' => 'ACT', 'name' => 'Australian Capital Territory'],
                ['code' => 'NSW', 'name' => 'New South Wales'],
                ['code' => 'NT', 'name' => 'Northern Territory'],
                ['code' => 'QLD', 'name' => 'Queensland'],
                ['code' => 'SA', 'name' => 'South Australia'],
                ['code' => 'TAS', 'name' => 'Tasmania'],
                ['code' => 'VIC', 'name' => 'Victoria'],
                ['code' => 'WA', 'name' => 'Western Australia']
            ];
        } else if ($countryCode === 'DE') {
            $states = [
                ['code' => 'BW', 'name' => 'Baden-Württemberg'],
                ['code' => 'BY', 'name' => 'Bavaria'],
                ['code' => 'BE', 'name' => 'Berlin'],
                ['code' => 'BB', 'name' => 'Brandenburg'],
                ['code' => 'HB', 'name' => 'Bremen'],
                ['code' => 'HH', 'name' => 'Hamburg'],
                ['code' => 'HE', 'name' => 'Hesse'],
                ['code' => 'MV', 'name' => 'Mecklenburg-Vorpommern'],
                ['code' => 'NI', 'name' => 'Lower Saxony'],
                ['code' => 'NW', 'name' => 'North Rhine-Westphalia'],
                ['code' => 'RP', 'name' => 'Rhineland-Palatinate'],
                ['code' => 'SL', 'name' => 'Saarland'],
                ['code' => 'SN', 'name' => 'Saxony'],
                ['code' => 'ST', 'name' => 'Saxony-Anhalt'],
                ['code' => 'SH', 'name' => 'Schleswig-Holstein'],
                ['code' => 'TH', 'name' => 'Thuringia']
            ];
        } else if ($countryCode === 'IN') {
            $states = [
                ['code' => 'AP', 'name' => 'Andhra Pradesh'],
                ['code' => 'AR', 'name' => 'Arunachal Pradesh'],
                ['code' => 'AS', 'name' => 'Assam'],
                ['code' => 'BR', 'name' => 'Bihar'],
                ['code' => 'CT', 'name' => 'Chhattisgarh'],
                ['code' => 'GA', 'name' => 'Goa'],
                ['code' => 'GJ', 'name' => 'Gujarat'],
                ['code' => 'HR', 'name' => 'Haryana'],
                ['code' => 'HP', 'name' => 'Himachal Pradesh'],
                ['code' => 'JH', 'name' => 'Jharkhand'],
                ['code' => 'KA', 'name' => 'Karnataka'],
                ['code' => 'KL', 'name' => 'Kerala'],
                ['code' => 'MP', 'name' => 'Madhya Pradesh'],
                ['code' => 'MH', 'name' => 'Maharashtra'],
                ['code' => 'MN', 'name' => 'Manipur'],
                ['code' => 'ML', 'name' => 'Meghalaya'],
                ['code' => 'MZ', 'name' => 'Mizoram'],
                ['code' => 'NL', 'name' => 'Nagaland'],
                ['code' => 'OR', 'name' => 'Odisha'],
                ['code' => 'PB', 'name' => 'Punjab'],
                ['code' => 'RJ', 'name' => 'Rajasthan'],
                ['code' => 'SK', 'name' => 'Sikkim'],
                ['code' => 'TN', 'name' => 'Tamil Nadu'],
                ['code' => 'TG', 'name' => 'Telangana'],
                ['code' => 'TR', 'name' => 'Tripura'],
                ['code' => 'UT', 'name' => 'Uttarakhand'],
                ['code' => 'UP', 'name' => 'Uttar Pradesh'],
                ['code' => 'WB', 'name' => 'West Bengal']
            ];
        } else if ($countryCode === 'MX') {
            $states = [
                ['code' => 'AGU', 'name' => 'Aguascalientes'],
                ['code' => 'BCN', 'name' => 'Baja California'],
                ['code' => 'BCS', 'name' => 'Baja California Sur'],
                ['code' => 'CAM', 'name' => 'Campeche'],
                ['code' => 'CHP', 'name' => 'Chiapas'],
                ['code' => 'CHH', 'name' => 'Chihuahua'],
                ['code' => 'CMX', 'name' => 'Ciudad de México'],
                ['code' => 'COA', 'name' => 'Coahuila'],
                ['code' => 'COL', 'name' => 'Colima'],
                ['code' => 'DUR', 'name' => 'Durango'],
                ['code' => 'GUA', 'name' => 'Guanajuato'],
                ['code' => 'GRO', 'name' => 'Guerrero'],
                ['code' => 'HID', 'name' => 'Hidalgo'],
                ['code' => 'JAL', 'name' => 'Jalisco'],
                ['code' => 'MEX', 'name' => 'México'],
                ['code' => 'MIC', 'name' => 'Michoacán'],
                ['code' => 'MOR', 'name' => 'Morelos'],
                ['code' => 'NAY', 'name' => 'Nayarit'],
                ['code' => 'NLE', 'name' => 'Nuevo León'],
                ['code' => 'OAX', 'name' => 'Oaxaca'],
                ['code' => 'PUE', 'name' => 'Puebla'],
                ['code' => 'QUE', 'name' => 'Querétaro'],
                ['code' => 'ROO', 'name' => 'Quintana Roo'],
                ['code' => 'SLP', 'name' => 'San Luis Potosí'],
                ['code' => 'SIN', 'name' => 'Sinaloa'],
                ['code' => 'SON', 'name' => 'Sonora'],
                ['code' => 'TAB', 'name' => 'Tabasco'],
                ['code' => 'TAM', 'name' => 'Tamaulipas'],
                ['code' => 'TLA', 'name' => 'Tlaxcala'],
                ['code' => 'VER', 'name' => 'Veracruz'],
                ['code' => 'YUC', 'name' => 'Yucatán'],
                ['code' => 'ZAC', 'name' => 'Zacatecas']
            ];
        }
        // CEMAC Countries
        // Cameroon
        else if ($countryCode === 'CM') {
            $states = [
                ['code' => 'AD', 'name' => 'Adamawa'],
                ['code' => 'CE', 'name' => 'Centre'],
                ['code' => 'ES', 'name' => 'East'],
                ['code' => 'EN', 'name' => 'Far North'],
                ['code' => 'LT', 'name' => 'Littoral'],
                ['code' => 'NO', 'name' => 'North'],
                ['code' => 'NW', 'name' => 'North-West'],
                ['code' => 'SU', 'name' => 'South'],
                ['code' => 'SW', 'name' => 'South-West'],
                ['code' => 'OU', 'name' => 'West']
            ];
        }
        // Central African Republic
        else if ($countryCode === 'CF') {
            $states = [
                ['code' => 'BGF', 'name' => 'Bangui'],
                ['code' => 'BB', 'name' => 'Bamingui-Bangoran'],
                ['code' => 'BK', 'name' => 'Basse-Kotto'],
                ['code' => 'HK', 'name' => 'Haute-Kotto'],
                ['code' => 'HM', 'name' => 'Haut-Mbomou'],
                ['code' => 'KG', 'name' => 'Kémo'],
                ['code' => 'LB', 'name' => 'Lobaye'],
                ['code' => 'HS', 'name' => 'Mambéré-Kadéï'],
                ['code' => 'MB', 'name' => 'Mbomou'],
                ['code' => 'NM', 'name' => 'Nana-Mambéré'],
                ['code' => 'MP', 'name' => 'Ombella-M\'Poko'],
                ['code' => 'UK', 'name' => 'Ouaka'],
                ['code' => 'AC', 'name' => 'Ouham'],
                ['code' => 'OP', 'name' => 'Ouham-Pendé'],
                ['code' => 'SE', 'name' => 'Sangha-Mbaéré'],
                ['code' => 'VK', 'name' => 'Vakaga']
            ];
        }
        // Chad
        else if ($countryCode === 'TD') {
            $states = [
                ['code' => 'BA', 'name' => 'Batha'],
                ['code' => 'BG', 'name' => 'Borkou'],
                ['code' => 'CB', 'name' => 'Chari-Baguirmi'],
                ['code' => 'EE', 'name' => 'Ennedi-Est'],
                ['code' => 'EO', 'name' => 'Ennedi-Ouest'],
                ['code' => 'GR', 'name' => 'Guéra'],
                ['code' => 'HL', 'name' => 'Hadjer-Lamis'],
                ['code' => 'KA', 'name' => 'Kanem'],
                ['code' => 'LC', 'name' => 'Lac'],
                ['code' => 'LO', 'name' => 'Logone Occidental'],
                ['code' => 'LR', 'name' => 'Logone Oriental'],
                ['code' => 'MA', 'name' => 'Mandoul'],
                ['code' => 'ME', 'name' => 'Mayo-Kebbi Est'],
                ['code' => 'MO', 'name' => 'Mayo-Kebbi Ouest'],
                ['code' => 'MC', 'name' => 'Moyen-Chari'],
                ['code' => 'ND', 'name' => 'N\'Djamena'],
                ['code' => 'OD', 'name' => 'Ouaddaï'],
                ['code' => 'SA', 'name' => 'Salamat'],
                ['code' => 'SI', 'name' => 'Sila'],
                ['code' => 'TA', 'name' => 'Tandjilé'],
                ['code' => 'TI', 'name' => 'Tibesti'],
                ['code' => 'WF', 'name' => 'Wadi Fira']
            ];
        }
        // Republic of Congo
        else if ($countryCode === 'CG') {
            $states = [
                ['code' => 'BZV', 'name' => 'Brazzaville'],
                ['code' => 'PNR', 'name' => 'Pointe-Noire'],
                ['code' => 'BOU', 'name' => 'Bouenza'],
                ['code' => 'CUV', 'name' => 'Cuvette'],
                ['code' => 'CUE', 'name' => 'Cuvette-Ouest'],
                ['code' => 'KOU', 'name' => 'Kouilou'],
                ['code' => 'LEK', 'name' => 'Lékoumou'],
                ['code' => 'LIK', 'name' => 'Likouala'],
                ['code' => 'NIA', 'name' => 'Niari'],
                ['code' => 'PLT', 'name' => 'Plateaux'],
                ['code' => 'POO', 'name' => 'Pool'],
                ['code' => 'SAN', 'name' => 'Sangha']
            ];
        }
        // Equatorial Guinea
        else if ($countryCode === 'GQ') {
            $states = [
                ['code' => 'AN', 'name' => 'Annobón'],
                ['code' => 'BN', 'name' => 'Bioko Norte'],
                ['code' => 'BS', 'name' => 'Bioko Sur'],
                ['code' => 'CS', 'name' => 'Centro Sur'],
                ['code' => 'KN', 'name' => 'Kié-Ntem'],
                ['code' => 'LI', 'name' => 'Litoral'],
                ['code' => 'WN', 'name' => 'Wele-Nzas']
            ];
        }
        // Gabon
        else if ($countryCode === 'GA') {
            $states = [
                ['code' => 'ES', 'name' => 'Estuaire'],
                ['code' => 'HO', 'name' => 'Haut-Ogooué'],
                ['code' => 'MO', 'name' => 'Moyen-Ogooué'],
                ['code' => 'NG', 'name' => 'Ngounié'],
                ['code' => 'NY', 'name' => 'Nyanga'],
                ['code' => 'OI', 'name' => 'Ogooué-Ivindo'],
                ['code' => 'OL', 'name' => 'Ogooué-Lolo'],
                ['code' => 'OM', 'name' => 'Ogooué-Maritime'],
                ['code' => 'WN', 'name' => 'Woleu-Ntem']
            ];
        }
        // ECOWAS Countries
        // Nigeria
        else if ($countryCode === 'NG') {
            $states = [
                ['code' => 'AB', 'name' => 'Abia'],
                ['code' => 'AD', 'name' => 'Adamawa'],
                ['code' => 'AK', 'name' => 'Akwa Ibom'],
                ['code' => 'AN', 'name' => 'Anambra'],
                ['code' => 'BA', 'name' => 'Bauchi'],
                ['code' => 'BY', 'name' => 'Bayelsa'],
                ['code' => 'BE', 'name' => 'Benue'],
                ['code' => 'BO', 'name' => 'Borno'],
                ['code' => 'CR', 'name' => 'Cross River'],
                ['code' => 'DE', 'name' => 'Delta'],
                ['code' => 'EB', 'name' => 'Ebonyi'],
                ['code' => 'ED', 'name' => 'Edo'],
                ['code' => 'EK', 'name' => 'Ekiti'],
                ['code' => 'EN', 'name' => 'Enugu'],
                ['code' => 'FC', 'name' => 'Federal Capital Territory'],
                ['code' => 'GO', 'name' => 'Gombe'],
                ['code' => 'IM', 'name' => 'Imo'],
                ['code' => 'JI', 'name' => 'Jigawa'],
                ['code' => 'KD', 'name' => 'Kaduna'],
                ['code' => 'KN', 'name' => 'Kano'],
                ['code' => 'KT', 'name' => 'Katsina'],
                ['code' => 'KE', 'name' => 'Kebbi'],
                ['code' => 'KO', 'name' => 'Kogi'],
                ['code' => 'KW', 'name' => 'Kwara'],
                ['code' => 'LA', 'name' => 'Lagos'],
                ['code' => 'NA', 'name' => 'Nasarawa'],
                ['code' => 'NI', 'name' => 'Niger'],
                ['code' => 'OG', 'name' => 'Ogun'],
                ['code' => 'ON', 'name' => 'Ondo'],
                ['code' => 'OS', 'name' => 'Osun'],
                ['code' => 'OY', 'name' => 'Oyo'],
                ['code' => 'PL', 'name' => 'Plateau'],
                ['code' => 'RI', 'name' => 'Rivers'],
                ['code' => 'SO', 'name' => 'Sokoto'],
                ['code' => 'TA', 'name' => 'Taraba'],
                ['code' => 'YO', 'name' => 'Yobe'],
                ['code' => 'ZA', 'name' => 'Zamfara']
            ];
        }
        // Ghana
        else if ($countryCode === 'GH') {
            $states = [
                ['code' => 'AF', 'name' => 'Ahafo'],
                ['code' => 'AH', 'name' => 'Ashanti'],
                ['code' => 'BA', 'name' => 'Bono'],
                ['code' => 'BE', 'name' => 'Bono East'],
                ['code' => 'CP', 'name' => 'Central'],
                ['code' => 'EP', 'name' => 'Eastern'],
                ['code' => 'AA', 'name' => 'Greater Accra'],
                ['code' => 'NE', 'name' => 'North East'],
                ['code' => 'NP', 'name' => 'Northern'],
                ['code' => 'OT', 'name' => 'Oti'],
                ['code' => 'SV', 'name' => 'Savannah'],
                ['code' => 'UE', 'name' => 'Upper East'],
                ['code' => 'UW', 'name' => 'Upper West'],
                ['code' => 'TV', 'name' => 'Volta'],
                ['code' => 'WP', 'name' => 'Western'],
                ['code' => 'WN', 'name' => 'Western North']
            ];
        }
        // Senegal
        else if ($countryCode === 'SN') {
            $states = [
                ['code' => 'DK', 'name' => 'Dakar'],
                ['code' => 'DB', 'name' => 'Diourbel'],
                ['code' => 'FK', 'name' => 'Fatick'],
                ['code' => 'KA', 'name' => 'Kaffrine'],
                ['code' => 'KL', 'name' => 'Kaolack'],
                ['code' => 'KD', 'name' => 'Kolda'],
                ['code' => 'KE', 'name' => 'Kédougou'],
                ['code' => 'LG', 'name' => 'Louga'],
                ['code' => 'MT', 'name' => 'Matam'],
                ['code' => 'SL', 'name' => 'Saint-Louis'],
                ['code' => 'SE', 'name' => 'Sédhiou'],
                ['code' => 'TC', 'name' => 'Tambacounda'],
                ['code' => 'TH', 'name' => 'Thiès'],
                ['code' => 'ZG', 'name' => 'Ziguinchor']
            ];
        }
        // Ivory Coast
        else if ($countryCode === 'CI') {
            $states = [
                ['code' => 'AB', 'name' => 'Abidjan'],
                ['code' => 'BS', 'name' => 'Bas-Sassandra'],
                ['code' => 'CM', 'name' => 'Comoé'],
                ['code' => 'DN', 'name' => 'Denguélé'],
                ['code' => 'GD', 'name' => 'Gôh-Djiboua'],
                ['code' => 'LC', 'name' => 'Lacs'],
                ['code' => 'LG', 'name' => 'Lagunes'],
                ['code' => 'MG', 'name' => 'Montagnes'],
                ['code' => 'SM', 'name' => 'Sassandra-Marahoué'],
                ['code' => 'SV', 'name' => 'Savanes'],
                ['code' => 'VB', 'name' => 'Vallée du Bandama'],
                ['code' => 'WR', 'name' => 'Woroba'],
                ['code' => 'YM', 'name' => 'Yamoussoukro'],
                ['code' => 'ZZ', 'name' => 'Zanzan']
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $states,
            'environment' => $environment
        ]);
    }
    
    /**
     * Get a list of cities for a state/province
     *
     * @param Request $request
     * @param string $environmentId
     * @param string $countryCode
     * @param string $stateCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCities(Request $request, string $environmentId, string $countryCode, string $stateCode)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        // For demonstration purposes, return a sample list of cities
        // In a real application, you would query a database or use a third-party API
        $cities = [];
        
        // US Cities
        if ($countryCode === 'US') {
            // California
            if ($stateCode === 'CA') {
                $cities = [
                    ['name' => 'Los Angeles'],
                    ['name' => 'San Francisco'],
                    ['name' => 'San Diego'],
                    ['name' => 'Sacramento'],
                    ['name' => 'San Jose'],
                    ['name' => 'Fresno'],
                    ['name' => 'Oakland'],
                    ['name' => 'Bakersfield'],
                    ['name' => 'Anaheim'],
                    ['name' => 'Santa Ana'],
                    ['name' => 'Riverside'],
                    ['name' => 'Irvine'],
                    ['name' => 'San Bernardino'],
                    ['name' => 'Modesto'],
                    ['name' => 'Fontana']
                ];
            } 
            // New York
            else if ($stateCode === 'NY') {
                $cities = [
                    ['name' => 'New York City'],
                    ['name' => 'Buffalo'],
                    ['name' => 'Rochester'],
                    ['name' => 'Yonkers'],
                    ['name' => 'Syracuse'],
                    ['name' => 'Albany'],
                    ['name' => 'New Rochelle'],
                    ['name' => 'Mount Vernon'],
                    ['name' => 'Schenectady'],
                    ['name' => 'Utica'],
                    ['name' => 'Binghamton'],
                    ['name' => 'Troy'],
                    ['name' => 'Niagara Falls'],
                    ['name' => 'White Plains'],
                    ['name' => 'Saratoga Springs']
                ];
            }
            // Texas
            else if ($stateCode === 'TX') {
                $cities = [
                    ['name' => 'Houston'],
                    ['name' => 'San Antonio'],
                    ['name' => 'Dallas'],
                    ['name' => 'Austin'],
                    ['name' => 'Fort Worth'],
                    ['name' => 'El Paso'],
                    ['name' => 'Arlington'],
                    ['name' => 'Corpus Christi'],
                    ['name' => 'Plano'],
                    ['name' => 'Laredo'],
                    ['name' => 'Lubbock'],
                    ['name' => 'Garland'],
                    ['name' => 'Irving'],
                    ['name' => 'Amarillo'],
                    ['name' => 'Grand Prairie']
                ];
            }
            // Florida
            else if ($stateCode === 'FL') {
                $cities = [
                    ['name' => 'Jacksonville'],
                    ['name' => 'Miami'],
                    ['name' => 'Tampa'],
                    ['name' => 'Orlando'],
                    ['name' => 'St. Petersburg'],
                    ['name' => 'Hialeah'],
                    ['name' => 'Tallahassee'],
                    ['name' => 'Fort Lauderdale'],
                    ['name' => 'Port St. Lucie'],
                    ['name' => 'Cape Coral'],
                    ['name' => 'Pembroke Pines'],
                    ['name' => 'Hollywood'],
                    ['name' => 'Miramar'],
                    ['name' => 'Gainesville'],
                    ['name' => 'Coral Springs']
                ];
            }
        }
        // Canada Cities
        else if ($countryCode === 'CA') {
            // Ontario
            if ($stateCode === 'ON') {
                $cities = [
                    ['name' => 'Toronto'],
                    ['name' => 'Ottawa'],
                    ['name' => 'Mississauga'],
                    ['name' => 'Brampton'],
                    ['name' => 'Hamilton'],
                    ['name' => 'London'],
                    ['name' => 'Markham'],
                    ['name' => 'Vaughan'],
                    ['name' => 'Kitchener'],
                    ['name' => 'Windsor']
                ];
            }
            // British Columbia
            else if ($stateCode === 'BC') {
                $cities = [
                    ['name' => 'Vancouver'],
                    ['name' => 'Victoria'],
                    ['name' => 'Surrey'],
                    ['name' => 'Burnaby'],
                    ['name' => 'Richmond'],
                    ['name' => 'Abbotsford'],
                    ['name' => 'Kelowna'],
                    ['name' => 'Coquitlam'],
                    ['name' => 'Saanich'],
                    ['name' => 'Delta']
                ];
            }
            // Quebec
            else if ($stateCode === 'QC') {
                $cities = [
                    ['name' => 'Montreal'],
                    ['name' => 'Quebec City'],
                    ['name' => 'Laval'],
                    ['name' => 'Gatineau'],
                    ['name' => 'Longueuil'],
                    ['name' => 'Sherbrooke'],
                    ['name' => 'Saguenay'],
                    ['name' => 'Lévis'],
                    ['name' => 'Trois-Rivières'],
                    ['name' => 'Terrebonne']
                ];
            }
        }
        // UK Cities
        else if ($countryCode === 'GB') {
            // England
            if ($stateCode === 'ENG') {
                $cities = [
                    ['name' => 'London'],
                    ['name' => 'Birmingham'],
                    ['name' => 'Manchester'],
                    ['name' => 'Liverpool'],
                    ['name' => 'Leeds'],
                    ['name' => 'Sheffield'],
                    ['name' => 'Bristol'],
                    ['name' => 'Newcastle'],
                    ['name' => 'Nottingham'],
                    ['name' => 'Southampton'],
                    ['name' => 'Oxford'],
                    ['name' => 'Cambridge'],
                    ['name' => 'York'],
                    ['name' => 'Brighton'],
                    ['name' => 'Portsmouth']
                ];
            }
            // Scotland
            else if ($stateCode === 'SCT') {
                $cities = [
                    ['name' => 'Edinburgh'],
                    ['name' => 'Glasgow'],
                    ['name' => 'Aberdeen'],
                    ['name' => 'Dundee'],
                    ['name' => 'Inverness'],
                    ['name' => 'Perth'],
                    ['name' => 'Stirling'],
                    ['name' => 'St Andrews'],
                    ['name' => 'Paisley'],
                    ['name' => 'Falkirk']
                ];
            }
            // Wales
            else if ($stateCode === 'WLS') {
                $cities = [
                    ['name' => 'Cardiff'],
                    ['name' => 'Swansea'],
                    ['name' => 'Newport'],
                    ['name' => 'Bangor'],
                    ['name' => 'St Davids'],
                    ['name' => 'Wrexham'],
                    ['name' => 'St Asaph'],
                    ['name' => 'Aberystwyth'],
                    ['name' => 'Llandudno'],
                    ['name' => 'Carmarthen']
                ];
            }
        }
        // Australian Cities
        else if ($countryCode === 'AU') {
            // New South Wales
            if ($stateCode === 'NSW') {
                $cities = [
                    ['name' => 'Sydney'],
                    ['name' => 'Newcastle'],
                    ['name' => 'Wollongong'],
                    ['name' => 'Central Coast'],
                    ['name' => 'Maitland'],
                    ['name' => 'Wagga Wagga'],
                    ['name' => 'Albury'],
                    ['name' => 'Port Macquarie'],
                    ['name' => 'Tamworth'],
                    ['name' => 'Orange']
                ];
            }
            // Victoria
            else if ($stateCode === 'VIC') {
                $cities = [
                    ['name' => 'Melbourne'],
                    ['name' => 'Geelong'],
                    ['name' => 'Ballarat'],
                    ['name' => 'Bendigo'],
                    ['name' => 'Shepparton'],
                    ['name' => 'Melton'],
                    ['name' => 'Mildura'],
                    ['name' => 'Warrnambool'],
                    ['name' => 'Wodonga'],
                    ['name' => 'Traralgon']
                ];
            }
            // Queensland
            else if ($stateCode === 'QLD') {
                $cities = [
                    ['name' => 'Brisbane'],
                    ['name' => 'Gold Coast'],
                    ['name' => 'Sunshine Coast'],
                    ['name' => 'Townsville'],
                    ['name' => 'Cairns'],
                    ['name' => 'Toowoomba'],
                    ['name' => 'Mackay'],
                    ['name' => 'Rockhampton'],
                    ['name' => 'Bundaberg'],
                    ['name' => 'Hervey Bay']
                ];
            }
        }
        // CEMAC Countries Cities
        // Cameroon
        else if ($countryCode === 'CM') {
            // Centre Region
            if ($stateCode === 'CE') {
                $cities = [
                    ['name' => 'Yaoundé'],
                    ['name' => 'Mbalmayo'],
                    ['name' => 'Obala'],
                    ['name' => 'Bafia'],
                    ['name' => 'Monatélé'],
                    ['name' => 'Nanga Eboko'],
                    ['name' => 'Ntui'],
                    ['name' => 'Eseka'],
                    ['name' => 'Mfou'],
                    ['name' => 'Nkoteng']
                ];
            }
            // Littoral Region
            else if ($stateCode === 'LT') {
                $cities = [
                    ['name' => 'Douala'],
                    ['name' => 'Nkongsamba'],
                    ['name' => 'Edéa'],
                    ['name' => 'Loum'],
                    ['name' => 'Manjo'],
                    ['name' => 'Mbanga'],
                    ['name' => 'Dizangué'],
                    ['name' => 'Yabassi'],
                    ['name' => 'Penja'],
                    ['name' => 'Njombé']
                ];
            }
            // North-West Region
            else if ($stateCode === 'NW') {
                $cities = [
                    ['name' => 'Bamenda'],
                    ['name' => 'Kumbo'],
                    ['name' => 'Nkambé'],
                    ['name' => 'Wum'],
                    ['name' => 'Mbengwi'],
                    ['name' => 'Fundong'],
                    ['name' => 'Ndop'],
                    ['name' => 'Batibo'],
                    ['name' => 'Bali'],
                    ['name' => 'Jakiri']
                ];
            }
        }
        // Republic of Congo
        else if ($countryCode === 'CG') {
            // Brazzaville
            if ($stateCode === 'BZV') {
                $cities = [
                    ['name' => 'Brazzaville'],
                    ['name' => 'Kintelé'],
                    ['name' => 'Nganga Lingolo'],
                    ['name' => 'Linzolo'],
                    ['name' => 'Kintambo'],
                    ['name' => 'Mbamou'],
                    ['name' => 'Goma Tsé-Tsé'],
                    ['name' => 'Ignié'],
                    ['name' => 'Makoua'],
                    ['name' => 'Ngabé']
                ];
            }
            // Pointe-Noire
            else if ($stateCode === 'PNR') {
                $cities = [
                    ['name' => 'Pointe-Noire'],
                    ['name' => 'Tié-Tié'],
                    ['name' => 'Loandjili'],
                    ['name' => 'Mongo-Mpoukou'],
                    ['name' => 'Ngoyo'],
                    ['name' => 'Lumumba'],
                    ['name' => 'Mvou-Mvou'],
                    ['name' => 'Tchibamba'],
                    ['name' => 'Nkouikou'],
                    ['name' => 'Vindoulou']
                ];
            }
        }
        // Gabon
        else if ($countryCode === 'GA') {
            // Estuaire
            if ($stateCode === 'ES') {
                $cities = [
                    ['name' => 'Libreville'],
                    ['name' => 'Owendo'],
                    ['name' => 'Ntoum'],
                    ['name' => 'Kango'],
                    ['name' => 'Cocobeach'],
                    ['name' => 'Ndzomoe'],
                    ['name' => 'Cap Estérias'],
                    ['name' => 'Cap Santa Clara'],
                    ['name' => 'Donguila'],
                    ['name' => 'Ikoy-Tsini']
                ];
            }
            // Haut-Ogooué
            else if ($stateCode === 'HO') {
                $cities = [
                    ['name' => 'Franceville'],
                    ['name' => 'Moanda'],
                    ['name' => 'Mounana'],
                    ['name' => 'Okondja'],
                    ['name' => 'Akiéni'],
                    ['name' => 'Lékoni'],
                    ['name' => 'Bakoumba'],
                    ['name' => 'Ngouoni'],
                    ['name' => 'Bongoville'],
                    ['name' => 'Boumango']
                ];
            }
        }
        // ECOWAS Countries Cities
        // Nigeria
        else if ($countryCode === 'NG') {
            // Lagos
            if ($stateCode === 'LA') {
                $cities = [
                    ['name' => 'Lagos'],
                    ['name' => 'Ikeja'],
                    ['name' => 'Badagry'],
                    ['name' => 'Epe'],
                    ['name' => 'Ikorodu'],
                    ['name' => 'Lekki'],
                    ['name' => 'Mushin'],
                    ['name' => 'Oshodi'],
                    ['name' => 'Surulere'],
                    ['name' => 'Yaba'],
                    ['name' => 'Ajah'],
                    ['name' => 'Alimosho'],
                    ['name' => 'Apapa'],
                    ['name' => 'Festac'],
                    ['name' => 'Victoria Island']
                ];
            }
            // Federal Capital Territory
            else if ($stateCode === 'FC') {
                $cities = [
                    ['name' => 'Abuja'],
                    ['name' => 'Gwagwalada'],
                    ['name' => 'Kuje'],
                    ['name' => 'Bwari'],
                    ['name' => 'Kwali'],
                    ['name' => 'Abaji'],
                    ['name' => 'Kubwa'],
                    ['name' => 'Nyanya'],
                    ['name' => 'Karu'],
                    ['name' => 'Jabi'],
                    ['name' => 'Maitama'],
                    ['name' => 'Asokoro'],
                    ['name' => 'Wuse'],
                    ['name' => 'Garki'],
                    ['name' => 'Lugbe']
                ];
            }
            // Rivers
            else if ($stateCode === 'RI') {
                $cities = [
                    ['name' => 'Port Harcourt'],
                    ['name' => 'Bonny'],
                    ['name' => 'Degema'],
                    ['name' => 'Eleme'],
                    ['name' => 'Okrika'],
                    ['name' => 'Omoku'],
                    ['name' => 'Opobo'],
                    ['name' => 'Oyigbo'],
                    ['name' => 'Buguma'],
                    ['name' => 'Bori'],
                    ['name' => 'Ahoada'],
                    ['name' => 'Eberi'],
                    ['name' => 'Etche'],
                    ['name' => 'Isiokpo'],
                    ['name' => 'Tai']
                ];
            }
        }
        // Ghana
        else if ($countryCode === 'GH') {
            // Greater Accra
            if ($stateCode === 'AA') {
                $cities = [
                    ['name' => 'Accra'],
                    ['name' => 'Tema'],
                    ['name' => 'Madina'],
                    ['name' => 'Teshie'],
                    ['name' => 'Ashaiman'],
                    ['name' => 'Adenta'],
                    ['name' => 'Dome'],
                    ['name' => 'Nungua'],
                    ['name' => 'Osu'],
                    ['name' => 'La'],
                    ['name' => 'Dansoman'],
                    ['name' => 'Kaneshie'],
                    ['name' => 'Achimota'],
                    ['name' => 'Labadi'],
                    ['name' => 'Jamestown']
                ];
            }
            // Ashanti
            else if ($stateCode === 'AH') {
                $cities = [
                    ['name' => 'Kumasi'],
                    ['name' => 'Obuasi'],
                    ['name' => 'Bekwai'],
                    ['name' => 'Ejisu'],
                    ['name' => 'Mampong'],
                    ['name' => 'Konongo'],
                    ['name' => 'Asokore Mampong'],
                    ['name' => 'Effiduase'],
                    ['name' => 'Offinso'],
                    ['name' => 'Ejura'],
                    ['name' => 'Agona'],
                    ['name' => 'Juaben'],
                    ['name' => 'Tepa'],
                    ['name' => 'Agogo'],
                    ['name' => 'Nkawie']
                ];
            }
        }
        // Senegal
        else if ($countryCode === 'SN') {
            // Dakar
            if ($stateCode === 'DK') {
                $cities = [
                    ['name' => 'Dakar'],
                    ['name' => 'Pikine'],
                    ['name' => 'Guédiawaye'],
                    ['name' => 'Rufisque'],
                    ['name' => 'Bargny'],
                    ['name' => 'Sébikotane'],
                    ['name' => 'Diamniadio'],
                    ['name' => 'Yène'],
                    ['name' => 'Sangalkam'],
                    ['name' => 'Jaxaay'],
                    ['name' => 'Keur Massar'],
                    ['name' => 'Mbao'],
                    ['name' => 'Thiaroye'],
                    ['name' => 'Yeumbeul'],
                    ['name' => 'Malika']
                ];
            }
            // Saint-Louis
            else if ($stateCode === 'SL') {
                $cities = [
                    ['name' => 'Saint-Louis'],
                    ['name' => 'Dagana'],
                    ['name' => 'Richard Toll'],
                    ['name' => 'Podor'],
                    ['name' => 'Matam'],
                    ['name' => 'Ndioum'],
                    ['name' => 'Ross Béthio'],
                    ['name' => 'Mpal'],
                    ['name' => 'Guédé'],
                    ['name' => 'Galoya']
                ];
            }
        }
        // Ivory Coast
        else if ($countryCode === 'CI') {
            // Abidjan
            if ($stateCode === 'AB') {
                $cities = [
                    ['name' => 'Abidjan'],
                    ['name' => 'Abobo'],
                    ['name' => 'Adjamé'],
                    ['name' => 'Attécoubé'],
                    ['name' => 'Cocody'],
                    ['name' => 'Koumassi'],
                    ['name' => 'Marcory'],
                    ['name' => 'Plateau'],
                    ['name' => 'Port-Bouët'],
                    ['name' => 'Treichville'],
                    ['name' => 'Yopougon'],
                    ['name' => 'Bingerville'],
                    ['name' => 'Songon'],
                    ['name' => 'Anyama'],
                    ['name' => 'Grand-Bassam']
                ];
            }
            // Yamoussoukro
            else if ($stateCode === 'YM') {
                $cities = [
                    ['name' => 'Yamoussoukro'],
                    ['name' => 'Toumodi'],
                    ['name' => 'Tiébissou'],
                    ['name' => 'Didiévi'],
                    ['name' => 'Attiégouakro'],
                    ['name' => 'Molonou'],
                    ['name' => 'Kossou'],
                    ['name' => 'Lolobo'],
                    ['name' => 'Seman'],
                    ['name' => 'Zatta']
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $cities,
            'environment' => $environment
        ]);
    }

    /**
     * Get featured products for an environment
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeaturedProducts(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $products = Product::where('environment_id', $environment->id)
            ->where('is_featured', true)
            ->where('status', 'active')
            ->with('category')
            ->limit(6)
            ->get();
        
        return response()->json(['data' => $products]);
    }

    /**
     * Get all products for an environment with pagination and filtering
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllProducts(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $query = Product::where('environment_id', $environment->id)
            ->where('status', 'active')
            ->with('category');
        
        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('category')) {
            $categoryIds = explode(',', $request->input('category'));
            $query->whereIn('category_id', $categoryIds);
        }
        
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }
        
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }
        
        // Apply sorting
        $sortField = 'created_at';
        $sortDirection = 'desc';
        
        if ($request->has('sort')) {
            switch ($request->input('sort')) {
                case 'price_low':
                    $sortField = 'price';
                    $sortDirection = 'asc';
                    break;
                case 'price_high':
                    $sortField = 'price';
                    $sortDirection = 'desc';
                    break;
                case 'name_asc':
                    $sortField = 'name';
                    $sortDirection = 'asc';
                    break;
                case 'name_desc':
                    $sortField = 'name';
                    $sortDirection = 'desc';
                    break;
                case 'oldest':
                    $sortField = 'created_at';
                    $sortDirection = 'asc';
                    break;
                case 'newest':
                    $sortField = 'created_at';
                    $sortDirection = 'desc';
                    break;
            }
        }
        
        $query->orderBy($sortField, $sortDirection);
        
        // Get pagination parameters
        $perPage = $request->input('per_page', 12);
        
        // Get categories for filtering
        $categories = ProductCategory::where('environment_id', $environment->id)
            ->get(['id', 'name', 'slug']);
        
        $products = $query->paginate($perPage);
        
        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'from' => $products->firstItem(),
                'last_page' => $products->lastPage(),
                'path' => $request->url(),
                'per_page' => $products->perPage(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * Get a product by slug
     *
     * @param Request $request
     * @param string $environmentId
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductBySlug(Request $request, string $environmentId, string $slug)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $product = Product::where('environment_id', $environment->id)
            ->where('slug', $slug)
            ->with(['category', 'courses'])
            ->first();
        
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        
        // Get related products
        $relatedProducts = Product::where('environment_id', $environment->id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->where(function($query) use ($product) {
                $query->where('category_id', $product->category_id)
                      ->orWhere('is_featured', true);
            })
            ->limit(4)
            ->get();
        
        $product->related_products = $relatedProducts;
        
        return response()->json(['data' => $product]);
    }
    
    /**
     * Get a product by ID
     *
     * @param Request $request
     * @param string $environmentId
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductById(Request $request, string $environmentId, int $id)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $product = Product::where('environment_id', $environment->id)
            ->where('id', $id)
            ->with(['category', 'courses'])
            ->first();
        
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        
        // Get related products
        $relatedProducts = Product::where('environment_id', $environment->id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->where(function($query) use ($product) {
                $query->where('category_id', $product->category_id)
                      ->orWhere('is_featured', true);
            })
            ->limit(4)
            ->get();
        
        $product->related_products = $relatedProducts;
        
        return response()->json(['data' => $product]);
    }

    /**
     * Get product categories
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategories(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $categories = ProductCategory::where('environment_id', $environment->id)
            ->get();
        
        return response()->json(['data' => $categories]);
    }

    /**
     * Get available payment methods
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentMethods(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $paymentMethods = PaymentGatewaySetting::where('environment_id', $environment->id)
            ->where('is_active', true)
            ->with('paymentGateway')
            ->get()
            ->map(function($method) {
                return [
                    'id' => $method->id,
                    'name' => $method->paymentGateway->name,
                    'type' => $method->paymentGateway->type,
                    'logo' => $method->paymentGateway->logo_url,
                    'description' => $method->paymentGateway->description,
                ];
            });
        
        return response()->json(['data' => $paymentMethods]);
    }
    
    /**
     * Get payment gateways
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentGateways(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        // Get active payment gateways for this environment
        $gateways = PaymentGatewaySetting::where('environment_id', $environment->id)
            ->where('status', true)
            ->orderBy('sort_order')
            ->get();
        
        return response()->json(['data' => $gateways]);
    }

    /**
     * Process checkout
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkout(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'nullable|string|max:20',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|exists:payment_gateway_settings,id',
            'billing_address' => 'required|string|max:255',
            'billing_city' => 'required|string|max:255',
            'billing_state' => 'required|string|max:255',
            'billing_zip' => 'required|string|max:20',
            'billing_country' => 'required|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Find or create user by email
            $userExists = User::where('email', $request->input('email'))->exists();
            
            $user = User::firstOrCreate(
                ['email' => $request->input('email')],
                [
                    'name' => $request->input('name'),
                    'password' => bcrypt(Str::random(16)),
                ]
            );
            
            // Dispatch event for user creation/environment association
            event(new \App\Events\UserCreatedDuringCheckout(
                $user,
                $environment,
                !$userExists // isNewUser flag is true if the user didn't exist before
            ));
            
            // Calculate total amount first
            $totalAmount = 0;
            $orderItems = [];
            
            // Process products and calculate total
            foreach ($request->input('products') as $item) {
                $product = Product::findOrFail($item['id']);
                
                // Skip if product doesn't belong to this environment
                if ($product->environment_id !== $environment->id) {
                    continue;
                }
                
                $price = $product->discount_price ?? $product->price;
                $quantity = $item['quantity'];
                $total = $price * $quantity;
                
                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $total
                ];
                
                $totalAmount += $total;
            }
            
            // Create order with total amount already set
            $order = new Order();
            $order->user_id = $user->id;
            $order->environment_id = $environment->id;
            $order->order_number = 'ORD-' . strtoupper(Str::random(8));
            $order->status = 'pending';
            $order->payment_method = $request->input('payment_method');
            $order->billing_name = $request->input('name');
            $order->billing_email = $request->input('email');
            $order->phone_number = $request->input('phone_number');
            $order->billing_address = $request->input('billing_address');
            $order->billing_city = $request->input('billing_city');
            $order->billing_state = $request->input('billing_state');
            $order->billing_zip = $request->input('billing_zip');
            $order->billing_country = $request->input('billing_country');
            $order->notes = $request->input('notes');
            $order->referral_id = $request->input('referral_id');
            $order->total_amount = $totalAmount;
            $order->currency = 'USD'; // Default currency
            $order->save();
            
           
            
            // Check if a referral code was provided
            if ($request->has('referral_code') && !empty($request->input('referral_code'))) {
                $referralCode = $request->input('referral_code');
                
                // Find the referral by code and environment
                $referral = \App\Models\EnvironmentReferral::where('code', $referralCode)
                    ->where('environment_id', $environment->id)
                    ->where('is_active', true)
                    ->first();
                    
                if ($referral) {
                    // Check if referral has expired
                    if (!$referral->expiration_date || now()->isBefore($referral->expiration_date)) {
                        // Check if referral has reached max uses
                        if ($referral->max_uses <= 0 || $referral->uses_count < $referral->max_uses) {
                            // Set the referral ID on the order
                            $order->referral_id = $referral->id;
                            $order->save();
                            
                            // Dispatch the event to process the referral usage
                            event(new \App\Events\OrderCompletedWithReferral($order, $referral));
                            
                            // Log the referral usage
                            Log::info('Referral code used in order', [
                                'referral_id' => $referral->id,
                                'referral_code' => $referral->code,
                                'order_id' => $order->id,
                                'order_number' => $order->order_number
                            ]);
                        }
                    }
                }
            }
            
            DB::commit();
            
            // Prepare the response data
            $responseData = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $totalAmount,
                'status' => 'pending'
            ];
            
            // Get the payment gateway code
            $gatewayCode = null;
            if ($request->has('payment_method')) {
                $gatewaySettings = PaymentGatewaySetting::find($request->input('payment_method'));
                if ($gatewaySettings) {
                    $gatewayCode = $gatewaySettings->gateway_code;
                }
            }
            
            Log::info('Gateway Code: ' . $gatewayCode);
            
            // If we have a valid gateway, create a payment session/intent
            if ($gatewayCode) {
                try {
                    // Create the payment using the appropriate gateway
                    $paymentService = app(\App\Services\PaymentService::class);
                    $paymentResult = $paymentService->createPayment(
                        $order->id,
                        $gatewayCode,
                        [],
                        $environment->id
                    );
                    
                    // Get transaction ID from payment result if available
                    $transactionId = $paymentResult['transaction_id'] ?? null;
                    
                    if ($paymentResult['success']) {
                        // Add payment-specific data to the response based on the gateway type
                        switch ($paymentResult['type']) {
                            case 'client_secret':
                                // For Stripe inline payments
                                $responseData['payment_type'] = 'stripe';
                                $responseData['client_secret'] = $paymentResult['value'];
                                $responseData['publishable_key'] = $paymentResult['publishable_key'] ?? null;
                                break;
                                
                            case 'checkout_url':
                                // For PayPal redirect-based payments
                                $responseData['payment_type'] = 'paypal';
                                $responseData['redirect_url'] = $paymentResult['value'];
                                break;
                                
                            case 'payment_url':
                                // For Lygos redirect-based payments
                                $responseData['payment_type'] = 'lygos';
                                $responseData['redirect_url'] = $paymentResult['value'];
                                break;
                                
                            default:
                                // Fallback to standard payment type
                                $responseData['payment_type'] = 'standard';
                                // No payment_url needed for standard payment type
                                break;
                        }
                    } else {
                        // If payment creation failed, fall back to standard payment type
                        $responseData['payment_type'] = 'standard';
                        // Log the error for debugging
                        Log::error('Payment creation failed: ' . ($paymentResult['message'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e) {
                    // If an exception occurred, fall back to standard payment type
                    $responseData['payment_type'] = 'standard';
                    // Log the error for debugging
                    Log::error('Payment creation exception: ' . $e->getMessage());
                }
            } else {
                // If no gateway was specified, just confirm the order was placed successfully
                $responseData['payment_type'] = 'standard';
                // No payment_url needed - frontend will redirect to success page
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $responseData
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process checkout',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get product reviews
     *
     * @param Request $request
     * @param string $environmentId
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductReviews(Request $request, string $environmentId, int $productId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $product = Product::where('environment_id', $environment->id)
            ->where('id', $productId)
            ->first();
            
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        
        // Get approved reviews for this product
        $reviews = ProductReview::where('product_id', $product->id)
            ->where('environment_id', $environment->id)
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Get average rating
        $averageRating = $reviews->avg('rating') ?: 0;
        
        return response()->json([
            'data' => $reviews,
            'average_rating' => round($averageRating, 1),
            'total_reviews' => $reviews->count()
        ]);
    }
    
    /**
     * Submit a product review
     *
     * @param Request $request
     * @param string $environmentId
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitProductReview(Request $request, string $environmentId, int $productId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $product = Product::where('environment_id', $environment->id)
            ->where('id', $productId)
            ->first();
            
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        
        // Check if user is authenticated
        $userId = null;
        if ($request->user()) {
            $userId = $request->user()->id;
        }
        
        // Create the review
        $review = new ProductReview();
        $review->product_id = $product->id;
        $review->environment_id = $environment->id;
        $review->user_id = $userId;
        $review->name = $request->name;
        $review->email = $request->email;
        $review->rating = $request->rating;
        $review->comment = $request->comment;
        $review->status = 'pending'; // Reviews are pending by default
        $review->save();
        
        return response()->json(['message' => 'Review submitted successfully', 'data' => $review]);
    }
    
    /**
     * Get all courses for an environment
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCourses(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $query = Course::where('environment_id', $environment->id);
        
        // Filter by status (default to published)
        $status = $request->get('status', 'published');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        // Filter by featured
        if ($request->has('featured')) {
            $query->where('is_featured', true);
        }
        
        // Search by title
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('title', 'like', "%{$search}%");
        }
        
        // Pagination
        $perPage = $request->get('per_page', 12);
        $courses = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        return response()->json(['data' => $courses]);
    }
    
    /**
     * Get a course by slug
     *
     * @param Request $request
     * @param string $environmentId
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCourseBySlug(Request $request, string $environmentId, string $slug)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $course = Course::where('environment_id', $environment->id)
            ->where('slug', $slug)
            ->with([
                'sections' => function($query) {
                    $query->orderBy('order');
                },
                'sections.items' => function($query) {
                    $query->orderBy('order');
                },
                'sections.items.activity'
            ])
            ->first();
        
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        
        // Get related courses
        $relatedCourses = Course::where('environment_id', $environment->id)
            ->where('id', '!=', $course->id)
            ->where('status', 'published')
            ->where(function($query) use ($course) {
                $query->where('difficulty_level', $course->difficulty_level)
                      ->orWhere('is_featured', true);
            })
            ->limit(4)
            ->get();
        
        $course->related_courses = $relatedCourses;
        
        // Get the product that contains this course
        $productCourse = DB::table('product_courses')
            ->where('course_id', $course->id)
            ->first();
        
        if ($productCourse) {
            $product = Product::where('id', $productCourse->product_id)
                ->where('environment_id', $environment->id)
                ->first();
                
            if ($product) {
                $course->product_id = $product->id;
                $course->product_slug = $product->slug;
            }
        }
        
        return response()->json(['data' => $course]);
    }
    
    /**
     * Get a course by ID
     *
     * @param Request $request
     * @param string $environmentId
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCourseById(Request $request, string $environmentId, int $id)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $course = Course::where('environment_id', $environment->id)
            ->where('id', $id)
            ->with([
                'sections' => function($query) {
                    $query->orderBy('order');
                },
                'sections.items' => function($query) {
                    $query->orderBy('order');
                },
                'sections.items.activity'
            ])
            ->first();
        
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        
        // Get related courses
        $relatedCourses = Course::where('environment_id', $environment->id)
            ->where('id', '!=', $course->id)
            ->where('status', 'published')
            ->where(function($query) use ($course) {
                $query->where('difficulty_level', $course->difficulty_level)
                      ->orWhere('is_featured', true);
            })
            ->limit(4)
            ->get();
        
        $course->related_courses = $relatedCourses;
        
        return response()->json(['data' => $course]);
    }
    
    /**
     * Get products for an environment (maps to getAllProducts)
     * 
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProducts(Request $request, string $environmentId)
    {
        // This method maps to getAllProducts to maintain API compatibility
        return $this->getAllProducts($request, $environmentId);
    }
    
    /**
     * Get a product by ID or slug
     * 
     * @param Request $request
     * @param string $environmentId
     * @param string $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProduct(Request $request, string $environmentId, string $productId)
    {
        // Determine if the productId is numeric (ID) or a string (slug)
        if (is_numeric($productId)) {
            return $this->getProductById($request, $environmentId, (int)$productId);
        } else {
            return $this->getProductBySlug($request, $environmentId, $productId);
        }
    }
    
    /**
     * Get a category by ID
     * 
     * @param Request $request
     * @param string $environmentId
     * @param int $categoryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategory(Request $request, string $environmentId, int $categoryId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $category = ProductCategory::where('environment_id', $environment->id)
            ->where('id', $categoryId)
            ->first();
        
        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }
        
        // Get products in this category
        $products = Product::whereHas('categories', function($query) use ($categoryId) {
                $query->where('product_category_id', $categoryId);
            })
            ->where('environment_id', $environment->id)
            ->where('status', 'published')
            ->paginate(12);
        
        $category->products = $products;
        
        return response()->json(['data' => $category]);
    }
    

}
