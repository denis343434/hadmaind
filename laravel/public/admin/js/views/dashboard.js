import { add, h } from '../../../js/dom.js';
import { todayISO } from '../../../js/format.js';
import { adminApi } from '../api.js';
import { extendNotice } from '../mode.js';
import { formatDayTitle, money, pageHeader, table } from '../ui.js';
import { bookingColumns, createBooking, editBooking, loadCatalog } from './bookings.js';

const stat = (value, label, href, accent) =>
  h(
    href ? 'a' : 'div',
    { class: `card stat ${accent && value > 0 ? 'stat--accent' : ''}`, href },
    h('div', { class: 'stat__value' }, value),
    h('div', { class: 'stat__label' }, label),
  );

export async function dashboardView(root, ctx) {
  const { dash, leads, orders } = await ctx.refreshBadges();
  const today = todayISO();

  add(root, pageHeader('Сводка', h('span', { class: 'muted' }, formatDayTitle(today))));

  const stats = h('div', { class: 'stats' });
  if (dash) {
    const guests = dash.today.reduce((sum, b) => sum + b.guests, 0);
    add(stats, 
      stat(dash.today.length, 'броней сегодня', `#/bookings?from=${today}&to=${today}`),
      stat(guests, 'гостей ожидается сегодня'),
      stat(dash.pendingCount, 'ждут подтверждения', '#/bookings?status=pending', true),
    );
  }
  if (leads) {
    add(stats, stat(leads.new ?? 0, 'новых заявок', '#/leads?status=new', true), stat(leads.in_progress ?? 0, 'заявок в работе', '#/leads?status=in_progress'));
  }
  if (orders) {
    add(stats,
      stat(orders.newCount ?? 0, 'новых заказов', '#/orders?status=new', true),
      stat(orders.todayOrders ?? 0, 'заказов сегодня', '#/orders'),
      stat(money(orders.todayRevenue ?? 0), 'продажи сегодня'),
    );
  }
  if (stats.children.length) add(root, stats);

  if (dash) {
    const catalog = await loadCatalog();
    const refresh = () => ctx.reload();
    add(root, 
      h(
        'div',
        { class: 'card' },
        h(
          'div',
          { class: 'card__head' },
          h('h2', { class: 'card__title' }, 'Сегодня'),
          h('button', { class: 'btn btn--small btn--ghost', type: 'button', onclick: async () => (await createBooking(catalog, today)) && refresh() }, 'Новая бронь'),
        ),
        table({
          columns: bookingColumns(refresh),
          rows: dash.today,
          onRowClick: async (b) => (await editBooking(b.id, catalog)) && refresh(),
          empty: 'На сегодня броней нет',
        }),
      ),
    );
  }

  add(root, extendNotice('Онлайн-оплата, рассылки клиентам, программа лояльности, связь с кассой?'));

  if (!dash && !leads && !orders) {
    add(root, 
      h(
        'div',
        { class: 'card card--pad' },
        h('p', null, 'Здесь появятся заказы и заявки, когда их модули включены.'),
        h('p', { class: 'muted' }, 'Товары — в разделе «Товары», контакты и доставка — в «Настройках».'),
      ),
    );
  }
}
