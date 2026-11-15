import { add, fill, h, toast } from '../../../js/dom.js';
import { adminApi } from '../api.js';
import { extendNotice } from '../mode.js';
import { BLANK_IMG, attempt, deleteButton, emptyState, formDialog, money, pageHeader, slugify, table } from '../ui.js';

const TAGS = [
  ['hit', 'Хит'],
  ['new', 'Новинка'],
  ['veg', 'Вегетарианское'],
  ['spicy', 'Острое'],
];
const TAG_LABELS = Object.fromEntries(TAGS);

/** При правке адрес меняем, только если поле было в форме (в простом режиме оно скрыто). */
const slugPatch = (d) => (d.slug === undefined ? d : { ...d, slug: d.slug || slugify(d.name) });

const categoryFields = [
  { name: 'name', label: 'Название', required: true },
  { name: 'slug', label: 'Адрес (латиницей)', advanced: true, hint: 'Используется в ссылке на раздел меню. Если оставить пустым — создастся из названия.' },
  { name: 'description', label: 'Описание', type: 'textarea', rows: 2, nullable: true },
  { name: 'isActive', label: 'Показывать на сайте', type: 'checkbox', default: true },
];

const itemFields = (categories) => [
  { name: 'name', label: 'Название', required: true },
  { name: 'description', label: 'Описание / состав', type: 'textarea', rows: 2, nullable: true },
  { name: 'price', label: 'Цена, ₽', type: 'money', half: true, required: true },
  { name: 'oldPrice', label: 'Старая цена, ₽', type: 'money', half: true, nullable: true, hint: 'Будет зачёркнута', advanced: true },
  { name: 'weight', label: 'Выход', half: true, nullable: true, placeholder: '250 г' },
  { name: 'categoryId', label: 'Категория', type: 'select', numeric: true, half: true, options: categories.map((c) => [c.id, c.name]) },
  { name: 'imageUrl', label: 'Фото', type: 'image', nullable: true },
  { name: 'tags', label: 'Метки', type: 'multiselect', options: TAGS },
  { name: 'isAvailable', label: 'В наличии (показывать на сайте)', type: 'checkbox', default: true },
];

export async function menuView(root) {
  let categories = await adminApi('/menu/categories');
  let currentId = categories[0]?.id ?? null;
  let items = [];

  const side = h('div', { class: 'card' });
  const main = h('div');

  const current = () => categories.find((c) => c.id === currentId);

  async function reloadCategories() {
    categories = await adminApi('/menu/categories');
    if (!current()) currentId = categories[0]?.id ?? null;
    renderSide();
  }

  function renderSide() {
    fill(side, 
      h(
        'ul',
        { class: 'list' },
        categories.map((c) =>
          h(
            'li',
            null,
            h(
              'button',
              {
                class: `list__item ${c.isActive ? '' : 'is-off'}`,
                type: 'button',
                'aria-current': String(c.id === currentId),
                onclick: () => {
                  currentId = c.id;
                  renderSide();
                  loadItems();
                },
              },
              h('span', null, c.name),
              h('span', { class: 'list__count' }, c._count?.items ?? ''),
            ),
          ),
        ),
      ),
      h('div', { class: 'list__add' }, h('button', { class: 'btn btn--ghost btn--small btn--block', type: 'button', onclick: addCategory }, '+ Категория')),
    );
  }

  async function addCategory() {
    const created = await formDialog({
      title: 'Новая категория',
      fields: categoryFields,
      submitLabel: 'Создать',
      onSubmit: (data) =>
        adminApi('/menu/categories', {
          method: 'POST',
          body: { ...data, slug: data.slug || slugify(data.name), sort: categories.length },
        }),
    });
    if (!created) return;
    currentId = created.id;
    await reloadCategories();
    loadItems();
  }

  async function editCategory(c) {
    const result = await formDialog({
      title: 'Категория',
      fields: categoryFields,
      values: c,
      onSubmit: (data) => adminApi(`/menu/categories/${c.id}`, { method: 'PATCH', body: slugPatch(data) }),
      extra: deleteButton('Удалить', `Удалить категорию «${c.name}» вместе со всеми блюдами?`, () =>
        adminApi(`/menu/categories/${c.id}`, { method: 'DELETE' }),
      ),
    });
    if (!result) return;
    if (result === 'deleted') toast('Категория удалена');
    await reloadCategories();
    loadItems();
  }

  async function moveCategory(c, dir) {
    const order = [...categories];
    const i = order.indexOf(c);
    [order[i], order[i + dir]] = [order[i + dir], order[i]];
    await attempt(() => adminApi('/menu/categories/reorder', { method: 'PUT', body: order.map((x, sort) => ({ id: x.id, sort })) }));
    await reloadCategories();
  }

  async function editItem(item) {
    const result = await formDialog({
      title: item ? item.name : 'Новое блюдо',
      fields: itemFields(categories),
      values: item ?? { categoryId: currentId, isAvailable: true, tags: [] },
      submitLabel: item ? 'Сохранить' : 'Добавить',
      onSubmit: (data) =>
        item
          ? adminApi(`/menu/items/${item.id}`, { method: 'PATCH', body: data })
          : adminApi('/menu/items', { method: 'POST', body: { ...data, sort: items.length } }),
      extra: item && deleteButton('Удалить', `Удалить «${item.name}»?`, () => adminApi(`/menu/items/${item.id}`, { method: 'DELETE' })),
    });
    if (!result) return;
    await reloadCategories();
    loadItems();
  }

  async function moveItem(item, dir) {
    const order = [...items];
    const i = order.indexOf(item);
    [order[i], order[i + dir]] = [order[i + dir], order[i]];
    await attempt(() => adminApi('/menu/items/reorder', { method: 'PUT', body: order.map((x, sort) => ({ id: x.id, sort })) }));
    loadItems();
  }

  async function toggleAvailable(item, checked) {
    const ok = await attempt(
      () => adminApi(`/menu/items/${item.id}`, { method: 'PATCH', body: { isAvailable: checked } }),
      checked ? `«${item.name}» снова в меню` : `«${item.name}» скрыто с сайта`,
    );
    if (ok) item.isAvailable = checked;
    loadItems();
  }

  async function loadItems() {
    const c = current();
    if (!c) {
      fill(main, h('div', { class: 'card' }, emptyState('Создайте первую категорию меню, например «Завтраки»')));
      return;
    }
    items = await adminApi('/menu/items', { query: { categoryId: c.id } });
    const ci = categories.indexOf(c);

    fill(main, 
      h(
        'div',
        { class: 'card' },
        h(
          'div',
          { class: 'card__head' },
          h('h2', { class: 'card__title' }, c.name, !c.isActive && h('span', { class: 'muted' }, ' · скрыта')),
          h(
            'div',
            { class: 'page-head__actions' },
            h('button', { class: 'icon-btn', type: 'button', title: 'Выше', disabled: ci === 0, onclick: () => moveCategory(c, -1) }, '↑'),
            h('button', { class: 'icon-btn', type: 'button', title: 'Ниже', disabled: ci === categories.length - 1, onclick: () => moveCategory(c, 1) }, '↓'),
            h('button', { class: 'btn btn--ghost btn--small', type: 'button', onclick: () => editCategory(c) }, 'Изменить'),
            h('button', { class: 'btn btn--small', type: 'button', onclick: () => editItem(null) }, '+ Блюдо'),
          ),
        ),
        table({
          columns: [
            { title: '', render: (i) => h('img', { class: 'thumb', src: i.imageUrl || BLANK_IMG, alt: '' }) },
            {
              title: 'Название',
              render: (i) =>
                h('div', null, i.name, h('div', { class: 'sub' }, [i.weight, ...i.tags.map((t) => TAG_LABELS[t] ?? t)].filter(Boolean).join(' · '))),
            },
            { title: 'Цена', class: 'num', render: (i) => money(i.price) },
            {
              title: 'На сайте',
              render: (i) => {
                const box = h('input', { type: 'checkbox', 'aria-label': 'Показывать на сайте' });
                box.checked = i.isAvailable;
                box.addEventListener('change', () => toggleAvailable(i, box.checked));
                return h('label', { class: 'switch' }, box);
              },
            },
            {
              title: '',
              class: 'actions',
              render: (i) => {
                const idx = items.indexOf(i);
                return [
                  h('button', { class: 'icon-btn', type: 'button', title: 'Выше', disabled: idx === 0, onclick: () => moveItem(i, -1) }, '↑'),
                  h('button', { class: 'icon-btn', type: 'button', title: 'Ниже', disabled: idx === items.length - 1, onclick: () => moveItem(i, 1) }, '↓'),
                ];
              },
            },
          ],
          rows: items,
          rowClass: (i) => !i.isAvailable && 'is-muted',
          onRowClick: editItem,
          empty: 'В категории пока нет блюд',
        }),
      ),
    );
  }

  add(root, 
    pageHeader('Меню'),
    h('div', { class: 'split' }, side, main),
    extendNotice('Нужен онлайн-заказ или доставка прямо из меню?'),
  );
  renderSide();
  await loadItems();
}
