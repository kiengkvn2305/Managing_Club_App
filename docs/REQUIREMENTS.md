# MASTER PROMPT – FCMS PRO

# FOOTBALL CLUB MANAGEMENT SYSTEM

## FULL-STACK WEB + PWA + ANDROID + IOS

# 1. VAI TRÒ VÀ NHIỆM VỤ
Bạn là OpenAI Codex, đóng vai trò một nhóm kỹ sư phát triển phần mềm chuyên nghiệp gồm:

- Senior Software Architect
- Senior Laravel Developer
- Senior Vanilla JavaScript Developer
- Senior Database Engineer
- Senior UI/UX Designer
- Senior Business Analyst
- QA Automation Engineer
- DevOps Engineer
- Mobile Application Engineer
- Chuyên gia nghiệp vụ quản lý câu lạc bộ thể thao
- Chuyên gia nghiệp vụ tài chính và kế toán nội bộ
Nhiệm vụ của bạn là trực tiếp xây dựng một hệ thống phần mềm hoàn chỉnh có tên:

**FCMS PRO – Football Club Management System**

Đây là nền tảng quản lý câu lạc bộ bóng đá, hỗ trợ các đội bóng phong trào, bán chuyên và chuyên nghiệp.

Phần mềm phải có thể hoạt động dưới 3 hình thức:

1. Web Application trên máy tính.
2. Progressive Web App (PWA) có thể cài đặt trên điện thoại.
3. Native-packaged Mobile Application thông qua Capacitor dành cho Android và iOS.
Tôi muốn phát triển hệ thống để sử dụng thực tế và có khả năng mở rộng thành sản phẩm thương mại trong tương lai.

Không được chỉ tạo giao diện minh họa.

Không được tạo các nút bấm giả, dữ liệu giả hoặc nghiệp vụ không hoạt động rồi tuyên bố rằng hệ thống đã hoàn thành.

Phải xây dựng frontend, backend, database, authentication, authorization, business logic, API, validation, testing và documentation.

Bạn được phép chủ động lựa chọn giải pháp kỹ thuật hợp lý trong phạm vi công nghệ quy định.

Nếu một chức năng quá lớn, hãy chia thành các phần nhỏ và triển khai tuần tự, nhưng không được tự ý bỏ chức năng khỏi kế hoạch tổng thể.

Nếu gặp giới hạn môi trường hoặc thời gian thực thi, hãy hoàn thành một phần có thể chạy và kiểm thử được, ghi rõ những gì còn thiếu, sau đó hướng dẫn chính xác cách tiếp tục.

# 2. CÔNG NGHỆ BẮT BUỘC

## 2.1 Frontend
Sử dụng:

- HTML5
- CSS3
- Vanilla JavaScript ES Modules
- Fetch API
- CSS Grid
- CSS Flexbox
- Responsive Design
- Web Components nếu cần thiết
- Chart.js để hiển thị biểu đồ
- SheetJS hoặc giải pháp phù hợp cho thao tác Excel phía frontend khi cần
- Vite làm công cụ phát triển và build frontend
KHÔNG sử dụng:

- React
- Vue
- Angular
- Next.js
- Nuxt.js
- jQuery làm nền tảng chính
- TypeScript
Có thể dùng các thư viện JavaScript nhỏ cho những chức năng cụ thể, nhưng không sử dụng frontend framework.

Tổ chức frontend theo kiến trúc module, tách biệt:

- API Services
- Authentication
- Routing
- State Management
- UI Components
- Form Validation
- Business Features
- Utilities
Tuyệt đối không viết toàn bộ JavaScript của hệ thống trong một file duy nhất.

## 2.2 Backend
Sử dụng:

- PHP phiên bản ổn định tương thích Laravel
- Laravel phiên bản stable phù hợp môi trường
- RESTful API
- Laravel Sanctum
- Laravel Policies
- Form Requests
- Eloquent ORM
- Laravel Queue
- Laravel Scheduler
- Laravel Notifications
- Laravel Excel hoặc PhpSpreadsheet
Áp dụng:

- Controller
- Service
- Repository khi thực sự cần
- Request Validation
- API Resource
- Policy
- Model
- Migration
- Factory
- Seeder
- Event/Listener khi phù hợp
Không lạm dụng design pattern gây phức tạp không cần thiết.

Ưu tiên clean code, dễ kiểm thử và dễ bảo trì.

## 2.3 Database
Sử dụng MySQL 8+.

Yêu cầu:

- Database chuẩn hóa.
- Primary Key.
- Foreign Key.
- Unique Constraint.
- Index hợp lý.
- Database Transaction.
- Referential Integrity.
- Soft Delete khi phù hợp.
- Audit Log.
- Multi-Tenant Data Isolation.
Sử dụng DECIMAL cho dữ liệu tiền tệ.

Không sử dụng FLOAT hoặc DOUBLE để tính toán tài chính.

## 2.4 Mobile Application
Sử dụng:

- Progressive Web App
- Web App Manifest
- Service Worker
- Capacitor
- Capacitor Android
- Capacitor iOS
Mục tiêu là tái sử dụng cùng một codebase frontend HTML/CSS/JS cho web và mobile.

Cần hỗ trợ:

- Giao diện mobile-first.
- Cài đặt ra màn hình chính.
- Splash Screen.
- App Icon.
- Camera khi cần.
- File Picker.
- File Download/Sharing.
- Push Notifications nếu được cấu hình.
- Trạng thái kết nối mạng.
- Trải nghiệm offline phù hợp.
- Điều hướng và nút Back trên Android.
Không được đưa dữ liệu tài chính nhạy cảm vào bộ nhớ offline không an toàn.

## 2.5 Development Environment
Sử dụng:

- Git
- Composer
- npm
- Docker
- Docker Compose
- MySQL
- PHP
- Node.js
- Android Studio cho Android
- Xcode trên macOS cho iOS
Tạo Docker Compose để chạy môi trường phát triển thuận tiện.

Không hardcode credentials.

Sử dụng .env và tạo .env.example.

# 3. MỤC TIÊU SẢN PHẨM
FCMS Pro là hệ thống quản lý nhiều câu lạc bộ bóng đá trên một nền tảng.

Ví dụ:

Một tài khoản có thể:

- Là chủ sở hữu CLB A.
- Là quản lý tài chính CLB B.
- Là cầu thủ CLB C.
Một CLB có thể có:

- Một hoặc nhiều người quản lý.
- Nhiều đội bóng.
- Nhiều cầu thủ.
- Nhiều mùa giải.
- Nhiều quỹ.
- Nhiều tài sản.
- Nhiều trận đấu.
- Nhiều khoản thu chi.
Mỗi CLB phải có không gian dữ liệu riêng.

Mọi API phải kiểm tra quyền truy cập theo CLB.

Không được để quản lý CLB A truy cập dữ liệu riêng của CLB B nếu không có quyền.

# 4. ACTOR
Hệ thống có chính xác 3 actor cấp cao:

1. System Admin.
2. Club Manager.
3. Club Member.
Tất cả actor đều có chức năng cơ bản của người dùng như:

- Đăng ký.
- Đăng nhập.
- Đăng xuất.
- Quên mật khẩu.
- Đổi mật khẩu.
- Cập nhật hồ sơ.
- Đổi ảnh đại diện.
- Xem thông báo.
- Quản lý phiên đăng nhập.
- Cài đặt cá nhân.

## 4.1 System Admin
Chức năng:

- Quản lý người dùng toàn hệ thống.
- Quản lý danh sách CLB.
- Quản lý trạng thái tài khoản.
- Quản lý trạng thái CLB.
- Quản lý chính sách.
- Quản lý báo cáo vận hành hệ thống.
- Quản lý thông báo hệ thống.
- Quản lý nhật ký bảo mật.
- Quản lý gói dịch vụ trong tương lai.
- Xử lý yêu cầu hỗ trợ.
- Quản lý các danh mục toàn hệ thống.
Admin không được tự động đọc toàn bộ dữ liệu tài chính hoặc thông tin riêng tư của mọi CLB nếu chưa có cơ chế cấp quyền hỗ trợ được kiểm soát.

## 4.2 Club Manager
Một Club Manager có thể quản lý nhiều CLB.

Một CLB có thể được quản lý bởi nhiều Club Manager.

Phải hỗ trợ phân quyền theo từng CLB:

- Owner
- Co-Owner
- General Manager
- Treasurer
- Human Resource Manager
- Equipment Manager
- Sporting Manager
Đây là các bộ quyền hoặc chức danh quản lý, không phải actor cấp cao riêng biệt.

Ví dụ:

Người dùng A có quyền quản lý tài chính tại CLB X nhưng không được sửa tài chính CLB Y.

Quyền của một người tại CLB này không tự động áp dụng cho CLB khác.

## 4.3 Club Member
Chức năng:

- Xem CLB đang tham gia.
- Xem hồ sơ cá nhân.
- Xem lịch thi đấu.
- Xác nhận tham gia.
- Điểm danh.
- Xem công nợ cá nhân.
- Xem lịch sử thanh toán.
- Theo dõi thống kê cá nhân.
- Xem thông báo.
- Tham gia biểu quyết.
- Đăng ký sự kiện.
- Xem thông tin CLB được công khai.

# 5. MODULE 1 – AUTHENTICATION & ACCOUNT
Xây dựng đầy đủ:

- Register.
- Login.
- Logout.
- Forgot Password.
- Reset Password.
- Change Password.
- Update Profile.
- Avatar Upload.
- Session Management.
- Account Activation/Deactivation.
Yêu cầu:

- Password hashing an toàn.
- Validation đầy đủ.
- Rate limiting.
- Login error handling.
- CSRF protection phù hợp.
- Session/cookie security.
- Authorization middleware.
- API response thống nhất.
Ưu tiên Sanctum session cookie cho ứng dụng web cùng origin.

Thiết kế cơ chế xác thực mobile an toàn riêng khi đóng gói Capacitor; không mặc định lưu access token lâu dài trong localStorage.

# 6. MODULE 2 – CLUB MANAGEMENT
Chức năng:

- Tạo CLB.
- Chỉnh sửa CLB.
- Logo.
- Ảnh bìa.
- Mô tả.
- Ngày thành lập.
- Địa chỉ.
- Sân nhà.
- Loại hình sân 5/7/11.
- Thông tin liên hệ.
- Cấu hình CLB.
- Danh sách thành viên.
- Danh sách quản lý.
- Phân quyền.
- Lời mời tham gia.
- Rời CLB.
- Chuyển giao quyền sở hữu.
- Lưu trữ CLB.
Phải hỗ trợ nhiều đội bóng thuộc một CLB.

Ví dụ:

CLB FCMS có:

- FCMS Team A.
- FCMS Team B.
- FCMS U18.
- FCMS 7-a-side.
- FCMS 11-a-side.

# 7. MODULE 3 – HUMAN RESOURCE MANAGEMENT
Quản lý hồ sơ nhân sự:

- Member ID.
- Full Name.
- Date of Birth.
- Phone.
- Email.
- Address.
- Avatar.
- Jersey Number.
- Primary Position.
- Secondary Position.
- Preferred Foot.
- Height.
- Weight.
- Joining Date.
- Membership Status.
- Team.
- Notes.
Vị trí cầu thủ:

- GK
- CB
- LB
- RB
- CDM
- CM
- CAM
- LM
- RM
- LW
- RW
- ST
- CF
Cho phép tùy chỉnh vị trí theo sân 5, 7 và 11.

Chức năng:

- CRUD nhân sự.
- Tìm kiếm.
- Lọc.
- Sắp xếp.
- Phân trang.
- Import Excel.
- Export Excel.
- Lịch sử tham gia.
- Chuyển đội.
- Cập nhật trạng thái.
- Phát hiện trùng hồ sơ.
- Liên kết hồ sơ với tài khoản.
Không bắt buộc cầu thủ được nhập từ Excel phải đăng ký tài khoản ngay.

# 8. MODULE 4 – SMART EXCEL IMPORT/EXPORT
Đây là chức năng bắt buộc và cần làm cẩn thận.

Quy trình:

1. Download Excel Template.
2. Upload Excel.
3. Validate File.
4. Map Excel Columns.
5. Parse Data.
6. Detect Duplicate.
7. Preview.
8. Display Row Errors.
9. Choose Import Mode.
10. Confirm Import.
11. Execute Import.
12. Generate Import Result.
Các chế độ:

- Insert Only.
- Update Only.
- Insert and Update.
Phải hỗ trợ:

- XLSX.
- CSV.
- Dữ liệu tiếng Việt Unicode.
- Kiểm tra ngày sinh.
- Kiểm tra số điện thoại.
- Kiểm tra email.
- Kiểm tra số áo trong phạm vi đội và thời điểm áp dụng.
- Kiểm tra thành viên trùng.
- Import nhiều dòng.
- Báo lỗi theo dòng.
- Export danh sách lỗi.
Không được import dữ liệu trước khi người dùng xác nhận.

File upload phải được kiểm tra định dạng, kích thước, nội dung và lưu trữ an toàn.

# 9. MODULE 5 – FINANCIAL MANAGEMENT
Đây là phân hệ quan trọng nhất.

Thiết kế hệ thống tài chính nội bộ giống phần mềm kế toán thu nhỏ.

## 9.1 Quản lý quỹ
Mỗi CLB có thể tạo nhiều quỹ:

- General Fund.
- Stadium Fund.
- Equipment Fund.
- Tournament Fund.
- Charity Fund.
- Custom Fund.
Mỗi quỹ có:

- Fund ID.
- Fund Name.
- Currency.
- Opening Balance.
- Current Balance.
- Status.
- Description.
Số dư phải được tính toán hoặc đối soát từ các bút toán hợp lệ.

Không cho phép sửa số dư trực tiếp mà không có nghiệp vụ điều chỉnh.

## 9.2 Thu chi
Các nghiệp vụ:

- Income.
- Expense.
- Fund Transfer.
- Adjustment.
- Reversal.
- Reimbursement.
Mỗi giao dịch có:

- Transaction ID.
- Transaction Code.
- Fund.
- Category.
- Amount.
- Description.
- Transaction Date.
- Creator.
- Approver.
- Payment Method.
- Attachment.
- Status.
- Audit History.
Các trạng thái:

- Draft.
- Pending Approval.
- Approved.
- Rejected.
- Posted.
- Reversed.
Thiết kế state machine và quy tắc chuyển trạng thái rõ ràng.

## 9.3 Phê duyệt chi
Hỗ trợ:

- Approval Workflow.
- Multiple Approval Levels.
- Approval Threshold.
- Rejection Reason.
- Approval History.
Ví dụ:

Khoản chi lớn hơn hạn mức cấu hình phải được chủ CLB duyệt.

## 9.4 Báo cáo kế toán
Xây dựng:

- Income Report.
- Expense Report.
- Cash Book.
- Fund Summary.
- Monthly Financial Report.
- Annual Financial Report.
- Receivable Report.
- Payment Report.
- Transaction Audit Report.
Excel xuất ra phải có:

- Tiêu đề.
- Tên CLB.
- Kỳ báo cáo.
- Ngày xuất.
- Tổng thu.
- Tổng chi.
- Số dư đầu kỳ.
- Số dư cuối kỳ.
- Danh sách giao dịch.
- Thông tin chứng từ.
- Công thức hoặc tổng hợp chính xác.
Phải đảm bảo số dư:

Closing Balance = Opening Balance + Income - Expense

Các khoản chuyển quỹ nội bộ không được tính trùng vào tổng thu chi toàn CLB.

## 9.5 Kiểm soát tài chính
Bắt buộc:

- Database Transaction.
- Concurrency Control.
- Idempotency.
- Audit Log.
- Financial Period Locking.
- Decimal Arithmetic.
- Authorization.
- Duplicate Prevention.
- Reversal Instead of Silent Deletion.
Không được hoàn thành module tài chính nếu chưa có automated tests cho những quy tắc này.

# 10. MODULE 6 – MEMBER DEBT MANAGEMENT
Xây dựng:

- Monthly Membership Fee.
- Match Fee.
- Tournament Fee.
- Penalty Fee.
- Other Receivables.
- Partial Payment.
- Full Payment.
- Payment History.
- Debt Reminder.
- Debt Adjustment.
- Debt Waiver.
Hỗ trợ:

- Thiết lập mức thu theo CLB.
- Mức thu theo thành viên.
- Miễn giảm.
- Theo dõi hạn thanh toán.
- Danh sách quá hạn.
- Báo cáo công nợ Excel.
Không được tính công nợ trực tiếp bằng cách cộng trừ tùy tiện các giao dịch không có quan hệ nghiệp vụ.

Thiết kế bảng khoản phải thu, phân bổ thanh toán và điều chỉnh rõ ràng.

# 11. MODULE 7 – AUTOMATIC MATCH COST SPLITTING
Chức năng tự động chia tiền sân.

Dữ liệu:

- Stadium Cost.
- Referee Cost.
- Water Cost.
- Equipment Cost.
- Other Cost.
- Sponsorship Offset.
- Participating Players.
Các cách tính:

- Equal Split.
- Weighted Split.
- Custom Amount.
- Guest Fee.
- Exempt Members.
Phải xử lý số tiền lẻ chính xác.

Không được tạo ra chênh lệch tổng tiền khi chia.

Cho phép xem trước số tiền từng người phải đóng.

Sau khi xác nhận, tạo công nợ tương ứng.

# 12. MODULE 8 – PAYMENT & VIETQR
Tích hợp QR thanh toán tại Việt Nam.

Yêu cầu:

- Sinh nội dung chuyển khoản duy nhất.
- Sinh QR với thông tin tài khoản và số tiền.
- Liên kết khoản thanh toán với công nợ.
- Cho phép tải QR.
- Cho phép upload ảnh chuyển khoản.
- Trạng thái chờ xác nhận.
- Quản lý xác nhận thanh toán.
Nếu chưa có tích hợp API ngân hàng, chỉ được hỗ trợ xác nhận thủ công.

Không được hiển thị thanh toán thành công chỉ vì người dùng quét QR hoặc tải ảnh lên.

# 13. MODULE 9 – INVENTORY & ASSET MANAGEMENT
Quản lý:

- Jersey.
- Shorts.
- Football.
- Goalkeeper Gloves.
- Training Bibs.
- Cones.
- Medical Kit.
- Other Equipment.
Chức năng:

- Inventory CRUD.
- Stock In.
- Stock Out.
- Equipment Assignment.
- Equipment Return.
- Damaged Items.
- Lost Items.
- Stock Adjustment.
- Stocktaking.
- Asset Disposal.
- Inventory Report.
- Low Stock Alert.
Mỗi thay đổi tồn kho phải có lịch sử.

Không cho tồn kho âm khi nghiệp vụ không cho phép.

Phân biệt vật tư tiêu hao với tài sản có thể cấp phát và thu hồi.

# 14. MODULE 10 – MATCH MANAGEMENT
Chức năng:

- Create Match.
- Edit Match.
- Cancel Match.
- Match Calendar.
- Opponent.
- Stadium.
- Start Time.
- End Time.
- Match Type.
- Player Registration.
- Starting Lineup.
- Substitute Players.
- Match Result.
- Match Expenses.
- Match Statistics.
Hỗ trợ sân 5, sân 7, sân 11.

Xây dựng giao diện lịch tháng, tuần và danh sách.

# 15. MODULE 11 – ATTENDANCE MANAGEMENT
Chức năng:

- RSVP.
- Present.
- Absent.
- Late.
- Excused.
- QR Attendance.
- Manual Attendance.
- Attendance History.
- Attendance Report.
Cho phép quản lý xác nhận điểm danh.

Không cho phép một thành viên được điểm danh trùng tại cùng một sự kiện.

Có thể tách xác nhận tham gia trước trận với điểm danh thực tế.

# 16. MODULE 12 – PLAYER STATISTICS
Thống kê:

- Matches Played.
- Goals.
- Assists.
- Yellow Cards.
- Red Cards.
- Clean Sheets.
- Goals Conceded.
- Attendance Rate.
- Player Rating.
- Season Statistics.
Cho phép xem:

- Thống kê cá nhân.
- Thống kê theo đội.
- Thống kê theo CLB.
- Thống kê theo mùa giải.
- Bảng xếp hạng cầu thủ.
Không tạo thống kê giả từ dữ liệu không tồn tại.

# 17. MODULE 13 – BIRTHDAY & EVENT MANAGEMENT
Chức năng:

- Birthday Calendar.
- Upcoming Birthdays.
- Birthday Notifications.
- Club Anniversary.
- Club Party.
- Team Building.
- Charity Events.
- Internal Events.
Thông báo:

- In-App Notification.
- Email Notification.
- Push Notification khi được hỗ trợ và cấu hình.
Không công khai ngày sinh hoặc dữ liệu cá nhân nhạy cảm nếu thành viên chưa được phép hoặc chính sách không cho phép.

# 18. MODULE 14 – DISCIPLINE MANAGEMENT
Quản lý:

- Club Rules.
- Violations.
- Yellow/Red Cards.
- Internal Penalties.
- Disciplinary Decisions.
- Appeals.
- Disciplinary History.
Nếu hình phạt có giá trị tài chính, phải liên kết với phân hệ công nợ.

# 19. MODULE 15 – RECRUITMENT MANAGEMENT
Chức năng:

- Recruitment Posts.
- Position Requirements.
- Application Form.
- Candidate Profiles.
- Application Status.
- Trial Match.
- Trial Evaluation.
- Acceptance/Rejection.
- Convert Candidate to Member.

# 20. MODULE 16 – SPONSORSHIP MANAGEMENT
Quản lý:

- Sponsors.
- Contracts.
- Sponsorship Value.
- Cash Sponsorship.
- Equipment Sponsorship.
- Sponsorship Period.
- Sponsor Benefits.
- Sponsor Reports.
Liên kết nghiệp vụ tài trợ tiền với tài chính và tài trợ hiện vật với kho.

# 21. MODULE 17 – INTERNAL VOTING
Chức năng:

- Create Poll.
- Poll Options.
- Eligible Voters.
- Voting Deadline.
- One Vote Per User.
- Voting Results.
- Poll History.
Phải kiểm soát bỏ phiếu trùng.

# 22. MODULE 18 – PUBLIC CLUB WEBSITE
Mỗi CLB có trang giới thiệu công khai.

Bao gồm:

- Club Profile.
- Club Logo.
- Cover Photo.
- Club History.
- Club Achievements.
- Public Squad.
- Match Schedule.
- Recruitment.
- Sponsors.
- Contact Information.
Dữ liệu cá nhân riêng tư không được công khai mặc định.

# 23. EXTRA MODULE – AUTOMATION & AI
Đây là nhóm chức năng nâng cao.

Automation:

- Nhắc sinh nhật.
- Nhắc đóng quỹ.
- Nhắc lịch đá.
- Cảnh báo khoản chi bất thường.
- Nhắc duyệt giao dịch.
- Cảnh báo tồn kho thấp.
- Tạo báo cáo hàng tháng.
AI Assistant:

- Tóm tắt hoạt động CLB.
- Phân tích báo cáo.
- Phát hiện dấu hiệu bất thường trong thu chi.
- Gợi ý thống kê.
- Trả lời câu hỏi dựa trên dữ liệu được phép truy cập.
Không được tự ý gửi dữ liệu cá nhân hoặc tài chính cho dịch vụ AI bên ngoài khi chưa có cấu hình, quyền truy cập và cơ sở xử lý phù hợp.

AI không được trực tiếp sửa số dư hoặc tự động duyệt thanh toán.

# 24. UI/UX DESIGN REQUIREMENTS
Tôi muốn giao diện đạt chất lượng của một sản phẩm SaaS hiện đại.

Phong cách:

- Professional Football Management Platform.
- Modern Sports Dashboard.
- Premium UI.
- Clean Layout.
- Consistent Design.
- Mobile First.
- Dark Mode.
- Light Mode.
- Smooth Interaction.
- Clear Typography.
- Accessible UI.
- Responsive Design.
Màu sắc gợi ý:

Primary:

- Deep Navy.
- Emerald Green.
Secondary:

- White.
- Light Gray.
- Dark Slate.
Accent:

- Gold hoặc Electric Green, sử dụng có kiểm soát.
Giao diện phải có bản sắc bóng đá nhưng không lạm dụng hình ảnh làm giảm khả năng đọc.

Bố cục desktop:

- Sidebar bên trái.
- Header phía trên.
- Main Content.
- Dashboard Cards.
- Charts.
- Data Tables.
- Filters.
- Action Buttons.
- Dialogs.
Bố cục mobile:

- Mobile Header.
- Bottom Navigation.
- Touch-Friendly Buttons.
- Responsive Cards.
- Mobile Forms.
- Mobile Tables có chiến lược hiển thị phù hợp.
- Safe Area Support.
- Loading/Error/Empty States.
Không được chỉ thu nhỏ giao diện desktop cho điện thoại.

Các trang phải được tối ưu độc lập cho màn hình nhỏ.

# 25. FRONTEND ARCHITECTURE
Hãy tổ chức frontend rõ ràng theo module.

Ví dụ:

frontend/
index.html
package.json
vite.config.js
public/
icons/
images/
manifest.webmanifest
src/
main.js
app.js
router/
api/
auth/
components/
layouts/
pages/
public/
admin/
manager/
member/
modules/
clubs/
members/
finance/
inventory/
matches/
attendance/
reports/
styles/
base.css
variables.css
layout.css
components.css
responsive.css
utils/
pwa/

Đây chỉ là cấu trúc gợi ý.

Bạn có thể tối ưu nếu có giải pháp tốt hơn.

Yêu cầu:

- Reusable Components.
- Centralized API Client.
- Error Handling.
- Route Guards phía client phục vụ UX, không thay thế backend authorization.
- Form Validation.
- Loading State.
- Empty State.
- Error State.
- Toast Notification.
- Confirmation Dialog.
- Debounced Search.
- Pagination.
- Responsive Tables.

# 26. BACKEND ARCHITECTURE
Tổ chức backend theo Laravel conventions.

Tách rõ:

- Controllers.
- Models.
- Services.
- Policies.
- Requests.
- Resources.
- Jobs.
- Events.
- Listeners.
- Notifications.
- Migrations.
- Seeders.
- Tests.
API phải có:

- Versioning khi hợp lý.
- Pagination.
- Filtering.
- Sorting.
- Validation.
- Authentication.
- Authorization.
- Consistent Error Response.
- Transaction Handling.
Quy định rõ trạng thái HTTP và mã lỗi.

# 27. DATABASE REQUIREMENTS
Trước khi code, thiết kế ERD cho toàn bộ hệ thống.

Các nhóm thực thể tối thiểu:

Accounts:

- users
- user_profiles
- roles
- permissions
- sessions
Clubs:

- clubs
- club_members
- club_managers
- teams
- seasons
- club_invitations
Players:

- player_profiles
- player_team_assignments
Finance:

- funds
- financial_transactions
- transaction_approvals
- transaction_categories
- financial_periods
- receivables
- payments
- payment_allocations
Inventory:

- inventory_items
- stock_movements
- equipment_assignments
- stocktakes
Matches:

- matches
- match_participants
- match_results
- player_statistics
Others:

- events
- attendance
- notifications
- recruitment_posts
- recruitment_applications
- sponsors
- sponsorships
- polls
- poll_votes
- import_jobs
- audit_logs
Được phép bổ sung, đổi tên hoặc tách bảng theo thiết kế chuẩn hóa.

Mỗi bảng phải có các ràng buộc cần thiết.

Các bảng thuộc CLB phải bảo đảm dữ liệu luôn gắn với đúng tenant.

Không được tin tưởng club_id gửi từ frontend khi xác định quyền.

Phải có cơ chế chống liên kết dữ liệu giữa hai CLB sai phạm vi.

# 28. PWA IMPLEMENTATION
Hãy triển khai:

- Web App Manifest.
- App Name.
- Short Name.
- App Icons.
- Theme Color.
- Display Standalone.
- Service Worker.
- App Shell Caching.
- Update Handling.
- Offline Fallback Page.
Yêu cầu:

- Không cache dữ liệu tài chính nhạy cảm một cách thiếu kiểm soát.
- Không cache response có chứa thông tin riêng tư giữa các phiên người dùng.
- Không làm hỏng cơ chế authentication.
- Không coi thao tác offline là giao dịch đã hoàn thành.
- Hiển thị rõ khi không có kết nối mạng.

# 29. CAPACITOR IMPLEMENTATION
Tích hợp Capacitor vào frontend.

Yêu cầu:

- capacitor.config.
- Android Project.
- iOS Project khi môi trường hỗ trợ.
- App Identifier.
- App Name.
- App Icon.
- Splash Screen.
- File Handling.
- Camera Permissions khi cần.
- Network Status.
- Back Button Handling.
- Safe Area Handling.
- Native Notification Integration khi được cấu hình.
Hướng dẫn:

1. Build web frontend.
2. Sync Capacitor.
3. Open Android Studio.
4. Build APK.
5. Build AAB.
6. Test Android.
7. Build iOS bằng Xcode khi có macOS.
8. Test iOS.
Nếu môi trường hiện tại không hỗ trợ Android Studio hoặc Xcode, không được tuyên bố đã build thành công APK/IPA.

Thay vào đó, hoàn thiện mã nguồn có thể build, xác minh phần có thể kiểm tra và cung cấp hướng dẫn thực hiện các bước còn lại.

Không triển khai bằng cách chỉ mở URL của website trong một WebView trống.

Ứng dụng phải có giao diện thích ứng, điều hướng và tích hợp mobile phù hợp.

# 30. TESTING REQUIREMENTS
Backend Tests:

- Unit Tests.
- Feature Tests.
- Authentication Tests.
- Authorization Tests.
- Tenant Isolation Tests.
- Finance Tests.
- Excel Import Tests.
- Inventory Tests.
- Attendance Tests.
Frontend Tests:

- JavaScript Unit Tests.
- Form Validation Tests.
- API Integration Tests.
- UI Interaction Tests.
- Responsive Checks.
E2E Tests:

- User Registration.
- Login.
- Create Club.
- Invite Manager.
- Add Member.
- Import Excel.
- Create Fund.
- Record Income.
- Record Expense.
- Approve Transaction.
- Calculate Member Debt.
- Create Match.
- Attendance.
- Export Report.
Các bài kiểm thử quan trọng phải bao gồm:

- Hai người đồng thời ghi giao dịch.
- Gửi lại request thanh toán.
- Sửa dữ liệu CLB khác.
- Import Excel trùng.
- Đảo giao dịch đã ghi nhận.
- Xóa người đang quản lý CLB.
- Chuyển quyền sở hữu.
- Giao dịch không đủ điều kiện.
- Truy cập trái phép.
- Sửa trực tiếp ID trên API request.

# 31. SECURITY REQUIREMENTS
Áp dụng:

- OWASP best practices.
- Secure Authentication.
- Backend Authorization.
- Tenant Isolation.
- CSRF Protection.
- XSS Prevention.
- SQL Injection Prevention.
- Secure File Upload.
- Rate Limiting.
- Secure Logging.
- Input Validation.
- Output Encoding.
- Sensitive Data Protection.
- Backup Strategy.
- Environment Secret Management.
Không bao giờ:

- Lưu mật khẩu dạng plain text.
- Tin vào role do frontend gửi.
- Cho phép chỉnh sửa quỹ trái phép.
- Trả về toàn bộ dữ liệu hệ thống nếu không có quyền.
- Để lộ thông tin cấu hình nhạy cảm.

# 32. PROJECT DOCUMENTATION
Tạo thư mục docs/ chứa:

- README.md
- REQUIREMENTS.md
- ARCHITECTURE.md
- DATABASE.md
- ERD.md
- API.md
- BUSINESS_RULES.md
- PERMISSIONS.md
- INSTALLATION.md
- DEVELOPMENT.md
- DEPLOYMENT.md
- TESTING.md
- MOBILE_BUILD.md
- ROADMAP.md
- CHANGELOG.md
Tài liệu phải phản ánh đúng mã nguồn thực tế.

Không ghi tính năng đã hoàn thành nếu chưa có implementation và chưa kiểm tra ở mức khả dụng.

# 33. DEVELOPMENT ROADMAP
Phải phát triển theo từng phase.

## PHASE 0 – PROJECT FOUNDATION

- Inspect current repository.
- Detect existing project.
- Set up project structure.
- Set up Laravel.
- Set up MySQL.
- Set up Vanilla JS frontend.
- Set up Vite.
- Set up Docker.
- Set up environment.
- Set up Git Ignore.
- Set up Coding Standards.
- Set up base tests.

## PHASE 1 – CORE PLATFORM

- Authentication.
- User Profile.
- Clubs.
- Multi-Club Management.
- Roles and Permissions.
- Club Invitations.
- Member Management.
- Excel Import/Export.
- Core Dashboard.

## PHASE 2 – FINANCIAL SYSTEM

- Funds.
- Transactions.
- Approval Workflow.
- Receivables.
- Payments.
- Financial Reports.
- Accounting Excel Export.
- Financial Audit Logs.

## PHASE 3 – FOOTBALL OPERATIONS

- Matches.
- Training.
- Attendance.
- Cost Splitting.
- Payment QR.
- Player Statistics.
- Seasons.

## PHASE 4 – MANAGEMENT MODULES

- Inventory.
- Assets.
- Birthdays.
- Events.
- Discipline.
- Notifications.

## PHASE 5 – ADVANCED FEATURES

- Recruitment.
- Sponsorship.
- Polling.
- Public Club Websites.
- Automation.
- AI Assistant integration foundation.

## PHASE 6 – MOBILE APPLICATION

- Responsive Optimization.
- PWA.
- Capacitor.
- Android Configuration.
- iOS Configuration.
- Mobile Testing.
- Packaging Instructions.
Lưu ý: Thiết kế responsive và mobile-first phải được thực hiện từ Phase 0, không đợi Phase 6.

## PHASE 7 – QUALITY & RELEASE

- Full Integration Tests.
- Security Review.
- Performance Optimization.
- Database Optimization.
- Documentation Review.
- Deployment Configuration.
- Final Verification.

# 34. QUY TẮC KHI CODING
Bạn phải thực hiện theo các nguyên tắc:

1. Đọc cấu trúc repository trước khi sửa code.
2. Không xóa mã nguồn hiện có nếu chưa xác định rõ ảnh hưởng.
3. Không tự ý xóa database hoặc dữ liệu thật.
4. Không ghi đè .env chứa secrets.
5. Không tạo một file chứa toàn bộ hệ thống.
6. Không để những chức năng giả.
7. Không tạo mock data ở production.
8. Không hardcode số liệu thống kê.
9. Không tạo API không có authorization.
10. Không bỏ qua validation.
11. Không bỏ qua transaction ở nghiệp vụ tài chính.
12. Không viết logic nghiệp vụ quan trọng chỉ ở frontend.
13. Không tuyên bố đã kiểm thử khi chưa chạy.
14. Không tuyên bố chức năng hoàn thành nếu chưa hoạt động.
15. Không âm thầm bỏ qua lỗi.
16. Không sửa những file không liên quan nếu không cần.
17. Không đổi công nghệ frontend đã lựa chọn.
18. Không sử dụng React, Vue, Angular hoặc TypeScript.
19. Không biến sản phẩm thành một bộ trang HTML tĩnh.
20. Luôn ưu tiên hệ thống có thể chạy thật.

# 35. YÊU CẦU TỰ KIỂM TRA SAU MỖI MODULE
Sau khi hoàn thành mỗi module:

1. Kiểm tra syntax.
2. Chạy automated tests.
3. Kiểm tra database migration.
4. Kiểm tra API.
5. Kiểm tra authentication.
6. Kiểm tra authorization.
7. Kiểm tra tenant isolation.
8. Kiểm tra business rules.
9. Kiểm tra giao diện desktop.
10. Kiểm tra giao diện mobile.
11. Cập nhật documentation.
12. Ghi lại kết quả.
Nếu test thất bại, hãy phân tích nguyên nhân và sửa lỗi trước khi triển khai module tiếp theo.

Không được thay đổi test chỉ để che giấu lỗi.

# 36. QUY TẮC BÁO CÁO TIẾN ĐỘ
Sau mỗi phase, hãy cung cấp:

- Các chức năng đã hoàn thành.
- Các file đã tạo.
- Các file đã sửa.
- Database migrations mới.
- API endpoints mới.
- Tests đã chạy.
- Tests thành công/thất bại.
- Lỗi còn tồn tại.
- Những gì chưa hoàn thành.
- Hướng dẫn chạy.
- Phase tiếp theo.
Tạo file PROGRESS.md để theo dõi tiến độ.

Tạo file NEXT_STEPS.md để ghi lại những công việc tiếp theo.

Không tự đánh dấu hoàn thành một phase nếu thiếu chức năng cốt lõi hoặc chưa kiểm thử được.

# 37. TIÊU CHÍ NGHIỆM THU CUỐI CÙNG
Hệ thống được coi là đủ điều kiện nghiệm thu khi:

1. Frontend chạy được.
2. Backend chạy được.
3. Database kết nối thành công.
4. Authentication hoạt động.
5. Phân quyền hoạt động đúng.
6. Có thể tạo nhiều CLB.
7. Có thể quản lý nhiều CLB trên một tài khoản.
8. Có thể có nhiều quản lý trên một CLB.
9. Nhân sự CRUD hoạt động.
10. Import Excel hoạt động.
11. Export Excel hoạt động.
12. Quỹ và giao dịch hoạt động đúng.
13. Công nợ tính toán chính xác.
14. Không truy cập chéo CLB trái phép.
15. Lịch thi đấu hoạt động.
16. Điểm danh hoạt động.
17. Tài sản được quản lý chính xác.
18. Dashboard lấy dữ liệu thật.
19. Giao diện chạy tốt trên desktop/mobile.
20. PWA cài đặt được trong môi trường được hỗ trợ.
21. Capacitor được cấu hình và có thể build trong môi trường phù hợp.
22. Các automated tests quan trọng chạy thành công.
23. Tài liệu cài đặt đầy đủ.
24. Không còn lỗi nghiêm trọng đã biết trong các luồng nghiệp vụ chính.
Nếu chưa đạt tất cả tiêu chí, phải báo cáo chính xác trạng thái thay vì tuyên bố sản phẩm hoàn thiện.

# 38. BẮT ĐẦU THỰC HIỆN NGAY
Bạn đang làm việc trong thư mục dự án FCMS Pro.

Hãy bắt đầu trực tiếp, không chỉ đưa ra lời khuyên hoặc mô tả lý thuyết.

Thực hiện theo thứ tự:

STEP 1:
Kiểm tra toàn bộ repository và môi trường hiện tại.

STEP 2:
Nếu chưa có source code, khởi tạo project theo công nghệ quy định.

STEP 3:
Tạo kiến trúc tổng thể, sơ đồ ERD, business rules và API design.

STEP 4:
Tạo project backend Laravel và frontend HTML/CSS/Vanilla JS.

STEP 5:
Thiết lập Docker, MySQL, migrations và seeders.

STEP 6:
Triển khai authentication và hệ thống phân quyền nhiều CLB.

STEP 7:
Triển khai toàn bộ chức năng theo roadmap.

STEP 8:
Kiểm thử, sửa lỗi và cập nhật tài liệu sau mỗi module.

STEP 9:
Tích hợp PWA và Capacitor.

STEP 10:
Hoàn thiện hướng dẫn build và triển khai hệ thống.

Được phép chủ động triển khai các bước đã rõ yêu cầu mà không cần liên tục hỏi xác nhận.

Chỉ hỏi khi thiếu một quyết định có thể gây mất dữ liệu, tạo chi phí bên ngoài, ảnh hưởng đến bảo mật, hoặc làm thay đổi lớn phạm vi dự án.

Không tự ý thực hiện thao tác phá hủy, công khai repository, deploy production hoặc phát hành ứng dụng mà chưa có sự cho phép.

Nếu không thể hoàn thiện toàn bộ hệ thống trong một phiên làm việc, hãy dừng tại ranh giới module an toàn, bảo đảm phần đã hoàn thành chạy được, cập nhật PROGRESS.md và NEXT_STEPS.md với trạng thái trung thực để phiên Codex tiếp theo có thể tiếp tục.

**BẮT ĐẦU XÂY DỰNG FCMS PRO NGAY TỪ PHASE 0.**