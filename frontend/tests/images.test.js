import test from 'node:test';
import assert from 'node:assert/strict';
import { avatar, imageUrl } from '../src/images.js';

test('image URLs stay on the authenticated application origin', () => {
  assert.equal(imageUrl('club', 3, 'abc', 3), '/api/v1/clubs/3/images/avatar?v=abc');
  assert.equal(imageUrl('background', 3, 'abc', 3), '/api/v1/clubs/3/images/background?v=abc');
  assert.equal(imageUrl('player', 4, 'abc', 3), '/api/v1/clubs/3/members/4/avatar?v=abc');
  assert.equal(imageUrl('account', 1, 'abc'), '/api/v1/auth/avatar?v=abc');
  assert.equal(imageUrl('player', '../4', 'abc', 3), '');
  assert.equal(imageUrl('background', 3, null, 3), '');
  assert.match(avatar('An', '/image'), /<img src="\/image"/);
  assert.doesNotMatch(avatar('<img src=x>', ''), /<img src=x>/);
});
