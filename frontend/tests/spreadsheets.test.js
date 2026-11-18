import test from 'node:test';
import assert from 'node:assert/strict';
import ExcelJS from 'exceljs';
import { parseMembers } from '../src/spreadsheets.js';

const headers = ['full_name', 'email', 'phone', 'date_of_birth', 'position', 'preferred_foot', 'status', 'notes'];
async function fileWith(row) {
  const book = new ExcelJS.Workbook();
  const sheet = book.addWorksheet('Thanh vien');
  sheet.addRow(headers); sheet.addRow(row);
  const buffer = await book.xlsx.writeBuffer();
  return { name: 'members.xlsx', size: buffer.byteLength, arrayBuffer: async () => buffer };
}
test('imports a real Excel file and keeps phone as text', async () => {
  const file = await fileWith(['Nguyễn An', 'an@example.test', '0912345678', '2000-02-29', 'GK', 'right', 'active', '']);
  const rows = await parseMembers(file);
  assert.equal(rows[0].full_name, 'Nguyễn An');
  assert.equal(rows[0].phone, '0912345678');
  assert.equal(rows[0].date_of_birth, '2000-02-29');
});
test('rejects formulas and numeric phone values', async () => {
  await assert.rejects(() => fileWith([{ formula: '1+1', result: 2 }]).then(parseMembers), /không dùng công thức/);
  await assert.rejects(() => fileWith(['An', '', 912345678]).then(parseMembers), /Text/);
});
