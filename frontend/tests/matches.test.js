import test from 'node:test';
import assert from 'node:assert/strict';
import { state } from '../src/state.js';
import { matchesPage, calendarRange } from '../src/matches-page.js';

test('match list shows status and score without rendering untrusted markup', () => {
  Object.assign(state, { clubId: 1, clubs: [{ id: 1, membership_role: 'owner' }], matchesMonth: '2026-05', matches: { meta: { total: 1, current_page: 1, last_page: 1 }, data: [{ id: 9, match_date: '2026-05-12', start_time: '18:00', opponent: '<script>bad</script>', team_name: 'A', match_type: 'friendly', status: 'completed', our_score: 2, opponent_score: 1 }] } });
  const html = matchesPage();
  assert.match(html, /Lịch trận đấu/); assert.match(html, /2 – 1/); assert.match(html, /Đã kết thúc/);
  assert.doesNotMatch(html, /<script>bad<\/script>/);
});

test('calendar ranges align to Monday across months and years', () => {
  assert.deepEqual(calendarRange('month', '2026-02', '2026-02-01'), { from: '2026-01-26', to: '2026-03-01' });
  assert.deepEqual(calendarRange('week', '2026-05', '2026-05-12'), { from: '2026-05-11', to: '2026-05-17' });
});
