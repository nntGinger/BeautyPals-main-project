# Đồ Án Lập Trình Ứng Dụng Web

## A-02 và A-03 — Tài khoản và phân quyền

Truy cập http://localhost:8081 để đăng ký Customer hoặc đăng nhập.
Sau khi chạy migration, tạo dữ liệu demo bằng `docker compose exec app php artisan db:seed`.

| Vai trò / tình huống | Email | Mật khẩu |
| --- | --- | --- |
| Customer | customer@beautypals.test | Password123! |
| Vendor đã duyệt | vendor@beautypals.test | Password123! |
| Admin | admin@beautypals.test | Password123! |
| Tài khoản khóa | locked@beautypals.test | Password123! |
| Vendor chờ duyệt | vendor.pending@beautypals.test | Password123! |

Khu vực chính: `/customer`, `/vendor`, `/admin` (có thêm URL `/dashboard` trong mỗi khu vực).
Vendor chưa duyệt đăng nhập được vào trang thông báo `/vendor/pending`, nhưng vào `/vendor` nhận 403.
Tài khoản bị khóa bị từ chối đăng nhập và phiên đang hoạt động bị hủy ở request tiếp theo.
Đăng nhập sai 5 lần bị chặn 15 phút riêng theo email, kèm bộ đếm cho cặp email/IP;
không dùng bộ đếm IP chung cho các tài khoản. Cấu hình tại `config/auth.php`.
Form báo số lần còn lại sau mỗi lần sai, và báo chặn ngay ở lần sai thứ 5.
Trong thời gian chặn, nhập đúng mật khẩu, tải lại trang hoặc đổi trình duyệt không bỏ qua được
giới hạn của tài khoản đó. Các email khác trên cùng IP vẫn đăng nhập bình thường.
Đăng nhập đúng trước khi đủ 5 lần sai vẫn được phép; thành công reset bộ đếm email
và cặp email/IP vừa đăng nhập. Sau khi đăng xuất, lần sai đầu tiên còn 4 lượt.
Các ô mật khẩu đăng nhập/đăng ký/xác nhận có nút con mắt để hiện hoặc ẩn từng ô độc lập.
Mặc định mật khẩu được che; khi gửi form hoặc rời trang, ô nhập được che lại.
Đăng ký luôn tạo Customer active, không nhận role/status từ trình duyệt.

Middleware `role` hỗ trợ cả `role:customer|admin` và `role:customer,admin`.
Policy được Laravel tự phát hiện trong `app/Policies`; controller nghiệp vụ phải gọi
`authorize()` hoặc dùng middleware `can` cho thao tác tương ứng.
Vendor chỉ truy cập đơn con của cửa hàng qua VendorOrderPolicy; đơn cha thuộc Customer và Admin.

Chạy kiểm thử (SQLite trong bộ nhớ, không xóa MySQL đang dùng):

```powershell
docker compose exec app php artisan test
```

Để kiểm tra dựng lại toàn bộ dữ liệu trên database phát triển dùng riêng, chạy
`docker compose exec app php artisan migrate:fresh --seed`.
Lệnh này **xóa toàn bộ bảng trong database đích**. Seeder demo chỉ dùng cho môi trường phát triển.


Dự án phát triển ứng dụng Web sử dụng framework **Laravel** kết hợp môi trường container hóa bằng **Docker** (Nginx + PHP 8.4-FPM + MySQL 8.0).

---

## Yêu cầu môi trường

### Hiệu năng Docker trên Windows

Thư viện Composer được lưu trong named volume `vendor_data` trên Linux để tránh đọc hàng nghìn
file PHP qua bind mount Windows mỗi request. Source vẫn mount trực tiếp nên sửa code có hiệu lực
tự động. Sau khi clone hoặc thay đổi `composer.lock`, chạy
`docker compose exec app composer install --optimize-autoloader` (hoặc chạy setup).
Thư mục vendor trên máy Windows không đại diện cho thư viện đang dùng trong container.
Không cần xóa database hay volume khi cập nhật mã nguồn.
PHP OPcache được cấu hình tại `docker/php/performance.ini`; sau khi sửa file ini chạy
`docker compose up -d --force-recreate app` để áp dụng. Source PHP vẫn được kiểm tra thay đổi mỗi 2 giây.

- Đã cài đặt và đang bật **[Docker Desktop](https://www.docker.com/products/docker-desktop/)**.
- Đã cài đặt **Git**.

---

## Hướng dẫn cài đặt & chạy dự án

Sau khi clone repository về máy, bạn có thể chọn **1 trong 2 cách** dưới đây:

### Cách 1: Tự động hóa 1-Click (Khuyên dùng - Nhanh nhất ⚡)

- **Trên Windows:** Chỉ cần **nhấp đúp chuột** vào file **`setup.bat`** (hoặc mở terminal gõ `.\setup.bat`).
- **Trên macOS / Linux:** Mở terminal gõ:
  ```bash
  chmod +x setup.sh && ./setup.sh
  ```
> Script sẽ tự động: Copy `.env` -> Bật Docker -> Cài Composer -> Tạo APP_KEY -> Chạy Migration Database từ A đến Z!

---

### Cách 2: Chạy thủ công từng bước

Mở terminal (PowerShell, Bash hoặc CMD) tại thư mục dự án và thực hiện tuần tự:

#### 1. Tạo file cấu hình môi trường
```bash
# Trên Linux/macOS/Git Bash:
cp .env.example .env

# Trên Windows PowerShell:
copy .env.example .env
```

#### 2. Khởi động và build các container Docker
```bash
docker compose up -d --build
```

#### 3. Cài đặt các gói thư viện PHP (Composer)
```bash
docker compose exec app composer install
```

#### 4. Tạo mã khóa ứng dụng (Application Key)
```bash
docker compose exec app php artisan key:generate
```

#### 5. Chạy Migration tạo các bảng trong cơ sở dữ liệu
```bash
docker compose exec app php artisan migrate
```

---

## Thông tin cổng kết nối & Dịch vụ

| Dịch vụ | Địa chỉ truy cập / Host | Cổng (Port) | Ghi chú |
| :--- | :--- | :---: | :--- |
| **Ứng dụng Web (Nginx)** | [http://localhost:8081](http://localhost:8081) | `8081` | Mở trực tiếp trên trình duyệt |
| **MySQL (Từ máy thật)** | `localhost` hoặc `127.0.0.1` | `3308` | Dùng kết nối DBeaver, Navicat, HeidiSQL |
| **MySQL (Nội bộ Docker)** | `db` | `3306` | Dành riêng cho code Laravel cấu hình qua file `.env` |

### Thông tin đăng nhập CSDL mặc định:
- **Database:** `laravel`
- **Username:** `root`
- **Password:** `root`

---

## Các lệnh Docker thường dùng trong quá trình làm việc hàng ngày

- **Khởi động hệ thống:**
  ```bash
  docker compose up -d
  ```
- **Dừng toàn bộ container:**
  ```bash
  docker compose down
  ```
- **Kiểm tra trạng thái các container:**
  ```bash
  docker compose ps
  ```
- **Xem log hệ thống (khi cần debug lỗi):**
  ```bash
  docker compose logs -f
  ```
- **Chạy lệnh Artisan bên trong container:**
  ```bash
  docker compose exec app php artisan <tên_lệnh>
  ```
