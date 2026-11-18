export const escapeHtml = (value) =>
  String(value ?? "").replace(
    /[&<>"']/g,
    (char) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        char
      ],
  );
export const initials = (value) =>
  String(value || "FC")
    .trim()
    .split(/\s+/)
    .slice(-2)
    .map((word) => word[0])
    .join("")
    .toLocaleUpperCase("vi");
export const number = (value) =>
  new Intl.NumberFormat("vi-VN").format(Number(value) || 0);
export const roleLabel = (role) =>
  ({
    owner: "Chủ sở hữu",
    co_owner: "Đồng sở hữu",
    general_manager: "Tổng quản lý",
    treasurer: "Thủ quỹ",
    hr_manager: "Quản lý nhân sự",
    equipment_manager: "Quản lý trang bị",
    sporting_manager: "Quản lý chuyên môn",
    member: "Thành viên",
  })[role] || "Thành viên";
export const statusLabel = (status) =>
  ({ active: "Đang hoạt động", inactive: "Tạm ngưng", injured: "Chấn thương" })[
    status
  ] || status;
export const canManageRoster = (role) =>
  [
    "owner",
    "co_owner",
    "general_manager",
    "hr_manager",
    "sporting_manager",
  ].includes(role);
export const canManageClub = (role) =>
  ["owner", "co_owner", "general_manager"].includes(role);
export function validDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const date = new Date(`${value}T00:00:00Z`);
  return (
    !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
  );
}
export function validate(kind, data) {
  const errors = {};
  const required = (key, label) => {
    if (!String(data[key] || "").trim())
      errors[key] = `${label} không được để trống.`;
  };
  if (["register", "profile"].includes(kind)) required("name", "Họ tên");
  if (["login", "register", "profile", "invitation"].includes(kind))
    required("email", "Email");
  if (["login", "register"].includes(kind)) required("password", "Mật khẩu");
  if (kind === "register" && data.password && data.password.length < 8)
    errors.password = "Mật khẩu cần ít nhất 8 ký tự.";
  if (kind === "register" && data.password_confirmation !== data.password)
    errors.password_confirmation = "Mật khẩu xác nhận chưa khớp.";
  if (kind === "member") {
    required("full_name", "Họ tên");
    if (
      data.jersey_number !== "" &&
      data.jersey_number != null &&
      (!/^\d+$/.test(String(data.jersey_number)) ||
        Number(data.jersey_number) > 999)
    )
      errors.jersey_number = "Số áo phải từ 0 đến 999.";
    if (
      data.date_of_birth &&
      (!validDate(data.date_of_birth) ||
        data.date_of_birth > new Date().toISOString().slice(0, 10))
    )
      errors.date_of_birth = "Ngày sinh không hợp lệ.";
    if (!["active", "inactive", "injured"].includes(data.status))
      errors.status = "Chọn trạng thái hợp lệ.";
  }
  if (["club", "team"].includes(kind)) required("name", "Tên");
  if (kind === "team" && !["5", "7", "11"].includes(String(data.format)))
    errors.format = "Chọn loại sân 5, 7 hoặc 11 người.";
  if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email))
    errors.email = "Nhập địa chỉ email hợp lệ.";
  return errors;
}
export function unpackList(payload) {
  if (Array.isArray(payload?.data))
    return { rows: payload.data, meta: payload.meta || {} };
  if (Array.isArray(payload?.data?.data))
    return { rows: payload.data.data, meta: payload.data };
  return { rows: [], meta: {} };
}
