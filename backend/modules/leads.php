<?php
declare(strict_types=1);

namespace App;

const LEAD_TYPES = ['contact', 'callback', 'event', 'custom'];
const LEAD_STATUSES = ['new', 'in_progress', 'done', 'spam'];
const LEAD_TITLES = [
    'contact' => 'Новая заявка с сайта',
    'callback' => 'Запрос обратного звонка',
    'event' => 'Заявка на мероприятие',
    'custom' => 'Новая заявка',
];
const LEAD_CASTS = ['meta' => 'json', 'createdAt' => 'date', 'updatedAt' => 'date'];

return [
    'public' => function (Router $r) {
        $r->post('/leads', function (Request $req) {
            if (RateLimit::isBot($req)) return Response::created(['ok' => true]);

            $data = V::parse($req->body(), [
                'type' => V::enum(LEAD_TYPES)->default('contact'),
                'name' => V::str()->trim()->min(1, 'Укажите имя')->max(100),
                'phone' => V::phone()->optional(),
                'email' => V::email()->optional(),
                'message' => V::str()->trim()->max(2000)->optional(),
                // Любые доп. поля формы и utm-метки: { utm_source, page, guests, date … }
                'meta' => V::record(V::any())->optional(),
                'consent' => V::accepted('Необходимо согласие на обработку персональных данных'),
            ]);
            if (empty($data['phone']) && empty($data['email'])) throw V::fail('phone', 'Укажите телефон или email');
            $meta = (array) ($data['meta'] ?? []);
            foreach ($meta as $k => $v) {
                if (!is_scalar($v) && $v !== null) throw V::fail("meta.$k", 'Ожидалась строка, число или true/false');
            }

            $id = Db::insert('leads', [
                'type' => $data['type'],
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'message' => $data['message'] ?? null,
                'meta' => isset($data['meta']) ? toJson($data['meta']) : null,
            ]);

            Notify::admin(LEAD_TITLES[$data['type']], [
                'Имя' => $data['name'],
                'Телефон' => $data['phone'] ?? null,
                'Email' => $data['email'] ?? null,
                'Сообщение' => $data['message'] ?? null,
                ...array_map(fn ($v) => is_bool($v) ? ($v ? 'да' : 'нет') : $v, $meta),
            ]);
            return Response::created(['ok' => true, 'id' => $id]);
        }, [RateLimit::class, 'forms']);
    },

    'admin' => function (Router $r) {
        $r->get('/leads', function (Request $req) {
            $q = V::parse($req->query, [
                'page' => V::int()->coerce()->min(1)->default(1),
                'limit' => V::int()->coerce()->min(1)->max(100)->default(20),
                'status' => V::enum(LEAD_STATUSES)->optional(),
                'type' => V::enum(LEAD_TYPES)->optional(),
                'q' => V::str()->trim()->optional(),
            ]);
            [$where, $params] = [['1 = 1'], []];
            foreach (['status', 'type'] as $f) {
                if (isset($q[$f])) [$where[], $params[]] = ["$f = ?", $q[$f]];
            }
            if (!empty($q['q'])) {
                $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
                array_push($params, ...array_fill(0, 3, '%' . addcslashes($q['q'], '%_\\') . '%'));
            }
            $sql = 'FROM leads WHERE ' . implode(' AND ', $where);
            $offset = ($q['page'] - 1) * $q['limit'];
            $items = Db::all("SELECT * $sql ORDER BY createdAt DESC, id DESC LIMIT {$q['limit']} OFFSET $offset", $params);
            return paged(castAll($items, LEAD_CASTS), (int) Db::value("SELECT COUNT(*) $sql", $params), $q['page'], $q['limit']);
        });

        $r->get('/leads/stats', function () {
            $stats = [];
            foreach (Db::all('SELECT status, COUNT(*) AS n FROM leads GROUP BY status') as $row) $stats[$row['status']] = $row['n'];
            return $stats ?: new \stdClass();
        });

        $r->get('/leads/:id', fn (Request $req) => cast(Db::findOrFail('leads', $req->id()), LEAD_CASTS));

        $r->patch('/leads/:id', function (Request $req) {
            Db::update('leads', $req->id(), V::parse($req->body(), ['status' => V::enum(LEAD_STATUSES)]));
            return cast(Db::findOrFail('leads', $req->id()), LEAD_CASTS);
        });

        $r->delete('/leads/:id', function (Request $req) {
            Db::delete('leads', $req->id());
            return Response::noContent();
        });
    },
];
