/*
 * image-cropper — modal de recorte de imagem, sem dependências.
 *
 * A imagem aparece inteira e fixa; um quadrado de seleção (arrastar para mover, cantos/slider para redimensionar)
 * marca o que será salvo. O recorte sai como Blob quadrado (PNG por padrão), pronto para enviar.
 * Usado no emoji customizado do Neko (deploy/neko/nekoemoji.js) e pensado para a foto de perfil do cinemanso.
 *
 *   import ImageCropper from '.../shared/image-cropper/image-cropper.js';   // ESM (Vite)
 *   // ou <script type="module" src="image-cropper.js"> → window.ImageCropper (como no Neko)
 *   // + image-cropper.css na página
 *
 *   ImageCropper.open({
 *     title: 'Foto de perfil',
 *     outputSize: 256,                 // largura do PNG final, em px (com aspect 1, é o lado)
 *     aspect: 1,                       // largura/altura do recorte (ex.: 4.8 para um banner 1920×400); o padrão é quadrado
 *     shape: 'circle',                 // 'square' (padrão) | 'circle': só a máscara da seleção/preview; o arquivo é sempre quadrado
 *     previewSizes: [96, 40],          // px das miniaturas de preview
 *     mimeType: 'image/png',           // ou 'image/jpeg' / 'image/webp' (+ quality 0..1)
 *     labels: { save: 'Salvar', cancel: 'Cancelar' },   // qualquer texto da interface (ver DEFAULT_LABELS)
 *     nameField: { label: 'Nome', prefix: ':', suffix: ':', validate: (v) => ({ ok: true, text: '', tone: '' }) },  // opcional
 *     note: 'Todo mundo na sala pode usar',
 *     file: someFile,                  // opcional: já abre com essa imagem (senão abre o seletor de arquivos)
 *     onSave: async ({ blob, name, size }) => { await upload(blob); },  // se lançar Error, a mensagem aparece e o modal fica aberto
 *     onClose: () => {},
 *   });
 *
 * Aparência: variáveis CSS --ic-* (ver image-cropper.css), para casar com o tema de cada app.
 */

const DEFAULT_LABELS = {
  title: 'Crop image',
  close: 'Close',
  drop: 'Drop an image here, paste it, or',
  choose: 'choose a file',
  preview: 'Preview',
  size: 'Size',
  largest: 'Largest',
  replace: 'Replace image',
  cancel: 'Cancel',
  save: 'Save',
  saving: 'Saving…',
  notImage: 'That file is not an image',
  unreadable: 'Could not read that image',
  saveFailed: 'Could not save',
  selection: (w, outW, h, outH) => `Selection ${w} × ${h} px, saved as ${outW} × ${outH}`,
  fileInfo: (w, h, kb) => `${w} × ${h} · ${kb} KB`,
};

const SVG_CLOSE =
  '<svg viewBox="0 0 256 256" aria-hidden="true"><path d="M200 56L56 200M200 200L56 56" fill="none" stroke="currentColor" stroke-width="28" stroke-linecap="round"/></svg>';

const TEMPLATE = `
  <div class="ic-panel">
    <div class="ic-head">
      <div class="ic-title"></div>
      <button type="button" class="ic-x">${SVG_CLOSE}</button>
    </div>
    <div class="ic-body">
      <div class="ic-stage">
        <label class="ic-drop"><span><span data-t="drop"></span> <u data-t="choose"></u></span><input type="file" hidden></label>
        <div class="ic-view" hidden>
          <div class="ic-clip">
            <img class="ic-pic" alt="" draggable="false">
            <div class="ic-sel"><i></i><i></i><i></i><i></i></div>
          </div>
          <div class="ic-frame"><b data-c="0"></b><b data-c="1"></b><b data-c="2"></b><b data-c="3"></b></div>
        </div>
      </div>
      <div class="ic-side">
        <div class="ic-label" data-t="preview"></div>
        <div class="ic-previews"></div>
        <div class="ic-file"></div>
        <div class="ic-muted ic-dims"></div>
        <div class="ic-muted ic-selinfo"></div>
        <button type="button" class="ic-btn ic-replace"></button>
      </div>
    </div>
    <div class="ic-row">
      <label class="ic-label ic-size-label" data-t="size"></label>
      <input type="range" class="ic-size" step="1" disabled>
      <button type="button" class="ic-btn ic-largest" disabled></button>
    </div>
    <div class="ic-row ic-name-row" hidden>
      <label class="ic-label ic-name-label"></label>
      <div class="ic-name"><span class="ic-pre"></span><input type="text" autocomplete="off" spellcheck="false"><span class="ic-suf"></span></div>
    </div>
    <div class="ic-hint"></div>
    <div class="ic-foot">
      <div class="ic-muted ic-note"></div>
      <div class="ic-actions">
        <button type="button" class="ic-btn ic-cancel"></button>
        <button type="button" class="ic-save" disabled></button>
      </div>
    </div>
  </div>`;

let uid = 0;

export function open(options = {}) {
  const o = {
    title: undefined,
    outputSize: 256,
    aspect: 1,
    shape: 'square',
    previewSizes: [48],
    mimeType: 'image/png',
    quality: 0.92,
    accept: 'image/*',
    stage: { width: 320, height: 240 },
    minSelection: 24,
    nameField: null,
    note: '',
    file: null,
    onSave: null,
    onClose: null,
    ...options,
    labels: { ...DEFAULT_LABELS, ...(options.labels || {}) },
  };
  const L = o.labels;
  const { width: STAGE_W, height: STAGE_H } = o.stage;
  const ASPECT = o.aspect > 0 ? o.aspect : 1; // largura / altura; `s` (a seleção) é sempre a LARGURA, a altura é s / ASPECT
  const OUT_W = o.outputSize;
  const OUT_H = Math.round(o.outputSize / ASPECT);
  const id = ++uid;
  const previouslyFocused = document.activeElement;
  const root = document.createElement('div');

  root.className = `ic-root ic-${o.shape}`;
  root.setAttribute('role', 'dialog');
  root.setAttribute('aria-modal', 'true');
  root.innerHTML = TEMPLATE;

  const q = (selector) => root.querySelector(selector);
  const state = { image: null, url: '', scale: 1, w: 0, h: 0, x: 0, y: 0, s: 0, saving: false };

  // textos (via textContent: nada do que vem de `labels` é interpretado como HTML)
  root.querySelectorAll('[data-t]').forEach((node) => (node.textContent = L[node.dataset.t]));
  q('.ic-title').textContent = o.title ?? L.title;
  root.setAttribute('aria-label', o.title ?? L.title);
  q('.ic-x').setAttribute('aria-label', L.close);
  q('.ic-replace').textContent = L.replace;
  q('.ic-largest').textContent = L.largest;
  q('.ic-cancel').textContent = L.cancel;
  q('.ic-save').textContent = L.save;
  q('.ic-note').textContent = o.note;
  q('.ic-stage').style.cssText = `width:${STAGE_W}px;height:${STAGE_H}px`;

  const fileInput = q('input[type=file]');
  const drop = q('.ic-drop');
  const view = q('.ic-view');
  const pic = q('.ic-pic');
  const sel = q('.ic-sel');
  const frame = q('.ic-frame');
  const slider = q('.ic-size');
  const largest = q('.ic-largest');
  const hint = q('.ic-hint');
  const save = q('.ic-save');
  const note = q('.ic-note');
  const noteDefault = o.note;
  const sizeId = `ic-size-${id}`;
  const nameId = `ic-name-${id}`;

  fileInput.accept = o.accept;
  slider.id = sizeId;
  slider.min = String(o.minSelection);
  slider.max = String(STAGE_H);
  q('.ic-size-label').setAttribute('for', sizeId);

  const previews = o.previewSizes.map((size) => {
    const canvas = document.createElement('canvas');

    const height = Math.round(size / ASPECT);

    canvas.width = size * 2;
    canvas.height = height * 2;
    canvas.style.cssText = `width:${size}px;height:${height}px`;
    q('.ic-previews').append(canvas);

    return canvas;
  });

  // ---- campo de nome (opcional)
  const field = o.nameField;
  const nameInput = q('.ic-name input');

  if (field) {
    q('.ic-name-row').hidden = false;
    q('.ic-name-label').textContent = field.label ?? '';
    q('.ic-name-label').setAttribute('for', nameId);
    q('.ic-pre').textContent = field.prefix ?? '';
    q('.ic-suf').textContent = field.suffix ?? '';
    nameInput.id = nameId;
    nameInput.maxLength = field.maxLength ?? 64;
    nameInput.value = field.initial ?? '';
  }

  const nameState = () => (field && field.validate ? field.validate(nameInput.value) : { ok: true, text: '', tone: '' });

  function updateForm() {
    const status = nameState();

    hint.textContent = field ? status.text || '' : '';
    hint.dataset.tone = status.tone || '';
    q('.ic-name').dataset.bad = String(status.tone === 'bad');
    save.disabled = state.saving || !(status.ok && state.image);
  }

  // ---- seleção
  function draw() {
    const { x, y, s, scale, image } = state;
    const h = s / ASPECT;
    const box = `left:${x}px;top:${y}px;width:${s}px;height:${h}px`;

    sel.style.cssText = box;
    frame.style.cssText = box;
    slider.value = String(Math.round(s));
    q('.ic-selinfo').textContent = L.selection(Math.round(s / scale), OUT_W, Math.round(h / scale), OUT_H);

    for (const canvas of previews) {
      const ctx = canvas.getContext('2d');

      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(image, x / scale, y / scale, s / scale, h / scale, 0, 0, canvas.width, canvas.height);
    }
  }

  // maior largura de seleção que ainda cabe na imagem
  const maxSel = () => Math.min(state.w, state.h * ASPECT);

  // mantém a seleção dentro da imagem
  function clampSel(x, y, s) {
    const size = Math.max(o.minSelection, Math.min(maxSel(), s));

    return { x: Math.max(0, Math.min(state.w - size, x)), y: Math.max(0, Math.min(state.h - size / ASPECT, y)), s: size };
  }

  function setSel(next) {
    Object.assign(state, clampSel(next.x, next.y, next.s));
    draw();
  }

  function suggestName(file) {
    return file.name
      .replace(/\.[^.]+$/, '')
      .toLowerCase()
      .replace(/[^a-z0-9_]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, field?.maxLength ?? 64);
  }

  function loadFile(file) {
    if (!file || !file.type.startsWith('image/')) {
      note.textContent = L.notImage;

      return;
    }

    const url = URL.createObjectURL(file);
    const image = new Image();

    image.onload = () => {
      if (state.url) URL.revokeObjectURL(state.url);

      state.image = image;
      state.url = url;
      state.scale = Math.min(STAGE_W / image.naturalWidth, STAGE_H / image.naturalHeight);
      state.w = Math.round(image.naturalWidth * state.scale);
      state.h = Math.round(image.naturalHeight * state.scale);

      const min = maxSel();
      const size = Math.round(min * 0.8);

      view.style.cssText = `width:${state.w}px;height:${state.h}px`;
      pic.src = url;
      pic.style.cssText = `width:${state.w}px;height:${state.h}px`;
      slider.max = String(min);
      slider.disabled = false;
      largest.disabled = false;
      drop.hidden = true;
      view.hidden = false;
      q('.ic-file').textContent = file.name;
      q('.ic-dims').textContent = L.fileInfo(image.naturalWidth, image.naturalHeight, Math.max(1, Math.round(file.size / 1024)));
      note.textContent = noteDefault;

      if (field && !nameInput.value) nameInput.value = field.fromFile ? field.fromFile(file.name) : suggestName(file);

      setSel({ x: (state.w - size) / 2, y: (state.h - size / ASPECT) / 2, s: size });
      updateForm();
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      note.textContent = L.unreadable;
    };
    image.src = url;
  }

  // mover: arrastar dentro do quadrado; redimensionar: arrastar os cantos (o canto oposto fica parado)
  function drag(target, onMove) {
    target.addEventListener('pointerdown', (event) => {
      if (!state.image || event.button !== 0) return;

      event.preventDefault();
      event.stopPropagation();
      target.setPointerCapture(event.pointerId);

      const box = view.getBoundingClientRect();
      const k = state.w / box.width;
      const origin = { x: state.x, y: state.y, s: state.s, px: event.clientX, py: event.clientY };
      const move = (e) => onMove(origin, (e.clientX - box.left) * k, (e.clientY - box.top) * k, k, e);
      const stop = () => {
        target.removeEventListener('pointermove', move);
        target.removeEventListener('pointerup', stop);
        target.removeEventListener('pointercancel', stop);
      };

      target.addEventListener('pointermove', move);
      target.addEventListener('pointerup', stop);
      target.addEventListener('pointercancel', stop);
    });
  }

  drag(sel, (origin, px, py, k, e) => setSel({ x: origin.x + (e.clientX - origin.px) * k, y: origin.y + (e.clientY - origin.py) * k, s: origin.s }));

  root.querySelectorAll('.ic-frame b').forEach((handle) => {
    const corner = Number(handle.dataset.c);

    drag(handle, (origin, px, py) => {
      const left = corner === 0 || corner === 2;
      const top = corner === 0 || corner === 1;
      const ax = left ? origin.x + origin.s : origin.x;
      const ay = top ? origin.y + origin.s / ASPECT : origin.y;
      const max = Math.min(left ? ax : state.w - ax, (top ? ay : state.h - ay) * ASPECT);
      const size = Math.max(o.minSelection, Math.min(max, Math.max(left ? ax - px : px - ax, (top ? ay - py : py - ay) * ASPECT)));

      setSel({ x: left ? ax - size : ax, y: top ? ay - size / ASPECT : ay, s: size });
    });
  });

  slider.addEventListener('input', () => {
    const size = Number(slider.value);

    setSel({ x: state.x + state.s / 2 - size / 2, y: state.y + state.s / ASPECT / 2 - size / ASPECT / 2, s: size });
  });
  largest.addEventListener('click', () => {
    const size = maxSel();

    setSel({ x: (state.w - size) / 2, y: (state.h - size / ASPECT) / 2, s: size });
  });
  nameInput.addEventListener('input', () => {
    if (field?.normalize) nameInput.value = field.normalize(nameInput.value);

    updateForm();
  });

  fileInput.addEventListener('change', () => loadFile(fileInput.files[0]));
  q('.ic-replace').addEventListener('click', () => fileInput.click());
  root.addEventListener('dragover', (event) => event.preventDefault());
  root.addEventListener('drop', (event) => {
    event.preventDefault();
    loadFile(event.dataTransfer?.files?.[0]);
  });

  const onPaste = (event) => {
    const file = [...(event.clipboardData?.files || [])].find((f) => f.type.startsWith('image/'));

    if (file) loadFile(file);
  };
  const onKey = (event) => {
    if (event.key === 'Escape') close();
  };

  function close() {
    document.removeEventListener('paste', onPaste);
    document.removeEventListener('keydown', onKey, true);

    if (state.url) URL.revokeObjectURL(state.url);

    root.remove();
    previouslyFocused?.focus?.();
    o.onClose?.();
  }

  q('.ic-x').addEventListener('click', close);
  q('.ic-cancel').addEventListener('click', close);

  save.addEventListener('click', async () => {
    if (state.saving || !state.image || !nameState().ok) return;

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const { x, y, s, scale, image } = state;

    canvas.width = OUT_W;
    canvas.height = OUT_H;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(image, x / scale, y / scale, s / scale, s / ASPECT / scale, 0, 0, OUT_W, OUT_H);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, o.mimeType, o.quality));

    state.saving = true;
    note.textContent = L.saving;
    updateForm();

    try {
      await o.onSave?.({ blob, name: field ? nameInput.value : undefined, size: o.outputSize, width: OUT_W, height: OUT_H });
    } catch (error) {
      state.saving = false;
      note.textContent = error?.message || L.saveFailed;
      updateForm();

      return;
    }

    state.saving = false;
    close();
  });

  document.addEventListener('paste', onPaste);
  document.addEventListener('keydown', onKey, true);
  document.body.append(root);
  updateForm();

  if (o.file) loadFile(o.file);
  else fileInput.click(); // abre o seletor de arquivos já no clique que chamou open()

  return { close, root };
}

const ImageCropper = { open };

if (typeof window !== 'undefined') window.ImageCropper ??= ImageCropper;

export default ImageCropper;
