@echo off
REM ============================================================
REM  BarangGabay  project structure scaffold
REM
REM  Creates the folders/files documented in CLAUDE.md (section 3)
REM  that are missing from a fresh clone or a new dev machine.
REM  Safe to re-run: every check is "if not exist", so nothing that
REM  already exists (real controllers, views, config, etc.) is ever
REM  touched or overwritten.
REM
REM  Run from anywhere:
REM    tools\scaffold-structure.bat
REM ============================================================

set APP_DIR=C:\xampp\htdocs\BarangGabay
cd /d "%APP_DIR%"

echo.
echo Scaffolding BarangGabay structure in %APP_DIR%
echo.

REM --- app/controllers ---
call :ensure_dir  "app\controllers"
call :ensure_file "app\controllers\AuthController.php"
call :ensure_file "app\controllers\AnnouncementController.php"
call :ensure_file "app\controllers\EventController.php"
call :ensure_file "app\controllers\OrdinanceController.php"
call :ensure_file "app\controllers\NotificationController.php"
call :ensure_file "app\controllers\ResidentController.php"
call :ensure_file "app\controllers\AdminController.php"
call :ensure_file "app\controllers\AIController.php"

REM --- app/models ---
call :ensure_dir  "app\models"
call :ensure_file "app\models\User.php"
call :ensure_file "app\models\Announcement.php"
call :ensure_file "app\models\Event.php"
call :ensure_file "app\models\Ordinance.php"
call :ensure_file "app\models\Notification.php"
call :ensure_file "app\models\MediaFile.php"
call :ensure_file "app\models\AuditLog.php"

REM --- app/services ---
call :ensure_dir  "app\services"
call :ensure_file "app\services\AIService.php"
call :ensure_file "app\services\MailService.php"
call :ensure_file "app\services\FileService.php"
call :ensure_file "app\services\NotificationService.php"

REM --- app/middleware ---
call :ensure_dir  "app\middleware"
call :ensure_file "app\middleware\AuthMiddleware.php"
call :ensure_file "app\middleware\RoleMiddleware.php"
call :ensure_file "app\middleware\RateLimitMiddleware.php"

REM --- app/views/layouts ---
call :ensure_dir  "app\views\layouts"
call :ensure_file "app\views\layouts\main.php"
call :ensure_file "app\views\layouts\admin.php"

REM --- app/views/auth ---
call :ensure_dir  "app\views\auth"
call :ensure_file "app\views\auth\login.php"
call :ensure_file "app\views\auth\register.php"

REM --- app/views/resident ---
call :ensure_dir  "app\views\resident"
call :ensure_file "app\views\resident\home.php"
call :ensure_file "app\views\resident\announcements.php"
call :ensure_file "app\views\resident\announcement-detail.php"
call :ensure_file "app\views\resident\events.php"
call :ensure_file "app\views\resident\event-detail.php"
call :ensure_file "app\views\resident\ordinances.php"
call :ensure_file "app\views\resident\ordinance-detail.php"
call :ensure_file "app\views\resident\profile.php"
call :ensure_file "app\views\resident\notifications.php"

REM --- app/views/admin ---
call :ensure_dir  "app\views\admin"
call :ensure_file "app\views\admin\dashboard.php"

call :ensure_dir  "app\views\admin\announcements"
call :ensure_file "app\views\admin\announcements\index.php"
call :ensure_file "app\views\admin\announcements\create.php"
call :ensure_file "app\views\admin\announcements\edit.php"

call :ensure_dir  "app\views\admin\events"
call :ensure_file "app\views\admin\events\index.php"
call :ensure_file "app\views\admin\events\create.php"
call :ensure_file "app\views\admin\events\edit.php"

call :ensure_dir  "app\views\admin\ordinances"
call :ensure_file "app\views\admin\ordinances\index.php"
call :ensure_file "app\views\admin\ordinances\upload.php"

call :ensure_dir  "app\views\admin\residents"
call :ensure_file "app\views\admin\residents\index.php"
call :ensure_file "app\views\admin\residents\verify.php"

call :ensure_dir  "app\views\admin\reports"
call :ensure_file "app\views\admin\reports\index.php"

REM --- app/views/shared ---
call :ensure_dir  "app\views\shared"
call :ensure_file "app\views\shared\_toast.php"
call :ensure_file "app\views\shared\_pagination.php"
call :ensure_file "app\views\shared\_ai-chat-widget.php"
call :ensure_file "app\views\shared\_loading-spinner.php"

REM --- config ---
call :ensure_dir  "config"
call :ensure_file "config\database.php"
call :ensure_file "config\app.php"
call :ensure_file "config\ai.php"

REM --- public/assets ---
call :ensure_dir  "public\assets\css"
call :ensure_file "public\assets\css\main.css"
call :ensure_file "public\assets\css\admin.css"

call :ensure_dir  "public\assets\js"
call :ensure_file "public\assets\js\main.js"
call :ensure_file "public\assets\js\admin.js"
call :ensure_file "public\assets\js\ai-widget.js"

call :ensure_dir "public\assets\img"
call :ensure_dir "public\uploads"

REM --- routes ---
call :ensure_dir  "routes"
call :ensure_file "routes\web.php"
call :ensure_file "routes\api.php"

REM --- database ---
call :ensure_dir "database\seeders"
call :ensure_file "database\schema.sql"
call :ensure_file "database\seeders\DemoDataSeeder.php"

echo.
echo Done.
exit /b 0

REM ============================================================
REM  Subroutines
REM ============================================================

:ensure_dir
if not exist "%~1" (
    mkdir "%~1"
    echo   [dir made]   %~1
)
exit /b 0

:ensure_file
if not exist "%~1" (
    type nul > "%~1"
    echo   [file made]  %~1
)
exit /b 0
