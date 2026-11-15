<?php
declare(strict_types=1);

namespace App;

/**
 * Страница лендинга. Блок: `type` выбирает компонент на фронте (hero, features, gallery, faq…),
 * `data` — его содержимое. Структура data не фиксируется: шаблон не навязывает фронту дизайн.
 */
function pageSchema(): array
{
    return [
        'slug' => V::slug(),
        'title' => V::str()->trim()->min(1),
        'seoTitle' => V::str()->nullish(),
        'seoDescription' => V::str()->nullish(),
        'blocks' => V::list(V::obj([
            'id' => V::str()->min(1),
            'type' => V::str()->min(1),
            'isVisible' => V::bool()->default(true),
            'data' => V::record(V::any())->default(new \stdClass()),
        ]))->default([]),
        'isPublished' => V::bool()->default(true),
    ];
}

const PAGE_CASTS = ['blocks' => 'json', 'isPublished' => 'bool', 'createdAt' => 'date', 'updatedAt' => 'date'];

function pageData(array $data): array
{
    if (array_key_exists('blocks', $data)) $data['blocks'] = toJson($data['blocks']);
    return $data;
}

function findPage(int $id): array
{
    return cast(Db::findOrFail('pages', $id), PAGE_CASTS);
}

return [
    'public' => function (Router $r) {
        $r->get('/pages/:slug', function (Request $req) {
            $page = cast(Db::one('SELECT * FROM pages WHERE slug = ? AND isPublished = 1', [$req->param('slug')]), PAGE_CASTS);
            if (!$page) throw HttpError::notFound('Страница не найдена');
            $page['blocks'] = array_values(array_filter($page['blocks'] ?? [], fn ($b) => $b->isVisible ?? true));
            return $page;
        });
    },

    'admin' => function (Router $r) {
        $r->get('/pages', fn () => castAll(
            Db::all('SELECT id, slug, title, isPublished, updatedAt FROM pages ORDER BY id'),
            PAGE_CASTS,
        ));

        $r->get('/pages/:id', fn (Request $req) => findPage($req->id()));

        $r->post('/pages', function (Request $req) {
            $id = Db::insert('pages', pageData(V::parse($req->body(), pageSchema())));
            return Response::created(findPage($id));
        });

        $r->patch('/pages/:id', function (Request $req) {
            Db::update('pages', $req->id(), pageData(V::parse($req->body(), pageSchema(), patch: true)));
            return findPage($req->id());
        });

        $r->delete('/pages/:id', function (Request $req) {
            Db::delete('pages', $req->id());
            return Response::noContent();
        });
    },
];
