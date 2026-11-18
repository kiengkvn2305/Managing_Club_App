# FCMS PRO — Tiến độ

Cập nhật ngày 2026-11-18. **Đã bàn giao lát cắt core kiểm chứng local; chưa hoàn thành Phase0/Phase1 tổng thể hoặc toàn bộ 18 module.** Repository ban đầu trống, không có source/DB/.env người dùng bị xóa. Git đã khởi tạo, chưa commit/deploy/publish.

## Đã triển khai và kiểm chứng

- Laravel12/Sanctum, session cookie/CSRF/rate limits, register/login/logout/profile, queued signed email verification; đổi email yêu cầu password và xóa verified.
- Nhiều CLB, create/update/archive/reactivate, owner tự tạo atomically, membership roles riêng từng CLB; không system-admin bypass.
- Teams list/create; roster CRUD, tên/email/phone search, team/status filters, sort/pagination, validation/số áo unique, composite tenant FK.
- Tạo/nhận invitation 7 ngày, hash token, email verified, đúng role, transaction/locks, expiry/replay protection; link chia sẻ thủ công.
- Dashboard số đếm thật, audit core transactional; không số liệu/fixture production.
- UI tiếng Việt desktop/mobile, dark/light, dialogs, validation/loading/error/empty states. PWA static/offline groundwork kiểm tra trên production preview; Capacitor config/native integration groundwork.
- Compose MySQL8.4/PHP8.4 + scripts setup/dev/test, migrations/empty seeder, toàn bộ tài liệu yêu cầu.
- [CHAY-NHANH.cmd](CHAY-NHANH.cmd) / `npm start`: chạy thử SQLite riêng bằng một thao tác, tự migrate và mở browser khi API sẵn sàng; giữ cấu hình MySQL và dữ liệu hiện có. Đã kiểm tra restart giữ dữ liệu, stop giải phóng cổng và chạy HTTP smoke trên DB QA riêng. Bộ test bổ sung có 3 launcher tests PASS.

## Kết quả

20 backend tests/146 assertions (sau bổ sung SQL export) và 11 JS tests PASS; production build PASS; PHP syntax checks, migration/rollback/re-migrate, real HTTP smoke và browser CRUD/multi-club/mobile/PWA checks PASS. Setup chạy lại thành công, giữ nguyên env/key và DB local không chứa QA. Đã bổ sung [SQL MySQL 8.4](backend/database/sql/fcms_pro_mysql.sql) gồm 15 bảng hiện tại, sinh từ migrations; chưa kiểm chứng import MySQL8. Chi tiết phạm vi/giới hạn: [TESTING](docs/TESTING.md).

## Files / schema / API

Tất cả source đều mới trong repo. Nhóm chính: backend app/models/requests/resources/policy/services/controllers/middleware/notifications/tests; frontend API/state/pages/components/app/styles/PWA; scripts; Compose/Docker; docs. Danh sách đầy đủ tại [FILE_INDEX](docs/FILE_INDEX.md). Framework scaffold được điều chỉnh: User, provider, bootstrap/routes, composer config, empty seeder, env examples; loại frontend scaffold Laravel dư thừa.

Migration mới `2026_11_17_000001_create_club_platform`: users flags, clubs, club_members, teams, player_profiles, club_invitations, audit_logs. Ba migrations nền Laravel tạo account/session/cache/jobs. 20 API routes mới được liệt kê tại [API](docs/API.md), ngoài ra Sanctum CSRF và /up.

## Chưa hoàn thành / giới hạn

Docker không có; MySQL8 chưa chạy (XAMPP có MariaDB10.4); PHP8.4 chưa test. Vì vậy Phase0 chưa đóng. Core subset đã hoạt động trên SQLite, chưa nghiệm thu production.

Phase1 còn recovery/reset/change password, avatar/full profile/session management, quản trị role/manager/ownership transfer/leave, lịch sử nhân sự/assignment và Excel XLSX/CSV preview/confirm/export. Phase2–5 trong roadmap (finance/football/management/advanced) chưa có implementation. Finance chưa được gọi là hoàn thành và chưa có financial tests.

PWA chưa xác minh install trên điện thoại thật; native auth, Android/iOS project/configuration/packaging/device tests chưa làm; **không APK/IPA**. SMTP/backup/production security/performance còn gate release. Không có lỗi chặn nào đã biết trong luồng core SQLite đã test; điều đó không chứng nhận phạm vi chưa test.

## Chạy và tiếp tục

`npm run dev` → http://127.0.0.1:5173; tự đăng ký account. `npm test`, `npm run build`. [README](README.md), [INSTALLATION](docs/INSTALLATION.md). Bước tiếp theo theo [NEXT_STEPS](NEXT_STEPS.md); đầy đủ [ROADMAP](docs/ROADMAP.md), [REQUIREMENTS](docs/REQUIREMENTS.md).
