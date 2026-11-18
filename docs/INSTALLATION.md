# Cài đặt

Node 22.12+ (khuyến nghị 24 LTS), PHP 8.2+ (Docker đích 8.4), Composer 2. Extensions: curl/ctype/dom/fileinfo/filter/hash/mbstring/openssl/PDO/pdo_mysql/pdo_sqlite/session/tokenizer/xml/xmlwriter/zip/bcmath. Không cần cài PHP system nếu có XAMPP; đặt PHP_BIN nếu cần.

## Chạy nhanh, không cần bật database server

Trên Windows, bấm đúp [CHAY-NHANH.cmd](../CHAY-NHANH.cmd). Hoặc từ root chạy `npm start`. Không cần mở XAMPP Control Panel, chuyển vào htdocs, import SQL hoặc sửa env. Script dùng PHP có sẵn (tự tìm XAMPP), tự migrate SQLite riêng, chạy backend/Vite/queue và mở http://127.0.0.1:5173 sau khi kiểm tra API hoạt động. Giữ cửa sổ mở; Ctrl+C dừng cả ba tiến trình. Nếu có bản ứng dụng khác đang chạy trên cổng 8000/5173, dừng cửa sổ đó trước; launcher không tự đóng tiến trình của bạn.

Database tại `.tools/local/fcms.sqlite` được giữ qua các lần chạy, không có dữ liệu demo. Chế độ này dùng tài khoản/dữ liệu riêng; **không chuyển dữ liệu MySQL** và không đổi [backend/.env](../backend/.env) có sẵn. Không xóa `.tools/local` nếu muốn giữ dữ liệu. Email xác minh ghi vào [laravel.log](../backend/storage/logs/laravel.log), không gửi SMTP. Mở liên kết bằng trình duyệt đang đăng nhập tại host 127.0.0.1.

Máy mới cần Node 22.12+ và PHP 8.2+ có PDO SQLite. Nếu thiếu dependencies/env/key, script chạy setup một lần bằng database local riêng, cần Internet để cài dependencies từ lock. Env/key đã có được giữ nguyên. Launcher từ chối APP_ENV=production. SQLite dùng để thử local; kiểm chứng MySQL8 vẫn là gate riêng.

## Chạy theo database đã cấu hình

Từ root: `node scripts/setup.mjs --sqlite`, `npm run dev`. Frontend http://127.0.0.1:5173, API http://127.0.0.1:8000. Script tự tìm XAMPP, tải/checksum Composer nếu thiếu; không có demo login. Tự đăng ký account. `.tools` được ignore.

Setup không ghi đè env có sẵn và không regenerate key đã có. `--sqlite` chỉ tác động khi tạo env mới. DB custom cần tạo file/database trước migrations. Đổi SQLite sang MySQL không tự migrate dữ liệu; phải có backup và kế hoạch chuyển riêng. Setup từ chối APP_ENV=production.

## Docker / MySQL 8.4 development

Cần Docker Desktop + Compose v2. **Chưa chạy Docker trên máy hiện tại.**

1. Copy root `.env.example` → `.env`, đặt MYSQL_PASSWORD và MYSQL_ROOT_PASSWORD mạnh, khác nhau, không commit.
2. Copy backend env example → backend env nếu chưa có. Tạo APP_KEY bằng `php backend/artisan key:generate` sau Composer install. Nếu chỉ dùng Docker: `docker compose build api`, `docker compose run --rm --no-deps api php artisan key:generate --show`, lưu key vào backend env.
3. `docker compose up --build -d`.
4. `docker compose exec api php artisan migrate --no-interaction`.
5. `docker compose exec -d api php artisan queue:work --sleep=1 --tries=3` xử lý verification mail; restart worker sau thay đổi.
6. Mở frontend :5173. DB bind localhost:3307. MariaDB XAMPP không thay thế gate MySQL8.

Volumes giữ DB/storage; `docker compose down` giữ volumes. Không chạy down -v/migrate:fresh/reset trên dữ liệu cần giữ. Development image cần rebuild sau thay đổi source.

Có thể tạo schema qua [file SQL](../backend/database/sql/fcms_pro_mysql.sql) thay cho bước migrate đầu tiên: xem [hướng dẫn phpMyAdmin/MySQL CLI](./DATABASE.md#import-sql-cho-mysql-84). Chỉ dùng một cách khởi tạo trên database trống; file SQL đã có lịch sử migrations. Các lần cập nhật sau dùng `php artisan migrate`.

## Email verification

MAIL_MAILER=log: queue worker ghi thư vào backend/storage/logs/laravel.log (Docker storage volume). Mở liên kết của chính tài khoản local khi đang login đúng tài khoản/cùng host 127.0.0.1. Log là hộp thư development; production cần SMTP + worker/monitoring, không có bypass xác minh.
