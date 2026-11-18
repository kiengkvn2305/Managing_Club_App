import test from 'node:test';
import assert from 'node:assert/strict';
import { state } from '../src/state.js';
import { discoveryPage } from '../src/discovery-page.js';
import { plannedExpensesPanel } from '../src/planned-expenses.js';

test('discovery and invitation controls use only public data and escaped text', () => {
  Object.assign(state, { clubId: 1, clubs: [{ id: 1, name: 'A', status: 'active', membership_role: 'owner', is_discoverable: false }], discoveryQuery: '', discovery: { data: [{ id: 2, name: '<script>Beta</script>', city: 'Hà Nội', formats: ['7'] }], meta: { current_page: 1, last_page: 1 } }, friendlies: { data: [{ id: 9, source_club_id: 2, target_club_id: 1, source_name: 'Beta', target_name: 'A', status: 'pending', match_date: '2026-05-01', message: '<img src=x>' }], meta: { current_page: 1, last_page: 1 } } });
  const html = discoveryPage();
  assert.match(html, /data-action="invite-friendly"/);
  assert.match(html, /data-status="accepted"/);
  assert.doesNotMatch(html, /<script>Beta<\/script>/);
  assert.doesNotMatch(html, /<img src=x>/);
});

test('planned expenses are labeled separately from actual spending', () => {
  Object.assign(state, { clubId: 1, clubs: [{ id: 1, can_manage_finance: true }], plannedExpenses: [{ id: 8, month: '2026-03', description: 'Tiền sân', amount: 1200000, notes: 'Cuối tháng' }] });
  const html = plannedExpensesPanel();
  assert.match(html, /không tự ghi chi hoặc trừ số dư/);
  assert.match(html, /Tiền sân/);
  assert.match(html, /data-action="edit-planned-expense"/);
});
