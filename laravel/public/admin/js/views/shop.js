import { add, fill, h, toast } from '../../../js/dom.js';
import { adminApi } from '../api.js';
import { extendNotice } from '../mode.js';
import { BLANK_IMG, attempt, deleteButton, emptyState, formDialog, money, pageHeader, slugify, table } from '../ui.js';

/** При правке адрес меняем, только если поле было в форме (в простом режиме оно скрыто). */
const slugPatch = (data) => (data.slug === undefined ? data : { ...data, slug: data.slug || slugify(data.name) });

const TONES = [
  ['', 'Как на фото'],
  ['photo', 'Лавандовая тонировка фото'],
  ['neutral', 'Нейтральная (без оттенка)'],
];

const categoryFields = [
  { name: 'name', label: 'Название', required: true },
  { name: 'slug', label: 'Адрес (латиницей)', advanced: true, hint: 'Используется в ссылке catalog.html?cat=… Если пусто — создастся из названия.' },
  { name: 'title', label: 'Заголовок страницы', advanced: true, hint: 'Показывается в шапке каталога. Пусто — название категории.' },
  { name: 'subtitle', label: 'Подзаголовок', type: 'textarea', rows: 2, nullable: true },
  { name: 'shortName', label: 'Короткое имя', advanced: true, hint: 'Для фильтров и хлебных крошек' },
  { name: 'isActive', label: 'Показывать на сайте', type: 'checkbox', default: true },
];

/** Характеристики в форме — по строке «Название: значение». */
function specsToText(specs) {
  return (specs ?? []).map((item) => `${item.name}: ${item.value}`).join('\n');
}

function specsFromText(text) {
  return String(text ?? '')
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const index = line.indexOf(':');
      if (index === -1) return null;
      const name = line.slice(0, index).trim();
      const value = line.slice(index + 1).trim();
      return name && value ? { name, value } : null;
    })
    .filter(Boolean);
}

const productFields = (categories) => [
  { name: 'name', label: 'Название', required: true, placeholder: 'Браслет из бисера «Лаванда»' },
  { name: 'slug', label: 'Адрес (латиницей)', advanced: true, hint: 'Ссылка product.html?id=… Если пусто — создастся из названия.' },
  { name: 'categoryId', label: 'Категория', type: 'select', numeric: true, half: true, options: categories.map((c) => [c.id, c.name]) },
  { name: 'price', label: 'Цена, ₽', type: 'money', half: true, required: true },
  { name: 'oldPrice', label: 'Старая цена, ₽', type: 'money', half: true, nullable: true, advanced: true, hint: 'Можно показывать перечёркнутой' },
  { name: 'badge', label: 'Метка', half: true, nullable: true, placeholder: 'Хит, Новинка', hint: 'Короткая надпись на карточке' },
  { name: 'imageUrl', label: 'Фото', type: 'image', nullable: true },
  { name: 'shortDescription', label: 'Короткое описание', type: 'textarea', rows: 2, nullable: true, hint: 'Показывается в каталоге, поиске и на карточке товара' },
  { name: 'description', label: 'Полное описание', type: 'textarea', rows: 4, nullable: true },
  { name: 'specsText', label: 'Характеристики', type: 'textarea', rows: 4, advanced: true, hint: 'По одной на строку, например: Материалы: бисер, авантюрин' },
  { name: 'tone', label: 'Тонировка фото', type: 'select', half: true, advanced: true, options: TONES },
  { name: 'zoom', label: 'Увеличение фото', type: 'number', step: '0.1', min: 0, half: true, nullable: true, advanced: true, placeholder: '1.6' },
  { name: 'isFeatured', label: 'Показывать на главной', type: 'checkbox' },
  { name: 'isActive', label: 'В продаже (показывать на сайте)', type: 'checkbox', default: true },
];

export async function shopView(root) {
  let categories = await adminApi('/shop/categories');
  let currentId = categories[0]?.id ?? null;
  let products = [];

  const side = h('div', { class: 'card' });
  const main = h('div');

  const current = () => categories.find((c) => c.id === currentId);

  async function reloadCategories() {
    categories = await adminApi('/shop/categories');
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
                  loadProducts();
                },
              },
              h('span', null, c.name),
              h('span', { class: 'list__count' }, c._count?.products ?? ''),
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
        adminApi('/shop/categories', {
          method: 'POST',
          body: { ...data, slug: data.slug || slugify(data.name), sort: categories.length },
        }),
    });
    if (!created) return;
    currentId = created.id;
    await reloadCategories();
    loadProducts();
  }

  async function editCategory(c) {
    const result = await formDialog({
      title: 'Категория',
      fields: categoryFields,
      values: c,
      onSubmit: (data) => adminApi(`/shop/categories/${c.id}`, { method: 'PATCH', body: slugPatch(data) }),
      extra: deleteButton('Удалить', `Удалить категорию «${c.name}» вместе со всеми товарами?`, () =>
        adminApi(`/shop/categories/${c.id}`, { method: 'DELETE' }),
      ),
    });
    if (!result) return;
    if (result === 'deleted') toast('Категория удалена');
    await reloadCategories();
    loadProducts();
  }

  async function moveCategory(c, dir) {
    const order = [...categories];
    const i = order.indexOf(c);
    [order[i], order[i + dir]] = [order[i + dir], order[i]];
    await attempt(() => adminApi('/shop/categories/reorder', { method: 'PUT', body: order.map((x, sort) => ({ id: x.id, sort })) }));
    await reloadCategories();
  }

  async function editProduct(product) {
    const result = await formDialog({
      title: product ? product.name : 'Новый товар',
      fields: productFields(categories),
      values: product
        ? { ...product, specsText: specsToText(product.specs) }
        : { categoryId: currentId, isActive: true, tone: '', specs: [] },
      submitLabel: product ? 'Сохранить' : 'Добавить',
      onSubmit: (data) => {
        const { specsText, ...rest } = data;
        const body = { ...rest, specs: specsFromText(specsText) };
        return product
          ? adminApi(`/shop/products/${product.id}`, { method: 'PATCH', body: slugPatch(body) })
          : adminApi('/shop/products', { method: 'POST', body: { ...body, slug: body.slug || slugify(body.name), sort: products.length } });
      },
      extra: product && deleteButton('Удалить', `Удалить «${product.name}»?`, () => adminApi(`/shop/products/${product.id}`, { method: 'DELETE' })),
    });
    if (!result) return;
    await reloadCategories();
    loadProducts();
  }

  async function moveProduct(product, dir) {
    const order = [...products];
    const i = order.indexOf(product);
    [order[i], order[i + dir]] = [order[i + dir], order[i]];
    await attempt(() => adminApi('/shop/products/reorder', { method: 'PUT', body: order.map((x, sort) => ({ id: x.id, sort })) }));
    loadProducts();
  }

  async function toggleField(product, field, checked, message) {
    const ok = await attempt(
      () => adminApi(`/shop/products/${product.id}`, { method: 'PATCH', body: { [field]: checked } }),
      message,
    );
    if (ok) product[field] = checked;
    loadProducts();
  }

  async function loadProducts() {
    const category = current();
    if (!category) {
      fill(main, h('div', { class: 'card' }, emptyState('Создайте первую категорию каталога, например «Украшения из бисера»')));
      return;
    }
    products = await adminApi('/shop/products', { query: { categoryId: category.id } });
    const categoryIndex = categories.indexOf(category);

    fill(main,
      h(
        'div',
        { class: 'card' },
        h(
          'div',
          { class: 'card__head' },
          h('h2', { class: 'card__title' }, category.name, !category.isActive && h('span', { class: 'muted' }, ' · скрыта')),
          h(
            'div',
            { class: 'page-head__actions' },
            h('button', { class: 'icon-btn', type: 'button', title: 'Выше', disabled: categoryIndex === 0, onclick: () => moveCategory(category, -1) }, '↑'),
            h('button', { class: 'icon-btn', type: 'button', title: 'Ниже', disabled: categoryIndex === categories.length - 1, onclick: () => moveCategory(category, 1) }, '↓'),
            h('button', { class: 'btn btn--ghost btn--small', type: 'button', onclick: () => editCategory(category) }, 'Изменить'),
            h('button', { class: 'btn btn--small', type: 'button', onclick: () => editProduct(null) }, '+ Товар'),
          ),
        ),
        table({
          columns: [
            { title: '', render: (p) => h('img', { class: 'thumb', src: p.imageUrl || BLANK_IMG, alt: '' }) },
            {
              title: 'Товар',
              render: (p) => h('div', null, p.name, h('div', { class: 'sub' }, [p.badge, p.isFeatured && 'на главной'].filter(Boolean).join(' · ') || p.slug)),
            },
            { title: 'Цена', class: 'num', render: (p) => money(p.price) },
            {
              title: 'На главной',
              render: (p) => {
                const box = h('input', { type: 'checkbox', 'aria-label': 'Показывать на главной' });
                box.checked = p.isFeatured;
                box.addEventListener('change', () =>
                  toggleField(p, 'isFeatured', box.checked, box.checked ? `«${p.name}» на главной` : `«${p.name}» убран с главной`),
                );
                return h('label', { class: 'switch' }, box);
              },
            },
            {
              title: 'В продаже',
              render: (p) => {
                const box = h('input', { type: 'checkbox', 'aria-label': 'Показывать на сайте' });
                box.checked = p.isActive;
                box.addEventListener('change', () =>
                  toggleField(p, 'isActive', box.checked, box.checked ? `«${p.name}» снова в продаже` : `«${p.name}» скрыт с сайта`),
                );
                return h('label', { class: 'switch' }, box);
              },
            },
            {
              title: '',
              class: 'actions',
              render: (p) => {
                const index = products.indexOf(p);
                return [
                  h('button', { class: 'icon-btn', type: 'button', title: 'Выше', disabled: index === 0, onclick: () => moveProduct(p, -1) }, '↑'),
                  h('button', { class: 'icon-btn', type: 'button', title: 'Ниже', disabled: index === products.length - 1, onclick: () => moveProduct(p, 1) }, '↓'),
                ];
              },
            },
          ],
          rows: products,
          rowClass: (p) => !p.isActive && 'is-muted',
          onRowClick: editProduct,
          empty: 'В категории пока нет товаров',
        }),
      ),
    );
  }

  add(root,
    pageHeader('Товары'),
    h('div', { class: 'split' }, side, main),
    extendNotice('Нужны варианты цвета и размера, промокоды или онлайн-оплата?'),
  );
  renderSide();
  await loadProducts();
}
