# 15. Security and Performance

## 15.1 Security Best Practices

**1. Authentication Security:**
- JWT tokens with 24-hour expiry
- Tokens stored in localStorage (not cookies to avoid CSRF)
- Token expiry checked on every request
- Multi-tab logout sync via storage events

**2. SQL Injection Prevention:**
- Eloquent ORM with parameter binding
- No raw SQL queries without parameter binding
- Input validation via Form Requests

**3. XSS Prevention:**
- React escapes all output by default
- Blade templates escape {{ }} output
- No innerHTML or dangerouslySetInnerHTML (except TipTap editor)

**4. CSRF Protection:**
- Laravel CSRF tokens for web routes
- API routes exempt (Sanctum token auth instead)

**5. Rate Limiting:**
```php
// routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});
```

**6. HTTPS Enforcement:**
- All production traffic over HTTPS
- HSTS header: `Strict-Transport-Security: max-age=31536000`
- Cloudflare SSL (Full mode)

**7. Environment Variable Security:**
- Never commit .env files
- Use Laravel secrets for sensitive config
- Rotate API keys quarterly

---

## 15.2 Performance Optimizations

**1. Database Optimization:**
- Indexes on foreign keys and frequently queried columns
- Query result caching (Redis)
- Eager loading to prevent N+1 queries
- Database connection pooling

**2. Application Caching:**
- Redis for session, cache, queue
- Config caching: `php artisan config:cache`
- Route caching: `php artisan route:cache`
- View caching: `php artisan view:cache`

**3. API Response Optimization:**
- API resources for consistent JSON formatting
- Pagination for large datasets (20-50 items per page)
- Gzip compression enabled in Nginx
- ETags for conditional requests

**4. Frontend Performance:**
- Next.js automatic code splitting
- Image optimization (next/image)
- Dynamic imports for heavy components
- Service workers for offline support (PWA)

**5. CDN & Asset Optimization:**
- Cloudflare CDN for static assets
- Asset versioning (cache busting)
- Minification (CSS, JS)
- Lazy loading images

---
