/**
 * Редактор страниц лендинга. Блоки описываются схемами ниже: какие поля у блока и какие поля у пунктов списка.
 * Схемы совпадают с тем, что рисует публичный сайт (frontend/js/blocks.js).
 */
import { add, fill, h, toast } from '../../../js/dom.js';
import { adminApi, uploadFiles } from '../api.js';
import { extendNotice, isFull, visible } from '../mode.js';
import { attempt, emptyState, fieldEl, pageHeader, table } from '../ui.js';

const CTA_ACTIONS = [
  ['', 'По умолчанию'],
  ['booking', 'К бронированию'],
  ['lead', 'Форма заявки'],
];

const BLOCKS = {
  hero: {
    label: 'Первый экран',
    fields: [
      { name: 'title', label: 'Заголовок' },
      { name: 'subtitle', label: 'Подзаголовок', type: 'textarea', rows: 2 },
      { name: 'image', label: 'Картинка справа', type: 'image' },
      { name: 'cta', label: 'Текст кнопки', half: true, placeholder: 'Забронировать' },
      { name: 'ctaAction', label: 'Кнопка ведёт', type: 'select', half: true, options: CTA_ACTIONS, advanced: true },
    ],
  },
  features: {
    label: 'Преимущества',
    fields: [{ name: 'title', label: 'Заголовок секции' }],
    items: { label: 'Пункты', add: 'Пункт', fields: [{ name: 'title', label: 'Заголовок' }, { name: 'text', label: 'Текст', type: 'textarea', rows: 2 }] },
  },
  text: {
    label: 'Текст',
    fields: [
      { name: 'title', label: 'Заголовок' },
      { name: 'text', label: 'Текст', type: 'textarea', rows: 6, hint: 'Абзацы разделяйте пустой строкой' },
    ],
  },
  gallery: { label: 'Галерея', fields: [{ name: 'title', label: 'Заголовок секции' }], gallery: true },
  prices: {
    label: 'Цены',
    fields: [{ name: 'title', label: 'Заголовок секции' }],
    items: {
      label: 'Позиции',
      add: 'Позиция',
      fields: [
        { name: 'name', label: 'Название', half: true },
        { name: 'price', label: 'Цена', half: true, placeholder: 'от 1 500 ₽' },
        { name: 'note', label: 'Примечание' },
      ],
    },
  },
  reviews: {
    label: 'Отзывы',
    fields: [{ name: 'title', label: 'Заголовок секции' }],
    items: { label: 'Отзывы', add: 'Отзыв', fields: [{ name: 'name', label: 'Имя' }, { name: 'text', label: 'Текст', type: 'textarea', rows: 3 }] },
  },
  faq: {
    label: 'Вопросы и ответы',
    fields: [{ name: 'title', label: 'Заголовок секции' }],
    items: { label: 'Вопросы', add: 'Вопрос', fields: [{ name: 'q', label: 'Вопрос' }, { name: 'a', label: 'Ответ', type: 'textarea', rows: 2 }] },
  },
  cta: {
    label: 'Призыв к действию',
    fields: [
      { name: 'title', label: 'Заголовок' },
      { name: 'text', label: 'Текст', type: 'textarea', rows: 2 },
      { name: 'cta', label: 'Текст кнопки', half: true },
      { name: 'ctaAction', label: 'Кнопка ведёт', type: 'select', half: true, options: CTA_ACTIONS, advanced: true },
    ],
  },
  contacts: {
    label: 'Контакты',
    fields: [{ name: 'title', label: 'Заголовок секции' }],
    note: 'Адрес, телефон и часы работы берутся из раздела «Настройки».',
  },
};

// Общие настройки любого блока
const COMMON_FIELDS = [
  { name: 'navTitle', label: 'Пункт в меню сайта', half: true, hint: 'Пусто — название по умолчанию', advanced: true },
  { name: 'alt', label: 'Тонированный фон', type: 'checkbox', half: true, advanced: true },
];

/** Поля, изменения которых сразу пишутся в объект data. */
function boundFields(fields, data, onChange) {
  return visible(fields).map((f) => {
    const el = fieldEl(f, data[f.name]);
    el.addEventListener('input', (e) => {
      const t = e.target;
      if (!t.name) return;
      const value = t.type === 'checkbox' ? t.checked : t.value;
      if (value === '' || value === false) delete data[f.name];
      else data[f.name] = value;
      onChange();
    });
    return el;
  });
}

function repeater(schema, data, onChange) {
  data.items ??= [];
  const box = h('div', { class: 'repeater' });
  const render = () => {
    fill(box, 
      h('span', { class: 'repeater__label' }, schema.label),
      ...data.items.map((item, i) =>
        h(
          'div',
          { class: 'repeater__item form--grid' },
          boundFields(schema.fields, item, onChange),
          h(
            'div',
            { class: 'repeater__tools' },
            h('button', { class: 'icon-btn', type: 'button', title: 'Выше', disabled: i === 0, onclick: () => move(i, -1) }, '↑'),
            h('button', { class: 'icon-btn', type: 'button', title: 'Ниже', disabled: i === data.items.length - 1, onclick: () => move(i, 1) }, '↓'),
            h('button', { class: 'icon-btn', type: 'button', title: 'Удалить', onclick: () => { data.items.splice(i, 1); onChange(); render(); } }, '×'),
          ),
        ),
      ),
      h('div', null, h('button', { class: 'btn btn--ghost btn--small', type: 'button', onclick: () => { data.items.push({}); onChange(); render(); } }, `+ ${schema.add}`)),
    );
  };
  const move = (i, dir) => {
    [data.items[i], data.items[i + dir]] = [data.items[i + dir], data.items[i]];
    onChange();
    render();
  };
  render();
  return box;
}

function galleryEditor(data, onChange) {
  data.images ??= [];
  const box = h('div', { class: 'repeater' });
  const file = h('input', { type: 'file', accept: 'image/*', multiple: true, hidden: true });
  const addBtn = h('button', { class: 'btn btn--ghost btn--small', type: 'button', onclick: () => file.click() }, '+ Загрузить фото');
  file.addEventListener('change', async () => {
    if (!file.files.length) return;
    addBtn.disabled = true;
    addBtn.textContent = 'Загрузка…';
    try {
      const uploaded = await uploadFiles(file.files);
      data.images.push(...uploaded.map((u) => u.url));
      onChange();
      render();
    } catch (err) {
      toast(err.message, { error: true });
    } finally {
      addBtn.disabled = false;
      addBtn.textContent = '+ Загрузить фото';
      file.value = '';
    }
  });
  const render = () =>
    fill(box, 
      h('span', { class: 'repeater__label' }, 'Фотографии'),
      h(
        'div',
        { class: 'gallery-edit' },
        data.images.map((img, i) =>
          h(
            'div',
            { class: 'gallery-edit__item' },
            h('img', { src: typeof img === 'string' ? img : img.url, alt: '' }),
            h('button', { class: 'icon-btn', type: 'button', title: 'Убрать', onclick: () => { data.images.splice(i, 1); onChange(); render(); } }, '×'),
          ),
        ),
      ),
      h('div', null, addBtn, file),
    );
  render();
  return box;
}

async function editPage(root, ctx, id) {
  const page = await adminApi(`/pages/${id}`);
  let dirty = false;
  const status = h('span', { class: 'muted' });
  const markDirty = () => {
    dirty = true;
    status.textContent = 'Есть несохранённые изменения';
  };
  const blocksBox = h('div');

  function renderBlocks() {
    fill(blocksBox, 
      ...page.blocks.map((block, i) => {
        // Скрытые блоки заказчику не показываем — они остаются в странице и сохраняются как есть
        if (!isFull() && block.isVisible === false) return '';
        const schema = BLOCKS[block.type];
        block.data ??= {};

        const body = schema
          ? [
              schema.note && h('p', { class: 'notice' }, schema.note),
              h('div', { class: 'form--grid' }, boundFields(schema.fields, block.data, markDirty)),
              schema.items && repeater(schema.items, block.data, markDirty),
              schema.gallery && galleryEditor(block.data, markDirty),
              isFull() && h('div', { class: 'form--grid' }, boundFields(COMMON_FIELDS, block.data, markDirty)),
            ]
          : [h('p', { class: 'notice' }, `Блок типа «${block.type}» редактируется только через API.`)];

        const card = h('div', { class: `card block-card ${block.isVisible === false ? 'is-hidden' : ''}` });
        add(card, 
          h(
            'div',
            { class: 'block-card__head' },
            h('span', { class: 'block-card__type' }, schema?.label ?? block.type),
            // Структура страницы (видимость, порядок, удаление) — только в полном режиме
            isFull() && blockTools(card, block, i, schema),
          ),
          h('div', { class: 'block-card__body' }, body),
        );
        return card;
      }),
    );
  }

  function blockTools(card, block, i, schema) {
    const shown = h('input', { type: 'checkbox' });
    shown.checked = block.isVisible !== false;
    shown.addEventListener('change', () => {
      block.isVisible = shown.checked;
      card.classList.toggle('is-hidden', !shown.checked);
      markDirty();
    });
    const remove = () => {
      if (!confirm(`Удалить блок «${schema?.label ?? block.type}»?`)) return;
      page.blocks.splice(i, 1);
      markDirty();
      renderBlocks();
    };
    return [
      h('label', { class: 'switch' }, shown, 'Показывать'),
      h('button', { class: 'icon-btn', type: 'button', title: 'Выше', disabled: i === 0, onclick: () => moveBlock(i, -1) }, '↑'),
      h('button', { class: 'icon-btn', type: 'button', title: 'Ниже', disabled: i === page.blocks.length - 1, onclick: () => moveBlock(i, 1) }, '↓'),
      h('button', { class: 'icon-btn', type: 'button', title: 'Удалить блок', onclick: remove }, '×'),
    ];
  }

  const moveBlock = (i, dir) => {
    [page.blocks[i], page.blocks[i + dir]] = [page.blocks[i + dir], page.blocks[i]];
    markDirty();
    renderBlocks();
  };

  const typeSelect = h('select', { 'aria-label': 'Тип нового блока' }, Object.entries(BLOCKS).map(([t, b]) => h('option', { value: t }, b.label)));
  const addBlock = () => {
    const type = typeSelect.value;
    page.blocks.push({ id: `${type}-${Date.now().toString(36)}`, type, isVisible: true, data: {} });
    markDirty();
    renderBlocks();
    blocksBox.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  // SEO и публикация
  const seoFields = [
    { name: 'title', label: 'Название страницы', required: true, half: true },
    { name: 'isPublished', label: 'Опубликована', type: 'checkbox', half: true },
    { name: 'seoTitle', label: 'SEO-заголовок', hint: 'Заголовок во вкладке браузера и в поиске' },
    { name: 'seoDescription', label: 'SEO-описание', type: 'textarea', rows: 2, hint: 'Текст под ссылкой в поиске, до 160 символов' },
  ];
  const seo = { title: page.title, isPublished: page.isPublished, seoTitle: page.seoTitle ?? '', seoDescription: page.seoDescription ?? '' };

  const saveBtn = h('button', { class: 'btn', type: 'button' }, 'Сохранить');
  saveBtn.addEventListener('click', async () => {
    if (!seo.title?.trim()) return toast('Укажите название страницы', { error: true });
    saveBtn.disabled = true;
    const ok = await attempt(
      () =>
        adminApi(`/pages/${page.id}`, {
          method: 'PATCH',
          body: {
            title: seo.title.trim(),
            isPublished: Boolean(seo.isPublished),
            seoTitle: seo.seoTitle || null,
            seoDescription: seo.seoDescription || null,
            blocks: page.blocks.map((b) => ({ ...b, isVisible: b.isVisible !== false })),
          },
        }),
      'Страница сохранена',
    );
    saveBtn.disabled = false;
    if (ok) {
      dirty = false;
      status.textContent = '';
    }
  });

  // Не теряем правки при уходе со страницы
  const beforeUnload = (e) => dirty && e.preventDefault();
  window.addEventListener('beforeunload', beforeUnload);
  const onHash = (e) => {
    if (dirty && !confirm('Уйти без сохранения изменений?')) {
      history.replaceState(null, '', new URL(e.oldURL).hash);
      e.stopImmediatePropagation();
      return;
    }
    window.removeEventListener('beforeunload', beforeUnload);
    window.removeEventListener('hashchange', onHash, true);
  };
  window.addEventListener('hashchange', onHash, true);

  add(root, 
    // Внутри раздела «Сайт» заголовок и кнопка «Открыть сайт» уже есть
    !ctx.embedded &&
    pageHeader(
      isFull() ? page.title : 'Тексты сайта',
      h('a', { class: 'btn btn--ghost', href: '../', target: '_blank', rel: 'noopener' }, 'Посмотреть на сайте ↗'),
    ),
    // SEO-поля в простом режиме скрыты — их значения сохраняются без изменений
    isFull() && h('div', { class: 'card card--pad' }, h('div', { class: 'form form--grid' }, boundFields(seoFields, seo, markDirty))),
    isFull() && h('h2', { class: 'card__title link--spaced' }, 'Блоки страницы'),
    blocksBox,
    isFull()
      ? h('div', { class: 'card card--pad toolbar' }, h('div', { class: 'field' }, typeSelect), h('button', { class: 'btn btn--ghost', type: 'button', onclick: addBlock }, '+ Добавить блок'))
      : extendNotice('Нужен новый раздел на сайте — галерея, отзывы, цены, акции?'),
    h('div', { class: 'savebar' }, status, saveBtn),
  );
  renderBlocks();
}

export async function pagesView(root, ctx) {
  if (ctx.query.id) return editPage(root, ctx, Number(ctx.query.id));

  const pages = await adminApi('/pages');
  // Обычно страница одна — сразу открываем редактор
  if (pages.length === 1) return editPage(root, ctx, pages[0].id);

  add(root, 
    pageHeader('Страницы'),
    h(
      'div',
      { class: 'card' },
      pages.length
        ? table({
            columns: [
              { title: 'Страница', render: (p) => p.title },
              { title: 'Адрес', render: (p) => `/${p.slug}` },
              { title: '', render: (p) => !p.isPublished && h('span', { class: 'status' }, 'Черновик') },
            ],
            rows: pages,
            onRowClick: (p) => ctx.navigate(`pages?id=${p.id}`),
          })
        : emptyState('Страниц нет. Запустите npm run db:seed в бэкенде, чтобы создать главную.'),
    ),
  );
}

