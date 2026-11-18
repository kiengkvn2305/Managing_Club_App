import { dialog, showErrors, toast } from './components/ui.js';
import { escapeHtml as e, statusLabel } from './utils.js';

export function reviewImport({ rows, preview, api, base, reload }) {
  const duplicates = preview.map((item, index) => ({ ...item, index })).filter((x) => x.matches.length);
  const descriptions = (person) => [
    ['Họ tên', person.full_name], ['Email', person.email], ['Điện thoại', person.phone],
    ['Ngày sinh', person.date_of_birth], ['Vị trí', person.position], ['Chân thuận', person.preferred_foot],
    ['Trạng thái', statusLabel(person.status)], ['Ghi chú', person.notes],
    ['Đội bóng', (person.teams || []).map((team) => team.name).join(', ')],
  ].map(([key, value]) => `<div><dt>${e(key)}</dt><dd>${e(value || 'Chưa có')}</dd></div>`).join('');
  const fields = `<div class="lineup-editor"><p>Tìm thấy ${duplicates.length} dòng có thể trùng. Email được kiểm tra trước; nếu không trùng email, hệ thống kiểm tra họ tên cùng ngày sinh. Email không được trùng khi tạo mới.</p>${duplicates.length ? '<button type="button" class="button secondary" data-overwrite-all>Ghi đè tất cả</button>' : '<p>Không có thành viên trùng. Các dòng sẽ được tạo mới.</p>'}${duplicates.map(({ index, matches }) => `<section class="panel panel-body"><h3>Dòng ${rows.sourceRows[index]} · ${e(rows[index].full_name)}</h3><p>Thông tin trong file</p><dl class="details">${descriptions(rows[index])}</dl>${matches.map((person, n) => `<p>Hồ sơ đang có ${matches.length > 1 ? n + 1 : ''}</p><dl class="details">${descriptions(person)}</dl>`).join('')}<div class="field"><label for="decision-${index}">Cách xử lý</label><select id="decision-${index}" data-decision="${index}"><option value="">Chọn cách xử lý</option>${matches.map((person, n) => `<option value="overwrite:${person.id}">Ghi đè ${e(person.full_name)}${matches.length > 1 ? ` (hồ sơ ${n + 1})` : ''}</option>`).join('')}<option value="create">Tạo thành viên mới</option><option value="skip">Không ghi đè, bỏ qua dòng này</option></select></div></section>`).join('')}</div>`;
  const modal = dialog('Kiểm tra thành viên trùng', 'Xem chi tiết và chọn cách xử lý trước khi nhập. Hủy sẽ không thay đổi dữ liệu.', fields, async (form) => {
    const decisions = preview.map((item, index) => {
      const value = form.querySelector(`[data-decision="${index}"]`)?.value || (item.matches.length ? '' : 'create');
      const [action, target] = value.split(':');
      return { action, ...(target ? { target_id: Number(target) } : {}), match_versions: item.matches.map((m) => m.version) };
    });
    if (decisions.some((d) => !d.action)) { showErrors(form, {}, 'Hãy chọn cách xử lý cho từng dòng trùng.'); return false; }
    const button = form.querySelector('[type="submit"]');
    if (button.disabled) return false;
    button.disabled = true;
    try {
      const result = await api.post(base('/members/import/commit'), { players: rows, decisions });
      await reload(); toast(`Đã tạo ${result.data.created}, ghi đè ${result.data.overwritten}, bỏ qua ${result.data.skipped} thành viên.`); return true;
    } catch (error) {
      const messages = Object.entries(error.errors || {}).map(([key, value]) => { const match = /^players\.(\d+)(?:\.(.+))?$/.exec(key); return match ? `Dòng ${rows.sourceRows[Number(match[1])]}${match[2] ? `, ${match[2]}` : ''}: ${Array.isArray(value) ? value[0] : value}` : `${key}: ${Array.isArray(value) ? value[0] : value}`; });
      showErrors(form, {}, messages.join(' | ') || error.message); return false;
    } finally { if (button.isConnected) button.disabled = false; }
  }, 'Xác nhận nhập');
  modal.querySelector('[data-overwrite-all]')?.addEventListener('click', () => {
    for (const item of duplicates) if (item.matches.length === 1) modal.querySelector(`[data-decision="${item.index}"]`).value = `overwrite:${item.matches[0].id}`;
    if (duplicates.some((item) => item.matches.length > 1)) showErrors(modal.querySelector('form'), {}, 'Có nhiều hồ sơ trùng: hãy chọn từng hồ sơ cụ thể.');
  });
}
