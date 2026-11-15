<?php
declare(strict_types=1);

namespace App;

function bookingCreateSchema(): array
{
    return [
        'serviceId' => V::id(),
        'resourceId' => V::id()->optional(),
        'startAt' => V::str()->min(1), // "2026-10-10T18:00" — время заведения
        'guests' => V::int()->coerce()->min(1)->default(1),
        'customerName' => V::str()->trim()->min(1, 'Укажите имя')->max(100),
        'customerPhone' => V::phone(),
        'customerEmail' => V::email()->optional(),
        'comment' => V::str()->trim()->max(1000)->optional(),
    ];
}

function resourceSchema(): array
{
    return [
        'name' => V::str()->trim()->min(1),
        'slug' => V::slug(),
        'type' => V::str()->nullish(),
        'description' => V::str()->nullish(),
        'imageUrl' => V::str()->nullish(),
        'capacity' => V::int()->min(1)->default(1),
        'maxGuests' => V::int()->min(1)->nullish(),
        'isActive' => V::bool()->default(true),
        'sort' => V::int()->default(0),
    ];
}

function serviceSchema(): array
{
    return [
        'name' => V::str()->trim()->min(1),
        'slug' => V::slug(),
        'description' => V::str()->nullish(),
        'imageUrl' => V::str()->nullish(),
        'durationMin' => V::int()->min(5),
        'bufferMin' => V::int()->min(0)->default(0),
        'slotStepMin' => V::int()->min(5)->nullish(),
        'price' => V::int()->min(0)->default(0),
        'pricePerGuest' => V::bool()->default(false),
        'minGuests' => V::int()->min(1)->default(1),
        'maxGuests' => V::int()->min(1)->nullish(),
        'isActive' => V::bool()->default(true),
        'sort' => V::int()->default(0),
        'resourceIds' => V::list(V::int()->min(1))->optional(),
    ];
}

/** Ресурсы/услуги со связанными записями [{id, name, …}]. */
function withRelated(array $rows, string $self, string $other, string $relatedSql): array
{
    if (!$rows) return [];
    $ids = array_column($rows, 'id');
    $related = Db::all(
        "SELECT rs.$self AS ownerId, x.* FROM resource_service rs JOIN $relatedSql
         WHERE rs.$self IN (" . Db::marks($ids) . ') ORDER BY x.sort, x.id',
        $ids,
    );
    $by = [];
    foreach ($related as $row) {
        $owner = $row['ownerId'];
        unset($row['ownerId']);
        $by[$owner][] = $row;
    }
    return array_map(fn ($r) => [...$r, $other => $by[$r['id']] ?? []], $rows);
}

function servicesWithResources(string $where = '1 = 1', array $params = []): array
{
    $rows = castAll(Db::all("SELECT * FROM services WHERE $where ORDER BY sort, id", $params), Booking::SERVICE_CASTS);
    return withRelated($rows, 'serviceId', 'resources', 'resources x ON x.id = rs.resourceId');
}

function setServiceResources(int $serviceId, array $resourceIds): void
{
    Db::exec('DELETE FROM resource_service WHERE serviceId = ?', [$serviceId]);
    foreach (array_unique($resourceIds) as $rid) {
        if (!Db::value('SELECT 1 FROM resources WHERE id = ?', [$rid])) throw HttpError::notFound("Ресурс $rid не найден");
        Db::insert('resource_service', ['resourceId' => $rid, 'serviceId' => $serviceId]);
    }
}

function serviceView(int $id): array
{
    $s = servicesWithResources('id = ?', [$id])[0] ?? throw HttpError::notFound('Запись не найдена');
    $s['resources'] = array_map(fn ($r) => ['id' => $r['id'], 'name' => $r['name']], $s['resources']);
    return $s;
}

function hoursOf(?int $resourceId): array
{
    return $resourceId
        ? Db::all('SELECT * FROM working_hours WHERE resourceId = ? ORDER BY weekday, openTime', [$resourceId])
        : Db::all('SELECT * FROM working_hours WHERE resourceId IS NULL ORDER BY weekday, openTime');
}

const ID_NAME = ['id' => true, 'name' => true];

/** «Подтверждена» → «подтверждена» без зависимости от mbstring. */
function lcfirstUtf8(string $s): string
{
    if (preg_match('/^(.)(.*)$/su', $s, $m)) {
        $first = function_exists('mb_strtolower') ? mb_strtolower($m[1], 'UTF-8') : strtr($m[1], ['П' => 'п', 'О' => 'о', 'З' => 'з', 'Н' => 'н']);
        return $first . $m[2];
    }
    return $s;
}

return [
    'public' => function (Router $r) {
        $r->get('/booking/services', function () {
            $services = servicesWithResources('isActive = 1');
            $fields = array_flip(['id', 'name', 'slug', 'type', 'imageUrl', 'maxGuests']);
            return array_map(fn ($s) => [...$s, 'resources' => array_values(array_map(
                fn ($r) => array_intersect_key($r, $fields),
                array_filter($s['resources'], fn ($r) => $r['isActive']),
            ))], $services);
        });

        $r->get('/booking/resources', function () {
            $rows = castAll(Db::all('SELECT * FROM resources WHERE isActive = 1 ORDER BY sort, id'), Booking::RESOURCE_CASTS);
            $fields = array_flip(['id', 'name', 'slug']);
            return array_map(fn ($res) => [...$res, 'services' => array_values(array_map(
                fn ($s) => array_intersect_key($s, $fields),
                array_filter($res['services'], fn ($s) => $s['isActive']),
            ))], withRelated($rows, 'resourceId', 'services', 'services x ON x.id = rs.serviceId'));
        });

        /** Свободные слоты на день: GET /availability?serviceId=1&date=2026-10-10&guests=4 */
        $r->get('/booking/availability', function (Request $req) {
            $q = V::parse($req->query, [
                'serviceId' => V::id(),
                'date' => V::date(),
                'guests' => V::int()->coerce()->min(1)->default(1),
                'resourceId' => V::id()->optional(),
            ]);
            $a = Booking::availability($q);
            return [
                'date' => $q['date'],
                'timezone' => env('TIMEZONE'),
                'service' => ['id' => $a['service']['id'], 'name' => $a['service']['name'], 'durationMin' => $a['service']['durationMin']],
                'price' => Booking::price($a['service'], $q['guests']),
                'resources' => array_map(fn ($x) => [
                    'id' => $x['resource']['id'],
                    'name' => $x['resource']['name'],
                    'slots' => array_map(fn ($s) => [
                        'start' => Booking::iso($s['start']),
                        'end' => Booking::iso($s['end']),
                        'time' => Booking::local($s['start'])->format('H:i'),
                        'free' => $s['free'],
                    ], $x['slots']),
                ], $a['resources']),
            ];
        });

        /** Какие дни диапазона имеют свободные слоты — для подсветки дат в календаре (до 62 дней). */
        $r->get('/booking/availability/days', function (Request $req) {
            $q = V::parse($req->query, [
                'serviceId' => V::id(),
                'from' => V::date(),
                'to' => V::date(),
                'guests' => V::int()->coerce()->min(1)->default(1),
                'resourceId' => V::id()->optional(),
            ]);
            $from = Booking::dayStart($q['from']);
            $to = Booking::dayStart($q['to']);
            $count = $from <= $to ? $from->diff($to)->days + 1 : 0;
            if ($count < 1 || $count > 62) throw HttpError::badRequest('Диапазон должен быть от 1 до 62 дней');

            $days = [];
            for ($i = 0; $i < $count; $i++) {
                $date = $from->modify("+$i day")->format('Y-m-d');
                $a = Booking::availability([...$q, 'date' => $date]);
                $days[] = ['date' => $date, 'available' => (bool) array_filter($a['resources'], fn ($x) => $x['slots'])];
            }
            return $days;
        });

        $r->post('/booking/bookings', function (Request $req) {
            if (RateLimit::isBot($req)) return Response::created(['ok' => true]);
            $data = V::parse($req->body(), [
                ...bookingCreateSchema(),
                'consent' => V::accepted('Необходимо согласие на обработку персональных данных'),
            ]);
            return Response::created(Booking::publicView(Booking::create($data)));
        }, [RateLimit::class, 'forms']);

        /** Проверка брони клиентом: GET /bookings/ABC123?phone=79990000000 */
        $r->get('/booking/bookings/:code', function (Request $req) {
            $q = V::parse($req->query, ['phone' => V::phone()]);
            return Booking::publicView(Booking::findByCodeAndPhone($req->param('code'), $q['phone']));
        });

        $r->post('/booking/bookings/:code/cancel', function (Request $req) {
            $data = V::parse($req->body(), ['phone' => V::phone()]);
            return Booking::publicView(Booking::cancelByCustomer($req->param('code'), $data['phone']));
        }, [RateLimit::class, 'forms']);
    },

    'admin' => function (Router $r) {
        // ─── Брони ───────────────────────────────────────────

        $r->get('/booking/bookings', function (Request $req) {
            $q = V::parse($req->query, [
                'page' => V::int()->coerce()->min(1)->default(1),
                'limit' => V::int()->coerce()->min(1)->max(100)->default(20),
                'from' => V::date()->optional(),
                'to' => V::date()->optional(),
                'status' => V::enum(Booking::STATUSES)->optional(),
                'resourceId' => V::id()->optional(),
                'serviceId' => V::id()->optional(),
                'q' => V::str()->trim()->optional(),
                'order' => V::enum(['asc', 'desc'])->default('asc'),
            ]);
            [$where, $params] = [['1 = 1'], []];
            foreach (['status', 'resourceId', 'serviceId'] as $f) {
                if (isset($q[$f])) [$where[], $params[]] = ["b.$f = ?", $q[$f]];
            }
            if (isset($q['from'])) [$where[], $params[]] = ['b.startAt >= ?', dbTime(Booking::dayStart($q['from'])->getTimestamp())];
            if (isset($q['to'])) [$where[], $params[]] = ['b.startAt < ?', dbTime(Booking::dayStart($q['to'])->modify('+1 day')->getTimestamp())];
            if (!empty($q['q'])) {
                $where[] = '(b.code LIKE ? OR b.customerName LIKE ? OR b.customerPhone LIKE ?)';
                array_push($params, ...array_fill(0, 3, '%' . addcslashes($q['q'], '%_\\') . '%'));
            }
            $sql = 'FROM bookings b JOIN services s ON s.id = b.serviceId JOIN resources r ON r.id = b.resourceId WHERE ' . implode(' AND ', $where);
            $offset = ($q['page'] - 1) * $q['limit'];
            $rows = Db::all(
                "SELECT b.*, s.name AS serviceName, r.name AS resourceName $sql
                 ORDER BY b.startAt {$q['order']}, b.id {$q['order']} LIMIT {$q['limit']} OFFSET $offset",
                $params,
            );
            $items = array_map(function ($b) {
                $b['service'] = ['id' => $b['serviceId'], 'name' => $b['serviceName']];
                $b['resource'] = ['id' => $b['resourceId'], 'name' => $b['resourceName']];
                unset($b['serviceName'], $b['resourceName']);
                return Booking::withLocal($b);
            }, $rows);
            return paged($items, (int) Db::value("SELECT COUNT(*) $sql", $params), $q['page'], $q['limit']);
        });

        $r->get('/booking/bookings/:id', fn (Request $req) => Booking::withLocal(Booking::find($req->id())));

        /** Бронь от админа (звонок, визит): без ограничений графика, но с проверкой пересечений. */
        $r->post('/booking/bookings', function (Request $req) {
            $data = V::parse($req->body(), [
                ...bookingCreateSchema(),
                'resourceId' => V::id(),
                'status' => V::enum(Booking::STATUSES)->default('confirmed'),
                'totalPrice' => V::int()->min(0)->optional(),
                'adminNote' => V::str()->optional(),
            ]);
            $service = cast(Db::findOrFail('services', $data['serviceId']), Booking::SERVICE_CASTS);
            $resource = Db::findOrFail('resources', $data['resourceId']);
            $start = Booking::parseLocal($data['startAt']);
            $end = $start + $service['durationMin'] * 60;

            $id = Booking::locked(function () use ($data, $service, $resource, $start, $end) {
                if (in_array($data['status'], Booking::ACTIVE, true)) {
                    Booking::assertNoConflict($resource['id'], $start, $end, $service['bufferMin'], $resource['capacity']);
                }
                return Db::insert('bookings', [
                    'code' => Booking::code(),
                    'serviceId' => $service['id'],
                    'resourceId' => $resource['id'],
                    'startAt' => dbTime($start),
                    'endAt' => dbTime($end),
                    'guests' => $data['guests'],
                    'customerName' => $data['customerName'],
                    'customerPhone' => $data['customerPhone'],
                    'customerEmail' => $data['customerEmail'] ?? null,
                    'comment' => $data['comment'] ?? null,
                    'adminNote' => $data['adminNote'] ?? null,
                    'status' => $data['status'],
                    'totalPrice' => $data['totalPrice'] ?? Booking::price($service, $data['guests']),
                ]);
            });
            return Response::created(Booking::withLocal(Booking::find($id)));
        });

        $r->patch('/booking/bookings/:id', function (Request $req) {
            $id = $req->id();
            $data = V::parse($req->body(), [
                'status' => V::enum(Booking::STATUSES)->optional(),
                'startAt' => V::str()->optional(),
                'resourceId' => V::id()->optional(),
                'guests' => V::int()->min(1)->optional(),
                'totalPrice' => V::int()->min(0)->optional(),
                'customerName' => V::str()->trim()->min(1)->optional(),
                'customerPhone' => V::phone()->optional(),
                'customerEmail' => V::email()->nullish(),
                'comment' => V::str()->nullish(),
                'adminNote' => V::str()->nullish(),
                'notifyCustomer' => V::bool()->default(true),
            ]);
            $notify = $data['notifyCustomer'];
            $startAt = $data['startAt'] ?? null;
            unset($data['notifyCustomer'], $data['startAt']);
            $current = Booking::find($id);

            Booking::locked(function () use ($id, $data, $startAt, $current) {
                $start = $startAt !== null ? Booking::parseLocal($startAt) : fromDbTime($current['startAt']);
                $end = $start + $current['service']['durationMin'] * 60;
                $resourceId = $data['resourceId'] ?? $current['resourceId'];
                $status = $data['status'] ?? $current['status'];

                $moved = $startAt !== null || isset($data['resourceId']);
                $reactivated = isset($data['status']) && !in_array($current['status'], Booking::ACTIVE, true);
                if (($moved || $reactivated) && in_array($status, Booking::ACTIVE, true)) {
                    $resource = Db::findOrFail('resources', $resourceId);
                    Booking::assertNoConflict($resourceId, $start, $end, $current['service']['bufferMin'], $resource['capacity'], $id);
                }
                if ($startAt !== null) $data += ['startAt' => dbTime($start), 'endAt' => dbTime($end)];
                Db::update('bookings', $id, $data);
            });

            $updated = Booking::find($id);
            $newStatus = $data['status'] ?? null;
            if ($notify && $newStatus && $newStatus !== $current['status'] && in_array($newStatus, ['confirmed', 'cancelled'], true)) {
                Booking::notifyCustomer($updated, "Бронь {$updated['code']}: " . lcfirstUtf8(Booking::TITLES[$newStatus]));
            }
            return Booking::withLocal($updated);
        });

        $r->delete('/booking/bookings/:id', function (Request $req) {
            Db::delete('bookings', $req->id());
            return Response::noContent();
        });

        /** Сводка для дашборда: брони на сегодня и ожидающие подтверждения. */
        $r->get('/booking/dashboard', function () {
            $today = Booking::local(time())->setTime(0, 0);
            $rows = Db::all(
                "SELECT b.*, s.name AS serviceName, r.name AS resourceName FROM bookings b
                 JOIN services s ON s.id = b.serviceId JOIN resources r ON r.id = b.resourceId
                 WHERE b.startAt >= ? AND b.startAt < ? AND b.status IN ('pending', 'confirmed') ORDER BY b.startAt",
                [dbTime($today->getTimestamp()), dbTime($today->modify('+1 day')->getTimestamp())],
            );
            return [
                'today' => array_map(function ($b) {
                    $b['service'] = ['name' => $b['serviceName']];
                    $b['resource'] = ['name' => $b['resourceName']];
                    unset($b['serviceName'], $b['resourceName']);
                    return Booking::withLocal($b);
                }, $rows),
                'pendingCount' => (int) Db::value("SELECT COUNT(*) FROM bookings WHERE status = 'pending' AND startAt >= ?", [dbTime(time())]),
            ];
        });

        // ─── Ресурсы (столы, комнаты) ────────────────────────

        $r->get('/booking/resources', function () {
            $rows = castAll(Db::all('SELECT * FROM resources ORDER BY sort, id'), Booking::RESOURCE_CASTS);
            return array_map(
                fn ($res) => [...$res, 'services' => array_map(fn ($s) => array_intersect_key($s, ID_NAME), $res['services'])],
                withRelated($rows, 'resourceId', 'services', 'services x ON x.id = rs.serviceId'),
            );
        });

        $r->post('/booking/resources', function (Request $req) {
            $id = Db::insert('resources', V::parse($req->body(), resourceSchema()));
            return Response::created(cast(Db::findOrFail('resources', $id), Booking::RESOURCE_CASTS));
        });

        $r->patch('/booking/resources/:id', function (Request $req) {
            Db::update('resources', $req->id(), V::parse($req->body(), resourceSchema(), patch: true));
            return cast(Db::findOrFail('resources', $req->id()), Booking::RESOURCE_CASTS);
        });

        $r->delete('/booking/resources/:id', function (Request $req) {
            if (Db::value('SELECT COUNT(*) FROM bookings WHERE resourceId = ?', [$req->id()])) {
                throw HttpError::conflict('У ресурса есть брони — деактивируйте его (isActive: false) вместо удаления');
            }
            Db::delete('resources', $req->id());
            return Response::noContent();
        });

        // ─── Услуги ──────────────────────────────────────────

        $r->get('/booking/services', fn () => array_map(
            fn ($s) => [...$s, 'resources' => array_map(fn ($x) => array_intersect_key($x, ID_NAME), $s['resources'])],
            servicesWithResources(),
        ));

        $r->post('/booking/services', function (Request $req) {
            $data = V::parse($req->body(), serviceSchema());
            $resourceIds = $data['resourceIds'] ?? [];
            unset($data['resourceIds']);
            $id = Db::tx(function () use ($data, $resourceIds) {
                $id = Db::insert('services', $data);
                setServiceResources($id, $resourceIds);
                return $id;
            });
            return Response::created(serviceView($id));
        });

        $r->patch('/booking/services/:id', function (Request $req) {
            $id = $req->id();
            $data = V::parse($req->body(), serviceSchema(), patch: true);
            $resourceIds = $data['resourceIds'] ?? null;
            unset($data['resourceIds']);
            Db::tx(function () use ($id, $data, $resourceIds) {
                Db::update('services', $id, $data);
                if ($resourceIds !== null) setServiceResources($id, $resourceIds);
            });
            return serviceView($id);
        });

        $r->delete('/booking/services/:id', function (Request $req) {
            if (Db::value('SELECT COUNT(*) FROM bookings WHERE serviceId = ?', [$req->id()])) {
                throw HttpError::conflict('По услуге есть брони — деактивируйте её (isActive: false) вместо удаления');
            }
            Db::delete('services', $req->id());
            return Response::noContent();
        });

        // ─── График работы ───────────────────────────────────

        /** ?resourceId=1 — график ресурса; без параметра — общий график заведения. */
        $r->get('/booking/hours', fn (Request $req) => hoursOf(($req->query['resourceId'] ?? '') !== '' ? (int) $req->query['resourceId'] : null));

        /** Полная замена графика (общего или ресурса). Пустой массив для ресурса — вернуться к общему графику. */
        $r->put('/booking/hours', function (Request $req) {
            $data = V::parse($req->body(), [
                'resourceId' => V::int()->min(1)->nullable()->default(null),
                'hours' => V::list(V::obj([
                    'weekday' => V::int()->min(1)->max(7),
                    'openTime' => V::time(),
                    'closeTime' => V::time(),
                ])),
            ]);
            $resourceId = $data['resourceId'];
            return Db::tx(function () use ($resourceId, $data) {
                if ($resourceId) {
                    Db::findOrFail('resources', $resourceId);
                    Db::exec('DELETE FROM working_hours WHERE resourceId = ?', [$resourceId]);
                } else {
                    Db::exec('DELETE FROM working_hours WHERE resourceId IS NULL');
                }
                foreach ($data['hours'] as $h) Db::insert('working_hours', [...$h, 'resourceId' => $resourceId]);
                return hoursOf($resourceId);
            });
        });

        // ─── Исключения (праздники, выходные) ────────────────

        $r->get('/booking/exceptions', function (Request $req) {
            $from = is_string($req->query['from'] ?? null) ? $req->query['from'] : null;
            $rows = Db::all(
                'SELECT e.*, r.name AS resourceName FROM schedule_exceptions e LEFT JOIN resources r ON r.id = e.resourceId'
                . ($from ? ' WHERE e.date >= ?' : '') . ' ORDER BY e.date, e.id',
                $from ? [$from] : [],
            );
            return array_map(function ($e) {
                $e['resource'] = $e['resourceId'] ? ['id' => $e['resourceId'], 'name' => $e['resourceName']] : null;
                unset($e['resourceName']);
                return cast($e, ['isClosed' => 'bool']);
            }, $rows);
        });

        $r->post('/booking/exceptions', function (Request $req) {
            $data = V::parse($req->body(), [
                'resourceId' => V::int()->min(1)->nullable()->default(null),
                'date' => V::date(),
                'isClosed' => V::bool()->default(true),
                'openTime' => V::time()->nullish(),
                'closeTime' => V::time()->nullish(),
                'note' => V::str()->nullish(),
            ]);
            if (!$data['isClosed'] && (empty($data['openTime']) || empty($data['closeTime']))) {
                throw V::fail('openTime', 'Для рабочего дня укажите openTime и closeTime');
            }
            if ($data['resourceId']) Db::findOrFail('resources', $data['resourceId']);

            // Одно исключение на дату и ресурс: повторный POST перезаписывает
            $existing = Db::value(
                'SELECT id FROM schedule_exceptions WHERE date = ? AND ' . ($data['resourceId'] ? 'resourceId = ?' : 'resourceId IS NULL'),
                $data['resourceId'] ? [$data['date'], $data['resourceId']] : [$data['date']],
            );
            if ($existing) Db::update('schedule_exceptions', (int) $existing, $data);
            $id = $existing ? (int) $existing : Db::insert('schedule_exceptions', $data);
            $row = cast(Db::findOrFail('schedule_exceptions', $id), ['isClosed' => 'bool']);
            return $existing ? $row : Response::created($row);
        });

        $r->delete('/booking/exceptions/:id', function (Request $req) {
            Db::delete('schedule_exceptions', $req->id());
            return Response::noContent();
        });
    },
];
