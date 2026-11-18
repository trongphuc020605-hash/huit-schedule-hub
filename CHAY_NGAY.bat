@echo off
chcp 65001 > nul
setlocal enabledelayedexpansion
cd /d "%~dp0"
title Khoi dong HUIT Schedule Hub

echo ================================================================
echo         HUIT SCHEDULE HUB - HE THONG PHAN LICH GIANG DAY
echo ================================================================
echo.

:: Tim PHP (uu tien PATH, sau do tu dong do Laragon / XAMPP)
set "PHP_BIN=php"
where php >nul 2>&1
if errorlevel 1 (
    for /d %%D in ("C:\laragon\bin\php\php-*") do set "PHP_BIN=%%D\php.exe"
    if not exist "!PHP_BIN!" if exist "C:\xampp\php\php.exe" set "PHP_BIN=C:\xampp\php\php.exe"
)

"!PHP_BIN!" -v >nul 2>&1
if errorlevel 1 (
    echo [LOI] Khong tim thay PHP. Vui long cai dat Laragon hoac XAMPP.
    pause
    exit /b 1
)

:: Tim MySQL (uu tien PATH, sau do tu dong do Laragon / XAMPP)
set "MYSQL_BIN=mysql"
where mysql >nul 2>&1
if errorlevel 1 (
    for /d %%D in ("C:\laragon\bin\mysql\mysql-*") do set "MYSQL_BIN=%%D\bin\mysql.exe"
    if not exist "!MYSQL_BIN!" if exist "C:\xampp\mysql\bin\mysql.exe" set "MYSQL_BIN=C:\xampp\mysql\bin\mysql.exe"
)

:: 1. Kiem tra file .env
if not exist ".env" (
    echo [1/4] Dang tao file cau hinh .env tu .env.example...
    copy /y ".env.example" ".env" > nul
) else (
    echo [1/4] File cau hinh .env da san sang.
)

:: 2. Kiem tra thu vien vendor/
if not exist "vendor\autoload.php" (
    echo [2/4] Dang cai dat thu vien Composer...
    composer install
    if errorlevel 1 (
        echo [LOI] Khong the chay composer install. Vui long kiem tra Composer.
        pause
        exit /b 1
    )
) else (
    echo [2/4] Thu vien vendor da san sang.
)

:: 3. Kiem tra va tu dong import CSDL huit_db neu chua co
echo [3/4] Dang kiem tra co so du lieu huit_db...
"!MYSQL_BIN!" -u root -e "USE huit_db; SELECT 1 FROM schedules LIMIT 1;" >nul 2>&1
if errorlevel 1 (
    echo       Chua co CSDL huit_db hoac bang du lieu. Dang import database\huit_db.sql...
    "!MYSQL_BIN!" -u root --default-character-set=utf8mb4 < "database\huit_db.sql" >nul 2>&1
    if errorlevel 1 (
        echo [CANH BAO] Khong ket noi duoc MySQL tu dong.
        echo            Hay bat MySQL trong Laragon/XAMPP va import file database\huit_db.sql.
    ) else (
        echo       Da tao va nap du lieu mau huit_db thanh cong.
    )
) else (
    echo       Co so du lieu huit_db da san sang.
)

:: 4. Mo trinh duyet va khoi dong Web Server
echo [4/4] Dang khoi dong Web Server tai http://localhost:8080 ...
echo.
echo ================================================================
echo   Dia chi web:     http://localhost:8080/login
echo   Tai khoan Admin: admin@huit.edu.vn / Admin@123
echo   Tai khoan GV:    giangvien@huit.edu.vn / Teacher@123
echo ================================================================
echo   Nhan Ctrl + C de dung Web Server khi khong su dung nua.
echo.

start "" "http://localhost:8080/login"
"!PHP_BIN!" -S 127.0.0.1:8080 index.php
