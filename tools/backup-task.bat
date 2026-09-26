@echo off
REM ============================================================
REM  BarangGabay — scheduled database backup
REM
REM  Register this with Windows Task Scheduler to get nightly backups.
REM  Run once, in an ADMINISTRATOR Command Prompt:
REM
REM    schtasks /create /tn "BarangGabay Nightly Backup" ^
REM             /tr "C:\xampp\htdocs\BarangGabay\tools\backup-task.bat" ^
REM             /sc daily /st 02:00 /rl HIGHEST
REM
REM  Check it:   schtasks /query /tn "BarangGabay Nightly Backup"
REM  Run it now: schtasks /run   /tn "BarangGabay Nightly Backup"
REM  Remove it:  schtasks /delete /tn "BarangGabay Nightly Backup" /f
REM
REM  Output is appended to storage/logs/backup.log.
REM ============================================================

set PHP_BIN=C:\xampp\php\php.exe
set APP_DIR=C:\xampp\htdocs\BarangGabay

cd /d "%APP_DIR%"
"%PHP_BIN%" tools\backup.php >> "%APP_DIR%\storage\logs\backup.log" 2>&1
exit /b %ERRORLEVEL%
