# Security Hardening & Best Practices

This document outlines the security architecture, potential threats, and implemented hardening measures for the CSL Certification platform. It is designed to guide developers and security engineers in maintaining a secure multi-tenant environment.

## 1. Threat Landscape & Hacking Techniques

Understanding how attackers operate is crucial for defense. The following techniques are commonly used to infiltrate multi-tenant SaaS applications:

### A. Tenant Isolation Bypass (The "Noisy Neighbor" Attack)
*   **Technique:** Attackers exploit weak logical separation to access data belonging to other tenants. This often involves manipulating IDs (IDOR) or exploiting global scopes that fail to filter by tenant.
*   **Vector:** API endpoints that accept resource IDs without validating ownership against the current tenant context.
*   **Mitigation:** Strict Global Scopes, Middleware-based Tenant Context, and explicit ownership validation.

### B. Identity & Access Management (IAM) Exploits
*   **Technique:** Credential Stuffing, Password Spraying, and Token Hijacking.
*   **Vector:** Weak password policies, lack of rate limiting on login endpoints, and insecure storage of authentication tokens (e.g., LocalStorage XSS).
*   **Mitigation:** HttpOnly Cookies, Rate Limiting, MFA (future), and robust password policies.

### C. Cross-Site Scripting (XSS) & Injection
*   **Technique:** Injecting malicious scripts into the application to steal session tokens or perform actions on behalf of the user.
*   **Vector:** Unsanitized user inputs (e.g., branding CSS/JS), reflected XSS in error messages, or stored XSS in profile fields.
*   **Mitigation:** Content Security Policy (CSP), Input Sanitization (BrandingMiddleware), and Output Encoding.

### D. Supply Chain & Dependency Attacks
*   **Technique:** Exploiting vulnerabilities in third-party packages (e.g., Laravel or npm dependencies).
*   **Vector:** Outdated libraries with known CVEs.
*   **Mitigation:** Regular dependency updates (`composer audit`, `npm audit`) and minimal use of external packages.

---

## 2. Implemented Security Measures

The following measures have been implemented or identified for implementation to counter the above threats.

### A. Multi-Tenancy & Data Isolation

**1. Middleware-Based Context Resolution**
*   **Mechanism:** `App\Http\Middleware\DetectEnvironment` resolves the tenant based on `X-Frontend-Domain`, `Origin`, or `Referer`.
*   **Security:** This ensures that every request is strictly scoped to a specific environment before any controller logic is executed.

**2. Global Scopes (Recommended)**
*   **Mechanism:** Apply a `TenantScope` to all tenant-specific models (`Order`, `Product`, `Customer`).
*   **Implementation:**
    ```php
    protected static function booted()
    {
        static::addGlobalScope('environment', function (Builder $builder) {
            $builder->where('environment_id', session('current_environment_id'));
        });
    }
    ```
*   **Defense:** Prevents IDOR attacks where an attacker guesses an ID from another tenant.

### B. Authentication & Session Management

**1. HttpOnly Cookies (Critical Upgrade)**
*   **Current State:** Tokens stored in `LocalStorage` (Vulnerable to XSS).
*   **Fix:** Configure Laravel Sanctum to use stateful, cookie-based authentication for first-party frontends.
*   **Defense:** Prevents attackers from stealing tokens via XSS. Even if a script runs, it cannot read the `HttpOnly` cookie.

**2. Tenant-Aware Rate Limiting**
*   **Mechanism:** Custom rate limiters in `RouteServiceProvider` that include the `environment_id` in the throttle key.
*   **Implementation:**
    ```php
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip() . $request->environment?->id);
    });
    ```
*   **Defense:** Mitigates Denial of Service (DoS) and brute-force attacks targeting specific tenants.

### C. Input Sanitization & Output Security

**1. Branding Sanitization**
*   **Mechanism:** `BrandingMiddleware` sanitizes custom CSS and colors to prevent injection attacks.
*   **Defense:** Prevents Stored XSS via malicious branding configurations.

**2. Security Headers (Recommended)**
*   **Mechanism:** Middleware to inject standard security headers.
*   **Headers:**
    *   `X-Content-Type-Options: nosniff`
    *   `X-Frame-Options: SAMEORIGIN`
    *   `Strict-Transport-Security: max-age=31536000; includeSubDomains`
    *   `Content-Security-Policy`: Restrict script sources.

---

## 3. Best Practices Checklist (2025 Edition)

### Architecture
- [ ] **Database Isolation:** Evaluate moving high-security tenants to dedicated schemas or databases.
- [ ] **Secret Management:** Rotate `APP_KEY` and `MEDIA_SERVICE_SECRET` regularly. Use a secrets manager (e.g., AWS Secrets Manager) in production.

### Monitoring & Logging
- [ ] **Audit Logs:** Implement `spatie/laravel-activitylog` to track *who* did *what* in *which* environment.
- [ ] **Anomaly Detection:** Monitor for spikes in 401/403 errors, which often indicate brute-force attempts.

### Development
- [ ] **Static Analysis:** Run `larastan` or `pint` in CI/CD to catch security issues early.
- [ ] **Dependency Audits:** Automate `composer audit` in the deployment pipeline.

---

## 4. Incident Response Plan (Brief)

1.  **Identify:** Confirm the breach and affected tenants.
2.  **Contain:** Rotate compromised keys, revoke active tokens (`php artisan sanctum:prune-expired --hours=0`), and enable maintenance mode for affected tenants.
3.  **Eradicate:** Patch the vulnerability.
4.  **Recover:** Restore data from backups if necessary.
5.  **Notify:** Inform affected tenants transparently.
