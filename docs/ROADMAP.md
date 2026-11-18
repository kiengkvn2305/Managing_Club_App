# Lộ trình

[Yêu cầu gốc](./REQUIREMENTS.md) giữ đầy đủ chức năng, field và tiêu chí nghiệm thu. Phase chỉ complete khi có code, schema, Policy/validation, UI/API thật và evidence. Lát cắt core không đồng nghĩa Phase1 complete.

| Phase | Phạm vi | Trạng thái / gate |
| --- | --- | --- |
| 0 Foundation | Repo/env/Git, kiến trúc/ERD, Laravel/Vite/MySQL/Docker, standards/tests | Local chạy; Docker/MySQL8/PHP8.4 chưa kiểm chứng, phase còn mở |
| 1 Core | Account đầy đủ, profile/avatar/session, CLB/teams/managers/roles/invites/owner transfer/archive, HR, Excel, dashboard | Đã có avatar, đổi và khôi phục mật khẩu, nhiều CLB, đội, hồ sơ và một luồng nhập/xuất Excel; gửi mail thực cần cấu hình, giao diện quản lý phiên, liên kết tài khoản, role CRUD và chuyển chủ sở hữu còn thiếu |
| 2 Finance | Funds/ledger/income/expense/transfer/adjustment/reversal/reimbursement, multi-level approval/threshold, periods, receivables/payments/allocations/waivers/reports/Excel/audit | Đã có sổ thu chi, khoản chi dự kiến lặp lại, biểu đồ, quỹ theo đội/tháng, miễn, đóng trước và điều chỉnh; phê duyệt tài chính, khóa kỳ, đối soát và kiểm thử đồng thời còn thiếu |
| 3 Football | Match/training/calendar/lineup/results/seasons/stats, RSVP/actual/QR attendance, cost splitting, VietQR/manual payment | Đã có lịch trận dạng danh sách/tháng/tuần, kết quả, đội hình liên kết, đăng ký/điểm danh thủ công và thống kê cầu thủ nhập thực tế; mùa giải, chia chi phí, QR và kiểm thử cạnh tranh còn thiếu |
| 4 Management | Kho/tài sản/movements/stocktake/assignment/return/loss/disposal, sinh nhật/sự kiện/privacy, discipline/debt, notifications | Đã có sinh nhật, hoạt động và kho với nhập/xuất, kiểm kê từng loại, cấp phát/thu hồi, lịch sử; hỏng/mất/thanh lý riêng, kiểm kê nhiều dòng, báo cáo kho, kỷ luật và thông báo còn thiếu |
| 5 Advanced | Recruitment, sponsorship, voting, public websites, automation, AI foundation | Đã có khám phá CLB tự nguyện, biểu quyết nội bộ và tin/hồ sơ tuyển dụng nội bộ; nộp đơn công khai, đánh giá thử, chuyển ứng viên, tài trợ, website đầy đủ và AI còn thiếu |
| 6 Mobile | Responsive refinement, PWA install/offline/update, Capacitor Android/iOS, file/camera/share/push khi cần | PWA/config groundwork; native auth/build/device QA thiếu |
| 7 Quality/release | Full E2E/security/performance/DB optimization, backup/docs/deployment review | PLANNED; chưa deploy/publish |

18 modules: Authentication, Club, Human Resources, Smart Excel, Finance, Debt, Match Cost Splitting, Payment/VietQR, Inventory/Assets, Matches, Attendance, Player Stats, Birthdays/Events, Discipline, Recruitment, Sponsorship, Voting, Public Club Website. Automation/AI là nhóm thêm, không bỏ khỏi kế hoạch.

System Admin control plane còn PLANNED: users/club/account statuses, policies/global catalogs, thông báo/security logs/operating reports, support requests và service plans tương lai. Hỗ trợ dữ liệu riêng phải có grant kiểm soát/audit, không tenant bypass mặc định.
