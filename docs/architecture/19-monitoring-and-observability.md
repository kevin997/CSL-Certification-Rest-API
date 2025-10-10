# 19. Monitoring and Observability

## 19.1 Application Monitoring

**Tools:**
- **Sentry** - Error tracking (frontend + backend)
- **Laravel Telescope** - Request debugging (local only)
- **New Relic / DataDog** - APM (optional)

**Metrics Tracked:**
- API response times
- Database query performance
- Queue job success/failure rates
- Error rates by endpoint
- User authentication success rate

---

## 19.2 Logging

**Log Channels:**
- `daily` - Rotated daily logs (14-day retention)
- `slack` - Critical errors to Slack
- `sentry` - Errors to Sentry

**Log Rotation:**
```bash