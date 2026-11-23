# Sprint 5 M2 S5-06 và S5-04

Nhánh triển khai `feat/s5-06-04-catalog-inventory`, nền main `f3b0f8c`. Thực hiện S5-06 trước, sau đó S5-04.

## S5-06 Kiểm thử và sửa lỗi Catalog Kho

Thêm `tests/Feature/CatalogAndBatchTest.php` với 10 ca: tạo sản phẩm chỉ nhận field được phép; SKU chuẩn hóa; cập nhật giữ trạng thái xét duyệt; sắp xếp thành phần và giữ concentration; chặn SKU ngoại/đụng mã; không xóa SKU/sản phẩm đã có lô; cô lập Vendor; điều chỉnh có lý do và bảo vệ giữ chỗ; chặn xuất lô không còn hợp lệ; giới hạn số truy vấn danh sách.

Bug tái hiện trước sửa: sau khi reserve FEFO thành công, đổi lô sang expired/quarantined hoặc để HSD đến hôm nay, rồi chuyển đơn packed → shipped. Hệ thống cũ vẫn trừ tồn vật lý và ghi commit. Ba ca regression đều thất bại trước sửa.

Sửa `InventoryService`: kiểm tra lại status active và HSD sau ngày nghiệp vụ Việt Nam dưới khóa Batch, trước mọi ghi xuất kho. Nếu bất kỳ lô nào không hợp lệ, rollback chuyển trạng thái/lịch sử/tồn. Nhả giữ chỗ vẫn được phép trên lô expired/quarantined. Không tự thay lô đã giữ hoặc thay quy tắc State Machine; đơn đang packed bị chặn xuất cần quy trình xử lý lô/đơn của nhóm, không tự nhả hàng khi báo lỗi.

Các lỗi HSD, múi giờ và khóa Vendor từ S4-05 tiếp tục được kiểm tra qua `SprintFourSellabilityTest`. Tìm kiếm INCI/Alias qua `IngredientSearchTest` xanh; không thêm thay đổi tìm kiếm khi không tái hiện được lỗi mới.

## S5-04 Truy vấn và index

| Vị trí | Thay đổi |
| --- | --- |
| Catalog | Chuyển kiểm tra schema từ từng request sang lệnh `catalog:check-schema`; CI và README gọi sau migrate. Listing còn count + page, không còn 16 truy vấn metadata mỗi lần. |
| Catalog còn hàng | Dùng kết quả của EXISTS đã lọc SKU, tránh lặp phép dò tồn trong cột in_stock. Giữ nguyên min-price của SKU hợp lệ. |
| HSD | Dùng `exp_date >= ngày mai Việt Nam` cho nguồn bán; `exp_date < ngày mai` cho command hết hạn. Tương đương quy tắc trước nhưng không bọc DATE() lên cột, cho phép dùng range index trên MySQL. Vẫn đúng với ngày lưu kèm 00:00:00 của SQLite. |
| products | `idx_products_status_newest (status, created_at DESC, id ASC)` khớp thứ tự newest và tie-breaker. |
| skus | `idx_skus_active_product_price (is_active, product_id, price)` bao phủ nhóm giá SKU active. |
| batches | `idx_batches_status_expiry (status, exp_date, id)` hỗ trợ công việc hết hạn toàn kho; giữ index FEFO theo sku_id đã có. |
| vendor_orders | `idx_vendor_orders_status_latest (vendor_id, status, id DESC)` hỗ trợ truy cập đơn mới nhất theo cửa hàng/trạng thái; giữ index ngày tạo/đối soát cũ. |

Migration mới có down() chỉ gỡ các index mới; đã thử rollback/reapply trên MySQL benchmark, truy vấn information_schema xác nhận không còn index S5 sau rollback. Bản ghi benchmark được giữ nguyên.

## Số đo MySQL local

Database riêng `beautypals_s5_benchmark`, không dùng dữ liệu web: 6.000 products, 12.000 skus, 48.000 batches, 12.000 vendor_orders. Mỗi ca warm-up một lần rồi lấy median của 5 lượt; đo PHP service/query, không phải thời gian tải trang qua mạng. Baseline chụp trước sửa S5-04; số sau là snapshot cuối sau tạo index và cập nhật thống kê MySQL. Số tuyệt đối phụ thuộc cache, thống kê optimizer và tải máy.

| Ca đo | Trước ms | Sau ms | Nhận xét |
| --- | ---: | ---: | --- |
| Catalog newest | 221.128 | 129.624 | 18 → 2 truy vấn |
| Catalog còn hàng + khoảng giá | 210.955 | 99.151 | 18 → 2 truy vấn, bỏ dò tồn lặp |
| products approved newest | 5.230 | 0.809 | EXPLAIN bỏ filesort, dùng idx_products_status_newest |
| SKU active nhóm giá | 12.442 | 5.744 | Using index với idx_skus_active_product_price |
| Tìm lô active hết hạn | 25.554 | 3.144 | ALL/filesort → range trên idx_batches_status_expiry |
| Đơn pending của Vendor | 3.079 | 2.529 | Chưa kết luận tăng tốc: optimizer vẫn chọn index cũ và filesort trên tập 200 đơn phù hợp. Không FORCE INDEX trong code ứng dụng. |

Tổng kết quả và SHA-256 dữ liệu trang của cả 6 ca khớp trước/sau. JSON kèm SQL, bindings và EXPLAIN tại `docs/performance/s5-before.json`, `s5-after.json`. Ca products/SKU/lô độc lập giúp tách ảnh hưởng của index; số catalog gồm cả thay đổi query. Index mới không được cam kết luôn là lựa chọn optimizer cho mọi phân bố dữ liệu, đặc biệt vendor_orders; cần tiếp tục đo với dữ liệu thật khi triển khai.

## Nghiệm thu

- SQLite toàn bộ suite: **222 tests passed, 1378 assertions**.
- MySQL riêng `beautypals_s5_test`: **102 tests passed, 683 assertions**, gồm CatalogAndBatchTest, IngredientSearchTest, SprintFourSellabilityTest, Uc04ProductSearchTest, CheckoutAndFefoTest, S3InventoryFefoTest, OrderStatusServiceTest, VendorOperationsTest.
- Laravel Pint --test: 10 file PHP thay đổi PASS.
- Schema web local: `catalog:check-schema` PASS; migration index đã áp dụng.

```powershell
docker compose exec app php artisan test --filter='CatalogAndBatchTest|IngredientSearchTest'
docker compose exec app php artisan test
docker compose exec app php artisan migrate
docker compose exec app php artisan catalog:check-schema
```

Trên web kiểm tra lại API `/api/catalog/products?in_stock=1`, lọc giá và chuyển trang; Vendor xem sản phẩm, lô và đơn của chính mình. Dữ liệu S4 demo trước đó vẫn dùng được; bản sửa này không seed/reset dữ liệu web.

Mốc giao hàng bị hết hạn/cách ly được giả lập an toàn bằng test, không cần sửa HSD hoặc trạng thái lô thật:

```powershell
docker compose exec app php artisan test --filter=shipping_revalidates_held_batch_but_release_remains_possible
```

## Chạy lại benchmark

Script `scripts/benchmark_s5.php` chỉ nhận MySQL database đúng tên `beautypals_s5_benchmark`. Nó tạo migration/dữ liệu giả nếu thiếu và không trỏ vào laravel (web). Không chạy với database dùng cho PHPUnit RefreshDatabase. Dùng CLI `php -d memory_limit=512M scripts/benchmark_s5.php after` trong container cùng các biến DB_* của database benchmark; báo cáo local ghi tại `.testing-local/s5-after.json`.

Tham số before chỉ dành cho baseline f3b0f8c trước áp dụng tối ưu (copy script vào checkout baseline để đo); script chặn gắn nhãn before trên code tối ưu. Bản JSON trước đã lưu nên không cần rollback code/web chỉ để đọc kết quả. Để lặp phép so sánh đầy đủ, dùng hai checkout và database benchmark riêng, cùng bộ dữ liệu; giữ nguyên môi trường khi đo.
