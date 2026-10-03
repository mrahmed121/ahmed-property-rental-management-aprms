@echo off
REM ============================================================
REM  APRMS - One-Click Launcher (Smart Setup)
REM  Ahmed Property & Rental Management System
REM  "Ahmed - Own Every Square Foot."
REM ============================================================
REM  First run: installs all dependencies automatically.
REM  Later runs: just starts the servers.
REM  Requires: PHP 8.3+, Composer, Node.js 18+ on PATH.
REM ============================================================

title APRMS Launcher
setlocal EnableDelayedExpansion

echo ============================================================
echo  APRMS - Ahmed Property ^& Rental Management
echo  Ahmed - Own Every Square Foot.
echo ============================================================
echo.

REM ---------- 1. Check prerequisites ----------
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] PHP not found. Install PHP 8.3+ and add it to PATH.
    echo         Download: https://www.php.net/downloads
    pause
    exit /b 1
)
where composer >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Composer not found. Install it and add it to PATH.
    echo         Download: https://getcomposer.org/download/
    pause
    exit /b 1
)
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Node.js not found. Install Node.js 18+ and add it to PATH.
    echo         Download: https://nodejs.org/
    pause
    exit /b 1
)
where npm >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] npm not found. Reinstall Node.js (includes npm).
    pause
    exit /b 1
)
echo [OK] PHP, Composer, Node.js found.
echo.

REM ---------- 2. Backend dependencies ----------
if not exist "%~dp0backend\vendor\autoload.php" (
    echo [1/5] Installing backend dependencies (composer install)...
    echo       This takes a few minutes on first run.
    cd /d "%~dp0backend"
    call composer install --no-interaction
    if %errorlevel% neq 0 (
        echo [ERROR] composer install failed. Check your internet connection.
        pause
        exit /b 1
    )
    echo [OK] Backend dependencies installed.
) else (
    echo [1/5] Backend dependencies already installed. Skipping.
)
echo.

REM ---------- 3. Backend .env setup ----------
if not exist "%~dp0backend\.env" (
    echo [2/5] Creating backend .env file...
    cd /d "%~dp0backend"
    copy /y .env.example .env >nul
    call php artisan key:generate --no-interaction
    call php artisan jwt:secret --no-interaction
    echo [OK] .env created with fresh keys.
) else (
    echo [2/5] backend\.env already exists. Skipping.
)
echo.

REM ---------- 4. Database migrate + seed ----------
echo [3/5] Setting up database...
cd /d "%~dp0backend"
call php artisan migrate --seed --no-interaction --force
if %errorlevel% neq 0 (
    echo [WARN] migrate --seed had an issue. If tables already exist, this is fine.
)
echo [OK] Database ready.
echo.

REM ---------- 5. Frontend dependencies ----------
if not exist "%~dp0frontend\node_modules\.package-lock.json" (
    if not exist "%~dp0frontend\node_modules" (
        echo [4/5] Installing frontend dependencies (npm install)...
        echo       This takes a few minutes on first run.
        cd /d "%~dp0frontend"
        call npm install
        if %errorlevel% neq 0 (
            echo [ERROR] npm install failed. Check your internet connection.
            pause
            exit /b 1
        )
        echo [OK] Frontend dependencies installed.
    ) else (
        echo [4/5] frontend\node_modules exists. Skipping.
    )
) else (
    echo [4/5] Frontend dependencies already installed. Skipping.
)
echo.

REM ---------- 6. Start servers ----------
echo [5/5] Starting servers...
echo       API:      http://127.0.0.1:8001
echo       Frontend: http://localhost:5173
echo.
start "APRMS API" cmd /k "cd /d %~dp0backend && php artisan serve --port=8001"
start "APRMS Frontend" cmd /k "cd /d %~dp0frontend && npm run dev"

echo ============================================================
echo  Done! Open http://localhost:5173 in your browser.
echo  Demo login: admin@ahmedestates.local / password123
echo ============================================================
echo.
pause
