# Sales Forms Feature

Marketing module that lets Trainers build shareable, Google-Forms-style forms to
collect learner contacts and pre-enroll them into products.

## Flow

1. Trainer builds a form (Marketing → Sales Forms) with drag-and-drop fields, a cover
   image, an optional YouTube video, attached products, and a per-form set of
   blocks/activities that stay unlocked before payment.
2. Publishing returns a public link: `https://{environment.primary_domain}/forms/{slug}`.
3. A learner submits the public form. The backend, in one transaction:
   - finds-or-creates the `User` by email,
   - generates an access code and stores a `SalesFormSubmission`,
   - creates **provisional** `Enrollment`s for each course of each selected product,
   - creates one **pending** `Order` (+`OrderItem`) per product, each exposing a
     payment link (`Order::continue_payment_url`).
4. The learner can pay via the link, or the Trainer marks the order complete manually
   (offline payment). Completion records a `Transaction` + commission and lifts the
   provisional flag, unlocking full course access.

## Provisional access gating

While `enrollments.is_provisional` is true, `Learner/CourseController::show` and
`Learner/TemplateController::show` filter the template's blocks/activities down to the
rows in `sales_form_access_blocks` for that course + form. Both endpoints set
`is_provisional_access` so the study-room shows a "complete payment to unlock" banner.

## Key files

Backend:
- Migrations: `database/migrations/2026_05_31_0000{01..06}_*`
- Models: `SalesForm`, `SalesFormField`, `SalesFormAccessBlock`, `SalesFormSubmission`;
  `Order` (`TYPE_SALES_FORM`, `sales_form_submission_id`), `Enrollment`
  (`is_provisional`, `sales_form_id`)
- Controllers: `Api/Sales/SalesFormController` (builder/admin + analytics + CSV),
  `Api/Sales/SalesFormSubmissionController` (public render/submit + manual complete)
- Routes: authed in `routes/api.php` (`/sales-forms*`), public in `routes/api-public.php`
- Test: `tests/Feature/SalesFormWorkflowTest.php`

Frontend (CSL-Certification):
- API/service: `lib/sales-forms-api.ts`, `lib/services/sales-form-service.ts`
- Shared field renderer: `components/sales-forms/*`
- Pages: `app/marketing/forms/page.tsx` (list), `app/marketing/forms/[id]/page.tsx`
  (builder), `app/marketing/forms/[id]/analytics/page.tsx`, `app/forms/[slug]/page.tsx`
  (public)
- Marketing hub card: `app/marketing/page.tsx`
- Dependency added: `country-state-city` (offline geo data for country/state/city)
