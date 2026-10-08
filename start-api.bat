@echo off
REM Parfum Lux - arrancar API en localhost:8000
cd /d "%~dp0backend\public"
echo Iniciando Parfum Lux API en http://localhost:8000 ...
echo Prueba: http://localhost:8000/api/health
php -S localhost:8000 router.php
pause
