<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Чистые функции расчёта слотов — без БД, покрыты тестами (backend/tests/slots_test.php).
 * Время внутри — Unix-секунды, часовой пояс нужен только для разбора дат и графика.
 *
 * Семантика «рабочего дня»: слоты на дату D строятся из часов работы дня D,
 * даже если они уходят за полночь (пт 18:00–02:00 → слот 01:00 относится к пятнице).
 */
final class Slots
{
    /**
     * Интервалы работы на дату. Исключение (праздник) важнее графика.
     *
     * @param list<array{weekday:int, openTime:string, closeTime:string}> $hours
     * @param array{isClosed:bool|int, openTime:?string, closeTime:?string}|null $exception
     * @return list<array{int, int}> [начало, конец]
     */
    public static function openIntervals(string $date, DateTimeZone $tz, array $hours, ?array $exception = null): array
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if (!$day || $day->format('Y-m-d') !== $date) return [];

        if ($exception) {
            if ($exception['isClosed'] || !$exception['openTime'] || !$exception['closeTime']) return [];
            $ranges = [$exception];
        } else {
            $weekday = (int) $day->format('N'); // 1 = пн … 7 = вс
            $ranges = array_filter($hours, fn ($h) => (int) $h['weekday'] === $weekday);
        }

        $intervals = [];
        foreach ($ranges as $r) {
            $start = self::at($day, $r['openTime']);
            $end = self::at($day, $r['closeTime']);
            if ($end <= $start) $end = self::at($day->modify('+1 day'), $r['closeTime']); // работа после полуночи
            $intervals[] = [$start, $end];
        }
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        return $intervals;
    }

    /**
     * Пересекается ли новая бронь [start, end) с существующей с учётом буферов обеих.
     *
     * @param array{start:int, end:int, bufferMin:int} $b
     */
    public static function overlaps(int $start, int $end, int $bufferMin, array $b): bool
    {
        return $b['start'] < $end + $bufferMin * 60 && $b['end'] + $b['bufferMin'] * 60 > $start;
    }

    /**
     * @param list<array{int, int}> $intervals
     * @param list<array{start:int, end:int, bufferMin:int}> $busy
     * @param array{durationMin:int, stepMin:int, bufferMin:int, capacity:int, notBefore:int, notAfter:int} $o
     * @return list<array{start:int, end:int, free:int}>
     */
    public static function generate(array $intervals, array $busy, array $o): array
    {
        $slots = [];
        $seen = [];
        $step = max(1, $o['stepMin']) * 60;

        foreach ($intervals as [$open, $close]) {
            for ($start = $open; ; $start += $step) {
                $end = $start + $o['durationMin'] * 60;
                if ($end > $close) break;
                if ($start < $o['notBefore'] || $start > $o['notAfter'] || isset($seen[$start])) continue;

                $taken = count(array_filter($busy, fn ($b) => self::overlaps($start, $end, $o['bufferMin'], $b)));
                $free = $o['capacity'] - $taken;
                if ($free > 0) {
                    $slots[] = ['start' => $start, 'end' => $end, 'free' => $free];
                    $seen[$start] = true;
                }
            }
        }
        return $slots;
    }

    private static function at(DateTimeImmutable $day, string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm) + [0, 0]);
        return $day->setTime($h, $m)->getTimestamp();
    }
}
