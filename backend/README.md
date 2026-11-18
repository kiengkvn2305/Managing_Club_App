# FCMS PRO API

Laravel 12 / Sanctum. Xem [README dự án](../README.md), [API](../docs/API.md), [phân quyền](../docs/PERMISSIONS.md), [kiểm thử](../docs/TESTING.md).

## Đội hình và đội trưởng

Sau khi cập nhật mã nguồn, khởi động MySQL và chạy `php artisan migrate` trong thư mục backend để bổ sung bảng đội hình và thông tin đội trưởng. Với PHP của XAMPP trên Windows, có thể chạy `C:\xampp\php\php.exe artisan migrate`. Migration giữ nguyên dữ liệu hiện có; không import lại file SQL vào database đang sử dụng.

Trong mục Đội bóng, chọn đội trưởng từ các cầu thủ thuộc đội. Trong mục Đội hình trận đấu, tạo trận với đối thủ và ngày thi đấu, chọn cầu thủ đá chính hoặc dự bị, rồi kéo cầu thủ trên sân hoặc dùng các nút mũi tên. Mỗi đội hình được lưu cho một trận; số cầu thủ đá chính tối đa bằng loại sân, còn số cầu thủ dự bị và số cầu thủ theo từng vị trí không bị giới hạn.
