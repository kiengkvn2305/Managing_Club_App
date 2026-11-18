import test from 'node:test';
import assert from 'node:assert/strict';
import { passwordResetPage } from '../src/password-reset-page.js';

test('reset page escapes token and renders the correct form', () => {
  const html = passwordResetPage({ authMode: 'reset', resetToken: '<script>', resetEmail: 'person@example.test' });
  assert.match(html, /data-form="reset-password"/); assert.doesNotMatch(html, /<script>/); assert.match(html, /&lt;script&gt;/);
  assert.match(passwordResetPage({ authMode: 'forgot' }), /data-form="forgot-password"/);
});
