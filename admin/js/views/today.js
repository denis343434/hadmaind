/**
 * «Сегодня» — стартовый экран простого режима: только то, что требует действия.
 * Брони, ждущие подтверждения, брони на сегодня, новые заявки. Без статистики.
 */
import { add, h } from '../../../js/dom.js';
import { todayISO } from '../../../js/format.js';
import { adminApi } from '../api.js';
import { extendNotice } from '../mode.js';
import { formatDateTime, formatDayTitle, localParts, money, pageHeader, phoneLink, table } from '../ui.js';
import { bookingColumns, createBooking, editBooking, loadCatalog } from './bookings.js';
import { leadStatusCell, openLead } from './leads.js';
import { openOrder, orderStatusCell } from './orders.js';

const card = (title, link, ...content) =>
  h(
    'div',
    { class: 'card' },
    h('div', { class: 'card__head' }, h('h2', { class: 'card__title' }, title), link),
    content,
  );

export async function todayView(root, ctx) {
  const today = todayISO();
  const hasBooking = ctx.has('booking');
  const hasLeads = ctx.has('leads');
  const hasShop = ctx.has('shop');

  const [pending, todays, leads, shopOrders, catalog] = await Promise.all([
    hasBooking ? adminApi('/booking/bookings', { query: { status: 'pending', from: today, limit: 50 } }) : null,
    hasBooking ? adminApi('/booking/bookings', { query: { from: today, to: today, limit: 100 } }) : null,
    hasLeads ? adminApi('/leads', { query: { status: 'new', limit: 10 } }) : null,
    hasShop ? adminApi('/shop/orders', { query: { status: 'new', limit: 10 } }) : null,
    hasBooking ? loadCatalog() : null,
  ]);
  ctx.refreshBadges();
  const reload = () => ctx.reload();

  add(
    root,
    pageHeader(
      'Сегодня',
      h('span', { class: 'muted' }, formatDayTitle(today)),
      hasBooking && h('button', { class: 'btn', type: 'button', onclick: async () => (await createBooking(catalog, today)) && reload() }, 'Новая бронь'),
    ),
  );

  const openBooking = async (b) => (await editBooking(b.id, catalog)) && reload();

  // 1. Требуют ответа — сверху и только если есть
  if (pending?.items.length) {
    const rows = pending.items.map((b) => ({ ...b, _day: localParts(b.startAt).date }));
    add(
      root,
      card(
        `Ждут подтверждения · ${pending.total}`,
        pending.total > pending.items.length && h('a', { class: 'link', href: '#/bookings?status=pending' }, 'Все'),
        table({
          columns: [
            {
              title: 'Когда',
              class: 'nowrap',
              render: (b) => (b._day === today ? `сегодня, ${localParts(b.startAt).time}` : formatDateTime(b.startAt)),
            },
            ...bookingColumns(reload).slice(1),
          ],
          rows,
          onRowClick: openBooking,
        }),
      ),
    );
  }

  if (leads?.items.length) {
    add(
      root,
      card(
        `Новые заявки · ${leads.total}`,
        h('a', { class: 'link', href: '#/leads' }, 'Все заявки'),
        table({
          columns: [
            { title: 'Когда', class: 'nowrap', render: (l) => formatDateTime(l.createdAt) },
            { title: 'Клиент', render: (l) => h('div', null, l.name, h('div', { class: 'sub' }, phoneLink(l.phone) ?? l.email)) },
            { title: 'Сообщение', class: 'clip', render: (l) => l.message ?? '—' },
            { title: '', class: 'actions', render: (l) => leadStatusCell(l, reload) },
          ],
          rows: leads.items,
          onRowClick: async (l) => (await openLead(l)) && reload(),
        }),
      ),
    );
  }

  if (shopOrders?.items.length) {
    add(
      root,
      card(
        `Новые заказы · ${shopOrders.total}`,
        h('a', { class: 'link', href: '#/orders' }, 'Все заказы'),
        table({
          columns: [
            { title: 'Когда', class: 'nowrap', render: (o) => formatDateTime(o.createdAt) },
            { title: 'Клиент', render: (o) => h('div', null, o.customerName, h('div', { class: 'sub' }, phoneLink(o.customerPhone) ?? o.customerEmail)) },
            { title: 'Состав', class: 'clip', render: (o) => o.itemsLabel ?? '—' },
            { title: 'Сумма', class: 'num', render: (o) => money(o.total) },
            { title: '', class: 'actions', render: (o) => orderStatusCell(o, reload) },
          ],
          rows: shopOrders.items,
          onRowClick: async (o) => (await openOrder(o)) && reload(),
        }),
      ),
    );
  }

  // 2. Расписание на сегодня
  if (todays) {
    const active = todays.items.filter((b) => b.status === 'pending' || b.status === 'confirmed');
    add(
      root,
      card(
        'Брони на сегодня',
        h('a', { class: 'link', href: '#/bookings' }, 'Все брони'),
        table({ columns: bookingColumns(reload), rows: active, onRowClick: openBooking, empty: 'На сегодня броней нет' }),
      ),
    );
  }

  if (!pending?.items.length && !leads?.items.length && !shopOrders?.items.length && (hasBooking || hasLeads || hasShop)) {
    add(root, h('p', { class: 'notice link--spaced' }, 'Всё обработано: новых броней, заказов и заявок нет.'));
  }

  add(root, extendNotice('Онлайн-оплата, рассылки клиентам, программа лояльности, связь с кассой?'));
}
