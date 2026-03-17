# Migrations

Preferred: run all migrations (SQL + PHP) using the migration runner:

```sh
php migrations/run_migrations.php
```

Check what is pending without applying:

```sh
php migrations/run_migrations.php --status
```

Manual (single SQL file) option:

```sh
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < migrations/2026-02-11_add_applicant_profile_fields.sql
```

Environment variables used:
- DB_HOST
- DB_PORT
- DB_NAME
- DB_USER
- DB_PASS

Note: This migration is idempotent and safe to run multiple times. It uses INFORMATION_SCHEMA checks to avoid errors if columns already exist.
