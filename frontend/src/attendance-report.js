import { dialog } from './components/ui.js';
import { escapeHtml as e, formatDate, unpackList, vietnamToday } from './utils.js';

export function summarizeAttendance(statuses) {
  const marked = statuses.filter((status) => ['present', 'absent', 'excused'].includes(status)).length;
  const present = statuses.filter((status) => status === 'present').length;
  return { marked, present, percent: marked ? Math.round(present / marked * 100) : null };
}
export function openAttendanceReport({ teams, trainings, api, base }) {
  const today = vietnamToday(), year = today.slice(0, 4), month = today.slice(5, 7);
  const fields = `<div class="member-picker"><div class="filters"><label>Đội bóng <select data-report-team>${teams.map((t) => `<option value="${e(t.id)}">${e(t.name)}</option>`).join('')}</select></label><label>Khoảng xem <select data-report-period><option value="month">Tháng</option><option value="quarter">Quý</option><option value="year">Năm</option></select></label><label>Năm <input type="number" data-report-year min="1900" max="2100" value="${e(year)}"></label><label data-report-month-label>Tháng <select data-report-month>${Array.from({length:12}, (_, i) => `<option value="${String(i+1).padStart(2, '0')}" ${String(i+1).padStart(2, '0') === month ? 'selected' : ''}>${i+1}</option>`).join('')}</select></label><label data-report-quarter-label hidden>Quý <select data-report-quarter>${[1,2,3,4].map((q) => `<option value="${q}" ${q === Math.ceil(Number(month)/3) ? 'selected' : ''}>${q}</option>`).join('')}</select></label><label>Tìm tên / ghi chú <input type="search" data-report-search></label><label>Sắp xếp <select data-report-sort><option value="asc">Tên A–Z</option><option value="desc">Tên Z–A</option></select></label></div><div data-report-body></div></div>`;
  const modal = dialog('Tiến độ điểm danh', '✓ Có mặt · Vắng · Phép · — Chưa điểm danh. Tỷ lệ chỉ tính những buổi đã ghi trạng thái cho người đó.', fields, async () => true, 'Đóng');
  modal.classList.add('member-picker-dialog');
  const teamSelect = modal.querySelector('[data-report-team]'), period = modal.querySelector('[data-report-period]'), body = modal.querySelector('[data-report-body]');
  let players = [], loadedTeam = '', serial = 0;
  const render = () => {
    const y = modal.querySelector('[data-report-year]').value, mode = period.value, m = modal.querySelector('[data-report-month]').value, q = Number(modal.querySelector('[data-report-quarter]').value);
    modal.querySelector('[data-report-month-label]').hidden = mode !== 'month'; modal.querySelector('[data-report-quarter-label]').hidden = mode !== 'quarter';
    const sessions = trainings.filter((t) => String(t.team_id) === teamSelect.value && t.approval_status === 'approved' && t.training_status === 'scheduled' && String(t.training_date).slice(0, 4) === y && (mode === 'year' || (mode === 'month' ? t.training_date.slice(5, 7) === m : Math.ceil(Number(t.training_date.slice(5, 7))/3) === q))).sort((a,b) => String(a.training_date).localeCompare(String(b.training_date)) || String(a.training_time).localeCompare(String(b.training_time)) || a.id - b.id);
    const byId = new Map(players.map((p) => [String(p.id), { id: p.id, name: p.full_name, notes: p.notes || '' }]));
    sessions.forEach((session) => (session.attendance || []).forEach((p) => { if (!byId.has(String(p.player_id))) byId.set(String(p.player_id), { id: p.player_id, name: p.name, notes: '' }); }));
    const query = modal.querySelector('[data-report-search]').value.trim().toLocaleLowerCase('vi'), direction = modal.querySelector('[data-report-sort]').value === 'desc' ? -1 : 1;
    const last = (name) => String(name || '').trim().split(/\s+/).pop();
    const rows = [...byId.values()].filter((p) => `${p.name} ${p.notes}`.toLocaleLowerCase('vi').includes(query)).sort((a,b) => direction * (last(a.name).localeCompare(last(b.name), 'vi') || a.name.localeCompare(b.name, 'vi')));
    const status = { present: '✓', absent: 'Vắng', excused: 'Phép' };
    body.innerHTML = sessions.length ? `<div class="table-wrap"><table><thead><tr><th>Thành viên</th>${sessions.map((t) => `<th>${e(formatDate(t.training_date))}<br>${e(t.training_time || '')}</th>`).join('')}<th>Có mặt / đã điểm danh</th></tr></thead><tbody>${rows.map((p) => { const values = sessions.map((t) => (t.attendance || []).find((entry) => String(entry.player_id) === String(p.id))?.status); const summary = summarizeAttendance(values); return `<tr><td>${e(p.name)}</td>${values.map((v) => `<td>${e(status[v] || '—')}</td>`).join('')}<td>${summary.present}/${summary.marked}${summary.percent !== null ? ` (${summary.percent}%)` : ''}</td></tr>`; }).join('')}</tbody></table></div>` : '<p class="panel-body">Không có buổi tập phù hợp trong khoảng đã chọn.</p>';
  };
  const loadTeam = async () => {
    const team = teamSelect.value, request = ++serial; if (!team) { body.textContent = 'Chưa có đội bóng.'; return; }
    body.textContent = 'Đang tải thành viên...'; const list = []; let page = 1, last = 1;
    try { do { const result = unpackList(await api.get(base(`/members?team_id=${encodeURIComponent(team)}&per_page=100&page=${page}`))); list.push(...result.rows); last = result.meta.last_page || 1; page++; } while (page <= last); if (request !== serial || !modal.isConnected) return; players = list; loadedTeam = team; render(); }
    catch (error) { if (request === serial) body.textContent = error.message; }
  };
  modal.querySelector('.member-picker').addEventListener('input', () => { if (loadedTeam === teamSelect.value) render(); });
  modal.querySelector('.member-picker').addEventListener('change', (event) => { if (event.target === teamSelect) loadTeam(); else if (loadedTeam === teamSelect.value) render(); });
  loadTeam(); return modal;
}
