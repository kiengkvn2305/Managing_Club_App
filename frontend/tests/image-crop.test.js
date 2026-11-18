import test from 'node:test';
import assert from 'node:assert/strict';
import { cropRectangle } from '../src/image-crop.js';

test('crop rectangle centers and clamps square and wide images', () => {
  assert.deepEqual(cropRectangle(1200, 800, 1), { x: 200, y: 0, width: 800, height: 800 });
  assert.deepEqual(cropRectangle(800, 1200, 1), { x: 0, y: 200, width: 800, height: 800 });
  const wide = cropRectangle(800, 1200, 16 / 9, 2, 100, -100);
  assert.equal(wide.width, 400); assert.equal(wide.height, 225);
  assert.equal(wide.x, 400); assert.equal(wide.y, 0);
  assert.throws(() => cropRectangle(0, 100, 1));
});
