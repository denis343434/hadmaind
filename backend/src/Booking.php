<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;

/** Р‘СЂРѕРЅРёСЂРѕРІР°РЅРёРµ: РґРѕСЃС‚СѓРїРЅРѕСЃС‚СЊ, СЃРѕР·РґР°РЅРёРµ, РѕС‚РјРµРЅР°, РїСЂРµРґСЃС‚Р°РІР»РµРЅРёРµ Р±СЂРѕРЅРё. */
final class Booking
{
    public const STATUSES = ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'];
    /** РЎС‚Р°С‚СѓСЃС‹, РєРѕС‚РѕСЂС‹Рµ Р·Р°РЅРёРјР°СЋС‚ РІСЂРµРјСЏ РІ СЂР°СЃРїРёСЃР°РЅРёРё. */
    public const ACTIVE = ['pending', 'confirmed'];
    public const TITLES = [
        'pending' => 'РћР¶РёРґР°РµС‚ РїРѕРґС‚РІРµСЂР¶РґРµРЅРёСЏ',
        'confirmed' => 'РџРѕРґС‚РІРµСЂР¶РґРµРЅР°',
        'cancelled' => 'РћС‚РјРµРЅРµРЅР°',
        'completed' => 'Р—Р°РІРµСЂС€РµРЅР°',
        'no_show' => 'РќРµ РїСЂРёС€Р»Рё',
    ];

    public const CASTS = ['startAt' => 'date', 'endAt' => 'date', 'createdAt' => 'date', 'updatedAt' => 'date'];
    public const SERVICE_CASTS = ['pricePerGuest' => 'bool', 'isActive' => 'bool'];
    public const RESOURCE_CASTS = ['isActive' => 'bool'];

    public static function tz(): DateTimeZone
    {
        static $tz;
        return $tz ??= new DateTimeZone(env('TIMEZONE'));
    }

    /** Unix-РІСЂРµРјСЏ в†’ ISO СЃРѕ СЃРјРµС‰РµРЅРёРµРј Р·Р°РІРµРґРµРЅРёСЏ: "2026-10-10T18:00:00.000+03:00". */
    public static function iso(int $ts): string
    {
        return self::local($ts)->format('Y-m-d\TH:i:s.vP');
    }

    public static function local(int $ts): DateTimeImmutable
    {
        return (new DateTimeImmutable("@$ts"))->setTimezone(self::tz());
    }

    /** РќР°С‡Р°Р»Рѕ РґРЅСЏ "YYYY-MM-DD" РІ С‡Р°СЃРѕРІРѕРј РїРѕСЏСЃРµ Р·Р°РІРµРґРµРЅРёСЏ. */
    public static function dayStart(string $date): DateTimeImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, self::tz());
        if (!$day || $day->format('Y-m-d') !== $date) throw HttpError::badRequest('РќРµРєРѕСЂСЂРµРєС‚РЅР°СЏ РґР°С‚Р°');
        return $day;
    }

    /** Р’СЂРµРјСЏ РѕС‚ РєР»РёРµРЅС‚Р°: "2026-10-10T18:00" (РІСЂРµРјСЏ Р·Р°РІРµРґРµРЅРёСЏ) РёР»Рё ISO СЃРѕ СЃРјРµС‰РµРЅРёРµРј. */
    public static function parseLocal(string $value): int
    {
        $re = '/^(\d{4}-\d{2}-\d{2})(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?)?(?:Z|[+-]\d{2}:?\d{2})?$/';
        if (preg_match($re, trim($value), $m)) {
            try {
                $dt = new DateTimeImmutable(trim($value), self::tz());
                if ($dt->format('Y-m-d') === $m[1]) return $dt->getTimestamp();
            } catch (\Exception) {
            }
        }
        throw HttpError::badRequest('РќРµРєРѕСЂСЂРµРєС‚РЅР°СЏ РґР°С‚Р°/РІСЂРµРјСЏ');
    }

    public static function price(array $service, int $guests): int
    {
        return $service['pricePerGuest'] ? $service['price'] * $guests : $service['price'];
    }

    /** Р—Р°РЅСЏС‚РѕСЃС‚СЊ СЂРµСЃСѓСЂСЃРѕРІ РІ РґРёР°РїР°Р·РѕРЅРµ: resourceId в†’ [{start, end, bufferMin}]. */
    private static function loadBusy(array $resourceIds, int $from, int $to, ?int $excludeId = null): array
    {
        if (!$resourceIds) return [];
        $sql = 'SELECT b.resourceId, b.startAt, b.endAt, s.bufferMin FROM bookings b JOIN services s ON s.id = b.serviceId
                WHERE b.resourceId IN (' . Db::marks($resourceIds) . ") AND b.status IN ('pending', 'confirmed')
                AND b.startAt < ? AND b.endAt > ?";
        $params = [...$resourceIds, dbTime($to), dbTime($from)];
        if ($excludeId) {
            $sql .= ' AND b.id <> ?';
            $params[] = $excludeId;
        }
        $busy = [];
        foreach (Db::all($sql, $params) as $b) {
            $busy[$b['resourceId']][] = ['start' => fromDbTime($b['startAt']), 'end' => fromDbTime($b['endAt']), 'bufferMin' => $b['bufferMin']];
        }
        return $busy;
    }

    /**
     * РЎРІРѕР±РѕРґРЅС‹Рµ СЃР»РѕС‚С‹ РЅР° РґР°С‚Сѓ РїРѕ РєР°Р¶РґРѕРјСѓ РїРѕРґС…РѕРґСЏС‰РµРјСѓ СЂРµСЃСѓСЂСЃСѓ.
     *
     * @param array{serviceId:int, date:string, guests:int, resourceId?:int} $q
     * @return array{service: array, resources: list<array{resource: array, slots: list<array>}>}
     */
    public static function availability(array $q): array
    {
        $service = cast(Db::one('SELECT * FROM services WHERE id = ? AND isActive = 1', [$q['serviceId']]), self::SERVICE_CASTS)
            ?? throw HttpError::notFound('РЈСЃР»СѓРіР° РЅРµ РЅР°Р№РґРµРЅР°');
        if ($q['guests'] < $service['minGuests']) throw HttpError::badRequest("РњРёРЅРёРјСѓРј РіРѕСЃС‚РµР№: {$service['minGuests']}");
        if ($service['maxGuests'] && $q['guests'] > $service['maxGuests']) throw HttpError::badRequest("РњР°РєСЃРёРјСѓРј РіРѕСЃС‚РµР№: {$service['maxGuests']}");

        $sql = 'SELECT r.* FROM resources r JOIN resource_service rs ON rs.resourceId = r.id
                WHERE rs.serviceId = ? AND r.isActive = 1';
        $params = [$service['id']];
        if (!empty($q['resourceId'])) {
            $sql .= ' AND r.id = ?';
            $params[] = $q['resourceId'];
        }
        $candidates = castAll(Db::all("$sql ORDER BY r.sort, r.id", $params), self::RESOURCE_CASTS);

        if (!empty($q['resourceId']) && !$candidates) throw HttpError::notFound('Р РµСЃСѓСЂСЃ РЅРµРґРѕСЃС‚СѓРїРµРЅ РґР»СЏ СЌС‚РѕР№ СѓСЃР»СѓРіРё');
        $resources = array_values(array_filter($candidates, fn ($r) => !$r['maxGuests'] || $q['guests'] <= $r['maxGuests']));
        if (!empty($q['resourceId']) && !$resources) throw HttpError::badRequest("Р’РјРµСЃС‚РёРјРѕСЃС‚СЊ: РґРѕ {$candidates[0]['maxGuests']} РіРѕСЃС‚РµР№");
        if (!$resources) return ['service' => $service, 'resources' => []];

        $ids = array_column($resources, 'id');
        $scope = '(resourceId IS NULL OR resourceId IN (' . Db::marks($ids) . '))';
        $day = self::dayStart($q['date']);
        $hours = Db::all("SELECT * FROM working_hours WHERE $scope", $ids);
        $exceptions = castAll(Db::all("SELECT * FROM schedule_exceptions WHERE date = ? AND $scope", [$q['date'], ...$ids]), ['isClosed' => 'bool']);
        // +2 РґРЅСЏ вЂ” СЃ Р·Р°РїР°СЃРѕРј РїРѕРґ СЂР°Р±РѕС‚Сѓ РїРѕСЃР»Рµ РїРѕР»СѓРЅРѕС‡Рё Рё Р±СѓС„РµСЂС‹
        $busy = self::loadBusy($ids, $day->modify('-1 day')->getTimestamp(), $day->modify('+2 days')->getTimestamp());

        $now = time();
        $options = [
            'durationMin' => $service['durationMin'],
            'stepMin' => $service['slotStepMin'] ?? env('BOOKING_SLOT_STEP_MIN'),
            'bufferMin' => $service['bufferMin'],
            'notBefore' => $now + env('BOOKING_MIN_LEAD_MIN') * 60,
            'notAfter' => $now + env('BOOKING_MAX_DAYS_AHEAD') * 86400,
        ];

        $result = [];
        foreach ($resources as $r) {
            // РЎРѕР±СЃС‚РІРµРЅРЅС‹Р№ РіСЂР°С„РёРє СЂРµСЃСѓСЂСЃР° РїРµСЂРµРєСЂС‹РІР°РµС‚ РѕР±С‰РёР№
            $own = array_values(array_filter($hours, fn ($h) => $h['resourceId'] === $r['id']));
            $rules = $own ?: array_values(array_filter($hours, fn ($h) => $h['resourceId'] === null));
            $exception = null;
            foreach ([$r['id'], null] as $owner) {
                foreach ($exceptions as $e) if ($exception === null && $e['resourceId'] === $owner) $exception = $e;
            }
            $intervals = Slots::openIntervals($q['date'], self::tz(), $rules, $exception);
            $slots = Slots::generate($intervals, $busy[$r['id']] ?? [], [...$options, 'capacity' => $r['capacity']]);
            $result[] = ['resource' => $r, 'slots' => $slots];
        }
        return ['service' => $service, 'resources' => $result];
    }

    public static function assertNoConflict(int $resourceId, int $start, int $end, int $bufferMin, int $capacity, ?int $excludeId = null): void
    {
        $busy = self::loadBusy([$resourceId], $start - 86400, $end + 86400, $excludeId)[$resourceId] ?? [];
        $taken = count(array_filter($busy, fn ($b) => Slots::overlaps($start, $end, $bufferMin, $b)));
        if ($taken >= $capacity) throw HttpError::conflict('Р­С‚Рѕ РІСЂРµРјСЏ СѓР¶Рµ Р·Р°РЅСЏС‚Рѕ');
    }

    /**
     * Р‘СЂРѕРЅРё РјРµРЅСЏСЋС‚СЃСЏ РїРѕРґ РёРјРµРЅРѕРІР°РЅРЅРѕР№ Р±Р»РѕРєРёСЂРѕРІРєРѕР№ MySQL + С‚СЂР°РЅР·Р°РєС†РёРµР№:
     * РґРІР° РєР»РёРµРЅС‚Р° РЅРµ Р·Р°Р№РјСѓС‚ РѕРґРЅРѕ РІСЂРµРјСЏ, РґР°Р¶Рµ РµСЃР»Рё РЅР°Р¶РјСѓС‚ В«Р—Р°Р±СЂРѕРЅРёСЂРѕРІР°С‚СЊВ» РѕРґРЅРѕРІСЂРµРјРµРЅРЅРѕ.
     */
    public static function locked(callable $fn): mixed
    {
        if ((int) Db::value("SELECT GET_LOCK('handmade_booking', 10)") !== 1) {
            throw HttpError::conflict('РљРѕРЅС„Р»РёРєС‚ РїР°СЂР°Р»Р»РµР»СЊРЅС‹С… Р·Р°РїСЂРѕСЃРѕРІ, РїРѕРІС‚РѕСЂРёС‚Рµ РїРѕРїС‹С‚РєСѓ');
        }
        try {
            return Db::tx($fn);
        } finally {
            Db::value("SELECT RELEASE_LOCK('handmade_booking')");
        }
    }

    // Р‘РµР· РїРѕС…РѕР¶РёС… СЃРёРјРІРѕР»РѕРІ (0/O, 1/I/L) вЂ” РєРѕРґ СѓРґРѕР±РЅРѕ РґРёРєС‚РѕРІР°С‚СЊ РїРѕ С‚РµР»РµС„РѕРЅСѓ.
    public static function code(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            if (!Db::value('SELECT 1 FROM bookings WHERE code = ?', [$code])) return $code;
        }
        throw new \RuntimeException('РќРµ СѓРґР°Р»РѕСЃСЊ СЃРіРµРЅРµСЂРёСЂРѕРІР°С‚СЊ РєРѕРґ Р±СЂРѕРЅРё');
    }

    /** РЎРѕР·РґР°РЅРёРµ Р±СЂРѕРЅРё РєР»РёРµРЅС‚РѕРј: СЃР»РѕС‚ РїРµСЂРµРїСЂРѕРІРµСЂСЏРµС‚СЃСЏ РїРѕРґ Р±Р»РѕРєРёСЂРѕРІРєРѕР№. */
    public static function create(array $input): array
    {
        $start = self::parseLocal($input['startAt']);

        $id = self::locked(function () use ($input, $start) {
            // РЎР»РѕС‚ РјРѕР¶РµС‚ РїСЂРёРЅР°РґР»РµР¶Р°С‚СЊ В«СЂР°Р±РѕС‡РµРјСѓ РґРЅСЋВ» РїСЂРµРґС‹РґСѓС‰РµР№ РґР°С‚С‹ (СЂР°Р±РѕС‚Р° РїРѕСЃР»Рµ РїРѕР»СѓРЅРѕС‡Рё)
            $date = self::local($start)->format('Y-m-d');
            foreach ([$date, self::local($start)->modify('-1 day')->format('Y-m-d')] as $day) {
                $a = self::availability([
                    'serviceId' => $input['serviceId'],
                    'resourceId' => $input['resourceId'] ?? null,
                    'guests' => $input['guests'],
                    'date' => $day,
                ]);
                // Р‘РµР· СЏРІРЅРѕРіРѕ resourceId Р±РµСЂС‘Рј РїРµСЂРІС‹Р№ СЃРІРѕР±РѕРґРЅС‹Р№ СЂРµСЃСѓСЂСЃ
                foreach ($a['resources'] as $r) {
                    if (!in_array($start, array_column($r['slots'], 'start'), true)) continue;
                    return Db::insert('bookings', [
                        'code' => self::code(),
                        'serviceId' => $a['service']['id'],
                        'resourceId' => $r['resource']['id'],
                        'startAt' => dbTime($start),
                        'endAt' => dbTime($start + $a['service']['durationMin'] * 60),
                        'guests' => $input['guests'],
                        'customerName' => $input['customerName'],
                        'customerPhone' => $input['customerPhone'],
                        'customerEmail' => $input['customerEmail'] ?? null,
                        'comment' => $input['comment'] ?? null,
                        'totalPrice' => self::price($a['service'], $input['guests']),
                        'status' => env('BOOKING_AUTO_CONFIRM') ? 'confirmed' : 'pending',
                    ]);
                }
            }
            throw HttpError::conflict('Р’С‹Р±СЂР°РЅРЅРѕРµ РІСЂРµРјСЏ РЅРµРґРѕСЃС‚СѓРїРЅРѕ, РІС‹Р±РµСЂРёС‚Рµ РґСЂСѓРіРѕРµ');
        });

        $booking = self::find($id);
        Notify::admin("РќРѕРІР°СЏ Р±СЂРѕРЅСЊ {$booking['code']}", self::describe($booking));
        self::notifyCustomer($booking, $booking['status'] === 'confirmed' ? 'Р‘СЂРѕРЅСЊ РїРѕРґС‚РІРµСЂР¶РґРµРЅР°' : 'Р‘СЂРѕРЅСЊ РїСЂРёРЅСЏС‚Р°');
        return $booking;
    }

    /** РћС‚РјРµРЅР° РєР»РёРµРЅС‚РѕРј РїРѕ РєРѕРґСѓ + С‚РµР»РµС„РѕРЅСѓ. */
    public static function cancelByCustomer(string $code, string $phone): array
    {
        $booking = self::findByCodeAndPhone($code, $phone);
        if (!in_array($booking['status'], self::ACTIVE, true)) throw HttpError::badRequest('Р‘СЂРѕРЅСЊ СѓР¶Рµ РЅРµР°РєС‚РёРІРЅР°');

        $hoursLeft = (fromDbTime($booking['startAt']) - time()) / 3600;
        $minHours = env('BOOKING_CANCEL_MIN_HOURS');
        if ($hoursLeft < $minHours) {
            throw HttpError::badRequest("РћС‚РјРµРЅР° РѕРЅР»Р°Р№РЅ РІРѕР·РјРѕР¶РЅР° РЅРµ РїРѕР·РґРЅРµРµ С‡РµРј Р·Р° $minHours С‡. РџРѕР·РІРѕРЅРёС‚Рµ РЅР°Рј, РїРѕР¶Р°Р»СѓР№СЃС‚Р°.");
        }
        Db::update('bookings', $booking['id'], ['status' => 'cancelled']);
        $updated = self::find($booking['id']);
        Notify::admin("РљР»РёРµРЅС‚ РѕС‚РјРµРЅРёР» Р±СЂРѕРЅСЊ {$updated['code']}", self::describe($updated));
        return $updated;
    }

    public static function findByCodeAndPhone(string $code, string $phone): array
    {
        $row = Db::one('SELECT id, customerPhone FROM bookings WHERE code = ?', [strtoupper($code)]);
        // РћРґРёРЅР°РєРѕРІС‹Р№ РѕС‚РІРµС‚, С‡С‚РѕР±С‹ РЅРµР»СЊР·СЏ Р±С‹Р»Рѕ РїРµСЂРµР±РѕСЂРѕРј СѓР·РЅР°С‚СЊ СЃСѓС‰РµСЃС‚РІСѓСЋС‰РёРµ РєРѕРґС‹
        if (!$row || $row['customerPhone'] !== $phone) throw HttpError::notFound('Р‘СЂРѕРЅСЊ РЅРµ РЅР°Р№РґРµРЅР°');
        return self::find($row['id']);
    }

    /** Р‘СЂРѕРЅСЊ СЃ СѓСЃР»СѓРіРѕР№ Рё СЂРµСЃСѓСЂСЃРѕРј (СЃС‹СЂС‹Рµ СЃС‚СЂРѕРєРё Р±Р°Р·С‹: РІСЂРµРјСЏ вЂ” UTC DATETIME). */
    public static function find(int $id): array
    {
        $b = Db::findOrFail('bookings', $id);
        $b['service'] = cast(Db::one('SELECT * FROM services WHERE id = ?', [$b['serviceId']]), self::SERVICE_CASTS);
        $b['resource'] = cast(Db::one('SELECT * FROM resources WHERE id = ?', [$b['resourceId']]), self::RESOURCE_CASTS);
        return $b;
    }

    /** Р”Р»СЏ Р°РґРјРёРЅРєРё: ISO-РґР°С‚С‹ + startLocal/endLocal РІ С‡Р°СЃРѕРІРѕРј РїРѕСЏСЃРµ Р·Р°РІРµРґРµРЅРёСЏ. */
    public static function withLocal(array $b): array
    {
        $b['startLocal'] = self::iso(fromDbTime($b['startAt']));
        $b['endLocal'] = self::iso(fromDbTime($b['endAt']));
        return cast($b, self::CASTS);
    }

    /** РџСѓР±Р»РёС‡РЅРѕРµ РїСЂРµРґСЃС‚Р°РІР»РµРЅРёРµ Р±СЂРѕРЅРё вЂ” Р±РµР· РІРЅСѓС‚СЂРµРЅРЅРёС… РїРѕР»РµР№. */
    public static function publicView(array $b): array
    {
        return [
            'code' => $b['code'],
            'status' => $b['status'],
            'startAt' => self::iso(fromDbTime($b['startAt'])),
            'endAt' => self::iso(fromDbTime($b['endAt'])),
            'guests' => $b['guests'],
            'totalPrice' => $b['totalPrice'],
            'service' => ['id' => $b['serviceId'], 'name' => $b['service']['name']],
            'resource' => ['id' => $b['resourceId'], 'name' => $b['resource']['name']],
            'customerName' => $b['customerName'],
        ];
    }

    public static function describe(array $b): array
    {
        $start = self::local(fromDbTime($b['startAt']));
        $end = self::local(fromDbTime($b['endAt']));
        $months = ['СЏРЅРІР°СЂСЏ', 'С„РµРІСЂР°Р»СЏ', 'РјР°СЂС‚Р°', 'Р°РїСЂРµР»СЏ', 'РјР°СЏ', 'РёСЋРЅСЏ', 'РёСЋР»СЏ', 'Р°РІРіСѓСЃС‚Р°', 'СЃРµРЅС‚СЏР±СЂСЏ', 'РѕРєС‚СЏР±СЂСЏ', 'РЅРѕСЏР±СЂСЏ', 'РґРµРєР°Р±СЂСЏ'];
        $days = ['РїРЅ', 'РІС‚', 'СЃСЂ', 'С‡С‚', 'РїС‚', 'СЃР±', 'РІСЃ'];
        return [
            'РљРѕРґ' => $b['code'],
            'РЈСЃР»СѓРіР°' => $b['service']['name'],
            'Р“РґРµ' => $b['resource']['name'],
            'Р”Р°С‚Р°' => $start->format('j') . ' ' . $months[$start->format('n') - 1] . ' ' . $start->format('Y') . ', ' . $days[$start->format('N') - 1],
            'Р’СЂРµРјСЏ' => $start->format('H:i') . 'вЂ“' . $end->format('H:i'),
            'Р“РѕСЃС‚РµР№' => $b['guests'],
            'РЎСѓРјРјР°' => formatRub($b['totalPrice']),
            'РРјСЏ' => $b['customerName'],
            'РўРµР»РµС„РѕРЅ' => $b['customerPhone'],
            'Email' => $b['customerEmail'],
            'РљРѕРјРјРµРЅС‚Р°СЂРёР№' => $b['comment'],
            'РЎС‚Р°С‚СѓСЃ' => self::TITLES[$b['status']] ?? $b['status'],
        ];
    }

    public static function notifyCustomer(array $b, string $subject): void
    {
        if (!$b['customerEmail']) return;
        $fields = array_diff_key(self::describe($b), array_flip(['РўРµР»РµС„РѕРЅ', 'Email', 'РљРѕРјРјРµРЅС‚Р°СЂРёР№']));
        Notify::customer($b['customerEmail'], $subject, $fields);
    }
}

/** Р¦РµРЅС‹ С…СЂР°РЅСЏС‚СЃСЏ РІ РєРѕРїРµР№РєР°С…: 39000 в†’ "390 в‚Ѕ". */
function formatRub(int $kopecks): string
{
    $decimals = $kopecks % 100 === 0 ? 0 : 2;
    return number_format($kopecks / 100, $decimals, ',', "\u{00A0}") . "\u{00A0}в‚Ѕ";
}
