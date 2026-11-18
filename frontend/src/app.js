import { createApiClient } from "./api.js";
import { state as s, currentClub } from "./state.js";
import { page, authPage, titles } from "./pages.js";
import { openLineup, openCaptain } from "./lineups.js";
import { downloadMemberTemplate, parseMembers, exportFinance } from "./spreadsheets.js";
import { reviewImport } from "./import-review.js";
import { activityAction } from "./activities.js";
import {
  icon,
  button,
  field,
  select,
  dialog,
  toast,
  showErrors,
} from "./components/ui.js";
import {
  escapeHtml as e,
  initials,
  roleLabel,
  canManageRoster,
  canManageClub,
  validate,
  unpackList,
} from "./utils.js";

const root = document.querySelector("#app");
let sequence = 0,
  controller,
  searchTimer;
const api = createApiClient({
  onUnauthorized: () => {
    clearSession();
    toast("Phiên đăng nhập đã hết hạn.", true);
  },
});
const currentRole = () => currentClub()?.membership_role || "member";
const base = (suffix = "") => `/clubs/${encodeURIComponent(s.clubId)}${suffix}`;
const navItems = [
  ["dashboard", "Tổng quan", "grid"],
  ["members", "Thành viên CLB", "users"],
  ["teams", "Đội bóng", "layers"],
  ["club-members", "Thành viên BCN", "users"],
  ["finance", "Sổ thu chi CLB", "layers"],
  ["lineups", "Đội hình trận đấu", "flag"],
  ["activities", "Hoạt động CLB", "flag"],
  ["club", "Câu lạc bộ", "shield"],
  ["profile", "Tài khoản", "user"],
];
const nav = () =>
  navItems
    .filter(([route]) => route !== "club-members" || currentRole() === "owner")
    .filter(([route]) => route !== "finance" || currentClub()?.can_view_finance)
    .map(
      ([route, name, glyph]) =>
        `<a href="#${route}" class="nav-link ${s.route === route ? "active" : ""}" ${s.route === route ? 'aria-current="page"' : ""}>${icon(glyph)}<span>${name}</span></a>`,
    )
    .join("");
const clubSelect = () =>
  `<select data-club-switch aria-label="Chuyển câu lạc bộ">${s.clubs.length ? s.clubs.map((c) => `<option value="${c.id}" ${String(c.id) === String(s.clubId) ? "selected" : ""}>${e(c.name)}</option>`).join("") : "<option>Chưa có câu lạc bộ</option>"}</select>`;
function render() {
  if (!s.user) {
    root.innerHTML = authPage();
    return;
  }
  root.innerHTML = `<div class="shell"><aside class="sidebar"><a class="brand" href="#dashboard"><img src="/icons/crest.svg" alt="">FCMS <small>PRO</small></a><div class="club-selector"><span class="club-icon">${e(initials(currentClub()?.name || "FC"))}</span><div><small>CÂU LẠC BỘ CỦA BẠN</small>${clubSelect()}</div></div><div class="nav-label">QUẢN LÝ CÂU LẠC BỘ</div><nav aria-label="Điều hướng chính">${nav()}<div class="nav-divider"></div><a class="nav-link ${s.route === "roadmap" ? "active" : ""}" href="#roadmap">${icon("flag")}<span>Lộ trình phát triển</span></a></nav><div class="sidebar-bottom"><div>${icon("flag")}<h3>Vững đội hình.<br>Chung mục tiêu.</h3><p>Mỗi hồ sơ chỉn chu là một bước tiến của câu lạc bộ.</p></div><small>FCMS PRO / PHIÊN BẢN NỀN TẢNG</small></div></aside><div class="workspace"><header class="topbar"><div class="breadcrumb">${icon("shield")} ${e(currentClub()?.name || "FCMS PRO")} <span>/</span> <strong>${titles[s.route]}</strong></div><a class="brand mobile-brand" href="#dashboard"><img src="/icons/crest.svg" alt="">FCMS <small>PRO</small></a><div class="header-actions"><button class="icon-button" data-action="theme" aria-label="Đổi giao diện sáng tối">${icon("moon")}</button><a class="user-link" href="#profile"><span class="avatar">${e(initials(s.user.name))}</span><div><strong>${e(s.user.name)}</strong><small>${e(roleLabel(currentRole()))}</small></div></a></div></header><div id="connection-banner" class="connection-banner" ${navigator.onLine ? "hidden" : ""} role="status">Bạn đang ngoại tuyến. Kết nối lại để cập nhật dữ liệu.</div><div class="mobile-club">${clubSelect()}</div><main id="main-content" tabindex="-1" class="page">${page()}<footer>© ${new Date().getFullYear()} FCMS PRO. Cùng câu lạc bộ vươn xa.</footer></main></div><nav class="bottom-nav" aria-label="Điều hướng di động">${nav()}</nav></div>`;
}
function clearSession() {
  sequence++;
  controller?.abort();
  s.user = null;
  s.clubs = [];
  s.clubId = null;
  clearData();
  document.querySelectorAll("dialog").forEach((x) => x.close());
  render();
}
function clearData() {
  s.members = [];
  s.teams = [];
  s.lineups = [];
  s.clubMemberships = [];
  s.activities = { celebrations: [], birthdays: [], trainings: [], awards: [] };
  s.finance = { data: [], summary: {}, meta: {} };
  s.meta = {};
  s.dashboard = {};
  s.error = "";
}
function readRoute() {
  const [route, query = ""] = location.hash.slice(1).split("?");
  const token = new URLSearchParams(query).get("token");
  s.route = Object.hasOwn(titles, route) ? route : "dashboard";
  s.teamRosterId = route === "team-roster" ? (new URLSearchParams(query).get("id") || "") : "";
  if (token) {
    s.invite = token;
    history.replaceState(null, "", location.pathname + "#accept");
    s.route = "accept";
  }
}
async function loadApp() {
  const seq = ++sequence;
  s.loading = true;
  s.error = "";
  render();
  try {
    const result = await api.get("/clubs");
    if (seq !== sequence || !s.user) return;
    s.clubs = unpackList(result).rows;
    if (!currentClub()) s.clubId = s.clubs[0]?.id || null;
    await loadView();
  } catch (error) {
    if (seq !== sequence || !s.user) return;
    s.loading = false;
    s.error = error.message;
    render();
  }
}
async function loadView() {
  controller?.abort();
  controller = new AbortController();
  const signal = controller.signal,
    tenant = s.clubId,
    route = s.route;
  clearData();
  s.loading = true;
  render();
  try {
    if (tenant && ["dashboard", "members", "team-roster", "teams", "lineups", "club-members", "finance", "activities"].includes(route)) {
      const tasks = [api.get(base("/teams"), { signal })];
      if (route === "dashboard")
        tasks.push(api.get(base("/dashboard"), { signal }));
      if (route === "lineups" && canManageRoster(currentRole()))
        tasks.push(api.get(base("/lineups"), { signal }));
      if (route === "club-members" && currentRole() === "owner")
        tasks.push(api.get(base("/club-memberships"), { signal }));
      if (route === "finance" && currentClub()?.can_view_finance)
        tasks.push(api.get(base(`/finance?${new URLSearchParams({ page: s.financePage, group: s.financeGroup, ...(s.financeFrom ? { from: s.financeFrom } : {}), ...(s.financeTo ? { to: s.financeTo } : {}) })}`), { signal }));
      if (route === "activities" && canManageRoster(currentRole())) tasks.push(api.get(base('/activities'), { signal }));
      if (
        (route === "dashboard" || route === "members" || (route === "team-roster" && /^\d+$/.test(s.teamRosterId))) &&
        canManageRoster(currentRole())
      ) {
        const q =
          route === "dashboard"
            ? new URLSearchParams({ per_page: "5", sort: "created_at" })
            : route === "team-roster" ? new URLSearchParams({ team_id: s.teamRosterId, page: String(s.teamRosterPage), per_page: "15" }) : new URLSearchParams({ ...s.filters, per_page: "15" });
        for (const [key, value] of [...q]) if (value === "") q.delete(key);
        tasks.push(api.get(base(`/members?${q}`), { signal }));
      }
      const results = await Promise.all(tasks);
      if (signal.aborted || !s.user || tenant !== s.clubId || route !== s.route)
        return;
      s.teams = unpackList(results[0]).rows;
      if (route === "lineups" && results[1]) s.lineups = unpackList(results[1]).rows;
      if (route === "club-members" && results[1]) s.clubMemberships = unpackList(results[1]).rows;
      if (route === "finance" && results[1]) s.finance = results[1];
      if (route === "activities" && results[1]) s.activities = results[1].data;
      if (route === "dashboard") s.dashboard = results[1].data;
      const list = ["lineups", "club-members", "finance", "activities"].includes(route) ? null : results[route === "dashboard" ? 2 : 1];
      if (list) {
        const { rows, meta } = unpackList(list);
        s.members = route === "dashboard" ? rows.map((m) => ({ ...m, team: { name: (m.teams || []).map((t) => t.name).join(", ") || "Chưa xếp đội" }, jersey_number: null })) : rows;
        s.meta = meta;
      }
    }
    if (signal.aborted || !s.user) return;
    s.loading = false;
    render();
  } catch (error) {
    if (error.name === "AbortError" || signal.aborted || !s.user) return;
    s.loading = false;
    s.error = error.message;
    render();
  }
}
function values(form) {
  return Object.fromEntries(
    [...new FormData(form)].map(([k, v]) => [
      k,
      k.includes("password") ? v : String(v).trim(),
    ]),
  );
}
async function submit(form, kind, handler) {
  const data = values(form),
    errors = validate(kind, data);
  if (kind === "accept" && data.token?.length !== 64)
    errors.token = "Mã lời mời phải có 64 ký tự.";
  showErrors(form, errors);
  if (Object.keys(errors).length) return false;
  const btn = form.querySelector('[type="submit"]');
  if (btn?.disabled) return false;
  if (btn) btn.disabled = true;
  form.setAttribute("aria-busy", "true");
  try {
    await handler(data);
    return true;
  } catch (error) {
    if (form.isConnected) showErrors(form, error.errors || {}, error.message);
    return false;
  } finally {
    if (btn?.isConnected) btn.disabled = false;
    form.removeAttribute("aria-busy");
  }
}
function editClub(edit = false) {
  const c = edit ? currentClub() : {};
  const fields =
    field("name", "Tên câu lạc bộ", c.name, "text", true) +
    field("city", "Thành phố", c.city) +
    field("home_ground", "Sân nhà", c.home_ground) +
    `<fieldset class="field format-options"><legend>Loại sân *</legend>${["5", "7", "11"].map((v) => `<label><input type="checkbox" name="formats" value="${v}" ${(c.formats || [c.format || "7"]).includes(v) ? "checked" : ""}> Sân ${v} người</label>`).join("")}</fieldset>` +
    (edit
      ? select(
          "status",
          "Trạng thái",
          [
            ["active", "Đang hoạt động"],
            ["archived", "Lưu trữ"],
          ],
          c.status,
        )
      : "") +
    field("description", "Giới thiệu", c.description, "textarea");
  dialog(
    edit ? "Chỉnh sửa câu lạc bộ" : "Tạo câu lạc bộ mới",
    "Bạn có thể quản lý nhiều câu lạc bộ trong một tài khoản.",
    fields,
    (form) =>
      submit(form, "club", async (data) => {
        data.formats = [...form.querySelectorAll('[name="formats"]:checked')].map((input) => input.value);
        if (!data.formats.length) throw new Error("Vui lòng chọn ít nhất một loại sân.");
        const result = edit
          ? await api.patch(base(), data)
          : await api.post("/clubs", data);
        s.clubId = result.data.id;
        s.route = "club";
        history.replaceState(null, "", location.pathname + "#club");
        await loadApp();
        toast("Đã lưu câu lạc bộ.");
      }),
  );
}
function editTeam() {
  dialog(
    "Tạo đội bóng",
    "Sắp xếp đội hình sân 5, 7 hoặc 11 người.",
    field("name", "Tên đội bóng", "", "text", true) +
      select(
        "format",
        "Loại sân",
        (currentClub().formats || [currentClub().format]).map((v) => [v, `Sân ${v} người`]),
        (currentClub().formats || [currentClub().format])[0],
      ),
    (form) =>
      submit(form, "team", async (data) => {
        await api.post(base("/teams"), data);
        await loadView();
        toast("Đã tạo đội bóng.");
      }),
  );
}
async function renameTeam(team) {
  const candidates = unpackList(await api.get(base('/team-manager-candidates'))).rows;
  const managerFields = `<fieldset class="field format-options"><legend>Người quản lý đội</legend>${candidates.map((m) => `<label><input type="checkbox" name="manager_ids" value="${m.id}" ${(team.managers || []).some((x) => String(x.id) === String(m.id)) ? "checked" : ""}> ${e(m.name)}</label>`).join("") || "Chưa có tài khoản thành viên."}</fieldset>`;
  dialog("Chỉnh sửa đội bóng", "Chỉnh sửa tên, loại sân và người quản lý.",
    field("name", "Tên đội bóng", team.name, "text", true) + select("format", "Loại sân", (currentClub().formats || [currentClub().format]).map((v) => [v, `Sân ${v} người`]), team.format) + managerFields,
    (form) => submit(form, "team-rename", async (data) => {
      if (!data.name) throw new Error("Vui lòng nhập tên đội bóng.");
      const managerIds = [...form.querySelectorAll('[name="manager_ids"]:checked')].map((x) => Number(x.value));
      await api.patch(base(`/teams/${team.id}`), { name: data.name, format: data.format, membership_ids: managerIds });
      await loadView();
      toast("Đã đổi tên đội bóng.");
    }));
}
function editMember(member = { status: "active" }) {
  const positions = [["GK", "Goalkeeper"], ["FIXO", "Fixo"], ["ALA", "Ala"], ["PIVOT", "Pivot"], ...["CB", "LB", "RB", "CDM", "CM", "CAM", "LM", "RM", "LW", "RW", "ST", "CF"].map((x) => [x, x])];
  const fields =
    field("full_name", "Họ và tên", member.full_name, "text", true) +
    field("email", "Email", member.email, "email") +
    field("phone", "Số điện thoại", member.phone, "tel") +
    field("date_of_birth", "Ngày sinh", member.date_of_birth, "date") +
    select(
      "position",
      "Vị trí",
      [
        ["", "Chưa cập nhật"],
        ...positions,
      ],
      member.position,
    ) +
    select(
      "preferred_foot",
      "Chân thuận",
      [
        ["", "Chưa cập nhật"],
        ["right", "Phải"],
        ["left", "Trái"],
        ["both", "Hai chân"],
      ],
      member.preferred_foot,
    ) +
    select(
      "status",
      "Trạng thái",
      ["active", "inactive", "injured"].map((x) => [
        x,
        {
          active: "Đang hoạt động",
          inactive: "Tạm ngưng",
          injured: "Chấn thương",
        }[x],
      ]),
      member.status,
    ) +
    field("notes", "Ghi chú", member.notes, "textarea");
  dialog(
    member.id ? "Chỉnh sửa thành viên" : "Thêm thành viên",
    "Hồ sơ cầu thủ không tự cấp quyền truy cập tài khoản.",
    fields,
    (form) =>
      submit(form, "member", async (data) => {
        for (const k of Object.keys(data)) if (data[k] === "") data[k] = null;
        if (member.id) await api.patch(base(`/members/${member.id}`), data);
        else await api.post(base("/members"), data);
        await loadView();
        toast("Đã lưu hồ sơ thành viên.");
      }),
  );
}
function invite() {
  let roles = ["member"];
  if (["owner", "co_owner"].includes(currentRole()))
    roles.push(
      "general_manager",
      "treasurer",
      "hr_manager",
      "equipment_manager",
      "sporting_manager",
    );
  if (currentRole() === "owner") roles.push("co_owner");
  let created;
  const el = dialog(
    "Mời tham gia câu lạc bộ",
    "Tạo liên kết và chia sẻ riêng với người nhận. Không gửi email tự động.",
    field("email", "Email người nhận", "", "email", true) +
      select(
        "role",
        "Vai trò",
        roles.map((r) => [r, roleLabel(r)]),
        "member",
      ),
    (form) =>
      submit(form, "invitation", async (data) => {
        created = (await api.post(base("/invitations"), data)).data;
      }),
    "Tạo lời mời",
  );
  el.addEventListener(
    "close",
    () => {
      if (!created) return;
      const link = `${location.origin}${location.pathname}#accept?token=${encodeURIComponent(created.token)}`;
      const result = dialog(
        "Lời mời đã sẵn sàng",
        "Sao chép liên kết này và gửi riêng cho người nhận. Hiệu lực 7 ngày.",
        field("link", "Liên kết nhận lời mời", link),
        async () => true,
        "Đóng",
      );
      result.querySelector("input").readOnly = true;
      result.querySelector("input").select();
    },
    { once: true },
  );
}
async function action(el) {
  const a = el.dataset.action;
  if (a === "theme") {
    const theme =
      document.documentElement.dataset.theme === "dark" ? "light" : "dark";
    document.documentElement.dataset.theme = theme;
    try {
      localStorage.setItem("fcms-theme", theme);
    } catch {}
    return;
  }
  if (a === "auth-mode") {
    s.authMode = s.authMode === "login" ? "register" : "login";
    render();
    return;
  }
  if (!s.user) return;
  if (a === "retry") return loadApp();
  if (a === "new-club") return editClub();
  if (a === "edit-club" && canManageClub(currentRole())) return editClub(true);
  if (a === "delete-club" && currentRole() === "owner") {
    const club = currentClub();
    return dialog("Xóa câu lạc bộ?", `Xóa ${e(club.name)} sẽ ẩn câu lạc bộ với tất cả thành viên. Dữ liệu được giữ trong hệ thống; hiện chưa có chức năng tự khôi phục trên giao diện.`, "", (form) => submit(form, "delete", async () => {
      await api.delete(base());
      s.clubId = null;
      s.route = "dashboard";
      history.replaceState(null, "", location.pathname + "#dashboard");
      await loadApp();
      toast("Đã xóa câu lạc bộ.");
    }), "Xác nhận xóa CLB");
  }
  if (a === "new-team") return editTeam();
  if (["new-celebration", "new-training", "new-award", "attendance"].includes(a) && canManageRoster(currentRole())) return activityAction(a, el.dataset.id, { state: s, api, base, reload: loadView });
  if (a === "rename-team" && canManageRoster(currentRole())) {
    const team = s.teams.find((t) => String(t.id) === el.dataset.id);
    if (team) return renameTeam(team);
  }
  if (a === "delete-team" && canManageRoster(currentRole())) {
    const team = s.teams.find((t) => String(t.id) === el.dataset.id);
    if (!team) return;
    return dialog("Xóa đội bóng?", `Xóa ${e(team.name)} sẽ xóa các đội hình trận đấu của đội. Hồ sơ cầu thủ được giữ lại nhưng không còn thuộc đội này.`, "", (form) => submit(form, "delete", async () => {
      await api.delete(base(`/teams/${team.id}`));
      await loadView();
      toast("Đã xóa đội bóng.");
    }), "Xác nhận xóa đội");
  }
  if (a === "edit-title" && currentRole() === "owner") {
    const member = s.clubMemberships.find((m) => String(m.id) === el.dataset.id);
    if (!member) return;
    const preset = ["Thành viên", "HLV", "Chủ nhiệm CLB", "Phó chủ nhiệm CLB", "Thủ quỹ", "Thư ký", "Media", "Truyền thông", ...s.clubMemberships.map((m) => m.title).filter(Boolean)];
    const choices = [...new Set(preset)];
    const modal = dialog("Vai trò thành viên", `Chọn chức danh cho ${e(member.name)}. Chủ nhiệm, phó chủ nhiệm, thủ quỹ và HLV có thể xem sổ thu chi; thủ quỹ được ghi khoản thu chi.`,
      select("title_choice", "Vai trò", [...choices.map((v) => [v, v]), ["__custom__", "Thêm vai trò khác…"]], member.title || (member.role === "owner" ? "Chủ nhiệm CLB" : "Thành viên")) + field("custom_title", "Vai trò khác", ""),
      (form) => submit(form, "title", async (data) => {
        const title = data.title_choice === "__custom__" ? data.custom_title : data.title_choice;
        if (!title) throw new Error("Vui lòng nhập vai trò.");
        await api.patch(base(`/club-memberships/${member.id}`), { title });
        await loadView(); toast("Đã cập nhật vai trò.");
      }));
    const toggle = () => { modal.querySelector('[name="custom_title"]').closest('.field').hidden = modal.querySelector('[name="title_choice"]').value !== '__custom__'; };
    modal.querySelector('[name="title_choice"]').addEventListener('change', toggle); toggle();
  }
  if (a === "new-lineup" || a === "edit-lineup" || a === "team-captain" || a === "select-team-players") {
    if (!canManageRoster(currentRole())) return;
    const rows = [];
    let pageNumber = 1, lastPage = 1;
    do {
      const result = unpackList(await api.get(base(`/members?per_page=100&page=${pageNumber}`)));
      rows.push(...result.rows);
      lastPage = result.meta.last_page || 1;
      pageNumber++;
    } while (pageNumber <= lastPage);
    if (a === "select-team-players") {
      const team = s.teams.find((t) => String(t.id) === el.dataset.id);
      if (!team) return;
      const fields = `<div class="lineup-editor lineup-roster">${rows.map((p) => `<label><span>${e(p.full_name)} · ${e(p.position || "Chưa có vị trí")}</span><input type="checkbox" name="player_ids" value="${p.id}" ${(p.teams || []).some((t) => String(t.id) === String(team.id)) ? "checked" : ""}></label>`).join("") || "Chưa có thành viên. Hãy tạo thành viên CLB trước."}</div>`;
      return dialog("Chọn thành viên cho đội", `Chọn từ các hồ sơ đã tạo trong CLB. Bỏ chọn sẽ đưa thành viên ra khỏi đội ${e(team.name)}.`, fields, (form) => submit(form, "team-players", async () => {
        const player_ids = [...form.querySelectorAll('[name="player_ids"]:checked')].map((x) => Number(x.value));
        await api.patch(base(`/teams/${team.id}/players`), { player_ids });
        await loadView(); toast("Đã cập nhật thành viên đội.");
      }));
    }
    if (a === "team-captain") return openCaptain({ team: s.teams.find((t) => String(t.id) === el.dataset.id), players: rows, api, base, reload: loadView });
    return openLineup({ teams: s.teams, players: rows, lineup: s.lineups.find((x) => String(x.id) === el.dataset.id), api, base, reload: loadView });
  }
  if (a === "new-member") return editMember();
  if (a === "invite") return invite();
  if (a === "reset") {
    s.filters = { q: "", team_id: "", status: "", page: 1 };
    return loadView();
  }
  if (a === "prev" || a === "next") {
    s.filters.page += a === "next" ? 1 : -1;
    return loadView();
  }
  if (a === "team-members") {
    s.teamRosterPage = 1;
    location.hash = `team-roster?id=${encodeURIComponent(el.dataset.id)}`;
    return;
  }
  if (a === "roster-prev" || a === "roster-next") {
    s.teamRosterPage += a === "roster-next" ? 1 : -1;
    return loadView();
  }
  if (a === "finance-prev" || a === "finance-next") {
    s.financePage += a === "finance-next" ? 1 : -1;
    return loadView();
  }
  if (a === "new-finance" && currentClub()?.can_manage_finance) {
    const today = new Date();
    const date = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
    return dialog("Ghi khoản thu chi", "Nhập số tiền bằng đồng Việt Nam. Có thể chọn hoặc chụp ảnh xác nhận (JPG, PNG, WEBP tối đa 5 MB).", select("type", "Loại khoản", [["income", "Thu"], ["expense", "Chi"]], "income") + field("amount", "Số tiền (đồng)", "", "number", true).replace('min="0" max="999"', 'min="1" max="1000000000000"') + field("occurred_on", "Ngày", date, "date", true) + field("description", "Nội dung", "", "text", true) + '<div class="field"><label for="f-receipt">Ảnh xác nhận (không bắt buộc)</label><input id="f-receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp"><small data-error="receipt"></small></div>', (form) => submit(form, "finance", async (data) => {
      if (!/^[1-9]\d*$/.test(data.amount) || Number(data.amount) > 1000000000000) throw new Error("Số tiền phải là số nguyên từ 1 đến 1.000.000.000.000 đồng.");
      const upload = new FormData(form);
      if (!form.elements.receipt.files.length) upload.delete('receipt');
      await api.post(base("/finance"), upload);
      s.financePage = 1; await loadView(); toast("Đã ghi khoản thu chi.");
    }));
  }
  if (a === "export-finance" && currentClub()?.can_view_finance) {
    const rows = []; let pageNumber = 1, lastPage = 1, summary = {};
    do {
      const query = new URLSearchParams({ page: pageNumber, per_page: 100, ...(s.financeFrom ? { from: s.financeFrom } : {}), ...(s.financeTo ? { to: s.financeTo } : {}) });
      const result = await api.get(base(`/finance?${query}`)); rows.push(...result.data); lastPage = result.meta.last_page; summary = result.summary; pageNumber++;
    } while (pageNumber <= lastPage);
    await exportFinance(rows, summary); toast("Đã xuất sổ thu chi."); return;
  }
  if (a === "import-members" && canManageRoster(currentRole())) {
    let pendingImport;
    const info = `<div class="lineup-editor"><p>Chỉ dùng file .xlsx theo mẫu, tối đa 500 dòng và 5 MB. Cột <strong>full_name</strong> bắt buộc; email không được trùng; ngày sinh theo YYYY-MM-DD. Số điện thoại đặt dạng Text. Không đổi thứ tự cột hoặc dùng công thức.</p><p>Tải mẫu để xem đầy đủ vị trí, chân thuận và trạng thái hợp lệ. Sau khi nhập, hãy chọn thành viên vào đội và đặt số áo tại đội.</p><button type="button" class="button secondary" data-download-template>Tải mẫu Excel</button><div class="field"><label for="member-file">File thành viên (.xlsx)</label><input id="member-file" type="file" name="member_file" accept=".xlsx" required></div></div>`;
    const modal = dialog("Nhập thành viên từ Excel", "Kiểm tra dữ liệu trước khi nhập; nếu có lỗi, không dòng nào được lưu.", info, (form) => submit(form, "member-import", async () => {
      const rows = await parseMembers(form.elements.member_file.files[0]);
      try { const result = await api.post(base('/members/import/preview'), { players: rows }); pendingImport = { rows, preview: result.data }; }
      catch (error) {
        const messages = Object.entries(error.errors || {}).map(([key, value]) => { const m = /^players\.(\d+)\.(.+)$/.exec(key); return m ? `Dòng ${rows.sourceRows[Number(m[1])]}, ${m[2]}: ${Array.isArray(value) ? value[0] : value}` : `${key}: ${Array.isArray(value) ? value[0] : value}`; });
        throw new Error(messages.length ? messages.join(' | ') : error.message);
      }
    }), "Kiểm tra file");
    modal.querySelector('[data-download-template]').addEventListener('click', () => downloadMemberTemplate().catch((error) => toast(error.message, true)));
    modal.addEventListener('close', () => { if (pendingImport) reviewImport({ ...pendingImport, api, base, reload: loadView }); }, { once: true });
    return;
  }
  if (a === "set-team-captain") {
    const team = s.teams.find((t) => String(t.id) === s.teamRosterId);
    if (!team || !canManageRoster(currentRole())) return;
    const captain = String(team.captain_id) === el.dataset.id ? null : Number(el.dataset.id);
    await api.patch(base(`/teams/${team.id}/captain`), { captain_id: captain });
    await loadView(); toast(captain ? "Đã chọn đội trưởng." : "Đã bỏ chọn đội trưởng."); return;
  }
  if (a === "edit-team-position") {
    const member = s.members.find((m) => String(m.id) === el.dataset.id);
    const team = s.teams.find((t) => String(t.id) === s.teamRosterId);
    if (!member || !team) return;
    const current = (member.teams || []).find((t) => String(t.id) === String(team.id))?.position || "";
    const choices = String(team.format) === "5" ? [["GK", "Goalkeeper"], ["FIXO", "Fixo"], ["ALA", "Ala"], ["PIVOT", "Pivot"]] : ["GK", "CB", "LB", "RB", "CDM", "CM", "CAM", "LM", "RM", "LW", "RW", "ST", "CF"].map((x) => [x, x]);
    if (current && !choices.some(([id]) => id === current)) choices.push([current, `${current} (đã lưu)`]);
    return dialog("Vị trí của đội", `${e(member.full_name)} · ${e(team.name)}`, select("position", "Vị trí", [["", "Chưa chọn"], ...choices], current), (form) => submit(form, "team-position", async (data) => {
      await api.patch(base(`/teams/${team.id}/players/${member.id}/position`), { position: data.position || null });
      await loadView(); toast("Đã cập nhật vị trí.");
    }));
  }
  if (a === "edit-team-jersey") {
    const member = s.members.find((m) => String(m.id) === el.dataset.id);
    const team = s.teams.find((t) => String(t.id) === s.teamRosterId);
    if (!member || !team) return;
    const jersey = (member.teams || []).find((t) => String(t.id) === String(team.id))?.jersey_number;
    return dialog("Số áo của đội", `${e(member.full_name)} · ${e(team.name)}. Để trống nếu chưa chọn.`, field("jersey_number", "Số áo", jersey ?? "", "number"), (form) => submit(form, "jersey", async (data) => {
      await api.patch(base(`/teams/${team.id}/players/${member.id}/jersey`), { jersey_number: data.jersey_number === "" ? null : data.jersey_number });
      await loadView(); toast("Đã cập nhật số áo.");
    }));
  }
  if (a === "remove-team-player") {
    const member = s.members.find((m) => String(m.id) === el.dataset.id), team = s.teams.find((t) => String(t.id) === s.teamRosterId);
    if (!member || !team || !canManageRoster(currentRole())) return;
    return dialog('Xóa khỏi đội bóng?', `${e(member.full_name)} sẽ rời ${e(team.name)} nhưng hồ sơ vẫn còn trong CLB và các đội khác. Số áo và vị trí ở đội này sẽ bị xóa.`, '', (form) => submit(form, 'remove-player', async () => {
      await api.delete(base(`/teams/${team.id}/players/${member.id}`));
      if (s.members.length === 1 && s.teamRosterPage > 1) s.teamRosterPage--;
      await loadView(); toast('Đã xóa thành viên khỏi đội.');
    }), 'Xóa khỏi đội');
  }
  if (a === "edit-member") {
    const m = s.members.find((x) => String(x.id) === el.dataset.id);
    if (m) return editMember(m);
  }
  if (a === "view-member") {
    const m = s.members.find((x) => String(x.id) === el.dataset.id);
    if (!m) return;
    const details = [["Họ tên", m.full_name], ["Email", m.email], ["Điện thoại", m.phone], ["Ngày sinh", m.date_of_birth], ["Vị trí", m.position], ["Chân thuận", m.preferred_foot], ["Trạng thái", m.status], ["Đội bóng", (m.teams || []).map((t) => `${t.name}${t.jersey_number == null ? "" : ` #${t.jersey_number}`}`).join(", ")], ["Ghi chú", m.notes]];
    const removeButton = s.route === 'team-roster' ? '<div class="lineup-editor"><button type="button" class="button secondary" data-remove-from-team>Xóa khỏi đội bóng</button></div>' : '';
    const modal = dialog("Hồ sơ thành viên", "Thông tin và các đội bóng trong câu lạc bộ.", `<dl class="details lineup-editor">${details.map(([label, value]) => `<div><dt>${e(label)}</dt><dd>${e(value ?? "Chưa cập nhật")}</dd></div>`).join("")}</dl>${removeButton}`, async () => true, "Đóng");
    modal.querySelector('[data-remove-from-team]')?.addEventListener('click', () => { modal.close(); action({ dataset: { action: 'remove-team-player', id: String(m.id) } }).catch((error) => toast(error.message, true)); });
    return modal;
  }
  if (a === "delete-member") {
    const m = s.members.find((x) => String(x.id) === el.dataset.id);
    if (!m) return;
    return dialog(
      "Xóa hồ sơ thành viên?",
      `Bạn sắp xóa hồ sơ của ${e(m.full_name)}. Tài khoản và quyền truy cập được quản lý riêng.`,
      "",
      (form) =>
        submit(form, "delete", async () => {
          await api.delete(base(`/members/${m.id}`));
          if (s.members.length === 1 && s.filters.page > 1) s.filters.page--;
          await loadView();
          toast("Đã xóa hồ sơ.");
        }),
      "Xác nhận xóa",
    );
  }
  if (a === "logout") {
    await api.post("/auth/logout");
    clearSession();
    toast("Đã đăng xuất an toàn.");
  }
  if (a === "verify") {
    const response = await api.post("/auth/verification-notification");
    toast(response.data.message);
  }
  if (a === "refresh-user") {
    s.user = (await api.get("/auth/user")).data;
    render();
    toast(
      s.user.email_verified_at
        ? "Email đã được xác minh."
        : "Email chưa được xác minh.",
    );
  }
}
root.addEventListener("click", (event) => {
  const el = event.target.closest("[data-action]");
  if (el && !el.disabled)
    action(el).catch((error) => toast(error.message, true));
});
root.addEventListener("change", (event) => {
  if (event.target.matches("[data-club-switch]")) {
    clearTimeout(searchTimer);
    s.clubId = Number(event.target.value);
    s.filters = { q: "", team_id: "", status: "", page: 1 };
    loadView();
  }
});
root.addEventListener("submit", (event) => {
  const form = event.target.closest("[data-form]");
  if (!form) return;
  event.preventDefault();
  const kind = form.dataset.form;
  if (kind === "filters") {
    s.filters = { ...values(form), page: 1 };
    loadView();
    return;
  }
  if (kind === "finance-filter") {
    const data = values(form);
    if (data.from && data.to && data.from > data.to) { toast('Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.', true); return; }
    s.financeFrom = data.from; s.financeTo = data.to; s.financeGroup = data.group;
    s.financePage = 1; loadView(); return;
  }
  submit(form, kind, async (data) => {
    if (kind === "login" || kind === "register") {
      s.user = (
        await api.post(`/auth/${kind}`, data, { silentUnauthorized: true })
      ).data;
      await loadApp();
      toast("Chào mừng bạn đến với FCMS PRO!");
    }
    if (kind === "profile") {
      s.user = (await api.patch("/auth/profile", data)).data;
      render();
      toast("Đã cập nhật tài khoản.");
    }
    if (kind === "accept") {
      const response = await api.post("/invitations/accept", {
        token: data.token,
      });
      s.invite = "";
      s.clubId = response.data.id;
      s.route = "dashboard";
      history.replaceState(null, "", location.pathname + "#dashboard");
      await loadApp();
      toast("Đã tham gia câu lạc bộ.");
    }
  });
});
root.addEventListener("input", (event) => {
  if (event.target.name !== "q") return;
  clearTimeout(searchTimer);
  const text = event.target.value;
  searchTimer = setTimeout(async () => {
    if (s.route !== "members" || !s.user) return;
    s.filters.q = text;
    s.filters.page = 1;
    await loadView();
    const input = root.querySelector('[name="q"]');
    if (input) {
      input.focus();
      input.setSelectionRange(text.length, text.length);
    }
  }, 500);
});
window.addEventListener("hashchange", () => {
  clearTimeout(searchTimer);
  document.querySelectorAll("dialog").forEach((x) => x.close());
  readRoute();
  if (s.user) loadView();
  else render();
  window.scrollTo(0, 0);
});
for (const type of ["online", "offline"])
  window.addEventListener(type, () => {
    const banner = document.querySelector("#connection-banner");
    if (banner) banner.hidden = navigator.onLine;
    toast(
      navigator.onLine ? "Đã kết nối lại." : "Bạn đang ngoại tuyến.",
      !navigator.onLine,
    );
  });
document.querySelector(".skip-link").addEventListener("click", (event) => {
  event.preventDefault();
  document.querySelector("#main-content")?.focus();
});
try {
  document.documentElement.dataset.theme =
    localStorage.getItem("fcms-theme") || "light";
} catch {}
readRoute();
async function boot() {
  try {
    s.user = (await api.get("/auth/user", { silentUnauthorized: true })).data;
    await loadApp();
  } catch (error) {
    s.loading = false;
    render();
    if (error.status !== 401) toast(error.message, true);
  }
}
boot();
if (import.meta.env.PROD && "serviceWorker" in navigator && isSecureContext)
  navigator.serviceWorker
    .register("/sw.js")
    .then((registration) => {
      registration.addEventListener("updatefound", () => {
        const worker = registration.installing;
        worker?.addEventListener("statechange", () => {
          if (
            worker.state === "installed" &&
            navigator.serviceWorker.controller
          )
            toast("Phiên bản mới đã sẵn sàng. Tải lại trang để cập nhật.");
        });
      });
    })
    .catch(() => toast("Không thể cài bộ nhớ ứng dụng ngoại tuyến.", true));
