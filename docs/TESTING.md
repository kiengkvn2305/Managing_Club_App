# Kiểm thử — cập nhật 2026-11-18

Môi trường đã chạy: Windows, PHP8.2.12 XAMPP, Laravel12.72/Sanctum4.4, Node26.13/npm11.20, Vite8.3.8. MySQL8/Docker/PHP8.4 chưa chạy. SQLite foreign_keys bật trong tests.

| Kiểm tra | Kết quả thực tế |
| --- | --- |
| `npm test` từ root (2026-11-18) | **20 backend tests, 146 assertions; 11 frontend tests; 3 launcher tests — PASS** |
| Launcher chạy nhanh | API/Vite proxy/queue sẵn sàng không có MySQL; smoke HTTP đăng ký/CLB/team/member/session/CSRF/IDOR PASS trên SQLite QA riêng |
| Launcher restart/stop | Dữ liệu giữ qua restart; gọi stop đóng cả ba tiến trình, giải phóng cổng; Ctrl+C trên terminal thực tế cũng giải phóng cổng — PASS |
| Launcher giữ cấu hình | Hash backend env, SQLite cũ và config cache gốc không đổi; lần mở thứ hai báo cổng bận và không dừng bản đang chạy — PASS |
| Launcher mặc định/UI | `node scripts/start.mjs --no-open` tự tạo DB local và migrations; trang đăng nhập tải qua browser tích hợp — PASS; không kích hoạt browser hệ thống trong kiểm thử |
| `php artisan test` từ backend sau bổ sung SQL export | **20 backend tests, 146 assertions — PASS**; SQL khớp migrations, 15 bảng InnoDB/utf8mb4, lịch sử migration, FK kép và unique số áo |
| SQL export | `php artisan db:export-mysql` PASS, sinh [fcms_pro_mysql.sql](../backend/database/sql/fcms_pro_mysql.sql) không kết nối DB; chưa chạy import trên MySQL8 |
| PHP syntax | 42 file app/bootstrap/routes/database/tests — PASS |
| PHP syntax bổ sung SQL | Exporter, command và test mới/sửa — PASS; editor diagnostics không lỗi |
| `npm run build` | Vite production build PASS, ES modules không framework/TypeScript |
| Composer validate --strict | PASS |
| Migration SQLite | Migrate, rollback migration core, migrate lại trên DB disposable riêng — PASS |
| `node scripts/smoke.mjs` với local servers + QA DB | PASS: real session/CSRF419/register/club/team/member/delete/counts/no-store/outsider404/logout401 |
| Browser automation tích hợp | Register, createclub/team/member, edit/delete, create/switch nhiều CLB, dark mode — PASS |
| Responsive | 1440px desktop và 390px mobile; bottom nav/mobile forms, không horizontal document overflow trong luồng đã kiểm tra — PASS |
| Production PWA preview | Worker active, offline navigation fallback đúng; cache chỉ static/icons/manifest, **không API/Sanctum** — PASS |
| Editor diagnostics | Không có lỗi trong app/frontend/scripts tại lần kiểm tra |
| Setup script | Cài lại từ lock thành công, env/key không đổi, database local không chứa users/CLB QA |

Backend tests kiểm tra hash/no secret/no privilege injection, validation, session logout, inactive account, login rate limit; signed verification chống sửa URL, đổi email cần password và hủy verified; role-specific privacy, admin không bypass, IDOR, composite FK chặn cross-club team, duplicate team/jersey, invite expiry/replay/email/role, archive writes, dashboard/audit persistence.

JS tests kiểm tra CSRF/header/cookie/no bearer, validation details, session expiry, không replay khi CSRF fail, offline/JSON errors, XSS escaping, calendar dates/jersey0/pagination/role presentation và worker không intercept private/cross-origin/mutation requests. Browser kiểm tra là luồng scripted thực tế qua công cụ tích hợp, chưa có bộ Playwright runner lưu trong repo.

## Chạy lại

Từ root: `npm test`, `npm run build`. Lệnh test PHP trực tiếp phải chạy trong thư mục backend: `php artisan test`. Root runner tự đặt cwd đúng. `php backend/artisan test` từ root không phù hợp với subprocess PHPUnit của framework.

Launcher unit tests: `npm run test:launcher`. Các kiểm tra HTTP tạo dữ liệu được chạy với thư mục `.tools/launcher-qa-*` riêng, không dùng `.tools/local/fcms.sqlite` dành cho người dùng. Lần chạy CLI/UI mặc định chỉ mở trang đăng nhập, không tạo tài khoản/CLB QA. Launcher mở browser sau khi `/up` trả thành công và proxy `/api/v1/auth/user` trả 401 JSON; có thể dùng `node scripts/start.mjs --no-open` khi kiểm thử không cần tự mở browser.

HTTP smoke chỉ cho phép localhost và **tạo bản ghi QA**. Chạy với database development disposable riêng; không chạy vào DB có dữ liệu thật. Ví dụ PowerShell trong root:

```powershell
$env:DB_DATABASE = "$PWD/.tools/http-qa.sqlite"
New-Item -ItemType File -Path $env:DB_DATABASE
& 'C:/xampp/php/php.exe' backend/artisan migrate --no-interaction
npm run dev
# Terminal khác:
node scripts/smoke.mjs
```

Nếu file đã tồn tại, chọn tên file QA mới; không ghi đè dữ liệu cần giữ. Phiên làm việc này dùng .tools/qa.sqlite riêng, không seed QA vào database local của người dùng. Queue worker thực tế cũng đã xử lý VerifyAccountEmail thành công với MAIL_MAILER=log.

## MySQL8 gate chưa chạy

Tạo **database disposable fcms_test** và user fcms_test chỉ có quyền trên DB đó. Cấu hình DB_HOST/DB_PORT/DB_PASSWORD đúng môi trường bằng environment (không commit secrets). Từ backend: `php vendor/phpunit/phpunit/phpunit --configuration phpunit.mysql.xml`. Config ép tên database/user riêng, RefreshDatabase có thể tạo/xóa schema test. Không cấp user test quyền database thật.

Thêm test nhiều process trên MySQL8 cho lock, invitation races và số áo; SQLite không chứng nhận FOR UPDATE hoặc collation MySQL. Finance concurrency/idempotency/precision/reversal tests chưa có vì module chưa triển khai.

Chưa chứng nhận: Docker build/health, MySQL8/PHP8.4, SMTP production, E2E Excel/finance/football/kho, performance/full security audit, native auth, Android/iOS builds, PWA installation trên thiết bị thật. Những mục này vẫn mở; không suy diễn từ build/test core.
