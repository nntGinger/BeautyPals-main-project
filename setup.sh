#!/bin/bash
set -e

echo "==================================================================="
echo "  KHOI TAO MOI TRUONG DO AN TU DONG (LARAVEL + DOCKER)"
echo "==================================================================="
echo ""

echo "[*] Kiem tra Docker..."
if ! docker info >/dev/null 2>&1; then
    echo "[!] LOI: Docker chua duoc bat! Vui long bat Docker Desktop truoc."
    exit 1
fi
echo "    -> Docker dang hoat dong tot."

echo ""
echo "[1/6] Kiem tra file cau hinh .env..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "    -> Da tao file .env tu .env.example."
else
    echo "    -> File .env da co san, bo qua."
fi

echo ""
echo "[2/6] Khoi dong va build cac container Docker..."
docker compose up -d --build

echo ""
echo "[3/6] Cai dat cac goi thu vien Composer..."
docker compose exec -T app composer install --no-interaction

echo ""
echo "[4/6] Tao ma khoa ung dung (APP_KEY) va cap quyen thu muc..."
docker compose exec -T app php artisan key:generate --force
docker compose exec -T app chmod -R 777 storage bootstrap/cache || true

echo ""
echo "[5/6] Dang cho co so du lieu MySQL san sang..."
docker compose exec -T app php -r "for($i=0;$i<30;$i++){try{new PDO('mysql:host=db;port=3306;dbname=laravel','root','root');exit(0);}catch(Exception $e){sleep(1);}}exit(1);" || sleep 5

echo ""
echo "[6/6] Chay Migration database..."
docker compose exec -T app php artisan migrate --force

echo ""
echo "==================================================================="
echo "  CHUC MUNG! BAN DA THIET LAP THANH CONG!"
echo "  - Web App:   http://localhost:8081"
echo "  - MySQL DB:  localhost:3308 (User: root / Pass: root)"
echo "==================================================================="
