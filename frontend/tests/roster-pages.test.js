import test from 'node:test';
import assert from 'node:assert/strict';
import { state } from '../src/state.js';
import { clubDirectoryPage, teamRosterPage } from '../src/roster-pages.js';
import { duesPage } from '../src/dues-page.js';

test('rosters show separate profile columns and dues are managed separately', () => {
  Object.assign(state, { clubs: [{ id: 1, membership_role: 'owner', can_view_finance: true, can_manage_finance: true }], clubId: 1, teamRosterId: '2', teams: [{ id: 2, name: 'A', format: '7' }], members: [{ id: 3, full_name: 'An', email: 'an@example.com', date_of_birth: '2000-01-01', phone: '0123456789', position: 'ST', status: 'active', teams: [{ id: 2, position: 'GK' }] }], meta: { total: 1, current_page: 1, last_page: 1 }, filters: { q: '', team_id: '', status: '', page: 1 }, teamDues: { 3: { status: 'paid', debt: 100, months: 1, upcoming: [{ status: 'paid', month: '2027-01' }] } } });
  const club = clubDirectoryPage();
  assert.match(club, /<th>Ngày sinh<\/th>/);
  assert.match(club, /<td>an@example.com<\/td>/);
  assert.doesNotMatch(club, /<th>Vị trí<\/th>/);
  assert.doesNotMatch(club, />ST</);
  const team = teamRosterPage();
  assert.match(team, /<td>GK<\/td>/);
  assert.doesNotMatch(team, /<th>Đóng quỹ<\/th>/);
  Object.assign(state, { duesMonth: '2026-05', duesSearch: '', duesTeam: '', duesStatus: '', dues: [{ player_id: 3, name: 'An', teams: [{ team_id: 2, name: 'A', amount: 100, status: 'debt' }] }] });
  const dues = duesPage();
  assert.match(dues, /Chưa đóng/);
  assert.match(dues, /data-action="correct-dues"/);
});
