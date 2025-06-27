<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSL Brands Learning REST API</title>

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
                        danger: '#EA4335',
                        warning: '#FBBC05',
                    },
                    fontFamily: {
                        'google-sans': ['Google Sans', 'sans-serif'],
                        'roboto': ['Roboto', 'sans-serif'],
                    },
                },
            },
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
            border-radius: 8px;
            box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
        }

        .btn-primary {
            background-color: #4285F4;
            color: white;
            padding: 8px 24px;
            border-radius: 4px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn-primary:hover {
            background-color: #3367D6;
        }

        .btn-outline {
            border: 1px solid #dadce0;
            padding: 8px 24px;
            border-radius: 4px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn-outline:hover {
            background-color: #f1f3f4;
        }
    </style>
</head>

<body class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container mx-auto px-4 py-12">
        <header class="mb-12 text-center">
            <!-- CSL Logo Placeholder - Replace with actual logo when available -->
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-white text-2xl font-bold">
                    CSL
                </div>
            </div>
            <h1 class="text-4xl font-google-sans font-bold text-gray-900 dark:text-white mb-4">CSL Brands Learning REST API</h1>
            <p class="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">A powerful and flexible API for managing learning certifications and training programs.</p>
        </header>

        <main>
            <!-- Main Features Section -->
            <section class="mb-16">
                <div class="card bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md">
                    <h2 class="text-2xl font-google-sans font-bold text-gray-800 dark:text-white mb-6">API Features</h2>

                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <div class="p-4">
                            <div class="text-primary text-2xl mb-3">📊</div>
                            <h3 class="text-xl font-google-sans font-medium text-gray-800 dark:text-white mb-2">User Management</h3>
                            <p class="text-gray-600 dark:text-gray-300">Comprehensive user registration, authentication, and profile management.</p>
                        </div>

                        <div class="p-4">
                            <div class="text-primary text-2xl mb-3">🎓</div>
                            <h3 class="text-xl font-google-sans font-medium text-gray-800 dark:text-white mb-2">Certification Tracking</h3>
                            <p class="text-gray-600 dark:text-gray-300">Track and verify user certifications with detailed progress reporting.</p>
                        </div>

                        <div class="p-4">
                            <div class="text-primary text-2xl mb-3">💳</div>
                            <h3 class="text-xl font-google-sans font-medium text-gray-800 dark:text-white mb-2">Payment Integration</h3>
                            <p class="text-gray-600 dark:text-gray-300">Secure payment processing with multiple gateway options and subscription management.</p>
                        </div>

                        <div class="p-4">
                            <div class="text-primary text-2xl mb-3">📱</div>
                            <h3 class="text-xl font-google-sans font-medium text-gray-800 dark:text-white mb-2">Mobile Friendly</h3>
                            <p class="text-gray-600 dark:text-gray-300">Responsive endpoints optimized for both web and mobile applications.</p>
                        </div>

                        <div class="p-4">
                            <div class="text-primary text-2xl mb-3">🔒</div>
                            <h3 class="text-xl font-google-sans font-medium text-gray-800 dark:text-white mb-2">Secure Authentication</h3>
                            <p class="text-gray-600 dark:text-gray-300">OAuth2 and token-based authentication with role-based access control.</p>
                        </div>

                        <div class="p-4">
                            <div class="text-primary text-2xl mb-3">📈</div>
                            <h3 class="text-xl font-google-sans font-medium text-gray-800 dark:text-white mb-2">Analytics</h3>
                            <p class="text-gray-600 dark:text-gray-300">Detailed reporting and analytics on user engagement and certification progress.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Getting Started Section -->
            <section class="mb-16">
                <div class="card bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md">
                    <h2 class="text-2xl font-google-sans font-bold text-gray-800 dark:text-white mb-6">Getting Started</h2>

                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="bg-primary text-white rounded-full w-8 h-8 flex items-center justify-center font-bold mr-4 shrink-0">1</div>
                            <div>
                                <h3 class="text-xl font-google-sans font-medium text-gray-800 dark:text-white mb-2">Register for API Access</h3>
                                <p class="text-gray-600 dark:text-gray-300">Contact our sales team at <a href="mailto:sales@cfpcsl.com" class="text-primary hover:underline">sales@cfpcsl.com</a> to request API credentials.</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="bg-primary text-white rounded-full w-8 h-8 flex items-center justify-center font-bold mr-4 shrink-0">2</div>
                            <div>
                                <h3 class="text-xl font-google-sans font-medium text-gray-800 dark:text-white mb-2">Explore Documentation</h3>
                                <p class="text-gray-600 dark:text-gray-300">Review our comprehensive API documentation to understand available endpoints and features.</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="bg-primary text-white rounded-full w-8 h-8 flex items-center justify-center font-bold mr-4 shrink-0">3</div>
                            <div>
                                <h3 class="text-xl font-google-sans font-medium text-gray-800 dark:text-white mb-2">Integrate with Your Platform</h3>
                                <p class="text-gray-600 dark:text-gray-300">Use our client libraries or direct API calls to integrate CSL certification features into your application.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Support Section -->
            <section class="mb-16">
                <div class="card bg-white dark:bg-gray-800 p-8 rounded-lg shadow-md text-center">
                    <h2 class="text-2xl font-google-sans font-bold text-gray-800 dark:text-white mb-6">Need Integration Support?</h2>
                    <p class="text-gray-600 dark:text-gray-300 mb-8 max-w-2xl mx-auto">Our team is ready to help you implement and customize the CSL Brands Learning REST API for your specific needs.</p>

                    <div class="inline-block">
                        <a href="mailto:sales@cfpcsl.com" class="btn-primary inline-block hover:bg-blue-600 transition-colors duration-300">Contact Sales Team</a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="text-center text-gray-500 dark:text-gray-400 pt-8 border-t border-gray-200 dark:border-gray-700">
            <p>&copy; {{ date('Y') }} CSL Brands. All rights reserved.</p>
            <p class="mt-2">For API integration support: <a href="mailto:sales@cfpcsl.com" class="text-primary hover:underline">sales@cfpcsl.com</a></p>
        </footer>
    </div>
</body>
</html>