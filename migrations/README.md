# Migrations

Run migrations against your MySQL database using the command below:

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
