# S4-05 Sửa lỗi nguồn bán và hướng dẫn kiểm thử

Nhánh triển khai: `feat/s4-05-sprint3-bugfixes`, dựa trên main `e4bd4b9`.

## Bug log

| Mã | Trước khi sửa | Nguyên nhân và cách sửa | Bằng chứng sau sửa |
| --- | --- | --- | --- |
| S4-M2-01 | Lô active có HSD hôm nay được catalog báo còn hàng và thêm vào giỏ, nhưng checkout báo thiếu tồn. | Catalog/Cart dùng >=, FEFO dùng >. Chuẩn hóa điều kiện HSD sau ngày nghiệp vụ; DATE() trong SQL tổng hợp để SQLite và MySQL cùng xử lý ngày đúng. | Catalog không báo còn hàng; thêm/cập nhật giỏ và checkout giỏ cũ đều chặn lô hôm nay. |
| S4-M2-02 | Lúc 01:00 Việt Nam, checkout vẫn giữ chỗ được lô hết hạn hôm đó vì ngày UTC còn hôm trước. | Thêm InventoryDate::today() theo Asia/Ho_Chi_Minh; Catalog, Cart, FEFO và batches:expire dùng chung ngày này. Giữ nguyên múi giờ timestamp toàn ứng dụng. | Test tại 23:59:59 và 00:00:00 Việt Nam: chỉ trước nửa đêm còn mua được; không cần đợi job hết hạn. |
| S4-M2-03 | Chủ Vendor bị khóa: catalog vẫn hiện hàng và thêm vào giỏ được, checkout mới chặn. | Catalog lọc tài khoản chủ active; Cart kiểm tra owner bị khóa như Checkout đã có. | Vendor khóa: không có trong catalog, không thêm/cập nhật giỏ hoặc checkout được; mở khóa thì mua lại được nếu lô còn hạn. |

## Mở web và tài khoản demo

Web: http://localhost:8081/login. Tài khoản riêng, mật khẩu chung `Password123!`:

| Email | Mục đích |
| --- | --- |
| customer.s4@beautypals.test | Kiểm tra giỏ hàng và checkout |
| vendor.s4@beautypals.test | Xem lô demo và lượng giữ chỗ |
| admin.s4@beautypals.test | Kiểm tra khóa/mở Vendor |
| vendor.locked.s4@beautypals.test | Chủ cửa hàng demo đang bị khóa, không dùng đăng nhập khi khóa |

Giỏ Customer có bốn dòng: HSD hôm qua, HSD hôm nay, Còn hạn và Vendor bị khóa. Các dòng không hợp lệ cố ý được dựng như giỏ đã thêm từ trước khi hết hạn/khóa; việc nhìn thấy chúng trong giỏ không đồng nghĩa được mua. Hệ thống kiểm tra lại khi cập nhật hoặc checkout. Seeder chỉ tạo dữ liệu còn thiếu, không khôi phục dòng giỏ đã xóa hoặc đặt lại thao tác/mật khẩu đã thay đổi. Ngày HSD demo lấy tại lần đầu chạy seeder; nếu test vào ngày khác, xem HSD thực trong kho và dùng test tự động cho mốc hôm nay.

## Test giỏ hàng và checkout

1. Đăng nhập Customer demo, mở http://localhost:8081/customer/cart.
2. Ở dòng **HSD hôm nay**, tăng số lượng rồi **Cập nhật**. Cần bị chặn với thông báo có `available: 0`; lượng đã lưu không tăng.
3. Thử tương tự với **HSD hôm qua**: cũng bị chặn.
4. Dòng **Vendor bị khóa**, tăng số lượng và cập nhật: cần báo Vendor không được phép bán.
5. Dòng **Còn hạn**, đổi số lượng trong khoảng 1–10 và cập nhật: thành công nếu không vượt tồn khả dụng.
6. Bấm **Tiến hành đặt hàng COD**, chọn địa chỉ và **Đặt hàng COD** khi vẫn còn ba dòng không hợp lệ: bị chặn, không tạo đơn hoặc giữ chỗ mới.
7. Quay lại giỏ, xóa hai dòng hết hạn và dòng Vendor bị khóa, giữ **Còn hạn**. Checkout COD: tạo được đơn và xem chi tiết đơn.
8. Đăng nhập Vendor demo, mở **Tồn kho**. `S4-LOT-FUTURE` tăng lượng giữ chỗ đúng số lượng đã mua. Hai lô hết hạn không được dùng để phân bổ.

Trên máy đã thử cập nhật hàng hôm nay (bị chặn), Vendor khóa (bị chặn), hàng còn hạn (thành công, số lượng 2), checkout giỏ không hợp lệ (bị chặn). Chưa xóa ba dòng lỗi để người nghiệm thu tiếp tục test.

## Test catalog

Mở http://localhost:8081/api/catalog/products?q=s4-demo&in_stock=1. Đây là API JSON hiện có của dự án. Khi Vendor demo khóa vẫn đang khóa, kết quả chỉ có `S4 DEMO - Còn hạn`.

Mở http://localhost:8081/api/catalog/products?q=s4-demo để xem cả sản phẩm hết hàng: hai sản phẩm hết hạn có `in_stock: 0`; sản phẩm thuộc Vendor bị khóa không xuất hiện. Catalog có thể vẫn liệt kê sản phẩm hết hàng khi không bật in_stock=1; đó là hành vi hiện có.

## Test mở khóa Vendor

1. Dùng trình duyệt khác/cửa sổ riêng đăng nhập `admin.s4@beautypals.test`, mở `/admin/users`.
2. Tìm email `vendor.locked.s4@beautypals.test`, nhập lý do và mở khóa.
3. Tải lại API catalog còn hàng: sản phẩm `S4 DEMO - Vendor bị khóa` xuất hiện. Tên sản phẩm là nhãn tình huống demo, không tự đổi khi mở khóa.
4. Trong giỏ Customer còn dòng tương ứng, thử cập nhật số lượng: thành công nếu còn tồn.
5. Khóa lại tài khoản demo kèm lý do để khôi phục tình huống thử. Giỏ vẫn có thể giữ dòng cũ, nhưng cập nhật/checkout bị chặn.

## Test mốc nửa đêm và chạy lại tự động

Không cần đổi giờ Windows hoặc sửa HSD trên database. Bộ test giả lập ngày giờ trong môi trường test:

```powershell
docker compose exec app php artisan test --filter=SprintFourSellabilityTest
docker compose exec app php artisan test
```

`SprintFourSellabilityTest` có 5 ca (65 assertions), bao gồm ba bộ dữ liệu HSD hôm qua/hôm nay/ngày mai, khóa/mở Vendor và ranh giới nửa đêm. Test kiểm tra cả HTTP và checkout/FEFO; thất bại không sinh đơn hoặc allocation mồ côi. Các lô thử HSD vẫn active trước job để chứng minh nguồn bán tự loại theo ngày.

Kết quả đã chạy:

- Toàn bộ suite SQLite: **212 passed, 1318 assertions**.
- MySQL riêng `beautypals_s4_test`: **42 passed, 280 assertions** cho SprintFourSellabilityTest, Uc04ProductSearchTest, CartServiceTest, CheckoutAndFefoTest.
- Kiểm tra web thật trên MySQL: các bước cập nhật giỏ, catalog và chặn checkout như trên đã đúng kết quả.

## Khởi động và dữ liệu demo

```powershell
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=CommissionRuleSeeder
docker compose exec app php artisan db:seed --class=SprintFourBugfixDemoSeeder
```

Không cần migration mới cho bản sửa này. Docker app, webserver, db và scheduler dùng cấu hình hiện có, web tại cổng 8081.
