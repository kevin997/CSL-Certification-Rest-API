@php
    // Get default environment branding (learning.csl-brands.com)
    $defaultEnvironment = \App\Models\Environment::where('primary_domain', 'learning.csl-brands.com')->first();
    $defaultBranding = $defaultEnvironment ? \App\Models\Branding::where('environment_id', $defaultEnvironment->id)->first() : null;
    $companyName = $defaultBranding && $defaultBranding->company_name ? $defaultBranding->company_name : 'CSL Brands Learning';
    $logoPath = $defaultBranding && $defaultBranding->logo_path ? $defaultBranding->logo_path : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Removed static meta refresh in favor of dynamic JavaScript redirect -->

    <title>Payment Failed - {{ $companyName }}</title>

    <!-- Favicon -->
    @if($logoPath)
    <link rel="icon" href="{{ asset($logoPath) }}" type="image/x-icon">
    @else
    <link rel="icon" href="https://cslbrandslearning.com/wp-content/uploads/2023/04/favicon.ico" type="image/x-icon">
    @endif

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4285F4',
                        secondary: '#34A853',
                        success: '#34A853',
                        warning: '#FBBC05',
                        danger: '#EA4335',
                    },
                    fontFamily: {
                        'google-sans': ['Google Sans', 'sans-serif'],
                        'roboto': ['Roboto', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #F8F9FA;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Google Sans', sans-serif;
        }

        .card {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>

<body class="antialiased bg-gray-50"
    @if($defaultBranding && $defaultBranding->primary_color) data-primary-color="{{ $defaultBranding->primary_color }}" @endif
    @if($defaultBranding && $defaultBranding->secondary_color) data-secondary-color="{{ $defaultBranding->secondary_color }}" @endif
    @if(isset($environment) && $environment->primary_domain) data-primary-domain="{{ $protocol }}://{{ $environment->primary_domain }}" @endif
    @if(isset($environment) && $environment->id) data-environment-id="{{ $environment->id }}" @endif
    @if(isset($transaction) && $transaction->transaction_id) data-transaction-id="{{ $transaction->transaction_id }}" @endif
    @if(isset($transaction) && $transaction->product_id) data-product-id="{{ $transaction->product_id }}" @endif
    @if(isset($transaction) && $transaction->gateway) data-gateway="{{ $transaction->gateway }}" @endif
    @if(isset($transaction) && $transaction->gateway_status) data-error-code="{{ $transaction->gateway_status }}" @endif
    @if(isset($transaction) && $transaction->order_id) data-order-id="{{ $transaction->order_id }}" @endif>
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-lg shadow-lg overflow-hidden card">
            <!-- Header with logo -->
            <div class="p-5 border-b border-gray-200 flex justify-center">
                @if($logoPath)
                <img src="{{ $logoPath }}" alt="{{ $companyName }} Logo" class="h-10">
                @else
                <img src="https://cslbrandslearning.com/wp-content/uploads/2023/04/CSL-Brands-Learning-Logo-1.png" alt="CSL Brands Learning Logo" class="h-10">
                @endif
            </div>

            <!-- Failed content -->
            <div class="p-8 text-center">
                <div class="flex justify-center mb-6">
                    <div class="rounded-full bg-danger/10 p-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>

                <h2 class="text-2xl font-semibold text-gray-800 mb-2">Payment Failed</h2>
                <p class="text-gray-600 mb-6">We're sorry, but your supported plan setup payment could not be processed. Please try again or contact our support team.</p>

                <!-- Transaction details -->
                @if(isset($transaction))
                <div class="bg-gray-50 rounded-md p-4 mb-6">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-500">Transaction ID:</span>
                        <span class="text-gray-800 font-medium">{{ $transaction->transaction_id }}</span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-500">Error:</span>
                        <span class="text-danger font-medium">{{ $transaction->gateway_status ?? 'Payment processing error' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Date:</span>
                        <span class="text-gray-800 font-medium">{{ $transaction->updated_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>
                @endif

                <!-- Loading indicator -->
                <div class="flex flex-col items-center">
                    <div class="relative">
                        <div class="w-12 h-12 rounded-full border-4 border-primary border-t-transparent animate-spin" id="loading-spinner"></div>
                    </div>
                    <p class="text-gray-500 mt-4">Redirecting you to retry payment...</p>
                </div>

                <!-- Retry button -->
                <div class="mt-6 flex justify-center space-x-4">
                    <a href="https://sales.csl-brands.com/supported-plan" class="inline-flex items-center px-4 py-2 bg-primary text-white font-medium rounded-md hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        Try Again
                    </a>
                    <a href="https://sales.csl-brands.com/contact" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-800 font-medium rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Contact Support
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
            <p class="mt-2">If you continue to experience issues, please <a href="https://sales.csl-brands.com/contact" class="text-primary hover:underline">contact support</a>.</p>
        </div>
    </div>

    <!-- Dynamic redirect script for supported plan -->
    <script>
        // Set timeout for redirect to CSL Sales Website
        setTimeout(function() {
            // Get transaction details from data attributes  
            var environmentId = document.body.getAttribute('data-environment-id') || 'default';
            var transactionId = document.body.getAttribute('data-transaction-id');
            var errorCode = document.body.getAttribute('data-error-code') || 'payment_failed';
            var gateway = document.body.getAttribute('data-gateway');

            // Build the redirect URL to retry payment flow
            var redirectUrl = 'https://sales.csl-brands.com/onboarding/supported-plan/payment-failed';

            // Add parameters for tracking and retry information
            var params = [];
            if (environmentId) params.push('environment=' + environmentId);
            if (transactionId) params.push('transaction=' + transactionId);
            if (errorCode) params.push('error=' + errorCode);
            if (gateway) params.push('gateway=' + gateway);
            params.push('status=failed');

            if (params.length > 0) {
                redirectUrl += '?' + params.join('&');
            }

            // Redirect to the CSL Sales Website
            window.location.href = redirectUrl;
        }, 1500); // 1.5 second delay before redirect
    </script>

    <!-- Apply branding colors dynamically -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply branding colors if available
            var primaryColor = '#4285F4';
            var secondaryColor = '#34A853';
            var dangerColor = '#EA4335';

            // Get branding colors from data attributes
            if (document.body.hasAttribute('data-primary-color')) {
                primaryColor = document.body.getAttribute('data-primary-color');
            }

            if (document.body.hasAttribute('data-secondary-color')) {
                secondaryColor = document.body.getAttribute('data-secondary-color');
            }

            // Apply primary color
            var primaryElements = document.querySelectorAll('.text-primary, .bg-primary, .border-primary, .ring-primary');
            primaryElements.forEach(function(element) {
                if (element.classList.contains('text-primary')) {
                    element.style.color = primaryColor;
                } else if (element.classList.contains('bg-primary')) {
                    element.style.backgroundColor = primaryColor;
                } else if (element.classList.contains('border-primary')) {
                    element.style.borderColor = primaryColor;
                } else if (element.classList.contains('ring-primary')) {
                    element.style.ringColor = primaryColor;
                }
            });

            // Apply primary color to the loading spinner
            var spinner = document.getElementById('loading-spinner');
            if (spinner) {
                spinner.style.borderColor = primaryColor;
                spinner.style.borderTopColor = 'transparent';
            }

            // Apply danger color to danger elements
            var dangerElements = document.querySelectorAll('.text-danger, .bg-danger\/10');
            dangerElements.forEach(function(element) {
                if (element.classList.contains('text-danger')) {
                    element.style.color = dangerColor;
                } else if (element.classList.contains('bg-danger\/10')) {
                    element.style.backgroundColor = dangerColor + '1A'; // 10% opacity
                }
            });

            // Apply hover states
            var primaryButtons = document.querySelectorAll('.bg-primary');
            primaryButtons.forEach(function(button) {
                button.addEventListener('mouseenter', function() {
                    this.style.opacity = '0.9';
                });
                button.addEventListener('mouseleave', function() {
                    this.style.opacity = '1';
                });
            });
        });
    </script>
</body>

</html>