# JKUAT Staff Housing Portal (Production Ready Update)

This repository has been updated with production-ready scaffolding: migrations, env config, session hardening, email verification, password reset, logging, Docker, and CI checks.

Quickstart (development with Docker):

1. Copy `.env.example` to `.env` and set values (do NOT commit `.env`).

2. Start services:

```bash
docker-compose up --build
```

3. Run migrations (from host or inside mysql container):

```bash
mysql -u $DB_USER -p -h 127.0.0.1 $DB_NAME < migrations/2026-02-11_add_applicant_profile_fields.sql
```

4. Seed admin:

```bash
DB_HOST=127.0.0.1 DB_USER=root DB_PASS=yourpass DB_NAME=staff_housing php migrations/seed_admin.php
```

CI:
- GitHub Actions workflow runs `php -l` and `php tests/run_tests.php` on PRs to `main`.

Notes:
- Email sending uses `mail()` fallback if SMTP env not configured and logs messages to `logs/emails.log`.
- Consider installing PHPMailer and configuring SMTP in production.

*** End of README
