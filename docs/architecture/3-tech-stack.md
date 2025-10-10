# 3. Tech Stack

## 3.1 Technology Stack Table

| Category | Technology | Version | Purpose | Rationale |
|----------|-----------|---------|---------|-----------|
| **Frontend Language** | TypeScript | 5.x | Type-safe JavaScript for frontend | Type safety prevents runtime errors, better IDE support, improves maintainability |
| **Frontend Framework** | Next.js | 15.x | React framework with SSR/SSG | Industry-leading React framework, excellent DX, built-in routing and API routes |
| **Frontend Library** | React | 19.x | UI component library | Latest React with concurrent rendering, stable and widely adopted |
| **UI Component Library** | Radix UI | 1.x | Headless accessible components | Unstyled, accessible, composable primitives for building custom UIs |
| **UI Styling** | TailwindCSS | 3.x | Utility-first CSS framework | Rapid UI development, consistent design system, highly customizable |
| **State Management** | React Context + SWR | 2.x | Client-side state and data fetching | SWR for server state (caching, revalidation), Context for app-wide state |
| **Backend Language** | PHP | 8.3 | Server-side programming language | Modern PHP with performance improvements, type safety, great for web APIs |
| **Backend Framework** | Laravel | 12.x | PHP web framework | Industry standard, comprehensive ecosystem, excellent ORM, built-in auth |
| **API Style** | REST | - | HTTP/JSON API architecture | RESTful endpoints, OpenAPI documentation, widely understood |
| **Primary Database** | MySQL | 8.0 | Relational database for certification data | Proven reliability, excellent for multi-tenant data, strong community support |
| **Secondary Database** | PostgreSQL | 15.x | Relational database for admin/sales data | Advanced features (JSON, full-text search), used by admin frontend |
| **ORM (Backend)** | Eloquent | (Laravel 12) | Laravel's database abstraction | Expressive syntax, relationships, query builder, migrations |
| **ORM (Admin Frontend)** | Prisma | 5.x | Type-safe database client for Node.js | Type-safe queries, excellent DX, automatic migrations for PostgreSQL |
| **Caching** | Redis | 7.x | In-memory data store | Fast caching, session storage, queue backend |
| **Message Queue** | RabbitMQ | 3.x | Message broker for async processing | Reliable message delivery, job queues, event-driven architecture |
| **Authentication (API)** | Laravel Sanctum | (Laravel 12) | JWT-based API authentication | Lightweight token-based auth, built into Laravel, perfect for SPAs |
| **Authentication (Admin)** | Clerk | Latest | Managed authentication service | Turnkey auth solution, OAuth providers, user management UI |
| **Payment Gateways** | Stripe, PayPal, MonetBil, Lygos | Various | Payment processing | Multi-gateway support for global and regional payments |
| **Rich Text Editor** | TipTap | 2.x | WYSIWYG editor for course content | Extensible, headless editor based on ProseMirror, excellent for educational content |
| **PDF Generation** | DomPDF | (Laravel) | Generate certificates and invoices | Server-side PDF generation, integrates well with Laravel |
| **File Storage** | Local/S3 | - | File uploads and storage | Local storage for development, S3-compatible for production |
| **Email Service** | SMTP/SendGrid | - | Transactional email delivery | Reliable email delivery for notifications, password resets, certificates |
| **Testing (Backend)** | PHPUnit | 10.x | Unit and integration testing | Laravel's default testing framework, comprehensive assertion library |
| **Testing (Frontend)** | Jest + React Testing Library | Latest | Component and unit testing | Industry standard for React testing, encourages accessibility-focused tests |
| **Code Quality (Backend)** | Laravel Pint | Latest | PHP code style fixer | Laravel's opinionated PHP linter, ensures consistent code style |
| **Code Quality (Frontend)** | ESLint + Prettier | Latest | JavaScript/TypeScript linting | Catches bugs, enforces code style, integrates with IDEs |
| **API Documentation** | Swagger/OpenAPI | 3.0 | API specification and docs | Standard API documentation, interactive testing, client generation |
| **Containerization** | Docker | 24.x | Application containerization | Consistent environments, easy deployment, Docker Compose orchestration |
| **Reverse Proxy** | Nginx | Latest | HTTP server and reverse proxy | High-performance, load balancing, SSL termination |
| **Version Control** | Git | - | Source code management | Distributed VCS, standard for collaboration, polyrepo structure |
| **CI/CD** | GitHub Actions / GitLab CI | - | Automated testing and deployment | Automated builds, tests, deployments on code push |
| **Monitoring** | Sentry | Latest | Error tracking and monitoring | Real-time error reporting, performance monitoring, alerting |
| **Logging** | Laravel Log | (Laravel 12) | Application logging | Built-in logging to files, Slack, email, Sentry |

---
