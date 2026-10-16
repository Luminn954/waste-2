@echo off
setlocal
title SmartWaste Waste 2

set "ROOT=%~dp0"
cd /d "%ROOT%"

set "XAMPP=C:\xampp"
if not exist "%XAMPP%\mysql_start.bat" (
  echo XAMPP tidak ditemukan di %XAMPP%.
  echo Pastikan XAMPP sudah terpasang, lalu coba lagi.
  pause
  exit /b 1
)

echo Menyalakan MySQL dan Apache XAMPP...
start "SmartWaste MySQL" /min "%XAMPP%\mysql_start.bat"
start "SmartWaste Apache" /min "%XAMPP%\apache_start.bat"

timeout /t 2 /nobreak >nul
start "" "http://localhost:8080/Waste2/frontend/index.html"
echo SmartWaste sudah dijalankan melalui XAMPP.
echo Web user  : http://localhost:8080/Waste2/frontend/index.html
echo API       : http://localhost:8080/Waste2/backend-php/index.php/health
echo Android   : panel admin; gunakan emulator Android Studio dengan server XAMPP tetap menyala.
pause
