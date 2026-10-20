@echo off
REM ============================================================================
REM  BarangGabay - Debug Menu
REM  Double-click this file (or run "debug.bat" in a terminal) for the everyday
REM  manual checks: start the server, run tests, read the error log, check the
REM  live Render site, and run the maintenance tools - without remembering paths.
REM ============================================================================
setlocal EnableDelayedExpansion
title BarangGabay Debug Menu
cd /d "%~dp0"

set "PHP=C:\xampp\php\php.exe"
set "XAMPP=C:\xampp"
set "LOCAL=http://localhost:8080/BarangGabay/public"
set "LIVE=https://baranggabay.onrender.com"

if not exist "%PHP%" (
    echo [!] PHP not found at %PHP%
    echo     Edit the PHP line at the top of debug.bat to point to your php.exe.
    pause
    exit /b 1
)

REM "debug.bat 5" runs option 5 directly and exits (handy for shortcuts).
if not "%~1"=="" (
    set "ONESHOT=1"
    set "choice=%~1"
    goto dispatch
)

:menu
cls
echo  ===========================================================
echo                  BarangGabay  -  Debug Menu
echo  ===========================================================
echo   SERVER
echo     1  Start Apache + MySQL (XAMPP)
echo     2  Stop Apache + MySQL
echo     3  Open the site (resident)
echo     4  Open the staff / admin login
echo.
echo   CHECKS
echo     5  Run all automated tests
echo     6  Run one test file (e.g. PurokTest)
echo     7  Check every PHP file for syntax errors
echo     8  Show the last 40 lines of the error log
echo     9  Open the logs folder
echo    10  Check the LIVE site on Render (health + commit)
echo    11  Translation health report
echo    12  SMS setup check (no message is sent)
echo.
echo   TOOLS
echo    13  Hazard check now (dry run - posts nothing)
echo    14  Retry failed translations
echo    15  Rebuild the voice word index
echo    16  Regenerate the Manobo UI labels
echo    17  Regenerate the dark-mode CSS
echo    18  Back up the database
echo    19  Preview the demo-account cleanup (changes nothing)
echo.
echo   GIT
echo    20  Git status and the last 10 commits
echo.
echo     0  Exit            (tip: "debug.bat 5" runs option 5 directly)
echo  ===========================================================
set "choice="
set /p "choice=  Choose a number: "
if "%choice%"=="" goto menu

:dispatch
if "%choice%"=="1"  goto start_server
if "%choice%"=="2"  goto stop_server
if "%choice%"=="3"  goto open_site
if "%choice%"=="4"  goto open_admin
if "%choice%"=="5"  goto tests_all
if "%choice%"=="6"  goto tests_one
if "%choice%"=="7"  goto lint
if "%choice%"=="8"  goto log_tail
if "%choice%"=="9"  goto log_folder
if "%choice%"=="10" goto live_health
if "%choice%"=="11" goto translations
if "%choice%"=="12" goto sms
if "%choice%"=="13" goto hazards
if "%choice%"=="14" goto retry_translations
if "%choice%"=="15" goto voice_index
if "%choice%"=="16" goto manobo_ui
if "%choice%"=="17" goto dark_css
if "%choice%"=="18" goto backup
if "%choice%"=="19" goto demo_preview
if "%choice%"=="20" goto git_status
if "%choice%"=="0"  goto end
echo   Unknown choice "%choice%".
goto pause_menu

REM ---------------------------------------------------------------- SERVER
:start_server
echo Starting Apache and MySQL in their own windows...
start "Apache" /min cmd /c "%XAMPP%\apache_start.bat"
start "MySQL"  /min cmd /c "%XAMPP%\mysql_start.bat"
timeout /t 4 >nul
echo Done. Keep those two windows open while you work.
goto pause_menu

:stop_server
call "%XAMPP%\apache_stop.bat"
call "%XAMPP%\mysql_stop.bat"
goto pause_menu

:open_site
start "" "%LOCAL%/"
goto back

:open_admin
start "" "%LOCAL%/login?as=staff"
goto back

REM ---------------------------------------------------------------- CHECKS
:tests_all
"%PHP%" vendor\bin\phpunit
goto pause_menu

:tests_one
set "test="
set /p "test=  Test name (file in tests\Unit, without .php): "
if "%test%"=="" goto back
if not exist "tests\Unit\%test%.php" (
    echo   tests\Unit\%test%.php not found.
    goto pause_menu
)
"%PHP%" vendor\bin\phpunit "tests\Unit\%test%.php"
goto pause_menu

:lint
echo Checking PHP syntax in app, config, routes, public and tools...
set /a errors=0
set /a files=0
for /f "delims=" %%F in ('dir /s /b app\*.php config\*.php routes\*.php public\*.php tools\*.php 2^>nul') do (
    set /a files+=1
    "%PHP%" -l "%%F" >nul 2>&1
    if errorlevel 1 (
        "%PHP%" -l "%%F"
        set /a errors+=1
    )
)
if !errors!==0 (echo   Checked !files! files - no syntax errors found.) else (echo   !errors! of !files! files have syntax errors - see above.)
goto pause_menu

:log_tail
if not exist "storage\logs\error.log" (
    echo   storage\logs\error.log does not exist yet - no errors logged.
    goto pause_menu
)
powershell -NoProfile -Command "Get-Content -Tail 40 -Encoding UTF8 'storage\logs\error.log'"
goto pause_menu

:log_folder
start "" explorer "%~dp0storage\logs"
goto back

:live_health
echo Asking %LIVE%/health ...
curl -s -m 60 "%LIVE%/health"
echo.
echo   "commit" = the git commit Render is running; compare with option 20.
goto pause_menu

:translations
"%PHP%" tools\check-translations.php
goto pause_menu

:sms
"%PHP%" tools\sms-test.php
goto pause_menu

REM ---------------------------------------------------------------- TOOLS
:hazards
"%PHP%" tools\hazard-check.php --dry
goto pause_menu

:retry_translations
"%PHP%" tools\retry-translations.php
goto pause_menu

:voice_index
"%PHP%" tools\rebuild-voice-index.php
goto pause_menu

:manobo_ui
"%PHP%" tools\generate-manobo-ui.php
goto pause_menu

:dark_css
python tools\gen-legibility-css.py
if errorlevel 1 echo   Python is needed for this one (python.org), then run it again.
goto pause_menu

:backup
"%PHP%" tools\backup.php
goto pause_menu

:demo_preview
"%PHP%" tools\remove-demo-accounts.php
goto pause_menu

REM ---------------------------------------------------------------- GIT
:git_status
git status --short --branch
echo.
git log --oneline -10
goto pause_menu

REM ----------------------------------------------------------------
:pause_menu
if defined ONESHOT goto end
echo.
pause
goto menu

:back
if defined ONESHOT goto end
goto menu

:end
endlocal
exit /b 0
