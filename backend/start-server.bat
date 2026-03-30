@echo off
cd /d "%~dp0"

set "PHP_EXE=php"
if exist "C:\php\php-8.2.30-Win32-vs16-x64\php.exe" set "PHP_EXE=C:\php\php-8.2.30-Win32-vs16-x64\php.exe"

"%PHP_EXE%" -d extension=openssl -S localhost:8000 -t public public/index.php
