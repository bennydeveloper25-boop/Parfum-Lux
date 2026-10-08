@echo off
REM Parfum Lux — Reparador TODO en uno. Doble clic, no tocar nada.
cd /d "%~dp0"
echo ===============================
echo  Parfum Lux - Reparador
echo ===============================
php tools\fix-environment.php
if %ERRORLEVEL%==2 (
  echo.
  echo El php.ini fue parcheado. Cierra TODO lo de PHP y vuelve a hacer doble clic en este archivo.
  pause
  exit /b 0
)
if %ERRORLEVEL% NEQ 0 (
  echo.
  echo Hubo un error. Lee el mensaje de arriba.
  pause
  exit /b 1
)
echo.
echo Arrancando API en http://localhost:8000 ...
cd /d "%~dp0backend\public"
php -S localhost:8000 router.php
pause
