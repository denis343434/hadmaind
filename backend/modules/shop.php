<?php
declare(strict_types=1);

namespace App;

/**
 * Магазин: каталог изделий и заказы.
 *
 * Публично: /shop/catalog (категории + товары одним запросом), /shop/products/:slug,
 * /shop/config (доставка), POST /shop/orders. Цены хранятся в копейках, считает их сервер.
 */

const SHOP_ORDER_STATUSES = ['new', 'confirmed', 'shipped', 'done', 'cancelled', 'spam'];
const SHOP_PAYMENTS = ['card', 'transfer', 'cash'];
const SHOP_DELIVERY = ['delivery', 'pickup'];
const SHOP_TONES = ['', 'photo', 'neutral'];

const SHOP_CATEGORY_CASTS = ['isActive' => 'bool'];
const SHOP_PRODUCT_CASTS = ['specs' => 'json', 'isFeatured' => 'bool', 'isActive' => 'bool', 'createdAt' => 'date', 'updatedAt' => 'date'];
const SHOP_ORDER_CASTS = ['createdAt' => 'date', 'updatedAt' => 'date'];

const SHOP_CONFIG_DEFAULTS = [
    'deliveryFee' => 39000,
    'freeFrom' => 300000,
    'pickupAddress' => '',
    'deliveryNote' => '',
    'currency' => 'RUB',
];

function shopConfig(): array
{
    $value = Db::value('SELECT value FROM settings WHERE `key` = ?', ['shop']);
    $saved = $value ? json_decode((string) $value, true) : [];
    return array_merge(SHOP_CONFIG_DEFAULTS, is_array($saved) ? $saved : []);
}

function shopSaveConfig(array $config): void
{
    Db::exec(
        'INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
        ['shop', toJson($config)],
    );
}

/** Товар в JSON одинаково для админки и сайта: цена в копейках, specs — массив объектов. */
function shopProduct(?array $row): ?array
{
    if (!$row) return null;
    $row = cast($row, SHOP_PRODUCT_CASTS);
    $row['zoom'] = $row['zoom'] === null ? null : (float) $row['zoom'];
    $row['price'] = (int) $row['price'];
    $row['oldPrice'] = $row['oldPrice'] === null ? null : (int) $row['oldPrice'];
    $row['specs'] = is_array($row['specs']) ? $row['specs'] : [];
    return $row;
}

function shopProducts(array $rows): array
{
    return array_map('App\shopProduct', $rows);
}

function shopCategorySchema(): array
{
    return [
        'name' => V::str()->trim()->min(1, 'Укажите название'),
        'slug' => V::slug(),
        'title' => V::str()->nullish(),
        'subtitle' => V::str()->nullish(),
        'shortName' => V::str()->nullish(),
        'sort' => V::int()->default(0),
        'isActive' => V::bool()->default(true),
    ];
}

function shopProductSchema(): array
{
    return [
        'categoryId' => V::int()->min(1),
        'slug' => V::slug(),
        'name' => V::str()->trim()->min(1, 'Укажите название'),
        'price' => V::int()->min(0),
        'oldPrice' => V::int()->min(0)->nullish(),
        'badge' => V::str()->nullish(),
        'shortDescription' => V::str()->trim()->nullish(),
        'description' => V::str()->nullish(),
        'specs' => V::list(V::obj(['name' => V::str()->trim()->min(1), 'value' => V::str()->trim()->min(1)]))->default([]),
        'imageUrl' => V::str()->nullish(),
        'tone' => V::enum(SHOP_TONES)->default(''),
        'zoom' => V::num()->min(0)->max(10)->nullish(),
        'isFeatured' => V::bool()->default(false),
        'isActive' => V::bool()->default(true),
        'sort' => V::int()->default(0),
    ];
}

function shopProductData(array $data): array
{
    unset($data['createdAt'], $data['updatedAt']);
    if (array_key_exists('specs', $data)) $data['specs'] = toJson(array_values($data['specs']));
    if (isset($data['categoryId']) && !Db::value('SELECT 1 FROM shop_categories WHERE id = ?', [$data['categoryId']])) {
        throw HttpError::conflict('Категория не найдена');
    }
    return $data;
}

function shopFindProduct(int $id): array
{
    return shopProduct(Db::findOrFail('shop_products', $id));
}

/** Уникальный номер заказа вида HM-8F3K2. */
function shopOrderCode(): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    do {
        $code = 'HM-';
        for ($i = 0; $i < 5; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    } while (Db::value('SELECT 1 FROM shop_orders WHERE code = ?', [$code]));
    return $code;
}

function shopOrder(int $id): array
{
    $order = cast(Db::findOrFail('shop_orders', $id), SHOP_ORDER_CASTS);
    $order['items'] = Db::all('SELECT * FROM shop_order_items WHERE orderId = ? ORDER BY id', [$id]);
    foreach ($order['items'] as &$item) $item['price'] = (int) $item['price'];
    unset($item);
    return $order;
}

/** Короткая строка состава заказа для списка и уведомлений. */
function shopItemsLabel(array $items): string
{
    return implode(', ', array_map(
        fn ($i) => $i['name'] . ' × ' . $i['qty'],
        $items,
    ));
}

function shopMoney(int $kopecks): string
{
    return number_format($kopecks / 100, 0, ',', ' ') . ' ₽';
}

/** Пересортировка [{id, sort}]. */
function shopReorder(string $table, Request $req): array
{
    $order = V::parseList($req->body(), V::obj(['id' => V::int(), 'sort' => V::int()]), 1);
    Db::tx(function () use ($table, $order) {
        foreach ($order as $o) Db::update($table, $o['id'], ['sort' => $o['sort']]);
    });
    return ['ok' => true];
}

return [
    'public' => function (Router $r) {
        /** Всё для витрины одним запросом: категории и активные товары. */
        $r->get('/shop/catalog', function (Request $req) {
            $cat = is_string($req->query['cat'] ?? null) ? trim($req->query['cat']) : '';
            $q = is_string($req->query['q'] ?? null) ? trim($req->query['q']) : '';

            $categories = castAll(
                Db::all('SELECT * FROM shop_categories WHERE isActive = 1 ORDER BY sort, id'),
                SHOP_CATEGORY_CASTS,
            );
            $products = shopProducts(Db::all(
                'SELECT p.*, c.slug AS categorySlug, c.name AS categoryName, c.shortName AS categoryShortName
                 FROM shop_products p JOIN shop_categories c ON c.id = p.categoryId
                 WHERE p.isActive = 1 AND c.isActive = 1
                 ORDER BY c.sort, p.sort, p.id',
            ));

            $lower = fn (string $s): string => function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
            $needle = $q === '' ? null : $lower($q);
            $products = array_values(array_filter($products, function (array $p) use ($cat, $needle, $lower) {
                if ($cat !== '' && $p['categorySlug'] !== $cat) return false;
                if ($needle === null) return true;
                $haystack = $lower(implode(' ', [$p['name'], $p['shortDescription'], $p['description'], $p['categoryName']]));
                return str_contains($haystack, $needle);
            }));

            return ['categories' => $categories, 'products' => $products, 'config' => shopConfig()];
        });

        $r->get('/shop/config', fn () => shopConfig());

        $r->get('/shop/products/:slug', function (Request $req) {
            $row = Db::one(
                'SELECT p.*, c.slug AS categorySlug, c.name AS categoryName, c.shortName AS categoryShortName
                 FROM shop_products p JOIN shop_categories c ON c.id = p.categoryId
                 WHERE p.slug = ? AND p.isActive = 1 AND c.isActive = 1',
                [$req->param('slug')],
            ) ?? throw HttpError::notFound('Товар не найден');
            return shopProduct($row);
        });

        /** Оформление заказа: цены и доставку считаем на сервере, клиентские суммы не доверяем. */
        $r->post('/shop/orders', function (Request $req) {
            if (RateLimit::isBot($req)) return Response::created(['ok' => true, 'code' => null]);

            $data = V::parse($req->body(), [
                'name' => V::str()->trim()->min(2, 'Укажите имя')->max(100),
                'phone' => V::phone(),
                'email' => V::email()->optional(),
                'deliveryMethod' => V::enum(SHOP_DELIVERY)->default('delivery'),
                'city' => V::str()->trim()->max(100)->optional(),
                'address' => V::str()->trim()->max(300)->optional(),
                'comment' => V::str()->trim()->max(1000)->optional(),
                'payment' => V::enum(SHOP_PAYMENTS)->default('card'),
                'items' => V::list(V::obj([
                    'slug' => V::slug(),
                    'qty' => V::int()->min(1)->max(99),
                ]))->min(1, 'Корзина пуста'),
                'consent' => V::accepted('Необходимо согласие на обработку персональных данных'),
            ]);

            if ($data['deliveryMethod'] === 'delivery') {
                if (empty($data['city'])) throw V::fail('city', 'Укажите город');
                if (empty($data['address'])) throw V::fail('address', 'Укажите адрес доставки');
            }

            // Объединяем повторы и загружаем актуальные товары
            $wanted = [];
            foreach ($data['items'] as $item) {
                $wanted[$item['slug']] = min(99, ($wanted[$item['slug']] ?? 0) + $item['qty']);
            }
            $rows = Db::all(
                'SELECT p.* FROM shop_products p JOIN shop_categories c ON c.id = p.categoryId
                 WHERE p.isActive = 1 AND c.isActive = 1 AND p.slug IN (' . Db::marks(array_keys($wanted)) . ')',
                array_keys($wanted),
            );
            $bySlug = [];
            foreach ($rows as $row) $bySlug[$row['slug']] = $row;
            foreach (array_keys($wanted) as $slug) {
                if (!isset($bySlug[$slug])) throw V::fail('items', 'Товар «' . $slug . '» больше недоступен');
            }

            $config = shopConfig();
            $subtotal = 0;
            $items = [];
            foreach ($wanted as $slug => $qty) {
                $row = $bySlug[$slug];
                $price = (int) $row['price'];
                $lineTotal = $price * $qty;
                $subtotal += $lineTotal;
                $items[] = [
                    'productId' => (int) $row['id'],
                    'slug' => $slug,
                    'name' => $row['name'],
                    'price' => $price,
                    'qty' => $qty,
                    'total' => $lineTotal,
                    'imageUrl' => $row['imageUrl'],
                ];
            }
            $deliveryPrice = $data['deliveryMethod'] === 'pickup' || $subtotal >= (int) $config['freeFrom']
                ? 0
                : (int) $config['deliveryFee'];
            $total = $subtotal + $deliveryPrice;
            $code = shopOrderCode();

            $orderId = Db::tx(function () use ($data, $items, $subtotal, $deliveryPrice, $total, $code) {
                $id = Db::insert('shop_orders', [
                    'code' => $code,
                    'customerName' => $data['name'],
                    'customerPhone' => $data['phone'],
                    'customerEmail' => $data['email'] ?? null,
                    'deliveryMethod' => $data['deliveryMethod'],
                    'city' => $data['city'] ?? null,
                    'address' => $data['address'] ?? null,
                    'comment' => $data['comment'] ?? null,
                    'payment' => $data['payment'],
                    'subtotal' => $subtotal,
                    'deliveryPrice' => $deliveryPrice,
                    'total' => $total,
                ]);
                foreach ($items as $item) Db::insert('shop_order_items', ['orderId' => $id, ...$item]);
                return $id;
            });

            $itemsLabel = shopItemsLabel($items);
            Notify::admin('Новый заказ №' . $code, [
                'Имя' => $data['name'],
                'Телефон' => $data['phone'],
                'Email' => $data['email'] ?? null,
                'Состав' => $itemsLabel,
                'Товары' => shopMoney($subtotal),
                'Доставка' => $deliveryPrice > 0 ? shopMoney($deliveryPrice) : 'бесплатно',
                'Итого' => shopMoney($total),
                'Получение' => $data['deliveryMethod'] === 'pickup'
                    ? 'самовывоз: ' . ($config['pickupAddress'] ?: 'мастерская')
                    : trim(($data['city'] ?? '') . ', ' . ($data['address'] ?? ''), ', '),
                'Оплата' => ['card' => 'картой онлайн', 'transfer' => 'переводом', 'cash' => 'при получении'][$data['payment']],
                'Комментарий' => $data['comment'] ?? null,
            ]);
            if (!empty($data['email']) && env('SMTP_HOST') !== '') {
                Notify::customer($data['email'], 'Заказ №' . $code . ' принят', [
                    'Имя' => $data['name'],
                    'Состав' => $itemsLabel,
                    'Сумма' => shopMoney($total),
                    'Что дальше' => 'Я свяжусь с вами, чтобы подтвердить детали и оплату.',
                ]);
            }

            return Response::created(shopOrder($orderId));
        }, [RateLimit::class, 'forms']);
    },

    'admin' => function (Router $r) {
        // ─── Настройки доставки ─────────────────────────────
        $r->get('/shop/settings', fn () => shopConfig());

        $r->put('/shop/settings', function (Request $req) {
            $config = array_merge(shopConfig(), V::parse($req->body(), [
                'deliveryFee' => V::int()->min(0),
                'freeFrom' => V::int()->min(0),
                'pickupAddress' => V::str()->nullish(),
                'deliveryNote' => V::str()->nullish(),
                'currency' => V::str()->default('RUB'),
            ], patch: true));
            shopSaveConfig($config);
            return $config;
        });

        // ─── Категории ──────────────────────────────────────
        $r->get('/shop/categories', fn () => array_map(
            function ($c) {
                $c['_count'] = ['products' => $c['productsCount']];
                unset($c['productsCount']);
                return cast($c, SHOP_CATEGORY_CASTS);
            },
            Db::all('SELECT c.*, (SELECT COUNT(*) FROM shop_products p WHERE p.categoryId = c.id) AS productsCount
                     FROM shop_categories c ORDER BY c.sort, c.id'),
        ));

        $r->post('/shop/categories', function (Request $req) {
            $id = Db::insert('shop_categories', V::parse($req->body(), shopCategorySchema()));
            return Response::created(cast(Db::findOrFail('shop_categories', $id), SHOP_CATEGORY_CASTS));
        });

        $r->put('/shop/categories/reorder', fn (Request $req) => shopReorder('shop_categories', $req));

        $r->patch('/shop/categories/:id', function (Request $req) {
            Db::update('shop_categories', $req->id(), V::parse($req->body(), shopCategorySchema(), patch: true));
            return cast(Db::findOrFail('shop_categories', $req->id()), SHOP_CATEGORY_CASTS);
        });

        $r->delete('/shop/categories/:id', function (Request $req) {
            Db::delete('shop_categories', $req->id()); // товары удаляются каскадно
            return Response::noContent();
        });

        // ─── Товары ─────────────────────────────────────────
        $r->get('/shop/products', function (Request $req) {
            $categoryId = (int) ($req->query['categoryId'] ?? 0);
            $rows = $categoryId
                ? Db::all('SELECT * FROM shop_products WHERE categoryId = ? ORDER BY sort, id', [$categoryId])
                : Db::all('SELECT * FROM shop_products ORDER BY categoryId, sort, id');
            return shopProducts($rows);
        });

        $r->post('/shop/products', function (Request $req) {
            $id = Db::insert('shop_products', shopProductData(V::parse($req->body(), shopProductSchema())));
            return Response::created(shopFindProduct($id));
        });

        $r->put('/shop/products/reorder', fn (Request $req) => shopReorder('shop_products', $req));

        $r->patch('/shop/products/:id', function (Request $req) {
            Db::update('shop_products', $req->id(), shopProductData(V::parse($req->body(), shopProductSchema(), patch: true)));
            return shopFindProduct($req->id());
        });

        $r->delete('/shop/products/:id', function (Request $req) {
            Db::delete('shop_products', $req->id());
            return Response::noContent();
        });

        // ─── Заказы ─────────────────────────────────────────
        $r->get('/shop/orders', function (Request $req) {
            $q = V::parse($req->query, [
                'page' => V::int()->coerce()->min(1)->default(1),
                'limit' => V::int()->coerce()->min(1)->max(100)->default(30),
                'status' => V::enum(SHOP_ORDER_STATUSES)->optional(),
                'q' => V::str()->trim()->optional(),
            ]);
            [$where, $params] = [['1 = 1'], []];
            if (isset($q['status'])) [$where[], $params[]] = ['status = ?', $q['status']];
            if (!empty($q['q'])) {
                $where[] = '(customerName LIKE ? OR customerPhone LIKE ? OR customerEmail LIKE ? OR code LIKE ?)';
                array_push($params, ...array_fill(0, 4, '%' . addcslashes($q['q'], '%_\\') . '%'));
            }
            $sql = 'FROM shop_orders o WHERE ' . implode(' AND ', $where);
            $offset = ($q['page'] - 1) * $q['limit'];
            $items = Db::all(
                "SELECT o.*, (SELECT COALESCE(SUM(i.qty), 0) FROM shop_order_items i WHERE i.orderId = o.id) AS itemsCount,
                        (SELECT GROUP_CONCAT(i.name ORDER BY i.id SEPARATOR '||') FROM shop_order_items i WHERE i.orderId = o.id) AS itemsLabel
                 $sql ORDER BY o.createdAt DESC, o.id DESC LIMIT {$q['limit']} OFFSET $offset",
                $params,
            );
            foreach ($items as &$row) {
                $row['itemsLabel'] = $row['itemsLabel'] ? str_replace('||', ', ', $row['itemsLabel']) : '';
                $row['itemsCount'] = (int) $row['itemsCount'];
                $row['total'] = (int) $row['total'];
            }
            unset($row);
            return paged(castAll($items, SHOP_ORDER_CASTS), (int) Db::value("SELECT COUNT(*) $sql", $params), $q['page'], $q['limit']);
        });

        $r->get('/shop/orders/stats', function () {
            $stats = [];
            foreach (Db::all('SELECT status, COUNT(*) AS n FROM shop_orders GROUP BY status') as $row) $stats[$row['status']] = (int) $row['n'];
            return $stats ?: new \stdClass();
        });

        /** Сводка для стартового экрана: новые заказы и продажи за сегодня (в часовом поясе сайта). */
        $r->get('/shop/dashboard', function () {
            $tz = new \DateTimeZone(env('TIMEZONE') ?: 'UTC');
            $today = new \DateTimeImmutable('today', $tz);
            $from = $today->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $to = $today->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $row = Db::one(
                "SELECT COUNT(*) AS orders, COALESCE(SUM(total), 0) AS revenue
                 FROM shop_orders WHERE status NOT IN ('cancelled', 'spam') AND createdAt >= ? AND createdAt < ?",
                [$from, $to],
            );
            return [
                'newCount' => (int) Db::value("SELECT COUNT(*) FROM shop_orders WHERE status = 'new'"),
                'todayOrders' => (int) ($row['orders'] ?? 0),
                'todayRevenue' => (int) ($row['revenue'] ?? 0),
            ];
        });

        $r->get('/shop/orders/:id', fn (Request $req) => shopOrder($req->id()));

        $r->patch('/shop/orders/:id', function (Request $req) {
            Db::update('shop_orders', $req->id(), V::parse($req->body(), [
                'status' => V::enum(SHOP_ORDER_STATUSES),
                'adminNote' => V::str()->max(2000)->nullish(),
            ]));
            return shopOrder($req->id());
        });

        $r->delete('/shop/orders/:id', function (Request $req) {
            Db::delete('shop_orders', $req->id()); // позиции удаляются каскадно
            return Response::noContent();
        });
    },
];
