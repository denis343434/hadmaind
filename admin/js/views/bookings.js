import { add, fill, h, toast } from '../../../js/dom.js';
import { STATUS, addDays, todayISO } from '../../../js/format.js';
import { adminApi } from '../api.js';
import { isFull } from '../mode.js';
import {
  attempt,
  deleteButton,
  formatDateTime,
  formatDayTitle,
  formDialog,
  localParts,
  money,
  pageHeader,
  phoneLink,
  statusBadge,
  table,
} from '../ui.js';

const STATUS_OPTIONS = Object.entries(STATUS);
const ACTIVE = ['pending', 'confirmed'];

/** Справочники для форм броней — грузим один раз на открытие раздела. */
export async function loadCatalog() {
  const [services, resources] = await Promise.all([adminApi('/booking/services'), adminApi('/booking/resources')]);
  return { services, resources };
}

/** Сменить статус брони одной кнопкой. */
async function setStatus(b, status, onChange) {
  const messages = { confirmed: `Бронь ${b.code} подтверждена`, cancelled: `Бронь ${b.code} отменена` };
  if (status === 'cancelled' && !confirm(`Отменить бронь ${b.code} (${b.customerName})?`)) return false;
  const ok = await attempt(() => adminApi(`/booking/bookings/${b.id}`, { method: 'PATCH', body: { status } }), messages[status]);
  if (ok) onChange();
  return Boolean(ok);
}

/** Кнопки действий: у новой брони — «Подтвердить» и «Отменить». */
export function bookingActions(b, onChange) {
  if (b.status !== 'pending') return null;
  const btn = (label, status, cls) =>
    h(
      'button',
      {
        class: `btn btn--small ${cls}`,
        type: 'button',
        onclick: async (e) => {
          e.target.disabled = true;
          if (!(await setStatus(b, status, onChange))) e.target.disabled = false;
        },
      },
      label,
    );
  return [btn('Подтвердить', 'confirmed', ''), btn('Отменить', 'cancelled', 'btn--ghost')];
}

/** Колонки таблицы броней — общие для раздела «Брони» и экрана «Сегодня». */
export function bookingColumns(onChange) {
  return [
    {
      title: 'Время',
      class: 'nowrap',
      render: (b) => `${localParts(b.startAt).time}–${localParts(b.endAt).time}`,
    },
    {
      title: 'Клиент',
      render: (b) => h('div', null, b.customerName, h('div', { class: 'sub' }, phoneLink(b.customerPhone))),
    },
    { title: 'Гостей', class: 'num', render: (b) => b.guests },
    // В простом режиме услуга обычно одна — показываем только место
    { title: 'Где', render: (b) => (isFull() ? h('div', null, b.resource.name, h('div', { class: 'sub' }, b.service.name)) : b.resource.name) },
    { title: 'Сумма', class: 'num', render: (b) => money(b.totalPrice), advanced: true },
    { title: 'Статус', render: (b) => statusBadge(b.status, STATUS) },
    { title: '', class: 'actions', render: (b) => bookingActions(b, onChange) },
  ];
}

/** Карточка брони: просмотр и редактирование. Возвращает true, если что-то изменилось. */
export async function editBooking(id, catalog) {
  const b = await adminApi(`/booking/bookings/${id}`);
  const start = localParts(b.startAt);
  const active = ACTIVE.includes(b.status);
  const fields = [
    // В простом режиме статус меняется кнопками «Подтвердить» / «Отменить бронь»
    { name: 'status', label: 'Статус', type: 'select', options: STATUS_OPTIONS, advanced: true },
    { name: 'date', label: 'Дата', type: 'date', half: true, required: true },
    { name: 'time', label: 'Время', type: 'time', half: true, required: true },
    {
      name: 'resourceId',
      label: 'Где',
      type: 'select',
      numeric: true,
      half: true,
      options: catalog.resources.map((r) => [r.id, r.isActive ? r.name : `${r.name} (неактивно)`]),
    },
    { name: 'guests', label: 'Гостей', type: 'number', min: 1, half: true, required: true },
    { name: 'totalPrice', label: 'Сумма, ₽', type: 'money', half: true, advanced: true },
    { name: 'customerName', label: 'Имя', half: true, required: true },
    { name: 'customerPhone', label: 'Телефон', type: 'tel', half: true, required: true },
    { name: 'customerEmail', label: 'Email', type: 'email', nullable: true, advanced: true },
    { name: 'comment', label: 'Комментарий клиента', type: 'textarea', rows: 2, nullable: true },
    { name: 'adminNote', label: 'Ваша заметка', type: 'textarea', rows: 2, nullable: true, hint: 'Клиент её не видит' },
    // Скрыто — бэк по умолчанию и так пишет клиенту
    { name: 'notifyCustomer', label: 'Написать клиенту на email при подтверждении или отмене', type: 'checkbox', default: true, advanced: true },
  ];

  // Простой режим: статус — кнопками слева от «Сохранить»
  const statusButtons = (close) => [
    b.status === 'pending' &&
      h('button', { class: 'btn', type: 'button', onclick: async () => (await setStatus(b, 'confirmed', () => {})) && close('status') }, 'Подтвердить'),
    active &&
      h('button', { class: 'btn btn--danger', type: 'button', onclick: async () => (await setStatus(b, 'cancelled', () => {})) && close('status') }, 'Отменить бронь'),
  ];

  const result = await formDialog({
    title: `Бронь ${b.code}`,
    intro: h('span', null, statusBadge(b.status, STATUS), ` · создана ${formatDateTime(b.createdAt)}`),
    fields,
    values: { ...b, date: start.date, time: start.time, notifyCustomer: true },
    onSubmit: (data) => {
      const { date, time, ...body } = data;
      // Время и место отправляем, только если их поменяли: иначе бэк не перепроверяет занятость
      if (date !== start.date || time !== start.time) body.startAt = `${date}T${time}`;
      if (body.resourceId === b.resourceId) delete body.resourceId;
      return adminApi(`/booking/bookings/${b.id}`, { method: 'PATCH', body });
    },
    // Заказчику удаление не нужно: есть «Отменить бронь», а история броней сохраняется
    extra: isFull()
      ? deleteButton('Удалить', 'Удалить бронь безвозвратно? Обычно достаточно статуса «Отменена».', () =>
          adminApi(`/booking/bookings/${b.id}`, { method: 'DELETE' }),
        )
      : statusButtons,
  });
  if (result === 'deleted') toast('Бронь удалена');
  else if (result && result !== 'status') toast('Сохранено');
  return Boolean(result);
}

/** Бронь от администратора: звонок, гость на месте. Проверяется только пересечение с другими бронями. */
export async function createBooking(catalog, date) {
  const active = catalog.services.filter((s) => s.isActive);
  if (active.length === 0) {
    toast('Сначала добавьте услугу в разделе «Услуги и график»', { error: true });
    return false;
  }
  const result = await formDialog({
    title: 'Новая бронь',
    intro: 'Например, гость позвонил. Можно и вне часов работы — но одно место дважды на одно время не займёшь.',
    fields: [
      // Одна услуга (типично для кафе) — не спрашиваем
      { name: 'serviceId', label: 'Услуга', type: 'select', numeric: true, options: active.map((s) => [s.id, s.name]), advanced: active.length === 1 },
      { name: 'resourceId', label: 'Где', type: 'select', numeric: true, options: catalog.resources.filter((r) => r.isActive).map((r) => [r.id, r.name]) },
      { name: 'date', label: 'Дата', type: 'date', half: true, required: true },
      { name: 'time', label: 'Время начала', type: 'time', half: true, required: true },
      { name: 'guests', label: 'Гостей', type: 'number', min: 1, half: true, required: true },
      { name: 'status', label: 'Статус', type: 'select', half: true, advanced: true, options: [['confirmed', STATUS.confirmed], ['pending', STATUS.pending]] },
      { name: 'customerName', label: 'Имя', half: true, required: true },
      { name: 'customerPhone', label: 'Телефон', type: 'tel', half: true, required: true },
      { name: 'customerEmail', label: 'Email', type: 'email', advanced: true },
      { name: 'comment', label: 'Комментарий', type: 'textarea', rows: 2 },
      { name: 'adminNote', label: 'Заметка администратора', type: 'textarea', rows: 2, advanced: true },
    ],
    values: { date, guests: 2 },
    submitLabel: 'Создать',
    onSubmit: ({ date: d, time, ...body }) =>
      adminApi('/booking/bookings', { method: 'POST', body: { serviceId: active[0].id, ...body, startAt: `${d}T${time}` } }),
  });
  if (result) toast(`Бронь ${result.code} создана`);
  return Boolean(result);
}

export async function bookingsView(root, ctx) {
  const today = todayISO();
  const state = {
    // По умолчанию — все предстоящие: бронь на дату через месяц не должна теряться
    from: ctx.query.from ?? today,
    to: ctx.query.to ?? '',
    preset:
      !ctx.query.from && !ctx.query.to
        ? ctx.query.status === 'pending'
          ? 'pending'
          : 'upcoming'
        : ctx.query.from === today && ctx.query.to === today
          ? 'today'
          : null,
    status: ctx.query.status ?? '',
    q: '',
    page: 1,
    items: [],
    total: 0,
  };
  const catalog = await loadCatalog();

  const listBox = h('div', { class: 'card' });
  const hint = h('p', { class: 'hint', hidden: true }, 'Поиск идёт по всем датам');
  const moreBtn = h('button', { class: 'btn btn--ghost', type: 'button', hidden: true, onclick: () => load(true) }, 'Показать ещё');

  const reload = () => {
    load();
    ctx.refreshBadges();
  };

  async function load(append = false) {
    state.page = append ? state.page + 1 : 1;
    // Ищут обычно конкретную бронь («клиент назвал код») — по всем датам, а не внутри периода
    const searching = Boolean(state.q);
    hint.hidden = !searching;
    const data = await attempt(() =>
      adminApi('/booking/bookings', {
        query: {
          from: searching ? '' : state.from,
          to: searching ? '' : state.to,
          order: state.preset === 'past' ? 'desc' : 'asc',
          status: state.status,
          q: state.q,
          page: state.page,
          limit: 100,
        },
      }),
    );
    if (!data) return;
    state.items = append ? [...state.items, ...data.items] : data.items;
    state.total = data.total;
    render();
  }

  function render() {
    // Группируем по дням, чтобы расписание читалось как журнал
    const rows = [];
    let day = null;
    for (const b of state.items) {
      const d = localParts(b.startAt).date;
      if (d !== day) {
        day = d;
        rows.push({ group: formatDayTitle(d) + (d === today ? ' · сегодня' : '') });
      }
      rows.push(b);
    }
    fill(listBox, 
      table({
        columns: bookingColumns(reload),
        rows,
        rowClass: (b) => !ACTIVE.includes(b.status) && 'is-muted',
        onRowClick: async (b) => (await editBooking(b.id, catalog)) && reload(),
        empty: state.q
          ? 'Ничего не найдено'
          : { upcoming: 'Предстоящих броней нет', pending: 'Все брони подтверждены' }[state.preset] ?? 'Броней за этот период нет',
      }),
    );
    moreBtn.hidden = state.items.length >= state.total;
    for (const chip of chips.children) chip.setAttribute('aria-pressed', String(chip.dataset.preset === state.preset));
  }

  // Фильтры: даты можно оставить пустыми — тогда период открыт с этой стороны
  const dateInput = (key) => {
    const input = h('input', { type: 'date', value: state[key] });
    input.addEventListener('change', () => {
      state[key] = input.value;
      state.preset = null;
      load();
    });
    return input;
  };
  const fromInput = dateInput('from');
  const toInput = dateInput('to');

  // [подпись, период, статус] — статус задаёт только «Ждут подтверждения»
  const PRESETS = {
    upcoming: ['Предстоящие', () => [today, '']],
    pending: ['Ждут подтверждения', () => [today, ''], 'pending'],
    today: ['Сегодня', () => [today, today]],
    tomorrow: ['Завтра', () => [addDays(today, 1), addDays(today, 1)]],
    week: ['7 дней', () => [today, addDays(today, 6)]],
    past: ['Прошедшие', () => ['', addDays(today, -1)]],
  };
  const chips = h(
    'div',
    { class: 'chips' },
    Object.entries(PRESETS).map(([key, [label, range, status]]) =>
      h(
        'button',
        {
          class: 'chip',
          type: 'button',
          'data-preset': key,
          onclick: () => {
            [state.from, state.to] = range();
            fromInput.value = state.from;
            toInput.value = state.to;
            state.preset = key;
            // В простом режиме статус задают только кнопки; в полном — ещё и список «Статус»
            state.status = status ?? (isFull() ? statusSelect.value : '');
            if (status) statusSelect.value = status;
            load();
          },
        },
        label,
      ),
    ),
  );

  const statusSelect = h('select', null, h('option', { value: '' }, 'Все статусы'), STATUS_OPTIONS.map(([v, l]) => h('option', { value: v }, l)));
  statusSelect.value = state.status;
  statusSelect.addEventListener('change', () => {
    state.status = statusSelect.value;
    load();
  });

  let searchTimer;
  const search = h('input', { type: 'search', placeholder: 'Код, имя или телефон' });
  search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      state.q = search.value.trim();
      load();
    }, 300);
  });

  add(root, 
    pageHeader(
      'Брони',
      h('button', { class: 'btn', type: 'button', onclick: async () => (await createBooking(catalog, state.from || today)) && reload() }, 'Новая бронь'),
    ),
    h('div', { class: 'toolbar' }, chips),
    h(
      'div',
      { class: 'toolbar' },
      // Произвольный период — в полном режиме; заказчику хватает кнопок «Сегодня», «Завтра»…
      isFull() && h('div', { class: 'field' }, h('label', null, 'С'), fromInput),
      isFull() && h('div', { class: 'field' }, h('label', null, 'По'), toInput),
      isFull() && h('div', { class: 'field' }, h('label', null, 'Статус'), statusSelect),
      h('div', { class: 'field' }, h('label', null, 'Поиск'), search, hint),
    ),
    listBox,
    h('div', { class: 'savebar' }, moreBtn),
  );
  await load();
}
