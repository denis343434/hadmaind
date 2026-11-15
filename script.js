/* Handmade - общие сценарии, корзина и оформление заказа. */

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

const CART_KEY = 'hm_cart_items';
const FAV_KEY = 'hm_favs';
const CUSTOMER_KEY = 'hm_customer';
const ORDERS_KEY = 'hm_orders';

const DELIVERY_FEE_DEFAULT = 390;
const FREE_FROM_DEFAULT = 3000;

/* Значения подставляются из настроек магазина на сервере (см. products.js). */
let DELIVERY_FEE = SHOP.deliveryFee || DELIVERY_FEE_DEFAULT;
let FREE_FROM = SHOP.freeFrom || FREE_FROM_DEFAULT;

const toast = $('#toast');
let toastTimer;
function notify(message) {
  if (!toast) return;
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
}

function formatPrice(value) {
  return Number(value).toLocaleString('ru-RU').replace(/\u00A0/g, ' ') + ' ₽';
}

/* ---------- корзина: состояние ---------- */
function getCartItems() {
  try {
    const list = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
    return Array.isArray(list) ? list.filter(item => item && findProduct(item.id) && item.qty > 0) : [];
  } catch (error) {
    return [];
  }
}

function saveCartItems(items) {
  localStorage.setItem(CART_KEY, JSON.stringify(items));
  renderCart();
  window.dispatchEvent(new CustomEvent('cart:change'));
}

function cartQuantity() {
  return getCartItems().reduce((sum, item) => sum + item.qty, 0);
}

function cartSubtotal(items = getCartItems()) {
  return items.reduce((sum, item) => sum + findProduct(item.id).price * item.qty, 0);
}

function deliveryCost(subtotal, method = 'delivery') {
  if (method === 'pickup' || subtotal === 0 || subtotal >= FREE_FROM) return 0;
  return DELIVERY_FEE;
}

function addToCart(id, amount = 1) {
  const product = findProduct(id);
  if (!product) return;
  const items = getCartItems();
  const existing = items.find(item => item.id === id);
  if (existing) existing.qty = Math.min(99, existing.qty + amount);
  else items.push({ id, qty: Math.min(99, amount) });
  saveCartItems(items);
  notify(`«${product.title}» в корзине`);
}

function setItemQuantity(id, qty) {
  const items = getCartItems();
  const item = items.find(entry => entry.id === id);
  if (!item) return;
  item.qty = Math.max(0, Math.min(99, qty));
  saveCartItems(items.filter(entry => entry.qty > 0));
}

function removeItem(id) {
  saveCartItems(getCartItems().filter(item => item.id !== id));
}

function renderCart() {
  const count = cartQuantity();
  const badge = $('#cartCount');
  if (badge) badge.textContent = count;
  renderDrawer();
}

/* ---------- корзина: панель ---------- */
const drawer = document.createElement('div');
drawer.className = 'cart-shell';
drawer.innerHTML = `
  <div class="cart-overlay" data-close-cart></div>
  <aside class="cart-drawer" id="cartDrawer" role="dialog" aria-modal="true" aria-label="Корзина" aria-hidden="true">
    <header class="cart-head">
      <h2>Корзина <span id="cartDrawerCount"></span></h2>
      <button class="cart-close" type="button" aria-label="Закрыть корзину" data-close-cart>✕</button>
    </header>

    <div class="cart-body">
      <div class="cart-items" id="cartItems"></div>
      <div class="cart-empty" id="cartEmpty" hidden>
        <div class="cart-empty-heart">♡</div>
        <p>В корзине пока пусто.<br>Посмотрите украшения и игрушки ручной работы.</p>
        <a class="btn btn-primary" href="catalog.html">Перейти в каталог</a>
      </div>
    </div>

    <footer class="cart-foot" id="cartFoot" hidden>
      <div class="cart-row"><span>Товары</span><b id="cartSubtotal">0 ₽</b></div>
      <div class="cart-row"><span>Доставка</span><b id="cartDelivery">0 ₽</b></div>
      <p class="cart-note" id="cartNote">Бесплатная доставка от 3 000 ₽</p>
      <div class="cart-total"><span>Итого</span><b id="cartTotal">0 ₽</b></div>
      <a class="btn btn-primary cart-checkout" href="checkout.html">Оформить заказ <span class="btn-arrow">→</span></a>
      <button class="cart-continue" type="button" data-close-cart>Продолжить покупки</button>
    </footer>
  </aside>`;
document.body.appendChild(drawer);

const drawerPanel = $('#cartDrawer', drawer);
drawerPanel.inert = true;
let lastFocused = null;

function openCart() {
  const items = getCartItems();
  if (!items.length) {
    renderDrawer();
  }
  lastFocused = document.activeElement;
  drawer.classList.add('is-open');
  drawerPanel.inert = false;
  drawerPanel.setAttribute('aria-hidden', 'false');
  document.body.classList.add('cart-open');
  const close = $('.cart-close', drawer);
  if (close) close.focus();
}

function closeCart() {
  drawer.classList.remove('is-open');
  drawerPanel.inert = true;
  drawerPanel.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('cart-open');
  if (lastFocused && lastFocused.focus) lastFocused.focus();
}

function drawerItem(item) {
  const product = findProduct(item.id);
  const url = `product.html?id=${encodeURIComponent(product.id)}`;
  return `
    <article class="cart-item" data-id="${product.id}">
      <a class="cart-thumb" href="${url}">
        <img class="${tintClass(product)}" src="${product.image}" alt="${escapeHTML(product.title)}">
      </a>
      <div class="cart-item-body">
        <a class="cart-item-title" href="${url}">${escapeHTML(product.title)}</a>
        <span class="cart-item-price">${formatPrice(product.price)}</span>
        <div class="cart-item-actions">
          <div class="qty qty-sm">
            <button type="button" data-cart-step="-1" ${item.qty <= 1 ? 'disabled' : ''} aria-label="Уменьшить количество">−</button>
            <input value="${item.qty}" readonly aria-label="Количество">
            <button type="button" data-cart-step="1" aria-label="Увеличить количество">+</button>
          </div>
          <button type="button" class="cart-remove" data-cart-remove>Удалить</button>
        </div>
      </div>
      <b class="cart-item-total">${formatPrice(product.price * item.qty)}</b>
    </article>`;
}

function renderDrawer() {
  const items = getCartItems();
  const list = $('#cartItems');
  const empty = $('#cartEmpty');
  const foot = $('#cartFoot');
  const countLabel = $('#cartDrawerCount');
  if (!list || !empty || !foot) return;

  const quantity = items.reduce((sum, item) => sum + item.qty, 0);
  if (countLabel) countLabel.textContent = quantity ? `${quantity} шт.` : '';

  if (!items.length) {
    list.innerHTML = '';
    list.hidden = true;
    empty.hidden = false;
    foot.hidden = true;
    return;
  }

  list.hidden = false;
  empty.hidden = true;
  foot.hidden = false;
  list.innerHTML = items.map(drawerItem).join('');

  const subtotal = cartSubtotal(items);
  const delivery = deliveryCost(subtotal, 'delivery');
  $('#cartSubtotal').textContent = formatPrice(subtotal);
  $('#cartDelivery').textContent = delivery ? formatPrice(delivery) : 'Бесплатно';
  $('#cartTotal').textContent = formatPrice(subtotal + delivery);
  const note = $('#cartNote');
  if (note) {
    note.textContent = subtotal >= FREE_FROM
      ? 'Доставка бесплатная - спасибо за заказ!'
      : `До бесплатной доставки: ${formatPrice(FREE_FROM - subtotal)}`;
  }
}

/* ---------- избранное ---------- */
function getFavorites() {
  try {
    const list = JSON.parse(localStorage.getItem(FAV_KEY) || '[]');
    return Array.isArray(list) ? list : [];
  } catch (error) {
    return [];
  }
}

function saveFavorites(list) {
  localStorage.setItem(FAV_KEY, JSON.stringify(list));
  syncFavorites();
}

function syncFavorites() {
  const favorites = getFavorites();
  $$('.favorite').forEach(button => {
    const active = favorites.includes(button.dataset.id);
    button.classList.toggle('active', active);
    button.textContent = active ? '♥' : '♡';
  });
  const big = $('#ppFav');
  if (big) {
    const active = favorites.includes(big.dataset.id);
    big.classList.toggle('active', active);
    big.textContent = active ? '♥' : '♡';
  }
}

function toggleFavorite(id) {
  const favorites = getFavorites();
  const index = favorites.indexOf(id);
  if (index === -1) favorites.push(id);
  else favorites.splice(index, 1);
  saveFavorites(favorites);
  notify(index === -1 ? 'Добавлено в избранное' : 'Удалено из избранного');
}

/* ---------- меню ---------- */
const menuButton = $('.menu-toggle');
const nav = $('.nav');
if (menuButton && nav) {
  const setMenu = open => {
    nav.classList.toggle('open', open);
    document.body.classList.toggle('menu-open', open);
    menuButton.setAttribute('aria-expanded', String(open));
    menuButton.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    menuButton.textContent = open ? '✕' : '☰';
  };

  menuButton.addEventListener('click', () => setMenu(!nav.classList.contains('open')));
  $$('a', nav).forEach(link => link.addEventListener('click', () => setMenu(false)));
  nav.addEventListener('click', event => {
    if (event.target === nav) setMenu(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && nav.classList.contains('open')) setMenu(false);
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 820 && nav.classList.contains('open')) setMenu(false);
  });
}

/* ---------- общие обработчики ---------- */
document.addEventListener('click', event => {
  if (event.target.closest('.bag')) {
    event.preventDefault();
    openCart();
    return;
  }

  if (event.target.closest('[data-close-cart]')) {
    closeCart();
    return;
  }

  const cartButton = event.target.closest('.add-cart');
  if (cartButton) {
    addToCart(cartButton.dataset.id || '');
    return;
  }

  const favoriteButton = event.target.closest('.favorite');
  if (favoriteButton) {
    toggleFavorite(favoriteButton.dataset.id || '');
    return;
  }

  const stepButton = event.target.closest('[data-cart-step]');
  if (stepButton) {
    const item = stepButton.closest('.cart-item');
    const current = getCartItems().find(entry => entry.id === item.dataset.id);
    if (current) {
      const next = current.qty + Number(stepButton.dataset.cartStep);
      if (next >= 1) setItemQuantity(current.id, next);
    }
    return;
  }

  const removeButton = event.target.closest('[data-cart-remove]');
  if (removeButton) {
    const item = removeButton.closest('.cart-item');
    removeItem(item.dataset.id);
    notify('Товар удалён из корзины');
  }
});

document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && drawer.classList.contains('is-open')) closeCart();
});

/* ---------- разметка карточки товара ---------- */
function escapeHTML(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function tintClass(product) {
  if (product.tone === 'neutral') return 'tint tint-neutral';
  if (product.tone === 'photo') return 'tint tint-photo';
  return 'tint';
}

function productCard(product) {
  const url = `product.html?id=${encodeURIComponent(product.id)}`;
  return `
    <article class="product" data-id="${product.id}">
      <div class="product-image">
        <a class="product-link" href="${url}" aria-label="${escapeHTML(product.title)}">
          <img class="${tintClass(product)}${product.zoom ? ' is-zoomed' : ''}" src="${product.image}" alt="${escapeHTML(product.title)}" loading="lazy"${product.zoom ? ` style="--z:${product.zoom}"` : ''}>
        </a>
        <button class="favorite" data-id="${product.id}" aria-label="В избранное">♡</button>
        <b class="price">${formatPrice(product.price)}</b>
        <button class="add-cart" data-id="${product.id}">В корзину</button>
      </div>
      <div class="product-info">
        <h3><a href="${url}">${escapeHTML(product.title)}</a></h3>
      </div>
    </article>`;
}

/* ---------- поиск: модальное окно ---------- */
function initSearch() {
  const shell = document.createElement('div');
  shell.className = 'search-shell';
  shell.innerHTML = `
    <div class="search-overlay" data-close-search></div>
    <div class="search-panel" role="dialog" aria-modal="true" aria-label="Поиск по каталогу" aria-hidden="true">
      <div class="search-head">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.2"/><path d="m15.6 15.6 4.4 4.4"/></svg>
        <input id="searchInput" type="search" placeholder="Что вы ищете? Например: браслет или зайка" autocomplete="off" aria-label="Поиск по каталогу">
        <button class="search-close" type="button" aria-label="Закрыть поиск" data-close-search>✕</button>
      </div>

      <div class="search-body">
        <div class="search-hints" id="searchHints">
          <span>Популярное:</span>
          <button type="button" data-search-hint="браслет">браслет</button>
          <button type="button" data-search-hint="серьги">серьги</button>
          <button type="button" data-search-hint="колье">колье</button>
          <button type="button" data-search-hint="зайка">зайка</button>
          <button type="button" data-search-hint="мишка">мишка</button>
          <button type="button" data-search-hint="подарок">подарок</button>
        </div>
        <div class="search-results" id="searchResults"></div>
        <p class="search-empty" id="searchEmpty" hidden></p>
      </div>

      <div class="search-foot" id="searchFoot" hidden>
        <span id="searchCount"></span>
        <a class="search-all" id="searchAll" href="catalog.html">Показать все результаты →</a>
      </div>
    </div>`;
  document.body.appendChild(shell);

  const panel = $('.search-panel', shell);
  panel.inert = true;
  const input = $('#searchInput', shell);
  const results = $('#searchResults', shell);
  const empty = $('#searchEmpty', shell);
  const hints = $('#searchHints', shell);
  const foot = $('#searchFoot', shell);
  const countLabel = $('#searchCount', shell);
  const allLink = $('#searchAll', shell);
  let lastFocused = null;

  function itemMarkup(product) {
    const category = CATEGORIES[product.cat];
    return `
      <a class="search-item" href="product.html?id=${encodeURIComponent(product.id)}">
        <span class="search-thumb"><img class="${tintClass(product)}" src="${product.image}" alt=""></span>
        <span class="search-info">
          <b>${escapeHTML(product.title)}</b>
          <i>${escapeHTML(category.short)} · ${formatPrice(product.price)}</i>
        </span>
        <span class="search-arrow">→</span>
      </a>`;
  }

  function render(raw) {
    const query = String(raw || '').trim();
    if (!query) {
      hints.hidden = false;
      results.innerHTML = '';
      empty.hidden = true;
      foot.hidden = true;
      return;
    }

    const needle = query.toLowerCase();
    const found = PRODUCTS.filter(product => {
      const haystack = `${product.title} ${CATEGORIES[product.cat].short} ${product.short}`.toLowerCase();
      return haystack.includes(needle);
    });

    hints.hidden = true;
    results.innerHTML = found.slice(0, 6).map(itemMarkup).join('');
    empty.hidden = found.length > 0;
    empty.textContent = `По запросу «${query}» ничего не найдено. Попробуйте «браслет» или «зайка».`;
    foot.hidden = found.length === 0;
    if (found.length) {
      countLabel.textContent = `${found.length} ${plural(found.length, 'товар', 'товара', 'товаров')}`;
      allLink.href = `catalog.html?q=${encodeURIComponent(query)}`;
    }
  }

  function open() {
    lastFocused = document.activeElement;
    input.value = '';
    render('');
    shell.classList.add('is-open');
    panel.inert = false;
    panel.setAttribute('aria-hidden', 'false');
    document.body.classList.add('search-open');
    requestAnimationFrame(() => input.focus());
  }

  function close() {
    shell.classList.remove('is-open');
    panel.inert = true;
    panel.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('search-open');
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  shell.addEventListener('click', event => {
    if (event.target.closest('[data-close-search]')) {
      close();
      return;
    }
    const hint = event.target.closest('[data-search-hint]');
    if (hint) {
      input.value = hint.dataset.searchHint;
      render(input.value);
      input.focus();
    }
  });

  input.addEventListener('input', () => render(input.value));

  input.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      const first = results.querySelector('.search-item');
      if (first) window.location.href = first.getAttribute('href');
      else if (input.value.trim()) window.location.href = `catalog.html?q=${encodeURIComponent(input.value.trim())}`;
    }
    if (event.key === 'ArrowDown') {
      const first = results.querySelector('.search-item');
      if (first) {
        event.preventDefault();
        first.focus();
      }
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && shell.classList.contains('is-open')) close();
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      if (shell.classList.contains('is-open')) close();
      else open();
    }
  });

  document.addEventListener('click', event => {
    if (event.target.closest('#searchBtn')) {
      event.preventDefault();
      open();
    }
  });
}

/* ---------- страница каталога ---------- */
function initCatalog() {
  const grid = $('#catalogGrid');
  if (!grid) return;

  /* Фильтры строим из категорий API — новая категория появляется на сайте сама. */
  const chipsBox = $('.catalog-toolbar .chips');
  if (chipsBox) {
    chipsBox.innerHTML = Object.entries(CATEGORIES)
      .map(([slug, category]) => {
        const label = slug === 'all' ? 'Все' : category.short;
        return `<button class="chip${slug === 'all' ? ' is-active' : ''}" data-cat="${escapeHTML(slug)}" aria-pressed="${slug === 'all'}">${escapeHTML(label)}</button>`;
      })
      .join('');
  }

  const chips = $$('.chip', chipsBox || document);
  const title = $('#catalogTitle');
  const sub = $('#catalogSub');
  const crumb = $('#crumbCat');
  const count = $('#catalogCount');
  const empty = $('#catalogEmpty');
  const params = new URLSearchParams(window.location.search);
  let activeCategory = params.get('cat') || 'all';
  if (!CATEGORIES[activeCategory]) activeCategory = 'all';
  let query = (params.get('q') || '').trim();

  function render() {
    const category = CATEGORIES[activeCategory];
    const needle = query.toLowerCase();
    const items = PRODUCTS.filter(product => {
      const matchesCategory = activeCategory === 'all' || product.cat === activeCategory;
      const haystack = `${product.title} ${product.short || ''}`.toLowerCase();
      const matchesQuery = !needle || haystack.includes(needle);
      return matchesCategory && matchesQuery;
    });

    grid.innerHTML = items.map(productCard).join('');
    syncFavorites();

    chips.forEach(chip => {
      const isActive = chip.dataset.cat === activeCategory;
      chip.classList.toggle('is-active', isActive);
      chip.setAttribute('aria-pressed', String(isActive));
    });

    if (title) title.textContent = query ? `Поиск: «${query}»` : category.title;
    if (sub) sub.textContent = query ? 'Результаты поиска по каталогу' : category.sub;
    if (crumb) crumb.textContent = query ? 'Поиск' : category.short;
    if (count) {
      count.textContent = items.length
        ? `${items.length} ${plural(items.length, 'изделие', 'изделия', 'изделий')}`
        : '';
    }
    if (empty) empty.hidden = items.length > 0;
    document.title = `${query ? 'Поиск' : category.short} - Handmade`;
  }

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      activeCategory = chip.dataset.cat;
      query = '';
      const next = new URL(window.location.href);
      if (activeCategory === 'all') next.searchParams.delete('cat');
      else next.searchParams.set('cat', activeCategory);
      next.searchParams.delete('q');
      history.replaceState(null, '', next);
      render();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  window.addEventListener('popstate', () => {
    const search = new URLSearchParams(window.location.search);
    activeCategory = CATEGORIES[search.get('cat')] ? search.get('cat') : 'all';
    query = (search.get('q') || '').trim();
    render();
  });

  render();
}

/* ---------- главная: популярные товары ---------- */
function initFeatured() {
  const grid = $('#featuredGrid');
  if (!grid) return;
  const featured = PRODUCTS.filter(product => product.featured);
  const items = (featured.length ? featured : PRODUCTS).slice(0, 4);
  grid.innerHTML = items.map(productCard).join('');
  syncFavorites();
}

/* ---------- страница товара ---------- */
function initProductPage() {
  const grid = $('.pp-grid');
  if (!grid) return;

  const id = new URLSearchParams(window.location.search).get('id');
  const product = findProduct(id);

  if (!product) {
    grid.innerHTML = `
      <div class="pp-missing">
        <p class="eyebrow">Каталог</p>
        <h1>Товар не найден</h1>
        <p>Возможно, это изделие уже продано. Посмотрите другие работы в каталоге.</p>
        <a class="btn btn-primary" href="catalog.html">Перейти в каталог</a>
      </div>`;
    return;
  }

  const category = CATEGORIES[product.cat];
  const media = $('#ppImage');
  const title = $('#ppTitle');
  const price = $('#ppPrice');
  const short = $('#ppShort');
  const desc = $('#ppDesc');
  const specs = $('#ppSpecs');
  const badge = $('#ppBadge');
  const crumb = $('#ppCrumb');
  const categoryLink = $('#ppCatLink');

  if (media) {
    media.src = product.image;
    media.alt = product.title;
    media.className = tintClass(product) + (product.zoom ? ' is-zoomed' : '');
    if (product.zoom) media.style.setProperty('--z', product.zoom);
  }
  if (title) title.textContent = product.title;
  if (price) price.textContent = formatPrice(product.price);
  if (short) short.textContent = product.short;
  if (desc) desc.textContent = product.desc;
  if (specs) {
    specs.innerHTML = product.specs
      .map(([name, value]) => `<div><dt>${escapeHTML(name)}</dt><dd>${escapeHTML(value)}</dd></div>`)
      .join('');
  }
  if (badge) {
    badge.textContent = product.badge;
    badge.hidden = !product.badge;
  }
  if (crumb) crumb.textContent = product.title;
  if (categoryLink) {
    categoryLink.textContent = category.short;
    categoryLink.href = `catalog.html?cat=${product.cat}`;
  }

  document.title = `${product.title} - Handmade`;
  const descriptionMeta = $('meta[name="description"]');
  if (descriptionMeta) descriptionMeta.setAttribute('content', product.short);

  const qtyInput = $('#ppQty');
  $$('.qty button').forEach(button => {
    button.addEventListener('click', () => {
      const step = Number(button.dataset.step);
      const next = Math.min(9, Math.max(1, Number(qtyInput.value) + step));
      qtyInput.value = next;
    });
  });

  const addButton = $('#ppAdd');
  if (addButton) {
    addButton.addEventListener('click', () => {
      addToCart(product.id, Number(qtyInput.value) || 1);
    });
  }

  const favButton = $('#ppFav');
  if (favButton) {
    favButton.dataset.id = product.id;
    favButton.addEventListener('click', () => toggleFavorite(product.id));
  }
  syncFavorites();

  const relatedGrid = $('#relatedGrid');
  if (relatedGrid) {
    const related = PRODUCTS.filter(item => item.cat === product.cat && item.id !== product.id);
    const extra = PRODUCTS.filter(item => item.cat !== product.cat && item.id !== product.id);
    relatedGrid.innerHTML = related.concat(extra).slice(0, 4).map(productCard).join('');
    syncFavorites();
  }
}

/* ---------- маска телефона ---------- */
function formatPhoneValue(raw) {
  let digits = String(raw).replace(/\D/g, '');
  if (!digits) return '';
  if (digits[0] === '8') digits = '7' + digits.slice(1);
  if (digits[0] !== '7') digits = '7' + digits;
  digits = digits.slice(0, 11);
  const national = digits.slice(1);
  let out = '+7';
  if (national.length) out += ` (${national.slice(0, 3)}`;
  if (national.length >= 3) out += ')';
  if (national.length > 3) out += ` ${national.slice(3, 6)}`;
  if (national.length > 6) out += `-${national.slice(6, 8)}`;
  if (national.length > 8) out += `-${national.slice(8, 10)}`;
  return out;
}

/** Маска +7 (900) 123-45-67: ввод, вставка, удаление и правка скобок/дефисов. */
function applyPhoneMask(input) {
  if (!input) return;

  const format = () => {
    input.value = formatPhoneValue(input.value);
    input.setSelectionRange(input.value.length, input.value.length);
  };

  input.addEventListener('input', event => {
    if (event.isComposing) return;
    format();
  });

  input.addEventListener('blur', () => {
    input.value = formatPhoneValue(input.value);
  });

  input.addEventListener('beforeinput', event => {
    if (event.inputType !== 'deleteContentBackward' && event.inputType !== 'deleteContentForward') return;
    const start = input.selectionStart ?? 0;
    const end = input.selectionEnd ?? 0;
    if (start !== end || start === 0) return;
    const index = event.inputType === 'deleteContentBackward' ? start - 1 : start;
    const char = input.value[index];
    if (char === undefined || /\d/.test(char)) return;

    /* Курсор стоит после скобки/дефиса — удаляем ближайшую цифру, формат восстановится сам. */
    event.preventDefault();
    const chars = input.value.split('');
    if (event.inputType === 'deleteContentBackward') {
      for (let i = index; i >= 0; i -= 1) {
        if (/\d/.test(chars[i])) { chars.splice(i, 1); break; }
      }
    } else {
      for (let i = index; i < chars.length; i += 1) {
        if (/\d/.test(chars[i])) { chars.splice(i, 1); break; }
      }
    }
    input.value = formatPhoneValue(chars.join(''));
    input.setSelectionRange(input.value.length, input.value.length);
  });

  if (input.value.trim()) input.value = formatPhoneValue(input.value);
}

/* ---------- оформление заказа ---------- */
function initCheckout() {
  const form = $('#checkoutForm');
  if (!form) return;

  const items = getCartItems();
  const summaryList = $('#coItems');
  const emptyState = $('#checkoutEmpty');
  const layout = $('#checkoutLayout');
  const success = $('#orderSuccess');

  if (!items.length) {
    if (layout) layout.hidden = true;
    if (emptyState) emptyState.hidden = false;
    return;
  }

  /* Подписи доставки из настроек магазина в админке */
  const pickupNote = $('#coPickupNote');
  if (pickupNote && SHOP.pickupAddress) pickupNote.textContent = `${SHOP.pickupAddress} · бесплатно`;
  const deliveryLabel = $('#coDeliveryLabel');
  if (deliveryLabel) {
    deliveryLabel.textContent = `${SHOP.deliveryNote ? SHOP.deliveryNote + ' · ' : ''}${formatPrice(DELIVERY_FEE)}, бесплатно от ${formatPrice(FREE_FROM)}`;
  }

  function currentItems() {
    return getCartItems();
  }

  function summaryMarkup(items) {
    return items.map(item => {
      const product = findProduct(item.id);
      return `
        <div class="co-item">
          <span class="co-thumb"><img class="${tintClass(product)}" src="${product.image}" alt="${escapeHTML(product.title)}"></span>
          <span class="co-item-name">${escapeHTML(product.title)}<i>${item.qty} × ${formatPrice(product.price)}</i></span>
          <b>${formatPrice(product.price * item.qty)}</b>
        </div>`;
    }).join('');
  }

  function currentMethod() {
    const checked = form.querySelector('input[name="delivery"]:checked');
    return checked ? checked.value : 'delivery';
  }

  function renderSummary() {
    const items = currentItems();
    if (!items.length) {
      if (layout) layout.hidden = true;
      if (emptyState) emptyState.hidden = false;
      return;
    }
    const subtotal = cartSubtotal(items);
    const delivery = deliveryCost(subtotal, currentMethod());
    if (summaryList) summaryList.innerHTML = summaryMarkup(items);
    $('#coSubtotal').textContent = formatPrice(subtotal);
    $('#coDelivery').textContent = delivery ? formatPrice(delivery) : 'Бесплатно';
    $('#coTotal').textContent = formatPrice(subtotal + delivery);
    const note = $('#coNote');
    if (note) {
      note.textContent = subtotal >= FREE_FROM
        ? 'Бесплатная доставка применена'
        : `Бесплатно от ${formatPrice(FREE_FROM)}`;
    }
  }

  /* подстановка сохранённых контактов */
  try {
    const saved = JSON.parse(localStorage.getItem(CUSTOMER_KEY) || '{}');
    if (saved.name) form.elements.name.value = saved.name;
    if (saved.phone) form.elements.phone.value = saved.phone;
    if (saved.email) form.elements.email.value = saved.email;
  } catch (error) { /* пусто */ }

  applyPhoneMask(form.elements.phone);

  /* переключение доставки */
  const deliveryFields = $('#deliveryFields');
  $$('input[name="delivery"]', form).forEach(radio => {
    radio.addEventListener('change', () => {
      if (deliveryFields) deliveryFields.hidden = currentMethod() === 'pickup';
      renderSummary();
    });
  });
  if (deliveryFields) deliveryFields.hidden = currentMethod() === 'pickup';

  renderSummary();
  window.addEventListener('cart:change', renderSummary);

  function setError(field, message) {
    const wrap = field.closest('.field');
    if (!wrap) return;
    const error = $('.field-error', wrap);
    wrap.classList.toggle('invalid', Boolean(message));
    if (error) {
      error.textContent = message || '';
      error.style.display = message ? 'block' : 'none';
    }
  }

  function validate() {
    const fields = form.elements;
    const errors = [];

    const name = fields.name;
    if (name.value.trim().length < 2) {
      setError(name, 'Укажите имя - хотя бы 2 символа');
      errors.push(name);
    } else setError(name, '');

    const phone = fields.phone;
    const digits = phone.value.replace(/\D/g, '');
    if (digits.length < 10) {
      setError(phone, 'Введите номер телефона полностью');
      errors.push(phone);
    } else setError(phone, '');

    const email = fields.email;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) {
      setError(email, 'Проверьте адрес электронной почты');
      errors.push(email);
    } else setError(email, '');

    const method = currentMethod();
    if (method === 'delivery') {
      const city = fields.city;
      if (city.value.trim().length < 2) {
        setError(city, 'Укажите город');
        errors.push(city);
      } else setError(city, '');

      const address = fields.address;
      if (address.value.trim().length < 5) {
        setError(address, 'Укажите улицу, дом и квартиру');
        errors.push(address);
      } else setError(address, '');
    }

    const consent = fields.consent;
    if (!consent.checked) {
      notify('Подтвердите согласие на обработку данных');
      errors.push(consent);
    }

    return errors;
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const errors = validate();
    if (errors.length) {
      const first = errors[0];
      if (first && first.focus) first.focus();
      if (!first || first.type !== 'checkbox') notify('Проверьте выделенные поля');
      return;
    }

    const fields = form.elements;
    const items = currentItems();
    if (!items.length) {
      renderSummary();
      return;
    }
    const subtotal = cartSubtotal(items);
    const method = currentMethod();
    const delivery = deliveryCost(subtotal, method);
    const total = subtotal + delivery;
    const customer = {
      name: fields.name.value.trim(),
      phone: fields.phone.value.trim(),
      email: fields.email.value.trim()
    };
    const deliveryInfo = {
      method,
      city: method === 'delivery' ? fields.city.value.trim() : '',
      address: method === 'delivery' ? fields.address.value.trim() : '',
      comment: fields.comment.value.trim()
    };
    const payment = (form.querySelector('input[name="payment"]:checked') || {}).value || 'card';

    const submitButton = form.querySelector('[type="submit"]');
    const submitLabel = submitButton ? submitButton.textContent : '';
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Отправляем…';
    }

    function showSuccess(number, totalValue) {
      localStorage.setItem(CUSTOMER_KEY, JSON.stringify(customer));
      localStorage.removeItem(CART_KEY);
      renderCart();

      if (layout) layout.hidden = true;
      const pageHead = $('.page-head');
      if (pageHead) pageHead.hidden = true;
      if (success) {
        success.hidden = false;
        $('#orderNumber').textContent = number;
        $('#orderTotal').textContent = formatPrice(totalValue);
        $('#orderName').textContent = customer.name;
        $('#orderContact').textContent = customer.phone;
        $('#orderDelivery').textContent = method === 'pickup'
          ? `Самовывоз: ${SHOP.pickupAddress || 'из мастерской'}`
          : `Доставка: ${deliveryInfo.city}, ${deliveryInfo.address}`;
        $('#orderEmail').textContent = customer.email;
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
      notify('Заказ оформлен - спасибо!');
    }

    /* Запасной вариант, если PHP-сервер недоступен: сохраняем заказ в браузере. */
    function saveLocally() {
      const order = {
        number: 'HM-' + String(Date.now()).slice(-6),
        date: new Date().toISOString(),
        items: items.map(item => {
          const product = findProduct(item.id);
          return { id: item.id, title: product.title, price: product.price, qty: item.qty };
        }),
        customer,
        delivery: deliveryInfo,
        payment,
        total,
        deliveryCost: delivery
      };
      const orders = JSON.parse(localStorage.getItem(ORDERS_KEY) || '[]');
      orders.push(order);
      localStorage.setItem(ORDERS_KEY, JSON.stringify(orders));
      showSuccess(order.number, order.total);
    }

    try {
      if (!window.siteApi) throw Object.assign(new Error('API недоступен'), { status: 0 });
      const order = await window.siteApi.post('/shop/orders', {
        name: customer.name,
        phone: customer.phone,
        email: customer.email,
        deliveryMethod: method,
        city: deliveryInfo.city,
        address: deliveryInfo.address,
        comment: deliveryInfo.comment,
        payment,
        items: items.map(item => ({ slug: item.id, qty: item.qty })),
        consent: true,
        website: fields.website ? fields.website.value : '',
      });
      showSuccess(order.code, Number(order.total) / 100);
    } catch (error) {
      if (error.status === 0) {
        saveLocally();
      } else {
        const details = Array.isArray(error.details) ? error.details : [];
        let highlighted = false;
        for (const detail of details) {
          const field = form.elements[detail.path];
          if (field && field.closest && field.closest('.field')) {
            setError(field, detail.message);
            highlighted = true;
          }
        }
        notify(highlighted ? 'Проверьте выделенные поля' : (error.message || 'Не удалось оформить заказ'));
        if (highlighted) form.querySelector('.field.invalid input, .field.invalid textarea')?.focus();
      }
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = submitLabel;
      }
    }
  });

  form.addEventListener('input', event => {
    const field = event.target;
    if (field.closest('.field') && field.closest('.field').classList.contains('invalid')) {
      setError(field, '');
    }
  });
}

/* ---------- данные сайта из админки ---------- */
function initSiteInfo() {
  if (!window.siteApi) return;
  window.siteApi.get('/site').then(site => {
    if (!site) return;

    const setField = (key, value) => {
      $$(`[data-site="${key}"]`).forEach(element => {
        const isEmpty = value === undefined || value === null || value === '';
        element.hidden = isEmpty;
        if (!isEmpty) element.textContent = value;
      });
    };
    setField('phone', site.phone);
    setField('email', site.email);
    setField('address', site.address);
    setField('hours', site.workingHoursText);

    const phone = String(site.phone || '').replace(/[^\d+]/g, '');
    if (phone) $$('[data-site="phone-link"]').forEach(link => { link.href = `tel:${phone}`; });
    else $$('[data-site="phone-link"]').forEach(link => { link.hidden = true; });
    if (site.email) $$('[data-site="email-link"]').forEach(link => { link.href = `mailto:${site.email}`; });
    else $$('[data-site="email-link"]').forEach(link => { link.hidden = true; });

    const socials = site.socials || {};
    const platforms = { vk: 'https://vk.com/', telegram: 'https://t.me/', instagram: 'https://instagram.com/', whatsapp: 'https://wa.me/' };
    for (const [key, base] of Object.entries(platforms)) {
      const url = String(socials[key] || '').trim();
      if (!url) continue;
      $$(`[data-site="${key}-link"]`).forEach(link => {
        link.href = /^https?:\/\//.test(url) ? url : base + url.replace(/^@/, '');
      });
    }
  }).catch(error => console.warn('Данные сайта недоступны:', error.message));
}

/* ---------- заявка на индивидуальный заказ ---------- */
function initCustomOrder() {
  if (!window.siteApi) return;

  const shell = document.createElement('div');
  shell.className = 'search-shell';
  shell.innerHTML = `
    <div class="search-overlay" data-close-custom></div>
    <div class="search-panel custom-order-panel" role="dialog" aria-modal="true" aria-label="Индивидуальный заказ" aria-hidden="true">
      <div class="search-head">
        <b class="custom-order-title">Индивидуальный заказ</b>
        <button class="search-close" type="button" aria-label="Закрыть" data-close-custom>✕</button>
      </div>
      <form class="custom-order-form" id="customOrderForm" novalidate>
        <p class="custom-order-lead">Расскажите, что хотите: цвет, размер, срок. Я отвечу и предложу варианты.</p>
        <div class="field">
          <label for="cof-name">Как вас зовут</label>
          <input id="cof-name" name="name" type="text" autocomplete="name" placeholder="Анна">
          <span class="field-error"></span>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="cof-phone">Телефон</label>
            <input id="cof-phone" name="phone" type="tel" autocomplete="tel" placeholder="+7 (900) 000-00-00">
            <span class="field-error"></span>
          </div>
          <div class="field">
            <label for="cof-email">E-mail <i>необязательно</i></label>
            <input id="cof-email" name="email" type="email" autocomplete="email" placeholder="anna@mail.ru">
            <span class="field-error"></span>
          </div>
        </div>
        <div class="field">
          <label for="cof-message">Что хотите заказать</label>
          <textarea id="cof-message" name="message" rows="3" placeholder="Браслет в сиреневой гамме на подарок к 8 марта"></textarea>
          <span class="field-error"></span>
        </div>
        <label class="consent">
          <input type="checkbox" name="consent">
          <span>Согласен(а) с обработкой персональных данных</span>
        </label>
        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <p class="custom-order-msg" id="customOrderMsg" role="status"></p>
        <button class="btn btn-primary" type="submit">Отправить заявку <span class="btn-arrow">→</span></button>
      </form>
    </div>`;
  document.body.appendChild(shell);

  const panel = $('.custom-order-panel', shell);
  const form = $('#customOrderForm', shell);
  const message = $('#customOrderMsg', shell);
  panel.inert = true;
  let lastFocused = null;

  applyPhoneMask(form.elements.phone);

  function open() {
    lastFocused = document.activeElement;
    form.reset();
    message.textContent = '';
    shell.classList.add('is-open');
    panel.inert = false;
    panel.setAttribute('aria-hidden', 'false');
    document.body.classList.add('search-open');
    requestAnimationFrame(() => form.elements.name.focus());
  }

  function close() {
    shell.classList.remove('is-open');
    panel.inert = true;
    panel.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('search-open');
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  document.addEventListener('click', event => {
    if (event.target.closest('[data-custom-order]')) {
      event.preventDefault();
      open();
    }
    if (event.target.closest('[data-close-custom]')) close();
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && shell.classList.contains('is-open')) close();
  });

  function fieldError(field, text = '') {
    const wrap = field.closest('.field');
    if (!wrap) return;
    wrap.classList.toggle('invalid', Boolean(text));
    const error = $('.field-error', wrap);
    if (error) error.textContent = text;
  }

  form.addEventListener('submit', async event => {
    event.preventDefault();
    const fields = form.elements;
    let valid = true;
    fieldError(fields.name, '');
    fieldError(fields.phone, '');
    fieldError(fields.email, '');
    fieldError(fields.message, '');

    if (fields.name.value.trim().length < 2) {
      fieldError(fields.name, 'Как к вам обращаться?');
      valid = false;
    }
    const phoneDigits = fields.phone.value.replace(/\D/g, '');
    const hasEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(fields.email.value.trim());
    if (phoneDigits.length < 10 && !hasEmail) {
      fieldError(fields.phone, 'Укажите телефон или e-mail');
      valid = false;
    }
    if (!fields.consent.checked) {
      message.textContent = 'Нужно согласие на обработку персональных данных';
      valid = false;
    }
    if (!valid) return;

    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    message.textContent = '';
    try {
      await window.siteApi.post('/leads', {
        type: 'custom',
        name: fields.name.value.trim(),
        phone: fields.phone.value.trim() || undefined,
        email: fields.email.value.trim() || undefined,
        message: fields.message.value.trim() || undefined,
        meta: { page: location.pathname },
        consent: true,
        website: fields.website.value,
      });
      form.reset();
      message.textContent = 'Спасибо! Я получила заявку и скоро отвечу.';
      notify('Заявка отправлена - спасибо!');
    } catch (error) {
      message.textContent = error.message || 'Не удалось отправить заявку. Попробуйте позже.';
    } finally {
      button.disabled = false;
    }
  });
}

/* ---------- запуск ---------- */
// старый счётчик корзины больше не используется
localStorage.removeItem('hm_cart');

async function bootstrap() {
  await loadShopData();
  DELIVERY_FEE = SHOP.deliveryFee;
  FREE_FROM = SHOP.freeFrom;

  renderCart();
  syncFavorites();
  initSearch();
  initFeatured();
  initCatalog();
  initProductPage();
  initCheckout();
  initSiteInfo();
  initCustomOrder();
}

bootstrap();
