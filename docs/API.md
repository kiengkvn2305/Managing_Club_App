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

Chưa có recovery/password changes, manager role CRUD/owner transfer, Excel, finance, football hoặc mobile tokens API. Xem [ROADMAP](./ROADMAP.md).
