# InternTrack backup and restore

InternTrack stores authoritative records in **MySQL** and uploaded files on the configured Laravel disk (`storage/app/private` by default). Application RAM, React state, and `sessionStorage` are not sources of truth.

This is **not** automatic off-site disaster recovery. A destroyed disk or site still requires copies stored somewhere else (another machine, object storage, or institutional backup).

## What is supported

| Capability | Status |
|---|---|
| Normal persistence (MySQL + private files) | Supported |
| Restart recovery (frontend, backend, DB reconnect) | Supported when MySQL and `storage/app/private` survive |
| Transaction rollback on failed multi-step writes | Supported in application code |
| Database backup | `php artisan interntrack:backup` (uses `DB_*` env, no hardcoded passwords) |
| File-storage backup | Included in the same command |
| Restore to an isolated database | `php artisan interntrack:restore` with `--force` and `--database=` |
| Off-site / catastrophic disaster recovery | **Not implemented** |

## Backup (development or deployment host)

```bash
cd backend
php artisan interntrack:backup
```

Creates `storage/app/backups/{timestamp}/` with:

- `storage-private/` — documents, portfolio images, signatures, MOAs, attachments
- `database.sql` — schema + rows, when `mysqldump` is on PATH

Passwords are read from the environment (`DB_PASSWORD` / Laravel config). They are passed to the client via `MYSQL_PWD`, not written into the backup scripts.

File-only:

```bash
php artisan interntrack:backup --skip-database
```

## Restore (isolated test database only)

Never restore over a live production schema without an operations plan and a verified extra copy of the current data.

```bash
php artisan interntrack:restore storage/app/backups/YYYYMMDD_HHMMSS --database=interntrack_restore_test --force
```

Storage-only:

```bash
php artisan interntrack:restore storage/app/backups/YYYYMMDD_HHMMSS --files-only --force
```

After restore, confirm:

1. Known student/internship rows
2. File download links for documents, signatures, MOA, portfolio images
3. Attendance / FO-30 times
4. Journals / FO-31
5. Supervisor intern feedback

## Production recommendation

Keep automated off-host copies of:

1. MySQL (mysqldump or managed snapshot)
2. `storage/app/private`

Store those copies off the application server. InternTrack does not replicate itself to a second site.
