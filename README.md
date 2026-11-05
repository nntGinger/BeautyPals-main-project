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

---

## Dữ liệu mẫu (Database Seeding) & Tài khoản Demo

### 1. Lệnh làm mới CSDL và nạp dữ liệu mẫu:
```bash
docker compose exec app php artisan migrate:fresh --seed
```
*Lệnh trên sẽ xóa và tạo lại toàn bộ bảng, sau đó chạy toàn bộ seeder demo.*

### 2. Danh sách tài khoản Demo:
*Tất cả tài khoản demo đều dùng mật khẩu:* **`Password123!`** *(hoặc `password` nếu seed bản cũ)*

| Vai trò / Tình huống | Email chính (Test suite) | Email alias | Mật khẩu | Trạng thái | Mô tả & Mục đích kiểm thử |
| :--- | :--- | :--- | :---: | :---: | :--- |
| **Admin** | `admin@beautypals.test` | `admin@example.com` | `Password123!` | `active` | Quản trị viên hệ thống, duyệt gian hàng, kiểm duyệt sản phẩm, xem audit logs |
| **Vendor đã duyệt 1** | `vendor@beautypals.test` | `vendor@example.com` | `Password123!` | `active` | Chủ gian hàng `BeautyPals Official Store` (được duyệt, quản lý kho & đơn con) |
| **Vendor đã duyệt 2** | `vendor2@example.com` | — | `Password123!` | `active` | Chủ gian hàng `An Minh Cosmetics Store` (phục vụ test giỏ hàng đa gian hàng) |
| **Customer chính** | `customer@beautypals.test` | `customer@example.com` | `Password123!` | `active` | Khách hàng demo (có sẵn 2 địa chỉ giao hàng, giỏ hàng, wishlist và đơn hàng mẫu) |
| **Customer thứ 2** | `customer2@example.com` | — | `Password123!` | `active` | Khách hàng demo thứ hai (`Nguyễn Văn An`, có đơn hàng chờ xác nhận) |
| **Vendor chờ duyệt** | `vendor.pending@beautypals.test` | — | `Password123!` | `active` | Chủ gian hàng `Pending Glow Shop` (test chặn vào `/vendor`, điều hướng `/vendor/pending`) |
| **Tài khoản bị khóa** | `locked@beautypals.test` | — | `Password123!` | `locked` | Test từ chối đăng nhập và tự động hủy phiên khi tài khoản bị khóa giữa chừng |
| **Vendor bị từ chối** | `vendor.rejected@example.com` | — | `Password123!` | `active` | Chủ gian hàng `Fake Brand Store` (test trạng thái reject và lý do từ chối) |

---

### 3. Dữ liệu mẫu đã được nạp bao gồm:

#### a. Danh mục & Thương hiệu (Catalog Taxonomy)
- **Thương hiệu:** 8 thương hiệu mỹ phẩm chính hãng hàng đầu: `Innisfree`, `La Roche-Posay`, `CeraVe`, `The Ordinary`, `Paula's Choice`, `Anessa`, `Klairs`, `MAC`.
- **Danh mục phân cấp:**
  - `Chăm sóc da mặt` ➔ `Sữa rửa mặt`, `Tẩy trang`, `Toner & Nước cân bằng`, `Serum & Tinh chất`, `Kem dưỡng ẩm`, `Kem chống nắng`.
  - `Trang điểm` ➔ `Son môi`, `Phấn nền & Cushion`.
  - `Chăm sóc cơ thể` ➔ `Sữa tắm & Dưỡng thể`.
- **Phân loại loại da (Skin Types):** `Da dầu`, `Da khô`, `Da nhạy cảm`, `Da hỗn hợp`, `Da thường`, `Da mụn`.
- **Thành phần mỹ phẩm & Bí danh (Ingredients & Aliases):** Niacinamide (Vitamin B3), Hyaluronic Acid (HA), Salicylic Acid (BHA), Chiết xuất rau má (Centella/Cica), Ceramide NP, Kẽm PCA (Zinc PCA), Retinol, Chiết xuất trà xanh, Panthenol (Vitamin B5), Vitamin E.

#### b. Sản phẩm & Biến thể (Products & SKUs)
- **Kem Dưỡng Ẩm Trà Xanh Innisfree Green Tea Seed Hyaluronic Cream:** 2 SKU (`INN-GTC-50ML` - 450.000đ, `INN-GTC-80ML` - 620.000đ).
- **Kem Chống Nắng La Roche-Posay Anthelios UVMune 400 Oil Control:** SKU `LRP-ANT-50ML` - 485.000đ.
- **Sữa Rửa Mặt CeraVe Foaming Cleanser:** 2 SKU (`CRV-FOAM-236ML` - 280.000đ, `CRV-FOAM-473ML` - 430.000đ).
- **Tinh Chất The Ordinary Niacinamide 10% + Zinc 1%:** 2 SKU (`TO-NIA-30ML` - 195.000đ, `TO-NIA-60ML` - 340.000đ).
- **Nước Hoa Hồng Klairs Supple Preparation Unscented Toner:** SKU `KLA-TON-180ML` - 310.000đ.
- **Son Thỏi MAC Matte Lipstick - Màu Chili Đỏ Gạch:** SKU `MAC-LIP-CHILI` - 610.000đ.
- **Sữa Chống Nắng Anessa Perfect UV Skincare Milk 60ml:** SKU `ANE-MILK-60ML` - 550.000đ.
- **Sản phẩm Nháp & Chờ duyệt:** 1 sản phẩm `draft` (`BP-CICA-DRAFT-50ML`) và 1 sản phẩm `pending` (`ANM-VITC-PENDING`) phục vụ kiểm thử phân quyền duyệt bài.

#### c. Quản lý kho & Lô hàng (Inventory & FEFO)
- Tất cả các SKU đều được cấp lô hàng (`batches`) có ngày sản xuất, hạn sử dụng rõ ràng.
- Riêng SKU `INN-GTC-50ML` có **2 lô hàng**:
  - `LOT-2026-001`: Hạn dùng gần hơn (còn 6 tháng) ➔ Kiểm thử logic xuất kho ưu tiên cận date trước (**FEFO**).
  - `LOT-2026-002`: Hạn dùng xa hơn (còn 24 tháng).
- Toàn bộ lô nhập đều sinh bản ghi lịch sử di chuyển kho (`stock_movements` - `inbound_purchase`).

#### d. Mã giảm giá (Vouchers)
- `WELCOME50`: Giảm 50.000đ cho đơn từ 200.000đ (còn hạn).
- `BEAUTY10`: Giảm 10% tối đa 100.000đ cho đơn từ 300.000đ (còn hạn).
- `FREESHIP`: Giảm 30.000đ phí vận chuyển cho đơn từ 150.000đ (còn hạn).
- `EXPIRED20`: Mã đã hết hạn (phục vụ test báo lỗi voucher không hợp lệ).

#### e. Quy tắc hoa hồng (Commission Rules)
- Tỷ lệ mặc định toàn sàn: `10.00%`.
- Danh mục `Chăm sóc da mặt`: `12.00%`.
- Danh mục `Trang điểm`: `15.00%`.

#### f. Đơn hàng mẫu & Tương tác (Orders & Social)
- **Đơn hàng `ORD-2026-1001` (Hoàn tất):** Khách mua 2 món từ `BeautyPals Official Store`, áp mã `FREESHIP`, thanh toán COD, đầy đủ lịch sử trạng thái đơn con và phân bổ lô hàng.
- **Đơn hàng `ORD-2026-1002` (Đang xử lý - Đa gian hàng):** Đơn hàng chia thành 2 đơn con: 1 đơn con của `BeautyPals Official Store` đang giao (`shipped`), 1 đơn con của `An Minh Cosmetics Store` đã xác nhận (`confirmed`).
- **Đơn hàng `ORD-2026-1003` (Chờ xử lý):** Khách thứ 2 đặt son MAC, trạng thái `pending`.
- **Đánh giá (Reviews):** Đã có đánh giá 5 sao kèm nhận xét thực tế cho các sản phẩm đã hoàn tất đơn hàng.
- **Giỏ hàng & Danh sách yêu thích:** Khách demo có sẵn 1 sản phẩm trong giỏ hàng và 2 sản phẩm trong wishlist.
- **Thông báo (Notifications):** Thông báo chào mừng và thông báo cập nhật đơn hàng thành công.
