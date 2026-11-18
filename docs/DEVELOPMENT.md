# Phát triển

Backend theo Laravel conventions. Frontend tách API client, state, pages, UI components, routing/interactions, validators và native module. Session HttpOnly cookie, CSRF; chỉ theme được lưu localStorage.

`npm run dev` khởi động PHP built-in server, Vite và queue worker trên loopback; Ctrl+C dừng cả ba. Vite proxy /api và /sanctum cùng origin. Không trộn localhost với 127.0.0.1. PHP built-in/Vite dev server không dành production. Có VS Code tasks “FCMS: development” và “FCMS: build frontend”; nếu provider không khả dụng, dùng npm scripts.

Env/key không bị setup ghi đè. `.tools`, vendor, node_modules, env, SQLite/logs và build outputs ignored. `php backend/artisan migrate` chạy schema; seeder trống. Queue verification retry 3 lần; xem queue:failed và sửa cấu hình trước retry. Scheduler chưa có job nghiệp vụ; schedule:work chỉ cần khi module automation triển khai.

PHP format: `php backend/vendor/bin/pint backend/app backend/routes backend/database backend/tests`. Mỗi feature cần Policy, scoped query, validation, Resource, FK/unique phù hợp và tests; nhiều entity+audit phải cùng transaction. Tài chính là domain riêng, không ghép vào CRUD tổng quát. [TESTING](./TESTING.md) hướng dẫn kiểm tra.
