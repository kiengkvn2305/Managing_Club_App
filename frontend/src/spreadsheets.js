const HEADERS = ['full_name', 'email', 'phone', 'date_of_birth', 'position', 'preferred_foot', 'status', 'notes'];
async function workbook() {
  const module = await import('exceljs');
  const Excel = module.default || module;
  return new Excel.Workbook();
}
function save(blob, name) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a'); link.href = url; link.download = name; link.click();
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}
async function download(book, name) {
  save(new Blob([await book.xlsx.writeBuffer()], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), name);
}
export async function downloadMemberTemplate() {
  const book = await workbook();
  const sheet = book.addWorksheet('Thanh vien');
  sheet.addRow(HEADERS);
  sheet.getRow(1).font = { bold: true };
  sheet.columns.forEach((column) => { column.width = 24; column.numFmt = '@'; });
  const help = book.addWorksheet('Huong dan');
  for (const row of [
    ['Cột', 'Quy tắc'], ['full_name', 'Bắt buộc, tối đa 120 ký tự'],
    ['email', 'Không bắt buộc, không trùng trong CLB hoặc file'],
    ['phone', 'Không bắt buộc; đặt định dạng Text để giữ số 0 đầu'],
    ['date_of_birth', 'Không bắt buộc; ghi YYYY-MM-DD, ví dụ 2002-01-31'],
    ['position', 'Không bắt buộc; GK, FIXO, ALA, PIVOT, CB, LB, RB, CDM, CM, CAM, LM, RM, LW, RW, ST hoặc CF'],
    ['preferred_foot', 'Không bắt buộc; right, left hoặc both'],
    ['status', 'Không bắt buộc; active, inactive hoặc injured; để trống là active'],
    ['notes', 'Không bắt buộc; tối đa 5000 ký tự'],
    ['Lưu ý', 'Một dòng một người; không đổi tên cột, không dùng công thức. Mỗi lần tối đa 500 dòng.'],
    ['Lưu ý', 'Sau khi nhập, chọn thành viên vào đội và thiết lập số áo theo từng đội.'],
  ]) help.addRow(row);
  help.getColumn(1).width = 22; help.getColumn(2).width = 85;
  await download(book, 'mau-thanh-vien.xlsx');
}
export async function parseMembers(file) {
  if (!file || !/\.xlsx$/i.test(file.name) || file.size > 5 * 1024 * 1024) throw new Error('Chọn file .xlsx tối đa 5 MB.');
  const book = await workbook();
  try { await book.xlsx.load(await file.arrayBuffer()); }
  catch { throw new Error('Không đọc được file Excel. Hãy dùng file .xlsx hợp lệ theo mẫu.'); }
  const sheet = book.worksheets[0];
  if (!sheet) throw new Error('File không có trang dữ liệu.');
  if (sheet.rowCount > 501 || sheet.columnCount > HEADERS.length) throw new Error('File tối đa 500 dòng và không thêm cột ngoài mẫu.');
  const headers = HEADERS.map((_, index) => String(sheet.getRow(1).getCell(index + 1).value || '').trim());
  if (headers.join('|') !== HEADERS.join('|')) throw new Error('Tên hoặc thứ tự cột không đúng; hãy tải mẫu và giữ nguyên hàng đầu.');
  const rows = [];
  rows.sourceRows = [];
  for (let index = 2; index <= sheet.rowCount; index++) {
    const row = sheet.getRow(index), result = {};
    for (let col = 0; col < HEADERS.length; col++) {
      const value = row.getCell(col + 1).value;
      if (value && typeof value === 'object' && !(value instanceof Date)) throw new Error(`Dòng ${index}, cột ${HEADERS[col]}: không dùng công thức hoặc liên kết.`);
      if (value instanceof Date) {
        if (HEADERS[col] !== 'date_of_birth' || Number.isNaN(value.getTime())) throw new Error(`Dòng ${index}: định dạng ngày không hợp lệ.`);
        result[HEADERS[col]] = value.toISOString().slice(0, 10);
      } else {
        if (HEADERS[col] === 'phone' && typeof value === 'number') throw new Error(`Dòng ${index}: đặt số điện thoại dạng Text để giữ số 0 đầu.`);
        result[HEADERS[col]] = value == null || String(value).trim() === '' ? null : String(value).trim();
      }
    }
    if (Object.values(result).every((x) => x === null)) continue;
    result.status ||= 'active'; rows.push(result); rows.sourceRows.push(index);
  }
  if (!rows.length) throw new Error('File chưa có dòng thành viên.');
  return rows;
}
export async function exportFinance(items, summary) {
  const book = await workbook(), sheet = book.addWorksheet('Thu chi');
  sheet.addRow(['Ngày', 'Loại', 'Nội dung', 'Số tiền (đồng)', 'Có ảnh xác nhận']);
  sheet.getRow(1).font = { bold: true };
  for (const item of items) sheet.addRow([String(item.occurred_on), item.type === 'income' ? 'Thu' : 'Chi', String(item.description), Number(item.amount), item.has_receipt ? 'Có' : 'Không']);
  sheet.addRow([]); sheet.addRow(['Tổng thu', Number(summary.income || 0)]); sheet.addRow(['Tổng chi', Number(summary.expense || 0)]); sheet.addRow(['Chênh lệch', Number(summary.balance || 0)]);
  sheet.columns.forEach((c) => { c.width = 24; }); sheet.getColumn(3).width = 50;
  await download(book, 'so-thu-chi.xlsx');
}
