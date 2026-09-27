<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\SocialAuthProvider;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Support\EnvironmentLearnerMembership;
use App\Support\SocialAuthException;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use App\Support\Tenancy\SwitchTokenIssuer;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider as SocialiteProviderContract;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class SocialAuthController extends Controller
{
    private const DRIVERS = [
        'google' => 'google',
        'facebook' => 'facebook',
        'linkedin' => 'linkedin-openid',
    ];

    private const STATE_ENVIRONMENT_PREFIX = 'social_auth.environment:';

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(isset(self::DRIVERS[$provider]), 404);

        $environment = $this->resolveEnvironment($request);
        $configuration = SocialAuthProvider::query()
            ->where('environment_id', $environment->id)
            ->where('provider', $provider)
            ->where('enabled', true)
            ->firstOrFail();
        $callbackUrl = route('social.auth.callback', ['provider' => $provider]);
        $response = $this->socialiteProvider($provider, $configuration, $callbackUrl)->redirect();
        parse_str((string) parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        abort_unless(is_string($state) && $state !== '', 500, 'Could not start social sign-in.');

        $request->session()->put(self::STATE_ENVIRONMENT_PREFIX.hash('sha256', $state), [
            'environment_id' => $environment->id,
            'provider' => $provider,
        ]);

        return $response;
    }

    public function callback(
        Request $request,
        string $provider,
        EnvironmentLearnerMembership $memberships,
        SwitchTokenIssuer $switchTokens,
    ): RedirectResponse {
        abort_unless(isset(self::DRIVERS[$provider]), 404);

        $state = $request->query('state');
        abort_unless(is_string($state) && $state !== '', 403, 'Sign-in failed. Please try again.');

        $pending = $request->session()->pull(self::STATE_ENVIRONMENT_PREFIX.hash('sha256', $state));
        abort_unless(
            is_array($pending) && ($pending['provider'] ?? null) === $provider,
            403,
            'Sign-in expired. Please try again.',
        );

        $environment = Environment::findActive((int) ($pending['environment_id'] ?? 0));
        abort_unless($environment instanceof Environment, 403, 'This academy is unavailable.');

        if (! $request->filled('error')) {
            $oauthState = $request->session()->get('state');
            abort_unless(is_string($oauthState) && hash_equals($oauthState, $state), 403, 'Sign-in failed. Please try again.');
        }

        if ($request->filled('error')) {
            $oauthState = $request->session()->pull('state');
            $request->session()->forget('code_verifier');
            abort_unless(is_string($oauthState) && hash_equals($oauthState, $state), 403, 'Sign-in failed. Please try again.');

            return $this->failureRedirect($environment, 'social_login_cancelled');
        }

        $configuration = SocialAuthProvider::query()
            ->where('environment_id', $environment->id)
            ->where('provider', $provider)
            ->where('enabled', true)
            ->first();

        if (! $configuration) {
            return $this->failureRedirect($environment, 'social_provider_disabled');
        }

        $callbackUrl = route('social.auth.callback', ['provider' => $provider]);

        try {
            $socialUser = $this->socialiteProvider($provider, $configuration, $callbackUrl)->user();

            $user = DB::transaction(function () use ($socialUser, $provider, $environment, $memberships): User {
                $user = $this->resolveUser($socialUser, $provider, $environment);
                $isMember = EnvironmentUser::query()
                    ->where('environment_id', $environment->id)
                    ->where('user_id', $user->id)
                    ->exists();

                if (! $isMember && ! $environment->allow_public_signup) {
                    throw new SocialAuthException('social_signup_disabled');
                }

                $memberships->join($user, $environment);

                return $user;
            });
        } catch (SocialAuthException $exception) {
            return $this->failureRedirect($environment, $exception->reason);
        } catch (InvalidStateException) {
            return $this->failureRedirect($environment, 'social_login_failed');
        } catch (Throwable $exception) {
            Log::warning('Social sign-in failed', [
                'provider' => $provider,
                'environment_id' => $environment->id,
                'exception_class' => $exception::class,
            ]);

            return $this->failureRedirect($environment, 'social_login_failed');
        }

        $token = $switchTokens->issue(
            $user,
            $environment,
            (int) config('tenancy.onboarding_switch_token_ttl_seconds', 60),
        );

        return redirect()->away($switchTokens->redirectUrl($environment, $token));
    }

    private function resolveEnvironment(Request $request): Environment
    {
        $resolver = app(EnvironmentResolver::class);
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $environment = $context instanceof EnvironmentContext ? $context->environment : null;

        if ($environment && $request->filled('environment_id')) {
            abort_unless((int) $request->query('environment_id') === (int) $environment->id, 404);
        }

        if (! $environment && $request->filled('environment_id')) {
            $environment = $resolver->explicitEnvironment($request);
        }

        abort_unless($environment instanceof Environment && $environment->is_active, 404);

        return $environment;
    }

    private function socialiteProvider(
        string $provider,
        SocialAuthProvider $configuration,
        string $callbackUrl,
    ): SocialiteProviderContract {
        $driver = self::DRIVERS[$provider];
        config([
            'services.'.$driver => [
                'client_id' => $configuration->client_id,
                'client_secret' => $configuration->client_secret,
                'redirect' => $callbackUrl,
            ],
        ]);

        $socialiteFactory = app(SocialiteFactory::class);
        if ($socialiteFactory instanceof SocialiteManager) {
            $socialiteFactory->forgetDrivers();
        }

        $socialiteProvider = Socialite::driver($driver);

        if ($provider !== 'facebook' && $socialiteProvider instanceof AbstractProvider) {
            return $socialiteProvider->enablePKCE();
        }

        return $socialiteProvider;
    }

    private function resolveUser(
        SocialiteUserContract $socialUser,
        string $provider,
        Environment $environment,
    ): User {
        $subject = trim((string) $socialUser->getId());
        $email = Str::lower(trim((string) $socialUser->getEmail()));
        $raw = $socialUser instanceof AbstractUser ? $socialUser->getRaw() : [];
        $emailVerified = filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($subject === '') {
            throw new SocialAuthException('social_identity_missing');
        }

        $identity = SocialIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $subject)
            ->first();

        if ($identity) {
            return $identity->user;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SocialAuthException('social_email_missing');
        }

        $user = User::query()->where('email', $email)->first();

        if ($user && ! $emailVerified) {
            throw new SocialAuthException('social_account_exists');
        }

        if (! $user) {
            if (! $environment->allow_public_signup) {
                throw new SocialAuthException('social_signup_disabled');
            }

            $user = User::create([
                'name' => $socialUser->getName() ?: $email,
                'email' => $email,
                'password' => Str::random(64),
                'role' => UserRole::LEARNER,
            ]);

            if ($emailVerified) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        }

        SocialIdentity::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $subject,
        ]);

        return $user;
    }

    private function failureRedirect(Environment $environment, string $reason): RedirectResponse
    {
        $allowedReasons = [
            'social_account_exists',
            'social_email_missing',
            'social_identity_missing',
            'social_login_cancelled',
            'social_login_failed',
            'social_provider_disabled',
            'social_signup_disabled',
        ];

        return redirect()->away(TenantUrl::to($environment, '/auth/login', [
            'social_error' => in_array($reason, $allowedReasons, true) ? $reason : 'social_login_failed',
        ]));
    }
}
