import { dialog, field, select, showErrors, toast } from './components/ui.js';
import { escapeHtml as e, number } from './utils.js';

function submitOnce(form, operation) {
  const submit = form.querySelector('[type="submit"]');
  if (submit.disabled) return Promise.resolve(false);
  submit.disabled = true;
  return operation().then(() => true).catch((error) => { showErrors(form, error.errors || {}, error.message); return false; }).finally(() => { if (submit.isConnected) submit.disabled = false; });
}

export function openInventoryItem({ item, api, base, reload }) {
  const fields = field('name', 'Tên vật tư', item?.name || '', 'text', true) + field('category', 'Nhóm', item?.category || '') + field('unit', 'Đơn vị', item?.unit || 'cái', 'text', true) + select('kind', 'Loại', [['consumable', 'Vật tư tiêu hao'], ['asset', 'Tài sản']], item?.kind || 'consumable') + field('low_stock_threshold', 'Ngưỡng cảnh báo tồn thấp', item?.low_stock_threshold ?? 0, 'number', true) + field('notes', 'Ghi chú', item?.notes || '', 'textarea');
  const modal = dialog(item ? 'Chỉnh sửa vật tư' : 'Thêm vật tư', 'Số lượng ban đầu bằng 0; dùng nhập kho để ghi nhận số lượng và lịch sử.', fields, (form) => submitOnce(form, async () => {
    const data = Object.fromEntries(new FormData(form)); data.low_stock_threshold = data.low_stock_threshold === '' ? null : Number(data.low_stock_threshold);
    if (item) await api.patch(base(`/inventory/${item.id}`), data); else await api.post(base('/inventory'), data);
    await reload(); toast('Đã lưu vật tư.');
  }));
  modal.querySelector('[name="low_stock_threshold"]').max = '1000000';
  return modal;
}

export function openInventoryMovement({ item, api, base, reload }) {
  const fields = select('type', 'Nghiệp vụ', [['in', 'Nhập kho'], ['out', 'Xuất kho'], ['adjust', 'Kiểm kê: đặt lại số tồn']], 'in') + field('quantity', 'Số lượng', '', 'number', true) + field('reason', 'Lý do / ghi chú', '', 'textarea', true);
  const modal = dialog('Ghi biến động kho', `${e(item.name)} · tồn hiện tại ${number(item.quantity)} ${e(item.unit)}. Với kiểm kê, nhập số tồn thực tế.`, fields, (form) => submitOnce(form, async () => {
    const data = Object.fromEntries(new FormData(form)); data.quantity = data.quantity === '' ? null : Number(data.quantity);
    await api.post(base(`/inventory/${item.id}/movements`), data); await reload(); toast('Đã ghi biến động kho.');
  }), 'Ghi biến động');
  modal.querySelector('[name="quantity"]').max = '1000000';
  return modal;
}

export async function showInventoryHistory({ item, api, base }) {
  let page = 1;
  const render = async () => {
    const result = await api.get(base(`/inventory/${item.id}/movements?page=${page}`));
    const labels = { in: 'Nhập', out: 'Xuất', adjust: 'Kiểm kê', assign: 'Cấp phát', return: 'Thu hồi' };
    const rows = result.data.map((m) => `<tr><td>${e(m.created_at)}</td><td>${e(labels[m.type] || m.type)}</td><td>${m.change > 0 ? '+' : ''}${number(m.change)}</td><td>${number(m.balance_after)}</td><td>${e(m.reason)}</td></tr>`).join('');
    const host = document.createElement('dialog'); host.setAttribute('aria-label', 'Lịch sử kho');
    host.innerHTML = `<div class="dialog-head"><div><h2>Lịch sử · ${e(item.name)}</h2><p>Lịch sử biến động không thể sửa hoặc xóa.</p></div><button type="button" class="icon-button" data-close aria-label="Đóng">×</button></div><div class="dialog-body">${rows ? `<div class="table-wrap"><table><thead><tr><th>Thời gian</th><th>Nghiệp vụ</th><th>Thay đổi</th><th>Tồn sau</th><th>Lý do</th></tr></thead><tbody>${rows}</tbody></table></div>` : '<p>Chưa có biến động.</p>'}<div class="table-footer"><button class="icon-button" data-prev ${page <= 1 ? 'disabled' : ''}>←</button> ${page} / ${result.last_page} <button class="icon-button" data-next ${page >= result.last_page ? 'disabled' : ''}>→</button></div></div>`;
    document.body.append(host); host.querySelector('[data-close]').onclick = () => host.close();
    host.querySelector('[data-prev]').onclick = async () => { page--; host.close(); await render(); };
    host.querySelector('[data-next]').onclick = async () => { page++; host.close(); await render(); };
    host.addEventListener('close', () => host.remove(), { once: true }); host.showModal();
  };
  try { await render(); } catch (error) { toast(error.message, true); }
}

export async function showInventoryAssignments({ item, api, base, reload, canManage }) {
  let page = 1;
  const render = async () => {
    const result = await api.get(base(`/inventory/${item.id}/assignments?page=${page}`));
    const host = document.createElement('dialog'); host.setAttribute('aria-label', 'Cấp phát trang bị');
    const rows = result.data.map((a) => `<tr><td>${e(a.player_name)}</td><td>${e(a.created_at)}</td><td>${number(a.quantity)}</td><td>${number(a.remaining)}</td><td>${canManage && !item.archived && a.remaining > 0 ? `<button type="button" class="text-button" data-return="${a.id}">Thu hồi</button>` : '—'}</td></tr>`).join('');
    host.innerHTML = `<div class="dialog-head"><div><h2>Cấp phát · ${e(item.name)}</h2><p>Theo dõi số lượng đang mượn và đã thu hồi.</p></div><button type="button" class="icon-button" data-close aria-label="Đóng">×</button></div><div class="dialog-body">${canManage && !item.archived ? '<button type="button" class="button" data-assign>Cấp phát mới</button>' : ''}${rows ? `<div class="table-wrap"><table><thead><tr><th>Thành viên</th><th>Thời gian</th><th>Đã cấp</th><th>Còn mượn</th><th>Thao tác</th></tr></thead><tbody>${rows}</tbody></table></div>` : '<p>Chưa có cấp phát.</p>'}<div class="table-footer"><button class="icon-button" data-prev aria-label="Trang trước" ${page <= 1 ? 'disabled' : ''}>←</button> ${page} / ${result.last_page} <button class="icon-button" data-next aria-label="Trang sau" ${page >= result.last_page ? 'disabled' : ''}>→</button></div></div>`;
    document.body.append(host); host.querySelector('[data-close]').onclick = () => host.close();
    const refresh = async () => { host.close(); await reload(); await render(); };
    host.querySelector('[data-assign]')?.addEventListener('click', () => openInventoryAssignment({ item, api, base, refresh }));
    host.querySelectorAll('[data-return]').forEach((button) => button.addEventListener('click', () => {
      const assignment = result.data.find((a) => String(a.id) === button.dataset.return);
      const formFields = field('quantity', 'Số lượng thu hồi', '', 'number', true) + field('reason', 'Lý do / ghi chú', '', 'textarea', true);
      const modal = dialog('Thu hồi trang bị', `${e(assignment.player_name)} · còn mượn ${number(assignment.remaining)} ${e(item.unit)}.`, formFields, (form) => submitOnce(form, async () => {
        const data = Object.fromEntries(new FormData(form)); data.quantity = data.quantity === '' ? null : Number(data.quantity);
        await api.post(base(`/inventory/${item.id}/assignments/${assignment.id}/return`), data); await refresh(); toast('Đã thu hồi trang bị.');
      }), 'Thu hồi');
      modal.querySelector('[name="quantity"]').max = String(assignment.remaining);
    }));
    host.querySelector('[data-prev]').onclick = async () => { page--; host.close(); await render(); };
    host.querySelector('[data-next]').onclick = async () => { page++; host.close(); await render(); };
    host.addEventListener('close', () => host.remove(), { once: true }); host.showModal();
  };
  try { await render(); } catch (error) { toast(error.message, true); }
}

function openInventoryAssignment({ item, api, base, refresh }) {
  const fields = `<div class="field"><label for="assignment-search">Tìm thành viên</label><input id="assignment-search" type="search" autocomplete="off"><button type="button" class="button secondary" data-search>Tìm</button><div><button type="button" class="icon-button" data-prev aria-label="Trang trước">←</button> <span data-page></span> <button type="button" class="icon-button" data-next aria-label="Trang sau">→</button></div></div><div class="field"><label for="f-player_id">Thành viên *</label><select id="f-player_id" name="player_id" required></select><small data-error="player_id"></small></div>` + field('quantity', 'Số lượng', '', 'number', true) + field('reason', 'Lý do / ghi chú', '', 'textarea', true);
  const modal = dialog('Cấp phát trang bị', `${e(item.name)}. Tìm theo tên, sau đó chọn thành viên.`, fields, (form) => submitOnce(form, async () => {
    const data = Object.fromEntries(new FormData(form)); data.quantity = data.quantity === '' ? null : Number(data.quantity);
    await api.post(base(`/inventory/${item.id}/assignments`), data); await refresh(); toast('Đã cấp phát trang bị.');
  }), 'Cấp phát');
  modal.querySelector('[name="quantity"]').max = '1000000';
  let page = 1, search = '', last = 1, serial = 0;
  const load = async () => {
    const current = ++serial;
    const selectEl = modal.querySelector('[name="player_id"]');
    selectEl.innerHTML = '<option value="">Đang tải thành viên...</option>'; selectEl.disabled = true;
    try {
      const result = await api.get(base(`/inventory/members?search=${encodeURIComponent(search)}&page=${page}`));
      if (current !== serial || !modal.isConnected) return;
      last = result.last_page; selectEl.disabled = false;
      selectEl.innerHTML = '<option value="">Chọn thành viên</option>' + result.data.map((p) => `<option value="${p.id}">${e([p.full_name, p.date_of_birth, p.email, (p.team_labels || []).join(', ') || 'Chưa xếp đội'].filter(Boolean).join(' · '))}</option>`).join('');
      modal.querySelector('[data-page]').textContent = `${page} / ${last}`;
      modal.querySelector('[data-prev]').disabled = page <= 1; modal.querySelector('[data-next]').disabled = page >= last;
    } catch (error) { if (current === serial && modal.isConnected) { selectEl.disabled = false; selectEl.innerHTML = '<option value="">Không tải được thành viên</option>'; toast(error.message, true); } }
  };
  modal.querySelector('[data-search]').onclick = () => { search = modal.querySelector('#assignment-search').value.trim(); page = 1; load(); };
  modal.querySelector('[data-prev]').onclick = () => { if (page > 1) { page--; load(); } };
  modal.querySelector('[data-next]').onclick = () => { if (page < last) { page++; load(); } };
  load();
}
