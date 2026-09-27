<?php

namespace App\Support\Sso;

use App\Models\SsoProvider;
use OneLogin\Saml2\Auth;
use OneLogin\Saml2\AuthnRequest;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use RuntimeException;

/**
 * Thin SAML 2.0 service-provider wrapper around onelogin/php-saml.
 * SP-initiated flow only: we emit AuthnRequests and consume signed responses;
 * assertions must be IdP-signed (wantAssertionsSigned) since unsigned or
 * self-signed assertions would let anyone mint identities.
 */
final class SamlClient
{
    /**
     * @return array{url: string, request_id: string}
     */
    public function loginRedirect(SsoProvider $provider, string $relayState): array
    {
        $settings = new Settings($this->settingsArray($provider));
        $request = new AuthnRequest($settings);

        return [
            'url' => Utils::redirect($provider->idp_sso_url, [
                'SAMLRequest' => $request->getRequest(),
                'RelayState' => $relayState,
            ], true),
            'request_id' => $request->getId(),
        ];
    }

    /**
     * Validates the POSTed SAMLResponse and returns normalized identity claims.
     *
     * @return array{email: string|null, name: string|null}
     */
    public function claimsFromResponse(SsoProvider $provider, string $requestId): array
    {
        $auth = new Auth($this->settingsArray($provider));
        $auth->processResponse($requestId);

        if (! $auth->isAuthenticated() || $auth->getErrors()) {
            $reason = method_exists($auth, 'getLastErrorReason') ? (string) $auth->getLastErrorReason() : '';
            throw new RuntimeException('SAML response rejected: '.implode(', ', $auth->getErrors()).' '.$reason);
        }

        $attributes = $auth->getAttributes();
        $first = fn (string $key): ?string => $attributes[$key][0] ?? null;

        $nameId = $auth->getNameId();

        return [
            // Common attribute names across Entra ID, Okta, ADFS, Google.
            'email' => $first('email')
                ?? $first('mail')
                ?? $first('http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress')
                ?? (filter_var($nameId, FILTER_VALIDATE_EMAIL) ? $nameId : null),
            'name' => $first('name')
                ?? $first('displayName')
                ?? $first('http://schemas.microsoft.com/identity/claims/displayname')
                ?? $nameId,
        ];
    }

    /**
     * SP metadata XML the IdP imports to register this academy.
     */
    public function metadata(SsoProvider $provider): string
    {
        $settings = new Settings($this->settingsArray($provider), true);

        return $settings->getSPMetadata();
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsArray(SsoProvider $provider): array
    {
        $idp = [
            'entityId' => $provider->idp_entity_id,
            'singleSignOnService' => [
                'url' => $provider->idp_sso_url,
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'x509cert' => $provider->idp_x509_cert,
        ];

        if ($provider->idp_slo_url) {
            $idp['singleLogoutService'] = [
                'url' => $provider->idp_slo_url,
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ];
        }

        return [
            'sp' => [
                'entityId' => route('sso.metadata', ['provider' => $provider->id]),
                'assertionConsumerService' => [
                    'url' => route('sso.samlAcs', ['provider' => $provider->id]),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            ],
            'idp' => $idp,
            'strict' => true,
            'security' => [
                'wantAssertionsSigned' => true,
                'authnRequestsSigned' => false,
                'requestedAuthnContext' => false,
            ],
        ];
    }
}
