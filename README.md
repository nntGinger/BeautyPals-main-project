# Đồ Án Lập Trình Ứng Dụng Web

Dự án phát triển ứng dụng Web sử dụng framework **Laravel** kết hợp môi trường container hóa bằng **Docker** (Nginx + PHP 8.4-FPM + MySQL 8.0).

---

## Yêu cầu môi trường
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
