# Changelog

## 2026-11-18 — Chạy local bằng một thao tác

- Thêm [CHAY-NHANH.cmd](../CHAY-NHANH.cmd) và `npm start`: SQLite độc lập, tự migrate, chạy backend/frontend/queue, đợi API sẵn sàng rồi mở trình duyệt.
- Giữ nguyên env/MySQL và database SQLite cũ; dữ liệu local mới lưu qua các lần chạy. Kiểm tra port trước khi khởi động; dừng các tiến trình con khi Ctrl+C.
- Bổ sung kiểm thử launcher; xem [TESTING](./TESTING.md).

## 0.1.0 — 2026-11-17 (development)

- Bổ sung [MySQL SQL schema](../backend/database/sql/fcms_pro_mysql.sql) gồm 15 bảng core, command export từ migrations, kiểm tra đồng bộ schema và hướng dẫn import phpMyAdmin/MySQL CLI.
- Khởi tạo repo trống, Laravel12/Sanctum và VanillaJS/Vite; thiết kế toàn hệ thống, giữ yêu cầu 18 module.
- Core session/account/email verification qua queue, CLB/team/roster/invites/dashboard, policies/FK/unique/audit.
- UI tiếng Việt, responsive/light/dark/dialog/loading/error/empty; PWA/native foundation.
- MySQL8.4 Docker development, setup/dev scripts, tests và docs; chưa chứng nhận production/native release.
- Bằng chứng và phần thiếu tại [TESTING](./TESTING.md), [PROGRESS](../PROGRESS.md).
