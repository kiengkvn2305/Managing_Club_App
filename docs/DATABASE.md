# Database

Đích: **MySQL 8.4**. Runtime cục bộ và PHPUnit hiện dùng SQLite. SQLite không chứng nhận row locks, collation hoặc concurrency của MySQL. MariaDB 10.4 có trên máy nhưng không thay thế gate MySQL 8.

Schema được quản lý bằng [Laravel migrations](../backend/database/migrations/), nên trước đây không có file SQL riêng. Migrations nền: users/password_reset_tokens/sessions, cache/cache_locks, jobs/job_batches/failed_jobs. Migration core: [2026_11_17_000001_create_club_platform.php](../backend/database/migrations/2026_11_17_000001_create_club_platform.php).

## Import SQL cho MySQL 8.4

File [fcms_pro_mysql.sql](../backend/database/sql/fcms_pro_mysql.sql) chứa **15 bảng** (gồm bảng `migrations`), khóa ngoại, unique/index, enum và defaults đúng migrations hiện tại. InnoDB, utf8mb4_unicode_ci; không chứa tài khoản, mật khẩu hay dữ liệu nghiệp vụ. Đây là schema core đã triển khai, chưa gồm các module còn PLANNED.

1. Trong phpMyAdmin, tạo database **trống** `fcms`, collation `utf8mb4_unicode_ci`.
2. Chọn database đó → **Import** → chọn [fcms_pro_mysql.sql](../backend/database/sql/fcms_pro_mysql.sql) → **Go/Thực hiện**.
3. Cấu hình [backend/.env](../backend/.env) với `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT` đúng server (Docker development: `3307`, MySQL local thường `3306`), `DB_DATABASE=fcms`, username/password của bạn. Xem [env example](../backend/.env.example).
4. Trong thư mục backend, chạy `php artisan config:clear`, rồi `php artisan migrate:status`. Bốn migration hiện tại phải có trạng thái **Ran**. Các migration bổ sung sau này vẫn chạy bằng `php artisan migrate`.

Nếu dùng MySQL CLI, từ root repository, sau khi tạo database trống:

```powershell
mysql --host=127.0.0.1 --port=3307 --user=fcms --password --database=fcms --execute="source backend/database/sql/fcms_pro_mysql.sql"
```

Thay host/port/user theo server. `--password` yêu cầu nhập mật khẩu, không ghi mật khẩu vào lệnh. File không tạo/drop database, không tắt kiểm tra FK. Chỉ import một lần vào DB trống; MySQL DDL không rollback toàn bộ khi lỗi giữa chừng. Với DB đã được tạo bằng migrations, tiếp tục dùng migrations thay vì import lại file này. Chuyển từ SQLite sang MySQL bằng SQL này chỉ tạo schema, không chuyển dữ liệu SQLite.

SQL được sinh trực tiếp bằng MySQL grammar của Laravel, không cần kết nối DB. Khi thay migrations, từ backend chạy:

```powershell
php artisan db:export-mysql
php artisan test --filter=MySqlSchemaExportTest
```

[Exporter](../backend/app/Support/MySqlSchemaExporter.php) ghi lại lịch sử migration để `migrate` không tạo trùng bảng; [test](../backend/tests/Feature/MySqlSchemaExportTest.php) phát hiện file SQL lệch migrations và kiểm tra FK/unique quan trọng. Chưa chạy import trên MySQL 8.4 trong môi trường hiện tại; xem [TESTING](./TESTING.md).

## Schema nghiệp vụ hiện tại

| Bảng | Trách nhiệm và ràng buộc chính |
| --- | --- |
| users | Email unique, password hash, is_active, is_system_admin; flags không mass assign |
| clubs | Tenant, slug unique, format 5/7/11, status active/archived |
| club_members | User/club/role/status; unique(club_id,user_id), user/club FK restrict |
| teams | Club/name/format; unique(club_id,name), unique(club_id,id) |
| player_profiles | Roster riêng, account nullable chưa có API link, team nullable; FK kép(club_id,team_id) |
| club_invitations | Token hash unique, unique(club_id,email), expiry/accepted_at; FK club/inviter |
| audit_logs | Actor/action/subject/time; index(club_id,created_at), không PII/token |

Player email unique theo CLB; `current_jersey` chỉ có giá trị với team và active/injured; unique(club_id,team_id,current_jersey) bảo vệ số áo ở DB. Field nội bộ không xuất qua Resource và không nhận từ API. Roster delete không xóa user/membership. Chưa có xóa account/club hay lịch sử assignment; cần thiết kế retain/archive trước khi triển khai.

Tất cả thời gian UTC. Schema tài chính PLANNED trong [ERD](./ERD.md); không tạo bảng rỗng để tính hoàn thành. Không seed tài khoản demo. Backup trước migration production; chỉ rollback database disposable khi test. Xem [TESTING](./TESTING.md).
