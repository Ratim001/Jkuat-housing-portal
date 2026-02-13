# SECURITY

Basic security checklist for this project:

- Store secrets in environment variables; never commit `.env` with real secrets.
- Use `password_hash()` and `password_verify()` for all passwords.
- Use prepared statements for database interactions to avoid SQL injection.
- Use HTTPS in production (set `APP_ENV=production` and `APP_URL` to https).
- Rotate secrets and credentials regularly.
- Log security-related events (login failures, password resets) to `logs/app.log`.
- For sending emails in production use a robust library (PHPMailer) and authenticated SMTP.
