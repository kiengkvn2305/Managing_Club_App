import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { pollsPage } from '../src/polls-page.js';

describe('polls page', () => {
  const polls = { data: [{ id: 1, title: '<Sân>', description: 'Chọn', deadline: '2026-12-01T00:00:00Z', eligible: true, eligible_count: 2, voted: false, closed: false, options: [{ id: 2, label: '<A>', votes: 1 }] }] };
  it('escapes content and offers eligible members one vote', () => {
    const html = pollsPage({ polls, canCreate: true });
    assert.ok(html.includes('&lt;Sân&gt;')); assert.ok(html.includes('&lt;A&gt;')); assert.ok(html.includes('data-action="poll-vote"')); assert.ok(html.includes('data-action="poll-new"'));
  });
  it('does not offer votes after voting or closing', () => {
    for (const changes of [{ voted: true }, { closed: true }, { eligible: false }]) {
      assert.ok(!pollsPage({ polls: { data: [{ ...polls.data[0], ...changes }] } }).includes('data-action="poll-vote"'));
    }
  });
});
