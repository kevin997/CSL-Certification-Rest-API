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

    <title>Payment Successful - {{ $companyName }}</title>

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
    @if(isset($transaction) && $transaction->gateway_status) data-gateway-status="{{ $transaction->gateway_status }}" @endif
    @if(isset($transaction) && $transaction->order_id) data-order-id="{{ $transaction->order_id }}" @endif
    @if(isset($transaction) && $transaction->total_amount) data-amount="{{ $transaction->total_amount }}" @endif
    @if(isset($transaction) && $transaction->currency) data-currency="{{ $transaction->currency }}" @endif>
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

            <!-- Success content -->
            <div class="p-8 text-center">
                <div class="flex justify-center mb-6">
                    <div class="rounded-full bg-success/10 p-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>

                <h2 class="text-2xl font-semibold text-gray-800 mb-2">Payment Successful!</h2>
                <p class="text-gray-600 mb-6">Thank you for choosing our Supported Plan. Your payment has been processed successfully.</p>

                <!-- Transaction details -->
                @if(isset($transaction))
                <div class="bg-gray-50 rounded-md p-4 mb-6">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-500">Transaction ID:</span>
                        <span class="text-gray-800 font-medium">{{ $transaction->transaction_id }}</span>
                    </div>
                    @if($transaction->total_amount)
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-500">Amount:</span>
                        <span class="text-gray-800 font-medium">{{ $transaction->currency }} {{ number_format($transaction->total_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-500">Date:</span>
                        <span class="text-gray-800 font-medium">{{ $transaction->created_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>
                @endif

                <!-- Loading indicator -->
                <div class="flex flex-col items-center">
                    <div class="relative">
                        <div class="w-12 h-12 rounded-full border-4 border-primary border-t-transparent animate-spin" id="loading-spinner"></div>
                    </div>
                    <p class="text-gray-500 mt-4">Setting up your environment and redirecting you...</p>
                </div>

                <!-- Additional info -->
                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <strong>What's next?</strong><br>
                        Our team will contact you within 24 hours to begin setting up your learning environment. You'll receive an email confirmation shortly.
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
        </div>
    </div>

    <!-- Dynamic redirect script for supported plan completion -->
    <script>
        // Set timeout for redirect to CSL Sales Website SupportedCompletion
        setTimeout(function() {
            // Get transaction details from data attributes
            var environmentId = document.body.getAttribute('data-environment-id');
            var transactionId = document.body.getAttribute('data-transaction-id');
            var orderId = document.body.getAttribute('data-order-id');
            var gateway = document.body.getAttribute('data-gateway');
            var gatewayStatus = document.body.getAttribute('data-gateway-status');
            var amount = document.body.getAttribute('data-amount');
            var currency = document.body.getAttribute('data-currency');

            // Build the redirect URL to SupportedCompletion component
            var redirectUrl = 'https://sales.csl-brands.com/onboarding/supported-plan/completion';

            // Add parameters for passing data to the completion page
            var params = [];
            if (environmentId) params.push('environment=' + environmentId);
            if (transactionId) params.push('transaction=' + transactionId);
            if (orderId) params.push('order=' + orderId);
            if (gateway) params.push('gateway=' + gateway);
            if (gatewayStatus) params.push('gateway_status=' + gatewayStatus);
            if (amount) params.push('amount=' + amount);
            if (currency) params.push('currency=' + currency);
            params.push('status=success');

            if (params.length > 0) {
                redirectUrl += '?' + params.join('&');
            }

            // Redirect to the CSL Sales Website SupportedCompletion component
            window.location.href = redirectUrl;
        }, 2000); // 2 second delay before redirect to allow user to see success message
    </script>

    <!-- Apply branding colors dynamically -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply branding colors if available
            var primaryColor = '#4285F4';
            var secondaryColor = '#34A853';

            // Get branding colors from data attributes
            if (document.body.hasAttribute('data-primary-color')) {
                primaryColor = document.body.getAttribute('data-primary-color');
            }

            if (document.body.hasAttribute('data-secondary-color')) {
                secondaryColor = document.body.getAttribute('data-secondary-color');
            }

            // Apply primary color
            var primaryElements = document.querySelectorAll('.text-primary, .border-primary');
            primaryElements.forEach(function(element) {
                if (element.classList.contains('text-primary')) {
                    element.style.color = primaryColor;
                } else if (element.classList.contains('border-primary')) {
                    element.style.borderColor = primaryColor;
                }
            });

            // Apply primary color to the loading spinner
            var spinner = document.getElementById('loading-spinner');
            if (spinner) {
                spinner.style.borderColor = primaryColor;
                spinner.style.borderTopColor = 'transparent';
            }

            // Apply secondary color to success elements
            var successElements = document.querySelectorAll('.text-success, .bg-success\/10');
            successElements.forEach(function(element) {
                if (element.classList.contains('text-success')) {
                    element.style.color = secondaryColor;
                } else if (element.classList.contains('bg-success\/10')) {
                    element.style.backgroundColor = secondaryColor + '1A'; // 10% opacity
                }
            });
        });
    </script>
</body>

</html>