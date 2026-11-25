# AI Development Log Sprint 3 · S3-16 · Trang (M6)

Ngày tổng hợp và chạy lại 25/11/2026. Phạm vi của log là `CheckoutAndFefoTest.php` (S3-15), bằng chứng kiểm thử và Bug Log (S3-16).

| Yêu cầu được giao | AI hỗ trợ | Cách kiểm chứng và kết quả |
| --- | --- | --- |
| Xác định đầu ra S3-15 và S3-16. | Codex đọc dòng 34–35 của sheet **Theo dõi tiến đồ án nhóm** trong bảng phân công và đối chiếu nhánh `feat/s3-15-checkout-fefo-tests`. | Bảng yêu cầu bốn kịch bản chính trong PHPUnit, ảnh chạy xanh và ít nhất một lỗi thực tế có nguyên nhân, cách sửa. Commit `98cf215` chứa bản test của Trang và `1403b07` chứa ảnh kết quả gốc. |
| Đối chiếu phạm vi bảo vệ hồi quy. | Codex đọc sáu phương thức test và các assertion trong commit `98cf215`. | Xác nhận bốn luồng chính trong bảng bên dưới. Hai ca bổ sung bao phủ hủy đơn từ giao diện Customer, thao tác giỏ hàng và phân trang lịch sử đơn. |
| Ghi nhận một lỗi thực tế cho Bug Log. | Codex đọc diff `006a8e9` để đối chiếu cách sửa trang chi tiết đơn. | Diff đổi kiểu tham số callback từ `Builder` sang `HasMany`. Kết quả hồi quy trên bản đã sửa được ghi trong [Bug Log](SPRINT_3_M6_BUG_LOG.md). |
| Hoàn thiện hai log dựa trên kết quả chạy thực tế. | Codex chạy đúng nhóm `CheckoutAndFefoTest` trên nhánh S3 bằng Docker Compose, sau đó đối chiếu lại kết quả với bằng chứng bàn giao. | Commit chạy lại `1403b07`, SQLite `:memory:`. **6 ca đạt, 134 assertions**, exit code 0. [Lệnh chạy và vị trí ảnh gốc](SPRINT_3_M6_BUG_LOG.md#kết-quả-kiểm-thử-và-minh-chứng). |

## Bốn kịch bản chính đã đối chiếu

| Kịch bản | Điểm xác minh trong test |
| --- | --- |
| Checkout ba Vendor | Một đơn cha, ba đơn con, đúng tiền và hoa hồng, giỏ được làm trống. Gửi lại cùng `idempotency_key` trả về đơn cũ và không ghi trùng đơn, thanh toán hay giữ tồn. |
| FEFO | Bỏ qua lô hết hạn. Khi đặt 150, giữ 100 ở lô hạn gần và 50 ở lô hạn xa. |
| Chống bán vượt tồn | Với tồn kho bằng 1, checkout đầu thành công, checkout sau bị từ chối. Đơn, thanh toán, lịch sử và giữ tồn của lượt đầu không bị ghi trùng. |
| Vòng đời đơn con | Hủy nhả giữ tồn một lần, giao đi trừ tồn một lần, đơn cha ở `processing` khi còn đơn con hoạt động và chuyển `completed` sau khi đơn con còn lại đủ hạn. |

Log ghi lại các bước Codex thực hiện có thể kiểm chứng trong phiên bàn giao. Lịch sử Git và ảnh kết quả được dùng làm căn cứ cho công việc S3 đã có trước phiên này. Nhánh S3 vẫn cần được bàn giao lên GitHub của nhóm.
