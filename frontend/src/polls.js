import { dialog, field, showErrors, toast } from './components/ui.js';
import { escapeHtml as e } from './utils.js';

export function openPollCreate({ api, base, reload }) {
  const fields = field('title', 'Nội dung biểu quyết', '', 'text', true) + field('description', 'Mô tả', '', 'textarea') + field('deadline', 'Hạn bỏ phiếu', '', 'datetime-local', true) + field('options', 'Các lựa chọn (mỗi dòng một lựa chọn)', '', 'textarea', true);
  return dialog('Tạo biểu quyết', 'Danh sách cử tri gồm các tài khoản đang hoạt động trong CLB tại thời điểm tạo.', fields, async (form) => {
    const submit = form.querySelector('[type="submit"]'); if (submit.disabled) return false; submit.disabled = true;
    try {
      const data = Object.fromEntries(new FormData(form)); data.options = data.options.split(/\r?\n/).map((x) => x.trim()).filter(Boolean);
      if (!data.deadline || Number.isNaN(new Date(data.deadline).getTime())) throw new Error('Vui lòng chọn hạn bỏ phiếu hợp lệ.');
      data.deadline = new Date(data.deadline).toISOString();
      await api.post(base('/polls'), data); await reload(); toast('Đã tạo biểu quyết.'); return true;
    } catch (error) { showErrors(form, error.errors || {}, error.message); return false; }
    finally { if (submit.isConnected) submit.disabled = false; }
  }, 'Tạo biểu quyết');
}

export function confirmPollVote({ poll, option, api, base, reload }) {
  return dialog('Xác nhận bỏ phiếu', `Bạn chọn “${e(option.label)}” cho biểu quyết “${e(poll.title)}”. Mỗi người chỉ được bỏ phiếu một lần.`, '', async (form) => {
    const submit = form.querySelector('[type="submit"]'); if (submit.disabled) return false; submit.disabled = true;
    try { await api.post(base(`/polls/${poll.id}/vote`), { option_id: option.id }); await reload(); toast('Đã ghi nhận phiếu.'); return true; }
    catch (error) { showErrors(form, error.errors || {}, error.message); return false; }
    finally { if (submit.isConnected) submit.disabled = false; }
  }, 'Xác nhận');
}
