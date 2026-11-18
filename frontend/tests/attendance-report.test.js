import test from 'node:test';
import assert from 'node:assert/strict';
import { summarizeAttendance } from '../src/attendance-report.js';
import { compareVietnameseNames } from '../src/utils.js';

test('attendance percentage ignores sessions without a recorded status', () => {
  assert.deepEqual(summarizeAttendance(['present', 'absent', 'excused', undefined]), { marked: 3, present: 1, percent: 33 });
  assert.deepEqual(summarizeAttendance([undefined]), { marked: 0, present: 0, percent: null });
});

test('member names are sorted by given name rather than family name', () => {
  assert.deepEqual(['An Bình', 'Trần An', 'Lê Cường'].sort(compareVietnameseNames), ['Trần An', 'An Bình', 'Lê Cường']);
});
