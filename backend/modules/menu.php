<?php
declare(strict_types=1);

namespace App;

const CATEGORY_CASTS = ['isActive' => 'bool'];
const ITEM_CASTS = ['tags' => 'json', 'isAvailable' => 'bool', 'createdAt' => 'date', 'updatedAt' => 'date'];

function categorySchema(): array
{
    return [
        'name' => V::str()->trim()->min(1),
        'slug' => V::slug(),
        'description' => V::str()->nullish(),
        'sort' => V::int()->default(0),
        'isActive' => V::bool()->default(true),
    ];
}

function itemSchema(): array
{
    return [
        'categoryId' => V::int()->min(1),
        'name' => V::str()->trim()->min(1),
        'description' => V::str()->nullish(),
        'price' => V::int()->min(0), // копейки
        'oldPrice' => V::int()->min(0)->nullish(),
        'weight' => V::str()->nullish(),
        'imageUrl' => V::str()->nullish(),
        'tags' => V::list(V::str())->default([]),
        'isAvailable' => V::bool()->default(true),
        'sort' => V::int()->default(0),
    ];
}

function itemData(array $data): array
{
    if (array_key_exists('tags', $data)) $data['tags'] = toJson($data['tags']);
    if (isset($data['categoryId']) && !Db::value('SELECT 1 FROM menu_categories WHERE id = ?', [$data['categoryId']])) {
        throw HttpError::conflict('Категория не найдена');
    }
    return $data;
}

function findItem(int $id): array
{
    return cast(Db::findOrFail('menu_items', $id), ITEM_CASTS);
}

/** PUT …/reorder: [{id, sort}, …] в одной транзакции. */
function reorder(string $table, Request $req): array
{
    $order = V::parseList($req->body(), V::obj(['id' => V::int(), 'sort' => V::int()]), 1);
    Db::tx(function () use ($table, $order) {
        foreach ($order as $o) Db::update($table, $o['id'], ['sort' => $o['sort']]);
    });
    return ['ok' => true];
}

return [
    'public' => function (Router $r) {
        /** Всё меню одним запросом: категории с доступными позициями. ?tag=veg — фильтр по тегу. */
        $r->get('/menu', function (Request $req) {
            $tag = is_string($req->query['tag'] ?? null) ? $req->query['tag'] : null;
            $categories = castAll(Db::all('SELECT * FROM menu_categories WHERE isActive = 1 ORDER BY sort, id'), CATEGORY_CASTS);
            $items = castAll(Db::all(
                'SELECT i.* FROM menu_items i JOIN menu_categories c ON c.id = i.categoryId
                 WHERE i.isAvailable = 1 AND c.isActive = 1 ORDER BY i.sort, i.id',
            ), ITEM_CASTS);

            $byCategory = [];
            foreach ($items as $item) {
                if ($tag === null || in_array($tag, $item['tags'] ?? [], true)) $byCategory[$item['categoryId']][] = $item;
            }
            $result = [];
            foreach ($categories as $c) {
                if (!empty($byCategory[$c['id']])) $result[] = [...$c, 'items' => $byCategory[$c['id']]];
            }
            return $result;
        });

        $r->get('/menu/items/:id', function (Request $req) {
            $item = Db::one(
                'SELECT i.*, c.name AS categoryName, c.slug AS categorySlug FROM menu_items i
                 JOIN menu_categories c ON c.id = i.categoryId
                 WHERE i.id = ? AND i.isAvailable = 1 AND c.isActive = 1',
                [$req->id()],
            ) ?? throw HttpError::notFound('Запись не найдена');
            $category = ['id' => $item['categoryId'], 'name' => $item['categoryName'], 'slug' => $item['categorySlug']];
            unset($item['categoryName'], $item['categorySlug']);
            return [...cast($item, ITEM_CASTS), 'category' => $category];
        });
    },

    'admin' => function (Router $r) {
        $r->get('/menu/categories', fn () => array_map(
            function ($c) {
                $c['_count'] = ['items' => $c['itemsCount']];
                unset($c['itemsCount']);
                return cast($c, CATEGORY_CASTS);
            },
            Db::all('SELECT c.*, (SELECT COUNT(*) FROM menu_items i WHERE i.categoryId = c.id) AS itemsCount
                     FROM menu_categories c ORDER BY c.sort, c.id'),
        ));

        $r->post('/menu/categories', function (Request $req) {
            $id = Db::insert('menu_categories', V::parse($req->body(), categorySchema()));
            return Response::created(cast(Db::findOrFail('menu_categories', $id), CATEGORY_CASTS));
        });

        $r->put('/menu/categories/reorder', fn (Request $req) => reorder('menu_categories', $req));

        $r->patch('/menu/categories/:id', function (Request $req) {
            Db::update('menu_categories', $req->id(), V::parse($req->body(), categorySchema(), patch: true));
            return cast(Db::findOrFail('menu_categories', $req->id()), CATEGORY_CASTS);
        });

        $r->delete('/menu/categories/:id', function (Request $req) {
            Db::delete('menu_categories', $req->id()); // позиции удаляются каскадно
            return Response::noContent();
        });

        $r->get('/menu/items', function (Request $req) {
            $categoryId = (int) ($req->query['categoryId'] ?? 0);
            $rows = $categoryId
                ? Db::all('SELECT * FROM menu_items WHERE categoryId = ? ORDER BY sort, id', [$categoryId])
                : Db::all('SELECT * FROM menu_items ORDER BY categoryId, sort, id');
            return castAll($rows, ITEM_CASTS);
        });

        $r->post('/menu/items', function (Request $req) {
            $id = Db::insert('menu_items', itemData(V::parse($req->body(), itemSchema())));
            return Response::created(findItem($id));
        });

        $r->put('/menu/items/reorder', fn (Request $req) => reorder('menu_items', $req));

        $r->patch('/menu/items/:id', function (Request $req) {
            Db::update('menu_items', $req->id(), itemData(V::parse($req->body(), itemSchema(), patch: true)));
            return findItem($req->id());
        });

        $r->delete('/menu/items/:id', function (Request $req) {
            Db::delete('menu_items', $req->id());
            return Response::noContent();
        });
    },
];
