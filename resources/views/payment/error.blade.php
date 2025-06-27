<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Error - {{ isset($branding) && $branding->company_name ? $branding->company_name : (isset($environment) ? $environment->name : 'CSL Brands Learning') }}</title>

    <!-- Favicon -->
    @if(isset($branding) && $branding->logo_path)
    <link rel="icon" href="{{ asset($branding->logo_path) }}" type="image/x-icon">
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
                        secondary: "#34A853"
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
    @if(isset($branding) && $branding->primary_color) data-primary-color="{{ $branding->primary_color }}" @endif
    @if(isset($branding) && $branding->secondary_color) data-secondary-color="{{ $branding->secondary_color }}" @endif>
    <div class="min-h-screen flex flex-col justify-center items-center p-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md overflow-hidden card">
            <div class="p-6">
                <div class="flex justify-center mb-6">
                    @if(isset($environment) && $environment->logo_url)
                    <img src="{{ $environment->logo_url }}" alt="{{ isset($environment) ? $environment->name : 'CSL Brands Learning' }} Logo" class="h-12">
                    @else
                    <img src="https://cslbrandslearning.com/wp-content/uploads/2023/04/CSL-Brands-Learning-Logo-1.png" alt="CSL Brands Learning Logo" class="h-12">
                    @endif
                </div>

                <div class="text-center">
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Payment Error</h1>
                    <p class="text-gray-600 mb-6">We encountered an error processing your payment.</p>

                    @if(isset($transaction))
                    <div class="bg-gray-100 rounded-lg p-4 mb-6">
                        <p class="text-sm text-gray-500 mt-2 mb-1">Transaction ID:</p>
                        <p class="font-medium">{{ $transaction->transaction_id }}</p>
                        <p class="text-sm text-gray-500 mt-2 mb-1">Amount:</p>
                        <p class="font-medium">{{ $transaction->currency }} {{ number_format($transaction->total_amount, 2) }}</p>
                        <p class="text-sm text-gray-500 mt-2 mb-1">Date:</p>
                        <p class="font-medium">{{ $transaction->created_at->format('M d, Y H:i') }}</p>
                    </div>
                    @endif

                    <div class="flex flex-col space-y-3">
                        <a href="{{ $protocol }}://{{ $environment->primary_domain }}/auth/login" class="btn-primary inline-block text-center">
                            Return to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center text-sm text-gray-500">
            <p>&copy; {{ date('Y') }} {{ isset($environment) && $environment->name ? $environment->name : (isset($environment) ? $environment->name : 'CSL Brands Learning') }}. All rights reserved.</p>
            <!-- <p class="mt-2">For support, contact <a href="mailto:sales@cslbrands.com" class="text-primary hover:underline">sales@cslbrands.com</a></p> -->
        </div>
    </div>

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