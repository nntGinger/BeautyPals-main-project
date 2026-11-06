# BeautyPals — Đồ án Lập trình ứng dụng Web

Ứng dụng Laravel, chạy bằng Docker với Nginx, PHP 8.4-FPM và MySQL 8.0. Hướng dẫn dưới đây dành cho môi trường phát triển local.

## Yêu cầu

- Git và Docker Desktop đang chạy (Windows nên bật WSL2).
- Cổng 8081 cho website, 3308 cho MySQL chưa bị ứng dụng khác chiếm.
- Node.js phù hợp Vite 8 nếu cần build frontend; máy nghiệm thu dùng Node 24.22.2. Không cần cài PHP/MySQL riêng trên Windows.

## Cài đặt lần đầu bằng PowerShell

Mở terminal tại thư mục `BeautyPals-main-project` của bản clone. Chỉ sao chép cấu hình nếu chưa có `.env`:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
docker compose up -d --build
docker compose exec app composer install --optimize-autoloader
```

Kiểm tra `.env`: `APP_URL=http://localhost:8081`, `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_PORT=3306`, `DB_DATABASE=laravel`, `DB_USERNAME=root`, `DB_PASSWORD=root`. Đây là cấu hình local của compose, không dùng cho triển khai công khai. Session và cache mặc định dùng database.

Chỉ khi `APP_KEY` trong `.env` còn trống, tạo khóa một lần:

```powershell
docker compose exec app php artisan key:generate
```

Tiếp tục:

```powershell
docker compose exec app php artisan config:clear
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app php artisan storage:link
```

Nếu MySQL mới khởi động chưa sẵn sàng, xem `docker compose logs db`, đợi thông báo sẵn sàng rồi chạy lại migration. Nếu link đã tồn tại, không cần tạo lại.

Mở [BeautyPals local](http://localhost:8081). PHP chạy trong container nên không cần chạy `php artisan serve` trên Windows. Kết nối MySQL từ công cụ trên máy host dùng `127.0.0.1:3308`; từ container app dùng `db:3306`.

`setup.bat` / `setup.sh` vẫn có trong repo. Script hiện gọi tạo lại APP_KEY; với môi trường đang dùng nên làm theo các lệnh thủ công trên và giữ khóa hiện tại.

## Chạy hằng ngày và cập nhật mã nguồn

```powershell
docker compose up -d
docker compose ps
```

Sau khi cập nhật `composer.lock`, chạy lại `composer install --optimize-autoloader` trong container. Sau thay đổi schema chạy `php artisan migrate` trong container. Thư viện Composer được lưu trong volume Linux `vendor_data`; thư mục vendor trên Windows không đại diện cho thư viện app đang sử dụng.

Dừng hệ thống:

```powershell
docker compose down
```

Lệnh trên giữ volume database. `docker compose down -v` xóa volume; `migrate:fresh` xóa bảng của DB đích, chỉ dùng khi chủ động muốn dựng lại dữ liệu. Seeder demo có thể cập nhật lại tài khoản và bản ghi demo; không dùng để giữ nguyên dữ liệu đã chỉnh sửa thủ công.

## Frontend

Các trang Blade hiện nạp CSS/JS public trực tiếp, gồm giao diện sản phẩm và ingredient; thao tác cơ bản không cần chạy Vite liên tục. Nếu cần dựng bundle frontend:

```powershell
npm.cmd ci
npm.cmd run build
```

Trên Linux/macOS dùng `npm` thay `npm.cmd`. Build hiện tải font qua fonts.bunny.net nên cần truy cập mạng. Cảnh báo thiếu `fontaine` cho font fallback là tùy chọn; lần build nghiệm thu vẫn thành công.

## Tài khoản demo

Chạy `db:seed` trước khi dùng. Mật khẩu chung: `Password123!`.

| Vai trò / tình huống | Email |
| --- | --- |
| Admin | admin@beautypals.test |
| Vendor đã duyệt | vendor@beautypals.test |
| Vendor cửa hàng thứ hai | vendor2@example.com |
| Customer | customer@beautypals.test |
| Vendor chờ duyệt | vendor.pending@beautypals.test |
| Customer bị khóa | locked@beautypals.test |

Khu vực chính: `/admin`, `/vendor`, `/customer`. Vendor chưa duyệt vào `/vendor/pending`; không được vào khu quản lý Vendor. Đăng ký tạo Customer active, không nhận role/status từ trình duyệt. Tài khoản khóa bị từ chối đăng nhập và phiên đang hoạt động bị hủy ở request tiếp theo.

Đăng nhập sai 5 lần bị chặn 15 phút theo email, kèm bộ đếm email/IP; đăng nhập thành công reset các bộ đếm tương ứng. Cấu hình ở `config/auth.php`. Các ô mật khẩu có nút hiện/ẩn riêng.

## Quản lý sản phẩm và thành phần A-06/A-07

- Vendor: `/vendor/products` — danh sách grid 5 cột trên desktop, thu gọn theo màn hình; tạo, xem, sửa sản phẩm và các thuộc tính/SKU của cửa hàng mình.
- Form hỗ trợ thương hiệu, danh mục, giá, ảnh JPEG/PNG/WebP tối đa 5 MiB/ảnh và tổng tối đa 8 ảnh, tìm thành phần theo tên/alias, chọn và sắp xếp tối đa 100 thành phần. Giới hạn vẫn được kiểm tra ở server.
- Admin: `/admin/ingredients` — quản lý ingredient và alias; không xóa được ingredient đang gắn sản phẩm. `/admin/products` — lọc, xem và xét duyệt sản phẩm, lý do và lịch sử trạng thái.

| Người thao tác | Trạng thái đầu | Hành động | Trạng thái sau |
| --- | --- | --- | --- |
| Vendor sở hữu | Nháp / Bị từ chối / Đã ẩn | Gửi duyệt | Chờ duyệt |
| Admin | Chờ duyệt | Duyệt / Từ chối | Đã duyệt / Bị từ chối |
| Admin | Đã duyệt | Ẩn, nhập lý do | Đã ẩn |
| Admin | Đã ẩn | Hiện lại | Đã duyệt |

Từ chối và ẩn phải có lý do. Gửi duyệt/duyệt/hiện lại kiểm tra điều kiện dữ liệu và Vendor hợp lệ. Vendor sửa được sản phẩm ở mọi trạng thái của chính mình; lưu chỉnh sửa giữ nguyên trạng thái, slug và thông tin xét duyệt. Muốn gửi duyệt, lưu trước rồi bấm nút gửi duyệt riêng. Chỉ xóa sản phẩm nháp khi không có dữ liệu phụ thuộc; giao diện yêu cầu xác nhận trước khi xóa.

Seeder hiện tạo 22 ingredient, 36 alias, 9 sản phẩm và 12 SKU. Ảnh demo trong seed chỉ có metadata, không kèm đủ file ảnh thật. Một số sản phẩm demo cũng chưa đủ ingredient; cần bổ sung dữ liệu/đăng ảnh thật trước khi gửi duyệt, duyệt hoặc hiện lại. Không coi mọi sản phẩm seed là đã sẵn sàng xuất bản.

## Kiểm tra và xử lý lỗi

```powershell
docker compose exec app php artisan route:list
docker compose exec app vendor/bin/pint --test
docker compose logs --tail=100 app webserver db
```

Log Laravel ở `storage/logs/laravel.log`. Nếu ảnh đã lưu nhưng không hiển thị, kiểm tra `storage:link`, file trong `storage/app/public` và quyền ghi storage/bootstrap/cache. Sau sửa cấu hình PHP ini, tạo lại app bằng `docker compose up -d --force-recreate app`.

Test mặc định của repo dùng SQLite trong bộ nhớ:

```powershell
docker compose exec app php artisan test
```

Kết quả SQLite không thay thế nghiệm thu MySQL. B9 đã nghiệm thu trên MySQL 8.0.46 riêng: 107 tests/1270 assertions, 38 kiểm tra HTTP thực và hai ca cạnh tranh. Cấu hình `.env.testing`, `phpunit.local.xml` và helper `.testing-local/` là bộ local ignored, không có sẵn trong clone mới. Chỉ chạy suite MySQL khi đã thiết lập DB/account test riêng và guard chặn DB phát triển; không trỏ test RefreshDatabase vào DB đang làm việc.

Hướng dẫn này đối chiếu với cấu hình hiện tại và kết quả B8/B9 ngày 06/11/2026. Tích hợp code nhóm (B10) và kiểm tra cài đặt từ môi trường sạch (B11) còn là bước nghiệm thu riêng.
