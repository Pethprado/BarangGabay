# Database backups

Gzipped `mysqldump` output, written by `App\Services\BackupService`.

> **These files contain every user row, including password hashes.**
> The folder sits outside the webroot on purpose and must never be moved into
> `public/`. Downloads are streamed by `SuperAdminController` after a
> superadmin role check, never linked directly.

## Making a backup

- **From the panel:** Super Admin → Backups → *Back up now*.
- **From the CLI:** `C:\xampp\php\php.exe tools\backup.php`

Only the newest `BackupService::KEEP_LATEST` (10) are kept; older ones are
pruned automatically after each run.

## Scheduling

There is no cron on XAMPP/Windows, so scheduling is a Windows Task Scheduler
job. Run once in an **Administrator** Command Prompt:

```bat
schtasks /create /tn "BarangGabay Nightly Backup" ^
         /tr "C:\xampp\htdocs\BarangGabay\tools\backup-task.bat" ^
         /sc daily /st 02:00 /rl HIGHEST
```

Until that task exists, the Backups page shows an "overdue" warning whenever
the newest backup is more than 24 hours old, so a missing schedule is visible
rather than silent.

## Restoring — read before you run this

**Restoring is deliberately not exposed in the web UI.** A restore overwrites
the live database and cannot be undone, and a misclick on a web page is far
too cheap for an action that destructive.

Restore from a shell instead, and take a fresh backup first:

```bat
REM 1. Back up the CURRENT state, so a bad restore is recoverable
C:\xampp\php\php.exe tools\backup.php

REM 2. Decompress the backup you want
cd C:\xampp\htdocs\BarangGabay\storage\backups
C:\xampp\php\php.exe -r "readgzfile('baranggabay_YYYY-MM-DD_HHMMSS.sql.gz');" > restore.sql

REM 3. Restore it
C:\xampp\mysql\bin\mysql.exe -u root baranggabay < restore.sql

REM 4. Remove the decompressed copy — it is plaintext credentials on disk
del restore.sql
```

Stop the web server first if anyone might be using the system, and confirm
you are pointed at the right database — step 3 replaces its contents.
