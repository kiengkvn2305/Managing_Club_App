# PWA / Capacitor

**Foundation**, chưa có APK/AAB/IPA; chưa generate Android/iOS project. Windows không build iOS; cần macOS/Xcode. Native API/auth riêng chưa hoàn thành, không sử dụng account thật trong native wrapper hiện tại.

PWA: manifest, SVG/PNG192/512/maskable icons, service worker static allow-list/offline fallback/update notification. Không cache API/auth/PII/tài chính, không queue mutation offline. Chỉ đăng ký worker ở production build trên HTTPS/localhost. Cài đặt/device QA chưa chứng nhận.

Capacitor config: appId vn.fcms.pro, name FCMS PRO, webDir dist. Đã khai báo core/cli/android/ios/App/Network/SplashScreen, có Back/network/splash foundation; không dùng server.url trỏ website trống.

Phase6 tiếp tục:

1. Hoàn thiện native API HTTPS/auth contract, expiry/revocation và OS secure storage; không bearer token localStorage. Kiểm chứng CORS/origin; cookie cùng origin của web không tự áp dụng native.
2. Từ frontend: `npm ci`, `npm run build`, `npx cap add android`, `npx cap sync android`, `npm run cap:android`.
3. Android Studio dùng SDK/JDK theo Capacitor lock; test emulator/device, Back/safe area/offline/permissions; Generate Signed Bundle/APK tạo APK/AAB. Signing keys ngoài repository.
4. macOS: `npx cap add ios`, `npx cap sync ios`, `npm run cap:ios`; Xcode bundle/team/provisioning, test device/simulator, Archive/export IPA.
5. Cấu hình native launcher/adaptive icons/splash bằng branding FCMS; PWA PNG chưa đồng nghĩa native icon đã cấu hình.
6. Camera/file picker/download/share/push thêm cùng nghiệp vụ, quyền tối thiểu; hiện chưa triển khai.

Config/web build không đủ để đánh dấu Phase6 complete.
