<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function read(string $key): ?array
    {
        $row = static::query()->find($key);
        if (! $row) {
            return null;
        }

        $value = json_decode((string) $row->value, true);

        return is_array($value) ? $value : null;
    }

    public static function write(string $key, array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );
    }
}
