import { state as s, currentClub } from "./state.js";
import {
  escapeHtml as e,
  initials,
  number,
  roleLabel,
  statusLabel,
  canManageRoster,
  canManageClub,
} from "./utils.js";
import { icon, button, field, empty } from "./components/ui.js";
export const titles = {
  dashboard: "Tổng quan",
  members: "Thành viên",
  teams: "Đội bóng",
  "team-roster": "Thành viên đội bóng",
  finance: "Sổ thu chi CLB",
  activities: "Hoạt động CLB",
  lineups: "Đội hình trận đấu",
  "club-members": "Thành viên BCN",
  club: "Câu lạc bộ",
  profile: "Tài khoản",
  accept: "Nhận lời mời",
  roadmap: "Lộ trình",
};
const avatar = (name) => `<span class="avatar">${e(initials(name))}</span>`;
const badge = (status) =>
  `<span class="badge ${e(status)}">${e(statusLabel(status))}</span>`;
const heading = (title, text, actions = "") =>
  `<div class="page-top"><div><div class="eyebrow">KHÔNG GIAN CÂU LẠC BỘ</div><h1>${title}</h1><p>${text}</p></div><div class="page-actions">${actions}</div></div>`;
export function authPage() {
  const register = s.authMode === "register";
  return `<div class="auth-layout"><section class="auth-story"><div class="brand"><img src="/icons/crest.svg" alt="">FCMS <small>PRO</small></div><div><span class="eyebrow">MỘT CÂU LẠC BỘ. MỘT TINH THẦN.</span><h1>Quản lý gọn gàng.<br>Ra sân <em>hết mình.</em></h1><p>Từ đội hình đến từng thành viên, xây dựng một câu lạc bộ có tổ chức và sẵn sàng cho những mục tiêu lớn hơn.</p><div class="pitch" aria-hidden="true"><i></i><b>F</b></div></div><small>FCMS PRO · DÀNH CHO NHỮNG NGƯỜI YÊU BÓNG ĐÁ</small></section><main id="main-content" class="auth-content" tabindex="-1"><button class="icon-button theme-control" data-action="theme" aria-label="Đổi giao diện sáng tối">${icon("moon")}</button><div class="auth-card"><div class="brand mobile-brand"><img src="/icons/crest.svg" alt="">FCMS <small>PRO</small></div>${s.invite ? '<div class="notice">Đăng nhập bằng email được mời để tham gia câu lạc bộ.</div>' : ""}<h2>${register ? "Bắt đầu hành trình mới" : "Chào mừng trở lại"}</h2><p>${register ? "Tạo tài khoản để xây dựng câu lạc bộ của bạn." : "Đăng nhập để kết nối với đội bóng của bạn."}</p><form data-form="${register ? "register" : "login"}" novalidate><div class="form-error" data-form-error role="alert"></div>${register ? field("name", "Họ và tên", "", "text", true) : ""}${field("email", "Địa chỉ email", "", "email", true)}${field("password", "Mật khẩu", "", "password", true)}${register ? field("password_confirmation", "Xác nhận mật khẩu", "", "password", true) : ""}<button type="submit" class="button wide">${register ? "Tạo tài khoản" : "Đăng nhập"}${icon("arrow")}</button></form><p class="auth-footer">${register ? "Đã có tài khoản?" : "Mới đến với FCMS PRO?"} <button class="text-button" data-action="auth-mode">${register ? "Đăng nhập" : "Tạo tài khoản"}</button></p><small class="auth-security">${icon("shield")} Phiên đăng nhập an toàn</small></div></main></div>`;
}
function dashboard() {
  const c = currentClub(),
    d = s.dashboard,
    allowed = canManageRoster(c.membership_role);
  const stats = [
    ["Tổng thành viên", d.members_count, "users"],
    ["Đang hoạt động", d.active_members_count, "check"],
    ["Đội bóng", d.teams_count, "layers"],
    ["Ban quản lý", d.managers_count, "shield"],
  ];
  const checks = [
    ["Tạo không gian câu lạc bộ", true, "club"],
    ["Thêm đội bóng đầu tiên", d.teams_count > 0, "teams"],
    ["Xây dựng danh sách thành viên", d.members_count > 0, "members"],
  ];
  return `${heading("Tổng quan câu lạc bộ", `Một ngày mới, cùng ${e(c.name)} tiến về phía trước.`, button("new-club", "Tạo CLB", "plus", "secondary"))}<section class="hero"><div><span class="eyebrow">KHÔNG GIAN CỦA ĐỘI BÓNG</span><h2>Chung một màu áo.<br>Cùng một hành trình.</h2><p>Quản lý con người và đội hình của bạn,<br>để mỗi lần ra sân đều sẵn sàng hơn.</p>${allowed ? button("new-member", "Thêm thành viên") : ""}</div><div class="pitch" aria-hidden="true"><i></i><b>F</b></div></section><div class="stats-grid">${stats.map(([label, value, glyph]) => `<section class="stat"><div><span>${label}</span><i>${icon(glyph)}</i></div><strong>${number(value)}</strong><small>Dữ liệu của câu lạc bộ hiện tại</small></section>`).join("")}</div><div class="dashboard-grid"><section class="panel"><div class="panel-head"><div><h2>Những thành viên mới nhất</h2><p>Hồ sơ được thêm gần đây trong câu lạc bộ</p></div>${allowed ? '<a class="text-link" href="#members">Xem tất cả →</a>' : ""}</div>${!allowed ? empty("Quyền truy cập được bảo vệ", "Thông tin nhân sự do ban quản lý câu lạc bộ phụ trách.") : s.members.length ? s.members.map((m) => `<div class="member-row">${avatar(m.full_name)}<div><strong>${e(m.full_name)}</strong><small>${e(m.team?.name || "Chưa xếp đội")} · ${e(m.position || "Chưa cập nhật vị trí")}</small></div>${badge(m.status)}<span class="jersey">${m.jersey_number != null ? "#" + e(m.jersey_number) : "—"}</span></div>`).join("") : empty("Đội hình đang chờ bạn", "Thêm thành viên đầu tiên để bắt đầu xây dựng hồ sơ đội bóng.", "new-member", "Thêm thành viên")}</section><section class="panel"><div class="panel-head"><div><h2>Sẵn sàng cho chặng đường mới</h2><p>Từng bước xây dựng câu lạc bộ</p></div>${icon("flag")}</div><div class="panel-body"><p class="muted">Hoàn thiện nền tảng để quản lý câu lạc bộ thuận tiện hơn.</p><div class="progress"><span style="width:${(checks.filter((x) => x[1]).length / 3) * 100}%"></span></div><div class="checklist">${checks.map(([label, done, route]) => `<a href="#${route}"><span class="${done ? "done" : ""}">${done ? icon("check") : "○"}</span>${label}</a>`).join("")}</div><div class="notice">Một đội bóng vững mạnh bắt đầu từ những kết nối tốt.</div></div></section></div>`;
}
function members() {
  const allowed = canManageRoster(currentClub().membership_role),
    f = s.filters,
    meta = s.meta;
  if (!allowed)
    return (
      heading("Thành viên", "Thông tin được bảo vệ theo quyền truy cập.") +
      `<section class="panel">${empty("Liên hệ ban quản lý", "Vai trò hiện tại chưa được cấp quyền xem thông tin nhân sự.")}</section>`
    );
  const team = s.teams.find((t) => String(t.id) === String(f.team_id));
  return `${heading("Thành viên", team ? `Thành viên đội ${e(team.name)} · Đội trưởng: ${e(team.captain_name || "Chưa chọn")}` : "Mỗi con người, một phần không thể thiếu của đội bóng.", (team ? `<button class="button secondary" data-action="team-captain" data-id="${team.id}">Chọn đội trưởng</button>` : "") + button("new-member", "Thêm thành viên"))}<section class="panel"><div class="panel-head"><div><h2>Danh sách thành viên <span class="pill">${number(meta.total || 0)}</span></h2><p>Hồ sơ và trạng thái hoạt động trong câu lạc bộ</p></div></div><form data-form="filters" class="filters" role="search"><input name="q" type="search" aria-label="Tìm thành viên" placeholder="Tìm tên, email, số điện thoại…" value="${e(f.q)}"><select name="team_id" aria-label="Lọc đội"><option value="">Tất cả đội bóng</option>${s.teams.map((t) => `<option value="${t.id}" ${String(t.id) === String(f.team_id) ? "selected" : ""}>${e(t.name)}</option>`).join("")}</select><select name="status" aria-label="Lọc trạng thái"><option value="">Mọi trạng thái</option>${["active", "inactive", "injured"].map((v) => `<option value="${v}" ${v === f.status ? "selected" : ""}>${statusLabel(v)}</option>`).join("")}</select><button class="button secondary" type="submit">Lọc</button><button class="text-button" type="button" data-action="reset">Xóa lọc</button></form>${s.members.length ? `<div class="table-wrap"><table><thead><tr><th>Thành viên</th><th class="hide-mobile">Đội / Vị trí</th><th>Trạng thái</th><th><span class="sr-only">Thao tác</span></th></tr></thead><tbody>${s.members.map((m) => `<tr><td><div class="person">${avatar(m.full_name)}<div><strong>${e(m.full_name)}</strong><small>${e(m.email || m.phone || "Chưa có liên hệ")}</small></div></div></td><td class="hide-mobile">${e(m.team?.name || "Chưa xếp đội")}<small>${e(m.position || "—")} ${m.jersey_number != null ? "· #" + e(m.jersey_number) : ""}</small></td><td>${badge(m.status)}</td><td><div class="row-actions"><button class="icon-button" data-action="edit-member" data-id="${m.id}" aria-label="Sửa ${e(m.full_name)}">${icon("edit")}</button><button class="icon-button danger" data-action="delete-member" data-id="${m.id}" aria-label="Xóa ${e(m.full_name)}">${icon("trash")}</button></div></td></tr>`).join("")}</tbody></table></div><div class="table-footer"><span>${number(meta.total)} thành viên</span><div><button class="icon-button" data-action="prev" aria-label="Trang trước" ${meta.current_page <= 1 ? "disabled" : ""}>←</button><span> ${meta.current_page} / ${meta.last_page} </span><button class="icon-button" data-action="next" aria-label="Trang sau" ${meta.current_page >= meta.last_page ? "disabled" : ""}>→</button></div></div>` : empty("Chưa có thành viên phù hợp", "Thử thay đổi bộ lọc hoặc thêm hồ sơ mới.", "new-member", "Thêm thành viên")}</section>`;
}
function teams() {
  return (
    heading(
      "Đội bóng",
      "Tổ chức đội hình cho từng sân chơi và mục tiêu.",
      canManageRoster(currentClub().membership_role)
        ? button("new-team", "Tạo đội bóng")
        : "",
    ) +
    (s.teams.length
      ? `<div class="team-grid">${s.teams.map((t) => `<section class="panel team-card"><div class="team-symbol">${icon("shield")}</div><span class="pill">SÂN ${e(t.format)} NGƯỜI</span><h2>${e(t.name)}</h2><p>${e(currentClub().name)}</p>${canManageRoster(currentClub().membership_role) ? `<p>Đội trưởng: ${e(t.captain_name || "Chưa chọn")}<br>Quản lý: ${e((t.managers || []).map((m) => m.name).join(", ") || "Chưa chọn")}</p><div class="team-actions"><button class="text-button" data-action="team-members" data-id="${t.id}">Xem thành viên →</button><button class="text-button" data-action="rename-team" data-id="${t.id}">Chỉnh sửa</button><button class="text-button danger" data-action="delete-team" data-id="${t.id}">Xóa đội</button></div>` : ""}</section>`).join("")}</div>`
      : `<section class="panel">${empty("Một đội hình mới đang chờ", "Tạo đội sân 5, 7 hoặc 11 người để sắp xếp các thành viên.")}</section>`)
  );
}
function memberDirectory() {
  if (!canManageRoster(currentClub().membership_role)) return heading("Thành viên CLB", "Thông tin được bảo vệ theo quyền truy cập.");
  const f = s.filters, meta = s.meta;
  const team = s.teams.find((t) => String(t.id) === String(f.team_id));
  const actions = team ? `<button class="button secondary" data-action="select-team-players" data-id="${team.id}">Chọn thành viên</button><button class="button secondary" data-action="team-captain" data-id="${team.id}">Chọn đội trưởng</button>` : button("new-member", "Thêm thành viên");
  return heading(team ? `Thành viên · ${e(team.name)}` : "Thành viên CLB", team ? `Đội trưởng: ${e(team.captain_name || "Chưa chọn")}` : "Tạo hồ sơ thành viên trước, sau đó chọn vào một hoặc nhiều đội.", actions) + `<section class="panel"><form data-form="filters" class="filters" role="search"><input name="q" type="search" aria-label="Tìm thành viên" placeholder="Tìm tên, email, số điện thoại…" value="${e(f.q)}"><select name="team_id" aria-label="Lọc đội"><option value="">Tất cả đội bóng</option>${s.teams.map((t) => `<option value="${t.id}" ${String(t.id) === String(f.team_id) ? "selected" : ""}>${e(t.name)}</option>`).join("")}</select><select name="status" aria-label="Lọc trạng thái"><option value="">Mọi trạng thái</option>${["active", "inactive", "injured"].map((v) => `<option value="${v}" ${v === f.status ? "selected" : ""}>${statusLabel(v)}</option>`).join("")}</select><button class="button secondary" type="submit">Lọc</button><button class="text-button" type="button" data-action="reset">Xóa lọc</button></form>${s.members.length ? `<div class="table-wrap"><table><thead><tr><th>Thành viên</th><th>Đội bóng / vị trí</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>${s.members.map((m) => `<tr><td><button class="text-button" data-action="view-member" data-id="${m.id}">${e(m.full_name)}</button><small>${e(m.email || m.phone || "Chưa có liên hệ")}</small></td><td>${e((m.teams || []).map((t) => t.name).join(", ") || "Chưa xếp đội")}<small>${e(m.position || "Chưa có vị trí")} ${m.jersey_number == null ? "" : `· #${e(m.jersey_number)}`}</small></td><td>${badge(m.status)}</td><td><div class="row-actions"><button class="icon-button" data-action="edit-member" data-id="${m.id}" aria-label="Sửa ${e(m.full_name)}">${icon("edit")}</button><button class="icon-button danger" data-action="delete-member" data-id="${m.id}" aria-label="Xóa ${e(m.full_name)}">${icon("trash")}</button></div></td></tr>`).join("")}</tbody></table></div><div class="table-footer"><span>${number(meta.total)} thành viên</span><div><button class="icon-button" data-action="prev" ${meta.current_page <= 1 ? "disabled" : ""}>←</button> ${meta.current_page} / ${meta.last_page} <button class="icon-button" data-action="next" ${meta.current_page >= meta.last_page ? "disabled" : ""}>→</button></div></div>` : empty("Chưa có thành viên", team ? "Chọn thành viên đã tạo vào đội bóng này." : "Thêm hồ sơ thành viên đầu tiên.")}</section>`;
}
function clubDirectory() {
  if (!canManageRoster(currentClub().membership_role)) return heading("Thành viên CLB", "Thông tin được bảo vệ theo quyền truy cập.");
  const meta = s.meta, f = s.filters;
  return heading("Thành viên CLB", "Hồ sơ cầu thủ và các đội bóng tham gia.", button("new-member", "Thêm thành viên")) + `<section class="panel"><form data-form="filters" class="filters" role="search"><input name="q" type="search" aria-label="Tìm thành viên" placeholder="Tìm tên, email, số điện thoại…" value="${e(f.q)}"><select name="team_id" aria-label="Lọc đội"><option value="">Tất cả đội</option>${s.teams.map((t) => `<option value="${t.id}" ${String(t.id) === String(f.team_id) ? "selected" : ""}>${e(t.name)}</option>`).join("")}</select><select name="status" aria-label="Trạng thái"><option value="">Mọi trạng thái</option>${["active", "inactive", "injured"].map((v) => `<option value="${v}" ${v === f.status ? "selected" : ""}>${e(statusLabel(v))}</option>`).join("")}</select><button class="button secondary">Lọc</button><button class="text-button" type="button" data-action="reset">Xóa lọc</button></form>${s.members.length ? `<div class="table-wrap"><table><thead><tr><th>Thành viên</th><th>Đội tham gia</th><th>Vị trí</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>${s.members.map((m) => `<tr><td><button class="text-button" data-action="view-member" data-id="${m.id}">${e(m.full_name)}</button><small>${e(m.email || m.phone || "Chưa có liên hệ")}</small></td><td>${e((m.teams || []).map((t) => t.name).join(", ") || "Chưa xếp đội")}</td><td>${e(m.position || "—")}</td><td>${badge(m.status)}</td><td><button class="icon-button" data-action="edit-member" data-id="${m.id}" aria-label="Sửa ${e(m.full_name)}">${icon("edit")}</button> <button class="icon-button danger" data-action="delete-member" data-id="${m.id}" aria-label="Xóa ${e(m.full_name)}">${icon("trash")}</button></td></tr>`).join("")}</tbody></table></div><div class="table-footer"><span>${number(meta.total)} thành viên</span><div><button class="icon-button" data-action="prev" aria-label="Trang trước" ${meta.current_page <= 1 ? "disabled" : ""}>←</button> ${meta.current_page} / ${meta.last_page} <button class="icon-button" data-action="next" aria-label="Trang sau" ${meta.current_page >= meta.last_page ? "disabled" : ""}>→</button></div></div>` : empty("Chưa có thành viên phù hợp", "Thêm hồ sơ hoặc thay đổi bộ lọc.")}</section>`;
}
function teamRoster() {
  if (!canManageRoster(currentClub().membership_role)) return heading("Thành viên đội", "Thông tin được bảo vệ theo quyền truy cập.");
  const team = s.teams.find((t) => String(t.id) === s.teamRosterId), meta = s.meta;
  if (!team) return heading("Không tìm thấy đội bóng", "Hãy chọn đội trong danh sách đội bóng.") + '<a class="text-link" href="#teams">← Quay lại đội bóng</a>';
  const actions = `<button class="button secondary" data-action="select-team-players" data-id="${team.id}">Chọn thành viên</button><button class="button secondary" data-action="team-captain" data-id="${team.id}">Chọn đội trưởng</button>`;
  return heading(`Đội ${e(team.name)}`, `Đội trưởng: ${e(team.captain_name || "Chưa chọn")} · ${number(meta.total)} thành viên`, actions) + `<section class="panel"><div class="panel-head"><div><h2>Danh sách đội bóng</h2><p>Vị trí và số áo được quản lý riêng cho đội này.</p></div><a class="text-link" href="#teams">← Các đội bóng</a></div>${s.members.length ? `<div class="table-wrap"><table><thead><tr><th>Thành viên</th><th>Đội trưởng</th><th>Vị trí</th><th>Số áo</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>${s.members.map((m) => { const assignment = (m.teams || []).find((t) => String(t.id) === String(team.id)); const captain = String(team.captain_id) === String(m.id); return `<tr><td><button class="text-button" data-action="view-member" data-id="${m.id}">${e(m.full_name)}</button></td><td><button class="text-button" data-action="set-team-captain" data-id="${m.id}" aria-label="${captain ? "Bỏ chọn" : "Chọn"} đội trưởng ${e(m.full_name)}" aria-pressed="${captain}">${captain ? "O" : "−"}</button></td><td>${e(assignment?.position || "—")}</td><td>${assignment?.jersey_number == null ? "—" : `#${e(assignment.jersey_number)}`}</td><td>${badge(m.status)}</td><td><div class="team-actions"><button class="text-button" data-action="edit-team-position" data-id="${m.id}">Sửa vị trí</button><button class="text-button" data-action="edit-team-jersey" data-id="${m.id}">Sửa số áo</button></div></td></tr>`; }).join("")}</tbody></table></div><div class="table-footer"><span>${number(meta.total)} thành viên</span><div><button class="icon-button" data-action="roster-prev" aria-label="Trang trước" ${meta.current_page <= 1 ? "disabled" : ""}>←</button> ${meta.current_page} / ${meta.last_page} <button class="icon-button" data-action="roster-next" aria-label="Trang sau" ${meta.current_page >= meta.last_page ? "disabled" : ""}>→</button></div></div>` : empty("Chưa có thành viên trong đội", "Chọn thành viên đã tạo trong CLB để thêm vào đội.")}</section>`;
}
function finance() {
  if (!currentClub()?.can_view_finance) return heading("Sổ thu chi", "Bạn không có quyền xem sổ thu chi.");
  const ledger = s.finance, summary = ledger.summary || {}, meta = ledger.meta || {};
  const money = (amount) => `${number(amount)} đ`;
  return heading("Sổ thu chi CLB", "Theo dõi các khoản thu, chi của câu lạc bộ.", currentClub()?.can_manage_finance ? button("new-finance", "Ghi thu chi") : "") + `<div class="stats-grid">${[["Tổng thu", summary.income], ["Tổng chi", summary.expense], ["Số dư", summary.balance]].map(([label, value]) => `<section class="stat"><div><span>${label}</span></div><strong>${e(money(value))}</strong></section>`).join("")}</div><section class="panel"><div class="panel-head"><h2>Khoản thu chi</h2></div>${ledger.data?.length ? `<div class="table-wrap"><table><thead><tr><th>Ngày</th><th>Nội dung</th><th>Loại</th><th>Số tiền</th></tr></thead><tbody>${ledger.data.map((item) => `<tr><td>${e(item.occurred_on)}</td><td>${e(item.description)}</td><td>${item.type === "income" ? "Thu" : "Chi"}</td><td>${e(money(item.amount))}</td></tr>`).join("")}</tbody></table></div><div class="table-footer"><span>${number(meta.total)} khoản</span><div><button class="icon-button" data-action="finance-prev" aria-label="Trang trước" ${meta.current_page <= 1 ? "disabled" : ""}>←</button> ${meta.current_page} / ${meta.last_page} <button class="icon-button" data-action="finance-next" aria-label="Trang sau" ${meta.current_page >= meta.last_page ? "disabled" : ""}>→</button></div></div>` : empty("Chưa có khoản thu chi", "Các khoản đã ghi sẽ hiển thị tại đây.")}</section>`;
}
function clubMembers() {
  if (currentClub().membership_role !== "owner") return heading("Thành viên BCN", "Chỉ chủ câu lạc bộ có quyền quản lý vai trò.");
  return heading("Thành viên BCN", "Gán chức danh cho tài khoản đã tham gia câu lạc bộ.") + `<section class="panel"><div class="panel-head"><div><h2>Tài khoản thành viên</h2><p>Chủ nhiệm, phó chủ nhiệm, thủ quỹ và HLV được xem sổ thu chi; chủ nhiệm và thủ quỹ được ghi khoản mới.</p></div></div>${s.clubMemberships.map((m) => `<div class="member-row"><div><strong>${e(m.name)}</strong><small>${e(m.email)} · ${e(m.title || (m.role === "owner" ? "Chủ nhiệm CLB" : "Thành viên"))}</small></div><button class="text-button" data-action="edit-title" data-id="${m.id}">Đổi vai trò</button></div>`).join("")}</section>`;
}
function financeWithTools() {
  if (!currentClub()?.can_view_finance) return heading("Sổ thu chi", "Bạn không có quyền xem sổ thu chi.");
  const ledger = s.finance, summary = ledger.summary || {}, meta = ledger.meta || {};
  const money = (amount) => `${number(amount)} đ`;
  const actions = button("export-finance", "Xuất Excel", "arrow", "secondary") + (currentClub()?.can_manage_finance ? button("new-finance", "Ghi thu chi") : "");
  const range = s.financeFrom ? `${e(s.financeFrom)} đến ${e(s.financeTo)}` : "Tất cả thời gian";
  return heading("Sổ thu chi CLB", "Theo dõi các khoản thu, chi và chứng từ của câu lạc bộ.", actions) + `<section class="panel"><form class="filters" data-form="finance-filter"><label>Kỳ <select name="period">${[["all", "Tất cả"], ["week", "Tuần"], ["month", "Tháng"], ["year", "Năm"]].map(([value, label]) => `<option value="${value}" ${value === s.financePeriod ? "selected" : ""}>${label}</option>`).join("")}</select></label><label>Chọn ngày trong kỳ <input type="date" name="anchor" value="${e(s.financeAnchor)}"></label><button class="button secondary">Xem kỳ</button><span>${range}</span></form></section><div class="stats-grid">${[["Tổng thu trong kỳ", summary.income], ["Tổng chi trong kỳ", summary.expense], ["Chênh lệch trong kỳ", summary.balance]].map(([label, value]) => `<section class="stat"><div><span>${label}</span></div><strong>${e(money(value))}</strong></section>`).join("")}</div><section class="panel"><div class="panel-head"><h2>Khoản thu chi</h2></div>${ledger.data?.length ? `<div class="table-wrap"><table><thead><tr><th>Ngày</th><th>Nội dung</th><th>Loại</th><th>Số tiền</th><th>Ảnh xác nhận</th></tr></thead><tbody>${ledger.data.map((item) => `<tr><td>${e(item.occurred_on)}</td><td>${e(item.description)}</td><td>${item.type === "income" ? "Thu" : "Chi"}</td><td>${e(money(item.amount))}</td><td>${item.has_receipt ? `<a class="text-link" href="/api/v1/clubs/${encodeURIComponent(s.clubId)}/finance/${encodeURIComponent(item.id)}/receipt" target="_blank" rel="noopener noreferrer">Xem ảnh</a>` : "—"}</td></tr>`).join("")}</tbody></table></div><div class="table-footer"><span>${number(meta.total)} khoản</span><div><button class="icon-button" data-action="finance-prev" aria-label="Trang trước" ${meta.current_page <= 1 ? "disabled" : ""}>←</button> ${meta.current_page} / ${meta.last_page} <button class="icon-button" data-action="finance-next" aria-label="Trang sau" ${meta.current_page >= meta.last_page ? "disabled" : ""}>→</button></div></div>` : empty("Chưa có khoản thu chi", "Không có khoản nào trong kỳ đã chọn.")}</section>`;
}
function financeByDate() {
  if (!currentClub()?.can_view_finance) return heading("Sổ thu chi", "Bạn không có quyền xem sổ thu chi.");
  const ledger = s.finance, summary = ledger.summary || {}, meta = ledger.meta || {};
  const money = (value) => `${number(value)} đ`;
  const groups = new Map();
  for (const item of ledger.chart || []) { const group = groups.get(item.period) || { income: 0, expense: 0 }; group[item.type] = Number(item.total); groups.set(item.period, group); }
  const entries = [...groups].sort(([a], [b]) => a.localeCompare(b));
  const maximum = Math.max(1, ...entries.flatMap(([, v]) => [v.income, v.expense]));
  const width = Math.max(600, entries.length * 90 + 100), left = 100, right = width - 30, top = 20, bottom = 240;
  const x = (index) => entries.length === 1 ? (left + right) / 2 : left + index * (right - left) / (entries.length - 1);
  const y = (value) => bottom - value / maximum * (bottom - top);
  const grid = Array.from({ length: 5 }, (_, i) => { const value = maximum * i / 4, position = y(value); return `<line x1="${left}" y1="${position}" x2="${right}" y2="${position}" class="finance-chart-grid"/><text x="${left - 10}" y="${position + 4}" text-anchor="end">${e(money(Math.round(value)))}</text>`; }).join('');
  const lines = ['income', 'expense'].map((type) => `<polyline class="finance-chart-line ${type}" points="${entries.map(([, value], i) => `${x(i)},${y(value[type])}`).join(' ')}"/>${entries.map(([period, value], i) => `<circle class="finance-chart-point ${type}" cx="${x(i)}" cy="${y(value[type])}" r="4"><title>${e(period)}: ${type === 'income' ? 'Thu' : 'Chi'} ${e(money(value[type]))}</title></circle>`).join('')}`).join('');
  const chart = entries.length ? `<div class="finance-chart-scroll"><svg class="finance-line-chart" viewBox="0 0 ${width} 280" style="min-width:${width}px" role="img" aria-label="Biểu đồ đường thu chi theo ${s.financeGroup === 'year' ? 'năm' : 'tháng'}, đơn vị đồng"><text x="${left}" y="12">Số tiền (đ)</text>${grid}${lines}${entries.map(([period], i) => `<text x="${x(i)}" y="260" text-anchor="middle">${e(period)}</text>`).join('')}</svg></div><div class="finance-chart-legend"><span>● Thu</span><span>● Chi</span></div><details><summary>Xem số liệu biểu đồ</summary><div class="table-wrap"><table><thead><tr><th>Thời gian</th><th>Thu</th><th>Chi</th></tr></thead><tbody>${entries.map(([period, value]) => `<tr><td>${e(period)}</td><td>${e(money(value.income))}</td><td>${e(money(value.expense))}</td></tr>`).join('')}</tbody></table></div></details>` : '';
  const actions = button('export-finance', 'Xuất Excel', 'arrow', 'secondary') + (currentClub()?.can_manage_finance ? button('new-finance', 'Ghi thu chi') : '');
  return heading('Sổ thu chi CLB', 'Thống kê theo khoảng ngày và theo tháng hoặc năm.', actions) + `<section class="panel"><form class="filters" data-form="finance-filter"><label>Từ ngày <input type="date" name="from" value="${e(s.financeFrom)}"></label><label>Đến ngày <input type="date" name="to" value="${e(s.financeTo)}"></label><label>Biểu đồ theo <select name="group"><option value="month" ${s.financeGroup === 'month' ? 'selected' : ''}>Tháng</option><option value="year" ${s.financeGroup === 'year' ? 'selected' : ''}>Năm</option></select></label><button class="button secondary">Xem</button></form></section><div class="stats-grid">${[['Tổng thu', summary.income], ['Tổng chi', summary.expense], ['Chênh lệch', summary.balance]].map(([label, value]) => `<section class="stat"><div><span>${label}</span></div><strong>${e(money(value))}</strong></section>`).join('')}</div><section class="panel"><div class="panel-head"><div><h2>Biểu đồ thu chi</h2><p>Màu xanh: thu · Màu cam: chi. Giá trị thuộc khoảng ngày đã chọn.</p></div></div><div class="panel-body">${chart || '<p>Chưa có dữ liệu trong khoảng ngày này.</p>'}</div></section><section class="panel"><div class="panel-head"><h2>Khoản thu chi</h2></div>${ledger.data?.length ? `<div class="table-wrap"><table><thead><tr><th>Ngày</th><th>Nội dung</th><th>Loại</th><th>Số tiền</th><th>Ảnh xác nhận</th></tr></thead><tbody>${ledger.data.map((item) => `<tr><td>${e(item.occurred_on)}</td><td>${e(item.description)}</td><td>${item.type === 'income' ? 'Thu' : 'Chi'}</td><td>${e(money(item.amount))}</td><td>${item.has_receipt ? `<a class="text-link" href="/api/v1/clubs/${encodeURIComponent(s.clubId)}/finance/${encodeURIComponent(item.id)}/receipt" target="_blank" rel="noopener noreferrer">Xem ảnh</a>` : '—'}</td></tr>`).join('')}</tbody></table></div><div class="table-footer"><span>${number(meta.total)} khoản</span><div><button class="icon-button" data-action="finance-prev" aria-label="Trang trước" ${meta.current_page <= 1 ? 'disabled' : ''}>←</button> ${meta.current_page} / ${meta.last_page} <button class="icon-button" data-action="finance-next" aria-label="Trang sau" ${meta.current_page >= meta.last_page ? 'disabled' : ''}>→</button></div></div>` : empty('Chưa có khoản thu chi', 'Không có khoản nào trong khoảng ngày đã chọn.')}</section>`;
}
function activities() {
  if (!canManageRoster(currentClub().membership_role)) return heading('Hoạt động CLB', 'Thông tin được bảo vệ theo quyền truy cập.');
  const { celebrations = [], birthdays = [], trainings = [], awards = [] } = s.activities;
  const row = (title, text, action = '') => `<div class="member-row"><div><strong>${e(title)}</strong><small>${e(text)}</small></div>${action}</div>`;
  return heading('Hoạt động CLB', 'Dịp lễ, sinh nhật, lịch tập, điểm danh và khen thưởng.') + `<div class="dashboard-grid"><section class="panel"><div class="panel-head"><div><h2>Dịp lễ & lời chúc</h2><p>Lưu các ngày đặc biệt của CLB.</p></div>${button('new-celebration', 'Thêm dịp lễ')}</div>${celebrations.length ? celebrations.map((x) => row(x.title, `${x.event_date}${x.message ? ` · ${x.message}` : ''}`)).join('') : empty('Chưa có dịp lễ', 'Thêm dịp đặc biệt và lời chúc.')}</section><section class="panel"><div class="panel-head"><div><h2>Sinh nhật thành viên</h2><p>Ngày sinh từ hồ sơ thành viên CLB.</p></div></div>${birthdays.length ? birthdays.map((p) => row(p.name, `${p.date_of_birth.slice(8, 10)}/${p.date_of_birth.slice(5, 7)} · Chúc mừng sinh nhật!`)).join('') : empty('Chưa có ngày sinh', 'Cập nhật ngày sinh trong hồ sơ thành viên.')}</section><section class="panel"><div class="panel-head"><div><h2>Lịch tập & điểm danh</h2><p>Chọn điểm danh cho từng buổi của từng đội.</p></div>${button('new-training', 'Tạo lịch tập')}</div>${trainings.length ? trainings.map((t) => row(`${t.team_name} · ${t.training_date} ${t.training_time}`, `${t.location} · Đã điểm danh ${(t.attendance || []).length}`, t.team_id ? `<button class="text-button" data-action="attendance" data-id="${t.id}">Điểm danh</button>` : '<small>Đội đã xóa</small>')).join('') : empty('Chưa có lịch tập', 'Tạo lịch tập cho đội bóng.')}</section><section class="panel"><div class="panel-head"><div><h2>Khen thưởng & phần quà</h2><p>Lưu danh hiệu theo năm.</p></div>${button('new-award', 'Thêm khen thưởng')}</div>${awards.length ? awards.map((a) => row(`${a.award_year} · ${a.player_name}`, `${a.title}${a.gift ? ` · Quà: ${a.gift}` : ''}${a.notes ? ` · ${a.notes}` : ''}`)).join('') : empty('Chưa có khen thưởng', 'Ghi nhận cá nhân xuất sắc và phần quà.')}</section></div>`;
}
function lineups() {
  if (!canManageRoster(currentClub().membership_role)) return heading("Đội hình trận đấu", "Thông tin dành cho ban quản lý đội bóng.");
  return heading("Đội hình trận đấu", "Tạo đội hình cho từng trận và sắp xếp cầu thủ trên sân.", button("new-lineup", "Tạo đội hình")) +
    `<section class="panel"><div class="panel-head"><h2>Các trận đã lưu</h2></div>${s.lineups.length ? s.lineups.map((item) => `<div class="member-row"><div><strong>${e(s.teams.find((team) => String(team.id) === String(item.team_id))?.name || "Đội bóng")} — ${e(item.opponent)}</strong><small>${e(item.match_date)} · ${e(item.formation || "Chưa đặt sơ đồ")} · ${item.players.filter((p) => p.role === "starter").length} đá chính · ${item.players.filter((p) => p.role === "substitute").length} dự bị</small></div><button class="text-button" data-action="edit-lineup" data-id="${item.id}">Chỉnh sửa</button></div>`).join("") : empty("Chưa có đội hình", "Tạo đội hình cho trận đấu đầu tiên.", "new-lineup", "Tạo đội hình")}</section>`;
}
function club() {
  const c = currentClub(),
    canInvite = ["owner", "co_owner", "general_manager", "hr_manager"].includes(
      c.membership_role,
    );
  return `${heading("Câu lạc bộ", "Bản sắc riêng, một không gian kết nối chung.", button("new-club", "Tạo CLB mới", "plus", "secondary") + (canManageClub(c.membership_role) ? button("edit-club", "Chỉnh sửa", "edit") : "") + (c.membership_role === "owner" ? button("delete-club", "Xóa CLB", "trash", "secondary") : ""))}<div class="dashboard-grid"><section class="panel"><div class="profile-head">${avatar(c.name)}<div><h2>${e(c.name)}</h2><p>${e(roleLabel(c.membership_role))} · ${c.status === "archived" ? "Đã lưu trữ" : "Đang hoạt động"}</p></div></div><dl class="details">${[
    ["Thành phố", c.city],
    ["Sân nhà", c.home_ground],
    ["Loại sân", (c.formats || [c.format]).map((v) => `Sân ${v}`).join(", ")],
    ["Giới thiệu", c.description],
  ]
    .map(
      ([label, value]) =>
        `<div><dt>${label}</dt><dd>${e(value || "Chưa cập nhật")}</dd></div>`,
    )
    .join(
      "",
    )}</dl></section><section class="panel"><div class="panel-head"><h2>Cùng xây dựng câu lạc bộ</h2>${icon("mail")}</div><div class="panel-body"><p class="muted">Mời đúng email của người nhận. Liên kết được chia sẻ trực tiếp và chỉ sử dụng một lần.</p>${canInvite ? button("invite", "Mời tham gia", "mail") : ""}<hr><p class="muted">Bạn có lời mời từ câu lạc bộ khác?</p><a class="text-link" href="#accept">Nhập mã tham gia →</a></div></section></div>`;
}
function profile() {
  return `${heading("Tài khoản của bạn", "Thông tin cá nhân và phiên đăng nhập.")}<section class="panel profile-panel"><div class="profile-head">${avatar(s.user.name)}<div><h2>${e(s.user.name)}</h2><p>${e(s.user.email)} · ${s.user.email_verified_at ? "Đã xác minh" : "Chưa xác minh email"}</p></div></div><div class="panel-body">${!s.user.email_verified_at ? verification() : ""}<form data-form="profile" novalidate><div class="form-error" data-form-error role="alert"></div><div class="form-grid">${field("name", "Họ và tên", s.user.name, "text", true)}${field("email", "Email", s.user.email, "email", true)}${field("current_password", "Mật khẩu hiện tại (khi đổi email)", "", "password")}</div><div class="form-actions">${button("logout", "Đăng xuất", "arrow", "secondary")}<button class="button" type="submit">Lưu thay đổi</button></div></form></div></section>`;
}
function verification() {
  return `<div class="notice"><p>Xác minh quyền sở hữu email trước khi nhận lời mời. Bạn có thể yêu cầu gửi lại liên kết hoặc tải lại sau khi xác minh.</p><div class="inline-actions">${button("verify", "Gửi liên kết xác minh", "mail", "secondary")}${button("refresh-user", "Đã xác minh, tải lại", "check", "secondary")}</div></div>`;
}
function accept() {
  return `${heading("Kết nối cùng câu lạc bộ", "Một lời mời, một hành trình mới.")}<section class="panel profile-panel"><div class="panel-body">${!s.user.email_verified_at ? verification() : ""}<p class="muted">Đang sử dụng email: <strong>${e(s.user.email)}</strong></p><form data-form="accept" novalidate><div class="form-error" data-form-error role="alert"></div>${field("token", "Mã lời mời", s.invite, "text", true)}<div class="form-actions"><button class="button" type="submit" ${!s.user.email_verified_at ? "disabled" : ""}>Chấp nhận lời mời</button></div></form></div></section>`;
}
export function page() {
  if (s.loading)
    return '<div class="panel loading" role="status"><div class="spinner"></div>Đang tải dữ liệu câu lạc bộ…</div>';
  if (s.error)
    return `${heading(titles[s.route] || "Tổng quan", "Dữ liệu cần được tải lại.")}<div class="panel">${empty("Chưa tải được dữ liệu", e(s.error), "retry", "Thử lại")}</div>`;
  if (s.route === "profile") return profile();
  if (s.route === "accept") return accept();
  if (s.route === "roadmap")
    return (
      heading(
        "Chặng đường phía trước",
        "Các phân hệ dự kiến, chưa hoạt động trong phiên bản này.",
      ) +
      `<div class="team-grid">${[
        ["Tài chính & Excel", "Sổ quỹ, phê duyệt, công nợ và import/export."],
        ["Bóng đá & điểm danh", "Lịch trận đấu, thống kê, chia chi phí."],
        [
          "Kho & tính năng nâng cao",
          "Trang bị, sự kiện, tuyển dụng, bình chọn và AI.",
        ],
      ]
        .map(
          ([name, text]) =>
            `<section class="panel team-card"><span class="pill">DỰ KIẾN PHÁT TRIỂN</span><h2>${name}</h2><p>${text}</p></section>`,
        )
        .join("")}</div>`
    );
  if (!currentClub())
    return (
      heading(
        "Câu lạc bộ bắt đầu từ bạn",
        "Tạo không gian đầu tiên và cùng nhau xây dựng đội bóng.",
      ) +
      `<section class="panel">${empty("Chào mừng đến với FCMS PRO", "Tạo câu lạc bộ của bạn hoặc nhận lời mời từ ban quản lý.", "new-club", "Tạo câu lạc bộ")}<p class="center"><a class="text-link" href="#accept">Đã có mã mời? Tham gia câu lạc bộ →</a></p></section>`
    );
  return ({ dashboard, members: () => (canManageRoster(currentClub().membership_role) ? `<div class="page-actions">${button("import-members", "Nhập Excel", "arrow", "secondary")}</div>` : "") + clubDirectory(), "team-roster": teamRoster, teams, lineups, finance: financeByDate, activities, "club-members": clubMembers, club }[s.route] || dashboard)();
}
