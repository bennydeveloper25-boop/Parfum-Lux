@echo off
REM Parfum Lux - arrancar Frontend en localhost:5500
cd /d "%~dp0frontend"
echo Iniciando Frontend en http://localhost:5500 ...
php -S localhost:5500
pause
