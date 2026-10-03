@echo off
REM APRMS One-Click Launcher (Windows)
REM Requires: PHP 8.3+, Composer, Node.js 18+ installed and on PATH.
REM Starts the Laravel API on :8001 and the Vite dev server on :5173.

title APRMS Launcher
echo ============================================
echo  APRMS - Ahmed Property ^& Rental Management
echo  Ahmed - Own Every Square Foot.
echo ============================================
echo.

where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] PHP not found on PATH. Install PHP 8.3+ first.
    pause
    exit /b 1
)
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Node.js not found on PATH. Install Node.js 18+ first.
    pause
    exit /b 1
)

echo [1/2] Starting Laravel API on http://127.0.0.1:8001 ...
start "APRMS API" cmd /k "cd /d %~dp0backend && php artisan serve --port=8001"

echo [2/2] Starting Vite dev server on http://localhost:5173 ...
start "APRMS Frontend" cmd /k "cd /d %~dp0frontend && npm run dev"

echo.
echo Done. Open http://localhost:5173 in your browser.
echo Demo login: admin@ahmedestates.local / password123
echo.
pause
