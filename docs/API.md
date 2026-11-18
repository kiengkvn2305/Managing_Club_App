# API v1

Base `/api/v1`. Gửi `Accept: application/json`, cookie credentials và `X-XSRF-TOKEN` URL-decoded cho mutations sau `GET /sanctum/csrf-cookie`. Toàn bộ route nằm trong web session/CSRF middleware và dùng auth:sanctum; không bỏ CSRF login/register. Không phát bearer token hoặc lưu auth ở localStorage.

Success `{data:...}`; validation `{message,errors:{field:[...]}}`; collections `data` và `meta/links` khi phân trang. Status 200/201/204; 401 unauthenticated, 403 forbidden, 404 outside tenant/not found, 419 CSRF, 422 invalid, 429 throttle. Production tắt debug.

| Method | Path | Payload / kết quả |
| --- | --- | --- |
| POST | /auth/register | name,email,password,password_confirmation; user+session, 201; verification queued |
| POST | /auth/login | email,password; user+session |
| POST | /auth/logout | Hủy session, 204 |
| GET | /auth/user | Account hiện tại, không secret |
| PATCH | /auth/profile | name/email; current_password bắt buộc khi đổi email |
| POST | /auth/verification-notification | Resend queued verification, throttle 3/min |
| GET | /auth/verify-email/{id}/{hash} | Named verification.verify; auth + signed URL |
| GET, POST | /clubs | List membership active / tạo CLB+owner |
| PATCH | /clubs/{club} | name,description,city,home_ground,format,status theo Policy |
| GET, POST | /clubs/{club}/teams | List / create {name,format} |
| GET, POST | /clubs/{club}/members | List paginated / create player profile |
| GET, PATCH, DELETE | /clubs/{club}/members/{member} | Scoped read/update/delete profile; delete 204 |
| GET | /clubs/{club}/dashboard | members_count,active_members_count,teams_count,managers_count |
| POST | /clubs/{club}/invitations | {email,role}; token trả một lần, 201; không tự gửi invite email |
| POST | /invitations/accept | {token}; đúng email đã xác minh, expiry/replay kiểm tra; trả Club Resource |

Club Resource gồm id,name,slug,description,city,home_ground,format,status,membership_role. Player payload: full_name required lúc tạo; team_id,email,phone,date_of_birth,jersey_number,position,preferred_foot,status,notes nullable/theo FormRequest. Position GK/CB/LB/RB/CDM/CM/CAM/LM/RM/LW/RW/ST/CF; foot left/right/both; status active/inactive/injured. Payload club_id/user_id/role không thể cấp quyền.

Member filters: q (tên/email/phone), team_id,status,page,per_page 1–100, sort full_name/jersey_number asc hoặc created_at desc. Managers count là access memberships active có role khác member; members count là player_profiles, CLB mới 0 hồ sơ/1 quản lý.

## Bổ sung: chi dự kiến và kết nối CLB

- Kho: `GET /inventory/members`, `GET/POST /inventory/{item}/assignments`, `POST /inventory/{item}/assignments/{assignment}/return` trong phạm vi `/clubs/{club}`. Cấp/thu hồi chỉ áp dụng tài sản, thay đổi tồn và lịch sử trong transaction.
- Trận đấu: `GET /matches/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD`, `GET /matches/{id}/lineup`, `GET/PUT /matches/{id}/attendance`, `GET/PUT/DELETE /matches/{id}/player-stats...` và `GET /player-stats?from=...&to=...` trong phạm vi `/clubs/{club}`. Thống kê chỉ tính bản ghi của trận kết thúc.
- Biểu quyết nội bộ: `GET/POST /clubs/{club}/polls`, `POST /clubs/{club}/polls/{id}/vote`; một phiếu cho mỗi cử tri đủ điều kiện. Tuyển dụng nội bộ: `GET/POST /clubs/{club}/recruitment/posts`, cập nhật tin và `GET/POST/PATCH` ứng viên theo tin. Không có đơn công khai trong lát cắt này.

- `GET/POST /clubs/{club}/inventory`, `PATCH/DELETE /clubs/{club}/inventory/{item}`, `POST .../{item}/restore`, `GET/POST .../{item}/movements`: quản lý kho và lịch sử, chỉ lưu trữ khi tồn bằng 0. Quyền kho được xác thực theo CLB; chưa có cấp phát cho thành viên.

- `POST /auth/forgot-password` nhận email và luôn trả thông điệp chung; `POST /auth/reset-password` nhận email, token một lần, password và password_confirmation. Liên kết trỏ về `FRONTEND_URL`; việc gửi thư cần cấu hình mail thực tế. Không hỗ trợ token bearer.
- `GET /clubs/{club}/matches?month=YYYY-MM`, `POST /clubs/{club}/matches`, `PATCH /clubs/{club}/matches/{id}` quản lý lịch trận theo CLB, với tỷ số và lựa chọn liên kết lời mời giao hữu đã chấp nhận. Mỗi CLB lưu lịch riêng.

- `GET /clubs/{club}/finance/planned?from=YYYY-MM&to=YYYY-MM` liệt kê tối đa 12 tháng; `POST` tạo khoản lặp lại. `PATCH /clubs/{club}/finance/planned/{id}` sửa từ tháng được gửi, `DELETE` cùng đường dẫn với `month=YYYY-MM` dừng từ tháng đó. Các route yêu cầu quyền tài chính và không tự tạo giao dịch thu chi.
- `GET /discover/clubs?q=...` chỉ trả CLB đang hoạt động đã bật `is_discoverable` và các trường id, tên, khu vực, loại sân, phiên bản ảnh. `GET /discover/clubs/{id}/avatar` chỉ đọc được ảnh của CLB đang công khai. Các route yêu cầu tài khoản đăng nhập.
- `GET` và `POST /clubs/{club}/friendlies` liệt kê hoặc gửi lời mời giao hữu; `PATCH /clubs/{club}/friendlies/{id}` cho CLB nhận chấp nhận/từ chối hoặc CLB gửi hủy. Yêu cầu quyền quản lý CLB và xác định CLB từ route đã xác thực. Chấp nhận chưa tự tạo lịch trận.

Bảng trên mô tả lát cắt core ban đầu; ứng dụng đã có thêm các API ảnh, tài chính, quỹ, hoạt động và Excel. Các phần chưa hoàn thiện được theo dõi tại [ROADMAP](./ROADMAP.md).
