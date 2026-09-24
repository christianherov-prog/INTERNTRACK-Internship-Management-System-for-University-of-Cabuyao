# Sanitized database schema snapshot

`interntrack-schema-2026-09-24.sql` contains the current structure of all 52
tables in the local `interntrack` database. It was exported with MariaDB
mysqldump using `--no-data --skip-triggers --skip-comments --skip-add-locks`.
Auto-increment counters were removed from table options.

This schema-only export contains no account records, password hashes, tokens,
sessions, or other application rows. It does not include uploaded files or
the application `.env`. Importing it creates empty tables, not populated accounts.

To restore into a separate database, open the MySQL/MariaDB client and run:

```sql
CREATE DATABASE interntrack_restored CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE interntrack_restored;
SOURCE /absolute/path/to/interntrack-schema-2026-09-24.sql;
```

Use forward slashes in the SOURCE path on Windows. The dump drops and recreates
tables in the selected database, so select a fresh database for restoration.
Set `DB_DATABASE=interntrack_restored` in the local backend `.env` to use it.
The migration history table is also empty; do not run migrations blindly over
this imported schema. For a fresh application installation, use the repository's
normal migration and seeding setup instead of importing this reference snapshot.

Validation for the accompanying code: frontend production build passed;
64 unit tests passed with 132 assertions. Integration tests were not rerun for
this snapshot commit. The export completed successfully and was checked for
52 CREATE TABLE statements and absence of data insertion statements and password
hashes. A restore was not run.
