<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Centralized Payment Gateway
    |--------------------------------------------------------------------------
    |
    | An environment that opts in to centralized gateways
    | (environment_payment_configs.use_centralized_gateways) does not use its
    | own PaymentGatewaySetting rows — it transacts through the gateways of a
    | single designated environment.
    |
    | That environment is identified by its primary domain, not by a literal
    | id, so moving it is a config change rather than a code change. The id
    | override exists for environments (tests, local) where the domain does
    | not resolve.
    |
    */

    'centralized' => [
        /*
         * Primary domain of the environment whose payment gateways every
         * centralized tenant transacts through.
         */
        'environment_domain' => env(
            'CENTRALIZED_PAYMENT_ENVIRONMENT_DOMAIN',
            'bootcamps.csl-brands.com'
        ),

        /*
         * Optional hard override. When set, it wins over the domain lookup.
         * Leave unset in production so the domain stays the single source of
         * truth.
         */
        'environment_id' => env('CENTRALIZED_PAYMENT_ENVIRONMENT_ID'),
    ],

];
