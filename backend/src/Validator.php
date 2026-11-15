<?php
declare(strict_types=1);

namespace App;

/**
 * Валидация входных данных (замена zod). Схема — массив "поле → V::...":
 *
 *   V::parse($req->body(), ['name' => V::str()->trim()->min(1, 'Укажите имя'), 'sort' => V::int()->default(0)]);
 *
 * Ошибки собираются по всем полям и уходят клиентом 400 с details: [{path, message}].
 * $patch = true — для PATCH: все поля необязательны и без значений по умолчанию,
 * чтобы PATCH { price } не сбрасывал остальные поля.
 */
final class V
{
    public static function str(): Field { return new Field('string'); }
    public static function int(): Field { return new Field('int'); }
    public static function num(): Field { return new Field('number'); }
    public static function bool(): Field { return new Field('bool'); }
    public static function email(string $msg = 'Некорректный email'): Field { return (new Field('email'))->msg($msg); }
    public static function any(): Field { return new Field('any'); }

    /** Телефон приводится к +79991234567 (см. normalizePhone). */
    public static function phone(): Field { return new Field('phone'); }

    public static function enum(array $values): Field
    {
        $f = new Field('enum');
        $f->values = $values;
        return $f;
    }

    /** Только true: согласие на обработку ПД. */
    public static function accepted(string $msg): Field { return (new Field('true'))->msg($msg); }

    public static function list(Field $of): Field
    {
        $f = new Field('array');
        $f->of = $of;
        return $f;
    }

    /** Объект с известными полями; $loose — пропускать и неизвестные поля. */
    public static function obj(array $shape, bool $loose = false): Field
    {
        $f = new Field('object');
        $f->shape = $shape;
        $f->loose = $loose;
        return $f;
    }

    /** Объект с произвольными ключами и значениями одного вида. */
    public static function record(Field $of): Field
    {
        $f = new Field('record');
        $f->of = $of;
        return $f;
    }

    public static function slug(): Field
    {
        return self::str()->trim()->min(1)->max(100)->regex('/^[a-z0-9-]+$/', 'Только латиница в нижнем регистре, цифры и дефис');
    }

    public static function time(): Field
    {
        return self::str()->regex('/^([01]\d|2[0-3]):[0-5]\d$/', 'Формат времени HH:mm');
    }

    public static function date(): Field
    {
        return self::str()->regex('/^\d{4}-\d{2}-\d{2}$/', 'Формат даты YYYY-MM-DD');
    }

    public static function id(): Field
    {
        return self::int()->coerce()->min(1);
    }

    /** Проверить данные по схеме или бросить 400 «Ошибка валидации». */
    public static function parse(mixed $data, array $shape, bool $patch = false, bool $loose = false): array
    {
        $errors = [];
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw HttpError::badRequest('Ошибка валидации', [['path' => '', 'message' => 'Ожидался объект']]);
        }
        $out = self::object($data, $shape, '', $errors, $patch, $loose);
        if ($errors) throw HttpError::badRequest('Ошибка валидации', $errors);
        return $out;
    }

    /** Список объектов (например, порядок сортировки [{id, sort}]). */
    public static function parseList(mixed $data, Field $of, int $min = 0): array
    {
        $errors = [];
        $field = self::list($of)->min($min);
        $out = $field->parse($data, '', $errors);
        if ($errors) throw HttpError::badRequest('Ошибка валидации', $errors);
        return $out;
    }

    public static function fail(string $path, string $message): HttpError
    {
        return HttpError::badRequest('Ошибка валидации', [['path' => $path, 'message' => $message]]);
    }

    /** @internal */
    public static function object(array $data, array $shape, string $prefix, array &$errors, bool $patch = false, bool $loose = false): array
    {
        $out = $loose ? array_diff_key($data, $shape) : [];
        foreach ($shape as $key => $field) {
            $path = $prefix === '' ? $key : "$prefix.$key";
            $value = $data[$key] ?? null;
            // Пустая строка в необязательном email/телефоне — «не указано»
            if ($value === '' && in_array($field->type, ['email', 'phone'], true) && ($field->optional || $field->nullable)) {
                $value = null;
            }
            if ($value === null) {
                if (array_key_exists($key, $data) && $field->nullable) $out[$key] = null;
                elseif ($patch) continue;
                elseif ($field->hasDefault) $out[$key] = $field->default;
                elseif (!$field->optional && !$field->nullable) $errors[] = ['path' => $path, 'message' => $field->message ?? 'Обязательное поле'];
                continue;
            }
            $before = count($errors);
            $parsed = $field->parse($value, $path, $errors);
            if (count($errors) === $before) $out[$key] = $parsed;
        }
        return $out;
    }
}

final class Field
{
    public bool $optional = false;
    public bool $nullable = false;
    public bool $hasDefault = false;
    public mixed $default = null;
    public ?string $message = null;
    public array $values = [];
    public ?Field $of = null;
    public array $shape = [];
    public bool $loose = false;
    private bool $coerce = false;
    private bool $trim = false;
    private bool $lower = false;
    private ?float $min = null;
    private ?string $minMsg = null;
    private ?float $max = null;
    private ?string $pattern = null;
    private string $patternMsg = '';

    public function __construct(public readonly string $type)
    {
    }

    public function optional(): self { $this->optional = true; return $this; }
    public function nullable(): self { $this->nullable = true; return $this; }
    /** null или отсутствует. */
    public function nullish(): self { $this->optional = $this->nullable = true; return $this; }
    public function default(mixed $value): self { $this->hasDefault = true; $this->default = $value; return $this; }
    /** Строки из query (?page=2) превращаются в числа / bool. */
    public function coerce(): self { $this->coerce = true; return $this; }
    public function trim(): self { $this->trim = true; return $this; }
    public function lower(): self { $this->lower = true; return $this; }
    public function msg(string $message): self { $this->message = $message; return $this; }
    public function max(float $n): self { $this->max = $n; return $this; }

    public function min(float $n, ?string $msg = null): self
    {
        $this->min = $n;
        $this->minMsg = $msg;
        return $this;
    }

    public function regex(string $pattern, string $msg): self
    {
        $this->pattern = $pattern;
        $this->patternMsg = $msg;
        return $this;
    }

    public function parse(mixed $v, string $path, array &$errors): mixed
    {
        $fail = function (string $msg) use ($path, &$errors) {
            $errors[] = ['path' => $path, 'message' => $this->message ?? $msg];
            return null;
        };

        switch ($this->type) {
            case 'any':
                return $v;

            case 'true':
                return $v === true ? true : $fail('Ожидалось true');

            case 'bool':
                if ($this->coerce && is_string($v) && in_array($v, ['true', 'false', '1', '0'], true)) $v = $v === 'true' || $v === '1';
                return is_bool($v) ? $v : $fail('Ожидалось true или false');

            case 'int':
            case 'number':
                if ($this->coerce && is_string($v) && is_numeric(trim($v))) $v = +trim($v);
                if (!is_int($v) && !is_float($v)) return $fail('Ожидалось число');
                if ($this->type === 'int') {
                    if (is_float($v) && floor($v) !== $v) return $fail('Ожидалось целое число');
                    $v = (int) $v;
                }
                if ($this->min !== null && $v < $this->min) return $fail($this->minMsg ?? "Значение должно быть не меньше {$this->fmt($this->min)}");
                if ($this->max !== null && $v > $this->max) return $fail("Значение должно быть не больше {$this->fmt($this->max)}");
                return $v;

            case 'enum':
                if (!is_string($v) || !in_array($v, $this->values, true)) {
                    return $fail('Недопустимое значение: ожидалось одно из ' . implode(', ', $this->values));
                }
                return $v;

            case 'string':
            case 'email':
            case 'phone':
                if ($this->coerce && (is_int($v) || is_float($v))) $v = (string) $v;
                if (!is_string($v)) return $fail('Ожидалась строка');
                if ($this->trim || $this->type !== 'string') $v = trim($v);
                if ($this->lower || $this->type === 'email') $v = strtolower($v);
                if ($this->type === 'phone') {
                    $v = normalizePhone($v);
                    if (!preg_match('/^\+\d{10,15}$/', $v)) return $fail('Некорректный телефон');
                }
                if ($this->type === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) return $fail('Некорректный email');
                $len = strLength($v);
                if ($this->min !== null && $len < $this->min) {
                    return $fail($this->minMsg ?? ($this->min <= 1 ? 'Обязательное поле' : "Минимум {$this->fmt($this->min)} символов"));
                }
                if ($this->max !== null && $len > $this->max) return $fail("Максимум {$this->fmt($this->max)} символов");
                if ($this->pattern !== null && !preg_match($this->pattern, $v)) return $fail($this->patternMsg);
                return $v;

            case 'array':
                if (!is_array($v) || !array_is_list($v)) return $fail('Ожидался массив');
                if ($this->min !== null && count($v) < $this->min) return $fail($this->minMsg ?? 'Массив не должен быть пустым');
                $out = [];
                foreach ($v as $i => $item) {
                    if ($this->of && $this->of->type === 'object') {
                        if (!is_array($item) || ($item !== [] && array_is_list($item))) {
                            $errors[] = ['path' => ltrim("$path.$i", '.'), 'message' => 'Ожидался объект'];
                            continue;
                        }
                        $out[] = V::object($item, $this->of->shape, ltrim("$path.$i", '.'), $errors, false, $this->of->loose);
                    } else {
                        $out[] = $this->of ? $this->of->parse($item, ltrim("$path.$i", '.'), $errors) : $item;
                    }
                }
                return $out;

            case 'object':
            case 'record':
                if (!is_array($v) || ($v !== [] && array_is_list($v))) return $fail('Ожидался объект');
                if ($this->type === 'object') {
                    $out = V::object($v, $this->shape, $path, $errors, false, $this->loose);
                } else {
                    $out = [];
                    foreach ($v as $k => $item) $out[$k] = $this->of ? $this->of->parse($item, "$path.$k", $errors) : $item;
                }
                return $out ?: new \stdClass(); // пустой объект остаётся {} в JSON

            default:
                throw new \LogicException("Неизвестный тип поля {$this->type}");
        }
    }

    private function fmt(float $n): string
    {
        return (string) (floor($n) === $n ? (int) $n : $n);
    }
}

/**
 * Телефон приводится к виду +79991234567: 8XXXXXXXXXX и XXXXXXXXXX считаются российскими.
 * Единый формат нужен, чтобы клиент нашёл свою бронь, как бы ни ввёл номер.
 */
function normalizePhone(string $raw): string
{
    $digits = preg_replace('/\D/', '', $raw) ?? '';
    if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
    if (strlen($digits) === 10 && $digits[0] === '9') $digits = "7$digits";
    return "+$digits";
}

/** Длина строки в символах (mbstring есть не везде). */
function strLength(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./su', $s);
}
