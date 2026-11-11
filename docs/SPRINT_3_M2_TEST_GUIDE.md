# Hướng dẫn nghiệm thu Sprint 3 của M2

Phạm vi: S3-04 (đơn hàng Vendor), S3-05 (điều chỉnh tồn kho) và S3-06 (đánh dấu lô hết hạn). Nhánh triển khai: `feat/s3-04-05-06-vendor-orders-inventory`.

## Mở website

- Website: http://localhost:8081/login
- Email demo riêng: `vendor.s3@beautypals.test`
- Mật khẩu: `Password123!`
- Sau đăng nhập, chọn **Đơn hàng** hoặc **Tồn kho** trên thanh điều hướng.

Dữ liệu demo có 20 đơn `S3-VO-001` đến `S3-VO-020`, một SKU và bốn lô `S3-LOT-NEAR`, `S3-LOT-FAR`, `S3-LOT-TODAY`, `S3-LOT-PAST`. Mỗi đơn có 3 sản phẩm, được phân bổ 2 đơn vị từ lô gần hạn và 1 đơn vị từ lô xa hạn. Đây là dữ liệu phân bổ mẫu để kiểm tra giao diện, không phải triển khai thuật toán checkout/FEFO S3-07.

Seeder chỉ thêm dữ liệu demo còn thiếu, không đặt lại trạng thái, số lượng hoặc mật khẩu đã sửa. Không cần chạy lại seeder trước mỗi lần test.

## S3-04 Kiểm tra đơn hàng

| Bước | Thao tác | Kết quả cần thấy |
| --- | --- | --- |
| 1 | Mở `/vendor/orders` | Chỉ đơn thuộc cửa hàng đang đăng nhập; tối đa 15 dòng mỗi trang; có Trang sau khi đủ dữ liệu. |
| 2 | Chọn Chờ xác nhận rồi Lọc đơn hàng | Chỉ có đơn pending; chuyển trang vẫn giữ bộ lọc. |
| 3 | Mở đơn pending chưa xử lý, ví dụ `S3-VO-014` | Thông tin người nhận, tổng tiền, sản phẩm, hai phân bổ lô và lịch sử trạng thái. |
| 4 | Bấm Xác nhận đơn | Trạng thái Đã xác nhận; chỉ còn nút Đã đóng gói; timeline ghi thời điểm và người xử lý. |
| 5 | Bấm Đã đóng gói rồi Bàn giao vận chuyển | Trạng thái Đang giao; phân bổ chuyển Đã xuất kho. Lô gần hạn giảm tồn thực tế và giữ chỗ 2; lô xa hạn giảm 1. |
| 6 | Bấm Xác nhận đã giao rồi Hoàn tất đơn | Đơn Hoàn tất; không còn nút chuyển trạng thái. |
| 7 | Mở một đơn pending khác, bấm Từ chối đơn khi bỏ trống lý do | Trình duyệt yêu cầu nhập lý do; server cũng kiểm tra khi gửi request trực tiếp. |
| 8 | Nhập lý do rồi Từ chối đơn | Đơn Đã từ chối; lý do xuất hiện trong timeline; phân bổ Đã nhả giữ chỗ; tồn thực tế không giảm. |
| 9 | Mở một đơn pending khác rồi Hủy đơn | Đơn Đã hủy; nhả giữ chỗ tương tự từ chối. |
| 10 | Ghi lại URL một đơn, đăng xuất, đăng nhập Vendor khác rồi mở URL đó | HTTP 403; không xem hoặc đổi trạng thái đơn cửa hàng khác. |

Để thử quyền bằng tài khoản đang có sẵn trong database, dùng `vendor@beautypals.test` nếu tài khoản này đã được seed trước đó. Dữ liệu Sprint 3 riêng không sửa mật khẩu của tài khoản cũ. Test tự động tạo hai Vendor riêng để kiểm chứng quyền, không phụ thuộc tài khoản demo cũ.

Thao tác test đã thực hiện trên máy: `S3-VO-016` chuyển đến Đang giao, `S3-VO-015` bị từ chối có lý do. Các đơn pending khác còn dùng được. Lịch sử thực tế trên web là nguồn kiểm tra trạng thái hiện tại.

## S3-05 Kiểm tra điều chỉnh kho

Mở `/vendor/inventory`, chọn **Xem / Điều chỉnh** ở `S3-LOT-NEAR`.

| Thao tác | Kết quả cần thấy |
| --- | --- |
| Nhập `5`, lý do `Kiểm kê thừa 5 sản phẩm`, bấm Lưu điều chỉnh | Tồn thực tế tăng 5; giữ chỗ và số lượng nhập ban đầu không đổi. Lịch sử có loại Điều chỉnh, số `+5`, lý do, người thực hiện và thời điểm. |
| Nhập `-3` kèm lý do | Tồn thực tế giảm 3, ghi lịch sử số âm. |
| Bỏ trống lý do | Bị chặn, không thay đổi tồn hoặc tạo lịch sử. |
| Nhập `0` hoặc số thập phân | Bị chặn. |
| Gọi H là tồn thực tế, R là giữ chỗ. Nhập `-(H - R + 1)` | Bị chặn vì tồn sau điều chỉnh nhỏ hơn giữ chỗ; không có lịch sử điều chỉnh mới. |
| Nhập `-(H - R)` khi H > R, kèm lý do | Được phép: tồn thực tế bằng đúng giữ chỗ. Nên dùng lô demo và tăng tồn lại sau khi kiểm tra. |
| Dùng Vendor khác mở URL lô hoặc POST điều chỉnh | HTTP 403. |

Trên điện thoại, bảng có thanh cuộn ngang để đọc đủ cột.

## S3-06 Kiểm tra hết hạn lô

Lệnh là chức năng backend, không có nút chạy trong giao diện Vendor. Mở PowerShell tại thư mục dự án:

```powershell
docker compose exec app php artisan batches:expire
```

Tải lại trang **Tồn kho**. Các lô active có HSD đến hôm nay hoặc trước hôm nay chuyển sang **Hết hạn**; lô còn hạn giữ nguyên. Số lượng tồn và giữ chỗ không bị xóa. Chạy lần hai sẽ không xử lý lại những lô đã expired.

Ngày nghiệp vụ và lịch chạy dùng múi giờ Việt Nam `Asia/Ho_Chi_Minh`. Job chạy hằng ngày lúc 00:00. Container `scheduler` chạy `php artisan schedule:work`; Docker cần hoạt động tại thời điểm đến lịch. Nếu máy tắt qua thời điểm đó, chạy `batches:expire` thủ công khi mở lại để cập nhật ngay.

```powershell
docker compose ps
docker compose exec app php artisan schedule:list
docker compose logs --tail=20 scheduler
```

Sau lần kiểm tra trên máy, hai lô demo TODAY và PAST có thể đã chuyển expired. Chạy lại command báo 0 là kết quả đúng khi không có lô active hết hạn mới.

Để chứng minh hai lô expired thực sự không đóng góp vào nguồn bán, chạy bộ kiểm tra local đã chuẩn bị trên máy này:

```powershell
docker compose exec -T app php .testing-local/verify-s3-expiry.php
```

Script tìm đúng hai lô TODAY/PAST, mô phỏng chạy command và tạm đặt lượng có thể bán của các lô khác về 0 trong transaction. Kết quả cần có: hai lô chuyển expired, catalog báo `in_stock = 0`, bộ lọc chỉ còn hàng loại sản phẩm khỏi kết quả, thêm 1 sản phẩm vào giỏ bị chặn với `available: 0`. Cuối cùng rollback toàn bộ mô phỏng, giữ nguyên tồn kho, trạng thái và giỏ hàng. Script nằm trong thư mục local ignored, không được đưa lên GitHub.

Nếu sản phẩm vẫn xuất hiện là còn hàng khi kiểm tra bình thường, hãy kiểm tra các lô NEAR/FAR: cùng SKU vẫn có thể bán từ các lô còn hạn. Không thể kết luận hai lô expired đang được bán chỉ từ việc sản phẩm còn xuất hiện. Kiểm tra này xác nhận nguồn hàng catalog và giỏ hàng; luồng reserve FEFO khi checkout cần kiểm tra tiếp khi S3-07 được tích hợp.

## Khởi động và kiểm thử lại

```powershell
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=SprintThreeDemoSeeder
docker compose exec app php artisan test --filter=VendorOperationsTest
docker compose exec app php artisan test
```

Test mặc định dùng SQLite `:memory:`, tách khỏi dữ liệu đang xem trên web. Bộ kiểm thử bao phủ lọc/phân trang, quyền sở hữu, eager loading phân bổ/lịch sử, trạng thái hợp lệ và request lỗi/cũ, bắt buộc lý do, xuất/nhả kho không lặp, rollback, dấu số lượng và người điều chỉnh, giới hạn giữ chỗ, mốc HSD hôm nay và lịch chạy.

## Ghi chú tích hợp cho nhóm

- `main` chưa có `OrderStatusService` và `InventoryService`. Bản local bổ sung phần điều phối tối thiểu cần cho nút chuyển trạng thái: transaction, xuất/nhả allocation held, cập nhật đơn cha. Đây là phần giao với S3-08/S3-09; cần đối chiếu với mã M3/M4 khi merge sau này. Không bao gồm checkout, reserve FEFO, customer cancel hoặc command tự hoàn tất sau 7 ngày.
- Giữ API sẵn có của State Machine; thêm `allowedTransitionsFor()` gọi lại `allowedTransitions()`. Giữ `statusHistories()` và thêm alias `statusHistory()`; bổ sung policy `updateStatus`.
- Schema hiện tại dùng `stock_movements.qty` làm delta có dấu. Bổ sung migration cột `reason` nullable để giữ dữ liệu lịch sử cũ, nhưng các điều chỉnh mới bắt buộc có lý do.
- `BatchService::adjust()` khóa dòng Batch, kiểm tra tồn và ghi movement trong cùng transaction; lý do cũng được kiểm tra trong service.
- Đã sửa lỗi có sẵn ở tìm kiếm Ingredient: câu SQL `ESCAPE` với dấu gạch chéo kép làm SQLite lỗi. Dùng `!` làm escape một ký tự, tương thích SQLite/MySQL, vẫn tìm `%` và `_` như ký tự thường.
- Script kiểm tra `.testing-local/verify-s3-expiry.php` chỉ có trên máy nghiệm thu. Các test chia sẻ trong `tests/Feature/VendorOperationsTest.php` chạy độc lập với dữ liệu local.
