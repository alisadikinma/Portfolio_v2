@echo off
REM ========================================
REM Playwright Test Execution - Portfolio v2
REM ========================================

echo.
echo ====================================
echo  Playwright Test Suite - Portfolio v2
echo ====================================
echo.

REM Check if backend is running
echo [1/4] Checking backend server...
curl -s -o nul -w "%%{http_code}" http://localhost/Portfolio_v2/backend/public > nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Backend not accessible at http://localhost/Portfolio_v2/backend/public
    echo Please start XAMPP Apache first!
    pause
    exit /b 1
)
echo       Backend is running ✓

REM Check if frontend dev server is running
echo [2/4] Checking frontend dev server...
curl -s -o nul -w "%%{http_code}" http://localhost:5173 > nul 2>&1
if %errorlevel% neq 0 (
    echo WARNING: Frontend dev server not running!
    echo.
    echo Please start it manually in a separate terminal:
    echo   cd C:\xampp\htdocs\Portfolio_v2\frontend
    echo   npm run dev
    echo.
    echo Press any key once the dev server is running...
    pause
) else (
    echo       Frontend dev server is running ✓
)

REM Verify frontend is accessible again
echo [3/4] Verifying frontend accessibility...
curl -s -o nul -w "%%{http_code}" http://localhost:5173 > nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Frontend still not accessible!
    echo Please ensure npm run dev is running successfully.
    pause
    exit /b 1
)
echo       Frontend is accessible ✓

REM Run Playwright tests
echo [4/4] Running Playwright tests...
echo.
npx playwright test

REM Show report prompt
echo.
echo ====================================
echo  Tests Complete!
echo ====================================
echo.
echo To view the HTML report, run:
echo   npx playwright show-report
echo.
pause
