# Phân quyền

Đúng ba actor cấp cao: **System Admin, Club Manager, Club Member**. Các role dưới đây là chức danh theo CLB, không tạo thêm actor. `users.is_system_admin` không cấp quyền tenant. API admin toàn hệ thống còn PLANNED.

| Role membership active | Thông tin CLB/đội/counts | Đọc/sửa nhân sự, tạo đội | Sửa CLB/lưu trữ | Tạo lời mời |
| --- | --- | --- | --- | --- |
| owner | Có | Có | Có | Mọi role trừ owner |
| co_owner | Có | Có | Có | Trừ owner/co_owner |
| general_manager | Có | Có | Có | Chỉ member |
| hr_manager | Có | Có | Không | Chỉ member |
| sporting_manager | Có | Có | Không | Không |
| treasurer | Có | Không | Không | Không |
| equipment_manager | Có | Không | Không | Không |
| member | Có | Không | Không | Không |

CLB archived vẫn đọc được theo quyền, chặn ghi roster/đội/lời mời; người có quyền sửa CLB có thể kích hoạt lại. Roster là thông tin riêng; member thường chưa có endpoint hồ sơ cầu thủ cá nhân trong lát cắt này. Roles finance/inventory chỉ có hiệu lực khi module tương ứng triển khai.

Mọi user chỉ sửa account của mình; nhận invite cần email đã xác minh đúng đích. Ngoài tenant nhận 404; membership hợp lệ nhưng thiếu quyền nhận 403; chưa login nhận 401. Route guards và ẩn nút chỉ hỗ trợ UX; backend Policy quyết định cuối cùng.
