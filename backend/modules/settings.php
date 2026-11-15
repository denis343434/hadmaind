<?php
declare(strict_types=1);

namespace App;

/**
 * Карточка заведения: всё, что фронт выводит в шапке, подвале и контактах.
 * Схема «мягкая»: можно хранить любые дополнительные поля под конкретный проект.
 */
function siteSchema(): array
{
    return [
        'name' => V::str()->default(''),
        'description' => V::str()->optional(),
        'logoUrl' => V::str()->optional(),
        'phone' => V::str()->optional(),
        'email' => V::str()->optional(),
        'address' => V::str()->optional(),
        'coordinates' => V::obj(['lat' => V::num(), 'lng' => V::num()])->optional(),
        'workingHoursText' => V::str()->optional(), // "Пн–Пт 10:00–22:00"
        'socials' => V::record(V::str())->optional(), // { telegram: "...", vk: "..." }
        'seo' => V::obj([
            'title' => V::str()->optional(),
            'description' => V::str()->optional(),
            'ogImage' => V::str()->optional(),
        ])->optional(),
    ];
}

function getSite(): array
{
    $value = Db::value('SELECT value FROM settings WHERE `key` = ?', ['site']);
    $site = $value ? json_decode((string) $value) : null;
    return $site instanceof \stdClass ? (array) $site : ['name' => ''];
}

function saveSite(array $site): void
{
    Db::exec(
        'INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
        ['site', toJson($site)],
    );
}

return [
    'public' => function (Router $r) {
        /** Карточка заведения + включённые модули (фронт прячет лишние разделы). */
        $r->get('/site', fn () => [
            ...getSite(),
            'preset' => env('SITE_PRESET'),
            'modules' => enabledModules(),
            'timezone' => env('TIMEZONE'),
        ]);
    },

    'admin' => function (Router $r) {
        $r->get('/settings/site', fn () => getSite() ?: new \stdClass());

        $r->put('/settings/site', function (Request $req) {
            $site = V::parse($req->body(), siteSchema(), loose: true);
            saveSite($site);
            return $site;
        });

        $r->patch('/settings/site', function (Request $req) {
            $site = array_merge(getSite(), V::parse($req->body(), siteSchema(), patch: true, loose: true));
            saveSite($site);
            return $site;
        });
    },
];
