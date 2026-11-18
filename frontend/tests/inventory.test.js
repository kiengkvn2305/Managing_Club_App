import test from 'node:test';
import assert from 'node:assert/strict';
import { inventoryPage } from '../src/inventory-page.js';

test('inventory page identifies low stock and escapes stored content', () => {
  const html = inventoryPage({ canManage: true, inventory: { data: [{ id: 1, name: '<b>Ball</b>', unit: 'quả', kind: 'asset', quantity: 2, low_stock_threshold: 2 }], total: 1, current_page: 1, last_page: 1 } });
  assert.match(html, /Tồn thấp/);
  assert.match(html, /&lt;b&gt;Ball&lt;\/b&gt;/);
  assert.match(html, /inventory-move/);
  assert.match(html, /inventory-assignments/);
  assert.doesNotMatch(inventoryPage({ canManage: false, inventory: { data: [{ id: 1, name: 'Ball', unit: 'quả', quantity: 3, low_stock_threshold: 2 }] } }), /inventory-move/);
  const filtered = inventoryPage({ search: '\"><b>', lowStock: true });
  assert.match(filtered, /value="&quot;&gt;&lt;b&gt;"/);
  assert.match(filtered, /value="1" checked/);
  assert.doesNotMatch(inventoryPage({ inventory: { data: [{ id: 2, name: 'Nước', unit: 'chai', kind: 'consumable', quantity: 1, low_stock_threshold: 0 }] } }), /inventory-assignments/);
});
