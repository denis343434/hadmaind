/**
 * Режимы админки.
 *   simple — для заказчика: только ежедневные задачи (брони, заявки, меню, тексты, контакты).
 *   full   — всё, включая структуру сайта, услуги, SEO, удаление записей.
 *
 * Скрытое не удаляется: поля, разделы и кнопки с пометкой advanced просто не рисуются в простом режиме.
 * Разработчик включает полный режим в своём браузере: /admin?full=1 (выключить — ?full=0).
 */
import { h } from '../../js/dom.js';

const KEY = 'admin_full';
const config = window.SITE_CONFIG?.admin ?? {};

const param = new URLSearchParams(location.search).get('full');
if (param !== null) {
  try {
    if (param === '1') localStorage.setItem(KEY, '1');
    else localStorage.removeItem(KEY);
  } catch {
    /* без localStorage работает только mode из config.js */
  }
  // Убираем ?full из адреса, чтобы ссылка не «залипла» в закладках
  history.replaceState(null, '', location.pathname + location.hash);
}

function storedFull() {
  try {
    return localStorage.getItem(KEY) === '1';
  } catch {
    return false;
  }
}

export const isFull = () => config.mode === 'full' || storedFull();
export const isDevOverride = () => config.mode !== 'full' && storedFull();

/** Оставляет только то, что положено видеть в текущем режиме. */
export const visible = (items) => items.filter((i) => i && (!i.advanced || isFull()));

/** В простом режиме — значение, в полном — undefined (для кнопок вроде «Удалить»). */
export const advancedOnly = (value) => (isFull() ? value : undefined);

function contacts() {
  const d = config.developer ?? {};
  const links = [
    d.telegram && h('a', { href: d.telegram, target: '_blank', rel: 'noopener' }, 'Telegram'),
    d.phone && h('a', { href: `tel:${d.phone.replace(/[^\d+]/g, '')}` }, d.phone),
    d.email && h('a', { href: `mailto:${d.email}` }, d.email),
  ].filter(Boolean);
  return links.length ? h('span', { class: 'extend__links' }, links) : null;
}

/**
 * Блок «Хотите больше?». Показывается только в простом режиме —
 * в полном разработчик и так всё видит.
 *   extendNotice('Нужен новый раздел на сайте — галерея, отзывы, цены?')
 */
export function extendNotice(text) {
  if (isFull()) return null;
  const name = config.developer?.name || 'разработчиком';
  return h(
    'div',
    { class: 'extend' },
    h('div', { class: 'extend__text' }, h('strong', null, text), h('span', null, ` Хотите расширить функционал — свяжитесь с ${name}.`)),
    contacts(),
  );
}
