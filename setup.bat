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
    echo     - Dong bo thu vien trong Docker volume...
    docker compose exec -T -e COMPOSER_PROCESS_TIMEOUT=0 app composer install --no-interaction --prefer-dist --optimize-autoloader
    if errorlevel 1 (
        echo.
        echo [!] LOI: composer install that bai. Hay chay lai setup.bat hoac:
        echo     docker compose exec -e COMPOSER_PROCESS_TIMEOUT=0 app composer install
        echo.
        pause
        exit /b 1
    )
    docker compose exec -T app test -f vendor/autoload.php
    if errorlevel 1 (
        echo.
        echo [!] LOI: Van thieu vendor\autoload.php sau khi cai Composer.
        pause
        exit /b 1
    )

echo.
echo [4/6] Tao ma khoa ung dung [APP_KEY] va cap quyen thu muc...
docker compose exec -T app php artisan key:generate --force
if errorlevel 1 (
    echo.
    echo [!] LOI: Khong chay duoc php artisan. Kiem tra lai buoc Composer.
    pause
    exit /b 1
)
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
if errorlevel 1 (
    echo.
    echo [!] LOI: Migration that bai. Kiem tra MySQL container roi chay:
    echo     docker compose exec app php artisan migrate --force
    echo.
    pause
    exit /b 1
)

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
