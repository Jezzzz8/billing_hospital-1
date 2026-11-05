@echo off
setlocal

set "APP_DIR=%~dp0"
set "PHP_EXE=%APP_DIR%..\..\php\php.exe"

if not exist "%PHP_EXE%" (
    set "PHP_EXE="
    for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
)

if not defined PHP_EXE (
    echo ERROR: PHP was not found. Install XAMPP PHP or add php.exe to PATH.
    goto startup_failure
)

if not exist "%APP_DIR%vendor\autoload.php" (
    echo ERROR: Composer dependencies are missing: "%APP_DIR%vendor\autoload.php"
    echo Run composer install from the application directory.
    goto startup_failure
)

"%PHP_EXE%" -v >nul 2>&1
if errorlevel 1 (
    echo ERROR: PHP could not be started from "%PHP_EXE%".
    goto startup_failure
)

echo Starting WebSocket server with "%PHP_EXE%"...
pushd "%APP_DIR%"
"%PHP_EXE%" "%APP_DIR%websocket-server.php"
set "SERVER_EXIT=%ERRORLEVEL%"
popd

if not "%SERVER_EXIT%"=="0" (
    echo WebSocket server exited with code %SERVER_EXIT%.
    pause
)

exit /b %SERVER_EXIT%

:startup_failure
pause
exit /b 1
