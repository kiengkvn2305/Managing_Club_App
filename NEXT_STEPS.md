# Công việc tiếp theo

Đọc [PROGRESS](PROGRESS.md), [TESTING](docs/TESTING.md), [ROADMAP](docs/ROADMAP.md) trước khi sửa. Đừng đánh dấu toàn bộ phase complete dựa trên core slice.

Đã bổ sung chi dự kiến, khám phá CLB, giao hữu, lịch trận tháng/tuần/danh sách, liên kết đội hình, đăng ký/điểm danh và thống kê cầu thủ nhập thực tế. Các phần còn lại của lộ trình vẫn chưa hoàn thiện; ưu tiên kiểm thử cạnh tranh trên MySQL, mùa giải, chia chi phí và QR.

Kho trang bị đã có cấp phát/thu hồi từng phần theo thành viên; tiếp tục hỏng/mất/thanh lý, kiểm kê nhiều dòng và báo cáo. Đã có biểu quyết một lựa chọn và tuyển dụng nội bộ; nộp đơn công khai, thử sức chi tiết và chuyển ứng viên thành thành viên chưa có. Khôi phục mật khẩu đã có nhưng môi trường hiện dùng mail log, cần cấu hình SMTP và URL frontend để gửi thư thực.

1. Hoàn tất Phase0: cài/chạy Docker, cấu hình env secrets theo [INSTALLATION](docs/INSTALLATION.md), `docker compose up --build -d`, migrate. Chạy tests với MySQL8/PHP8.4 qua phpunit.mysql.xml trong DB fcms_test riêng; thêm concurrency/lock/collation cases. Không dùng MariaDB10.4 làm bằng chứng MySQL8.
2. Hoàn tất account module: đã có đổi/khôi phục mật khẩu và ảnh đại diện riêng tư; còn giao diện quản lý phiên, account lifecycle và vận hành gửi thư. Cần cấu hình `FRONTEND_URL` và SMTP thực tế để gửi liên kết khôi phục; tiếp tục kiểm thử rate-limit/CSRF/ownership và chuẩn bị giám sát gửi thư trước release.
3. Hoàn tất clubs/HR: manager membership CRUD/permissions, owner transfer có bảo vệ last owner, invitations revoke/list, leave/archive lifecycle, account linking, height/weight/secondary position/join dates/history/team assignments; tests hai CLB, blocked/revoked accounts, ownership transfer.
4. Smart Excel: PhpSpreadsheet/Laravel Excel theo PHP8.4; file validation/private staging, mapping/Unicode/XLSX/CSV, preview/errors/duplicates, confirm token/idempotency, insert/update/upsert, exports/formula injection, API+UI+tests. Chỉ sau gate này mới đóng Phase1.
5. Finance theo [BUSINESS_RULES](docs/BUSINESS_RULES.md): schema DECIMAL/ledger/periods/approval/idempotency trước UI; tests exact totals/concurrency/reversal/authorization trước công bố. Debt/allocations/waiver/reports/Excel không bỏ scope.
6. Phase3–5 lần lượt football/attendance/cost split/VietQR, inventory/assets/events/discipline/notifications, recruitment/sponsors/votes/public sites/automation/AI. Mỗi module cập nhật docs và chạy tests, không nút/số liệu giả.
7. Phase6–7: [MOBILE_BUILD](docs/MOBILE_BUILD.md), native auth/secure storage/SDK projects/platform builds/device QA, rồi full E2E/security/performance/backup/deploy gates. Không publish production hoặc store app nếu chưa được phép.

Lệnh local tiếp tục: `npm run dev`, `npm test`, `npm run build`. Giữ env/key/DB người dùng; mọi rollback/reset chỉ trên DB disposable được chỉ rõ. Không thay test để che lỗi; mọi giới hạn phải cập nhật lại tiến độ.
