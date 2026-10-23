/*
 * Emoji customizado no Neko (estilos em nekoemoji.css; imagens guardadas pelo sidecar em emoji-server/).
 *
 * O seletor de emoji e o chat do Neko são componentes React fechados, então tudo aqui é feito por cima da DOM:
 *   - uma seção "Custom" no topo do seletor (admins ganham o "+" que abre o editor de recorte, e o ✕ para remover);
 *   - `:nome:` nas mensagens do chat vira a imagem;
 *   - clicar num emoji insere `:nome:` no campo do chat (igual aos emotes embutidos).
 * Quem pode subir/remover é decidido pelo servidor (whoami do Neko); o botão só aparece para admin.
 */
(() => {
  const BASE = '/custom-emoji';
  const NAME_RE = /^[a-z0-9_]{2,32}$/;
  const OUT_SIZE = 128;

  let available = false; // o sidecar respondeu à lista
  let emojis = []; // [{ name, v }]
  let builtin = null; // Set com os nomes dos emotes do próprio Neko
  let lastRefresh = 0;
  let generation = 0; // sobe a cada mudança da lista, para reprocessar as mensagens do chat

  const $ = (selector, root = document) => root.querySelector(selector);
  const isAdmin = () => !!$('[data-testid="admin-badge"]');
  const imageUrl = (emoji) => `${BASE}/img/${emoji.name}.png?v=${emoji.v}`;
  const el = (tag, className, attrs = {}) => {
    const node = document.createElement(tag);

    if (className) node.className = className;

    for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value);

    return node;
  };

  /* ------------------------------------------------------------------ dados */

  function authHeaders() {
    const headers = {};

    try {
      const token = localStorage.getItem('neko_session');

      if (token) headers.Authorization = `Bearer ${token}`;
    } catch {}

    return headers;
  }

  async function refresh() {
    lastRefresh = Date.now();

    try {
      const response = await fetch(`${BASE}/api/list`, { cache: 'no-store' });

      if (!response.ok) return;

      const data = await response.json();

      if (!Array.isArray(data)) return;

      available = true;
      emojis = data.filter((e) => e && NAME_RE.test(e.name));
      generation += 1;
      schedule();
    } catch {}
  }

  async function loadBuiltin() {
    if (builtin) return builtin;

    try {
      const data = await (await fetch('emoji.json')).json();

      builtin = new Set(data.list || []);
    } catch {
      builtin = new Set();
    }

    return builtin;
  }

  /* ---------------------------------------------------------- seletor de emoji */

  function insertEmoji(name) {
    const input = $('[data-testid="chat-input"]');

    if (!input) return;

    const token = `:${name}:`;
    const start = input.selectionStart ?? input.value.length;
    const end = input.selectionEnd ?? start;
    const value = (input.value.slice(0, start) + token + input.value.slice(end)).slice(0, 512);

    // o React só enxerga a mudança pelo setter nativo + evento `input`
    Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value').set.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    closePicker();
    input.focus();

    const caret = Math.min(start + token.length, value.length);

    input.setSelectionRange(caret, caret);
  }

  function closePicker() {
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
  }

  async function removeEmoji(name) {
    if (!window.confirm(`Remove :${name}: for everyone?`)) return;

    try {
      await fetch(`${BASE}/api/emoji/${name}`, { method: 'DELETE', headers: authHeaders() });
    } catch {}

    refresh();
  }

  const PLUS =
    '<svg viewBox="0 0 256 256" aria-hidden="true"><path d="M128 40v176M40 128h176" fill="none" stroke="currentColor" stroke-width="20" stroke-linecap="round"/></svg>';
  const CLOSE_X =
    '<svg viewBox="0 0 256 256" aria-hidden="true"><path d="M200 56L56 200M200 200L56 56" fill="none" stroke="currentColor" stroke-width="28" stroke-linecap="round"/></svg>';

  function buildSection(admin) {
    const fragment = document.createDocumentFragment();
    const title = el('span', 'nk-ce-title');
    const grid = el('ul', 'nk-ce-grid');

    title.textContent = 'Custom';

    if (admin) {
      const item = el('li', 'nk-ce-cell');
      const add = el('button', 'nk-ce-add', { type: 'button', 'aria-label': 'Add custom emoji', title: 'Add custom emoji' });

      add.innerHTML = PLUS;
      add.addEventListener('click', openEditor);
      item.append(add);
      grid.append(item);
    }

    const search = $('[data-testid="emoji-search"]');

    for (const emoji of emojis) {
      const item = el('li', 'nk-ce-cell');
      const button = el('button', 'nk-ce-btn', { type: 'button', 'aria-label': `:${emoji.name}:`, title: `:${emoji.name}:` });
      const image = el('img', '', { src: imageUrl(emoji), alt: `:${emoji.name}:`, width: '24', height: '24', draggable: 'false' });

      button.append(image);
      button.addEventListener('click', () => insertEmoji(emoji.name));
      // o Neko mostra o nome do emoji sob o cursor no placeholder da busca
      button.addEventListener('mouseenter', () => search && (search.placeholder = `:${emoji.name}:`));
      item.append(button);

      if (admin) {
        const remove = el('button', 'nk-ce-del', { type: 'button', 'aria-label': `Remove :${emoji.name}:`, title: 'Remove' });

        remove.innerHTML = CLOSE_X;
        remove.addEventListener('click', (event) => {
          event.stopPropagation();
          removeEmoji(emoji.name);
        });
        item.append(remove);
      }

      grid.append(item);
    }

    fragment.append(title, grid);

    return fragment;
  }

  function renderPicker() {
    // sem busca ativa a lista é um ul.flex-col com uma seção por categoria; com busca vira um ul plano
    const list = $('[data-testid="emoji-list"] > ul.flex-col');

    if (!list) return;

    const admin = isAdmin();
    let section = $(':scope > .nk-ce', list);

    if (!available || (!emojis.length && !admin)) {
      section?.remove();

      return;
    }

    const signature = JSON.stringify([admin, emojis]);

    if (section && section.dataset.sig === signature) return;

    if (!section) {
      section = el('li', 'nk-ce');
      list.prepend(section);
    }

    section.dataset.sig = signature;
    section.replaceChildren(buildSection(admin));
  }

  /* --------------------------------------------------------------------- chat */

  const TOKEN_RE = /:([a-z0-9_]{2,32}):/g;

  function emojifyMessage(message) {
    const byName = new Map(emojis.map((e) => [e.name, e]));
    const walker = document.createTreeWalker(message, NodeFilter.SHOW_TEXT);
    const nodes = [];

    while (walker.nextNode()) {
      if (walker.currentNode.nodeValue.includes(':')) nodes.push(walker.currentNode);
    }

    for (const node of nodes) {
      const text = node.nodeValue;
      let fragment = null;
      let last = 0;
      let match;

      TOKEN_RE.lastIndex = 0;

      while ((match = TOKEN_RE.exec(text))) {
        const emoji = byName.get(match[1]);

        if (!emoji) {
          maybeNew(match[1]);
          continue;
        }

        fragment ??= document.createDocumentFragment();
        fragment.append(text.slice(last, match.index));
        fragment.append(el('img', 'nk-ce-img', { src: imageUrl(emoji), alt: match[0], title: match[0], width: '22', height: '22' }));
        last = match.index + match[0].length;
      }

      if (fragment) {
        fragment.append(text.slice(last));
        node.replaceWith(fragment);
      }
    }
  }

  // `:nome:` desconhecido (e que não é emote do Neko) pode ser um emoji que um admin acabou de criar
  function maybeNew(name) {
    if (!/[a-z]/.test(name) || !builtin || builtin.has(name)) return;
    if (Date.now() - lastRefresh > 10000) refresh();
  }

  function renderChat() {
    if (!available || !emojis.length) return;

    for (const message of document.querySelectorAll('[data-testid="chat-message"]')) {
      if (message.dataset.nkGen === String(generation)) continue;

      message.dataset.nkGen = String(generation);
      emojifyMessage(message);
    }
  }

  /* ------------------------------------------------------------------- editor */

  // O recorte da imagem é o componente compartilhado shared/image-cropper (window.ImageCropper); aqui só
  // entram as regras do nome e o envio do PNG para o sidecar.
  function openEditor() {
    closePicker();
    loadBuiltin();

    if (!window.ImageCropper) {
      window.alert('The image editor could not be loaded');

      return;
    }

    function validate(name) {
      if (!name) return { ok: false, text: 'a–z, 0–9 and _ · 2–32 characters', tone: '' };
      if (!NAME_RE.test(name)) return { ok: false, text: 'Use only a–z, 0–9 and _ (2–32 characters)', tone: 'bad' };
      if (builtin?.has(name)) return { ok: false, text: `“${name}” is already a built-in emoji`, tone: 'bad' };
      if (emojis.some((e) => e.name === name)) return { ok: true, text: `“${name}” already exists — saving replaces it`, tone: 'warn' };

      return { ok: true, text: 'a–z, 0–9 and _ · 2–32 characters', tone: '' };
    }

    window.ImageCropper.open({
      title: 'Add custom emoji',
      outputSize: OUT_SIZE,
      previewSizes: [22, 32, 48],
      note: 'Everyone in the room can use it',
      labels: { save: 'Save emoji' },
      nameField: {
        label: 'Name',
        prefix: ':',
        suffix: ':',
        maxLength: 32,
        normalize: (value) => value.toLowerCase().replace(/\s+/g, '_'),
        validate,
      },
      async onSave({ blob, name }) {
        if (validate(name).tone === 'warn' && !window.confirm(`Replace :${name}: for everyone?`)) throw new Error('Not saved');

        let response;

        try {
          response = await fetch(`${BASE}/api/emoji/${name}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'image/png', ...authHeaders() },
            body: blob,
          });
        } catch {
          throw new Error('Could not reach the server');
        }

        if (!response.ok) {
          const reasons = { 401: 'You are not logged in', 403: 'Only admins can add emoji' };
          const body = await response.json().catch(() => ({}));

          throw new Error(reasons[response.status] || body.error || 'Could not save the emoji');
        }

        refresh();
      },
    });
  }

  /* ------------------------------------------------- efeitos (emotes que sobem no vídeo) */

  // Os "emojis de efeito" do Neko (menu do ícone de figurinha na barra) são enviados como broadcast "emote" e os
  // outros clientes só aceitam os nomes da lista fixa do Neko. Então o emoji custom viaja como `nkce_<nome>`, e quem
  // tem este script escuta o broadcast e põe o efeito na tela do jeito que o Neko faz. Para falar com o cliente do Neko
  // (client/app) pega-se o contexto do React pela fibra do #neko; se isso mudar numa versão futura, o menu simplesmente
  // fica sem os emojis custom.
  const EFFECT = 'nkce_';
  let neko = null;
  let hooked = false;
  let cssGeneration = -1;

  function nekoContext() {
    if (neko) return neko;

    const node = $('#neko');
    const key = node && Object.keys(node).find((k) => k.startsWith('__reactFiber$'));
    let fiber = key ? node[key] : null;

    while (fiber) {
      const value = fiber.memoizedProps?.value;

      if (value && value.client && value.app && typeof value.client.sendBroadcast === 'function' && value.client.events) {
        neko = value;

        return neko;
      }

      fiber = fiber.return;
    }

    return null;
  }

  function showEffect(type) {
    const { app } = nekoContext() || {};

    if (!app || app.getState().settings?.ignore_emotes || document.visibilityState === 'hidden') return;

    const id = `nk${Math.random().toString(36).slice(2)}`;

    app.setState((state) => ({ emotes: { ...state.emotes, [id]: type } }));
  }

  function sendEffect(name) {
    const ctx = nekoContext();

    if (!ctx) return;

    try {
      ctx.client.sendBroadcast('emote', EFFECT + name);
    } catch {
      return;
    }

    showEffect(EFFECT + name);
  }

  function hookEffects() {
    const ctx = hooked ? null : nekoContext();

    if (!ctx) return;

    hooked = true;
    ctx.client.events.on('receive.broadcast', (id, subject, body) => {
      if (subject !== 'emote' || typeof body !== 'string' || !body.startsWith(EFFECT)) return;

      const name = body.slice(EFFECT.length);

      if (!NAME_RE.test(name) || ctx.app.getState().ignored?.[id]) return;
      if (!emojis.some((e) => e.name === name) && Date.now() - lastRefresh > 3000) refresh(); // emoji novo de outro admin

      showEffect(body);
    });
  }

  // `.emote.nkce_<nome>` com a imagem: é o que o componente de efeito do Neko desenha
  function renderEffectCss() {
    if (cssGeneration === generation) return;

    cssGeneration = generation;

    let style = document.getElementById('nk-ce-effects');

    if (!style) {
      style = el('style', '', { id: 'nk-ce-effects' });
      document.head.append(style);
    }

    style.textContent = emojis.map((e) => `.emote.${EFFECT}${e.name}{background-image:url("${imageUrl(e)}")}`).join('\n');
  }

  function renderEffectMenu() {
    const menu = $('[data-testid="emotes-menu"]');

    if (!menu) return;

    let box = $(':scope > .nk-ce-fx', menu);

    if (!emojis.length || !nekoContext()) {
      box?.remove();

      return;
    }

    const signature = JSON.stringify(emojis);

    if (box && box.dataset.sig === signature) return;

    // os itens copiam a classe de um item do próprio menu (mesmo visual); o `display: contents` deixa-os fluir na grade dele
    const template = $('[role="menuitem"]:not(.nk-ce-item)', menu);

    box?.remove();
    box = el('div', 'nk-ce-fx');
    box.dataset.sig = signature;

    for (const emoji of emojis) {
      const item = el('div', `${template?.className || 'relative cursor-pointer rounded-[3px] p-[5px]'} nk-ce-item`, {
        role: 'menuitem',
        tabindex: '-1',
        title: `:${emoji.name}:`,
        'aria-label': `:${emoji.name}:`,
      });

      item.append(el('div', `emote ${EFFECT}${emoji.name}`));
      item.addEventListener('click', () => {
        sendEffect(emoji.name);
        closePicker(); // fecha o menu, como o onSelect dos itens do Neko
      });
      box.append(item);
    }

    if (template) box.append(el('div', 'nk-ce-sep'));

    menu.prepend(box);
  }

  /* ---------------------------------------------------------------- orquestra */

  let queued = false;
  let pickerWasOpen = false;

  function tick() {
    queued = false;

    const pickerOpen = !!$('[data-testid="emoji-picker"]');

    if (pickerOpen && !pickerWasOpen && Date.now() - lastRefresh > 3000) refresh(); // pega emoji criado por outro admin

    pickerWasOpen = pickerOpen;
    renderPicker();
    renderChat();
    hookEffects();
    renderEffectCss();
    renderEffectMenu();
  }

  function schedule() {
    if (queued) return;

    queued = true;
    setTimeout(tick, 16); // não rAF: não roda com a aba em segundo plano
  }

  new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      for (const node of mutation.addedNodes) {
        if (node.nodeType === 1) return schedule();
      }
    }
  }).observe(document.documentElement, { childList: true, subtree: true });

  loadBuiltin().then(refresh);
})();
