# 12. Unified Project Structure

## 12.1 Polyrepo Architecture

The CSL platform uses a **polyrepo** (multiple repositories) approach rather than monorepo:

**Repositories:**
1. **CSL-Certification-Rest-API** - Laravel 12 backend
2. **CSL-Certification** - Next.js 15 frontend (instructor/learner)
3. **CSL-Sales-Website** - Next.js 15 frontend (admin)
4. **CSL-Serverless-Functions** - Laravel serverless functions
5. **CSL-DevOps** - Docker Compose, infrastructure configs

**Benefits:**
- Independent deployment cycles
- Separate version control
- Smaller repository sizes
- Team autonomy (backend/frontend teams)

**Challenges:**
- Cross-repo synchronization (API contracts)
- Shared dependencies management
- Integration testing complexity

---

## 12.2 Common File Patterns

**Configuration Files:**
```
.env                      # Environment variables (NOT committed)
.env.example              # Example environment file (committed)
.gitignore                # Git ignore patterns
.editorconfig             # Editor configuration
composer.json / package.json  # Dependencies
phpunit.xml / jest.config.js  # Test configuration
docker-compose.yml        # Docker orchestration
```

**Environment Variables:**
```bash