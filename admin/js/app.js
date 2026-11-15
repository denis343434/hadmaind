import { api } from '../../js/api.js';
import { $, $$, h, initDialogs } from '../../js/dom.js';
import { clearErrors, showErrors, withPending } from '../../js/forms.js';
import { setTimezone } from '../../js/format.js';
import { adminApi, auth, setUnauthorizedHandler } from './api.js';
import { isDevOverride, isFull } from './mode.js';
import { dashboardView } from './views/dashboard.js';
import { bookingsView } from './views/bookings.js';
import { leadsView } from './views/leads.js';
import { menuView } from './views/menu.js';
import { catalogView } from './views/catalog.js';
import { ordersView } from './views/orders.js';
import { shopView } from './views/shop.js';
import { pagesView } from './views/pages.js';
import { passwordDialog, settingsView } from './views/settings.js';
import { todayView } from './views/today.js';
import { siteView } from './views/site.js';

// advanced — только в полном режиме, simple — только в простом (см. mode.js)
const ROUTES = [
  { path: 'today', title: 'Сегодня', view: todayView, simple: true },
  { path: 'dashboard', title: 'Сводка', view: dashboardView, advanced: true },
  { path: 'orders', title: 'Заказы', view: ordersView, module: 'shop', badge: 'orders' },
  { path: 'bookings', title: 'Брони', view: bookingsView, module: 'booking', badge: 'bookings' },
  { path: 'leads', title: 'Заявки', view: leadsView, module: 'leads', badge: 'leads' },
  { path: 'shop', title: 'Товары', view: shopView, module: 'shop' },
  { path: 'menu', title: 'Меню', view: menuView, module: 'menu' },
  // Простой режим: тексты, контакты, доставка и часы работы — в одном разделе «Сайт»
  { path: 'site', title: 'Сайт', view: siteView, simple: true },
  { path: 'booking-settings', title: 'Услуги и график', view: catalogView, module: 'booking', advanced: true },
  { path: 'pages', title: 'Страницы', view: pagesView, module: 'pages', advanced: true },
  { path: 'settings', title: 'Настройки', view: settingsView, advanced: true },
];

const ctx = {
  site: null,
  has: (m) => ctx.site?.modules.includes(m),
  refreshBadges,
  reload: () => render(),
  navigate: (path) => {
    location.hash = `#/${path}`;
  },
};

const routes = () =>
  ROUTES.filter((r) => (!r.module || ctx.has(r.module)) && (r.advanced ? isFull() : r.simple ? !isFull() : true));

function parseHash() {
  const [path, qs] = location.hash.replace(/^#\/?/, '').split('?');
  return { path: path || 'dashboard', query: Object.fromEntries(new URLSearchParams(qs ?? '')) };
}

// ─── Навигация и счётчики ───────────────────────────────

function renderSidebar() {
  $('#sidebar-nav').replaceChildren(
    ...routes().map((r) =>
      h('a', { href: `#/${r.path}`, 'data-path': r.path }, h('span', null, r.title), r.badge && h('span', { class: 'badge', 'data-badge': r.badge })),
    ),
  );
  $('#admin-email').textContent = auth.email ?? '';
  // В простом режиме нет раздела «Настройки» — смена пароля здесь
  if (!isFull() && !document.querySelector('#change-password')) {
    $('#logout').before(h('button', { class: 'link', type: 'button', id: 'change-password', onclick: passwordDialog }, 'Сменить пароль'));
  }
  // Разработчик включил полный режим у себя в браузере — напоминаем, как выйти
  if (isDevOverride() && !document.querySelector('.dev-mode')) {
    $('#admin-email').before(h('a', { class: 'dev-mode', href: '?full=0', title: 'Вернуть простой режим заказчика' }, 'Режим разработчика · выйти'));
  }
}

async function refreshBadges() {
  const set = (name, n) => {
    const el = document.querySelector(`[data-badge="${name}"]`);
    if (el) el.textContent = n > 0 ? String(n) : '';
  };
  const [dash, leads, orders] = await Promise.all([
    ctx.has('booking') ? adminApi('/booking/dashboard').catch(() => null) : null,
    ctx.has('leads') ? adminApi('/leads/stats').catch(() => null) : null,
    ctx.has('shop') ? adminApi('/shop/dashboard').catch(() => null) : null,
  ]);
  if (dash) set('bookings', dash.pendingCount);
  if (leads) set('leads', leads.new ?? 0);
  if (orders) set('orders', orders.newCount ?? 0);
  return { dash, leads, orders };
}

// ─── Роутер ─────────────────────────────────────────────

let renderId = 0;

async function render() {
  if (!auth.token) return showLogin();
  const { path, query } = parseHash();
  const route = routes().find((r) => r.path === path) ?? routes()[0];

  for (const a of $$('#sidebar-nav a')) {
    if (a.dataset.path === route.path) a.setAttribute('aria-current', 'page');
    else a.removeAttribute('aria-current');
  }
  document.title = `${route.title} — админка`;

  const id = ++renderId;
  const view = $('#view');
  view.replaceChildren(h('div', { class: 'empty' }, 'Загрузка…'));
  const root = h('div');
  try {
    await route.view(root, { ...ctx, query });
    if (id === renderId) view.replaceChildren(root);
  } catch (err) {
    if (id === renderId) view.replaceChildren(h('div', { class: 'empty' }, `Не удалось загрузить раздел: ${err.message}`));
  }
}

// ─── Вход ───────────────────────────────────────────────

function showLogin() {
  $('#shell').hidden = true;
  $('#login').hidden = false;
  document.title = 'Вход — админка';
  $('#login-form').elements.email.value ||= auth.email ?? '';
  $('#login-form').elements[auth.email ? 'password' : 'email'].focus();
}

async function showShell() {
  $('#login').hidden = true;
  $('#shell').hidden = false;
  renderSidebar();
  await render();
  refreshBadges();
}

function initLogin() {
  const form = $('#login-form');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors(form);
    const email = form.elements.email.value.trim();
    const password = form.elements.password.value;
    if (!email || !password) {
      showErrors(form, { message: 'Введите email и пароль' });
      return;
    }
    try {
      const { token, admin } = await withPending(form, () =>
        adminApi('/auth/login', { method: 'POST', body: { email, password } }),
      );
      auth.login(token, admin.email);
      form.elements.password.value = '';
      showShell();
    } catch (err) {
      showErrors(form, err);
    }
  });

  $('#logout').addEventListener('click', () => {
    auth.logout();
    showLogin();
  });
}

async function main() {
  initDialogs();
  initLogin();

  try {
    ctx.site = await api('/site');
  } catch (err) {
    document.body.replaceChildren(h('p', { class: 'empty' }, `Сервер недоступен: ${err.message}`));
    return;
  }
  setTimezone(ctx.site.timezone);
  for (const el of $$('[data-site-name]')) el.textContent = ctx.site.name;

  // Протухший токен → обратно на вход
  setUnauthorizedHandler(showLogin);
  window.addEventListener('hashchange', render);
  // Счётчики новых броней и заявок обновляются раз в минуту
  setInterval(() => auth.token && !$('#shell').hidden && !document.hidden && refreshBadges(), 60_000);

  if (!auth.token) return showLogin();
  try {
    await adminApi('/auth/me');
    showShell();
  } catch {
    showLogin();
  }
}

main();
