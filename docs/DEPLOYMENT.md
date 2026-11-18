# Triển khai — chưa phát hành production

Compose là development (PHP built-in server + Vite), chưa build/chạy trên máy này. Chưa deploy hoặc publish.

Production đích: PHP8.4 FPM/Nginx, MySQL8.4, Composer --no-dev --optimize-autoloader; npm ci và build frontend. Serve dist tại /, /api/* và /sanctum/* về Laravel public/index.php, static SPA fallback cho route frontend. Một origin HTTPS; preserve public host cho signed verification URL đúng APP_URL.

APP_ENV=production, APP_DEBUG=false, key riêng ổn định, HTTPS APP_URL, SESSION_SECURE_COOKIE=true, SESSION_ENCRYPT=true, SameSite Lax/domain đúng. Secrets qua environment/secret store, không image/Git. Trusted proxy rõ ràng, TLS, body limit, security headers/CSP, DB private và ít quyền.

Migrate sau backup/restore check, không migrate:fresh. Worker qua supervisor, restart khi deploy, SMTP/failed-job monitoring. Scheduler bật khi có jobs. Storage/log private, retention và backup mã hóa. Không cache API/private data ở reverse proxy/CDN/service worker.

Release gates mở: Docker/MySQL8/PHP8.4 integration và concurrency, account lifecycle đầy đủ, security/a11y/performance, SMTP, backup/restore, feature acceptance, native auth/platform builds. Xem [ROADMAP](./ROADMAP.md).
