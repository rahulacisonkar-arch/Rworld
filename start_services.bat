@echo off
title Artee Shipping Portal Services
color 0A

echo ========================================================
echo          Starting Artee Shipping Portal Services
echo ========================================================
echo.

:: 1. Start MariaDB Database Server
echo [1/2] Starting MariaDB Database Server...
start "MariaDB Server" /B "C:\Users\Artee Admin\Desktop\browser-use-main\mariadb\bin\mysqld.exe" --defaults-file="C:\Users\Artee Admin\Desktop\browser-use-main\mariadb\data\my.ini"
timeout /t 3 /nobreak >nul

:: 2. Start PHP Web Server
echo [2/2] Starting PHP Web Server on port 80...
start "PHP Web Server" /B "C:\Users\Artee Admin\Desktop\browser-use-main\php\php.exe" -S localhost:80 -t "C:\Users\Artee Admin\Desktop\browser-use-main"
timeout /t 2 /nobreak >nul

echo.
echo ========================================================
echo Services started!
echo Database is running on default port (3306)
echo Portal is available at: http://localhost/shipping-portal/public
echo ========================================================
echo.
echo Press any key to stop the servers...
pause >nul

echo.
echo Stopping services...
taskkill /F /IM mysqld.exe >nul 2>&1
taskkill /F /IM php.exe >nul 2>&1
echo Services stopped successfully.
pause
