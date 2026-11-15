import { add, fill, h, toast } from '../../../js/dom.js';
import { formatDuration, todayISO } from '../../../js/format.js';
import { adminApi } from '../api.js';
import { advancedOnly, extendNotice, isFull, visible } from '../mode.js';
import { attempt, deleteButton, emptyState, formatDayTitle, formDialog, money, pageHeader, slugify, table } from '../ui.js';

// В простом режиме заказчик видит график, праздники и цены услуг; места и структура услуг — у разработчика
const TABS = [
  { key: 'hours', label: 'График работы' },
  { key: 'exceptions', label: 'Праздники и выходные' },
  { key: 'services', label: 'Услуги и цены' },
  { key: 'resources', label: 'Места', advanced: true },
];
const WEEKDAYS = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];

const withSlug = (data) => ({ ...data, slug: data.slug || slugify(data.name) });
/** При правке адрес меняем, только если поле было в форме (в простом режиме оно скрыто). */
const slugPatch = (d) => (d.slug === undefined ? d : withSlug(d));

// ─── Услуги ─────────────────────────────────────────────

export async function servicesTab(box, data) {
  const fields = [
    { name: 'name', label: 'Название', required: true, placeholder: 'Квест 60 минут' },
    { name: 'slug', label: 'Адрес (латиницей)', advanced: true, hint: 'Если пусто — создастся из названия' },
    { name: 'description', label: 'Описание', type: 'textarea', rows: 2, nullable: true },
    { name: 'durationMin', label: 'Длительность, мин', type: 'number', min: 5, half: true, required: true, advanced: true },
    { name: 'bufferMin', label: 'Перерыв после, мин', type: 'number', min: 0, half: true, advanced: true, hint: 'Уборка, подготовка' },
    { name: 'slotStepMin', label: 'Шаг записи, мин', type: 'number', min: 5, half: true, nullable: true, advanced: true, hint: 'Пусто — по умолчанию (30)' },
    { name: 'price', label: 'Цена, ₽', type: 'money', half: true },
    { name: 'pricePerGuest', label: 'Цена за каждого гостя', type: 'checkbox', advanced: true },
    { name: 'minGuests', label: 'Гостей от', type: 'number', min: 1, half: true, advanced: true },
    { name: 'maxGuests', label: 'Гостей до', type: 'number', min: 1, half: true, nullable: true, advanced: true },
    { name: 'imageUrl', label: 'Картинка', type: 'image', nullable: true },
    {
      name: 'resourceIds',
      label: 'Где проводится',
      type: 'multiselect',
      advanced: true,
      numeric: true,
      options: data.resources.map((r) => [r.id, r.name]),
      emptyOptions: 'Сначала добавьте места на вкладке «Места»',
      hint: 'Клиент сможет выбрать любое из отмеченных мест',
    },
    { name: 'isActive', label: 'Доступна для брони', type: 'checkbox', default: true },
    { name: 'sort', label: 'Порядок вывода', type: 'number', half: true, advanced: true },
  ];

  const edit = async (s) => {
    const result = await formDialog({
      title: s ? s.name : 'Новая услуга',
      fields,
      values: s ? { ...s, resourceIds: s.resources.map((r) => r.id) } : { durationMin: 60, bufferMin: 0, minGuests: 1, price: 0, isActive: true, sort: data.services.length, resourceIds: data.resources.map((r) => r.id) },
      submitLabel: s ? 'Сохранить' : 'Создать',
      onSubmit: (d) =>
        s ? adminApi(`/booking/services/${s.id}`, { method: 'PATCH', body: slugPatch(d) }) : adminApi('/booking/services', { method: 'POST', body: withSlug(d) }),
      extra: s && advancedOnly(deleteButton('Удалить', `Удалить услугу «${s.name}»?`, () => adminApi(`/booking/services/${s.id}`, { method: 'DELETE' }))),
    });
    if (result) {
      data.services = await adminApi('/booking/services');
      render();
    }
  };

  const render = () =>
    fill(box, 
      h(
        'div',
        { class: 'card' },
        h('div', { class: 'card__head' }, h('h2', { class: 'card__title' }, 'Что можно забронировать'), isFull() && h('button', { class: 'btn btn--small', type: 'button', onclick: () => edit(null) }, '+ Услуга')),
        table({
          columns: [
            { title: 'Услуга', render: (s) => h('div', null, s.name, h('div', { class: 'sub' }, s.resources.map((r) => r.name).join(', ') || 'Не привязана к местам')) },
            { title: 'Длительность', class: 'nowrap', render: (s) => formatDuration(s.durationMin) + (s.bufferMin ? ` + ${s.bufferMin} мин` : '') },
            { title: 'Гостей', class: 'nowrap', render: (s) => (s.maxGuests ? `${s.minGuests}–${s.maxGuests}` : `от ${s.minGuests}`) },
            { title: 'Цена', class: 'num', render: (s) => money(s.price) + (s.pricePerGuest ? ' / чел.' : '') },
            { title: '', render: (s) => !s.isActive && h('span', { class: 'status' }, 'Скрыта') },
          ],
          rows: data.services,
          rowClass: (s) => !s.isActive && 'is-muted',
          onRowClick: edit,
          empty: 'Услуг пока нет',
        }),
      ),
    );
  render();
}

// ─── Места ──────────────────────────────────────────────

async function resourcesTab(box, data) {
  const fields = [
    { name: 'name', label: 'Название', required: true, placeholder: 'Стол у окна, Комната «Фараон», Дорожка 1' },
    { name: 'slug', label: 'Адрес (латиницей)', hint: 'Если пусто — создастся из названия' },
    { name: 'type', label: 'Тип', half: true, nullable: true, placeholder: 'стол, комната, дорожка' },
    { name: 'maxGuests', label: 'Вместимость, чел.', type: 'number', min: 1, half: true, nullable: true },
    {
      name: 'capacity',
      label: 'Одновременных броней',
      type: 'number',
      min: 1,
      half: true,
      hint: 'Обычно 1. Больше — если одно место принимает несколько групп сразу (зал, VR-арена)',
    },
    { name: 'sort', label: 'Порядок вывода', type: 'number', half: true },
    { name: 'description', label: 'Описание', type: 'textarea', rows: 2, nullable: true },
    { name: 'imageUrl', label: 'Фото', type: 'image', nullable: true },
    { name: 'isActive', label: 'Доступно для брони', type: 'checkbox', default: true },
  ];

  const edit = async (r) => {
    const result = await formDialog({
      title: r ? r.name : 'Новое место',
      fields,
      values: r ?? { capacity: 1, isActive: true, sort: data.resources.length },
      submitLabel: r ? 'Сохранить' : 'Создать',
      onSubmit: (d) =>
        r ? adminApi(`/booking/resources/${r.id}`, { method: 'PATCH', body: slugPatch(d) }) : adminApi('/booking/resources', { method: 'POST', body: withSlug(d) }),
      extra: r && deleteButton('Удалить', `Удалить «${r.name}»?`, () => adminApi(`/booking/resources/${r.id}`, { method: 'DELETE' })),
    });
    if (result) {
      data.resources = await adminApi('/booking/resources');
      render();
    }
  };

  const render = () =>
    fill(box, 
      h(
        'div',
        { class: 'card' },
        h('div', { class: 'card__head' }, h('h2', { class: 'card__title' }, 'Столы, комнаты, зоны'), h('button', { class: 'btn btn--small', type: 'button', onclick: () => edit(null) }, '+ Место')),
        table({
          columns: [
            { title: 'Место', render: (r) => h('div', null, r.name, h('div', { class: 'sub' }, r.type ?? '')) },
            { title: 'Вместимость', class: 'num', render: (r) => (r.maxGuests ? `до ${r.maxGuests} чел.` : '—') },
            { title: 'Групп одновременно', class: 'num', render: (r) => r.capacity },
            { title: 'Услуги', render: (r) => r.services.map((s) => s.name).join(', ') || '—' },
            { title: '', render: (r) => !r.isActive && h('span', { class: 'status' }, 'Скрыто') },
          ],
          rows: data.resources,
          rowClass: (r) => !r.isActive && 'is-muted',
          onRowClick: edit,
          empty: 'Мест пока нет',
        }),
      ),
    );
  render();
}

// ─── График ─────────────────────────────────────────────

export async function hoursTab(box, data) {
  let target = ''; // '' — общий график, иначе id места

  const targetSelect = h(
    'select',
    { 'aria-label': 'Чей график' },
    h('option', { value: '' }, 'Общий график заведения'),
    data.resources.map((r) => h('option', { value: r.id }, `Отдельный график: ${r.name}`)),
  );
  targetSelect.addEventListener('change', () => {
    target = targetSelect.value;
    load();
  });
  const editor = h('div');
  // Отдельные графики мест — в полном режиме; заказчик правит общий график заведения
  fill(box, isFull() && data.resources.length > 0 && h('div', { class: 'toolbar' }, h('div', { class: 'field' }, targetSelect)), editor);

  async function load() {
    const resourceId = target ? Number(target) : null;
    const rows = await adminApi('/booking/hours', { query: resourceId ? { resourceId } : {} });

    if (resourceId && rows.length === 0) {
      fill(editor, 
        h(
          'div',
          { class: 'card card--pad' },
          h('p', null, 'Это место работает по общему графику заведения.'),
          h('p', { class: 'muted' }, 'Задайте отдельный график, если у места другие часы (например, летняя веранда).'),
          h(
            'button',
            {
              class: 'btn btn--ghost link--spaced',
              type: 'button',
              onclick: async () => renderWeek(await adminApi('/booking/hours'), resourceId),
            },
            'Задать отдельный график',
          ),
        ),
      );
      return;
    }
    renderWeek(rows, resourceId);
  }

  function renderWeek(rows, resourceId) {
    const multi = WEEKDAYS.some((_, i) => rows.filter((r) => r.weekday === i + 1).length > 1);
    const days = WEEKDAYS.map((name, i) => {
      const rule = rows.find((r) => r.weekday === i + 1);
      const enabled = h('input', { type: 'checkbox' });
      enabled.checked = Boolean(rule);
      const open = h('input', { type: 'time', value: rule?.openTime ?? '10:00', 'aria-label': `${name}: открытие` });
      const close = h('input', { type: 'time', value: rule?.closeTime ?? '22:00', 'aria-label': `${name}: закрытие` });
      const note = h('p', { class: 'hint' });
      const sync = () => {
        open.disabled = close.disabled = !enabled.checked;
        note.textContent = !enabled.checked ? 'Выходной' : close.value && close.value <= open.value ? 'до следующего дня' : '';
      };
      for (const el of [enabled, open, close]) el.addEventListener('input', sync);
      sync();
      return {
        weekday: i + 1,
        get value() {
          return enabled.checked ? { weekday: i + 1, openTime: open.value, closeTime: close.value } : null;
        },
        el: h(
          'div',
          { class: 'week-row' },
          h('span', { class: 'week-row__day' }, name),
          h('label', { class: 'switch' }, enabled, 'Работает'),
          h('div', { class: 'time-range' }, open, '—', close, note),
        ),
      };
    });

    const save = h('button', { class: 'btn', type: 'button' }, 'Сохранить график');
    save.addEventListener('click', async () => {
      const hours = days.map((d) => d.value).filter(Boolean);
      if (hours.some((x) => !x.openTime || !x.closeTime)) return toast('Укажите время открытия и закрытия', { error: true });
      save.disabled = true;
      await attempt(() => adminApi('/booking/hours', { method: 'PUT', body: { resourceId, hours } }), 'График сохранён');
      save.disabled = false;
    });

    fill(editor, 
      multi && h('p', { class: 'notice' }, 'В графике были дни с перерывами — здесь показан только первый интервал. При сохранении перерывы удалятся.'),
      h('div', { class: 'card card--pad' }, days.map((d) => d.el)),
      h(
        'div',
        { class: 'savebar' },
        resourceId &&
          h(
            'button',
            {
              class: 'btn btn--ghost',
              type: 'button',
              onclick: async () => {
                if (!confirm('Вернуть место к общему графику заведения?')) return;
                await attempt(() => adminApi('/booking/hours', { method: 'PUT', body: { resourceId, hours: [] } }), 'Место работает по общему графику');
                load();
              },
            },
            'Вернуть к общему графику',
          ),
        save,
      ),
    );
  }

  await load();
}

// ─── Исключения ─────────────────────────────────────────

export async function exceptionsTab(box, data) {
  const fields = [
    { name: 'date', label: 'Дата', type: 'date', required: true, half: true },
    { name: 'resourceId', label: 'Где', type: 'select', numeric: true, nullable: true, half: true, advanced: true, options: [['', 'Всё заведение'], ...data.resources.map((r) => [r.id, r.name])] },
    { name: 'isClosed', label: 'Закрыто весь день', type: 'checkbox', default: true },
    { name: 'openTime', label: 'Или работаем с', type: 'time', half: true, nullable: true },
    { name: 'closeTime', label: 'до', type: 'time', half: true, nullable: true },
    { name: 'note', label: 'Причина', nullable: true, placeholder: 'Новый год, санитарный день' },
  ];

  const add = async () => {
    const result = await formDialog({
      title: 'Особый день',
      intro: 'Праздник, выходной или сокращённые часы. Перекрывает обычный график на эту дату.',
      fields,
      values: { isClosed: true },
      submitLabel: 'Добавить',
      onSubmit: (d) => adminApi('/booking/exceptions', { method: 'POST', body: { ...d, resourceId: d.resourceId ?? null } }),
    });
    if (result) load();
  };

  const remove = async (e) => {
    if (!confirm(`Удалить особый день ${e.date}?`)) return;
    if (await attempt(() => adminApi(`/booking/exceptions/${e.id}`, { method: 'DELETE' }), 'Удалено')) load();
  };

  async function load() {
    const items = await adminApi('/booking/exceptions', { query: { from: todayISO() } });
    fill(box, 
      h(
        'div',
        { class: 'card' },
        h('div', { class: 'card__head' }, h('h2', { class: 'card__title' }, 'Ближайшие особые дни'), h('button', { class: 'btn btn--small', type: 'button', onclick: add }, '+ Особый день')),
        table({
          columns: [
            { title: 'Дата', class: 'nowrap', render: (e) => formatDayTitle(e.date) },
            { title: 'Где', render: (e) => e.resource?.name ?? 'Всё заведение' },
            { title: 'Режим', render: (e) => (e.isClosed ? h('span', { class: 'status status--cancelled' }, 'Закрыто') : `${e.openTime}–${e.closeTime}`) },
            { title: 'Причина', render: (e) => e.note ?? '' },
            { title: '', class: 'actions', render: (e) => h('button', { class: 'icon-btn', type: 'button', title: 'Удалить', onclick: () => remove(e) }, '×') },
          ],
          rows: items,
          empty: 'Особых дней нет — заведение работает по обычному графику',
        }),
      ),
    );
  }
  await load();
}

// ─── Раздел ─────────────────────────────────────────────

export async function catalogView(root, ctx) {
  const tabs = visible(TABS);
  const tab = tabs.find((t) => t.key === ctx.query.tab) ?? tabs[0];
  const [services, resources] = await Promise.all([adminApi('/booking/services'), adminApi('/booking/resources')]);
  const data = { services, resources };

  const box = h('div');
  add(root, 
    pageHeader(isFull() ? 'Услуги и график' : 'График работы'),
    h(
      'div',
      { class: 'chips' },
      tabs.map((t) => h('a', { class: 'chip', href: `#/booking-settings?tab=${t.key}`, 'aria-pressed': String(t === tab) }, t.label)),
    ),
    box,
    extendNotice('Новая услуга, ещё один зал или столы, онлайн-предоплата брони?'),
  );
  const render = { services: servicesTab, resources: resourcesTab, hours: hoursTab, exceptions: exceptionsTab }[tab.key];
  await render(box, data);
  if (!box.children.length) add(box, emptyState('Пусто'));
}
