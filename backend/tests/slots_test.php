<?php
declare(strict_types=1);

/** Тесты расчёта слотов, телефона и валидации: php backend/tests/slots_test.php */

namespace App;

require dirname(__DIR__) . '/app.php';

$failed = 0;
function test(string $name, callable $fn): void
{
    global $failed;
    try {
        $fn();
        echo "  ✔ $name\n";
    } catch (\Throwable $e) {
        $failed++;
        echo "  ✖ $name: {$e->getMessage()}\n";
    }
}
function eq(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) throw new \Exception('ожидалось ' . json_encode($expected, JSON_UNESCAPED_UNICODE) . ', получено ' . json_encode($actual, JSON_UNESCAPED_UNICODE));
}

$tz = new \DateTimeZone('Europe/Moscow');
$at = fn (string $iso) => (new \DateTimeImmutable($iso, $tz))->getTimestamp();
$hm = fn (int $ts) => (new \DateTimeImmutable("@$ts"))->setTimezone($tz)->format('H:i');
$times = fn (array $slots) => array_map(fn ($s) => $hm($s['start']), $slots);
$hours = [
    ['weekday' => 1, 'openTime' => '10:00', 'closeTime' => '14:00'], // пн
    ['weekday' => 5, 'openTime' => '20:00', 'closeTime' => '02:00'], // пт, после полуночи
];
// 2026-10-05 — понедельник, 2026-10-09 — пятница
$base = ['stepMin' => 60, 'bufferMin' => 0, 'capacity' => 1, 'notBefore' => $at('2000-01-01'), 'notAfter' => $at('2100-01-01')];
$mon = Slots::openIntervals('2026-10-05', $tz, $hours);

echo "openIntervals\n";
test('берёт часы по дню недели', function () use ($mon, $hm) {
    eq(['10:00', '14:00'], [$hm($mon[0][0]), $hm($mon[0][1])]);
});
test('переносит закрытие после полуночи на следующий день', function () use ($tz, $hours) {
    $end = Slots::openIntervals('2026-10-09', $tz, $hours)[0][1];
    eq('2026-10-10', (new \DateTimeImmutable("@$end"))->setTimezone($tz)->format('Y-m-d'));
});
test('выходной день без правил', fn () => eq([], Slots::openIntervals('2026-10-06', $tz, $hours)));
test('исключение-праздник закрывает день', fn () => eq([], Slots::openIntervals('2026-10-05', $tz, $hours, ['isClosed' => true, 'openTime' => null, 'closeTime' => null])));
test('исключение с сокращёнными часами', function () use ($tz, $hours, $hm) {
    $i = Slots::openIntervals('2026-10-05', $tz, $hours, ['isClosed' => false, 'openTime' => '12:00', 'closeTime' => '13:00']);
    eq('12:00', $hm($i[0][0]));
});

echo "generateSlots\n";
test('сетка слотов с шагом и длительностью', fn () => eq(['10:00', '11:00', '12:00', '13:00'], $times(Slots::generate($mon, [], [...$base, 'durationMin' => 60]))));
test('слот не выходит за закрытие', fn () => eq(['10:00', '11:00', '12:00'], $times(Slots::generate($mon, [], [...$base, 'durationMin' => 90]))));
test('занятые слоты исключаются с учётом буфера', function () use ($mon, $base, $at, $times) {
    $busy = [['start' => $at('2026-10-05T11:00'), 'end' => $at('2026-10-05T12:00'), 'bufferMin' => 30]];
    eq(['10:00', '13:00'], $times(Slots::generate($mon, $busy, [...$base, 'durationMin' => 60])));
});
test('capacity > 1 допускает параллельные брони', function () use ($mon, $base, $at) {
    $busy = [['start' => $at('2026-10-05T10:00'), 'end' => $at('2026-10-05T11:00'), 'bufferMin' => 0]];
    $slots = Slots::generate($mon, $busy, [...$base, 'durationMin' => 60, 'capacity' => 2]);
    eq(1, $slots[0]['free']);
    eq(4, count($slots));
});
test('учитывает минимальное время до начала', fn () => eq(['12:00', '13:00'], $times(Slots::generate($mon, [], [...$base, 'durationMin' => 60, 'notBefore' => $at('2026-10-05T11:30')]))));
test('слоты после полуночи в пятницу', fn () => eq(
    ['20:00', '21:00', '22:00', '23:00', '00:00', '01:00'],
    $times(Slots::generate(Slots::openIntervals('2026-10-09', $tz, $hours), [], [...$base, 'durationMin' => 60])),
));

echo "overlaps\n";
$b = ['start' => $at('2026-10-05T12:00'), 'end' => $at('2026-10-05T13:00'), 'bufferMin' => 0];
test('впритык — не пересечение', function () use ($b, $at) {
    eq(false, Slots::overlaps($at('2026-10-05T13:00'), $at('2026-10-05T14:00'), 0, $b));
    eq(false, Slots::overlaps($at('2026-10-05T11:00'), $at('2026-10-05T12:00'), 0, $b));
});
test('буфер новой брони мешает следующей', fn () => eq(true, Slots::overlaps($at('2026-10-05T11:00'), $at('2026-10-05T12:00'), 15, $b)));

echo "normalizePhone\n";
foreach (['+7 (999) 111-22-33', '89991112233', '79991112233', '9991112233'] as $raw) {
    test("$raw → +79991112233", fn () => eq('+79991112233', normalizePhone($raw)));
}

echo "V::parse (PATCH)\n";
$schema = ['name' => V::str(), 'sort' => V::int()->default(0), 'isActive' => V::bool()->default(true), 'tags' => V::list(V::str())->default([])];
test('не подставляет значения по умолчанию для непереданных полей', fn () => eq(['name' => 'x'], V::parse(['name' => 'x'], $schema, patch: true)));
test('проверяет переданные поля', function () use ($schema) {
    try {
        V::parse(['sort' => 'abc'], $schema, patch: true);
        throw new \Exception('ошибка не брошена');
    } catch (HttpError $e) {
        eq(400, $e->status);
    }
    eq(['isActive' => false], V::parse(['isActive' => false], $schema, patch: true));
});

echo $failed ? "\nУпало: $failed\n" : "\nВсе тесты прошли\n";
exit($failed ? 1 : 0);
