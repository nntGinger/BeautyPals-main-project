@echo off
chcp 65001 >nul
echo ===================================================================
echo   KHOI TAO MOI TRUONG DO AN TU DONG [LARAVEL + DOCKER]
echo ===================================================================
echo.

echo [*] Kiem tra Docker Desktop...
docker info >nul 2>&1
if errorlevel 1 (
    echo.
    echo [!] LOI: Docker Desktop chua duoc bat hoac chua khoi dong xong!
    echo     Vui long bat Docker Desktop cho den khi hien "Engine running",
    echo     roi chay lai file setup.bat nay nhe.
    echo.
    pause
    exit /b 1
)
echo     - Docker Desktop dang hoat dong tot.

echo.
echo [1/6] Kiem tra file cau hinh .env...
if not exist ".env" (
    copy .env.example .env >nul
    echo     - Da tao file .env tu .env.example
) else (
    echo     - File .env da co san
)

echo.
echo [2/6] Khoi dong va build cac container Docker...
docker compose up -d --build
if errorlevel 1 (
    echo.
    echo [!] LOI: Khong the khoi dong Docker Compose!
    pause
    exit /b 1
)
echo     - Cac container da khoi dong xong.

echo.
echo [3/6] Kiem tra va cai dat thu vien Composer [vendor]...
if not exist "vendor" (
    echo     - Dang tai cac goi thu vien lan dau [mat khoang 1-2 phut]...
    docker compose exec -T app composer install --no-interaction
) else (
    echo     - Thu muc vendor da co san, bo qua buoc tai ve.
)

echo.
echo [4/6] Tao ma khoa ung dung [APP_KEY] va cap quyen thu muc...
docker compose exec -T app php artisan key:generate --force
docker compose exec -T app chmod -R 777 storage bootstrap/cache >nul 2>&1

echo.
echo [5/6] Dang cho co so du lieu MySQL san sang...
docker compose exec -T app php -r "for($i=0;$i<30;$i++){try{new PDO('mysql:host=db;port=3306;dbname=laravel','root','root');exit(0);}catch(Exception $e){sleep(1);}}exit(1);"
if errorlevel 1 (
    echo     - Cho them giay lat de MySQL khoi tao...
    ping 127.0.0.1 -n 6 >nul
)

echo.
echo [6/6] Chay Migration tao bang co so du lieu...
docker compose exec -T app php artisan migrate --force

echo.
echo ===================================================================
echo   CHUC MUNG! BAN DA THIET LAP THANH CONG!
echo.
echo   - Web App:   http://localhost:8081
echo   - MySQL DB:  localhost:3308 [User: root / Pass: root / DB: laravel]
echo ===================================================================
echo.
echo Dang mo trinh duyet...
start http://localhost:8081
