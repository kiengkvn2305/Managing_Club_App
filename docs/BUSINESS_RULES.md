# Quy tắc nghiệp vụ

## Core đã triển khai

- User là danh tính toàn cục; `club_members` cấp quyền theo CLB; `player_profiles` là hồ sơ cầu thủ riêng, không tự cấp/thu quyền khi CRUD.
- Một user có nhiều CLB/role khác nhau. Không có system-admin bypass tenant. Membership active là điều kiện bắt buộc.
- Club + owner + audit tạo atomically; không có API xóa owner hoặc tự gán role. Chuyển owner là luồng tương lai.
- Tenant ID lấy từ route đã kiểm tra membership. Resource con truy vấn qua CLB. Foreign key kép bảo vệ team assignment.
- Số áo 0–999 không trùng trong cùng đội với hồ sơ active/injured; inactive giải phóng số. Hồ sơ chưa có team không giữ chỗ số áo. Lịch sử số áo và assignment là phần tiếp theo.
- Email nhân sự unique trong CLB khi có email. Hồ sơ có thể chưa có account, ngày sinh không nằm trong tương lai. Phone/date/position kiểm tra server.
- Lời mời: token ngẫu nhiên 64 ký tự, chỉ hash SHA-256 lưu DB, hiệu lực 7 ngày, đúng email đã xác minh, dùng một lần; nhận trong transaction, khóa CLB rồi invitation. Token chỉ trả một lần cho người tạo, chia sẻ thủ công. Reissue lời mời hết hạn thay token cũ.
- Login có rate limit email+IP và IP; registration/resend/invites cũng bị giới hạn. Session regenerate khi đăng nhập, invalidate khi logout. Đổi email cần current password, xóa trạng thái xác minh và gửi lại verification qua queue.
- Bcrypt password tối đa 72 byte, không chứa NULL; kiểm tra trước hashing, kể cả Unicode và dữ liệu không phải string. Web release chỉ nhận session, không nhận bearer token chưa được hỗ trợ.
- Audit core ghi actor/action/subject/time trong cùng transaction. Không lưu plaintext token hay toàn bộ PII trong audit. Chưa có màn hình tra cứu audit.

## Excel — PLANNED

XLSX/CSV, Unicode, mapping, preview, lỗi theo dòng, phát hiện trùng, insert/update/upsert; **chỉ confirm mới ghi**. Validate loại/kích thước/nội dung file, tenant, quyền, ngày/phone/email/số áo; staging riêng, transaction, chống replay; export lỗi và báo cáo chống formula injection. Không coi upload là import thành công.

## Finance / debt / payments — PLANNED

DECIMAL và exact arithmetic; số dư từ ledger, không sửa số dư trực tiếp. Chứng từ `draft → pending_approval → approved/rejected → posted → reversed` có guard. Threshold/multi-level approvals; rejected phải có lý do. Posted immutable, sửa bằng reversal có liên kết/audit, không đảo hai lần.

Transfer tạo hai vế cùng transaction; không cộng vào tổng thu/chi toàn CLB. Opening + Income − Expense = Closing theo kỳ. Kỳ khóa chặn posting/reversal trái quy định. Lock đồng thời, idempotency và unique business references bắt buộc.

Debt từ receivable, payment allocation và adjustment/waiver có chứng cứ; không cộng trừ giao dịch không liên kết. Allocation không vượt số tiền/nợ; partial/full payment và hoàn tiền có lịch sử. VietQR chỉ là chỉ dẫn: quét QR/upload ảnh không đồng nghĩa đã nhận tiền; xác nhận thủ công có quyền nếu chưa tích hợp ngân hàng.

Cost splitting equal/weighted/custom/guest/exempt, sponsorship offset, phân bổ phần dư ổn định; tổng dòng đúng bằng chi phí. Preview trước confirm; confirm idempotent mới tạo nợ. Automated tests về precision, approval, khóa kỳ, concurrency, replay, reversal, tenant là gate bắt buộc trước nghiệm thu tài chính.

## Vận hành khác — PLANNED

Attendance unique event/player, RSVP riêng actual. Stats lấy từ trận/sự kiện được xác nhận. Inventory thay đổi qua stock movement; không âm trái policy, tài sản cấp/thu hồi có lịch sử. Phạt tài chính nối receivable; cash sponsor nối finance, hiện vật nối kho. Một vote/user/poll. Sinh nhật và public website theo consent/publication allow-list. AI chỉ đọc dữ liệu được phép với cấu hình xử lý rõ ràng; không tự sửa số dư/phê duyệt hay gửi PII/tài chính ra ngoài khi chưa được phép.
