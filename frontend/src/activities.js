import { dialog, field, select, showErrors, toast } from './components/ui.js';
import { escapeHtml as e, unpackList } from './utils.js';

async function allPlayers(api, base, teamId = '') {
  const result = []; let page = 1, last = 1;
  do {
    const query = new URLSearchParams({ page, per_page: 100, ...(teamId ? { team_id: teamId } : {}) });
    const list = unpackList(await api.get(base(`/members?${query}`)));
    result.push(...list.rows); last = list.meta.last_page || 1; page++;
  } while (page <= last);
  return result;
}
function saveDialog(title, description, fields, save) {
  return dialog(title, description, fields, async (form) => {
    const button = form.querySelector('[type="submit"]'); if (button.disabled) return false;
    button.disabled = true;
    try { await save(form); return true; }
    catch (error) { showErrors(form, error.errors || {}, error.message); return false; }
    finally { if (button.isConnected) button.disabled = false; }
  });
}
export async function activityAction(action, id, { state: s, api, base, reload }) {
  const post = async (path, data) => { await api.post(base(path), data); await reload(); toast('Đã lưu.'); };
  if (action === 'new-celebration') return saveDialog('Thêm dịp lễ', 'Lưu ngày và lời chúc để CLB theo dõi.', field('title', 'Tên dịp lễ', '', 'text', true) + field('event_date', 'Ngày', '', 'date', true) + field('message', 'Lời chúc', '', 'textarea'), async (form) => {
    await post('/activities/celebrations', { title: form.elements.title.value.trim(), event_date: form.elements.event_date.value, message: form.elements.message.value.trim() || null });
  });
  if (action === 'new-training') {
    if (!s.teams.length) { toast('Hãy tạo đội bóng trước.', true); return; }
    return saveDialog('Tạo lịch tập', 'Chọn đội bóng, ngày giờ và địa điểm.', select('team_id', 'Đội bóng', s.teams.map((t) => [t.id, t.name])) + field('training_date', 'Ngày tập', '', 'date', true) + field('training_time', 'Giờ tập', '', 'time', true) + field('location', 'Địa điểm', '', 'text', true) + field('notes', 'Ghi chú', '', 'textarea'), async (form) => {
      await post('/activities/trainings', { team_id: Number(form.elements.team_id.value), training_date: form.elements.training_date.value, training_time: form.elements.training_time.value, location: form.elements.location.value.trim(), notes: form.elements.notes.value.trim() || null });
    });
  }
  if (action === 'new-award') {
    const players = await allPlayers(api, base);
    if (!players.length) { toast('Hãy tạo thành viên CLB trước.', true); return; }
    return saveDialog('Khen thưởng cá nhân', 'Lưu danh hiệu và phần quà theo năm.', select('player_id', 'Thành viên', players.map((p) => [p.id, p.full_name])) + field('award_year', 'Năm', new Date().getFullYear(), 'number', true).replace('min="0" max="999"', 'min="1900" max="2100"') + field('title', 'Danh hiệu', '', 'text', true) + field('gift', 'Phần quà', '') + field('notes', 'Ghi chú', '', 'textarea'), async (form) => {
      await post('/activities/awards', { player_id: Number(form.elements.player_id.value), award_year: form.elements.award_year.value, title: form.elements.title.value.trim(), gift: form.elements.gift.value.trim() || null, notes: form.elements.notes.value.trim() || null });
    });
  }
  if (action === 'attendance') {
    const training = s.activities.trainings.find((x) => String(x.id) === id);
    if (!training?.team_id) { toast('Đội bóng không còn hoạt động.', true); return; }
    const players = await allPlayers(api, base, training.team_id);
    const previous = new Map((training.attendance || []).map((x) => [String(x.player_id), x.status]));
    const fields = `<div class="lineup-editor lineup-roster">${players.map((p) => `<label><span>${e(p.full_name)}</span><select data-player="${p.id}" aria-label="Điểm danh ${e(p.full_name)}"><option value="">Chưa điểm danh</option>${[['present', 'Có mặt'], ['absent', 'Vắng'], ['excused', 'Có phép']].map(([v, label]) => `<option value="${v}" ${previous.get(String(p.id)) === v ? 'selected' : ''}>${label}</option>`).join('')}</select></label>`).join('') || '<p>Đội chưa có thành viên.</p>'}</div>`;
    return saveDialog('Điểm danh', `${e(training.team_name)} · ${e(training.training_date)}`, fields, async (form) => {
      const selected = [...form.querySelectorAll('[data-player]')].filter((x) => x.value).map((x) => ({ player_id: Number(x.dataset.player), status: x.value }));
      await api.patch(base(`/activities/trainings/${training.id}/attendance`), { players: selected }); await reload(); toast('Đã lưu điểm danh.');
    });
  }
}
