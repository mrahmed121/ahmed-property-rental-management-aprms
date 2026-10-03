@echo off
REM ============================================================
REM  APRMS - One-Click Launcher (Smart Setup v2)
REM  Ahmed Property & Rental Management System
REM ============================================================

title APRMS Launcher
setlocal EnableDelayedExpansion

echo ============================================================
echo  APRMS - Ahmed Property ^& Rental Management
echo  Ahmed - Own Every Square Foot.
echo ============================================================
echo.

REM ---------- 0. Go to script directory ----------
cd /d "%~dp0"
echo Working directory: %CD%
echo.

REM ---------- 1. Check prerequisites ----------
set MISSING=0
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [MISSING] PHP not found on PATH.
    echo           Install PHP 8.3+: https://www.php.net/downloads
    set MISSING=1
)
where composer >nul 2>nul
if %errorlevel% neq 0 (
    echo [MISSING] Composer not found on PATH.
    echo           Install: https://getcomposer.org/download/
    set MISSING=1
)
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [MISSING] Node.js not found on PATH.
    echo           Install Node.js 18+: https://nodejs.org/
    set MISSING=1
)
if %MISSING%==1 (
    echo.
    echo Please install the missing software, restart this window, and try again.
    pause
    exit /b 1
)
echo [OK] PHP, Composer, Node.js found.
echo.

REM ---------- 2. Backend dependencies ----------
cd /d "%~dp0backend"
if not exist "vendor\autoload.php" (
    echo [1/6] Installing backend dependencies...
    echo       This takes a few minutes on first run. Please wait.
    call composer install --no-interaction --no-progress
    if %errorlevel% neq 0 (
        echo.
        echo [ERROR] composer install failed.
        echo        Check your internet connection and try again.
        pause
        exit /b 1
    )
    echo [OK] Backend dependencies installed.
) else (
    echo [1/6] Backend dependencies already installed.
)
echo.

REM ---------- 3. Backend .env ----------
if not exist ".env" (
    echo [2/6] Setting up backend .env...
    copy /y ".env.example" ".env" >nul
    call php artisan key:generate --no-interaction --force
    call php artisan jwt:secret --no-interaction --force
    echo [OK] .env created.
) else (
    echo [2/6] backend\.env exists.
)
echo.

REM ---------- 4. SQLite database file ----------
if not exist "database\database.sqlite" (
    echo [3/6] Creating SQLite database file...
    type nul > "database\database.sqlite"
    echo [OK] database.sqlite created.
) else (
    echo [3/6] database.sqlite exists.
)
echo.

REM ---------- 5. Migrate + seed ----------
echo [4/6] Running migrations and seeders...
call php artisan migrate --seed --force --no-interaction
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Database setup failed. See message above.
    pause
    exit /b 1
)
echo [OK] Database ready with demo data.
echo.

REM ---------- 6. Frontend dependencies ----------
cd /d "%~dp0frontend"
if not exist "node_modules" (
    echo [5/6] Installing frontend dependencies...
    echo       This takes a few minutes on first run. Please wait.
    call npm install --no-audit --no-fund
    if %errorlevel% neq 0 (
        echo.
        echo [ERROR] npm install failed.
        echo        Check your internet connection and try again.
        pause
        exit /b 1
    )
    echo [OK] Frontend dependencies installed.
) else (
    echo [5/6] Frontend dependencies already installed.
)
echo.

REM ---------- 7. Start servers ----------
echo [6/6] Starting servers...
start "APRMS API" cmd /k "cd /d %~dp0backend && php artisan serve --port=8001"
echo       Waiting for API to start...
timeout /t 5 /nobreak >nul
start "APRMS Frontend" cmd /k "cd /d %~dp0frontend && npm run dev"
echo       Waiting for frontend to start...
timeout /t 8 /nobreak >nul

REM ---------- 8. Open browser ----------
echo.
echo Opening browser...
start "" "http://localhost:5173"

echo.
echo ============================================================
echo  APRMS is running!
echo  Frontend: http://localhost:5173
echo  API:      http://127.0.0.1:8001
echo.
echo  Demo login: admin@ahmedestates.local / password123
echo ============================================================
echo.
echo Do NOT close this window while using the app.
echo Press any key to stop the servers and exit.
pause >nul

REM ---------- 9. Cleanup on exit ----------
echo Stopping servers...
taskkill /fi "WINDOWTITLE eq APRMS API*" /f >nul 2>nul
taskkill /fi "WINDOWTITLE eq APRMS Frontend*" /f >nul 2>nul
echo Done.
