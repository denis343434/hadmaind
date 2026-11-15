/**
 * «Сайт» — всё про сайт в одном разделе простого режима:
 * тексты, контакты, часы работы, праздники и (если есть платные услуги) цены.
 * Собирается из тех же частей, что и разделы полного режима.
 */
import { add, h } from '../../../js/dom.js';
import { adminApi } from '../api.js';
import { extendNotice } from '../mode.js';
import { pageHeader } from '../ui.js';
import { exceptionsTab, hoursTab, servicesTab } from './catalog.js';
import { pagesView } from './pages.js';
import { shopSettingsForm, siteForm } from './settings.js';

export async function siteView(root, ctx) {
  const booking = ctx.has('booking')
    ? await Promise.all([adminApi('/booking/services'), adminApi('/booking/resources')]).then(([services, resources]) => ({ services, resources }))
    : null;

  const tabs = [
    ctx.has('pages') && { key: 'texts', label: 'Тексты', render: (box) => pagesView(box, { ...ctx, embedded: true }) },
    { key: 'contacts', label: 'Контакты', render: siteForm },
    ctx.has('shop') && { key: 'delivery', label: 'Доставка', render: shopSettingsForm },
    booking && { key: 'hours', label: 'Часы работы', render: (box) => hoursTab(box, booking) },
    booking && { key: 'holidays', label: 'Праздники', render: (box) => exceptionsTab(box, booking) },
    // Для кафе бронь стола бесплатная — вкладка «Цены» там не нужна. Без меню (развлечения) цены есть всегда.
    booking && (!ctx.has('menu') || booking.services.some((s) => s.price > 0)) &&{ key: 'prices', label: 'Цены', render: (box) => servicesTab(box, booking) },
  ].filter(Boolean);
  const tab = tabs.find((t) => t.key === ctx.query.tab) ?? tabs[0];

  const box = h('div');
  add(
    root,
    pageHeader('Сайт', h('a', { class: 'btn btn--ghost', href: '../', target: '_blank', rel: 'noopener' }, 'Открыть сайт ↗')),
    h(
      'div',
      { class: 'chips' },
      tabs.map((t) => h('a', { class: 'chip', href: `#/site?tab=${t.key}`, 'aria-pressed': String(t === tab) }, t.label)),
    ),
    box,
  );
  await tab.render(box);

  // У «Текстов» свой блок про новые разделы сайта
  if (tab.key !== 'texts') {
    add(root, extendNotice('Свой домен, уведомления о бронях в Telegram, ещё один язык сайта, онлайн-предоплата?'));
  }
}
