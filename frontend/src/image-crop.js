export function cropRectangle(width, height, aspect, zoom = 1, x = 0, y = 0) {
  if (!(width > 0 && height > 0 && aspect > 0)) throw new Error('Kích thước ảnh không hợp lệ.');
  const scale = Math.max(1, Math.min(3, Number(zoom) || 1));
  const cropWidth = Math.min(width, height * aspect) / scale;
  const cropHeight = cropWidth / aspect;
  const position = (v) => (Math.max(-100, Math.min(100, Number(v) || 0)) + 100) / 200;
  return { x: (width - cropWidth) * position(x), y: (height - cropHeight) * position(y), width: cropWidth, height: cropHeight };
}

export function setupImageCropper(container, input, { background = false } = {}) {
  const canvas = container.querySelector('canvas'), context = canvas.getContext('2d');
  const zoom = container.querySelector('[data-crop-zoom]'), horizontal = container.querySelector('[data-crop-x]'), vertical = container.querySelector('[data-crop-y]');
  const controls = container.querySelector('[data-crop-controls]'), message = container.querySelector('[data-crop-message]');
  const aspect = background ? 16 / 9 : 1;
  canvas.width = background ? 640 : 400; canvas.height = background ? 360 : 400;
  let image = null, url = '', generation = 0, pending = null, drag = null;
  function draw() {
    if (!image) return;
    const rect = cropRectangle(image.naturalWidth, image.naturalHeight, aspect, zoom.value, horizontal.value, vertical.value);
    context.clearRect(0, 0, canvas.width, canvas.height);
    if (background) { context.fillStyle = '#ffffff'; context.fillRect(0, 0, canvas.width, canvas.height); }
    context.drawImage(image, rect.x, rect.y, rect.width, rect.height, 0, 0, canvas.width, canvas.height);
  }
  function reset() { image = null; controls.hidden = true; canvas.hidden = true; context.clearRect(0, 0, canvas.width, canvas.height); }
  input.addEventListener('change', () => {
    generation++; const current = generation;
    if (url) URL.revokeObjectURL(url); url = '';
    reset(); const file = input.files[0];
    if (!file) { message.textContent = 'Chọn một ảnh để điều chỉnh khung hình.'; pending = null; return; }
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { message.textContent = 'Chọn ảnh JPG, PNG hoặc WEBP tối đa 5 MB.'; pending = null; return; }
    url = URL.createObjectURL(file); const candidate = new Image();
    message.textContent = 'Đang mở ảnh…';
    pending = new Promise((resolve) => {
      candidate.onload = () => {
        if (current !== generation) { resolve(false); return; }
        if (!candidate.naturalWidth || !candidate.naturalHeight || candidate.naturalWidth > 6000 || candidate.naturalHeight > 6000) { message.textContent = 'Ảnh phải có kích thước không quá 6000 × 6000 pixel.'; resolve(false); return; }
        image = candidate; zoom.value = '1'; horizontal.value = '0'; vertical.value = '0';
        canvas.hidden = false; controls.hidden = false; message.textContent = 'Kéo ảnh hoặc dùng thanh điều chỉnh để chọn vùng hiển thị.'; draw(); resolve(true);
      };
      candidate.onerror = () => { if (current === generation) message.textContent = 'Không đọc được ảnh. Hãy chọn ảnh khác.'; resolve(false); };
      candidate.src = url;
    });
  });
  [zoom, horizontal, vertical].forEach((control) => control.addEventListener('input', draw));
  canvas.addEventListener('pointerdown', (event) => { if (!image) return; drag = { x: event.clientX, y: event.clientY, h: Number(horizontal.value), v: Number(vertical.value) }; canvas.setPointerCapture(event.pointerId); });
  canvas.addEventListener('pointermove', (event) => {
    if (!drag || !image) return;
    const rect = cropRectangle(image.naturalWidth, image.naturalHeight, aspect, zoom.value, horizontal.value, vertical.value), bounds = canvas.getBoundingClientRect();
    const dx = image.naturalWidth - rect.width, dy = image.naturalHeight - rect.height;
    horizontal.value = String(Math.max(-100, Math.min(100, drag.h - (dx > 0 ? (event.clientX - drag.x) / bounds.width * rect.width / dx * 200 : 0))));
    vertical.value = String(Math.max(-100, Math.min(100, drag.v - (dy > 0 ? (event.clientY - drag.y) / bounds.height * rect.height / dy * 200 : 0))));
    draw();
  });
  for (const event of ['pointerup', 'pointercancel', 'lostpointercapture']) canvas.addEventListener(event, () => { drag = null; });
  return {
    async exportFile() {
      if (pending) await pending;
      if (!image) throw new Error(message.textContent || 'Hãy chọn ảnh hợp lệ.');
      const output = document.createElement('canvas'); output.width = background ? 1600 : 512; output.height = background ? 900 : 512;
      const target = output.getContext('2d'), rect = cropRectangle(image.naturalWidth, image.naturalHeight, aspect, zoom.value, horizontal.value, vertical.value);
      if (background) { target.fillStyle = '#ffffff'; target.fillRect(0, 0, output.width, output.height); }
      target.drawImage(image, rect.x, rect.y, rect.width, rect.height, 0, 0, output.width, output.height);
      const mime = background ? 'image/jpeg' : 'image/png';
      const blob = await new Promise((resolve) => output.toBlob(resolve, mime, 0.92));
      if (!blob) throw new Error('Không thể tạo ảnh đã cắt. Hãy chọn ảnh khác.');
      return new File([blob], background ? 'anh-nen-da-cat.jpg' : 'anh-dai-dien-da-cat.png', { type: mime });
    },
    destroy() { generation++; if (url) URL.revokeObjectURL(url); url = ''; image = null; },
  };
}
