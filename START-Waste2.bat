@echo off
setlocal
title Smart Waste - Safaras Web

set "ROOT=%~dp0"
cd /d "%ROOT%"
set "XAMPP=C:\xampp"
set "MYSQL_BIN=%XAMPP%\mysql\bin\mysqld.exe"
set "MYSQL_CONFIG=%XAMPP%\mysql\my-usb-3307.ini"

if not exist "%MYSQL_BIN%" (
  echo MariaDB XAMPP tidak ditemukan di %MYSQL_BIN%.
  pause
  exit /b 1
)
if not exist "%MYSQL_CONFIG%" (
  echo Konfigurasi database Smart Waste port 3307 tidak ditemukan.
  pause
  exit /b 1
)

netstat -ano | findstr /R /C:":3307 .*LISTENING" >nul
if errorlevel 1 (
  echo Menyalakan database Smart Waste di port 3307...
  start "SmartWaste MySQL 3307" /min "%MYSQL_BIN%" --defaults-file="%MYSQL_CONFIG%" --standalone
)
call :waitforport 3307 "database Smart Waste"
if errorlevel 1 exit /b 1

netstat -ano | findstr /R /C:":8080 .*LISTENING" >nul
if errorlevel 1 (
  echo Menyalakan Apache XAMPP di port 8080...
  start "SmartWaste Apache 8080" /min "%XAMPP%\apache_start.bat"
)
call :waitforport 8080 "Apache"
if errorlevel 1 exit /b 1

start "" "http://localhost:8080/Waste2/frontend/index.html"
echo Smart Waste memakai satu database MySQL pada port 3307.
echo Web user  : http://localhost:8080/Waste2/frontend/index.html
echo API       : http://localhost:8080/Waste2/backend-php/index.php/health
echo phpMyAdmin: http://localhost:8080/phpmyadmin/
echo.
pause
exit /b 0

:waitforport
set "PORT=%~1"
set "SERVICE=%~2"
for /L %%I in (1,1,30) do (
  netstat -ano | findstr /R /C:":%PORT% .*LISTENING" >nul
  if not errorlevel 1 exit /b 0
  timeout /t 1 /nobreak >nul
)
echo %SERVICE% belum siap. Periksa log XAMPP untuk detailnya.
pause
exit /b 1
