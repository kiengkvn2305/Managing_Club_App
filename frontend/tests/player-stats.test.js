import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { playerStatsMarkup } from '../src/player-stats.js';

describe('player statistics', () => {
  it('explains empty real data without inventing records', () => {
    const html = playerStatsMarkup({ players: [], teams: [], club: { matches: 0 } });
    assert.match(html, /Chưa có thống kê/);
    assert.match(html, /0 trận có thống kê/);
  });
  it('escapes names and distinguishes unknown goalkeeper and rating data', () => {
    const html = playerStatsMarkup({ players: [{ full_name: '<script>', date_of_birth: null, email: null, matches_played: 1, goals: 1, assists: 0, yellow_cards: 0, red_cards: 0, goalkeeper_matches: 0, clean_sheets: 0, goals_conceded: 0, average_rating: null }], teams: [], club: {} });
    assert.doesNotMatch(html, /<script>/);
    assert.match(html, /&lt;script&gt;/);
    assert.match(html, /0 \/ 0/);
  });
});
