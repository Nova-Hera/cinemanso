/*
 * Fullscreen do Neko no estilo Hyperbeam (estilos em nekofs.css).
 *
 * O botão de tela cheia do Neko só põe o <div> do vídeo em fullscreen, então a barra de avatares
 * e o chat (que ficam fora dele) somem. Aqui o fullscreen passa a ser do <html> inteiro e os
 * componentes originais do Neko são reposicionados por CSS; este script só cuida de:
 *   - redirecionar o requestFullscreen do player para o <html>;
 *   - mostrar a barra quando o mouse chega embaixo;
 *   - a seta que abre/fecha o chat (e o ponto vermelho de mensagem nova);
 *   - garantir que o painel lateral (onde o chat é montado) exista enquanto está em tela cheia.
 */
(() => {
  const root = document.documentElement;
  const BAR_HEIGHT = 74;

  const nativeRequest = Element.prototype.requestFullscreen;
  if (nativeRequest) {
    Element.prototype.requestFullscreen = function (...args) {
      // o container que o Neko manda para tela cheia é o pai direto de [data-testid=player]
      const isPlayer = this.querySelector(':scope > [data-testid="player"]');

      return nativeRequest.apply(isPlayer ? root : this, args);
    };
  }

  const inIframe = window.self !== window.top;
  const $ = (selector) => document.querySelector(selector);

  // Dentro do iframe do cinemanso não dá para confiar só em document.fullscreenElement / :fullscreen,
  // então o estado vira a classe .nk-fs no <html>; no iframe, tela cheia também é o iframe ocupar a tela.
  const fullscreenNow = () =>
    !!document.fullscreenElement ||
    (inIframe && innerWidth >= screen.width - 2 && innerHeight >= screen.height - 2);
  const inFullscreen = () => root.classList.contains('nk-fs');

  let openedSide = false;
  let quietUntil = 0;
  let unread = 0;
  let observer = null;

  const sideToggle = () => $('[data-testid="side-toggle"]');

  // O chat só é montado com o painel lateral aberto; se o usuário o mantém fechado, abre por baixo
  // dos panos (o CSS esconde) e desfaz ao sair da tela cheia.
  function ensureSide() {
    const toggle = sideToggle();

    if (toggle && toggle.getAttribute('aria-expanded') === 'false') {
      openedSide = true;
      quietUntil = Date.now() + 1000; // o histórico monta de uma vez: não conta como "novas"
      toggle.click();
    }
  }

  function selectChatTab() {
    const tabs = [...document.querySelectorAll('[data-testid="tabs"] [role="tab"]')];
    const tab = tabs.find((t) => /chat/i.test(t.textContent)) || tabs[0];

    if (tab && tab.getAttribute('data-state') !== 'active') {
      tab.focus(); // Radix Tabs ativa no foco
      tab.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, button: 0 }));
    }
  }

  function updateBadge() {
    const badge = $('.nk-badge');

    if (badge) badge.hidden = unread === 0;
  }

  function setChat(open) {
    root.classList.toggle('nk-chat', open);

    if (!open) return;

    ensureSide();
    selectChatTab();
    unread = 0;
    updateBadge();
    root.classList.add('nk-bar');
    setTimeout(() => $('[data-testid="chat-input"]')?.focus(), 150);
  }

  function ensureArrow() {
    const bar = $('[data-testid="room-bar"]');

    if (!bar || bar.querySelector('.nk-arrow')) return;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'nk-arrow';
    button.setAttribute('aria-label', 'Chat');
    button.innerHTML =
      '<svg class="nk-l" viewBox="0 0 256 256" aria-hidden="true"><path d="M165.66,202.34a8,8,0,0,1-11.32,11.32l-80-80a8,8,0,0,1,0-11.32l80-80a8,8,0,0,1,11.32,11.32L91.31,128Z"/></svg>' +
      '<svg class="nk-r" viewBox="0 0 256 256" aria-hidden="true"><path d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z"/></svg>' +
      '<span class="nk-badge" hidden>•</span>';
    button.addEventListener('click', () => setChat(!root.classList.contains('nk-chat')));
    bar.appendChild(button);
    updateBadge();
  }

  function onMouseMove(event) {
    if (!inFullscreen()) return;

    ensureArrow();

    const barVisible = root.classList.contains('nk-bar');
    const near = event.clientY > innerHeight - (barVisible ? BAR_HEIGHT + 24 : 40);
    // a barra só aparece com o mouse embaixo; uma vez aberta, não some enquanto o mouse está nela, no
    // chat ou no seletor de emoji (o chat sobe/desce junto com a barra e o seletor ficaria desalinhado)
    const keep =
      barVisible &&
      (event.target.closest?.('[data-testid="room-bar"], aside[data-testid="side"], [data-radix-popper-content-wrapper]') ||
        $('[data-testid="emoji-picker"]'));

    root.classList.toggle('nk-bar', near || !!keep);
  }

  function onMutations(mutations) {
    if (root.classList.contains('nk-chat') || Date.now() < quietUntil) return;

    for (const mutation of mutations) {
      for (const node of mutation.addedNodes) {
        if (node.nodeType === 1 && node.matches('[data-testid="chat-message"]')) {
          unread += 1;
        }
      }
    }

    updateBadge();
  }

  function enter() {
    ensureSide();
    ensureArrow();
    observer = new MutationObserver(onMutations);
    observer.observe(document.body, { childList: true, subtree: true });
  }

  function leave() {
    observer?.disconnect();
    observer = null;
    root.classList.remove('nk-bar', 'nk-chat');
    unread = 0;
    updateBadge();

    const toggle = sideToggle();

    if (openedSide && toggle && toggle.getAttribute('aria-expanded') === 'true') toggle.click();

    openedSide = false;
  }

  function sync() {
    const on = fullscreenNow();

    if (on === inFullscreen()) return;

    root.classList.toggle('nk-fs', on);
    on ? enter() : leave();
  }

  document.addEventListener('fullscreenchange', sync);
  window.addEventListener('resize', sync);
  window.addEventListener('mousemove', onMouseMove, true);
  root.addEventListener('mouseleave', () => {
    if (inFullscreen() && !$('[data-testid="emoji-picker"]')) root.classList.remove('nk-bar');
  });
})();
