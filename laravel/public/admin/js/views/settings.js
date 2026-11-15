import { add, h, toast } from '../../../js/dom.js';
import { clearErrors, showErrors, withPending } from '../../../js/forms.js';
import { adminApi } from '../api.js';
import { extendNotice, isFull, visible } from '../mode.js';
import { checkRequired, collect, fieldEl, formDialog, pageHeader } from '../ui.js';

const SOCIALS = [
  ['telegram', 'Telegram'],
  ['vk', 'ВКонтакте'],
  ['whatsapp', 'WhatsApp'],
  ['max', 'MAX'],
  ['instagram', 'Instagram'],
  ['youtube', 'YouTube'],
];

const SITE_FIELDS = [
  { name: 'name', label: 'Название заведения', required: true },
  { name: 'description', label: 'Короткое описание', type: 'textarea', rows: 2, hint: 'Показывается, если на главной нет блока «Первый экран», и в поиске' },
  { name: 'phone', label: 'Телефон', half: true, placeholder: '+7 (999) 000-00-00' },
  { name: 'email', label: 'Email', type: 'email', half: true },
  { name: 'address', label: 'Адрес' },
  { name: 'workingHoursText', label: 'Часы работы (текстом)', placeholder: 'Ежедневно 10:00–23:00', hint: 'Для подвала и контактов. Расписание для брони — в разделе «График работы»' },
  { name: 'lat', label: 'Широта', type: 'number', step: 'any', half: true, advanced: true, hint: 'Для точной метки на карте, необязательно' },
  { name: 'lng', label: 'Долгота', type: 'number', step: 'any', half: true, advanced: true },
];
const SOCIAL_FIELDS = SOCIALS.map(([name, label]) => ({ name, label, half: true, placeholder: 'https://' }));
const SEO_FIELDS = [
  { name: 'seoTitle', label: 'Заголовок для поиска' },
  { name: 'seoDescription', label: 'Описание для поиска', type: 'textarea', rows: 2 },
  { name: 'ogImage', label: 'Картинка для соцсетей', type: 'image', hint: 'Показывается при отправке ссылки в мессенджерах, 1200×630' },
];

const section = (title, fields, values) =>
  h('div', { class: 'card card--pad' }, h('h2', { class: 'card__title' }, title), h('div', { class: 'form form--grid' }, visible(fields).map((f) => fieldEl(f, values[f.name]))));

const SHOP_FIELDS = [
  { name: 'deliveryFee', label: 'Доставка, ₽', type: 'money', half: true, hint: 'Стоимость курьерской доставки' },
  { name: 'freeFrom', label: 'Бесплатно от, ₽', type: 'money', half: true, hint: 'От этой суммы доставка бесплатная' },
  { name: 'deliveryNote', label: 'Срок доставки', half: true, nullable: true, placeholder: '2–5 дней' },
  { name: 'pickupAddress', label: 'Адрес самовывоза', half: true, nullable: true, placeholder: 'Москва, ул. Лавандовая, 7' },
];

/** Доставка и самовывоз — настройки магазина (settings.key = shop). */
export async function shopSettingsForm(root) {
  const config = await adminApi('/shop/settings');
  const form = h(
    'form',
    { class: 'form form--grid', novalidate: true },
    SHOP_FIELDS.map((f) => fieldEl(f, config[f.name])),
    h('p', { class: 'form__error', role: 'alert', hidden: true }),
    h('div', { class: 'savebar' }, h('button', { class: 'btn', type: 'submit' }, 'Сохранить')),
  );

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors(form);
    const data = collect(form, SHOP_FIELDS);
    try {
      await withPending(form, () => adminApi('/shop/settings', { method: 'PUT', body: data }));
      toast('Настройки доставки сохранены');
    } catch (error) {
      showErrors(form, error);
    }
  });

  add(root, h('div', { class: 'card card--pad' }, h('h2', { class: 'card__title' }, 'Доставка и самовывоз'), form));
}

export async function siteForm(root) {
  const site = await adminApi('/settings/site');
  const values = {
    ...site,
    lat: site.coordinates?.lat,
    lng: site.coordinates?.lng,
    ...site.socials,
    seoTitle: site.seo?.title,
    seoDescription: site.seo?.description,
    ogImage: site.seo?.ogImage,
  };
  const all = visible([...SITE_FIELDS, ...SOCIAL_FIELDS, ...(isFull() ? SEO_FIELDS : [])]);

  const form = h(
    'form',
    { class: 'form', novalidate: true },
    section('Заведение', SITE_FIELDS, values),
    section('Соцсети и мессенджеры', SOCIAL_FIELDS, values),
    isFull() && section('Поиск и соцсети', SEO_FIELDS, values),
    h('p', { class: 'form__error', role: 'alert', hidden: true }),
    h('div', { class: 'savebar' }, h('button', { class: 'btn', type: 'submit' }, 'Сохранить')),
  );

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors(form);
    if (!checkRequired(form, all)) return;
    const d = collect(form, all);
    const strip = (o) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== undefined && v !== ''));
    // Берём сохранённый объект целиком: в нём могут быть поля, которых нет в форме
    const body = {
      ...site,
      ...strip({ name: d.name, description: d.description, phone: d.phone, email: d.email, address: d.address, workingHoursText: d.workingHoursText }),
      socials: strip(Object.fromEntries(SOCIALS.map(([k]) => [k, d[k]]))),
    };
    // Очищенные поля удаляем, а не оставляем старое значение
    for (const k of ['description', 'phone', 'email', 'address', 'workingHoursText']) if (!d[k]) delete body[k];
    // Координаты и SEO редактируются только в полном режиме — иначе оставляем как было
    if (isFull()) {
      if (d.lat != null && d.lng != null) body.coordinates = { lat: d.lat, lng: d.lng };
      else delete body.coordinates;
      body.seo = strip({ title: d.seoTitle, description: d.seoDescription, ogImage: d.ogImage });
    }

    try {
      await withPending(form, () => adminApi('/settings/site', { method: 'PUT', body }));
      toast('Настройки сохранены');
      for (const el of document.querySelectorAll('[data-site-name]')) el.textContent = body.name;
    } catch (err) {
      showErrors(form, err);
    }
  });
  add(root, form);
}

function passwordForm(root) {
  const fields = [
    { name: 'currentPassword', label: 'Текущий пароль', type: 'password', required: true, autocomplete: 'current-password' },
    { name: 'newPassword', label: 'Новый пароль', type: 'password', required: true, half: true, autocomplete: 'new-password', hint: 'Не короче 8 символов' },
    { name: 'repeat', label: 'Ещё раз', type: 'password', required: true, half: true, autocomplete: 'new-password' },
  ];
  const form = h(
    'form',
    { class: 'form form--grid', novalidate: true },
    fields.map((f) => fieldEl(f)),
    h('p', { class: 'form__error', role: 'alert', hidden: true }),
    h('div', null, h('button', { class: 'btn btn--ghost', type: 'submit' }, 'Сменить пароль')),
  );
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors(form);
    if (!checkRequired(form, fields)) return;
    const { currentPassword, newPassword, repeat } = collect(form, fields);
    if (newPassword !== repeat) return showErrors(form, { message: '', details: [{ path: 'repeat', message: 'Пароли не совпадают' }] });
    try {
      await withPending(form, () => adminApi('/auth/change-password', { method: 'POST', body: { currentPassword, newPassword } }));
      form.reset();
      toast('Пароль изменён');
    } catch (err) {
      showErrors(form, err);
    }
  });
  add(root, h('div', { class: 'card card--pad link--spaced' }, h('h2', { class: 'card__title' }, 'Пароль администратора'), form));
}

const PASSWORD_FIELDS = [
  { name: 'currentPassword', label: 'Текущий пароль', type: 'password', required: true, autocomplete: 'current-password' },
  { name: 'newPassword', label: 'Новый пароль', type: 'password', required: true, half: true, autocomplete: 'new-password', hint: 'Не короче 8 символов' },
  { name: 'repeat', label: 'Ещё раз', type: 'password', required: true, half: true, autocomplete: 'new-password' },
];

/** Смена пароля в диалоге — ссылка в меню простого режима. */
export async function passwordDialog() {
  const ok = await formDialog({
    title: 'Сменить пароль',
    fields: PASSWORD_FIELDS,
    submitLabel: 'Сменить',
    onSubmit: ({ currentPassword, newPassword, repeat }) => {
      if (newPassword !== repeat) throw Object.assign(new Error(''), { details: [{ path: 'repeat', message: 'Пароли не совпадают' }] });
      return adminApi('/auth/change-password', { method: 'POST', body: { currentPassword, newPassword } });
    },
  });
  if (ok) toast('Пароль изменён');
}

export async function settingsView(root, ctx) {
  add(root, pageHeader('Настройки'));
  await siteForm(root);
  if (ctx?.has?.('shop')) await shopSettingsForm(root);
  passwordForm(root);
  add(root, extendNotice('Уведомления о заказах в Telegram, свой домен, онлайн-оплата, ещё один язык сайта?'));
}
