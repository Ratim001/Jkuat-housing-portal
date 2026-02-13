**Production Readiness Checklist**

This file summarizes the changes made to the repository and the remaining steps to deploy and verify the application in a production-like environment.

What I changed (done):
- DB migration: `migrations/2026-02-11_add_applicant_profile_fields.sql` (adds profile fields, tokens, indexes)
- Seed script: `migrations/seed_admin.php` (env-driven admin insert)
- Env/example: `.env.example` and `.gitignore` updated to ignore `.env`
- DB config: `includes/db.php` now reads env vars and sets utf8mb4
- Bootstrap: `includes/init.php` with session/cookie hardening
- Helpers: `includes/helpers.php` (safe output, logging, `send_email()` with PHPMailer fallback)
- Validation: `includes/validation.php` and unit tests in `tests/`
- Auth flows: registration, email verification (`php/verify_email.php`), password reset (`php/request_password_reset.php`, `php/reset_password.php`)
- Profile: `php/applicant_profile.php` validation and secure update
- Admin: `php/edit_applicant.php` and `php/manage_applicants.php` (missing-profile filter + edit link)
- Email templates: `templates/emails/verify_email.html`, `templates/emails/reset_password.html`
- Docker and nginx: `Dockerfile`, `docker-compose.yml`, `docker/nginx/default.conf`
- CI: `.github/workflows/ci.yml` (lint + tests; runs `composer test` if available)
- Composer: `composer.json` added (PHPMailer, phpunit dev)
- Tests: `phpunit.xml`, `tests/ValidationTest.php`, `tests/bootstrap.php`
- Docs: `docs/backup_and_restore.md`, `README.md`, `SECURITY.md` updates
- Logging: `logs/` usage and `logs_write()` helper

Next manual steps (required to fully verify and run):
1. Create a local `.env` from `.env.example` and set values (DB, APP_URL, APP_ENV, SMTP_*). Do NOT commit `.env`.
2. Run migrations against your MySQL database:

```bash
# from repo root
mysql -u "$DB_USER" -p -h "$DB_HOST" -P "$DB_PORT" "$DB_NAME" < migrations/2026-02-11_add_applicant_profile_fields.sql
```

3. Seed the admin user (ensure `.env` is set):

```bash
php migrations/seed_admin.php
```

4. Install PHP dependencies (locally) to enable PHPMailer and PHPUnit:

```bash
composer install
```

5. Run tests locally:

```bash
composer test
# or
vendor/bin/phpunit --configuration phpunit.xml
```

6. For email verification and password reset in development, either configure SMTP env vars (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE`) or inspect `logs/emails.log` for generated links.

7. Start the app locally with Docker (optional):

```bash
docker-compose up --build
```

8. After first login, change seeded admin password and verify `role` contains `Admin` (used for admin checks).

Notes / Caveats:
- Composer dependencies are not installed in this workspace; CI runs `composer install` and `composer test` if present.
- Sentry is a stub; to enable full Sentry reporting add the Sentry PHP SDK and configure DSN in env.
- Expand unit and integration tests for auth flows and email sending as a next step.

Files added/modified are visible in the repo. If you'd like, I can run `composer install` and the test suite here (requires Composer on the host), or create a PR and finalize commits.
