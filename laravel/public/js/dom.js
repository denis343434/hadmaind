/**
 * Мини-хелпер для сборки DOM. Весь текст вставляется как текст (не HTML),
 * поэтому контент из админки не может внедрить разметку или скрипты.
 *   h('a', { class: 'btn', href: '#' }, 'Текст', h('span', null, '!'))
 */
export function h(tag, props, ...children) {
  const el = document.createElement(tag);
  for (const [key, value] of Object.entries(props ?? {})) {
    if (value === undefined || value === null || value === false) continue;
    if (key === 'class') el.className = value;
    else if (key.startsWith('on') && typeof value === 'function') el.addEventListener(key.slice(2), value);
    else el.setAttribute(key, value === true ? '' : String(value));
  }
  for (const child of children.flat(Infinity)) {
    if (child === undefined || child === null || child === false) continue;
    el.append(child instanceof Node ? child : String(child));
  }
  return el;
}

/** append/replaceChildren, которые, как h(), пропускают false/null/undefined (удобно для `cond && node`). */
const present = (children) => children.flat(Infinity).filter((c) => c !== undefined && c !== null && c !== false);
export const add = (el, ...children) => el.append(...present(children));
export const fill = (el, ...children) => el.replaceChildren(...present(children));

export const $ = (sel, root = document) => root.querySelector(sel);
export const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

let toastTimer;
export function toast(message, { error = false } = {}) {
  let el = $('#toast');
  if (!el) {
    el = h('div', { class: 'toast', id: 'toast', role: 'status', 'aria-live': 'polite' });
    document.body.append(el);
  }
  // Открытый <dialog> лежит в top layer поверх всего — тост переносим внутрь, иначе он под затемнением
  const host = document.querySelector('dialog[open]') ?? document.body;
  if (el.parentElement !== host) host.append(el);
  el.textContent = message;
  el.classList.toggle('toast--error', error);
  el.classList.add('is-visible');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('is-visible'), 4000);
}

export function openDialog(name) {
  const dialog = document.getElementById(`${name}-dialog`);
  if (dialog && !dialog.open) dialog.showModal();
  return dialog;
}

/**
 * [data-open="lead"] открывает #lead-dialog, [data-close] закрывает.
 * Клик по фону закрывает диалог, кроме помеченных data-sticky (формы, где жалко потерять ввод).
 */
export function initDialogs() {
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-open]');
    if (opener) openDialog(opener.dataset.open);
    const closer = e.target.closest('[data-close]');
    if (closer) closer.closest('dialog')?.close();

    const dialog = e.target;
    if (dialog instanceof HTMLDialogElement && !dialog.hasAttribute('data-sticky')) {
      // target === dialog и при клике по его внутреннему отступу — проверяем, что клик снаружи
      const r = dialog.getBoundingClientRect();
      const outside = e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom;
      if (outside) dialog.close();
    }
  });
}
