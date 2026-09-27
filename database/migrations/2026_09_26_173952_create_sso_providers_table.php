<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-environment identity providers for SSO sign-in (OIDC first; the
     * driver column leaves room for SAML later).
     */
    public function up(): void
    {
        Schema::create('sso_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('driver')->default('oidc');
            // OIDC relying-party config
            $table->string('issuer_url')->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            // SAML 2.0 IdP config
            $table->string('idp_entity_id')->nullable();
            $table->string('idp_sso_url')->nullable();
            $table->string('idp_slo_url')->nullable();
            $table->text('idp_x509_cert')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('auto_provision')->default(true);
            $table->string('default_role')->default('learner');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_providers');
    }
};
