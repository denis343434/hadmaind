/* Каталог изделий мастерской: данные из API /api/shop/catalog с резервным локальным набором. */

const FALLBACK_CATEGORIES = {
  all: { title: 'Каталог изделий', sub: 'Украшения из бисера и вязаные игрушки ручной работы', short: 'Все товары' },
  bead: { title: 'Украшения из бисера', sub: 'Браслеты, серьги и колье из бисера и натуральных камней', short: 'Украшения из бисера' },
  toy: { title: 'Вязаные игрушки', sub: 'Амигуруми из мягкой пряжи - с любовью и вниманием к деталям', short: 'Вязаные игрушки' },
  gift: { title: 'Подарки и сувениры', sub: 'Готовые подарки и упаковка в лавандовой гамме', short: 'Подарки и сувениры' }
};

const FALLBACK_PRODUCTS = [
  {
    id: 'bracelet-lavender',
    cat: 'bead',
    tone: 'photo',
    title: 'Браслет из бисера «Лаванда»',
    price: 690,
    badge: 'Хит',
    featured: true,
    image: 'https://images.unsplash.com/photo-1629890731335-52295b8be1d9?auto=format&fit=crop&w=1000&q=85',
    short: 'Нежный браслет из бисера и натуральных камней в лавандовой гамме - лёгкий акцент на каждый день.',
    desc: 'Браслет набран вручную из матового бисера и полированных бусин авантюрина. Мягкая лавандовая гамма подходит и к повседневным образам, и к вечернему платью. Каждая бусина закреплена отдельно, поэтому браслет держит форму и не растягивается.',
    specs: [['Материалы', 'бисер, авантюрин, фурнитура'], ['Размер', '17 см (под заказ - любой)'], ['Изготовление', '2-3 дня'], ['Упаковка', 'подарочная коробочка']]
  },
  {
    id: 'earrings-pearl',
    cat: 'bead',
    tone: 'photo',
    zoom: 1.6,
    title: 'Серьги «Ягодки»',
    price: 790,
    badge: '',
    featured: true,
    image: 'https://images.unsplash.com/photo-1653083210739-7cc5cf95f6ed?auto=format&fit=crop&w=1000&q=85',
    short: 'Серьги из бисера в виде маленьких ягод - красный, зелёный и жемчужный бисер на золотистых крючках.',
    desc: 'Каждая ягодка сплетена вручную из мелкого бисера: красные бусины, зелёные листики и жемчужные акценты. Серьги почти ничего не весят, красиво двигаются и подходят даже тем, кто обычно не носит крупные украшения. Фурнитура гипоаллергенная.',
    specs: [['Материалы', 'бисер, жемчужные бусины, позолоченная фурнитура'], ['Размер', 'около 2,5 см'], ['Изготовление', '2-3 дня'], ['Упаковка', 'подарочная коробочка']]
  },
  {
    id: 'necklace-lace',
    cat: 'bead',
    tone: 'photo',
    title: 'Колье «Кружево»',
    price: 1290,
    badge: 'Новинка',
    image: 'https://images.unsplash.com/photo-1786118791837-1a2f74beabf6?auto=format&fit=crop&w=1000&q=85',
    short: 'Кружевное колье из мелкого бисера - воздушное, как тонкое кружево, и удивительно лёгкое.',
    desc: 'Колье сплетено из матового и жемчужного бисера в технике кружевного плетения. Ложится мягкой волной, не тянет шею и красиво смотрится и с открытым платьем, и поверх топа. Застёжка регулируется по длине.',
    specs: [['Материалы', 'бисер, жемчужные бусины'], ['Размер', '38-42 см с регулировкой'], ['Изготовление', '5-7 дней'], ['Упаковка', 'подарочная коробочка']]
  },
  {
    id: 'necklace-tenderness',
    cat: 'bead',
    tone: 'photo',
    title: 'Колье «Нежность»',
    price: 890,
    badge: '',
    image: 'https://images.unsplash.com/photo-1786052338161-bc8eba2d9f3e?auto=format&fit=crop&w=1000&q=85',
    short: 'Колье из бисера с голубыми и золотистыми акцентами - тонкая ручная работа на каждый день.',
    desc: 'Каждая бусина нанизана вручную: голубой, белый и золотистый бисер собраны в лёгкий узор. Колье застёгивается на карабин с регулировкой длины, поэтому подойдёт и под горловину, и под глубокий вырез.',
    specs: [['Материалы', 'бисер, стеклярус, фурнитура'], ['Размер', '42-46 см'], ['Изготовление', '4-5 дней'], ['Упаковка', 'подарочная коробочка']]
  },
  {
    id: 'bunny-amigurumi',
    cat: 'toy',
    title: 'Зайка амигуруми',
    price: 1490,
    badge: 'Хит',
    featured: true,
    image: 'https://images.unsplash.com/photo-1755315897817-97bd6ec34256?auto=format&fit=crop&w=1000&q=85',
    short: 'Вязаная зайка из мягкой пряжи - уютный подарок для детей и взрослых.',
    desc: 'Зайка связана крючком из хлопковой пряжи, набита гипоаллергенным холлофайбером. Глазки вышиты, поэтому игрушка безопасна для малышей. Высота - 22 см, лапки и ушки держат форму.',
    specs: [['Материалы', 'хлопковая пряжа, холлофайбер'], ['Высота', '22 см'], ['Изготовление', '5-7 дней'], ['Уход', 'ручная стирка при 30°']]
  },
  {
    id: 'cat-scarf',
    cat: 'toy',
    title: 'Котик в шарфе',
    price: 1390,
    badge: '',
    featured: true,
    image: 'https://images.unsplash.com/photo-1615486363990-dff6c0722a38?auto=format&fit=crop&w=1000&q=85',
    short: 'Амигуруми-котик с вязаным шарфом и маленьким бантом на ушке.',
    desc: 'Котик связан вручную из мягкой пряжи, шарф снимается - можно менять настроение игрушки. Мордочка вышита, все детали закреплены на совесть. Отличный подарок на новоселье или день рождения.',
    specs: [['Материалы', 'пряжа, холлофайбер, атласная лента'], ['Высота', '20 см'], ['Изготовление', '5-7 дней'], ['Уход', 'ручная стирка при 30°']]
  },
  {
    id: 'bear-hat',
    cat: 'toy',
    title: 'Мишка в шапочке',
    price: 1690,
    badge: 'Новинка',
    image: 'https://images.unsplash.com/photo-1615486364134-62a4c72c822d?auto=format&fit=crop&w=1000&q=85',
    short: 'Вязаный мишка в комбинезоне и шапочке - коллекционная игрушка ручной работы.',
    desc: 'Мишка связан из пряжи двух оттенков, одежда съёмная. Лапки на шарнирах, поэтому игрушка сидит и стоит самостоятельно. Каждая деталь вывязана вручную, повтор исключён.',
    specs: [['Материалы', 'пряжа, холлофайбер, пуговицы'], ['Высота', '24 см'], ['Изготовление', '7-10 дней'], ['Уход', 'сухая чистка']]
  },
  {
    id: 'bear-lavender',
    cat: 'toy',
    title: 'Мишка «Лаванда»',
    price: 1590,
    badge: '',
    image: 'https://images.unsplash.com/photo-1577635515158-dcce4789c8fb?auto=format&fit=crop&w=1000&q=85',
    short: 'Классический вязаный мишка в лавандовом цвете - тёплый и очень обнимательный.',
    desc: 'Мягкий мишка связан из плюшевой пряжи, внутри - холлофайбер. Лапы и голова подвижные, поэтому игрушку удобно сажать и укладывать. Прекрасно смотрится на полке и в детской кроватке.',
    specs: [['Материалы', 'плюшевая пряжа, холлофайбер'], ['Высота', '26 см'], ['Изготовление', '6-8 дней'], ['Уход', 'ручная стирка при 30°']]
  },
  {
    id: 'gift-box',
    cat: 'gift',
    tone: 'neutral',
    title: 'Подарочная коробка «Лаванда»',
    price: 590,
    badge: '',
    image: 'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?auto=format&fit=crop&w=1000&q=85',
    short: 'Коробка с атласной лентой и сухоцветами - для подарка, который хочется сохранить.',
    desc: 'Плотная коробка пастельного оттенка с широкой атласной лентой и веточкой сухоцвета. Внутри - мягкая стружка, которая бережно держит украшение или игрушку. Можно добавить открытку с вашим текстом.',
    specs: [['Материалы', 'картон, атлас, сухоцветы'], ['Размер', '18 × 13 × 8 см'], ['Открытка', 'в подарок'], ['Упаковка', 'готова к вручению']]
  },
  {
    id: 'gift-set',
    cat: 'gift',
    tone: 'neutral',
    title: 'Набор «Нежность»',
    price: 1990,
    badge: 'Выгодно',
    image: 'https://images.unsplash.com/photo-1787074630731-d048e8455ee2?auto=format&fit=crop&w=1000&q=85',
    short: 'Браслет и серьги из бисера в подарочной упаковке - готовый подарок без хлопот.',
    desc: 'В набор входят браслет «Лаванда», серьги «Цветочки» и открытка с вашим пожеланием. Всё уложено в подарочную коробку с лентой - можно сразу вручать. Приятнее, чем покупать по отдельности.',
    specs: [['Состав', 'браслет, серьги, открытка'], ['Упаковка', 'подарочная коробка'], ['Изготовление', '3-5 дней'], ['Открытка', 'с вашим текстом']]
  }
];

/* Данные витрины. После загрузки API заменяются актуальными из базы. */
let CATEGORIES = FALLBACK_CATEGORIES;
let PRODUCTS = FALLBACK_PRODUCTS;

/* Настройки магазина (доставка) с сервера; в резерве — те же значения. */
const SHOP = {
  online: false,
  deliveryFee: 390,
  freeFrom: 3000,
  pickupAddress: 'Москва, ул. Лавандовая, 7',
  deliveryNote: '2–5 дней'
};

function mapApiCategory(category) {
  return {
    title: category.title || category.name,
    sub: category.subtitle || '',
    short: category.shortName || category.name
  };
}

function mapApiProduct(product) {
  return {
    id: product.slug,
    apiId: product.id,
    cat: product.categorySlug,
    title: product.name,
    price: Math.round(Number(product.price) / 100),
    badge: product.badge || '',
    featured: Boolean(product.isFeatured),
    image: product.imageUrl || '',
    short: product.shortDescription || '',
    desc: product.description || '',
    specs: Array.isArray(product.specs) ? product.specs.map(item => [item.name, item.value]) : [],
    tone: product.tone || '',
    zoom: product.zoom ? Number(product.zoom) : null
  };
}

/**
 * Загружает каталог и настройки доставки из бэкенда.
 * При недоступном API остаётся локальный набор — страница продолжает работать.
 */
async function loadShopData() {
  if (!window.siteApi) return false;
  try {
    const data = await window.siteApi.get('/shop/catalog');
    if (!data || !Array.isArray(data.products)) throw new Error('Пустой ответ каталога');

    const categories = { all: FALLBACK_CATEGORIES.all };
    for (const category of data.categories ?? []) categories[category.slug] = mapApiCategory(category);

    CATEGORIES = categories;
    PRODUCTS = data.products.map(mapApiProduct);

    const config = data.config ?? {};
    SHOP.deliveryFee = Math.round(Number(config.deliveryFee ?? SHOP.deliveryFee * 100) / 100);
    SHOP.freeFrom = Math.round(Number(config.freeFrom ?? SHOP.freeFrom * 100) / 100);
    SHOP.pickupAddress = config.pickupAddress != null ? config.pickupAddress : SHOP.pickupAddress;
    SHOP.deliveryNote = config.deliveryNote != null ? config.deliveryNote : SHOP.deliveryNote;
    SHOP.online = true;
    return true;
  } catch (error) {
    console.warn('Каталог с сервера недоступен, показываем локальные данные:', error.message);
    return false;
  }
}

function formatPrice(value) {
  return Number(value).toLocaleString('ru-RU').replace(/\u00A0/g, ' ') + ' ₽';
}

function findProduct(id) {
  return PRODUCTS.find(item => item.id === id);
}

function plural(count, one, few, many) {
  const mod10 = count % 10;
  const mod100 = count % 100;
  if (mod10 === 1 && mod100 !== 11) return one;
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few;
  return many;
}
