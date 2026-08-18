<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderCompleted;
use App\Events\OrderPlaced;
use App\Events\UserCreatedDuringCheckout;
use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\EnvironmentReferral;
use App\Models\EnvironmentUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGatewaySetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductReview;
use App\Models\User;
use App\Notifications\OrderCreated;
use App\Services\Commission\CommissionService;
use App\Services\EnvironmentPaymentConfigService;
use App\Services\OrderService;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\Payments\PaymentGatewayResolver;
use App\Services\PaymentService;
use App\Services\Tax\TaxZoneService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
// Response::HTTP_* is used at four sites below and was never imported: PHP
// resolved App\Http\Controllers\Api\Response, so the 422 branch threw an Error
// -- not an Exception, so the catch below missed it -- and the graceful refusal
// surfaced as a 500.
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StorefrontController extends Controller
{
    /**
     * The tax zone service instance.
     *
     * @var TaxZoneService
     */
    protected $taxZoneService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(TaxZoneService $taxZoneService)
    {
        $this->taxZoneService = $taxZoneService;
    }

    /**
     * Get the environment by ID
     *
     * @return Environment|null
     */
    protected function getEnvironmentById(string $environmentId)
    {
        if (is_numeric($environmentId)) {
            return Environment::find($environmentId);
        }

        $domain = strtolower(trim($environmentId));

        return Environment::whereRaw('LOWER(primary_domain) = ?', [$domain])
            ->orWhereRaw('LOWER(subdomain) = ?', [$domain])
            ->orWhere(function ($query) use ($domain) {
                $query->whereNotNull('additional_domains')
                    ->whereJsonContains('additional_domains', $domain);
            })
            ->first();
    }

    /**
     * Get the order by ID
     *
     * @return Order|null
     */
    protected function getOrderById(string $environmentId, string $orderId)
    {
        // Scope the lookup to the environment. Previously this was Order::find($orderId)
        // which ignored the environment entirely on a PUBLIC route — any caller could
        // fetch ANY order (and its billing PII) by guessing a numeric id (IDOR).
        return Order::where('id', $orderId)
            ->where('environment_id', $environmentId)
            ->first();
    }

    /**
     * Get the order by ID
     *
     * @return JsonResponse
     */
    public function getOrder(string $environmentId, string $orderId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        // Pass the RESOLVED environment id (the route param may be a domain), so the
        // order is scoped to the store it actually belongs to.
        $order = $this->getOrderById((string) $environment->id, $orderId);

        if (! $order) {
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
     * @return JsonResponse
     */
    public function getCountries(Request $request, string $environmentId)
    {
        // $environment = $this->getEnvironmentById($environmentId);

        // if (!$environment) {
        //     return response()->json(['message' => 'Environment not found'], 404);
        // }

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

            // EAC Countries (East African Community)
            ['code' => 'KE', 'name' => 'Kenya'],
            ['code' => 'TZ', 'name' => 'Tanzania'],
            ['code' => 'UG', 'name' => 'Uganda'],
            ['code' => 'RW', 'name' => 'Rwanda'],
            ['code' => 'BI', 'name' => 'Burundi'],
            ['code' => 'SS', 'name' => 'South Sudan'],

            // SADC Countries (Southern African Development Community)
            ['code' => 'BW', 'name' => 'Botswana'],
            ['code' => 'LS', 'name' => 'Lesotho'],
            ['code' => 'SZ', 'name' => 'Eswatini (Swaziland)'],
            ['code' => 'NA', 'name' => 'Namibia'],
            ['code' => 'ZW', 'name' => 'Zimbabwe'],
            ['code' => 'ZM', 'name' => 'Zambia'],
            ['code' => 'MW', 'name' => 'Malawi'],
            ['code' => 'MZ', 'name' => 'Mozambique'],
            ['code' => 'AO', 'name' => 'Angola'],
            ['code' => 'CD', 'name' => 'Democratic Republic of Congo'],
            ['code' => 'MG', 'name' => 'Madagascar'],
            ['code' => 'MU', 'name' => 'Mauritius'],
            ['code' => 'SC', 'name' => 'Seychelles'],

            // North African Countries
            ['code' => 'DZ', 'name' => 'Algeria'],
            ['code' => 'LY', 'name' => 'Libya'],
            ['code' => 'MA', 'name' => 'Morocco'],
            ['code' => 'SD', 'name' => 'Sudan'],
            ['code' => 'TN', 'name' => 'Tunisia'],

            // Other African Countries
            ['code' => 'ET', 'name' => 'Ethiopia'],
            ['code' => 'DJ', 'name' => 'Djibouti'],
            ['code' => 'ER', 'name' => 'Eritrea'],
            ['code' => 'SO', 'name' => 'Somalia'],
            ['code' => 'KM', 'name' => 'Comoros'],
            ['code' => 'ST', 'name' => 'São Tomé and Príncipe'],
        ];

        return response()->json([
            'success' => true,
            'data' => $this->sortByName($countries),
        ]);
    }

    /**
     * Sort country/state rows alphabetically by display name.
     *
     * These lists are hand-maintained literals grouped by region, and several
     * are ordered by ISO code rather than by the name shown to the user — so
     * "Lower Saxony" landed after "Mecklenburg-Vorpommern" (code NI vs MV).
     * Collator is used so accented names sort where a reader expects them:
     * "Kédougou" before "Kolda", not after every unaccented entry.
     *
     * @param  array<int, array{code: string, name: string}>  $rows
     * @return array<int, array{code: string, name: string}>
     */
    private function sortByName(array $rows): array
    {
        if (class_exists(\Collator::class)) {
            $collator = new \Collator(app()->getLocale());
            usort($rows, fn ($a, $b) => $collator->compare($a['name'], $b['name']));

            return array_values($rows);
        }

        // intl absent: fold accents to their ASCII base so the order stays
        // close to correct rather than pushing accented names to the end.
        $key = fn (string $name) => @iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
        usort($rows, fn ($a, $b) => strcasecmp($key($a['name']), $key($b['name'])));

        return array_values($rows);
    }

    /**
     * Get a list of states/provinces for a country
     *
     * @return JsonResponse
     */
    public function getStates(Request $request, string $environmentId, string $countryCode)
    {
        // $environment = $this->getEnvironmentById($environmentId);

        // if (!$environment) {
        //     return response()->json(['message' => 'Environment not found'], 404);
        // }

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
                ['code' => 'DC', 'name' => 'District of Columbia'],
            ];
        } elseif ($countryCode === 'CA') {
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
                ['code' => 'YT', 'name' => 'Yukon'],
            ];
        } elseif ($countryCode === 'GB') {
            $states = [
                ['code' => 'ENG', 'name' => 'England'],
                ['code' => 'SCT', 'name' => 'Scotland'],
                ['code' => 'WLS', 'name' => 'Wales'],
                ['code' => 'NIR', 'name' => 'Northern Ireland'],
            ];
        } elseif ($countryCode === 'AU') {
            $states = [
                ['code' => 'ACT', 'name' => 'Australian Capital Territory'],
                ['code' => 'NSW', 'name' => 'New South Wales'],
                ['code' => 'NT', 'name' => 'Northern Territory'],
                ['code' => 'QLD', 'name' => 'Queensland'],
                ['code' => 'SA', 'name' => 'South Australia'],
                ['code' => 'TAS', 'name' => 'Tasmania'],
                ['code' => 'VIC', 'name' => 'Victoria'],
                ['code' => 'WA', 'name' => 'Western Australia'],
            ];
        } elseif ($countryCode === 'DE') {
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
                ['code' => 'TH', 'name' => 'Thuringia'],
            ];
        } elseif ($countryCode === 'IN') {
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
                ['code' => 'WB', 'name' => 'West Bengal'],
            ];
        } elseif ($countryCode === 'MX') {
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
                ['code' => 'ZAC', 'name' => 'Zacatecas'],
            ];
        }
        // CEMAC Countries
        // Cameroon
        elseif ($countryCode === 'CM') {
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
                ['code' => 'OU', 'name' => 'West'],
            ];
        }
        // Central African Republic
        elseif ($countryCode === 'CF') {
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
                ['code' => 'VK', 'name' => 'Vakaga'],
            ];
        }
        // Chad
        elseif ($countryCode === 'TD') {
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
                ['code' => 'WF', 'name' => 'Wadi Fira'],
            ];
        }
        // Republic of Congo
        elseif ($countryCode === 'CG') {
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
                ['code' => 'SAN', 'name' => 'Sangha'],
            ];
        }
        // Equatorial Guinea
        elseif ($countryCode === 'GQ') {
            $states = [
                ['code' => 'AN', 'name' => 'Annobón'],
                ['code' => 'BN', 'name' => 'Bioko Norte'],
                ['code' => 'BS', 'name' => 'Bioko Sur'],
                ['code' => 'CS', 'name' => 'Centro Sur'],
                ['code' => 'KN', 'name' => 'Kié-Ntem'],
                ['code' => 'LI', 'name' => 'Litoral'],
                ['code' => 'WN', 'name' => 'Wele-Nzas'],
            ];
        }
        // Gabon
        elseif ($countryCode === 'GA') {
            $states = [
                ['code' => 'ES', 'name' => 'Estuaire'],
                ['code' => 'HO', 'name' => 'Haut-Ogooué'],
                ['code' => 'MO', 'name' => 'Moyen-Ogooué'],
                ['code' => 'NG', 'name' => 'Ngounié'],
                ['code' => 'NY', 'name' => 'Nyanga'],
                ['code' => 'OI', 'name' => 'Ogooué-Ivindo'],
                ['code' => 'OL', 'name' => 'Ogooué-Lolo'],
                ['code' => 'OM', 'name' => 'Ogooué-Maritime'],
                ['code' => 'WN', 'name' => 'Woleu-Ntem'],
            ];
        }
        // ECOWAS Countries
        // Nigeria
        elseif ($countryCode === 'NG') {
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
                ['code' => 'ZA', 'name' => 'Zamfara'],
            ];
        }
        // Ghana
        elseif ($countryCode === 'GH') {
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
                ['code' => 'WN', 'name' => 'Western North'],
            ];
        }
        // Senegal
        elseif ($countryCode === 'SN') {
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
                ['code' => 'ZG', 'name' => 'Ziguinchor'],
            ];
        }
        // Ivory Coast
        elseif ($countryCode === 'CI') {
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
                ['code' => 'ZZ', 'name' => 'Zanzan'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $this->sortByName($states),
        ]);
    }

    /**
     * Get a list of cities for a state/province
     *
     * @return JsonResponse
     */
    public function getCities(Request $request, string $environmentId, string $countryCode, string $stateCode)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
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
                    ['name' => 'Fontana'],
                ];
            }
            // New York
            elseif ($stateCode === 'NY') {
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
                    ['name' => 'Saratoga Springs'],
                ];
            }
            // Texas
            elseif ($stateCode === 'TX') {
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
                    ['name' => 'Grand Prairie'],
                ];
            }
            // Florida
            elseif ($stateCode === 'FL') {
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
                    ['name' => 'Coral Springs'],
                ];
            }
        }
        // Canada Cities
        elseif ($countryCode === 'CA') {
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
                    ['name' => 'Windsor'],
                ];
            }
            // British Columbia
            elseif ($stateCode === 'BC') {
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
                    ['name' => 'Delta'],
                ];
            }
            // Quebec
            elseif ($stateCode === 'QC') {
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
                    ['name' => 'Terrebonne'],
                ];
            }
        }
        // UK Cities
        elseif ($countryCode === 'GB') {
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
                    ['name' => 'Portsmouth'],
                ];
            }
            // Scotland
            elseif ($stateCode === 'SCT') {
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
                    ['name' => 'Falkirk'],
                ];
            }
            // Wales
            elseif ($stateCode === 'WLS') {
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
                    ['name' => 'Carmarthen'],
                ];
            }
        }
        // Australian Cities
        elseif ($countryCode === 'AU') {
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
                    ['name' => 'Orange'],
                ];
            }
            // Victoria
            elseif ($stateCode === 'VIC') {
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
                    ['name' => 'Traralgon'],
                ];
            }
            // Queensland
            elseif ($stateCode === 'QLD') {
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
                    ['name' => 'Hervey Bay'],
                ];
            }
        }
        // CEMAC Countries Cities
        // Cameroon
        elseif ($countryCode === 'CM') {
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
                    ['name' => 'Nkoteng'],
                ];
            }
            // Littoral Region
            elseif ($stateCode === 'LT') {
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
                    ['name' => 'Njombé'],
                ];
            }
            // North-West Region
            elseif ($stateCode === 'NW') {
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
                    ['name' => 'Jakiri'],
                ];
            }
        }
        // Republic of Congo
        elseif ($countryCode === 'CG') {
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
                    ['name' => 'Ngabé'],
                ];
            }
            // Pointe-Noire
            elseif ($stateCode === 'PNR') {
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
                    ['name' => 'Vindoulou'],
                ];
            }
        }
        // Gabon
        elseif ($countryCode === 'GA') {
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
                    ['name' => 'Ikoy-Tsini'],
                ];
            }
            // Haut-Ogooué
            elseif ($stateCode === 'HO') {
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
                    ['name' => 'Boumango'],
                ];
            }
        }
        // ECOWAS Countries Cities
        // Nigeria
        elseif ($countryCode === 'NG') {
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
                    ['name' => 'Victoria Island'],
                ];
            }
            // Federal Capital Territory
            elseif ($stateCode === 'FC') {
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
                    ['name' => 'Lugbe'],
                ];
            }
            // Rivers
            elseif ($stateCode === 'RI') {
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
                    ['name' => 'Tai'],
                ];
            }
        }
        // Ghana
        elseif ($countryCode === 'GH') {
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
                    ['name' => 'Jamestown'],
                ];
            }
            // Ashanti
            elseif ($stateCode === 'AH') {
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
                    ['name' => 'Nkawie'],
                ];
            }
        }
        // Senegal
        elseif ($countryCode === 'SN') {
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
                    ['name' => 'Malika'],
                ];
            }
            // Saint-Louis
            elseif ($stateCode === 'SL') {
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
                    ['name' => 'Galoya'],
                ];
            }
        }
        // Ivory Coast
        elseif ($countryCode === 'CI') {
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
                    ['name' => 'Grand-Bassam'],
                ];
            }
            // Yamoussoukro
            elseif ($stateCode === 'YM') {
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
                    ['name' => 'Zatta'],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $cities,
        ]);
    }

    /**
     * Get featured products for an environment
     *
     * @return JsonResponse
     */
    public function getFeaturedProducts(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
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
     * @return JsonResponse
     */
    public function getAllProducts(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $query = Product::where('environment_id', $environment->id)
            ->where('status', 'active')
            ->with('category');

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
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

        $learnersCount = Enrollment::where('environment_id', $environment->id)
            ->whereIn('status', [Enrollment::STATUS_ENROLLED, Enrollment::STATUS_IN_PROGRESS])
            ->count();

        $salesCount = Order::where('environment_id', $environment->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->count();

        $reviewsCount = ProductReview::where('environment_id', $environment->id)
            ->where('status', 'approved')
            ->count();

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
            'environment' => [
                'id' => $environment->id,
                'name' => $environment->name,
                'primary_domain' => $environment->primary_domain,
            ],
            'metrics' => [
                'learners_count' => $learnersCount,
                'sales_count' => $salesCount,
                'reviews_count' => $reviewsCount,
            ],
        ]);
    }

    /**
     * Get a product by slug
     *
     * @return JsonResponse
     */
    public function getProductBySlug(Request $request, string $environmentId, string $slug)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $product = Product::where('environment_id', $environment->id)
            ->where('slug', $slug)
            ->with(['category', 'courses.template.blocks.activities'])
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Get related products
        $relatedProducts = Product::where('environment_id', $environment->id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->where(function ($query) use ($product) {
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
     * @return JsonResponse
     */
    public function getProductById(Request $request, string $environmentId, int $id)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $product = Product::where('environment_id', $environment->id)
            ->where('id', $id)
            ->with(['category', 'courses.template.blocks.activities'])
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Get related products
        $relatedProducts = Product::where('environment_id', $environment->id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->where(function ($query) use ($product) {
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
     * @return JsonResponse
     */
    public function getCategories(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $categories = ProductCategory::where('environment_id', $environment->id)
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * Get available payment methods
     *
     * @return JsonResponse
     */
    public function getPaymentMethods(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        // A centralized environment transacts through the designated centralized
        // environment's gateways rather than its own.
        $targetEnvironmentId = app(EnvironmentPaymentConfigService::class)
            ->getEffectiveEnvironmentId($environment->id);

        // withoutGlobalScopes() bypasses EnvironmentScope, which would otherwise
        // restrict the query to the requesting environment and return nothing.
        // The enabled flag on this table is `status`; there is no `is_active`
        // column, so the previous filter made this endpoint throw.
        // There is no paymentGateway relation on this model — eager-loading it
        // threw RelationNotFoundException, so this endpoint has never returned
        // a response. The settings row carries these fields itself.
        $paymentMethods = PaymentGatewaySetting::withoutGlobalScopes()
            ->where('environment_id', $targetEnvironmentId)
            ->where('status', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (PaymentGatewaySetting $method) {
                return [
                    'id' => $method->id,
                    'name' => $method->display_name ?: $method->gateway_name,
                    'type' => $method->code,
                    'logo' => $method->icon,
                    'description' => $method->description,
                ];
            });

        return response()->json(['data' => $paymentMethods]);
    }

    /**
     * Get payment gateways
     *
     * @return JsonResponse
     */
    public function getPaymentGateways(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        // A centralized environment transacts through the designated centralized
        // environment's gateways rather than its own.
        $paymentConfigService = app(EnvironmentPaymentConfigService::class);
        $targetEnvironmentId = $paymentConfigService->getEffectiveEnvironmentId($environment->id);

        if ($targetEnvironmentId !== $environment->id) {
            \Log::info('Using centralized payment gateways', [
                'requesting_environment' => $environment->id,
                'target_environment' => $targetEnvironmentId,
            ]);
        } else {
            \Log::info('Using local payment gateways', [
                'environment' => $environment->id,
                'use_centralized' => $paymentConfigService->isCentralized($environment->id),
            ]);
        }

        // create an array of gateways we don't fetch
        $excludeGateways = ['lygos'];

        // Get active payment gateways for this environment
        // Use withoutGlobalScopes() to bypass EnvironmentScope when fetching centralized gateways
        $gateways = PaymentGatewaySetting::withoutGlobalScopes()
            ->where('environment_id', $targetEnvironmentId)
            ->where('status', true)
            ->whereNotIn('code', $excludeGateways)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PaymentGatewaySetting $gateway) => $this->publicGatewayPayload($gateway))
            ->values();

        return response()->json(['data' => $gateways]);
    }

    /**
     * The subset of a gateway safe to hand an unauthenticated storefront visitor.
     *
     * This route has no auth middleware, and it previously serialized the whole
     * model — which put the `settings` blob (Stripe api_key and webhook_secret,
     * MonetBill service_secret, TaraMoney api_key) in a public response.
     *
     * Whitelisted rather than blacklisted on purpose: `publishable_key` sits in
     * the same blob as `api_key`, so a "remove the secret names" rule would leak
     * every key a future gateway introduces. Nothing here needs the browser to
     * see `settings` — checkout receives Stripe's publishable key in the
     * create-order response, next to the client secret.
     *
     * @return array<string, mixed>
     */
    private function publicGatewayPayload(PaymentGatewaySetting $gateway): array
    {
        return [
            'id' => $gateway->id,
            'environment_id' => $gateway->environment_id,
            'gateway_name' => $gateway->gateway_name,
            'code' => $gateway->code,
            'display_name' => $gateway->display_name,
            'description' => $gateway->description,
            'status' => $gateway->status,
            'is_default' => $gateway->is_default,
            'icon' => $gateway->icon,
            'mode' => $gateway->mode,
            'transaction_fee_percentage' => $gateway->transaction_fee_percentage,
            'transaction_fee_fixed' => $gateway->transaction_fee_fixed,
            'sort_order' => $gateway->sort_order,
        ];
    }

    /**
     * Process checkout
     *
     * @return JsonResponse
     */
    public function checkout(Request $request, string $environmentId)
    {
        return $this->checkoutWithExplicitPaymentState($request, $environmentId);

    }

    private function checkoutWithExplicitPaymentState(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'nullable|string|max:20',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'nullable',
            'billing_address' => 'required|string|max:255',
            'billing_city' => 'required|string|max:255',
            'billing_state' => 'required|string|max:255',
            'billing_zip' => 'nullable|string|max:20',
            'billing_country' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $authenticatedUser = $request->user('sanctum');
        if ($authenticatedUser) {
            foreach ($request->input('products') as $productData) {
                $product = Product::find($productData['id']);
                if ($product && $product->created_by === $authenticatedUser->id) {
                    return response()->json(['message' => 'Instructors cannot purchase their own courses.'], 403);
                }
            }
        }

        try {
            DB::beginTransaction();

            $userExists = User::where('email', $request->input('email'))->exists();
            $user = User::firstOrCreate(
                ['email' => $request->input('email')],
                [
                    'name' => $request->input('name'),
                    'password' => bcrypt(Str::random(16)),
                ]
            );

            event(new UserCreatedDuringCheckout($user, $environment, ! $userExists));

            $totalAmount = 0;
            $currency = null;
            $orderItems = [];

            foreach ($request->input('products') as $item) {
                $product = Product::where('id', $item['id'])
                    ->where('environment_id', $environment->id)
                    ->firstOrFail();

                $price = $product->discount_price ?? $product->price;
                $quantity = (int) $item['quantity'];
                $lineTotal = $price * $quantity;
                $currency = $currency ?? $product->currency;

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $lineTotal,
                ];

                $totalAmount += $lineTotal;
            }

            $referral = null;
            if ($request->filled('referral_code')) {
                $referral = EnvironmentReferral::where('code', $request->input('referral_code'))
                    ->where('environment_id', $environment->id)
                    ->where('is_active', true)
                    ->first();

                if (
                    $referral
                    && (! $referral->expiration_date || now()->isBefore($referral->expiration_date))
                    && ($referral->max_uses <= 0 || $referral->uses_count < $referral->max_uses)
                ) {
                    $discount = $referral->discount_type === 'fixed'
                        ? min($totalAmount, (float) $referral->discount_value)
                        : $totalAmount * (((float) $referral->discount_value) / 100);
                    $totalAmount = max(0, $totalAmount - $discount);
                } else {
                    $referral = null;
                }
            }

            $isFree = $totalAmount <= 0;
            $gatewayCode = null;
            $paymentMethod = $request->input('payment_method');

            if (! $isFree) {
                if (! $paymentMethod || ! is_numeric($paymentMethod)) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'A valid payment method is required for paid checkout',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                // Resolved against the effective environment: a centralized
                // tenant's gateway belongs to the provider, and the bare find()
                // here was additionally filtered by EnvironmentScope to the
                // tenant's own session environment -- so it never matched the id
                // this same controller had just listed to the browser.
                $gatewaySettings = app(PaymentGatewayResolver::class)
                    ->forId($paymentMethod, $environment->id);
                if (! $gatewaySettings || ! $gatewaySettings->status) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Selected payment method is not available',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $gatewayCode = $gatewaySettings->code;
            }

            // Idempotency: reuse a very recent identical pending order instead of
            // creating a duplicate. Checkout has no idempotency key, so a double
            // submit / retry within a short window would otherwise create duplicate
            // pending orders for the same user + products + amount.
            $productKey = collect($orderItems)
                ->map(fn ($i) => $i['product']->id.'x'.$i['quantity'])
                ->sort()
                ->values()
                ->implode(',');

            $existingOrder = Order::where('user_id', $user->id)
                ->where('environment_id', $environment->id)
                ->where('status', 'pending')
                ->where('total_amount', $totalAmount)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->with('items')
                ->latest()
                ->get()
                ->first(function ($o) use ($productKey) {
                    $key = $o->items
                        ->map(fn ($i) => $i->product_id.'x'.$i->quantity)
                        ->sort()
                        ->values()
                        ->implode(',');

                    return $key === $productKey;
                });

            if ($existingOrder) {
                $order = $existingOrder;
            } else {
                $order = new Order;
                $order->user_id = $user->id;
                $order->environment_id = $environment->id;
                $order->order_number = 'ORD-'.strtoupper(Str::random(8));
                $order->status = 'pending';
                $order->payment_method = $isFree ? 'free' : $paymentMethod;
                $order->billing_name = $request->input('name');
                $order->billing_email = $request->input('email');
                $order->phone_number = $request->input('phone_number');
                $order->billing_address = $request->input('billing_address');
                $order->billing_city = $request->input('billing_city');
                $order->billing_state = $request->input('billing_state');
                $order->billing_zip = $request->input('billing_zip') ?? '00000';
                $order->billing_country = $request->input('billing_country');
                $order->notes = $request->input('notes');
                $order->referral_id = $referral?->id;
                $order->total_amount = $totalAmount;
                $order->currency = $currency;
                $order->save();

                foreach ($orderItems as $item) {
                    $orderItem = new OrderItem;
                    $orderItem->order_id = $order->id;
                    $orderItem->product_id = $item['product']->id;
                    $orderItem->quantity = $item['quantity'];
                    $orderItem->price = $item['price'];
                    $orderItem->total = $item['total'];
                    $orderItem->save();
                }
            }

            DB::commit();

            // Only fire creation side-effects for a genuinely new order — a reused
            // (deduped) order already sent these when it was first created.
            if (! $existingOrder) {
                try {
                    $order->load(['user']);
                    if ($order->user) {
                        $order->user->notify(new OrderCreated($order, app(TelegramService::class)));
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send OrderCreated notification: '.$e->getMessage());
                }

                // Marketing automation trigger (pending-payment storefront checkout).
                // The instant-complete path fires OrderCompleted → payment_confirmed
                // automation instead, so it is intentionally not wired here.
                event(new OrderPlaced($order));
            }

            $responseData = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'status' => 'pending',
            ];

            if ($isFree) {
                event(new OrderCompleted($order));

                return response()->json([
                    'success' => true,
                    'message' => 'Order completed successfully',
                    'data' => array_merge($responseData, [
                        'status' => 'completed',
                        'payment_type' => 'free',
                    ]),
                ]);
            }

            $orderService = app()->make(OrderService::class);
            $gatewayFactory = app()->make(PaymentGatewayFactory::class);
            $commissionService = app()->make(CommissionService::class);
            $taxZoneService = app()->make(TaxZoneService::class);
            $environmentPaymentConfigService = app()->make(EnvironmentPaymentConfigService::class);

            $paymentService = new PaymentService($orderService, $gatewayFactory, $commissionService, $taxZoneService, $environmentPaymentConfigService);
            $paymentResult = $paymentService->createPayment($order->id, $gatewayCode, [], $environment->name);

            if (! ($paymentResult['success'] ?? false)) {
                Log::error('Payment creation failed', [
                    'order_id' => $order->id,
                    'gateway' => $gatewayCode,
                    'message' => $paymentResult['message'] ?? 'Unknown error',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'] ?? 'Payment creation failed',
                    'data' => $responseData,
                ], Response::HTTP_BAD_GATEWAY);
            }

            $transaction = $paymentResult['transaction'] ?? null;
            if ($transaction) {
                $responseData['total_amount'] = $transaction->total_amount;
                $responseData['currency'] = $transaction->currency;
                $responseData['transaction'] = $transaction;
            }

            switch ($paymentResult['type'] ?? null) {
                case 'client_secret':
                    $responseData['payment_type'] = 'stripe';
                    $responseData['client_secret'] = $paymentResult['value'];
                    $responseData['publishable_key'] = $paymentResult['publishable_key'] ?? null;
                    break;

                case 'checkout_url':
                    $responseData['payment_type'] = 'paypal';
                    $responseData['redirect_url'] = $paymentResult['value'];
                    break;

                case 'payment_url':
                    $responseData['payment_type'] = $gatewayCode;
                    $responseData['redirect_url'] = $paymentResult['value'];
                    break;

                case 'redirect_url':
                    $responseData['payment_type'] = $gatewayCode === 'taramoney' ? 'taramoney' : $gatewayCode;
                    $responseData['redirect_url'] = $paymentResult['redirect_url'] ?? $paymentResult['general_link'] ?? $paymentResult['value'] ?? null;
                    $responseData['general_link'] = $paymentResult['general_link'] ?? null;
                    break;

                case 'payment_links':
                    $responseData['payment_type'] = 'taramoney';
                    if (! empty($paymentResult['general_link'])) {
                        $responseData['redirect_url'] = $paymentResult['general_link'];
                        $responseData['general_link'] = $paymentResult['general_link'];
                    } else {
                        $responseData['payment_links'] = $paymentResult['payment_links'] ?? [];
                        $responseData['whatsapp_link'] = $paymentResult['whatsapp_link'] ?? null;
                        $responseData['telegram_link'] = $paymentResult['telegram_link'] ?? null;
                        $responseData['dikalo_link'] = $paymentResult['dikalo_link'] ?? null;
                        $responseData['sms_link'] = $paymentResult['sms_link'] ?? null;
                    }
                    break;

                default:
                    Log::error('Unsupported payment response type', [
                        'order_id' => $order->id,
                        'gateway' => $gatewayCode,
                        'payment_result' => $paymentResult,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Unsupported payment response from gateway',
                        'data' => $responseData,
                    ], Response::HTTP_BAD_GATEWAY);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to process checkout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get product reviews
     *
     * @return JsonResponse
     */
    public function getProductReviews(Request $request, string $environmentId, int $productId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $product = Product::where('environment_id', $environment->id)
            ->where('id', $productId)
            ->first();

        if (! $product) {
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
            'total_reviews' => $reviews->count(),
        ]);
    }

    /**
     * Get all product reviews for a store/environment
     *
     * @return JsonResponse
     */
    public function getStoreReviews(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $limit = $request->get('limit', 10);

        // Get approved reviews for any product in this environment
        $reviewsQuery = ProductReview::where('environment_id', $environment->id)
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc');

        // Optional: limit the number of reviews returned
        if ($limit > 0) {
            $reviews = $reviewsQuery->take($limit)->get();
        } else {
            $reviews = $reviewsQuery->get();
        }

        // Get average rating across all products in the store
        $averageRating = ProductReview::where('environment_id', $environment->id)
            ->where('status', 'approved')
            ->avg('rating') ?: 0;

        $totalReviews = ProductReview::where('environment_id', $environment->id)
            ->where('status', 'approved')
            ->count();

        // Load product details (id, name, slug) for each review to display context
        $reviews->load(['product:id,name,slug']);

        return response()->json([
            'data' => $reviews,
            'average_rating' => round($averageRating, 1),
            'total_reviews' => $totalReviews,
        ]);
    }

    /**
     * Submit a product review
     *
     * @return JsonResponse
     */
    public function submitProductReview(Request $request, string $environmentId, int $productId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $product = Product::where('environment_id', $environment->id)
            ->where('id', $productId)
            ->first();

        if (! $product) {
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
        $review = new ProductReview;
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
     * @return JsonResponse
     */
    public function getCourses(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
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
     * @return JsonResponse
     */
    public function getCourseBySlug(Request $request, string $environmentId, string $slug)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $course = Course::where('environment_id', $environment->id)
            ->where('slug', $slug)
            ->with([
                'sections' => function ($query) {
                    $query->orderBy('order');
                },
                'sections.items' => function ($query) {
                    $query->orderBy('order');
                },
                'sections.items.activity',
            ])
            ->first();

        if (! $course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        // Get related courses
        $relatedCourses = Course::where('environment_id', $environment->id)
            ->where('id', '!=', $course->id)
            ->where('status', 'published')
            ->where(function ($query) use ($course) {
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
     * @return JsonResponse
     */
    public function getCourseById(Request $request, string $environmentId, int $id)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $course = Course::where('environment_id', $environment->id)
            ->where('id', $id)
            ->with([
                'sections' => function ($query) {
                    $query->orderBy('order');
                },
                'sections.items' => function ($query) {
                    $query->orderBy('order');
                },
                'sections.items.activity',
            ])
            ->first();

        if (! $course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        // Get related courses
        $relatedCourses = Course::where('environment_id', $environment->id)
            ->where('id', '!=', $course->id)
            ->where('status', 'published')
            ->where(function ($query) use ($course) {
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
     * @return JsonResponse
     */
    public function getProducts(Request $request, string $environmentId)
    {
        // This method maps to getAllProducts to maintain API compatibility
        return $this->getAllProducts($request, $environmentId);
    }

    /**
     * Get a product by ID or slug
     *
     * @return JsonResponse
     */
    public function getProduct(Request $request, string $environmentId, string $productId)
    {
        // Determine if the productId is numeric (ID) or a string (slug)
        if (is_numeric($productId)) {
            return $this->getProductById($request, $environmentId, (int) $productId);
        } else {
            return $this->getProductBySlug($request, $environmentId, $productId);
        }
    }

    /**
     * Get a category by ID
     *
     * @return JsonResponse
     */
    public function getCategory(Request $request, string $environmentId, int $categoryId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $category = ProductCategory::where('environment_id', $environment->id)
            ->where('id', $categoryId)
            ->first();

        if (! $category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Get products in this category
        $products = Product::whereHas('categories', function ($query) use ($categoryId) {
            $query->where('product_category_id', $categoryId);
        })
            ->where('environment_id', $environment->id)
            ->where('status', 'published')
            ->paginate(12);

        $category->products = $products;

        return response()->json(['data' => $category]);
    }

    /**
     * Continue payment for a pending order
     *
     * @param  int  $orderId
     * @return JsonResponse
     */
    public function continuePayment(Request $request, $orderId)
    {
        // Get the authenticated user
        $user = $request->user();

        // Fetch the order by ID
        $order = Order::find($orderId);

        // Check if order exists
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // Validate that the order belongs to the current user
        if ($user->id !== $order->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this order',
            ], 403);
        }

        // Check that order status is pending
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can continue payment',
                'order_status' => $order->status,
            ], 400);
        }

        // Validate the payment method data
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string',
            'payment_data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Load order items and related products
        $order->load(['items.product', 'user']);

        // Process the payment based on the payment method
        $paymentMethod = $request->input('payment_method');
        $paymentData = $request->input('payment_data', []);

        try {
            // Get the payment gateway settings
            // order->environment_id stays the TENANT; the resolver converts it to
            // the environment that actually owns the gateway. The previous query
            // filtered on the tenant directly, which owns none.
            //
            // gateway_name is no longer matched: the client sends gateway.code
            // (continue-payment-client.tsx), never the display name.
            $resolver = app(PaymentGatewayResolver::class);
            $paymentGatewaySetting = is_numeric($paymentMethod)
                ? $resolver->forId($paymentMethod, $order->environment_id)
                : $resolver->forCode((string) $paymentMethod, $order->environment_id);

            if (! $paymentGatewaySetting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not available',
                ], 400);
            }

            // Create OrderService instance
            $orderService = app()->make(OrderService::class);

            // Create PaymentGatewayFactory instance
            $gatewayFactory = app()->make(PaymentGatewayFactory::class);

            // Create CommissionService instance
            $commissionService = app()->make(CommissionService::class);

            $taxZoneService = app()->make(TaxZoneService::class);
            $environmentPaymentConfigService = app()->make(EnvironmentPaymentConfigService::class);

            // Initialize payment service with proper dependencies
            $paymentService = new PaymentService($orderService, $gatewayFactory, $commissionService, $taxZoneService, $environmentPaymentConfigService);

            // Process the payment - pass order ID as expected by the method
            $result = $paymentService->processPayment($order->id, [
                'payment_method' => $paymentGatewaySetting->code, // Use the gateway code from settings
                'environment_id' => $order->environment_id,
                ...$paymentData,
            ]);

            // Update the order with payment method
            $order->payment_method = $paymentGatewaySetting->id;
            $order->save();

            $responseData = [];
            $responseData['user'] = $user;
            $responseData['transaction'] = $result['transaction'] ?? null;
            $responseData['order'] = $order;

            // processPayment → processGatewayPayment returns a flat structure keyed by
            // field name (client_secret, checkout_url, redirect_url, payment_links, etc.)
            // with payment_type set to the gateway code — not a 'type' key.
            $paymentType = $result['payment_type'] ?? $paymentGatewaySetting->code;
            $responseData['payment_type'] = $paymentType;

            switch ($paymentType) {
                case 'stripe':
                    $responseData['client_secret'] = $result['client_secret'] ?? null;
                    $responseData['publishable_key'] = $result['publishable_key'] ?? null;
                    break;

                case 'paypal':
                    $responseData['redirect_url'] = $result['checkout_url'] ?? $result['redirect_url'] ?? null;
                    break;

                case 'taramoney':
                    if (! empty($result['general_link'])) {
                        $responseData['redirect_url'] = $result['general_link'];
                        $responseData['general_link'] = $result['general_link'];
                    } else {
                        $responseData['payment_links'] = $result['payment_links'] ?? [];
                        $responseData['whatsapp_link'] = $result['whatsapp_link'] ?? null;
                        $responseData['telegram_link'] = $result['telegram_link'] ?? null;
                        $responseData['dikalo_link'] = $result['dikalo_link'] ?? null;
                        $responseData['sms_link'] = $result['sms_link'] ?? null;
                    }
                    break;

                default:
                    // Generic gateway — surface whatever redirect/url the gateway returned.
                    $responseData['redirect_url'] = $result['redirect_url']
                        ?? $result['checkout_url']
                        ?? $result['payment_url']
                        ?? null;
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment processing initiated',
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            Log::error('Payment continuation error: '.$e->getMessage(), [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tax rate for a specific location
     *
     * @return JsonResponse
     */
    public function getTaxRateForLocation(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'country_code' => 'required|string|size:2',
            'state_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $countryCode = $request->input('country_code');
        $stateCode = $request->input('state_code');

        try {
            // Get tax rate from TaxZoneService
            $taxZone = $this->taxZoneService->findTaxZone($countryCode, $stateCode);
            $taxRate = $taxZone ? $taxZone->tax_rate : 0;

            // Get commission rate from Commission model
            $commission = Commission::getActiveCommission($environmentId);
            $commissionRate = $commission ? ($commission->rate / 100) : 0; // Phase 2: 0% platform commission (no 17% fallback)

            return response()->json([
                'success' => true,
                'data' => [
                    'tax_rate' => $taxRate,
                    'commission_rate' => $commissionRate,
                    'tax_zone' => $taxZone ? [
                        'id' => $taxZone->id,
                        'name' => $taxZone->name,
                        'country_code' => $taxZone->country_code,
                        'state_code' => $taxZone->state_code,
                        'rate' => $taxZone->tax_rate,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching tax rate: '.$e->getMessage(), [
                'environment_id' => $environmentId,
                'country_code' => $countryCode,
                'state_code' => $stateCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tax rate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate product price with commission included (for product creation)
     *
     * @return JsonResponse
     */
    public function calculateProductPriceWithCommission(Request $request, ?string $environmentId = null)
    {
        $environmentId = $environmentId
            ?? session('current_environment_id')
            ?? $request->input('environment_id');
        $environment = $this->getEnvironmentById($environmentId);

        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'base_price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $basePrice = (float) $request->input('base_price');
        $discountPrice = $request->input('discount_price') ? (float) $request->input('discount_price') : null;

        try {
            // Get commission rate from Commission model
            $commission = Commission::getActiveCommission($environmentId);
            $commissionRate = $commission ? ($commission->rate / 100) : 0; // Phase 2: 0% platform commission (no 17% fallback)

            // Commission is INCLUDED in the entered price (deducted from seller earnings at sale time).
            // The stored product price equals what the customer pays — nothing is added on top.
            $baseCommission = round($basePrice * $commissionRate, 2);
            $sellerPayout = round($basePrice - $baseCommission, 2);

            $discountCommission = null;
            $discountSellerPayout = null;
            if ($discountPrice !== null) {
                $discountCommission = round($discountPrice * $commissionRate, 2);
                $discountSellerPayout = round($discountPrice - $discountCommission, 2);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'original_base_price' => $basePrice,
                    'commission_rate' => $commissionRate,
                    'base_commission' => $baseCommission,
                    'final_base_price' => $basePrice,
                    'seller_payout' => $sellerPayout,
                    'original_discount_price' => $discountPrice,
                    'discount_commission' => $discountCommission,
                    'final_discount_price' => $discountPrice,
                    'discount_seller_payout' => $discountSellerPayout,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculating product price with commission: '.$e->getMessage(), [
                'environment_id' => $environmentId,
                'base_price' => $basePrice,
                'discount_price' => $discountPrice,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate product price with commission',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enroll user in free course
     *
     * @return JsonResponse
     */
    public function enrollFree(Request $request, string $environmentId)
    {
        // Validate environment
        $environment = $this->getEnvironmentById($environmentId);
        if (! $environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'password' => 'nullable|string|min:8',
            'phone_number' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get authenticated user or create new one
        $user = auth('sanctum')->user();

        // Check if authenticated user is the instructor/owner of the product
        if ($user) {
            $product = Product::find($request->product_id);
            if ($product && $product->created_by === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instructors cannot enroll in their own courses.',
                ], 403);
            }
        }
        $token = null;

        if (! $user) {
            // Check if registration data is provided
            if ($request->has(['name', 'email', 'password'])) {
                // Check if user exists
                $existingUser = User::where('email', $request->email)->first();

                if ($existingUser) {
                    // Check if user is part of the environment
                    $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                        ->where('user_id', $existingUser->id)
                        ->first();

                    if (! $environmentUser) {
                        // Add user to environment
                        event(new UserCreatedDuringCheckout(
                            $existingUser,
                            $environment,
                            false // isNewUser = false
                        ));

                        // Use this user for enrollment
                        $user = $existingUser;

                        // Create token for the existing user so they are logged in
                        $token = $user->createToken('auth_token')->plainTextToken;
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'User already exists. Please login to continue.',
                            'error_code' => 'USER_EXISTS',
                        ], 400);
                    }
                } else {
                    // Create NEW user
                    try {
                        DB::beginTransaction();

                        // Create user
                        $user = User::create([
                            'name' => $request->name,
                            'email' => $request->email,
                            'role' => 'learner',
                            'password' => Hash::make($request->password),
                            'whatsapp_number' => $request->phone_number,
                        ]);

                        // Create environment membership logic (delegated to listener)
                        event(new UserCreatedDuringCheckout($user, $environment, true));

                        // Create token for the new user
                        $token = $user->createToken('auth_token')->plainTextToken;

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to create user account',
                            'error' => $e->getMessage(),
                        ], 500);
                    }
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required or provide registration details',
                ], 401);
            }
        } else {
            // Ensure existing user is added to the environment if not already a member
            event(new UserCreatedDuringCheckout($user, $environment, false));
        }

        // Get and validate product
        $product = Product::where('id', $request->product_id)
            ->where('environment_id', $environmentId)
            ->first();

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        if (! $product->is_free) {
            return response()->json([
                'success' => false,
                'message' => 'This course requires payment',
                'error_code' => 'COURSE_NOT_FREE',
            ], 400);
        }

        // Create a free order
        try {
            DB::beginTransaction();

            $order = Order::create([
                'user_id' => $user->id,
                'environment_id' => $environmentId,
                'order_number' => 'ORD-'.strtoupper(Str::random(10)),
                'status' => Order::STATUS_COMPLETED,
                'total_amount' => 0,
                'currency' => $product->currency ?? 'USD',
                'payment_method' => 'free',
                'payment_id' => 'free_'.Str::random(10),
                'billing_name' => $user->name,
                'billing_email' => $user->email,
            ]);

            // Create order item
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 0,
                'total' => 0,
            ]);

            DB::commit();

            // Dispatch OrderCreated notification
            try {
                $order->load(['user']);
                if ($order->user) {
                    $order->user->notify(new OrderCreated($order, app(TelegramService::class)));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send OrderCreated notification: '.$e->getMessage());
            }

            // Dispatch event to handle enrollments and digital delivery
            event(new OrderCompleted($order));

            return response()->json([
                'success' => true,
                'message' => 'Successfully enrolled in course',
                'token' => $token, // Return token for auto-login
                'user' => $user,
                'data' => [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'status' => 'completed',
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to process enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
