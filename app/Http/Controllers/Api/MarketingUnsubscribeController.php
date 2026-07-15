<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;

/**
 * One-click marketing unsubscribe link, reached via a signed URL emailed
 * with every marketing campaign. The `signed` route middleware rejects
 * tampered/expired links with Laravel's own 403 before this ever runs.
 */
class MarketingUnsubscribeController extends Controller
{
    public function __invoke(int $user): Response
    {
        $user = User::findOrFail($user);

        $user->forceFill(['marketing_opt_in' => false])->save();

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KURSA</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color:#f4f4f7; margin:0; padding:40px 20px; text-align:center;">
    <p style="font-size:16px; color:#333;">Vous êtes désinscrit(e) des emails marketing KURSA.</p>
    <p style="font-size:16px; color:#333;">You've been unsubscribed from KURSA marketing emails.</p>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }
}
