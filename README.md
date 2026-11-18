# FCMS PRO

Laravel 12 + Sanctum; HTML/CSS/Vanilla JavaScript + Vite; MySQL 8.4 là đích triển khai. SQLite phục vụ development/kiểm thử local.

**Hiện là lát cắt core chạy được, chưa phải toàn bộ sản phẩm.** Account/session/email verification, nhiều CLB/role theo CLB, đội, nhân sự CRUD/tìm kiếm/lọc/phân trang, lời mời và dashboard dùng dữ liệu thật. Không seed account hoặc số liệu minh họa. Excel/tài chính/bóng đá/kho/advanced chưa triển khai.

**Chạy nhanh trên Windows:** bấm đúp [CHAY-NHANH.cmd](CHAY-NHANH.cmd). Script tự chuẩn bị database SQLite riêng, khởi động backend/frontend/queue và mở trình duyệt khi sẵn sàng. Giữ cửa sổ mở; nhấn Ctrl+C để dừng. Không cần bật Apache/MySQL hoặc import SQL. Máy hiện tại đã có PHP của XAMPP và Node.js.

Hoặc từ terminal:

```powershell
npm start
```

Dữ liệu chế độ chạy nhanh được lưu ở `.tools/local/fcms.sqlite`, giữ lại qua các lần chạy. Đây là database **riêng**, không tự lấy tài khoản/dữ liệu MySQL. Cấu hình backend env và database đang có được giữ nguyên. Chưa có tài khoản thì tự đăng ký. Lần đầu thiếu dependencies sẽ tự cài từ lock, cần Internet; các lần sau không cài lại. Máy mới vẫn cần Node 22.12+ và PHP 8.2+ có PDO SQLite.

Muốn dùng database đã cấu hình trong [backend/.env](backend/.env), bật server database tương ứng rồi chạy:

```powershell
npm run dev
```

Mở http://127.0.0.1:5173 và tự đăng ký tài khoản. API http://127.0.0.1:8000. Script tìm PHP trong PATH hoặc XAMPP; có thể đặt PHP_BIN. Ctrl+C dừng server và queue worker. Database dùng thực tế không chứa dữ liệu QA từ kiểm thử launcher.

Checkout mới cần Node 22.12+ (khuyến nghị 24 LTS), PHP 8.2+ và các extensions Laravel/PDO/XML/zip/mbstring/bcmath:

```powershell
node scripts/setup.mjs --sqlite
npm run dev
```

Setup giữ env/key đã có; chỉ chọn SQLite nếu tạo env mới. Composer portable được tải từ nguồn chính thức và kiểm tra SHA-256. MySQL/Docker xem [INSTALLATION](docs/INSTALLATION.md).

Nếu muốn import database bằng phpMyAdmin/MySQL CLI, dùng [fcms_pro_mysql.sql](backend/database/sql/fcms_pro_mysql.sql) trên database **trống**. File gồm 15 bảng của core hiện tại, sinh từ Laravel migrations; xem [hướng dẫn import](docs/DATABASE.md#import-sql-cho-mysql-84). Không chứa tài khoản hay dữ liệu mẫu.

```powershell
npm test
npm run build
```

[PROGRESS](PROGRESS.md) và [NEXT_STEPS](NEXT_STEPS.md) ghi kết quả/phần thiếu; [TESTING](docs/TESTING.md) ghi bằng chứng và giới hạn. [Tài liệu](docs/README.md) gồm toàn bộ yêu cầu/ERD/API/roadmap. Chưa deploy/publish hoặc build APK/IPA.
