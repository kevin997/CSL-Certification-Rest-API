<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Confirming your payment - {{ isset($branding) && $branding->company_name ? $branding->company_name : (isset($environment) ? $environment->name : 'CSL Brands Learning') }}</title>

    @if(isset($branding) && $branding->logo_path)
    <link rel="icon" href="{{ $branding->logo_path }}" type="image/x-icon">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #F8F9FA; }
        h1, h2, h3 { font-family: 'Google Sans', sans-serif; }
        .card { box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
    </style>
</head>

{{--
    KURSA licensing transition (Phase 3): browser callbacks are DISPLAY-ONLY.
    A pending transaction reaches this page because settlement is asynchronous
    (a signed webhook / server-to-server verification settles it). We NEVER infer
    success from arriving here — we POLL the public status endpoint and only then
    redirect. This fixes §9.11 "renders success when the status request fails".
--}}
<body class="antialiased bg-gray-50"
    @if(isset($environment) && $environment->primary_domain) data-primary-domain="{{ $protocol }}://{{ $environment->primary_domain }}" @endif
    @if(isset($environment) && $environment->id) data-environment-id="{{ $environment->id }}" @endif
    @if(isset($transaction) && $transaction->transaction_id) data-transaction-id="{{ $transaction->transaction_id }}" @endif
    @if(isset($transaction) && $transaction->order_id) data-order-id="{{ $transaction->order_id }}" @endif
    @if(isset($transaction)) data-gateway="{{ $transaction->payment_method }}" @endif>

    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-lg shadow-lg overflow-hidden card">
            <div class="p-5 border-b border-gray-200 flex justify-center">
                @if(isset($branding) && $branding->logo_path)
                <img src="{{ $branding->logo_path }}" alt="Logo" class="h-10">
                @endif
            </div>

            <div class="p-8 text-center">
                <div class="flex flex-col items-center mb-6">
                    <div class="w-12 h-12 rounded-full border-4 border-blue-500 border-t-transparent animate-spin" id="loading-spinner"></div>
                </div>

                <h2 class="text-2xl font-semibold text-gray-800 mb-2" id="status-title">Confirming your payment…</h2>
                <p class="text-gray-600 mb-6" id="status-message">
                    We’re verifying your payment with the provider. This can take a few moments — please keep this page open.
                </p>

                @if(isset($transaction))
                <div class="bg-gray-50 rounded-md p-4 mb-2 text-left">
                    <div class="flex justify-between mb-2">
                        <span class="text-gray-500">Transaction:</span>
                        <span class="text-gray-800 font-medium break-all">{{ $transaction->transaction_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Amount:</span>
                        <span class="text-gray-800 font-medium">{{ $transaction->currency }} {{ number_format($transaction->total_amount, 2) }}</span>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="mt-8 text-center text-sm text-gray-500">
            <p>&copy; {{ date('Y') }} {{ isset($environment) ? $environment->name : 'CSL Brands Learning' }}. All rights reserved.</p>
        </div>
    </div>

    <script>
        (function () {
            var body = document.body;
            var transactionId = body.getAttribute('data-transaction-id');
            var baseDomain = body.getAttribute('data-primary-domain') || '{{ config("app.frontend_url") }}';
            var environmentId = body.getAttribute('data-environment-id') || 'default';
            var orderId = body.getAttribute('data-order-id');
            var gateway = body.getAttribute('data-gateway');

            var statusUrl = '/api/payments/transactions/' + encodeURIComponent(transactionId) + '/status';
            var attempts = 0;
            var maxAttempts = 40; // ~2 min at 3s

            function successUrl() {
                var url = baseDomain + '/checkout/' + environmentId + '/success';
                if (orderId) { url += '?order=' + orderId; }
                if (gateway) { url += (orderId ? '&' : '?') + 'gateway=' + gateway; }
                return url;
            }

            function failureUrl() {
                return baseDomain + '/checkout/' + environmentId + '/failed';
            }

            function showTerminal(title, message) {
                var spinner = document.getElementById('loading-spinner');
                if (spinner) { spinner.style.display = 'none'; }
                document.getElementById('status-title').textContent = title;
                document.getElementById('status-message').textContent = message;
            }

            function poll() {
                attempts++;
                fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (!data) { return schedule(); }
                        var s = (data.status || '').toLowerCase();
                        if (s === 'completed') {
                            showTerminal('Payment confirmed!', 'Redirecting you to your dashboard…');
                            window.location.href = successUrl();
                            return;
                        }
                        if (s === 'failed' || s === 'cancelled') {
                            showTerminal('Payment not completed', 'Your payment did not go through. Redirecting…');
                            window.location.href = failureUrl();
                            return;
                        }
                        schedule();
                    })
                    .catch(function () { schedule(); });
            }

            function schedule() {
                if (attempts >= maxAttempts) {
                    showTerminal('Still processing', 'Your payment is taking longer than usual. You can safely close this page — your access updates automatically once the payment is confirmed.');
                    return;
                }
                setTimeout(poll, 3000);
            }

            poll();
        })();
    </script>
</body>

</html>
