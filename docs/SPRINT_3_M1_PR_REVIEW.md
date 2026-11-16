# Checklist & Báo Cáo Review PR Sprint 3 — Task `S3-02` (M1: Nguyễn Nữ Trà Giang)

> **Phạm vi:** Kiểm soát chất lượng mã nguồn, transaction, thứ tự khóa dòng (`lockForUpdate`), tính idempotent và phân quyền cho các PR thuộc Sprint 3 trước khi hợp nhất vào `main`.

---

## 1. Checklist Review PR 5 Điểm Của Trưởng Nhóm (M1)

Mọi PR Sprint 3 muốn được merge vào `main` bắt buộc vượt qua đủ **5 tiêu chí** sau:

1. **Không đặt logic nghiệp vụ trong Controller:** Controller chỉ nhận/validate `Request` (hoặc `FormRequest`), gọi `$this->authorize(...)`, rồi ủy quyền hoàn toàn cho tầng `Service` (`CheckoutService`, `ReservationService`, `InventoryService`, `OrderStatusService`, `BatchService`, `PaymentService`).
2. **Bắt buộc có `DB::transaction` và `lockForUpdate()` đúng thứ tự cố định:**
   - Thứ tự khóa chuẩn cho luồng đơn/tồn kho: `Order` → `VendorOrder` → `OrderItemAllocation` → `Batch` (`exp_date ASC, id ASC` hoặc `batch_id ASC, id ASC`).
   - Thứ tự khóa chuẩn cho luồng thanh toán: `Order` → `Payment`.
3. **Tính Idempotent & Chống ghi trùng:**
   - `CheckoutService`: chặn trùng `idempotency_key`.
   - `InventoryService`: `commitForVendorOrder()` & `releaseForVendorOrder()` chỉ xử lý `OrderItemAllocation` có `status = 'held'`.
   - `PaymentService`: `handleCallback()` kiểm tra `txn_ref` & trạng thái hiện tại để gọi lặp lại không tạo bản ghi `payments` hay `audit_logs` trùng.
4. **Kiểm tra phân quyền chặt chẽ (Policy + Middleware):**
   - Vendor A bị chặn (403) khi xem/đổi trạng thái `VendorOrder` hoặc điều chỉnh `Batch` của Vendor B.
   - Customer A bị chặn (403) khi xem/hủy `Order` hoặc mở màn hình thanh toán của Customer B.
   - Endpoint webhook `/payments/callback` phải kiểm tra chữ ký HMAC-SHA256 trước khi chạm CSDL.
5. **Code Style & Kiểm thử tự động (CI xanh):**
   - Chạy `./vendor/bin/pint --test` sạch trên toàn bộ file PHP thay đổi.
   - Chạy `php artisan test` đạt 100% PASS.

---

## 2. Kết Quả Review Thực Tế Các Nhánh Sprint 3 Hiện Có Trên `origin`

### 2.1. Nhánh `origin/feat/s3-04-05-06-vendor-orders-inventory` (PR #4 — M2, commit `cb8398c`)
* **Phạm vi:** `S3-04` (`Vendor\OrderController`), `S3-05` (`BatchService::adjust`, `Vendor\InventoryController`), `S3-06` (`ExpireBatchesCommand` `batches:expire`).
* **Đánh giá theo Checklist:**
  * `[PASS]` Tầng Service/Transaction/Locking: `InventoryService::process()` khóa `VendorOrder` → `OrderItemAllocation (status='held', orderBy batch_id, id)` → `Batch`; `BatchService::adjust()` khóa `Batch` và chặn `qty_on_hand + delta < qty_reserved`.
  * `[PASS]` Authorization: `BatchPolicy` và `VendorOrderPolicy::updateStatus` cô lập theo `vendor_id`.
  * `[PASS]` Sửa lỗi nền trên `main`: Đã đổi `Ingredient::scopeSearch()` từ `ESCAPE '\\'` sang `ESCAPE '!'` giúp 3 test tìm kiếm thành phần chạy xanh trên SQLite.
  * `[LƯU Ý HỢP NHẤT]` Nhánh có chứa file tạm giao thoa với M3/M4 (`app/Services/Inventory/InventoryService.php`, `app/Services/OrderStatusService.php`) và bổ sung migration `add_reason_to_stock_movements` + `scheduler` service trong `docker-compose.yml`. Cần thống nhất bản `OrderStatusService` khi merge cùng M4.

### 2.2. Nhánh `origin/feat/s3-09-10-order-lifecycle` (M4, commits `bbb0d46`, `1fc9408`)
* **Phạm vi:** `S3-09` (`OrderStatusService::transition`, `cancelByCustomer`), `S3-10` (`deriveParentStatus`, `CompleteDeliveredOrdersCommand` `orders:complete {--days=7}`).
* **Các điểm cần chỉnh trước khi merge vào `main`:**
  1. **Xung đột tại `app/Http/Controllers/Vendor/OrderController.php`:** Nhánh M4 hiện chỉ định nghĩa phương thức `transition()` trong `Vendor\OrderController` (không chứa `index()` và `show()` của M2). Nếu merge M4 đè lên M2 sẽ làm mất trang danh sách & chi tiết đơn hàng Vendor. **Yêu cầu:** Giữ `index()` và `show()` từ M2 (`cb8398c`), giữ `transition()` đã bắt thêm `ValidationException` từ M4.
  2. **Không nhất quán tên Policy ability (`update` vs `updateStatus`):** M2 kiểm tra `$this->authorize('updateStatus', $vendorOrder)` và định nghĩa `VendorOrderPolicy::updateStatus`, trong khi M4 kiểm tra `$this->authorize('update', $vendorOrder)`. **Yêu cầu:** Khi hợp nhất, giữ cả `update` và `updateStatus` trên `VendorOrderPolicy` hoặc chuẩn hóa một tên thống nhất.
  3. **Xung đột chữ ký kiểm tra quyền trong `OrderStatusService::transition()`:** Bản M2 gọi `Gate::forUser($actor)->authorize('updateStatus', $vendorOrder)` ngay đầu hàm `transition()`. Tuy nhiên trong nhánh M4, `cancelByCustomer()` cũng gọi lại `this->transition(..., OrderStatus::CANCELLED, $customer, $reason)` — vì `VendorOrderPolicy` trả về `false` cho Customer, nếu giữ `Gate::authorize('updateStatus')` bên trong `transition()` thì Customer hủy đơn sẽ bị lỗi 403! **Yêu cầu:** Giữ cơ chế phân quyền ở `Vendor\OrderController` (cho Vendor) và trong `cancelByCustomer()` (`$locked->customer_id === $customer->id` cho Customer), đồng thời giữ phần validate `reason` bắt buộc khi `$target === OrderStatus::REJECTED` bên trong `OrderStatusService::transition()`.
  4. **Hợp nhất `routes/console.php`:** Giữ cả hai lịch hằng ngày `batches:expire` (M2) và `orders:complete` (M4).
  5. **Độ ổn định khóa dòng `[PASS]`:** Cả `OrderStatusService::transition()`, `cancelByCustomer()`, `deriveParentStatus()` và `CompleteDeliveredOrdersCommand` đều giữ đúng thứ tự khóa `Order` → `VendorOrder` (sắp xếp `orderBy('id')`) → `OrderItemAllocation` → `Batch`.

### 2.3. Hiện trạng các nhánh chưa nộp & Thứ tự Merge Sprint 3 vào `main`
* **Cảnh báo kỹ thuật trên `main` hiện tại (`ddb7ce9`):**
  1. `app/Models/Ingredient.php` vẫn đang dùng `ESCAPE '\\'`, gây lỗi 3 test trên SQLite (`IngredientManagementTest`, `IngredientSearchTest`); sẽ tự hết khi merge PR #4 của M2 (`cb8398c`).
  2. Hiện có 14 file PHP cũ từ các nhánh Sprint 1–2 (`CartException`, `VendorController`, `RegistrationController`, `ProductSearchService`, `VendorStatusService`, v.v.) chưa đạt chuẩn Pint đầy đủ; `.github/workflows/ci.yml` được cấu hình kiểm tra Pint trên tập file thay đổi của từng PR để chặn code mới sai format mà không làm nghẽn tiến độ trước khi cả nhóm format đồng loạt ở Sprint 5.
* **Thứ tự Merge Sprint 3 chuẩn xác:**
  1. **M3** (`feat/s3-07-08-fefo-inventory` — `ReservationService`, `CheckoutService`): *Đang chờ M3 mở PR*.
  2. **M2** (`origin/feat/s3-04-05-06-vendor-orders-inventory` — PR #4).
  3. **M4** (`origin/feat/s3-09-10-order-lifecycle`): rebase theo M2 & M3, áp dụng 4 điểm chỉnh sửa ở mục 2.2.
  4. **M1** (`feat/s3-01-02-payment-and-review`): hoàn thiện `S3-01` (`PaymentService`) và `S3-02` (CI + PR review checklist).
  5. **M5** (`S3-12`, `S3-13` — Giao diện Giỏ hàng, Checkout, Đơn của tôi): *Đang chờ M5 mở PR*.
  6. **M6** (`S3-15`, `S3-16` — `CheckoutAndFefoTest`): *Đang chờ M6 mở PR*.

