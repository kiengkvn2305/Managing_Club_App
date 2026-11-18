# Lộ trình

[Yêu cầu gốc](./REQUIREMENTS.md) giữ đầy đủ chức năng, field và tiêu chí nghiệm thu. Phase chỉ complete khi có code, schema, Policy/validation, UI/API thật và evidence. Lát cắt core không đồng nghĩa Phase1 complete.

| Phase | Phạm vi | Trạng thái / gate |
| --- | --- | --- |
| 0 Foundation | Repo/env/Git, kiến trúc/ERD, Laravel/Vite/MySQL/Docker, standards/tests | Local chạy; Docker/MySQL8/PHP8.4 chưa kiểm chứng, phase còn mở |
| 1 Core | Account đầy đủ, profile/avatar/session, CLB/teams/managers/roles/invites/owner transfer/archive, HR, Excel, dashboard | Core slice verified local; recovery/password/avatar/session/role CRUD/transfer/Excel và hồ sơ nâng cao thiếu |
| 2 Finance | Funds/ledger/income/expense/transfer/adjustment/reversal/reimbursement, multi-level approval/threshold, periods, receivables/payments/allocations/waivers/reports/Excel/audit | PLANNED; exact arithmetic, concurrency, idempotency, period locks/reversal tests bắt buộc |
| 3 Football | Match/training/calendar/lineup/results/seasons/stats, RSVP/actual/QR attendance, cost splitting, VietQR/manual payment | PLANNED; exact remainders, duplicate prevention, E2E/tenant gates |
| 4 Management | Kho/tài sản/movements/stocktake/assignment/return/loss/disposal, sinh nhật/sự kiện/privacy, discipline/debt, notifications | PLANNED; không âm tồn trái policy, history và liên kết tài chính |
| 5 Advanced | Recruitment, sponsorship, voting, public websites, automation, AI foundation | PLANNED; unique vote, cash/in-kind mapping, public allow-list, consent/no auto approvals |
| 6 Mobile | Responsive refinement, PWA install/offline/update, Capacitor Android/iOS, file/camera/share/push khi cần | PWA/config groundwork; native auth/build/device QA thiếu |
| 7 Quality/release | Full E2E/security/performance/DB optimization, backup/docs/deployment review | PLANNED; chưa deploy/publish |

18 modules: Authentication, Club, Human Resources, Smart Excel, Finance, Debt, Match Cost Splitting, Payment/VietQR, Inventory/Assets, Matches, Attendance, Player Stats, Birthdays/Events, Discipline, Recruitment, Sponsorship, Voting, Public Club Website. Automation/AI là nhóm thêm, không bỏ khỏi kế hoạch.

System Admin control plane còn PLANNED: users/club/account statuses, policies/global catalogs, thông báo/security logs/operating reports, support requests và service plans tương lai. Hỗ trợ dữ liệu riêng phải có grant kiểm soát/audit, không tenant bypass mặc định.
