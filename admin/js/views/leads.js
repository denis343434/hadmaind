import { add, fill, h, toast } from '../../../js/dom.js';
import { adminApi } from '../api.js';
import { isFull } from '../mode.js';
import { attempt, deleteButton, formatDateTime, formDialog, pageHeader, phoneLink, statusBadge, table } from '../ui.js';

export const LEAD_STATUS = { new: 'Новая', in_progress: 'В работе', done: 'Обработана', spam: 'Спам' };
const LEAD_TYPE = { contact: 'Сообщение', callback: 'Обратный звонок', event: 'Мероприятие', custom: 'Другое' };
const META_LABELS = { page: 'Страница', utm_source: 'Источник', utm_medium: 'Канал', utm_campaign: 'Кампания', utm_content: 'Объявление', utm_term: 'Ключевое слово' };

function statusSelect(lead, onChange) {
  const select = h(
    'select',
    { class: 'select--inline', 'aria-label': 'Статус заявки' },
    Object.entries(LEAD_STATUS).map(([v, l]) => h('option', { value: v }, l)),
  );
  select.value = lead.status;
  select.addEventListener('change', async () => {
    const ok = await attempt(() => adminApi(`/leads/${lead.id}`, { method: 'PATCH', body: { status: select.value } }));
    if (ok) onChange();
    else select.value = lead.status;
  });
  return select;
}

const setStatus = (lead, status) => attempt(() => adminApi(`/leads/${lead.id}`, { method: 'PATCH', body: { status } }), status === 'done' ? 'Заявка обработана' : undefined);

/** Простой режим: у новой заявки — кнопка «Обработана», у остальных — просто статус. */
function statusCell(lead, onChange) {
  if (isFull()) return statusSelect(lead, onChange);
  if (lead.status !== 'new') return statusBadge(lead.status, LEAD_STATUS);
  return h(
    'button',
    {
      class: 'btn btn--small',
      type: 'button',
      onclick: async (e) => {
        e.target.disabled = true;
        if (await setStatus(lead, 'done')) onChange();
        else e.target.disabled = false;
      },
    },
    'Обработана',
  );
}

export { statusCell as leadStatusCell };

export async function openLead(lead) {
  const meta = Object.entries(lead.meta ?? {});
  const intro = h(
    'div',
    null,
    h('p', null, `${LEAD_TYPE[lead.type] ?? lead.type} · ${formatDateTime(lead.createdAt)}`),
    h(
      'dl',
      { class: 'summary' },
      [
        ['Имя', lead.name],
        ['Телефон', phoneLink(lead.phone)],
        ['Email', lead.email && h('a', { href: `mailto:${lead.email}` }, lead.email)],
        ...meta.map(([k, v]) => [META_LABELS[k] ?? k, String(v)]),
      ]
        .filter(([, v]) => v)
        .map(([k, v]) => h('div', { class: 'summary__row' }, h('dt', null, k), h('dd', null, v))),
    ),
    lead.message && h('p', { class: 'notice' }, lead.message),
  );

  // Простой режим: без выпадающего статуса — одна главная кнопка и «Это спам»
  const simpleSubmit = lead.status === 'new' ? 'Обработана' : 'Вернуть в новые';
  const result = await formDialog({
    title: `Заявка №${lead.id}`,
    intro,
    fields: [{ name: 'status', label: 'Статус', type: 'select', options: Object.entries(LEAD_STATUS), advanced: true }],
    values: lead,
    submitLabel: isFull() ? 'Сохранить' : simpleSubmit,
    onSubmit: (body) => {
      if (isFull()) return adminApi(`/leads/${lead.id}`, { method: 'PATCH', body });
      return adminApi(`/leads/${lead.id}`, { method: 'PATCH', body: { status: lead.status === 'new' ? 'done' : 'new' } });
    },
    // Вместо удаления у заказчика есть «Это спам»
    extra: isFull()
      ? deleteButton('Удалить', 'Удалить заявку?', () => adminApi(`/leads/${lead.id}`, { method: 'DELETE' }))
      : (close) =>
          lead.status !== 'spam' &&
          h('button', { class: 'btn btn--ghost', type: 'button', onclick: async () => (await setStatus(lead, 'spam')) && close('spam') }, 'Это спам'),
  });
  if (result === 'deleted') toast('Заявка удалена');
  return Boolean(result);
}

export async function leadsView(root, ctx) {
  // Заказчику по умолчанию показываем то, с чем надо работать — новые заявки
  const state = { status: ctx.query.status ?? (isFull() ? '' : 'new'), q: '', page: 1, items: [], total: 0 };
  const listBox = h('div', { class: 'card' });
  const tabs = h('div', { class: 'chips' });
  const moreBtn = h('button', { class: 'btn btn--ghost', type: 'button', hidden: true, onclick: () => load(true) }, 'Показать ещё');

  const reload = () => {
    load();
    renderTabs();
    ctx.refreshBadges();
  };

  async function renderTabs() {
    const stats = (await attempt(() => adminApi('/leads/stats'))) ?? {};
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
    if (isFull()) fill(tabs, tab('', 'Все'), ...Object.entries(LEAD_STATUS).map(([v, l]) => tab(v, l)));
    else fill(tabs, tab('new', 'Новые'), tab('', 'Все'));
  }

  async function load(append = false) {
    state.page = append ? state.page + 1 : 1;
    const data = await attempt(() => adminApi('/leads', { query: { status: state.status, q: state.q, page: state.page, limit: 50 } }));
    if (!data) return;
    state.items = append ? [...state.items, ...data.items] : data.items;
    state.total = data.total;
    fill(listBox, 
      table({
        columns: [
          { title: 'Когда', class: 'nowrap', render: (l) => formatDateTime(l.createdAt) },
          { title: 'Тип', class: 'nowrap', render: (l) => LEAD_TYPE[l.type] ?? l.type, advanced: true },
          {
            title: 'Клиент',
            render: (l) => h('div', null, l.name, h('div', { class: 'sub' }, phoneLink(l.phone) ?? l.email)),
          },
          { title: 'Сообщение', class: 'clip', render: (l) => l.message ?? '—' },
          { title: 'Статус', render: (l) => statusCell(l, reload) },
        ],
        rows: state.items,
        rowClass: (l) => (l.status === 'done' || l.status === 'spam') && 'is-muted',
        onRowClick: async (l) => (await openLead(l)) && reload(),
        empty: state.q ? 'Ничего не найдено' : state.status === 'new' ? 'Новых заявок нет' : state.status ? 'Ничего не найдено' : 'Заявок пока нет',
      }),
    );
    moreBtn.hidden = state.items.length >= state.total;
  }

  let timer;
  const search = h('input', { type: 'search', placeholder: 'Имя, телефон или email' });
  search.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      state.q = search.value.trim();
      load();
    }, 300);
  });

  add(root, 
    pageHeader('Заявки'),
    h('div', { class: 'toolbar' }, tabs, search),
    listBox,
    h('div', { class: 'savebar' }, moreBtn),
  );
  await Promise.all([renderTabs(), load()]);
}
