# Backup and Restore

Recommended commands to backup and restore the MySQL database used by the app.

Backup (mysqldump):

```sh
mysqldump -u $DB_USER -p -h $DB_HOST $DB_NAME > backups/$(date +%F)_${DB_NAME}.sql
```

Restore:

```sh
mysql -u $DB_USER -p -h $DB_HOST $DB_NAME < backups/file_to_restore.sql
```

Recommendations:
- Backup daily for production; keep 30 days retention (rotate with cron).
- Store backups offsite or in cloud object storage (S3, Azure Blob).
- Test restores periodically.
