# Bug Log Sprint 3 · S3-16 · Trang (M6)

Ngày cập nhật 25/11/2026. Phạm vi gồm checkout COD, FEFO, chống bán vượt tồn và vòng đời đơn đã được kiểm thử trong S3-15. Căn cứ phân công là dòng 34–35 trong sheet **Theo dõi tiến đồ án nhóm** của `PTUDW.xlsx`.

## S3-16 · Lỗi khi mở chi tiết đơn của Customer

| Mục | Ghi nhận |
| --- | --- |
| Luồng ảnh hưởng | Customer mở chi tiết đơn của mình ở `GET /customer/orders/{order}`. |
| Kỳ vọng | Trang trả HTTP 200 và hiển thị chi tiết đơn cùng lịch sử trạng thái của các đơn con. |
| Lỗi và nguyên nhân | Trong mã trước `006a8e9`, callback eager loading cho `vendorOrders.statusHistories` khai báo tham số kiểu `Illuminate\Database\Eloquent\Builder`. Laravel truyền relation `HasMany` vào callback, nên kiểu tham số không khớp và có thể phát sinh `TypeError` khi mở đơn có đơn con. |
| Cách sửa | Commit `006a8e9` đổi tham số thành `Illuminate\Database\Eloquent\Relations\HasMany` tại `Customer\OrderController::show`. [Mã controller](../app/Http/Controllers/Customer/OrderController.php). |
| Kiểm tra lại | Trên nhánh S3 của Trang, ca checkout COD kết thúc bằng điều hướng về trang chi tiết đơn. Ca kiểm thử giao diện đơn sau đó xác nhận request chi tiết trả HTTP 200 và hiển thị mã đơn. |
| Trạng thái | Bản sửa đã có trong lịch sử repo. Kiểm thử hồi quy đạt trên `1403b07`. Nguyên nhân được đối chiếu từ diff `006a8e9`. Lượt 25/11 kiểm tra bản đã sửa. |

## Kết quả kiểm thử và minh chứng

- Nhánh `feat/s3-15-checkout-fefo-tests`. Commit test `98cf215`, commit chạy lại `1403b07`.
- Docker Compose, service `app`, SQLite trong bộ nhớ. Ngày 25/11/2026 chạy lại **6 ca đạt, 134 assertions**, exit code 0.
- Ảnh kết quả gốc nằm tại `docs/images/Ket-qua-chay-S3-16.jpg` trong commit `1403b07` trên nhánh S3. Mở file ảnh khi checkout nhánh S3.

```powershell
docker compose exec -T -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= app php artisan test --filter=CheckoutAndFefoTest --no-ansi
```

Ca chống bán vượt tồn kiểm tra hai lượt checkout nối tiếp với tồn kho bằng 1. Ca kiểm tra rollback của lượt thứ hai và dữ liệu tồn sau đó. Lượt chạy này dùng SQLite, nên chỉ xác nhận hành vi đó trên môi trường đã nêu.
