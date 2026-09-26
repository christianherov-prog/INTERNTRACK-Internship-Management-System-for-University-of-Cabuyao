# Database snapshots

## `interntrack-defense-demo-2026-09-26.sql` — controlled demonstration database

A full MariaDB export (schema, migration history, and records) of the controlled
CCS demonstration dataset as of September 26, 2026. It is intended for
development, demonstration, thesis defense, and team reproduction — not
production. Restore and account setup steps are in the repository README
(*Database Restore*, *Demo File Restore*, *Demo / Defense Accounts*).

Exported with `mysqldump --single-transaction --routines --skip-comments
--skip-dump-date --no-tablespaces` (MariaDB 10.4). Timestamps are written in
UTC (`TIME_ZONE='+00:00'`), matching the application timezone.

Prepared from a staging copy of the development database, never the live one:

- Emptied: `personal_access_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
  `failed_jobs`, `password_reset_tokens`.
- `users.remember_token` and `users.avatar_path` cleared (no profile photos);
  lockout counters reset.
- Contact numbers replaced with fictional values; test supervisor-invitation
  names, e-mail addresses, and related notification text anonymized.
- Two uploaded images and one message attachment that are unsuitable for
  distribution were excluded.
- Password hashes are included so accounts stay usable; set your own local
  password with `php artisan interntrack:set-demo-passwords`.

Verification: restored into an empty database (import exit code 0),
`php artisan migrate:status` showed no pending migrations, record counts and
per-Student totals matched the source apart from the three excluded items, and
Student, Faculty, Coordinator, Industry Supervisor, Director, and MISD accounts
signed in against the restored copy.

Files referenced by the snapshot are provided as synthetic stand-ins in
`../demo-files/private/` (restore with `php artisan interntrack:restore-demo-files`).

## `interntrack-schema-2026-09-24.sql` — schema-only reference

Structure of the 52 tables without any rows, password hashes, tokens, or
sessions. Importing it creates empty tables with an empty migration history; do
not run migrations blindly over it. Use the demonstration snapshot above or the
normal migration and seeding setup for a working installation.
