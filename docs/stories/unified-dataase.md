Agile Epic: Identity Unification (Single Database)
Status: In Progress Priority: Critical / Blocker (Required for Mobile App & Marketplace) Target Stack: Laravel 12 / MySQL

## Implementation Status
- [x] Story 1: Database Normalization & Linkage - `php artisan identity:unify`
- [x] Story 2: Smart Login Controller - Auto-heal implemented in `TokenController`
- [x] Story 3: Join Environment API - `POST /api/environments/{id}/join`
- [ ] Story 4: Cleanup & Hardening (Scheduled for later sprint)

Executive Summary
Currently, our system operates with Siloed Identity. Users have separate authentication credentials (passwords) stored in the environment_users table for every environment they join. This prevents a unified mobile experience and blocks cross-environment features (like a unified shopping cart).

The Goal: Transition to a Federated Identity model within our single database. The users table will become the Single Source of Truth for authentication (Login), while the environment_users table will be demoted to handle only Authorization (Membership & Roles).

Success Metrics:

100% of environment_users records are linked to a parent users record via Foreign Key.

Users can log in to the application using their most recently used password.

Users can "join" a new environment without re-entering their email or creating a new password.

Story 1: Database Normalization & Linkage
As a Lead Developer, I want to populate a foreign key user_id in the environment_users table, So that every environment-specific profile is strictly linked to a single global User account.

Technical Tasks:

Create a migration to add user_id (nullable, indexed) to environment_users.

Write a "One-Time Script" (Artisan Command) to:

Loop through environment_users.

Match the email to the users table.

Update environment_users.user_id with users.id.

Handle Orphans: If an email exists in environment_users but not in users:

Auto-create the User record.

Copy the password hash/name from the environment record.

Link the IDs.

Acceptance Criteria:

[ ] Migration runs successfully without data loss.

[ ] A SQL query SELECT * FROM environment_users WHERE user_id IS NULL returns 0 rows.

[ ] Every user profile is now logically connected to a master account.

Story 2: The "Smart Login" Controller (Migration Logic)
As a System Architect, I want the login logic to check the global user table first, but fallback to the legacy environment table if needed, So that users can log in seamlessly without being forced to reset their passwords manually.

Technical Tasks:

Refactor AuthController@login.

Logic Step A: Attempt login against users table. If success -> Return Token.

Logic Step B (Fallback): If Step A fails, check environment_users for the given email + password combination.

Logic Step C (Auto-Heal): If Step B succeeds:

Update the users table password to match the password they just provided.

Log them in.

(Next time, Step A will succeed).

Acceptance Criteria:

[ ] User logs in with current global password -> Success.

[ ] User logs in with an old password specific to one environment -> Success, and global password is silently updated.

[ ] Invalid password for both tables -> 401 Unauthorized.

Story 3: Refactor "Join Environment" Logic
As a User, I want to join a new school/environment by simply clicking a button, So that I don't have to fill out a registration form or create a new password every time.

Technical Tasks:

Update the User model: public function environments() { return $this->hasManyThrough(Environment::class, EnvironmentUser::class, ...); }

Create/Update endpoint POST /api/environments/{id}/join.

New Logic:

Check if Auth::user() is already linked to this environment.

If not, create a row in environment_users with user_id = Auth::id() and environment_id = $id.

Do not require email or password in the payload.

Acceptance Criteria:

[ ] An authenticated user can be added to a new environment via API call without providing credentials.

[ ] The new record in environment_users correctly references the logged-in user's ID.

Story 4: Cleanup & Hardening (Post-Migration)
As a Database Administrator, I want to remove redundant data columns and enforce strict relationships, So that the database integrity is maintained and we stop storing passwords in two places.

Note: This story should be scheduled for 1-2 sprints AFTER Story 1-3 to ensure stability.

Technical Tasks:

Create a migration to drop email and password columns from environment_users.

Update the migration to make environment_users.user_id non-nullable.

Add a Foreign Key Constraint: ON DELETE CASCADE.

Acceptance Criteria:

[ ] The environment_users table no longer contains PII (Personally Identifiable Information) or Credentials.

[ ] The application code no longer references environment_users.email or environment_users.password.

Technical Flow Diagram
Developer Implementation Notes:
Backup: Ensure a full SQL dump is taken before running the Story 1 script.

Mobile App Impact: Once Story 3 is done, the Mobile App "School Switcher" becomes a simple API call to GET /api/user/environments to list available schools, and POST /api/environments/{id}/join to add new ones.