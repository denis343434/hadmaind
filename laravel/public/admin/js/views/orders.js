import { add, fill, h, toast } from '../../../js/dom.js';
import { adminApi } from '../api.js';
import { isFull } from '../mode.js';
import { attempt, deleteButton, formatDateTime, formDialog, money, pageHeader, phoneLink, statusBadge, table } from '../ui.js';

export const ORDER_STATUS = {
  new: 'Новый',
  confirmed: 'Подтверждён',
  shipped: 'Отправлен',
  done: 'Выполнен',
  cancelled: 'Отменён',
  spam: 'Спам',
};
const PAYMENT = { card: 'Картой онлайн', transfer: 'Переводом', cash: 'При получении' };
const DELIVERY = { delivery: 'Доставка', pickup: 'Самовывоз' };

const setStatus = (order, status, message) =>
  attempt(() => adminApi(`/shop/orders/${order.id}`, { method: 'PATCH', body: { status } }), message);

/** Простой режим: у нового заказа — одна кнопка «Обработан». */
export function orderStatusCell(order, reload) {
  if (isFull()) {
    const select = h(
      'select',
      { class: 'select--inline', 'aria-label': 'Статус заказа' },
      Object.entries(ORDER_STATUS).map(([value, label]) => h('option', { value }, label)),
    );
    select.value = order.status;
    select.addEventListener('change', async () => {
      const ok = await attempt(() => adminApi(`/shop/orders/${order.id}`, { method: 'PATCH', body: { status: select.value } }));
      if (ok) reload();
      else select.value = order.status;
    });
    return select;
  }
  if (order.status !== 'new') return statusBadge(order.status, ORDER_STATUS);
  return h(
    'button',
    {
      class: 'btn btn--small',
      type: 'button',
      onclick: async (event) => {
        event.target.disabled = true;
        if (await setStatus(order, 'done', 'Заказ обработан')) reload();
        else event.target.disabled = false;
      },
    },
    'Обработан',
  );
}

function orderIntro(order) {
  const items = (order.items ?? []).map((item) =>
    h(
      'div',
      { class: 'summary__row' },
      h('dt', null, `${item.name} × ${item.qty}`),
      h('dd', null, money(item.total)),
    ),
  );
  return h(
    'div',
    null,
    h('p', null, `${formatDateTime(order.createdAt)} · заказ №${order.code}`),
    h(
      'dl',
      { class: 'summary' },
      h('div', { class: 'summary__row' }, h('dt', null, 'Клиент'), h('dd', null, order.customerName)),
      h('div', { class: 'summary__row' }, h('dt', null, 'Телефон'), h('dd', null, phoneLink(order.customerPhone))),
      order.customerEmail && h('div', { class: 'summary__row' }, h('dt', null, 'Email'), h('dd', null, h('a', { href: `mailto:${order.customerEmail}` }, order.customerEmail))),
      h(
        'div',
        { class: 'summary__row' },
        h('dt', null, 'Получение'),
        h('dd', null, order.deliveryMethod === 'pickup'
          ? DELIVERY.pickup
          : `${DELIVERY.delivery}: ${[order.city, order.address].filter(Boolean).join(', ')}`),
      ),
      h('div', { class: 'summary__row' }, h('dt', null, 'Оплата'), h('dd', null, PAYMENT[order.payment] ?? order.payment)),
      ...items,
      h('div', { class: 'summary__row' }, h('dt', null, 'Товары'), h('dd', null, money(order.subtotal))),
      h('div', { class: 'summary__row' }, h('dt', null, 'Доставка'), h('dd', null, order.deliveryPrice ? money(order.deliveryPrice) : 'бесплатно')),
      h('div', { class: 'summary__row' }, h('dt', null, 'Итого'), h('dd', null, h('b', null, money(order.total)))),
    ),
    order.comment && h('p', { class: 'notice' }, order.comment),
    order.adminNote && h('p', { class: 'muted' }, `Заметка: ${order.adminNote}`),
  );
}

export async function openOrder(order) {
  const full = order.items ? order : await adminApi(`/shop/orders/${order.id}`);
  const simpleDone = full.status === 'new' || full.status === 'confirmed';

  const result = await formDialog({
    title: `Заказ №${full.code}`,
    intro: orderIntro(full),
    fields: [
      { name: 'status', label: 'Статус', type: 'select', options: Object.entries(ORDER_STATUS), advanced: true },
      { name: 'adminNote', label: 'Заметка для себя', type: 'textarea', rows: 2, nullable: true, advanced: true },
    ],
    values: full,
    submitLabel: isFull() ? 'Сохранить' : simpleDone ? 'Выполнен' : 'Вернуть в новые',
    onSubmit: (body) => {
      if (isFull()) return adminApi(`/shop/orders/${full.id}`, { method: 'PATCH', body });
      return adminApi(`/shop/orders/${full.id}`, { method: 'PATCH', body: { status: simpleDone ? 'done' : 'new' } });
    },
    extra: isFull()
      ? deleteButton('Удалить', `Удалить заказ №${full.code}?`, () => adminApi(`/shop/orders/${full.id}`, { method: 'DELETE' }))
      : (close) =>
          full.status !== 'cancelled' &&
          h(
            'button',
            {
              class: 'btn btn--ghost',
              type: 'button',
              onclick: async () => (await setStatus(full, 'cancelled', 'Заказ отменён')) && close('cancelled'),
            },
            'Отменить заказ',
          ),
  });
  if (result === 'deleted') toast('Заказ удалён');
  return Boolean(result);
}

export async function ordersView(root, ctx) {
  const state = { status: ctx.query.status ?? (isFull() ? '' : 'new'), q: '', page: 1, items: [], total: 0 };
  const listBox = h('div', { class: 'card' });
  const tabs = h('div', { class: 'chips' });
  const moreButton = h('button', { class: 'btn btn--ghost', type: 'button', hidden: true, onclick: () => load(true) }, 'Показать ещё');

  const reload = () => {
    load();
    renderTabs();
    ctx.refreshBadges();
  };

  async function renderTabs() {
    const stats = (await attempt(() => adminApi('/shop/orders/stats'))) ?? {};
    const tab = (value, label) =>
      h(
        'button',
        {
          class: 'chip',
          type: 'button',
          'aria-pressed': String(state.status === value),
          onclick: () => {
            state.status = value;
            renderTabs();
            load();
          },
        },
        label,
        value && stats[value] ? ` · ${stats[value]}` : '',
      );
    if (isFull()) fill(tabs, tab('', 'Все'), ...Object.entries(ORDER_STATUS).map(([value, label]) => tab(value, label)));
    else fill(tabs, tab('new', 'Новые'), tab('', 'Все'));
  }

  async function load(append = false) {
    state.page = append ? state.page + 1 : 1;
    const data = await attempt(() =>
      adminApi('/shop/orders', { query: { status: state.status, q: state.q, page: state.page, limit: 50 } }),
    );
    if (!data) return;
    state.items = append ? [...state.items, ...data.items] : data.items;
    state.total = data.total;
    fill(listBox,
      table({
        columns: [
          { title: 'Когда', class: 'nowrap', render: (o) => formatDateTime(o.createdAt) },
          {
            title: 'Клиент',
            render: (o) => h('div', null, o.customerName, h('div', { class: 'sub' }, phoneLink(o.customerPhone) ?? o.customerEmail)),
          },
          { title: 'Состав', class: 'clip', render: (o) => h('div', null, o.itemsLabel, h('div', { class: 'sub' }, `${o.itemsCount} шт.`)) },
          { title: 'Сумма', class: 'num', render: (o) => money(o.total) },
          { title: 'Статус', render: (o) => orderStatusCell(o, reload) },
        ],
        rows: state.items,
        rowClass: (o) => ['done', 'cancelled', 'spam'].includes(o.status) && 'is-muted',
        onRowClick: async (o) => (await openOrder(o)) && reload(),
        empty: state.q
          ? 'Ничего не найдено'
          : state.status === 'new'
            ? 'Новых заказов нет'
            : state.status
              ? 'Заказов с таким статусом нет'
              : 'Заказов пока нет',
      }),
    );
    moreButton.hidden = state.items.length >= state.total;
  }

  let timer;
  const search = h('input', { type: 'search', placeholder: 'Имя, телефон, email или номер заказа' });
  search.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      state.q = search.value.trim();
      load();
    }, 300);
  });

  add(root,
    pageHeader('Заказы'),
    h('div', { class: 'toolbar' }, tabs, search),
    listBox,
    h('div', { class: 'savebar' }, moreButton),
  );
  await Promise.all([renderTabs(), load()]);
}
