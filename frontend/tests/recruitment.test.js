import { test } from 'node:test';
import assert from 'node:assert/strict';
import { recruitmentPage } from '../src/recruitment-page.js';

test('recruitment page escapes data, restricts editing and displays state', () => {
  const post = { id: 1, title: '<script>alert(1)</script>', status: 'open', position: 'ST' };
  const page = recruitmentPage({ recruitment: { data: [post], total: 1 }, canManage: false });
  assert.match(page, /&lt;script&gt;/);
  assert.doesNotMatch(page, /<script>/);
  assert.match(page, /Đang tuyển/);
  assert.doesNotMatch(page, /recruitment-edit/);
  assert.match(recruitmentPage({ recruitment: { data: [post] }, canManage: true }), /recruitment-edit/);
});
