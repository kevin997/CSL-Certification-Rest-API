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
    <title>Payment Error - {{ $companyName }}</title>

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
                        primary: "#4285F4",
                        secondary: "#34A853",
                        danger: "#EA4335"
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

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Google Sans', sans-serif;
        }

        .card {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .btn-primary {
            background-color: #4285F4;
            color: white;
            padding: 8px 24px;
            border-radius: 4px;
            font-weight: 500;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background-color: #34A853;
            color: white;
            padding: 8px 24px;
            border-radius: 4px;
            font-weight: 500;
        }

        .btn-secondary:hover {
            opacity: 0.9;
        }

        .btn-outline {
            border: 1px solid #dadce0;
            padding: 8px 24px;
            border-radius: 4px;
            font-weight: 500;
        }

        .btn-outline:hover {
            background-color: #f1f3f4;
        }
    </style>
</head>

<body class="antialiased"
    @if($defaultBranding && $defaultBranding->primary_color) data-primary-color="{{ $defaultBranding->primary_color }}" @endif
    @if($defaultBranding && $defaultBranding->secondary_color) data-secondary-color="{{ $defaultBranding->secondary_color }}" @endif
    @if(isset($environment) && $environment->id) data-environment-id="{{ $environment->id }}" @endif
    @if(isset($transaction) && $transaction->transaction_id) data-transaction-id="{{ $transaction->transaction_id }}" @endif>
    <div class="min-h-screen flex flex-col justify-center items-center p-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md overflow-hidden card">
            <div class="p-6">
                <div class="flex justify-center mb-6">
                    @if($logoPath)
                    <img src="{{ $logoPath }}" alt="{{ $companyName }} Logo" class="h-12">
                    @else
                    <img src="https://cslbrandslearning.com/wp-content/uploads/2023/04/CSL-Brands-Learning-Logo-1.png" alt="CSL Brands Learning Logo" class="h-12">
                    @endif
                </div>

                <div class="text-center">
                    <!-- Error Icon -->
                    <div class="flex justify-center mb-6">
                        <div class="rounded-full bg-red-100 p-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>

                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Payment Processing Error</h1>
                    <p class="text-gray-600 mb-6">We encountered an unexpected error while processing your supported plan payment. Please try again or contact our support team.</p>

                    @if(isset($transaction))
                    <div class="bg-gray-100 rounded-lg p-4 mb-6">
                        <p class="text-sm text-gray-500 mt-2 mb-1">Transaction ID:</p>
                        <p class="font-medium">{{ $transaction->transaction_id }}</p>
                        @if($transaction->total_amount && $transaction->currency)
                        <p class="text-sm text-gray-500 mt-2 mb-1">Amount:</p>
                        <p class="font-medium">{{ $transaction->currency }} {{ number_format($transaction->total_amount, 2) }}</p>
                        @endif
                        <p class="text-sm text-gray-500 mt-2 mb-1">Date:</p>
                        <p class="font-medium">{{ $transaction->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    @endif

                    <div class="flex flex-col space-y-3">
                        <a href="https://sales.csl-brands.com/supported-plan" class="btn-primary inline-block text-center">
                            Try Payment Again
                        </a>
                        <a href="https://sales.csl-brands.com/contact" class="btn-outline inline-block text-center">
                            Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center text-sm text-gray-500">
            <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
            <p class="mt-2">For immediate assistance, email <a href="mailto:support@sales.csl-brands.com" class="text-primary hover:underline">support@sales.csl-brands.com</a></p>
        </div>
    </div>

    <!-- Redirect script for error handling -->
    <script>
        // Optional auto-redirect after showing error for a few seconds
        setTimeout(function() {
            // Get transaction details from data attributes
            var environmentId = document.body.getAttribute('data-environment-id');
            var transactionId = document.body.getAttribute('data-transaction-id');

            // Build the redirect URL to error handling page
            var redirectUrl = 'https://sales.csl-brands.com/onboarding/supported-plan/payment-error';

            // Add parameters for error tracking
            var params = [];
            if (environmentId) params.push('environment=' + environmentId);
            if (transactionId) params.push('transaction=' + transactionId);
            params.push('status=error');

            if (params.length > 0) {
                redirectUrl += '?' + params.join('&');
            }

            // Redirect to the CSL Sales Website error handling
            window.location.href = redirectUrl;
        }, 5000); // 5 second delay to allow user to read the error
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
            var primaryElements = document.querySelectorAll('.btn-primary, .text-primary');
            primaryElements.forEach(function(element) {
                if (element.classList.contains('btn-primary')) {
                    element.style.backgroundColor = primaryColor;
                } else if (element.classList.contains('text-primary')) {
                    element.style.color = primaryColor;
                }
            });
            
            // Apply secondary color
            var secondaryElements = document.querySelectorAll('.btn-secondary');
            secondaryElements.forEach(function(element) {
                element.style.backgroundColor = secondaryColor;
            });
        });
    </script>
</body>

</html>